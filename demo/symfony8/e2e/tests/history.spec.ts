import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page } from '@playwright/test';
import { headerAction } from './helpers';

/** 1.15 — the history timeline (history.timeline in the demo): Save version, labels and authors, Copy to new page. */
const rollback = '/tools/wiki/spaces/engineering/pages/rollback';

async function seriousViolations(page: Page, include: string): Promise<string[]> {
  await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
  const { violations } = await new AxeBuilder({ page }).include(include).exclude('.nowo-ui-btn--secondary').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  return violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

test.describe.configure({ mode: 'serial' });

test.describe('Wiki history timeline', () => {
  test('Save version names the current revision; the history shows the label and an opaque author', async ({ page }) => {
    await page.goto(rollback);
    await (await headerAction(page, page.getByTestId('wiki-save-version'))).click();
    await expect(page).toHaveURL(/\/versions\/new$/);
    expect(await seriousViolations(page, '[data-testid="wiki-page-version"]')).toEqual([]);

    // An empty name is refused (422) and nothing is saved.
    await page.getByLabel('Version name').fill(' ');
    await page.getByRole('button', { name: 'Save version' }).click();
    await expect(page).toHaveURL(/\/versions\/new$/);

    await page.getByLabel('Version name').fill('Reviewed rollback');
    await page.getByRole('button', { name: 'Save version' }).click();
    await expect(page).toHaveURL(/\/rollback\/history$/);
    await expect(page.getByText(/saved as “Reviewed rollback”/)).toBeVisible();

    const history = page.getByTestId('wiki-history');
    await expect(history.getByRole('columnheader', { name: 'Version name' })).toBeVisible();
    await expect(history.locator('tbody tr').first()).toContainText('Reviewed rollback');
    await expect(history.locator('tbody tr').first()).toContainText(/Editor [0-9a-f]{4}/);
    expect(await seriousViolations(page, '[data-testid="wiki-history"]')).toEqual([]);
  });

  test('Copy to new page creates a top-level page from an old version', async ({ page }) => {
    await page.goto(rollback + '/history');
    await page.getByTestId('wiki-history').getByRole('button', { name: 'Copy to new page' }).last().click();
    await expect(page).toHaveURL(/\/pages\/rollback-v\d+(-\d+)?$/);
    await expect(page.getByText(/“Rollback \(v\d+\)” was created from the old version\./)).toBeVisible();
    await expect(page.getByRole('heading', { level: 1, name: /Rollback \(v\d+\)/ })).toBeVisible();

    // Keep the demo tree tidy: the copy goes to the trash.
    await (await headerAction(page, page.getByTestId('wiki-delete'))).click();
    await page.locator('dialog[open]').getByRole('button', { name: 'Delete' }).click();
    await expect(page).toHaveURL(/\/spaces\/engineering$/);
  });
});
