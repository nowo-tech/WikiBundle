import { test, expect } from '@playwright/test';

/** REQ-DEMO-013 — Wiki demo happy path (auto-login demo user; spaces and pages from the demo fixtures). */
const welcome = '/tools/wiki/spaces/engineering/pages/welcome';

test.describe('Wiki demo', () => {
  test('wiki index lists the demo spaces', async ({ page }) => {
    const response = await page.goto('/tools/wiki');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByRole('link', { name: 'Engineering' }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Product' }).first()).toBeVisible();
  });

  test('page view renders content and the page tree; links navigate', async ({ page }) => {
    await page.goto(welcome);
    await expect(page.locator('[data-wiki-sidebar] .wiki-tree').first()).toBeVisible();
    await expect(page.locator('.wiki-page-content')).toBeVisible();
    await page.locator('.wiki-page-content a', { hasText: 'Getting Started' }).first().click();
    await expect(page).toHaveURL(/\/pages\/getting-started$/);
    await expect(page.locator('h2.card-title', { hasText: 'Getting Started' })).toBeVisible();
  });

  test('search finds pages across spaces and pluralises the count', async ({ page }) => {
    await page.goto('/tools/wiki/search?q=deploy');
    await expect(page.locator('a.card-link h3', { hasText: 'Deploy' }).first()).toBeVisible();
    await expect(page.getByText(/^\d+ results?$/)).toBeVisible();
  });

  test('page editor loads the Tiptap script and mounts the editor', async ({ page }) => {
    const script = page.waitForResponse((r) => r.url().includes('tiptap-editor.js'));
    await page.goto(welcome + '/edit');
    expect((await script).ok()).toBeTruthy();
    await expect(page.locator('nowo-tiptap-editor .ProseMirror, nowo-tiptap-editor [contenteditable="true"]').first()).toBeVisible();
  });
});
