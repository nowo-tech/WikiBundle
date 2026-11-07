<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\RetryableException;
use JsonException;
use LogicException;
use Nowo\WikiBundle\Controller\WikiCollabController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

use function base64_decode;
use function count;
use function function_exists;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function rtrim;
use function sprintf;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;
use const PREG_SPLIT_NO_EMPTY;

/**
 * Server side of the Milkdown collaboration protocol on Mercure (1.13, FR-076; Milkdown ≥ 1.2 for the state log).
 *
 * The caller has already checked the edit permission ({@see WikiCollabController} does
 * it on every request; a host that wires its own endpoints must do the same). This service rate-limits every request
 * per user and page, validates the payloads (JSON, client id, strict base64, sizes), keeps the Yjs log through
 * {@see WikiCollabStateStoreInterface} within an entry and a byte budget, and relays messages through
 * {@see WikiCollabPublisherInterface}. It never decodes Yjs and never reads the document: the client that saves
 * submits the Markdown.
 *
 * - `POST token` (origin-checked) authorizes the subscription, drops a stale log and claims the seeding of an empty
 *   log for the requesting client (user + the tab's Yjs client id, header `X-Nowo-Collab-Client`); `GET state` only
 *   reads (CW-I3): it answers `seed: true` to that client while the claim is fresh (`seed_timeout`).
 * - "Reset only when needed" (user decision 2026-11-02 on CW-L1): a save made from the live session (the form posts
 *   {@see self::SESSION_FIELD} = the page topic and the log's base is the revision being replaced) keeps the log and
 *   records the new base ({@see self::saved()}). The log is dropped and `{t: "r"}` published — live editors reload —
 *   for a save outside the session or with a stale base, a restore, an import, a conversion ({@see self::replaced()}),
 *   a client report that the log cannot be applied ({@see self::corrupt()}, capped per page and window), an editor's
 *   explicit reset ({@see self::reset()}) and a log over `max_log_bytes` (CW-M1).
 * - A save from the page form outside the session, while the log holds unsaved updates, is not made at all unless the
 *   person confirms it ({@see self::blocksSaveOutsideSession()}, audit HF-M1): dropping the log would lose the others' text.
 */
final readonly class WikiCollabService
{
    /** Form field the Milkdown client (≥ 1.2) adds to the page form when it saves from a live session: the page topic. */
    public const SESSION_FIELD = '_nowo_milkdown_collab';

    /** Field of the "Replace for everyone" button: a save outside the session that may drop unsaved updates. */
    public const REPLACE_FIELD = '_nowo_wiki_collab_replace';

    /** Request attribute (page id): the save is inside {@see self::saveOutsideSession()}, which handles the log itself. */
    public const LOG_HANDLED_ATTRIBUTE = '_nowo_wiki_collab_log_handled';

    /** Request attribute (the page id) of a save made from a live session; read by {@see WikiCollabSaveListener}. */
    public const SESSION_SAVE_ATTRIBUTE = '_nowo_wiki_collab_session_save';

    /** Header carrying the Yjs client id of the tab (one seed per tab, not per user). */
    public const CLIENT_HEADER = 'X-Nowo-Collab-Client';

    /** An empty Yjs update (`Y.encodeStateAsUpdate(new Y.Doc())`), base64. */
    public const EMPTY_STATE = 'AAA=';

    /** Defaults of `nowo_wiki.collaboration`. */
    public const DEFAULTS = [
        'hub_url'            => '/.well-known/mercure',
        'topic_prefix'       => 'urn:nowo-wiki:page:',
        'max_update_bytes'   => 262144,
        'max_snapshot_bytes' => 4194304,
        'max_log_bytes'      => 16777216,
        'compact_after'      => 200,
        'max_log_entries'    => 2000,
        'seed_timeout'       => 30,
        'reset_after_idle'   => 300,
        'corrupt_resets'     => 3,
        'corrupt_window'     => 600,
        'show_names'         => false,
        'save_conflict'      => true,
    ];

    /** @var array{hub_url: string, topic_prefix: string, max_update_bytes: int, max_snapshot_bytes: int, max_log_bytes: int, compact_after: int, max_log_entries: int, seed_timeout: int, reset_after_idle: int, corrupt_resets: int, corrupt_window: int, show_names: bool, save_conflict: bool} */
    private array $options;

    /**
     * @param WikiCollabStateStoreInterface $store Yjs log of each page
     * @param WikiCollabPublisherInterface $publisher relays messages to the page topic
     * @param WikiCollabSubscriberAuthorizerInterface $authorizer lets the browser subscribe to the page topic
     * @param array<string, mixed> $options `nowo_wiki.collaboration` options (see {@see self::DEFAULTS})
     * @param RateLimiterFactory|null $rateLimiter limits every request per user and page (required by the configuration)
     * @param RateLimiterFactory|null $userRateLimiter limits every request per user across pages (1.13.1, CW-I9; optional)
     * @param string $secret key of the per-page pseudonyms "Editor xxxx" (`kernel.secret`, 1.13.1, DA-L2)
     * @param RateLimiterFactory|null $updateRateLimiter per user and page for `update` (1.14.1; null: `$rateLimiter`)
     * @param RateLimiterFactory|null $awarenessRateLimiter per user and page for `awareness` (1.14.1; null: `$rateLimiter`)
     * @param LoggerInterface|null $logger logs a reset that could not be published
     */
    public function __construct(
        private WikiCollabStateStoreInterface $store,
        private WikiCollabPublisherInterface $publisher,
        private WikiCollabSubscriberAuthorizerInterface $authorizer,
        array $options = [],
        private ?RateLimiterFactory $rateLimiter = null,
        private ?RateLimiterFactory $userRateLimiter = null,
        private string $secret = '',
        private ?RateLimiterFactory $updateRateLimiter = null,
        private ?RateLimiterFactory $awarenessRateLimiter = null,
        private ?LoggerInterface $logger = null,
    ) {
        /** @var array{hub_url: string, topic_prefix: string, max_update_bytes: int, max_snapshot_bytes: int, max_log_bytes: int, compact_after: int, max_log_entries: int, seed_timeout: int, reset_after_idle: int, corrupt_resets: int, corrupt_window: int, show_names: bool, save_conflict: bool} $merged */
        $merged        = $options + self::DEFAULTS;
        $this->options = $merged;
    }

    /**
     * CSRF token id of the editor's "Reset collaboration" form of a page.
     */
    public static function resetTokenId(WikiPage $page): string
    {
        return 'wiki_collab_reset_' . $page->getId();
    }

    /**
     * Topic IRI of a page (private: only subscribers whose JWT lists it receive its messages).
     */
    public function topic(WikiPage $page): string
    {
        return $this->options['topic_prefix'] . $page->getId();
    }

    /**
     * The `collaboration` option of MilkdownEditorType for an editor of the page (never give it to a reader).
     *
     * Co-editors see each other's name: the user's display name when the user class has `getDisplayName()` or
     * `getName()`, otherwise an opaque label derived from the identifier (CW-I2: never the e-mail / login itself).
     *
     * @param WikiPage $page page being edited
     * @param UserInterface $user its editor
     * @param string $url base URL of the page's collaboration endpoints (`<url>/token`, `/state`, …)
     *
     * @return array{url: string, hub_url: string, topic: string, user: array{name: string, color: string}}
     */
    public function editorOptions(WikiPage $page, UserInterface $user, string $url): array
    {
        // DA-L2 / DA-I5: keyed (a subkey of kernel.secret for this purpose only) and per page, so a label cannot be
        // matched against a user directory nor linked across pages.
        if ($this->secret === '') {
            throw new LogicException('nowo_wiki.collaboration needs a non-empty kernel.secret to derive the co-editor labels.');
        }
        $hash = hash_hmac('sha256', $page->getId() . '|' . $user->getUserIdentifier(), hash_hmac('sha256', 'nowo_wiki_collab_label', $this->secret));

        return [
            'url'     => rtrim($url, '/'),
            'hub_url' => $this->options['hub_url'],
            'topic'   => $this->topic($page),
            'user'    => ['name' => $this->displayName($user) ?? 'Editor ' . substr($hash, 6, 4), 'color' => '#' . substr($hash, 0, 6)],
        ];
    }

    /**
     * `POST <url>/token`: drops a stale log, claims the seeding of an empty log and authorizes the subscription.
     *
     * @param list<string> $extraTopics 1.16: more topics of the same grant (the comments topic for viewers with ViewComments)
     *
     * @throws WikiCollabException when rate-limited
     */
    public function token(Request $request, WikiPage $page, UserInterface $user, array $extraTopics = []): void
    {
        $this->limit($page, $user);
        $id      = $page->getId();
        $info    = $this->store->info($id);
        $current = $page->getCurrentRevision()?->getId();
        if ($info->baseRevisionId !== $current && ($info->isEmpty() || $this->idleFor($info) >= $this->options['reset_after_idle'])) {
            $this->store->reset($id, $current);
            $info = $this->store->info($id);
        }

        if ($info->isEmpty()) {
            $this->store->claimSeed($id, $this->staleBefore(), $this->claimant($request, $user));
        }

        // 1.16: the page's private comments topic rides on the same grant (one cookie), when the caller adds it.
        if ($extraTopics !== [] && $this->authorizer instanceof WikiCollabTopicsAuthorizerInterface) {
            $this->authorizer->authorizeTopics($request, [$this->topic($page), ...$extraTopics]);

            return;
        }
        $this->authorizer->authorize($request, $this->topic($page));
    }

    /**
     * `GET <url>/state`: the stored log, whether to compact it and whether this user's client seeds it (read only).
     *
     * @throws WikiCollabException when rate-limited
     *
     * @return array{state: string, updates: list<string>, seq: int, compact: bool, seed: bool}
     */
    public function state(WikiPage $page, UserInterface $user, ?Request $request = null): array
    {
        $this->limit($page, $user);
        $state = $this->store->load($page->getId());
        $me    = $request instanceof Request ? $this->claimant($request, $user) : $user->getUserIdentifier();

        return [
            'state'   => $state->snapshot ?? self::EMPTY_STATE,
            'updates' => $state->updates,
            'seq'     => $state->seq,
            'compact' => $state->updateCount >= $this->options['compact_after'],
            'seed'    => $state->isEmpty() && $state->seededBy === $me
                && $state->seededAt instanceof DateTimeImmutable && $state->seededAt >= $this->staleBefore(),
        ];
    }

    /**
     * `POST <url>/update`: stores the update and relays it.
     *
     * @throws WikiCollabException on an invalid, too large or rate-limited request, a full log (429 `log_full`:
     *                             compact first) or a log over its byte budget (409 `log_reset`: dropped, editors reload)
     */
    public function update(Request $request, WikiPage $page, UserInterface $user): void
    {
        [$client, $data] = $this->payload($request, $page, $user, $this->options['max_update_bytes'], 'update');
        // 1.14.1: the caps and the append in one locked write (no size read before and after), then the relay.
        if ($this->store instanceof WikiCollabCountingStoreInterface) {
            // @igor-ignore - Persists the update through the store (database write); the service keeps no state
            $refused = $this->store->appendWithin($page->getId(), $data, $this->options['max_log_entries'], $this->options['max_log_bytes']);
            if ($refused === 'log_full') {
                throw new WikiCollabException(Response::HTTP_TOO_MANY_REQUESTS, 'log_full');
            }
            if ($refused === 'log_reset') {
                $this->drop($page, $page->getCurrentRevision()?->getId());

                throw new WikiCollabException(Response::HTTP_CONFLICT, 'log_reset');
            }
            $this->publisher->publish($this->topic($page), json_encode(['t' => 'u', 'c' => $client, 'd' => $data], JSON_THROW_ON_ERROR));

            return;
        }

        $info = $this->store->info($page->getId());
        if ($info->updateCount >= $this->options['max_log_entries']) {
            throw new WikiCollabException(Response::HTTP_TOO_MANY_REQUESTS, 'log_full');
        }

        if ($info->bytes + strlen($data) > $this->options['max_log_bytes']) {
            $this->drop($page, $page->getCurrentRevision()?->getId());

            throw new WikiCollabException(Response::HTTP_CONFLICT, 'log_reset');
        }

        // @igor-ignore - Persists the update through the store (database write); the service keeps no state
        $this->store->append($page->getId(), $data);
        // CW-I8: concurrent appends may pass the check above together; the budget is checked again after the write.
        if ($this->store->info($page->getId())->bytes > $this->options['max_log_bytes']) {
            $this->drop($page, $page->getCurrentRevision()?->getId());

            throw new WikiCollabException(Response::HTTP_CONFLICT, 'log_reset');
        }

        $this->publisher->publish($this->topic($page), json_encode(['t' => 'u', 'c' => $client, 'd' => $data], JSON_THROW_ON_ERROR));
    }

    /**
     * `POST <url>/awareness`: relays presence (cursor, name); never stored.
     *
     * @throws WikiCollabException on an invalid, too large or rate-limited request
     */
    public function awareness(Request $request, WikiPage $page, UserInterface $user): void
    {
        [$client, $data] = $this->payload($request, $page, $user, $this->options['max_update_bytes'], 'awareness');
        $this->publisher->publish($this->topic($page), json_encode(['t' => 'a', 'c' => $client, 'd' => $data], JSON_THROW_ON_ERROR));
    }

    /**
     * `POST <url>/compact` (Milkdown ≥ 1.2): replaces the log up to `s` — never less than the current snapshot covers —
     * with the client's full state, which becomes the only snapshot.
     *
     * @throws WikiCollabException on an invalid, too large or rate-limited request, or a sequence number out of range
     */
    public function compact(Request $request, WikiPage $page, UserInterface $user): void
    {
        [, $data, $body] = $this->payload($request, $page, $user, $this->options['max_snapshot_bytes']);
        $seq             = $body['s'] ?? null;
        $info            = $this->store->info($page->getId());
        if (!is_int($seq) || $seq < 1 || $seq < $info->snapshotSeq || $seq > $info->seq) {
            throw new WikiCollabException(Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        $this->store->compact($page->getId(), $data, $seq);
    }

    /**
     * Runs a save made outside the live session unless it has to ask first. The check, the save and the reset of the log
     * happen with the log locked, so an update waits and is not lost between them (audit HO-L1); a host's store without
     * {@see WikiCollabLockingStoreInterface} runs them unlocked. Live editors hear of the reset after the commit, so a
     * hub that fails cannot undo the save and a slow one holds no lock (audit HO-M1).
     *
     * @param Request $request the request: {@see WikiCollabSaveListener} leaves the log to this method
     * @param callable(): mixed $save
     *
     * @throws WikiRevisionCollisionException when the save lost a race with another one: nothing was written
     *
     * @return bool false: nothing was saved, the person has to confirm
     */
    public function saveOutsideSession(Request $request, WikiPage $page, callable $save): bool
    {
        $request->attributes->set(self::LOG_HANDLED_ATTRIBUTE, $page->getId());
        $reset = false;
        $work  = function () use ($page, $save, &$reset): bool {
            if ($this->blocksSaveOutsideSession($page)) {
                return false;
            }

            $hasLog = $this->store->info($page->getId())->updatedAt instanceof DateTimeImmutable;
            $save();
            if ($hasLog) {
                $this->store->reset($page->getId(), $page->getCurrentRevision()?->getId());
                $reset = true;
            }

            return true;
        };

        try {
            $saved = $this->store instanceof WikiCollabLockingStoreInterface ? $this->store->whileLocked($page->getId(), $work) : $work();
        } catch (RetryableException $exception) {
            // Lost a race under the lock: the transaction rolled back, so nothing was written.
            throw new WikiRevisionCollisionException('The save lost a race with another one.', 0, $exception);
        } finally {
            $request->attributes->remove(self::LOG_HANDLED_ATTRIBUTE);
        }

        if ($reset) {
            $this->publishReset($page);
        }

        return $saved;
    }

    /**
     * Whether a save from the page form outside the live session has to ask first: the log holds updates no revision
     * has, which {@see self::replaced()} would drop (audit HF-M1). False with `collaboration.save_conflict: false`.
     */
    public function blocksSaveOutsideSession(WikiPage $page): bool
    {
        return $this->options['save_conflict'] && $this->store->info($page->getId())->hasUnsavedUpdates();
    }

    /**
     * A save from the live session: when the log's base is the revision this save replaced, the log stays and records
     * the new revision (no reload); otherwise the save is treated as a replacement.
     */
    public function saved(WikiPage $page, WikiPageRevision $revision, ?WikiPageRevision $previous): void
    {
        $info = $this->store->info($page->getId());
        if ($info->updatedAt instanceof DateTimeImmutable && $previous instanceof WikiPageRevision && $info->baseRevisionId === $previous->getId()) {
            $this->store->markSaved($page->getId(), $revision->getId());

            return;
        }

        $this->replaced($page, $revision);
    }

    /**
     * `POST <url>/corrupt` (Milkdown ≥ 1.2): a client could not apply the log answered with sequence number `s`.
     * Idempotent — a report about another log (already reset, or grown since) changes nothing — and capped: at most
     * `corrupt_resets` such resets per page in `corrupt_window` seconds (429 `reset_capped` beyond).
     *
     * @throws WikiCollabException on an invalid or rate-limited request, or over the cap
     */
    public function corrupt(Request $request, WikiPage $page, UserInterface $user): void
    {
        $this->limit($page, $user);
        $body = $this->json($request, 1024);
        $seq  = $body['s'] ?? null;
        if (!is_int($body['c'] ?? null) || !is_int($seq) || $seq < 0) {
            throw new WikiCollabException(Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        $info = $this->store->info($page->getId());
        if ($info->isEmpty() || $info->seq !== $seq) {
            return;
        }

        $this->capReset($page);
        $this->drop($page, $page->getCurrentRevision()?->getId());
    }

    /**
     * An editor's explicit "Reset collaboration" (the caller checked the edit right and the CSRF token): drops the log
     * and reloads the live editors, who then start again from the saved Markdown. Rate-limited and capped like
     * {@see self::corrupt()} (audit CW-L3); a log that is already empty is not broadcast again.
     *
     * @throws WikiCollabException when rate-limited (429 `rate_limited`) or over the cap (429 `reset_capped`)
     */
    public function reset(WikiPage $page, UserInterface $user): void
    {
        $this->limit($page, $user);
        if ($this->store->info($page->getId())->isEmpty()) {
            return;
        }

        $this->capReset($page);
        $this->drop($page, $page->getCurrentRevision()?->getId());
    }

    /**
     * The page was replaced outside the live session — a save without a live session or with a stale base, a restore,
     * an import, a conversion…: drops its log (without reading it), records the saved revision and tells the live
     * editors to reload. A page that never had a session is left alone.
     */
    public function replaced(WikiPage $page, WikiPageRevision $revision): void
    {
        if ($this->store->info($page->getId())->updatedAt instanceof DateTimeImmutable) {
            $this->drop($page, $revision->getId());
        }
    }

    /**
     * Drops the logs of pages untouched since `$before` and tells their live editors (if any) to reload, so a tab still
     * open on a purged page does not keep a document the server no longer has (1.13.1, CW-I10). Stores without
     * {@see WikiCollabPagePurgeInterface} are purged without the broadcast.
     *
     * @return int number of purged page logs
     */
    public function purge(DateTimeImmutable $before): int
    {
        if (!$this->store instanceof WikiCollabPagePurgeInterface) {
            return $this->store->purge($before);
        }
        $pages = $this->store->purgePages($before);
        foreach ($pages as $pageId) {
            $this->publisher->publish($this->options['topic_prefix'] . $pageId, json_encode(['t' => 'r', 'c' => 0, 'd' => ''], JSON_THROW_ON_ERROR));
        }

        return count($pages);
    }

    /**
     * @throws WikiCollabException over `corrupt_resets` client / editor resets of the page per `corrupt_window`
     */
    private function capReset(WikiPage $page): void
    {
        if (!$this->store->noteReset($page->getId(), new DateTimeImmutable(sprintf('-%d seconds', $this->options['corrupt_window'])), $this->options['corrupt_resets'])) {
            throw new WikiCollabException(Response::HTTP_TOO_MANY_REQUESTS, 'reset_capped');
        }
    }

    private function drop(WikiPage $page, ?string $revisionId): void
    {
        $this->store->reset($page->getId(), $revisionId);
        $this->publishReset($page);
    }

    /**
     * Tells live editors the log is gone. A failure is logged and goes no further: the log is already reset, and a save
     * that has succeeded must not answer an error (audit HO-M1). Those editors stay on the old text until they reload.
     */
    private function publishReset(WikiPage $page): void
    {
        try {
            $this->publisher->publish($this->topic($page), json_encode(['t' => 'r', 'c' => 0, 'd' => ''], JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            $this->logger?->error('Wiki co-editing reset could not be published.', ['bundle' => 'nowo_wiki', 'page' => $page->getId(), 'exception_class' => $exception::class]);
        }
    }

    /**
     * @throws WikiCollabException when the user exhausted the page's budget
     */
    private function limit(WikiPage $page, UserInterface $user, string $kind = 'other'): void
    {
        // 1.14.1: updates and presence may have their own budgets (null: the shared rate_limiter).
        $limiter = match ($kind) {
            'update'    => $this->updateRateLimiter ?? $this->rateLimiter,
            'awareness' => $this->awarenessRateLimiter ?? $this->rateLimiter,
            default     => $this->rateLimiter,
        };
        if ($limiter instanceof RateLimiterFactory
            && !$limiter->create($user->getUserIdentifier() . '|' . $page->getId())->consume()->isAccepted()) {
            throw new WikiCollabException(Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }
        if ($this->userRateLimiter instanceof RateLimiterFactory
            && !$this->userRateLimiter->create('nowo_wiki_collab:' . $user->getUserIdentifier())->consume()->isAccepted()) {
            throw new WikiCollabException(Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, mixed>}
     */
    private function payload(Request $request, WikiPage $page, UserInterface $user, int $maxBytes, string $kind = 'other'): array
    {
        $this->limit($page, $user, $kind);
        $body   = $this->json($request, (int) ($maxBytes * 4 / 3) + 64);
        $client = $body['c'] ?? null;
        $data   = $body['d'] ?? null;
        if (!is_int($client) || $client < 0 || !is_string($data) || $data === '' || preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $data) !== 1) {
            throw new WikiCollabException(Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        $decoded = base64_decode($data, true);
        if ($decoded === false || $decoded === '') {
            throw new WikiCollabException(Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        if (strlen($decoded) > $maxBytes) {
            throw new WikiCollabException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'too_large');
        }

        return [$client, $data, $body];
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Request $request, int $maxLength): array
    {
        if ($request->getContentTypeFormat() !== 'json') {
            throw new WikiCollabException(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'json_required');
        }

        $content = $request->getContent();
        if (strlen($content) > $maxLength) {
            throw new WikiCollabException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'too_large');
        }

        try {
            $body = json_decode($content, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new WikiCollabException(Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        if (!is_array($body)) {
            throw new WikiCollabException(Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        /* @var array<string, mixed> $body */
        return $body;
    }

    /**
     * Who claims the seed: the user, plus the tab's Yjs client id when the client sends it (one seed per tab).
     */
    private function claimant(Request $request, UserInterface $user): string
    {
        $client = (string) $request->headers->get(self::CLIENT_HEADER, '');

        return $user->getUserIdentifier() . (preg_match('/^\d{1,16}$/', $client) === 1 ? '#' . $client : '');
    }

    private function staleBefore(): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('-%d seconds', $this->options['seed_timeout']));
    }

    /**
     * The name co-editors see (1.13.1): an opaque "Editor xxxx" unless `show_names` is on; even then a name that could
     * identify the login falls back to the opaque label (CW-I2, DA-L3).
     */
    private function displayName(UserInterface $user): ?string
    {
        if (!$this->options['show_names']) {
            return null;
        }
        foreach (['getDisplayName', 'getName'] as $method) {
            $name = method_exists($user, $method) ? $user->{$method}() : null;
            if (!is_string($name) || trim($this->spaces($name)) === '') {
                continue;
            }

            return self::isSafeName($name, $user->getUserIdentifier()) ? trim($this->spaces($name)) : null;
        }

        return null;
    }

    /**
     * Whether a display name says nothing about the login (DA-L3). Conservative:
     *
     * - no format / bidi controls (`\p{Cf}`) and only Latin letters (Basic Latin, Latin-1, Latin Extended-A / B), digits,
     *   spaces and common punctuation — fullwidth, mathematical, circled or small-cap letters and other scripts
     *   (lookalikes) fall back to "Editor xxxx";
     * - no `@`;
     * - after folding (lower case, accents transliterated, separators removed) neither the login nor its e-mail local
     *   part may equal the name, appear as one of its words or, from 3 characters, inside it.
     *
     * Leet variants (`al0pez`) are not detected: `show_names` is for display names the application manages.
     */
    public static function isSafeName(string $name, string $login): bool
    {
        if (preg_match('/\p{Cf}/u', $name) === 1 || preg_match('/[^\x{0020}-\x{007E}\x{00A0}-\x{024F}\x{2019}]/u', $name) === 1 || str_contains($name, '@')) {
            return false;
        }
        $words = self::words($name);
        if ($words === []) {
            return false;
        }
        $compact = implode('', $words);
        $login   = mb_strtolower(trim($login), 'UTF-8');
        $local   = str_contains($login, '@') ? (string) strstr($login, '@', true) : $login;
        foreach ([$login, $local] as $id) {
            $idWords = self::words($id);
            $bare    = implode('', $idWords);
            if ($bare === $compact || in_array($bare, $words, true) || (strlen($bare) >= 3 && str_contains($compact, $bare))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lower-case ASCII words of a value: accents transliterated (iconv), everything but letters and digits a separator.
     *
     * @return list<string>
     */
    private static function words(string $value): array
    {
        // DA-I8: fail closed — without a transliteration the name is not shown (ext-iconv is required).
        $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : false;
        if ($ascii === false) {
            return []; // @codeCoverageIgnore
        }

        return preg_split('/[^a-z0-9]+/', strtolower($ascii), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function spaces(string $value): string
    {
        return (string) preg_replace('/[\p{Z}\p{Cc}]+/u', ' ', $value);
    }

    private function idleFor(WikiCollabState $state): int
    {
        return $state->updatedAt instanceof DateTimeImmutable ? (new DateTimeImmutable())->getTimestamp() - $state->updatedAt->getTimestamp() : PHP_INT_MAX;
    }
}
