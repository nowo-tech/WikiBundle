import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page } from '@playwright/test';

/** 1.14 (T065) — the trash (trash.enabled in the demo): delete with its sub-pages, restore together, purge twice confirmed. */
const retired = '/tools/wiki/spaces/engineering/pages/retired-runbooks';
const child = '/tools/wiki/spaces/engineering/pages/old-on-call-notes';

async function seriousViolations(page: Page, include: string): Promise<string[]> {
  await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
  const { violations } = await new AxeBuilder({ page }).include(include).exclude('.nowo-ui-btn--secondary').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  return violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

async function deleteRetired(page: Page): Promise<void> {
  await page.goto(retired);
  await page.getByTestId('wiki-delete').click();
  const dialog = page.locator('dialog[open]');
  await expect(dialog).toContainText('Move this page to the trash?');
  await dialog.getByRole('button', { name: 'Delete' }).click();
  await expect(page).toHaveURL(/\/spaces\/engineering$/);
  await expect(page.getByText('“Retired runbooks” and its sub-pages were moved to the trash.')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test.describe('Wiki trash', () => {
  test('delete moves the page and its sub-pages to the trash; restore brings them back together', async ({ page }) => {
    // Cancel keeps the page.
    await page.goto(retired);
    await page.getByTestId('wiki-delete').click();
    await page.locator('dialog[open]').getByRole('button', { name: 'Cancel' }).click();
    await expect(page).toHaveURL(new RegExp(retired + '$'));

    await deleteRetired(page);
    await expect(page.locator('[data-wiki-sidebar]').getByRole('treeitem', { name: 'Retired runbooks' })).toHaveCount(0);
    expect((await page.goto(child))?.status()).toBe(404);

    await page.goto('/tools/wiki/spaces/engineering');
    await page.getByTestId('wiki-trash').click();
    const item = page.getByTestId('wiki-trash-item').filter({ hasText: 'Retired runbooks' });
    await expect(item).toContainText('2 page(s)');
    expect(await seriousViolations(page, '[data-testid="wiki-trash-list"]')).toEqual([]);
    await item.getByRole('button', { name: 'Restore' }).click();
    await expect(page).toHaveURL(new RegExp(retired + '$'));
    await expect(page.getByText('“Retired runbooks” was restored with its sub-pages.')).toBeVisible();
    expect((await page.goto(child))?.status()).toBe(200);
  });

  test('purge needs the confirmation page and a second confirmation, then the pages are gone for good', async ({ page }) => {
    await deleteRetired(page);
    await page.goto('/tools/wiki/spaces/engineering/trash');
    await page.getByTestId('wiki-trash-item').filter({ hasText: 'Retired runbooks' }).getByRole('link', { name: 'Delete for good' }).click();
    await expect(page.getByRole('heading', { name: 'Delete “Retired runbooks” for good?' })).toBeVisible();
    await expect(page.getByTestId('wiki-trash-purge-list')).toContainText('Old on-call notes');
    expect(await seriousViolations(page, '[data-testid="wiki-trash-purge-list"]')).toEqual([]);
    await page.getByTestId('wiki-trash-purge-confirm').click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toContainText('Delete for good?');
    await dialog.getByRole('button', { name: 'Delete for good' }).click();
    await expect(page).toHaveURL(/\/trash$/);
    await expect(page.getByText('“Retired runbooks” was deleted for good (2 page(s)).')).toBeVisible();
    await expect(page.getByTestId('wiki-trash-item').filter({ hasText: 'Retired runbooks' })).toHaveCount(0);
    expect((await page.goto(retired))?.status()).toBe(404);
  });
});
