/* =========================================================================
   v4.js — child theme hello-elementor-child 1.3.0 (research/09-v4-home-spec.md
   §5.3–§5.5; research/10-v4.1-home-spec.md §2–§3). No libraries; deferred; on
   every page.
   1. The accessibility menu (the floating «נגישות» button and its panel, printed
      by functions.php): text size, high contrast, highlighted links, «עצירת
      אנימציות» (html.nh-a11y-still), reset. The choices live in localStorage
      `nh-a11y` (JSON); the inline head script applies them before the first
      paint.
   2. «בשפה פשוטה» in the v4 header (an Elementor Button widget with the class
      nh-plain-toggle and the link #plain) becomes a toggle button for
      html.nh-plainmode. The choice lives in localStorage `nh-plain` ("on" /
      "off", the plugin's key, so it carries between v3.2 and v4 pages). Not on
      a page with the plugin's own toggle (.v3-plainbtn), not in the editor.
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
      btn.setAttribute("aria-expanded", "true");
      const first = panel.querySelector("button:not([disabled]), a[href]");
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
        if (k === "text") {
          state.text = (state.text + 1) % STEPS.length;   // 100% → 115% → 130% → 100%
          announce("גודל הטקסט: " + STEPS[state.text]);
        } else if (SWITCHES.indexOf(k) !== -1) {
          state[k] = !state[k];
        } else if (k === "reset") {
          state = { text: 0, contrast: false, links: false, still: false };
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

    /* ---------- 2. «בשפה פשוטה» -------------------------------------------------- */
    const headerLoc = d.querySelector(".elementor-location-header");
    const headerFloats = () => !!headerLoc && /^(sticky|fixed)$/.test(w.getComputedStyle(headerLoc).position);
    const plainLinks = Array.from(d.querySelectorAll(".nh-plain-toggle a, a.nh-plain-toggle"));
    if (plainLinks.length && !inEditor && !d.querySelector(".v3-plainbtn")) {
      const isPlain = () => html.classList.contains("nh-plainmode");
      const syncPlain = () => plainLinks.forEach((a) => a.setAttribute("aria-pressed", String(isPlain())));
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
      const toggle = () => {
        const on = !isPlain();
        keepPlace(() => html.classList.toggle("nh-plainmode", on));
        store.set("nh-plain", on ? "on" : "off");
        try {                                             // a ?plain= link keeps working after a reload
          const url = new URL(w.location.href);
          if (url.searchParams.has("plain")) {
            url.searchParams.set("plain", on ? "1" : "0");
            w.history.replaceState(w.history.state, "", url);
          }
        } catch (e) { /* old browsers */ }
        syncPlain();
        announce(on ? "הדף מוצג עכשיו בשפה פשוטה" : "הדף מוצג בשפה הרגילה");
      };
      let spaceOn = null;                                 // Space acts on key-up, as on a native button
      plainLinks.forEach((a) => {
        a.setAttribute("role", "button");
        a.addEventListener("click", (e) => { e.preventDefault(); toggle(); });   // mouse, touch and Enter
        a.addEventListener("keydown", (e) => {
          if (e.key === " " || e.key === "Spacebar") { e.preventDefault(); spaceOn = a; }
        });
        a.addEventListener("keyup", (e) => {
          if ((e.key === " " || e.key === "Spacebar") && spaceOn === a) { e.preventDefault(); spaceOn = null; toggle(); }
        });
      });
      syncPlain();
      w.addEventListener("pageshow", (e) => {
        if (!e.persisted) return;
        const q = /[?&]plain=([01])/.exec(w.location.search);
        html.classList.toggle("nh-plainmode", q ? q[1] === "1" : store.get("nh-plain") === "on");
        syncPlain();
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
  };

  if (d.readyState === "loading") d.addEventListener("DOMContentLoaded", init, { once: true });
  else init();
})();
