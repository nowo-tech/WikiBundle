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
});
