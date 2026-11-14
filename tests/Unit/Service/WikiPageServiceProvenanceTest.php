<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Provenance\NullWikiWriteProvenanceProvider;
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceProviderInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function mb_strlen;
use function str_repeat;

/**
 * Spec 234: every new revision asks the host's provenance provider at write time; without one (or when it
 * answers null) the columns stay null.
 */
final class WikiPageServiceProvenanceTest extends TestCase
{
    public function testEveryWriteRecordsTheProvenanceOfThatMoment(): void
    {
        $provider = new class implements WikiWriteProvenanceProviderInterface {
            public ?WikiWriteProvenance $next = null;

            public int $asked = 0;

            public function current(): ?WikiWriteProvenance
            {
                ++$this->asked;

                return $this->next;
            }
        };
        $service = $this->service($provider);
        $space   = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');

        $provider->next = new WikiWriteProvenance('mcp', 'Claude <script>' . str_repeat('x', 200));
        $page           = $service->create($space, 'Hello', 'Body', new TestUser(), format: WikiPageRevision::FORMAT_MARKDOWN);
        $first          = $page->getCurrentRevision();
        self::assertSame('mcp', $first?->getChannel());
        self::assertSame(WikiWriteProvenance::MAX_LABEL, mb_strlen((string) $first?->getActorLabel()));
        self::assertStringStartsWith('Claude <script>', (string) $first?->getActorLabel());

        $provider->next = new WikiWriteProvenance('web');
        $second         = $service->saveRevision($page, 'Hello', '<p>Two</p>', new TestUser());
        self::assertSame('web', $second->getChannel());
        self::assertNull($second->getActorLabel());

        $provider->next = null;
        $third          = $service->restore($page, $first ?? $second, new TestUser());
        self::assertNull($third->getChannel());
        self::assertNull($third->getActorLabel());
        self::assertSame(3, $provider->asked, 'once per revision');
        self::assertSame('mcp', $first?->getChannel(), 'a revision keeps what was true when it was written');
    }

    public function testWithoutAProviderNothingIsRecorded(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        foreach ([null, new NullWikiWriteProvenanceProvider()] as $provider) {
            $revision = $this->service($provider)->create($space, 'Plain', '<p>Body</p>', new TestUser())->getCurrentRevision();
            self::assertNull($revision?->getChannel());
            self::assertNull($revision?->getActorLabel());
        }
    }

    private function service(?WikiWriteProvenanceProviderInterface $provider): WikiPageService
    {
        $pages = $this->createStub(WikiPageRepositoryInterface::class);
        $pages->method('countBySpaceAndSlug')->willReturn(0);
        $revisions = $this->createStub(WikiPageRevisionRepositoryInterface::class);
        $revisions->method('getNextRevisionNumber')->willReturnOnConsecutiveCalls(2, 3, 4);

        return new WikiPageService($pages, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher(), provenance: $provider);
    }
}
