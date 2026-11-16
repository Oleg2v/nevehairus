/* =========================================================================
   נווה האירוס — nh-core.js (plugin nevehairus-site, core). No libraries.
   window.nh: the helpers every widget script uses, the display modes (plain
   language, calm mode), the one allowed reveal of print photos, photos that
   cannot be shown, the cookie-settings panel, the visit button and focus after
   an in-page link, the widget hook for Elementor (SPEC §3.12, §5.5; research/07
   §12.3). Ported from the v3.2 prototype: site.js 2 (the visit button and
   in-page anchors), 3 (cookie settings), 6 (the year) and v3.js 1 (plain
   language, calm mode, keepPlace), 2 (the one reveal), 3 (a photo that cannot
   be shown); the menu is masthead.js's, the video voices.js's, the forms
   nh-forms.js's. Nothing moves by itself (research/07 §4): no scroll listener,
   no ResizeObserver, no animation loop, nothing opens by itself; one
   IntersectionObserver (the reveal). Switches, cookie-settings links and
   in-page links are delegated, so widgets added later (Elementor editor) work too.
   ========================================================================= */
(() => {
  "use strict";
  const d = document, w = window, html = d.documentElement;
  if (w.nh && w.nh.__core) return;                                          // loaded twice: keep the first
  html.classList.add("v3-ready");                                           // states that need this script to undo them

  const data = w.nhData || {};
  const i18n = data.i18n || {};
  const store = {
    get(k) { try { return w.localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { w.localStorage.setItem(k, v); } catch (e) { /* private mode */ } },
  };
  const osReduced = () => !!(w.matchMedia && w.matchMedia("(prefers-reduced-motion: reduce)").matches);
  // the old "reduce motion" choice (nh-motion in localStorage, applied before paint) still counts
  const motionOK = () => !html.classList.contains("nh-reduce-motion") && !html.classList.contains("nh-calm") && !osReduced();
  // an open modal dialog makes the rest of the page inert: speak from inside it
  const announce = (msg) => {
    const el = d.querySelector("dialog[open] [data-announce-menu]") || d.querySelector("[data-announce]");
    if (!el || !msg) return;
    el.textContent = ""; setTimeout(() => { el.textContent = msg; }, 80);
  };

  /* ---------- the one allowed reveal (research/07 §4.2) --------------------------------
     A print photo (figure.v3-print[data-reveal] > picture) appears once, softly, when at
     least 15% of it is on screen and its image has loaded. Only below the first screen,
     only when motion is allowed, never in the Elementor preview; the caption never moves. */
  const reveal = () => {
    if (!motionOK() || !("IntersectionObserver" in w) || /[?&]elementor-preview=/.test(w.location.search)) return;
    const els = d.querySelectorAll(".v3-print[data-reveal]:not(.is-shown)");
    if (!els.length) return;
    const show = (el) => requestAnimationFrame(() => { el.classList.add("is-shown"); el.classList.remove("is-waiting"); });
    const io = new IntersectionObserver((entries) => entries.forEach((e) => {
      const el = e.target;
      if (!el.dataset.revealSeen) {                    // first report: never hide what is already on or above the screen
        el.dataset.revealSeen = "1";
        if (e.isIntersecting || e.boundingClientRect.top < 0) io.unobserve(el);
        else el.classList.add("is-waiting");
        return;
      }
      if (e.intersectionRatio < 0.15) return;          // isIntersecting is already true at the first pixel
      io.unobserve(el);
      const img = el.querySelector("img");
      if (!img || (img.complete && img.naturalWidth)) { show(el); return; }
      img.addEventListener("load", () => show(el), { once: true });
      img.addEventListener("error", () => show(el), { once: true });
      setTimeout(() => show(el), 2500);
    }), { threshold: 0.15 });
    els.forEach((el) => io.observe(el));
  };

  /* ---------- a photo that cannot be shown (images switched off, a missing file) — research/07 §8.3 item 4 -------
     The design holds without photos: a letter's room shows its quiet sky (.is-sky, letters.css), a print is not
     shown at all (in the gallery its cell goes too, so the grid closes up without a hole), the masthead's emblem
     goes and its name stays. Instant, no motion. Our images only (the footer's logo is Elementor's own widget). */
  const lost = (img) => {
    const room = img.closest(".v3-room");
    if (room) { room.classList.add("is-sky"); return; }
    const print = img.closest(".v3-print");
    if (print) { (print.closest(".v3-gallery > li") || print).hidden = true; return; }
    img.hidden = true;                                                      // the emblem: the name stays
  };
  const watchPhotos = () => d.querySelectorAll(".v3-room img, .v3-print img, img.nh-logo__mark").forEach((img) => {
    if (img.complete && img.getAttribute("src") && !img.naturalWidth) lost(img);   // failed before this script ran
    else img.addEventListener("error", () => lost(img), { once: true });
  });

  /* ---------- display switches -------------------------------------------------- */
  // switches keep a constant label and say their state; the action button in the plain box changes its label instead
  const syncToggles = () => {
    const plain = String(html.classList.contains("nh-plainmode"));
    const calm = String(html.classList.contains("nh-calm"));
    d.querySelectorAll("[data-plain-toggle]:not([data-plain-toggle='action'])").forEach((b) => b.setAttribute("aria-pressed", plain));
    d.querySelectorAll("[data-calm-toggle]").forEach((b) => b.setAttribute("aria-pressed", calm));
  };
  // nobody loses their place: the control that was used (or else the first line on screen) stays put
  const keepPlace = (change) => {
    const top = (d.querySelector(".nh-header") || { offsetHeight: 0 }).offsetHeight;
    let anchor = null, before = 0;
    const a = d.activeElement;
    if (a && a !== d.body && a.closest("main, footer, .nh-footer")) { anchor = a; before = a.getBoundingClientRect().top; }
    else {
      // the first line on screen that exists in both modes (not inside a full-only or plain-only block)
      for (const el of d.querySelectorAll(":is(.v3-spread, .v3-plainbox-wrap) :is(h1, h2, h3, li, p, figure)")) {
        if (el.closest(".v3-full, .v3-plain")) continue;
        const r = el.getBoundingClientRect();
        if (r.height && r.top >= top + 8) { anchor = el; before = r.top; break; }
      }
    }
    change();
    if (anchor) {
      let dy = anchor.getBoundingClientRect().top - before;
      const after = anchor.getBoundingClientRect().top - dy;
      if (after < top + 8) dy -= top + 16 - after;                       // never under the masthead
      w.scrollBy({ top: dy, behavior: "instant" });
    }
  };
  const changed = () => d.dispatchEvent(new CustomEvent("nh:displaychange"));
  const togglePlain = () => {
    const on = !html.classList.contains("nh-plainmode");
    keepPlace(() => html.classList.toggle("nh-plainmode", on));
    store.set("nh-plain", on ? "on" : "off");
    try {
      const url = new URL(w.location.href);
      if (url.searchParams.has("plain")) { url.searchParams.set("plain", on ? "1" : "0"); history.replaceState(null, "", url); }
    } catch (e) { /* old browsers */ }
    syncToggles(); changed();
    announce(on ? i18n.plainOn : i18n.plainOff);
  };
  const toggleCalm = () => {
    const on = !html.classList.contains("nh-calm");
    html.classList.toggle("nh-calm", on);
    store.set("nh-calm", on ? "on" : "off");
    syncToggles(); changed();
    announce(on ? i18n.calmOn : i18n.calmOff);
  };

  /* ---------- focus after an in-page link (research/07 §5.5) -------------------------
     No smooth scroll: the browser jumps to the target by itself; then the target's heading
     takes focus — the target if it is h1–h3, else its aria-labelledby, else its first h2/h3. */
  const headingOf = (el) => {
    if (/^H[1-3]$/.test(el.tagName)) return el;
    const ref = (el.getAttribute("aria-labelledby") || "").trim().split(/\s+/)[0];
    const named = ref ? d.getElementById(ref) : null;
    return named || el.querySelector("h2, h3");
  };
  const focusTarget = (el) => requestAnimationFrame(() => {
    const h = el && el.isConnected ? headingOf(el) : null;
    if (!h) return;
    if (!h.hasAttribute("tabindex")) h.setAttribute("tabindex", "-1");
    h.focus({ preventScroll: true });
  });
  const byHash = (hash) => {
    if (!hash || hash.length < 2 || hash === "#nh-cookies") return null;   // the panel opens itself (§9)
    let id = hash.slice(1);
    try { id = decodeURIComponent(id); } catch (e) { /* keep it as written */ }
    const el = d.getElementById(id);
    return el && !el.matches("main") ? el : null;                        // the skip link's main takes focus by itself
  };
  // the same page by address, not by the start of href: catches "#door" and "/#homes" alike
  const samePage = (a) => a.origin === w.location.origin && a.pathname === w.location.pathname
    && a.search === w.location.search && a.hash.length > 1;
  const followAnchor = (e, a) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if ((a.target && a.target !== "_self") || a.matches(".nh-skip") || !samePage(a)) return;
    const target = byHash(a.hash);
    if (!target) return;
    // a link in the phone menu: the dialog closes first and would give focus back to its button
    const dialog = a.closest("dialog");
    if (dialog) dialog.addEventListener("close", () => focusTarget(target), { once: true });
    else focusTarget(target);
  };
  // the visit buttons lead to this page's door when it has one (else to the home page's #door, as printed)
  const visitButtons = () => {
    const door = d.querySelector(".v3-door[id]");
    if (door) d.querySelectorAll("a[data-visit-cta]").forEach((a) => a.setAttribute("href", "#" + door.id));
  };

  /* ---------- cookie settings (the choice stays in this browser; nothing is sent) ------ */
  const consentKey = (data.consent && data.consent.key) || "nh-consent-v1";
  const consentV = (data.consent && Number(data.consent.v)) || 1;
  const panel = () => d.getElementById("nh-cookies");
  // a record of an older consent version counts as none: the video asks again (§9)
  const read = () => {
    let rec = null;
    try { rec = JSON.parse(store.get(consentKey) || "null"); } catch (e) { rec = null; }
    return rec && typeof rec === "object" && Number(rec.v) >= consentV ? rec : null;
  };
  const save = (c) => {
    const rec = { necessary: true, stats: !!c.stats, media: !!c.media, date: new Date().toISOString(), v: consentV };
    store.set(consentKey, JSON.stringify(rec));
    d.dispatchEvent(new CustomEvent("nh:consent", { detail: rec }));
    return rec;
  };
  // when the link that opened the panel is gone: the page's main, else the page title
  const focusPage = () => {
    const main = d.querySelector("main");
    if (main) {
      if (!main.hasAttribute("tabindex")) main.setAttribute("tabindex", "-1");
      main.focus({ preventScroll: true });
    }
    if (!main || d.activeElement !== main) {
      const h1 = d.querySelector("h1");
      if (h1) { h1.setAttribute("tabindex", "-1"); h1.focus({ preventScroll: true }); }
    }
  };
  let opener = null;
  // hides the panel (kept under this name: the video's consent calls it before loading the film)
  const hideBanner = () => { const p = panel(); if (p) p.hidden = true; opener = null; };
  const closePanel = () => {
    const p = panel();
    if (!p || p.hidden) return;
    const back = opener;
    hideBanner();
    if (back && back.isConnected && back.getClientRects().length) back.focus();
    else focusPage();
  };
  // in the flow after the footer, no animation: shown, scrolled to at once, its title focused
  const openSettings = (source) => {
    const p = panel();
    if (!p) return false;
    if (source && source.nodeType === 1) opener = source;
    const c = read() || {};
    const stats = p.querySelector("#nh-c-stats"), media = p.querySelector("#nh-c-media");
    if (stats) stats.checked = !!c.stats;
    if (media) media.checked = !!c.media;
    p.hidden = false;
    try { p.scrollIntoView({ block: "start" }); } catch (e) { p.scrollIntoView(); }
    const h = p.querySelector("h2");
    if (h) h.focus({ preventScroll: true });
    return true;
  };
  const initPanel = () => {
    const p = panel();
    if (!p) return;
    p.addEventListener("click", (e) => {
      const btn = e.target instanceof Element ? e.target.closest("[data-consent]") : null;
      if (!btn) return;
      const act = btn.dataset.consent;
      if (act === "save") {
        const s = p.querySelector("#nh-c-stats"), m = p.querySelector("#nh-c-media");
        save({ stats: !!(s && s.checked), media: !!(m && m.checked) });
      }
      if (act === "save" || act === "close") closePanel();
    });
    p.addEventListener("keydown", (e) => { if (e.key === "Escape") { e.preventDefault(); closePanel(); } });
  };

  /* ---------- delegated clicks: switches, «הגדרות עוגיות», in-page links ------------- */
  d.addEventListener("click", (e) => {
    const t = e.target instanceof Element ? e.target : null;
    if (!t) return;
    if (t.closest("[data-plain-toggle]")) { togglePlain(); return; }
    if (t.closest("[data-calm-toggle]")) { toggleCalm(); return; }
    const a = t.closest("a[href]");
    // «הגדרות עוגיות»: [data-cookie-settings] or any link to #nh-cookies on this page (the footer's Text Editor)
    const settings = t.closest("[data-cookie-settings]") || (a && a.hash === "#nh-cookies" && samePage(a) ? a : null);
    if (settings) { if (openSettings(settings)) e.preventDefault(); return; }
    if (a) followAnchor(e, a);
  });

  /* ---------- nh.widget: init every element of a widget, now and when Elementor renders one ---------- */
  const seen = {};
  const run = (name, root, selector, init) => {
    if (!root || !root.querySelectorAll) return;
    const done = seen[name] || (seen[name] = new WeakSet());
    const list = root.nodeType === 1 && root.matches(selector) ? [root] : [];
    root.querySelectorAll(selector).forEach((el) => list.push(el));
    list.forEach((el) => {
      if (done.has(el)) return;
      done.add(el);
      try { init(el); } catch (err) { if (w.console) w.console.error(err); }
    });
    syncToggles();
  };
  const onElementor = (fn) => {
    let added = false;
    const go = () => {
      const ef = w.elementorFrontend;
      if (added || !ef || !ef.hooks) return;
      added = true;
      fn(ef.hooks);
    };
    go();
    if (added) return;
    if (w.jQuery) w.jQuery(w).on("elementor/frontend/init", go);           // Elementor triggers it with jQuery
    w.addEventListener("elementor/frontend/init", go);
  };
  const widget = (name, selector, init) => {
    run(name, d, selector, init);
    onElementor((hooks) => hooks.addAction("frontend/element_ready/nh-" + name + ".default", ($scope) => {
      run(name, $scope && $scope[0] ? $scope[0] : $scope, selector, init);
    }));
  };

  /* ---------- start ------------------------------------------------------------------- */
  syncToggles();
  initPanel();
  visitButtons();
  reveal();
  watchPhotos();
  // arrived from another page with #…: after `load`, because the browser's own scroll to the fragment at
  // load runs its focusing steps and would drop a focus set earlier; never over a focus the visitor moved
  const landed = byHash(w.location.hash);
  if (landed) {
    const land = () => { if (!d.activeElement || d.activeElement === d.body) focusTarget(landed); };
    if (d.readyState === "complete") land(); else w.addEventListener("load", land, { once: true });
  }
  d.querySelectorAll("[data-year]").forEach((el) => { el.textContent = String(new Date().getFullYear()); });

  w.nh = Object.assign(w.nh || {}, {
    __core: true,
    data,
    store,
    osReduced,
    motionOK,
    announce,
    reveal,
    widget,
    consent: { read, save, openSettings, hideBanner },
    syncToggles,
    keepPlace,
  });
})();
