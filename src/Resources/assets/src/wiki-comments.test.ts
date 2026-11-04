import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindWikiComments, PANEL_ID, setCommentsCount, setNavigator, setStatePoster, showCommentsEditor, type SidePanelApi } from './wiki-comments';

type PanelWindow = Window & { NowoUiSidePanel?: SidePanelApi };

const markup = `
    <div data-nowo-wiki-server-body id="server">Body</div>
    <div data-nowo-wiki-comments-view hidden id="view"><div class="nowo-milkdown" id="root"></div></div>
    <a aria-controls="${PANEL_ID}" id="toggle"><span data-nowo-ui-side-panel-badge hidden></span><span data-nowo-ui-side-panel-badge-suffix hidden> open</span></a>
    <aside id="${PANEL_ID}" data-state-url="/state" data-state-token="tok" hidden>
        <div data-nowo-wiki-comments-mount></div>
        <div data-nowo-wiki-comments-fallback id="fallback"></div>
    </aside>
`;

const fire = (target: EventTarget, name: string, detail?: unknown): void => {
    target.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
};

const flush = async (): Promise<void> => {
    await Promise.resolve();
    await Promise.resolve();
};

describe('wiki comments glue', () => {
    let posted: Array<{ url: string; token: string; body: unknown }>;

    beforeEach(() => {
        document.body.innerHTML = markup;
        posted = [];
        setStatePoster(async (url, token, body) => {
            posted.push({ url, token, body });
        });
        bindWikiComments(document);
        bindWikiComments(document);
    });

    afterEach(() => {
        delete (window as PanelWindow).NowoUiSidePanel;
        document.body.innerHTML = '';
    });

    it('updates the badge from the component count, through the UiKit API when loaded', () => {
        const api = { open: vi.fn(), isOpen: vi.fn(), setCount: vi.fn() };
        (window as PanelWindow).NowoUiSidePanel = api;

        fire(document.getElementById('root') as HTMLElement, 'nowo-milkdown:comments-count', { open: 3, resolved: 1 });

        expect(api.setCount).toHaveBeenCalledWith(PANEL_ID, 3);
    });

    it('updates the badge markup itself without the UiKit script', () => {
        const badge = document.querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge]') as HTMLElement;
        const suffix = document.querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge-suffix]') as HTMLElement;

        fire(document, 'nowo-milkdown:comments-count', { open: 2 });
        expect(badge.textContent).toBe('2');
        expect(badge.hidden).toBe(false);
        expect(suffix.hidden).toBe(false);

        fire(document, 'nowo-milkdown:comments-count', { open: 'x' });
        expect(badge.textContent).toBe('');
        expect(badge.hidden).toBe(true);
        expect(suffix.hidden).toBe(true);

        fire(document, 'nowo-milkdown:comments-count');
        expect(badge.hidden).toBe(true);
    });

    it('tolerates toggles without a badge and ignores counts without a panel', () => {
        document.body.innerHTML = `<a aria-controls="${PANEL_ID}"></a>`;
        expect(() => setCommentsCount(document, -1)).not.toThrow();

        const api = { open: vi.fn(), isOpen: vi.fn(), setCount: vi.fn() };
        (window as PanelWindow).NowoUiSidePanel = api;
        fire(document, 'nowo-milkdown:comments-count', { open: 1 });
        expect(api.setCount).not.toHaveBeenCalled();
    });

    it('opens the closed panel when the component asks for it', () => {
        const api = { open: vi.fn(), isOpen: vi.fn().mockReturnValueOnce(false).mockReturnValueOnce(true), setCount: vi.fn() };
        (window as PanelWindow).NowoUiSidePanel = api;

        fire(document, 'nowo-milkdown:comments-open-requested', { id: 't1' });
        fire(document, 'nowo-milkdown:comments-open-requested', { id: null });

        expect(api.open).toHaveBeenCalledTimes(1);
        expect(api.open).toHaveBeenCalledWith(PANEL_ID);
    });

    it('does not open anything without the UiKit script or without the panel', () => {
        expect(() => fire(document, 'nowo-milkdown:comments-open-requested', {})).not.toThrow();

        const api = { open: vi.fn(), isOpen: vi.fn().mockReturnValue(false), setCount: vi.fn() };
        (window as PanelWindow).NowoUiSidePanel = api;
        document.getElementById(PANEL_ID)?.remove();
        fire(document, 'nowo-milkdown:comments-open-requested', {});
        expect(api.open).not.toHaveBeenCalled();
    });

    it('swaps the read-only editor in for the server body once it is ready', () => {
        fire(document.getElementById('root') as HTMLElement, 'nowo-milkdown:ready');

        expect((document.getElementById('view') as HTMLElement).hidden).toBe(false);
        expect((document.getElementById('server') as HTMLElement).hidden).toBe(true);
        expect((document.getElementById('fallback') as HTMLElement).hidden).toBe(true);
    });

    it('only hides the fallback for the edit form editor, and ignores editors without a panel', () => {
        const root = document.createElement('div');
        document.body.append(root);
        showCommentsEditor(document, root);
        expect((document.getElementById('fallback') as HTMLElement).hidden).toBe(true);
        expect((document.getElementById('view') as HTMLElement).hidden).toBe(true);

        document.getElementById(PANEL_ID)?.remove();
        (document.getElementById('server') as HTMLElement).hidden = false;
        fire(document.getElementById('root') as HTMLElement, 'nowo-milkdown:ready');
        expect((document.getElementById('server') as HTMLElement).hidden).toBe(false);
        expect(() => document.dispatchEvent(new CustomEvent('nowo-milkdown:ready'))).not.toThrow();
    });

    it('stores the open state of the comments panel only', async () => {
        const panel = document.getElementById(PANEL_ID) as HTMLElement;
        fire(panel, 'nowo-ui:side-panel:open', { id: PANEL_ID });
        fire(panel, 'nowo-ui:side-panel:close', { id: PANEL_ID });
        await flush();

        expect(posted).toEqual([
            { url: '/state', token: 'tok', body: { panel: 'comments', open: true } },
            { url: '/state', token: 'tok', body: { panel: 'comments', open: false } },
        ]);

        const other = document.createElement('aside');
        other.id = 'other';
        document.body.append(other);
        fire(other, 'nowo-ui:side-panel:open', { id: 'other' });
        document.dispatchEvent(new CustomEvent('nowo-ui:side-panel:open'));
        delete panel.dataset.stateUrl;
        fire(panel, 'nowo-ui:side-panel:open', { id: PANEL_ID });
        panel.dataset.stateUrl = '/state';
        delete panel.dataset.stateToken;
        fire(panel, 'nowo-ui:side-panel:close', { id: PANEL_ID });
        await flush();

        expect(posted).toHaveLength(3);
        expect(posted[2]).toEqual({ url: '/state', token: '', body: { panel: 'comments', open: false } });
    });

    it('loads the server-rendered list when a lazy panel is opened in place', async () => {
        const visited: string[] = [];
        setNavigator((url) => visited.push(url));
        const panel = document.getElementById(PANEL_ID) as HTMLElement;
        panel.dataset.reloadHref = '/p?comments=1';
        fire(panel, 'nowo-ui:side-panel:close', { id: PANEL_ID });
        expect(visited).toEqual([]);
        fire(panel, 'nowo-ui:side-panel:open', { id: PANEL_ID });
        expect(visited).toEqual(['/p?comments=1']);
        await flush();
    });

    it('swallows a failed state request', async () => {
        setStatePoster(() => Promise.reject(new Error('offline')));
        fire(document.getElementById(PANEL_ID) as HTMLElement, 'nowo-ui:side-panel:open', { id: PANEL_ID });
        await flush();
        expect(posted).toHaveLength(0);
    });
});

describe('wiki comments default transport', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    it('navigates with window.location by default', async () => {
        vi.resetModules();
        const mod = await import('./wiki-comments');
        const doc = document.implementation.createHTMLDocument('x');
        Object.defineProperty(doc, 'defaultView', { value: window });
        doc.body.innerHTML = `<aside id="${mod.PANEL_ID}" data-reload-href="#comments-reload"></aside>`;
        mod.setStatePoster(async () => undefined);
        mod.bindWikiComments(doc);
        fire(doc.getElementById(mod.PANEL_ID) as HTMLElement, 'nowo-ui:side-panel:open', { id: mod.PANEL_ID });
        expect(window.location.hash).toBe('#comments-reload');
    });

    it('posts the state as same-origin JSON with the CSRF header', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }));
        vi.stubGlobal('fetch', fetchMock);
        vi.resetModules();
        const mod = await import('./wiki-comments');
        const doc = document.implementation.createHTMLDocument('x');
        Object.defineProperty(doc, 'defaultView', { value: window });
        doc.body.innerHTML = `<aside id="${mod.PANEL_ID}" data-state-url="/s" data-state-token="t"></aside>`;
        mod.bindWikiComments(doc);

        fire(doc.getElementById(mod.PANEL_ID) as HTMLElement, 'nowo-ui:side-panel:open', { id: mod.PANEL_ID });
        await flush();

        expect(fetchMock).toHaveBeenCalledWith('/s', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': 't' },
            body: JSON.stringify({ panel: 'comments', open: true }),
        });
    });
});
