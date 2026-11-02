/**
 * Wiki page tree: brings the current page (`aria-current="page"`) into view inside each scrollable tree
 * (`[data-wiki-tree]`), so long trees open at the page being read. No-op without a current page.
 *
 * @param root - Document subtree to scan for page trees.
 */
export function initWikiTrees(root: ParentNode = document): void {
    root.querySelectorAll('[data-wiki-tree]').forEach((tree) => {
        const current = tree.querySelector('[aria-current="page"]');
        if (current instanceof HTMLElement && typeof current.scrollIntoView === 'function') {
            current.scrollIntoView({ block: 'nearest' });
        }
    });
}

/** The part of the nowo-tech/milkdown-editor-bundle browser API the wiki uses (1.13, `editor.type: milkdown`). */
export interface RichContentApi {
    renderRichContent(root: ParentNode, options?: { nonce?: string }): Promise<void>;
}

type RichContentWindow = Window & { NowoMilkdownEditor?: RichContentApi };

/**
 * Draws the Mermaid diagrams and math of every page body marked `data-wiki-rich-content` with the Milkdown read-side
 * renderer (strict Mermaid, bounded KaTeX, CSP nonce). Without the Milkdown script, or without such a body, nothing
 * happens; a failing body never stops the others.
 *
 * @param root - Document subtree to scan.
 * @param api - The Milkdown browser API (defaults to `window.NowoMilkdownEditor`).
 * @param nonce - CSP nonce of the response (defaults to the nonce of this script).
 */
export async function renderWikiRichContent(
    root: ParentNode = document,
    api: RichContentApi | undefined = (window as RichContentWindow).NowoMilkdownEditor,
    nonce: string | undefined = scriptNonce,
): Promise<number> {
    const bodies = [...root.querySelectorAll('[data-wiki-rich-content]')];
    if (bodies.length === 0 || typeof api?.renderRichContent !== 'function') {
        return 0;
    }

    const results = await Promise.allSettled(bodies.map((body) => api.renderRichContent(body, nonce ? { nonce } : {})));

    return results.filter((result) => result.status === 'fulfilled').length;
}

/**
 * Nonce of a script element (browsers hide the attribute value, the property keeps it), or undefined.
 *
 * @param script - The script element (`document.currentScript` while wiki.js runs).
 */
export function currentNonce(script: HTMLOrSVGScriptElement | null): string | undefined {
    const nonce = script instanceof HTMLScriptElement ? script.nonce : '';

    return nonce !== '' ? nonce : undefined;
}

/**
 * Runs the callback once the page (and the deferred Milkdown module) has loaded.
 *
 * @param callback - What to run.
 * @param doc - The document.
 * @param win - The window.
 */
export function whenLoaded(callback: () => void, doc: Document = document, win: Window = window): void {
    if (doc.readyState === 'complete') {
        callback();

        return;
    }

    win.addEventListener('load', callback, { once: true });
}

/** Nonce of the wiki.js script element, read while it runs. */
const scriptNonce = currentNonce(document.currentScript);

document.documentElement.classList.add('wiki-ready');
initWikiTrees();
whenLoaded(() => void renderWikiRichContent());
