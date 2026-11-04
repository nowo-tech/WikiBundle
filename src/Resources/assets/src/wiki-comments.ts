/**
 * Comments panel glue (1.16, `comments.enabled`): ties the Milkdown 1.6 thread component (mounted into the panel with
 * `panel: 'container'`) to the UiKit side panel (`js/nowo-ui-side-panel.js`).
 *
 * - `nowo-milkdown:comments-count` `{open}` → the toggle's badge (`NowoUiSidePanel.setCount`; never announced);
 * - `nowo-milkdown:comments-open-requested` (a highlight, a new comment) → opens the panel if it is closed (the
 *   component then scrolls to the thread and focuses its first control);
 * - `nowo-ui:side-panel:open` / `close` of the panel → the open state is stored for the next page
 *   (`POST data-state-url`, `X-CSRF-Token: data-state-token`, `{panel: "comments", open}`);
 * - `nowo-milkdown:ready` of a comments editor → the no-JavaScript list is hidden, and on the read view the read-only
 *   editor replaces the server-rendered body.
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
    view.hidden = false;
    doc.querySelectorAll<HTMLElement>('[data-nowo-wiki-server-body]').forEach((body) => {
        body.hidden = true;
    });
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
        if (url) {
            void Promise.resolve()
                .then(() => statePoster(url, panel.dataset.stateToken ?? '', { panel: 'comments', open }))
                .catch(() => undefined);
        }
    };
    doc.addEventListener('nowo-ui:side-panel:open', onPanel(true));
    doc.addEventListener('nowo-ui:side-panel:close', onPanel(false));
}
