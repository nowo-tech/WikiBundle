import { test, expect, type Page } from '@playwright/test';

/**
 * 1.40.1 — the page form's IconSelector picker as Symfony Beacon configures it: page_icon.picker: icon_selector,
 * page_icon.emoji: true, IconSelector source ux_icons_local (the demo commits a few Bootstrap Icons under
 * assets/icons/bi), mode search, IconSelector >= 1.8 with its UiKit theme (auto: UiKit is installed).
 * Open the picker, search "robot", pick it, save the page form: the stored value is the field's value when the form
 * opens again and the page tree draws the icon.
 */
const space = '/tools/wiki/spaces/engineering';
const backend = `${space}/pages/backend`;
const tree = (p: Page) => p.locator('[data-wiki-sidebar] [role="tree"][data-wiki-tree]');
const item = (p: Page, name: string) => tree(p).getByRole('treeitem', { name, exact: true });
const widget = (p: Page) => p.locator('#wiki_page_form_icon_icon_selector');
const field = (p: Page) => p.locator('input[name="wiki_page_form[icon]"]');

async function pickRobot(p: Page): Promise<void> {
  const w = widget(p);
  await expect(w).toHaveAttribute('data-icon-selector-enhanced', '1');
  await w.getByRole('tab', { name: 'Icons' }).click();
  await w.locator('.icon-selector-trigger').click();
  await w.getByRole('textbox', { name: 'Search icons...' }).fill('robot');
  const robot = w.locator('.icon-selector-item[data-icon-id="bi:robot"]');
  await expect(robot).toBeVisible();
  await expect(w.locator('.icon-selector-item')).toHaveCount(1);
  await robot.click();
  await expect(field(p)).toHaveValue('bi:robot');
}

test.describe.configure({ mode: 'serial' });

test.describe('Page form icon picker (search mode, emoji, ux_icons_local)', () => {
  test('the picker\'s own texts come from its catalogue (never a raw key), UiKit look, no inline style', async ({ page: p }) => {
    await p.goto(`${backend}/edit`);
    const w = widget(p);
    await expect(w).toHaveAttribute('data-icon-selector-enhanced', '1');
    await expect(w).toHaveAttribute('data-icon-selector-theme-value', 'uikit');
    await expect(w).toHaveAttribute('data-icon-selector-search-placeholder', 'Search icons...');
    await w.getByRole('tab', { name: 'Icons' }).click();
    await expect(w.locator('.icon-selector-trigger')).toHaveText(/Choose icon\.\.\.|bi:/);
    await w.locator('.icon-selector-trigger').click();
    await expect(w.getByRole('textbox', { name: 'Search icons...' })).toHaveAttribute('placeholder', 'Search icons...');
    expect(await w.evaluate((el) => el.textContent ?? '')).not.toMatch(/search_placeholder|no_icons/);
    expect(await w.evaluate((el) => el.querySelectorAll('[style]').length)).toBe(0);
    expect(await w.evaluate((el) => Array.from(el.querySelectorAll('[class]')).some((n) => /\b(form-control|bg-white|shadow-sm|btn-outline-secondary)\b/.test(n.getAttribute('class') ?? '')))).toBe(false);
  });

  test('search "robot", pick it, save: the stored icon is kept and the tree shows it', async ({ page: p }) => {
    await p.goto(`${backend}/edit`);
    await pickRobot(p);
    await p.locator('form[name="wiki_page_form"]').getByRole('button', { name: 'Save', exact: true }).click();
    await expect(p).toHaveURL(new RegExp(`${backend}$`));
    await expect(item(p, 'Backend').locator('.nowo-wiki-page-icon .bi-robot')).toBeAttached();

    await p.goto(`${backend}/edit`);
    await expect(field(p)).toHaveValue('bi:robot');
    await expect(widget(p).locator('.icon-selector-trigger')).toContainText('bi:robot');
  });

  test('Beacon\'s flow: the text field filled and a Save button outside the form clicked while the picker loads', async ({ page: p }) => {
    // IconSelector 1.7 swallowed this submit (its form guard prevented it and never sent it again): nothing was saved.
    let release: () => void = () => {};
    const gate = new Promise<void>((resolve) => { release = resolve; });
    await p.route('**/api/icon-selector/config*', async (route) => {
      await gate;
      await route.continue();
    });
    await p.goto(`${backend}/edit`, { waitUntil: 'domcontentloaded' });
    await p.evaluate(() => {
      const form = document.querySelector('form[name="wiki_page_form"]') as HTMLFormElement;
      form.id ||= 'wiki-page-form';
      const save = document.createElement('button');
      save.type = 'submit';
      save.setAttribute('form', form.id);
      save.textContent = 'Toolbar save';
      document.body.prepend(save);
    });
    await expect(widget(p)).toHaveAttribute('data-icon-selector-enhanced', '0');
    // Emoji mode: until the widget is set up the value field is a text input.
    await field(p).fill('emoji:🤖');
    await p.getByRole('button', { name: 'Toolbar save' }).click();
    await p.waitForTimeout(300);
    await expect(p).toHaveURL(new RegExp(`${backend}/edit$`));
    release();
    await expect(p).toHaveURL(new RegExp(`${backend}$`));
    await expect(item(p, 'Backend').locator('.nowo-wiki-page-icon--emoji')).toHaveText('🤖');
  });
});
