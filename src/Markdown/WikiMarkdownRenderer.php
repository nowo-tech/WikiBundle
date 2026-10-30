<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

use function trim;

/**
 * Default Markdown renderer of Markdown revisions (1.11): league/commonmark with GitHub-flavoured Markdown (tables,
 * task lists, strikethrough, autolinks), raw HTML escaped (never interpreted), unsafe links (`javascript:`, `data:`…)
 * dropped, plus every extension tagged `nowo_wiki.markdown_extension` ({@see WikiMarkdownExtensionInterface}; none by
 * default). The converter is built once in the constructor and keeps no per-request state.
 */
final readonly class WikiMarkdownRenderer implements WikiMarkdownRendererInterface
{
    /** Deepest block / inline nesting parsed (deeper input is kept as text). */
    public const MAX_NESTING_LEVEL = 50;

    private MarkdownConverter $converter;

    /**
     * Builds the CommonMark environment with the core, GFM and host extensions.
     *
     * @param iterable<ExtensionInterface> $extensions extensions tagged nowo_wiki.markdown_extension
     */
    public function __construct(
        #[AutowireIterator('nowo_wiki.markdown_extension')]
        iterable $extensions = [],
    ) {
        $environment = new Environment([
            'html_input'         => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level'  => self::MAX_NESTING_LEVEL,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        foreach ($extensions as $extension) {
            $environment->addExtension($extension);
        }

        $environment->addEventListener(DocumentParsedEvent::class, new WikiTaskListNormalizer(), -100);
        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $markdown): string
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return '';
        }

        return trim($this->converter->convert($markdown)->getContent());
    }
}
