import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page } from '@playwright/test';

/**
 * 1.41 — the rest of the page tree's context menu (tree.context_menu): Open in new tab, Edit, New page above / below,
 * Duplicate, Move up / down / to…, Archive with its undo, Delete to the trash; the keyboard and a long press.
 */
const space = '/tools/wiki/spaces/engineering';
const deploy = `${space}/pages/deploy`;
const tree = (page: Page) => page.locator('[data-wiki-sidebar] [role="tree"][data-wiki-tree]');
const item = (page: Page, name: string) => tree(page).getByRole('treeitem', { name, exact: true });
const menu = (page: Page) => page.getByTestId('wiki-tree-menu');
const entries = (page: Page) => menu(page).getByRole('menuitem');
const status = (page: Page) => page.getByTestId('wiki-tree-menu-status');
const copyName = 'Rollback (copy)';

/** Titles of the children of a row, in tree order. */
async function childrenOf(page: Page, name: string): Promise<string[]> {
  const group = item(page, name).locator('xpath=ancestor::li[1]/ul[@role="group"]');

  return group.locator(':scope > li > .nowo-ui-tree__row .nowo-ui-tree__label').allTextContents();
}

async function choose(page: Page, row: string, entry: string): Promise<void> {
  await item(page, row).click({ button: 'right' });
  await menu(page).getByRole('menuitem', { name: entry, exact: true }).click();
}

async function seriousViolations(page: Page, include: string): Promise<string[]> {
  await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'));
  const { violations } = await new AxeBuilder({ page }).include(include).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  return violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

test.describe.configure({ mode: 'serial' });

test.describe('Tree menu: page actions (1.41)', () => {
  test('every entry the row allows, in groups; keyboard to the last one and back to the row', async ({ page }) => {
    await page.goto(deploy);
    await item(page, 'Rollback').click({ button: 'right' });
    // Rollback is the last child of Runbooks: no "Move down".
    await expect(entries(page)).toHaveText([
      'Open in new tab', 'Edit', 'Rename', 'Change icon / emoji',
      'Add child page', 'New page above', 'New page below', 'Duplicate',
      'Move up', 'Move to…',
      'Copy link',
      'Archive', 'Delete',
    ]);
    await expect(menu(page).locator('[role="separator"]:visible')).toHaveCount(4);
    expect(await seriousViolations(page, '[data-wiki-tree-menu]')).toEqual([]);
    await page.keyboard.press('End');
    await expect(menu(page).getByRole('menuitem', { name: 'Delete' })).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(entries(page).first()).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(menu(page)).toBeHidden();
    await expect(item(page, 'Rollback')).toBeFocused();

    // Shift+F10 on the first child: "Move down", no "Move up".
    await item(page, 'Deploy').focus();
    await page.keyboard.press('Shift+F10');
    await expect(menu(page).getByRole('menuitem', { name: 'Move down' })).toBeVisible();
    await expect(menu(page).getByRole('menuitem', { name: 'Move up' })).toBeHidden();
    await page.keyboard.press('Escape');
  });

  test('a long press on a row opens the same menu (touch)', async ({ page }) => {
    await page.goto(deploy);
    const link = item(page, 'Rollback');
    const box = (await link.boundingBox())!;
    const at = { clientX: box.x + 8, clientY: box.y + 8, bubbles: true, pointerType: 'touch' };
    await link.dispatchEvent('pointerdown', at);
    await expect(menu(page)).toBeVisible();
    await expect(menu(page).getByRole('menuitem', { name: 'Duplicate' })).toBeVisible();
    await link.dispatchEvent('pointerup', at);
    // The click that ends the long press stays on this page.
    await link.dispatchEvent('click', { bubbles: true, cancelable: true });
    await expect(page).toHaveURL(new RegExp(`${deploy}$`));
    await page.keyboard.press('Escape');

    // A finger that moves is a scroll, not a press.
    await link.dispatchEvent('pointerdown', at);
    await link.dispatchEvent('pointermove', { ...at, clientY: at.clientY + 40 });
    await page.waitForTimeout(700);
    await expect(menu(page)).toBeHidden();
  });

  test('Open in new tab, Edit and New page above', async ({ page, context }) => {
    await page.goto(deploy);
    const [tab] = await Promise.all([context.waitForEvent('page'), choose(page, 'Rollback', 'Open in new tab')]);
    await expect(tab).toHaveURL(/\/pages\/rollback$/);
    await tab.close();

    await choose(page, 'Rollback', 'Edit');
    await expect(page).toHaveURL(/\/pages\/rollback\/edit$/);

    await page.goto(deploy);
    await choose(page, 'Rollback', 'New page above');
    await expect(page).toHaveURL(/\/pages\/new\?place=above%3A/);
    await expect(page.locator('input[name="wiki_page_form[place]"]')).toHaveValue(/^above:/);
  });

  test('Duplicate, Move up / down / to…, Archive and its undo, Delete', async ({ page }) => {
    await page.goto(deploy);
    await choose(page, 'Rollback', 'Duplicate');
    await expect(page).toHaveURL(/\/pages\/rollback-copy(-\d+)?$/);
    const copyUrl = new URL(page.url()).pathname;
    await expect(page.getByText(`“${copyName}” created from “Rollback”.`)).toBeVisible();
    expect(await childrenOf(page, 'Runbooks')).toEqual(['Deploy', 'Rollback', copyName]);

    // Move up, then down again (the tree's Alt+arrow move: the server's sentence in the tree's live region).
    await choose(page, copyName, 'Move up');
    await expect.poll(() => childrenOf(page, 'Runbooks')).toEqual(['Deploy', copyName, 'Rollback']);
    await expect(item(page, copyName)).toBeFocused();
    await choose(page, copyName, 'Move down');
    await expect.poll(() => childrenOf(page, 'Runbooks')).toEqual(['Deploy', 'Rollback', copyName]);
    await page.reload();
    expect(await childrenOf(page, 'Runbooks')).toEqual(['Deploy', 'Rollback', copyName]);

    // Move to…: the parents offered never include the page itself; the top level.
    await choose(page, copyName, 'Move to…');
    const moveDialog = page.getByTestId('wiki-tree-move-dialog');
    await expect(moveDialog).toBeVisible();
    const parent = moveDialog.getByLabel('Parent page');
    await expect(parent).toBeFocused();
    await expect(parent.locator('option', { hasText: copyName })).toHaveCount(0);
    await parent.selectOption({ label: 'Top level' });
    await page.getByTestId('wiki-tree-move-save').click();
    await expect(moveDialog).toBeHidden();
    await expect(item(page, copyName)).toHaveAttribute('aria-level', '1');
    await page.reload();
    await expect(item(page, copyName)).toHaveAttribute('aria-level', '1');

    // Archive behind the confirm dialog; the undo line brings it back.
    await page.goto(deploy);
    await choose(page, copyName, 'Archive');
    const confirm = page.getByTestId('wiki-tree-confirm-dialog');
    await expect(confirm).toContainText(`Archive “${copyName}”? Its sub-pages stay where they are.`);
    expect(await seriousViolations(page, '[data-testid="wiki-tree-confirm-dialog"]')).toEqual([]);
    await page.getByTestId('wiki-tree-confirm-ok').click();
    await expect(item(page, copyName)).toHaveCount(0);
    await expect(page.getByTestId('wiki-tree-undo')).toContainText(`“${copyName}” archived.`);
    await page.getByTestId('wiki-tree-undo-button').click();
    await expect(item(page, copyName)).toBeVisible();

    // Delete from the copy's own page: the count in the confirmation, then back to the space.
    await page.goto(copyUrl);
    await choose(page, copyName, 'Delete');
    await expect(page.getByTestId('wiki-tree-confirm-message')).toHaveText(`“${copyName}” and 0 sub-page(s) will go to the trash. You can restore them from the trash.`);
    await page.getByTestId('wiki-tree-confirm-ok').click();
    await expect(page).toHaveURL(new RegExp(`${space}$`));
    await expect(item(page, copyName)).toHaveCount(0);

    // Leave the demo as it was: the copy goes for good.
    await page.goto(`${space}/trash`);
    await page.getByTestId('wiki-trash-item').filter({ hasText: copyName }).first().getByRole('link', { name: 'Delete for good' }).click();
    await page.getByTestId('wiki-trash-purge-confirm').click();
    await page.locator('dialog[open]').getByRole('button', { name: 'Delete for good' }).click();
    await expect(page).toHaveURL(/\/trash$/);
  });

  test('the status line says why an entry was refused', async ({ page }) => {
    await page.goto(deploy);
    // The page went meanwhile (another tab): the entry answers instead of failing.
    await page.route('**/tree/duplicate', (route) => route.fulfill({ status: 410, contentType: 'application/json', body: JSON.stringify({ error: 'page_gone', message: 'This page was archived or deleted meanwhile: reload the page.' }) }));
    await choose(page, 'Rollback', 'Duplicate');
    await expect(status(page)).toHaveText('This page was archived or deleted meanwhile: reload the page.');
  });
});
