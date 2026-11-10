<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\FileCard;
use Symfony\Contracts\Service\ResetInterface;

use function bin2hex;
use function count;
use function preg_match;
use function preg_replace_callback;
use function random_bytes;
use function str_contains;

/**
 * 1.28 (Symfony Beacon spec 212 block B): the file preview frames of the per-viewer render, kept out of the wiki
 * sanitizer (which never lets a sandboxed `<iframe>` through) and put back after it.
 *
 * {@see WikiLeafDirectiveRenderer} registers the `<iframe>` Milkdown's {@see FileCard}
 * built for a preview the host's `FilePreviewResolverInterface` approved, and writes an empty placeholder
 * `<span class="nowo-wiki-file-frame nowo-wiki-file-frame--<token>"></span>` instead; `nowo_wiki_safe_html` sanitizes,
 * then {@see self::hydrate()} replaces each placeholder whose token this request registered with its frame. The token is
 * 128 random bits made during the render, so stored HTML, an HTML revision, a comment or any author text can never name
 * one: an unknown placeholder is removed, never framed. Only the frame markup Milkdown built (resolver URL through
 * `safeUrl()` and the wiki link policy, the `file_preview_sandbox` sandbox, `loading="lazy"`,
 * `referrerpolicy="no-referrer"`) is ever written back.
 *
 * Request-scoped: emptied by `kernel.reset` (FrankenPHP worker mode) and bounded to {@see self::MAX_FRAMES} frames
 * waiting for hydration; a token is used once. Renders that are never hydrated run {@see self::withoutFrames()}.
 */
final class WikiFilePreviewFrames implements ResetInterface
{
    /** Frames one request may register; later previews read as plain cards. */
    public const MAX_FRAMES = 1000;

    /** Class of the placeholder (and prefix of its token class). */
    public const PLACEHOLDER_CLASS = 'nowo-wiki-file-frame';

    /** The frame Milkdown's FileCard writes (bundle markup, every value escaped). */
    private const FRAME = '~^<iframe class="nowo-md-file__frame" src="[^"<>]+" title="[^"<>]*" sandbox="[a-z -]*" loading="lazy" referrerpolicy="no-referrer" height="\d{1,4}"></iframe>$~D';

    /** A placeholder in the sanitizer's canonical form. */
    private const PLACEHOLDER = '~<span\b[^>]*\bclass="' . self::PLACEHOLDER_CLASS . ' ' . self::PLACEHOLDER_CLASS . '--([0-9a-f]{32})"[^>]*>\s*</span>~';

    /** @var array<string, string> frame markup by token */
    private array $frames = [];

    /** Renders in progress that are never hydrated ({@see self::withoutFrames()}). */
    private int $suspended = 0;

    /**
     * Keeps a frame for {@see self::hydrate()} and returns its placeholder, or null when the frame is not the markup
     * Milkdown writes or the request holds {@see self::MAX_FRAMES} frames already.
     *
     * @param string $frame the `<iframe>` of Milkdown's FileCard
     */
    public function placeholder(string $frame): ?string
    {
        if ($this->suspended > 0 || count($this->frames) >= self::MAX_FRAMES || preg_match(self::FRAME, $frame) !== 1) {
            return null;
        }
        $token                = bin2hex(random_bytes(16));
        $this->frames[$token] = $frame;

        return '<span class="' . self::PLACEHOLDER_CLASS . ' ' . self::PLACEHOLDER_CLASS . '--' . $token . '"></span>';
    }

    /**
     * Runs a render whose HTML is never hydrated (the reader's Markdown of the REST API): its preview cards read as plain
     * cards, as in 1.27, and register nothing (audit KG-W-L1, KG-W-I4).
     *
     * @template T
     *
     * @param callable(): T $render
     *
     * @return T
     */
    public function withoutFrames(callable $render): mixed
    {
        ++$this->suspended;
        try {
            return $render();
        } finally {
            --$this->suspended;
        }
    }

    /**
     * Puts the frames of this request back into sanitized HTML; any other placeholder is removed. A token is used once
     * (audit KG-W-I1): a second copy of a placeholder is removed too.
     *
     * @param string $html sanitized page HTML
     */
    public function hydrate(string $html): string
    {
        if (!str_contains($html, self::PLACEHOLDER_CLASS)) {
            return $html;
        }

        return preg_replace_callback(self::PLACEHOLDER, function (array $match): string {
            $frame = $this->frames[$match[1]] ?? '';
            unset($this->frames[$match[1]]);

            return $frame;
        }, $html) ?? $html;
    }

    public function reset(): void
    {
        $this->frames    = [];
        $this->suspended = 0;
    }
}
