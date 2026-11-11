<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Panel;

use Nowo\WikiBundle\Controller\WikiCommentController;
use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function strtok;

/**
 * The page tree rail (1.29, `web_ui.tree_rail`): whether the tree card of the space home and the page view renders
 * collapsed to a thin rail (`?tree=rail|full`, else the user's remembered state in the panel state store), the
 * no-JavaScript link to the other state and the store endpoint that UiKit `js/nowo-ui-rail.js` posts to. Stateless:
 * every value is computed per call (worker mode, kernel reset false).
 *
 * @phpstan-type Rail array{id: string, body_id: string, collapsed: bool, href: string, state_url: string|null, state_token: string|null, state_panel: string, persist: string|null}
 */
final readonly class WikiTreeRail
{
    /** Id of the tree card (the rail host). */
    public const ID = 'nowo-wiki-tree';

    /** Id of the part that collapses (`aria-controls` of the toggle). */
    public const BODY_ID = 'nowo-wiki-tree-body';

    /** localStorage key of the fallback when there is no server store (no session). */
    public const PERSIST_KEY = 'wiki-tree';

    /**
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes
     * @param bool $sessionStore the store is the bundle's session store (without a session: the localStorage fallback)
     */
    public function __construct(
        private WikiPanelStateStoreInterface $state,
        private UrlGeneratorInterface $urlGenerator,
        private CsrfTokenManagerInterface $csrf,
        private array $routes,
        private bool $sessionStore = true,
    ) {
    }

    /**
     * The rail of a space's tree for the viewer.
     *
     * @param Request|null $request the current request (`?tree=rail|full` overrides the remembered state)
     *
     * @return Rail
     */
    public function rail(?Request $request, UserInterface $user, WikiSpace $space): array
    {
        $query     = $request?->query->get('tree');
        $collapsed = $query === 'rail' ? true : ($query === 'full' ? false : $this->state->isOpen($user, WikiPanelStateStoreInterface::TREE_RAIL));
        $here      = $request instanceof Request ? (string) strtok($request->getRequestUri(), '?') : '';
        $route     = $this->routes['space_panel_state']['name'] ?? null;
        $noSession = $this->sessionStore && $request?->hasSession() !== true;

        return [
            'id'          => self::ID,
            'body_id'     => self::BODY_ID,
            'collapsed'   => $collapsed,
            'href'        => $here . '?tree=' . ($collapsed ? 'full' : 'rail'),
            'state_url'   => $route !== null && !$noSession ? $this->urlGenerator->generate($route, ['spaceSlug' => $space->getSlug()]) : null,
            'state_token' => $route !== null && !$noSession ? $this->csrf->getToken(WikiCommentController::panelTokenId($space))->getValue() : null,
            'state_panel' => WikiPanelStateStoreInterface::TREE_RAIL,
            // Without a server store the browser keeps the state (UiKit persist, applied before the first paint).
            'persist' => $noSession ? self::PERSIST_KEY : null,
        ];
    }
}
