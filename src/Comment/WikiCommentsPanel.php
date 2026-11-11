<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Comment;

use Nowo\MilkdownEditorBundle\ProfileRegistry;
use Nowo\WikiBundle\Controller\WikiCommentController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Form\WikiCommentType;
use Nowo\WikiBundle\Panel\WikiPanelStateStoreInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function array_slice;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function strtok;

/**
 * The comments panel of the read and edit views (1.16, `comments.enabled`): whether the viewer gets the toggle and
 * the panel ({@see WikiAction::ViewComments}), its open state (`?comments=1|0`, else the user's
 * remembered state), the badge count, the no-JavaScript URLs and the Milkdown options that mount the thread
 * component into the panel (`panel: container`). Viewers without the right get null: no toggle, no panel, no count.
 *
 * @phpstan-type Panel array{id: string, open: bool, count: int, shortcut: string|null, toggle_href: string, close_href: string, comments_url: string, csrf_token: string, state_url: string|null, state_token: string|null, mounted: bool, edit_href: string|null, truncated: bool, reload_href: string|null, can: array{view: bool, comment: bool, resolve: bool, moderate: bool}, page_threads: list<array<string, mixed>>, form: FormView|null, moderation: bool, error_notices: bool, drawer_below: string, persist: string|null, open_requested: bool, layout: string, thread_list: string}
 */
final readonly class WikiCommentsPanel
{
    /** Id of the panel element (`aria-controls` of the toggle; the heading is `<id>-title`). */
    public const ID = 'nowo-wiki-comments';

    /** 1.29: localStorage key of the open state when there is no server store (no session). */
    public const PERSIST_KEY = 'wiki-comments';

    /** 1.29 `comments.dock_min_width` values (UiKit side panel `drawer_below`). */
    public const DOCK_WIDTHS = ['md', 'lg', 'xl'];

    /** Selector of the element the Milkdown thread component mounts into. */
    public const MOUNT = '[data-nowo-wiki-comments-mount]';

    /**
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes
     * @param string|null $shortcut `comments.shortcut` (e.g. `Alt+Shift+C`; null: none)
     * @param ProfileRegistry|null $profiles Milkdown profiles (the features the comments feature is added to)
     * @param FormFactoryInterface|null $forms builds the no-JavaScript comment form
     * @param bool $moderationPanel 1.21 `comments.moderation_panel`: "Moderate threads" for users with ModerateComments
     * @param bool $errorNotices 1.21 `comments.error_notices`: own texts for writes refused with 422 / 429
     * @param string $dockMinWidth 1.29 `comments.dock_min_width`: md | lg | xl (UiKit `drawer_below`; anything else: md)
     * @param bool $sessionStore 1.29: the state store is the bundle's session store (without a session the panel
     *                           keeps its state in localStorage, UiKit `persist`)
     * @param bool $overlay 1.31 `comments.layout: overlay`: the panel is a drawer at every width (UiKit side panel
     *                      `layout: overlay`) and starts closed (the remembered state is not applied)
     * @param bool $uikitThreadList 1.31 `comments.thread_list: uikit`: wiki.js gives the thread component's list UiKit
     *                              rows, plural count badges and a UiKit checkbox
     */
    public function __construct(
        private WikiCommentService $comments,
        private WikiPanelStateStoreInterface $state,
        private UrlGeneratorInterface $urlGenerator,
        private CsrfTokenManagerInterface $csrf,
        private array $routes,
        private ?string $shortcut = null,
        private ?ProfileRegistry $profiles = null,
        private ?FormFactoryInterface $forms = null,
        private bool $readViewSource = false,
        private bool $moderationPanel = false,
        private bool $errorNotices = false,
        private string $dockMinWidth = 'md',
        private bool $sessionStore = false,
        private bool $overlay = false,
        private bool $uikitThreadList = false,
    ) {
    }

    /**
     * `comments.read_view_source` (default false): whether viewers who may not edit the page get its Markdown source in
     * the read view (the read-only Milkdown mount with inline highlights). Off, they get the server-rendered list.
     */
    public function readViewSource(): bool
    {
        return $this->readViewSource;
    }

    /**
     * The panel of a page for the viewer, or null without {@see WikiAction::ViewComments}.
     *
     * @param Request|null $request the current request (`?comments=1|0` overrides the remembered state)
     * @param string|null $editHref the page's edit URL when the viewer may edit it (the server list then links its
     *                              inline threads to the editor; null: a plain hint)
     * @param bool $mounted the Milkdown thread component mounts into the panel (the edit view, or a read view that
     *                      carries the page source); false: the server renders every open thread (quotes of the inline
     *                      ones included) and the comment form, and no Markdown is sent
     *
     * @return Panel|null
     */
    public function panel(?Request $request, UserInterface $user, WikiSpace $space, WikiPage $page, bool $mounted = true, ?string $editHref = null): ?array
    {
        $can = $this->comments->rights($user, $space, $page);
        if (!$can['view']) {
            return null;
        }

        $query = $request?->query->get('comments');
        // 1.31 (comments.layout: overlay): a drawer is opened on demand; the remembered state (a desktop column choice)
        // is not applied. ?comments=1 still reads the list and opens the drawer after load (open_requested).
        $open    = $query === '1' ? true : ($query === '0' || $this->overlay ? false : $this->state->isOpen($user, WikiPanelStateStoreInterface::COMMENTS));
        $here    = $request instanceof Request ? (string) strtok($request->getRequestUri(), '?') : '';
        $state   = $this->routes['space_panel_state']['name'] ?? null;
        $threads = $open ? $this->comments->pageThreads($user, $space, $page, !$mounted, WikiCommentService::LIST_CAP + 1) : [];

        return [
            'id'           => self::ID,
            'open'         => $open,
            'count'        => $this->comments->openCount($page),
            'shortcut'     => $this->shortcut,
            'toggle_href'  => $here . '?comments=' . ($open ? '0' : '1'),
            'close_href'   => $here . '?comments=0',
            'comments_url' => $this->commentsUrl($space, $page),
            'csrf_token'   => $this->csrf->getToken(WikiCommentService::csrfTokenId($page))->getValue(),
            'state_url'    => $state !== null ? $this->urlGenerator->generate($state, ['spaceSlug' => $space->getSlug()]) : null,
            'state_token'  => $state !== null ? $this->csrf->getToken(WikiCommentController::panelTokenId($space))->getValue() : null,
            'can'          => $can,
            'mounted'      => $mounted,
            // Inline threads of the server list are replied to from the editor (opened on the comments panel).
            'edit_href' => $editHref,
            // The server list (the no-JavaScript fallback, or the panel's content when nothing mounts: every open
            // thread with its quote) is read only when the panel is rendered open, and capped (audit EI-L4). A closed
            // server-list panel reloads the page with ?comments=1 when it is opened in place (wiki.js, `reload_href`).
            'page_threads' => array_slice($threads, 0, WikiCommentService::LIST_CAP),
            'truncated'    => count($threads) > WikiCommentService::LIST_CAP,
            'reload_href'  => !$mounted && !$open ? $here . '?comments=1' : null,
            'form'         => $open && $can['comment'] ? $this->form($space, $page) : null,
            // 1.21: the panel extras (each off by default); the moderation list only for moderators.
            'moderation'    => $this->moderationPanel && $can['moderate'],
            'error_notices' => $this->errorNotices,
            // 1.29: the width from which the panel docks (a drawer below it), the localStorage fallback without a
            // session, and ?comments=1 (a no-JavaScript link or the reload of a server list): opened as a drawer
            // after load also where a server-open lg / xl panel starts closed.
            'drawer_below'   => in_array($this->dockMinWidth, self::DOCK_WIDTHS, true) ? $this->dockMinWidth : 'md',
            'persist'        => $this->sessionStore && $request?->hasSession() !== true ? self::PERSIST_KEY : null,
            'open_requested' => $query === '1',
            // 1.31 comments.layout: inline | overlay (UiKit side panel layout).
            'layout' => $this->overlay ? 'overlay' : 'inline',
            // 1.31 comments.thread_list: plain | uikit.
            'thread_list' => $this->uikitThreadList ? 'uikit' : 'plain',
        ];
    }

    /**
     * Milkdown options (MilkdownEditorType / `nowo_milkdown_view()`) with the comments feature mounted into the
     * panel; the editor's options are returned unchanged without a panel.
     *
     * @param array<string, mixed> $options the editor options of the page
     * @param Panel|null $panel the viewer's panel ({@see self::panel()})
     * @param bool $live with co-editing: live updates on the page's private comments topic
     *
     * @return array<string, mixed>
     */
    public function editorOptions(array $options, ?array $panel, WikiPage $page, bool $live = false): array
    {
        if ($panel === null) {
            return $options;
        }

        $features = is_array($options['features'] ?? null) ? array_values($options['features']) : $this->profileFeatures($options['profile'] ?? null);
        if (!in_array('comments', $features, true)) {
            $features[] = 'comments';
        }
        $options['features'] = $features;
        $options['comments'] = [
            'url'        => $panel['comments_url'],
            'csrf_token' => $panel['csrf_token'],
            'can_create' => $panel['can']['comment'],
            'panel'      => 'container',
            'container'  => self::MOUNT,
            'topic'      => $live ? $this->comments->topic($page) : null,
        ];

        return $options;
    }

    private function form(WikiSpace $space, WikiPage $page): ?FormView
    {
        return $this->forms?->createNamed('', WikiCommentType::class, null, [
            'csrf_token_id' => WikiCommentService::csrfTokenId($page),
            'action'        => $this->commentsUrl($space, $page) . '/new',
        ])->createView();
    }

    /**
     * @return list<string>
     */
    private function profileFeatures(mixed $profile): array
    {
        if (!$this->profiles instanceof ProfileRegistry) {
            return [];
        }

        return array_values($this->profiles->get(is_string($profile) ? $profile : null)['features']);
    }

    private function commentsUrl(WikiSpace $space, WikiPage $page): string
    {
        return $this->urlGenerator->generate($this->routes['page_comments']['name'], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug(), 'path' => '']);
    }
}
