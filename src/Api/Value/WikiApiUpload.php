<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

use RuntimeException;

use function fopen;
use function sprintf;

/**
 * A file uploaded through the REST API (1.32.0): the framework-free twin of the Symfony `UploadedFile` that
 * `Api\Hook\WikiApiFilesInterface::upload()` takes ({@see \Nowo\WikiBundle\Api\Hook\WikiApiFileStoreInterface}).
 * The client name and type are what the client sent: never trust them.
 */
final readonly class WikiApiUpload
{
    /**
     * @param string $pathname temporary path of the upload
     * @param string $clientName file name the client sent
     * @param string|null $clientMimeType media type the client sent
     * @param int $size size in bytes
     */
    public function __construct(
        public string $pathname,
        public string $clientName,
        public ?string $clientMimeType,
        public int $size,
    ) {
    }

    /**
     * Opens the upload for reading.
     *
     * @throws RuntimeException when the temporary file cannot be read
     *
     * @return resource read stream (the caller closes it)
     */
    public function open()
    {
        $stream = @fopen($this->pathname, 'r');
        if ($stream === false) {
            throw new RuntimeException(sprintf('The uploaded file "%s" cannot be read.', $this->clientName));
        }

        return $stream;
    }
}
