<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiDownload;
use Nowo\WikiBundle\Api\Value\WikiApiFile;
use Nowo\WikiBundle\Api\Value\WikiApiUpload;
use Nowo\WikiBundle\Api\Value\WikiApiUploadedFile;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;

/**
 * The REST API's host files, framework-free (1.33.0): the twin of the deprecated {@see WikiApiFilesInterface}
 * (`nowo_wiki.api.hooks.files` takes either).
 */
interface WikiApiFileStoreInterface
{
    /**
     * The files the page's current revision shows, only those the reader may open.
     *
     * @return list<WikiApiFile>
     */
    public function list(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiPageSnapshot $page): array;

    /** @throws WikiApiRefusal a refusal stores nothing */
    public function upload(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiApiUpload $file): WikiApiUploadedFile;

    /** The file, for readers of that wiki only; null: not one of this wiki's files (404). */
    public function download(WikiApiPrincipal $principal, WikiApiWiki $wiki, string $fileUuid): ?WikiApiDownload;
}
