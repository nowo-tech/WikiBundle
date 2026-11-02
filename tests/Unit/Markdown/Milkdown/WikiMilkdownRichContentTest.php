<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\MarkdownRendererInterface;
use Nowo\MilkdownEditorBundle\Twig\MilkdownEditorTwig;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRichContent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;

final class WikiMilkdownRichContentTest extends TestCase
{
    public function testPrintsTheMilkdownAssetTags(): void
    {
        $packages = new Packages(new Package(new EmptyVersionStrategy()), ['nowo_milkdown_editor' => new Package(new EmptyVersionStrategy())]);
        $twig     = new MilkdownEditorTwig($this->createStub(MarkdownRendererInterface::class), $packages);

        $assets = (new WikiMilkdownRichContent($twig))->assets();

        self::assertStringContainsString('<link rel="stylesheet" href="milkdown-editor.css">', $assets);
        self::assertStringContainsString('<script type="module" src="milkdown-editor.js"></script>', $assets);
    }
}
