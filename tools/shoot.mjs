// Screenshots + basic checks of the local site.
//   node tools/shoot.mjs <outDir> [path ...]
// Each path is shot at desktop (1440) and mobile (390). Reports console errors,
// failed requests and horizontal overflow.
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const [outDir, ...paths] = process.argv.slice(2);
const base = process.env.BASE || 'http://localhost:8080';
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const viewports = { desktop: { width: 1440, height: 900 }, mobile: { width: 390, height: 844 } };
const only = process.env.VIEW ? [process.env.VIEW] : Object.keys(viewports);

for (const p of paths.length ? paths : ['/']) {
  for (const name of only) {
    const ctx = await browser.newContext({ viewport: viewports[name], deviceScaleFactor: 1, reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    const problems = [];
    page.on('console', (m) => m.type() === 'error' && problems.push('console: ' + m.text()));
    page.on('requestfailed', (r) => problems.push('failed: ' + r.url()));
    page.on('response', (r) => r.status() >= 400 && problems.push(`${r.status()}: ${r.url()}`));
    const res = await page.goto(base + p, { waitUntil: 'load', timeout: 30000 });
    // Trigger lazy-loaded images before a full-page shot.
    await page.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 60)); }
      window.scrollTo(0, 0);
      document.querySelectorAll('.bi-reveal').forEach((el) => el.classList.add('is-in'));
      // Don't hang on an image that never settles.
      await Promise.race([
        Promise.all([...document.images].map((i) => (i.complete ? null : new Promise((r) => { i.onload = i.onerror = r; })))),
        new Promise((r) => setTimeout(r, 4000)),
      ]);
    });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    const file = path.join(outDir, `${p.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home'}-${name}.png`);
    await page.screenshot({ path: file, fullPage: !process.env.FOLD });
    console.log(`${res.status()} ${p} [${name}] overflow=${overflow}px ${problems.length ? '\n  ' + problems.join('\n  ') : ''}`);
    await ctx.close();
  }
}
await browser.close();
