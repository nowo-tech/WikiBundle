<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use ReflectionObject;

/**
 * 1.32.0 (REQ-ARCH-001 rule 2): a host on `database.mapping_type: xml` maps the wiki entities from the XML files only,
 * and the bundle works the same: pages written and read through the REST API, revisions with their author relation.
 */
final class ApiXmlMappingTest extends ApiTestCase
{
    protected static array $wikiConfig = ['database' => ['mapping_type' => 'xml']];

    public function testTheWikiEntitiesComeFromTheXmlFiles(): void
    {
        $driver = $this->chain($this->em()->getConfiguration()->getMetadataDriverImpl());
        self::assertInstanceOf(MappingDriverChain::class, $driver);
        self::assertInstanceOf(SimplifiedXmlDriver::class, $driver->getDrivers()['Nowo\\WikiBundle\\Entity'] ?? null);
        self::assertSame(TestUserEntity::class, $this->em()->getClassMetadata(WikiPageRevision::class)->getAssociationMapping('author')->targetEntity);
    }

    public function testPagesAreWrittenAndReadAsWithTheAttributes(): void
    {
        $response = $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook', 'content_markdown' => '# Steps']);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $created = $this->json($response);

        $this->em()->clear();
        $page = $this->em()->find(WikiPage::class, $created['id']);
        self::assertInstanceOf(WikiPage::class, $page);
        self::assertSame('Runbook', $page->getTitle());
        self::assertSame(1, $page->getCurrentRevision()?->getRevisionNumber());
        self::assertSame('# Steps', $page->getCurrentRevision()->getContentMarkdown());

        $read = $this->json($this->call('GET', '/wikis/eng/pages/' . $created['id']));
        self::assertSame('# Steps', $read['content_markdown']);
        self::assertSame(1, $read['revision']);
    }

    /**
     * The driver chain behind DoctrineBundle's (possibly decorated) metadata driver.
     */
    private function chain(?object $driver, int $depth = 0): ?MappingDriverChain
    {
        if ($driver instanceof MappingDriverChain || $driver === null || $depth > 3) {
            return $driver instanceof MappingDriverChain ? $driver : null;
        }
        foreach ((new ReflectionObject($driver))->getProperties() as $property) {
            $value = $property->isInitialized($driver) ? $property->getValue($driver) : null;
            if ($value instanceof MappingDriver && ($chain = $this->chain($value, $depth + 1)) instanceof MappingDriverChain) {
                return $chain;
            }
        }

        return null;
    }
}
