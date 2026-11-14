// 1.35 web_ui.new_page_dialog: Chromium checks of the page tree's "New page" menu and the new-page dialog, on the markup
// of tests/Browser/new-page-menu/index.html with the built wiki.css / wiki.js and the UiKit CSS, dialog and tree
// scripts. Run: make check-new-page-menu. Keyboard (Shift+F10, ContextMenu, arrows, Home / End, Escape, Tab), right
// click, the «…» button, the choices each row allows, the dialog (modal, focus kept inside, place filled, cleared on
// close, focus back on the row) and the page action. Exits non-zero on the first failed check.
const { chromium } = require('playwright');

const failures = [];
const check = (name, ok, detail) => {
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${ok ? '' : ` — ${JSON.stringify(detail)}`}`);
  if (!ok) failures.push(name);
};

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  page.on('pageerror', (e) => failures.push(`page error: ${e.message}`));
  await page.goto('http://127.0.0.1:8000/index.html');
  await page.waitForFunction(() => document.documentElement.classList.contains('wiki-ready'));

  const focused = () => page.evaluate(() => {
    const el = document.activeElement;
    return el ? (el.dataset.wikiNewPageChoice || el.id || el.dataset.testid || el.textContent.trim() || el.tagName) : null;
  });
  const menuOpen = () => page.evaluate(() => !document.querySelector('[data-wiki-new-page-menu]').hidden);
  const choices = () => page.evaluate(() => [...document.querySelectorAll('[data-wiki-new-page-choice]')].filter((b) => !b.hidden && b.offsetParent !== null).map((b) => b.dataset.wikiNewPageChoice));
  const place = () => page.evaluate(() => [document.getElementById('wiki_page_form_parent').value, document.getElementById('wiki_page_form_position').value, document.querySelector('[data-wiki-new-page-context]').textContent]);
  const dialogState = () => page.evaluate(() => { const d = document.getElementById('nowo-wiki-new-page-dialog'); return { open: d.open, modal: d.matches(':modal') }; });
  const link = (title) => page.locator('[data-nowo-ui-tree-item]', { hasText: title });

  // The «…» buttons: shown on the rows that have a choice — all five here (the top level and the children of the
  // editable Operations always have above / below).
  check('«…» buttons revealed', (await page.locator('[data-wiki-new-page-more]:not([hidden])').count()) === 5, await page.locator('[data-wiki-new-page-more]:not([hidden])').count());

  // Keyboard: Shift+F10 on a focused row.
  await link('Runbooks').focus();
  await page.keyboard.press('Shift+F10');
  check('Shift+F10 opens the menu', await menuOpen());
  check('focus on the first choice', (await focused()) === 'above', await focused());
  check('all three choices on an editable row under an editable parent', JSON.stringify(await choices()) === '["above","below","child"]', await choices());
  await page.keyboard.press('ArrowDown');
  check('ArrowDown moves to below', (await focused()) === 'below', await focused());
  await page.keyboard.press('End');
  check('End moves to the last choice', (await focused()) === 'child', await focused());
  await page.keyboard.press('ArrowDown');
  check('ArrowDown wraps to the first', (await focused()) === 'above', await focused());
  await page.keyboard.press('ArrowUp');
  check('ArrowUp wraps to the last', (await focused()) === 'child', await focused());
  await page.keyboard.press('Home');
  check('Home moves to the first', (await focused()) === 'above', await focused());
  await page.keyboard.press('Escape');
  check('Escape closes the menu', !(await menuOpen()));
  check('Escape returns the focus to the row', (await focused()) === 'Runbooks', await focused());

  // Keyboard: the ContextMenu key, then Enter on "New page below".
  await page.keyboard.press('ContextMenu');
  check('ContextMenu key opens the menu', await menuOpen());
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
  check('a choice closes the menu', !(await menuOpen()));
  check('the dialog opens modal', JSON.stringify(await dialogState()) === '{"open":true,"modal":true}', await dialogState());
  check('below Runbooks = position 2 under Operations', JSON.stringify(await place()) === '["p-ops","2","Below “Runbooks”"]', await place());
  check('focus on the title', (await focused()) === 'wiki_page_form_title', await focused());
  const outside = [];
  for (let i = 0; i < 8; i++) {
    await page.keyboard.press('Tab');
    outside.push(await page.evaluate(() => { const el = document.activeElement; return el && el !== document.body && !el.closest('dialog') ? el.textContent.trim() || el.tagName : null; }));
  }
  check('Tab keeps the focus inside the dialog', outside.every((x) => x === null), outside);
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => !document.getElementById('nowo-wiki-new-page-dialog').open);
  // The close event is a task after the dialog closed.
  await page.waitForFunction(() => document.getElementById('wiki_page_form_position').value === '', null, { timeout: 2000 }).catch(() => undefined);
  check('Escape closes the dialog and clears the place', JSON.stringify(await place()) === '["","",""]', await place());
  check('focus back on the row after the dialog', (await focused()) === 'Runbooks', await focused());

  // Right click: Locked (top level, not editable) → above / below only; Guide → as child, position 1.
  const box = await link('Locked').boundingBox();
  await page.mouse.click(box.x + 10, box.y + 5, { button: 'right' });
  check('right click opens the menu', await menuOpen());
  check('no "as child" on a page the user may not edit', JSON.stringify(await choices()) === '["above","below"]', await choices());
  const menuBox = await page.locator('[data-wiki-new-page-menu]').boundingBox();
  check('the menu opens at the pointer', Math.abs(menuBox.x - (box.x + 10)) < 2 && Math.abs(menuBox.y - (box.y + 5)) < 2, { menuBox, box });
  await page.mouse.click(1200, 850);
  check('a click outside closes the menu', !(await menuOpen()));
  await page.locator('[data-nowo-ui-tree-node="p-guide"] > .nowo-ui-tree__row').click({ button: 'right' });
  await page.getByTestId('wiki-new-page-child').click();
  check('as child of Guide = position 1', JSON.stringify(await place()) === '["p-guide","1","Inside “Guide”"]', await place());
  await page.locator('#nowo-wiki-new-page-dialog [data-nowo-ui-dialog-close]').first().click();
  await page.waitForFunction(() => !document.getElementById('nowo-wiki-new-page-dialog').open);

  await page.waitForFunction(() => document.getElementById('wiki_page_form_position').value === '');
  // The «…» button: visible on hover, opens the menu below it, toggles.
  const faqRow = page.locator('[data-nowo-ui-tree-node="p-faq"] > .nowo-ui-tree__row');
  await faqRow.hover();
  const more = faqRow.locator('[data-wiki-new-page-more]');
  check('«…» visible on hover', (await more.evaluate((el) => getComputedStyle(el).opacity)) === '1', await more.evaluate((el) => getComputedStyle(el).opacity));
  await more.click();
  check('«…» opens the menu', await menuOpen());
  check('FAQ (not editable, under Operations): above / below', JSON.stringify(await choices()) === '["above","below"]', await choices());
  check('the row keeps its fold arrow state', (await page.locator('[data-nowo-ui-tree-node="p-faq"] > .nowo-ui-tree__row [aria-expanded]').count()) === 0);
  await page.getByTestId('wiki-new-page-above').click();
  check('above FAQ = position 2 under Operations', JSON.stringify(await place()) === '["p-ops","2","Above “FAQ”"]', await place());
  await page.keyboard.press('Escape');
  await page.waitForFunction(() => !document.getElementById('nowo-wiki-new-page-dialog').open);

  // The page action opens the same dialog with no place.
  await page.getByTestId('wiki-new-page').click();
  await page.waitForFunction(() => document.getElementById('nowo-wiki-new-page-dialog').open);
  check('the page action opens the dialog without a place', JSON.stringify(await place()) === '["","",""]', await place());
  check('the page action dialog is modal', (await dialogState()).modal);
  await page.screenshot({ path: '/work/new-page-dialog.png' });
  await page.keyboard.press('Escape');

  // Phones: the «…» button is always visible (no hover).
  const phone = await browser.newPage({ viewport: { width: 390, height: 800 }, hasTouch: true, isMobile: true });
  await phone.goto('http://127.0.0.1:8000/index.html');
  await phone.waitForFunction(() => document.documentElement.classList.contains('wiki-ready'));
  const phoneOpacity = await phone.locator('[data-nowo-ui-tree-node="p-guide"] [data-wiki-new-page-more]').evaluate((el) => getComputedStyle(el).opacity);
  check('«…» always visible on touch screens', phoneOpacity === '1', phoneOpacity);
  await phone.locator('[data-nowo-ui-tree-node="p-guide"] [data-wiki-new-page-more]').tap();
  const phoneMenu = await phone.locator('[data-wiki-new-page-menu]').boundingBox();
  check('the menu stays inside a phone screen', phoneMenu.x >= 0 && phoneMenu.x + phoneMenu.width <= 390, phoneMenu);
  await phone.screenshot({ path: '/work/new-page-menu-390.png' });

  await browser.close();
  if (failures.length > 0) {
    console.error(`${failures.length} failed: ${failures.join('; ')}`);
    process.exit(1);
  }
  console.log('all checks passed');
})().catch((e) => { console.error(e); process.exit(1); });
