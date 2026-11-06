/**
 * Comments panel extras (1.21), each rendered by the server only when its option is on:
 *
 * - `comments.error_notices` (`[data-nowo-wiki-comments-notice]`): a comment write the server refuses for a cap
 *   (422 `limit_reached`) or the rate limit (429) says so — the Milkdown thread component only announces "could not be
 *   saved" (`nowo-milkdown:comment-error` carries the HTTP status and bubbles to the document). The text is a UiKit
 *   toast when the UiKit toast script is loaded (`window.nowoUiDismissToast`; the toast markup is the server-rendered
 *   `<template data-nowo-wiki-comments-toast>`), else it is written into the notice (a polite live region).
 * - `comments.moderation_panel` (`[data-nowo-wiki-moderation]`, users with ModerateComments only): *Moderate threads*
 *   reads every thread of the page when it is first opened (`GET …/comments/threads`) and lists them, each with
 *   *Delete thread* behind the confirm dialog (`<dialog data-nowo-wiki-moderation-confirm>`), which calls the
 *   moderation endpoint (`DELETE …/comments/threads/{id}`, header `X-CSRF-Token`). The thread component drops a
 *   deleted thread on its next refresh (live with co-editing).
 *
 * Every text is set with textContent (thread quotes are user content); no inline script or style (strict CSP).
 */

/** A thread as `GET …/comments/threads` lists it (the part the list uses). */
export interface ModerationThread {
    id: string;
    anchor: { quote?: { exact?: string } } | null;
    resolved: boolean;
    count: number;
}

/** Labels of the moderation list (`data-labels`, JSON). */
export interface ModerationLabels {
    page_comment: string;
    resolved: string;
    count: string;
    delete: string;
    deleted: string;
    failed: string;
    empty: string;
    rate_limited: string;
    confirm: string;
}

/** HTTP transport of the moderation list (tests and hosts may replace it). */
export type ModerationFetch = (url: string, init: RequestInit) => Promise<Response>;

let transport: ModerationFetch = (url, init) => fetch(url, init);

/**
 * Replaces the HTTP transport (tests, hosts with their own transport).
 *
 * @param next - The transport.
 */
export function setModerationFetch(next: ModerationFetch): void {
    transport = next;
}

/** How long a toast stays (ms): an error stays as long as UiKit's flash errors. */
export const TOAST_TIMEOUT = 8000;

const QUOTE_MAX = 80;

type ToastWindow = Window & { nowoUiDismissToast?: (toast: HTMLElement) => void };

/**
 * Shows a message: a UiKit toast when the toast script and the toast template are there, else the text of the notice.
 *
 * @param doc - The document.
 * @param message - Plain text.
 * @param tone - `error` or `success` (the notice gets data-tone).
 */
export function notify(doc: Document, message: string, tone: 'error' | 'success'): void {
    const win = doc.defaultView as ToastWindow | null;
    const template = doc.querySelector<HTMLTemplateElement>(`template[data-nowo-wiki-comments-toast="${tone}"]`);
    const dismiss = win?.nowoUiDismissToast;
    if (template && typeof dismiss === 'function') {
        const fragment = template.content.cloneNode(true) as DocumentFragment;
        const toast = fragment.querySelector<HTMLElement>('[data-nowo-ui-toast]');
        const text = fragment.querySelector<HTMLElement>('.nowo-ui-toast__message');
        if (toast && text) {
            text.textContent = message;
            toast.setAttribute('data-nowo-ui-toast-armed', '');
            const stack = doc.querySelector<HTMLElement>('[data-nowo-ui-toast-stack]');
            if (stack) {
                stack.append(toast);
            } else {
                doc.body.append(fragment);
            }
            win?.setTimeout(() => dismiss(toast), TOAST_TIMEOUT);

            return;
        }
    }
    doc.querySelectorAll<HTMLElement>('[data-nowo-wiki-comments-notice]').forEach((notice) => {
        notice.textContent = message;
        notice.dataset.tone = tone;
        notice.hidden = false;
    });
}

/**
 * The text of a refused comment write (`nowo-milkdown:comment-error`), or null when the panel has no notice (option
 * off) or the status has no text of its own.
 *
 * @param doc - The document.
 * @param status - HTTP status of the refused write.
 */
export function commentErrorText(doc: Document, status: unknown): string | null {
    const notice = doc.querySelector<HTMLElement>('[data-nowo-wiki-comments-notice]');
    if (!notice) {
        return null;
    }
    const text = 422 === status ? notice.dataset.limitReached : 429 === status ? notice.dataset.rateLimited : undefined;

    return text !== undefined && text !== '' ? text : null;
}

const labelsOf = (root: HTMLElement): ModerationLabels => JSON.parse(root.dataset.labels ?? '{}') as ModerationLabels;

const endpoint = (root: HTMLElement, path: string): string => (root.dataset.url ?? '').replace(/\/$/, '') + path;

const request = (root: HTMLElement, method: 'GET' | 'DELETE', path: string): Promise<Response> =>
    transport(endpoint(root, path), {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-Token': root.dataset.token ?? '' },
    });

/**
 * One row of the moderation list (texts only through textContent).
 *
 * @param doc - The document.
 * @param thread - The thread.
 * @param labels - The labels.
 */
export function moderationItem(doc: Document, thread: ModerationThread, labels: ModerationLabels): HTMLLIElement {
    const quote = thread.anchor?.quote?.exact?.trim() ?? '';
    const li = doc.createElement('li');
    li.className = 'nowo-wiki-moderation__item';
    li.dataset.threadId = thread.id;
    const text = doc.createElement('div');
    text.className = 'nowo-wiki-moderation__text';
    const label = doc.createElement('p');
    label.className = 'nowo-wiki-moderation__label';
    label.textContent = '' === quote ? labels.page_comment : `“${quote.length > QUOTE_MAX ? `${quote.slice(0, QUOTE_MAX - 1)}…` : quote}”`;
    const meta = doc.createElement('p');
    meta.className = 'nowo-wiki-moderation__meta';
    meta.textContent = [labels.count.replace('%count%', String(thread.count)), thread.resolved ? labels.resolved : ''].filter((part) => '' !== part).join(' · ');
    text.append(label, meta);
    const button = doc.createElement('button');
    button.type = 'button';
    button.className = (doc.querySelector<HTMLElement>('[data-nowo-wiki-moderation]')?.dataset.buttonClass ?? '').trim();
    button.dataset.nowoWikiModerationDelete = thread.id;
    button.dataset.testid = 'wiki-comments-delete-thread';
    button.textContent = labels.delete;
    li.append(text, button);

    return li;
}

const status = (root: HTMLElement, text: string): void => {
    const target = root.querySelector<HTMLElement>('[data-nowo-wiki-moderation-status]');
    if (target) {
        target.textContent = text;
    }
};

const list = (root: HTMLElement): HTMLElement | null => root.querySelector<HTMLElement>('[data-nowo-wiki-moderation-list]');

/**
 * Reads the threads into the list, once (the first opening of *Moderate threads*).
 *
 * @param doc - The document.
 * @param root - The `[data-nowo-wiki-moderation]` element.
 */
export async function loadModeration(doc: Document, root: HTMLElement): Promise<void> {
    if (root.dataset.loaded === '1') {
        return;
    }
    root.dataset.loaded = '1';
    const labels = labelsOf(root);
    const response = await request(root, 'GET', '/threads').catch(() => null);
    if (!response?.ok) {
        delete root.dataset.loaded;
        status(root, labels.failed);

        return;
    }
    const data = (await response.json()) as { threads?: ModerationThread[]; page?: ModerationThread[] };
    const threads = [...(data.threads ?? []), ...(data.page ?? [])];
    status(root, 0 === threads.length ? labels.empty : '');
    list(root)?.replaceChildren(...threads.map((thread) => moderationItem(doc, thread, labels)));
}

/**
 * Asks to confirm a deletion: the confirm dialog of the panel, else the browser's confirm().
 *
 * @param doc - The document.
 * @param root - The `[data-nowo-wiki-moderation]` element.
 */
export function confirmDeletion(doc: Document, root: HTMLElement): Promise<boolean> {
    const dialog = root.querySelector<HTMLDialogElement>('dialog[data-nowo-wiki-moderation-confirm]');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return Promise.resolve(doc.defaultView?.confirm(labelsOf(root).confirm) ?? false);
    }

    return new Promise((resolve) => {
        const onClick = (event: Event): void => {
            const target = event.target instanceof Element ? event.target : null;
            if (target?.closest('[data-nowo-wiki-moderation-accept]')) {
                dialog.close('accept');
            } else if (target?.closest('[data-nowo-wiki-moderation-cancel]')) {
                dialog.close('cancel');
            }
        };
        dialog.addEventListener('click', onClick);
        dialog.addEventListener(
            'close',
            () => {
                dialog.removeEventListener('click', onClick);
                resolve(dialog.returnValue === 'accept');
            },
            { once: true },
        );
        dialog.returnValue = '';
        dialog.showModal();
    });
}

/**
 * *Delete thread*: confirm, then the moderation endpoint; the row goes away on 204 (or 404: already gone).
 *
 * @param doc - The document.
 * @param root - The `[data-nowo-wiki-moderation]` element.
 * @param button - The row's Delete button.
 */
export async function deleteThread(doc: Document, root: HTMLElement, button: HTMLButtonElement): Promise<void> {
    const labels = labelsOf(root);
    if (!(await confirmDeletion(doc, root))) {
        return;
    }
    button.disabled = true;
    const id = button.dataset.nowoWikiModerationDelete ?? '';
    const response = await request(root, 'DELETE', `/threads/${encodeURIComponent(id)}`).catch(() => null);
    if (response && (204 === response.status || 404 === response.status)) {
        button.closest('li')?.remove();
        notify(doc, labels.deleted, 'success');
        if (0 === (list(root)?.children.length ?? 0)) {
            status(root, labels.empty);
        }

        return;
    }
    button.disabled = false;
    notify(doc, 429 === response?.status ? labels.rate_limited : labels.failed, 'error');
}

const boundDocs = new WeakSet<Document>();

/**
 * Binds the extras once per document (no-op while the server renders none of them).
 *
 * @param doc - The document.
 */
export function bindCommentsModeration(doc: Document = document): void {
    if (boundDocs.has(doc)) {
        return;
    }
    boundDocs.add(doc);

    doc.addEventListener('nowo-milkdown:comment-error', (event) => {
        const text = commentErrorText(doc, (event as CustomEvent<{ status?: unknown }>).detail?.status);
        if (text !== null) {
            notify(doc, text, 'error');
        }
    });

    // `toggle` does not bubble: heard in the capture phase.
    doc.addEventListener(
        'toggle',
        (event) => {
            const root = event.target;
            if (root instanceof HTMLDetailsElement && root.matches('[data-nowo-wiki-moderation]') && root.open) {
                void loadModeration(doc, root);
            }
        },
        true,
    );

    doc.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest<HTMLButtonElement>('button[data-nowo-wiki-moderation-delete]') : null;
        const root = button?.closest<HTMLElement>('[data-nowo-wiki-moderation]');
        if (button && root) {
            void deleteThread(doc, root, button);
        }
    });
}
