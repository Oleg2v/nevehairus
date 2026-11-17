/* =========================================================================
   v4.js — child theme hello-elementor-child 1.2.0 (research/09-v4-home-spec.md
   §5.3–§5.5). No libraries; deferred; on every page.
   1. The accessibility menu (the floating «נגישות» button and its panel, printed
      by functions.php): text size, high contrast, highlighted links, reset.
      The choices live in localStorage `nh-a11y` (JSON); the inline head script
      applies them before the first paint.
   2. «בשפה פשוטה» in the v4 header (an Elementor Button widget with the class
      nh-plain-toggle and the link #plain) becomes a toggle button for
      html.nh-plainmode. The choice lives in localStorage `nh-plain` ("on" /
      "off", the plugin's key, so it carries between v3.2 and v4 pages). Not on
      a page with the plugin's own toggle (.v3-plainbtn), not in the editor.
   3. What the v4 header needs: the sticky header's height (scroll-padding in
      v4.css), the word «תפריט» in the menu toggle's name if it is missing, and
      no jQuery animations (the menu's sub-menus fade and slide by default).
   Nothing moves: no animation, no smooth scrolling.
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
    const live = d.getElementById("nh-v4-live");
    const announce = (msg) => {
      if (!live || !msg) return;
      live.textContent = "";
      w.setTimeout(() => { live.textContent = msg; }, 80);
    };

    /* ---------- 1. the accessibility menu ------------------------------------------ */
    const KEY = "nh-a11y";
    const STEPS = ["100%", "115%", "130%"];
    const read = () => {
      let v = null;
      try { v = JSON.parse(store.get(KEY) || "null"); } catch (e) { v = null; }
      if (!v || typeof v !== "object") v = {};
      const t = Number(v.text);
      return { text: t === 1 || t === 2 ? t : 0, contrast: v.contrast === true, links: v.links === true };
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
      panel.querySelectorAll("[data-a11y]").forEach((b) => {
        const k = b.getAttribute("data-a11y");
        if (k === "text") {
          b.setAttribute("aria-pressed", String(state.text > 0));
          const s = b.querySelector(".nh-a11y-opt__state");
          if (s) s.textContent = STEPS[state.text];
        } else if (k === "contrast" || k === "links") {
          b.setAttribute("aria-pressed", String(state[k]));
        }
      });
    };
    const save = () => {
      if (!state.text && !state.contrast && !state.links) store.del(KEY);
      else store.set(KEY, JSON.stringify(state));
    };
    // a switch applies at once: no colour transition fades the whole page (v4.css, html.nh-a11y-switching)
    const apply = () => {
      html.classList.add("nh-a11y-switching");
      sync();
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
        } else if (k === "contrast" || k === "links") {
          state[k] = !state[k];
        } else if (k === "reset") {
          state = { text: 0, contrast: false, links: false };
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
    const plainLinks = Array.from(d.querySelectorAll(".nh-plain-toggle a, a.nh-plain-toggle"));
    if (plainLinks.length && !inEditor && !d.querySelector(".v3-plainbtn")) {
      const isPlain = () => html.classList.contains("nh-plainmode");
      const syncPlain = () => plainLinks.forEach((a) => a.setAttribute("aria-pressed", String(isPlain())));
      // nobody loses their place: the section at the top of the screen stays where it was, or, if it began
      // above the screen, its start comes to the top (just under the sticky header)
      const keepPlace = (change) => {
        const loc = d.querySelector(".elementor-location-header");
        const top = loc && w.getComputedStyle(loc).position === "sticky" ? loc.getBoundingClientRect().bottom : 0;
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

    /* ---------- 3. the v4 header -------------------------------------------------- */
    if (!inEditor && d.querySelector(".e-con.nh-header, .nh-sec")) {
      // no jQuery animations on a v4 page: SmartMenus fades the menu's sub-menus out (200ms) and slides the
      // phone dropdown's sub-menus; with fx.off they appear and go at once. jQuery may load after this script
      const fxOff = () => { if (w.jQuery && w.jQuery.fx) w.jQuery.fx.off = true; };
      fxOff();
      d.addEventListener("DOMContentLoaded", fxOff);
      w.addEventListener("load", fxOff);

      // the sticky header's height, for scroll-padding-top: a jump to #content or to a focused control
      // lands below the header (WCAG 2.4.7 / 2.4.11)
      const loc = d.querySelector(".elementor-location-header");
      if (loc && loc.querySelector(".e-con.nh-header")) {
        const setH = () => {
          const h = w.getComputedStyle(loc).position === "sticky" ? Math.ceil(loc.getBoundingClientRect().height) : 0;
          html.style.setProperty("--nh4-sticky-h", h + "px");
        };
        setH();
        w.addEventListener("resize", setH);
        if ("ResizeObserver" in w) new w.ResizeObserver(setH).observe(loc);
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
  };

  if (d.readyState === "loading") d.addEventListener("DOMContentLoaded", init, { once: true });
  else init();
})();
