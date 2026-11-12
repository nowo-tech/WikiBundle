// 1.30 web_ui.read_view.seamless_swap: Chromium check that the read-only editor swaps in without moving anything.
// Run: make check-seamless-swap (copies the built wiki.css / wiki.js, the UiKit CSS and the Milkdown bundle next to
// index.html and runs this script in the Playwright image). For each viewport (1280, 390) and motion preference it loads
// the server body, measures the shared box and the next card, mounts the real Milkdown read-only editor, waits for the
// swap and measures again: the box and the next card must not move, CLS must stay 0, every block must sit at the same
// y in both bodies and the deduped <h1> must not show. 1.31: widget.html — a leading .ProseMirror-widget never moves the
// first block. Exits non-zero on any difference.
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const out = {};
  for (const w of [1280, 390]) {
    for (const motion of ['no-preference', 'reduce']) {
      const p = await b.newPage({ viewport: { width: w, height: 900 }, reducedMotion: motion });
      await p.addInitScript(() => { window.__cls = 0; new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); });
      await p.goto('http://127.0.0.1:8000/index.html');
      await p.waitForTimeout(300);
      const box = () => p.evaluate(() => { const r = (s) => { const b = document.querySelector(s).getBoundingClientRect(); return [Math.round(b.x * 10) / 10, Math.round(b.y * 10) / 10, Math.round(b.width * 10) / 10, Math.round(b.height * 10) / 10]; }; return { swap: r('[data-testid=wiki-read-swap]'), after: r('#after'), state: document.querySelector('[data-testid=wiki-read-swap]').getAttribute('data-nowo-wiki-read-swap') }; });
      const before = await box();
      await p.screenshot({ path: `/work/before-${w}-${motion}.png` });
      await p.addScriptTag({ type: 'module', url: 'http://127.0.0.1:8000/mk/milkdown-editor.js' });
      await p.waitForFunction(() => document.querySelector('[data-testid=wiki-read-swap]').getAttribute('data-nowo-wiki-read-swap') === 'ready', null, { timeout: 20000 });
      await p.waitForTimeout(400);
      const after = await box();
      await p.screenshot({ path: `/work/after-${w}-${motion}.png` });
      const detail = await p.evaluate(() => {
        const pos = (root, text) => { const els = [...root.querySelectorAll('p, li, h2, h3, pre, th, blockquote')].filter((e) => e.textContent.trim().startsWith(text)); if (!els.length) return null; const b = els[0].getBoundingClientRect(); return [Math.round(b.y), Math.round(b.height)]; };
        const s = document.querySelector('[data-nowo-wiki-server-body] .nowo-wiki-content'); const v = document.querySelector('.ProseMirror');
        const t = ['Intro', 'Steps', 'First', 'Bullet a', 'A quote', 'Commands', 'make deploy', 'A', 'Final'];
        return { cls: window.__cls, h1Visible: v.querySelector('h1') ? getComputedStyle(v.querySelector('h1')).display : 'none', rows: t.map((x) => [x, pos(s, x), pos(v, x)]), serverH: Math.round(s.getBoundingClientRect().height), editorH: Math.round(document.querySelector('.nowo-wiki-comments-view').getBoundingClientRect().height), serverCardH: Math.round(document.querySelector('[data-nowo-wiki-server-body]').getBoundingClientRect().height) };
      });
      out[`${w}-${motion}`] = { before, after, ...detail };
      await p.close();
    }
  }
  // 1.31 (item 4a): a leading .ProseMirror-widget (div or span) does not push the first block down, and the block after
  // a deduped title starts where the server body's first block does.
  for (const w of [1280, 390]) {
    const p = await b.newPage({ viewport: { width: w, height: 900 } });
    await p.goto('http://127.0.0.1:8000/widget.html');
    await p.waitForTimeout(200);
    out[`widget-${w}`] = await p.evaluate(() => {
      const offset = (id, sel) => { const box = document.querySelector(`[data-testid=${id}]`); const card = box.querySelector('.nowo-ui-card'); const root = box.querySelector('.nowo-wiki-content, .ProseMirror'); const el = [...root.querySelectorAll(sel)].find((e) => getComputedStyle(e).display !== 'none'); return Math.round((el.getBoundingClientRect().top - card.getBoundingClientRect().top) * 10) / 10; };
      return { server: offset('server', 'h1'), div: offset('editor-div', 'h1'), span: offset('editor-span', 'h1'), serverP: offset('server-deduped', 'p'), dedupedP: offset('editor-deduped', 'p'), dedupedH1: getComputedStyle(document.querySelector('[data-testid=editor-deduped] h1')).display };
    });
    await p.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await b.close();
  const failures = [];
  for (const [key, run] of Object.entries(out)) {
    if (key.startsWith('widget-')) {
      if (run.div !== run.server || run.span !== run.server) failures.push(`${key}: the widget moves the title (${run.server} / ${run.div} / ${run.span})`);
      if (run.dedupedP !== run.serverP) failures.push(`${key}: after the deduped title the text is at ${run.dedupedP}, not ${run.serverP}`);
      if (run.dedupedH1 !== 'none') failures.push(`${key}: the deduped <h1> shows after a widget`);
      continue;
    }
    if (JSON.stringify(run.before.swap) !== JSON.stringify(run.after.swap)) failures.push(`${key}: the box moved`);
    if (run.before.after[1] !== run.after.after[1]) failures.push(`${key}: the next card moved`);
    if (run.cls > 0.001) failures.push(`${key}: CLS ${run.cls}`);
    if (run.h1Visible !== 'none') failures.push(`${key}: the deduped <h1> shows`);
    for (const [text, server, editor] of run.rows) if (server && editor && server[0] !== editor[0]) failures.push(`${key}: "${text}" at ${server[0]} / ${editor[0]}`);
  }
  if (failures.length) { console.error(failures.join('\n')); process.exit(1); }
  console.log('seamless swap: no movement');
})();
