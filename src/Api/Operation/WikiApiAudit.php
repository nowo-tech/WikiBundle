<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Operation;

use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Event\WikiApiWriteEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Service\WikiQuotaGate;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

/**
 * 1.24 (spec 218 FR-008, FR-030): what every API write needs from the host before and after it. Before: an audit
 * listener (else 503 `AUDIT_001`, nothing written) and the plan limits (a refusal, or no listener while
 * `api.quota_listener_required`, is 403 `PLAN_002`). After: one {@see WikiApiWriteEvent}, once the write committed; a
 * listener that fails does not undo it and is logged by class only.
 */
final readonly class WikiApiAudit
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private WikiQuotaGate $quota,
        private bool $quotaListenerRequired = true,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /** @throws WikiApiRefusal */
    public function requireAudit(): void
    {
        if (!$this->dispatcher->hasListeners(WikiEvents::API_WRITE)) {
            throw new WikiApiRefusal(503, 'AUDIT_001', 'Writes are not available');
        }
    }

    /**
     * A metered write (`page_create`, `page_unarchive`) through the host's limits.
     *
     * @throws WikiApiRefusal
     */
    public function requireQuota(string $action, UserInterface $user, WikiSpace $space, ?WikiPage $page = null): void
    {
        if ($this->quotaListenerRequired && !$this->dispatcher->hasListeners(WikiEvents::QUOTA_CHECK)) {
            throw new WikiApiRefusal(403, 'PLAN_002', 'No plan limits are configured');
        }
        $refusal = $this->quota->refusal($action, $user, $space, 1, 0, $page);
        if ($refusal !== null) {
            throw new WikiApiRefusal(403, 'PLAN_002', $refusal);
        }
    }

    /**
     * @param array<string, string> $targetIds
     * @param list<string> $changedFields
     */
    public function written(WikiApiPrincipal $principal, string $operation, string $wikiSlug, ?string $spaceId, ?string $pageId, array $targetIds = [], array $changedFields = []): void
    {
        try {
            $this->dispatcher->dispatch(new WikiApiWriteEvent($principal, $operation, $wikiSlug, $spaceId, $pageId, $targetIds, $changedFields), WikiEvents::API_WRITE);
        } catch (Throwable $failure) {
            $this->logger->error('Wiki API: the audit listener failed; the write stands.', ['bundle' => 'nowo_wiki', 'operation' => $operation, 'exception_class' => $failure::class]);
        }
    }
}
