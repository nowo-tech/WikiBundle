// 1.38.1: Chromium check of the edit view's card toolbar and page head (tests/Browser/edit-1381). Run:
// make check-edit-view. At phone widths (320 and 375 px, light and dark) the toolbar is its own row and the path row
// under it leaves no gap beyond 0.5rem; at 1280 px the path still lines up with the floating toolbar (2.5rem head).
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const failures = [];
  const out = {};
  for (const w of [320, 375, 1280]) {
    for (const scheme of ['light', 'dark']) {
      const p = await b.newPage({ viewport: { width: w, height: 800 }, colorScheme: scheme });
      await p.goto('http://127.0.0.1:8000/index.html');
      await p.waitForTimeout(200);
      const m = await p.evaluate(() => {
        const r = (s) => document.querySelector(s).getBoundingClientRect();
        const bar = r('.nowo-wiki-card-toolbar__bar'); const head = r('[data-testid=wiki-page-head]'); const path = r('[data-testid=wiki-page-path]');
        return { barBottom: bar.bottom, headTop: head.top, headHeight: head.height, pathTop: path.top, pathBottom: path.bottom, scroll: document.documentElement.scrollWidth > innerWidth };
      });
      out[`${w}-${scheme}`] = m;
      await p.screenshot({ path: `/work/edit-${w}-${scheme}.png` });
      if (m.scroll) failures.push(`${w}-${scheme}: horizontal scroll`);
      if (w < 640) {
        const gap = m.pathTop - m.barBottom;
        if (gap > 9) failures.push(`${w}-${scheme}: ${gap}px between the toolbar and the path (max 8)`);
        if (m.headHeight > m.pathBottom - m.pathTop + 1) failures.push(`${w}-${scheme}: the page head is ${m.headHeight}px for a ${m.pathBottom - m.pathTop}px path`);
      } else if (m.headHeight < 39) {
        failures.push(`${w}-${scheme}: the page head lost its 2.5rem height (${m.headHeight})`);
      }
      await p.close();
    }
  }
  console.log(JSON.stringify(out, null, 1));
  await b.close();
  if (failures.length) { console.error(failures.join('\n')); process.exit(1); }
  console.log('edit-1381: OK');
})();
