<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use function in_array;
use function is_string;

use const PHP_URL_HOST;

/**
 * Allowlist-based HTML sanitizer for wiki page bodies (mitigates stored XSS).
 */
final class WikiHtmlSanitizer implements WikiHtmlSanitizerInterface
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><del><h1><h2><h3><h4><ul><ol><li><blockquote><code><pre><a><img><table><thead><tbody><tr><th><td><hr><span><div><iframe>';

    /** @var list<string> */
    private const ALLOWED_EMBED_HOSTS = [
        'www.youtube.com',
        'youtube.com',
        'www.youtube-nocookie.com',
        'player.vimeo.com',
    ];

    public function sanitize(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/i', '', $html) ?? $html;
        $html = preg_replace('/\s(href|src)\s*=\s*(["\'])\s*javascript:.*?\2/i', '', $html) ?? $html;

        $html = strip_tags($html, self::ALLOWED_TAGS);

        return $this->stripUnsafeIframes($html);
    }

    private function stripUnsafeIframes(string $html): string
    {
        return preg_replace_callback(
            '/<iframe\b[^>]*>.*?<\/iframe>/is',
            static function (array $matches): string {
                if (preg_match('/\ssrc=(["\'])([^"\']+)\1/i', $matches[0], $srcMatch) !== 1) {
                    return '';
                }

                $host = parse_url($srcMatch[2], PHP_URL_HOST);

                return is_string($host) && in_array($host, self::ALLOWED_EMBED_HOSTS, true)
                    ? $matches[0]
                    : '';
            },
            $html,
        ) ?? $html;
    }
}
