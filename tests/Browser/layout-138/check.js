// 1.38: Chromium checks of the read layout (tests/Browser/layout-138). Run: make check-layout-138 (copies the built
// wiki.css / wiki.js and the UiKit CSS next to index.html and runs this script in the Playwright image).
// Width (web_ui.reading_width: text, reading_align: start): at 1280 / 1600 / 1920 px, light and dark, the content card
// reaches the main column's end edge (no empty band), the text keeps the reading measure against the card's start
// edge, and the revision line sits inside the card, in its footer padding. Exits non-zero on any failure.
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
  console.log(JSON.stringify(out, null, 1));
  await b.close();
  if (failures.length) { console.error(failures.join('\n')); process.exit(1); }
  console.log('layout-138: OK');
})();
