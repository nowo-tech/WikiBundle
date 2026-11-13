<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

use function is_array;
use function strtolower;

/**
 * Adds the cookies of a framework-free co-editing subscription grant (1.34.0,
 * {@see WikiCollabSubscriptionPolicyInterface}) to the response of the request they were granted for. They ride on that
 * request's attributes only (no service state), so they never reach another request in a kept-alive worker.
 *
 * @internal
 */
final class WikiCollabCookieListener
{
    public const ATTRIBUTE = '_nowo_wiki_collab_cookies';

    /**
     * Queues cookies on the request.
     *
     * @param Request $request the granted request
     * @param list<WikiCollabCookie> $cookies cookies to set
     */
    public static function queue(Request $request, array $cookies): void
    {
        $queued = $request->attributes->get(self::ATTRIBUTE);
        $request->attributes->set(self::ATTRIBUTE, [...(is_array($queued) ? $queued : []), ...$cookies]);
    }

    /**
     * Sets the queued cookies on the response (`kernel.response`).
     *
     * @param ResponseEvent $event the response event
     */
    public function onResponse(ResponseEvent $event): void
    {
        $cookies = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if (!is_array($cookies)) {
            return;
        }
        $event->getRequest()->attributes->remove(self::ATTRIBUTE);
        foreach ($cookies as $cookie) {
            if ($cookie instanceof WikiCollabCookie) {
                $event->getResponse()->headers->setCookie(Cookie::create($cookie->name, $cookie->value, $cookie->expiresAt ?? 0, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httpOnly, false, $this->sameSite($cookie->sameSite)));
            }
        }
    }

    /**
     * @return 'lax'|'none'|'strict' the SameSite value (anything unknown is `strict`)
     */
    private function sameSite(string $value): string
    {
        return match (strtolower($value)) {
            'lax'   => 'lax',
            'none'  => 'none',
            default => 'strict',
        };
    }
}
