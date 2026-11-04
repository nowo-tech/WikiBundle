import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page } from '@playwright/test';

/**
 * 1.16 — the comments panel (comments.enabled; the demo grants the comment rights in the Product space): the tab row,
 * the toggle with its count, the UiKit side panel (inline column on desktop, full-height drawer on phones), focus in /
 * back, Escape, the live region, the remembered state, the Milkdown thread component and the no-JavaScript fallback.
 */
const roadmap = '/tools/wiki/spaces/product/pages/roadmap';

async function seriousViolations(page: Page, include: string): Promise<string[]> {
  await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
  const { violations } = await new AxeBuilder({ page }).include(include).exclude('.nowo-ui-btn--secondary').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  return violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

async function badge(page: Page): Promise<number> {
  const text = (await page.getByTestId('wiki-comments-toggle').locator('[data-nowo-ui-side-panel-badge]').textContent()) ?? '';
  return text.trim() === '' ? 0 : Number(text.trim());
}

const stored = (page: Page) => page.waitForResponse((r) => r.url().includes('/panel-state') && r.status() === 204);

/** Closes the panel if a previous test left it open (the state is remembered per user). */
async function startClosed(page: Page): Promise<void> {
  await page.goto(roadmap);
  const toggle = page.getByTestId('wiki-comments-toggle');
  if ((await toggle.getAttribute('aria-expanded')) === 'true') {
    await Promise.all([stored(page), toggle.click()]);
  }
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
}

test.describe.configure({ mode: 'serial' });

test.describe('Wiki comments panel', () => {
  test('the tab row is always there; the toggle only where the viewer may see comments', async ({ page }) => {
    await page.goto('/tools/wiki/spaces/engineering/pages/deploy');
    const tabs = page.getByTestId('wiki-tabs');
    await expect(tabs.getByTestId('wiki-tab-page')).toHaveAttribute('aria-current', 'page');
    await expect(tabs.getByTestId('wiki-tab-edit')).toBeVisible();
    await expect(tabs.getByTestId('wiki-tab-history')).toBeVisible();
    await expect(page.getByTestId('wiki-comments-toggle')).toHaveCount(0);
    await expect(page.locator('#nowo-wiki-comments')).toHaveCount(0);
  });

  test('desktop: opens as an inline column, comments, closes with Escape and remembers the state', async ({ page }) => {
    await startClosed(page);
    const toggle = page.getByTestId('wiki-comments-toggle');
    const panel = page.locator('#nowo-wiki-comments');
    await expect(toggle).toHaveAccessibleName(/^Comments/);
    await expect(panel).toBeHidden();

    // The read-only editor replaces the server body once it is ready (it carries the highlights and the threads).
    await expect(page.getByTestId('wiki-comments-view')).toBeVisible({ timeout: 15_000 });
    const before = await badge(page);

    await Promise.all([stored(page), toggle.click()]);
    await expect(panel).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#nowo-wiki-comments-title')).toBeFocused();
    await expect(page.locator('[data-nowo-ui-side-panel-status][data-for="nowo-wiki-comments"]')).toHaveText('Comments opened');
    // Inline on desktop: a column right of the content, nothing made inert.
    const content = await page.getByTestId('wiki-comments-view').boundingBox();
    const box = await panel.boundingBox();
    expect(box && content && box.x >= content.x + content.width - 1).toBe(true);
    await expect(page.locator('[data-nowo-ui-side-panel-main][inert]')).toHaveCount(0);
    expect(await seriousViolations(page, '#nowo-wiki-comments')).toEqual([]);

    // A page-level comment through the Milkdown thread component mounted into the panel.
    const component = panel.locator('.nowo-milkdown-comments--container');
    await expect(component).toBeVisible();
    await component.getByRole('button', { name: 'Add a page comment' }).click();
    const text = `Ship the panel ${Date.now()}`;
    await page.keyboard.type(text);
    await component.getByRole('button', { name: 'Send' }).first().click();
    await expect(component).toContainText(text);
    await expect.poll(() => badge(page)).toBe(before + 1);
    await expect(toggle).toHaveAccessibleName(new RegExp(`^Comments\\s*${before + 1}\\s*open$`));

    // Remembered per user: the next page view renders it open (no flash).
    await page.goto(roadmap);
    await expect(panel).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');

    // Escape inside the panel closes it and gives the focus back to the toggle.
    await page.locator('#nowo-wiki-comments-title').focus();
    await Promise.all([stored(page), page.keyboard.press('Escape')]);
    await expect(panel).toBeHidden();
    await expect(toggle).toBeFocused();
    await expect(page.locator('[data-nowo-ui-side-panel-status][data-for="nowo-wiki-comments"]')).toHaveText('Comments closed');
    await page.goto(roadmap);
    await expect(panel).toBeHidden();
    // ?comments=1 opens it for one request (deep links from notifications).
    await page.goto(`${roadmap}?comments=1`);
    await expect(panel).toBeVisible();
    await page.goto(roadmap);
    await expect(panel).toBeHidden();

    // Dark mode: the panel stays accessible.
    await page.emulateMedia({ colorScheme: 'dark' });
    await Promise.all([stored(page), toggle.click()]);
    await expect(panel).toBeVisible();
    expect(await seriousViolations(page, '#nowo-wiki-comments')).toEqual([]);
    await page.locator('#nowo-wiki-comments-title').focus();
    await Promise.all([stored(page), page.keyboard.press('Escape')]);
  });

  test('phone: a full-height drawer over the page, which is inert while it is open', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 740 });
    await startClosed(page);
    const toggle = page.getByTestId('wiki-comments-toggle');
    const panel = page.locator('#nowo-wiki-comments');

    await Promise.all([stored(page), toggle.click()]);
    await expect(panel).toBeVisible();
    await expect(page.locator('#nowo-wiki-comments-title')).toBeFocused();
    const box = await panel.boundingBox();
    expect(box?.width).toBeGreaterThanOrEqual(370);
    expect(box?.height).toBeGreaterThanOrEqual(730);
    await expect(page.locator('[data-nowo-ui-side-panel-main][inert]')).toHaveCount(1);
    await expect(panel.locator('[data-nowo-ui-side-panel-close]')).toBeVisible();
    expect(await seriousViolations(page, '#nowo-wiki-comments')).toEqual([]);

    await Promise.all([stored(page), panel.locator('[data-nowo-ui-side-panel-close]').click()]);
    await expect(panel).toBeHidden();
    await expect(page.locator('[data-nowo-ui-side-panel-main][inert]')).toHaveCount(0);
    await expect(toggle).toBeFocused();
  });

  test('without JavaScript: the toggle is a link and page comments are plain forms', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto(`${roadmap}?comments=0`);
    const toggle = page.getByTestId('wiki-comments-toggle');
    await expect(toggle).toHaveAttribute('href', /\?comments=1$/);
    await toggle.click();
    await expect(page).toHaveURL(/\?comments=1$/);

    const fallback = page.getByTestId('wiki-comments-fallback');
    await expect(fallback).toBeVisible();
    const text = `No-JS note ${Date.now()}`;
    await fallback.getByLabel('Comment on this page').fill(text);
    await fallback.getByRole('button', { name: 'Comment' }).click();
    await expect(page).toHaveURL(/\?comments=1#nowo-wiki-comments$/);
    await expect(page.getByText('Your comment was added.')).toBeVisible();
    await expect(page.getByTestId('wiki-comments-fallback')).toContainText(text);
    // The close control is a link to the closed state (followed directly: without JavaScript the page keeps moving
    // while the flash message animates).
    const close = page.getByRole('button', { name: 'Close comments' });
    await expect(close).toHaveAttribute('href', /\?comments=0$/);
    await page.goto((await close.getAttribute('href')) ?? '');
    await expect(page.locator('#nowo-wiki-comments')).toBeHidden();
    await context.close();
  });
});
