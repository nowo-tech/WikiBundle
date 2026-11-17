// 1.40: Chromium check of section anchors in the read view (tests/Browser/section-links). Run: make check-section-links.
// - the "link to this section" control is hidden until hover / keyboard focus, named, and copies the page URL with
//   #nowo-h-<id> (clipboard), putting the fragment in the address bar;
// - page#nowo-h-<id> scrolls to the heading; a legacy #<id> scrolls to nowo-h-<id>; a missing section stays at the top;
// - a hidden page's placeholder carries no href, anchor or section text; axe-core finds no violation.
const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
  const b = await chromium.launch();
  const failures = [];
  const base = 'http://127.0.0.1:8000/index.html';
  const ctx = await b.newContext({ viewport: { width: 1280, height: 700 }, permissions: ['clipboard-read', 'clipboard-write'] });
  const p = await ctx.newPage();
  const errors = [];
  p.on('pageerror', (e) => errors.push(String(e)));

  // Hidden until hover or focus.
  await p.goto(base);
  await p.waitForTimeout(200);
  const control = '#nowo-h-install-guide .nowo-md-heading-anchor';
  const opacity = () => p.evaluate((s) => getComputedStyle(document.querySelector(s)).opacity, control);
  if ((await opacity()) !== '0') failures.push('the control shows without hover');
  await p.hover('#nowo-h-install-guide');
  if ((await opacity()) !== '1') failures.push('the control does not show on hover');
  await p.mouse.move(0, 0);
  await p.focus(control);
  if ((await opacity()) !== '1') failures.push('the control does not show on keyboard focus');
  const name = await p.evaluate((s) => document.querySelector(s).textContent.trim(), control);
  if (!name.startsWith('Link to this section')) failures.push(`accessible name: ${name}`);

  // Copy.
  await p.hover('#nowo-h-install-guide');
  await p.click(control);
  await p.waitForTimeout(200);
  const copied = await p.evaluate(() => navigator.clipboard.readText());
  if (copied !== `${base}#nowo-h-install-guide`) failures.push(`copied ${copied}`);
  if (!p.url().endsWith('#nowo-h-install-guide')) failures.push(`address bar ${p.url()}`);
  const flash = await p.getAttribute(control, 'title');
  if (flash !== 'Link copied') failures.push(`flash title ${flash}`);
  await p.screenshot({ path: '/work/section-link-copied.png' });

  // Fragments.
  const land = async (url) => {
    const q = await ctx.newPage();
    await q.goto(url);
    await q.waitForTimeout(300);
    const r = await q.evaluate(() => ({
      y: scrollY,
      install: document.getElementById('nowo-h-install-guide').getBoundingClientRect().top,
      nuevo: document.getElementById('nowo-h-año-nuevo').getBoundingClientRect().top,
    }));
    await q.close();
    return r;
  };
  const near = (top) => top >= 0 && top <= 120;
  const section = await land(`${base}#nowo-h-install-guide`);
  if (section.y < 100 || !near(section.install)) failures.push(`page#section did not land on the heading: ${JSON.stringify(section)}`);
  const legacy = await land(`${base}#install-guide`);
  if (legacy.y < 100 || !near(legacy.install)) failures.push(`a legacy #id did not land on its heading: ${JSON.stringify(legacy)}`);
  const unicode = await land(`${base}#a%C3%B1o-nuevo`);
  if (unicode.y < 100 || !near(unicode.nuevo)) failures.push(`a legacy Unicode #id did not land on its heading: ${JSON.stringify(unicode)}`);
  const missing = await land(`${base}#nowo-h-gone`);
  if (missing.y !== 0) failures.push(`a missing section scrolled to ${missing.y}`);

  // Hidden page placeholder.
  const hidden = await p.evaluate(() => {
    const s = document.querySelector('.nowo-md-ref--hidden');
    return { tag: s.tagName, href: s.getAttribute('href'), text: s.textContent };
  });
  if (hidden.tag !== 'SPAN' || hidden.href !== null || hidden.text !== 'Content not visible') failures.push(`placeholder ${JSON.stringify(hidden)}`);

  // Accessibility.
  await p.addScriptTag({ content: fs.readFileSync('/tmp/node_modules/axe-core/axe.min.js', 'utf8') });
  const axe = await p.evaluate(async () => (await window.axe.run(document.querySelector('[data-testid=wiki-content]'))).violations.map((v) => v.id));
  if (axe.length) failures.push(`axe: ${axe.join(', ')}`);
  if (errors.length) failures.push(`page errors: ${errors.join(' | ')}`);

  await b.close();
  if (failures.length) { console.error(failures.join('\n')); process.exit(1); }
  console.log('section-links: OK');
})();
