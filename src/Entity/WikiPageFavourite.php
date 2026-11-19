<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\Uuid;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;

/**
 * 1.45 (`favourites.enabled`): a page one person starred. Per person (`user_id`, the user's `getId()` as a string, no
 * foreign key: the row is erased with `wiki:quick-access:erase-user`), deleted with its page. The list a person sees is
 * always filtered again for that person (tenant, space and page read rules); a row is never shown on its own.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_page_favourites')]
#[ORM\UniqueConstraint(name: 'wiki_page_favourite_unique', columns: ['user_id', 'page_id'])]
#[ORM\Index(name: 'wiki_page_favourite_user', columns: ['user_id', 'created_at'])]
class WikiPageFavourite
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * @param WikiPage $page the starred page
     * @param string $userId the person's id ({@see WikiUserId})
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: WikiPage::class)]
        #[ORM\JoinColumn(name: 'page_id', nullable: false, onDelete: 'CASCADE')]
        private WikiPage $page,
        #[ORM\Column(name: 'user_id', type: 'string', length: WikiStoredUserRef::MAX_LENGTH)]
        private string $userId,
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

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
