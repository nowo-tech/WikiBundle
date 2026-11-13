<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use DateTimeImmutable;

/**
 * A cookie the co-editing subscription grant sets on the response (1.33.0): what
 * {@see WikiCollabSubscriptionPolicyInterface::grant()} returns instead of writing to the HTTP `Request` (e.g. the
 * Mercure `mercureAuthorization` cookie with the subscriber JWT). The bundle adds it to the response of the request it
 * was granted for.
 */
final readonly class WikiCollabCookie
{
    /**
     * @param string $name cookie name
     * @param string $value cookie value
     * @param DateTimeImmutable|null $expiresAt expiry; null: a session cookie
     * @param string $path cookie path
     * @param string|null $domain cookie domain; null: the request host
     * @param bool $secure HTTPS only
     * @param bool $httpOnly hidden from scripts
     * @param string $sameSite `strict`, `lax` or `none`
     */
    public function __construct(
        public string $name,
        public string $value,
        public ?DateTimeImmutable $expiresAt = null,
        public string $path = '/',
        public ?string $domain = null,
        public bool $secure = true,
        public bool $httpOnly = true,
        public string $sameSite = 'strict',
    ) {
    }
}
