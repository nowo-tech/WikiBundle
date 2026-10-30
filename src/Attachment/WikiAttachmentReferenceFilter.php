<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Repository\WikiAttachmentRepositoryInterface;

use function array_unique;
use function array_values;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_replace_callback;

/**
 * Keeps attachment URLs in a page body only when they point to an attachment of the page's own space (1.9): an
 * image of another space's attachment is removed, a link to one loses its {@code href}. A page therefore never embeds
 * a file its readers may not open. Runs on the sanitizer's canonical output (double-quoted attributes).
 */
final readonly class WikiAttachmentReferenceFilter
{
    /** An attachment URL carries {@code /attachments/<uuid>}. */
    private const ID_PATTERN = '#/attachments/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})(?![0-9a-f-])#';

    /**
     * @param WikiAttachmentRepositoryInterface $attachmentRepository attachment lookups (one query per call)
     */
    public function __construct(
        private WikiAttachmentRepositoryInterface $attachmentRepository,
    ) {
    }

    /**
     * Removes references to attachments of other spaces (or unknown ones) from the HTML.
     *
     * @param WikiSpace $space space of the page being saved
     * @param string $html sanitized HTML
     *
     * @return string filtered HTML
     */
    public function filter(WikiSpace $space, string $html): string
    {
        if (preg_match_all(self::ID_PATTERN, $html, $matches) === 0) {
            return $html;
        }

        $allowed = [];
        foreach ($this->attachmentRepository->findByIds(array_values(array_unique($matches[1]))) as $attachment) {
            if ($attachment->getSpace()->getId() === $space->getId()) {
                $allowed[] = $attachment->getId();
            }
        }

        $html = (string) preg_replace_callback(
            '#<img\b[^>]*\ssrc="([^"]*)"[^>]*>#i',
            fn (array $m): string => $this->isForeign($m[1], $allowed) ? '' : $m[0],
            $html,
        );

        return (string) preg_replace_callback(
            '#<a\b([^>]*?)\shref="([^"]*)"([^>]*)>#i',
            fn (array $m): string => $this->isForeign($m[2], $allowed) ? '<a' . $m[1] . $m[3] . '>' : $m[0],
            $html,
        );
    }

    /**
     * @param list<string> $allowed attachment ids of the space
     */
    private function isForeign(string $url, array $allowed): bool
    {
        return preg_match(self::ID_PATTERN, $url, $m) === 1 && !in_array($m[1], $allowed, true);
    }
}
