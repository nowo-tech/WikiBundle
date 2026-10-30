<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\Link\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\ValueObject\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A link from a page to a host object (an issue, a task, …) identified by type and id (1.9).
 *
 * The host describes and authorizes the objects through
 * {@see WikiObjectLinkResolverInterface}; deleting the page deletes its links.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_page_links')]
#[ORM\UniqueConstraint(name: 'wiki_page_link_unique', columns: ['page_id', 'object_type', 'object_id'])]
#[ORM\Index(name: 'wiki_page_link_object', columns: ['object_type', 'object_id'])]
class WikiPageLink
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * @param WikiPage $page linked page
     * @param string $objectType host object type (e.g. "issue")
     * @param string $objectId host object id
     * @param object|null $createdBy user who linked it
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: WikiPage::class)]
        #[ORM\JoinColumn(name: 'page_id', nullable: false, onDelete: 'CASCADE')]
        private WikiPage $page,
        #[ORM\Column(name: 'object_type', type: 'string', length: 64)]
        private string $objectType,
        #[ORM\Column(name: 'object_id', type: 'string', length: 64)]
        private string $objectId,
        #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
        private ?object $createdBy = null,
    ) {
        $this->id        = Uuid::generate()->toString();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPage(): WikiPage
    {
        return $this->page;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function getObjectId(): string
    {
        return $this->objectId;
    }

    public function getCreatedBy(): ?object
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
