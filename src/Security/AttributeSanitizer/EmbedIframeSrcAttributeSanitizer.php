<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security\AttributeSanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

use function array_map;
use function in_array;
use function is_array;
use function strtolower;

/**
 * Keeps `<iframe src>` only for https URLs whose host is on the embed allowlist.
 *
 * @internal
 */
final class EmbedIframeSrcAttributeSanitizer implements AttributeSanitizerInterface
{
    /** @var list<string> */
    private readonly array $allowedHosts;

    /**
     * @param list<string> $allowedHosts
     */
    public function __construct(array $allowedHosts)
    {
        $this->allowedHosts = array_map(strtolower(...), $allowedHosts);
    }

    public function getSupportedElements(): array
    {
        return ['iframe'];
    }

    public function getSupportedAttributes(): array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $parts = parse_url(trim($value));
        if (!is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || !in_array(strtolower($parts['host'] ?? ''), $this->allowedHosts, true)
        ) {
            return null;
        }

        return $value;
    }
}
