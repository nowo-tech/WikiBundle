<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use Nowo\WikiBundle\ValueObject\Uuid;

/**
 * 1.45 (`backlinks.enabled`): one `[[wiki:<uuid>]]` reference of a page's current revision to another page, the row of
 * the backlink index. Written from {@see WikiMarkdownReferenceExtractor} after every save
 * (and by `wiki:backlinks:rebuild`), deleted with its source page. The target is only an id (no foreign key): a link
 * to a page that does not exist yet, or no longer exists, is kept and simply never listed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_page_references')]
#[ORM\UniqueConstraint(name: 'wiki_page_reference_unique', columns: ['source_page_id', 'target_page_id'])]
#[ORM\Index(name: 'wiki_page_reference_target', columns: ['target_page_id', 'source_page_id'])]
class WikiPageReference
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    /**
     * @param WikiPage $source the page whose current revision links the target
     * @param string $targetPageId the linked page's id (lower case)
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: WikiPage::class)]
        #[ORM\JoinColumn(name: 'source_page_id', nullable: false, onDelete: 'CASCADE')]
        private WikiPage $source,
        #[ORM\Column(name: 'target_page_id', type: 'string', length: 36)]
        private string $targetPageId,
    ) {
        $this->id = Uuid::generate()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSource(): WikiPage
    {
        return $this->source;
    }

    public function getTargetPageId(): string
    {
        return $this->targetPageId;
    }
}
