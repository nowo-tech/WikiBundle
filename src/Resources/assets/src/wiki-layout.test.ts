import { afterEach, describe, expect, it, vi } from 'vitest';
import { bindTreeHiddenFocus, observeCardToolbar, TREE_ID } from './wiki-layout';

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
