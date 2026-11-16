/* =========================================================================
   נווה האירוס — nh-core.js (plugin nevehairus-site, core). No libraries.
   window.nh: the helpers every widget script uses, the display modes (plain
   language, calm mode, reduced motion), cookie consent, the mobile visit bar,
   the widget hook for Elementor (SPEC §3.12, §5.5).
   Ported from prototype/assets/js/site.js L12–40, L108–133, L152–216, L457–458
   and v3.js L14–81, L176–184, L235–241 (v3.1). Toggles and the cookie-settings
   buttons are delegated, so widgets added later (Elementor editor) work too.
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
  const motionOK = () => !html.classList.contains("nh-reduce-motion") && !html.classList.contains("nh-calm") && !osReduced();
  // an open modal dialog makes the rest of the page inert: speak from inside it
  const announce = (msg) => {
    const el = d.querySelector("dialog[open] [data-announce-menu]") || d.querySelector("[data-announce]");
    if (!el || !msg) return;
    el.textContent = ""; setTimeout(() => { el.textContent = msg; }, 80);
  };

  /* ---------- once: a class when first seen ------------------------------------ */
  const once = (target, cls, rootMargin) => {
    const els = typeof target === "string" ? Array.from(d.querySelectorAll(target))
      : (target && target.nodeType === 1 ? [target] : Array.from(target || []));
    if (!els.length) return;
    if (!("IntersectionObserver" in w)) { els.forEach((el) => el.classList.add(cls)); return; }
    const io = new IntersectionObserver((entries) => entries.forEach((e) => {
      if (e.isIntersecting) { e.target.classList.add(cls); io.unobserve(e.target); }
    }), { rootMargin: rootMargin || "0px 0px -30% 0px" });
    els.forEach((el) => io.observe(el));
  };

  /* ---------- display switches -------------------------------------------------- */
  // switches keep a constant label and say their state; the action button in the plain box changes its label instead
  const syncToggles = () => {
    const plain = String(html.classList.contains("nh-plainmode"));
    const calm = String(html.classList.contains("nh-calm"));
    const motion = String(html.classList.contains("nh-reduce-motion"));
    d.querySelectorAll("[data-plain-toggle]:not([data-plain-toggle='action'])").forEach((b) => b.setAttribute("aria-pressed", plain));
    d.querySelectorAll("[data-calm-toggle]").forEach((b) => b.setAttribute("aria-pressed", calm));
    d.querySelectorAll("[data-motion-toggle]").forEach((b) => b.setAttribute("aria-pressed", motion));
  };
  // nobody loses their place: the control that was used (or else the spread at the top of the screen) stays put
  const keepPlace = (change) => {
    const top = (d.querySelector(".nh-header") || { offsetHeight: 0 }).offsetHeight;
    let anchor = null, before = 0;
    const a = d.activeElement;
    if (a && a !== d.body && a.closest("main, footer, .nh-footer")) { anchor = a; before = a.getBoundingClientRect().top; }
    else {
      // the first line on screen that exists in both modes (not inside a full-only or plain-only block)
      for (const el of d.querySelectorAll("[data-running] :is(h1, h2, h3, li, p, figure)")) {
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
  const toggleMotion = () => {
    const reduce = !html.classList.contains("nh-reduce-motion");
    html.classList.toggle("nh-reduce-motion", reduce);
    store.set("nh-motion", reduce ? "reduce" : "full");
    syncToggles();
    d.dispatchEvent(new CustomEvent("nh:motionchange", { detail: { reduce } }));
  };
  d.addEventListener("nh:motionchange", (e) => announce(e.detail && e.detail.reduce ? i18n.motionOn : i18n.motionOff));

  /* ---------- cookie consent (stays in this browser; nothing is sent) ------------- */
  const consentKey = (data.consent && data.consent.key) || "nh-consent-v1";
  const consentV = (data.consent && Number(data.consent.v)) || 1;
  const banner = () => d.getElementById("nh-cookies");
  const read = () => {
    try { return JSON.parse(store.get(consentKey) || "null"); } catch (e) { return null; }
  };
  const save = (c) => {
    const rec = { necessary: true, stats: !!c.stats, media: !!c.media, date: new Date().toISOString(), v: consentV };
    store.set(consentKey, JSON.stringify(rec));
    d.dispatchEvent(new CustomEvent("nh:consent", { detail: rec }));
    return rec;
  };
  const showBanner = () => { const b = banner(); if (b) { b.hidden = false; html.classList.add("nh-has-cookies"); } };
  const hideBanner = () => { const b = banner(); if (b) { b.hidden = true; html.classList.remove("nh-has-cookies"); } };
  // after a choice, focus goes back to the page: #main, else the first main, else the page title
  const focusPage = () => {
    const main = d.getElementById("main") || d.querySelector("main");
    if (main) {
      if (!main.hasAttribute("tabindex")) main.setAttribute("tabindex", "-1");
      main.focus({ preventScroll: true });
    }
    if (!main || d.activeElement !== main) {
      const h1 = d.querySelector("h1");
      if (h1) { h1.setAttribute("tabindex", "-1"); h1.focus({ preventScroll: true }); }
    }
  };
  const openSettings = () => {
    const b = banner();
    if (!b) return;
    const c = read() || {};
    const stats = b.querySelector("#nh-c-stats"), media = b.querySelector("#nh-c-media"), prefs = b.querySelector(".nh-cookies__prefs");
    if (stats) stats.checked = !!c.stats;
    if (media) media.checked = !!c.media;
    if (prefs) prefs.hidden = false;
    const set = b.querySelector("[data-consent='settings']");
    if (set) set.setAttribute("aria-expanded", "true");
    showBanner();
    const h = b.querySelector("h2");
    if (h) h.focus();
  };
  const initBanner = () => {
    const b = banner();
    if (!b) return;
    // the page keeps focus clear of the banner: scroll padding follows the banner's real height
    // (measured by a ResizeObserver after layout, never forced while the page loads)
    if ("ResizeObserver" in w) new ResizeObserver(([e]) => {
      const h = e.borderBoxSize && e.borderBoxSize[0] ? e.borderBoxSize[0].blockSize : b.offsetHeight;
      if (h) html.style.setProperty("--nh-cookie-h", Math.ceil(h) + "px");
    }).observe(b);
    const rec = read();
    if (!rec || !(Number(rec.v) >= consentV)) showBanner();
    const prefs = b.querySelector(".nh-cookies__prefs");
    const more = b.querySelector("[data-cookie-more]");
    if (more) more.addEventListener("click", () => {
      const open = !b.classList.contains("is-more");
      b.classList.toggle("is-more", open);
      more.setAttribute("aria-expanded", String(open));
    });
    b.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-consent]");
      if (!btn) return;
      const act = btn.dataset.consent;
      if (act === "settings") {
        if (!prefs) return;
        prefs.hidden = !prefs.hidden;
        btn.setAttribute("aria-expanded", String(!prefs.hidden));
        const first = prefs.querySelector("input:not([disabled])");
        if (!prefs.hidden && first) first.focus();
        return;
      }
      const c = { stats: false, media: false };
      if (act === "all") { c.stats = true; c.media = true; }
      if (act === "save") {
        const s = b.querySelector("#nh-c-stats"), m = b.querySelector("#nh-c-media");
        c.stats = !!(s && s.checked);
        c.media = !!(m && m.checked);
      }
      save(c);
      hideBanner();
      focusPage();
    });
  };

  /* ---------- delegated clicks: switches anywhere, "cookie settings" anywhere -------- */
  d.addEventListener("click", (e) => {
    const t = e.target instanceof Element ? e.target : null;
    if (!t) return;
    if (t.closest("[data-plain-toggle]")) { togglePlain(); return; }
    if (t.closest("[data-calm-toggle]")) { toggleCalm(); return; }
    if (t.closest("[data-motion-toggle]")) { toggleMotion(); return; }
    if (t.closest("[data-cookie-settings]")) openSettings();
  });

  /* ---------- the mobile visit bar --------------------------------------------------- */
  // after the cover's own visit button has scrolled away; hidden near the door, the footer and
  // any [data-bar-hide]; steps aside while a form field is being filled in
  const initBar = () => {
    const bar = d.querySelector("[data-bar]");
    if (!bar) return;
    const door = d.querySelector(".v3-door[id]");
    const cta = bar.querySelector("[data-bar-cta]");
    if (door && cta) cta.setAttribute("href", "#" + door.id);
    const zones = Array.from(d.querySelectorAll(".v3-door, .nh-footer, [data-bar-hide]"));
    const inView = (el) => { const b = el.getBoundingClientRect(); return b.top < w.innerHeight && b.bottom > 0; };
    const firstCta = d.querySelector(".v3-cover__cta a");
    let ctaGone = false, bt = false;
    const passed = () => (firstCta && "IntersectionObserver" in w ? ctaGone : w.scrollY > w.innerHeight * 0.9);
    const upd = () => {
      const on = passed() && !zones.some(inView);
      bar.classList.toggle("is-on", on);
      html.classList.toggle("nh-has-bar", on);
    };
    const later = () => { if (bt) return; bt = true; requestAnimationFrame(() => { upd(); bt = false; }); };
    w.addEventListener("scroll", later, { passive: true });
    if (firstCta && "IntersectionObserver" in w) {
      new IntersectionObserver(([e]) => { ctaGone = !e.isIntersecting && e.boundingClientRect.top < 0; later(); }).observe(firstCta);
    } else later();
    const field = (el) => el && el.matches && el.matches("input, select, textarea");
    d.addEventListener("focusin", (e) => { if (field(e.target)) bar.classList.add("is-typing"); });
    d.addEventListener("focusout", (e) => { if (field(e.target)) bar.classList.remove("is-typing"); });
  };

  /* ---------- the skip link always lands on the page's main ------------------------- */
  const fixSkip = () => {
    const skip = d.querySelector("a.nh-skip[href^='#']");
    if (!skip || d.getElementById(skip.getAttribute("href").slice(1))) return;
    const main = d.querySelector("main");
    if (!main) return;
    if (!main.id) main.id = "main";
    if (!main.hasAttribute("tabindex")) main.setAttribute("tabindex", "-1");
    skip.setAttribute("href", "#" + main.id);
  };

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
  initBanner();
  initBar();
  fixSkip();
  d.querySelectorAll("[data-year]").forEach((el) => { el.textContent = String(new Date().getFullYear()); });

  w.nh = Object.assign(w.nh || {}, {
    __core: true,
    data,
    store,
    osReduced,
    motionOK,
    announce,
    once,
    widget,
    consent: { read, save, showBanner, hideBanner, openSettings },
    syncToggles,
    keepPlace,
  });
})();
