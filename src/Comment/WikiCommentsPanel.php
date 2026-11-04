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

use function array_values;
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
 * @phpstan-type Panel array{id: string, open: bool, count: int, shortcut: string|null, toggle_href: string, close_href: string, comments_url: string, csrf_token: string, state_url: string|null, state_token: string|null, can: array{view: bool, comment: bool, resolve: bool, moderate: bool}, page_threads: list<array<string, mixed>>, form: FormView|null}
 */
final readonly class WikiCommentsPanel
{
    /** Id of the panel element (`aria-controls` of the toggle; the heading is `<id>-title`). */
    public const ID = 'nowo-wiki-comments';

    /** Selector of the element the Milkdown thread component mounts into. */
    public const MOUNT = '[data-nowo-wiki-comments-mount]';

    /**
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes
     * @param string|null $shortcut `comments.shortcut` (e.g. `Alt+Shift+C`; null: none)
     * @param ProfileRegistry|null $profiles Milkdown profiles (the features the comments feature is added to)
     * @param FormFactoryInterface|null $forms builds the no-JavaScript comment form
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
    ) {
    }

    /**
     * The panel of a page for the viewer, or null without {@see WikiAction::ViewComments}.
     *
     * @param Request|null $request the current request (`?comments=1|0` overrides the remembered state)
     *
     * @return Panel|null
     */
    public function panel(?Request $request, UserInterface $user, WikiSpace $space, WikiPage $page): ?array
    {
        $can = $this->comments->rights($user, $space, $page);
        if (!$can['view']) {
            return null;
        }

        $query = $request?->query->get('comments');
        $open  = $query === '1' ? true : ($query === '0' ? false : $this->state->isOpen($user, WikiPanelStateStoreInterface::COMMENTS));
        $here  = $request instanceof Request ? (string) strtok($request->getRequestUri(), '?') : '';
        $state = $this->routes['space_panel_state']['name'] ?? null;

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
            // The no-JavaScript list (page-level threads) is only read when the panel is rendered open.
            'page_threads' => $open ? $this->comments->pageThreads($user, $space, $page) : [],
            'form'         => $open && $can['comment'] ? $this->form($space, $page) : null,
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
