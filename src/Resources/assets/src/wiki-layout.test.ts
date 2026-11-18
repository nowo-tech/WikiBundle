import { afterEach, describe, expect, it, vi } from 'vitest';
import { bindFocusTreeDrawer, bindTreeHiddenFocus, observeCardToolbar, TREE_ID } from './wiki-layout';

const layout = (mode: string): string => `
<header><button type="button" data-nowo-ui-rail-toggle data-nowo-wiki-tree-toggle id="header-toggle">Expand</button><button type="button" id="other">Other</button></header>
<div class="nowo-wiki-layout ${mode}">
  <aside id="${TREE_ID}" data-nowo-ui-rail-host data-nowo-ui-rail="expanded">
    <button type="button" data-nowo-ui-rail-toggle id="card-toggle">Collapse</button>
  </aside>
</div>`;

const change = (host: Element, collapsed: boolean | undefined): void => {
    host.dispatchEvent(new CustomEvent('nowo-ui:rail:change', { bubbles: true, detail: collapsed === undefined ? undefined : { collapsed } }));
};

describe('wiki layout 1.30: tree hidden mode focus', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('moves the focus to the toolbar toggle on collapse and back to the card toggle on expand', () => {
        bindTreeHiddenFocus(document);
        bindTreeHiddenFocus(document);
        document.body.innerHTML = layout('nowo-wiki-layout--tree-hidden');
        const host = document.getElementById(TREE_ID) as HTMLElement;
        const card = document.getElementById('card-toggle') as HTMLElement;
        const header = document.getElementById('header-toggle') as HTMLElement;

        card.focus();
        change(host, true);
        expect(document.activeElement).toBe(header);

        change(host, false);
        expect(document.activeElement).toBe(card);

        // Focus on <body> (the button that had it disappeared): collapsing with no detail = expanded.
        (document.activeElement as HTMLElement).blur();
        change(host, undefined);
        expect(document.activeElement).toBe(card);
    });

    it('leaves the focus elsewhere, other hosts and the rail mode alone', () => {
        bindTreeHiddenFocus();
        document.body.innerHTML = layout('nowo-wiki-layout--tree-hidden');
        const host = document.getElementById(TREE_ID) as HTMLElement;
        const other = document.getElementById('other') as HTMLElement;
        other.focus();
        change(host, true);
        expect(document.activeElement).toBe(other);

        // Another rail host.
        host.id = 'filters';
        (document.getElementById('card-toggle') as HTMLElement).focus();
        change(host, true);
        expect(document.activeElement?.id).toBe('card-toggle');

        // The rail mode (1.29): the card toggle stays visible, nothing moves.
        document.body.innerHTML = layout('');
        (document.getElementById('card-toggle') as HTMLElement).focus();
        change(document.getElementById(TREE_ID) as HTMLElement, true);
        expect(document.activeElement?.id).toBe('card-toggle');

        // An event from outside any element.
        document.dispatchEvent(new CustomEvent('nowo-ui:rail:change', { detail: { collapsed: true } }));
        expect(document.activeElement?.id).toBe('card-toggle');

        // No toolbar toggle: nothing to focus.
        document.body.innerHTML = layout('nowo-wiki-layout--tree-hidden');
        document.getElementById('header-toggle')?.remove();
        (document.getElementById('card-toggle') as HTMLElement).focus();
        change(document.getElementById(TREE_ID) as HTMLElement, true);
        expect(document.activeElement?.id).toBe('card-toggle');
    });
});

describe('wiki layout 1.30: card toolbar width', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('publishes the toolbar width on its frame, again on resize', () => {
        document.body.innerHTML = '<div data-nowo-wiki-card-frame id="frame"><div data-nowo-wiki-card-toolbar><div role="toolbar" id="bar"></div></div></div>';
        const bar = document.getElementById('bar') as HTMLElement;
        let width = 120.2;
        bar.getBoundingClientRect = () => ({ width, height: 40.5 }) as DOMRect;
        const callbacks: Array<() => void> = [];
        const observe = vi.fn();
        class FakeObserver {
            constructor(callback: () => void) {
                callbacks.push(callback);
            }

            observe = observe;
        }
        expect(observeCardToolbar(document, FakeObserver as unknown as typeof ResizeObserver)).toBe(1);
        const frame = document.getElementById('frame') as HTMLElement;
        expect(frame.style.getPropertyValue('--nowo-wiki-card-toolbar-width')).toBe('121px');
        expect(frame.style.getPropertyValue('--nowo-wiki-card-toolbar-height')).toBe('41px');
        expect(observe).toHaveBeenCalledWith(bar);
        width = 80;
        callbacks[0]();
        expect(frame.style.getPropertyValue('--nowo-wiki-card-toolbar-width')).toBe('80px');
    });

    it('measures once without ResizeObserver, and the bar itself when it has no child', () => {
        document.body.innerHTML = '<div data-nowo-wiki-card-frame id="frame"><div data-nowo-wiki-card-toolbar id="bar"></div></div>';
        expect(observeCardToolbar(document, undefined)).toBe(1);
        expect((document.getElementById('frame') as HTMLElement).style.getPropertyValue('--nowo-wiki-card-toolbar-width')).toBe('0px');
        expect(observeCardToolbar()).toBe(1);
        document.body.innerHTML = '';
        expect(observeCardToolbar()).toBe(0);
    });
});

describe('bindFocusTreeDrawer (1.41)', () => {
    const page = (withSidebar = true): void => {
        document.body.innerHTML = '<button type="button" aria-expanded="false" data-wiki-focus-tree-toggle>Pages</button>'
            + (withSidebar ? '<aside data-wiki-sidebar><ul role="tree"><li><a href="/a" data-nowo-ui-tree-item tabindex="-1">A</a></li><li><a href="/b" data-nowo-ui-tree-item tabindex="0">B</a></li></ul></aside>' : '')
            + '<p id="elsewhere">x</p>';
    };
    const button = (): HTMLElement => document.querySelector('[data-wiki-focus-tree-toggle]') as HTMLElement;
    const drawer = (): HTMLElement => document.querySelector('[data-wiki-sidebar]') as HTMLElement;

    it('opens the tree drawer on the tree\'s tab stop and closes it with the button, Escape or a click outside', () => {
        page();
        bindFocusTreeDrawer(document);
        bindFocusTreeDrawer(document);
        button().click();
        expect(button().getAttribute('aria-expanded')).toBe('true');
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(true);
        expect(document.activeElement?.textContent).toBe('B');
        // A click inside the drawer keeps it open; another key does nothing.
        (drawer().querySelector('a') as HTMLElement).dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'a', bubbles: true }));
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(true);
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(false);
        expect(button().getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(button());

        button().click();
        document.getElementById('elsewhere')!.click();
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(false);
        button().click();
        button().click();
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(false);
        // Closed: Escape and clicks elsewhere do nothing; a click on the document itself neither.
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        document.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(button().getAttribute('aria-expanded')).toBe('false');
    });

    it('1.44: one Escape closes only the drawer (consumed before UiKit focus mode); the next one reaches it', () => {
        page();
        bindFocusTreeDrawer(document);
        const reached: boolean[] = [];
        // UiKit's focus mode: a document listener that ignores a handled Escape.
        const focusMode = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                reached.push(event.defaultPrevented);
            }
        };
        document.addEventListener('keydown', focusMode);
        button().click();
        const item = drawer().querySelector('[tabindex="0"]') as HTMLElement;
        const first = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
        item.dispatchEvent(first);
        expect(first.defaultPrevented).toBe(true);
        expect(reached).toEqual([]);
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(false);
        expect(document.activeElement).toBe(button());
        button().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
        expect(reached).toEqual([false]);

        // Modified or composing Escape, and an Escape already handled, leave the drawer open.
        button().click();
        for (const init of [{ shiftKey: true }, { ctrlKey: true }, { altKey: true }, { metaKey: true }, { isComposing: true }]) {
            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true, ...init }));
        }
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(true);
        document.removeEventListener('keydown', focusMode);
    });

    it('1.44: an Escape that belongs to a field or a menu inside the drawer is left to it', () => {
        page();
        drawer().insertAdjacentHTML('beforeend', '<input id="rename"><div role="menu"><button type="button" id="menu-item">Rename</button></div>');
        bindFocusTreeDrawer(document);
        button().click();
        for (const id of ['rename', 'menu-item']) {
            const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
            document.getElementById(id)!.dispatchEvent(event);
            expect(event.defaultPrevented).toBe(false);
            expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(true);
        }
        // A field outside the drawer does not own it: the drawer closes.
        document.body.insertAdjacentHTML('beforeend', '<input id="outside">');
        document.getElementById('outside')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(false);
    });

    it('1.44: the close button and the backdrop close the drawer, focus back on the button', () => {
        document.body.innerHTML = '<button type="button" aria-expanded="false" data-wiki-focus-tree-toggle>Pages</button>'
            + '<div class="layout"><div class="aside"><aside data-wiki-sidebar id="nowo-wiki-pages"><header><button type="button" data-wiki-focus-drawer-close aria-controls="nowo-wiki-pages"><span>×</span></button></header>'
            + '<ul role="tree"><li><a href="/b" data-nowo-ui-tree-item tabindex="0">B</a></li></ul></aside></div></div>';
        bindFocusTreeDrawer(document);
        button().click();
        const shade = document.querySelector('[data-wiki-focus-backdrop]') as HTMLElement;
        expect(shade).not.toBeNull();
        expect(shade.nextElementSibling?.className).toBe('aside');
        expect(shade.getAttribute('aria-hidden')).toBe('true');
        (document.querySelector('[data-wiki-focus-drawer-close] span') as HTMLElement).click();
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(false);
        expect(document.querySelector('[data-wiki-focus-backdrop]')).toBeNull();
        expect(document.activeElement).toBe(button());

        button().click();
        (document.querySelector('[data-wiki-focus-backdrop]') as HTMLElement).click();
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(false);
        expect(document.querySelectorAll('[data-wiki-focus-backdrop]')).toHaveLength(0);
    });

    it('a tree without a tab stop opens without moving the focus', () => {
        page();
        drawer().querySelectorAll('a').forEach((a) => a.setAttribute('tabindex', '-1'));
        const doc = document;
        bindFocusTreeDrawer(doc);
        button().focus();
        button().click();
        expect(drawer().hasAttribute('data-wiki-drawer-open')).toBe(true);
        expect(document.activeElement).toBe(button());
    });

    it('a page without the tree card leaves the button alone', () => {
        page(false);
        const doc = document.implementation.createHTMLDocument('x');
        doc.body.innerHTML = document.body.innerHTML;
        bindFocusTreeDrawer(doc);
        (doc.querySelector('[data-wiki-focus-tree-toggle]') as HTMLElement).click();
        expect(doc.querySelector('[data-wiki-focus-tree-toggle]')!.getAttribute('aria-expanded')).toBe('false');
    });
});
