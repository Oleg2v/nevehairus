/* =========================================================================
   v4.js — child theme hello-elementor-child 1.4.8 (research/09-v4-home-spec.md
   §5.3–§5.5; research/10-v4.1-home-spec.md §2–§3; research/11-v4.3-home-spec.md).
   No libraries; deferred; on every page.
   1. The accessibility menu (the floating «נגישות» button and its panel, printed
      by functions.php): text size, high contrast, highlighted links, «עצירת
      אנימציות» (html.nh-a11y-still), reset. The choices live in localStorage
      `nh-a11y` (JSON); the inline head script applies them before the first
      paint.
   2. «בשפה פשוטה»: v4.3, the first option of the «נגישות» panel (it left the v4
      header), shown only on a page with plain texts; it toggles
      html.nh-plainmode. The choice lives in localStorage `nh-plain` ("on" /
      "off", the plugin's key, so it carries between v3.2 and v4 pages). On a
      v3.2 page the plugin's own script toggles it (the button carries
      data-plain-toggle); «איפוס» turns it off too.
   3. What the v4 header needs: its height when it is sticky or floating
      (scroll-padding and the hero's top room in v4.css), the word «תפריט» in the
      menu toggle's name if it is missing, no jQuery animations (the menu's
      sub-menus fade and slide by default). v4.1: html.nh-header-solid from 24px
      of scroll (a passive listener, one update per frame), and the floating
      header follows WordPress's admin bar where the bar scrolls away (≤600px).
   4. v4.1 motion (§3): the blocks marked nh-reveal rise into view once
      (IntersectionObserver; what is already in view is shown at once, nothing
      stays hidden), and the hero photo zooms in very slowly, once, after it has
      loaded. None of it under prefers-reduced-motion, with «עצירת אנימציות» or
      in the Elementor editor; without this script nothing is hidden.
   5. v4.3 our cookie settings (#nh-cookies, printed by functions.php on every
      page): the banner while no current choice is stored, the settings, the
      openers (links to #nh-cookies), window.nhConsent for the plugin's widgets.
      The record is the plugin's (localStorage `nh-consent-v1`).
   6. v4.3 a question in place before an Elementor video loads YouTube while
      embedded content is not allowed (the template #nh-video-ask).
   7. v4.3 the contact form of the v4 home (Elementor Pro's Form, class
      nh-contact-form) at the inner page's form's accessibility: checks on send,
      an error summary that takes the focus, each error tied to its field, the
      hint tied to the message field, the result message focused.
   ========================================================================= */
(() => {
  "use strict";
  const d = document, w = window, html = d.documentElement;
  if (w.nhV4) return;                                   // loaded twice: keep the first
  w.nhV4 = true;

  // printed with `defer`, so the document is parsed when this runs; if a cache plugin moves the script into the
  // head without it, wait for the markup (the floating buttons are printed at the end of <body>)
  const init = () => {
    const store = {
      get(k) { try { return w.localStorage.getItem(k); } catch (e) { return null; } },
      set(k, v) { try { w.localStorage.setItem(k, v); } catch (e) { /* private mode, blocked storage */ } },
      del(k) { try { w.localStorage.removeItem(k); } catch (e) { /* idem */ } },
    };
    const inEditor = /[?&]elementor-preview=/.test(w.location.search)
      || d.body.classList.contains("elementor-editor-active")
      || !!(w.elementorFrontend && typeof w.elementorFrontend.isEditMode === "function" && w.elementorFrontend.isEditMode());
    // the head script set it before the first paint (not in the editor); a cache plugin may have dropped that script
    if (!inEditor) html.classList.add("nh-js");
    const live = d.getElementById("nh-v4-live");
    const announce = (msg) => {
      if (!live || !msg) return;
      live.textContent = "";
      w.setTimeout(() => { live.textContent = msg; }, 80);
    };
    // motion is allowed: not in the editor, not «עצירת אנימציות», not prefers-reduced-motion
    const reduce = typeof w.matchMedia === "function" ? w.matchMedia("(prefers-reduced-motion: reduce)") : null;
    const motionOK = () => !inEditor && !html.classList.contains("nh-a11y-still") && !(reduce && reduce.matches);
    const onMotionOff = [];                            // what stops when motion is switched off (part 4)
    const motionChanged = () => { if (!motionOK()) onMotionOff.forEach((f) => f()); };

    /* ---------- 1. the accessibility menu ------------------------------------------ */
    const KEY = "nh-a11y";
    const STEPS = ["100%", "115%", "130%"];
    const SWITCHES = ["contrast", "links", "still"];
    const read = () => {
      let v = null;
      try { v = JSON.parse(store.get(KEY) || "null"); } catch (e) { v = null; }
      if (!v || typeof v !== "object") v = {};
      const t = Number(v.text);
      return { text: t === 1 || t === 2 ? t : 0, contrast: v.contrast === true, links: v.links === true, still: v.still === true };
    };
    let state = read();
    const btn = d.querySelector(".nh-a11y-btn");
    const panel = d.getElementById("nh-a11y-panel");
    const isPlain = () => html.classList.contains("nh-plainmode");
    // a v3.2 page: the plugin's script owns plain language (its document delegate on [data-plain-toggle]); decided at
    // click time, because this script runs before the plugin's
    const pluginPlain = () => !d.querySelector(".nh-sec") && !!(w.nh && w.nh.__core);
    let setPlain = null;                               // part 2: (on, quiet) → the v4 page's plain language
    // a ?plain= link keeps working after a reload: the address follows the choice (only where it has the parameter)
    const plainParam = (on) => {
      try {
        const url = new URL(w.location.href);
        if (!url.searchParams.has("plain")) return;
        url.searchParams.set("plain", on ? "1" : "0");
        w.history.replaceState(w.history.state, "", url);
      } catch (e) { /* old browsers */ }
    };

    // the classes on <html> and the panel's buttons follow `state`
    const sync = () => {
      html.classList.toggle("nh-a11y-text-1", state.text === 1);
      html.classList.toggle("nh-a11y-text-2", state.text === 2);
      html.classList.toggle("nh-a11y-contrast", state.contrast);
      html.classList.toggle("nh-a11y-links", state.links);
      html.classList.toggle("nh-a11y-still", state.still);
      panel.querySelectorAll("[data-a11y]").forEach((b) => {
        const k = b.getAttribute("data-a11y");
        if (k === "text") {
          b.setAttribute("aria-pressed", String(state.text > 0));
          const s = b.querySelector(".nh-a11y-opt__state");
          if (s) s.textContent = STEPS[state.text];
        } else if (SWITCHES.indexOf(k) !== -1) {
          b.setAttribute("aria-pressed", String(state[k]));
        } else if (k === "plain") {
          b.setAttribute("aria-pressed", String(isPlain()));
        }
      });
    };
    const save = () => {
      if (!state.text && !state.contrast && !state.links && !state.still) store.del(KEY);
      else store.set(KEY, JSON.stringify(state));
    };
    // a switch applies at once: no colour transition fades the whole page (v4.css, html.nh-a11y-switching)
    const apply = () => {
      html.classList.add("nh-a11y-switching");
      sync();
      motionChanged();
      w.requestAnimationFrame(() => w.requestAnimationFrame(() => html.classList.remove("nh-a11y-switching")));
    };
    const isOpen = () => !panel.hidden;
    const open = () => {
      panel.hidden = false;
      panel.scrollTop = 0;                              // a short screen scrolls the panel: start at its first option
      btn.setAttribute("aria-expanded", "true");
      // the first control that shows (the plain option is hidden on a page without plain texts)
      const first = Array.from(panel.querySelectorAll("button:not([disabled]), a[href]")).find((el) => el.getClientRects().length);
      if (first) first.focus({ preventScroll: true });
    };
    const close = (returnFocus) => {
      if (!isOpen()) return;
      panel.hidden = true;
      btn.setAttribute("aria-expanded", "false");
      if (returnFocus) btn.focus({ preventScroll: true });
    };

    if (btn && panel) {                                   // not printed in the Elementor editor
      sync();
      btn.addEventListener("click", () => (isOpen() ? close(true) : open()));
      panel.addEventListener("click", (e) => {
        const b = e.target.closest("[data-a11y]");
        if (!b || !panel.contains(b)) return;
        const k = b.getAttribute("data-a11y");
        if (k === "close") { close(true); return; }
        if (k === "plain") {
          if (pluginPlain()) { w.setTimeout(sync, 0); return; }   // the plugin toggles it; our button follows
          e.stopPropagation();                                     // and never a second toggle by the plugin
          if (setPlain) setPlain(!isPlain(), false);
          return;
        }
        if (k === "text") {
          state.text = (state.text + 1) % STEPS.length;   // 100% → 115% → 130% → 100%
          announce("גודל הטקסט: " + STEPS[state.text]);
        } else if (SWITCHES.indexOf(k) !== -1) {
          state[k] = !state[k];
        } else if (k === "reset") {
          state = { text: 0, contrast: false, links: false, still: false };
          // v4.3: plain language is one of the panel's options now, so «איפוס» turns it off too (quietly)
          if (isPlain()) {
            if (pluginPlain()) {
              const off = () => {
                html.classList.remove("nh-plainmode");
                store.set("nh-plain", "off");
                plainParam(false);                      // a ?plain=1 link must not switch it back on after a reload
                if (typeof w.nh.syncToggles === "function") w.nh.syncToggles();
                d.dispatchEvent(new CustomEvent("nh:displaychange"));
              };
              if (typeof w.nh.keepPlace === "function") w.nh.keepPlace(off); else off();
            } else if (setPlain) {
              setPlain(false, true);
            } else {                                    // a page without plain texts of its own (no option shown)
              html.classList.remove("nh-plainmode");
              store.set("nh-plain", "off");
              plainParam(false);
            }
          }
          announce("הגדרות הנגישות אופסו");
        } else {
          return;
        }
        apply();
        save();
      });
      // [text-to-speech goes here later (§5.3): a «הקראה» button in the panel and its handler — not built yet]

      // Esc closes and gives the focus back to the button
      d.addEventListener("keydown", (e) => {
        if ((e.key === "Escape" || e.key === "Esc") && isOpen()) {
          e.preventDefault();
          close(true);
        }
      });
      // a click outside closes; so does the focus moving on (Tab past the panel), without moving it back
      d.addEventListener("click", (e) => {
        if (isOpen() && !panel.contains(e.target) && !btn.contains(e.target)) close(false);
      });
      d.addEventListener("focusin", (e) => {
        if (isOpen() && !panel.contains(e.target) && !btn.contains(e.target)) close(false);
      });
      // another tab changed the choices, or the page came back from the back-forward cache
      w.addEventListener("storage", (e) => { if (e.key === KEY || e.key === null) { state = read(); apply(); } });
      w.addEventListener("pageshow", (e) => { if (e.persisted) { state = read(); apply(); } });
    }

    /* ---------- 2. «בשפה פשוטה» (v4.3: in the «נגישות» panel) --------------------------- */
    const headerLoc = d.querySelector(".elementor-location-header");
    const headerFloats = () => !!headerLoc && /^(sticky|fixed)$/.test(w.getComputedStyle(headerLoc).position);
    const plainBtn = panel ? panel.querySelector('[data-a11y="plain"]') : null;
    // only inside the page's content: the cookie banner and the v4 footer have plain twins of their own on every page
    if (plainBtn && !inEditor && d.querySelector("main .nh-plain, main .v3-plain")) {
      const li = plainBtn.closest("li");
      if (li) li.hidden = false;                          // only where the page has plain texts
      // nobody loses their place: the section at the top of the screen stays where it was, or, if it began
      // above the screen, its start comes to the top (just under the sticky or floating header)
      const keepPlace = (change) => {
        const top = headerFloats() ? headerLoc.getBoundingClientRect().bottom : 0;
        let anchor = null, before = 0;
        for (const s of d.querySelectorAll(".nh-sec")) {
          const r = s.getBoundingClientRect();
          if (r.height && r.bottom > top + 1) { anchor = s; before = r.top; break; }
        }
        change();
        if (!anchor) return;
        const dy = anchor.getBoundingClientRect().top - Math.max(before, top);
        if (Math.abs(dy) >= 1) w.scrollBy(0, dy);        // instant (:root { scroll-behavior: auto })
      };
      setPlain = (on, quiet) => {
        keepPlace(() => html.classList.toggle("nh-plainmode", on));
        store.set("nh-plain", on ? "on" : "off");
        plainParam(on);
        sync();
        if (!quiet) announce(on ? "הדף מוצג עכשיו בשפה פשוטה" : "הדף מוצג בשפה הרגילה");
      };
      sync();
    }
    // a page restored by «back» follows the choice made meanwhile on another page (or its address), with or
    // without plain texts of its own; on a v3.2 page the plugin's own switches and widgets follow too
    if (panel && !inEditor) {
      w.addEventListener("pageshow", (e) => {
        if (!e.persisted) return;
        const q = /[?&]plain=([01])/.exec(w.location.search);
        const was = isPlain();
        html.classList.toggle("nh-plainmode", q ? q[1] === "1" : store.get("nh-plain") === "on");
        if (pluginPlain() && isPlain() !== was) {
          if (typeof w.nh.syncToggles === "function") w.nh.syncToggles();
          d.dispatchEvent(new CustomEvent("nh:displaychange"));
        }
        sync();
      });
    }

    // one handler for scrolling and resizing, run once per frame (parts 3 and 4 add their work)
    const frameJobs = [];
    let frameQueued = false;
    const runFrame = () => { frameQueued = false; frameJobs.forEach((f) => f()); };
    const onScrollOrResize = () => {
      if (frameQueued || !frameJobs.length) return;
      frameQueued = true;
      w.requestAnimationFrame(runFrame);
    };
    w.addEventListener("scroll", onScrollOrResize, { passive: true });
    w.addEventListener("resize", onScrollOrResize, { passive: true });

    /* ---------- 3. the v4 header -------------------------------------------------- */
    if (!inEditor && d.querySelector(".e-con.nh-header, .nh-sec")) {
      // no jQuery animations on a v4 page: SmartMenus fades the menu's sub-menus out (200ms) and slides the
      // phone dropdown's sub-menus; with fx.off they appear and go at once. jQuery may load after this script
      const fxOff = () => { if (w.jQuery && w.jQuery.fx) w.jQuery.fx.off = true; };
      fxOff();
      d.addEventListener("DOMContentLoaded", fxOff);
      w.addEventListener("load", fxOff);

      if (headerLoc && headerLoc.querySelector(".e-con.nh-header")) {
        // the header's height while it is sticky (v4, from 1025px) or floating (v4.1, over the hero), for
        // scroll-padding-top: a jump to #content, to an anchor or to a focused control lands below the header
        // (WCAG 2.4.7 / 2.4.11); and for the hero's room at the top
        const bar = d.getElementById("wpadminbar");
        let barScrolls = false;
        const setH = () => {
          const h = headerFloats() ? Math.ceil(headerLoc.getBoundingClientRect().height) : 0;
          html.style.setProperty("--nh4-header-h", h + "px");
          barScrolls = !!bar && w.getComputedStyle(bar).position === "absolute";
        };
        // solid from 24px of scroll (v4.css keeps the floating header transparent above it, over the hero);
        // where the admin bar scrolls away with the page (≤600px), the floating header follows its lower edge
        let solid = null, barTop = null;
        const update = () => {
          const s = (w.scrollY || w.pageYOffset || 0) >= 24;
          if (s !== solid) { solid = s; html.classList.toggle("nh-header-solid", s); }
          const t = barScrolls ? Math.max(0, Math.round(bar.getBoundingClientRect().bottom)) : null;
          if (t !== barTop) {
            barTop = t;
            if (t === null) html.style.removeProperty("--nh4-top");
            else html.style.setProperty("--nh4-top", t + "px");
          }
        };
        setH();
        update();
        frameJobs.push(update);
        w.addEventListener("resize", setH);
        w.addEventListener("load", () => { setH(); update(); });
        w.addEventListener("pageshow", () => { setH(); update(); });
        if ("ResizeObserver" in w) new w.ResizeObserver(setH).observe(headerLoc);
      }

      // the menu toggle shows the word «תפריט» (v4.css); its accessible name must contain it (WCAG 2.5.3).
      // Elementor's own aria-label is kept when it already does
      d.querySelectorAll(".e-con.nh-header .elementor-menu-toggle").forEach((t) => {
        if ((t.getAttribute("aria-label") || "").indexOf("תפריט") === -1) t.setAttribute("aria-label", "תפריט");
      });

      // Elementor's Nested Accordion prints an English, untranslatable aria-label on its wrapper <div> («Accordion. Open
      // links with Enter or Space…»); a generic div may not carry a name (ARIA 1.2) and its <summary> is the control
      d.querySelectorAll(".nh-sec .e-n-accordion[aria-label]").forEach((a) => a.removeAttribute("aria-label"));
      // 1.4.3: its open region would be named by its whole text (Elementor points the region's aria-labelledby, and
      // the summary's aria-controls, at the <details> itself): the summary names the region and controls it
      d.querySelectorAll(".nh-sec .e-n-accordion-item").forEach((item, i) => {
        const head = item.querySelector(":scope > summary");
        const region = item.querySelector(':scope > [role="region"]');
        if (!head || !region) return;
        const base = item.id || "nh-acc-" + i;
        if (!head.id) head.id = base + "-title";
        if (!region.id) region.id = base + "-panel";
        region.setAttribute("aria-labelledby", head.id);
        head.setAttribute("aria-controls", region.id);
      });
    }

    /* ---------- 4. v4.1 motion ---------------------------------------------------- */
    if (!inEditor) {
      // 4a. the hero photo zooms in very slowly, once (v4.css: .is-zoom), from the moment it has loaded and been
      // decoded, so it never jumps. Once stopped («עצירת אנימציות»), it stays still for this page view
      const heroImg = d.querySelector(".e-con.nh-hero .nh-hero__img img");
      const heroBox = heroImg ? heroImg.closest(".nh-hero__img") : null;
      let zoomDone = false;
      const startZoom = () => {
        if (zoomDone || !heroBox || !motionOK()) return;
        zoomDone = true;
        heroBox.classList.add("is-zoom");
      };
      if (heroImg && motionOK()) {
        const go = () => {
          const p = typeof heroImg.decode === "function" ? heroImg.decode() : null;
          if (p && typeof p.then === "function") p.then(startZoom, startZoom);
          else startZoom();
        };
        if (heroImg.complete && heroImg.naturalWidth > 0) go();
        else heroImg.addEventListener("load", go, { once: true });
      }
      onMotionOff.push(() => {
        zoomDone = true;
        if (heroBox) heroBox.classList.remove("is-zoom");
      });

      // 4b. the fade-ins: a block marked nh-reveal is shown (.is-in) once it is 15% in view (5% above the screen's
      // foot). What is already in view, or above it (the page was opened lower down), is shown at once, without the
      // fade; v4.css hides the rest only after that (html.nh-reveal-on), so the first screen never waits
      const blocks = Array.from(d.querySelectorAll(".nh-reveal"));
      const pending = new Set();
      let io = null;
      const show = (el) => {
        el.classList.add("is-in");
        pending.delete(el);
        if (io) io.unobserve(el);
      };
      const showAll = () => {
        Array.from(pending).forEach(show);
        if (io) { io.disconnect(); io = null; }
      };
      if (blocks.length) {
        if (!motionOK() || !("IntersectionObserver" in w)) {
          blocks.forEach((el) => el.classList.add("is-in"));
        } else {
          const vh = w.innerHeight || html.clientHeight;
          blocks.forEach((el) => {
            // a block that holds other fading blocks stays put (v4.css): its children move
            if (el.querySelector(".nh-reveal") || el.getBoundingClientRect().top < vh) el.classList.add("is-in");
            else pending.add(el);
          });
          html.classList.add("nh-reveal-on");
          if (pending.size) {
            io = new w.IntersectionObserver((entries) => {
              entries.forEach((e) => {
                if (!e.isIntersecting) return;
                const rh = e.rootBounds ? e.rootBounds.height : vh;
                // 15% of the block, or a quarter of the screen for a block too tall to ever show 15%
                if (e.intersectionRatio >= 0.15 || e.intersectionRect.height >= rh * 0.25) show(e.target);
              });
            }, { threshold: [0, 0.15, 0.25, 0.5], rootMargin: "0px 0px -5% 0px" });
            pending.forEach((el) => io.observe(el));
            // nothing stays hidden: a block scrolled past without ever being in view (End, a jump to an anchor, a
            // fast fling), or anything left when the page's end is reached, is shown at once
            frameJobs.push(() => {
              if (!pending.size) return;
              const h = w.innerHeight || html.clientHeight;
              const atEnd = (w.scrollY || w.pageYOffset || 0) + h >= html.scrollHeight - 2;
              Array.from(pending).forEach((el) => {
                const r = el.getBoundingClientRect();
                if ((r.height > 0 && r.bottom <= 0) || (atEnd && r.height > 0 && r.top < h)) show(el);
              });
            });
          }
          // the keyboard reaches a block that is still hidden (Tab, find in page): shown at once, without the fade
          // (1.3.3: the focus ring must not fade in with the block; the transition is off for this one change)
          d.addEventListener("focusin", (e) => {
            for (let el = e.target; el && el !== d.body; el = el.parentElement) {
              if (!pending.has(el)) continue;
              const block = el;
              block.style.setProperty("transition", "none", "important");
              show(block);
              w.requestAnimationFrame(() => w.requestAnimationFrame(() => block.style.removeProperty("transition")));
            }
          });
          w.addEventListener("beforeprint", showAll);
        }
      }
      onMotionOff.push(showAll);
      // the system's reduced-motion setting changed while the page is open
      if (reduce) {
        if (typeof reduce.addEventListener === "function") reduce.addEventListener("change", motionChanged);
        else if (typeof reduce.addListener === "function") reduce.addListener(motionChanged);
      }
    }

    /* ---------- 5. v4.3 our cookie settings (#nh-cookies) ----------------------------- */
    const CKEY = "nh-consent-v1";                         // the plugin's record: the two read each other's choice
    const cons = d.getElementById("nh-cookies");
    const CV = cons ? Number(cons.getAttribute("data-v")) || 2 : 2;
    // where the browser blocks site data (localStorage throws), a choice still holds for this page view: it is kept
    // in memory, so the banner can be answered and «כן» before a video is remembered until the next page
    let cMemo = null;
    const cStorable = (() => {
      try { const t = "nh-t"; w.localStorage.setItem(t, "1"); w.localStorage.removeItem(t); return true; } catch (e) { return false; }
    })();
    const cRead = () => {
      let r = null;
      try { r = JSON.parse(store.get(CKEY) || "null"); } catch (e) { r = null; }
      if (!(r && typeof r === "object")) r = cMemo;
      return r && Number(r.v) >= CV ? r : null;           // an older version counts as none
    };
    const cSave = (c) => {
      const rec = { necessary: true, stats: !!(c && c.stats), media: !!(c && c.media), date: new Date().toISOString(), v: CV };
      cMemo = rec;
      store.set(CKEY, JSON.stringify(rec));
      html.classList.remove("nh-consent-ask");
      d.dispatchEvent(new CustomEvent("nh:consent", { detail: rec }));
      return rec;
    };
    if (cons && cons.getAttribute("data-owner") === "theme" && !inEditor) {
      const prefs = d.getElementById("nh-cookies-prefs");
      const prefsBtn = cons.querySelector('[data-consent="prefs"]');
      const media = d.getElementById("nh-c-media");
      const title = d.getElementById("nh-cookies-title");
      const mainEl = d.querySelector("main") || d.getElementById("content");
      let opener = null;
      // the bar that stays at the top of the screen (the v4 header, or the v3.2 pages' fixed masthead), whatever its
      // markup: the fixed or sticky ancestor of what is at the top centre of the screen; 0 if none
      const topBar = () => {
        const hit = d.elementFromPoint(Math.round(w.innerWidth / 2), 1);
        for (let el = hit; el && el !== d.body && el !== html; el = el.parentElement) {
          if (cons.contains(el)) return 0;
          const pos = w.getComputedStyle(el).position;
          if (pos === "fixed" || pos === "sticky") {
            const r = el.getBoundingClientRect();
            return r.top <= 1 ? Math.max(0, Math.ceil(r.bottom)) : 0;
          }
        }
        return 0;
      };
      const phoneMQ = typeof w.matchMedia === "function" ? w.matchMedia("(max-width: 767px)") : null;
      // on phones the floating column (52 + 8 + 56px) rises above the banner; when the banner is too tall for that
      // (zoom, large text, the settings open) it stands beside the column instead (html.nh-consent-tall, v4.css).
      // Decided on the banner's full-width height, so it never flips back and forth; in the next frame, so the
      // ResizeObserver that calls it never resizes what it observes during its own callback
      let tallQueued = false;
      const decideTall = () => {
        tallQueued = false;
        const top = topBar();
        html.style.setProperty("--nh-top-h", top + "px");
        const shown = html.classList.contains("nh-consent-ask") || html.classList.contains("nh-consent-open");
        let tall = false;
        if (shown && phoneMQ && phoneMQ.matches) {
          html.classList.remove("nh-consent-tall");
          tall = cons.getBoundingClientRect().height + 16 + 116 + 16 > w.innerHeight - top;
        }
        html.classList.toggle("nh-consent-tall", tall);
        html.style.setProperty("--nh-consent-h", Math.ceil(cons.getBoundingClientRect().height) + "px");
      };
      // its height, for the room the page keeps at its end and the floating buttons above it (v4.css)
      const setCH = () => {
        html.style.setProperty("--nh-consent-h", Math.ceil(cons.getBoundingClientRect().height) + "px");
        if (!tallQueued) { tallQueued = true; w.requestAnimationFrame(decideTall); }
      };
      if ("ResizeObserver" in w) new w.ResizeObserver(setCH).observe(cons);
      w.addEventListener("resize", setCH, { passive: true });
      const showPrefs = (on) => {
        if (!prefs) return;
        prefs.hidden = !on;
        if (prefsBtn) prefsBtn.setAttribute("aria-expanded", String(on));
        if (on && media) { const r = cRead(); media.checked = !!(r && r.media); }
        setCH();
      };
      const isOpen = () => cons.classList.contains("is-open");
      const focusTo = (el) => {
        const t = el && d.contains(el) && el !== d.body ? el : mainEl;
        if (!t) return;
        if (t === mainEl && !t.hasAttribute("tabindex")) t.setAttribute("tabindex", "-1");
        t.focus({ preventScroll: true });
      };
      const open = (src) => {
        opener = src || null;
        cons.classList.add("is-open");
        html.classList.add("nh-consent-open");
        showPrefs(true);
        if (title) title.focus();
      };
      const hide = (returnFocus) => {
        const back = opener;
        opener = null;
        cons.classList.remove("is-open");
        html.classList.remove("nh-consent-open");
        showPrefs(false);
        if (!cRead() && cStorable) html.classList.add("nh-consent-ask");   // no choice yet: the banner stays
        if (returnFocus) focusTo(back);
      };
      const chosen = (rec) => {
        const back = opener;
        hide(false);
        announce("הבחירה נשמרה");
        focusTo(back);
        return rec;
      };
      cons.addEventListener("click", (e) => {
        const b = e.target.closest("[data-consent]");
        if (!b || !cons.contains(b)) return;
        const k = b.getAttribute("data-consent");
        if (k === "necessary") chosen(cSave({ media: false }));
        else if (k === "all") chosen(cSave({ media: true }));
        else if (k === "save") chosen(cSave({ media: !!(media && media.checked) }));
        else if (k === "prefs") {
          const on = !prefs || prefs.hidden;
          showPrefs(on);
          if (on && media) media.focus();
        } else if (k === "close") {
          if (cRead() || isOpen()) hide(true);
          else { showPrefs(false); if (prefsBtn) prefsBtn.focus(); }   // as Esc: the focused «סגירה» is hidden now
        }
      });
      // Esc: the settings close (the banner stays while nothing is chosen)
      cons.addEventListener("keydown", (e) => {
        if (e.key !== "Escape" && e.key !== "Esc") return;
        if (isOpen()) { e.preventDefault(); hide(true); }
        else if (prefs && !prefs.hidden) { e.preventDefault(); showPrefs(false); if (prefsBtn) prefsBtn.focus(); }
      });
      // the openers: the footers' «הגדרות עוגיות» (#nh-cookies) and anything marked data-cookie-settings
      d.addEventListener("click", (e) => {
        const a = e.target.closest('a[href$="#nh-cookies"], [data-cookie-settings]');
        if (!a) return;
        const href = a.getAttribute("href");
        if (href && href.charAt(0) !== "#" && href.split("#")[0] !== w.location.href.split("#")[0]) return;
        e.preventDefault();
        open(a);
      });
      if (w.location.hash === "#nh-cookies") open(null);
      // no choice yet: the banner (the head script may have been dropped by a cache tool). Not where the browser
      // blocks site data: nothing could be kept there, the banner would come back on every page, and only the
      // necessary storage is used anyway (a video still asks in place before YouTube loads)
      if (!cRead() && cStorable) html.classList.add("nh-consent-ask");
      if (!cStorable) html.classList.remove("nh-consent-ask");
      setCH();
      // keyboard focus never sits under the banner (WCAG 2.4.11): scroll-padding covers what the browser scrolls to,
      // but a control already on screen behind the banner is not scrolled by the browser; move it just above the
      // banner, and if it is too tall for the room left, bring its top just under the header
      d.addEventListener("focusin", (e) => {
        const t = e.target;
        if (!t || !t.getBoundingClientRect || cons.contains(t) || !html.matches(".nh-consent-ask, .nh-consent-open")) return;
        if (t.closest(".elementor-location-header, .nh-float, .nh-a11y-panel, .nh-a11y")) return;   // fixed: scrolling does not move them
        const c = cons.getBoundingClientRect();
        if (!c.height) return;
        const r = t.getBoundingClientRect();
        if (!r.height) return;
        const over = (b) => !(r.bottom <= b.top || r.top >= b.bottom || r.right <= b.left || r.left >= b.right);
        // on phones the floating column sits right above the banner: a control is not left under it either
        const fl = d.querySelector(".nh-float");
        const f = fl ? fl.getBoundingClientRect() : null;
        const col = !!(f && f.height && f.top < c.top);
        if (!over(c) && !(col && over(f))) return;
        const top = topBar() + 8;
        let limit = c.top - 16;
        if (col && !(r.right <= f.left || r.left >= f.right)) limit = Math.min(limit, f.top - 8);
        const dy = r.height <= limit - top ? r.bottom - limit : r.top - top;
        if (dy > 0) w.scrollBy(0, dy);                    // instant (:root { scroll-behavior: auto })
      });
      // another tab chose
      w.addEventListener("storage", (e) => {
        if (e.key === CKEY || e.key === null) html.classList.toggle("nh-consent-ask", !cRead() && cStorable);
      });
      // the plugin's widgets (the voices widget of the v3.2 pages) read and save through this (nh-core.js 0.2.7)
      // read() → the record or null; save({stats, media}) → the record (synchronous; dispatches nh:consent); open(src) →
      // true; hide() hides at once and never moves the focus (the voices widget focuses its player right after)
      w.nhConsent = { read: cRead, save: cSave, open: (src) => { open(src || null); return true; }, hide: () => { hide(false); } };
    }

    /* ---------- 6. v4.3 a question before an Elementor video loads YouTube ------------- */
    const askTpl = d.getElementById("nh-video-ask");
    if (askTpl && !inEditor) {
      const mediaOK = () => { const r = w.nhConsent ? w.nhConsent.read() : cRead(); return !!(r && r.media); };
      const saveMedia = () => {
        const r = (w.nhConsent ? w.nhConsent.read() : cRead()) || {};
        (w.nhConsent ? w.nhConsent.save : cSave)({ stats: !!r.stats, media: true });
      };
      let passing = null, askN = 0;
      // the player appears in place of the cover: the focus goes to it, and it gets a name
      const focusPlayer = (wgt, name) => {
        if (!("MutationObserver" in w)) return;
        const mo = new w.MutationObserver(() => {
          const f = wgt.querySelector("iframe");
          if (!f) return;
          mo.disconnect();
          if (!f.getAttribute("title")) f.setAttribute("title", name || "סרטון YouTube");
          f.focus();
        });
        mo.observe(wgt, { childList: true, subtree: true });
        w.setTimeout(() => mo.disconnect(), 15000);
      };
      const capOf = (wgt) => {
        const cap = wgt.parentElement ? wgt.parentElement.querySelector(".nh-video__cap") : null;
        return cap ? cap.textContent.trim() : "";
      };
      const ask = (ov) => {
        const wgt = ov.closest(".elementor-widget-video");
        if (!wgt) return;
        let q = wgt.parentElement ? wgt.parentElement.querySelector(":scope > .nh-video-ask") : null;
        if (!q) {
          q = askTpl.content.firstElementChild.cloneNode(true);
          const qid = "nh-video-ask-" + (++askN);
          q.querySelector(".nh-video-ask__q").id = qid;
          q.setAttribute("aria-labelledby", qid);
          q.querySelector('[data-ask="yes"]').setAttribute("aria-describedby", qid);
          wgt.insertAdjacentElement("afterend", q);        // after the widget: inside it the frame clips it
          // a key held down on the play button repeats onto «כן», which took the focus: consent must be its own
          // press, so repeats do nothing here (Enter acts on keydown, Space on keyup after a keydown)
          q.addEventListener("keydown", (e) => {
            if (e.repeat && (e.key === "Enter" || e.key === " " || e.key === "Spacebar")) e.preventDefault();
          }, true);
          q.addEventListener("click", (e) => {
            const b = e.target.closest("[data-ask]");
            if (!b) return;
            const play = ov.querySelector(".elementor-custom-embed-play");
            if (b.getAttribute("data-ask") === "yes") {
              saveMedia();
              q.remove();
              focusPlayer(wgt, capOf(wgt));
              passing = ov;
              ov.click();                                   // Elementor's own handler starts the video now
              passing = null;
            } else {
              q.remove();
              (play || ov).focus({ preventScroll: true });
            }
          });
        }
        const yes = q.querySelector('[data-ask="yes"]');
        if (yes) yes.focus();
      };
      const gate = (e) => {
        const ov = e.target && e.target.closest ? e.target.closest(".elementor-widget-video .elementor-custom-embed-image-overlay") : null;
        if (!ov || ov === passing) return;
        const key = e.type === "keydown";
        if (key && !(e.key === "Enter" || e.key === " " || e.key === "Spacebar")) return;
        if (mediaOK()) {
          // allowed already: Elementor's own handler starts the video (nothing is stopped here). Space must not
          // scroll the page away from it, and the player takes the focus, as after «כן»
          if (key) e.preventDefault();
          const wgt = ov.closest(".elementor-widget-video");
          if (wgt && !(key && e.repeat)) focusPlayer(wgt, capOf(wgt));
          return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        e.stopPropagation();
        ask(ov);
      };
      // capture phase on window: before Elementor's handlers on the cover
      w.addEventListener("click", gate, true);
      w.addEventListener("keydown", gate, true);
    }

    /* ---------- 7. v4.3 the contact form (Elementor Pro's Form, .nh-contact-form) ------- */
    const T = Object.assign({
      required: "צריך למלא את השדה הזה.", tel: "מספר הטלפון לא תקין: צריך 9 או 10 ספרות, ובהתחלה 0.",
      consent: "צריך לסמן את התיבה כדי לשלוח.", summary1: "נמצאה שגיאה אחת בטופס:", summaryN: "נמצאו {n} שגיאות בטופס:",
      consentName: "אישור מדיניות הפרטיות",
    }, w.nhFormText || {});
    d.querySelectorAll(".nh-contact-form form.elementor-form").forEach((form, fi) => {
      if (inEditor) return;
      const nameIn = form.querySelector('[name="form_fields[name]"]');
      const telIn = form.querySelector('[name="form_fields[phone]"]');
      const msgIn = form.querySelector('[name="form_fields[message]"]');
      const consIn = form.querySelector('[name="form_fields[consent]"]');
      form.setAttribute("novalidate", "");
      if (!form.getAttribute("aria-label")) form.setAttribute("aria-label", form.getAttribute("name") || "בקשה לביקור");
      if (nameIn) nameIn.setAttribute("autocomplete", "name");
      if (telIn) { telIn.setAttribute("autocomplete", "tel"); telIn.setAttribute("inputmode", "tel"); telIn.setAttribute("dir", "ltr"); }
      const hint = form.querySelector("#nh-form-hint");
      // the message field and its hint (the hint stays tied; an error is added in front of it)
      const baseDesc = new Map();
      if (msgIn && hint) baseDesc.set(msgIn, hint.id);
      const labelOf = (input) => {
        if (input === consIn) return T.consentName;
        const l = input.id ? form.querySelector('label[for="' + input.id + '"]') : null;
        return l ? l.textContent.replace(/\s*\*\s*$/, "").trim() : "";
      };
      // the summary: above the fields, hidden until a send finds errors; it takes the focus
      const sum = d.createElement("div");
      sum.className = "nh-form-errors";
      sum.id = "nh-form-errors-" + fi;
      sum.setAttribute("tabindex", "-1");
      sum.hidden = true;
      sum.innerHTML = '<p class="nh-form-errors__title" id="' + sum.id + '-t"></p><ul class="nh-form-errors__list"></ul>';
      sum.setAttribute("role", "group");
      sum.setAttribute("aria-labelledby", sum.id + "-t");
      form.insertBefore(sum, form.firstChild);
      const setErr = (input, msg) => {
        const group = input.closest(".elementor-field-group");
        const eid = "nh-err-" + fi + "-" + (input.id || input.name).replace(/\W+/g, "-");
        let p = d.getElementById(eid);
        if (group) {
          // the server's own error on this field (Pro's inline message) goes, in both cases: one message per field,
          // and screen and reader agree
          group.querySelectorAll(".elementor-form-help-inline").forEach((m) => m.remove());
          group.classList.remove("elementor-error");
          group.querySelectorAll(".elementor-error").forEach((x) => x.classList.remove("elementor-error"));
        }
        if (msg) {
          if (!p) { p = d.createElement("p"); p.id = eid; p.className = "nh-field-error"; (group || input.parentElement).appendChild(p); }
          p.textContent = msg;
          input.setAttribute("aria-invalid", "true");
          input.setAttribute("aria-describedby", [eid, baseDesc.get(input)].filter(Boolean).join(" "));
          if (group) group.classList.add("nh-has-error");
        } else {
          if (p) p.remove();
          input.removeAttribute("aria-invalid");
          if (baseDesc.has(input)) input.setAttribute("aria-describedby", baseDesc.get(input));
          else input.removeAttribute("aria-describedby");
          if (group) group.classList.remove("nh-has-error");
        }
      };
      // every field is right again: the summary and Pro's form-level «נמצאו שגיאות בטופס.» go
      const allFixed = () => {
        sum.hidden = true;
        form.querySelectorAll(".elementor-message-danger").forEach((m) => { if (!m.closest(".elementor-field-group")) m.remove(); });
      };
      // what Pro printed after the last send (its result and its field errors), as Pro clears it before a new send
      const clearPro = () => {
        form.querySelectorAll(".elementor-message").forEach((m) => m.remove());
        form.querySelectorAll(".elementor-error").forEach((g) => g.classList.remove("elementor-error"));
      };
      if (msgIn && hint) msgIn.setAttribute("aria-describedby", hint.id);
      const check = () => {
        const errs = [];
        if (nameIn && !nameIn.value.trim()) errs.push([nameIn, T.required]);
        if (telIn) {
          const v = telIn.value.trim();
          if (!v) errs.push([telIn, T.required]);
          else if (!/^0\d{8,9}$/.test(v.replace(/\D+/g, ""))) errs.push([telIn, T.tel]);
        }
        if (consIn && !consIn.checked) errs.push([consIn, T.consent]);
        return errs;
      };
      const show = (errs) => {
        [nameIn, telIn, consIn].forEach((i) => i && setErr(i, ""));
        errs.forEach(([i, m]) => setErr(i, m));
        const list = sum.querySelector("ul");
        list.textContent = "";
        if (!errs.length) { sum.hidden = true; return; }
        errs.forEach(([i, m]) => {
          const li = d.createElement("li");
          const a = d.createElement("a");
          a.href = "#" + i.id;
          a.textContent = labelOf(i) + ": " + m;
          a.addEventListener("click", (e) => { e.preventDefault(); i.focus(); });
          li.appendChild(a);
          list.appendChild(li);
        });
        sum.querySelector(".nh-form-errors__title").textContent = errs.length === 1 ? T.summary1 : T.summaryN.replace("{n}", String(errs.length));
        sum.hidden = false;
        // its title at the top of the screen, under the header (scroll-padding-top), even at 400% zoom where the
        // whole summary does not fit
        sum.focus({ preventScroll: true });
        sum.scrollIntoView({ block: "start" });
      };
      // on send: our checks first (capture phase, before Pro's handler); with errors Pro never sends
      form.addEventListener("submit", (e) => {
        const errs = check();
        if (errs.length) {
          e.preventDefault();
          e.stopImmediatePropagation();
          clearPro();                                     // no old «תודה!» next to the new errors
          show(errs);
        } else {
          sum.hidden = true;
        }
      }, true);
      // an error goes as soon as its field is right (no new errors while typing)
      form.addEventListener("input", (e) => {
        const i = e.target;
        if (i.getAttribute && i.getAttribute("aria-invalid") === "true") {
          const still = check().find(([x]) => x === i);
          setErr(i, still ? still[1] : "");
          if (!form.querySelector('[aria-invalid="true"]')) allFixed();
        }
      });
      form.addEventListener("change", (e) => {
        if (e.target === consIn && consIn.checked) {
          setErr(consIn, "");
          if (!form.querySelector('[aria-invalid="true"]')) allFixed();
        }
      });
      // Pro's answer: its message gets the focus (role=alert already speaks it); its own field errors are tied
      if ("MutationObserver" in w) {
        new w.MutationObserver((recs) => {
          recs.forEach((r) => r.addedNodes.forEach((n) => {
            if (!(n instanceof w.HTMLElement)) return;
            const msg = n.matches(".elementor-message") ? n : n.querySelector && n.querySelector(".elementor-message");
            if (!msg) return;
            const group = msg.closest(".elementor-field-group");
            if (group) {                                    // a field error from the server
              const input = group.querySelector("input, textarea, select");
              if (input) {
                msg.id = msg.id || "nh-srv-" + fi + "-" + (input.id || "f");
                input.setAttribute("aria-invalid", "true");
                input.setAttribute("aria-describedby", [msg.id, baseDesc.get(input)].filter(Boolean).join(" "));
              }
              return;
            }
            msg.setAttribute("tabindex", "-1");             // the form's result: success or failure
            w.setTimeout(() => msg.focus(), 50);
          }));
        }).observe(form, { childList: true, subtree: true });
      }
    });
  };

  if (d.readyState === "loading") d.addEventListener("DOMContentLoaded", init, { once: true });
  else init();
})();
