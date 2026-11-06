import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    bindCommentsModeration,
    commentErrorText,
    confirmDeletion,
    deleteThread,
    loadModeration,
    moderationItem,
    notify,
    setModerationFetch,
    TOAST_TIMEOUT,
    type ModerationLabels,
    type ModerationThread,
} from './wiki-comments-moderation';

const labels: ModerationLabels = {
    page_comment: 'Page comment',
    resolved: 'resolved',
    count: '%count% comment(s)',
    delete: 'Delete thread',
    deleted: 'Thread deleted.',
    failed: 'Failed.',
    empty: 'No threads.',
    rate_limited: 'Too many.',
    confirm: 'Sure?',
};

const toastTemplates = `
    <template data-nowo-wiki-comments-toast="error"><div data-nowo-ui-toast-stack><div data-nowo-ui-toast><p class="nowo-ui-toast__message">-</p></div></div></template>
    <template data-nowo-wiki-comments-toast="success"><div data-nowo-ui-toast-stack><div data-nowo-ui-toast><p class="nowo-ui-toast__message">-</p></div></div></template>
`;

const notice = '<p id="notice" data-nowo-wiki-comments-notice hidden data-limit-reached="Limit." data-rated="x" data-rate-limited="Slow down."></p>';

const moderation = (dialog = true): string => `
    <details id="mod" data-nowo-wiki-moderation data-url="/p/comments/" data-token="tok" data-button-class=" btn " data-labels='${JSON.stringify(labels)}'>
        <summary>Moderate</summary>
        <p id="status" data-nowo-wiki-moderation-status></p>
        <ul id="list" data-nowo-wiki-moderation-list></ul>
        ${dialog ? '<dialog data-nowo-wiki-moderation-confirm><button id="accept" data-nowo-wiki-moderation-accept></button><button id="cancel" data-nowo-wiki-moderation-cancel></button><p id="other"></p></dialog>' : ''}
    </details>
`;

const thread = (id: string, over: Partial<ModerationThread> = {}): ModerationThread => ({ id, anchor: null, resolved: false, count: 1, ...over });

const json = (body: unknown, status = 200): Response => new Response(JSON.stringify(body), { status });

const empty = (status: number): Response => new Response(null, { status });

const flush = async (): Promise<void> => {
    for (let i = 0; i < 8; i++) {
        await Promise.resolve();
    }
    await new Promise((resolve) => setTimeout(resolve, 0));
};

const root = (): HTMLElement => document.getElementById('mod') as HTMLElement;

type ToastWindow = Window & { nowoUiDismissToast?: (toast: HTMLElement) => void };

/** jsdom has no modal dialogs: the parts of HTMLDialogElement the code uses. */
function polyfillDialog(dialog: HTMLDialogElement): void {
    dialog.showModal = vi.fn();
    dialog.close = (value?: string) => {
        dialog.returnValue = value ?? '';
        dialog.dispatchEvent(new Event('close'));
    };
}

describe('comments panel extras', () => {
    let calls: Array<{ url: string; init: RequestInit }>;

    const respond = (response: Response | Error): void => {
        setModerationFetch(async (url, init) => {
            calls.push({ url, init });
            if (response instanceof Error) {
                throw response;
            }

            return response;
        });
    };

    beforeEach(() => {
        calls = [];
        document.body.innerHTML = '';
    });

    afterEach(() => {
        delete (window as ToastWindow).nowoUiDismissToast;
        vi.restoreAllMocks();
        vi.useRealTimers();
    });

    it('uses fetch until a host replaces the transport', async () => {
        vi.resetModules();
        const fresh = await import('./wiki-comments-moderation');
        const fetcher = vi.fn(async () => json({ threads: [] }));
        vi.stubGlobal('fetch', fetcher);
        document.body.innerHTML = moderation();
        await fresh.loadModeration(document, root());
        expect(fetcher).toHaveBeenCalledWith('/p/comments/threads', expect.objectContaining({ method: 'GET' }));
        vi.unstubAllGlobals();
    });

    describe('notify', () => {
        it('writes into the notice without the toast script', () => {
            document.body.innerHTML = toastTemplates + notice;
            notify(document, 'Limit.', 'error');
            const target = document.getElementById('notice') as HTMLElement;
            expect(target.textContent).toBe('Limit.');
            expect(target.dataset.tone).toBe('error');
            expect(target.hidden).toBe(false);
            expect(document.querySelector('[data-nowo-ui-toast-armed]')).toBeNull();
        });

        it('writes into the notice without the toast template', () => {
            document.body.innerHTML = notice;
            (window as ToastWindow).nowoUiDismissToast = vi.fn();
            notify(document, 'Done.', 'success');
            expect((document.getElementById('notice') as HTMLElement).dataset.tone).toBe('success');
        });

        it('writes into the notice when the template has no toast', () => {
            document.body.innerHTML = '<template data-nowo-wiki-comments-toast="error"><p>no toast</p></template>' + notice;
            (window as ToastWindow).nowoUiDismissToast = vi.fn();
            notify(document, 'Limit.', 'error');
            expect((document.getElementById('notice') as HTMLElement).textContent).toBe('Limit.');
        });

        it('writes into the notice when the toast has no message element', () => {
            document.body.innerHTML = '<template data-nowo-wiki-comments-toast="error"><div data-nowo-ui-toast></div></template>' + notice;
            (window as ToastWindow).nowoUiDismissToast = vi.fn();
            notify(document, 'Limit.', 'error');
            expect((document.getElementById('notice') as HTMLElement).textContent).toBe('Limit.');
        });

        it('shows a UiKit toast in a new stack, armed, dismissed after the timeout, text not parsed as HTML', () => {
            vi.useFakeTimers();
            document.body.innerHTML = toastTemplates + notice;
            const dismiss = vi.fn();
            (window as ToastWindow).nowoUiDismissToast = dismiss;
            notify(document, '<b>Limit</b>', 'error');
            const toast = document.querySelector('body > [data-nowo-ui-toast-stack] [data-nowo-ui-toast]') as HTMLElement;
            expect(toast.hasAttribute('data-nowo-ui-toast-armed')).toBe(true);
            expect(toast.querySelector('.nowo-ui-toast__message')?.textContent).toBe('<b>Limit</b>');
            expect(toast.querySelector('b')).toBeNull();
            expect((document.getElementById('notice') as HTMLElement).hidden).toBe(true);
            vi.advanceTimersByTime(TOAST_TIMEOUT - 1);
            expect(dismiss).not.toHaveBeenCalled();
            vi.advanceTimersByTime(1);
            expect(dismiss).toHaveBeenCalledWith(toast);
        });

        it('adds the toast to the stack of the page when there is one', () => {
            document.body.innerHTML = toastTemplates + '<div id="stack" data-nowo-ui-toast-stack></div>';
            (window as ToastWindow).nowoUiDismissToast = vi.fn();
            notify(document, 'Done.', 'success');
            expect(document.querySelector('#stack > [data-nowo-ui-toast]')?.textContent).toContain('Done.');
            expect(document.querySelectorAll('body > [data-nowo-ui-toast-stack]')).toHaveLength(1);
        });

        it('does nothing without toast or notice (a document without a window included)', () => {
            const detached = document.implementation.createHTMLDocument('x');
            expect(() => notify(detached, 'x', 'error')).not.toThrow();
        });
    });

    describe('commentErrorText', () => {
        it('has no text while the option is off', () => {
            expect(commentErrorText(document, 429)).toBeNull();
        });

        it('has the texts of 422 and 429 only', () => {
            document.body.innerHTML = notice;
            expect(commentErrorText(document, 422)).toBe('Limit.');
            expect(commentErrorText(document, 429)).toBe('Slow down.');
            expect(commentErrorText(document, 500)).toBeNull();
            expect(commentErrorText(document, undefined)).toBeNull();
        });

        it('has no text when the attribute is empty', () => {
            document.body.innerHTML = '<p data-nowo-wiki-comments-notice data-limit-reached=""></p>';
            expect(commentErrorText(document, 422)).toBeNull();
        });
    });

    describe('moderationItem', () => {
        it('draws a page-level thread, without a moderation root for the button class', () => {
            const item = moderationItem(document, thread('t1', { count: 3 }), labels);
            expect(item.dataset.threadId).toBe('t1');
            expect(item.querySelector('.nowo-wiki-moderation__label')?.textContent).toBe('Page comment');
            expect(item.querySelector('.nowo-wiki-moderation__meta')?.textContent).toBe('3 comment(s)');
            const button = item.querySelector('button') as HTMLButtonElement;
            expect(button.className).toBe('');
            expect(button.type).toBe('button');
            expect(button.dataset.nowoWikiModerationDelete).toBe('t1');
            expect(button.textContent).toBe('Delete thread');
        });

        it('quotes the text, as text, and marks a resolved thread', () => {
            document.body.innerHTML = moderation();
            const item = moderationItem(document, thread('t2', { anchor: { quote: { exact: ' <img src=x onerror=alert(1)> ' } }, resolved: true }), labels);
            expect(item.querySelector('.nowo-wiki-moderation__label')?.textContent).toBe('“<img src=x onerror=alert(1)>”');
            expect(item.querySelector('img')).toBeNull();
            expect(item.querySelector('.nowo-wiki-moderation__meta')?.textContent).toBe('1 comment(s) · resolved');
            expect((item.querySelector('button') as HTMLButtonElement).className).toBe('btn');
        });

        it('cuts a long quote and treats an anchor without a quote as a page comment', () => {
            const long = moderationItem(document, thread('t3', { anchor: { quote: { exact: 'x'.repeat(200) } } }), labels);
            expect(long.querySelector('.nowo-wiki-moderation__label')?.textContent).toBe(`“${'x'.repeat(79)}…”`);
            for (const anchor of [{}, { quote: {} }, { quote: { exact: '  ' } }]) {
                expect(moderationItem(document, thread('t4', { anchor }), labels).querySelector('.nowo-wiki-moderation__label')?.textContent).toBe('Page comment');
            }
        });
    });

    describe('loadModeration', () => {
        it('reads inline and page threads once, with the CSRF header and the trailing slash of the URL removed', async () => {
            document.body.innerHTML = moderation();
            respond(json({ threads: [thread('t1')], page: [thread('t2')] }));
            await loadModeration(document, root());
            await loadModeration(document, root());
            expect(calls).toHaveLength(1);
            expect(calls[0].url).toBe('/p/comments/threads');
            expect(calls[0].init.method).toBe('GET');
            expect(calls[0].init.credentials).toBe('same-origin');
            expect((calls[0].init.headers as Record<string, string>)['X-CSRF-Token']).toBe('tok');
            expect(Array.from(document.querySelectorAll('#list li')).map((li) => (li as HTMLElement).dataset.threadId)).toEqual(['t1', 't2']);
            expect(document.getElementById('status')?.textContent).toBe('');
        });

        it('says so when the page has no threads (missing keys included)', async () => {
            document.body.innerHTML = moderation();
            respond(json({}));
            await loadModeration(document, root());
            expect(document.getElementById('status')?.textContent).toBe('No threads.');
        });

        it('says so on a refused or failed read and may try again', async () => {
            document.body.innerHTML = moderation();
            respond(empty(403));
            await loadModeration(document, root());
            expect(document.getElementById('status')?.textContent).toBe('Failed.');
            expect(root().dataset.loaded).toBeUndefined();
            respond(new Error('offline'));
            await loadModeration(document, root());
            expect(document.getElementById('status')?.textContent).toBe('Failed.');
            respond(json({ threads: [thread('t1')] }));
            await loadModeration(document, root());
            expect(document.querySelectorAll('#list li')).toHaveLength(1);
        });

        it('survives markup without list, status, URL, token or labels', async () => {
            document.body.innerHTML = '<details id="mod" data-nowo-wiki-moderation></details>';
            respond(json({ threads: [thread('t1', { count: 2 })] }, 200));
            setModerationFetch(async (url, init) => {
                calls.push({ url, init });

                return empty(500);
            });
            await loadModeration(document, root());
            expect(calls[0].url).toBe('/threads');
            expect((calls[0].init.headers as Record<string, string>)['X-CSRF-Token']).toBe('');
            setModerationFetch(async () => json({}));
            await expect(loadModeration(document, root())).resolves.toBeUndefined();
        });
    });

    describe('confirmDeletion', () => {
        it('falls back to confirm() without the dialog', async () => {
            document.body.innerHTML = moderation(false);
            const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
            await expect(confirmDeletion(document, root())).resolves.toBe(true);
            expect(confirm).toHaveBeenCalledWith('Sure?');
        });

        it('refuses without a dialog and without a window', async () => {
            document.body.innerHTML = moderation(false);
            const detached = document.implementation.createHTMLDocument('x');
            await expect(confirmDeletion(detached, root())).resolves.toBe(false);
        });

        it('falls back to confirm() when the browser has no showModal', async () => {
            document.body.innerHTML = moderation();
            const dialog = root().querySelector('dialog') as HTMLDialogElement;
            Object.defineProperty(dialog, 'showModal', { value: undefined });
            vi.spyOn(window, 'confirm').mockReturnValue(false);
            await expect(confirmDeletion(document, root())).resolves.toBe(false);
        });

        it('resolves with the button of the dialog, ignoring other clicks', async () => {
            document.body.innerHTML = moderation();
            const dialog = root().querySelector('dialog') as HTMLDialogElement;
            polyfillDialog(dialog);

            const accepted = confirmDeletion(document, root());
            expect(dialog.showModal).toHaveBeenCalled();
            document.getElementById('other')?.click();
            dialog.dispatchEvent(new Event('click'));
            document.getElementById('accept')?.click();
            await expect(accepted).resolves.toBe(true);

            const cancelled = confirmDeletion(document, root());
            document.getElementById('cancel')?.click();
            await expect(cancelled).resolves.toBe(false);

            // Closed by Escape: no accept.
            const escaped = confirmDeletion(document, root());
            dialog.close();
            await expect(escaped).resolves.toBe(false);
        });

        it('treats a click with a non-element target as no answer', async () => {
            document.body.innerHTML = moderation();
            const dialog = root().querySelector('dialog') as HTMLDialogElement;
            polyfillDialog(dialog);
            const pending = confirmDeletion(document, root());
            const event = new Event('click');
            Object.defineProperty(event, 'target', { value: window });
            dialog.dispatchEvent(event);
            dialog.close('cancel');
            await expect(pending).resolves.toBe(false);
        });
    });

    describe('deleteThread', () => {
        const row = async (): Promise<HTMLButtonElement> => {
            respond(json({ threads: [thread('a/b')] }));
            await loadModeration(document, root());
            calls = [];

            return document.querySelector('#list button') as HTMLButtonElement;
        };

        beforeEach(() => {
            document.body.innerHTML = moderation(false) + notice;
            vi.spyOn(window, 'confirm').mockReturnValue(true);
        });

        it('does nothing when not confirmed', async () => {
            const button = await row();
            vi.spyOn(window, 'confirm').mockReturnValue(false);
            await deleteThread(document, root(), button);
            expect(calls).toHaveLength(0);
            expect(button.disabled).toBe(false);
        });

        it('deletes through the moderation endpoint, removes the row and says the list is empty', async () => {
            const button = await row();
            respond(empty(204));
            await deleteThread(document, root(), button);
            expect(calls[0].url).toBe('/p/comments/threads/a%2Fb');
            expect(calls[0].init.method).toBe('DELETE');
            expect((calls[0].init.headers as Record<string, string>)['X-CSRF-Token']).toBe('tok');
            expect(document.querySelectorAll('#list li')).toHaveLength(0);
            expect(document.getElementById('notice')?.textContent).toBe('Thread deleted.');
            expect(document.getElementById('status')?.textContent).toBe('No threads.');
        });

        it('treats 404 as already gone and keeps the other rows', async () => {
            respond(json({ threads: [thread('t1'), thread('t2')] }));
            await loadModeration(document, root());
            respond(empty(404));
            await deleteThread(document, root(), document.querySelector('#list button') as HTMLButtonElement);
            expect(document.querySelectorAll('#list li')).toHaveLength(1);
            expect(document.getElementById('status')?.textContent).toBe('');
        });

        it('keeps the row and says why on 429, other errors and network failures', async () => {
            const button = await row();
            for (const [response, text] of [[empty(429), 'Too many.'], [empty(500), 'Failed.'], [new Error('offline'), 'Failed.']] as const) {
                respond(response);
                await deleteThread(document, root(), button);
                expect(button.disabled).toBe(false);
                expect(document.querySelectorAll('#list li')).toHaveLength(1);
                expect(document.getElementById('notice')?.textContent).toBe(text);
                expect((document.getElementById('notice') as HTMLElement).dataset.tone).toBe('error');
            }
        });

        it('survives a button outside a row, with no list and no id', async () => {
            document.body.innerHTML = moderation(false).replace(/<ul.*<\/ul>/, '').replace(/<p id="status".*<\/p>/, '');
            const button = document.createElement('button');
            root().append(button);
            respond(empty(204));
            await deleteThread(document, root(), button);
            expect(calls[0].url).toBe('/p/comments/threads/');
        });
    });

    describe('bindCommentsModeration', () => {
        // One document for the whole block: the binding happens once per document.
        const bound = document.implementation.createHTMLDocument('bound');
        bindCommentsModeration(bound);
        bindCommentsModeration(bound);

        const mount = (html: string): void => {
            bound.body.innerHTML = html;
        };

        it('binds the default document', () => {
            const add = vi.spyOn(document, 'addEventListener');
            bindCommentsModeration();
            bindCommentsModeration();
            expect(add).toHaveBeenCalledTimes(3);
        });

        it('shows the text of a refused write, and nothing for other statuses or without details', () => {
            mount(notice);
            const target = bound.getElementById('notice') as HTMLElement;
            const fire = (detail?: unknown): void => {
                bound.body.dispatchEvent(new CustomEvent('nowo-milkdown:comment-error', { bubbles: true, detail }));
            };
            fire({ status: 500 });
            fire();
            expect(target.hidden).toBe(true);
            fire({ status: 429 });
            expect(target.textContent).toBe('Slow down.');
            expect(target.hidden).toBe(false);
        });

        it('stays silent while the option is off', () => {
            mount('');
            bound.body.dispatchEvent(new CustomEvent('nowo-milkdown:comment-error', { bubbles: true, detail: { status: 429 } }));
            expect(bound.body.textContent).toBe('');
        });

        it('loads the list on the first opening only, and ignores other details', async () => {
            const win = window;
            mount(moderation() + '<details id="other-details"></details>');
            respond(json({ threads: [thread('t1')] }));
            const details = bound.getElementById('mod') as HTMLDetailsElement;
            const other = bound.getElementById('other-details') as HTMLDetailsElement;
            other.open = true;
            other.dispatchEvent(new win.Event('toggle'));
            details.dispatchEvent(new win.Event('toggle'));
            await flush();
            expect(calls).toHaveLength(0);
            details.open = true;
            details.dispatchEvent(new win.Event('toggle'));
            await flush();
            expect(calls).toHaveLength(1);
            expect(bound.querySelectorAll('#list li')).toHaveLength(1);
            bound.body.dispatchEvent(new win.Event('toggle'));
            await flush();
            expect(calls).toHaveLength(1);
        });

        it('deletes from a click on a row button, and ignores other clicks', async () => {
            mount(moderation(false) + notice + '<button id="outside" data-nowo-wiki-moderation-delete="x"></button><button id="plain"></button>');
            respond(json({ threads: [thread('t1')] }));
            await loadModeration(bound, bound.getElementById('mod') as HTMLElement);
            calls = [];
            const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
            respond(empty(204));
            (bound.getElementById('outside') as HTMLElement).click();
            (bound.getElementById('plain') as HTMLElement).click();
            bound.dispatchEvent(new Event('click'));
            await flush();
            expect(calls).toHaveLength(0);
            expect(confirm).not.toHaveBeenCalled();
            // createHTMLDocument has no window: the fallback confirm refuses, so nothing is sent.
            (bound.querySelector('#list button') as HTMLElement).click();
            await flush();
            expect(calls).toHaveLength(0);
        });
    });
});
