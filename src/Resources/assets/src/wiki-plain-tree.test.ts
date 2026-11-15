import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    initPlainTrees,
    localTreeStorage,
    readTreeState,
    rememberTreeRow,
    setTreeRowOpen,
    TREE_STATE_LIMIT,
    TREE_STATE_PREFIX,
    treeDefault,
} from './wiki-plain-tree';

/** The markup of `_page_tree.html.twig`: Guides > (Deploy > Rollback), Reference > API; the current page is Deploy. */
function tree(mode = 'current_path', space = 'docs'): string {
    const toggle = (id: string, title: string): string => `<button type="button" class="nowo-ui-tree__toggle nowo-wiki-tree__toggle" aria-expanded="true" aria-controls="nowo-wiki-tree-group-${id}" data-wiki-tree-fold="${id}" hidden><span>Sub-pages of ${title}</span></button>`;

    return `<ul class="nowo-wiki-tree" data-wiki-tree data-wiki-tree-plain data-wiki-tree-default="${mode}" data-wiki-tree-space="${space}">`
        + `<li><div>${toggle('g', 'Guides')}<a href="/g">Guides</a></div>`
        + '<ul id="nowo-wiki-tree-group-g" data-wiki-tree-group data-wiki-tree-current-path>'
        + `<li><div>${toggle('d', 'Deploy')}<a href="/d" aria-current="page">Deploy</a></div>`
        + '<ul id="nowo-wiki-tree-group-d" data-wiki-tree-group><li><div><a href="/r">Rollback</a></div></li></ul></li></ul></li>'
        + `<li><div>${toggle('f', 'Reference')}<a href="/f">Reference</a></div>`
        + '<ul id="nowo-wiki-tree-group-f" data-wiki-tree-group><li><div><a href="/a">API</a></div></li></ul></li>'
        + '</ul>';
}

/** An in-memory Storage. */
function memory(initial: Record<string, string> = {}): Storage {
    const data = new Map(Object.entries(initial));

    return {
        get length() {
            return data.size;
        },
        clear: () => data.clear(),
        getItem: (key: string) => data.get(key) ?? null,
        key: (index: number) => [...data.keys()][index] ?? null,
        removeItem: (key: string) => void data.delete(key),
        setItem: (key: string, value: string) => void data.set(key, value),
    };
}

const button = (id: string): HTMLButtonElement => document.querySelector(`[data-wiki-tree-fold="${id}"]`) as HTMLButtonElement;
const group = (id: string): HTMLElement => document.getElementById(`nowo-wiki-tree-group-${id}`) as HTMLElement;
const open = (id: string): boolean => button(id).getAttribute('aria-expanded') === 'true' && !group(id).hidden;

afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('treeDefault', () => {
    it('reads the three modes and falls back to current_path', () => {
        expect(treeDefault('all')).toBe('all');
        expect(treeDefault('none')).toBe('none');
        expect(treeDefault('current_path')).toBe('current_path');
        expect(treeDefault('weird')).toBe('current_path');
        expect(treeDefault(undefined)).toBe('current_path');
    });
});

describe('initPlainTrees', () => {
    it('opens the current path only, shows the buttons and binds a tree once', () => {
        document.body.innerHTML = tree();
        expect(initPlainTrees(document, memory())).toBe(1);
        expect(open('g')).toBe(true);
        expect(open('d')).toBe(false);
        expect(open('f')).toBe(false);
        expect(button('g').hidden).toBe(false);
        expect(document.querySelector('[data-wiki-tree-plain]')?.hasAttribute('data-wiki-tree-ready')).toBe(true);
        expect(initPlainTrees(document, memory())).toBe(0);
    });

    it('opens every row with all, and keeps the current path open with none', () => {
        document.body.innerHTML = tree('all');
        initPlainTrees(document, memory());
        expect(['g', 'd', 'f'].map(open)).toEqual([true, true, true]);

        document.body.innerHTML = tree('none');
        initPlainTrees(document, memory());
        expect(['g', 'd', 'f'].map(open)).toEqual([true, false, false]);
    });

    it('toggles on click (the keyboard activates the native button the same way) and remembers it per space', () => {
        document.body.innerHTML = tree();
        const storage = memory();
        initPlainTrees(document, storage);

        button('f').click();
        expect(open('f')).toBe(true);
        button('g').click();
        expect(open('g')).toBe(false);
        expect(JSON.parse(storage.getItem(`${TREE_STATE_PREFIX}docs`) ?? '{}')).toEqual({ f: true, g: false });
        // A click elsewhere in the tree does nothing.
        (document.querySelector('a[href="/a"]')?.parentElement as HTMLElement).click();
        const text = document.createTextNode(' ');
        document.querySelector('[data-wiki-tree-plain]')?.appendChild(text);
        text.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(open('f')).toBe(true);

        // The next page of the space: the reader's choices win over the default, the current path still starts open.
        document.body.innerHTML = tree();
        initPlainTrees(document, storage);
        expect(open('f')).toBe(true);
        expect(open('g')).toBe(true);
        // Another space starts from the default.
        document.body.innerHTML = tree('current_path', 'other');
        initPlainTrees(document, storage);
        expect(open('f')).toBe(false);
    });

    it('works without storage and skips a button without its list', () => {
        document.body.innerHTML = tree().replace('aria-controls="nowo-wiki-tree-group-f"', 'aria-controls="missing"');
        initPlainTrees(document, null);
        expect(button('f').hidden).toBe(true);
        button('g').click();
        expect(open('g')).toBe(false);

        document.body.innerHTML = tree().replace(' aria-controls="nowo-wiki-tree-group-f"', '');
        initPlainTrees(document, null);
        expect(button('f').hidden).toBe(true);
    });

    it('uses the defaults of a bare root', () => {
        document.body.innerHTML = tree().replace(' data-wiki-tree-default="current_path" data-wiki-tree-space="docs"', '').replace(' data-wiki-tree-fold="f"', ' data-wiki-tree-fold');
        const storage = memory();
        initPlainTrees(document, storage);
        const bare = document.querySelector('[aria-controls="nowo-wiki-tree-group-f"]') as HTMLButtonElement;
        expect(bare.getAttribute('aria-expanded')).toBe('false');
        bare.click();
        expect(bare.getAttribute('aria-expanded')).toBe('true');
        expect(storage.length).toBe(0);
    });

    it('scans the whole document by default', () => {
        document.body.innerHTML = tree();
        vi.spyOn(window, 'localStorage', 'get').mockReturnValue(memory());
        expect(initPlainTrees()).toBe(1);
    });
});

describe('setTreeRowOpen', () => {
    it('sets aria-expanded even when the list is gone', () => {
        document.body.innerHTML = '<button aria-controls="nope" aria-expanded="true"></button>';
        const lone = document.querySelector('button') as HTMLElement;
        setTreeRowOpen(lone, false);
        expect(lone.getAttribute('aria-expanded')).toBe('false');
    });
});

describe('storage', () => {
    it('reads only boolean rows and survives bad data', () => {
        expect(readTreeState(memory({ [`${TREE_STATE_PREFIX}s`]: '{"a":true,"b":"x","c":false}' }), 's')).toEqual({ a: true, c: false });
        expect(readTreeState(memory({ [`${TREE_STATE_PREFIX}s`]: '[1]' }), 's')).toEqual({});
        expect(readTreeState(memory({ [`${TREE_STATE_PREFIX}s`]: 'null' }), 's')).toEqual({});
        expect(readTreeState(memory({ [`${TREE_STATE_PREFIX}s`]: '{bad' }), 's')).toEqual({});
        expect(readTreeState(memory(), 's')).toEqual({});
        expect(readTreeState(null, 's')).toEqual({});
        expect(readTreeState(memory(), '')).toEqual({});
    });

    it('keeps the most recent choices and ignores write errors', () => {
        const storage = memory();
        for (let i = 0; i < TREE_STATE_LIMIT + 2; i++) {
            rememberTreeRow(storage, 's', `p${i}`, true);
        }
        const state = readTreeState(storage, 's');
        expect(Object.keys(state)).toHaveLength(TREE_STATE_LIMIT);
        expect(state.p0).toBeUndefined();
        expect(state[`p${TREE_STATE_LIMIT + 1}`]).toBe(true);

        const full = memory();
        full.setItem = () => {
            throw new Error('QuotaExceededError');
        };
        expect(() => rememberTreeRow(full, 's', 'a', true)).not.toThrow();
        rememberTreeRow(null, 's', 'a', true);
        rememberTreeRow(storage, '', 'a', true);
        rememberTreeRow(storage, 's', '', true);
    });

    it('reaches localStorage, or null when the browser refuses it', () => {
        expect(localTreeStorage()).toBe(window.localStorage);
        const blocked = {} as Window;
        Object.defineProperty(blocked, 'localStorage', {
            get() {
                throw new Error('SecurityError');
            },
        });
        expect(localTreeStorage(blocked)).toBeNull();
    });
});
