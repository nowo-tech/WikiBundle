import { test, expect } from '@playwright/test';

/** REQ-DEMO-013 — Wiki demo happy path (auto-login demo user; spaces and pages from the demo fixtures, UiKit pages). */
const welcome = '/tools/wiki/spaces/engineering/pages/welcome';
const deploy = '/tools/wiki/spaces/engineering/pages/deploy';

test.describe('Wiki demo', () => {
  test('wiki index lists the demo spaces inside the host layout', async ({ page }) => {
    const response = await page.goto('/tools/wiki');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByTestId('demo-masthead')).toBeVisible();
    const spaces = page.getByTestId('wiki-spaces');
    await expect(spaces.getByRole('link', { name: 'Engineering' }).first()).toBeVisible();
    await expect(spaces.getByRole('link', { name: 'Product' }).first()).toBeVisible();
  });

  test('page view renders content and the page tree; links navigate', async ({ page }) => {
    await page.goto(welcome);
    await expect(page.locator('[data-wiki-sidebar] [data-wiki-tree]')).toBeVisible();
    await expect(page.locator('[data-wiki-tree] [aria-current="page"]')).toHaveText('Welcome');
    const content = page.getByTestId('wiki-content');
    await expect(content).toBeVisible();
    await content.locator('a', { hasText: 'Getting Started' }).first().click();
    await expect(page).toHaveURL(/\/pages\/getting-started$/);
    await expect(page.locator('h1.nowo-ui-page-header__title')).toHaveText('Getting Started');
  });

  test('search finds pages across spaces and pluralises the count', async ({ page }) => {
    await page.goto('/tools/wiki/search?q=deploy');
    const results = page.getByTestId('wiki-search');
    await expect(results.getByRole('link', { name: 'Deploy', exact: true }).first()).toBeVisible();
    await expect(results.getByText(/^\d+ results?$/)).toBeVisible();
  });

  test('space page shows the tree, search, exports and archived pages (no import for a ROLE_USER)', async ({ page }) => {
    await page.goto('/tools/wiki/spaces/engineering');
    await expect(page.getByTestId('wiki-new-page')).toBeVisible();
    await expect(page.getByTestId('wiki-space-search')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Export (Outline ZIP)' })).toBeVisible();
    await expect(page.getByTestId('wiki-import')).toHaveCount(0);
    await expect(page.getByTestId('wiki-archived')).toContainText('Legacy deploy (Capistrano)');
  });

  test('archive asks for confirmation in a native dialog', async ({ page }) => {
    await page.goto(deploy);
    await page.getByRole('button', { name: 'Archive' }).first().click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('Archive this page?');
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(page.locator('dialog[open]')).toHaveCount(0);
    await expect(page).toHaveURL(new RegExp(deploy + '$'));
  });

  test('restoring a revision goes through the confirm dialog and appends a revision', async ({ page }) => {
    await page.goto(deploy + '/history');
    const history = page.getByTestId('wiki-history');
    await expect(history.locator('tbody tr').first()).toBeVisible();
    await history.getByRole('button', { name: 'Restore this revision' }).first().click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toContainText('Restore this revision?');
    await dialog.getByRole('button', { name: 'Restore this revision' }).click();
    await expect(page).toHaveURL(new RegExp(deploy + '$'));
    await expect(page.getByText('The revision was restored as a new revision.')).toBeVisible();
  });

  test('page editor loads the Tiptap script and mounts the editor', async ({ page }) => {
    const script = page.waitForResponse((r) => r.url().includes('tiptap-editor.js'));
    await page.goto(welcome + '/edit');
    expect((await script).ok()).toBeTruthy();
    await expect(page.locator('nowo-tiptap-editor .ProseMirror, nowo-tiptap-editor [contenteditable="true"]').first()).toBeVisible();
  });
});
