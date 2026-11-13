<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Api\Hook\WikiApiFileStoreInterface;
use Nowo\WikiBundle\Api\Value\WikiApiDownload;
use Nowo\WikiBundle\Api\Value\WikiApiUpload;
use Nowo\WikiBundle\Api\Value\WikiApiUploadedFile;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

use function fopen;
use function fwrite;
use function rewind;

/**
 * 1.34 (`WIKI_TEST_PORTS=twin`): {@see ApiTestFiles} through the framework-free file store.
 */
final readonly class ApiTwinFiles implements WikiApiFileStoreInterface
{
    public function __construct(
        private ApiTestFiles $inner,
        private ManagerRegistry $doctrine,
    ) {
    }

    public function list(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiPageSnapshot $page): array
    {
        $entity = $this->doctrine->getManager()->find(WikiPage::class, $page->id);
        if (!$entity instanceof WikiPage) {
            throw new RuntimeException('Unknown page ' . $page->id);
        }

        return $this->inner->list($principal, $wiki, $entity);
    }

    public function upload(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiApiUpload $file): WikiApiUploadedFile
    {
        return $this->inner->upload($principal, $wiki, new UploadedFile($file->pathname, $file->clientName, $file->clientMimeType, null, true));
    }

    public function download(WikiApiPrincipal $principal, WikiApiWiki $wiki, string $fileUuid): ?WikiApiDownload
    {
        $response = $this->inner->download($principal, $wiki, $fileUuid);
        if (!$response instanceof Response) {
            return null;
        }
        $stream = fopen('php://memory', 'w+');
        if ($stream === false) {
            throw new RuntimeException('No memory stream');
        }
        fwrite($stream, (string) $response->getContent());
        rewind($stream);

        return new WikiApiDownload($stream, 'x.html', (string) $response->headers->get('Content-Type'));
    }
}
