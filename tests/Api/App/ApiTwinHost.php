<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanPolicyInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRateLimiterInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRequestAuthenticatorInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiUsersInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiDirectoryInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiManagementInterface;
use Nowo\WikiBundle\Api\Value\WikiApiNewWiki;
use Nowo\WikiBundle\Api\Value\WikiApiRateLimitState;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\Value\WikiApiWikiPatch;
use Nowo\WikiBundle\Api\WikiApiAuthFailure;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Security\WikiSpaceAccessPolicyInterface;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiRequestContext;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 1.32 (`WIKI_TEST_PORTS=twin`): the API test host through the framework-free twins only. Every answer is the
 * {@see ApiTestHost} answer, so the whole API suite proves the twins and their gates behave like the deprecated ports.
 * Spaces travel by id (no entity in a {@see WikiApiWiki}).
 */
final readonly class ApiTwinHost implements WikiApiRequestAuthenticatorInterface, WikiApiWikiDirectoryInterface, WikiApiPlanPolicyInterface, WikiSpaceAccessPolicyInterface, WikiApiWikiManagementInterface, WikiApiRateLimiterInterface, WikiApiUsersInterface
{
    public function __construct(
        private ApiTestHost $inner,
        private ManagerRegistry $doctrine,
    ) {
    }

    public function authenticate(WikiRequestContext $request): WikiApiPrincipal|WikiApiAuthFailure
    {
        $server = $request->credential === null ? [] : ['HTTP_AUTHORIZATION' => 'Bearer ' . $request->credential];
        $result = $this->inner->authenticate(Request::create($request->path, $request->method, [], [], [], $server));

        return $result instanceof WikiApiPrincipal
            ? WikiApiPrincipal::of($result->userRef(), $result->level, $result->where, $result->tokenId, $result->tokenName, $result->channel)
            : $result;
    }

    public function list(WikiApiPrincipal $principal, WikiApiWikiFilter $filter): array
    {
        return array_map(self::byId(...), $this->inner->list($principal, $filter));
    }

    public function find(WikiApiPrincipal $principal, string $slug): ?WikiApiWiki
    {
        $wiki = $this->inner->find($principal, $slug);

        return $wiki === null ? null : self::byId($wiki);
    }

    public function ofSpace(WikiApiPrincipal $principal, WikiSpaceSnapshot $space): ?WikiApiWiki
    {
        return $this->find($principal, $space->slug);
    }

    public function provision(WikiApiPrincipal $principal, WikiApiWiki $wiki): string
    {
        return $this->inner->provision($principal, $wiki)->getId();
    }

    public function ownerKey(WikiApiPrincipal $principal, WikiApiNewWiki $wiki): ?string
    {
        return $this->inner->ownerKey($principal, $wiki);
    }

    public function create(WikiApiPrincipal $principal, WikiApiNewWiki $wiki): WikiApiWiki
    {
        return self::byId($this->inner->create($principal, $wiki));
    }

    public function update(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiApiWikiPatch $patch): WikiApiWiki
    {
        return self::byId($this->inner->update($principal, $wiki, $patch));
    }

    public function archive(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiApiWiki
    {
        return self::byId($this->inner->archive($principal, $wiki));
    }

    public function unarchive(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiApiWiki
    {
        return self::byId($this->inner->unarchive($principal, $wiki));
    }

    public function consume(WikiApiPrincipal $principal): WikiApiRateLimitState
    {
        return $this->inner->consume($principal);
    }

    public function failedTokenState(string $clientIp): WikiApiRateLimitState
    {
        return $this->inner->failedTokenState($clientIp);
    }

    public function registerFailedToken(string $clientIp): void
    {
        $this->inner->registerFailedToken($clientIp);
    }

    public function apiId(object $user): ?string
    {
        return $this->inner->apiId($user);
    }

    public function allowsWiki(WikiUserRef $user, WikiApiWiki $wiki): bool
    {
        return $this->inner->allowsWiki(WikiUserRefs::securityUser($user), $wiki);
    }

    public function isGranted(WikiUserRef $user, WikiAction $action, WikiSpaceSnapshot $space, ?WikiPageSnapshot $page = null): bool
    {
        $manager      = $this->doctrine->getManager();
        $spaceEntity  = $manager->find(WikiSpace::class, $space->id);
        $pageEntity   = $page === null ? null : $manager->find(WikiPage::class, $page->id);
        $securityUser = WikiUserRefs::securityUser($user);

        return $spaceEntity instanceof WikiSpace && $securityUser instanceof UserInterface
            && $this->inner->isGranted($securityUser, $action, $spaceEntity, $pageEntity instanceof WikiPage ? $pageEntity : null);
    }

    /** The same wiki with its space named by id only, as a DDD host's directory answers. */
    private static function byId(WikiApiWiki $wiki): WikiApiWiki
    {
        return new WikiApiWiki($wiki->slug, null, $wiki->name, $wiki->description, $wiki->ownerType, $wiki->ownerId, $wiki->ownerKey, $wiki->archived, $wiki->restricted, $wiki->canOpen, $wiki->pageCount, $wiki->createdAt, $wiki->updatedAt, $wiki->spaceId);
    }
}
