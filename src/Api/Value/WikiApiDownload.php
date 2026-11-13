<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

use InvalidArgumentException;
use Nowo\WikiBundle\Api\Hook\WikiApiFileStoreInterface;

use function is_resource;
use function is_string;

/**
 * A file the REST API sends (1.34.0): the framework-free twin of the Symfony `Response` that
 * `Api\Hook\WikiApiFilesInterface::download()` returns ({@see WikiApiFileStoreInterface}).
 * Either a local path or an open stream; the bundle builds the response, always as an attachment with a restrictive
 * Content-Security-Policy.
 */
final readonly class WikiApiDownload
{
    /**
     * @param resource|string $source a readable local path, or an open read stream (closed by the bundle once sent)
     * @param string $fileName file name of the attachment
     * @param string $mimeType media type
     * @param int|null $size size in bytes, when known
     *
     * @throws InvalidArgumentException when the source is neither a path nor a stream
     */
    public function __construct(
        public mixed $source,
        public string $fileName,
        public string $mimeType = 'application/octet-stream',
        public ?int $size = null,
    ) {
        if (!is_string($source) && !is_resource($source)) {
            throw new InvalidArgumentException('A wiki API download is a local path or an open stream.');
        }
    }
}
