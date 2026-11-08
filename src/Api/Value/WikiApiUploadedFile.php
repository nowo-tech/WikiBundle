<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

/**
 * 1.24 (spec 218 FR-038): a file the host stored, with the Markdown that shows it (an image or a file card). The upload
 * changes no page: the client puts the Markdown in its next update.
 */
final readonly class WikiApiUploadedFile
{
    public function __construct(
        public string $uuid,
        public string $markdown,
    ) {
    }
}
