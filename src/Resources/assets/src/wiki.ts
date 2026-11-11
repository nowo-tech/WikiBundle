import { bindWikiComments, openRequestedPanel } from './wiki-comments';
import { bindThreadList } from './wiki-comments-list';
import { bindCommentsModeration } from './wiki-comments-moderation';
import { codeLabel, observeScrollableCode } from './wiki-code-blocks';
import { bindTreeHiddenFocus, observeCardToolbar } from './wiki-layout';

/** True when the element scrolls its own content vertically (overflow auto / scroll and taller content). */
const scrollsItself = (el: HTMLElement): boolean => {
    const { overflowY } = getComputedStyle(el);

    return (overflowY === 'auto' || overflowY === 'scroll') && el.scrollHeight > el.clientHeight;
};

/**
 * The tree's own scroll container: the tree itself or an ancestor up to its sidebar (`[data-wiki-sidebar]`) that
 * scrolls on its own. Null when the tree flows with the page (phones: the sidebar is stacked under the content and
 * has no height cap), so the page / window scroller is never a candidate.
 *
 * @param tree - A page tree (`[data-wiki-tree]`).
 */
export function treeScroller(tree: HTMLElement): HTMLElement | null {
    const boundary = tree.closest<HTMLElement>('[data-wiki-sidebar]') ?? tree;
    for (let el: HTMLElement | null = tree; el !== null; el = el.parentElement) {
        if (scrollsItself(el)) {
            return el;
        }
        if (el === boundary) {
            break;
        }
    }

    return null;
}

/**
 * Scrolls `scroller` (and only it: no `scrollIntoView`, which would also scroll the page) so that `item` is
 * visible, by the smallest amount ("nearest").
 */
export function revealInScroller(scroller: HTMLElement, item: HTMLElement): void {
    const box = scroller.getBoundingClientRect();
    const rect = item.getBoundingClientRect();
    const top = box.top + scroller.clientTop;
    const bottom = top + scroller.clientHeight;
    if (rect.top < top) {
        scroller.scrollTop += rect.top - top;
    } else if (rect.bottom > bottom) {
        scroller.scrollTop += Math.min(rect.bottom - bottom, rect.top - top);
    }
}

/**
 * Wiki page tree: brings the current page (`aria-current="page"`) into view inside each tree (`[data-wiki-tree]`)
 * that is its own scroller (the sticky side column), so long trees open at the page being read. Only the tree's
 * container moves: the page is never scrolled (MUX-80: on phones the tree sits under the content, and
 * `scrollIntoView` dragged long pages to the bottom). No-op without a current page or a scrolling tree.
 *
 * @param root - Document subtree to scan for page trees.
 */
export function initWikiTrees(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>('[data-wiki-tree]').forEach((tree) => {
        const current = tree.querySelector('[aria-current="page"]');
        const scroller = current instanceof HTMLElement ? treeScroller(tree) : null;
        if (scroller !== null) {
            revealInScroller(scroller, current as HTMLElement);
        }
    });
}

/** A move asked by the UiKit tree: Alt+arrows (`nowo-ui:tree:move`) or a pointer drop (`nowo-ui:tree:drop`). */
export type TreeMoveDetail = { id: string; direction: 'up' | 'down' | 'in' | 'out' };
export type TreeDropDetail = { id: string; targetId: string; position: 'before' | 'after' | 'inside' };

/** Where a page goes: its new parent item (null: top level) and its index among the new siblings, itself left out. */
export interface TreePlan {
    item: HTMLLIElement;
    parent: HTMLLIElement | null;
    index: number;
}

const NODE = 'li[data-nowo-ui-tree-node]';

const nodeItem = (tree: HTMLElement, id: string): HTMLLIElement | null =>
    tree.querySelector<HTMLLIElement>(`${NODE}[data-nowo-ui-tree-node="${CSS.escape(id)}"]`);

const parentNode = (tree: HTMLElement, item: HTMLLIElement): HTMLLIElement | null => {
    const parent = item.parentElement?.closest<HTMLLIElement>(NODE) ?? null;

    return parent && tree.contains(parent) ? parent : null;
};

const childItems = (list: Element | null | undefined): HTMLLIElement[] =>
    list ? ([...list.children].filter((el) => el.matches(NODE)) as HTMLLIElement[]) : [];

const groupOf = (item: HTMLLIElement): HTMLUListElement | null => item.querySelector<HTMLUListElement>(':scope > ul[role="group"]');

const linkOf = (item: HTMLLIElement): HTMLElement | null => item.querySelector<HTMLElement>(':scope > .nowo-ui-tree__row > [data-nowo-ui-tree-item]');

/**
 * Turns a tree move request into a target (null when there is nowhere to go: first item up, top level out, a drop
 * onto the page itself or into its own sub-tree).
 *
 * @param tree - The tree root (`ul[role=tree]`).
 * @param detail - The move or drop of the UiKit tree.
 */
export function planTreeMove(tree: HTMLElement, detail: TreeMoveDetail | TreeDropDetail): TreePlan | null {
    const item = nodeItem(tree, detail.id);
    if (!item) {
        return null;
    }
    const parent = parentNode(tree, item);
    const siblings = childItems(item.parentElement);
    const index = siblings.indexOf(item);
    if ('direction' in detail) {
        switch (detail.direction) {
            case 'up':
                return index > 0 ? { item, parent, index: index - 1 } : null;
            case 'down':
                return index < siblings.length - 1 ? { item, parent, index: index + 1 } : null;
            case 'in': {
                const previous = siblings[index - 1];

                return previous ? { item, parent: previous, index: childItems(groupOf(previous)).length } : null;
            }
            default: {
                if (!parent) {
                    return null;
                }

                return { item, parent: parentNode(tree, parent), index: childItems(parent.parentElement).indexOf(parent) + 1 };
            }
        }
    }
    const target = nodeItem(tree, detail.targetId);
    if (!target || item.contains(target)) {
        return null;
    }
    if (detail.position === 'inside') {
        return { item, parent: target, index: childItems(groupOf(target)).filter((el) => el !== item).length };
    }
    const others = childItems(target.parentElement).filter((el) => el !== item);

    return { item, parent: parentNode(tree, target), index: others.indexOf(target) + (detail.position === 'after' ? 1 : 0) };
}

/**
 * Moves the item in the DOM after the server accepted the move: the new parent gets a disclosure and an expanded
 * group if it had none, the old one loses them when it is left empty, and every item gets its aria-level / setsize /
 * posinset again. The moved item becomes the tab stop and takes the focus.
 *
 * @param tree - The tree root.
 * @param plan - The accepted move.
 */
export function applyTreeMove(tree: HTMLElement, plan: TreePlan): void {
    const { item, parent, index } = plan;
    const oldParent = parentNode(tree, item);
    let list: HTMLElement = tree;
    if (parent) {
        let group = groupOf(parent);
        const link = linkOf(parent);
        if (!group) {
            group = tree.ownerDocument.createElement('ul');
            group.className = tree.querySelector('ul[role="group"]')?.className || 'nowo-ui-tree__group';
            group.setAttribute('role', 'group');
            group.id = `${tree.id}-${String(parent.dataset.nowoUiTreeNode).replace(/[^A-Za-z0-9_-]/g, '_')}-group`;
            parent.append(group);
            parent.querySelector(':scope > .nowo-ui-tree__row > .nowo-ui-tree__spacer')?.setAttribute('class', 'nowo-ui-tree__toggle');
            parent.querySelector(':scope > .nowo-ui-tree__row > .nowo-ui-tree__toggle')?.setAttribute('data-nowo-ui-tree-toggle', '');
            link?.setAttribute('aria-owns', group.id);
        }
        group.hidden = false;
        link?.setAttribute('aria-expanded', 'true');
        list = group;
    }
    item.remove();
    list.insertBefore(item, childItems(list)[index] ?? null);
    if (oldParent && oldParent !== parent && childItems(groupOf(oldParent)).length === 0) {
        groupOf(oldParent)?.remove();
        const toggle = oldParent.querySelector(':scope > .nowo-ui-tree__row > .nowo-ui-tree__toggle');
        toggle?.removeAttribute('data-nowo-ui-tree-toggle');
        toggle?.setAttribute('class', 'nowo-ui-tree__spacer');
        linkOf(oldParent)?.removeAttribute('aria-expanded');
        linkOf(oldParent)?.removeAttribute('aria-owns');
    }
    const number = (list: Element, level: number): void => {
        const items = childItems(list);
        items.forEach((child, i) => {
            const link = linkOf(child);
            link?.setAttribute('aria-level', String(level));
            link?.setAttribute('aria-setsize', String(items.length));
            link?.setAttribute('aria-posinset', String(i + 1));
            const group = groupOf(child);
            if (group) {
                number(group, level + 1);
            }
        });
    };
    number(tree, 1);
    tree.querySelectorAll<HTMLElement>('[data-nowo-ui-tree-item]').forEach((link) => {
        link.tabIndex = -1;
    });
    const link = linkOf(item) as HTMLElement;
    link.tabIndex = 0;
    link.focus();
}

/**
 * Says the text in the tree's polite live region (the same region as UiKit's `announceTree`, created on first use).
 *
 * @param tree - The tree root.
 * @param text - What to say.
 */
export function announceInTree(tree: HTMLElement, text: string): void {
    let live = tree.parentElement?.querySelector<HTMLElement>(':scope > [data-nowo-ui-tree-live]') ?? null;
    if (!live) {
        live = tree.ownerDocument.createElement('div');
        live.className = 'visually-hidden';
        live.setAttribute('data-nowo-ui-tree-live', '');
        live.setAttribute('role', 'status');
        tree.after(live);
    }
    live.textContent = text;
}

/** JSON POST to a tree endpoint with the tree's CSRF token (same-origin cookies). */
export type TreePoster = (url: string, body: Record<string, unknown>) => Promise<{ ok: boolean; data: { message?: string } }>;

const postJson: TreePoster = async (url, body) => {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
    });
    const data = response.status === 204 ? {} : ((await response.json().catch(() => ({}))) as { message?: string });

    return { ok: response.ok, data };
};

let poster: TreePoster = postJson;

/**
 * Replaces the JSON transport of the tree endpoints (tests, hosts with their own transport).
 *
 * @param next - The transport.
 */
export function setTreePoster(next: TreePoster): void {
    poster = next;
}

const boundTrees = new WeakSet<Document>();

/**
 * Page tree glue (1.14, `tree.enabled`): listens to the UiKit tree of the sidebar (`[data-wiki-tree][role=tree]`).
 * A fold change is recorded (`data-wiki-tree-state`); with `data-wiki-tree-move` a move or drop is sent to the server
 * and, once accepted, applied in place and announced (the server's sentence, or `data-wiki-tree-error`). One move at
 * a time.
 *
 * @param doc - The document (bound once).
 */
export function bindWikiTree(doc: Document = document): void {
    if (boundTrees.has(doc)) {
        return;
    }
    boundTrees.add(doc);
    let busy = false;
    doc.addEventListener('nowo-ui:tree:toggle', (event) => {
        const tree = (event.target as Element | null)?.closest?.<HTMLElement>('[data-wiki-tree][role="tree"]');
        const { id, expanded } = (event as CustomEvent<{ id: string; expanded: boolean }>).detail;
        const url = tree?.dataset.wikiTreeState;
        if (url) {
            void poster(url, { page: id, expanded, _token: tree.dataset.wikiTreeToken ?? '' }).catch(() => undefined);
        }
    });
    const onMove = (event: Event): void => {
        const tree = (event.target as Element | null)?.closest?.<HTMLElement>('[data-wiki-tree][role="tree"]');
        const url = tree?.dataset.wikiTreeMove;
        if (!tree || !url) {
            return;
        }
        event.preventDefault();
        const plan = busy ? null : planTreeMove(tree, (event as CustomEvent<TreeMoveDetail | TreeDropDetail>).detail);
        if (!plan) {
            return;
        }
        busy = true;
        const body = { page: plan.item.dataset.nowoUiTreeNode, parent: plan.parent?.dataset.nowoUiTreeNode ?? null, index: plan.index, _token: tree.dataset.wikiTreeToken ?? '' };
        void poster(url, body)
            .then(({ ok, data }) => {
                if (ok) {
                    applyTreeMove(tree, plan);
                }
                announceInTree(tree, data.message ?? tree.dataset.wikiTreeError ?? '');
            })
            .catch(() => announceInTree(tree, tree.dataset.wikiTreeError ?? ''))
            .finally(() => {
                busy = false;
            });
    };
    doc.addEventListener('nowo-ui:tree:move', onMove);
    doc.addEventListener('nowo-ui:tree:drop', onMove);
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
bindWikiTree();
bindWikiComments();
bindCommentsModeration();
bindThreadList();
bindTreeHiddenFocus();
observeCardToolbar();
whenLoaded(() => {
    openRequestedPanel();
    void renderWikiRichContent();
});
observeScrollableCode(codeLabel(document.currentScript));
