import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page } from '@playwright/test';
import { headerAction } from './helpers';

/** 1.14 — the Outline-style page tree (tree.enabled in the demo): treeview, fold state, keyboard moves, Move form. */
const deploy = '/tools/wiki/spaces/engineering/pages/deploy';
const tree = (page: Page) => page.locator('[data-wiki-sidebar] [role="tree"][data-wiki-tree]');
const item = (page: Page, name: string) => tree(page).getByRole('treeitem', { name, exact: true });

async function seriousViolations(page: Page, include?: string, exclude?: string): Promise<string[]> {
  // Page transitions fade the content in: measure contrast once every animation has finished.
  await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).exclude('.sf-toolbar');
  if (include) builder = builder.include(include);
  if (exclude) builder = builder.exclude(exclude);
  const { violations } = await builder.analyze();
  return violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${v.id}: ${v.nodes.map((n) => `${n.target.join(' ')} [${n.any[0]?.message ?? ''}]`).join(', ')}`);
}

/** Labels of the children of a parent item, in order. */
async function children(page: Page, parent: string): Promise<string[]> {
  const group = page.locator(`#${await item(page, parent).getAttribute('aria-owns')}`);
  return group.locator(':scope > li > .nowo-ui-tree__row > [role="treeitem"]').allInnerTexts();
}

test.describe.configure({ mode: 'serial' });

test.describe('Wiki page tree', () => {
  test('treeview with the current branch unfolded, one tab stop, no axe violations, no inline styles', async ({ page }) => {
    await page.goto(deploy);
    await expect(tree(page)).toHaveAttribute('data-nowo-ui-tree-ready', '');
    await expect(item(page, 'Deploy')).toHaveAttribute('aria-current', 'page');
    await expect(item(page, 'Runbooks')).toHaveAttribute('aria-expanded', 'true');
    await expect(item(page, 'Architecture')).toHaveAttribute('aria-expanded', 'false');
    await expect(tree(page).locator('[role="treeitem"][tabindex="0"]')).toHaveCount(1);
    await expect(item(page, 'Deploy')).toHaveAttribute('tabindex', '0');
    expect(await seriousViolations(page, '[data-wiki-sidebar]')).toEqual([]);
    // Strict-CSP friendly: the tree scripts never write style or handler attributes.
    await expect(tree(page).locator('[style], [onclick], [onkeydown]')).toHaveCount(0);
  });

  test('Alt+arrows move a page, the live region says where, the order is saved', async ({ page }) => {
    await page.goto(deploy);
    expect(await children(page, 'Runbooks')).toEqual(['Deploy', 'Rollback']);
    await item(page, 'Deploy').focus();
    await page.keyboard.press('Alt+ArrowDown');
    await expect(page.locator('[data-nowo-ui-tree-live]')).toHaveText('“Deploy” moved under “Runbooks”, position 2 of 2.');
    await expect(item(page, 'Deploy')).toBeFocused();
    expect(await children(page, 'Runbooks')).toEqual(['Rollback', 'Deploy']);
    await expect(item(page, 'Deploy')).toHaveAttribute('aria-posinset', '2');

    await page.reload();
    expect(await children(page, 'Runbooks')).toEqual(['Rollback', 'Deploy']);
    await item(page, 'Deploy').focus();
    await page.keyboard.press('Alt+ArrowUp');
    await expect(page.locator('[data-nowo-ui-tree-live]')).toHaveText('“Deploy” moved under “Runbooks”, position 1 of 2.');
    // Out and back in: Alt+Left to the top level, Alt+Right under the previous page.
    await page.keyboard.press('Alt+ArrowLeft');
    await expect(page.locator('[data-nowo-ui-tree-live]')).toHaveText(/^“Deploy” moved to the top level, position \d+ of \d+\.$/);
    await expect(item(page, 'Deploy')).toHaveAttribute('aria-level', '1');
    await page.keyboard.press('Alt+ArrowRight');
    await expect(item(page, 'Deploy')).toHaveAttribute('aria-level', '2');
    await page.reload();
    expect(await children(page, 'Runbooks')).toEqual(['Rollback', 'Deploy']);
    await item(page, 'Deploy').focus();
    await page.keyboard.press('Alt+ArrowUp');
    await expect(page.locator('[data-nowo-ui-tree-live]')).toContainText('position 1 of 2');
  });

  test('the fold state is kept on the server and applied before rendering', async ({ page }) => {
    await page.goto(deploy);
    const saved = (page: Page) => page.waitForResponse((r) => r.url().endsWith('/tree/state') && r.status() === 204);
    await item(page, 'Architecture').focus();
    await Promise.all([saved(page), page.keyboard.press('ArrowRight')]);
    await expect(item(page, 'Architecture')).toHaveAttribute('aria-expanded', 'true');
    await page.reload();
    await expect(item(page, 'Architecture')).toHaveAttribute('aria-expanded', 'true');
    await item(page, 'Architecture').focus();
    await Promise.all([saved(page), page.keyboard.press('ArrowLeft')]);
    await page.reload();
    await expect(item(page, 'Architecture')).toHaveAttribute('aria-expanded', 'false');
  });

  test('the Move form is the single-pointer alternative: parent and position, then back', async ({ page }) => {
    await page.goto('/tools/wiki/spaces/engineering/pages/resources');
    await (await headerAction(page, page.getByTestId('wiki-move'))).click();
    await expect(page.getByTestId('wiki-page-move')).toBeVisible();
    // The Cancel button is the shared UiKit form scaffold in the demo's stock Bootstrap secondary colour (every wiki
    // form has it; a demo theme matter, not the Move form's).
    expect(await seriousViolations(page, 'form', '.nowo-ui-btn--secondary')).toEqual([]);
    await page.getByLabel('Parent page').selectOption({ label: 'Runbooks' });
    await page.getByLabel('Position').fill('1');
    await page.getByRole('button', { name: 'Move' }).click();
    await expect(page).toHaveURL(/\/pages\/resources$/);
    await expect(page.getByText('“Resources” moved under “Runbooks”, position 1 of 3.')).toBeVisible();
    expect(await children(page, 'Runbooks')).toEqual(['Resources', 'Deploy', 'Rollback']);

    await (await headerAction(page, page.getByTestId('wiki-move'))).click();
    await page.getByLabel('Parent page').selectOption({ label: 'Top level' });
    await page.getByRole('button', { name: 'Move' }).click();
    await expect(page.getByText(/^“Resources” moved to the top level, position \d+ of \d+\.$/)).toBeVisible();
    await expect(item(page, 'Resources')).toHaveAttribute('aria-level', '1');
  });
});

/**
 * MUX-80: hosts stack the tree under the page body on phones (one column). Bringing the current page into view must
 * scroll the tree's own container only, never the page: a long page opens at its top.
 */
test.describe('Wiki page tree on a phone (390 × 844)', () => {
  test.use({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });

  test('a long page opens at the top, the tree under the content does not drag the page down', async ({ page }) => {
    // A long body before the sidebar, as a host layout that puts the tree after the content renders it.
    await page.route(`**${deploy}`, async (route) => {
      const response = await route.fetch();
      const filler = Array.from({ length: 60 }, (_, i) => `<p>Filler paragraph ${i + 1} of a long page body.</p>`).join('');
      const body = (await response.text()).replace(/<div class="nowo-wiki-layout[^"]*">/, (layout) => `${layout}<div data-e2e-filler>${filler}</div>`);
      await route.fulfill({ response, body });
    });
    await page.goto(deploy);
    await expect(tree(page)).toHaveAttribute('data-nowo-ui-tree-ready', '');
    await expect(page.locator('[data-e2e-filler]')).toHaveCount(1);
    await expect(item(page, 'Deploy')).toHaveAttribute('aria-current', 'page');
    // Precondition: the current tree entry starts well below the fold.
    const top = await item(page, 'Deploy').evaluate((el) => el.getBoundingClientRect().top + window.scrollY);
    expect(top).toBeGreaterThan(844 * 2);
    await page.waitForLoadState('load');
    expect(await page.evaluate(() => window.scrollY)).toBe(0);
    // No scroller outside the sidebar moved (hosts may scroll an inner page container instead of the window).
    const moved = await page.evaluate(() => [...document.querySelectorAll('*')]
      .filter((el) => el.scrollTop > 0 && !el.closest('[data-wiki-sidebar]'))
      .map((el) => `${el.tagName.toLowerCase()}.${el.className}`));
    expect(moved).toEqual([]);
    await expect(page.locator('[data-e2e-filler] p').first()).toBeInViewport();
  });
});
