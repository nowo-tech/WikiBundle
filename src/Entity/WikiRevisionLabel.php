<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\ValueObject\Uuid;

/**
 * A named version (1.15, `history.timeline`): a label on one revision ("Before the migration"). Named versions are
 * never thinned by the retention and break the timeline's sessions.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_revision_labels')]
#[ORM\UniqueConstraint(name: 'wiki_revision_label_revision', columns: ['revision_id'])]
class WikiRevisionLabel
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: WikiPageRevision::class)]
        #[ORM\JoinColumn(name: 'revision_id', nullable: false, onDelete: 'CASCADE')]
        private WikiPageRevision $revision,
        #[ORM\Column(type: 'string', length: 120)]
        private string $label,
    ) {
        $this->id        = Uuid::generate()->toString();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRevision(): WikiPageRevision
    {
        return $this->revision;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->label = $label;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
