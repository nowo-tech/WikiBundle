import { expect, type Locator, type Page } from '@playwright/test';

/** README screenshots never show the Symfony debug toolbar (it is not part of the demo platform): hide it. */
export async function waitForToolbar(page: Page): Promise<void> {
  await page.addStyleTag({ content: '.sf-toolbar,.sf-minitoolbar,[id^="sfwdt"]{display:none!important}' });
  await page.waitForTimeout(300);
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
