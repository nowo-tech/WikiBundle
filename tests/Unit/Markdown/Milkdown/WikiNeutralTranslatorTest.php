<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Form\MilkdownEditorType;
use Nowo\WikiBundle\Markdown\Milkdown\WikiNeutralTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 1.31 fix: the shared cache stores no translated placeholder (a Spanish "Contenido no visible" under an English UI).
 */
final class WikiNeutralTranslatorTest extends TestCase
{
    public function testTheHiddenPlaceholderIsEmptyAndTheRestGoesToTheHost(): void
    {
        $inner = $this->createMock(TranslatorInterface::class);
        $inner->method('trans')->willReturnCallback(static fn (string $id): string => 'es:' . $id);
        $inner->method('getLocale')->willReturn('es');
        $translator = new WikiNeutralTranslator($inner);

        self::assertSame('', $translator->trans('ref.hidden', [], MilkdownEditorType::DOMAIN));
        self::assertSame('es:ref.hidden', $translator->trans('ref.hidden', [], 'other'));
        self::assertSame('es:file.open', $translator->trans('file.open', [], MilkdownEditorType::DOMAIN));
        self::assertSame('es', $translator->getLocale());
    }

    public function testWithoutAHostTranslator(): void
    {
        $translator = new WikiNeutralTranslator();

        self::assertSame('', $translator->trans('ref.hidden', [], MilkdownEditorType::DOMAIN));
        self::assertSame('file.open', $translator->trans('file.open', [], MilkdownEditorType::DOMAIN));
        self::assertSame('en', $translator->getLocale());
    }
}
