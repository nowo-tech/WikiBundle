/**
 * 1.38: expand / collapse of the nested page tree (`_page_tree.html.twig`, `tree.enabled` off). Each parent row has a
 * `<button data-wiki-tree-fold>` (hidden by the server: without JavaScript every level is open) with `aria-expanded`
 * and `aria-controls` naming its sub-list. Once bound, the buttons show, the rows open as the default says
 * (`data-wiki-tree-default`: `current_path`, `all` or `none`) or as the reader left them (remembered per space,
 * `data-wiki-tree-space`, in localStorage under `nowo-wiki-tree:<space>`), and the ancestors of the current page always
 * start open so it stays visible. Storage errors (private mode, blocked site data, quota) only lose the memory.
 */

/** localStorage key prefix of the remembered rows. */
export const TREE_STATE_PREFIX = 'nowo-wiki-tree:';

/** At most this many rows are remembered per space (the most recent choices). */
export const TREE_STATE_LIMIT = 500;

export type TreeDefault = 'current_path' | 'all' | 'none';

/** The default of a tree root (`current_path` for anything unknown). */
export function treeDefault(value: string | undefined): TreeDefault {
    return value === 'all' || value === 'none' ? value : 'current_path';
}

/** The browser's localStorage, or null when it cannot be reached. */
export function localTreeStorage(win: Window = window): Storage | null {
    try {
        return win.localStorage;
    } catch {
        return null;
    }
}

/** The rows a reader opened (true) or closed (false) in a space; empty when nothing is stored or it cannot be read. */
export function readTreeState(storage: Storage | null, space: string): Record<string, boolean> {
    if (storage === null || space === '') {
        return {};
    }
    try {
        const parsed: unknown = JSON.parse(storage.getItem(TREE_STATE_PREFIX + space) ?? '{}');
        if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return {};
        }
        const state: Record<string, boolean> = {};
        for (const [id, open] of Object.entries(parsed as Record<string, unknown>)) {
            if (typeof open === 'boolean') {
                state[id] = open;
            }
        }

        return state;
    } catch {
        return {};
    }
}

/** Remembers one row's choice in a space (the oldest choices go past {@link TREE_STATE_LIMIT}); errors are ignored. */
export function rememberTreeRow(storage: Storage | null, space: string, id: string, open: boolean): void {
    if (storage === null || space === '' || id === '') {
        return;
    }
    const state = readTreeState(storage, space);
    delete state[id];
    state[id] = open;
    const ids = Object.keys(state);
    for (const old of ids.slice(0, Math.max(0, ids.length - TREE_STATE_LIMIT))) {
        delete state[old];
    }
    try {
        storage.setItem(TREE_STATE_PREFIX + space, JSON.stringify(state));
    } catch {
        // Quota, private mode: the choice is not remembered.
    }
}

/** The sub-list a toggle controls (`aria-controls`), or null. */
function groupOf(button: HTMLElement): HTMLElement | null {
    const id = button.getAttribute('aria-controls') ?? '';

    return id === '' ? null : button.ownerDocument.getElementById(id);
}

/** Opens or closes a row: the button's `aria-expanded` and the sub-list's `hidden`. */
export function setTreeRowOpen(button: HTMLElement, open: boolean): void {
    const group = groupOf(button);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (group !== null) {
        group.hidden = !open;
    }
}

/**
 * Binds every nested page tree under `root` (once per tree). Returns the number of trees bound.
 *
 * @param root - Document subtree to scan.
 * @param storage - Where the choices are remembered (null: nowhere).
 */
export function initPlainTrees(root: ParentNode = document, storage: Storage | null = localTreeStorage()): number {
    let bound = 0;
    root.querySelectorAll<HTMLElement>('[data-wiki-tree-plain]').forEach((tree) => {
        if (tree.hasAttribute('data-wiki-tree-ready')) {
            return;
        }
        const mode = treeDefault(tree.dataset.wikiTreeDefault);
        const space = tree.dataset.wikiTreeSpace ?? '';
        const saved = readTreeState(storage, space);
        tree.querySelectorAll<HTMLButtonElement>('button[data-wiki-tree-fold]').forEach((button) => {
            const group = groupOf(button);
            if (group === null) {
                return;
            }
            // The selector guarantees the attribute (empty for a row without an id).
            const id = String(button.dataset.wikiTreeFold);
            const onPath = group.hasAttribute('data-wiki-tree-current-path');
            // Own entries only (audit QY-I2): an id such as `constructor` is never read from the prototype.
            setTreeRowOpen(button, onPath || (Object.hasOwn(saved, id) ? saved[id] : mode === 'all'));
            button.hidden = false;
        });
        tree.addEventListener('click', (event) => {
            const target = event.target;
            const button = target instanceof Element ? target.closest<HTMLButtonElement>('button[data-wiki-tree-fold]') : null;
            if (button === null || !tree.contains(button)) {
                return;
            }
            const open = button.getAttribute('aria-expanded') !== 'true';
            setTreeRowOpen(button, open);
            rememberTreeRow(storage, space, String(button.dataset.wikiTreeFold), open);
        });
        tree.setAttribute('data-wiki-tree-ready', '');
        bound++;
    });

    return bound;
}
