import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { waitForToolbar } from './helpers';

/** REQ-DEMO-013 — README screenshot (captured as PNG, converted to WebP by scripts/to-webp.mjs). */
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
    await expect(page.locator('[data-wiki-sidebar] .wiki-tree').first()).toBeVisible();
    await expect(page.locator('.wiki-page-content img').first()).toBeVisible();
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'wiki-page.png') });
  });
});
