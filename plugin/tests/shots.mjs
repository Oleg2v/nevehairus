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
     node plugin/tests/shots.mjs --behaviour     the core scripts in a real browser instead (v3.2):
                                 nothing opens or moves by itself, no scroll listener, no morph data, the
                                 one soft appearance of prints (also after a reload mid-page, never in calm
                                 mode), what moves on hover, focus and scroll (colours ≤ 150 ms, the
                                 appearance), the cookie panel from the footer's link, the visit buttons
                                 (#door, #visit), focus after an in-page link, a menu link and a landing
                                 with #…, the phone menu, display switches, announcements, photos that
                                 cannot be shown, form validation, the token refresh, server answers (mocked)

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
  await page.waitForTimeout(600);                                        // v3.2: nothing animates on load
  // the footer is not compared: the prototype's is its own markup, the site's is Elementor's own widgets (research/07
  // §12.6), which the harness does not render (it holds only a stand-in list and the display widget)
  await page.evaluate(() => document.querySelectorAll("body > footer").forEach((f) => { f.style.display = "none"; }));
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
/* v3.2 (research/07 §13 «Стенд», §17.1): nothing opens by itself, no banner of its own, no visit bar, no morph; the
   one soft appearance (only `> picture`, once, never with reduced motion or in calm mode, never for a print already on
   screen); what really moves on hover, focus and scroll; the cookie panel from the footer's «הגדרות עוגיות»; the visit
   buttons; focus after an in-page link, after a link of the phone menu and on a landing with #…; no scroll listener
   and no running animation; photos that cannot be shown; then the display switches and the forms as before. The
   harness renders hrefs with the stub's home_url() (https://nh.test/…): links that must stay on this page are pointed
   at their #fragment first, as a real site's would be. */
async function behaviour() {
  let failed = 0;
  const ok = (c, m) => { if (!c) failed++; console.log((c ? "ok   " : "FAIL ") + m); };
  const url = `${base}/plugin/tests/out/page-home.html`;
  const house = `${base}/plugin/tests/out/page-main-home.html`;
  const cookieLink = "footer.elementor-location-footer a[href='#nh-cookies']";   // the site's footer: a Text Editor link
  const json = (body) => ({ status: 200, contentType: "application/json", headers: { "access-control-allow-origin": "*" }, body: JSON.stringify(body) });
  const scrollYOk = (y) => y > 400;                                     // the reload kept the place in the middle of the page
  const local = (p, sel) => p.evaluate((s) => document.querySelectorAll(s).forEach((a) => {
    const u = new URL(a.getAttribute("href"), location.href);
    if (u.hash) a.setAttribute("href", u.hash);
  }), sel);
  {
    // 1. first visit: nothing opens by itself, nothing moves, no scroll listener; the visit buttons; the cookie panel
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    const errors = [];
    p.on("pageerror", (e) => errors.push(e.message));
    await p.goto(url, { waitUntil: "load" });
    await p.waitForTimeout(600);
    ok(await p.evaluate(() => document.getElementById("nh-cookies").hidden && !document.querySelector("dialog[open]")), "first visit: the cookie panel hidden, no dialog open");
    ok(await p.evaluate(() => document.getAnimations().length === 0), "after load: no animation or transition running");
    const cdp = await ctx.newCDPSession(p);
    const types = [];
    for (const expr of ["window", "document"]) {
      const { result } = await cdp.send("Runtime.evaluate", { expression: expr });
      const { listeners } = await cdp.send("DOMDebugger.getEventListeners", { objectId: result.objectId });
      types.push(...listeners.map((l) => l.type));
    }
    ok(!types.includes("scroll") && !types.includes("resize"), `no scroll or resize listener on window/document (${[...new Set(types)].join(", ")})`);
    ok(await p.evaluate(() => Array.from(document.querySelectorAll("a[data-visit-cta]")).every((a) => a.getAttribute("href") === "#door")), "every visit button leads to this page's door (#door)");
    ok(await p.evaluate(() => !document.querySelector(".v3-mast__running, .v3-progress, .v3-display, [data-bar]")), "no running head, progress line, display panel or visit bar");
    ok(await p.evaluate(() => !document.getElementById("nh-letters") && !document.querySelector("[data-vt], [data-ring], .v3-glint, .v3-works")), "no morph data, page-transition names, rings or palm swap");
    // the cookie panel: the footer's own link (no data attribute, as the site's Text Editor writes it)
    ok(await p.locator(cookieLink).count() === 1, "the footer has «הגדרות עוגיות» (href #nh-cookies)");
    await p.click(cookieLink);
    ok(await p.evaluate(() => !document.getElementById("nh-cookies").hidden && document.activeElement.id === "nh-cookies-title"), "the footer's link opens the panel, focus on its title");
    ok(await p.evaluate(() => {
      const panel = document.getElementById("nh-cookies"), foot = document.querySelector("body > footer"), cs = getComputedStyle(panel);
      return !!(foot.compareDocumentPosition(panel) & Node.DOCUMENT_POSITION_FOLLOWING) && cs.position === "static" && cs.boxShadow === "none" && document.getAnimations().length === 0;
    }), "in the flow right after the footer: not over the content, no shadow, no animation");
    await p.keyboard.press("Escape");
    ok(await p.evaluate((sel) => document.getElementById("nh-cookies").hidden && document.activeElement === document.querySelector(sel), cookieLink), "Esc hides it, focus back on the link");
    await p.click(cookieLink);
    await p.click("[data-consent='close']");
    ok(await p.evaluate((sel) => document.getElementById("nh-cookies").hidden && document.activeElement === document.querySelector(sel), cookieLink), "«סגירה» hides it, focus back on the link");
    await p.click(cookieLink);
    await p.check("#nh-c-media");
    await p.click("[data-consent='save']");
    const rec = await p.evaluate(() => JSON.parse(localStorage.getItem("nh-consent-v1")));
    ok(rec && rec.media === true && rec.stats === false && rec.v === 1, "«שמירת הבחירה» stores the choice in this browser");
    ok(await p.evaluate((sel) => document.getElementById("nh-cookies").hidden && document.activeElement === document.querySelector(sel), cookieLink), "saved: hidden, focus back on the link");
    await p.click(cookieLink);
    ok(await p.evaluate(() => document.getElementById("nh-c-media").checked), "opened again: the box shows the stored choice");
    await p.keyboard.press("Escape");
    // 2. an in-page link: the browser jumps at once, the target's heading takes focus
    await local(p, ".nh-nav a");
    await p.evaluate(() => window.scrollTo(0, 0));
    const y0 = await p.evaluate(() => scrollY);
    await p.click(".nh-nav a[href='#homes']");
    await p.waitForTimeout(150);
    ok(await p.evaluate(() => document.activeElement && document.activeElement.id === document.getElementById("homes").getAttribute("aria-labelledby")), "menu link #homes: focus on the spread's heading");
    ok(await p.evaluate((y) => scrollY !== y && getComputedStyle(document.documentElement).scrollBehavior !== "smooth", y0), "the page jumped there, without smooth scrolling");
    // the assembled page carries Elementor 4.4.0's own `html { scroll-behavior: smooth }` (its frontend.min.css, printed
    // first on the site): the child theme's :root rule must win, and a jump must land within one frame
    ok(await p.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior === "auto"
      && Array.from(document.styleSheets).some((s) => s.ownerNode && s.ownerNode.id === "elementor-frontend-stub-css")), "Elementor's smooth scrolling is on the page, the theme turns it off (scroll-behavior: auto)");
    const jump = await p.evaluate(() => new Promise((resolve) => {
      window.scrollTo(0, 0);
      requestAnimationFrame(() => {
        document.querySelector(".nh-nav a[href='#door']").click();
        requestAnimationFrame(() => { const first = scrollY; setTimeout(() => resolve({ first, last: scrollY }), 400); });
      });
    }));
    ok(jump.first > 1000 && jump.first === jump.last, `#door: the whole jump in one frame (${jump.first} → ${jump.last})`);
    // 3. the display switches: the masthead's plain button, the plain box, the footer's calm switch
    await p.click(".v3-plainbtn");
    await p.waitForTimeout(200);
    ok(await p.evaluate(() => document.documentElement.classList.contains("nh-plainmode") && localStorage.getItem("nh-plain") === "on"), "plain language on, stored");
    ok(await p.evaluate(() => Array.from(document.querySelectorAll("[data-plain-toggle]:not([data-plain-toggle='action'])")).every((b) => b.getAttribute("aria-pressed") === "true")
      && document.querySelector("[data-plain-toggle='action']").getAttribute("aria-pressed") === null), "every switch says pressed; the plain box action button has no aria-pressed");
    ok((await p.evaluate(() => document.querySelector("[data-announce]").textContent)) === "הדף מוצג עכשיו בשפה פשוטה.", "announced in the live region");
    await p.click("[data-plain-toggle='action']");
    ok(await p.evaluate(() => !document.documentElement.classList.contains("nh-plainmode")), "the plain box button switches back");
    await p.click(".v3-switches.nh-on-dark [data-calm-toggle]");
    ok(await p.evaluate(() => document.documentElement.classList.contains("nh-calm") && Array.from(document.querySelectorAll("[data-calm-toggle]")).every((b) => b.getAttribute("aria-pressed") === "true")), "calm mode from the footer's switch; every calm switch says pressed");
    ok(await p.evaluate(() => getComputedStyle(document.querySelector(".v3-switches.nh-on-dark .v3-toggle__state"), "::before").content.includes("פועל")), "the switch says «פועל»");
    await p.click(".v3-switches.nh-on-dark [data-calm-toggle]");
    ok(errors.length === 0, "no script errors" + (errors.length ? ": " + errors.join("; ") : ""));
    await ctx.close();
  }
  {
    // 4. the one soft appearance: only the photo frame of a print below the first screen, once, after its image
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    await p.goto(url, { waitUntil: "load" });
    await p.waitForTimeout(300);
    const first = await p.evaluate(() => Array.from(document.querySelectorAll(".v3-print[data-reveal]")).map((f) => f.className));
    ok(first.length > 0 && first.every((c) => !c.includes("is-shown")), `${first.length} prints; none shown yet`);
    ok(await p.evaluate(() => Array.from(document.querySelectorAll(".v3-print[data-reveal].is-waiting figcaption")).every((c) => getComputedStyle(c).opacity === "1" && getComputedStyle(c).transform === "none")), "a waiting print keeps its caption visible and still");
    const h = await p.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < h; y += 300) { await p.evaluate((v) => window.scrollTo(0, v), y); await p.waitForTimeout(60); }
    await p.waitForTimeout(800);
    ok(await p.evaluate(() => Array.from(document.querySelectorAll(".v3-print[data-reveal]")).every((f) => f.classList.contains("is-shown") && !f.classList.contains("is-waiting"))), "after a slow scroll every print is shown");
    ok(await p.evaluate(() => Array.from(document.querySelectorAll(".v3-print[data-reveal] > picture")).every((pic) => getComputedStyle(pic).opacity === "1" && getComputedStyle(pic).transform === "none")), "every photo frame at rest: opacity 1, no transform");
    ok(await p.evaluate(() => document.getAnimations().length === 0), "nothing runs afterwards");
    await ctx.close();
    const rctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: "reduce" });
    const r = await rctx.newPage();
    await r.goto(url, { waitUntil: "load" });
    await r.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight)); await r.waitForTimeout(400);
    ok(await r.evaluate(() => !document.querySelector(".v3-print.is-waiting, .v3-print.is-shown")), "reduced motion: no print is ever hidden or animated");
    await rctx.close();
    // calm mode chosen earlier (stored): the appearance is off as with reduced motion
    const cctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    await cctx.addInitScript(() => { try { localStorage.setItem("nh-calm", "on"); } catch (e) { /* storage blocked */ } });
    const c = await cctx.newPage();
    await c.goto(url, { waitUntil: "load" });
    await c.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight)); await c.waitForTimeout(400);
    ok(await c.evaluate(() => document.documentElement.classList.contains("nh-calm") && !document.querySelector(".v3-print.is-waiting, .v3-print.is-shown")), "calm mode: no print is ever hidden or animated");
    await cctx.close();
    // a print on screen at the observer's first report is never hidden: a reload in the middle of the page
    const mctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const m = await mctx.newPage();
    await m.goto(url, { waitUntil: "load" });
    await m.evaluate(() => document.querySelector(".v3-day .v3-print, .v3-print").scrollIntoView({ block: "center" }));
    await m.waitForTimeout(500);
    await m.reload({ waitUntil: "load" });
    await m.waitForTimeout(400);
    const mid = await m.evaluate(() => Array.from(document.querySelectorAll(".v3-print[data-reveal]")).filter((f) => {
      const r = f.getBoundingClientRect(); return r.bottom > 0 && r.top < innerHeight;
    }).map((f) => f.className));
    ok(scrollYOk(await m.evaluate(() => scrollY)) && mid.length > 0 && mid.every((cl) => !cl.includes("is-waiting")), `a reload mid-page: the ${mid.length} print(s) on screen are not hidden`);
    await mctx.close();
  }
  {
    // 4b. what really moves (research/07 §17.1): hover every link and button, Tab through the page, scroll to the end
    // and back; every transition that runs is a colour (or the index rows' backdrop opacity) of at most 150 ms, or the
    // one appearance of a print's photo frame (opacity/transform, 320 ms); no animation at all
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    await ctx.addInitScript(() => {
      window.__nhRuns = [];
      const dur = (el, pseudo, prop) => {
        const cs = getComputedStyle(el, pseudo || null);
        const props = cs.transitionProperty.split(",").map((x) => x.trim()), durs = cs.transitionDuration.split(",").map((x) => parseFloat(x) * (x.trim().endsWith("ms") ? 1 : 1000));
        const short = prop.replace(/^border-(top|right|bottom|left)-color$/, "border-color");
        let i = props.indexOf(prop);
        if (i < 0) i = props.indexOf(short);
        if (i < 0) i = props.findIndex((x) => x === "all" || prop.startsWith(x + "-"));
        return Math.round(durs[(i < 0 ? 0 : i) % durs.length]);
      };
      document.addEventListener("transitionrun", (e) => {
        const t = e.target;
        window.__nhRuns.push({ kind: "transition", prop: e.propertyName, pseudo: e.pseudoElement, ms: dur(t, e.pseudoElement, e.propertyName),
          reveal: !!(t.matches && t.matches(".v3-print[data-reveal].is-shown > picture")), where: (t.className && t.className.baseVal === undefined ? t.className : t.tagName) + "" });
      }, true);
      document.addEventListener("animationstart", (e) => { window.__nhRuns.push({ kind: "animation", prop: e.animationName, where: e.target.tagName }); }, true);
    });
    const p = await ctx.newPage();
    await p.goto(url, { waitUntil: "load" });
    const targets = await p.$$("a[href], button");
    let hovered = 0;
    for (const t of targets) {
      if (!(await t.isVisible())) continue;
      try { await t.hover({ timeout: 800 }); hovered++; await p.waitForTimeout(20); } catch (e) { /* covered */ }
    }
    await p.mouse.move(1, 1);
    for (let i = 0; i < 60; i++) { await p.keyboard.press("Tab"); await p.waitForTimeout(10); }
    const h = await p.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < h; y += 400) { await p.evaluate((v) => window.scrollTo(0, v), y); await p.waitForTimeout(40); }
    for (let y = h; y >= 0; y -= 800) { await p.evaluate((v) => window.scrollTo(0, v), y); await p.waitForTimeout(20); }
    await p.waitForTimeout(500);
    const runs = await p.evaluate(() => window.__nhRuns);
    const colour = new Set(["color", "background-color", "border-top-color", "border-right-color", "border-bottom-color", "border-left-color", "text-decoration-color", "opacity"]);
    const bad = runs.filter((r) => r.kind === "animation" || (r.reveal ? !(["opacity", "transform"].includes(r.prop) && r.ms <= 320) : !(colour.has(r.prop) && r.ms <= 150)));
    ok(runs.length > 0 && bad.length === 0, `hover on ${hovered} links and buttons, 60 Tab stops, a scroll down and up: ${runs.length} transition(s) ran, all colours ≤ 150 ms or the one appearance (320 ms)` + (bad.length ? "; not allowed: " + bad.slice(0, 6).map((r) => `${r.kind} ${r.prop} ${r.ms} ms on ${r.where}`).join("; ") : ""));
    ok(runs.some((r) => r.reveal) && runs.filter((r) => r.reveal).every((r) => r.ms === 320), "the one appearance ran (320 ms)");
    await ctx.close();
  }
  {
    // 4c. arriving from another page with #…, and the house page's visit buttons
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    await p.goto(url + "#homes", { waitUntil: "load" });
    await p.waitForTimeout(300);
    ok(await p.evaluate(() => document.activeElement && document.activeElement.id === document.getElementById("homes").getAttribute("aria-labelledby")), "a landing with #homes: focus on «שלושה בתים.» (the spread's heading)");
    await p.goto(house, { waitUntil: "load" });
    ok(await p.evaluate(() => { const a = Array.from(document.querySelectorAll("a[data-visit-cta]")); return a.length >= 3 && a.every((x) => x.getAttribute("href") === "#visit"); }), "the house page: every visit button leads to its own door (#visit)");
    ok(await p.evaluate(() => { const c = document.querySelectorAll(".nh-nav a[aria-current='true']"); return c.length === 1 && c[0].textContent.trim() === "הבתים שלנו" && !document.querySelector(".nh-nav a[aria-current='page']"); }), "the house page: «הבתים שלנו» is the current section (aria-current=true), no link says page");
    await ctx.close();
  }
  {
    // 4d. photos that cannot be shown (images off, a missing file): the room shows its sky, a print is not shown, the
    // emblem goes and the name stays — at once, nothing moves (research/07 §8.3 item 4)
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    await ctx.route(/\.(webp|avif|png|jpe?g)(\?.*)?$/, (r) => r.abort());
    const p = await ctx.newPage();
    await p.goto(house, { waitUntil: "load" });
    const h = await p.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < h; y += 500) { await p.evaluate((v) => window.scrollTo(0, v), y); await p.waitForTimeout(40); }
    await p.waitForTimeout(400);
    ok(await p.evaluate(() => { const r = document.querySelector(".v3-cover .v3-room"); return r.classList.contains("is-sky") && getComputedStyle(r.querySelector(".v3-room__photo")).backgroundImage.includes("gradient"); }), "images off: the cover's room shows its quiet sky");
    ok(await p.evaluate(() => Array.from(document.querySelectorAll(".v3-gallery > li")).every((li) => li.hidden && getComputedStyle(li).display === "none")), "images off: the gallery's cells are not shown (the grid closes up)");
    ok(await p.evaluate(() => { const e = document.querySelector("img.nh-logo__mark"); const n = document.querySelector(".nh-logo__name"); return (!e || e.hidden) && n && n.getBoundingClientRect().width > 0; }), "images off: the emblem goes, the name stays");
    ok(await p.evaluate(() => document.getAnimations().length === 0), "images off: nothing moves");
    await ctx.close();
  }
  {
    // 4e. 0.2.2: the video's «כן» is described by its question (full or plain wording on screen); plain language on a
    // page without plain text says so; a page that does not start with a cover starts below the fixed masthead
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    const errors = [];
    p.on("pageerror", (e) => errors.push(e.message));
    await p.goto(url, { waitUntil: "load" });
    await p.click(".nh-video__play");
    const ask = await p.evaluate(() => {
      const yes = document.activeElement, id = yes.getAttribute("aria-describedby") || "", q = id && document.getElementById(id);
      return { yes: yes.matches("[data-video-load]"), q: q ? q.textContent.trim() : "", full: !!(q && q.matches(".nh-video__consent > .v3-full")) };
    });
    ok(ask.yes && ask.full && ask.q.includes("YouTube"), `the consent question: focus on «כן», described by the question on screen («${ask.q.slice(0, 40)}…»)`);
    await p.click(".v3-plainbtn");
    ok(await p.evaluate(() => { const q = document.getElementById(document.querySelector("[data-video-load]").getAttribute("aria-describedby")); return !!q && q.matches(".v3-plain") && q.getClientRects().length > 0; }), "plain language on: «כן» is described by the plain wording");
    await p.click(".v3-plainbtn");
    await p.click("[data-video-cancel]");
    // a page without plain text: switching plain language on announces that, not a change
    await p.evaluate(() => document.querySelectorAll("main .v3-plain").forEach((el) => el.remove()));
    await p.click(".v3-plainbtn");
    await p.waitForTimeout(200);
    ok((await p.evaluate(() => document.querySelector("[data-announce]").textContent)) === "שפה פשוטה פועלת. לדף הזה אין עדיין נוסח בשפה פשוטה."
      && await p.evaluate(() => document.documentElement.classList.contains("nh-plainmode")), "a page without plain text: «…אין עדיין נוסח בשפה פשוטה», the choice is still on");
    await p.click(".v3-plainbtn");
    // no cover: main#content starts below the masthead (72px at 1440) with the theme's 2rem
    const pad = await p.evaluate(() => {
      const main = document.getElementById("content"), before = parseFloat(getComputedStyle(main).paddingTop);
      document.querySelectorAll(".v3-cover").forEach((el) => el.closest(".elementor-element").remove());
      const header = document.querySelector(".nh-header").getBoundingClientRect();
      return { before, after: parseFloat(getComputedStyle(main).paddingTop), mast: header.height };
    });
    ok(pad.before === 0 && Math.abs(pad.after - (72 + 32)) < 0.5 && pad.after > pad.mast, `no cover: main#content's top padding ${pad.after}px clears the ${pad.mast}px masthead (with a cover: ${pad.before}px)`);
    ok(errors.length === 0, "no script errors" + (errors.length ? ": " + errors.join("; ") : ""));
    await ctx.close();
  }
  {
    // 5. the phone menu: opened only by «תפריט», Esc returns focus to it, a link of the menu does not
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const p = await ctx.newPage();
    await p.goto(url, { waitUntil: "load" });
    ok(await p.evaluate(() => !document.getElementById("nh-menu").open), "the menu is closed until asked");
    await p.click("[data-menu-open]");
    ok(await p.evaluate(() => document.getElementById("nh-menu").open && document.querySelector("[data-menu-open]").getAttribute("aria-expanded") === "true"), "«תפריט» opens it (aria-expanded)");
    await p.keyboard.press("Escape");
    await p.waitForTimeout(100);
    ok(await p.evaluate(() => !document.getElementById("nh-menu").open && document.activeElement.matches("[data-menu-open]")), "Esc closes it, focus back on «תפריט»");
    await local(p, "#nh-menu nav a");
    await p.click("[data-menu-open]");
    await p.click("#nh-menu nav a[href='#day']");
    await p.waitForTimeout(200);
    ok(await p.evaluate(() => !document.getElementById("nh-menu").open && !document.getElementById("nh-menu").dataset.nav
      && document.activeElement.id === document.getElementById("day").getAttribute("aria-labelledby")), "a menu link closes it; focus on the target's heading, not on «תפריט»");
    await p.click("[data-menu-open]");
    await p.click("#nh-menu [data-menu-close]");
    await p.waitForTimeout(100);
    ok(await p.evaluate(() => !document.getElementById("nh-menu").open && document.activeElement.matches("[data-menu-open]")), "«סגירה» closes it, focus back on «תפריט»");
    await ctx.close();
  }
  {
    // 6. the visit form (nh-forms.js), as before
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
