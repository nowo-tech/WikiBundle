<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiSearchService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function dirname;
use function extension_loaded;
use function sort;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * LIKE wildcards typed by the user (%, _ and the escape character) must match literally.
 */
final class WikiSearchServiceSqliteTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 2) . '/src/Entity'],
            true,
            sys_get_temp_dir() . '/wiki_search_proxies',
        );
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($this->em))->createSchema([
            $this->em->getClassMetadata(WikiSpace::class),
            $this->em->getClassMetadata(WikiPage::class),
        ]);
        $this->em->getConnection()->executeStatement(
            'CREATE TABLE wiki_page_revisions (id VARCHAR(36) NOT NULL PRIMARY KEY, page_id VARCHAR(36) NOT NULL, revision_number INTEGER NOT NULL, content_html CLOB NOT NULL, author_id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL)',
        );

        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->space);
        foreach (['Growth 50% target', 'Growth 500 target', 'snake_case names', 'snakeXcase names', 'Path C:\\temp', 'Path C:temp', 'Bang! page', 'Bang page'] as $i => $title) {
            $this->em->persist(new WikiPage($this->space, 'p' . $i, $title));
        }
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function literalQueries(): iterable
    {
        yield 'percent' => ['50%', ['Growth 50% target']];
        yield 'underscore' => ['snake_case', ['snake_case names']];
        yield 'backslash' => ['C:\\temp', ['Path C:\\temp']];
        yield 'escape character' => ['Bang!', ['Bang! page']];
        yield 'plain' => ['Growth', ['Growth 50% target', 'Growth 500 target']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('literalQueries')]
    public function testUserInputIsMatchedLiterally(string $query, array $expected): void
    {
        $space = $this->em->find(WikiSpace::class, $this->space->getId());
        self::assertNotNull($space);
        $results = (new WikiSearchService($this->em))->search($space, $query);

        $titles = array_map(static fn (array $row): string => $row['page']->getTitle(), $results);
        sort($titles);

        self::assertSame($expected, $titles);
    }
}
