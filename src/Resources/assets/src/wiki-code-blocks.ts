/**
 * Focusable code blocks (1.17, KIT-71): a rendered `<pre>` that overflows horizontally gets `tabindex="0"`, `role="group"`
 * and an `aria-label` ("Code block", from the wiki.js script's `data-code-label`), so keyboard users can scroll it
 * (WCAG 2.1.1, axe `scrollable-region-focusable`). Code blocks rendered after load (comments panel, page preview,
 * read-only editor) are picked up by a `MutationObserver`; a resize re-checks every block. Blocks that stop
 * overflowing lose what was added; attributes set by the markup itself are never touched. Code inside an editable
 * region (`contenteditable`) is left alone.
 */

/** Marker of the attributes this module added (so it never removes the markup's own). */
export const MARK_ATTR = 'data-wiki-scroll-focus';

/** Delay that batches DOM / resize changes into one pass (ms). */
export const RECHECK_DELAY_MS = 50;

/** Fallback accessible name of a focusable code block. */
export const DEFAULT_CODE_LABEL = 'Code block';

/**
 * Whether an element is wider than its box (1px tolerance for sub-pixel rounding).
 *
 * @param el - The element.
 */
export function overflowsHorizontally(el: Element): boolean {
    return el.scrollWidth > el.clientWidth + 1;
}

/**
 * Whether a `<pre>` sits in an editable region (an editor being edited).
 *
 * @param el - The element.
 */
function isEditable(el: Element): boolean {
    return el.closest('[contenteditable]:not([contenteditable="false"])') !== null;
}

/**
 * Makes the overflowing `<pre>` elements of a subtree focusable and labelled, and undoes it on those that no longer
 * overflow.
 *
 * @param root - Where to look (the root itself counts when it is a `<pre>`).
 * @param label - Accessible name of the blocks.
 * @param overflows - Overflow test (tests replace it: jsdom has no layout).
 * @returns The number of blocks marked as focusable after the pass.
 */
export function markScrollableCode(
    root: ParentNode = document,
    label: string = DEFAULT_CODE_LABEL,
    overflows: (el: Element) => boolean = overflowsHorizontally,
): number {
    const blocks = [...root.querySelectorAll('pre')];
    if (root instanceof HTMLPreElement) {
        blocks.unshift(root);
    }
    let marked = 0;
    for (const pre of blocks) {
        const ours = pre.hasAttribute(MARK_ATTR);
        if (!isEditable(pre) && overflows(pre)) {
            if (!ours && !pre.hasAttribute('tabindex')) {
                pre.setAttribute('tabindex', '0');
                pre.setAttribute(MARK_ATTR, pre.hasAttribute('aria-label') ? 'tabindex' : 'tabindex label');
                if (!pre.hasAttribute('aria-label')) {
                    pre.setAttribute('aria-label', label);
                    if (!pre.hasAttribute('role')) {
                        pre.setAttribute('role', 'group');
                    }
                }
            }
            marked += pre.hasAttribute(MARK_ATTR) ? 1 : 0;
            continue;
        }
        if (ours) {
            if (`${pre.getAttribute(MARK_ATTR)}`.includes('label')) {
                pre.removeAttribute('aria-label');
                if (pre.getAttribute('role') === 'group') {
                    pre.removeAttribute('role');
                }
            }
            pre.removeAttribute('tabindex');
            pre.removeAttribute(MARK_ATTR);
        }
    }

    return marked;
}

/**
 * Accessible name for the code blocks: the wiki.js script's `data-code-label`, else {@see DEFAULT_CODE_LABEL}.
 *
 * @param script - The script element (`document.currentScript` while wiki.js runs).
 */
export function codeLabel(script: HTMLOrSVGScriptElement | null): string {
    const label = script instanceof HTMLScriptElement ? (script.dataset.codeLabel ?? '').trim() : '';

    return label !== '' ? label : DEFAULT_CODE_LABEL;
}

/**
 * Marks the code blocks now and whenever the DOM or the window size changes (batched: one pass per {@see RECHECK_DELAY_MS}).
 *
 * @param label - Accessible name of the blocks.
 * @param doc - The document.
 * @param win - The window.
 * @param overflows - Overflow test.
 * @returns A function that stops watching.
 */
export function observeScrollableCode(
    label: string = DEFAULT_CODE_LABEL,
    doc: Document = document,
    win: Window = window,
    overflows: (el: Element) => boolean = overflowsHorizontally,
): () => void {
    let queued = false;
    const run = (): void => {
        queued = false;
        markScrollableCode(doc, label, overflows);
    };
    const schedule = (): void => {
        if (queued) {
            return;
        }
        queued = true;
        win.setTimeout(run, RECHECK_DELAY_MS);
    };

    markScrollableCode(doc, label, overflows);
    const observer = new MutationObserver((records) => {
        // Our own attribute changes are not observed (childList only); any added node may hold or be a code block.
        if (records.some((record) => record.addedNodes.length > 0)) {
            schedule();
        }
    });
    observer.observe(doc.documentElement, { childList: true, subtree: true });
    win.addEventListener('resize', schedule);

    return () => {
        observer.disconnect();
        win.removeEventListener('resize', schedule);
    };
}
