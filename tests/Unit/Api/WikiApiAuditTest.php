<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Api;

use Nowo\WikiBundle\Api\Operation\WikiApiAudit;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Service\WikiQuotaGate;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * 1.24 (spec 218 FR-008, FR-030): writes need the host's audit and, by default, its plan limits; a failing audit
 * listener does not undo a write.
 */
final class WikiApiAuditTest extends TestCase
{
    public function testWritesNeedAnAuditListener(): void
    {
        $dispatcher = new EventDispatcher();
        $audit      = new WikiApiAudit($dispatcher, new WikiQuotaGate($dispatcher));

        try {
            $audit->requireAudit();
            self::fail('No audit listener: refused.');
        } catch (WikiApiRefusal $refusal) {
            self::assertSame([503, 'AUDIT_001'], [$refusal->status, $refusal->ecode]);
        }

        $dispatcher->addListener(WikiEvents::API_WRITE, static fn () => null);
        $audit->requireAudit();
    }

    public function testPlanLimitsFailClosedUnlessTheHostSaysItHasNone(): void
    {
        $dispatcher = new EventDispatcher();
        $space      = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');

        try {
            (new WikiApiAudit($dispatcher, new WikiQuotaGate($dispatcher)))->requireQuota('page_create', new TestUser(), $space);
            self::fail('No quota listener: refused.');
        } catch (WikiApiRefusal $refusal) {
            self::assertSame([403, 'PLAN_002', 'No plan limits are configured'], [$refusal->status, $refusal->ecode, $refusal->getMessage()]);
        }

        (new WikiApiAudit($dispatcher, new WikiQuotaGate($dispatcher), false))->requireQuota('page_create', new TestUser(), $space);
        $this->addToAssertionCount(1);
    }

    public function testAFailingAuditListenerIsLoggedAndTheWriteStands(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::API_WRITE, static fn () => throw new RuntimeException('audit store down: page text'));
        $logger = new RecordingLogger();

        (new WikiApiAudit($dispatcher, new WikiQuotaGate($dispatcher), true, $logger))->written(new WikiApiPrincipal(new TestUser(), WikiApiLevel::Edit, WikiApiWhere::everything(), 't', 'n'), 'page.update', 'eng', 's', 'p');

        self::assertSame(['error: Wiki API: the audit listener failed; the write stands.'], $logger->lines());
        self::assertSame(RuntimeException::class, $logger->records[0]['context']['exception_class']);
    }
}
