import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page } from '@playwright/test';

/**
 * 1.39 — the page tree's context menu (tree.context_menu), emoji page icons with the IconSelector picker
 * (page_icon.picker / page_icon.emoji), previous / next page (web_ui.page_nav: both) and the edit view's title field.
 */
const space = '/tools/wiki/spaces/engineering';
const deploy = `${space}/pages/deploy`;
const tree = (page: Page) => page.locator('[data-wiki-sidebar] [role="tree"][data-wiki-tree]');
const item = (page: Page, name: string) => tree(page).getByRole('treeitem', { name, exact: true });
const row = (page: Page, name: string) => item(page, name).locator('xpath=ancestor::li[1]');
const menu = (page: Page) => page.getByTestId('wiki-tree-menu');
const entries = (page: Page) => menu(page).getByRole('menuitem');

async function seriousViolations(page: Page, include?: string): Promise<string[]> {
  await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running' || a.effect?.getComputedTiming().iterations === Infinity));
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).exclude('.sf-toolbar');
  if (include) builder = builder.include(include);
  const { violations } = await builder.analyze();
  return violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

test.describe.configure({ mode: 'serial' });

test.describe('Tree context menu', () => {
  test('mouse: right click opens the menu with the row entries; Escape gives the focus back', async ({ page }) => {
    await page.goto(deploy);
    await item(page, 'Rollback').click({ button: 'right' });
    await expect(menu(page)).toBeVisible();
    // 1.41: the 1.39 entries keep their place among the new ones ("Add sibling page" is now "New page below").
    await expect(entries(page)).toHaveText(['Open in new tab', 'Edit', 'Rename', 'Change icon / emoji', 'Add child page', 'New page above', 'New page below', 'Duplicate', 'Move up', 'Move to…', 'Copy link', 'Archive', 'Delete']);
    await expect(entries(page).first()).toBeFocused();
    expect(await seriousViolations(page, '[data-wiki-tree-menu]')).toEqual([]);
    await page.keyboard.press('Escape');
    await expect(menu(page)).toBeHidden();
    await expect(item(page, 'Rollback')).toBeFocused();
  });

  test('keyboard: Shift+F10 and the ContextMenu key, roving focus with arrows / Home / End', async ({ page }) => {
    await page.goto(deploy);
    await item(page, 'Deploy').focus();
    await page.keyboard.press('Shift+F10');
    await expect(menu(page)).toBeVisible();
    await page.keyboard.press('ArrowDown');
    await expect(entries(page).nth(1)).toBeFocused();
    await page.keyboard.press('End');
    await expect(entries(page).last()).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(entries(page).first()).toBeFocused();
    await page.keyboard.press('Home');
    await expect(entries(page).first()).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(menu(page)).toBeHidden();
    await item(page, 'Deploy').focus();
    await page.keyboard.press('ContextMenu');
    await expect(menu(page)).toBeVisible();
    await page.keyboard.press('Escape');
  });

  test('touch: the «…» button opens the same menu without moving the row', async ({ page }) => {
    await page.goto(deploy);
    // 1.45: the sidebar may list favourites and recents above the tree, so the menu can scroll the page: the row is
    // measured within the tree, not the viewport.
    const inTree = async (): Promise<{ x: number; y: number; width: number; height: number }> => {
      const [box, frame] = await Promise.all([item(page, 'Rollback').boundingBox(), tree(page).boundingBox()]);
      expect(box).not.toBeNull();
      expect(frame).not.toBeNull();

      return { x: box!.x - frame!.x, y: box!.y - frame!.y, width: box!.width, height: box!.height };
    };
    const before = await inTree();
    const more = row(page, 'Rollback').getByTestId('wiki-tree-menu-more');
    await expect(more).toHaveAttribute('aria-haspopup', 'menu');
    await item(page, 'Rollback').hover();
    await more.click();
    await expect(menu(page)).toBeVisible();
    expect(await inTree()).toEqual(before);
    await more.click();
    await expect(menu(page)).toBeHidden();
  });

  test('Rename in place adds a revision; Escape cancels', async ({ page }) => {
    await page.goto(deploy);
    await item(page, 'Rollback').click({ button: 'right' });
    await menu(page).getByRole('menuitem', { name: 'Rename' }).click();
    const field = tree(page).getByTestId('wiki-tree-rename-input');
    await expect(field).toBeFocused();
    await expect(field).toHaveValue('Rollback');
    await field.fill('Rollback plan');
    await page.keyboard.press('Enter');
    await expect(item(page, 'Rollback plan')).toBeVisible();
    await expect(page.getByTestId('wiki-tree-menu-status')).toContainText('renamed');
    await page.reload();
    await expect(item(page, 'Rollback plan')).toBeVisible();

    // Back, and Escape leaves the title as it is.
    await page.goto(deploy);
    await item(page, 'Rollback plan').click({ button: 'right' });
    await menu(page).getByRole('menuitem', { name: 'Rename' }).click();
    await tree(page).getByTestId('wiki-tree-rename-input').fill('Rollback');
    await page.keyboard.press('Enter');
    await expect(item(page, 'Rollback')).toBeVisible();
    await item(page, 'Rollback').click({ button: 'right' });
    await menu(page).getByRole('menuitem', { name: 'Rename' }).click();
    await tree(page).getByTestId('wiki-tree-rename-input').fill('Nope');
    await page.keyboard.press('Escape');
    await expect(item(page, 'Rollback')).toBeFocused();
  });

  test('Change icon: the picker\'s Emoji tab sets an emoji, shown as text in the row', async ({ page }) => {
    await page.goto(deploy);
    await item(page, 'Rollback').click({ button: 'right' });
    // 1.53 (lazy dialogs): the icon field is not in the page; the dialog loads it when it opens.
    await expect(page.locator('input[name="form[icon]"]')).toHaveCount(0);
    await menu(page).getByRole('menuitem', { name: 'Change icon / emoji' }).click();
    const dialog = page.getByTestId('wiki-tree-icon-dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('[data-icon-selector-enhanced="1"]')).toBeAttached();
    await dialog.getByRole('tab', { name: 'Emoji' }).click();
    await dialog.getByRole('searchbox', { name: 'Search emoji...' }).fill('rewind');
    await dialog.getByRole('button', { name: 'fast reverse button', exact: true }).click();
    await page.getByTestId('wiki-tree-icon-save').click();
    await expect(dialog).toBeHidden();
    await expect(item(page, 'Rollback').locator('.nowo-wiki-page-icon--emoji')).toHaveText('⏪');
    await page.reload();
    await expect(item(page, 'Rollback').locator('.nowo-wiki-page-icon--emoji')).toHaveText('⏪');
  });

  test('Add child page opens the new-page form with the place', async ({ page }) => {
    await page.goto(deploy);
    await item(page, 'Runbooks').click({ button: 'right' });
    await menu(page).getByRole('menuitem', { name: 'Add child page' }).click();
    await expect(page).toHaveURL(/\/pages\/new\?place=child%3A/);
    await expect(page.locator('input[name="wiki_page_form[place]"]')).toHaveValue(/^child:/);
  });

  test('Copy link puts the page address on the clipboard', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.goto(deploy);
    await item(page, 'Rollback').click({ button: 'right' });
    // Chosen with the keyboard (End, then up past Delete and Archive).
    await page.keyboard.press('End');
    await page.keyboard.press('ArrowUp');
    await page.keyboard.press('ArrowUp');
    await expect(menu(page).getByRole('menuitem', { name: 'Copy link' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.getByTestId('wiki-tree-menu-status')).toHaveText('Link copied.');
    expect(await page.evaluate(() => navigator.clipboard.readText())).toMatch(/\/pages\/rollback$/);
  });

  test('a reader gets no menu (the browser\'s own) and no «…» button', async ({ page, context }) => {
    await page.goto(deploy);
    await context.addCookies([{ name: 'demo_wiki_reader', value: '1', url: new URL(page.url()).origin }]);
    await page.reload();
    await expect(page.getByTestId('wiki-tree-menu')).toHaveCount(0);
    await expect(tree(page).getByTestId('wiki-tree-menu-more')).toHaveCount(0);
    await item(page, 'Rollback').click({ button: 'right' });
    await expect(page.getByRole('menu')).toHaveCount(0);
  });
});

test.describe('Previous / next page', () => {
  test('links in reading order with rel and accessible names, no axe violations', async ({ page }) => {
    await page.goto(deploy);
    const top = page.getByTestId('wiki-page-nav-top');
    await expect(top).toBeVisible();
    await expect(page.getByTestId('wiki-page-nav-bottom')).toBeVisible();
    const prev = top.getByTestId('wiki-page-nav-prev');
    const next = top.getByTestId('wiki-page-nav-next');
    await expect(prev).toHaveAttribute('rel', 'prev');
    await expect(prev).toHaveAttribute('aria-label', 'Previous page: Runbooks');
    await expect(prev).toContainText('📘');
    await expect(next).toHaveAttribute('rel', 'next');
    await expect(next).toHaveAttribute('aria-label', 'Next page: Rollback');
    expect(await seriousViolations(page, '[data-testid="wiki-page-nav-top"]')).toEqual([]);
    await next.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/pages\/rollback$/);
  });

  test('phone: stacked, titles cut with an ellipsis', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto(deploy);
    const top = page.getByTestId('wiki-page-nav-top');
    const prev = await top.getByTestId('wiki-page-nav-prev').boundingBox();
    const next = await top.getByTestId('wiki-page-nav-next').boundingBox();
    expect(prev && next && next.y > prev.y).toBeTruthy();
    expect(await top.locator('.nowo-wiki-page-nav__text').first().evaluate((el) => getComputedStyle(el).textOverflow)).toBe('ellipsis');
    // The links fit the phone's width (long titles are cut, never widen the page).
    const box = await top.boundingBox();
    expect(box && box.x >= 0 && box.x + box.width <= 375).toBeTruthy();
    expect(await top.evaluate((el) => el.scrollWidth <= el.clientWidth)).toBeTruthy();
  });
});

test.describe('Edit view', () => {
  test('the title field reads as a title and keeps its label', async ({ page }) => {
    await page.goto(`${deploy}/edit`);
    const title = page.getByLabel('Title', { exact: true });
    await expect(title).toHaveClass(/nowo-wiki-title-input/);
    const size = await title.evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
    const body = await page.evaluate(() => parseFloat(getComputedStyle(document.body).fontSize));
    expect(size).toBeGreaterThan(body * 1.4);
  });
});
