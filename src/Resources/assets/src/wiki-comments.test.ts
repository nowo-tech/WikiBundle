import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    bindWikiComments,
    DEDUPED_ATTR,
    hideDuplicateTitle,
    isDrawerLayout,
    normaliseTitle,
    openRequestedPanel,
    PANEL_ID,
    READ_SWAP_ATTR,
    setCommentsCount,
    setNavigator,
    setStatePoster,
    showCommentsEditor,
    swapReadView,
    type SidePanelApi,
} from './wiki-comments';

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

describe('wiki comments 1.29: requested panel and title dedupe', () => {
    beforeEach(() => {
        document.body.innerHTML = markup;
        bindWikiComments(document);
    });

    afterEach(() => {
        delete (window as PanelWindow).NowoUiSidePanel;
        document.body.innerHTML = '';
    });

    it('opens the panel asked for in the URL when it started closed (1.29)', () => {
        const api = { open: vi.fn(() => true), isOpen: vi.fn(() => false), setCount: vi.fn() };
        // Not asked for: nothing.
        (window as PanelWindow).NowoUiSidePanel = api;
        expect(openRequestedPanel(document)).toBe(false);

        const panel = document.getElementById(PANEL_ID) as HTMLElement;
        panel.setAttribute('data-nowo-wiki-comments-requested', '');
        expect(openRequestedPanel()).toBe(true);
        expect(api.open).toHaveBeenCalledWith(PANEL_ID);

        // Already open (docked on a wide screen): no second open.
        api.open.mockClear();
        api.isOpen.mockReturnValue(true);
        expect(openRequestedPanel(document)).toBe(false);
        expect(api.open).not.toHaveBeenCalled();

        // Without the UiKit script or without the panel.
        delete (window as PanelWindow).NowoUiSidePanel;
        expect(openRequestedPanel(document)).toBe(false);
        (window as PanelWindow).NowoUiSidePanel = api;
        panel.remove();
        expect(openRequestedPanel(document)).toBe(false);
    });

    it('hides the read-only editor heading that repeats the title (1.29)', () => {
        const view = document.getElementById('view') as HTMLElement;
        const root = document.getElementById('root') as HTMLElement;
        root.innerHTML = '<div class="ProseMirror" contenteditable="false"><h1> Deploy\n  RUNBOOK </h1><p>Steps</p></div>';
        const heading = root.querySelector('h1') as HTMLElement;

        // No title asked for: untouched.
        expect(hideDuplicateTitle(view)).toBe(false);
        expect(view.hasAttribute(DEDUPED_ATTR)).toBe(false);

        view.dataset.nowoWikiDedupeTitle = 'Deploy runbook';
        fire(root, 'nowo-milkdown:ready');
        // 1.30: the wrapper is marked (wiki.css hides the heading); the editor's own DOM is never changed, so a
        // ProseMirror redraw cannot bring the title back.
        expect(view.hasAttribute(DEDUPED_ATTR)).toBe(true);
        expect(heading.hidden).toBe(false);
        expect(heading.getAttributeNames()).toEqual([]);
        expect(view.hidden).toBe(false);
        // Redrawn by the editor (a new node): the marker still applies; a later non-matching body clears it.
        root.querySelector('.ProseMirror')!.innerHTML = '<h1>Deploy runbook</h1>';
        expect(hideDuplicateTitle(view)).toBe(true);
        root.querySelector('.ProseMirror')!.innerHTML = '<h1>Other</h1>';
        expect(hideDuplicateTitle(view)).toBe(false);
        expect(view.hasAttribute(DEDUPED_ATTR)).toBe(false);
        expect(normaliseTitle('  A \t B ')).toBe('a b');
    });

    it('keeps headings that differ, are not first or are not h1 (1.29)', () => {
        const view = document.getElementById('view') as HTMLElement;
        const root = document.getElementById('root') as HTMLElement;
        view.dataset.nowoWikiDedupeTitle = 'Deploy';
        for (const html of ['<h1>Deploy v2</h1>', '<h2>Deploy</h2>', '<p>x</p><h1>Deploy</h1>', '', '<h1>Deploy<img src="/x.png" alt=""></h1>']) {
            root.innerHTML = `<div class="ProseMirror">${html}</div>`;
            expect(hideDuplicateTitle(view)).toBe(false);
        }
        root.innerHTML = '';
        expect(hideDuplicateTitle(view)).toBe(false);
        root.innerHTML = '<div class="ProseMirror"><h1></h1></div>';
        view.dataset.nowoWikiDedupeTitle = '';
        expect(hideDuplicateTitle(view)).toBe(false);
    });

    it('stores the state only while the panel is a column, never from the drawer (1.29)', async () => {
        const posted: unknown[] = [];
        setStatePoster(async (_url, _token, body) => {
            posted.push(body);
        });
        const panel = document.getElementById(PANEL_ID) as HTMLElement;
        panel.dataset.drawerBelow = 'lg';
        let width = 1024;
        const queries: string[] = [];
        const matchMedia = vi.fn((query: string) => {
            queries.push(query);
            const max = Number(/(\d+)px/.exec(query)?.[1]);

            return { matches: width <= max } as MediaQueryList;
        });
        Object.defineProperty(window, 'matchMedia', { configurable: true, writable: true, value: matchMedia });

        // 1024 px with an lg dock: the drawer — opening and closing it stores nothing.
        fire(panel, 'nowo-ui:side-panel:open', { id: PANEL_ID });
        fire(panel, 'nowo-ui:side-panel:close', { id: PANEL_ID });
        await flush();
        expect(posted).toEqual([]);
        expect(queries).toContain('(max-width: 1199px)');

        // 1280 px: a column — stored.
        width = 1280;
        fire(panel, 'nowo-ui:side-panel:open', { id: PANEL_ID });
        await flush();
        expect(posted).toEqual([{ panel: 'comments', open: true }]);

        // xl and the md default; an unknown value reads as md; an overlay layout is always a drawer.
        panel.dataset.drawerBelow = 'xl';
        expect(isDrawerLayout(panel)).toBe(true);
        delete panel.dataset.drawerBelow;
        expect(isDrawerLayout(panel)).toBe(false);
        width = 700;
        panel.dataset.drawerBelow = 'huge';
        expect(isDrawerLayout(panel)).toBe(true);
        panel.dataset.layout = 'overlay';
        width = 2000;
        expect(isDrawerLayout(panel)).toBe(true);
        delete panel.dataset.layout;

        // No layout engine (no matchMedia, or no window): a column.
        delete (window as { matchMedia?: unknown }).matchMedia;
        expect(isDrawerLayout(panel)).toBe(false);
        expect(isDrawerLayout(panel, null)).toBe(false);
    });
});

describe('wiki comments 1.30: seamless read swap', () => {
    const swapMarkup = `
        <div class="nowo-wiki-read-swap" data-nowo-wiki-read-swap="pending" id="swap">
            <div data-nowo-wiki-comments-view data-nowo-wiki-dedupe-title="Deploy" data-nowo-wiki-title-deduped id="view"><div class="nowo-milkdown" id="root"></div></div>
            <article data-nowo-wiki-server-body id="server"><a href="/x">Body</a></article>
        </div>
        <div data-nowo-wiki-server-body id="outside">Elsewhere</div>
        <aside id="${PANEL_ID}"><div data-nowo-wiki-comments-fallback id="fallback"></div></aside>`;

    beforeEach(() => {
        document.body.innerHTML = swapMarkup;
        bindWikiComments(document);
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('keeps both in the same box and marks it ready when the editor is ready, never hiding either (1.30)', () => {
        const swap = document.getElementById('swap') as HTMLElement;
        const view = document.getElementById('view') as HTMLElement;
        const server = document.getElementById('server') as HTMLElement;
        const root = document.getElementById('root') as HTMLElement;
        // Before: the server body shows, the editor is laid out invisibly (CSS), the heading already deduped (server).
        expect(swap.getAttribute(READ_SWAP_ATTR)).toBe('pending');
        expect(view.hasAttribute(DEDUPED_ATTR)).toBe(true);

        root.innerHTML = '<div class="ProseMirror" contenteditable="false"><h1>Deploy</h1><p>Steps</p></div>';
        fire(root, 'nowo-milkdown:ready');

        expect(swap.getAttribute(READ_SWAP_ATTR)).toBe('ready');
        // No `hidden` on either: the cell keeps its size (no reflow); the body leaves the tab order and the a11y tree.
        expect(view.hidden).toBe(false);
        expect(server.hidden).toBe(false);
        expect(server.hasAttribute('inert')).toBe(true);
        expect(server.getAttribute('aria-hidden')).toBe('true');
        // The dedupe stays (the editor's heading equals the title); a body outside the box is left alone.
        expect(view.hasAttribute(DEDUPED_ATTR)).toBe(true);
        const outside = document.getElementById('outside') as HTMLElement;
        expect(outside.hidden).toBe(false);
        expect(outside.hasAttribute('inert')).toBe(false);
        expect((document.getElementById('fallback') as HTMLElement).hidden).toBe(true);

        // Once only.
        expect(swapReadView(swap)).toBe(false);
    });

    it('drops a server dedupe mark that the editor does not confirm (1.30)', () => {
        const view = document.getElementById('view') as HTMLElement;
        const root = document.getElementById('root') as HTMLElement;
        root.innerHTML = '<div class="ProseMirror"><h1>Other</h1></div>';
        fire(root, 'nowo-milkdown:ready');
        expect(view.hasAttribute(DEDUPED_ATTR)).toBe(false);
    });
});
