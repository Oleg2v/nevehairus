#!/usr/bin/env node
/* =========================================================================
   Optional visual check (plugin/SPEC.md §11.4).
   Serves the repository on a local port, opens the assembled test pages
   (plugin/tests/out/page-*.html, written by run.php) and the built prototype pages
   at 1440×900 and 390×844, normal / ?plain=1 / reduced motion, with the cookie
   consent pre-set, takes full-page screenshots into out/shots/, computes the share
   of differing pixels in the browser (canvas) and writes out/shots/report.json.

     node plugin/tests/shots.mjs [--page=home|main-home] [--mode=normal|plain|reduced]
                                 [--viewport=desktop|mobile] [--threshold=0.01]
     node plugin/tests/shots.mjs --behaviour     the core scripts in a real browser instead:
                                 cookie consent, display switches, announcements, form validation,
                                 the token refresh and the server answers (mocked), the visit bar

   Needs Node 22, the globally installed Playwright (1.63) and /usr/bin/google-chrome.
   Local files only: nothing leaves this machine. Exit 1 when a pair differs by more
   than the threshold (default 1 %).
   ========================================================================= */
import { createRequire } from "node:module";
import http from "node:http";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, "..", "..");
const SHOTS = path.join(HERE, "out", "shots");
const arg = (name, dflt) => {
  const a = process.argv.find((x) => x.startsWith(`--${name}=`));
  return a ? a.split("=")[1] : dflt;
};
const PAGES = [
  { name: "home", plugin: "plugin/tests/out/page-home.html", proto: "prototype/index.html" },
  { name: "main-home", plugin: "plugin/tests/out/page-main-home.html", proto: "prototype/program-main-home.html" },
].filter((p) => !arg("page") || p.name === arg("page"));
const VIEWPORTS = [
  { name: "desktop", width: 1440, height: 900 },
  { name: "mobile", width: 390, height: 844 },
].filter((v) => !arg("viewport") || v.name === arg("viewport"));
const MODES = ["normal", "plain", "reduced"].filter((m) => !arg("mode") || m === arg("mode"));
const THRESHOLD = parseFloat(arg("threshold", "0.01"));

const TYPES = {
  ".html": "text/html; charset=utf-8", ".css": "text/css", ".js": "text/javascript", ".mjs": "text/javascript",
  ".json": "application/json", ".woff2": "font/woff2", ".webp": "image/webp", ".avif": "image/avif",
  ".svg": "image/svg+xml", ".png": "image/png", ".jpg": "image/jpeg",
};

function serve() {
  const server = http.createServer((req, res) => {
    const rel = decodeURIComponent(new URL(req.url, "http://localhost").pathname);
    const file = path.normalize(path.join(ROOT, rel));
    if (!file.startsWith(ROOT) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) {
      res.writeHead(404); res.end("not found"); return;
    }
    res.writeHead(200, { "Content-Type": TYPES[path.extname(file)] || "application/octet-stream" });
    fs.createReadStream(file).pipe(res);
  });
  return new Promise((resolve) => server.listen(0, "127.0.0.1", () => resolve(server)));
}

async function shoot(browser, base, rel, viewport, mode, file) {
  const context = await browser.newContext({
    viewport: { width: viewport.width, height: viewport.height },
    deviceScaleFactor: 1,
    reducedMotion: mode === "reduced" ? "reduce" : "no-preference",
  });
  await context.addInitScript(() => {
    try {
      localStorage.setItem("nh-consent-v1", JSON.stringify({ necessary: true, stats: false, media: false, date: "2026-11-16T00:00:00.000Z", v: 1 }));
    } catch (e) { /* storage blocked */ }
  });
  const page = await context.newPage();
  await page.goto(`${base}/${rel}${mode === "plain" ? "?plain=1" : ""}`, { waitUntil: "load" });
  await page.evaluate(async () => {
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    document.querySelectorAll("img[loading='lazy']").forEach((img) => { img.loading = "eager"; });
    // walk down once, so lazy images and "once seen" states load the same way in both pages
    for (let y = 0; y < document.documentElement.scrollHeight; y += Math.round(innerHeight * 0.8)) {
      scrollTo(0, y);
      await sleep(40);
    }
    await document.fonts.ready;
    await Promise.allSettled(Array.from(document.images).map((img) => img.decode()));
    scrollTo(0, 0);
    await sleep(200);
  });
  await page.waitForTimeout(mode === "normal" ? 2600 : 600);            // the cover's entrance (≤ 1.8 s), the door, the glint
  await page.screenshot({ path: file, fullPage: true });
  await context.close();
}

async function diffShare(browser, a, b) {
  const page = await browser.newPage();
  const share = await page.evaluate(async ([da, db]) => {
    const load = (src) => new Promise((resolve, reject) => { const i = new Image(); i.onload = () => resolve(i); i.onerror = reject; i.src = src; });
    const [ia, ib] = await Promise.all([load(da), load(db)]);
    const w = Math.max(ia.width, ib.width), h = Math.max(ia.height, ib.height);
    const px = (img) => {
      const c = document.createElement("canvas"); c.width = w; c.height = h;
      const x = c.getContext("2d", { willReadFrequently: true }); x.fillStyle = "#ff00ff"; x.fillRect(0, 0, w, h); x.drawImage(img, 0, 0);
      return x.getImageData(0, 0, w, h).data;
    };
    const pa = px(ia), pb = px(ib);
    let diff = 0;
    for (let i = 0; i < pa.length; i += 4) {
      if (Math.abs(pa[i] - pb[i]) > 24 || Math.abs(pa[i + 1] - pb[i + 1]) > 24 || Math.abs(pa[i + 2] - pb[i + 2]) > 24) diff++;
    }
    return { share: diff / (w * h), w, h, ha: ia.height, hb: ib.height };
  }, [`data:image/png;base64,${fs.readFileSync(a).toString("base64")}`, `data:image/png;base64,${fs.readFileSync(b).toString("base64")}`]);
  await page.close();
  return share;
}

const server = await serve();
const base = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath: fs.existsSync("/usr/bin/google-chrome") ? "/usr/bin/google-chrome" : undefined });

/* ---------- --behaviour: the core scripts on the assembled home page -------------------------- */
async function behaviour() {
  let failed = 0;
  const ok = (c, m) => { if (!c) failed++; console.log((c ? "ok   " : "FAIL ") + m); };
  const url = `${base}/plugin/tests/out/page-home.html`;
  const json = (body) => ({ status: 200, contentType: "application/json", headers: { "access-control-allow-origin": "*" }, body: JSON.stringify(body) });
  {
    const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const errors = [];
    p.on("pageerror", (e) => errors.push(e.message));
    let answer = null, posted = "", tokens = 0, tokenAt = 0, postAt = 0;
    await p.route("https://nh.test/wp-admin/admin-ajax.php?**", (r) => { tokens++; tokenAt = Date.now(); r.fulfill(json({ nonce: "fresh-nonce", t: 1763000000, s: "fresh-sig" })); });
    await p.route("https://nh.test/wp-admin/admin-post.php", (r) => {
      posted = (r.request().postDataBuffer() || Buffer.alloc(0)).toString("utf8");
      postAt = Date.now();
      return answer ? r.fulfill(json(answer)) : r.abort();
    });
    await p.goto(url, { waitUntil: "load" });
    ok(await p.evaluate(() => !document.getElementById("nh-cookies").hidden && document.documentElement.classList.contains("nh-has-cookies")), "first visit: the cookie banner is shown");
    await p.click("[data-consent='settings']");
    ok(await p.evaluate(() => !document.getElementById("nh-cookie-prefs").hidden && document.activeElement.id === "nh-c-stats"), "settings open, focus on the first choice");
    await p.click("[data-consent='all']");
    const rec = await p.evaluate(() => JSON.parse(localStorage.getItem("nh-consent-v1")));
    ok(rec && rec.media && rec.stats && rec.v === 1, "consent saved in this browser");
    ok(await p.evaluate(() => document.getElementById("nh-cookies").hidden && document.activeElement.id === "main"), "banner hidden, focus back on main");
    await p.click(".v3-plainbtn");
    await p.waitForTimeout(200);
    ok(await p.evaluate(() => document.documentElement.classList.contains("nh-plainmode") && localStorage.getItem("nh-plain") === "on"), "plain language on, stored");
    ok(await p.evaluate(() => Array.from(document.querySelectorAll("[data-plain-toggle]:not([data-plain-toggle='action'])")).every((b) => b.getAttribute("aria-pressed") === "true")
      && document.querySelector("[data-plain-toggle='action']").getAttribute("aria-pressed") === null), "every switch says pressed; the plain box action button has no aria-pressed");
    ok((await p.evaluate(() => document.querySelector("[data-announce]").textContent)) === "הדף מוצג עכשיו בשפה פשוטה.", "announced in the live region");
    await p.click("[data-plain-toggle='action']");
    ok(await p.evaluate(() => !document.documentElement.classList.contains("nh-plainmode")), "the plain box button switches back");
    await p.click(".v3-footer-toggles [data-calm-toggle]");
    await p.click(".v3-footer-toggles [data-motion-toggle]");
    ok(await p.evaluate(() => document.documentElement.classList.contains("nh-calm") && document.documentElement.classList.contains("nh-reduce-motion")), "calm mode and reduced motion from the footer switches");
    await p.click("[data-cookie-settings]");
    ok(await p.evaluate(() => !document.getElementById("nh-cookies").hidden && document.activeElement.id === "nh-cookies-title" && document.getElementById("nh-c-media").checked), "cookie settings reopened, choices restored");
    await p.click("[data-consent='necessary']");
    await p.click("#door button[type='submit']");
    const sum = await p.evaluate(() => { const s = document.querySelector("#door [data-error-summary]"); return { shown: !s.hidden, n: s.querySelectorAll("li").length, focus: document.activeElement === s }; });
    ok(sum.shown && sum.focus && sum.n === 3, "empty submit: the error summary, focused, three errors");
    ok(await p.evaluate(() => document.getElementById("door-name").getAttribute("aria-invalid") === "true"), "fields marked aria-invalid");
    ok(tokens === 1 && await p.evaluate(() => document.querySelector("#door input[name='_nh_nonce']").value === "fresh-nonce"), "a fresh token on the first focus, once");
    await p.fill("#door-name", "דנה");
    ok(await p.evaluate(() => !document.getElementById("door-name").hasAttribute("aria-invalid")), "an error clears on input once valid");
    const fill = async () => { await p.fill("#door-name", "דנה"); await p.fill("#door-phone", "0501234567"); await p.check("#door-consent"); };
    answer = { ok: false, status: "invalid", errors: { phone: "tel" }, message: "" };
    await fill(); await p.click("#door button[type='submit']"); await p.waitForTimeout(400);
    ok(posted === "" && await p.evaluate(() => document.querySelector("#door button[type='submit']").disabled), "sent right after the fresh token: the post waits (the server drops a form younger than 3 s), the button disabled");
    for (let i = 0; i < 60 && !posted; i++) await p.waitForTimeout(100);
    await p.waitForTimeout(400);
    ok(postAt - tokenAt >= 3400, `the post left ${postAt - tokenAt} ms after the fresh token (at least 3.5 s)`);
    ok(/name="action"\r\n\r\nnh_visit/.test(posted) && /name="nh_js"\r\n\r\n1/.test(posted), "posted to admin-post.php (action=nh_visit, nh_js=1)");
    ok(await p.evaluate(() => document.getElementById("door-phone").getAttribute("aria-invalid") === "true"), "server 'invalid' mapped to the field");
    answer = { ok: true, status: "sent", errors: {}, message: "" };
    await p.click("#door button[type='submit']"); await p.waitForTimeout(400);
    ok(await p.evaluate(() => { const s = document.querySelector("#door [data-success]"); return !s.hidden && document.activeElement === s && document.getElementById("door-name").value === ""; }), "sent: the success message, focused; the form reset");
    answer = { ok: false, status: "limited", errors: {}, message: "נשלחו כבר כמה בקשות." };
    await fill(); await p.click("#door button[type='submit']"); await p.waitForTimeout(400);
    ok(await p.evaluate(() => document.querySelector("#door [data-error-summary]").textContent.includes("נשלחו כבר כמה בקשות")), "limited: the server's message in the summary");
    answer = null;
    await p.click("#door button[type='submit']"); await p.waitForTimeout(400);
    ok(await p.evaluate(() => document.querySelector("#door [data-error-summary]").textContent.includes("משהו השתבש") && !document.querySelector("#door button[type='submit']").disabled), "network error: the settings' error message, the button enabled again");
    ok(errors.length === 0, "no script errors" + (errors.length ? ": " + errors.join("; ") : ""));
    await p.close();
  }
  {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
    await ctx.addInitScript(() => localStorage.setItem("nh-consent-v1", JSON.stringify({ necessary: true, stats: false, media: false, date: "x", v: 1 })));
    const p = await ctx.newPage();
    await p.goto(url, { waitUntil: "load" });
    ok(await p.evaluate(() => document.getElementById("nh-cookies").hidden), "consent stored: no banner");
    ok(await p.evaluate(() => !document.querySelector("[data-bar]").classList.contains("is-on")), "visit bar hidden while the cover's button is on screen");
    await p.evaluate(() => window.scrollTo(0, 1600)); await p.waitForTimeout(400);
    ok(await p.evaluate(() => document.querySelector("[data-bar]").classList.contains("is-on") && document.querySelector("[data-bar-cta]").getAttribute("href") === "#door"), "visit bar shown after the cover, its link to this page's door");
    await p.evaluate(() => document.getElementById("door").scrollIntoView()); await p.waitForTimeout(400);
    ok(await p.evaluate(() => !document.querySelector("[data-bar]").classList.contains("is-on")), "visit bar hidden at the door");
    await ctx.close();
  }
  {
    const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await p.goto(`${base}/plugin/tests/out/cover--prototype.html`, { waitUntil: "load" });
    await p.mouse.wheel(0, 700); await p.waitForTimeout(600);
    ok(await p.evaluate(() => parseFloat(getComputedStyle(document.querySelector(".v3-room")).getPropertyValue("--t")) > 0.5), "the cover's ב thins while scrolling");
    await p.close();
  }
  return failed;
}
if (process.argv.includes("--behaviour")) {
  const failed = await behaviour();
  await browser.close();
  server.close();
  console.log(failed ? `${failed} behaviour check(s) failed` : "behaviour: all checks passed");
  process.exit(failed ? 1 : 0);
}
fs.mkdirSync(SHOTS, { recursive: true });
const report = [];
let worst = 0;
try {
  for (const p of PAGES) {
    if (!fs.existsSync(path.join(ROOT, p.plugin))) { console.log(`skip ${p.name}: run php plugin/tests/run.php first`); continue; }
    for (const v of VIEWPORTS) {
      for (const m of MODES) {
        const fa = path.join(SHOTS, `${p.name}-${v.name}-${m}-prototype.png`);
        const fb = path.join(SHOTS, `${p.name}-${v.name}-${m}-plugin.png`);
        await shoot(browser, base, p.proto, v, m, fa);
        await shoot(browser, base, p.plugin, v, m, fb);
        const d = await diffShare(browser, fa, fb);
        worst = Math.max(worst, d.share);
        report.push({ page: p.name, viewport: v.name, mode: m, prototype: path.relative(HERE, fa), plugin: path.relative(HERE, fb), size: [d.w, d.h], heights: { prototype: d.ha, plugin: d.hb }, diffShare: Number(d.share.toFixed(5)) });
        console.log(`${p.name.padEnd(9)} ${v.name.padEnd(7)} ${m.padEnd(7)} ${(d.share * 100).toFixed(2).padStart(6)} %  (heights ${d.ha} / ${d.hb})`);
      }
    }
  }
} finally {
  await browser.close();
  server.close();
}
fs.writeFileSync(path.join(SHOTS, "report.json"), JSON.stringify({ threshold: THRESHOLD, worst, pairs: report }, null, 2));
console.log(`report: ${path.relative(ROOT, path.join(SHOTS, "report.json"))}; worst ${(worst * 100).toFixed(2)} % (threshold ${(THRESHOLD * 100).toFixed(2)} %)`);
process.exit(worst > THRESHOLD ? 1 : 0);
