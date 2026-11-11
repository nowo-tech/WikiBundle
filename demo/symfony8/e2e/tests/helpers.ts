import { expect, type Locator, type Page } from '@playwright/test';

/** Wait for the Symfony WebProfiler toolbar (dev) so it is part of the capture. */
export async function waitForToolbar(page: Page): Promise<void> {
  const toolbar = page.locator('.sf-toolbar');
  if (await toolbar.count()) {
    await expect(toolbar.locator('.sf-toolbar-block').first()).toBeVisible({ timeout: 15_000 });
    await page.waitForTimeout(400);
  }
}

/**
 * 1.29 compact header (web_ui.page_header.layout: compact, split actions): an action may sit in the "More actions"
 * menu; opens the menu when it is not visible and returns the (first) visible action.
 */
export async function headerAction(page: Page, action: Locator): Promise<Locator> {
  const first = action.first();
  if (!(await first.isVisible())) {
    await page.locator('[data-nowo-ui-header-more] [data-nowo-ui-popover-trigger]').first().click();
    await expect(first).toBeVisible();
  }

  return first;
}
