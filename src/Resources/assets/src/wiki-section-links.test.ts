import { afterEach, describe, expect, it, vi } from 'vitest';
import { bindSectionLinks, mapLegacyFragment, sectionUrl } from './wiki-section-links';

const heading = (id: string, label = 'Link to this section'): string =>
    `<h2 id="nowo-h-${id}">Set up <a class="nowo-md-heading-anchor" data-wiki-section-link data-wiki-copied-label="Link copied" href="#nowo-h-${id}" title="${label}"><span class="nowo-milkdown-sr-only">${label}</span><span>#</span></a></h2>`;

describe('section links (1.40)', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        window.history.replaceState(null, '', '/');
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('builds the page URL with the fragment, without the query', () => {
        window.history.replaceState(null, '', '/wiki/eng/guide?x=1#old');
        document.body.innerHTML = heading('setup');
        const link = document.querySelector('a') as HTMLAnchorElement;

        expect(sectionUrl(link)).toBe(`${window.location.origin}/wiki/eng/guide#nowo-h-setup`);
        link.setAttribute('href', 'https://evil.example/');
        expect(sectionUrl(link)).toBe(`${window.location.origin}/wiki/eng/guide`);
        link.removeAttribute('href');
        expect(sectionUrl(link)).toBe(`${window.location.origin}/wiki/eng/guide`);
    });

    it('copies the link, shows it in the address bar and says it was copied', async () => {
        vi.useFakeTimers();
        window.history.replaceState(null, '', '/wiki/eng/guide');
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        document.body.innerHTML = heading('setup');
        bindSectionLinks(document);
        bindSectionLinks(document);
        const link = document.querySelector('a') as HTMLAnchorElement;

        const click = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
        (link.querySelector('span') as HTMLElement).dispatchEvent(click);
        await Promise.resolve();
        await Promise.resolve();

        expect(click.defaultPrevented).toBe(true);
        expect(writeText).toHaveBeenCalledTimes(1);
        expect(writeText).toHaveBeenCalledWith(`${window.location.origin}/wiki/eng/guide#nowo-h-setup`);
        expect(window.location.hash).toBe('#nowo-h-setup');
        expect(link.getAttribute('title')).toBe('Link copied');
        expect(link.querySelector('.nowo-milkdown-sr-only')?.textContent).toBe('Link copied');
        vi.advanceTimersByTime(2000);
        expect(link.getAttribute('title')).toBe('Link to this section');
        expect(link.querySelector('.nowo-milkdown-sr-only')?.textContent).toBe('Link to this section');
    });

    it('leaves modified clicks, other links and a missing clipboard to the browser', async () => {
        const writeText = vi.fn().mockRejectedValue(new Error('denied'));
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        document.body.innerHTML = `${heading('a')}<a id="other" href="#x">x</a>`;
        bindSectionLinks(document);
        const link = document.querySelector('a.nowo-md-heading-anchor') as HTMLAnchorElement;
        link.removeAttribute('title');
        (link.querySelector('.nowo-milkdown-sr-only') as HTMLElement).remove();

        const modified = new MouseEvent('click', { bubbles: true, cancelable: true, ctrlKey: true });
        link.dispatchEvent(modified);
        expect(modified.defaultPrevented).toBe(false);
        const other = new MouseEvent('click', { bubbles: true, cancelable: true });
        (document.getElementById('other') as HTMLElement).dispatchEvent(other);
        expect(other.defaultPrevented).toBe(false);
        document.dispatchEvent(new MouseEvent('click', { bubbles: true }));

        // A refused copy changes nothing.
        link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await Promise.resolve();
        await Promise.resolve();
        expect(writeText).toHaveBeenCalledTimes(1);
        expect(link.hasAttribute('title')).toBe(false);

        Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true });
        const plain = new MouseEvent('click', { bubbles: true, cancelable: true });
        link.dispatchEvent(plain);
        expect(plain.defaultPrevented).toBe(false);
    });

    it('a copy without a copied label says nothing new', async () => {
        vi.useFakeTimers();
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        const doc = document.implementation.createHTMLDocument('x');
        doc.body.innerHTML = heading('b');
        Object.defineProperty(doc, 'defaultView', { value: window });
        bindSectionLinks(doc);
        const link = doc.querySelector('a') as HTMLAnchorElement;
        delete link.dataset.wikiCopiedLabel;
        link.querySelector('.nowo-milkdown-sr-only')?.remove();
        link.removeAttribute('title');

        link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await Promise.resolve();
        await Promise.resolve();

        expect(link.getAttribute('title')).toBe('');
        vi.advanceTimersByTime(2000);
        expect(link.getAttribute('title')).toBe('');
    });

    it('maps a legacy fragment to its heading and nothing else', () => {
        document.body.innerHTML = `${heading('setup')}<p id="plain">x</p><p id="nowo-h-para">y</p>`;
        const target = document.getElementById('nowo-h-setup') as HTMLElement;
        target.scrollIntoView = vi.fn();

        window.history.replaceState(null, '', '/p#setup');
        expect(mapLegacyFragment(document)).toBe(true);
        expect(target.scrollIntoView).toHaveBeenCalledTimes(1);

        for (const hash of ['', '#nowo-h-setup', '#plain', '#missing', '#para', '#%E0%A4%A']) {
            window.history.replaceState(null, '', `/p${hash}`);
            expect(mapLegacyFragment(document)).toBe(false);
        }
        expect(target.scrollIntoView).toHaveBeenCalledTimes(1);
    });
});
