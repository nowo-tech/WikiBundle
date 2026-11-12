/**
 * 1.31 (`comments.thread_list: uikit`): the thread list of the Milkdown thread component, mounted into the comments
 * panel (`[data-nowo-wiki-comments-mount]`), drawn with the host's UiKit classes and the wiki's translations, which
 * the server prints on the mount as `data-nowo-wiki-thread-list` (JSON, {@link ThreadListConfig}):
 *
 * - each list (`ul.nowo-milkdown-comments__list`) gets the UiKit `list` classes, each thread (`li.nowo-milkdown-comments__item`)
 *   the `list_item` classes;
 * - each count (`.nowo-milkdown-comments__count`) is a UiKit neutral badge reading the plural form of the page
 *   language ("1 comment", "3 comments"; `Intl.PluralRules` category, `#` replaced by the number), the number kept in
 *   `data-count`;
 * - the "Show resolved" box (`.nowo-milkdown-comments__toggle`) is a UiKit checkbox (`check`, `check_input`);
 * - a thread without a title (the component's "Open thread" fallback) reads `thread_label`.
 *
 * The component redraws its lists often (a new comment, a resolve, a live update), so a MutationObserver re-applies
 * the classes and texts; it only writes what differs, so its own writes settle at once. Only the component's DOM
 * inside the mount is touched (no innerHTML: texts are set as text). No-op without the attribute (plain list).
 */

/** The JSON of `data-nowo-wiki-thread-list`. */
export interface ThreadListConfig {
    list: string;
    item: string;
    badge: string;
    check: string;
    check_input: string;
    open_label: string;
    thread_label: string;
    count: Partial<Record<Intl.LDMLPluralRule, string>> & { other: string };
}

const ATTR = 'data-nowo-wiki-thread-list';

/**
 * Reads the mount's configuration; null when it is missing or not valid.
 *
 * @param mount - The comments mount.
 */
export function threadListConfig(mount: Element): ThreadListConfig | null {
    const raw = mount.getAttribute(ATTR);
    if (raw === null) {
        return null;
    }
    try {
        const value = JSON.parse(raw) as Partial<ThreadListConfig> | null;

        return value !== null && typeof value === 'object' && typeof value.count?.other === 'string' ? (value as ThreadListConfig) : null;
    } catch {
        return null;
    }
}

/**
 * The plural text of a count in the language `lang` (`#` → the number, formatted for the language).
 *
 * @param count - The number of comments.
 * @param forms - The forms per CLDR plural category (`other` required).
 * @param lang - A BCP 47 language tag (the page's `lang`; empty: the browser default).
 */
export function pluralText(count: number, forms: ThreadListConfig['count'], lang: string): string {
    let category: Intl.LDMLPluralRule = 'other';
    let number = String(count);
    try {
        const locale = lang !== '' ? lang : undefined;
        category = new Intl.PluralRules(locale).select(count);
        number = new Intl.NumberFormat(locale).format(count);
    } catch {
        // An invalid language tag: the `other` form with the plain number.
    }

    return (forms[category] ?? forms.other).replaceAll('#', number);
}

/** Adds the space-separated classes `list` to `el` that it does not have yet. */
const addClasses = (el: Element, list: string): void => {
    const missing = list.split(/\s+/).filter((name) => name !== '' && !el.classList.contains(name));
    if (missing.length > 0) {
        el.classList.add(...missing);
    }
};

/** Sets an element's text only when it differs (no mutation otherwise). */
const setText = (el: Element, text: string): void => {
    if (el.textContent !== text) {
        el.textContent = text;
    }
};

/**
 * Applies the UiKit classes and the wiki texts to the thread list inside `mount` once.
 *
 * @param mount - The comments mount.
 * @param config - Its configuration.
 * @param lang - The page language.
 */
export function decorateThreadList(mount: Element, config: ThreadListConfig, lang: string): void {
    mount.querySelectorAll('ul.nowo-milkdown-comments__list').forEach((list) => addClasses(list, config.list));
    mount.querySelectorAll('li.nowo-milkdown-comments__item').forEach((item) => addClasses(item, config.item));
    mount.querySelectorAll('.nowo-milkdown-comments__count').forEach((badge) => {
        // The component writes "{count} comments" (a plain number); once rewritten, the number is kept in data-count.
        const ours = badge.getAttribute('data-text');
        const count = ours !== null && badge.textContent === ours
            ? Number(badge.getAttribute('data-count'))
            : Number(/\d+/.exec(`${badge.textContent}`)?.[0] ?? 0);
        const text = pluralText(count, config.count, lang);
        addClasses(badge, config.badge);
        badge.setAttribute('data-count', String(count));
        badge.setAttribute('data-text', text);
        setText(badge, text);
    });
    mount.querySelectorAll('.nowo-milkdown-comments__card-text').forEach((label) => {
        if (label.textContent === config.open_label) {
            setText(label, config.thread_label);
        }
    });
    mount.querySelectorAll('label.nowo-milkdown-comments__toggle').forEach((toggle) => {
        addClasses(toggle, config.check);
        const box = toggle.querySelector('input[type="checkbox"]');
        if (box !== null) {
            addClasses(box, config.check_input);
        }
    });
}

/**
 * Decorates every configured mount under `root` now and after each redraw of the component.
 *
 * @param root - The document (or a subtree).
 * @param Observer - The MutationObserver class (null: decorate once, e.g. tests).
 * @returns The number of mounts decorated.
 */
export function bindThreadList(root: Document = document, Observer: typeof MutationObserver | null = globalThis.MutationObserver): number {
    const lang = root.documentElement.lang;
    let count = 0;
    root.querySelectorAll(`[${ATTR}]`).forEach((mount) => {
        const config = threadListConfig(mount);
        if (config === null) {
            return;
        }
        count += 1;
        decorateThreadList(mount, config, lang);
        if (Observer !== null) {
            new Observer(() => decorateThreadList(mount, config, lang)).observe(mount, { childList: true, subtree: true, characterData: true });
        }
    });

    return count;
}
