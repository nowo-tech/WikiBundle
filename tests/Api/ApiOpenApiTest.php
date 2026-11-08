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
use function array_keys;
use function array_merge;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_string;
use function preg_match_all;
use function sort;
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
        $stored = $em->find(WikiPage::class, $page->getId());
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

    /**
     * @param array<string, mixed> $answer
     */
    private function assertShape(string $schema, array $answer): void
    {
        [$required, $named, $open] = $this->fields($schema);
        $keys                      = array_keys($answer);
        self::assertSame([], array_diff($required, $keys), $schema . ': missing required fields');
        if (!$open) {
            self::assertSame([], array_diff($keys, $named), $schema . ': fields the document does not name');
        }
    }

    /**
     * @return array{0: list<string>, 1: list<string>, 2: bool} required, named, open to more
     */
    private function fields(string $schema): array
    {
        $definition = $this->document()['components']['schemas'][$schema];
        $required   = [];
        $named      = [];
        $open       = ($definition['additionalProperties'] ?? false) === true;
        foreach ($definition['allOf'] ?? [$definition] as $part) {
            if (is_string($part['$ref'] ?? null)) {
                [$r, $n] = $this->fields(substr($part['$ref'], strlen('#/components/schemas/')));
            } else {
                [$r, $n] = [$part['required'] ?? [], array_keys($part['properties'] ?? [])];
            }
            $required = array_merge($required, $r);
            $named    = array_merge($named, $n);
        }
        self::assertTrue(in_array($schema, array_keys($this->document()['components']['schemas']), true));

        return [$required, $named, $open];
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
