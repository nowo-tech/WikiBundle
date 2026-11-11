/**
 * Comments panel glue (1.16, `comments.enabled`): ties the Milkdown 1.6 thread component (mounted into the panel with
 * `panel: 'container'`) to the UiKit side panel (`js/nowo-ui-side-panel.js`).
 *
 * - `nowo-milkdown:comments-count` `{open}` → the toggle's badge (`NowoUiSidePanel.setCount`; never announced);
 * - `nowo-milkdown:comments-open-requested` (a highlight, a new comment) → opens the panel if it is closed (the
 *   component then scrolls to the thread and focuses its first control);
 * - `nowo-ui:side-panel:open` / `close` of the panel → the open state is stored for the next page
 *   (`POST data-state-url`, `X-CSRF-Token: data-state-token`, `{panel: "comments", open}`);
 * - opening a closed panel whose threads are rendered by the server (`data-reload-href`, readers without the page
 *   source): the page is loaded again with `?comments=1`, so the list is only read when someone opens it;
 * - `nowo-milkdown:ready` of a comments editor → the no-JavaScript list is hidden, and on the read view the read-only
 *   editor replaces the server-rendered body (1.29: without its first `<h1>` when that repeats the page title,
 *   `data-nowo-wiki-dedupe-title`);
 * - 1.29: a panel asked for in the URL (`?comments=1`, `data-nowo-wiki-comments-requested`) that started closed (a
 *   server-open `lg` / `xl` panel under its breakpoint, UiKit LV-L2) is opened as a drawer after load
 *   ({@link openRequestedPanel}).
 */

/** Id of the comments panel (WikiCommentsPanel::ID). */
export const PANEL_ID = 'nowo-wiki-comments';

/** The part of `window.NowoUiSidePanel` (UiKit >= 2.25) the glue uses. */
export interface SidePanelApi {
    open(id: string, options?: { focus?: string | HTMLElement }): boolean;
    isOpen(id: string): boolean;
    setCount(id: string, count: number, suffix?: string): void;
}

/** POST of the panel state (tests and hosts may replace it). */
export type StatePoster = (url: string, token: string, body: { panel: string; open: boolean }) => Promise<unknown>;

const postState: StatePoster = (url, token, body) =>
    fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify(body),
    });

let statePoster: StatePoster = postState;

/** Page navigation (tests replace it). */
export type Navigator = (url: string) => void;

let navigate: Navigator = (url) => {
    window.location.href = url;
};

/**
 * Replaces the navigation used to load a server-rendered comments list (tests).
 *
 * @param next - The navigation.
 */
export function setNavigator(next: Navigator): void {
    navigate = next;
}

/**
 * Replaces the transport of the panel state (tests, hosts with their own transport).
 *
 * @param next - The transport.
 */
export function setStatePoster(next: StatePoster): void {
    statePoster = next;
}

type PanelWindow = Window & { NowoUiSidePanel?: SidePanelApi };

const sidePanel = (doc: Document): SidePanelApi | undefined => (doc.defaultView as PanelWindow | null)?.NowoUiSidePanel;

/**
 * Sets the badge of the comments toggle: through the UiKit API when it is loaded, else on the markup itself (the same
 * attributes), so a missing script never leaves a stale count.
 *
 * @param doc - The document.
 * @param count - Open threads.
 */
export function setCommentsCount(doc: Document, count: number): void {
    const open = Number.isInteger(count) && count > 0 ? count : 0;
    const api = sidePanel(doc);
    if (api && doc.getElementById(PANEL_ID)) {
        api.setCount(PANEL_ID, open);

        return;
    }
    doc.querySelectorAll<HTMLElement>(`[aria-controls="${PANEL_ID}"]`).forEach((toggle) => {
        const badge = toggle.querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge]');
        if (badge) {
            badge.textContent = open > 0 ? String(open) : '';
            badge.hidden = open === 0;
        }
        const suffix = toggle.querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge-suffix]');
        if (suffix) {
            suffix.hidden = open === 0;
        }
    });
}

/**
 * Shows the editor once it is ready: hides the no-JavaScript list of the panel and, when the editor is the read-only
 * view of the page, swaps it in for the server-rendered body.
 *
 * @param doc - The document.
 * @param root - The editor root that became ready.
 */
export function showCommentsEditor(doc: Document, root: Element): void {
    doc.querySelectorAll<HTMLElement>('[data-nowo-wiki-comments-fallback]').forEach((fallback) => {
        fallback.hidden = true;
    });
    const view = root.closest<HTMLElement>('[data-nowo-wiki-comments-view]');
    if (!view) {
        return;
    }
    hideDuplicateTitle(view);
    view.hidden = false;
    doc.querySelectorAll<HTMLElement>('[data-nowo-wiki-server-body]').forEach((body) => {
        body.hidden = true;
    });
}

/**
 * Trimmed, whitespace collapsed, lower case (as `WikiTitleDedupe::normalise()`).
 *
 * @param text - The text.
 */
export function normaliseTitle(text: string): string {
    return text.replace(/\s+/gu, ' ').trim().toLowerCase();
}

/**
 * 1.29 (`web_ui.page_header.title: dedupe`): hides the read-only editor's first block when it is an `<h1>` whose text
 * equals the page title (`data-nowo-wiki-dedupe-title`). Only the DOM node is hidden: the editor document, and so the
 * comment anchors, stay as they are.
 *
 * @param view - The read-only view (`[data-nowo-wiki-comments-view]`).
 * @returns Whether a heading was hidden.
 */
export function hideDuplicateTitle(view: HTMLElement): boolean {
    const title = normaliseTitle(view.dataset.nowoWikiDedupeTitle ?? '');
    const editor = view.querySelector<HTMLElement>('[contenteditable]') ?? view.querySelector<HTMLElement>('.ProseMirror');
    const first = editor?.firstElementChild;
    if (title === '' || !(first instanceof HTMLHeadingElement) || first.tagName !== 'H1' || normaliseTitle(`${first.textContent}`) !== title) {
        return false;
    }
    first.hidden = true;

    return true;
}

/**
 * 1.29: opens the comments panel asked for in the URL (`?comments=1` → `data-nowo-wiki-comments-requested`) when it is
 * still closed after load: a server-open `lg` / `xl` panel starts closed under its breakpoint (UiKit 2.48 LV-L2), so a
 * no-JavaScript link or the reload of a server-rendered list would otherwise leave it shut. No-op without the UiKit
 * script, without the request, or when the panel is already open.
 *
 * @param doc - The document.
 * @returns Whether the panel was opened.
 */
export function openRequestedPanel(doc: Document = document): boolean {
    const api = sidePanel(doc);
    const panel = doc.getElementById(PANEL_ID);
    if (!api || !panel || !panel.hasAttribute('data-nowo-wiki-comments-requested') || api.isOpen(PANEL_ID)) {
        return false;
    }

    return api.open(PANEL_ID);
}

const boundDocs = new WeakSet<Document>();

/**
 * Binds the glue once per document (no-op without a comments panel when the events fire).
 *
 * @param doc - The document.
 */
export function bindWikiComments(doc: Document = document): void {
    if (boundDocs.has(doc)) {
        return;
    }
    boundDocs.add(doc);

    doc.addEventListener('nowo-milkdown:comments-count', (event) => {
        const detail = (event as CustomEvent<{ open?: unknown }>).detail;
        if (doc.getElementById(PANEL_ID)) {
            setCommentsCount(doc, typeof detail?.open === 'number' ? detail.open : 0);
        }
    });

    doc.addEventListener('nowo-milkdown:comments-open-requested', () => {
        const api = sidePanel(doc);
        if (api && doc.getElementById(PANEL_ID) && !api.isOpen(PANEL_ID)) {
            api.open(PANEL_ID);
        }
    });

    doc.addEventListener('nowo-milkdown:ready', (event) => {
        const root = event.target;
        if (root instanceof Element && doc.getElementById(PANEL_ID)) {
            showCommentsEditor(doc, root);
        }
    });

    const onPanel = (open: boolean) => (event: Event): void => {
        const panel = event.target;
        if (!(panel instanceof HTMLElement) || panel.id !== PANEL_ID) {
            return;
        }
        const url = panel.dataset.stateUrl;
        const reload = open ? panel.dataset.reloadHref : undefined;
        if (reload) {
            navigate(reload);
        }
        if (url) {
            void Promise.resolve()
                .then(() => statePoster(url, panel.dataset.stateToken ?? '', { panel: 'comments', open }))
                .catch(() => undefined);
        }
    };
    doc.addEventListener('nowo-ui:side-panel:open', onPanel(true));
    doc.addEventListener('nowo-ui:side-panel:close', onPanel(false));
}
