import { expect, type Page } from '@playwright/test';

/** Wait for the Symfony WebProfiler toolbar (dev) so it is part of the capture. */
export async function waitForToolbar(page: Page): Promise<void> {
  const toolbar = page.locator('.sf-toolbar');
  if (await toolbar.count()) {
    await expect(toolbar.locator('.sf-toolbar-block').first()).toBeVisible({ timeout: 15_000 });
    await page.waitForTimeout(400);
  }
}
