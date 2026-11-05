import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    DEFAULT_CODE_LABEL,
    MARK_ATTR,
    RECHECK_DELAY_MS,
    codeLabel,
    markScrollableCode,
    observeScrollableCode,
    overflowsHorizontally,
} from './wiki-code-blocks';

/** jsdom has no layout: a block "overflows" when it carries data-wide. */
const wide = (el: Element): boolean => el.hasAttribute('data-wide');

describe('focusable code blocks (KIT-71)', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.useRealTimers();
    });

    it('measures the horizontal overflow', () => {
        const pre = document.createElement('pre');
        Object.defineProperty(pre, 'clientWidth', { value: 100 });
        Object.defineProperty(pre, 'scrollWidth', { value: 101, configurable: true });
        expect(overflowsHorizontally(pre)).toBe(false);
        Object.defineProperty(pre, 'scrollWidth', { value: 102 });
        expect(overflowsHorizontally(pre)).toBe(true);
    });

    it('makes overflowing blocks focusable and labelled, and leaves the others alone', () => {
        document.body.innerHTML = `
            <pre id="a" data-wide><code>long</code></pre>
            <pre id="b"><code>short</code></pre>
            <pre id="c" data-wide aria-label="Shell session"><code>long</code></pre>
            <pre id="d" data-wide tabindex="-1"><code>long</code></pre>
            <pre id="e" data-wide role="figure"><code>long</code></pre>
            <div contenteditable="true"><pre id="f" data-wide>edited</pre></div>
            <div contenteditable="false"><pre id="g" data-wide>read only</pre></div>
        `;

        expect(markScrollableCode(document, 'Bloque de código', wide)).toBe(4);

        const a = document.getElementById('a') as HTMLElement;
        expect(a.getAttribute('tabindex')).toBe('0');
        expect(a.getAttribute('aria-label')).toBe('Bloque de código');
        expect(a.getAttribute('role')).toBe('group');
        expect(document.getElementById('b')?.hasAttribute('tabindex')).toBe(false);
        const c = document.getElementById('c') as HTMLElement;
        expect(c.getAttribute('tabindex')).toBe('0');
        expect(c.getAttribute('aria-label')).toBe('Shell session');
        expect(c.hasAttribute('role')).toBe(false);
        expect(document.getElementById('d')?.getAttribute('tabindex')).toBe('-1');
        expect(document.getElementById('d')?.hasAttribute(MARK_ATTR)).toBe(false);
        expect(document.getElementById('e')?.getAttribute('role')).toBe('figure');
        expect(document.getElementById('f')?.hasAttribute('tabindex')).toBe(false);
        expect(document.getElementById('g')?.getAttribute('tabindex')).toBe('0');

        // A second pass changes nothing.
        expect(markScrollableCode(document, 'Bloque de código', wide)).toBe(4);
        expect(a.getAttribute('tabindex')).toBe('0');
    });

    it('undoes only its own attributes when a block stops overflowing', () => {
        document.body.innerHTML = `
            <pre id="a" data-wide>x</pre>
            <pre id="c" data-wide aria-label="Mine">x</pre>
            <pre id="e" data-wide role="figure">x</pre>
        `;
        markScrollableCode(document, DEFAULT_CODE_LABEL, wide);
        document.querySelectorAll('pre').forEach((pre) => pre.removeAttribute('data-wide'));

        expect(markScrollableCode(document, DEFAULT_CODE_LABEL, wide)).toBe(0);

        const a = document.getElementById('a') as HTMLElement;
        expect(a.hasAttribute('tabindex')).toBe(false);
        expect(a.hasAttribute('aria-label')).toBe(false);
        expect(a.hasAttribute('role')).toBe(false);
        expect(a.hasAttribute(MARK_ATTR)).toBe(false);
        const c = document.getElementById('c') as HTMLElement;
        expect(c.hasAttribute('tabindex')).toBe(false);
        expect(c.getAttribute('aria-label')).toBe('Mine');
        const e = document.getElementById('e') as HTMLElement;
        expect(e.getAttribute('role')).toBe('figure');
        expect(e.hasAttribute('aria-label')).toBe(false);
    });

    it('checks the root itself when it is a pre, with the default label and measurement', () => {
        const pre = document.createElement('pre');
        Object.defineProperty(pre, 'clientWidth', { value: 10 });
        Object.defineProperty(pre, 'scrollWidth', { value: 500 });
        document.body.append(pre);

        expect(markScrollableCode(pre)).toBe(1);
        expect(pre.getAttribute('aria-label')).toBe(DEFAULT_CODE_LABEL);
        expect(markScrollableCode()).toBe(1);
    });

    it('reads the label from the script element', () => {
        const script = document.createElement('script');
        expect(codeLabel(script)).toBe(DEFAULT_CODE_LABEL);
        script.dataset.codeLabel = '  Codeblok ';
        expect(codeLabel(script)).toBe('Codeblok');
        expect(codeLabel(null)).toBe(DEFAULT_CODE_LABEL);
    });

    it('picks up blocks rendered after load and re-checks on resize', async () => {
        vi.useFakeTimers();
        document.body.innerHTML = '<pre id="first" data-wide>x</pre>';
        const stop = observeScrollableCode('Code', document, window, wide);
        expect(document.getElementById('first')?.getAttribute('tabindex')).toBe('0');

        // A comment rendered later (comments panel, preview).
        const panel = document.createElement('div');
        panel.innerHTML = '<p>Reply</p><pre id="late" data-wide><code>long</code></pre>';
        document.body.append(panel);
        await Promise.resolve();
        // Another change before the pass runs joins the queued pass.
        document.body.append(document.createElement('span'));
        await Promise.resolve();
        vi.advanceTimersByTime(RECHECK_DELAY_MS);
        expect(document.getElementById('late')?.getAttribute('tabindex')).toBe('0');
        expect(document.getElementById('late')?.getAttribute('aria-label')).toBe('Code');

        // A removal alone schedules nothing; a resize does.
        document.getElementById('late')?.removeAttribute('data-wide');
        panel.querySelector('p')?.remove();
        await Promise.resolve();
        vi.advanceTimersByTime(RECHECK_DELAY_MS);
        expect(document.getElementById('late')?.getAttribute('tabindex')).toBe('0');
        window.dispatchEvent(new Event('resize'));
        vi.advanceTimersByTime(RECHECK_DELAY_MS);
        expect(document.getElementById('late')?.hasAttribute('tabindex')).toBe(false);

        stop();
        document.body.append(Object.assign(document.createElement('pre'), { id: 'after' }));
        document.getElementById('after')?.setAttribute('data-wide', '');
        window.dispatchEvent(new Event('resize'));
        await Promise.resolve();
        vi.advanceTimersByTime(RECHECK_DELAY_MS);
        expect(document.getElementById('after')?.hasAttribute('tabindex')).toBe(false);
    });

    it('starts with the defaults', () => {
        document.body.innerHTML = '<pre>x</pre>';
        const stop = observeScrollableCode();
        expect(document.querySelector('pre')?.hasAttribute('tabindex')).toBe(false);
        stop();
    });
});
