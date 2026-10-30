<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\Attachment\WikiAttachmentStorageInterface;
use Nowo\WikiBundle\ValueObject\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

use function in_array;

/**
 * A file uploaded to a page (1.9). The bytes live in
 * {@see WikiAttachmentStorageInterface} under a server-generated key; downloads are
 * authorized on the attachment's own page.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_attachments')]
#[ORM\Index(name: 'wiki_attachment_space', columns: ['space_id'])]
class WikiAttachment
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'uploaded_at', type: 'datetime_immutable')]
    private DateTimeImmutable $uploadedAt;

    /**
     * @param WikiPage $page page the file was uploaded to
     * @param WikiSpace $space space of the page (denormalized for quotas)
     * @param string $originalName sanitized original file name
     * @param string $storageKey key in the storage (server-generated)
     * @param string $mimeType MIME type sniffed on the server
     * @param int $size size in bytes
     * @param string $sha256 SHA-256 of the bytes (hex)
     * @param object|null $uploadedBy user who uploaded it
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: WikiPage::class)]
        #[ORM\JoinColumn(name: 'page_id', nullable: false, onDelete: 'CASCADE')]
        private WikiPage $page,
        #[ORM\ManyToOne(targetEntity: WikiSpace::class)]
        #[ORM\JoinColumn(name: 'space_id', nullable: false, onDelete: 'CASCADE')]
        private WikiSpace $space,
        #[ORM\Column(name: 'original_name', type: 'string', length: 255)]
        private string $originalName,
        #[ORM\Column(name: 'storage_key', type: 'string', length: 255, unique: true)]
        private string $storageKey,
        #[ORM\Column(name: 'mime_type', type: 'string', length: 100)]
        private string $mimeType,
        #[ORM\Column(type: 'bigint')]
        private int|string $size,
        #[ORM\Column(type: 'string', length: 64)]
        private string $sha256,
        #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(name: 'uploaded_by_id', nullable: true, onDelete: 'SET NULL')]
        private ?object $uploadedBy = null,
        ?string $id = null,
    ) {
        $this->id         = $id ?? Uuid::generate()->toString();
        $this->uploadedAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPage(): WikiPage
    {
        return $this->page;
    }

    public function getSpace(): WikiSpace
    {
        return $this->space;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return (int) $this->size;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function getUploadedBy(): ?object
    {
        return $this->uploadedBy;
    }

    public function getUploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    /**
     * Tells whether the file is a raster image shown inline (PNG, JPEG, GIF, WebP).
     */
    public function isInlineImage(): bool
    {
        return in_array($this->mimeType, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
    }
}
