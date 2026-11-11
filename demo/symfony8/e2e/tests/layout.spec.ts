import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page } from '@playwright/test';

/**
 * 1.29 (block A, layout) — the demo turns every option on (config/packages/nowo_wiki.yaml): the tree rail, the tree
 * after the page on phones, the readable column, the compact header with the title dedupe, and the comments panel
 * docked only from 1200 px (comments.dock_min_width: lg). Checked at 390, 1024 and 1280 px: wide code and tables never
 * scroll the page sideways, the rail folds / unfolds with the keyboard and is remembered, the panel is a drawer below
 * 1200 px (focus in and back, Escape) and a column above.
 */
const deploy = '/tools/wiki/spaces/engineering/pages/deploy';
const roadmap = '/tools/wiki/spaces/product/pages/roadmap';

const longLine = `kubectl rollout restart deployment/${'very-long-service-name-'.repeat(20)}api --namespace=production`;
// 48 columns: wider than any column even though the body breaks long words anywhere (overflow-wrap).
// Wrapped as the server wraps every table of a body (nowo_wiki_scroll_tables, audit MA-L1).
const wideTable = `<div class="nowo-wiki-table-scroll" data-nowo-ui-scroll-region data-nowo-ui-scroll-label="Table"><table><thead><tr>${Array.from({ length: 48 }, (_, i) => `<th>C${i + 1}</th>`).join('')}</tr></thead><tbody><tr>${Array.from({ length: 48 }, (_, i) => `<td>${i + 1}</td>`).join('')}</tr></tbody></table></div>`;

/** Serves the page with a 300+ character code line and a 48-column table at the top of its body. */
async function withWideContent(page: Page, url: string): Promise<void> {
  await page.route(`**${url}`, async (route) => {
    const response = await route.fetch();
    const html = (await response.text()).replace(/(data-testid="wiki-content"[^>]*>)/, `$1<pre data-e2e-wide-code><code>${longLine}</code></pre>${wideTable}`);
    await route.fulfill({ response, body: html });
  });
}

/** Expands the tree again if a previous test (or run) left it folded (the state is remembered per user). */
async function startExpanded(page: Page): Promise<void> {
  const host = page.locator('[data-wiki-sidebar]');
  if ((await host.getAttribute('data-nowo-ui-rail')) === 'collapsed') {
    await Promise.all([page.waitForResponse((r) => r.url().includes('/panel-state')), page.getByTestId('wiki-tree-rail-toggle').click()]);
  }
  await expect(host).toHaveAttribute('data-nowo-ui-rail', 'expanded');
}

const pageScrollsSideways = (page: Page): Promise<boolean> =>
  page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);

test.describe.configure({ mode: 'serial' });

for (const width of [390, 1024, 1280]) {
  test.describe(`Wiki layout at ${width} px`, () => {
    test.use({ viewport: { width, height: 900 } });

    test('wide code and tables scroll in their own box, never the page', async ({ page }) => {
      await withWideContent(page, deploy);
      await page.goto(deploy);
      await page.waitForLoadState('load');
      const code = page.locator('[data-e2e-wide-code]');
      await expect(code).toBeVisible();
      expect(await pageScrollsSideways(page)).toBe(false);
      expect(await code.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true);
      // The table keeps its table display (semantics); its scroll box scrolls and is a labelled, focusable region.
      const box = page.getByTestId('wiki-content').locator('.nowo-wiki-table-scroll').first();
      const table = box.locator('table');
      expect(await table.evaluate((el) => getComputedStyle(el).display)).toBe('table');
      expect(await box.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true);
      await expect(code).toHaveAttribute('tabindex', '0');
      await expect(box).toHaveAttribute('tabindex', '0');
      await expect(box).toHaveAttribute('role', 'region');
      await expect(box).toHaveAccessibleName('Table');
      // Every table of the page body (the seeded ones too) sits in a box.
      const content = page.getByTestId('wiki-content');
      expect(await content.locator('table').count()).toBe(await content.locator('.nowo-wiki-table-scroll > table').count());
      // The page body stays inside the viewport.
      const card = await page.getByTestId('wiki-content').boundingBox();
      expect(card && card.x + card.width <= width + 1).toBe(true);
    });

    test('compact header: the tabs in the header row, no separate tab row', async ({ page }) => {
      await page.goto(roadmap);
      await expect(page.getByTestId('wiki-tabs')).toHaveCount(0);
      await expect(page.getByTestId('wiki-tab-page')).toHaveAttribute('aria-current', 'page');
      expect(await pageScrollsSideways(page)).toBe(false);
      if (width >= 1024) {
        // One row: the tabs at the start, the comments toggle at the end.
        const tab = await page.getByTestId('wiki-tab-page').boundingBox();
        const toggle = await page.getByTestId('wiki-comments-toggle').boundingBox();
        expect(tab && toggle && Math.abs(tab.y + tab.height / 2 - (toggle.y + toggle.height / 2)) < 24).toBe(true);
      }
    });
  });
}

test.describe('Wiki tree rail (1280 px)', () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test('folds to a rail with the keyboard, keeps focus, is remembered, and unfolds', async ({ page }) => {
    await page.goto(deploy);
    await startExpanded(page);
    const host = page.locator('[data-wiki-sidebar]');
    const toggle = page.getByTestId('wiki-tree-rail-toggle');
    await expect(toggle).toHaveAccessibleName('Collapse Pages');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');

    const before = await page.getByTestId('wiki-content').boundingBox();
    await toggle.focus();
    await Promise.all([page.waitForResponse((r) => r.url().includes('/panel-state') && r.status() === 204), page.keyboard.press('Enter')]);
    await expect(host).toHaveAttribute('data-nowo-ui-rail', 'collapsed');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(toggle).toHaveAccessibleName('Expand Pages');
    await expect(toggle).toBeFocused();
    await expect(page.locator('[data-wiki-tree]')).toBeHidden();
    const rail = await host.boundingBox();
    expect(rail && rail.width < 60).toBe(true);
    // The page column takes the room (the readable column moves towards the start).
    const after = await page.getByTestId('wiki-content').boundingBox();
    expect(before && after && after.x < before.x).toBe(true);

    // Remembered on the next page (server store, no flash).
    await page.goto('/tools/wiki/spaces/engineering');
    await expect(page.locator('[data-wiki-sidebar]')).toHaveAttribute('data-nowo-ui-rail', 'collapsed');
    const violations = await new AxeBuilder({ page }).include('[data-wiki-sidebar]').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(violations.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${v.id}: ${v.nodes.map((n) => `${n.target.join(' ')} ${n.failureSummary ?? ''}`).join(', ')}`)).toEqual([]);

    await page.goto(deploy);
    await startExpanded(page);
    await expect(page.locator('[data-wiki-tree]')).toBeVisible();
  });
});

test.describe('Wiki tree on a phone (390 px)', () => {
  test.use({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });

  test('the page comes first, its tree after it', async ({ page }) => {
    await page.goto(deploy);
    const content = await page.getByTestId('wiki-content').boundingBox();
    const tree = await page.locator('[data-wiki-sidebar]').boundingBox();
    expect(content && tree && tree.y > content.y).toBe(true);
  });
});

test.describe('Wiki comments dock (comments.dock_min_width: lg)', () => {
  test('1024 px: an overlay drawer — focus in, Escape, focus back; 1280 px: a column beside the page', async ({ page }) => {
    const stored: string[] = [];
    page.on('request', (r) => {
      if (r.url().includes('/panel-state') && (r.postData() ?? '').includes('"comments"')) stored.push(r.postData() ?? '');
    });
    await page.setViewportSize({ width: 1024, height: 800 });
    await page.goto(roadmap);
    const toggle = page.getByTestId('wiki-comments-toggle');
    const panel = page.locator('#nowo-wiki-comments');
    // A remembered "open" panel starts closed here (never a modal drawer on load).
    await expect(panel).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await toggle.click();
    await expect(panel).toBeVisible();
    expect(await panel.evaluate((el) => getComputedStyle(el).position)).toBe('fixed');
    await expect.poll(() => panel.evaluate((el) => el.contains(document.activeElement))).toBe(true);
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(toggle).toBeFocused();

    await page.setViewportSize({ width: 1280, height: 800 });
    await Promise.all([page.waitForResponse((r) => r.url().includes('/panel-state')), toggle.click()]);
    await expect(panel).toBeVisible();
    expect(await panel.evaluate((el) => getComputedStyle(el).position)).not.toBe('fixed');
    expect(await pageScrollsSideways(page)).toBe(false);
    // Only the column toggle was stored: the drawer toggles at 1024 px never reached the store.
    expect(stored).toEqual([JSON.stringify({ panel: 'comments', open: true })]);
  });

  test('a drawer toggle never overwrites the desktop choice (1.29, Beacon Q4)', async ({ page }) => {
    const stored: string[] = [];
    page.on('request', (r) => {
      if (r.url().includes('/panel-state') && (r.postData() ?? '').includes('"comments"')) stored.push(r.postData() ?? '');
    });
    const toggle = page.getByTestId('wiki-comments-toggle');
    const panel = page.locator('#nowo-wiki-comments');

    // Desktop: open the column (stored for this user's session).
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(roadmap);
    if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
      await Promise.all([page.waitForResponse((r) => r.url().includes('/panel-state')), toggle.click()]);
    }
    await expect(panel).toBeVisible();
    stored.length = 0;

    // Tablet width: open the drawer and close it again.
    await page.setViewportSize({ width: 1024, height: 800 });
    await page.goto(roadmap);
    await expect(panel).toBeHidden();
    await toggle.click();
    await expect(panel).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await page.waitForTimeout(300);
    expect(stored).toEqual([]);

    // Back on the desktop: still the stored "open" column.
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(roadmap);
    await expect(panel).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');

    // Leave it closed for the other specs.
    await Promise.all([page.waitForResponse((r) => r.url().includes('/panel-state')), toggle.click()]);
    await expect(panel).toBeHidden();
  });
});
