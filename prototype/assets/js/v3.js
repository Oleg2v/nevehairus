/* =========================================================================
   נווה האירוס v3 «אותיות שגרים בהן» — behaviour. No animation library.
   v3.2 «calm» (research/07 §4, §11.1): nothing moves by itself. The letters stand still at one weight each;
   there is no cover morph, no "once, when seen", no palm swap and no page transition.
   1. Display choices: plain language for the whole page, calm mode; the reader keeps their place
   2. The one allowed appearance (§4.2): a print photo appears once, softly, when it is on screen and loaded
   3. Without photos (images off, a missing file) the design holds: rooms show the sky, prints are not shown
   No scroll listeners, no requestAnimationFrame loops, no ResizeObserver; one IntersectionObserver (2).
   ========================================================================= */
(() => {
  "use strict";
  const d = document, w = window, html = d.documentElement;
  html.classList.add("v3-ready");                                         // states that need this script to undo them
  const store = {
    get(k) { try { return w.localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { w.localStorage.setItem(k, v); } catch (e) { /* private mode */ } },
  };
  const osReduced = () => w.matchMedia && w.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const motionOK = () => !html.classList.contains("nh-reduce-motion") && !html.classList.contains("nh-calm") && !osReduced();
  const live = d.querySelector("[data-announce]");
  // an open modal dialog makes the rest of the page inert: speak from inside it
  const announce = (msg) => {
    const el = d.querySelector("dialog[open] [data-announce-menu]") || live;
    if (!el) return;
    el.textContent = ""; setTimeout(() => { el.textContent = msg; }, 80);
  };

  /* ---------- 1. Plain language and calm mode ------------------------------ */
  const sync = () => {
    const plain = String(html.classList.contains("nh-plainmode")), calm = String(html.classList.contains("nh-calm"));
    // switches keep a constant label and say their state; the action button in the plain box changes its label instead
    d.querySelectorAll("[data-plain-toggle]:not([data-plain-toggle='action'])").forEach((b) => b.setAttribute("aria-pressed", plain));
    d.querySelectorAll("[data-calm-toggle]").forEach((b) => b.setAttribute("aria-pressed", calm));
  };
  // nobody loses their place: the control that was used (or else the first line of a spread on screen) stays put
  const keepPlace = (change) => {
    const top = (d.querySelector(".nh-header") || { offsetHeight: 0 }).offsetHeight;
    let anchor = null, before = 0;
    const a = d.activeElement;
    if (a && a !== d.body && a.closest("main, footer")) { anchor = a; before = a.getBoundingClientRect().top; }
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
  d.querySelectorAll("[data-plain-toggle]").forEach((b) => b.addEventListener("click", () => {
    const on = !html.classList.contains("nh-plainmode");
    keepPlace(() => html.classList.toggle("nh-plainmode", on));
    store.set("nh-plain", on ? "on" : "off");
    try {
      const url = new URL(w.location.href);
      if (url.searchParams.has("plain")) { url.searchParams.set("plain", on ? "1" : "0"); history.replaceState(null, "", url); }
    } catch (e) { /* old browsers */ }
    sync(); changed();
    // a page with no plain-language text does not change, so it must not be announced as changed (the plugin's
    // pages outside its widgets; both pages here have it)
    const none = on && !d.querySelector("main .v3-plain") && "שפה פשוטה פועלת. לדף הזה אין עדיין נוסח בשפה פשוטה.";
    announce(none || (on ? "הדף מוצג עכשיו בשפה פשוטה." : "הדף מוצג עכשיו בקריאה רגילה."));
  }));
  d.querySelectorAll("[data-calm-toggle]").forEach((b) => b.addEventListener("click", () => {
    const on = !html.classList.contains("nh-calm");
    html.classList.toggle("nh-calm", on);
    store.set("nh-calm", on ? "on" : "off");
    sync(); changed();
    announce(on ? "מצב רגוע פעיל: בלי תנועה ועם צבעים רכים יותר." : "מצב רגוע כבוי.");
  }));
  sync();

  /* ---------- 2. v3.2: the one allowed reveal (print photos only, once) — research/07 §4.2 -----------------
     figure.v3-print[data-reveal]: only the photo frame (> picture) moves: opacity and 8px, 320ms, once, when at
     least 15% of the print is on screen and its picture has loaded. The caption never moves. A print that is on
     or above the screen at the observer's first report is never hidden. Nothing is hidden without this script,
     without IntersectionObserver, in the Elementor preview, with reduced motion or in calm mode (the CSS shows
     anything still waiting at once if one of them is switched on later). */
  const reveal = () => {
    if (!motionOK() || !("IntersectionObserver" in w) || /[?&]elementor-preview=/.test(w.location.search)) return;
    const els = d.querySelectorAll("[data-reveal]:not(.is-shown)");
    if (!els.length) return;
    const show = (el) => requestAnimationFrame(() => { el.classList.add("is-shown"); el.classList.remove("is-waiting"); });
    const io = new IntersectionObserver((entries) => entries.forEach((e) => {
      const el = e.target;
      if (!el.dataset.revealSeen) {                 // first report: never hide what is already on or above the screen
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
  reveal();

  /* ---------- 3. A photo that cannot be shown (images switched off, a missing file) — research/07 §8.3 p. 4 -------
     The design holds without photos: a letter's room shows its quiet sky, a print is not shown at all (in the
     gallery its cell goes too, so the grid closes up without a hole), the logo keeps its name. Instant, no motion. */
  const lost = (img) => {
    const room = img.closest(".v3-room");
    if (room) { room.classList.add("is-sky"); return; }
    const print = img.closest(".v3-print");
    if (print) { (print.closest(".v3-gallery > li") || print).hidden = true; return; }
    img.hidden = true;                                                      // the emblem: the name stays
  };
  d.querySelectorAll(".v3-room img, .v3-print img, img.nh-logo__mark, .nh-footer__logo img").forEach((img) => {
    if (img.complete && img.getAttribute("src") && !img.naturalWidth) lost(img);   // failed before this script ran
    else img.addEventListener("error", () => lost(img), { once: true });
  });
})();
