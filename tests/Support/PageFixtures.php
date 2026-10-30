<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\TiptapEditorBundle\Form\TiptapEditorType;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Form\WikiAiAskType;
use Nowo\WikiBundle\Form\WikiImportType;
use Nowo\WikiBundle\Form\WikiPageFormType;
use Nowo\WikiBundle\Form\WikiPostActionType;
use Nowo\WikiBundle\Form\WikiSearchType;
use Nowo\WikiBundle\Link\WikiLinkedObject;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;

use function http_build_query;

/**
 * Page contexts of the REQ-UI-003 page contract test: the view models come from {@see WikiPageFactory} (the service
 * the controller uses), the FormViews from the real form types (CSRF), the other variables mirror the controller.
 */
final class PageFixtures
{
    /** Routes of the default configuration (route key => name). */
    public const ROUTE_KEYS = ['index', 'space', 'page_view', 'page_edit', 'page_new', 'page_history', 'page_diff', 'page_archive', 'page_unarchive', 'page_restore', 'search', 'ai_ask', 'space_export', 'space_import'];

    /**
     * @return array<string, mixed>
     */
    public static function index(bool $empty = false, bool $ai = true, ?WikiPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $spaces = $empty ? [] : [self::space('engineering', 'Engineering'), self::space('product', 'Product')];

        return $factory->context([
            'page' => $factory->index(
                $spaces,
                Pagination::fromTotal($empty ? 0 : 3, 1, 2, [2], 2, 'nowo_wiki_index'),
                self::searchForm(['engineering' => 'Engineering', 'product' => 'Product']),
                $ai,
            ),
            'ai_form'         => $ai ? self::aiForm(['autofocus' => true]) : null,
            'routes'          => self::routes(),
            'dashboard_route' => null,
            'can_create'      => true,
            'can_ask_ai'      => $ai,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function spaceHome(bool $full = true, ?WikiPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $space    = self::space('engineering', 'Engineering');
        [$root]   = self::pages($space);
        $archived = new WikiPage($space, 'old-notes', 'Old notes');
        $archived->archive();

        return $factory->context([
            'page'             => $factory->space($space, ['create' => $full, 'import' => $full, 'export' => $full]),
            'tree'             => $factory->tree($space, self::treeNodes($space)),
            'tree_truncated'   => $full,
            'max_tree_pages'   => 500,
            'tree_search_href' => '/nowo_wiki_search?space=engineering',
            'archived'         => $full ? $factory->archived($space, [$archived, $root], [$archived->getId() => self::postForm('wiki_unarchive_' . $archived->getId())]) : [],
            'search_form'      => self::searchForm(null, false),
            'import_form'      => $full ? self::formFactory()->createNamed('', WikiImportType::class, null, ['csrf_token_id' => 'wiki_import_1'])->createView() : null,
            'routes'           => self::routes(),
            'dashboard_route'  => 'app_dashboard',
            'can_create'       => $full,
            'can_import'       => $full,
            'can_export'       => $full,
            'permissions'      => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function pageView(bool $full = true, ?WikiPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $space    = self::space('engineering', 'Engineering');
        [, $page] = self::pages($space);
        $page->setCurrentRevision(new WikiPageRevision($page, 3, '<h2>Deploy</h2><p>Run <code>make deploy</code>.</p>', new TestUser()));

        return $factory->context([
            'page'             => $factory->pageView($space, $page, ['edit' => $full, 'history' => $full], $full ? self::postForm('wiki_archive_' . $page->getId()) : null),
            'content_html'     => $page->getCurrentRevision()?->getContentHtml() ?? '',
            'tree'             => $factory->tree($space, self::treeNodes($space), $page->getSlug()),
            'tree_truncated'   => false,
            'max_tree_pages'   => 500,
            'tree_search_href' => null,
            'used_in'          => $full ? $factory->usedIn(['ticket' => [new WikiLinkedObject('ticket', '42', 'Ticket #42', '/tickets/42', 'open'), new WikiLinkedObject('ticket', '43', 'Ticket #43', '/tickets/43')]]) : [],
            'routes'           => self::routes(),
            'dashboard_route'  => null,
            'can_edit'         => $full,
            'can_history'      => $full,
            'can_archive'      => $full,
            'permissions'      => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function pageForm(bool $edit = true, ?WikiPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $space    = self::space('engineering', 'Engineering');
        [, $page] = self::pages($space);
        $form     = self::formFactory()->create(WikiPageFormType::class, new WikiPageFormData($edit ? $page->getTitle() : '', $edit ? '<p>Body</p>' : ''), [
            'csrf_token_id' => $edit ? 'wiki_edit_1' : 'wiki_new_1',
        ])->createView();

        return $factory->context([
            'page'            => $factory->pageForm($space, $edit ? $page : null, $form),
            'routes'          => self::routes(),
            'dashboard_route' => null,
            'csrf_token_id'   => $edit ? 'wiki_edit_1' : 'wiki_new_1',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function history(bool $empty = false, ?WikiPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $space     = self::space('engineering', 'Engineering');
        [, $page]  = self::pages($space);
        $user      = new TestUser();
        $revisions = [];
        if (!$empty) {
            foreach ([3, 2] as $number) {
                $revisions[] = new WikiPageRevision($page, $number, '<p>v' . $number . '</p>', $user);
            }
            $page->setCurrentRevision($revisions[0]);
        }
        $older = $empty ? null : new WikiPageRevision($page, 1, '<p>v1</p>', $user);

        return $factory->context([
            'page' => $factory->history(
                $space,
                $page,
                $revisions,
                $older,
                $empty ? [] : [2 => self::postForm('wiki_restore_' . $page->getId())],
                Pagination::fromTotal($empty ? 0 : 3, 1, 2, [2], 2, 'nowo_wiki_page_history', [], ['spaceSlug' => 'engineering', 'pageSlug' => 'deploy']),
            ),
            'routes'          => self::routes(),
            'dashboard_route' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function diff(bool $empty = false, ?WikiPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $space    = self::space('engineering', 'Engineering');
        [, $page] = self::pages($space);

        return $factory->context([
            'page' => $factory->diff($space, $page, 1, 3),
            'diff' => $empty ? [] : [
                ['kind' => 'same', 'line' => 'Deploy'],
                ['kind' => 'remove', 'line' => 'Run make release.'],
                ['kind' => 'add', 'line' => 'Run make deploy.'],
            ],
            'routes'          => self::routes(),
            'dashboard_route' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function search(string $query = 'deploy', bool $global = true, ?WikiPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $space         = self::space('engineering', 'Engineering');
        [$root, $page] = self::pages($space);
        $hits          = $query === '' ? [] : [
            ['page' => $page, 'space' => $space, 'excerpt' => '…run make deploy…'],
            ['page' => $root, 'space' => $space, 'excerpt' => 'Welcome'],
        ];

        return $factory->context([
            'page'            => $factory->search(self::searchForm(['engineering' => 'Engineering']), $query === 'nothing' ? [] : $hits, $query, $global),
            'query'           => $query,
            'global_search'   => $global,
            'routes'          => self::routes(),
            'dashboard_route' => 'app_dashboard',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function aiAsk(bool $answered = true, ?string $error = null, ?WikiPageFactory $factory = null): array
    {
        $factory ??= self::factory();
        $space    = self::space('engineering', 'Engineering');
        [, $page] = self::pages($space);

        return $factory->context([
            'page'            => $factory->aiAsk(self::aiForm(), $answered),
            'question'        => $answered ? 'How do we deploy?' : '',
            'answer'          => $answered ? "Run make deploy.\nThen check the logs." : null,
            'sources'         => $answered ? $factory->hits([['page' => $page, 'space' => $space, 'excerpt' => '…run make deploy…']]) : [],
            'error'           => $error,
            'routes'          => self::routes(),
            'dashboard_route' => null,
            'csrf_token_id'   => WikiAiAskType::CSRF_TOKEN_ID,
        ]);
    }

    public static function factory(?WikiPages $pages = null, ?string $dashboardRoute = null): WikiPageFactory
    {
        return new WikiPageFactory(self::urlGenerator(), self::routes(), $pages ?? new WikiPages(), $dashboardRoute);
    }

    /**
     * @return array<string, array{path: string, name: string}>
     */
    public static function routes(): array
    {
        $routes = [];
        foreach (self::ROUTE_KEYS as $key) {
            $routes[$key] = ['path' => '/' . $key, 'name' => 'nowo_wiki_' . $key];
        }

        return $routes;
    }

    public static function space(string $slug, string $name): WikiSpace
    {
        return new WikiSpace($slug, $name, WikiSpaceOwnerScope::Team, 'team-1');
    }

    /**
     * Welcome (root) > Deploy > Rollback, plus Glossary (root).
     *
     * @return array{0: WikiPage, 1: WikiPage, 2: WikiPage, 3: WikiPage}
     */
    public static function pages(WikiSpace $space): array
    {
        $root     = new WikiPage($space, 'welcome', 'Welcome');
        $page     = new WikiPage($space, 'deploy', 'Deploy', $root);
        $child    = new WikiPage($space, 'rollback', 'Rollback', $page);
        $glossary = new WikiPage($space, 'glossary', 'Glossary');

        return [$root, $page, $child, $glossary];
    }

    /**
     * @return list<array{page: WikiPage, children: list<mixed>}>
     */
    public static function treeNodes(WikiSpace $space): array
    {
        [$root, $page, $child, $glossary] = self::pages($space);

        return [
            ['page' => $root, 'children' => [['page' => $page, 'children' => [['page' => $child, 'children' => []]]]]],
            ['page' => $glossary, 'children' => []],
        ];
    }

    /**
     * Nameless POST form with the CSRF token (archive / unarchive / restore), as the controller builds it.
     */
    public static function postForm(string $tokenId): FormView
    {
        return self::formFactory()->createNamed('', WikiPostActionType::class, null, ['csrf_token_id' => $tokenId])->createView();
    }

    /**
     * @param array<string, string>|null $spaces slug => name (null = hidden space field)
     */
    public static function searchForm(?array $spaces, bool $queryRequired = true): FormView
    {
        return self::formFactory()->createNamed('', WikiSearchType::class, [], [
            'action'         => '/nowo_wiki_search',
            'space_choices'  => $spaces,
            'query_required' => $queryRequired,
        ])->createView();
    }

    /**
     * @param array<string, mixed> $questionAttr
     */
    public static function aiForm(array $questionAttr = []): FormView
    {
        return self::formFactory()->createNamed('', WikiAiAskType::class, [], [
            'action'        => '/nowo_wiki_ai_ask',
            'space_choices' => ['engineering' => 'Engineering'],
            'question_attr' => $questionAttr,
        ])->createView();
    }

    public static function formFactory(): FormFactoryInterface
    {
        $types = [
            FormKitTestSupport::withMerger(new WikiPageFormType()),
            FormKitTestSupport::withMerger(new WikiSearchType()),
            FormKitTestSupport::withMerger(new WikiAiAskType()),
            FormKitTestSupport::withMerger(new WikiImportType()),
            new TiptapEditorType(['notion' => ['toolbar' => true, 'min_height' => '200px', 'form_theme' => 'bootstrap_5_layout', 'debug' => false, 'variant' => 'notion', 'theme' => 'light']], 'notion'),
        ];

        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new CsrfExtension(new class implements CsrfTokenManagerInterface {
                public function getToken(string $tokenId): CsrfToken
                {
                    return new CsrfToken($tokenId, 'token-' . $tokenId);
                }

                public function refreshToken(string $tokenId): CsrfToken
                {
                    return $this->getToken($tokenId);
                }

                public function removeToken(string $tokenId): ?string
                {
                    return null;
                }

                public function isTokenValid(CsrfToken $token): bool
                {
                    return true;
                }
            }))
            ->addExtension(new PreloadedExtension($types, []))
            ->getFormFactory();
    }

    public static function urlGenerator(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/' . $name . ($parameters === [] ? '' : '?' . http_build_query($parameters));
            }

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }
        };
    }
}
