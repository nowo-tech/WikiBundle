<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Stub;

use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\Entity\WikiPage;

/**
 * 2.0 (W1-2): an application entity with a ManyToOne to a wiki page (set with the application's getReference()) and a
 * field of its own, for the host-entity tests of the private entity manager.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_host_bookmarks')]
class TestHostBookmark
{
    #[ORM\ManyToOne(targetEntity: WikiPage::class)]
    #[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?WikiPage $page = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 36)]
        private string $id,
        #[ORM\Column(type: 'string', length: 100)]
        private string $note = '',
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }

    public function getPage(): ?WikiPage
    {
        return $this->page;
    }

    public function setPage(?WikiPage $page): void
    {
        $this->page = $page;
    }
}
