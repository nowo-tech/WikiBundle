import { afterEach, describe, expect, it, vi } from 'vitest';
import { initWikiTrees } from './wiki';

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
        const current = document.querySelector('[aria-current="page"]') as HTMLElement & { scrollIntoView?: unknown };
        current.scrollIntoView = undefined;

        expect(() => initWikiTrees()).not.toThrow();
    });

    it('marks the document as ready on load', () => {
        expect(document.documentElement.classList.contains('wiki-ready')).toBe(true);
    });
});
