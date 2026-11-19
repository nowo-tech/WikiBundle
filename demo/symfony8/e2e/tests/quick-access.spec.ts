import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page } from '@playwright/test';
import { headerAction } from './helpers';

/** 1.45 — favourites and recently viewed (sidebar), "Linked from", the rendered revision diff. */
const space = '/tools/wiki/spaces/engineering';

async function seriousViolations(page: Page, include: string): Promise<string[]> {
  await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running' || a.effect?.getComputedTiming().iterations === Infinity));
  const { violations } = await new AxeBuilder({ page }).include(include).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  return violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

test.describe.configure({ mode: 'serial' });

test.describe('Favourites, recents, backlinks and the rendered diff', () => {
  test('Add to favourites lists the page above the tree; Remove takes it away', async ({ page }) => {
    await page.goto(`${space}/pages/deploy`);
    await (await headerAction(page, page.getByTestId('wiki-favourite'))).click();
    await expect(page).toHaveURL(/\/pages\/deploy$/);
    const favourites = page.getByTestId('wiki-favourites');
    await expect(favourites.getByRole('link', { name: 'Deploy' })).toBeVisible();
    await expect(favourites.getByRole('link', { name: 'Deploy' })).toHaveAttribute('aria-current', 'page');
    expect(await seriousViolations(page, '[data-testid="wiki-favourites"]')).toEqual([]);

    // The section folds without JavaScript (a native disclosure).
    await favourites.locator('summary').click();
    await expect(favourites.getByRole('link', { name: 'Deploy' })).toBeHidden();

    await (await headerAction(page, page.getByTestId('wiki-favourite'))).click();
    await expect(page.getByTestId('wiki-favourites').getByRole('link', { name: 'Deploy' })).toHaveCount(0);
  });

  test('Recently viewed lists the pages opened before, not the current one', async ({ page }) => {
    await page.goto(`${space}/pages/rollback`);
    await page.goto(`${space}/pages/welcome`);
    const recents = page.getByTestId('wiki-recents');
    await expect(recents.getByRole('link', { name: 'Rollback' })).toBeVisible();
    await expect(recents.getByRole('link', { name: 'Welcome' })).toHaveCount(0);
  });

  test('Linked from lists the pages that link this one', async ({ page }) => {
    await page.goto(`${space}/pages/welcome`);
    const backlinks = page.getByTestId('wiki-backlinks');
    await expect(backlinks.getByRole('heading', { name: 'Linked from' })).toBeVisible();
    await expect(backlinks.getByRole('link', { name: 'Milkdown showcase' })).toBeVisible();
    expect(await seriousViolations(page, '[data-testid="wiki-backlinks"]')).toEqual([]);
  });

  test('Compare shows the rendered diff of two Markdown revisions', async ({ page }) => {
    await page.goto(`${space}/pages/milkdown-showcase/diff?from=1&to=2`);
    const diff = page.getByTestId('wiki-rendered-diff');
    await expect(diff).toBeVisible();
    await expect(diff.locator('ins.nowo-md-diff-ins')).toContainText('and ideas');
    await expect(diff.locator('[data-change="changed"]')).toHaveCount(1);
    expect(await seriousViolations(page, '[data-testid="wiki-rendered-diff"]')).toEqual([]);
  });
});
