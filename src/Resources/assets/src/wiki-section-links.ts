/**
 * 1.40: section anchors of the page view (contract "section anchors and link picker" §4).
 *
 * - **Copy link:** the server appends `<a class="nowo-md-heading-anchor" href="#nowo-h-<id>" data-wiki-section-link
 *   data-wiki-copied-label="…">` to every heading (Twig `nowo_wiki_section_links`). Without JavaScript it is a plain
 *   fragment link; with it, a click copies the page URL with the fragment, puts the fragment in the address bar and says
 *   the copied label (title, accessible name and the polite live region of the control).
 * - **Legacy fragments:** a URL ending with `#<id>` where no element has that id, but a heading has `nowo-h-<id>`, scrolls
 *   to that heading (links written before 1.40 pointed at `#<id>`). Nothing else happens: a missing section leaves the
 *   page at the top, as the browser does.
 */

const LINK = 'a.nowo-md-heading-anchor[data-wiki-section-link]';
const PREFIX = 'nowo-h-';
const FLASH_MS = 1600;

const bound = new WeakSet<Document>();

/** The page URL (no query, no fragment) followed by the link's fragment. */
export function sectionUrl(link: HTMLAnchorElement, win: Window = window): string {
    const fragment = link.getAttribute('href') ?? '';

    return `${win.location.origin}${win.location.pathname}${fragment.startsWith('#') ? fragment : ''}`;
}

/** Says `text` on the control for a moment (title and accessible name), then restores its label. */
function flash(link: HTMLAnchorElement, text: string, win: Window): void {
    const label = link.getAttribute('title') ?? '';
    const name = link.querySelector<HTMLElement>('.nowo-milkdown-sr-only');
    link.setAttribute('title', text);
    if (name) {
        name.textContent = text;
    }
    win.setTimeout(() => {
        link.setAttribute('title', label);
        if (name) {
            name.textContent = label;
        }
    }, FLASH_MS);
}

/**
 * Copies a section link on click (delegated, once per document). A modified click (new tab, …) is left to the browser.
 *
 * @param doc - The document.
 */
export function bindSectionLinks(doc: Document = document): void {
    if (bound.has(doc)) {
        return;
    }
    bound.add(doc);
    doc.addEventListener('click', (event: MouseEvent) => {
        const target = event.target;
        const link = target instanceof Element ? target.closest<HTMLAnchorElement>(LINK) : null;
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        const win = doc.defaultView as Window;
        const url = sectionUrl(link, win);
        const clipboard = win.navigator.clipboard as Clipboard | undefined;
        if (!clipboard) {
            // No clipboard: the fragment link does its default (the address bar shows the section).
            return;
        }
        event.preventDefault();
        win.history.replaceState(win.history.state, '', url);
        void clipboard.writeText(url).then(
            () => flash(link, link.dataset.wikiCopiedLabel ?? '', win),
            () => undefined,
        );
    });
}

/**
 * Scrolls to `nowo-h-<id>` when the URL fragment is a legacy `#<id>` that no element has.
 *
 * @param doc - The document.
 * @returns Whether a heading was found and scrolled to.
 */
export function mapLegacyFragment(doc: Document = document): boolean {
    const win = doc.defaultView as Window;
    let id = '';
    try {
        id = decodeURIComponent(win.location.hash.slice(1));
    } catch {
        return false;
    }
    if (id === '' || id.startsWith(PREFIX) || doc.getElementById(id) !== null) {
        return false;
    }
    const heading = doc.getElementById(PREFIX + id);
    if (!heading || !/^H[1-6]$/.test(heading.tagName)) {
        return false;
    }
    heading.scrollIntoView();

    return true;
}
