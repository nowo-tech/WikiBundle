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
use function is_array;
use function is_resource;
use function is_string;
use function parse_url;
use function strtolower;

/**
 * Converts between the Symfony HTTP objects and the framework-free values of the twin ports (1.36.0).
 *
 * @internal
 */
final class WikiRequestContexts
{
    /**
     * The only headers copied into a {@see WikiRequestContext} (audit OX-L3): content negotiation, the client and the
     * proxy chain, and request ids. Never a credential, a session or CSRF value, nor an infrastructure token
     * (`Cf-Access-Jwt-Assertion`, `X-Amzn-Oidc-*`, `X-Goog-Iap-Jwt-Assertion`, `X-Forwarded-Access-Token`, ...).
     */
    public const ALLOWED_HEADERS = [
        'accept', 'accept-language', 'accept-encoding', 'content-type', 'content-length', 'host', 'origin', 'referer',
        'user-agent', 'x-request-id', 'x-correlation-id', 'x-forwarded-for', 'x-forwarded-host', 'x-forwarded-port',
        'x-forwarded-prefix', 'x-forwarded-proto', 'x-real-ip',
    ];

    /**
     * @param Request $request the request
     * @param bool $withCredential whether to pass the REST API bearer credential
     *
     * @return WikiRequestContext the request: method, path, route, client IP and the allowed headers only (no query
     *                            string; the referrer without its user info, query and fragment)
     */
    public static function fromRequest(Request $request, bool $withCredential = false): WikiRequestContext
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $name = strtolower((string) $name);
            if (!in_array($name, self::ALLOWED_HEADERS, true) || !isset($values[0]) || !is_string($values[0])) {
                continue;
            }
            $value = $name === 'referer' ? self::origin($values[0]) : $values[0];
            if ($value !== null) {
                $headers[$name] = $value;
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

    /**
     * A referrer as scheme, host, port and path (an OAuth `code`, a token or user info in it is dropped); null when it
     * is not an absolute http(s) URL.
     */
    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        return strtolower($parts['scheme']) . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . ($parts['path'] ?? '');
    }
}
