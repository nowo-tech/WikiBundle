import { afterEach, describe, expect, it, vi } from 'vitest';
import { currentNonce, initWikiTrees, renderWikiRichContent, whenLoaded } from './wiki';

describe('wiki page tree', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('scrolls the current page of each tree into view', () => {
        document.body.innerHTML = `
            <ul data-wiki-tree><li><a href="/a">A</a></li><li><a href="/b" aria-current="page">B</a></li></ul>
            <ul data-wiki-tree><li><a href="/c">C</a></li></ul>
        `;
        const current = document.querySelector('[aria-current="page"]') as HTMLElement;
        const scroll = vi.fn();
        current.scrollIntoView = scroll;

        initWikiTrees(document);

        expect(scroll).toHaveBeenCalledTimes(1);
        expect(scroll).toHaveBeenCalledWith({ block: 'nearest' });
    });

    it('ignores trees without a current page and elements without scrollIntoView', () => {
        document.body.innerHTML = '<ul data-wiki-tree><li><a href="/a" aria-current="page">A</a></li></ul>';
        const current = document.querySelector('[aria-current="page"]') as unknown as { scrollIntoView?: unknown };
        current.scrollIntoView = undefined;

        expect(() => initWikiTrees()).not.toThrow();
    });

    it('marks the document as ready on load', () => {
        expect(document.documentElement.classList.contains('wiki-ready')).toBe(true);
    });
});

describe('wiki rich content', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('draws every marked page body with the Milkdown API and the nonce', async () => {
        document.body.innerHTML = '<div data-wiki-rich-content id="a"></div><div data-wiki-rich-content id="b"></div><div id="c"></div>';
        const render = vi.fn().mockResolvedValueOnce(undefined).mockRejectedValueOnce(new Error('mermaid'));

        const drawn = await renderWikiRichContent(document, { renderRichContent: render }, 'n0nce');

        expect(drawn).toBe(1);
        expect(render).toHaveBeenCalledTimes(2);
        expect(render).toHaveBeenNthCalledWith(1, document.getElementById('a'), { nonce: 'n0nce' });
    });

    it('passes no nonce when there is none', async () => {
        document.body.innerHTML = '<div data-wiki-rich-content></div>';
        const render = vi.fn().mockResolvedValue(undefined);

        await renderWikiRichContent(document, { renderRichContent: render }, undefined);

        expect(render).toHaveBeenCalledWith(document.querySelector('[data-wiki-rich-content]'), {});
    });

    it('does nothing without a marked body or without the Milkdown API', async () => {
        const render = vi.fn();
        expect(await renderWikiRichContent(document, { renderRichContent: render })).toBe(0);

        document.body.innerHTML = '<div data-wiki-rich-content></div>';
        expect(await renderWikiRichContent(document, undefined)).toBe(0);
        expect(await renderWikiRichContent()).toBe(0);
        expect(render).not.toHaveBeenCalled();
    });
});

describe('wiki boot helpers', () => {
    it('reads the nonce of a script element', () => {
        const script = document.createElement('script');
        expect(currentNonce(script)).toBeUndefined();
        script.nonce = 'abc';
        expect(currentNonce(script)).toBe('abc');
        expect(currentNonce(null)).toBeUndefined();
    });

    it('runs now when the page is loaded, on load otherwise', () => {
        const now = vi.fn();
        whenLoaded(now, { readyState: 'complete' } as Document);
        expect(now).toHaveBeenCalledTimes(1);

        const later = vi.fn();
        const win = new EventTarget() as Window;
        whenLoaded(later, { readyState: 'loading' } as Document, win);
        expect(later).not.toHaveBeenCalled();
        win.dispatchEvent(new Event('load'));
        win.dispatchEvent(new Event('load'));
        expect(later).toHaveBeenCalledTimes(1);

        const fallback = vi.fn();
        whenLoaded(fallback);
        expect(fallback.mock.calls.length + (document.readyState === 'complete' ? 0 : 1)).toBe(1);
    });
});
