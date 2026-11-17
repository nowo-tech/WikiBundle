/**
 * Page layout glue (1.30, every part opt-in through the markup the options print):
 *
 * - `web_ui.tree_collapsed_mode: hidden` (`.nowo-wiki-layout--tree-hidden`): a collapsed tree is not drawn at all and
 *   its expand button sits in the page toolbar (`[data-nowo-wiki-tree-toggle]`, shown by wiki.css only while the tree
 *   is collapsed). UiKit `js/nowo-ui-rail.js` toggles and stores the state; this module only moves the focus, which
 *   would otherwise be lost with the button that disappears: collapsing moves it to the toolbar button, expanding to
 *   the tree card's own toggle ({@link bindTreeHiddenFocus}).
 * - `web_ui.page_header.toolbar_position: card` (`[data-nowo-wiki-card-frame]`): the size of the sticky toolbar is
 *   published as `--nowo-wiki-card-toolbar-width` / `-height` on the frame, so the first block of the body (the title
 *   `<h1>`) keeps clear of it and a focused element scrolled into view stops below it ({@link observeCardToolbar}).
 *
 * Delegated listeners, no inline handlers or styles other than one custom property; nothing kept between pages.
 */

/** Id of the tree card (WikiTreeRail::ID). */
export const TREE_ID = 'nowo-wiki-tree';

const boundDocs = new WeakSet<Document>();

/**
 * Moves the focus between the tree's two toggles when the tree is shown or hidden in the `hidden` mode. Only when the
 * focus was on a toggle of the tree (or nowhere): a change made through the API or a shortcut elsewhere leaves it.
 *
 * @param doc - The document (bound once).
 */
export function bindTreeHiddenFocus(doc: Document = document): void {
    if (boundDocs.has(doc)) {
        return;
    }
    boundDocs.add(doc);
    doc.addEventListener('nowo-ui:rail:change', (event) => {
        const host = event.target;
        if (!(host instanceof HTMLElement) || host.id !== TREE_ID || !host.closest('.nowo-wiki-layout--tree-hidden')) {
            return;
        }
        // Never null while a document has a body; <body> when the focused button just disappeared.
        const active = doc.activeElement as Element;
        if (active !== doc.body && !active.matches('[data-nowo-ui-rail-toggle]')) {
            return;
        }
        const collapsed = (event as CustomEvent<{ collapsed?: boolean }>).detail?.collapsed === true;
        const next = collapsed
            ? doc.querySelector<HTMLElement>('[data-nowo-wiki-tree-toggle]')
            : host.querySelector<HTMLElement>('[data-nowo-ui-rail-toggle]');
        next?.focus();
    });
}

/**
 * Publishes the width of each card toolbar (`[data-nowo-wiki-card-toolbar]` in a `[data-nowo-wiki-card-frame]`) as
 * `--nowo-wiki-card-toolbar-width` on its frame, now and whenever it changes (ResizeObserver; once without it).
 *
 * @param root - Document subtree to scan.
 * @param Observer - The ResizeObserver class (tests; undefined = measure once).
 * @returns How many toolbars were found.
 */
export function observeCardToolbar(root: ParentNode = document, Observer: typeof ResizeObserver | undefined = globalThis.ResizeObserver): number {
    const bars = [...root.querySelectorAll<HTMLElement>('[data-nowo-wiki-card-frame] > [data-nowo-wiki-card-toolbar]')];
    bars.forEach((bar) => {
        // The selector guarantees the frame parent.
        const frame = bar.parentElement as HTMLElement;
        const inner = bar.firstElementChild instanceof HTMLElement ? bar.firstElementChild : bar;
        const publish = (): void => {
            const box = inner.getBoundingClientRect();
            frame.style.setProperty('--nowo-wiki-card-toolbar-width', `${Math.ceil(box.width)}px`);
            frame.style.setProperty('--nowo-wiki-card-toolbar-height', `${Math.ceil(box.height)}px`);
        };
        publish();
        if (typeof Observer === 'function') {
            new Observer(publish).observe(inner);
        }
    });

    return bars.length;
}

const drawerDocs = new WeakSet<Document>();

/**
 * 1.41: the page tree as a drawer in the UiKit shell's focus mode. The "Pages" button (`[data-wiki-focus-tree-toggle]`,
 * printed only when UiKit's focus mode is enabled and shown by wiki.css only while the shell has `data-focus-mode`)
 * opens and closes the tree card (`[data-wiki-sidebar]`, `data-wiki-drawer-open`; wiki.css turns it into a drawer at the
 * start edge in focus mode). Escape, or a click outside the drawer and the button, closes it; on open the focus moves
 * to the tree's tab stop, on close back to the button. Outside focus mode the attribute changes nothing.
 *
 * @param doc - The document (bound once).
 */
export function bindFocusTreeDrawer(doc: Document = document): void {
    if (drawerDocs.has(doc)) {
        return;
    }
    drawerDocs.add(doc);
    const set = (button: HTMLElement, open: boolean): void => {
        const drawer = doc.querySelector<HTMLElement>('[data-wiki-sidebar]');
        if (!drawer) {
            return;
        }
        button.setAttribute('aria-expanded', String(open));
        drawer.toggleAttribute('data-wiki-drawer-open', open);
        (open ? drawer.querySelector<HTMLElement>('[data-nowo-ui-tree-item][tabindex="0"]') : button)?.focus();
    };
    const openButton = (): HTMLElement | null => doc.querySelector<HTMLElement>('[data-wiki-focus-tree-toggle][aria-expanded="true"]');
    doc.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const button = target?.closest<HTMLElement>('[data-wiki-focus-tree-toggle]');
        if (button) {
            set(button, button.getAttribute('aria-expanded') !== 'true');

            return;
        }
        const open = openButton();
        if (open && !target?.closest('[data-wiki-sidebar]')) {
            set(open, false);
        }
    });
    doc.addEventListener('keydown', (event) => {
        const open = event.key === 'Escape' ? openButton() : null;
        if (open) {
            set(open, false);
        }
    });
}
