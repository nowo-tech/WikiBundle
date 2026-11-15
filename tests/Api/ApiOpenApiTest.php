<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

use function array_diff;
use function array_is_list;
use function array_keys;
use function array_merge;
use function array_values;
use function file_get_contents;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function preg_match_all;
use function sort;
use function str_repeat;
use function strlen;
use function strtolower;
use function substr;

/**
 * 1.24 (spec 218 SC-008): docs/openapi.yaml documents every route of the API and only those, every `$ref` resolves, and
 * the answers carry exactly the fields the document requires (and no field it does not name).
 */
final class ApiOpenApiTest extends ApiTestCase
{
    public function testTheDocumentHasEveryRouteAndNothingElse(): void
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $routes = [];
        foreach ($router->getRouteCollection()->all() as $route) {
            if ($route->getDefault(WikiApiRequestListener::ROUTE_FLAG) === true) {
                foreach ($route->getMethods() as $method) {
                    $routes[] = strtolower($method) . ' ' . substr($route->getPath(), strlen('/api/wiki/v1'));
                }
            }
        }
        $documented = [];
        foreach ($this->document()['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[] = $method . ' ' . $path;
            }
        }
        sort($routes);
        sort($documented);

        self::assertSame($routes, $documented);
    }

    public function testEveryReferenceResolves(): void
    {
        $text     = (string) file_get_contents(__DIR__ . '/../../docs/openapi.yaml');
        $document = $this->document();
        preg_match_all("~\\\$ref: '#/components/([a-zA-Z]+)/([a-zA-Z]+)'~", $text, $matches);
        self::assertNotEmpty($matches[0]);
        foreach ($matches[1] as $index => $section) {
            self::assertTrue(is_array($document['components'][$section][$matches[2][$index]] ?? null), $section . '/' . $matches[2][$index]);
        }
        self::assertSame('3.1.0', $document['openapi']);
    }

    public function testAnswersMatchTheirSchemas(): void
    {
        $em      = $this->em();
        $page    = $this->page($this->eng, 'Guide', '# Guide');
        $service = static::getContainer()->get(WikiCommentService::class);
        self::assertInstanceOf(WikiCommentService::class, $service);
        $stored = $this->wikiEm()->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $stored);
        $thread = $service->create($em->find(TestUserEntity::class, 'u-ana') ?? $this->ana, $em->find(WikiSpace::class, $this->eng->getId()) ?? $this->eng, $stored, null, 'Hello');
        $base   = '/wikis/eng/pages/' . $page->getId();

        $this->assertShape('Wiki', $this->json($this->call('GET', '/wikis/eng')));
        $this->assertShape('WikiList', $this->json($this->call('GET', '/wikis')));
        $this->assertShape('PageHead', $this->json($this->call('GET', '/wikis/eng/pages'))['pages'][0]);
        $this->assertShape('Page', $this->json($this->call('GET', $base)));
        $this->assertShape('RevisionHead', $this->json($this->call('GET', $base . '/revisions'))['revisions'][0]);
        $this->assertShape('Revision', $this->json($this->call('GET', $base . '/revisions/1')));
        $this->assertShape('Thread', $this->json($this->call('GET', $base . '/threads/' . $thread['id'])));
        $this->assertShape('Comment', $this->json($this->call('GET', $base . '/threads/' . $thread['id']))['comments'][0]);
        $this->assertShape('SearchResult', $this->json($this->call('GET', '/search?q=guide')));
        $this->assertShape('SearchHit', $this->json($this->call('GET', '/search?q=guide'))['hits'][0]);
        $this->assertShape('Diff', $this->json($this->call('GET', $base . '/revisions/1/diff?against=1')));
        $this->assertShape('Error', $this->json($this->call('GET', '/wikis/missing')));
        $this->token('reader', 'u-bob', WikiApiLevel::Read);
        $this->assertShape('Page', $this->json($this->call('GET', $base, 'reader')));
    }

    public function testWriteAnswersAndRefusalsAreDocumented(): void
    {
        $created = $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Guide', 'content_markdown' => 'x', 'extra' => 1]);
        $page    = $this->json($created);
        $this->assertShape('Page', $page);
        $this->assertDocumented('post', '/wikis/{wiki}/pages', $created->getStatusCode());
        $base = '/wikis/eng/pages/' . $page['id'];
        $this->assertShape('Page', $this->json($this->call('PUT', $base, self::ANA, ['content_markdown' => 'y', 'base_revision' => 1])));
        $thread = $this->json($this->call('POST', $base . '/threads', self::ANA, ['body' => 'Hi']));
        $this->assertShape('Thread', $thread);
        $this->assertShape('ThreadList', $this->json($this->call('GET', $base . '/threads')));
        $this->assertShape('PageList', $this->json($this->call('GET', '/wikis/eng/pages')));
        $this->assertShape('RevisionList', $this->json($this->call('GET', $base . '/revisions')));
        $this->assertShape('Wiki', $this->json($this->call('POST', '/wikis', self::ANA, ['owner_type' => 'project', 'owner_id' => 'owner-1', 'name' => 'Handbook'])));
        $this->assertShape('FileList', $this->json($this->call('GET', $base . '/files')));

        foreach ([
            ['put', '/wikis/{wiki}/pages/{page}', $this->call('PUT', $base, self::ANA, ['content_markdown' => 'z', 'base_revision' => 1])],
            ['put', '/wikis/{wiki}/pages/{page}', $this->call('PUT', $base, self::ANA, ['content_markdown' => 'z'])],
            ['put', '/wikis/{wiki}/pages/{page}', $this->call('PUT', $base, self::ANA, 'x=1', ['Content-Type' => 'text/plain'])],
            ['post', '/wikis/{wiki}/pages', $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'x', 'content_markdown' => str_repeat('*a* ', 20000)])],
            ['get', '/wikis/{wiki}', $this->call('GET', '/wikis/missing')],
            ['get', '/search', $this->call('GET', '/search')],
            ['get', '/wikis', $this->call('GET', '/wikis', 'unknown')],
        ] as [$method, $path, $response]) {
            $this->assertShape('Error', $this->json($response));
            $this->assertDocumented($method, $path, $response->getStatusCode());
        }
    }

    private function assertDocumented(string $method, string $path, int $status): void
    {
        self::assertArrayHasKey((string) $status, $this->document()['paths'][$path][$method]['responses'], $method . ' ' . $path . ' ' . $status);
    }

    /**
     * Required fields present, no unnamed field, each value of the type its property declares, nested objects and list
     * items checked the same way.
     */
    private function assertShape(string $schema, mixed $answer, string $at = ''): void
    {
        $this->assertValue(['$ref' => '#/components/schemas/' . $schema], $answer, $at === '' ? $schema : $at);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function assertValue(array $definition, mixed $value, string $at): void
    {
        if (is_string($definition['$ref'] ?? null)) {
            $this->assertValue($this->document()['components']['schemas'][substr($definition['$ref'], strlen('#/components/schemas/'))], $value, $at);

            return;
        }
        if (isset($definition['oneOf'])) {
            $value === null ? $this->addToAssertionCount(1) : $this->assertValue($definition['oneOf'][0], $value, $at);

            return;
        }
        if (isset($definition['allOf']) || ($definition['type'] ?? null) === 'object') {
            self::assertIsArray($value, $at);
            [$required, $properties, $open] = $this->objectOf($definition);
            self::assertSame([], array_values(array_diff($required, array_keys($value))), $at . ': missing required fields');
            if (!$open) {
                self::assertSame([], array_values(array_diff(array_keys($value), array_keys($properties))), $at . ': fields the document does not name');
            }
            foreach ($value as $key => $item) {
                if (isset($properties[$key])) {
                    $this->assertValue($properties[$key], $item, $at . '.' . $key);
                }
            }

            return;
        }
        $types = (array) ($definition['type'] ?? []);
        if ($types === []) {
            return;
        }
        $actual = match (true) {
            $value === null                           => 'null',
            is_bool($value)                           => 'boolean',
            is_int($value)                            => 'integer',
            is_string($value)                         => 'string',
            is_array($value) && array_is_list($value) => 'array',
            default                                   => 'object',
        };
        self::assertContains($actual, $types, $at . ': type');
        if ($actual === 'array' && isset($definition['items'])) {
            foreach ($value as $index => $item) {
                $this->assertValue($definition['items'], $item, $at . '[' . $index . ']');
            }
        }
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array{0: list<string>, 1: array<string, array<string, mixed>>, 2: bool}
     */
    private function objectOf(array $definition): array
    {
        $required   = [];
        $properties = [];
        $open       = ($definition['additionalProperties'] ?? false) === true;
        foreach ($definition['allOf'] ?? [$definition] as $part) {
            if (is_string($part['$ref'] ?? null)) {
                $part = $this->document()['components']['schemas'][substr($part['$ref'], strlen('#/components/schemas/'))];
            }
            $required   = array_merge($required, $part['required'] ?? []);
            $properties = array_merge($properties, $part['properties'] ?? []);
            $open       = $open || ($part['additionalProperties'] ?? false) === true;
        }

        return [$required, $properties, $open];
    }

    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        $document = Yaml::parseFile(__DIR__ . '/../../docs/openapi.yaml');
        self::assertIsArray($document);

        return $document;
    }
}
