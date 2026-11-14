/**
 * 1.35 (`web_ui.new_page_dialog`): the "New page above / below / as child" menu of the page tree and the new-page
 * dialog it opens.
 *
 * The server renders one ARIA menu (`[data-wiki-new-page-menu]`), the dialog (`[data-wiki-new-page-dialog]`, a native
 * `<dialog>` with the new-page form and its hidden `parent` / `position` fields) and, on each tree row, a hidden «…»
 * button. It marks the rows the user may create pages under (`data-wiki-new-child`); the top level takes new pages from
 * anyone who sees the menu. The server checks every create again.
 *
 * The menu opens from a right click on a row, the row's «…» button, or the ContextMenu key / Shift+F10 on a row. It
 * follows the WAI-ARIA menu pattern: focus on the first choice, arrows / Home / End move it (roving, wrapping), Enter /
 * Space choose, Escape and Tab close it and give the focus back to the row; a click elsewhere closes it. A choice fills
 * the dialog's place (1-based position among the row's siblings, or last among its children) and opens it modal.
 */

/** A choice of the menu. */
export type NewPageChoice = 'above' | 'below' | 'child';

/** Where the new page goes: the parent page id ('' = the top level) and the 1-based position among its children. */
export interface NewPagePlace {
    parent: string;
    position: number;
}

const NODE = 'li[data-nowo-ui-tree-node]';
const CHOICES: readonly NewPageChoice[] = ['above', 'below', 'child'];
const CHILD = 'data-wiki-new-child';

const nodesIn = (list: Element | null | undefined): HTMLLIElement[] =>
    list ? ([...list.children].filter((el) => el.matches(NODE)) as HTMLLIElement[]) : [];

const parentItem = (item: HTMLLIElement): HTMLLIElement | null => item.parentElement?.closest<HTMLLIElement>(NODE) ?? null;

const linkOf = (item: HTMLLIElement): HTMLElement | null => item.querySelector<HTMLElement>(':scope > .nowo-ui-tree__row > [data-nowo-ui-tree-item]');

/** The row's label (UiKit's label span, else the whole row's text). */
const titleOf = (item: HTMLLIElement): string =>
    `${(item.querySelector(':scope > .nowo-ui-tree__row .nowo-ui-tree__label') ?? item.querySelector(':scope > .nowo-ui-tree__row')!).textContent}`.trim();

/**
 * The choices the user may take on a row: "as child" when the row is marked, "above" / "below" when its parent is
 * marked or it sits at the top level.
 *
 * @param item - A tree row (`li[data-nowo-ui-tree-node]`).
 */
export function allowedChoices(item: HTMLLIElement): NewPageChoice[] {
    const parent = parentItem(item);
    const sibling = parent === null || parent.hasAttribute(CHILD);

    return CHOICES.filter((choice) => (choice === 'child' ? item.hasAttribute(CHILD) : sibling));
}

/**
 * The place of a new page next to (or under) a row, as the server counts it: the visible siblings, 1-based.
 *
 * @param item - The row the menu was opened on.
 * @param choice - Above or below the row, or last among its children.
 */
export function placeFor(item: HTMLLIElement, choice: NewPageChoice): NewPagePlace {
    if (choice === 'child') {
        return { parent: item.dataset.nowoUiTreeNode ?? '', position: nodesIn(item.querySelector(':scope > ul[role="group"]')).length + 1 };
    }

    return {
        parent: parentItem(item)?.dataset.nowoUiTreeNode ?? '',
        position: nodesIn(item.parentElement).indexOf(item) + (choice === 'above' ? 1 : 2),
    };
}

/**
 * Writes the place into the dialog's hidden fields and the line that says where the page goes (hidden when empty).
 *
 * @param dialog - The new-page dialog.
 * @param place - The place, or null to clear it (a new page as before: no parent, no position).
 * @param context - The sentence of the line ('' hides it).
 */
export function fillNewPageDialog(dialog: HTMLElement, place: NewPagePlace | null, context: string): void {
    const parent = dialog.querySelector<HTMLInputElement>('[data-wiki-new-page-parent]');
    const position = dialog.querySelector<HTMLInputElement>('[data-wiki-new-page-position]');
    if (parent) {
        parent.value = place?.parent ?? '';
    }
    if (position) {
        position.value = place ? String(place.position) : '';
    }
    const line = dialog.querySelector<HTMLElement>('[data-wiki-new-page-context]');
    if (line) {
        line.textContent = context;
        line.hidden = context === '';
    }
}

/** The tree row of an event target, when it is a row of a wiki page tree. */
const rowItem = (target: EventTarget | null): HTMLLIElement | null => {
    const row = target instanceof Element ? target.closest('.nowo-ui-tree__row') : null;

    return row?.closest('[data-wiki-tree]') ? row.closest<HTMLLIElement>(NODE) : null;
};

/** Shows the «…» button of the rows that have a choice, hides the others. */
export function refreshMoreButtons(root: ParentNode): void {
    root.querySelectorAll<HTMLElement>('[data-wiki-new-page-more]').forEach((button) => {
        const item = button.closest<HTMLLIElement>(NODE);
        button.hidden = item === null || allowedChoices(item).length === 0;
    });
}

const boundMenus = new WeakSet<HTMLElement>();

/**
 * Binds the menu and the dialog of the document (once per menu; nothing without both). The document listeners do
 * nothing once that menu has left the document (a body swapped by the host).
 *
 * @param doc - The document.
 */
export function bindNewPageMenu(doc: Document = document): void {
    const menu = doc.querySelector<HTMLElement>('[data-wiki-new-page-menu]');
    const dialog = doc.querySelector<HTMLDialogElement>('[data-wiki-new-page-dialog]');
    if (!menu || !dialog || boundMenus.has(menu)) {
        return;
    }
    boundMenus.add(menu);
    let current: HTMLLIElement | null = null;
    let opener: HTMLElement | null = null;
    let returnTo: HTMLElement | null = null;

    const choices = (): HTMLElement[] => [...menu.querySelectorAll<HTMLElement>('[data-wiki-new-page-choice]')].filter((el) => !el.hidden);

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
        const allowed = allowedChoices(item);
        if (allowed.length === 0) {
            return false;
        }
        close(false);
        menu.querySelectorAll<HTMLElement>('[data-wiki-new-page-choice]').forEach((el) => {
            el.hidden = !allowed.includes(el.dataset.wikiNewPageChoice as NewPageChoice);
        });
        current = item;
        opener = button;
        button?.setAttribute('data-wiki-open', '');
        menu.hidden = false;
        menu.style.left = `${Math.max(0, Math.min(x, window.innerWidth - menu.offsetWidth - 8))}px`;
        menu.style.top = `${Math.max(0, Math.min(y, window.innerHeight - menu.offsetHeight - 8))}px`;
        choices()[0]?.focus();

        return true;
    };

    const choose = (item: HTMLLIElement, choice: NewPageChoice): void => {
        const line = dialog.querySelector<HTMLElement>('[data-wiki-new-page-context]');
        const context = (line?.dataset[choice] ?? '').replace('%title%', titleOf(item));
        fillNewPageDialog(dialog, placeFor(item, choice), context);
        close(true);
        returnTo = linkOf(item);
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
        dialog.querySelector<HTMLElement>('input:not([type="hidden"]), textarea, [contenteditable="true"]')?.focus();
    };

    const onMenuKey = (event: KeyboardEvent): void => {
        const list = choices();
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
        if (!item) {
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
        const box = (event.target as Element).getBoundingClientRect();
        if (item && open(item, box.left, box.bottom, null)) {
            event.preventDefault();
        }
    });

    doc.addEventListener('click', (event) => {
        const target = menu.isConnected && event.target instanceof Element ? event.target : null;
        const more = target?.closest<HTMLElement>('[data-wiki-new-page-more]');
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
        const choice = target?.closest<HTMLElement>('[data-wiki-new-page-choice]');
        if (choice && current && menu.contains(choice)) {
            choose(current, choice.dataset.wikiNewPageChoice as NewPageChoice);

            return;
        }
        if (!menu.hidden && !(target && menu.contains(target))) {
            close(false);
        }
    });

    // Closed (Escape, Cancel, the close button): back to a page "as before" and the focus on the row that opened it.
    dialog.addEventListener('close', () => {
        fillNewPageDialog(dialog, null, '');
        returnTo?.focus();
        returnTo = null;
    });

    refreshMoreButtons(doc);
    // A tree move changes who is whose parent, so which rows have a choice.
    doc.querySelectorAll('[data-wiki-tree]').forEach((tree) => {
        new MutationObserver(() => refreshMoreButtons(tree)).observe(tree, { childList: true, subtree: true });
    });
}
