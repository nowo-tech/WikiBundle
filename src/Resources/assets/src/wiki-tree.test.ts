import { afterAll, afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { RECHECK_DELAY_MS } from './wiki-code-blocks';
import { announceInTree, applyTreeMove, bindWikiTree, planTreeMove, setTreePoster, type TreePlan, type TreePoster } from './wiki';

// wiki.ts watches the document on import: let its queued code-block pass run before the test environment goes away.
afterAll(() => new Promise<void>((resolve) => {
    setTimeout(resolve, RECHECK_DELAY_MS + 50);
}));

type Spec = [id: string, children?: Spec[]];

/** Markup of the UiKit `tree` partial (2.24) for the given nodes. */
function items(nodes: Spec[], level = 1, treeId = 't'): string {
    return nodes
        .map(([id, children], i) => {
            const parent = (children ?? []).length > 0;
            const group = `${treeId}-${id}-group`;
            return `<li class="nowo-ui-tree__item" role="none" data-nowo-ui-tree-node="${id}"><div class="nowo-ui-tree__row">`
                + (parent ? '<span class="nowo-ui-tree__toggle" aria-hidden="true" data-nowo-ui-tree-toggle></span>' : '<span class="nowo-ui-tree__spacer" aria-hidden="true"></span>')
                + `<a href="/${id}" role="treeitem" data-nowo-ui-tree-item tabindex="${level === 1 && i === 0 ? 0 : -1}" aria-level="${level}" aria-setsize="${nodes.length}" aria-posinset="${i + 1}"`
                + (parent ? ` aria-expanded="false" aria-owns="${group}"` : '')
                + `>${id}</a></div>`
                + (parent ? `<ul class="theme-group" role="group" id="${group}" hidden>${items(children!, level + 1, treeId)}</ul>` : '')
                + '</li>';
        })
        .join('');
}

function render(nodes: Spec[], attrs = 'data-wiki-tree-state="/state" data-wiki-tree-move="/move" data-wiki-tree-token="tok" data-wiki-tree-error="Failed"'): HTMLElement {
    document.body.innerHTML = `<nav><ul id="t" role="tree" data-wiki-tree ${attrs}>${items(nodes)}</ul></nav>`;
    return document.getElementById('t')!;
}

const li = (id: string): HTMLLIElement => document.querySelector(`[data-nowo-ui-tree-node="${id}"]`) as HTMLLIElement;
const link = (id: string): HTMLElement => li(id).querySelector('[data-nowo-ui-tree-item]') as HTMLElement;
const order = (list: Element): string[] => [...list.children].map((el) => (el as HTMLElement).dataset.nowoUiTreeNode!);
const aria = (id: string): string => ['aria-level', 'aria-posinset', 'aria-setsize'].map((a) => link(id).getAttribute(a)).join('/');
const brief = (plan: TreePlan | null) => plan && { id: plan.item.dataset.nowoUiTreeNode, parent: plan.parent?.dataset.nowoUiTreeNode ?? null, index: plan.index };

const SAMPLE: Spec[] = [['a', [['a1'], ['a2']]], ['b'], ['c']];

afterEach(() => {
    document.body.innerHTML = '';
});

describe('planTreeMove', () => {
    it('plans Alt+arrow moves (up, down, in, out) and refuses the impossible ones', () => {
        const tree = render(SAMPLE);
        expect(planTreeMove(tree, { id: 'zz', direction: 'up' })).toBeNull();
        expect(planTreeMove(tree, { id: 'a', direction: 'up' })).toBeNull();
        expect(brief(planTreeMove(tree, { id: 'b', direction: 'up' }))).toEqual({ id: 'b', parent: null, index: 0 });
        expect(brief(planTreeMove(tree, { id: 'a', direction: 'down' }))).toEqual({ id: 'a', parent: null, index: 1 });
        expect(planTreeMove(tree, { id: 'c', direction: 'down' })).toBeNull();
        expect(planTreeMove(tree, { id: 'a', direction: 'in' })).toBeNull();
        expect(brief(planTreeMove(tree, { id: 'b', direction: 'in' }))).toEqual({ id: 'b', parent: 'a', index: 2 });
        expect(brief(planTreeMove(tree, { id: 'c', direction: 'in' }))).toEqual({ id: 'c', parent: 'b', index: 0 });
        expect(planTreeMove(tree, { id: 'b', direction: 'out' })).toBeNull();
        expect(brief(planTreeMove(tree, { id: 'a2', direction: 'out' }))).toEqual({ id: 'a2', parent: null, index: 1 });
    });

    it('plans drops before, after and inside a target, never into the own sub-tree', () => {
        const tree = render(SAMPLE);
        expect(planTreeMove(tree, { id: 'c', targetId: 'zz', position: 'before' })).toBeNull();
        expect(planTreeMove(tree, { id: 'a', targetId: 'a1', position: 'inside' })).toBeNull();
        expect(planTreeMove(tree, { id: 'a', targetId: 'a', position: 'after' })).toBeNull();
        expect(brief(planTreeMove(tree, { id: 'c', targetId: 'b', position: 'inside' }))).toEqual({ id: 'c', parent: 'b', index: 0 });
        expect(brief(planTreeMove(tree, { id: 'a1', targetId: 'a', position: 'inside' }))).toEqual({ id: 'a1', parent: 'a', index: 1 });
        expect(brief(planTreeMove(tree, { id: 'c', targetId: 'a', position: 'before' }))).toEqual({ id: 'c', parent: null, index: 0 });
        expect(brief(planTreeMove(tree, { id: 'a', targetId: 'b', position: 'after' }))).toEqual({ id: 'a', parent: null, index: 1 });
        expect(brief(planTreeMove(tree, { id: 'c', targetId: 'a1', position: 'after' }))).toEqual({ id: 'c', parent: 'a', index: 1 });
    });
});

describe('applyTreeMove', () => {
    it('moves items, adds and removes disclosures, renumbers and focuses the moved item', () => {
        const tree = render(SAMPLE);
        // Into a leaf: it becomes an expanded parent with a group of the theme's class.
        applyTreeMove(tree, planTreeMove(tree, { id: 'c', direction: 'in' })!);
        const group = li('b').querySelector(':scope > ul[role="group"]') as HTMLUListElement;
        expect(group.className).toBe('theme-group');
        expect(group.id).toBe('t-b-group');
        expect(group.hidden).toBe(false);
        expect(link('b').getAttribute('aria-expanded')).toBe('true');
        expect(link('b').getAttribute('aria-owns')).toBe('t-b-group');
        expect(li('b').querySelector('.nowo-ui-tree__toggle')?.hasAttribute('data-nowo-ui-tree-toggle')).toBe(true);
        expect(aria('c')).toBe('2/1/1');
        expect(aria('b')).toBe('1/2/2');
        expect(document.activeElement).toBe(link('c'));
        expect(link('c').tabIndex).toBe(0);
        expect(link('a').tabIndex).toBe(-1);
        // Out of a parent that keeps children; then empty it: the parent loses its disclosure.
        applyTreeMove(tree, planTreeMove(tree, { id: 'a1', direction: 'out' })!);
        expect(order(tree)).toEqual(['a', 'a1', 'b']);
        expect(aria('a2')).toBe('2/1/1');
        applyTreeMove(tree, planTreeMove(tree, { id: 'a2', targetId: 'b', position: 'after' })!);
        expect(order(tree)).toEqual(['a', 'a1', 'b', 'a2']);
        expect(li('a').querySelector('ul')).toBeNull();
        expect(link('a').hasAttribute('aria-expanded')).toBe(false);
        expect(link('a').hasAttribute('aria-owns')).toBe(false);
        expect(li('a').querySelector('.nowo-ui-tree__spacer')?.hasAttribute('data-nowo-ui-tree-toggle')).toBe(false);
        // Into an existing, collapsed parent: it is expanded.
        applyTreeMove(tree, planTreeMove(tree, { id: 'a', targetId: 'b', position: 'inside' })!);
        expect(order(li('b').querySelector('ul')!)).toEqual(['c', 'a']);
        expect(aria('a')).toBe('2/2/2');
    });

    it('uses the UiKit group class when the tree has no group yet; a reorder keeps the parent', () => {
        const tree = render([['x'], ['y'], ['z']]);
        applyTreeMove(tree, planTreeMove(tree, { id: 'y', direction: 'in' })!);
        expect(li('x').querySelector('ul')?.className).toBe('nowo-ui-tree__group');
        applyTreeMove(tree, planTreeMove(tree, { id: 'z', direction: 'up' })!);
        expect(order(tree)).toEqual(['z', 'x']);
        expect(link('x').getAttribute('aria-expanded')).toBe('true');
    });
});

describe('announceInTree', () => {
    it('creates one polite region after the tree and reuses it', () => {
        const tree = render(SAMPLE);
        announceInTree(tree, 'One');
        announceInTree(tree, 'Two');
        const regions = document.querySelectorAll('[data-nowo-ui-tree-live]');
        expect(regions).toHaveLength(1);
        expect(regions[0]!.getAttribute('role')).toBe('status');
        expect(regions[0]!.className).toBe('visually-hidden');
        expect(regions[0]!.textContent).toBe('Two');
        expect(tree.nextElementSibling).toBe(regions[0]);
    });
});

describe('bindWikiTree', () => {
    let post: ReturnType<typeof vi.fn<TreePoster>>;
    const emit = (target: Element, name: string, detail: unknown): CustomEvent => {
        const event = new CustomEvent(name, { bubbles: true, cancelable: true, detail });
        target.dispatchEvent(event);
        return event;
    };
    const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
    const live = () => document.querySelector('[data-nowo-ui-tree-live]')?.textContent;

    beforeEach(() => {
        post = vi.fn<TreePoster>();
        setTreePoster(post);
        bindWikiTree(document);
        bindWikiTree(document); // idempotent
    });

    it('records fold changes; trees without endpoints are left alone', async () => {
        post.mockResolvedValue({ ok: true, data: {} });
        let tree = render(SAMPLE);
        emit(tree, 'nowo-ui:tree:toggle', { id: 'a', expanded: true });
        expect(post).toHaveBeenCalledTimes(1);
        expect(post).toHaveBeenCalledWith('/state', { page: 'a', expanded: true, _token: 'tok' });
        post.mockRejectedValue(new Error('offline'));
        emit(tree, 'nowo-ui:tree:toggle', { id: 'a', expanded: false });
        await flush();
        tree = render(SAMPLE, '');
        emit(tree, 'nowo-ui:tree:toggle', { id: 'a', expanded: true });
        expect(emit(tree, 'nowo-ui:tree:move', { id: 'b', direction: 'up' }).defaultPrevented).toBe(false);
        emit(document.body, 'nowo-ui:tree:toggle', { id: 'a', expanded: true });
        emit(document.body, 'nowo-ui:tree:move', { id: 'a', direction: 'up' });
        tree = render(SAMPLE, 'data-wiki-tree-state="/state" data-wiki-tree-move="/move"');
        emit(tree, 'nowo-ui:tree:toggle', { id: 'a', expanded: true });
        expect(post).toHaveBeenLastCalledWith('/state', { page: 'a', expanded: true, _token: '' });
        expect(post).toHaveBeenCalledTimes(3);
        emit(tree, 'nowo-ui:tree:move', { id: 'b', direction: 'up' });
        expect(post).toHaveBeenLastCalledWith('/move', { page: 'b', parent: null, index: 0, _token: '' });
        await flush();
    });

    it('sends a move, applies it once accepted and says the server sentence; one move at a time', async () => {
        const tree = render(SAMPLE);
        let resolve: (value: { ok: boolean; data: { message?: string } }) => void = () => undefined;
        post.mockReturnValue(new Promise((r) => {
            resolve = r;
        }));
        const event = emit(tree, 'nowo-ui:tree:move', { id: 'c', direction: 'up' });
        expect(event.defaultPrevented).toBe(true);
        expect(post).toHaveBeenCalledWith('/move', { page: 'c', parent: null, index: 1, _token: 'tok' });
        emit(tree, 'nowo-ui:tree:drop', { id: 'b', targetId: 'a', position: 'inside' }); // busy: ignored
        expect(post).toHaveBeenCalledTimes(1);
        resolve({ ok: true, data: { message: 'c moved, position 2 of 3' } });
        await flush();
        expect(order(tree)).toEqual(['a', 'c', 'b']);
        expect(live()).toBe('c moved, position 2 of 3');
        // Not busy any more: a drop inside a parent.
        post.mockResolvedValue({ ok: true, data: { message: 'b moved under a' } });
        emit(tree, 'nowo-ui:tree:drop', { id: 'b', targetId: 'a', position: 'inside' });
        await flush();
        expect(post).toHaveBeenLastCalledWith('/move', { page: 'b', parent: 'a', index: 2, _token: 'tok' });
        expect(order(li('a').querySelector('ul')!)).toEqual(['a1', 'a2', 'b']);
        // Impossible moves send nothing.
        emit(tree, 'nowo-ui:tree:move', { id: 'a', direction: 'up' });
        expect(post).toHaveBeenCalledTimes(2);
    });

    it('a refused or failed move changes nothing and says why', async () => {
        const tree = render(SAMPLE);
        post.mockResolvedValueOnce({ ok: false, data: { message: 'Too deep' } });
        emit(tree, 'nowo-ui:tree:move', { id: 'b', direction: 'in' });
        await flush();
        expect(order(tree)).toEqual(['a', 'b', 'c']);
        expect(live()).toBe('Too deep');
        post.mockResolvedValueOnce({ ok: false, data: {} });
        emit(tree, 'nowo-ui:tree:move', { id: 'b', direction: 'in' });
        await flush();
        expect(live()).toBe('Failed');
        post.mockRejectedValueOnce(new Error('offline'));
        emit(tree, 'nowo-ui:tree:move', { id: 'b', direction: 'in' });
        await flush();
        expect(live()).toBe('Failed');
        tree.removeAttribute('data-wiki-tree-error');
        post.mockRejectedValueOnce(new Error('offline'));
        emit(tree, 'nowo-ui:tree:move', { id: 'b', direction: 'in' });
        await flush();
        expect(live()).toBe('');
        post.mockResolvedValueOnce({ ok: false, data: {} });
        emit(tree, 'nowo-ui:tree:move', { id: 'b', direction: 'in' });
        await flush();
        expect(live()).toBe('');
    });
});

describe('default transport', () => {
    const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
    it('posts JSON with the same-origin cookies and reads the answer (204, JSON, invalid JSON)', async () => {
        // The first module instance stays bound: its transport never answers.
        setTreePoster(() => new Promise(() => undefined));
        vi.resetModules();
        const fetchMock = vi.fn()
            .mockResolvedValueOnce({ ok: true, status: 204, json: () => Promise.reject(new Error('empty')) })
            .mockResolvedValueOnce({ ok: false, status: 422, json: () => Promise.resolve({ message: 'Too deep' }) })
            .mockResolvedValueOnce({ ok: false, status: 500, json: () => Promise.reject(new Error('html')) });
        vi.stubGlobal('fetch', fetchMock);
        const fresh = await import('./wiki');
        const tree = render(SAMPLE);
        const toggle = new CustomEvent('nowo-ui:tree:toggle', { bubbles: true, detail: { id: 'a', expanded: true } });
        tree.dispatchEvent(toggle);
        expect(fetchMock).toHaveBeenCalledWith('/state', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ page: 'a', expanded: true, _token: 'tok' }),
        });
        tree.dispatchEvent(new CustomEvent('nowo-ui:tree:move', { bubbles: true, cancelable: true, detail: { id: 'b', direction: 'in' } }));
        await flush();
        await flush();
        expect(document.querySelector('[data-nowo-ui-tree-live]')?.textContent).toBe('Too deep');
        tree.dispatchEvent(new CustomEvent('nowo-ui:tree:move', { bubbles: true, cancelable: true, detail: { id: 'b', direction: 'in' } }));
        await flush();
        await flush();
        expect(document.querySelector('[data-nowo-ui-tree-live]')?.textContent).toBe('Failed');
        expect(typeof fresh.bindWikiTree).toBe('function');
        vi.unstubAllGlobals();
    });
});
