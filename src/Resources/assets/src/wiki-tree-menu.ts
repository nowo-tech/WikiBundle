/**
 * 1.39 (`tree.context_menu`): the page tree's context menu.
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
export type TreeMenuItem = 'rename' | 'icon' | 'child' | 'below' | 'above' | 'copy';

const NODE = 'li[data-nowo-ui-tree-node]';
const ITEMS: readonly TreeMenuItem[] = ['rename', 'icon', 'child', 'below', 'above', 'copy'];

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
    const has = (attr: string): boolean => item.hasAttribute(attr);
    const out = ITEMS.filter((entry) => {
        switch (entry) {
            case 'rename':
                return has('data-wiki-menu-edit');
            case 'icon':
                return has('data-wiki-menu-icon');
            case 'child':
                return has('data-wiki-new-child');
            case 'below':
            case 'above':
                return has('data-wiki-new-sibling');
            default:
                return false;
        }
    }).filter((entry) => rendered.includes(entry));

    return out.length > 0 && rendered.includes('copy') ? [...out, 'copy'] : out;
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

    const open = (item: HTMLLIElement, x: number, y: number, button: HTMLElement | null): boolean => {
        const allowed = allowedItems(item, rendered);
        if (allowed.length === 0) {
            return false;
        }
        close(false);
        menu.querySelectorAll<HTMLElement>('[data-wiki-tree-menu-item]').forEach((el) => {
            el.hidden = !allowed.includes(el.dataset.wikiTreeMenuItem as TreeMenuItem);
        });
        current = item;
        opener = button;
        button?.setAttribute('data-wiki-open', '');
        menu.hidden = false;
        const view = doc.defaultView as Window;
        menu.style.left = `${Math.max(0, Math.min(x, view.innerWidth - menu.offsetWidth - 8))}px`;
        menu.style.top = `${Math.max(0, Math.min(y, view.innerHeight - menu.offsetHeight - 8))}px`;
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

    const choose = (item: HTMLLIElement, entry: TreeMenuItem): void => {
        close(entry === 'copy');
        switch (entry) {
            case 'rename':
                startRename(item);
                break;
            case 'icon':
                openIconDialog(item);
                break;
            case 'copy':
                copyLink(item);
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

    doc.addEventListener('contextmenu', (event) => {
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
        if (open(item, box.left, box.bottom, null)) {
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
            open(item, box.left, box.bottom, more);

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
