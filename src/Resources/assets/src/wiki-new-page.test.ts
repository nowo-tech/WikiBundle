import { afterEach, describe, expect, it, vi } from 'vitest';
import { allowedChoices, bindNewPageMenu, fillNewPageDialog, placeFor, refreshMoreButtons } from './wiki-new-page';

/** A row: id, what the server marked ('c' = as child, 's' = above / below), children. */
type Spec = [id: string, marks: string, children?: Spec[]];

/** Markup of the UiKit `tree` partial with the wiki's «…» actions template and the server's `data-wiki-new-child`. */
function items(nodes: Spec[], level = 1): string {
    return nodes
        .map(([id, marks, children], i) => {
            const group = (children ?? []).length > 0
                ? `<ul role="group" id="g-${id}" hidden>${items(children!, level + 1)}</ul>`
                : '';
            return `<li class="nowo-ui-tree__item" role="none" data-nowo-ui-tree-node="${id}"${marks.includes('c') ? ' data-wiki-new-child="1"' : ''}${marks.includes('s') ? ' data-wiki-new-sibling="1"' : ''}>`
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
    + '<input type="hidden" data-wiki-new-page-place><input type="text" name="title"></dialog>';

/** A fresh body per test: the listeners of the previous tests' menus do nothing once those left the document. */
function setup(nodes: Spec[], extra = MENU + DIALOG): Document {
    document.body.innerHTML = `<nav><ul role="tree" data-wiki-tree>${items(nodes)}</ul></nav>${extra}<p id="outside">x</p>`;

    return document;
}

const SAMPLE: Spec[] = [['a', 'cs', [['a1', 's'], ['a2', 'cs']]], ['b', 's', [['b1', 'c']]], ['c', 'cs']];

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
const field = (doc: Document): string => (doc.querySelector('[data-wiki-new-page-place]') as HTMLInputElement).value;

afterEach(() => {
    vi.restoreAllMocks();
});

describe('allowedChoices / placeFor', () => {
    it('offers what the server marked on the row', () => {
        const doc = setup(SAMPLE);
        expect(allowedChoices(li(doc, 'a'))).toEqual(['above', 'below', 'child']);
        expect(allowedChoices(li(doc, 'a1'))).toEqual(['above', 'below']);
        expect(allowedChoices(li(doc, 'b1'))).toEqual(['child']);
    });

    it('sends the intent, never an index', () => {
        const doc = setup(SAMPLE);
        expect(placeFor(li(doc, 'a2'), 'above')).toBe('above:a2');
        expect(placeFor(li(doc, 'b'), 'below')).toBe('below:b');
        expect(placeFor(li(doc, 'a'), 'child')).toBe('child:a');
        expect(placeFor(doc.createElement('li') as HTMLLIElement, 'child')).toBe('child:');
    });
});

describe('fillNewPageDialog / refreshMoreButtons', () => {
    it('writes and clears the place and the context line; tolerates a dialog without them', () => {
        const doc = setup(SAMPLE);
        const dialog = dialogOf(doc);
        fillNewPageDialog(dialog, 'child:a', 'Inside “A”');
        expect(field(doc)).toBe('child:a');
        const line = dialog.querySelector<HTMLElement>('[data-wiki-new-page-context]')!;
        expect([line.textContent, line.hidden]).toEqual(['Inside “A”', false]);
        fillNewPageDialog(dialog, '', '');
        expect(field(doc)).toBe('');
        expect(line.hidden).toBe(true);
        expect(() => fillNewPageDialog(doc.createElement('div'), 'child:a', 'x')).not.toThrow();
    });

    it('shows the «…» button of the rows that have a choice only', () => {
        const doc = setup([['x', 's', [['x1', '']]], ['y', 'c']]);
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
        const doc2 = setup([['z', '', [['z1', '']]]]);
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
        expect(field(doc)).toBe('below:b');
        expect(dialog.querySelector('[data-wiki-new-page-context]')!.textContent).toBe('Below “B”');
        expect(doc.activeElement).toBe(dialog.querySelector('input[name="title"]'));

        dialog.dispatchEvent(new Event('close'));
        expect(field(doc)).toBe('');
        expect(doc.activeElement).toBe(link(doc, 'b'));
        // Opened another way (the page actions): closing it leaves the focus alone.
        dialog.dispatchEvent(new Event('close'));

        // A stray choice outside the menu, or with the menu closed, does nothing.
        choice(doc, 'child').click();
        expect(showModal).toHaveBeenCalledOnce();
    });

    it('falls back to the open attribute without showModal and to an empty context without the line', () => {
        const doc = setup(SAMPLE, MENU + '<dialog data-wiki-new-page-dialog><input type="hidden" data-wiki-new-page-place></dialog>');
        bindNewPageMenu(doc);
        const dialog = dialogOf(doc);
        Object.defineProperty(dialog, 'showModal', { value: undefined, configurable: true });

        more(doc, 'c').click();
        choice(doc, 'child').click();

        expect(dialog.hasAttribute('open')).toBe(true);
        expect(field(doc)).toBe('child:c');
    });

    it('a row without a label uses its text, and a `$` in a title stays text', () => {
        const doc = setup([['p', 'cs'], ['q', 's']]);
        bindNewPageMenu(doc);
        const dialog = dialogOf(doc);
        Object.defineProperty(dialog, 'showModal', { value: () => undefined, configurable: true });
        link(doc, 'p').innerHTML = ' Plain ';
        more(doc, 'p').click();
        choice(doc, 'above').click();
        // The row's text: the link and its «…» button.
        expect(dialog.querySelector('[data-wiki-new-page-context]')!.textContent).toBe('Above “Plain …”');

        // PT-I2: replacement patterns in a title are not expanded.
        link(doc, 'q').querySelector('.nowo-ui-tree__label')!.textContent = 'a$`b $& $1';
        more(doc, 'q').click();
        choice(doc, 'below').click();
        expect(dialog.querySelector('[data-wiki-new-page-context]')!.textContent).toBe('Below “a$`b $& $1”');
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
