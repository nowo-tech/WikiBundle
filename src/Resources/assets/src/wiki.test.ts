import { afterAll, afterEach, describe, expect, it, vi } from 'vitest';
import { RECHECK_DELAY_MS } from './wiki-code-blocks';
import { currentNonce, initWikiTrees, renderWikiRichContent, revealInScroller, treeScroller, whenLoaded } from './wiki';

// wiki.ts watches the document on import: let its queued code-block pass run before the test environment goes away.
afterAll(() => new Promise<void>((resolve) => {
    setTimeout(resolve, RECHECK_DELAY_MS + 50);
}));

describe('wiki page tree', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    /** Gives an element a box (jsdom has no layout): top / height in px, plus scrollHeight / clientHeight. */
    const box = (el: HTMLElement, top: number, height: number, scrollHeight = height): void => {
        el.getBoundingClientRect = () => ({ top, bottom: top + height, left: 0, right: 0, width: 0, height, x: 0, y: top, toJSON: () => ({}) });
        Object.defineProperty(el, 'clientHeight', { configurable: true, value: height });
        Object.defineProperty(el, 'scrollHeight', { configurable: true, value: scrollHeight });
    };

    const sidebar = (current: string) => `
        <aside data-wiki-sidebar><nav class="body" style="overflow-y: auto">
            <ul data-wiki-tree><li><a href="/a" ${current === 'a' ? 'aria-current="page"' : ''}>A</a></li><li><a href="/b" ${current === 'b' ? 'aria-current="page"' : ''}>B</a></li></ul>
        </nav></aside>`;

    it('scrolls only the tree container down to a current page below its fold, never the page (MUX-80)', () => {
        document.body.innerHTML = sidebar('b');
        const nav = document.querySelector('nav') as HTMLElement;
        const current = document.querySelector('[aria-current="page"]') as HTMLElement;
        box(nav, 100, 200, 900);
        box(current, 500, 30);
        current.scrollIntoView = vi.fn();
        const windowScroll = vi.spyOn(window, 'scrollTo').mockImplementation(() => undefined);

        initWikiTrees(document);

        expect(nav.scrollTop).toBe(230);
        expect(current.scrollIntoView).not.toHaveBeenCalled();
        expect(windowScroll).not.toHaveBeenCalled();
        windowScroll.mockRestore();
    });

    it('scrolls the tree container up to a current page above its fold, and leaves a visible one alone', () => {
        document.body.innerHTML = sidebar('a');
        const nav = document.querySelector('nav') as HTMLElement;
        const current = document.querySelector('[aria-current="page"]') as HTMLElement;
        box(nav, 100, 200, 900);
        nav.scrollTop = 300;
        box(current, 40, 30);
        initWikiTrees(document);
        expect(nav.scrollTop).toBe(240);

        box(current, 150, 30);
        initWikiTrees(document);
        expect(nav.scrollTop).toBe(240);
    });

    it('uses the tree itself when it is the scroller', () => {
        document.body.innerHTML = '<ul data-wiki-tree style="overflow-y: scroll"><li><a href="/a" aria-current="page">A</a></li></ul>';
        const tree = document.querySelector('ul') as HTMLElement;
        const current = document.querySelector('a') as HTMLElement;
        box(tree, 0, 100, 400);
        box(current, 300, 20);

        expect(treeScroller(tree)).toBe(tree);
        initWikiTrees();
        expect(tree.scrollTop).toBe(220);
    });

    it('does nothing when the tree flows with the page (phones), even inside a scrolling page container', () => {
        document.body.innerHTML = `<main style="overflow-y: auto">${sidebar('b')}</main>`;
        const main = document.querySelector('main') as HTMLElement;
        const nav = document.querySelector('nav') as HTMLElement;
        const current = document.querySelector('[aria-current="page"]') as HTMLElement;
        box(main, 0, 844, 5000);
        box(nav, 4000, 300, 300);
        box(current, 4200, 30);
        current.scrollIntoView = vi.fn();

        expect(treeScroller(document.querySelector('[data-wiki-tree]') as HTMLElement)).toBeNull();
        initWikiTrees(document);

        expect(main.scrollTop).toBe(0);
        expect(nav.scrollTop).toBe(0);
        expect(current.scrollIntoView).not.toHaveBeenCalled();
    });

    it('stops at a standalone tree and ignores trees without a current page', () => {
        document.body.innerHTML = '<main style="overflow-y: auto"><ul data-wiki-tree><li><a href="/a">A</a></li></ul></main>';
        const main = document.querySelector('main') as HTMLElement;
        box(main, 0, 100, 900);

        expect(treeScroller(document.querySelector('ul') as HTMLElement)).toBeNull();
        expect(() => initWikiTrees()).not.toThrow();
        expect(main.scrollTop).toBe(0);
    });

    it('reveals by the smallest amount, keeping the top of an item taller than the scroller', () => {
        document.body.innerHTML = '<div><span></span></div>';
        const scroller = document.querySelector('div') as HTMLElement;
        const item = document.querySelector('span') as HTMLElement;
        box(scroller, 0, 100, 1000);
        box(item, 150, 300);

        revealInScroller(scroller, item);

        expect(scroller.scrollTop).toBe(150);
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
