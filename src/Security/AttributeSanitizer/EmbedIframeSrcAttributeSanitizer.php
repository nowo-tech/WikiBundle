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
final readonly class EmbedIframeSrcAttributeSanitizer implements AttributeSanitizerInterface
{
    /** @var list<string> */
    private array $allowedHosts;

    /**
     * Creates the sanitizer with a lowercased copy of the host allowlist.
     *
     * @param list<string> $allowedHosts hosts allowed as iframe `src` (compared case-insensitively)
     */
    public function __construct(array $allowedHosts)
    {
        $this->allowedHosts = array_map(strtolower(...), $allowedHosts);
    }

    /**
     * Returns the elements this sanitizer applies to.
     *
     * @return list<string> only `iframe`
     */
    public function getSupportedElements(): array
    {
        return ['iframe'];
    }

    /**
     * Returns the attributes this sanitizer applies to.
     *
     * @return list<string> only `src`
     */
    public function getSupportedAttributes(): array
    {
        return ['src'];
    }

    /**
     * Keeps the `src` value only when it is an https URL without credentials whose host is on the allowlist.
     *
     * @param string $element element name
     * @param string $attribute attribute name
     * @param string $value attribute value
     * @param HtmlSanitizerConfig $config active sanitizer configuration
     *
     * @return string|null the original value, or null to drop the attribute
     */
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
