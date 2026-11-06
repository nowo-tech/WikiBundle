<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as ListeningDispatcher;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Asks the host whether a metered write may happen (1.21, {@see WikiEvents::QUOTA_CHECK}). Stateless: safe in worker
 * mode.
 */
final readonly class WikiQuotaGate
{
    /** Catalogue key of the text shown when a listener refuses without a reason. */
    public const DEFAULT_REASON_KEY = 'wiki.quota.denied';

    /** That text without a translator. */
    public const DEFAULT_REASON = 'This would go over a limit of your plan.';

    public function __construct(
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * Whether a listener may answer, so a caller can skip costly work for the event's `count` (reading an archive). A
     * dispatcher that cannot tell counts as active.
     */
    public function isActive(): bool
    {
        if (!$this->eventDispatcher instanceof EventDispatcherInterface) {
            return false;
        }

        return !$this->eventDispatcher instanceof ListeningDispatcher || $this->eventDispatcher->hasListeners(WikiEvents::QUOTA_CHECK);
    }

    /**
     * Dispatches the check.
     *
     * @param string $action a {@see WikiQuotaCheckEvent} action constant
     *
     * @return string|null null when the write may happen, else the text of the refusal (plain text: the listener's
     *                     reason, else {@see self::DEFAULT_REASON_KEY} translated)
     */
    public function refusal(string $action, UserInterface $user, WikiSpace $space, int $count = 1, int $bytes = 0, ?WikiPage $page = null): ?string
    {
        if (!$this->eventDispatcher instanceof EventDispatcherInterface) {
            return null;
        }

        $event = new WikiQuotaCheckEvent($action, $space, $user, $count, $bytes, $page);
        $this->eventDispatcher->dispatch($event, WikiEvents::QUOTA_CHECK);

        $reason = $event->getReason();
        if ($reason === null || $reason !== '') {
            return $reason;
        }

        return $this->translator instanceof TranslatorInterface ? $this->translator->trans(self::DEFAULT_REASON_KEY, [], 'NowoWikiBundle') : self::DEFAULT_REASON;
    }
}
