<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function preg_match_all;

/**
 * CSP-friendly bundle views: no inline style attribute or block, no on* handler, no CDN URL, no confirm(), and every
 * <script> prints the request nonce.
 */
final class TemplateLintTest extends TestCase
{
    public function testNoTemplateHasInlineStylesHandlersCdnOrUnnoncedScripts(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . '/src/Resources/views'));
        $count = 0;
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'twig') {
                continue;
            }
            ++$count;
            $source = (string) file_get_contents($file->getPathname());
            $name   = $file->getFilename();

            self::assertDoesNotMatchRegularExpression('/\sstyle\s*=|<style[\s>]/i', $source, $name . ': inline style');
            self::assertDoesNotMatchRegularExpression('/<[a-z][^>]*\son[a-z]+\s*=/i', $source, $name . ': inline handler');
            self::assertDoesNotMatchRegularExpression('#(src|href)\s*=\s*"https?://#i', $source, $name . ': CDN URL');
            self::assertDoesNotMatchRegularExpression('/\bconfirm\s*\(/', $source, $name . ': confirm()');

            preg_match_all('/<script\b[^>]*>/i', $source, $matches);
            foreach ($matches[0] as $tag) {
                self::assertStringContainsString('nonce=', $tag, $name . ': <script> without the request nonce');
            }
        }

        self::assertGreaterThan(10, $count);
    }
}
