// 1.43 BC check: the page-card design tokens (--nowo-wiki-page-card-*, --nowo-wiki-read-max-width, --nowo-wiki-read-h{1,2,3}-weight)
// must not change a single computed value while no host sets them. jsdom resolves no var(), so the check is on the CSS:
// every var() of a new token is replaced by its fallback, and the rules that carry them must then equal the declarations
// of 1.42.0 (the baselines below are copied from that release).
import { describe, expect, it } from 'vitest';
import sourceCss from './wiki.css?raw';
import builtCss from '../../public/wiki.css?raw';
import usage from '../../../../docs/USAGE.md?raw';

const css = sourceCss.replace(/\/\*[\s\S]*?\*\//g, '');

const NEW_TOKEN = /--nowo-wiki-(?:page-card-[a-z-]+|read-max-width|read-h[123]-weight)/;

/** Replaces var(--new-token, fallback) by fallback (innermost first, so nested fallbacks resolve too). */
const resolveNewTokens = (value: string): string => {
    let out = value;
    for (let guard = 0; guard < 20; guard += 1) {
        const m = new RegExp(`var\\(${NEW_TOKEN.source},\\s*((?:[^()]|\\((?:[^()]|\\([^()]*\\))*\\))*)\\)`).exec(out);
        if (!m) break;
        out = out.slice(0, m.index) + m[1].trim() + out.slice(m.index + m[0].length);
    }
    return out.replace(/\s+/g, ' ').trim();
};

/** The declarations of every rule whose selector list is exactly `selector`, in order, with the new tokens resolved. */
const rules = (selector: string): Record<string, string>[] => {
    const found: Record<string, string>[] = [];
    const re = /([^{}]+)\{([^{}]*)\}/g;
    for (let m = re.exec(css); m; m = re.exec(css)) {
        const sel = m[1].replace(/@media[^{]*$/, '').replace(/\s+/g, ' ').trim();
        if (sel !== selector) continue;
        const decls: Record<string, string> = {};
        for (const d of m[2].split(/;(?![^(]*\))/)) {
            const i = d.indexOf(':');
            if (i > 0) decls[d.slice(0, i).trim()] = resolveNewTokens(d.slice(i + 1));
        }
        found.push(decls);
    }
    return found;
};

const swap = ':is(.nowo-wiki-read-swap, .nowo-wiki-edit-body) :is(.nowo-wiki-content, .nowo-milkdown .milkdown .ProseMirror)';

describe('page-card design tokens keep the 1.42.0 defaults', () => {
    it('card title', () => {
        const [base] = rules('.nowo-wiki-page-title');
        expect(base).toEqual({
            margin: '0 0 var(--nowo-wiki-page-title-gap, 0.75rem)',
            padding: '0',
            'font-family': 'var(--nowo-wiki-read-heading-font-family, inherit)',
            'font-size': 'var(--nowo-wiki-read-h1-size, 2em)',
            'font-weight': 'var(--nowo-wiki-read-heading-weight, 600)',
            'line-height': 'var(--nowo-wiki-read-heading-line-height, 1.25)',
            'overflow-wrap': 'anywhere',
        });
        const [framed, narrow] = rules('.nowo-wiki-card-frame > .nowo-wiki-page-title');
        expect(framed).toEqual({ 'padding-inline-end': 'calc(var(--nowo-wiki-card-toolbar-width, 12rem) + var(--nowo-wiki-card-toolbar-inset, 0.75rem))' });
        expect(narrow).toEqual({ 'padding-inline-end': '0' });
    });

    it('page head', () => {
        const [base] = rules('.nowo-wiki-page-head');
        expect(base).toMatchObject({ display: 'flex', gap: '0.25rem 0.75rem', padding: '0' });
        const heads = rules('.nowo-wiki-card-frame > .nowo-wiki-page-head');
        // Source order: the narrow-screen reset in the toolbar block, then the base rule, then its own narrow media rule.
        const framed = heads.find((d) => d['min-block-size'] === '2.5rem');
        const narrow = heads[heads.length - 1];
        expect(framed).toEqual({
            'padding-inline-end': 'calc(var(--nowo-wiki-card-toolbar-width, 12rem) + var(--nowo-wiki-card-toolbar-inset, 0.75rem))',
            'min-block-size': '2.5rem',
        });
        expect(narrow['min-block-size']).toBe('0');
    });

    it('page card keeps the UiKit card look (padding 0, 1px border, theme radius and surface, no shadow, no gap)', () => {
        const [card] = rules('.nowo-wiki-page-card');
        expect(card).not.toHaveProperty('display');
        expect(card).not.toHaveProperty('gap');
        expect(rules('.nowo-wiki-page-card > * + *')).toEqual([{ 'margin-block-start': '0' }]);
        expect(card).toMatchObject({
            padding: '0',
            border: '1px solid var(--nowo-ui-card-border, var(--nowo-ui-border))',
            'border-radius': 'var(--nowo-ui-card-radius, var(--nowo-ui-radius))',
            background: 'var(--nowo-ui-card-bg, var(--nowo-ui-surface))',
            'box-shadow': 'none',
            overflow: 'visible',
        });
        expect(rules('.nowo-ui-card.nowo-wiki-page-card')[0]).toEqual({ 'margin-block-end': '1rem' });
    });

    it('reading text', () => {
        const measure = 'var(--nowo-wiki-reading-width, var(--nowo-ui-reading-width, 75ch))';
        expect(rules(':is(.nowo-wiki-content)').length).toBe(0);
        const readers = css.match(/max-inline-size:[^;]+;/g) ?? [];
        expect(readers.map((r: string) => resolveNewTokens(r.slice('max-inline-size:'.length, -1)))).toEqual(expect.arrayContaining([measure]));
        expect(rules(`${swap} h1`)[0]['font-weight']).toBe('var(--nowo-wiki-read-heading-weight, 600)');
        expect(rules(`${swap} h2`)[0]['font-weight']).toBe('var(--nowo-wiki-read-heading-weight, 600)');
        expect(rules(`${swap} h3`)[0]['font-weight']).toBe('var(--nowo-wiki-read-heading-weight, 600)');
    });

    it('ships the built copy unchanged', () => {
        expect(builtCss).toBe(sourceCss);
    });
});

describe('every token is documented', () => {
    it('names each new token in docs/USAGE.md', () => {
        const used = new Set(sourceCss.match(new RegExp(NEW_TOKEN.source, 'g')));
        expect(used.size).toBeGreaterThan(15);
        expect([...used].filter((token) => !usage.includes(token))).toEqual([]);
    });
});
