<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use BackedEnum;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use InvalidArgumentException;
use LogicException;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Space\WikiSpaceLifecycleInterface;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\Util\WikiSlugger;
use Nowo\WikiBundle\Util\WikiSpaceSlugRule;
use Nowo\WikiBundle\ValueObject\WikiNewSpace;
use Nowo\WikiBundle\ValueObject\WikiOwnerScopeId;
use Nowo\WikiBundle\ValueObject\WikiSpaceIcon;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

use function mb_strlen;
use function trim;

/**
 * {@see WikiSpaceOperationsInterface} (1.36.0). Since 1.55 a space is created by {@see self::createSpaceFrom()} on the
 * wiki's transaction runner (both `createSpace()` and `createSpaceFrom()`): the row is written by one `INSERT`
 * statement on the wiki's connection, never by a flush, so a slug taken by a concurrent request fails that statement on
 * the unique key (`wiki_space_scope_slug`) and answers {@see WikiSlugTakenException} without closing, clearing or
 * flushing any entity manager (REQ-RUNTIME-003 rules 2 and 4). Stateless: one instance serves every request of a
 * long-running worker.
 */
final readonly class WikiSpaceOperations implements WikiSpaceOperationsInterface
{
    /** Longest space name (the column). */
    private const MAX_NAME_LENGTH = 255;

    /**
     * @param WikiSpaceServiceInterface $spaceService slug lookups (since 1.55 spaces are no longer created through it)
     * @param WikiSpaceRepositoryInterface $spaces space lookups
     * @param WikiTransactionRunner $runner 1.55: the wiki's runner (its manager and connection)
     * @param WikiSpaceSlugRule $slugRule 1.55: the space slug rule (the bundle's, or a tenant path's)
     * @param WikiSlugger $slugger the slug of a name, for {@see self::createSpace()} without a slug
     * @param list<string> $iconPrefixes 1.55: icon prefixes of the space settings (`space_icon`, emoji included when on)
     * @param WikiSpaceLifecycleInterface|null $lifecycle 1.56: the space changes ({@see self::setSpaceIcon()})
     */
    public function __construct(
        private WikiSpaceServiceInterface $spaceService,
        private WikiSpaceRepositoryInterface $spaces,
        private WikiTransactionRunner $runner,
        private WikiSpaceSlugRule $slugRule = new WikiSpaceSlugRule(),
        private WikiSlugger $slugger = new WikiSlugger(),
        private array $iconPrefixes = WikiSpaceIcon::DEFAULT_PREFIXES,
        private ?WikiSpaceLifecycleInterface $lifecycle = null,
    ) {
    }

    public function findSpace(string $spaceId): ?WikiSpaceSnapshot
    {
        $space = $this->spaces->findById($spaceId);

        return $space instanceof WikiSpace && $space->getId() === $spaceId ? WikiSnapshots::space($space) : null;
    }

    public function createSpace(string $name, WikiSpaceOwnerScope $ownerScopeType, string $ownerScopeId, ?string $slug = null): WikiSpaceSnapshot
    {
        $row = $this->create(new WikiNewSpace($slug ?? $this->slugger->slugify($name), $name, $ownerScopeId, $ownerScopeType));

        // As stored (the database's time precision); the values written when another request deleted it meanwhile.
        return $this->findSpace($row->getId()) ?? WikiSnapshots::space($row);
    }

    public function createSpaceFrom(WikiNewSpace $space): string
    {
        return $this->create($space)->getId();
    }

    /**
     * Checks the values, then writes the row in one unit of the wiki's runner.
     *
     * @return WikiSpace the row written (not managed by any entity manager)
     */
    private function create(WikiNewSpace $space): WikiSpace
    {
        $name = trim($space->name);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new InvalidArgumentException('Invalid space name: 1 to 255 characters.');
        }
        // 1.31.1 (house rule LQ): an owner id that a collation could confuse with another one is refused, never trimmed.
        if (!WikiOwnerScopeId::isUsable($space->ownerScopeId)) {
            throw new InvalidArgumentException('Invalid space owner id: no leading or trailing whitespace, control or format characters, at most 64 characters.');
        }
        if (!$this->slugRule->isValid($space->slug)) {
            throw new WikiInvalidSlugException('Invalid space slug.');
        }
        $row = new WikiSpace($space->slug, $name, $space->ownerScopeType, $space->ownerScopeId);
        if ($space->icon !== null) {
            if (!WikiSpaceIcon::isValid($space->icon, $this->iconPrefixes)) {
                throw new WikiInvalidIconException('Invalid space icon.');
            }
            $row->setIcon($space->icon, $this->iconPrefixes);
        }

        try {
            // @igor-ignore - The closure runs at once inside the unit of work and is not kept: the runner holds no state.
            $this->runner->runOnConnection(function () use ($row): void {
                // The usual answer without a failed statement (on PostgreSQL a failed statement aborts the transaction).
                if ($this->spaces->findBySlug($row->getOwnerScopeType(), $row->getOwnerScopeId(), $row->getSlug()) instanceof WikiSpace) {
                    throw new WikiSlugTakenException('Space slug already exists for this owner.');
                }
                $this->insert($row);
            });
        } catch (UniqueConstraintViolationException $exception) { // thrown by the INSERT inside the unit
            // Another request took the slug between the check and the insert: nothing was written.
            throw new WikiSlugTakenException('Space slug already exists for this owner.', 0, $exception);
        }

        return $row;
    }

    public function setSpaceIcon(string $spaceId, ?string $icon, ?WikiUserRef $actor = null, ?int $expectedVersion = null): WikiSpaceSnapshot
    {
        $lifecycle = $this->lifecycle ?? throw new LogicException('WikiSpaceOperations::setSpaceIcon() needs the space lifecycle (the container always wires it).');

        // @igor-ignore - A call to the stateless lifecycle service (it writes the space row in its own unit), not a mutation of service state.
        return $lifecycle->setSpaceIcon($spaceId, $icon, $actor, $expectedVersion);
    }

    public function findSpaceBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpaceSnapshot
    {
        $space = $this->spaceService->findBySlug($scopeType, $ownerScopeId, $slug);

        // The repository already re-checks owner, scope and slug (1.31.1); checked again here, byte for byte.
        return $space instanceof WikiSpace && $space->getOwnerScopeType() === $scopeType && $space->getOwnerScopeId() === $ownerScopeId && $space->getSlug() === $slug
            ? WikiSnapshots::space($space)
            : null;
    }

    /**
     * The row as one `INSERT` of every mapped column (attribute or XML mapping, table prefix and quoting of the wiki's
     * manager): the entity never enters a unit of work, so a refused insert leaves every manager as it was.
     */
    private function insert(WikiSpace $row): void
    {
        $manager  = $this->runner->manager();
        $metadata = $manager->getClassMetadata(WikiSpace::class);
        $platform = $manager->getConnection()->getDatabasePlatform();
        $quotes   = $manager->getConfiguration()->getQuoteStrategy();
        $data     = [];
        $types    = [];
        foreach ($metadata->getFieldNames() as $field) {
            $column         = $quotes->getColumnName($field, $metadata, $platform);
            $value          = $metadata->getFieldValue($row, $field);
            $data[$column]  = $value instanceof BackedEnum ? $value->value : $value;
            $types[$column] = $metadata->getTypeOfField($field) ?? 'string';
        }
        $manager->getConnection()->insert($quotes->getTableName($metadata, $platform), $data, $types);
    }
}
