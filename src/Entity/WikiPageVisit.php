<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\Uuid;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;

/**
 * 1.45 (`recents.enabled`): the last time one person opened a page. One row per person and page, at most
 * `recents.keep` rows per person (older ones are deleted on the next visit), deleted with its page. The list a person
 * sees is always filtered again for that person (tenant, space and page read rules).
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_page_visits')]
#[ORM\UniqueConstraint(name: 'wiki_page_visit_unique', columns: ['user_id', 'page_id'])]
#[ORM\Index(name: 'wiki_page_visit_user', columns: ['user_id', 'visited_at'])]
#[ORM\Index(name: 'wiki_page_visit_time', columns: ['visited_at'])]
class WikiPageVisit
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    /**
     * @param WikiPage $page the visited page
     * @param string $userId the person's id ({@see WikiUserId})
     * @param DateTimeImmutable $visitedAt when the page was last opened
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: WikiPage::class)]
        #[ORM\JoinColumn(name: 'page_id', nullable: false, onDelete: 'CASCADE')]
        private WikiPage $page,
        #[ORM\Column(name: 'user_id', type: 'string', length: WikiStoredUserRef::MAX_LENGTH)]
        private string $userId,
        #[ORM\Column(name: 'visited_at', type: 'datetime_immutable')]
        private DateTimeImmutable $visitedAt,
    ) {
        $this->id = Uuid::generate()->toString();
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

    public function getVisitedAt(): DateTimeImmutable
    {
        return $this->visitedAt;
    }
}
