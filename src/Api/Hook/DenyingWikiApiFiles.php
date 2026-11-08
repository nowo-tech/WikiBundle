<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiUploadedFile;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiPage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * 1.24 (spec 218): the default: no files through the API until the host sets `api.hooks.files`.
 */
final readonly class DenyingWikiApiFiles implements WikiApiFilesInterface
{
    public function list(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiPage $page): array
    {
        return [];
    }

    public function upload(WikiApiPrincipal $principal, WikiApiWiki $wiki, UploadedFile $file): WikiApiUploadedFile
    {
        throw new WikiApiRefusal(403, 'FILE_001', 'Files are not available through this API');
    }

    public function download(WikiApiPrincipal $principal, WikiApiWiki $wiki, string $fileUuid): ?Response
    {
        return null;
    }
}
