import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * REQ-DEMO-014 — the demo runs in UiKit's demo platform shell: layout grammar, no demo styles / host tokens / framework
 * classes, closed drawer inert, 44 px targets on coarse pointers, input borders 3:1 (same rules as UiKit's own spec),
 * the phone drawer by keyboard, and axe (wcag2a / 2aa / 21a / 21aa; serious + critical = 0) in light and dark.
 * Pages that need data are listed with the path the demo fixtures provide.
 */
const pages: Array<{ name: string; path: string; auth?: boolean }> = [
  { name: "spaces", path: "/tools/wiki" },
  { name: "space", path: "/tools/wiki/spaces/engineering" },
  { name: "page", path: "/tools/wiki/spaces/engineering/pages/deploy" },
  { name: "page-edit", path: "/tools/wiki/spaces/engineering/pages/deploy/edit" },
  { name: "history", path: "/tools/wiki/spaces/engineering/pages/deploy/history" },
  { name: "search", path: "/tools/wiki/search?q=deploy" },
  { name: "ask", path: "/tools/wiki/ask" },
  { name: "trash", path: "/tools/wiki/spaces/engineering/trash" },
];

/**
 * Findings of pages rendered by a bundle's own templates (bundle-owned: reported in the PR, fixed in the bundle, never
 * patched in the demo). Everything else must be clean. Each entry: the page name and the issue it may report.
 */
const bundleOwned: Array<[string, RegExp]> = [
  ["space", /target-under-44px:(nowo-wiki-tree__more|input)/],
  ["page", /target-under-44px:nowo-wiki-tree__more/],
  ["page-edit", /target-under-44px:(nowo-wiki-title-input|icon-selector-[a-z-]+|nowo-milkdown__action|input|nowo-wiki-tree__more)/],
  ["page-edit", /input-border-under-3:1:.*/],
  ["page-edit", /color-contrast: #wiki_page_form_title/],
  ["ask", /target-under-44px:input/],
  ["ask", /color-contrast: #wiki-ai-space/],
  ["space", /color-contrast: #wiki-space-search/],
];

/** Drops the documented bundle-owned findings of a page (and records them as test annotations). */
function unowned(issues: string[], pageName: string): string[] {
  return issues.filter((issue) => {
    const owned = bundleOwned.some(([name, re]) => name === pageName && re.test(issue));
    if (owned) test.info().annotations.push({ type: 'bundle-owned', description: `${pageName}: ${issue}` });
    return !owned;
  });
}

/** Signs in for the pages that need a user (empty when the demo has no sign-in). */
async function signIn(page: Page): Promise<void> {
  // The demo signs the visitor in automatically (DemoAutoLoginAuthenticator).
}

async function open(page: Page, p: { path: string; auth?: boolean }): Promise<void> {
  if (p.auth) await signIn(page);
  await page.goto(p.path);
  await page.waitForLoadState('load');
}

// Demo platform layout + style rules, evaluated in the page (same code as the ROLLOUT-BRIEF spec template and UiKit's
// demo/symfony8/e2e/tests/demo-platform.spec.ts). Returns a list of issue codes; [] = clean.
function demoPlatformRules(): string[] {
  const issues: string[] = [];
  const main = document.querySelector('#nowo-ui-content');
  const shell = document.querySelector('[data-testid="demo-platform"]');
  if (!main || !shell) return ['no-demo-platform-shell'];
  const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const carded = (el) => el.closest('.nowo-ui-card, [data-nowo-ui-region="table"], [data-nowo-ui-region="form"], .nowo-ui-form-panel, dialog');
  const skip = (el) => el.closest('dialog, [data-nowo-ui-region="filters"], .nowo-ui-filters, .nowo-ui-page-header');

  // Layout grammar.
  main.querySelectorAll('table').forEach((t) => { if (!carded(t)) issues.push('table-outside-card'); });
  main.querySelectorAll('form').forEach((f) => {
    if (f.querySelector('input:not([type="hidden"]), select, textarea') && !carded(f) && !skip(f) && !f.closest('table')) issues.push('form-outside-card');
  });
  const primaries = [...main.querySelectorAll('.nowo-ui-btn--primary')].filter((b) => visible(b) && !b.closest('table, dialog, [data-nowo-ui-region="filters"], .nowo-ui-filters, form'));
  if (primaries.length > 1) issues.push(`primary-buttons:${primaries.length}`);
  const header = main.querySelector('.nowo-ui-page-header');
  const flashes = main.querySelector('.nowo-ui-flashes');
  if (header && flashes && (flashes.compareDocumentPosition(header) & Node.DOCUMENT_POSITION_FOLLOWING)) issues.push('flashes-before-page-header');
  if (document.documentElement.scrollWidth > document.documentElement.clientWidth + 1) issues.push('page-scrolls-horizontally');

  // Styles: no demo styles, no host theme tokens, no CDN / foreign stylesheet, no framework classes inside the shell.
  document.querySelectorAll('style').forEach((s) => {
    if (s.matches('[data-nowo-ui-theme-tokens], [data-nowo-ui-appearance]')) { issues.push('host-theme-tokens'); return; }
    if (!/sf-toolbar|sfwdt|sf-minitoolbar|sf-display/.test(s.textContent || '') && !s.closest('[id^="sfwdt"], .sf-toolbar')) issues.push('style-element');
  });
  document.querySelectorAll('link[rel="stylesheet"]').forEach((l) => {
    const path = new URL(l.href, location.href).pathname;
    if (!path.startsWith('/bundles/') && !path.startsWith('/_wdt/') && !path.startsWith('/_profiler/')) issues.push(`stylesheet:${path}`);
  });
  const framework = /^(acme-.*|nav-tabs|nav-link|nav-item|alert|alert-.*|form-check.*|form-control|form-select|form-label|btn|btn-.*|card|card-.*|badge|row|col|col-.*|table|table-.*)$/;
  shell.querySelectorAll('[class]').forEach((el) => {
    for (const c of el.classList) { if (framework.test(c)) { issues.push(`framework-class:${c}`); break; } }
  });

  // Drawer: below 901 px a closed drawer is inert and the burger says so.
  const aside = shell.querySelector('[data-nowo-ui-aside]');
  if (aside && window.innerWidth < 901) {
    const open = shell.classList.contains('is-aside-open-mobile');
    if (!open && !aside.hasAttribute('inert')) issues.push('drawer-not-inert');
    shell.querySelectorAll('[data-nowo-ui-burger]').forEach((b) => {
      if (visible(b) && b.getAttribute('aria-expanded') !== (open ? 'true' : 'false')) issues.push('burger-state');
    });
  }

  // Touch: 44 px targets on coarse pointers (checkbox / radio 24 px, their label carries the 44 px row).
  if (window.matchMedia('(pointer: coarse)').matches) {
    const controls = [...shell.querySelectorAll('.nowo-ui-btn, button, summary, input:not([type="hidden"]), select, textarea, [role="tab"], .nowo-ui-aside__link, .nowo-ui-demo-platform__locale')]
      .filter((el) => visible(el) && !el.closest('[inert], table'));
    for (const el of controls) {
      const r = el.getBoundingClientRect();
      const small = el.matches('input[type="checkbox"], input[type="radio"]') ? r.height < 24 || r.width < 24 : r.height < 43.5;
      if (small) { issues.push(`target-under-44px:${(el.className && String(el.className).split(' ')[0]) || el.tagName.toLowerCase()}`); }
    }
  }

  // Inputs: boundary 3:1 or more against the surface behind them (WCAG 1.4.11).
  const rgb = (c) => { const m = c.match(/[\d.]+/g); return m ? m.slice(0, 4).map(Number) : null; };
  const lum = (c) => { const v = c.slice(0, 3).map((x) => { const s = x / 255; return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4; }); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; };
  const bgOf = (el) => { for (let n = el.parentElement; n; n = n.parentElement) { const c = rgb(getComputedStyle(n).backgroundColor); if (c && (c.length < 4 || c[3] > 0)) return c; } return [255, 255, 255]; };
  shell.querySelectorAll('input:not([type="hidden"], [type="checkbox"], [type="radio"]), select, textarea').forEach((el) => {
    if (!visible(el) || el.closest('[inert]')) return;
    const border = rgb(getComputedStyle(el).borderTopColor);
    if (!border) return;
    const a = lum(border); const b = lum(bgOf(el));
    if ((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05) < 3) issues.push(`input-border-under-3:1:${el.id || el.name || el.tagName.toLowerCase()}`);
  });

  return [...new Set(issues)];
}

type Theme = 'light' | 'dark';

async function setTheme(page: Page, theme: Theme): Promise<void> {
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
  }, theme);
}

async function axe(page: Page): Promise<string[]> {
  const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).exclude('.sf-toolbar').exclude('[id^="sfwdt"]').analyze();
  return result.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')}`);
}

test.describe('Demo platform rules', () => {
  test.describe('desktop 1440 px', () => {
    test.use({ viewport: { width: 1440, height: 900 } });
    for (const p of pages) {
      test(`rules: ${p.name}`, async ({ page }) => {
        await open(page, p);
        expect(unowned(await page.evaluate(demoPlatformRules), p.name)).toEqual([]);
      });
      for (const theme of ['light', 'dark'] as Theme[]) {
        test(`axe: ${p.name}, ${theme}`, async ({ page }) => {
          await open(page, p);
          await setTheme(page, theme);
          expect(unowned(await axe(page), p.name)).toEqual([]);
        });
      }
    }
  });

  test.describe('phone 390 px (touch)', () => {
    test.use({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    for (const p of pages) {
      test(`rules: ${p.name}`, async ({ page }) => {
        await open(page, p);
        expect(unowned(await page.evaluate(demoPlatformRules), p.name)).toEqual([]);
      });
      for (const theme of ['light', 'dark'] as Theme[]) {
        test(`axe: ${p.name}, ${theme}`, async ({ page }) => {
          await open(page, p);
          await setTheme(page, theme);
          expect(unowned(await axe(page), p.name)).toEqual([]);
        });
      }
    }

    test('drawer by keyboard: Enter opens and moves focus in, Escape closes and returns to the burger', async ({ page }) => {
      await open(page, pages[0]);
      const burger = page.locator('[data-nowo-ui-burger]:visible').first();
      await expect(burger).toHaveAttribute('aria-expanded', 'false');
      await burger.focus();
      await page.keyboard.press('Enter');
      await expect(burger).toHaveAttribute('aria-expanded', 'true');
      await expect.poll(() => page.evaluate(() => !!document.activeElement?.closest('[data-nowo-ui-aside]'))).toBe(true);
      await expect(page.locator('.nowo-ui-main')).toHaveAttribute('inert', '');
      await expect(page.locator('html')).toHaveClass(/nowo-ui-drawer-open/);
      await page.keyboard.press('Escape');
      await expect(burger).toBeFocused();
      await expect(page.locator('[data-nowo-ui-aside]')).toHaveAttribute('inert', '');
      await expect(page.locator('html')).not.toHaveClass(/nowo-ui-drawer-open/);
    });
  });
});
