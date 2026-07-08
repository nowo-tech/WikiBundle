<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function is_array;

/**
 * Parses optional YAML front matter from Markdown documents.
 */
final class WikiFrontMatterParser
{
    /**
     * @return array{meta: array<string, mixed>, body: string}
     */
    public function parse(string $content): array
    {
        if (!str_starts_with($content, "---\n") && !str_starts_with($content, "---\r\n")) {
            return ['meta' => [], 'body' => $content];
        }

        $closing = strpos($content, "\n---", 4);
        if ($closing === false) {
            return ['meta' => [], 'body' => $content];
        }

        $yamlBlock = substr($content, 4, $closing - 4);
        $bodyStart = $closing + 4;
        if ($content[$bodyStart] === "\r") {
            ++$bodyStart;
        }
        if (isset($content[$bodyStart]) && $content[$bodyStart] === "\n") {
            ++$bodyStart;
        }

        $body = substr($content, $bodyStart);
        $meta = [];

        try {
            $parsed = Yaml::parse($yamlBlock);
            if (is_array($parsed)) {
                /** @var array<string, mixed> $meta */
                $meta = $parsed;
            }
        } catch (ParseException) {
            return ['meta' => [], 'body' => $content];
        }

        return ['meta' => $meta, 'body' => $body];
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function serialize(array $meta, string $body): string
    {
        if ($meta === []) {
            return $body;
        }

        $yaml = trim(Yaml::dump($meta, 2, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));

        return "---\n" . $yaml . "---\n\n" . ltrim($body);
    }
}
