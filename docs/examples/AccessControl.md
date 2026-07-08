# Access control examples — WikiBundle

Integrators replace `WikiAccessCheckerInterface` and optionally `WikiTeamMembershipResolverInterface`.

## Team-based tool access (DevKit pattern)

```php
final readonly class DevKitWikiAccessChecker implements WikiAccessCheckerInterface
{
    public function __construct(
        private TeamAccessChecker $teams,
        private Security $security,
    ) {}

    public function canAccess(?UserInterface $user = null): bool
    {
        return $this->security->isGranted('ROLE_ADMIN')
            || $this->teams->canAccessTool(ToolSlug::Wiki, $user);
    }

    // canCreate / canEdit / canList / canViewHistory / canArchive …
}
```

```yaml
# config/packages/nowo_wiki.yaml
nowo_wiki:
    user_class: App\Entity\User
    space_scope: team
    team_membership_resolver: App\Infrastructure\Security\DevKitWikiTeamMembershipResolver
    security:
        access_checker: App\Infrastructure\Security\DevKitWikiAccessChecker
```

## Role-based defaults

When `security.access_checker` is omitted, `ConfigurableWikiAccessChecker` uses Symfony roles from `nowo_wiki.security.*_roles`.

## Events

Listen to `nowo_wiki.page.saved` and `nowo_wiki.page.archived` for audit logging or search indexing.
