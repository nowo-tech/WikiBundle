<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown;

use Nowo\MilkdownEditorBundle\Markdown\HeadingIds;
use Nowo\WikiBundle\Markdown\WikiHeadingAnchor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function is_array;
use function is_file;
use function json_decode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * 1.40: the one id grammar (contract §2.3) is the same as Milkdown 1.18's, slug for slug and id for id.
 */
final class WikiHeadingAnchorTest extends TestCase
{
    public function testTheMilkdownSharedVectorsGiveTheSameSlugs(): void
    {
        $file = dirname(__DIR__, 3) . '/vendor/nowo-tech/milkdown-editor-bundle/tests/Fixtures/heading-ids.json';
        if (!is_file($file)) {
            self::markTestSkipped('Milkdown fixtures are not installed.');
        }
        $vectors = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($vectors);
        self::assertTrue(is_array($vectors['slug'] ?? null));
        foreach ($vectors['slug'] as [$text, $slug]) {
            self::assertSame($slug, WikiHeadingAnchor::slug($text), $text);
            self::assertSame(HeadingIds::slug($text), WikiHeadingAnchor::slug($text), $text);
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function ids(): iterable
    {
        yield 'ascii' => ['setup', true];
        yield 'unicode' => ['año-nuevo', true];
        yield 'cjk' => ['日本語', true];
        yield 'digits' => ['1-6-0', true];
        yield 'empty' => ['', false];
        yield 'upper case' => ['Setup', false];
        yield 'double hyphen' => ['a--b', false];
        yield 'trailing hyphen' => ['a-', false];
        yield 'leading mark' => ["\u{301}a", false];
        yield 'underscore' => ['a_b', false];
        yield 'space' => ['a b', false];
        yield 'too long' => [str_repeat('a', 91), false];
        yield 'longest' => [str_repeat('a', 90), true];
        yield 'markup' => ['a"><script>', false];
    }

    #[DataProvider('ids')]
    public function testValidIdsAreTheMilkdownOnes(string $id, bool $valid): void
    {
        self::assertSame($valid, WikiHeadingAnchor::isValid($id));
        self::assertSame(HeadingIds::isValid($id), WikiHeadingAnchor::isValid($id));
    }

    public function testNormaliseDropsThePrefixAndRefusesInvalidIds(): void
    {
        self::assertSame('setup', WikiHeadingAnchor::normalise('nowo-h-setup'));
        self::assertSame('setup', WikiHeadingAnchor::normalise('setup'));
        self::assertSame('', WikiHeadingAnchor::normalise('nowo-h-'));
        self::assertSame('', WikiHeadingAnchor::normalise('Nowo-H-x'));
        self::assertSame('', WikiHeadingAnchor::normalise('a--b'));
        self::assertSame(HeadingIds::normalise('nowo-h-año'), WikiHeadingAnchor::normalise('nowo-h-año'));
    }

    public function testSlugEdgeCases(): void
    {
        self::assertSame('section', WikiHeadingAnchor::slug('!!!'));
        self::assertSame('install-the-bundle', WikiHeadingAnchor::slug('  Install the Bundle  '));
        self::assertSame(str_repeat('a', 80), WikiHeadingAnchor::slug(str_repeat('A', 120)));
    }
}
