<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Api\Value\WikiApiDownload;
use Nowo\WikiBundle\Api\Value\WikiApiUpload;
use Nowo\WikiBundle\Api\WikiApiCredential;
use Nowo\WikiBundle\ValueObject\WikiRequestContext;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function fclose;
use function fpassthru;
use function in_array;
use function is_resource;
use function is_string;
use function str_starts_with;
use function strtolower;

/**
 * Converts between the Symfony HTTP objects and the framework-free values of the twin ports (1.33.0).
 *
 * @internal
 */
final class WikiRequestContexts
{
    /** Headers never copied into a {@see WikiRequestContext} (a host may log it). */
    public const CREDENTIAL_HEADERS = ['authorization', 'proxy-authorization', 'cookie', 'x-api-key'];

    /**
     * @param Request $request the request
     * @param bool $withCredential whether to pass the REST API bearer credential
     *
     * @return WikiRequestContext the request without credential headers
     */
    public static function fromRequest(Request $request, bool $withCredential = false): WikiRequestContext
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $name = strtolower((string) $name);
            if (in_array($name, self::CREDENTIAL_HEADERS, true) || str_starts_with($name, 'php-auth-') || !isset($values[0]) || !is_string($values[0])) {
                continue;
            }
            $headers[$name] = $values[0];
        }
        $query = [];
        foreach ($request->query->all() as $name => $value) {
            if (is_string($value)) {
                $query[(string) $name] = $value;
            }
        }
        $route = $request->attributes->get('_route');

        return new WikiRequestContext(
            $request->getMethod(),
            $request->getPathInfo(),
            is_string($route) ? $route : null,
            $request->getClientIp(),
            $withCredential ? WikiApiCredential::fromRequest($request) : null,
            $headers,
            $query,
        );
    }

    /**
     * @param UploadedFile $file the upload
     *
     * @return WikiApiUpload its framework-free description
     */
    public static function upload(UploadedFile $file): WikiApiUpload
    {
        return new WikiApiUpload($file->getPathname(), $file->getClientOriginalName(), $file->getClientMimeType(), (int) $file->getSize());
    }

    /**
     * The response of a download: the file as an attachment (the API operation adds its CSP and keeps `attachment`).
     *
     * @param WikiApiDownload $download the host's file
     *
     * @return Response the response
     */
    public static function response(WikiApiDownload $download): Response
    {
        $source = $download->source;
        if (is_string($source)) {
            $response = new BinaryFileResponse($source, 200, ['Content-Type' => $download->mimeType]);
        } else {
            $response = new StreamedResponse(static function () use ($source): void {
                if (is_resource($source)) {
                    fpassthru($source);
                    fclose($source);
                }
            }, 200, ['Content-Type' => $download->mimeType]);
            if ($download->size !== null) {
                $response->headers->set('Content-Length', (string) $download->size);
            }
        }
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $download->fileName, 'download'));

        return $response;
    }
}
