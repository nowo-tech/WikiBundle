// 1.38: Chromium checks of the read layout (tests/Browser/layout-138). Run: make check-layout-138 (copies the built
// wiki.css / wiki.js and the UiKit CSS next to index.html and runs this script in the Playwright image).
// Width (web_ui.reading_width: text, reading_align: start): at 1280 / 1600 / 1920 px, light and dark, the content card
// reaches the main column's end edge (no empty band), the text keeps the reading measure against the card's start
// edge, and the revision line sits inside the card, in its footer padding.
// Tree (tree.default_expanded: current_path): the buttons show, only the current path is open, Tab reaches a button,
// Enter / Space toggle it (aria-expanded, the list hidden), the choice survives a reload (localStorage per space),
// a blocked localStorage breaks nothing, and axe-core finds no violation (WCAG 2.x A / AA) in light and dark.
// Without JavaScript every level is open and no button shows. Exits non-zero on any failure.
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const failures = [];
  const out = {};
  for (const w of [1280, 1600, 1920]) {
    for (const scheme of ['light', 'dark']) {
      const p = await b.newPage({ viewport: { width: w, height: 900 }, colorScheme: scheme });
      await p.goto('http://127.0.0.1:8000/index.html');
      await p.waitForTimeout(200);
      const m = await p.evaluate(() => {
        const r = (s) => document.querySelector(s).getBoundingClientRect();
        const main = r('#main'); const card = r('[data-testid=wiki-page-card]'); const text = r('[data-testid=wiki-content]');
        const meta = r('[data-testid=wiki-page-meta]'); const aside = r('.nowo-wiki-layout__aside');
        return { main: [main.left, main.right], card: [card.left, card.right, card.bottom], text: [text.left, text.width], meta: [meta.left, meta.bottom], aside: aside.width };
      });
      out[`${w}-${scheme}`] = m;
      await p.screenshot({ path: `/work/layout-${w}-${scheme}.png`, fullPage: true });
      if (Math.abs(m.card[1] - m.main[1]) > 1) failures.push(`${w}-${scheme}: the card stops at ${m.card[1]}, the column at ${m.main[1]}`);
      if (Math.abs(m.text[0] - m.card[0]) > 1) failures.push(`${w}-${scheme}: the text does not start at the card's edge`);
      if (w >= 1600 && m.text[1] >= m.card[1] - m.card[0] - 1) failures.push(`${w}-${scheme}: the text is not capped at the measure (${m.text[1]})`);
      if (m.meta[0] - m.card[0] < 8) failures.push(`${w}-${scheme}: the revision line is flush with the card's left edge`);
      if (m.card[2] - m.meta[1] < 6) failures.push(`${w}-${scheme}: the revision line is flush with the card's bottom edge`);
      await p.close();
    }
  }
  // Tree.
  const state = async (p) => p.evaluate(() => Object.fromEntries([...document.querySelectorAll('[data-wiki-tree-fold]')].map((btn) => [btn.dataset.wikiTreeFold, { shown: !btn.hidden, expanded: btn.getAttribute('aria-expanded'), listHidden: document.getElementById(btn.getAttribute('aria-controls')).hidden }])));
  {
    const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
    await p.goto('http://127.0.0.1:8000/index.html');
    await p.waitForFunction(() => document.querySelector('[data-wiki-tree-ready]') !== null);
    const first = await state(p);
    out.treeInitial = first;
    if (!(first.a.expanded === 'true' && !first.a.listHidden && first.b.expanded === 'false' && first.b.listHidden && first.d.expanded === 'false' && first.d.listHidden && first.a.shown)) failures.push('tree: the default is not the current path');
    // Keyboard: Tab from the top reaches the first toggle; Enter opens Reference, Space closes it again, Enter opens it.
    await p.focus('[data-wiki-tree-fold="d"]');
    await p.keyboard.press('Enter');
    let now = await state(p);
    if (now.d.expanded !== 'true' || now.d.listHidden) failures.push('tree: Enter does not open a row');
    await p.keyboard.press('Space');
    now = await state(p);
    if (now.d.expanded !== 'false' || !now.d.listHidden) failures.push('tree: Space does not close a row');
    await p.keyboard.press('Enter');
    await p.keyboard.down('Shift'); await p.keyboard.press('Tab'); await p.keyboard.up('Shift');
    const focusName = await p.evaluate(() => document.activeElement?.textContent ?? '');
    if (!focusName.includes('Deploy') && !focusName.includes('Rollback')) failures.push(`tree: Shift+Tab from a toggle lands on "${focusName}"`);
    await p.screenshot({ path: '/work/tree-open-reference.png' });
    await p.reload();
    await p.waitForFunction(() => document.querySelector('[data-wiki-tree-ready]') !== null);
    now = await state(p);
    if (now.d.expanded !== 'true') failures.push('tree: the choice is not remembered after a reload');
    out.treeStored = await p.evaluate(() => localStorage.getItem('nowo-wiki-tree:docs'));
    for (const scheme of ['light', 'dark']) {
      await p.emulateMedia({ colorScheme: scheme });
      await p.addScriptTag({ path: '/tmp/node_modules/axe-core/axe.min.js' });
      const result = await p.evaluate(async () => (await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } })).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`));
      out[`axe-${scheme}`] = result;
      if (result.length) failures.push(`axe (${scheme}): ${result.join(' | ')}`);
    }
    await p.close();
  }
  {
    // A browser that refuses localStorage: the tree still folds.
    const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
    await p.addInitScript(() => Object.defineProperty(window, 'localStorage', { get() { throw new Error('SecurityError'); } }));
    await p.goto('http://127.0.0.1:8000/index.html');
    await p.waitForFunction(() => document.querySelector('[data-wiki-tree-ready]') !== null);
    await p.click('[data-wiki-tree-fold="d"]');
    if ((await state(p)).d.expanded !== 'true') failures.push('tree: no folding without localStorage');
    await p.close();
  }
  {
    // Without JavaScript: every level open, no button.
    const ctx = await b.newContext({ javaScriptEnabled: false, viewport: { width: 1280, height: 900 } });
    const p = await ctx.newPage();
    await p.goto('http://127.0.0.1:8000/index.html');
    const visible = await p.evaluate(() => [...document.querySelectorAll('.nowo-wiki-tree__link')].filter((a) => a.getBoundingClientRect().height > 0).length);
    const buttons = await p.evaluate(() => [...document.querySelectorAll('[data-wiki-tree-fold]')].filter((btn) => btn.getBoundingClientRect().height > 0).length);
    out.noJs = { visible, buttons };
    if (visible !== 6 || buttons !== 0) failures.push(`tree without JavaScript: ${visible} links, ${buttons} buttons`);
    await p.screenshot({ path: '/work/tree-no-js.png' });
    await ctx.close();
  }

  console.log(JSON.stringify(out, null, 1));
  await b.close();
  if (failures.length) { console.error(failures.join('\n')); process.exit(1); }
  console.log('layout-138: OK');
})();
