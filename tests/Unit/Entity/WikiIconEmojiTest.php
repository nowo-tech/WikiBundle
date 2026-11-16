<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Entity;

use InvalidArgumentException;
use Nowo\IconSelectorBundle\Emoji\EmojiCatalog;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\ValueObject\WikiSpaceIcon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function class_exists;
use function str_repeat;

/**
 * 1.39: `emoji:<grapheme>` icons (page_icon.emoji / space_icon.emoji): the shape guard of every writer and the strict
 * catalogue check of the service and the form fields.
 */
final class WikiIconEmojiTest extends TestCase
{
    private const WITH_EMOJI = ['tabler', 'emoji'];

    protected function setUp(): void
    {
        if (!class_exists(EmojiCatalog::class)) {
            self::markTestSkipped('nowo-tech/icon-selector-bundle >= 1.6 (dev dependency) is not installed.');
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wellShaped(): iterable
    {
        yield 'single' => ['emoji:🚀'];
        yield 'VS16' => ["emoji:\u{2764}\u{FE0F}"];
        yield 'ZWJ + tone' => ['emoji:👩🏽‍💻'];
        yield 'flag' => ['emoji:🇪🇸'];
        yield 'tag flag' => ['emoji:🏴󠁧󠁢󠁥󠁮󠁧󠁿'];
        yield 'keycap' => ["emoji:#\u{FE0F}\u{20E3}"];
    }

    #[DataProvider('wellShaped')]
    public function testWellShapedEmojiPassTheGuard(string $icon): void
    {
        self::assertTrue(WikiSpaceIcon::isValid($icon, self::WITH_EMOJI));
        self::assertNotNull(WikiSpaceIcon::emojiOf($icon));
        self::assertTrue(WikiPageIconService::acceptable($icon, self::WITH_EMOJI, null, new EmojiCatalog()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badlyShaped(): iterable
    {
        yield 'empty' => ['emoji:'];
        yield 'ascii word' => ['emoji:rocket'];
        yield 'markup' => ['emoji:<b>'];
        yield 'space' => ['emoji:🚀 '];
        yield 'bidi' => ["emoji:\u{202E}🚀"];
        yield 'zero width space' => ["emoji:🚀\u{200B}"];
        yield 'control' => ["emoji:\u{0007}"];
        yield 'combining mark' => ["emoji:\u{0301}"];
        yield 'too long' => ['emoji:' . str_repeat('🚀', 20)];
        yield 'invalid utf-8' => ["emoji:\xF0\x9F\x9A"];
    }

    #[DataProvider('badlyShaped')]
    public function testBadlyShapedValuesAreRefusedEverywhere(string $icon): void
    {
        self::assertFalse(WikiSpaceIcon::isValid($icon, self::WITH_EMOJI));
        self::assertNull(WikiSpaceIcon::emojiOf($icon));
        self::assertFalse(WikiPageIconService::acceptable($icon, self::WITH_EMOJI, null, new EmojiCatalog()));
    }

    public function testEmojiNeedTheEmojiPrefixAndTheCatalogue(): void
    {
        self::assertFalse(WikiSpaceIcon::isValid('emoji:🚀', ['tabler']));
        // Well shaped but not one RGI emoji: only the catalogue tells.
        self::assertTrue(WikiSpaceIcon::isValid('emoji:🚀🚀', self::WITH_EMOJI));
        self::assertFalse(WikiPageIconService::acceptable('emoji:🚀🚀', self::WITH_EMOJI, null, new EmojiCatalog()));
        // No catalogue (or not one): no emoji at all.
        self::assertFalse(WikiPageIconService::acceptable('emoji:🚀', self::WITH_EMOJI, null, null));
        self::assertFalse(WikiPageIconService::acceptable('emoji:🚀', self::WITH_EMOJI, null, new stdClass()));
        self::assertFalse(WikiSpaceIcon::isEmoji(null));
        self::assertNull(WikiSpaceIcon::emojiOf('tabler:home'));
    }

    public function testEmojiNeverReachTheIconRendererAndIconsStillDo(): void
    {
        $renderer = new class {
            public int $calls = 0;

            public function renderIcon(string $name): string
            {
                ++$this->calls;
                if ($name === 'tabler:missing') {
                    throw new RuntimeException('missing');
                }

                return '<svg/>';
            }
        };

        self::assertTrue(WikiPageIconService::acceptable('emoji:🚀', self::WITH_EMOJI, $renderer, new EmojiCatalog()));
        self::assertSame(0, $renderer->calls);
        self::assertTrue(WikiPageIconService::acceptable('tabler:home', self::WITH_EMOJI, $renderer, null));
        self::assertFalse(WikiPageIconService::acceptable('tabler:missing', self::WITH_EMOJI, $renderer, null));
        self::assertSame(2, $renderer->calls);
    }

    public function testTheEntitySetterKeepsTheGuard(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, '1');
        $space->setIcon('emoji:🚀', self::WITH_EMOJI);
        self::assertSame('emoji:🚀', $space->getIcon());

        $this->expectException(InvalidArgumentException::class);
        $space->setIcon('emoji:🚀', ['tabler']);
    }
}
