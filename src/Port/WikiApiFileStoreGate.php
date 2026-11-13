<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Api\Hook\WikiApiFilesInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiFileStoreInterface;
use Nowo\WikiBundle\Api\Value\WikiApiDownload;
use Nowo\WikiBundle\Api\Value\WikiApiUploadedFile;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Entity\WikiPage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asks a framework-free {@see WikiApiFileStoreInterface} (1.32.0) and builds the download response.
 *
 * @internal
 */
final readonly class WikiApiFileStoreGate implements WikiApiFilesInterface
{
    public function __construct(private WikiApiFileStoreInterface $store)
    {
    }

    public function list(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiPage $page): array
    {
        return $this->store->list($principal, $wiki, WikiSnapshots::page($page));
    }

    public function upload(WikiApiPrincipal $principal, WikiApiWiki $wiki, UploadedFile $file): WikiApiUploadedFile
    {
        return $this->store->upload($principal, $wiki, WikiRequestContexts::upload($file));
    }

    public function download(WikiApiPrincipal $principal, WikiApiWiki $wiki, string $fileUuid): ?Response
    {
        $download = $this->store->download($principal, $wiki, $fileUuid);

        return $download instanceof WikiApiDownload ? WikiRequestContexts::response($download) : null;
    }
}
