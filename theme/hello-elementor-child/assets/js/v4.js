/* =========================================================================
   v4.js — child theme hello-elementor-child 1.5.13 (research/09-v4-home-spec.md
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
   8. 1.5.0 the click-to-load map of the inner pages.
   9. 1.5.4 «הקראה» (Oleg 23.11): the page read aloud by the browser's own
      Hebrew voice (speechSynthesis; a voice of the device itself, nothing sent
      anywhere); in plain mode the plain texts; «השהיה» / «המשך» and «עצירה».
      Shown only where the device has such a voice. 1.5.5 (after its review):
      an outside interruption pauses at the sentence, the screen stays on while
      it reads, forms' texts and phone buttons are read, the focus follows.
      1.5.7: the voices are looked for again when the panel opens and shortly
      after the load (Safari may list them late without voiceschanged).
      1.5.12: page numbers are not read; the text «להמשך קריאה» folds is.
  10. 1.5.7 the header's «צור קשר»: the page's own contact form where there
      is one, else the contact page (the header template's link). 1.5.12: a
      step in the history, as a link to a place on the page; Ctrl+click as usual.
  11. 1.5.7 the photos' lightbox: a grid of photos browses as one slideshow;
      the title is the photo's own caption or nothing (never «nh4:…»).
      1.5.9: one photo stays a single photo (no «1 / 1»); while it is open
      html.nh-lightbox-open (our bar and banner hide), each slide gets the
      grid photo's alt, the arrows Hebrew names, and Esc returns the focus.
      1.5.12: any set of one photo (a Basic Gallery's too); watched however it
      opens (a shared link); a dialog with a name, the focus inside, Tab round.
      1.5.13: no share button; «next» at the left, «previous» at the right (v4.css).
  12. 1.5.9 a form field focused above the bar: the field and its hint.
      1.5.12: and its labels, clear of the cookie banner too; on a short
      screen on the screen, when the keys move the focus.
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
    let consentAtTop = () => false;                       // set below where the banner is ours; part 12 reads it too
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
      // 1.5.12: the banner at the top (the home up to 1024px, v4.css 1.5.10) or at the foot (everywhere else). At the
      // top it stands exactly 8px under --nh-top-h and always ends at least 16px above the bar; at the foot it ends
      // 8px above the bar or the screen's foot (24px on desktops), however tall it grows (the settings open, large
      // text), so a tall banner at the foot is never taken for one at the top
      consentAtTop = (c) => {
        const fl = d.querySelector(".nh-float");
        const foot = fl && w.getComputedStyle(fl).flexDirection === "row" ? fl.getBoundingClientRect().top : w.innerHeight;
        const topH = parseFloat(w.getComputedStyle(html).getPropertyValue("--nh-top-h")) || 0;
        return Math.abs(c.top - (topH + 8)) <= 2 && foot - c.bottom > 12;
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
      // banner, and if it is too tall for the room left, bring its top just under the header.
      // 1.5.12: only for keyboard focus (:focus-visible). A tap or a click focuses a link as well, and scrolling
      // between the press and the release sent the click elsewhere: a photo or a link half under the banner did not
      // open. Under a banner at the top (the home up to 1024px, 1.5.10) the control moves down, just below it. The
      // fields of a form are part 12's, with their labels and hints.
      d.addEventListener("focusin", (e) => {
        const t = e.target;
        if (!t || !t.getBoundingClientRect || cons.contains(t) || !html.matches(".nh-consent-ask, .nh-consent-open")) return;
        if (t.closest(".elementor-location-header, .nh-float, .nh-a11y-panel, .nh-a11y, .nh-skip, .elementor-lightbox")) return;   // fixed: scrolling does not move them
        if (t.matches && t.matches("input, textarea, select")) return;
        let byKeys = true;
        try { byKeys = t.matches(":focus-visible"); } catch (err) { byKeys = true; }   // an engine without :focus-visible
        if (!byKeys) return;
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
        if (consentAtTop(c)) {                            // the banner at the top: the control just below it
          const up = r.top - (c.bottom + 16);
          if (up < 0) w.scrollBy(0, up);
          return;
        }
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

    /* ---------- 8. 1.5.0 the click-to-load map (.nh-map, the inner pages) --------------- */
    // Google's frame is never in the page until a click on «הצגת המפה» (nothing from Google before: the cookie
    // settings' rule for embedded content, as for YouTube). The click loads it for this view only; the link to Google
    // Maps stays beside it. The frame takes the focus, so the keyboard does not fall back to the page's top.
    d.querySelectorAll(".nh-map[data-src]").forEach((box) => {
      const btn = box.querySelector(".nh-map__btn");
      if (!btn || inEditor) return;
      btn.addEventListener("click", () => {
        const f = d.createElement("iframe");
        f.src = box.getAttribute("data-src");
        f.title = box.getAttribute("data-title") || "מפה";
        f.setAttribute("referrerpolicy", "no-referrer-when-downgrade");
        f.setAttribute("allowfullscreen", "");
        f.className = "nh-map__frame";
        const text = box.querySelector(".nh-map__text");
        if (text) text.remove();
        btn.remove();
        box.insertBefore(f, box.firstChild);
        f.focus();
      });
    });

    /* ---------- 9. 1.5.4 «הקראה»: the page read aloud (Oleg 23.11) -------------------- */
    // The browser's own speech (speechSynthesis) with a Hebrew voice that runs on the device (localService): no
    // library, nothing sent to another server (a browser's network voices are not used). The option shows only where
    // such a voice exists; Chrome lists its voices late (voiceschanged). It reads the text of <main> that is displayed,
    // in document order: in plain mode the plain twins (the full texts are not displayed then). One short utterance
    // per block or sentence: some browsers cut a long one after ~15 s. «השהיה» stops and keeps the place (the
    // browsers' own pause fails on Android), «המשך» goes on from the sentence it was in; «עצירה», «איפוס», leaving the
    // page and the end of the text stop it; switching plain mode on or off reads the page again in the new mode.
    const tts = w.speechSynthesis;
    const ttsLi = panel ? panel.querySelector(".nh-a11y-panel__tts") : null;
    if (ttsLi && tts && typeof w.SpeechSynthesisUtterance === "function" && !inEditor) {
      const ttsBtn = ttsLi.querySelector('[data-a11y="tts"]');
      const ctrl = ttsLi.querySelector(".nh-a11y-tts");
      const pauseBtn = ttsLi.querySelector('[data-a11y="tts-pause"]');
      const stopBtn = ttsLi.querySelector('[data-a11y="tts-stop"]');
      // what is never read: code, controls (buttons; links styled as buttons, but for a phone or an e-mail), the
      // fields of a form (its texts are read), what is hidden, our floating parts. Our own words for screen readers
      // (.nh-visually-hidden: «כוכבית» for the required mark) are read, so a sentence keeps its word.
      // 1.5.12: and the page numbers of a list (nav: «1 2» at the end of «מאמרים וכתבות») and the control of a folded
      // part (summary: «להמשך קריאה»; what it folds is read, below)
      const SKIP = 'script, style, noscript, template, svg, iframe, canvas, video, audio, button, select, textarea, '
        + 'input, [hidden], [aria-hidden="true"], .screen-reader-text, .elementor-screen-only, '
        + '.nh-map__actions, .elementor-post__read-more, .elementor-button:not([href^="tel:"]):not([href^="mailto:"]), '
        + 'nav, .elementor-pagination, summary, .nh-float, #nh-cookies, .nh-skip, #wpadminbar';
      // the nearest of these around a piece of text makes one block (one or more utterances)
      const BLOCK = 'h1, h2, h3, h4, h5, h6, p, li, dt, dd, th, td, caption, figcaption, blockquote, address, pre, '
        + '.elementor-post__meta-data, div, section, article, main';
      const MAX = 220;                                   // characters per utterance
      // a letter or a digit somewhere in the text (\p{…} built at run time: an older engine would reject the file)
      let wordy = /[0-9A-Za-z\u0590-\u05FF]/;
      try { wordy = new RegExp("[\\p{L}\\p{N}]", "u"); } catch (e) { /* engines without \p{…} */ }
      let lock = null;                                   // the screen kept on while it reads (Wake Lock)
      let voice = null, items = [], pos = 0, session = 0, active = false, paused = false;

      const pickVoice = () => {
        let list = [];
        try { list = tts.getVoices() || []; } catch (e) { list = []; }
        const he = list.filter((v) => /^(he|iw|heb)([-_]|$)/i.test(v.lang || "") && v.localService !== false);
        voice = he.find((v) => v.default) || he.find((v) => /^he[-_]IL$/i.test(v.lang)) || he[0] || null;
        // 1.5.12: the option goes while it has the focus (the device dropped its voice): the focus to the panel's next
        // option, not lost to the page
        const had = !voice && !ttsLi.hidden && ttsLi.contains(d.activeElement);
        ttsLi.hidden = !voice;
        if (!voice && active) stop(false);
        if (had) {
          const all = Array.from(panel.querySelectorAll("button, a[href], input")).filter((el) => !ttsLi.contains(el) && el.getClientRects().length);
          const next = all.find((el) => ttsLi.compareDocumentPosition(el) & 4) || all[all.length - 1] || btn;   // 4: following
          if (next) next.focus({ preventScroll: true });
        }
      };
      // sentences of at most MAX characters (a longer sentence is cut at a space)
      const split = (text) => {
        if (text.length <= MAX) return [text];
        const out = [];
        let cur = "";
        text.replace(/([.!?…]["'״”’)]*)\s+/g, "$1\u0000").split("\u0000").forEach((s) => {
          if (cur && (cur + " " + s).length > MAX) { out.push(cur); cur = ""; }
          cur = cur ? cur + " " + s : s;
          while (cur.length > MAX + 80) {
            let cut = cur.lastIndexOf(" ", MAX);
            if (cut < 40) cut = MAX;
            out.push(cur.slice(0, cut).trim());
            cur = cur.slice(cut).trim();
          }
        });
        if (cur) out.push(cur);
        return out;
      };
      // 1.5.12: the text folded in a closed «להמשך קריאה» (an accordion, <details>) is the page's text all the same: it
      // is read where it stands. Not when the fold itself is not displayed (the other twin), or when something between
      // the text and the fold's panel is hidden (the panel itself is: Elementor hides it while the fold is closed)
      const folded = (el) => {
        const det = el.closest("details:not([open])");
        if (!det || !det.getClientRects().length) return false;
        for (let x = el; x && x !== det && x.parentElement !== det; x = x.parentElement) {
          const cs = w.getComputedStyle(x);
          if (cs.display === "none" || cs.visibility === "hidden" || cs.visibility === "collapse") return false;
        }
        return true;
      };
      const collect = () => {
        const root = d.querySelector("main") || d.body;
        const walker = d.createTreeWalker(root, w.NodeFilter.SHOW_TEXT);
        const range = d.createRange();
        const groups = [];
        let last = null;
        for (let n = walker.nextNode(); n; n = walker.nextNode()) {
          if (!n.nodeValue || !n.nodeValue.trim()) continue;
          const el = n.parentElement;
          if (!el || el.closest(SKIP)) continue;
          range.selectNodeContents(n);
          if (!range.getClientRects().length && !folded(el)) continue;   // not displayed: the other twin, a closed part
          const vis = w.getComputedStyle(el).visibility;
          if (vis === "hidden" || vis === "collapse") continue;
          const block = el.closest(BLOCK);
          if (!groups.length || block !== last) { groups.push([]); last = block; }
          groups[groups.length - 1].push(n.nodeValue);
        }
        const out = [];
        groups.forEach((g) => {
          const text = g.join(" ").replace(/\s+/g, " ").replace(/־ /g, "־").trim();
          if (wordy.test(text)) split(text).forEach((s) => out.push(s));
        });
        return out;
      };
      const setUI = () => {
        ttsBtn.setAttribute("aria-pressed", String(active));
        ctrl.hidden = !active;
        pauseBtn.textContent = paused ? "המשך" : "השהיה";
      };
      const queue = (from, my) => {
        for (let k = from; k < items.length; k++) {
          const u = new w.SpeechSynthesisUtterance(items[k]);
          u.voice = voice;
          u.lang = voice.lang;
          u.onstart = () => { if (my === session) pos = k; };
          u.onend = () => { if (my === session && k === items.length - 1) stop(true); };
          // every cancel of ours moves the session first, so an error in this session came from outside (the screen
          // went off, another app or tab, the engine): pause at this sentence, «המשך» reads it again; cancelling here
          // keeps the browser from starting the next one
          u.onerror = () => {
            if (my !== session) return;
            pos = k;
            paused = true;
            session++;
            try { tts.cancel(); } catch (e) { /* idem */ }
            setUI();
            wake(false);
          };
          tts.speak(u);
        }
      };
      // the screen stays on while it reads (a phone that goes dark stops the voice); asked again when the page
      // shows again, since the browser drops it whenever the page is hidden
      const wake = (on) => {
        if (!on) {
          if (lock) lock.release().catch(() => {});
          lock = null;
          return;
        }
        if ((lock && !lock.released) || !w.navigator.wakeLock || d.hidden) return;
        w.navigator.wakeLock.request("screen").then((l) => {
          if (active && !paused) lock = l; else l.release().catch(() => {});
        }).catch(() => { /* not allowed or not supported: the voice still works */ });
      };
      const speakFrom = (from) => {
        const my = ++session;
        pos = from;
        if (tts.speaking || tts.pending) {
          tts.cancel();
          w.setTimeout(() => { if (my === session) queue(from, my); }, 60);   // Chrome drops a speak() right after cancel()
        } else {
          queue(from, my);                                // inside the click: what iOS needs to start speaking
        }
      };
      const stop = (focusBack) => {
        const hadFocus = ctrl.contains(d.activeElement);
        session++;
        active = false;
        paused = false;
        items = [];
        pos = 0;
        try { tts.cancel(); } catch (e) { /* nothing to stop */ }
        setUI();
        wake(false);
        if (focusBack && hadFocus) ttsBtn.focus({ preventScroll: true });
      };
      const start = () => {
        if (!voice) return;
        items = collect();
        if (!items.length) return;
        active = true;
        paused = false;
        setUI();
        speakFrom(0);
        wake(true);
      };

      ttsBtn.addEventListener("click", () => (active ? stop(false) : start()));
      pauseBtn.addEventListener("click", () => {
        if (!active) return;
        if (paused) {
          paused = false;
          setUI();
          speakFrom(pos);                                 // the sentence it stopped in, from its start
          wake(true);
        } else {
          paused = true;
          session++;                                      // the cancelled utterances' events are ignored
          try { tts.cancel(); } catch (e) { /* idem */ }
          setUI();
          wake(false);
        }
        pauseBtn.focus({ preventScroll: true });          // Safari does not focus a clicked button
      });
      stopBtn.addEventListener("click", () => {
        stop(false);
        ttsBtn.focus({ preventScroll: true });           // the pressed control is gone: back to «הקראה»
        if (ttsBtn.offsetTop < panel.scrollTop) panel.scrollTop = Math.max(0, ttsBtn.offsetTop - 8);
      });
      d.addEventListener("visibilitychange", () => { if (!d.hidden && active && !paused) wake(true); });
      const resetBtn = panel.querySelector('[data-a11y="reset"]');
      if (resetBtn) resetBtn.addEventListener("click", () => { if (active) stop(false); });
      // plain mode switched while reading: read the page again, now in the other mode (a paused reading just ends)
      let plainWas = isPlain();
      new w.MutationObserver(() => {
        const now = isPlain();
        if (now === plainWas) return;
        plainWas = now;
        if (!active) return;
        if (paused) { stop(false); return; }
        items = collect();
        if (items.length) speakFrom(0); else stop(false);
      }).observe(html, { attributes: true, attributeFilter: ["class"] });
      // leaving the page stops the voice; a page restored by «back» starts silent
      w.addEventListener("pagehide", () => { session++; try { tts.cancel(); } catch (e) { /* idem */ } wake(false); });
      w.addEventListener("pageshow", (e) => { if (e.persisted && active) stop(false); });

      pickVoice();
      if (typeof tts.addEventListener === "function") tts.addEventListener("voiceschanged", pickVoice);
      else tts.onvoiceschanged = pickVoice;
      // 1.5.7: some engines list their voices late and say nothing (Safari without voiceschanged): look again shortly
      // after the load and whenever the panel opens, so a Hebrew voice that is there always shows the option
      [400, 1500, 4000].forEach((ms) => w.setTimeout(pickVoice, ms));
      if (btn) btn.addEventListener("click", pickVoice);
      // 1.5.9: and while the panel stays open (a voice listed later still shows the option)
      panel.addEventListener("focusin", pickVoice);
      panel.addEventListener("pointerenter", pickVoice);
      w.setInterval(() => { if (!panel.hidden && ttsLi.hidden) pickVoice(); }, 2000);
    }

    /* ---------- 10. 1.5.7 the header's «צור קשר» (Oleg 23.11) ---------------------------- */
    // The header template links it to the contact page; on a page with a contact form of its own (the home's
    // #contact, «מעון ראשי – בית לחיים», «צור קשר») it leads to that form's section instead.
    const ownForm = d.querySelector(".nh-sec .nh-contact-form");
    const formSec = ownForm ? ownForm.closest(".nh-sec[id]") : null;
    if (formSec && !inEditor) {
      d.querySelectorAll(".elementor-location-header .nh-header__contact a[href]").forEach((a) => {
        a.setAttribute("href", "#" + formSec.id);
        // 1.5.9: a visible result even where the form is already on the screen: the section at the top, the focus
        // in its first field
        // 1.5.12: as an ordinary link to a place on the page: a step in the history (Back returns to where the visitor
        // was), and Ctrl, Cmd or Shift with the click opens a new tab or window, as the browser does
        a.addEventListener("click", (e) => {
          if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
          const first = Array.from(formSec.querySelectorAll("input, textarea, select")).find((el) => el.type !== "hidden"
            && el.getClientRects().length && !el.closest(".elementor-field-type-honeypot"));
          e.preventDefault();
          if (w.location.hash !== "#" + formSec.id) {
            try { w.history.pushState(null, "", "#" + formSec.id); } catch (err) { /* old browsers */ }
          }
          formSec.scrollIntoView({ block: "start" });
          if (first) first.focus({ preventScroll: true });
        });
      });
    }

    /* ---------- 11. 1.5.7 the photos' lightbox (Oleg 23.11) ------------------------------ */
    // Elementor's lightbox reads these attributes at the click. A grid of photos (.nh-product: the products and
    // «גלריית תמונות») browses as one slideshow: its links share data-elementor-lightbox-slideshow (a Basic Gallery
    // has its own). The title under an enlarged photo is the photo's own caption, or nothing: never the attachment's
    // title (nh4:… is the deploy's marker; functions.php drops it too).
    if (!inEditor) {
      d.querySelectorAll(".elementor-widget-image.nh-product a[data-elementor-open-lightbox]").forEach((a) => {
        const grid = a.closest(".elementor-widget-image").parentElement;
        if (!grid || grid.querySelectorAll(".elementor-widget-image.nh-product a[data-elementor-open-lightbox]").length < 2) return;
        a.setAttribute("data-elementor-lightbox-slideshow", "nh-" + (grid.getAttribute("data-id") || "grid"));
      });
      // 1.5.12: a set of one photo opens as a single photo. A Basic Gallery gives even one photo a slideshow of its
      // own, which read «1 / 1» («חדשות ועדכונים», the post «הספר של תמי שחר»). Counted as Elementor counts a set:
      // every link with the same name on the page, the copies a slider makes left out
      const sets = new Map();
      d.querySelectorAll("a[data-elementor-open-lightbox][data-elementor-lightbox-slideshow]").forEach((a) => {
        if (a.closest(".swiper-slide-duplicate, .slick-cloned")) return;
        const k = a.getAttribute("data-elementor-lightbox-slideshow");
        sets.set(k, (sets.get(k) || []).concat([a]));
      });
      sets.forEach((list) => { if (list.length === 1) list[0].removeAttribute("data-elementor-lightbox-slideshow"); });
      // 1.5.12: Elementor's own word for the zoom button is «מיקוד» ('focus'): «הגדלה»
      const L = w.elementorFrontendConfig && w.elementorFrontendConfig.i18n;
      if (L && L.zoom === "מיקוד") L.zoom = "הגדלה";
      d.querySelectorAll("a[data-elementor-open-lightbox]").forEach((a) => {
        const fig = a.closest("figure");
        const cap = fig ? fig.querySelector("figcaption") : null;
        const text = cap ? cap.textContent.replace(/\s+/g, " ").trim() : "";
        if (text) a.setAttribute("data-elementor-lightbox-title", text);
        else a.removeAttribute("data-elementor-lightbox-title");
        const desc = a.getAttribute("data-elementor-lightbox-description");
        if (desc !== null && (!desc.trim() || /^nh4:/.test(desc))) a.removeAttribute("data-elementor-lightbox-description");
      });
      // 1.5.9: while a lightbox is open: html.nh-lightbox-open (v4.css hides our bar and banner), each slide's photo
      // gets the grid photo's alt (Elementor takes it from the title, which is gone), the arrows Hebrew names; when it
      // closes the focus goes back to the photo that opened it
      const altOf = new Map();
      d.querySelectorAll("a[data-elementor-open-lightbox]").forEach((a) => {
        const img = a.querySelector("img");
        if (img && img.alt) altOf.set(a.href, img.alt);
      });
      // 1.5.12: whatever opens it, a click or a shared link (Elementor opens that one by itself on load, and the header
      // showed through it): watched from the moment Elementor adds it to the page. The photo's alt is always the
      // grid photo's when there is one (Elementor's is the caption, which a screen reader then heard twice); the window
      // gets a role and a name; the focus goes into the window (Elementor left it on the photo behind the dark
      // cover), and Tab goes round inside it (Elementor's own Tab let it out after the arrows, and Shift+Tab stuck
      // on «סגור»). 1.5.13: no share button (Kit setting off, v4.css hides it otherwise); the arrows as Hebrew reads
      // (v4.css)
      let opener = null, isOpen = false;
      const shown = (el) => !!el && el.isConnected && w.getComputedStyle(el).display !== "none";
      const openBox = () => Array.from(d.querySelectorAll(".elementor-lightbox")).find(shown) || null;
      const name = (el, label) => { if (el.getAttribute("aria-label") !== label) el.setAttribute("aria-label", label); };
      const dress = (lb) => {
        if (lb.getAttribute("role") !== "dialog") lb.setAttribute("role", "dialog");
        name(lb, "תמונה מוגדלת");
        lb.querySelectorAll("img.elementor-lightbox-image").forEach((im) => {
          const own = altOf.get(im.getAttribute("src") || "") || altOf.get(im.getAttribute("data-src") || "");
          if (own) { if (im.alt !== own) im.alt = own; } else if (!im.hasAttribute("alt") || /^nh4:/.test(im.alt)) im.alt = "";
        });
        lb.querySelectorAll(".elementor-swiper-button-next").forEach((b) => name(b, "התמונה הבאה"));
        lb.querySelectorAll(".elementor-swiper-button-prev").forEach((b) => name(b, "התמונה הקודמת"));
      };
      const sync = () => {
        const lb = openBox();
        if (lb) dress(lb);
        if (!!lb === isOpen) return;
        isOpen = !!lb;
        html.classList.toggle("nh-lightbox-open", isOpen);
        if (lb) {
          const x = lb.querySelector(".dialog-close-button");
          if (!lb.contains(d.activeElement)) (x || lb).focus({ preventScroll: true });
        } else {
          if (opener && d.contains(opener)) opener.focus({ preventScroll: true });
          opener = null;
        }
      };
      const seen = typeof w.WeakSet === "function" ? new w.WeakSet() : null;
      const scan = () => {
        d.querySelectorAll(".elementor-lightbox").forEach((lb) => {
          if (!seen || seen.has(lb)) return;
          seen.add(lb);
          new w.MutationObserver(sync).observe(lb, { attributes: true, attributeFilter: ["style", "class", "src"], childList: true, subtree: true });
        });
        sync();
      };
      new w.MutationObserver(scan).observe(d.body, { childList: true });
      scan();
      d.addEventListener("click", (e) => {
        const a = e.target.closest ? e.target.closest("a[data-elementor-open-lightbox]") : null;
        if (a && !isOpen) opener = a;
      }, true);
      w.addEventListener("keydown", (e) => {
        if (e.key !== "Tab" || !isOpen || e.altKey || e.ctrlKey || e.metaKey) return;
        const lb = openBox();
        if (!lb) return;
        const sw = lb.querySelector(".swiper");
        if (sw) sw.dispatchEvent(new w.MouseEvent("mousemove", { bubbles: true }));   // Elementor shows its toolbar on a key
        const list = Array.from(lb.querySelectorAll("a[href], button, input, select, textarea, iframe, video[controls], [tabindex]")).filter((el) =>
          el !== lb && el.tabIndex >= 0 && el.getClientRects().length && w.getComputedStyle(el).visibility !== "hidden");
        if (!list.length) return;
        e.preventDefault();
        e.stopImmediatePropagation();                     // Elementor's own Tab, after this one, moved it again
        const at = list.indexOf(d.activeElement);
        const to = e.shiftKey ? (at <= 0 ? list.length - 1 : at - 1) : (at < 0 || at >= list.length - 1 ? 0 : at + 1);
        list[to].focus();
      }, true);
    }

    /* ---------- 12. 1.5.9 a focused form field above the bar ----------------------------- */
    // Up to 1024px (the bar: .nh-float in a row) a browser scrolls only a textarea's caret into view: the field and its
    // hint (aria-describedby: «אין צורך לפרט כאן מידע רפואי…») could stay under the bar. After the browser's own
    // scroll, the page moves just enough for both to show above the bar, never pushing the field under the header.
    // 1.5.12: the field with its labels too (the consent's three lines beside its box), clear of the cookie banner as
    // well (above it at the foot, below it at the top), and on a short screen (no bar) on the screen, when the keys
    // move the focus (a tap leaves it to the browser and its on-screen keyboard). Shift+Tab: a label left under the
    // header comes down, as long as the field stays clear at the foot.
    const bar = d.querySelector(".nh-float");
    let byKeys = false;
    d.addEventListener("keydown", (e) => { if (e.key === "Tab") byKeys = true; }, true);
    d.addEventListener("pointerdown", () => { byKeys = false; }, true);
    d.addEventListener("focusin", (e) => {
      const f = e.target;
      if (!bar || inEditor || !f.matches || !f.matches("input, textarea, select")) return;
      if (f.closest(".nh-consent, .nh-float, .elementor-location-header, .elementor-lightbox")) return;   // fixed: scrolling does not move them
      w.requestAnimationFrame(() => {
        if (d.activeElement !== f || !f.getClientRects().length) return;
        const row = w.getComputedStyle(bar).flexDirection === "row";
        if (!row && !byKeys) return;
        const fr = f.getBoundingClientRect();
        let top = fr.top, bottom = fr.bottom, left = fr.left, right = fr.right;
        const add = (el) => {
          if (!el || !el.getClientRects().length) return;
          const r = el.getBoundingClientRect();
          top = Math.min(top, r.top); bottom = Math.max(bottom, r.bottom);
          left = Math.min(left, r.left); right = Math.max(right, r.right);
        };
        Array.from(f.labels || []).forEach(add);
        (f.getAttribute("aria-describedby") || "").split(/\s+/).forEach((id) => add(id ? d.getElementById(id) : null));
        let limit = (row ? bar.getBoundingClientRect().top : w.innerHeight) - 8;
        let head = headerFloats() ? headerLoc.getBoundingClientRect().bottom : 0;
        if (cons && html.matches(".nh-consent-ask, .nh-consent-open")) {
          const c = cons.getBoundingClientRect();
          if (c.height && !(right <= c.left || left >= c.right)) {
            if (consentAtTop(c)) head = Math.max(head, c.bottom);
            else limit = Math.min(limit, c.top - 8);
          }
        }
        head += 8;
        if (bottom > limit) {
          // never its label's top under the header; where the room is too short for both, the field's own top
          let room = top - head;
          if (room <= 0) room = fr.top - head;
          if (room > 0) w.scrollBy(0, Math.min(bottom - limit, room));
        } else if (top < head && byKeys) {
          const down = Math.max(top - head, bottom - limit);
          if (down < 0) w.scrollBy(0, down);
        }
      });
    });
  };

  if (d.readyState === "loading") d.addEventListener("DOMContentLoaded", init, { once: true });
  else init();
})();
