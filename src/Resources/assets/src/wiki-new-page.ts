/**
 * 1.35 (`web_ui.new_page_dialog`): the "New page above / below / as child" menu of the page tree and the new-page
 * dialog it opens.
 *
 * The server renders one ARIA menu (`[data-wiki-new-page-menu]`), the dialog (`[data-wiki-new-page-dialog]`, a native
 * `<dialog>` with the new-page form and its hidden `place` field) and, on each tree row, a hidden «…» button. It marks
 * what each row offers (`data-wiki-new-child`: "as child"; `data-wiki-new-sibling`: "above" / "below"). The browser
 * sends the intent (`child:<id>`, `above:<id>`, `below:<id>`), never an index: the server computes the place and checks
 * every create again (audit PT-L3).
 *
 * The menu opens from a right click on a row, the row's «…» button, or the ContextMenu key / Shift+F10 on a row. It
 * follows the WAI-ARIA menu pattern: focus on the first choice, arrows / Home / End move it (roving, wrapping), Enter /
 * Space choose, Escape and Tab close it and give the focus back to the row; a click elsewhere closes it. A choice fills
 * the dialog's place and opens it modal.
 */

/** A choice of the menu. */
export type NewPageChoice = 'above' | 'below' | 'child';

const NODE = 'li[data-nowo-ui-tree-node]';
const CHOICES: readonly NewPageChoice[] = ['above', 'below', 'child'];
const CHILD = 'data-wiki-new-child';
const SIBLING = 'data-wiki-new-sibling';

const linkOf = (item: HTMLLIElement): HTMLElement | null => item.querySelector<HTMLElement>(':scope > .nowo-ui-tree__row > [data-nowo-ui-tree-item]');

/** The row's label (UiKit's label span, else the whole row's text). */
const titleOf = (item: HTMLLIElement): string =>
    `${(item.querySelector(':scope > .nowo-ui-tree__row .nowo-ui-tree__label') ?? item.querySelector(':scope > .nowo-ui-tree__row')!).textContent}`.trim();

/**
 * The choices the server marked on a row: "above" / "below" (`data-wiki-new-sibling`) and "as child"
 * (`data-wiki-new-child`).
 *
 * @param item - A tree row (`li[data-nowo-ui-tree-node]`).
 */
export function allowedChoices(item: HTMLLIElement): NewPageChoice[] {
    return CHOICES.filter((choice) => item.hasAttribute(choice === 'child' ? CHILD : SIBLING));
}

/**
 * The intent the form sends for a choice on a row (`child:<id>`, `above:<id>`, `below:<id>`).
 *
 * @param item - The row the menu was opened on.
 * @param choice - Above or below the row, or last among its children.
 */
export function placeFor(item: HTMLLIElement, choice: NewPageChoice): string {
    return `${choice}:${item.dataset.nowoUiTreeNode ?? ''}`;
}

/**
 * Writes the place into the dialog's hidden field and the line that says where the page goes (hidden when empty).
 *
 * @param dialog - The new-page dialog.
 * @param place - The intent, or '' to clear it (a new page as before: no place).
 * @param context - The sentence of the line ('' hides it).
 */
export function fillNewPageDialog(dialog: HTMLElement, place: string, context: string): void {
    const field = dialog.querySelector<HTMLInputElement>('[data-wiki-new-page-place]');
    if (field) {
        field.value = place;
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
        // A function: a `$` in a title is text, not a replacement pattern (audit PT-I2).
        const context = (line?.dataset[choice] ?? '').replace('%title%', () => titleOf(item));
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
        fillNewPageDialog(dialog, '', '');
        returnTo?.focus();
        returnTo = null;
    });

    refreshMoreButtons(doc);
}
