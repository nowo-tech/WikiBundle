import { afterEach, describe, expect, it } from 'vitest';
import { bindThreadList, decorateThreadList, pluralText, threadListConfig, type ThreadListConfig } from './wiki-comments-list';

const CONFIG: ThreadListConfig = {
    list: 'nowo-ui-list list-group m-0',
    item: 'nowo-ui-list-item list-group-item',
    badge: 'nowo-ui-badge nowo-ui-badge--neutral',
    check: 'nowo-ui-check',
    check_input: 'nowo-ui-check__input',
    open_label: 'Open thread',
    thread_label: 'Comment thread',
    count: { one: '# comment', other: '# comments' },
};

/** The thread component's list as Milkdown draws it (two threads, the "Show resolved" box). */
const COMPONENT = `
<div class="nowo-milkdown-comments nowo-milkdown-comments--container">
  <div class="nowo-milkdown-comments__head"><label class="nowo-milkdown-comments__toggle"><input type="checkbox"> Show resolved</label></div>
  <ul class="nowo-milkdown-comments__list">
    <li class="nowo-milkdown-comments__item"><button class="nowo-milkdown-comments__card"><span class="nowo-milkdown-comments__card-text">Open thread</span><span class="nowo-milkdown-comments__count">1 comments</span></button></li>
    <li class="nowo-milkdown-comments__item"><button class="nowo-milkdown-comments__card"><span class="nowo-milkdown-comments__card-text">Ana</span><span class="nowo-milkdown-comments__count">3 comments</span></button></li>
  </ul>
  <ul class="nowo-milkdown-comments__list nowo-milkdown-comments__list--page"></ul>
</div>`;

const mount = (config: unknown = CONFIG): HTMLElement => {
    const el = document.createElement('div');
    el.setAttribute('data-nowo-wiki-comments-mount', '');
    if (config !== null) {
        el.setAttribute('data-nowo-wiki-thread-list', typeof config === 'string' ? config : JSON.stringify(config));
    }
    el.innerHTML = COMPONENT;
    document.body.append(el);

    return el;
};

afterEach(() => {
    document.body.replaceChildren();
    document.documentElement.lang = '';
});

describe('threadListConfig', () => {
    it('reads the JSON of the mount', () => {
        expect(threadListConfig(mount())).toEqual(CONFIG);
    });

    it('is null without the attribute, with invalid JSON or without the other form', () => {
        const plain = document.createElement('div');
        expect(threadListConfig(plain)).toBeNull();
        expect(threadListConfig(mount('{not json'))).toBeNull();
        expect(threadListConfig(mount('null'))).toBeNull();
        expect(threadListConfig(mount({ ...CONFIG, count: { one: '# x' } }))).toBeNull();
    });
});

describe('pluralText', () => {
    it('picks the CLDR category of the language', () => {
        expect(pluralText(1, CONFIG.count, 'en')).toBe('1 comment');
        expect(pluralText(2, CONFIG.count, 'en')).toBe('2 comments');
        expect(pluralText(0, CONFIG.count, 'fr')).toBe('0 comment');
        expect(pluralText(1, CONFIG.count, '')).toBe('1 comment');
    });

    it('formats the number for the language and falls back to other', () => {
        expect(pluralText(5, { other: '# comentarios' }, 'es')).toBe('5 comentarios');
        expect(pluralText(12345, { other: '# comments' }, 'en')).toBe('12,345 comments');
        expect(pluralText(1, { other: '# comments' }, 'en')).toBe('1 comments');
    });

    it('uses the other form and the plain number with an invalid language tag', () => {
        expect(pluralText(1, CONFIG.count, 'not a tag!')).toBe('1 comments');
    });
});

describe('decorateThreadList', () => {
    it('gives the component UiKit rows, badges with plural counts and a UiKit checkbox', () => {
        const el = mount();
        decorateThreadList(el, CONFIG, 'en');

        el.querySelectorAll('ul').forEach((list) => expect(list.classList.contains('nowo-ui-list')).toBe(true));
        const items = el.querySelectorAll('li');
        expect(items[0].className).toBe('nowo-milkdown-comments__item nowo-ui-list-item list-group-item');
        const badges = [...el.querySelectorAll('.nowo-milkdown-comments__count')];
        expect(badges.map((b) => b.textContent)).toEqual(['1 comment', '3 comments']);
        expect(badges[0].classList.contains('nowo-ui-badge--neutral')).toBe(true);
        expect(badges[1].getAttribute('data-count')).toBe('3');
        expect(el.querySelector('.nowo-milkdown-comments__card-text')?.textContent).toBe('Comment thread');
        expect(el.querySelectorAll('.nowo-milkdown-comments__card-text')[1].textContent).toBe('Ana');
        expect(el.querySelector('label')?.classList.contains('nowo-ui-check')).toBe(true);
        expect(el.querySelector('input')?.classList.contains('nowo-ui-check__input')).toBe(true);
    });

    it('is stable: a second pass changes nothing, a redraw is decorated again', () => {
        const el = mount();
        decorateThreadList(el, CONFIG, 'en');
        const before = el.innerHTML;
        decorateThreadList(el, CONFIG, 'en');
        expect(el.innerHTML).toBe(before);

        // The component redraws a count with its own text: read again.
        const badge = el.querySelectorAll('.nowo-milkdown-comments__count')[1];
        badge.textContent = '4 comments';
        decorateThreadList(el, CONFIG, 'en');
        expect(badge.textContent).toBe('4 comments');
        expect(badge.getAttribute('data-count')).toBe('4');
        badge.textContent = 'no number';
        decorateThreadList(el, CONFIG, 'en');
        expect(badge.textContent).toBe('0 comments');
    });

    it('keeps a toggle without a box', () => {
        const el = mount();
        el.querySelector('input')?.remove();
        decorateThreadList(el, CONFIG, 'en');
        expect(el.querySelector('label')?.classList.contains('nowo-ui-check')).toBe(true);
    });
});

describe('bindThreadList', () => {
    it('decorates configured mounts now and after each redraw', async () => {
        document.documentElement.lang = 'en';
        const el = mount();
        mount('{bad');
        expect(bindThreadList(document)).toBe(1);
        expect(el.querySelector('.nowo-milkdown-comments__count')?.textContent).toBe('1 comment');

        const list = el.querySelector('ul') as HTMLUListElement;
        list.insertAdjacentHTML('beforeend', '<li class="nowo-milkdown-comments__item"><button><span class="nowo-milkdown-comments__count">2 comments</span></button></li>');
        await new Promise((resolve) => setTimeout(resolve, 0));
        const added = list.lastElementChild as HTMLElement;
        expect(added.classList.contains('nowo-ui-list-item')).toBe(true);
        expect(added.querySelector('.nowo-milkdown-comments__count')?.classList.contains('nowo-ui-badge')).toBe(true);
    });

    it('decorates once without MutationObserver and ignores plain mounts', () => {
        const el = mount();
        mount(null);
        expect(bindThreadList(document, null)).toBe(1);
        expect(el.querySelector('.nowo-milkdown-comments__count')?.textContent).toBe('1 comment');
    });
});
