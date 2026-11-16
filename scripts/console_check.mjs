// Browser check of the TEST site: opens each URL in Chromium (desktop 1440x900 and phone 390x844) and reports
// console errors and warnings, uncaught page errors and failed requests. Read-only: it only loads pages.
//   node scripts/console_check.mjs https://nevehairus.ussl.co.il/ https://nevehairus.ussl.co.il/hello-world/ ...
// Prints one JSON object: { "<url> @<viewport>": { status, console: [...], pageErrors: [...], failed: [...], motion: [...] } }.
// motion: what research/07 forbids that a page's CSS can switch on by itself, e.g. Elementor 4.4.0's
// `html { scroll-behavior: smooth }` (§5.5: a jump to an anchor is instant; the child theme turns it off).
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const HOST = 'nevehairus.ussl.co.il';
const urls = process.argv.slice(2);
for (const u of urls) {
  if (new URL(u).hostname !== HOST) {
    console.error(`refusing ${new URL(u).hostname}: only ${HOST}`);
    process.exit(2);
  }
}
const viewports = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844 } };
const proxy = process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined;
const browser = await chromium.launch({ proxy });
const out = {};
for (const [vpName, viewport] of Object.entries(viewports)) {
  const ctx = await browser.newContext({ viewport, locale: 'he-IL' });
  for (const url of urls) {
    const page = await ctx.newPage();
    const rec = { status: null, console: [], pageErrors: [], failed: [], motion: [] };
    page.on('console', (m) => {
      if (m.type() === 'error' || m.type() === 'warning') rec.console.push(`${m.type()}: ${m.text().slice(0, 300)}`);
    });
    page.on('pageerror', (e) => rec.pageErrors.push(String(e).slice(0, 300)));
    page.on('requestfailed', (r) => rec.failed.push(`${r.failure()?.errorText || 'failed'} ${r.url().slice(0, 200)}`));
    page.on('response', (r) => {
      if (r.status() >= 400) rec.failed.push(`HTTP ${r.status()} ${r.url().slice(0, 200)}`);
    });
    try {
      const res = await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
      rec.status = res ? res.status() : null;
      await page.waitForTimeout(1500);
      const scroll = await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior);
      if (scroll !== 'auto') rec.motion.push(`html scroll-behavior: ${scroll} (an in-page jump would glide)`);
    } catch (e) {
      rec.pageErrors.push(`navigation: ${String(e).slice(0, 200)}`);
    }
    out[`${url} @${vpName}`] = rec;
    await page.close();
  }
  await ctx.close();
}
await browser.close();
console.log(JSON.stringify(out, null, 1));
