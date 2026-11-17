import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { allowedItems, bindTreeMenu, LONG_PRESS_MS } from './wiki-tree-menu';

/**
 * 1.41: the tree menu's other entries — Open in new tab, Edit, Duplicate, Move up / down / to…, Archive (and its undo),
 * Delete (trash, confirm with the count) — the separators, and the long press.
 */

/** A row: id, marks ('e' rename, 'h' edit address, 's' sibling, 'v' move, 'a' archive, 'd' delete), children. */
type Spec = [id: string, marks: string, children?: Spec[], current?: boolean];

function rows(nodes: Spec[]): string {
    return nodes
        .map(([id, marks, children, current]) => {
            const attrs = [
                marks.includes('e') ? ' data-wiki-menu-edit="1"' : '',
                marks.includes('h') ? ` data-wiki-edit-href="/p/${id}/edit"` : '',
                marks.includes('s') ? ' data-wiki-new-sibling="1"' : '',
                marks.includes('v') ? ' data-wiki-menu-move="1"' : '',
                marks.includes('a') ? ' data-wiki-menu-archive="1"' : '',
                marks.includes('d') ? ' data-wiki-menu-delete="1"' : '',
            ].join('');
            const group = children ? `<ul role="group">${rows(children)}</ul>` : '';

            return `<li role="none" data-nowo-ui-tree-node="${id}"${attrs}><div class="nowo-ui-tree__row">`
                + `<a href="http://localhost/p/${id}" role="treeitem" data-nowo-ui-tree-item tabindex="${id === 'a' ? 0 : -1}"${current ? ' aria-current="page"' : ''}>`
                + `<span class="nowo-ui-tree__label">${id.toUpperCase()}</span></a></div>${group}</li>`;
        })
        .join('');
}

const ENTRIES = [['open', 'edit', 'rename'], ['duplicate'], ['up', 'down', 'moveto'], ['copy'], ['archive', 'delete']];

const MENU = '<div role="menu" hidden data-wiki-tree-menu data-failed="Failed" data-denied="Denied" data-archive-confirm="Archive “%title%”?">'
    + ENTRIES.map((group, i) => (i > 0 ? '<div role="separator" hidden data-wiki-tree-menu-separator></div>' : '')
        + group.map((e) => `<button type="button" role="menuitem" tabindex="-1" hidden data-wiki-tree-menu-item="${e}">${e}</button>`).join('')).join('')
    + '</div><p role="status" data-wiki-tree-menu-status></p>';

const CONFIRM = '<dialog data-wiki-tree-confirm-dialog><p data-wiki-tree-confirm-message></p><p hidden data-wiki-tree-confirm-error></p>'
    + '<button type="button" data-wiki-tree-confirm-ok data-delete="Delete" data-archive="Archive"></button></dialog>';

const UNDO = '<div hidden data-wiki-tree-undo><span data-wiki-tree-undo-text></span><button type="button" data-wiki-tree-undo-button>Unarchive</button></div>';

const MOVE = '<dialog data-wiki-tree-move-dialog><p data-wiki-tree-move-context data-template="Page “%title%”" data-root="Top level"></p>'
    + '<form><select name="form[parent]"></select></form></dialog>';

const TREE_ATTRS = ' data-wiki-tree-token="tok" data-wiki-tree-move="/t/move" data-wiki-tree-parents="/t/parents" data-wiki-tree-duplicate="/t/duplicate"'
    + ' data-wiki-tree-archive="/t/archive" data-wiki-tree-unarchive="/t/unarchive" data-wiki-tree-delete="/t/delete" data-wiki-tree-space="/space"';

function setup(nodes: Spec[], extra = MENU + CONFIRM + UNDO + MOVE): void {
    document.body.innerHTML = `<ul role="tree" data-wiki-tree${TREE_ATTRS}>${rows(nodes)}</ul>${extra}<p id="out">x</p>`;
    for (const d of document.querySelectorAll('dialog')) {
        (d as HTMLDialogElement).showModal = function showModal(this: HTMLDialogElement) { this.setAttribute('open', ''); };
        (d as HTMLDialogElement).close = function close(this: HTMLDialogElement) { this.removeAttribute('open'); this.dispatchEvent(new Event('close')); };
    }
}

const li = (id: string): HTMLLIElement => document.querySelector(`[data-nowo-ui-tree-node="${id}"]`) as HTMLLIElement;
const link = (id: string): HTMLAnchorElement => li(id).querySelector('[data-nowo-ui-tree-item]') as HTMLAnchorElement;
const menuEl = (): HTMLElement => document.querySelector('[data-wiki-tree-menu]') as HTMLElement;
const entry = (name: string): HTMLElement => menuEl().querySelector(`[data-wiki-tree-menu-item="${name}"]`) as HTMLElement;
const status = (): string => `${document.querySelector('[data-wiki-tree-menu-status]')?.textContent}`;
const dialog = (attr: string): HTMLDialogElement => document.querySelector(`[${attr}]`) as HTMLDialogElement;
const ok = (): HTMLButtonElement => document.querySelector('[data-wiki-tree-confirm-ok]') as HTMLButtonElement;
const flush = async (): Promise<void> => {
    for (let i = 0; i < 4; ++i) {
        await new Promise((r) => setTimeout(r, 0));
    }
};
const rightClick = (target: Element): MouseEvent => {
    const event = new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 10, clientY: 20 });
    target.dispatchEvent(event);

    return event;
};
/** Clicks the element; whether something before the document's bubbling phase prevented it (no navigation either way). */
const clicked = (target: Element): boolean => {
    let prevented = false;
    document.addEventListener('click', (event) => {
        prevented = event.defaultPrevented;
        event.preventDefault();
    }, { once: true });
    target.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    return prevented;
};
const choose = (id: string, name: string): void => {
    rightClick(link(id));
    entry(name).click();
};

type Answer = [status: number, data: unknown] | 'reject';

/** fetch answering the given answers in turn (the last one again after that). */
function fetcher(...answers: Answer[]) {
    let call = 0;

    return vi.fn<typeof fetch>(async () => {
        const answer = answers[Math.min(call++, answers.length - 1)];
        if (answer === 'reject') {
            throw new Error('network');
        }

        return { status: answer[0], json: async () => answer[1] } as unknown as Response;
    });
}

const sent = (f: ReturnType<typeof fetcher>, call = 0): [string, Record<string, unknown>] => [String(f.mock.calls[call][0]), JSON.parse(String(f.mock.calls[call][1]?.body)) as Record<string, unknown>];

let location: { assign: ReturnType<typeof vi.fn>; reload: ReturnType<typeof vi.fn> };

beforeEach(() => {
    location = { assign: vi.fn(), reload: vi.fn() };
    vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, ...location } as unknown as Location);
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
});

describe('entries and separators', () => {
    it('offers each entry where the row is marked; Move up / down only where a sibling is', () => {
        setup([['a', 'ehsvad'], ['b', 'v', [['b1', 'v']]], ['c', 'v']]);
        expect(allowedItems(li('a'))).toEqual(['open', 'edit', 'rename', 'above', 'below', 'duplicate', 'down', 'moveto', 'copy', 'archive', 'delete']);
        expect(allowedItems(li('b'))).toEqual(['open', 'up', 'down', 'moveto', 'copy']);
        expect(allowedItems(li('c'))).toEqual(['open', 'up', 'moveto', 'copy']);
        expect(allowedItems(li('b1'))).toEqual(['open', 'moveto', 'copy']);
        // Only entries the menu renders.
        expect(allowedItems(li('a'), ['archive'])).toEqual(['archive']);
        setup([['r', '']]);
        expect(allowedItems(li('r'))).toEqual([]);
    });

    it('shows a separator only between two visible groups', () => {
        setup([['a', 'e'], ['b', 'a']]);
        bindTreeMenu(document, fetcher([200, {}]));
        rightClick(link('a'));
        const shown = [...menuEl().children].filter((el) => !(el as HTMLElement).hidden).map((el) => (el as HTMLElement).dataset.wikiTreeMenuItem ?? '|');
        expect(shown).toEqual(['open', 'rename', '|', 'copy']);
        rightClick(link('b'));
        const second = [...menuEl().children].filter((el) => !(el as HTMLElement).hidden).map((el) => (el as HTMLElement).dataset.wikiTreeMenuItem ?? '|');
        expect(second).toEqual(['open', '|', 'copy', '|', 'archive']);

        // A first group with nothing to show leaves no separator in front.
        setup([['b', 'a']], '<div role="menu" hidden data-wiki-tree-menu><button type="button" role="menuitem" hidden data-wiki-tree-menu-item="rename">r</button>'
            + '<div role="separator" hidden data-wiki-tree-menu-separator></div><button type="button" role="menuitem" hidden data-wiki-tree-menu-item="archive">a</button></div>'
            + '<p data-wiki-tree-menu-status></p>');
        bindTreeMenu(document, fetcher([200, {}]));
        rightClick(link('b'));
        expect([...menuEl().children].filter((el) => !(el as HTMLElement).hidden).map((el) => (el as HTMLElement).dataset.wikiTreeMenuItem)).toEqual(['archive']);
    });
});

describe('placement', () => {
    it('opens above the anchor when there is no room below it', () => {
        setup([['a', 'e']]);
        bindTreeMenu(document, fetcher([200, {}]));
        Object.defineProperty(menuEl(), 'offsetHeight', { value: 300, configurable: true });
        link('a').dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 10, clientY: 600 }));
        expect(menuEl().style.top).toBe('300px');
        menuEl().hidden = true;
        link('a').dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 10, clientY: 100 }));
        expect(menuEl().style.top).toBe('100px');
    });
});

describe('Open in new tab, Edit, Move up / down', () => {
    it('opens the page in a new tab and the edit form in this one', () => {
        setup([['a', 'h']]);
        const open = vi.spyOn(window, 'open').mockReturnValue(null);
        bindTreeMenu(document, fetcher([200, {}]));
        choose('a', 'open');
        expect(open).toHaveBeenCalledWith('http://localhost/p/a', '_blank', 'noopener');
        expect(document.activeElement).toBe(link('a'));
        choose('a', 'edit');
        expect(location.assign).toHaveBeenCalledWith('/p/a/edit');
    });

    it('asks the tree for its Alt+arrow move', () => {
        setup([['a', 'v'], ['b', 'v']]);
        const moves: unknown[] = [];
        document.addEventListener('nowo-ui:tree:move', (event) => moves.push((event as CustomEvent).detail));
        bindTreeMenu(document, fetcher([200, {}]));
        choose('a', 'down');
        choose('b', 'up');
        expect(moves).toEqual([{ id: 'a', direction: 'down' }, { id: 'b', direction: 'up' }]);
    });
});

describe('Duplicate', () => {
    it('opens the copy, or says why not', async () => {
        setup([['a', 's']]);
        const f = fetcher([200, { url: '/p/a-copy' }], [403, {}], [422, { message: 'Plan full' }], 'reject');
        bindTreeMenu(document, f);
        choose('a', 'duplicate');
        await flush();
        expect(sent(f)).toEqual(['/t/duplicate', { page: 'a', _token: 'tok' }]);
        expect(location.assign).toHaveBeenCalledWith('/p/a-copy');
        choose('a', 'duplicate');
        await flush();
        expect(status()).toBe('Denied');
        choose('a', 'duplicate');
        await flush();
        expect(status()).toBe('Plan full');
        choose('a', 'duplicate');
        await flush();
        expect(status()).toBe('Failed');
    });
});

describe('Archive and its undo', () => {
    it('confirms, archives, takes the row out and offers Unarchive', async () => {
        setup([['a', 'a'], ['b', 'a']]);
        const f = fetcher([200, { message: '“A” archived.' }], [200, { message: 'back' }]);
        bindTreeMenu(document, f);
        choose('a', 'archive');
        const confirm = dialog('data-wiki-tree-confirm-dialog');
        expect(confirm.hasAttribute('open')).toBe(true);
        expect(confirm.querySelector('[data-wiki-tree-confirm-message]')!.textContent).toBe('Archive “A”?');
        expect(ok().textContent).toBe('Archive');
        ok().click();
        // A second click while the first one runs does nothing.
        ok().click();
        await flush();
        expect(f).toHaveBeenCalledTimes(1);
        expect(sent(f)).toEqual(['/t/archive', { page: 'a', _token: 'tok' }]);
        expect(confirm.hasAttribute('open')).toBe(false);
        expect(document.querySelector('[data-nowo-ui-tree-node="a"]')).toBeNull();
        expect(document.activeElement).toBe(link('b'));
        expect(link('b').tabIndex).toBe(0);
        expect(status()).toBe('“A” archived.');
        const undo = document.querySelector('[data-wiki-tree-undo]') as HTMLElement;
        expect(undo.hidden).toBe(false);
        expect(undo.textContent).toContain('“A” archived.');

        (undo.querySelector('button') as HTMLElement).click();
        await flush();
        expect(sent(f, 1)).toEqual(['/t/unarchive', { page: 'a', _token: 'tok' }]);
        expect(undo.hidden).toBe(true);
        expect(location.reload).toHaveBeenCalled();
        // Nothing left to undo.
        (undo.querySelector('button') as HTMLElement).click();
        expect(f).toHaveBeenCalledTimes(2);
    });

    it('a refused archive stays in the dialog; a refused undo is said', async () => {
        setup([['a', 'a']]);
        const f = fetcher([422, { message: 'No' }], 'reject', [200, { message: 'done' }], [404, {}], 'reject');
        bindTreeMenu(document, f);
        choose('a', 'archive');
        ok().click();
        await flush();
        const error = document.querySelector('[data-wiki-tree-confirm-error]') as HTMLElement;
        expect(error.hidden).toBe(false);
        expect(error.textContent).toBe('No');
        ok().click();
        await flush();
        expect(error.textContent).toBe('Failed');
        ok().click();
        await flush();
        // The last row went: no tab stop is left to focus.
        expect(document.querySelectorAll('[data-nowo-ui-tree-item]')).toHaveLength(0);
        const undo = document.querySelector('[data-wiki-tree-undo] button') as HTMLElement;
        undo.click();
        await flush();
        expect(status()).toBe('Failed');
        undo.click();
        await flush();
        expect(status()).toBe('Failed');
        expect(location.reload).not.toHaveBeenCalled();
    });

    it('works without the undo line; Cancel gives the focus back; OK without a pending action does nothing', async () => {
        setup([['a', 'a'], ['b', 'a']], MENU + CONFIRM);
        const f = fetcher([200, { message: 'gone' }]);
        bindTreeMenu(document, f);
        ok().click();
        expect(f).not.toHaveBeenCalled();
        choose('b', 'archive');
        dialog('data-wiki-tree-confirm-dialog').close();
        expect(document.activeElement).toBe(link('b'));
        choose('b', 'archive');
        ok().click();
        await flush();
        expect(status()).toBe('gone');
        expect(document.querySelector('[data-nowo-ui-tree-node="b"]')).toBeNull();
    });
});

describe('Delete', () => {
    it('confirms with the server\'s count, then sends the page and its sub-pages to the trash', async () => {
        setup([['a', 'd', [['a1', 'd']]], ['b', 'd']]);
        const f = fetcher([200, { count: 2, message: '“A” and 1 sub-page(s) will go to the trash.' }], [200, { message: 'trashed' }]);
        bindTreeMenu(document, f);
        choose('a', 'delete');
        await flush();
        expect(sent(f)).toEqual(['/t/delete', { page: 'a', preview: true, _token: 'tok' }]);
        expect(dialog('data-wiki-tree-confirm-dialog').querySelector('[data-wiki-tree-confirm-message]')!.textContent).toBe('“A” and 1 sub-page(s) will go to the trash.');
        expect(ok().textContent).toBe('Delete');
        ok().click();
        await flush();
        expect(sent(f, 1)).toEqual(['/t/delete', { page: 'a', _token: 'tok' }]);
        expect(document.querySelector('[data-nowo-ui-tree-node="a1"]')).toBeNull();
        expect(status()).toBe('trashed');
    });

    it('goes back to the space when the page being read went; refusals are said', async () => {
        setup([['a', 'd', [['a1', 'd', undefined, true]]]]);
        const f = fetcher([200, { message: 'sure?' }], [200, { message: 'trashed' }], [403, {}], [200, { message: 'sure?' }], [410, { message: 'Gone' }]);
        bindTreeMenu(document, f);
        choose('a', 'delete');
        await flush();
        ok().click();
        await flush();
        expect(location.assign).toHaveBeenCalledWith('/space');
        expect(li('a')).not.toBeNull();
        choose('a', 'delete');
        await flush();
        expect(status()).toBe('Denied');
        choose('a', 'delete');
        await flush();
        ok().click();
        await flush();
        expect(document.querySelector('[data-wiki-tree-confirm-error]')!.textContent).toBe('Gone');
    });

    it('a failed request is said in the dialog, or in the status line for the count', async () => {
        setup([['a', 'd']]);
        const f = fetcher('reject', [200, { message: 'sure?' }], 'reject');
        bindTreeMenu(document, f);
        choose('a', 'delete');
        await flush();
        expect(status()).toBe('Failed');
        choose('a', 'delete');
        await flush();
        ok().click();
        await flush();
        expect(document.querySelector('[data-wiki-tree-confirm-error]')!.textContent).toBe('Failed');
    });
});

describe('Move to…', () => {
    const tree = (): Spec[] => [['a', 'v', [['a1', 'v'], ['a2', 'v']]], ['b', 'v']];

    it('lists the parents the server offers and moves with the tree\'s drop', async () => {
        setup(tree());
        const drops: unknown[] = [];
        document.addEventListener('nowo-ui:tree:drop', (event) => drops.push((event as CustomEvent).detail));
        const f = fetcher([200, { parents: [{ id: 'a', label: 'A' }, { id: 'b', label: 'B' }], root: true, parent: 'a' }]);
        bindTreeMenu(document, f);
        choose('a1', 'moveto');
        await flush();
        expect(sent(f)).toEqual(['/t/parents', { page: 'a1', _token: 'tok' }]);
        const move = dialog('data-wiki-tree-move-dialog');
        const select = move.querySelector('select') as HTMLSelectElement;
        expect(move.hasAttribute('open')).toBe(true);
        expect(document.activeElement).toBe(select);
        expect(move.querySelector('[data-wiki-tree-move-context]')!.textContent).toBe('Page “A1”');
        expect([...select.options].map((o) => [o.value, o.text])).toEqual([['', 'Top level'], ['a', 'A'], ['b', 'B']]);
        expect(select.value).toBe('a');

        // The same parent: nothing to do.
        move.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        expect(move.hasAttribute('open')).toBe(false);
        expect(document.activeElement).toBe(link('a1'));
        expect(drops).toEqual([]);

        choose('a1', 'moveto');
        await flush();
        select.value = 'b';
        move.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        choose('a1', 'moveto');
        await flush();
        select.value = '';
        move.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        expect(drops).toEqual([{ id: 'a1', targetId: 'b', position: 'inside' }, { id: 'a1', targetId: 'b', position: 'after' }]);
    });

    it('a parent outside the drawn tree, or a last top-level row, goes through the move endpoint and the page again', async () => {
        setup([['a', 'v'], ['b', 'v']]);
        const f = fetcher([200, { parents: [{ id: 'far', label: 'Far' }], root: true, parent: 'gone' }], [200, {}], [200, { parents: [], root: true, parent: 'gone' }], [422, { message: 'Cycle' }], [403, {}], [200, {}]);
        bindTreeMenu(document, f);
        choose('b', 'moveto');
        await flush();
        const move = dialog('data-wiki-tree-move-dialog');
        const select = move.querySelector('select') as HTMLSelectElement;
        select.value = 'far';
        move.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();
        expect(sent(f, 1)).toEqual(['/t/move', { page: 'b', parent: 'far', index: 1_000_000, _token: 'tok' }]);
        expect(location.reload).toHaveBeenCalledTimes(1);

        // B is the last top-level row already (its archived parent shows it there): the endpoint places it.
        choose('b', 'moveto');
        await flush();
        expect(select.value).toBe('');
        move.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();
        expect(sent(f, 3)).toEqual(['/t/move', { page: 'b', parent: null, index: 1_000_000, _token: 'tok' }]);
        expect(status()).toBe('Cycle');

        // The parents refused (403), then an answer without parents nor root.
        choose('b', 'moveto');
        await flush();
        expect(status()).toBe('Denied');
        choose('b', 'moveto');
        await flush();
        expect(select.options).toHaveLength(0);
    });
});

describe('long press', () => {
    const press = (target: Element, type: string, x = 5, y = 5, pointerType = 'touch'): MouseEvent => {
        const event = new MouseEvent(type, { bubbles: true, cancelable: true, clientX: x, clientY: y });
        Object.defineProperty(event, 'pointerType', { value: pointerType });
        target.dispatchEvent(event);

        return event;
    };

    it('opens the menu after a still press, and the click that ends it does not follow the link', () => {
        vi.useFakeTimers();
        setup([['a', 'e'], ['r', '']]);
        bindTreeMenu(document, fetcher([200, {}]));
        press(link('a'), 'pointerdown');
        press(link('a'), 'pointermove', 8, 8);
        vi.advanceTimersByTime(LONG_PRESS_MS);
        expect(menuEl().hidden).toBe(false);
        expect(entry('rename').hidden).toBe(false);
        expect(clicked(link('a'))).toBe(true);
        // Only that one click.
        expect(clicked(link('a'))).toBe(false);
    });

    it('moving, lifting, a mouse, another target or the native menu cancel it', () => {
        vi.useFakeTimers();
        setup([['a', 'e'], ['r', '']]);
        bindTreeMenu(document, fetcher([200, {}]));
        press(link('a'), 'pointermove', 50, 50);
        press(link('a'), 'pointerdown');
        press(link('a'), 'pointermove', 40, 40);
        vi.advanceTimersByTime(LONG_PRESS_MS);
        expect(menuEl().hidden).toBe(true);
        press(link('a'), 'pointerdown');
        press(link('a'), 'pointerup');
        press(link('a'), 'pointerdown');
        press(link('a'), 'pointercancel');
        press(link('a'), 'pointerdown', 5, 5, 'mouse');
        press(document.getElementById('out')!, 'pointerdown');
        vi.advanceTimersByTime(LONG_PRESS_MS);
        expect(menuEl().hidden).toBe(true);
        // A row without entries: no menu, and its click is not swallowed.
        press(link('r'), 'pointerdown');
        vi.advanceTimersByTime(LONG_PRESS_MS);
        expect(menuEl().hidden).toBe(true);
        expect(clicked(link('r'))).toBe(false);
        // The browser's own long-press menu came first: it opens ours, the timer is dropped.
        press(link('a'), 'pointerdown');
        rightClick(link('a'));
        expect(menuEl().hidden).toBe(false);
        vi.advanceTimersByTime(LONG_PRESS_MS);
        // A swallowed click elsewhere than a row is let through.
        press(link('a'), 'pointerdown');
        vi.advanceTimersByTime(LONG_PRESS_MS);
        expect(clicked(document.getElementById('out')!)).toBe(false);
    });
});
