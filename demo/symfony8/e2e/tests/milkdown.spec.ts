import { test, expect } from '@playwright/test';

/**
 * WikiBundle 1.13 — Milkdown ↔ wiki bridge (editor.type: milkdown): edit with Milkdown, save, server-side render of
 * the dialect, search excerpt, "Linked from" (demo panel on the bundle's reference extractor), conversion dry run.
 */
const space = '/tools/wiki/spaces/engineering';
const showcase = `${space}/pages/milkdown-showcase`;

test.describe('Milkdown wiki pages', () => {
  test('the dialect is rendered on the server for the viewer, never as raw tokens', async ({ page }) => {
    await page.goto(showcase);
    const content = page.getByTestId('wiki-content');
    await expect(content.locator('a.nowo-md-ref', { hasText: 'Welcome' })).toHaveAttribute('href', /\/pages\/welcome$/);
    await expect(content.locator('a.nowo-md-ref', { hasText: 'Deploy' })).toHaveAttribute('href', /\/pages\/deploy$/);
    await expect(content.locator('.nowo-md-callout--info')).toContainText('Page links show the current title');
    await expect(content.locator('svg.nowo-md-icon use')).toHaveCount(2);
    await expect(content.locator('mark.nowo-md-mark')).toHaveText('Highlighted');
    await expect(content.locator('li[data-type="taskItem"][data-checked="true"]')).toContainText('Write the page in Markdown');
    await expect(content.locator('.katex').first()).toBeVisible({ timeout: 15_000 });
    await expect(content.locator('.nowo-md-mermaid svg')).toBeVisible({ timeout: 15_000 });
    await expect(content).not.toContainText('[[wiki:');
    await expect(content).not.toContainText(':tabler-');
  });

  test('editing with Milkdown saves Markdown that renders server-side, is searchable and feeds the backlinks', async ({ page }) => {
    const marker = `Milkdown e2e ${Date.now()}`;
    await page.goto(`${showcase}/edit`);
    const editor = page.locator('.nowo-milkdown .ProseMirror[contenteditable="true"]').first();
    await expect(editor).toBeVisible();
    await editor.locator('p', { hasText: 'Start at' }).first().click();
    await page.keyboard.press('End');
    await page.keyboard.press('Enter');
    await page.keyboard.type(marker);
    await page.getByRole('button', { name: 'Save' }).first().click();

    await expect(page).toHaveURL(new RegExp(`${showcase}$`));
    const content = page.getByTestId('wiki-content');
    await expect(content).toContainText(marker);
    await expect(content.locator('a.nowo-md-ref', { hasText: 'Welcome' })).toBeVisible();

    await page.goto(`/tools/wiki/search?q=${encodeURIComponent(marker)}`);
    const results = page.getByTestId('wiki-search');
    await expect(results.getByRole('link', { name: 'Milkdown showcase' }).first()).toBeVisible();
    await expect(results).toContainText(marker);
    await expect(results).not.toContainText('[[wiki:');

    await page.goto(`${space}/pages/welcome`);
    await expect(page.getByTestId('demo-wiki-backlinks').getByRole('link', { name: 'Milkdown showcase' })).toBeVisible();
  });

  test('the conversion dry run reports the HTML pages without writing a revision', async ({ page, request }) => {
    const response = await request.get('/demo/wiki/conversion-dry-run/engineering');
    expect(response.ok()).toBeTruthy();
    const body = await response.json();
    expect(body.exit_code).toBe(0);
    expect(body.console).toContain('Dry run:');
    expect(body.report.format).toBe('nowo-wiki-conversion-report');
    expect(body.report.dry_run).toBe(true);
    expect(body.report.dialect).toBe('milkdown');
    expect(body.report.totals.converted).toBeGreaterThan(0);
    const welcome = body.report.pages.find((p: { page: string }) => p.page === 'welcome');
    expect(welcome.outcome).toBe('would_convert');
    expect(welcome.to_revision).toBeNull();
    expect(body.report.pages.find((p: { page: string }) => p.page === 'milkdown-showcase').outcome).toBe('skipped_markdown');

    await page.goto(`${space}/pages/welcome/history`);
    await expect(page.getByTestId('wiki-history').locator('tbody tr')).toHaveCount(1);
  });
});
