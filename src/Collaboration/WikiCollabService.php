<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use DateTimeImmutable;
use JsonException;
use Nowo\WikiBundle\Controller\WikiCollabController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\User\UserInterface;

use function base64_decode;
use function hash;
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
 */
final readonly class WikiCollabService
{
    /** Form field the Milkdown client (≥ 1.2) adds to the page form when it saves from a live session: the page topic. */
    public const SESSION_FIELD = '_nowo_milkdown_collab';

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
    ];

    /** @var array{hub_url: string, topic_prefix: string, max_update_bytes: int, max_snapshot_bytes: int, max_log_bytes: int, compact_after: int, max_log_entries: int, seed_timeout: int, reset_after_idle: int, corrupt_resets: int, corrupt_window: int} */
    private array $options;

    /**
     * @param WikiCollabStateStoreInterface $store Yjs log of each page
     * @param WikiCollabPublisherInterface $publisher relays messages to the page topic
     * @param WikiCollabSubscriberAuthorizerInterface $authorizer lets the browser subscribe to the page topic
     * @param array<string, mixed> $options `nowo_wiki.collaboration` options (see {@see self::DEFAULTS})
     * @param RateLimiterFactory|null $rateLimiter limits every request per user and page (required by the configuration)
     */
    public function __construct(
        private WikiCollabStateStoreInterface $store,
        private WikiCollabPublisherInterface $publisher,
        private WikiCollabSubscriberAuthorizerInterface $authorizer,
        array $options = [],
        private ?RateLimiterFactory $rateLimiter = null,
    ) {
        /** @var array{hub_url: string, topic_prefix: string, max_update_bytes: int, max_snapshot_bytes: int, max_log_bytes: int, compact_after: int, max_log_entries: int, seed_timeout: int, reset_after_idle: int, corrupt_resets: int, corrupt_window: int} $merged */
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
        $hash = hash('sha256', $user->getUserIdentifier());

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
     * @throws WikiCollabException when rate-limited
     */
    public function token(Request $request, WikiPage $page, UserInterface $user): void
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
        [$client, $data] = $this->payload($request, $page, $user, $this->options['max_update_bytes']);
        $info            = $this->store->info($page->getId());
        if ($info->updateCount >= $this->options['max_log_entries']) {
            throw new WikiCollabException(Response::HTTP_TOO_MANY_REQUESTS, 'log_full');
        }

        if ($info->bytes + strlen($data) > $this->options['max_log_bytes']) {
            $this->drop($page, $page->getCurrentRevision()?->getId());

            throw new WikiCollabException(Response::HTTP_CONFLICT, 'log_reset');
        }

        // @igor-ignore - Persists the update through the store (database write); the service keeps no state
        $this->store->append($page->getId(), $data);
        $this->publisher->publish($this->topic($page), json_encode(['t' => 'u', 'c' => $client, 'd' => $data], JSON_THROW_ON_ERROR));
    }

    /**
     * `POST <url>/awareness`: relays presence (cursor, name); never stored.
     *
     * @throws WikiCollabException on an invalid, too large or rate-limited request
     */
    public function awareness(Request $request, WikiPage $page, UserInterface $user): void
    {
        [$client, $data] = $this->payload($request, $page, $user, $this->options['max_update_bytes']);
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

        if (!$this->store->noteCorruptReset($page->getId(), new DateTimeImmutable(sprintf('-%d seconds', $this->options['corrupt_window'])), $this->options['corrupt_resets'])) {
            throw new WikiCollabException(Response::HTTP_TOO_MANY_REQUESTS, 'reset_capped');
        }

        $this->drop($page, $page->getCurrentRevision()?->getId());
    }

    /**
     * An editor's explicit "Reset collaboration" (the caller checked the edit right and the CSRF token): drops the log
     * and reloads the live editors, who then start again from the saved Markdown.
     */
    public function reset(WikiPage $page): void
    {
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

    private function drop(WikiPage $page, ?string $revisionId): void
    {
        $this->store->reset($page->getId(), $revisionId);
        $this->publisher->publish($this->topic($page), json_encode(['t' => 'r', 'c' => 0, 'd' => ''], JSON_THROW_ON_ERROR));
    }

    /**
     * @throws WikiCollabException when the user exhausted the page's budget
     */
    private function limit(WikiPage $page, UserInterface $user): void
    {
        if ($this->rateLimiter instanceof RateLimiterFactory
            && !$this->rateLimiter->create($user->getUserIdentifier() . '|' . $page->getId())->consume()->isAccepted()) {
            throw new WikiCollabException(Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, mixed>}
     */
    private function payload(Request $request, WikiPage $page, UserInterface $user, int $maxBytes): array
    {
        $this->limit($page, $user);
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

    private function displayName(UserInterface $user): ?string
    {
        foreach (['getDisplayName', 'getName'] as $method) {
            $name = method_exists($user, $method) ? $user->{$method}() : null;
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return null;
    }

    private function idleFor(WikiCollabState $state): int
    {
        return $state->updatedAt instanceof DateTimeImmutable ? (new DateTimeImmutable())->getTimestamp() - $state->updatedAt->getTimestamp() : PHP_INT_MAX;
    }
}
