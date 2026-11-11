<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Form\MilkdownEditorType;
use Nowo\WikiBundle\Search\WikiPlainText;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 1.31 fix: the translator of the **neutral** Milkdown environment, the one that renders the `content_html` cache every
 * reader, search excerpt and AI context share ({@see MilkdownWikiMarkdownRenderer}).
 *
 * That environment resolves nothing (no page, user, host file), so every page link and host reference reads *Content
 * not visible*; until 1.30 the text was translated in the locale of whoever saved the page and stored, so readers in
 * another language saw it in the saver's (a Spanish "Contenido no visible" under an English interface). Here the
 * placeholder is an empty string: the cache keeps only the language-neutral marker
 * `<span class="nowo-md-ref nowo-md-ref--hidden"></span>` (no title, id, URL or name of the hidden target, as before),
 * which plain text and excerpts drop ({@see WikiPlainText::fromHtml()}). Readers of a page see
 * the per-viewer render, which translates the placeholder in their own locale at render time.
 *
 * Any other message goes to the host translator (or is returned as its id without one). Stateless (worker mode).
 */
final readonly class WikiNeutralTranslator implements TranslatorInterface
{
    /** The Milkdown message of a reference the reader may not see. */
    public const HIDDEN = 'ref.hidden';

    public function __construct(private ?TranslatorInterface $inner = null)
    {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if ($id === self::HIDDEN && $domain === MilkdownEditorType::DOMAIN) {
            return '';
        }

        return $this->inner?->trans($id, $parameters, $domain, $locale) ?? $id;
    }

    public function getLocale(): string
    {
        return $this->inner?->getLocale() ?? 'en';
    }
}
