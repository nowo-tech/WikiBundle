<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use Nowo\WikiBundle\Port\WikiRequestContexts;

use function strtolower;

/**
 * The HTTP request a framework-free host port is asked about (1.36.0, REQ-ARCH-001 rule 3): the twin of the Symfony
 * `Request` the deprecated ports take. Built with `Port\WikiRequestContexts::fromRequest()`.
 *
 * It carries an allow-list of plain headers only ({@see WikiRequestContexts::ALLOWED_HEADERS}: content negotiation,
 * client, proxy chain, request ids; the referrer reduced to scheme, host and path) and never the query string (audit
 * OX-L3): no credential, session, CSRF or infrastructure token header. The REST API's bearer credential reaches the
 * authenticator only as {@see self::$credential} (null for the co-editing ports): never log that one.
 */
final readonly class WikiRequestContext
{
    /**
     * @param string $method HTTP method (upper case)
     * @param string $path path info (no query string)
     * @param string|null $route matched route name, when known
     * @param string|null $clientIp client IP (trusted proxies applied)
     * @param string|null $credential the bearer credential of the REST API (`Authorization: Bearer …` without the
     *                                scheme); null without one and for the co-editing ports
     * @param array<string, string> $headers the allowed request headers, lower-case names, first value each
     */
    public function __construct(
        public string $method,
        public string $path,
        public ?string $route = null,
        public ?string $clientIp = null,
        public ?string $credential = null,
        public array $headers = [],
    ) {
    }

    /**
     * One header (case-insensitive name), or null.
     *
     * @param string $name header name
     *
     * @return string|null its first value
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
