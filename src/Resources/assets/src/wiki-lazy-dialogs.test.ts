import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindNewPageMenu } from './wiki-new-page';
import { bindTreeMenu, loadDialogForm } from './wiki-tree-menu';

/**
 * 1.53 (lazy dialogs): the page holds no dialog form. "Change icon" and "Move to…" load theirs from the dialog's
 * `data-wiki-tree-*-src` with X-Nowo-Dialog: 1 when they open; the new-page choices open the UiKit remote dialog with
 * the place in the URL (or the form's page without the UiKit dialog script).
 */

type Answer = { status: number; type?: string | null; text?: string; json?: unknown } | 'reject';

/** fetch answering the given answers in turn (the last one again after that). */
function fetcher(...answers: Answer[]) {
    let call = 0;

    return vi.fn<typeof fetch>(async () => {
        const answer = answers[Math.min(call++, answers.length - 1)];
        if (answer === 'reject') {
            throw new Error('network');
        }

        return {
            status: answer.status,
            headers: new Headers(answer.type === null ? {} : { 'content-type': answer.type ?? 'application/json' }),
            text: async () => answer.text ?? '',
            json: async () => answer.json,
        } as unknown as Response;
    });
}

const html = (text: string, status = 200): Answer => ({ status, type: 'text/html; charset=UTF-8', text });
const json = (data: unknown, status = 200): Answer => ({ status, json: data });

const ROW_MARKS = ' data-wiki-menu-edit="1" data-wiki-menu-icon="1" data-wiki-icon="bi:house" data-wiki-menu-move="1" data-wiki-new-child="1" data-wiki-new-sibling="1"';

function setup(extra: string): void {
    const rows = ['a', 'b'].map((id) => `<li role="none" data-nowo-ui-tree-node="${id}"${ROW_MARKS}><div class="nowo-ui-tree__row">`
        + `<a href="http://localhost/p/${id}" role="treeitem" data-nowo-ui-tree-item tabindex="-1"><span class="nowo-ui-tree__label">${id.toUpperCase()}</span></a></div></li>`).join('');
    const menu = '<div role="menu" hidden data-wiki-tree-menu data-failed="Failed">'
        + ['icon', 'child', 'below', 'moveto'].map((i) => `<button type="button" role="menuitem" tabindex="-1" hidden data-wiki-tree-menu-item="${i}">${i}</button>`).join('')
        + '</div><p role="status" data-wiki-tree-menu-status></p>';
    document.body.innerHTML = `<ul role="tree" data-wiki-tree data-wiki-tree-token="tok" data-wiki-tree-icon="/t/icon" data-wiki-tree-parents="/t/parents" data-wiki-tree-move="/t/move">${rows}</ul>${menu}${extra}`;
    for (const d of document.querySelectorAll('dialog')) {
        (d as HTMLDialogElement).showModal = function showModal(this: HTMLDialogElement) { this.setAttribute('open', ''); };
        (d as HTMLDialogElement).close = function close(this: HTMLDialogElement) { this.removeAttribute('open'); this.dispatchEvent(new Event('close')); };
    }
}

const ICON = '<dialog data-wiki-tree-icon-dialog data-wiki-tree-icon-src="/s?dialog=tree-icon"><p data-wiki-tree-icon-context data-template="Page “%title%”"></p>'
    + '<div data-wiki-tree-icon-slot></div><p hidden data-wiki-tree-icon-error></p></dialog>';
const MOVE = '<dialog data-wiki-tree-move-dialog data-wiki-tree-move-src="/s?dialog=tree-move"><p data-wiki-tree-move-context data-template="Page “%title%”" data-root="Top level"></p>'
    + '<div data-wiki-tree-move-slot></div></dialog>';
const NEW = '<dialog id="nowo-wiki-new-page-dialog" data-wiki-new-page-dialog data-wiki-new-page-url="/new"></dialog>';

const link = (id: string): HTMLAnchorElement => document.querySelector(`[data-nowo-ui-tree-node="${id}"] [data-nowo-ui-tree-item]`) as HTMLAnchorElement;
const choose = (id: string, name: string): void => {
    link(id).dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 1, clientY: 1 }));
    (document.querySelector(`[data-wiki-tree-menu-item="${name}"]`) as HTMLElement).click();
};
const status = (): string => `${document.querySelector('[data-wiki-tree-menu-status]')?.textContent}`;
const flush = async (): Promise<void> => {
    for (let i = 0; i < 6; ++i) {
        await new Promise((r) => setTimeout(r, 0));
    }
};

let location: { assign: ReturnType<typeof vi.fn>; reload: ReturnType<typeof vi.fn> };

beforeEach(() => {
    location = { assign: vi.fn(), reload: vi.fn() };
    vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, ...location } as unknown as Location);
});

afterEach(() => {
    vi.restoreAllMocks();
    delete (window as { nowoUiOpenRemoteForm?: unknown }).nowoUiOpenRemoteForm;
});

describe('loadDialogForm', () => {
    it('asks for the fragment and parses it inert, without scripts', async () => {
        const f = fetcher(html('<form id="x"><input name="form[icon]"></form><script>alert(1)</script>'));
        const fragment = await loadDialogForm('/s?dialog=tree-icon', f);
        expect(fragment.querySelector('form#x')).not.toBeNull();
        expect(fragment.querySelector('script')).toBeNull();
        const init = f.mock.calls[0][1] as RequestInit;
        expect((init.headers as Record<string, string>)['X-Nowo-Dialog']).toBe('1');
        expect(init.credentials).toBe('same-origin');
    });

    it('rejects a refusal, a whole page and another type', async () => {
        await expect(loadDialogForm('/x', fetcher(html('Denied', 403)))).rejects.toThrow('HTTP 403');
        await expect(loadDialogForm('/x', fetcher(html('<!DOCTYPE html><html><body>page</body></html>')))).rejects.toThrow();
        await expect(loadDialogForm('/x', fetcher(json({})))).rejects.toThrow();
        await expect(loadDialogForm('/x', fetcher(html('')))).rejects.toThrow();
        await expect(loadDialogForm('/x', fetcher({ status: 200, type: null, text: '<form></form>' }))).rejects.toThrow();
    });
});

describe('Change icon, loaded on open', () => {
    it('loads the form into the dialog, then saves through the tree endpoint and empties it on close', async () => {
        setup(ICON);
        const f = fetcher(html('<form id="nowo-wiki-tree-icon-form"><input type="text" name="form[icon]"></form>'), json({ icon: 'bi:star', icon_html: '<span class="nowo-wiki-page-icon">*</span>', message: 'Saved' }));
        bindTreeMenu(document, f);
        const dialog = document.querySelector('[data-wiki-tree-icon-dialog]') as HTMLDialogElement;
        expect(dialog.querySelector('form')).toBeNull(); // nothing before it opens
        choose('a', 'icon');
        await flush();
        expect(String(f.mock.calls[0][0])).toBe('/s?dialog=tree-icon');
        expect(dialog.hasAttribute('open')).toBe(true);
        const field = dialog.querySelector('input[name="form[icon]"]') as HTMLInputElement;
        expect(field.value).toBe('bi:house');
        expect(dialog.querySelector('[data-wiki-tree-icon-context]')!.textContent).toBe('Page “A”');
        field.value = 'bi:star';
        dialog.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();
        expect(String(f.mock.calls[1][0])).toBe('/t/icon');
        expect(dialog.hasAttribute('open')).toBe(false);
        expect(dialog.querySelector('form')).toBeNull();
        expect(status()).toBe('Saved');
    });

    it('a form that does not load is said, and no dialog opens', async () => {
        setup(ICON);
        bindTreeMenu(document, fetcher(html('Denied', 403)));
        choose('a', 'icon');
        await flush();
        expect((document.querySelector('[data-wiki-tree-icon-dialog]') as HTMLDialogElement).hasAttribute('open')).toBe(false);
        expect(status()).toBe('Failed');
    });
});

describe('Move to…, loaded on open', () => {
    it('loads the form, then the parents, and moves through the endpoint', async () => {
        setup(MOVE);
        const f = fetcher(
            html('<form id="nowo-wiki-tree-move-form"><select name="form[parent]"></select></form>'),
            json({ parents: [{ id: 'b', label: 'B' }], root: true, parent: null }),
            json({ index: 0, count: 1, moved: true, message: 'Moved' }),
        );
        bindTreeMenu(document, f);
        const dialog = document.querySelector('[data-wiki-tree-move-dialog]') as HTMLDialogElement;
        expect(dialog.querySelector('form')).toBeNull();
        choose('a', 'moveto');
        await flush();
        expect(String(f.mock.calls[0][0])).toBe('/s?dialog=tree-move');
        expect(String(f.mock.calls[1][0])).toBe('/t/parents');
        expect(dialog.hasAttribute('open')).toBe(true);
        const select = dialog.querySelector('select') as HTMLSelectElement;
        expect([...select.options].map((o) => o.value)).toEqual(['', 'b']);
        select.value = 'b';
        dialog.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
        expect(dialog.hasAttribute('open')).toBe(false);
        expect(dialog.querySelector('form')).toBeNull(); // emptied on close
    });

    it('a form that does not load is said', async () => {
        setup(MOVE);
        bindTreeMenu(document, fetcher('reject'));
        choose('a', 'moveto');
        await flush();
        expect(status()).toBe('Failed');
    });
});

describe('New page, remote dialog', () => {
    it('the tree menu opens the UiKit remote dialog with the place', () => {
        setup(NEW);
        const open = vi.fn(() => true);
        (window as { nowoUiOpenRemoteForm?: unknown }).nowoUiOpenRemoteForm = open;
        bindTreeMenu(document, fetcher(json({})));
        choose('b', 'below');
        expect(open).toHaveBeenCalledWith('nowo-wiki-new-page-dialog', '/new?place=below%3Ab', link('b'));
        expect(location.assign).not.toHaveBeenCalled();
    });

    it('without the UiKit dialog script the tree menu opens the form page', () => {
        setup(NEW);
        bindTreeMenu(document, fetcher(json({})));
        choose('a', 'child');
        expect(location.assign).toHaveBeenCalledWith('/new?place=child%3Aa');
    });

    const legacyMenu = (): void => {
        document.body.innerHTML = '<ul role="tree" data-wiki-tree><li data-nowo-ui-tree-node="a" data-wiki-new-child="1"><div class="nowo-ui-tree__row">'
            + '<a href="/a" data-nowo-ui-tree-item><span class="nowo-ui-tree__label">A</span></a><button type="button" data-wiki-new-page-more>…</button></div></li></ul>'
            + '<div role="menu" hidden data-wiki-new-page-menu><button type="button" data-wiki-new-page-choice="child">child</button></div>' + NEW;
    };

    it('the 1.35 new-page menu does the same', () => {
        legacyMenu();
        const open = vi.fn(() => false);
        (window as { nowoUiOpenRemoteForm?: unknown }).nowoUiOpenRemoteForm = open;
        bindNewPageMenu(document);
        (document.querySelector('[data-wiki-new-page-more]') as HTMLElement).click();
        (document.querySelector('[data-wiki-new-page-choice="child"]') as HTMLElement).click();
        expect(open).toHaveBeenCalledWith('nowo-wiki-new-page-dialog', '/new?place=child%3Aa', document.querySelector('[data-nowo-ui-tree-item]'));
        expect(location.assign).toHaveBeenCalledWith('/new?place=child%3Aa'); // the remote dialog could not open
    });

    it('the 1.35 new-page menu opens the form page without the UiKit dialog script', () => {
        legacyMenu();
        bindNewPageMenu(document);
        (document.querySelector('[data-wiki-new-page-more]') as HTMLElement).click();
        (document.querySelector('[data-wiki-new-page-choice="child"]') as HTMLElement).click();
        expect(location.assign).toHaveBeenCalledWith('/new?place=child%3Aa');
    });

    it('the 1.35 new-page menu stays on the page once the remote dialog opened', () => {
        legacyMenu();
        (window as { nowoUiOpenRemoteForm?: unknown }).nowoUiOpenRemoteForm = vi.fn(() => true);
        bindNewPageMenu(document);
        (document.querySelector('[data-wiki-new-page-more]') as HTMLElement).click();
        (document.querySelector('[data-wiki-new-page-choice="child"]') as HTMLElement).click();
        expect(location.assign).not.toHaveBeenCalled();
    });
});
