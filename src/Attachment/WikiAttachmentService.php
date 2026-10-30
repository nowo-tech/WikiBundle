<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

use DateTimeImmutable;
use finfo;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Event\WikiAttachmentEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Repository\WikiAttachmentRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function bin2hex;
use function fclose;
use function filesize;
use function fopen;
use function hash_file;
use function in_array;
use function is_resource;
use function mb_substr;
use function pathinfo;
use function preg_replace;
use function random_bytes;
use function strtolower;
use function trim;

use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

/**
 * Stores, deletes and cleans up page attachments (1.9). Authorization is the caller's job (the controller asks the
 * space checker before calling it).
 */
final readonly class WikiAttachmentService
{
    /**
     * @param WikiAttachmentRepositoryInterface $attachmentRepository attachment rows
     * @param WikiAttachmentStorageInterface $storage private bytes storage
     * @param WikiAttachmentScannerInterface $scanner pre-store scanner
     * @param WikiAttachmentLimitsProviderInterface $limitsProvider limits per space
     * @param EventDispatcherInterface $eventDispatcher dispatcher of the attachment events
     * @param LoggerInterface|null $logger logger (defaults to a NullLogger)
     */
    public function __construct(
        private WikiAttachmentRepositoryInterface $attachmentRepository,
        private WikiAttachmentStorageInterface $storage,
        private WikiAttachmentScannerInterface $scanner,
        private WikiAttachmentLimitsProviderInterface $limitsProvider,
        private EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    private LoggerInterface $logger;

    /**
     * Validates and stores an uploaded file for the page; nothing is stored when it is refused.
     *
     * @param WikiPage $page page the file is uploaded to
     * @param string $path local path of the uploaded file
     * @param string $originalName client file name (sanitized here)
     * @param object|null $actor user who uploads it
     *
     * @throws WikiAttachmentRejectedException when a limit, the allowlist or the scanner refuses the file
     *
     * @return WikiAttachment the stored attachment
     */
    public function upload(WikiPage $page, string $path, string $originalName, ?object $actor = null): WikiAttachment
    {
        $space  = $page->getSpace();
        $limits = $this->limitsProvider->limits($space);
        $size   = (int) @filesize($path);
        $name   = self::sanitizeName($originalName);

        if ($size <= 0) {
            throw new WikiAttachmentRejectedException(WikiAttachmentRejectedException::EMPTY);
        }

        if ($size > $limits->maxFileBytes) {
            throw new WikiAttachmentRejectedException(WikiAttachmentRejectedException::TOO_LARGE);
        }

        if ($this->attachmentRepository->sumSizeBySpace($space) + $size > $limits->maxSpaceBytes) {
            throw new WikiAttachmentRejectedException(WikiAttachmentRejectedException::QUOTA_EXCEEDED);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $accepted  = $limits->allowedTypes[$extension] ?? null;
        if ($accepted === null) {
            throw new WikiAttachmentRejectedException(WikiAttachmentRejectedException::TYPE_NOT_ALLOWED);
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, $accepted, true)) {
            throw new WikiAttachmentRejectedException(WikiAttachmentRejectedException::TYPE_MISMATCH);
        }

        $reason = $this->scanner->scan($path, $mime, $name);
        if ($reason !== null) {
            throw new WikiAttachmentRejectedException(WikiAttachmentRejectedException::SCANNER, $reason);
        }

        $key    = $space->getId() . '/' . bin2hex(random_bytes(16));
        $stream = @fopen($path, 'r');
        if (!is_resource($stream)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException('Unable to read the uploaded file.');
            // @codeCoverageIgnoreEnd
        }

        try {
            $this->storage->write($key, $stream);
        } finally {
            fclose($stream);
        }

        $attachment = new WikiAttachment($page, $space, $name, $key, $mime, $size, (string) hash_file('sha256', $path), $actor);

        try {
            $this->attachmentRepository->save($attachment);
        } catch (Throwable $exception) {
            $this->storage->delete($key);

            throw $exception;
        }

        $this->logger->info('Wiki attachment uploaded.', [
            'bundle' => 'nowo_wiki',
            'action' => 'attachment_upload',
            'space'  => $space->getSlug(),
            'page'   => $page->getSlug(),
            'size'   => $size,
            'mime'   => $mime,
        ]);
        $this->eventDispatcher->dispatch(new WikiAttachmentEvent($attachment, $actor), WikiEvents::ATTACHMENT_UPLOADED);

        return $attachment;
    }

    /**
     * Deletes the attachment row and its file.
     *
     * @param WikiAttachment $attachment attachment to delete
     * @param object|null $actor user who deletes it (null for maintenance)
     */
    public function delete(WikiAttachment $attachment, ?object $actor = null): void
    {
        // @igor-ignore - Repository call (Doctrine remove + flush of a request-scoped entity); no service state.
        $this->attachmentRepository->remove($attachment);
        $this->storage->delete($attachment->getStorageKey());
        $this->eventDispatcher->dispatch(new WikiAttachmentEvent($attachment, $actor), WikiEvents::ATTACHMENT_DELETED);
    }

    /**
     * Opens the bytes of an attachment; the caller closes the stream.
     *
     * @return resource readable stream
     */
    public function openStream(WikiAttachment $attachment): mixed
    {
        return $this->storage->readStream($attachment->getStorageKey());
    }

    /**
     * Deletes attachments uploaded before the date that no revision of their page references (orphans).
     * Attachments referenced by any revision, current or old, are kept (a restore needs them).
     *
     * @param DateTimeImmutable $uploadedBefore only attachments older than this are considered
     *
     * @return int number of attachments deleted
     */
    public function purgeOrphans(DateTimeImmutable $uploadedBefore): int
    {
        $deleted = 0;
        foreach ($this->attachmentRepository->findUploadedBefore($uploadedBefore) as $attachment) {
            if ($this->attachmentRepository->isReferencedByPageRevisions($attachment->getPage(), $attachment->getId())) {
                continue;
            }

            $this->delete($attachment);
            ++$deleted;
        }

        return $deleted;
    }

    /**
     * Removes stored files that no attachment row points to (e.g. after a page or space was deleted).
     *
     * @return int number of files removed
     */
    public function purgeStrayFiles(): int
    {
        $removed = 0;
        foreach ($this->storage->keys() as $key) {
            if (!$this->attachmentRepository->existsByStorageKey($key)) {
                $this->storage->delete($key);
                ++$removed;
            }
        }

        return $removed;
    }

    /**
     * Keeps a safe display name: no path, no control characters, at most 255 characters.
     */
    public static function sanitizeName(string $name): string
    {
        $name = (string) preg_replace('#^.*[/\\\\]#', '', $name);
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F"]+/u', '', $name), " .\t");

        return $name === '' ? 'file' : mb_substr($name, 0, 255);
    }
}
