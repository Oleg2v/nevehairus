/* =========================================================================
   נווה האירוס — widget cover: the house makes room (plugin nevehairus-site, core).
   The ב thins from its weight (800 on the home cover) to 300 while the page scrolls
   on wide, tall screens (the letter is pinned by CSS sticky), to 500 elsewhere, finished
   while the room is still ~80% on screen. Walls get thinner, the room grows. rAF, no
   layout reads in the loop; nothing moves with reduced motion, calm mode or plain language.
   Ported from prototype/assets/js/v3.js L116–173 (v3.1). Only the first cover of a page
   morphs; the page cover (widget page-cover) uses this script too and starts at its own --t.
   ========================================================================= */
(() => {
  "use strict";
  const d = document, w = window, html = d.documentElement;
  const nh = w.nh;
  if (!nh || !nh.widget) return;
  const arity = { M: 2, L: 2, Q: 4, C: 6, Z: 0 };
  let current = null;                                                       // { el, stop } of the cover that morphs

  const init = (cover) => {
    if (current && current.el === cover) return;
    if (current && current.el.isConnected) return;                          // one morphing cover per page
    if (current) current.stop();                                            // the editor replaced the cover
    const data = d.getElementById("nh-letters");
    const room = cover.querySelector(".v3-room");
    const ink = room && room.querySelector(".v3-letter__ink");
    if (!data || !room || !ink) return;
    let L;
    try { L = JSON.parse(data.textContent).bet; } catch (e) { return; }
    if (!L || !L.c || !L.a || !L.b) return;

    const outline = (t) => {
      let i = 0, out = "";
      for (const c of L.c) {
        out += c;
        for (let k = 0; k < arity[c]; k++, i++) out += (k ? " " : "") + (L.a[i] + (L.b[i] - L.a[i]) * t).toFixed(1);
      }
      return out;
    };
    // the page cover starts lighter (its own --t, e.g. 0.4 = wght 600) and only gets lighter
    const t0 = parseFloat(getComputedStyle(room).getPropertyValue("--t")) || 0;
    let last = -1, raf = 0, on = false, maxT = 1, range = 1, measured = false;
    const measure = () => {
      const wide = w.matchMedia("(min-width: 64em)").matches;
      const pinned = wide && w.matchMedia("(min-height: 43.75em)").matches;   // the 150svh cover with a sticky ב (motion on)
      maxT = wide ? 1 : 0.6;                                                    // 800 → 300 / 800 → 500
      // pinned: t reaches 1 while the whole letter is still on screen; otherwise within 40% of a screen
      if (pinned && !cover.classList.contains("v3-cover--page")) range = Math.max(1, cover.offsetHeight - w.innerHeight);
      else {
        // not pinned: finish while the room is still about 80% visible under the masthead
        const r = (room.querySelector(".v3-room__photo") || room).getBoundingClientRect(), mast = (d.querySelector(".nh-header") || { offsetHeight: 0 }).offsetHeight;
        range = Math.max(80, r.top + w.scrollY + r.height * 0.2 - mast);
      }
    };
    const paint = (t) => { ink.setAttribute("d", outline(t)); room.style.setProperty("--t", String(t)); };
    const frame = () => {
      raf = 0;
      if (!measured) { measure(); measured = true; }                     // first read inside a frame, not at load
      const p = Math.min(1, Math.max(0, w.scrollY / range));
      const t = Math.round((t0 + (Math.max(t0, maxT) - t0) * p) * 200) / 200;
      if (t !== last) { last = t; paint(t); }
    };
    const onScroll = () => { if (!raf) raf = requestAnimationFrame(frame); };
    const onResize = () => { measured = false; onScroll(); };
    const start = () => {
      if (on) return; on = true; measured = false;
      w.addEventListener("scroll", onScroll, { passive: true });
      w.addEventListener("resize", onResize);
      if (w.scrollY > 0) onScroll();                                     // at the top the letter is already at t0
    };
    const stop = () => {
      if (!on) return; on = false; last = -1;
      w.removeEventListener("scroll", onScroll);
      w.removeEventListener("resize", onResize);
      paint(t0);
    };
    const decide = () => (nh.motionOK() && !html.classList.contains("nh-plainmode") ? start() : stop());
    decide();
    d.addEventListener("nh:motionchange", decide);
    d.addEventListener("nh:displaychange", decide);
    current = {
      el: cover,
      stop: () => {
        stop();
        d.removeEventListener("nh:motionchange", decide);
        d.removeEventListener("nh:displaychange", decide);
      },
    };
  };

  nh.widget("cover", ".v3-cover", init);
  nh.widget("page-cover", ".v3-cover", init);                               // the editor re-renders a page cover by its own name
})();
