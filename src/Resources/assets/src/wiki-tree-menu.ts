/**
 * 1.39 (`tree.context_menu`): the page tree's context menu.
 *
 * 1.41: also Open in new tab, Edit (`data-wiki-edit-href`), New page above / below, Duplicate, Move up / down / to…
 * (`data-wiki-menu-move`: the tree's Alt+arrow moves and drops, so the tree's move endpoint and its ordering), Archive
 * (`data-wiki-menu-archive`, with an undo line offering Unarchive) and Delete (`data-wiki-menu-delete`, the trash, behind
 * the confirm dialog with the count of pages). A long press (touch) opens the menu as a right click does; separators
 * show only between visible groups.
 *
 * The server renders one ARIA menu (`[data-wiki-tree-menu]`) with its entries (`[data-wiki-tree-menu-item]`: rename,
 * icon, child, below, above, copy), a polite status line, the "Change icon" dialog (`[data-wiki-tree-icon-dialog]`)
 * and, on each tree row, a hidden «…» button. It marks what each row offers (`data-wiki-menu-edit`: Rename;
 * `data-wiki-menu-icon`: Change icon; `data-wiki-new-child` / `data-wiki-new-sibling`: Add child / sibling page). A row
 * without a mark gets no menu: readers keep the browser's own context menu. "Copy link" comes with any other entry.
 *
 * The menu opens from a right click on a row, the row's «…» button (touch) or the ContextMenu key / Shift+F10 on a
 * row, and follows the WAI-ARIA menu pattern: focus on the first entry, arrows / Home / End move it (roving,
 * wrapping), Enter / Space choose, Escape and Tab close it and give the focus back to the row. Writes go to the tree's
 * endpoints with its CSRF token (`data-wiki-tree-token`); the server checks every one again. No inline handlers, no
 * HTML from user data: titles and messages are set as text.
 */

/** An entry of the menu. */
export type TreeMenuItem = 'open' | 'edit' | 'rename' | 'icon' | 'child' | 'above' | 'below' | 'duplicate' | 'up' | 'down' | 'moveto' | 'copy' | 'archive' | 'delete';

const NODE = 'li[data-nowo-ui-tree-node]';
const ITEMS: readonly TreeMenuItem[] = ['open', 'edit', 'rename', 'icon', 'child', 'above', 'below', 'duplicate', 'up', 'down', 'moveto', 'copy', 'archive', 'delete'];

/** How long a touch stays down before it opens the menu (ms), and how far it may move meanwhile (px). */
export const LONG_PRESS_MS = 500;
const LONG_PRESS_SLOP = 10;

/** The mark each entry needs on the row ("open" and "copy" come with any other entry). */
const MARKS: Partial<Record<TreeMenuItem, string>> = {
    edit: 'data-wiki-edit-href',
    rename: 'data-wiki-menu-edit',
    icon: 'data-wiki-menu-icon',
    child: 'data-wiki-new-child',
    above: 'data-wiki-new-sibling',
    below: 'data-wiki-new-sibling',
    duplicate: 'data-wiki-new-sibling',
    up: 'data-wiki-menu-move',
    down: 'data-wiki-menu-move',
    moveto: 'data-wiki-menu-move',
    archive: 'data-wiki-menu-archive',
    delete: 'data-wiki-menu-delete',
};

const siblingRows = (item: HTMLLIElement): HTMLLIElement[] => [...(item.parentElement as HTMLElement).children].filter((el) => el.matches(NODE)) as HTMLLIElement[];

const linkOf = (item: HTMLLIElement): HTMLAnchorElement | null =>
    item.querySelector<HTMLAnchorElement>(':scope > .nowo-ui-tree__row > [data-nowo-ui-tree-item]');

const labelOf = (item: HTMLLIElement): HTMLElement | null =>
    item.querySelector<HTMLElement>(':scope > .nowo-ui-tree__row .nowo-ui-tree__label');

/** The row's title (UiKit's label span, else the link's text). */
export const titleOf = (item: HTMLLIElement): string => `${labelOf(item)?.textContent}`.trim();

const treeOf = (item: HTMLLIElement): HTMLElement | null => item.closest<HTMLElement>('[data-wiki-tree]');

/**
 * The entries a row offers, in menu order (only those the menu renders: "icon" needs the dialog, "above" the
 * new-page dialog). Empty: no menu for that row.
 *
 * @param item - A tree row.
 * @param rendered - Entries the menu has.
 */
export function allowedItems(item: HTMLLIElement, rendered: readonly TreeMenuItem[] = ITEMS): TreeMenuItem[] {
    const siblings = siblingRows(item);
    const at = siblings.indexOf(item);
    const marked = ITEMS.filter((entry) => {
        const mark = MARKS[entry];
        // Move up / down only where there is a sibling on that side.
        const edge = (entry === 'up' && at === 0) || (entry === 'down' && at === siblings.length - 1);

        return mark !== undefined && item.hasAttribute(mark) && !edge && rendered.includes(entry);
    });

    return marked.length === 0 ? [] : ITEMS.filter((entry) => marked.includes(entry) || ((entry === 'open' || entry === 'copy') && rendered.includes(entry)));
}

/** The place the new-page form receives for an entry (`child:<id>`, `below:<id>`, `above:<id>`). */
export function placeFor(item: HTMLLIElement, entry: 'child' | 'below' | 'above'): string {
    return `${entry}:${item.dataset.nowoUiTreeNode}`;
}

/** The tree row of an event target, when it is a row of a wiki page tree. */
const rowItem = (target: EventTarget | null): HTMLLIElement | null => {
    const row = (target as Element).closest('.nowo-ui-tree__row');

    return row?.closest('[data-wiki-tree]') ? row.closest<HTMLLIElement>(NODE) : null;
};

/** Shows the «…» button of the rows that have an entry, hides the others. */
export function refreshMenuButtons(root: ParentNode, rendered: readonly TreeMenuItem[] = ITEMS): void {
    root.querySelectorAll<HTMLElement>('[data-wiki-tree-menu-more]').forEach((button) => {
        const item = button.closest<HTMLLIElement>(NODE);
        button.hidden = item === null || allowedItems(item, rendered).length === 0;
    });
}

/** Answer of the tree endpoints. */
interface TreeAnswer {
    title?: string;
    icon?: string | null;
    icon_html?: string;
    message?: string;
    error?: string;
    url?: string;
    parents?: { id: string; label: string }[];
    root?: boolean;
    parent?: string | null;
}

/**
 * Posts a JSON body to a tree endpoint with the tree's CSRF token.
 *
 * @returns The status and the parsed answer (an empty object when it is not JSON).
 */
export async function postTree(url: string, token: string, body: Record<string, unknown>, fetcher: typeof fetch = fetch): Promise<{ status: number; data: TreeAnswer }> {
    const response = await fetcher(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ ...body, _token: token }),
    });
    let data: TreeAnswer = {};
    try {
        data = (await response.json()) as TreeAnswer;
    } catch {
        data = {};
    }

    return { status: response.status, data };
}

const boundMenus = new WeakSet<HTMLElement>();

/**
 * Binds the menu of the document (once per menu; nothing without it). The document listeners do nothing once that
 * menu has left the document.
 *
 * @param doc - The document.
 * @param fetcher - fetch (tests).
 */
export function bindTreeMenu(doc: Document = document, fetcher: typeof fetch = fetch): void {
    const menu = doc.querySelector<HTMLElement>('[data-wiki-tree-menu]');
    if (!menu || boundMenus.has(menu)) {
        return;
    }
    boundMenus.add(menu);
    const status = doc.querySelector<HTMLElement>('[data-wiki-tree-menu-status]')!;
    const rendered = [...menu.querySelectorAll<HTMLElement>('[data-wiki-tree-menu-item]')].map((el) => el.dataset.wikiTreeMenuItem as TreeMenuItem);
    const iconDialog = doc.querySelector<HTMLDialogElement>('[data-wiki-tree-icon-dialog]');
    let current: HTMLLIElement | null = null;
    let opener: HTMLElement | null = null;

    const say = (text: string): void => {
        status.textContent = text;
    };

    /** A translated text the server put on the menu (`data-copied`, `data-failed`, …). */
    const text = (key: string): string => menu.dataset[key] ?? '';

    /** Opens a native dialog modal (the `open` attribute where showModal() is missing). */
    const openDialog = (dialog: HTMLDialogElement): void => {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
    };

    const entries = (): HTMLElement[] => [...menu.querySelectorAll<HTMLElement>('[data-wiki-tree-menu-item]')].filter((el) => !el.hidden);

    const close = (focusRow: boolean): void => {
        if (menu.hidden) {
            return;
        }
        menu.hidden = true;
        opener?.removeAttribute('data-wiki-open');
        const row = current;
        current = null;
        opener = null;
        if (focusRow && row) {
            linkOf(row)?.focus();
        }
    };

    const open = (item: HTMLLIElement, x: number, y: number, button: HTMLElement | null, above: number = y): boolean => {
        const allowed = allowedItems(item, rendered);
        if (allowed.length === 0) {
            return false;
        }
        close(false);
        menu.querySelectorAll<HTMLElement>('[data-wiki-tree-menu-item]').forEach((el) => {
            el.hidden = !allowed.includes(el.dataset.wikiTreeMenuItem as TreeMenuItem);
        });
        // A separator shows only between two visible groups.
        let pending: HTMLElement | null = null;
        let shown = false;
        for (const el of menu.children as HTMLCollectionOf<HTMLElement>) {
            if (el.hasAttribute('data-wiki-tree-menu-separator')) {
                el.hidden = true;
                pending = shown ? el : null;
            } else if (!el.hidden) {
                pending?.removeAttribute('hidden');
                pending = null;
                shown = true;
            }
        }
        current = item;
        opener = button;
        button?.setAttribute('data-wiki-open', '');
        menu.hidden = false;
        const view = doc.defaultView as Window;
        menu.style.left = `${Math.max(0, Math.min(x, view.innerWidth - menu.offsetWidth - 8))}px`;
        // 1.41: without room below, the menu opens above its anchor (the row or the «…» button) rather than over it.
        const top = y + menu.offsetHeight + 8 > view.innerHeight ? above - menu.offsetHeight : y;
        menu.style.top = `${Math.max(0, Math.min(top, view.innerHeight - menu.offsetHeight - 8))}px`;
        entries()[0]?.focus();

        return true;
    };

    const startRename = (item: HTMLLIElement): void => {
        const tree = treeOf(item) as HTMLElement;
        const link = linkOf(item) as HTMLAnchorElement;
        const url = `${tree.dataset.wikiTreeRename}`;
        const before = titleOf(item);
        const input = doc.createElement('input');
        input.type = 'text';
        input.className = 'nowo-wiki-tree__rename';
        input.value = before;
        input.maxLength = 255;
        input.setAttribute('aria-label', text('renameLabel').replace('%title%', () => before));
        input.dataset.testid = 'wiki-tree-rename-input';
        link.hidden = true;
        link.insertAdjacentElement('afterend', input);
        input.focus();
        input.select();
        let busy = false;
        let done = false;

        const finish = (): void => {
            done = true;
            input.remove();
            link.hidden = false;
            link.focus();
        };

        const commit = async (): Promise<void> => {
            if (busy) {
                return;
            }
            const title = input.value.trim();
            if (title === '' || title === before) {
                finish();

                return;
            }
            busy = true;
            input.setAttribute('aria-busy', 'true');
            try {
                const { status: code, data } = await postTree(url, `${tree.dataset.wikiTreeToken}`, { page: item.dataset.nowoUiTreeNode, title }, fetcher);
                if (code === 200) {
                    (labelOf(item) as HTMLElement).textContent = `${data.title}`;
                    say(`${data.message}`);
                    finish();
                    if (link.getAttribute('aria-current') === 'page') {
                        // The page being read: its title is in the header, the card and the document title.
                        doc.defaultView?.location.reload();
                    }

                    return;
                }
                input.setAttribute('aria-invalid', 'true');
                say(data.message ?? text('failed'));
            } catch {
                input.setAttribute('aria-invalid', 'true');
                say(text('failed'));
            } finally {
                busy = false;
                input.removeAttribute('aria-busy');
            }
        };

        input.addEventListener('keydown', (event) => {
            // The field's keys are its own: not the tree's typeahead, arrows or Enter.
            event.stopPropagation();
            if (event.key === 'Enter') {
                event.preventDefault();
                void commit();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                finish();
            }
        });
        input.addEventListener('blur', () => {
            // Not while saving, nor after Enter / Escape ended it, nor over a refused title (the message stays).
            if (!busy && !done && !input.hasAttribute('aria-invalid')) {
                void commit();
            }
        });
    };

    const openIconDialog = (item: HTMLLIElement): void => {
        // The menu offers "icon" only with the dialog (WikiTreeMenu + _tree_menu.html.twig render them together).
        const dialog = iconDialog as HTMLDialogElement;
        const tree = treeOf(item) as HTMLElement;
        const url = `${tree.dataset.wikiTreeIcon}`;
        const template = dialog.querySelector<HTMLTemplateElement>('template[data-wiki-tree-icon-template]') as HTMLTemplateElement;
        const slot = dialog.querySelector<HTMLElement>('[data-wiki-tree-icon-slot]') as HTMLElement;
        const fragment = template.content.cloneNode(true) as DocumentFragment;
        const field = fragment.querySelector<HTMLInputElement>('[name$="[icon]"]') as HTMLInputElement;
        const value = `${item.getAttribute('data-wiki-icon')}`;
        field.value = value;
        field.setAttribute('value', value);
        slot.replaceChildren(fragment);
        const error = dialog.querySelector<HTMLElement>('[data-wiki-tree-icon-error]') as HTMLElement;
        error.hidden = true;
        error.textContent = '';
        const context = dialog.querySelector<HTMLElement>('[data-wiki-tree-icon-context]') as HTMLElement;
        context.textContent = `${context.dataset.template}`.replace('%title%', () => titleOf(item));
        const form = slot.querySelector<HTMLFormElement>('form') as HTMLFormElement;
        const refuse = (message: string): void => {
            error.textContent = message;
            error.hidden = false;
        };
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const icon = (form.querySelector<HTMLInputElement>('[name$="[icon]"]') as HTMLInputElement).value.trim();
            void postTree(url, `${tree.dataset.wikiTreeToken}`, { page: item.dataset.nowoUiTreeNode, icon: icon === '' ? null : icon }, fetcher)
                .then(({ status: code, data }) => {
                    if (code !== 200) {
                        refuse(data.message ?? text('failed'));

                        return;
                    }
                    updateRowIcon(item, data.icon ?? null, `${data.icon_html}`);
                    say(`${data.message}`);
                    dialog.close();
                    if (linkOf(item)?.getAttribute('aria-current') === 'page') {
                        (doc.defaultView as Window).location.reload();
                    }
                }, () => refuse(text('failed')));
        });
        const back = linkOf(item);
        dialog.addEventListener('close', () => {
            slot.replaceChildren();
            back?.focus();
        }, { once: true });
        openDialog(dialog);
        slot.querySelector<HTMLElement>('input:not([type="hidden"]), button, select')?.focus();
    };

    const newPage = (item: HTMLLIElement, entry: 'child' | 'below' | 'above'): void => {
        const place = placeFor(item, entry);
        const dialog = doc.querySelector<HTMLDialogElement>('[data-wiki-new-page-dialog]');
        if (dialog) {
            // The 1.35 new-page dialog: its hidden place field and the line that says where the page goes.
            const field = dialog.querySelector<HTMLInputElement>('[data-wiki-new-page-place]') as HTMLInputElement;
            field.value = place;
            const line = dialog.querySelector<HTMLElement>('[data-wiki-new-page-context]') as HTMLElement;
            line.textContent = `${line.dataset[entry]}`.replace('%title%', () => titleOf(item));
            line.hidden = false;
            const back = linkOf(item);
            dialog.addEventListener('close', () => {
                field.value = '';
                back?.focus();
            }, { once: true });
            openDialog(dialog);
            dialog.querySelector<HTMLElement>('input:not([type="hidden"]), textarea, [contenteditable="true"]')?.focus();

            return;
        }
        // Without the dialog the server rendered data-wiki-tree-new (the new-page form, which takes ?place=).
        (doc.defaultView as Window).location.assign(`${treeOf(item)?.dataset.wikiTreeNew}?place=${encodeURIComponent(place)}`);
    };

    const copyLink = (item: HTMLLIElement): void => {
        const clipboard = (doc.defaultView as Window).navigator.clipboard as Clipboard | undefined;
        if (!clipboard) {
            say(text('copyFailed'));

            return;
        }
        clipboard.writeText((linkOf(item) as HTMLAnchorElement).href).then(
            () => say(text('copied')),
            () => say(text('copyFailed')),
        );
    };

    const view = doc.defaultView as Window;

    /** The sentence of a refusal: the server's; "not allowed" for a 403 without one (the firewall's page); else "failed". */
    const refusal = (code: number, data: TreeAnswer): string => data.message ?? text(code === 403 ? 'denied' : 'failed');

    /** Posts the row's page to one of its tree's endpoints (`data-wiki-tree-<name>`). */
    const send = (item: HTMLLIElement, name: string, body: Record<string, unknown> = {}): Promise<{ status: number; data: TreeAnswer }> => {
        const tree = treeOf(item) as HTMLElement;

        return postTree(`${tree.dataset[name]}`, `${tree.dataset.wikiTreeToken}`, { page: item.dataset.nowoUiTreeNode, ...body }, fetcher);
    };

    /** Runs a request; its refusal (or a network failure) is said in the status line. */
    const request = (item: HTMLLIElement, name: string, body: Record<string, unknown>, done: (data: TreeAnswer) => void): void => {
        send(item, name, body).then(
            ({ status: code, data }) => (code === 200 ? done(data) : say(refusal(code, data))),
            () => say(text('failed')),
        );
    };

    /** Takes a row (and its sub-tree) out of the tree; the tree keeps one tab stop, which takes the focus. */
    const removeRow = (item: HTMLLIElement): void => {
        const tree = treeOf(item) as HTMLElement;
        item.remove();
        const links = [...tree.querySelectorAll<HTMLElement>('[data-nowo-ui-tree-item]')];
        const stop = links.find((l) => l.tabIndex === 0) ?? links[0];
        if (stop) {
            stop.tabIndex = 0;
            stop.focus();
        }
    };

    // The confirm dialog of Archive and Delete (one action at a time).
    const confirmDialog = doc.querySelector<HTMLDialogElement>('[data-wiki-tree-confirm-dialog]');
    let confirmed: (() => Promise<void>) | null = null;

    const askConfirm = (item: HTMLLIElement, kind: 'archive' | 'delete', message: string, run: (fail: (text: string) => void) => Promise<void>): void => {
        const dialog = confirmDialog as HTMLDialogElement;
        (dialog.querySelector('[data-wiki-tree-confirm-message]') as HTMLElement).textContent = message;
        const ok = dialog.querySelector<HTMLButtonElement>('[data-wiki-tree-confirm-ok]') as HTMLButtonElement;
        ok.textContent = `${ok.dataset[kind]}`;
        const error = dialog.querySelector<HTMLElement>('[data-wiki-tree-confirm-error]') as HTMLElement;
        error.hidden = true;
        const fail = (reason: string): void => {
            error.textContent = reason;
            error.hidden = false;
        };
        confirmed = () => run(fail);
        const back = linkOf(item) as HTMLElement;
        dialog.addEventListener('close', () => {
            confirmed = null;
            if (back.isConnected) {
                back.focus();
            }
        }, { once: true });
        openDialog(dialog);
    };

    confirmDialog?.querySelector<HTMLButtonElement>('[data-wiki-tree-confirm-ok]')?.addEventListener('click', (event) => {
        const ok = event.currentTarget as HTMLButtonElement;
        if (!confirmed || ok.disabled) {
            return;
        }
        ok.disabled = true;
        void confirmed().finally(() => {
            ok.disabled = false;
        });
    });

    // The undo line of Archive: Unarchive brings the page back (the tree is drawn again).
    const undo = doc.querySelector<HTMLElement>('[data-wiki-tree-undo]');
    let archived: { tree: HTMLElement; id: string } | null = null;
    undo?.querySelector('[data-wiki-tree-undo-button]')?.addEventListener('click', () => {
        if (!archived) {
            return;
        }
        const { tree, id } = archived;
        postTree(`${tree.dataset.wikiTreeUnarchive}`, `${tree.dataset.wikiTreeToken}`, { page: id }, fetcher).then(
            ({ status: code, data }) => {
                if (code !== 200) {
                    say(refusal(code, data));

                    return;
                }
                archived = null;
                (undo as HTMLElement).hidden = true;
                view.location.reload();
            },
            () => say(text('failed')),
        );
    });

    const archive = (item: HTMLLIElement): void => {
        const tree = treeOf(item) as HTMLElement;
        const id = `${item.dataset.nowoUiTreeNode}`;
        askConfirm(item, 'archive', text('archiveConfirm').replace('%title%', () => titleOf(item)), async (fail) => {
            const { status: code, data } = await send(item, 'wikiTreeArchive').catch(() => ({ status: 0, data: {} as TreeAnswer }));
            if (code !== 200) {
                fail(refusal(code, data));

                return;
            }
            // The row first: the dialog then has no row to give the focus back to, the tree's tab stop takes it.
            removeRow(item);
            (confirmDialog as HTMLDialogElement).close();
            say(`${data.message}`);
            if (undo) {
                archived = { tree, id };
                (undo.querySelector('[data-wiki-tree-undo-text]') as HTMLElement).textContent = `${data.message}`;
                undo.hidden = false;
            }
        });
    };

    const remove = (item: HTMLLIElement): void => {
        const tree = treeOf(item) as HTMLElement;
        // The count of pages that go comes from the server (the pages the user may read).
        request(item, 'wikiTreeDelete', { preview: true }, (preview) => askConfirm(item, 'delete', `${preview.message}`, async (fail) => {
            const { status: code, data } = await send(item, 'wikiTreeDelete').catch(() => ({ status: 0, data: {} as TreeAnswer }));
            if (code !== 200) {
                fail(refusal(code, data));

                return;
            }
            if (item.querySelector('[aria-current="page"]')) {
                // The page being read (or one above it) went to the trash: back to the space.
                (confirmDialog as HTMLDialogElement).close();
                view.location.assign(`${tree.dataset.wikiTreeSpace}`);

                return;
            }
            removeRow(item);
            (confirmDialog as HTMLDialogElement).close();
            say(`${data.message}`);
        }));
    };

    // "Move to…": the parents the server offers; the move itself is the tree's drop (or, outside the drawn tree, the
    // move endpoint and the page again).
    const moveDialog = doc.querySelector<HTMLDialogElement>('[data-wiki-tree-move-dialog]');
    let moving: { item: HTMLLIElement; current: string | null } | null = null;

    const moveTo = (item: HTMLLIElement, parent: string | null): void => {
        const tree = treeOf(item) as HTMLElement;
        const id = `${item.dataset.nowoUiTreeNode}`;
        const rows = [...tree.querySelectorAll<HTMLLIElement>(NODE)];
        const roots = rows.filter((row) => row.parentElement === tree);
        const last = roots[roots.length - 1];
        const detail = parent === null
            ? (last !== item ? { id, targetId: `${last.dataset.nowoUiTreeNode}`, position: 'after' } : null)
            : (rows.some((row) => row.dataset.nowoUiTreeNode === parent) ? { id, targetId: parent, position: 'inside' } : null);
        if (detail) {
            linkOf(item)?.dispatchEvent(new CustomEvent('nowo-ui:tree:drop', { bubbles: true, cancelable: true, detail }));

            return;
        }
        request(item, 'wikiTreeMove', { parent, index: 1_000_000 }, () => view.location.reload());
    };

    moveDialog?.querySelector('form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const select = (event.currentTarget as HTMLFormElement).querySelector('select') as HTMLSelectElement;
        const target = moving as { item: HTMLLIElement; current: string | null };
        const parent = select.value === '' ? null : select.value;
        (moveDialog as HTMLDialogElement).close();
        if (parent !== target.current) {
            moveTo(target.item, parent);
        }
    });

    const openMove = (item: HTMLLIElement): void => {
        request(item, 'wikiTreeParents', {}, (data) => {
            const dialog = moveDialog as HTMLDialogElement;
            const select = dialog.querySelector('select') as HTMLSelectElement;
            const context = dialog.querySelector<HTMLElement>('[data-wiki-tree-move-context]') as HTMLElement;
            context.textContent = `${context.dataset.template}`.replace('%title%', () => titleOf(item));
            const options = (data.parents ?? []).map((parent) => new Option(parent.label, parent.id));
            if (data.root) {
                options.unshift(new Option(`${context.dataset.root}`, ''));
            }
            select.replaceChildren(...options);
            select.value = data.parent ?? '';
            moving = { item, current: data.parent ?? null };
            const back = linkOf(item);
            dialog.addEventListener('close', () => back?.focus(), { once: true });
            openDialog(dialog);
            select.focus();
        });
    };

    const choose = (item: HTMLLIElement, entry: TreeMenuItem): void => {
        // Entries that open a field or a dialog move the focus there; the others give it back to the row.
        close(!['rename', 'icon', 'child', 'above', 'below', 'moveto', 'archive'].includes(entry));
        const link = linkOf(item) as HTMLAnchorElement;
        switch (entry) {
            case 'open':
                view.open(link.href, '_blank', 'noopener');
                break;
            case 'edit':
                view.location.assign(`${item.getAttribute('data-wiki-edit-href')}`);
                break;
            case 'rename':
                startRename(item);
                break;
            case 'icon':
                openIconDialog(item);
                break;
            case 'copy':
                copyLink(item);
                break;
            case 'duplicate':
                request(item, 'wikiTreeDuplicate', {}, (data) => view.location.assign(`${data.url}`));
                break;
            case 'up':
            case 'down':
                // The tree's Alt+arrow move: the move endpoint, the place in the tree and the announcement.
                link.dispatchEvent(new CustomEvent('nowo-ui:tree:move', { bubbles: true, cancelable: true, detail: { id: item.dataset.nowoUiTreeNode, direction: entry } }));
                break;
            case 'moveto':
                openMove(item);
                break;
            case 'archive':
                archive(item);
                break;
            case 'delete':
                remove(item);
                break;
            default:
                newPage(item, entry);
        }
    };

    const onMenuKey = (event: KeyboardEvent): void => {
        const list = entries();
        const at = list.indexOf(event.target as HTMLElement);
        const next = {
            ArrowDown: (at + 1) % list.length,
            ArrowUp: (at - 1 + list.length) % list.length,
            Home: 0,
            End: list.length - 1,
        }[event.key];
        if (next !== undefined) {
            event.preventDefault();
            list[next].focus();
        } else if (event.key === 'Escape' || event.key === 'Tab') {
            event.preventDefault();
            close(true);
        }
    };

    // 1.41: a long press on a row (touch) opens the menu as a right click does; moving the finger cancels it, and the
    // click that ends it does not follow the row's link.
    let press: { timer: number; x: number; y: number } | null = null;
    let swallowClick = false;
    const cancelPress = (): void => {
        if (press) {
            view.clearTimeout(press.timer);
            press = null;
        }
    };
    doc.addEventListener('pointerdown', (event) => {
        cancelPress();
        swallowClick = false;
        const item = event.pointerType === 'touch' && menu.isConnected ? rowItem(event.target) : null;
        if (!item) {
            return;
        }
        const { clientX: x, clientY: y } = event;
        press = {
            x,
            y,
            timer: view.setTimeout(() => {
                press = null;
                swallowClick = open(item, x, y, null);
            }, LONG_PRESS_MS),
        };
    });
    doc.addEventListener('pointermove', (event) => {
        if (press && Math.hypot(event.clientX - press.x, event.clientY - press.y) > LONG_PRESS_SLOP) {
            cancelPress();
        }
    });
    doc.addEventListener('pointerup', cancelPress);
    doc.addEventListener('pointercancel', cancelPress);
    doc.addEventListener('click', (event) => {
        if (swallowClick && event.target instanceof Element && rowItem(event.target)) {
            event.preventDefault();
        }
        swallowClick = false;
    }, true);

    doc.addEventListener('contextmenu', (event) => {
        cancelPress();
        const item = menu.isConnected ? rowItem(event.target) : null;
        // The rename field keeps the browser's own menu (copy / paste).
        if (!item || (event.target as Element).matches('.nowo-wiki-tree__rename')) {
            return;
        }
        // The ContextMenu key and Shift+F10 opened the menu on keydown already.
        if ((!menu.hidden && current === item) || open(item, event.clientX, event.clientY, null)) {
            event.preventDefault();
        }
    });

    doc.addEventListener('keydown', (event) => {
        if (!menu.isConnected) {
            return;
        }
        if (!menu.hidden && menu.contains(event.target as Node)) {
            onMenuKey(event);

            return;
        }
        if (event.key !== 'ContextMenu' && !(event.key === 'F10' && event.shiftKey)) {
            return;
        }
        const item = rowItem(event.target);
        if (!item || !(event.target instanceof Element)) {
            return;
        }
        const box = event.target.getBoundingClientRect();
        if (open(item, box.left, box.bottom, null, box.top)) {
            event.preventDefault();
        }
    });

    doc.addEventListener('click', (event) => {
        const target = menu.isConnected && event.target instanceof Element ? event.target : null;
        const more = target?.closest<HTMLElement>('[data-wiki-tree-menu-more]');
        const item = more?.closest<HTMLLIElement>(NODE);
        if (more && item) {
            event.preventDefault();
            if (!menu.hidden && current === item) {
                close(true);

                return;
            }
            const box = more.getBoundingClientRect();
            open(item, box.left, box.bottom, more, box.top);

            return;
        }
        const choice = target?.closest<HTMLElement>('[data-wiki-tree-menu-item]');
        if (choice && current && menu.contains(choice)) {
            choose(current, choice.dataset.wikiTreeMenuItem as TreeMenuItem);

            return;
        }
        if (!menu.hidden && !(target && menu.contains(target))) {
            close(false);
        }
    });

    refreshMenuButtons(doc, rendered);
}

/**
 * Puts the icon the server rendered (`icon_html`, escaped by Twig) in the row: replaces the row's icon, adds one before
 * the label, or removes it when the icon was cleared.
 *
 * @param item - The tree row.
 * @param icon - The stored value (null: none).
 * @param html - The server's rendering of the icon partial.
 */
export function updateRowIcon(item: HTMLLIElement, icon: string | null, html: string): void {
    item.setAttribute('data-wiki-icon', icon ?? '');
    const link = linkOf(item);
    if (!link) {
        return;
    }
    const old = link.querySelector(':scope .nowo-wiki-page-icon');
    const template = item.ownerDocument.createElement('template');
    template.innerHTML = html;
    const fresh = template.content.firstElementChild;
    if (!fresh) {
        old?.remove();

        return;
    }
    if (old) {
        old.replaceWith(fresh);
    } else {
        link.insertBefore(fresh, labelOf(item));
    }
}
