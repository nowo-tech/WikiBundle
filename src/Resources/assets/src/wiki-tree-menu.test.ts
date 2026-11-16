import { afterEach, describe, expect, it, vi } from 'vitest';
import { allowedItems, bindTreeMenu, placeFor, postTree, refreshMenuButtons, titleOf, updateRowIcon } from './wiki-tree-menu';

/** A row: id, marks ('e' rename, 'i' icon, 'c' child, 's' sibling), current icon. */
type Spec = [id: string, marks: string, icon?: string, current?: boolean];

function rows(nodes: Spec[]): string {
    return nodes
        .map(([id, marks, icon, current]) => {
            const attrs = [
                marks.includes('e') ? ' data-wiki-menu-edit="1"' : '',
                marks.includes('i') ? ` data-wiki-menu-icon="1" data-wiki-icon="${icon ?? ''}"` : '',
                marks.includes('c') ? ' data-wiki-new-child="1"' : '',
                marks.includes('s') ? ' data-wiki-new-sibling="1"' : '',
            ].join('');

            return `<li role="none" data-nowo-ui-tree-node="${id}"${attrs}><div class="nowo-ui-tree__row">`
                + `<a href="http://localhost/p/${id}" role="treeitem" data-nowo-ui-tree-item tabindex="-1"${current ? ' aria-current="page"' : ''}>`
                + (icon ? `<span class="nowo-wiki-page-icon" aria-hidden="true">old</span>` : '')
                + `<span class="nowo-ui-tree__label">${id.toUpperCase()}</span></a>`
                + '<button type="button" tabindex="-1" hidden data-wiki-tree-menu-more>…</button></div></li>';
        })
        .join('');
}

const ITEMS = ['rename', 'icon', 'child', 'below', 'above', 'copy'];

function menu(items: string[] = ITEMS): string {
    return '<div role="menu" hidden data-wiki-tree-menu data-copied="Copied" data-copy-failed="Copy failed" data-rename-label="Rename “%title%”" data-failed="Failed">'
        + items.map((i) => `<button type="button" role="menuitem" tabindex="-1" hidden data-wiki-tree-menu-item="${i}">${i}</button>`).join('')
        + '</div><p role="status" data-wiki-tree-menu-status></p>';
}

const ICON_DIALOG = '<dialog data-wiki-tree-icon-dialog><p data-wiki-tree-icon-context data-template="Page “%title%”"></p>'
    + '<template data-wiki-tree-icon-template><form id="f"><input type="text" name="form[icon]"></form></template>'
    + '<div data-wiki-tree-icon-slot></div><p hidden data-wiki-tree-icon-error></p></dialog>';

const NEW_DIALOG = '<dialog data-wiki-new-page-dialog><p hidden data-wiki-new-page-context data-child="Inside “%title%”" data-below="Below “%title%”"></p>'
    + '<input type="hidden" data-wiki-new-page-place><input type="text" name="title"></dialog>';

function setup(nodes: Spec[], extra = menu() + ICON_DIALOG, treeAttrs = ' data-wiki-tree-rename="/t/rename" data-wiki-tree-icon="/t/icon" data-wiki-tree-new="/p/new"'): Document {
    document.body.innerHTML = `<ul role="tree" data-wiki-tree data-wiki-tree-token="tok"${treeAttrs}>${rows(nodes)}</ul>${extra}<p id="out">x</p>`;
    for (const d of document.querySelectorAll('dialog')) {
        (d as HTMLDialogElement).showModal = function showModal(this: HTMLDialogElement) { this.setAttribute('open', ''); };
        (d as HTMLDialogElement).close = function close(this: HTMLDialogElement) { this.removeAttribute('open'); this.dispatchEvent(new Event('close')); };
    }

    return document;
}

const li = (id: string): HTMLLIElement => document.querySelector(`[data-nowo-ui-tree-node="${id}"]`) as HTMLLIElement;
const link = (id: string): HTMLAnchorElement => li(id).querySelector('[data-nowo-ui-tree-item]') as HTMLAnchorElement;
const menuEl = (): HTMLElement => document.querySelector('[data-wiki-tree-menu]') as HTMLElement;
const visible = (): string[] => [...menuEl().querySelectorAll<HTMLElement>('[data-wiki-tree-menu-item]')].filter((b) => !b.hidden).map((b) => b.dataset.wikiTreeMenuItem!);
const item = (name: string): HTMLElement => menuEl().querySelector(`[data-wiki-tree-menu-item="${name}"]`) as HTMLElement;
const key = (target: Element, k: string, init: KeyboardEventInit = {}): KeyboardEvent => {
    const event = new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...init });
    target.dispatchEvent(event);

    return event;
};
const rightClick = (target: Element): MouseEvent => {
    const event = new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 10, clientY: 20 });
    target.dispatchEvent(event);

    return event;
};
const flush = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

function fetcher(status: number, data: unknown) {
    return vi.fn<typeof fetch>(async () => ({ status, json: async () => data }) as unknown as Response);
}

const sent = (f: ReturnType<typeof fetcher>, call = 0): Record<string, unknown> => JSON.parse(String(f.mock.calls[call][1]?.body)) as Record<string, unknown>;

afterEach(() => {
    vi.restoreAllMocks();
});

describe('allowedItems / placeFor / titleOf', () => {
    it('offers what the server marked, plus Copy link', () => {
        setup([['a', 'eics'], ['b', 'e'], ['r', '']]);
        expect(allowedItems(li('a'))).toEqual(['rename', 'icon', 'child', 'below', 'above', 'copy']);
        expect(allowedItems(li('a'), ['rename', 'child', 'below', 'copy'])).toEqual(['rename', 'child', 'below', 'copy']);
        expect(allowedItems(li('b'))).toEqual(['rename', 'copy']);
        expect(allowedItems(li('b'), ['rename'])).toEqual(['rename']);
        // A reader: nothing, the browser's own context menu.
        expect(allowedItems(li('r'))).toEqual([]);
        expect(placeFor(li('a'), 'child')).toBe('child:a');
        expect(titleOf(li('a'))).toBe('A');
    });

    it('shows the «…» button only where there is an entry', () => {
        setup([['a', 'e'], ['r', '']]);
        refreshMenuButtons(document);
        expect((li('a').querySelector('[data-wiki-tree-menu-more]') as HTMLElement).hidden).toBe(false);
        expect((li('r').querySelector('[data-wiki-tree-menu-more]') as HTMLElement).hidden).toBe(true);
    });
});

describe('bindTreeMenu: opening, keyboard, closing', () => {
    it('opens on right click with the row entries, roves with arrows / Home / End, Escape gives focus back', () => {
        setup([['a', 'ecs'], ['r', '']]);
        bindTreeMenu(document, fetcher(200, {}));
        bindTreeMenu(document, fetcher(200, {}));

        expect(rightClick(link('r')).defaultPrevented).toBe(false);
        expect(menuEl().hidden).toBe(true);

        expect(rightClick(link('a')).defaultPrevented).toBe(true);
        expect(menuEl().hidden).toBe(false);
        expect(visible()).toEqual(['rename', 'child', 'below', 'above', 'copy']);
        expect(document.activeElement).toBe(item('rename'));
        key(item('rename'), 'ArrowDown');
        expect(document.activeElement).toBe(item('child'));
        key(item('child'), 'ArrowUp');
        key(item('rename'), 'ArrowUp');
        expect(document.activeElement).toBe(item('copy'));
        key(item('copy'), 'Home');
        expect(document.activeElement).toBe(item('rename'));
        key(item('rename'), 'End');
        expect(document.activeElement).toBe(item('copy'));
        key(item('copy'), 'x');
        // A second right click on the same row while open keeps it open.
        expect(rightClick(link('a')).defaultPrevented).toBe(true);
        key(item('copy'), 'Escape');
        expect(menuEl().hidden).toBe(true);
        expect(document.activeElement).toBe(link('a'));
    });

    it('opens with Shift+F10 and the ContextMenu key; Tab and outside clicks close it', () => {
        setup([['a', 'e'], ['r', '']]);
        bindTreeMenu(document, fetcher(200, {}));
        link('a').focus();
        expect(key(link('a'), 'F10', { shiftKey: true }).defaultPrevented).toBe(true);
        expect(menuEl().hidden).toBe(false);
        key(item('rename'), 'Tab');
        expect(menuEl().hidden).toBe(true);
        expect(key(link('a'), 'ContextMenu').defaultPrevented).toBe(true);
        document.getElementById('out')!.click();
        expect(menuEl().hidden).toBe(true);
        expect(key(link('r'), 'ContextMenu').defaultPrevented).toBe(false);
        expect(key(link('a'), 'F10').defaultPrevented).toBe(false);
        expect(key(document.getElementById('out')!, 'ContextMenu').defaultPrevented).toBe(false);
    });

    it('opens from the «…» button (touch) and toggles it', () => {
        setup([['a', 'e']]);
        bindTreeMenu(document, fetcher(200, {}));
        const more = li('a').querySelector('[data-wiki-tree-menu-more]') as HTMLElement;
        expect(more.hidden).toBe(false);
        more.click();
        expect(menuEl().hidden).toBe(false);
        expect(more.hasAttribute('data-wiki-open')).toBe(true);
        more.click();
        expect(menuEl().hidden).toBe(true);
        expect(more.hasAttribute('data-wiki-open')).toBe(false);
    });

    it('does nothing without a menu, nor once the menu left the document', () => {
        document.body.innerHTML = '<ul data-wiki-tree></ul>';
        expect(() => bindTreeMenu(document)).not.toThrow();
        setup([['a', 'e']]);
        bindTreeMenu(document, fetcher(200, {}));
        menuEl().remove();
        expect(rightClick(link('a')).defaultPrevented).toBe(false);
        expect(key(link('a'), 'ContextMenu').defaultPrevented).toBe(false);
    });
});

describe('Rename', () => {
    it('edits the title in place and saves it on Enter', async () => {
        setup([['a', 'e']]);
        const fetchFn = fetcher(200, { title: 'Nuevo <b>título</b>', message: 'Renamed' });
        bindTreeMenu(document, fetchFn);
        rightClick(link('a'));
        item('rename').click();
        const input = li('a').querySelector('input.nowo-wiki-tree__rename') as HTMLInputElement;
        expect(link('a').hidden).toBe(true);
        expect(input.value).toBe('A');
        expect(input.getAttribute('aria-label')).toBe('Rename “A”');
        input.value = '  Nuevo <b>título</b> ';
        key(input, 'Enter');
        key(input, 'Enter');
        await flush();
        await flush();
        expect(fetchFn).toHaveBeenCalledTimes(1);
        expect(fetchFn).toHaveBeenCalledWith('/t/rename', expect.objectContaining({ method: 'POST' }));
        expect(sent(fetchFn)).toEqual({ page: 'a', title: 'Nuevo <b>título</b>', _token: 'tok' });
        expect(li('a').querySelector('.nowo-ui-tree__label')!.textContent).toBe('Nuevo <b>título</b>');
        expect(li('a').querySelector('b')).toBeNull();
        expect(li('a').querySelector('input')).toBeNull();
        expect(link('a').hidden).toBe(false);
        expect(document.activeElement).toBe(link('a'));
        expect(document.querySelector('[data-wiki-tree-menu-status]')!.textContent).toBe('Renamed');
    });

    it('cancels on Escape or an unchanged title, keeps the field on a refusal', async () => {
        setup([['a', 'e']]);
        const fetchFn = fetcher(409, { error: 'conflict', message: 'Changed meanwhile' });
        bindTreeMenu(document, fetchFn);
        rightClick(link('a'));
        item('rename').click();
        let input = li('a').querySelector('input') as HTMLInputElement;
        const tree = key(input, 'ArrowDown');
        expect(tree.defaultPrevented).toBe(false);
        key(input, 'Escape');
        expect(li('a').querySelector('input')).toBeNull();

        rightClick(link('a'));
        item('rename').click();
        input = li('a').querySelector('input') as HTMLInputElement;
        input.dispatchEvent(new Event('blur'));
        expect(li('a').querySelector('input')).toBeNull();
        expect(fetchFn).not.toHaveBeenCalled();

        rightClick(link('a'));
        item('rename').click();
        input = li('a').querySelector('input') as HTMLInputElement;
        input.value = 'Other';
        key(input, 'Enter');
        await flush();
        await flush();
        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(li('a').querySelector('input')).toBe(input);
        expect(document.querySelector('[data-wiki-tree-menu-status]')!.textContent).toBe('Changed meanwhile');
        // A failed request says so too.
        const failing = vi.fn<typeof fetch>(async () => { throw new Error('offline'); });
        setup([['b', 'e']]);
        bindTreeMenu(document, failing);
        rightClick(link('b'));
        item('rename').click();
        input = li('b').querySelector('input') as HTMLInputElement;
        input.value = 'X';
        key(input, 'Enter');
        await flush();
        await flush();
        expect(document.querySelector('[data-wiki-tree-menu-status]')!.textContent).toBe('Failed');
    });

    it('reloads after renaming the page being read', async () => {
        setup([['a', 'e', '', true]]);
        const reload = vi.fn();
        vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, reload } as Location);
        bindTreeMenu(document, fetcher(200, { title: 'Z' }));
        rightClick(link('a'));
        item('rename').click();
        const input = li('a').querySelector('input') as HTMLInputElement;
        input.value = 'Z';
        input.dispatchEvent(new Event('blur'));
        await flush();
        await flush();
        expect(reload).toHaveBeenCalled();
    });
});

describe('Change icon', () => {
    it('opens the dialog with the current value and saves the new icon in the row', async () => {
        setup([['a', 'ei', 'tabler:home']]);
        const fetchFn = fetcher(200, { icon: 'emoji:🚀', icon_html: '<span class="nowo-wiki-page-icon nowo-wiki-page-icon--emoji" aria-hidden="true">🚀</span>', message: 'Saved' });
        bindTreeMenu(document, fetchFn);
        rightClick(link('a'));
        item('icon').click();
        const dialog = document.querySelector('[data-wiki-tree-icon-dialog]') as HTMLDialogElement;
        expect(dialog.hasAttribute('open')).toBe(true);
        expect(dialog.querySelector('[data-wiki-tree-icon-context]')!.textContent).toBe('Page “A”');
        const field = dialog.querySelector('input[name="form[icon]"]') as HTMLInputElement;
        expect(field.value).toBe('tabler:home');
        field.value = 'emoji:🚀';
        dialog.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();
        await flush();
        expect(sent(fetchFn)).toEqual({ page: 'a', icon: 'emoji:🚀', _token: 'tok' });
        expect(link('a').querySelector('.nowo-wiki-page-icon')!.textContent).toBe('🚀');
        expect(li('a').getAttribute('data-wiki-icon')).toBe('emoji:🚀');
        expect(dialog.hasAttribute('open')).toBe(false);
        expect(dialog.querySelector('[data-wiki-tree-icon-slot]')!.children).toHaveLength(0);
        expect(document.activeElement).toBe(link('a'));
    });

    it('shows the refusal and clears with an empty value', async () => {
        setup([['a', 'ei', '']]);
        const fetchFn = fetcher(422, { error: 'icon_invalid', message: 'Invalid icon' });
        bindTreeMenu(document, fetchFn);
        rightClick(link('a'));
        item('icon').click();
        const dialog = document.querySelector('[data-wiki-tree-icon-dialog]') as HTMLDialogElement;
        dialog.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();
        await flush();
        expect(sent(fetchFn).icon).toBeNull();
        const error = dialog.querySelector('[data-wiki-tree-icon-error]') as HTMLElement;
        expect(error.hidden).toBe(false);
        expect(error.textContent).toBe('Invalid icon');
    });

    it('a failed request is reported in the dialog', async () => {
        setup([['a', 'ei', '']]);
        bindTreeMenu(document, vi.fn<typeof fetch>(async () => { throw new Error('x'); }));
        rightClick(link('a'));
        item('icon').click();
        const dialog = document.querySelector('[data-wiki-tree-icon-dialog]') as HTMLDialogElement;
        dialog.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();
        await flush();
        expect(dialog.querySelector('[data-wiki-tree-icon-error]')!.textContent).toBe('Failed');
    });

    it('updateRowIcon adds, replaces and removes the icon', () => {
        setup([['a', 'ei', '']]);
        updateRowIcon(li('a'), 'tabler:x', '<span class="nowo-wiki-page-icon">i</span>');
        expect(link('a').firstElementChild!.className).toBe('nowo-wiki-page-icon');
        updateRowIcon(li('a'), null, '');
        expect(link('a').querySelector('.nowo-wiki-page-icon')).toBeNull();
        expect(li('a').getAttribute('data-wiki-icon')).toBe('');
        li('a').querySelector('[data-nowo-ui-tree-item]')!.remove();
        expect(() => updateRowIcon(li('a'), null, '')).not.toThrow();
    });
});

describe('Add page and Copy link', () => {
    it('opens the new-page dialog with the place when the page has it', () => {
        setup([['a', 'cs']], menu() + NEW_DIALOG);
        bindTreeMenu(document, fetcher(200, {}));
        rightClick(link('a'));
        item('child').click();
        const dialog = document.querySelector('[data-wiki-new-page-dialog]') as HTMLDialogElement;
        expect(dialog.hasAttribute('open')).toBe(true);
        expect((dialog.querySelector('[data-wiki-new-page-place]') as HTMLInputElement).value).toBe('child:a');
        expect(dialog.querySelector('[data-wiki-new-page-context]')!.textContent).toBe('Inside “A”');
        dialog.close();
        expect((dialog.querySelector('[data-wiki-new-page-place]') as HTMLInputElement).value).toBe('');
        expect(document.activeElement).toBe(link('a'));
    });

    it('opens the new-page form with ?place= without the dialog', () => {
        setup([['a', 'cs']]);
        const assign = vi.fn();
        vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, assign } as Location);
        bindTreeMenu(document, fetcher(200, {}));
        rightClick(link('a'));
        item('below').click();
        expect(assign).toHaveBeenCalledWith('/p/new?place=below%3Aa');
    });

    it('copies the page address and says so', async () => {
        setup([['a', 'e']]);
        const writeText = vi.fn<(text: string) => Promise<void>>(async () => undefined);
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        bindTreeMenu(document, fetcher(200, {}));
        rightClick(link('a'));
        item('copy').click();
        await flush();
        expect(writeText).toHaveBeenCalledWith('http://localhost/p/a');
        expect(document.querySelector('[data-wiki-tree-menu-status]')!.textContent).toBe('Copied');
        writeText.mockRejectedValueOnce(new Error('denied'));
        rightClick(link('a'));
        item('copy').click();
        await flush();
        await flush();
        expect(document.querySelector('[data-wiki-tree-menu-status]')!.textContent).toBe('Copy failed');
        Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true });
        rightClick(link('a'));
        item('copy').click();
        expect(document.querySelector('[data-wiki-tree-menu-status]')!.textContent).toBe('Copy failed');
    });
});

describe('fallbacks', () => {
    it('opens dialogs without showModal() and says "failed" when a refusal has no message', async () => {
        setup([['a', 'eics', '']], menu() + ICON_DIALOG + NEW_DIALOG);
        for (const d of document.querySelectorAll('dialog')) {
            Object.defineProperty(d, 'showModal', { value: undefined, configurable: true });
        }
        const fetchFn = fetcher(500, {});
        bindTreeMenu(document, fetchFn);
        rightClick(link('a'));
        item('icon').click();
        const iconDialog = document.querySelector('[data-wiki-tree-icon-dialog]') as HTMLDialogElement;
        expect(iconDialog.hasAttribute('open')).toBe(true);
        iconDialog.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();
        await flush();
        expect(iconDialog.querySelector('[data-wiki-tree-icon-error]')!.textContent).toBe('Failed');
        iconDialog.close();

        rightClick(link('a'));
        item('child').click();
        expect(document.querySelector('[data-wiki-new-page-dialog]')!.hasAttribute('open')).toBe(true);

        rightClick(link('a'));
        item('rename').click();
        const input = li('a').querySelector('input') as HTMLInputElement;
        input.value = 'Q';
        key(input, 'Enter');
        await flush();
        await flush();
        expect(document.querySelector('[data-wiki-tree-menu-status]')!.textContent).toBe('Failed');
        // A refused title keeps its message on blur (no second request).
        input.dispatchEvent(new Event('blur'));
        expect(fetchFn).toHaveBeenCalledTimes(2);
    });

    it('a menu without its texts still works', () => {
        setup([['a', 'e']], '<div role="menu" hidden data-wiki-tree-menu>'
            + ['rename', 'copy'].map((i) => `<button type="button" role="menuitem" hidden data-wiki-tree-menu-item="${i}">${i}</button>`).join('')
            + '</div><p data-wiki-tree-menu-status>before</p>');
        Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true });
        bindTreeMenu(document);
        rightClick(link('a'));
        expect(visible()).toEqual(['rename', 'copy']);
        item('copy').click();
        expect(document.querySelector('[data-wiki-tree-menu-status]')!.textContent).toBe('');
    });

    it('reloads after changing the icon of the page being read; no icon in the answer clears the row', async () => {
        setup([['a', 'ei', 'tabler:home', true]]);
        const reload = vi.fn();
        vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, reload } as Location);
        bindTreeMenu(document, fetcher(200, { icon_html: '', message: 'ok' }));
        rightClick(link('a'));
        item('icon').click();
        document.querySelector('[data-wiki-tree-icon-dialog] form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();
        await flush();
        expect(reload).toHaveBeenCalled();
        expect(li('a').getAttribute('data-wiki-icon')).toBe('');
        expect(link('a').querySelector('.nowo-wiki-page-icon')).toBeNull();
    });

    it('a right click in the rename field keeps the browser menu', () => {
        setup([['a', 'e']]);
        bindTreeMenu(document, fetcher(200, {}));
        rightClick(link('a'));
        item('rename').click();
        const input = li('a').querySelector('input') as HTMLInputElement;
        expect(rightClick(input).defaultPrevented).toBe(false);
    });
});

describe('postTree', () => {
    it('sends JSON with the token and survives a non-JSON answer', async () => {
        const fetchFn = vi.fn<typeof fetch>(async () => ({ status: 503, json: async () => { throw new Error('html'); } }) as unknown as Response);
        expect(await postTree('/x', 't', { a: 1 }, fetchFn)).toEqual({ status: 503, data: {} });
        expect(fetchFn.mock.calls[0][1]).toMatchObject({ method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } });
    });
});
