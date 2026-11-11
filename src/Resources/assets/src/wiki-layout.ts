/**
 * Page layout glue (1.30, every part opt-in through the markup the options print):
 *
 * - `web_ui.tree_collapsed_mode: hidden` (`.nowo-wiki-layout--tree-hidden`): a collapsed tree is not drawn at all and
 *   its expand button sits in the page toolbar (`[data-nowo-wiki-tree-toggle]`, shown by wiki.css only while the tree
 *   is collapsed). UiKit `js/nowo-ui-rail.js` toggles and stores the state; this module only moves the focus, which
 *   would otherwise be lost with the button that disappears: collapsing moves it to the toolbar button, expanding to
 *   the tree card's own toggle ({@link bindTreeHiddenFocus}).
 * - `web_ui.page_header.toolbar_position: card` (`[data-nowo-wiki-card-frame]`): the width of the sticky toolbar is
 *   published as `--nowo-wiki-card-toolbar-width` on the frame, so the first block of the body (the title `<h1>`)
 *   keeps clear of it ({@link observeCardToolbar}).
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
            frame.style.setProperty('--nowo-wiki-card-toolbar-width', `${Math.ceil(inner.getBoundingClientRect().width)}px`);
        };
        publish();
        if (typeof Observer === 'function') {
            new Observer(publish).observe(inner);
        }
    });

    return bars.length;
}
