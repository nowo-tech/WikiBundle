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
use function count;
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
 * it on every request; a host that wires its own endpoints must do the same). This service validates the payloads
 * (JSON, client id, strict base64, sizes), rate-limits per user and page, keeps the Yjs log through
 * {@see WikiCollabStateStoreInterface} and relays messages through {@see WikiCollabPublisherInterface}. It never
 * decodes Yjs and never reads the document: the client that saves still submits the Markdown.
 *
 * Seeding rule: a page whose log is empty gets `seed: true` for one client only (a claim older than
 * `seed_timeout` seconds may be taken again, so a client that left before seeding does not leave the page empty).
 * Reset rule (user decision 2026-11-02, "the replacement wins"): every save that is not the collaborative save of the
 * edit form — a restore, an import, a conversion, an API save — drops the log and publishes `{t: "r"}`: live editors
 * stop and reload with the new content ({@see self::replaced()}, called by {@see WikiCollabSaveListener}). As a
 * safety net, a log whose base revision is not the current one is also dropped when it is empty or idle for
 * `reset_after_idle` seconds.
 */
final readonly class WikiCollabService
{
    /** Request attribute set (to the page id) while the page edit form saves a collaborative document. */
    public const COLLAB_SAVE_ATTRIBUTE = '_nowo_wiki_collab_save';

    /** An empty Yjs update (`Y.encodeStateAsUpdate(new Y.Doc())`), base64. */
    public const EMPTY_STATE = 'AAA=';

    /** Defaults of `nowo_wiki.collaboration`. */
    public const DEFAULTS = [
        'hub_url'            => '/.well-known/mercure',
        'topic_prefix'       => 'urn:nowo-wiki:page:',
        'max_update_bytes'   => 262144,
        'max_snapshot_bytes' => 4194304,
        'compact_after'      => 200,
        'max_log_entries'    => 2000,
        'seed_timeout'       => 30,
        'reset_after_idle'   => 300,
    ];

    /** @var array{hub_url: string, topic_prefix: string, max_update_bytes: int, max_snapshot_bytes: int, compact_after: int, max_log_entries: int, seed_timeout: int, reset_after_idle: int} */
    private array $options;

    /**
     * @param WikiCollabStateStoreInterface $store Yjs log of each page
     * @param WikiCollabPublisherInterface $publisher relays messages to the page topic
     * @param WikiCollabSubscriberAuthorizerInterface $authorizer lets the browser subscribe to the page topic
     * @param array<string, mixed> $options `nowo_wiki.collaboration` options (see {@see self::DEFAULTS})
     * @param RateLimiterFactory|null $rateLimiter limits update / awareness / compact requests per user and page
     */
    public function __construct(
        private WikiCollabStateStoreInterface $store,
        private WikiCollabPublisherInterface $publisher,
        private WikiCollabSubscriberAuthorizerInterface $authorizer,
        array $options = [],
        private ?RateLimiterFactory $rateLimiter = null,
    ) {
        /** @var array{hub_url: string, topic_prefix: string, max_update_bytes: int, max_snapshot_bytes: int, compact_after: int, max_log_entries: int, seed_timeout: int, reset_after_idle: int} $merged */
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
     * @param WikiPage $page page being edited
     * @param UserInterface $user its editor (shown to the others by name, with a colour derived from the identifier)
     * @param string $url base URL of the page's collaboration endpoints (`<url>/token`, `/state`, …)
     *
     * @return array{url: string, hub_url: string, topic: string, user: array{name: string, color: string}}
     */
    public function editorOptions(WikiPage $page, UserInterface $user, string $url): array
    {
        return [
            'url'     => rtrim($url, '/'),
            'hub_url' => $this->options['hub_url'],
            'topic'   => $this->topic($page),
            'user'    => ['name' => $user->getUserIdentifier(), 'color' => '#' . substr(hash('sha256', $user->getUserIdentifier()), 0, 6)],
        ];
    }

    /**
     * `POST <url>/token`: authorizes the browser to subscribe to the page topic.
     */
    public function token(Request $request, WikiPage $page): void
    {
        $this->authorizer->authorize($request, $this->topic($page));
    }

    /**
     * `GET <url>/state`: the stored log (reset first when stale), whether to compact it and whether this client seeds.
     *
     * @return array{state: string, updates: list<string>, seq: int, compact: bool, seed: bool}
     */
    public function state(WikiPage $page): array
    {
        $id      = $page->getId();
        $state   = $this->store->load($id);
        $current = $page->getCurrentRevision()?->getId();
        if ($state->baseRevisionId !== $current && ($state->isEmpty() || $this->idleFor($state) >= $this->options['reset_after_idle'])) {
            $this->store->reset($id, $current);
            $state = $this->store->load($id);
        }

        return [
            'state'   => $state->snapshot ?? self::EMPTY_STATE,
            'updates' => $state->updates,
            'seq'     => $state->seq,
            'compact' => count($state->updates) >= $this->options['compact_after'],
            'seed'    => $state->isEmpty() && $this->store->claimSeed($id, new DateTimeImmutable(sprintf('-%d seconds', $this->options['seed_timeout']))),
        ];
    }

    /**
     * `POST <url>/update`: stores the update and relays it.
     *
     * @throws WikiCollabException on an invalid, too large or rate-limited request, or a full log (429: compact first)
     */
    public function update(Request $request, WikiPage $page, UserInterface $user): void
    {
        [$client, $data] = $this->payload($request, $page, $user, $this->options['max_update_bytes']);
        if (count($this->store->load($page->getId())->updates) >= $this->options['max_log_entries']) {
            throw new WikiCollabException(Response::HTTP_TOO_MANY_REQUESTS, 'log_full');
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
     * `POST <url>/compact` (Milkdown ≥ 1.2): replaces the log up to `s` with the client's full state.
     *
     * @throws WikiCollabException on an invalid, too large or rate-limited request, or a sequence number not in the log
     */
    public function compact(Request $request, WikiPage $page, UserInterface $user): void
    {
        [, $data, $body] = $this->payload($request, $page, $user, $this->options['max_snapshot_bytes']);
        $seq             = $body['s'] ?? null;
        if (!is_int($seq) || $seq < 1 || $seq > $this->store->load($page->getId())->seq) {
            throw new WikiCollabException(Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        $this->store->compact($page->getId(), $data, $seq);
    }

    /**
     * The page was replaced outside the collaborative session (restore, import, conversion…): drops its log, records
     * the revision it restarts from and tells the live editors to reload (`{t: "r"}`). A page that never had a
     * session is left alone.
     */
    public function replaced(WikiPage $page, WikiPageRevision $revision): void
    {
        if (!$this->store->load($page->getId())->updatedAt instanceof DateTimeImmutable) {
            return;
        }

        $this->store->reset($page->getId(), $revision->getId());
        $this->publisher->publish($this->topic($page), json_encode(['t' => 'r', 'c' => 0, 'd' => ''], JSON_THROW_ON_ERROR));
    }

    /**
     * Records that the collaborative document was saved as the revision (the log stays valid).
     */
    public function saved(WikiPage $page, WikiPageRevision $revision): void
    {
        $this->store->markSaved($page->getId(), $revision->getId());
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, mixed>}
     */
    private function payload(Request $request, WikiPage $page, UserInterface $user, int $maxBytes): array
    {
        if ($this->rateLimiter instanceof RateLimiterFactory
            && !$this->rateLimiter->create($user->getUserIdentifier() . '|' . $page->getId())->consume()->isAccepted()) {
            throw new WikiCollabException(Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }

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

    private function idleFor(WikiCollabState $state): int
    {
        return $state->updatedAt instanceof DateTimeImmutable ? (new DateTimeImmutable())->getTimestamp() - $state->updatedAt->getTimestamp() : PHP_INT_MAX;
    }
}
