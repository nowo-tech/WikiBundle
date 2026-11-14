import { afterEach, describe, expect, it, vi } from 'vitest';
import { allowedChoices, bindNewPageMenu, fillNewPageDialog, placeFor, refreshMoreButtons } from './wiki-new-page';

type Spec = [id: string, child: boolean, children?: Spec[]];

/** Markup of the UiKit `tree` partial with the wiki's «…» actions template and the server's `data-wiki-new-child`. */
function items(nodes: Spec[], level = 1): string {
    return nodes
        .map(([id, child, children], i) => {
            const group = (children ?? []).length > 0
                ? `<ul role="group" id="g-${id}" hidden>${items(children!, level + 1)}</ul>`
                : '';
            return `<li class="nowo-ui-tree__item" role="none" data-nowo-ui-tree-node="${id}"${child ? ' data-wiki-new-child="1"' : ''}>`
                + '<div class="nowo-ui-tree__row"><span class="nowo-ui-tree__spacer"></span>'
                + `<a href="/${id}" role="treeitem" data-nowo-ui-tree-item tabindex="${level === 1 && i === 0 ? 0 : -1}"><span class="nowo-ui-tree__label">${id.toUpperCase()}</span></a>`
                + `<button type="button" tabindex="-1" hidden aria-haspopup="menu" data-wiki-new-page-more>…</button></div>${group}</li>`;
        })
        .join('');
}

const MENU = '<div role="menu" hidden data-wiki-new-page-menu>'
    + ['above', 'below', 'child'].map((c) => `<button type="button" role="menuitem" tabindex="-1" data-wiki-new-page-choice="${c}">${c}</button>`).join('')
    + '</div>';

const DIALOG = '<dialog data-wiki-new-page-dialog><p hidden data-wiki-new-page-context data-above="Above “%title%”" data-below="Below “%title%”" data-child="Inside “%title%”"></p>'
    + '<input type="hidden" data-wiki-new-page-parent><input type="hidden" data-wiki-new-page-position><input type="text" name="title"></dialog>';

/** A fresh body per test: the listeners of the previous tests' menus do nothing once those left the document. */
function setup(nodes: Spec[], extra = MENU + DIALOG): Document {
    document.body.innerHTML = `<nav><ul role="tree" data-wiki-tree>${items(nodes)}</ul></nav>${extra}<p id="outside">x</p>`;

    return document;
}

const SAMPLE: Spec[] = [['a', true, [['a1', false], ['a2', true]]], ['b', false, [['b1', true]]], ['c', true]];

const li = (doc: Document, id: string): HTMLLIElement => doc.querySelector(`[data-nowo-ui-tree-node="${id}"]`) as HTMLLIElement;
const link = (doc: Document, id: string): HTMLElement => li(doc, id).querySelector('[data-nowo-ui-tree-item]') as HTMLElement;
const more = (doc: Document, id: string): HTMLElement => li(doc, id).querySelector('[data-wiki-new-page-more]') as HTMLElement;
const menuOf = (doc: Document): HTMLElement => doc.querySelector('[data-wiki-new-page-menu]') as HTMLElement;
const dialogOf = (doc: Document): HTMLDialogElement => doc.querySelector('[data-wiki-new-page-dialog]') as HTMLDialogElement;
const visibleChoices = (doc: Document): string[] =>
    [...menuOf(doc).querySelectorAll<HTMLElement>('[data-wiki-new-page-choice]')].filter((b) => !b.hidden).map((b) => b.dataset.wikiNewPageChoice!);
const choice = (doc: Document, c: string): HTMLElement => menuOf(doc).querySelector(`[data-wiki-new-page-choice="${c}"]`) as HTMLElement;
const key = (target: Element, k: string, init: KeyboardEventInit = {}): KeyboardEvent => {
    const event = new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...init });
    target.dispatchEvent(event);

    return event;
};
const fields = (doc: Document): [string, string] => [
    (doc.querySelector('[data-wiki-new-page-parent]') as HTMLInputElement).value,
    (doc.querySelector('[data-wiki-new-page-position]') as HTMLInputElement).value,
];

afterEach(() => {
    vi.restoreAllMocks();
});

describe('allowedChoices / placeFor', () => {
    it('offers "as child" on marked rows and above / below under a marked parent or at the top level', () => {
        const doc = setup(SAMPLE);
        expect(allowedChoices(li(doc, 'a'))).toEqual(['above', 'below', 'child']);
        expect(allowedChoices(li(doc, 'a1'))).toEqual(['above', 'below']);
        expect(allowedChoices(li(doc, 'b'))).toEqual(['above', 'below']);
        // Under b (not editable): no sibling; b1 itself is editable.
        expect(allowedChoices(li(doc, 'b1'))).toEqual(['child']);
    });

    it('counts 1-based positions among the visible siblings, and the last place among the children', () => {
        const doc = setup(SAMPLE);
        expect(placeFor(li(doc, 'a'), 'above')).toEqual({ parent: '', position: 1 });
        expect(placeFor(li(doc, 'b'), 'below')).toEqual({ parent: '', position: 3 });
        expect(placeFor(li(doc, 'a2'), 'above')).toEqual({ parent: 'a', position: 2 });
        expect(placeFor(li(doc, 'a'), 'child')).toEqual({ parent: 'a', position: 3 });
        expect(placeFor(li(doc, 'c'), 'child')).toEqual({ parent: 'c', position: 1 });
        const bare = doc.createElement('li');
        expect(placeFor(bare as HTMLLIElement, 'child')).toEqual({ parent: '', position: 1 });
        expect(placeFor(bare as HTMLLIElement, 'above')).toEqual({ parent: '', position: 0 });
    });
});

describe('fillNewPageDialog / refreshMoreButtons', () => {
    it('writes and clears the place and the context line; tolerates a dialog without them', () => {
        const doc = setup(SAMPLE);
        const dialog = dialogOf(doc);
        fillNewPageDialog(dialog, { parent: 'a', position: 2 }, 'Inside “A”');
        expect(fields(doc)).toEqual(['a', '2']);
        const line = dialog.querySelector<HTMLElement>('[data-wiki-new-page-context]')!;
        expect([line.textContent, line.hidden]).toEqual(['Inside “A”', false]);
        fillNewPageDialog(dialog, null, '');
        expect(fields(doc)).toEqual(['', '']);
        expect(line.hidden).toBe(true);
        expect(() => fillNewPageDialog(doc.createElement('div'), { parent: '', position: 1 }, 'x')).not.toThrow();
    });

    it('shows the «…» button of the rows that have a choice only', () => {
        const doc = setup([['x', false, [['x1', false]]], ['y', true]]);
        doc.body.insertAdjacentHTML('beforeend', '<button data-wiki-new-page-more id="stray" hidden></button>');
        refreshMoreButtons(doc);
        expect(more(doc, 'x').hidden).toBe(false);
        expect(more(doc, 'x1').hidden).toBe(true);
        expect(more(doc, 'y').hidden).toBe(false);
        expect((doc.getElementById('stray') as HTMLElement).hidden).toBe(true);
    });
});

describe('bindNewPageMenu', () => {
    it('does nothing without the menu or the dialog, and binds a document once', () => {
        const bare = setup(SAMPLE, '');
        bindNewPageMenu(bare);
        expect(more(bare, 'a').hidden).toBe(true);

        const doc = setup(SAMPLE);
        const listen = vi.spyOn(doc, 'addEventListener');
        bindNewPageMenu(doc);
        bindNewPageMenu(doc);
        expect(listen).toHaveBeenCalledTimes(3);
        expect(more(doc, 'a').hidden).toBe(false);
        // Once that menu left the document, its listeners do nothing.
        const old = menuOf(doc);
        setup(SAMPLE, '');
        const event = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
        link(doc, 'a').dispatchEvent(event);
        expect(key(link(doc, 'a'), 'ContextMenu').defaultPrevented).toBe(false);
        more(doc, 'a').click();
        expect(event.defaultPrevented).toBe(false);
        expect(old.hidden).toBe(true);
    });

    it('opens from a right click at the pointer, with the allowed choices only and the focus on the first', () => {
        const doc = setup(SAMPLE);
        bindNewPageMenu(doc);
        const event = new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 40, clientY: 30 });
        link(doc, 'b').dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(menuOf(doc).hidden).toBe(false);
        expect(visibleChoices(doc)).toEqual(['above', 'below']);
        expect(doc.activeElement).toBe(choice(doc, 'above'));
        expect(menuOf(doc).style.left).toBe('40px');
        expect(menuOf(doc).style.top).toBe('30px');

        // On the document itself, or outside the tree: nothing.
        const onDoc = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
        doc.dispatchEvent(onDoc);
        expect(onDoc.defaultPrevented).toBe(false);
        const outside = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
        doc.getElementById('outside')!.dispatchEvent(outside);
        expect(outside.defaultPrevented).toBe(false);

        // A row without any choice keeps the browser's menu.
        const doc2 = setup([['z', false, [['z1', false]]]]);
        bindNewPageMenu(doc2);
        const plain = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
        link(doc2, 'z1').dispatchEvent(plain);
        expect(plain.defaultPrevented).toBe(false);
        expect(menuOf(doc2).hidden).toBe(true);
    });

    it('opens from the ContextMenu key and Shift+F10, and the contextmenu event that follows is swallowed', () => {
        const doc = setup(SAMPLE);
        bindNewPageMenu(doc);

        expect(key(link(doc, 'a'), 'ContextMenu').defaultPrevented).toBe(true);
        expect(visibleChoices(doc)).toEqual(['above', 'below', 'child']);
        const follow = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
        link(doc, 'a').dispatchEvent(follow);
        expect(follow.defaultPrevented).toBe(true);
        expect(menuOf(doc).hidden).toBe(false);

        key(doc.activeElement!, 'Escape');
        expect(menuOf(doc).hidden).toBe(true);
        expect(doc.activeElement).toBe(link(doc, 'a'));

        expect(key(link(doc, 'b1'), 'F10', { shiftKey: true }).defaultPrevented).toBe(true);
        expect(visibleChoices(doc)).toEqual(['child']);
        // Another key, a plain F10, or a key outside a row: nothing.
        key(doc.activeElement!, 'Tab');
        expect(key(link(doc, 'a'), 'F10').defaultPrevented).toBe(false);
        expect(key(link(doc, 'a'), 'Enter').defaultPrevented).toBe(false);
        expect(key(doc.getElementById('outside')!, 'ContextMenu').defaultPrevented).toBe(false);
        expect(menuOf(doc).hidden).toBe(true);
    });

    it('moves the focus with the arrows, Home and End (wrapping), and Tab closes it', () => {
        const doc = setup(SAMPLE);
        bindNewPageMenu(doc);
        key(link(doc, 'a'), 'ContextMenu');

        key(doc.activeElement!, 'ArrowDown');
        expect(doc.activeElement).toBe(choice(doc, 'below'));
        key(doc.activeElement!, 'End');
        expect(doc.activeElement).toBe(choice(doc, 'child'));
        key(doc.activeElement!, 'ArrowDown');
        expect(doc.activeElement).toBe(choice(doc, 'above'));
        key(doc.activeElement!, 'ArrowUp');
        expect(doc.activeElement).toBe(choice(doc, 'child'));
        key(doc.activeElement!, 'Home');
        expect(doc.activeElement).toBe(choice(doc, 'above'));
        expect(key(doc.activeElement!, 'x').defaultPrevented).toBe(false);
        expect(key(doc.activeElement!, 'Tab').defaultPrevented).toBe(true);
        expect(menuOf(doc).hidden).toBe(true);
        expect(doc.activeElement).toBe(link(doc, 'a'));
    });

    it('opens from the «…» button, which toggles it; a click outside closes it without moving the focus', () => {
        const doc = setup(SAMPLE);
        bindNewPageMenu(doc);
        more(doc, 'c').click();
        expect(menuOf(doc).hidden).toBe(false);
        expect(more(doc, 'c').hasAttribute('data-wiki-open')).toBe(true);

        // Another row's button moves the menu there.
        more(doc, 'a').click();
        expect(more(doc, 'c').hasAttribute('data-wiki-open')).toBe(false);
        expect(more(doc, 'a').hasAttribute('data-wiki-open')).toBe(true);
        more(doc, 'a').click();
        expect(menuOf(doc).hidden).toBe(true);
        expect(doc.activeElement).toBe(link(doc, 'a'));

        more(doc, 'a').click();
        menuOf(doc).click();
        expect(menuOf(doc).hidden).toBe(false);
        doc.getElementById('outside')!.click();
        expect(menuOf(doc).hidden).toBe(true);
        // Closed already: nothing more.
        doc.getElementById('outside')!.click();
        doc.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(menuOf(doc).hidden).toBe(true);
    });

    it('a choice fills the dialog, opens it modal with the focus on the title, and closing it clears the place', () => {
        const doc = setup(SAMPLE);
        bindNewPageMenu(doc);
        const dialog = dialogOf(doc);
        const showModal = vi.fn(function (this: HTMLDialogElement) {
            this.setAttribute('open', '');
        });
        Object.defineProperty(dialog, 'showModal', { value: showModal, configurable: true });

        key(link(doc, 'b'), 'ContextMenu');
        choice(doc, 'below').click();

        expect(showModal).toHaveBeenCalledOnce();
        expect(menuOf(doc).hidden).toBe(true);
        expect(fields(doc)).toEqual(['', '3']);
        expect(dialog.querySelector('[data-wiki-new-page-context]')!.textContent).toBe('Below “B”');
        expect(doc.activeElement).toBe(dialog.querySelector('input[name="title"]'));

        dialog.dispatchEvent(new Event('close'));
        expect(fields(doc)).toEqual(['', '']);
        expect(doc.activeElement).toBe(link(doc, 'b'));
        // Opened another way (the page actions): closing it leaves the focus alone.
        dialog.dispatchEvent(new Event('close'));

        // A stray choice outside the menu, or with the menu closed, does nothing.
        choice(doc, 'child').click();
        expect(showModal).toHaveBeenCalledOnce();
    });

    it('falls back to the open attribute without showModal and to an empty context without the line', () => {
        const doc = setup(SAMPLE, MENU + '<dialog data-wiki-new-page-dialog><input type="hidden" data-wiki-new-page-parent></dialog>');
        bindNewPageMenu(doc);
        const dialog = dialogOf(doc);
        Object.defineProperty(dialog, 'showModal', { value: undefined, configurable: true });

        more(doc, 'c').click();
        choice(doc, 'child').click();

        expect(dialog.hasAttribute('open')).toBe(true);
        expect((doc.querySelector('[data-wiki-new-page-parent]') as HTMLInputElement).value).toBe('c');
    });

    it('a row without a label uses its link text, and tree changes refresh the «…» buttons', async () => {
        const doc = setup([['p', true], ['q', false]]);
        bindNewPageMenu(doc);
        const dialog = dialogOf(doc);
        Object.defineProperty(dialog, 'showModal', { value: () => undefined, configurable: true });
        link(doc, 'p').innerHTML = ' Plain ';
        more(doc, 'p').click();
        choice(doc, 'above').click();
        // The row's text: the link and its «…» button.
        expect(dialog.querySelector('[data-wiki-new-page-context]')!.textContent).toBe('Above “Plain …”');

        // q moves under p (not editable): it loses its choices; its button hides once the tree changed.
        const q = li(doc, 'q');
        expect(more(doc, 'q').hidden).toBe(false);
        li(doc, 'p').insertAdjacentHTML('beforeend', '<ul role="group"></ul>');
        li(doc, 'p').removeAttribute('data-wiki-new-child');
        li(doc, 'p').querySelector('ul')!.append(q);
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(more(doc, 'q').hidden).toBe(true);
    });

    it('keeps the menu inside the window', () => {
        const doc = setup(SAMPLE);
        bindNewPageMenu(doc);
        const menu = menuOf(doc);
        Object.defineProperty(menu, 'offsetWidth', { value: 200, configurable: true });
        Object.defineProperty(menu, 'offsetHeight', { value: 100, configurable: true });
        link(doc, 'a').dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: window.innerWidth, clientY: window.innerHeight }));

        expect(menu.style.left).toBe(`${window.innerWidth - 208}px`);
        expect(menu.style.top).toBe(`${window.innerHeight - 108}px`);
    });
});
