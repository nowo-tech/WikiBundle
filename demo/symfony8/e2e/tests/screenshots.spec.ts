import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { waitForToolbar } from './helpers';

/** REQ-DEMO-013 — README screenshots (captured as PNG, converted to WebP by scripts/to-webp.mjs). */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('Wiki screenshots', () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test('wiki-page — page tree and rendered page with internal links', async ({ page }) => {
    await page.goto('/tools/wiki/spaces/engineering/pages/welcome');
    await expect(page.locator('[data-wiki-sidebar] [data-wiki-tree]')).toBeVisible();
    await expect(page.getByTestId('wiki-content').locator('img').first()).toBeVisible();
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'wiki-page.png') });
  });

  test('wiki-space — space home with search, import and archived pages', async ({ page }) => {
    await page.goto('/tools/wiki/spaces/engineering');
    await expect(page.getByTestId('wiki-archived')).toBeVisible();
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'wiki-space.png') });
  });

  test('wiki-history — version history with the restore confirmation', async ({ page }) => {
    await page.goto('/tools/wiki/spaces/engineering/pages/deploy/history');
    await expect(page.getByTestId('wiki-history')).toBeVisible();
    await waitForToolbar(page);
    await page.getByRole('button', { name: 'Restore this revision' }).first().click();
    await expect(page.locator('dialog[open]')).toBeVisible();
    await page.waitForTimeout(300);
    await page.screenshot({ path: resolve(outDir, 'wiki-history.png') });
  });

  test('wiki-tree-menu — the page tree\'s context menu, emoji icons and previous / next page', async ({ page }) => {
    await page.goto('/tools/wiki/spaces/engineering/pages/deploy');
    await expect(page.getByTestId('wiki-page-nav-top')).toBeVisible();
    await waitForToolbar(page);
    await page.locator('[data-wiki-tree] [role="treeitem"]', { hasText: 'Rollback' }).click({ button: 'right' });
    await expect(page.getByTestId('wiki-tree-menu')).toBeVisible();
    await page.waitForTimeout(200);
    await page.screenshot({ path: resolve(outDir, 'wiki-tree-menu.png') });
    await page.keyboard.press('Escape');
  });

  test('wiki-icon-picker — Change icon / emoji with the IconSelector picker', async ({ page }) => {
    await page.goto('/tools/wiki/spaces/engineering/pages/deploy');
    await page.locator('[data-wiki-tree] [role="treeitem"]', { hasText: 'Deploy' }).first().click({ button: 'right' });
    await page.getByTestId('wiki-tree-menu-icon').click();
    const dialog = page.getByTestId('wiki-tree-icon-dialog');
    await expect(dialog.locator('[data-icon-selector-enhanced="1"]')).toBeAttached();
    await dialog.getByRole('tab', { name: 'Emoji' }).click();
    await expect(dialog.locator('.icon-selector-emoji-item').nth(20)).toBeVisible();
    await page.waitForTimeout(300);
    await page.screenshot({ path: resolve(outDir, 'wiki-icon-picker.png') });
  });

  test('wiki-edit — the edit view with the title as a heading and the icon picker', async ({ page }) => {
    await page.goto('/tools/wiki/spaces/engineering/pages/deploy/edit');
    await expect(page.locator('.nowo-wiki-title-input')).toBeVisible();
    await waitForToolbar(page);
    await page.waitForTimeout(500);
    await page.screenshot({ path: resolve(outDir, 'wiki-edit.png') });
  });

  test('wiki-page-nav-phone — previous / next page on a phone', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/tools/wiki/spaces/engineering/pages/deploy');
    const nav = page.getByTestId('wiki-page-nav-bottom');
    await nav.scrollIntoViewIfNeeded();
    await nav.screenshot({ path: resolve(outDir, 'wiki-page-nav-phone.png') });
  });
});
