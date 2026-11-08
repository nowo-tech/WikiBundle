<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Nowo\WikiBundle\Api\Hook\WikiApiFilesInterface;
use Nowo\WikiBundle\Api\Value\WikiApiFile;
use Nowo\WikiBundle\Api\Value\WikiApiUploadedFile;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiPage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

use function count;
use function str_ends_with;
use function str_pad;

use const STR_PAD_LEFT;

/**
 * The host's files in the API tests (static: the kernel boots per request).
 */
final class ApiTestFiles implements WikiApiFilesInterface
{
    /** @var array<string, string> uuid => file name */
    public static array $stored = [];

    public function list(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiPage $page): array
    {
        $files = [];
        foreach (self::$stored as $uuid => $name) {
            $files[] = new WikiApiFile($uuid, $name, 'file', 'application/pdf', 5);
        }

        return $files;
    }

    public function upload(WikiApiPrincipal $principal, WikiApiWiki $wiki, UploadedFile $file): WikiApiUploadedFile
    {
        if (str_ends_with($file->getClientOriginalName(), '.docm')) {
            throw new WikiApiRefusal(403, 'FILE_002', 'Office macros are refused');
        }
        $uuid                = '11111111-1111-4111-8111-' . str_pad((string) count(self::$stored), 12, '0', STR_PAD_LEFT);
        self::$stored[$uuid] = $file->getClientOriginalName();

        return new WikiApiUploadedFile($uuid, '[' . $file->getClientOriginalName() . '](beacon-file:' . $uuid . ')');
    }

    public function download(WikiApiPrincipal $principal, WikiApiWiki $wiki, string $fileUuid): ?Response
    {
        return isset(self::$stored[$fileUuid]) ? new Response('<script>alert(1)</script>', 200, ['Content-Type' => 'text/html', 'Content-Disposition' => 'inline; filename="x.html"']) : null;
    }
}
