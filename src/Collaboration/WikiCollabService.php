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
 *   log for the requesting user; `GET state` only reads (CW-I3): it answers `seed: true` to the claimant while the
 *   claim is fresh (`seed_timeout`).
 * - Every save of the page — the edit form, a restore, an import, a conversion — drops the log and publishes
 *   `{t: "r"}` (user decision 2026-11-02 and audit CW-L1: the saved Markdown is the source of truth); live editors
 *   reload. A log over `max_log_bytes` is dropped the same way (CW-M1).
 */
final readonly class WikiCollabService
{
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
    ];

    /** @var array{hub_url: string, topic_prefix: string, max_update_bytes: int, max_snapshot_bytes: int, max_log_bytes: int, compact_after: int, max_log_entries: int, seed_timeout: int, reset_after_idle: int} */
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
        /** @var array{hub_url: string, topic_prefix: string, max_update_bytes: int, max_snapshot_bytes: int, max_log_bytes: int, compact_after: int, max_log_entries: int, seed_timeout: int, reset_after_idle: int} $merged */
        $merged        = $options + self::DEFAULTS;
        $this->options = $merged;
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
            $this->store->claimSeed($id, $this->staleBefore(), $user->getUserIdentifier());
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
    public function state(WikiPage $page, UserInterface $user): array
    {
        $this->limit($page, $user);
        $state = $this->store->load($page->getId());

        return [
            'state'   => $state->snapshot ?? self::EMPTY_STATE,
            'updates' => $state->updates,
            'seq'     => $state->seq,
            'compact' => $state->updateCount >= $this->options['compact_after'],
            'seed'    => $state->isEmpty() && $state->seededBy === $user->getUserIdentifier()
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
     * The page was saved — by the edit form, a restore, an import, a conversion…: drops its log (without reading it),
     * records the saved revision and tells the live editors to reload. A page that never had a session is left alone.
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
        if ($request->getContentTypeFormat() !== 'json') {
            throw new WikiCollabException(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'json_required');
        }

        $content = $request->getContent();
        if (strlen($content) > (int) ($maxBytes * 4 / 3) + 64) {
            throw new WikiCollabException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'too_large');
        }

        try {
            $body = json_decode($content, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new WikiCollabException(Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        $client = is_array($body) ? ($body['c'] ?? null) : null;
        $data   = is_array($body) ? ($body['d'] ?? null) : null;
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

        /* @var array<string, mixed> $body */
        return [$client, $data, $body];
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
