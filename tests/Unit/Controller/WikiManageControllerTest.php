<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Ai\WikiAiAnswer;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZipArchive;

final class WikiManageControllerTest extends TestCase
{
    /** @var array<string, array{path: string, name: string}> */
    private array $routes;

    /** @var array<string, string> */
    private array $templates;

    protected function setUp(): void
    {
        $this->routes = [
            'index'        => ['path' => '/tools/wiki', 'name' => 'nowo_wiki_index'],
            'space'        => ['path' => '/tools/wiki/spaces/{spaceSlug}', 'name' => 'nowo_wiki_space'],
            'page_view'    => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}', 'name' => 'nowo_wiki_page_view'],
            'page_edit'    => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/edit', 'name' => 'nowo_wiki_page_edit'],
            'page_new'     => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/new', 'name' => 'nowo_wiki_page_new'],
            'page_history' => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/history', 'name' => 'nowo_wiki_page_history'],
            'page_diff'    => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/diff', 'name' => 'nowo_wiki_page_diff'],
            'page_archive' => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/archive', 'name' => 'nowo_wiki_page_archive'],
            'search'       => ['path' => '/tools/wiki/search', 'name' => 'nowo_wiki_search'],
            'ai_ask'       => ['path' => '/tools/wiki/ask', 'name' => 'nowo_wiki_ai_ask'],
            'space_export' => ['path' => '/tools/wiki/spaces/{spaceSlug}/export', 'name' => 'nowo_wiki_space_export'],
            'space_import' => ['path' => '/tools/wiki/spaces/{spaceSlug}/import', 'name' => 'nowo_wiki_space_import'],
        ];
        $this->templates = [
            'layout'       => '@NowoWikiBundle/layout.html.twig',
            'index'        => '@NowoWikiBundle/manage/index.html.twig',
            'space'        => '@NowoWikiBundle/manage/space.html.twig',
            'page_view'    => '@NowoWikiBundle/manage/page_view.html.twig',
            'page_edit'    => '@NowoWikiBundle/manage/page_edit.html.twig',
            'page_history' => '@NowoWikiBundle/manage/page_history.html.twig',
            'page_diff'    => '@NowoWikiBundle/manage/page_diff.html.twig',
            'search'       => '@NowoWikiBundle/manage/search.html.twig',
            'ai_ask'       => '@NowoWikiBundle/manage/ai_ask.html.twig',
        ];
    }

    public function testIndexListsSpaces(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->index();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('@NowoWikiBundle/manage/index.html.twig', (string) $response->getContent());
    }

    public function testDenyWhenCannotList(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canList')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->index();
    }

    public function testViewPageRendersContent(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->viewPage('eng', 'welcome');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_view', (string) $response->getContent());
    }

    public function testViewPageNotFoundForForeignSpace(): void
    {
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(NotFoundHttpException::class);
        $controller->viewPage('eng', 'missing');
    }

    public function testArchiveRequiresValidCsrf(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canArchive')->willReturn(true);

        $controller = $this->controller(accessChecker: $checker, spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/archive', 'POST', ['_token' => 'invalid']);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->archivePage($request, 'eng', 'welcome');
    }

    public function testSpaceRendersTree(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->space('eng');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('space', (string) $response->getContent());
    }

    public function testSearchWithoutSpaceSlug(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'deploy', 'Deploy');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            searchService: $this->createSearchService([[$page, 'contentHtml' => '<p>deploy runbook</p>']]),
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->search(Request::create('/search?q=deploy'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('search', (string) $response->getContent());
    }

    public function testAskAiShowsUnavailableWhenNotConfigured(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->askAi(Request::create('/ask'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('ai_ask', (string) $response->getContent());
    }

    public function testPageHistoryRendersRevisions(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $rev   = new WikiPageRevision($page, 1, '<p>Hi</p>', $user);

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('findByPage')->willReturn([$rev]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->pageHistory('eng', 'welcome');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_history', (string) $response->getContent());
    }

    public function testPageDiffNotFoundWhenRevisionMissing(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(NotFoundHttpException::class);
        $controller->pageDiff(Request::create('/diff?from=1&to=2'), 'eng', 'welcome');
    }

    public function testPageDiffRendersWhenRevisionsExist(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $from  = new WikiPageRevision($page, 1, '<p>old</p>', $user);
        $to    = new WikiPageRevision($page, 2, '<p>new</p>', $user);

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('findByPageAndNumber')->willReturnMap([
            [$page, 1, $from],
            [$page, 2, $to],
        ]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->pageDiff(Request::create('/diff?from=1&to=2'), 'eng', 'welcome');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_diff', (string) $response->getContent());
    }

    public function testEditPageGetRendersForm(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->editPage(Request::create('/edit'), 'eng', 'welcome');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_edit', (string) $response->getContent());
    }

    public function testDenyWhenCannotEdit(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canEdit')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->editPage(Request::create('/edit'), 'eng', 'welcome');
    }

    public function testDenyWhenCannotViewHistory(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canList')->willReturn(true);
        $checker->method('canViewHistory')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->pageHistory('eng', 'welcome');
    }

    public function testViewArchivedPageNotFound(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->archive();

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(NotFoundHttpException::class);
        $controller->viewPage('eng', 'welcome');
    }

    public function testArchiveWithValidCsrfRedirects(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);
        $pageRepo->expects(self::once())->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $tokenId = 'wiki_archive_' . $page->getId();
        $request = Request::create('/archive', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, $tokenId),
        ]);

        $response = $controller->archivePage($request, 'eng', 'welcome');

        self::assertTrue($response->isRedirect());
    }

    public function testRequireUserDeniedWhenNotAuthenticated(): void
    {
        $controller = $this->controller();
        WikiControllerContainerBuilder::bind($controller);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->index();
    }

    public function testEditPagePostSavesRevisionAndRedirects(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);
        $pageRepo->expects(self::atLeastOnce())->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('getNextRevisionNumber')->willReturn(2);
        $revisionRepo->expects(self::once())->method('save');

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->editPage(
            $this->createWikiPageFormRequest($container, 'wiki_edit_' . $page->getId(), [
                'title'   => 'Updated',
                'content' => '<p>Updated</p>',
            ]),
            'eng',
            'welcome',
        );

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_page_view'));
    }

    public function testNewPageGetRendersCreateForm(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->newPage(Request::create('/new'), 'eng');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_edit', (string) $response->getContent());
    }

    public function testNewPagePostCreatesPageAndRedirects(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepo->expects(self::exactly(2))->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('getNextRevisionNumber')->willReturn(1);
        $revisionRepo->expects(self::once())->method('save');

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->newPage(
            $this->createWikiPageFormRequest($container, 'wiki_new_' . $space->getId(), [
                'title'   => 'New doc',
                'content' => '<p>Body</p>',
            ]),
            'eng',
        );

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_page_view'));
    }

    public function testSearchWithSpaceAndQuery(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([[$page, 'contentHtml' => '<p>welcome</p>']]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            searchService: new WikiSearchService($em),
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->search(Request::create('/search?space=eng&q=welcome'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('search', (string) $response->getContent());
    }

    public function testDenyWhenCannotCreate(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canCreate')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->newPage(Request::create('/new'), 'eng');
    }

    public function testEditPostRejectsInvalidCsrf(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = $this->createWikiPageFormRequest($container, 'invalid-token-id', [
            'title'   => 'Updated',
            'content' => '<p>Updated</p>',
        ]);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->editPage($request, 'eng', 'welcome');
    }

    public function testNewPagePostRejectsInvalidCsrf(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = $this->createWikiPageFormRequest($container, 'invalid-token-id', [
            'title'   => 'New doc',
            'content' => '<p>Body</p>',
        ]);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->newPage($request, 'eng');
    }

    public function testNewPagePostRerendersOnDuplicateSlug(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(1);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->newPage(
            $this->createWikiPageFormRequest($container, 'wiki_new_' . $space->getId(), [
                'title'   => 'Duplicate',
                'content' => '<p>Body</p>',
            ]),
            'eng',
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_edit', (string) $response->getContent());
    }

    public function testImportSpaceNotFoundWhenImportExportDisabled(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            importExportEnabled: false,
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(NotFoundHttpException::class);
        $controller->importSpace(Request::create('/import', 'POST'), 'eng');
    }

    public function testExportSpaceUsesOutlineWhenFormatAuto(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->exportSpace(Request::create('/export?format=auto'), 'eng');

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        @unlink($response->getFile()->getPathname());
    }

    public function testImportSpaceRejectsOversizedUpload(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            maxUploadBytes: 10,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $temp = tempnam(sys_get_temp_dir(), 'wiki-upload-');
        file_put_contents($temp, str_repeat('x', 20));

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($temp, 'archive.zip', 'application/zip', null, true),
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        @unlink($temp);
    }

    public function testImportSpaceHandlesImporterException(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $zipPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(4)) . '.zip';
        file_put_contents($zipPath, 'not-a-valid-zip');

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        @unlink($zipPath);
    }

    public function testImportSpaceFlashesWarningMessages(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $sourceDir = sys_get_temp_dir() . '/wiki-upload-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $zipPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sourceDir . '/intro.md', 'intro.md');
        $zip->close();

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $page     = new WikiPage($space, 'intro', 'Intro');
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(1);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        unlink($sourceDir . '/intro.md');
        rmdir($sourceDir);
        @unlink($zipPath);
    }

    public function testImportSpaceImportsZipWithFormatOption(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $sourceDir = sys_get_temp_dir() . '/wiki-upload-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $zipPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sourceDir . '/intro.md', 'intro.md');
        $zip->close();

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([]);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepo->expects(self::exactly(2))->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('getNextRevisionNumber')->willReturn(1);
        $revisionRepo->expects(self::once())->method('save');

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, revisionRepository: $revisionRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
            'format' => WikiInterchangeFormat::Outline->value,
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        try {
            $response = $controller->importSpace($request, 'eng');
            self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        } finally {
            unlink($sourceDir . '/intro.md');
            rmdir($sourceDir);
            @unlink($zipPath);
        }
    }

    public function testImportSpaceUsesAutoFormatFallback(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $sourceDir = sys_get_temp_dir() . '/wiki-upload-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $zipPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sourceDir . '/intro.md', 'intro.md');
        $zip->close();

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([]);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepo->method('save');

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
            'format' => 'not-a-format',
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        try {
            $response = $controller->importSpace($request, 'eng');
            self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        } finally {
            unlink($sourceDir . '/intro.md');
            rmdir($sourceDir);
            @unlink($zipPath);
        }
    }

    public function testExportSpaceUsesAutoFormatFallback(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->exportSpace(
            Request::create('/export?format=' . WikiInterchangeFormat::Auto->value),
            'eng',
        );

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        @unlink($response->getFile()->getPathname());
    }

    public function testExportSpaceFallsBackWhenFormatInvalid(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->exportSpace(Request::create('/export?format=not-valid'), 'eng');

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        @unlink($response->getFile()->getPathname());
    }

    public function testImportSpaceRedirectsWhenCopyFails(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $upload = $this->createMock(UploadedFile::class);
        $upload->method('getClientOriginalExtension')->willReturn('zip');
        $upload->method('getPathname')->willReturn('/tmp/does-not-exist-' . bin2hex(random_bytes(4)) . '.zip');
        $upload->method('getSize')->willReturn(100);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => $upload,
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
    }

    public function testDenyUnlessFeatureFallsBackToCanAccess(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canAccess')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        $method     = new ReflectionMethod(WikiManageController::class, 'denyUnlessFeature');

        $this->expectException(AccessDeniedHttpException::class);
        $method->invoke($controller, 'unknown');
    }

    public function testDenyWhenCannotExport(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canExport')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->exportSpace(Request::create('/export'), 'eng');
    }

    public function testExportSpaceReturnsZipDownload(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->exportSpace(Request::create('/export?format=outline'), 'eng');

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertFileExists($response->getFile()->getPathname());
        @unlink($response->getFile()->getPathname());
    }

    public function testExportSpaceNotFoundWhenImportExportDisabled(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            importExportEnabled: false,
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(NotFoundHttpException::class);
        $controller->exportSpace(Request::create('/export'), 'eng');
    }

    public function testDenyWhenCannotImport(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canImport')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->importSpace(Request::create('/import', 'POST'), 'eng');
    }

    public function testImportSpaceRedirectsWhenFileMissing(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
    }

    public function testImportSpaceRejectsNonZipUpload(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $temp    = tempnam(sys_get_temp_dir(), 'wiki-upload-');
        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($temp, 'notes.txt', 'text/plain', null, true),
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        @unlink($temp);
    }

    public function testImportSpaceImportsZipArchive(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $sourceDir = sys_get_temp_dir() . '/wiki-upload-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $zipPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sourceDir . '/intro.md', 'intro.md');
        $zip->close();

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([]);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepo->expects(self::exactly(2))->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('getNextRevisionNumber')->willReturn(1);
        $revisionRepo->expects(self::once())->method('save');

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        try {
            $response = $controller->importSpace($request, 'eng');
            self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        } finally {
            unlink($sourceDir . '/intro.md');
            rmdir($sourceDir);
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    public function testImportSpaceRejectsInvalidCsrf(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->importSpace(Request::create('/import', 'POST'), 'eng');
    }

    public function testAskAiPostReturnsAnswer(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $aiAssistant = $this->createMock(WikiAiAssistantInterface::class);
        $aiAssistant->method('isAvailable')->willReturn(true);
        $aiAssistant->method('ask')->willReturn(new WikiAiAnswer('Use the runbook.', []));

        $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $aiAssistant);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->askAi(Request::create('/ask', 'POST', [
            'q'      => 'How do I deploy?',
            'space'  => 'eng',
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_ai_ask'),
        ]));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('ai_ask', (string) $response->getContent());
    }

    public function testAskAiPostRejectsEmptyQuestion(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $aiAssistant = $this->createMock(WikiAiAssistantInterface::class);
        $aiAssistant->method('isAvailable')->willReturn(true);

        $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $aiAssistant);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->askAi(Request::create('/ask', 'POST', [
            'q'      => '   ',
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_ai_ask'),
        ]));

        self::assertStringContainsString('ai_ask', (string) $response->getContent());
    }

    public function testAskAiPostHandlesAssistantFailure(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $aiAssistant = $this->createMock(WikiAiAssistantInterface::class);
        $aiAssistant->method('isAvailable')->willReturn(true);
        $aiAssistant->method('ask')->willThrowException(new RuntimeException('AI down'));

        $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $aiAssistant);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->askAi(Request::create('/ask', 'POST', [
            'q'      => 'question',
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_ai_ask'),
        ]));

        self::assertStringContainsString('ai_ask', (string) $response->getContent());
    }

    public function testAskAiPostRejectsInvalidCsrf(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $aiAssistant = $this->createMock(WikiAiAssistantInterface::class);
        $aiAssistant->method('isAvailable')->willReturn(true);

        $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $aiAssistant);
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->askAi(Request::create('/ask', 'POST', ['q' => 'question']));
    }

    public function testAskAiWithSpaceSlug(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->askAi(Request::create('/ask?space=eng&q=deploy'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @param array<string, string> $fields
     */
    private function createWikiPageFormRequest(Container $container, string $actionTokenId, array $fields = []): Request
    {
        return Request::create('/submit', 'POST', [
            'wiki_page_form' => array_merge([
                'title'   => 'Title',
                'content' => '<p>Content</p>',
                '_token'  => WikiControllerContainerBuilder::csrfToken($container, 'wiki_page_form'),
            ], $fields),
            '_token' => WikiControllerContainerBuilder::csrfToken($container, $actionTokenId),
        ]);
    }

    private function createSearchService(array $rows): WikiSearchService
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($rows);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new WikiSearchService($em);
    }

    private function controller(
        ?WikiAccessCheckerInterface $accessChecker = null,
        ?WikiSpaceAccessResolverInterface $spaceAccessResolver = null,
        ?WikiPageRepositoryInterface $pageRepository = null,
        ?WikiPageRevisionRepositoryInterface $revisionRepository = null,
        ?WikiPageService $pageService = null,
        ?WikiSearchService $searchService = null,
        ?WikiAiAssistantInterface $aiAssistant = null,
        bool $importExportEnabled = true,
        int $maxUploadBytes = 52428800,
    ): WikiManageController {
        $accessChecker ??= $this->createConfiguredMock(WikiAccessCheckerInterface::class, [
            'canAccess'      => true,
            'canList'        => true,
            'canCreate'      => true,
            'canEdit'        => true,
            'canViewHistory' => true,
            'canArchive'     => true,
            'canAskAi'       => true,
            'canImport'      => true,
            'canExport'      => true,
        ]);

        $pageRepository ??= $this->createMock(WikiPageRepositoryInterface::class);
        $revisionRepository ??= $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $pageService ??= new WikiPageService(
            $pageRepository,
            $revisionRepository,
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
        );

        return new WikiManageController(
            $accessChecker,
            $spaceAccessResolver ?? $this->createMock(WikiSpaceAccessResolverInterface::class),
            $pageRepository,
            $revisionRepository,
            $pageService,
            new WikiPageTreeBuilder(),
            new WikiRevisionDiffService($revisionRepository),
            $searchService ?? new WikiSearchService($this->createMock(EntityManagerInterface::class)),
            $aiAssistant ?? new NullWikiAiAssistant(),
            $this->documentImporter($pageRepository, $pageService),
            $this->documentExporter($pageRepository),
            new WikiArchiveHelper(),
            $this->routes,
            $this->templates,
            null,
            ['tiptap_config' => 'notion'],
            ['enabled'       => $importExportEnabled, 'max_upload_bytes' => $maxUploadBytes],
        );
    }

    private function documentImporter(
        WikiPageRepositoryInterface $pageRepository,
        WikiPageService $pageService,
    ): WikiDocumentImporter {
        return new WikiDocumentImporter(
            new WikiArchiveHelper(),
            new WikiFormatDetector(new WikiFrontMatterParser()),
            new WikiDocumentTreeReader(new WikiFrontMatterParser()),
            new WikiMarkdownConverter(),
            $pageRepository,
            $pageService,
            new WikiSlugger(),
        );
    }

    private function documentExporter(WikiPageRepositoryInterface $pageRepository): WikiDocumentExporter
    {
        return new WikiDocumentExporter(
            $pageRepository,
            new WikiPageTreeBuilder(),
            new WikiMarkdownConverter(),
            new WikiFrontMatterParser(),
        );
    }
}
