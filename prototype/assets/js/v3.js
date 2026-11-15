/* =========================================================================
   נווה האירוס v3 «אותיות שגרים בהן» — behaviour. No animation library.
   1. Display choices: plain language for the whole page, calm mode
      (reduced motion lives in site.js and is shared via "nh:motionchange")
   2. Display panel (a disclosure): Esc closes it and returns focus
   3. Running head: the masthead names the spread being read
   4. Cover ב: weight 800 → 300 while the letter is pinned (desktop; 800 → 500 on phones,
      over the first 40% of a screen), walls get thinner and the room grows. rAF, no layout
      reads in the loop.
   5. Once, when seen: a glint across the mandala's glass heart, the door's lintel extends
   6. The palm (כ): choosing a work puts it in the palm
   7. Mobile visit bar steps aside while a form field is being filled in
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
  // nobody loses their place: the control that was used (or else the spread at the top of the screen) stays put
  const keepPlace = (change) => {
    const top = (d.querySelector(".nh-header") || { offsetHeight: 0 }).offsetHeight;
    let anchor = null, before = 0;
    const a = d.activeElement;
    if (a && a !== d.body && a.closest("main, footer")) { anchor = a; before = a.getBoundingClientRect().top; }
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
  d.querySelectorAll("[data-plain-toggle]").forEach((b) => b.addEventListener("click", () => {
    const on = !html.classList.contains("nh-plainmode");
    keepPlace(() => html.classList.toggle("nh-plainmode", on));
    store.set("nh-plain", on ? "on" : "off");
    try {
      const url = new URL(w.location.href);
      if (url.searchParams.has("plain")) { url.searchParams.set("plain", on ? "1" : "0"); history.replaceState(null, "", url); }
    } catch (e) { /* old browsers */ }
    sync(); changed();
    announce(on ? "הדף מוצג עכשיו בשפה פשוטה." : "הדף מוצג עכשיו בקריאה רגילה.");
  }));
  d.querySelectorAll("[data-calm-toggle]").forEach((b) => b.addEventListener("click", () => {
    const on = !html.classList.contains("nh-calm");
    html.classList.toggle("nh-calm", on);
    store.set("nh-calm", on ? "on" : "off");
    sync(); changed();
    announce(on ? "מצב רגוע פעיל: בלי תנועה ועם צבעים רכים יותר." : "מצב רגוע כבוי.");
  }));
  d.addEventListener("nh:motionchange", (e) => announce(e.detail && e.detail.reduce ? "התנועה באתר מופחתת." : "התנועה באתר חזרה."));
  sync();

  /* ---------- 2. Display panel ------------------------------------------------ */
  const dBtn = d.querySelector("[data-display-open]");
  const panel = dBtn && d.getElementById(dBtn.getAttribute("aria-controls"));
  if (dBtn && panel) {
    const close = (focusBack) => {
      if (panel.hidden) return;
      panel.hidden = true; dBtn.setAttribute("aria-expanded", "false");
      if (focusBack) dBtn.focus();
    };
    dBtn.addEventListener("click", () => {
      const open = panel.hidden;
      panel.hidden = !open; dBtn.setAttribute("aria-expanded", String(open));
      if (open) panel.querySelector("button").focus();
    });
    panel.addEventListener("keydown", (e) => { if (e.key === "Escape") { e.preventDefault(); close(true); } });
    dBtn.addEventListener("keydown", (e) => { if (e.key === "Escape") close(true); });
    d.addEventListener("click", (e) => { if (!panel.contains(e.target) && !dBtn.contains(e.target)) close(false); });
    panel.addEventListener("focusout", (e) => {
      const to = e.relatedTarget;
      if (to && !panel.contains(to) && to !== dBtn) close(false);
    });
  }

  /* ---------- 3. Running head --------------------------------------------------- */
  const running = d.querySelector(".v3-mast__running");
  const spreads = d.querySelectorAll("[data-running]");                  // by attribute, so page-builder wrappers do not matter
  if (running && spreads.length && "IntersectionObserver" in w) {
    const io = new IntersectionObserver((entries) => entries.forEach((e) => {
      if (e.isIntersecting) running.textContent = e.target.dataset.running;
    }), { rootMargin: "-15% 0px -80% 0px" });
    spreads.forEach((s) => io.observe(s));
  }

  /* ---------- 4. Cover ב: the house makes room --------------------------------- */
  const data = d.getElementById("nh-letters");
  const room = d.querySelector(".v3-cover .v3-room");
  const cover = d.querySelector(".v3-cover");
  if (data && room && cover) {
    const L = JSON.parse(data.textContent).bet;
    const ink = room.querySelector(".v3-letter__ink");
    const arity = { M: 2, L: 2, Q: 4, C: 6, Z: 0 };
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
        // not pinned: finish while the room is still at least 70% visible under the masthead
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
    const decide = () => (motionOK() && !html.classList.contains("nh-plainmode") ? start() : stop());
    decide();
    d.addEventListener("nh:motionchange", decide);
    d.addEventListener("nh:displaychange", decide);
  }

  /* ---------- 5. Once, when seen ---------------------------------------------------- */
  const once = (sel, cls, rootMargin) => {
    const els = d.querySelectorAll(sel);
    if (!els.length) return;
    if (!("IntersectionObserver" in w)) { els.forEach((el) => el.classList.add(cls)); return; }
    const io = new IntersectionObserver((entries) => entries.forEach((e) => {
      if (e.isIntersecting) { e.target.classList.add(cls); io.unobserve(e.target); }
    }), { rootMargin: rootMargin || "0px 0px -30% 0px" });
    els.forEach((el) => io.observe(el));
  };
  once(".v3-samekh__art", "is-lit");
  once(".v3-door__way", "is-open", "0px 0px -20% 0px");

  /* ---------- 6. The palm holds the chosen work ---------------------------------- */
  const worksData = d.getElementById("nh-works");
  const palm = d.querySelector(".v3-kaf__palm");
  if (worksData && palm) {
    const W = JSON.parse(worksData.textContent);
    const source = palm.querySelector("source"), img = palm.querySelector("img");
    const put = (name) => {
      const m = W[name]; if (!m || !img) return;
      const set = (ext) => m.widths.map((x) => `assets/img/${name}-${x}.${ext} ${x}w`).join(", ");
      const swap = () => {
        if (source) source.srcset = set("avif");
        img.srcset = set("webp");
        img.src = `assets/img/${name}-${m.widths.includes(768) ? 768 : m.widths[m.widths.length - 1]}.webp`;
      };
      if (!motionOK()) { swap(); return; }
      img.style.opacity = "0";
      setTimeout(() => {
        img.addEventListener("load", () => { img.style.opacity = ""; }, { once: true });
        swap();
        if (img.complete) img.style.opacity = "";
      }, 300);
    };
    const name = d.querySelector("[data-work-name]");                     // «בכף היד: …»
    d.querySelectorAll("[data-work]").forEach((b) => b.addEventListener("click", () => {
      d.querySelectorAll("[data-work]").forEach((o) => o.setAttribute("aria-pressed", String(o === b)));
      if (name) name.textContent = b.textContent.trim();
      put(b.dataset.work);
    }));
  }

  /* ---------- 6b. Page transition: the homes-index ב becomes the page cover ב ------ */
  d.querySelectorAll(".v3-home__link[href$='.html']").forEach((a) => a.addEventListener("click", () => {
    const r = a.querySelector(".v3-room");
    if (!r || !motionOK()) return;
    r.style.viewTransitionName = "home-bet-1";
    try { sessionStorage.setItem("nh-vt", "home-bet-1"); } catch (e) { /* private mode: plain cross-fade */ }
  }));
  w.addEventListener("pageshow", () => d.querySelectorAll(".v3-home__link .v3-room").forEach((r) => { r.style.viewTransitionName = ""; }));

  /* ---------- 7. The visit bar steps aside while typing --------------------------- */
  const bar = d.querySelector("[data-bar]");
  if (bar) {
    const field = (el) => el && el.matches && el.matches("input, select, textarea");
    d.addEventListener("focusin", (e) => { if (field(e.target)) bar.classList.add("is-typing"); });
    d.addEventListener("focusout", (e) => { if (field(e.target)) bar.classList.remove("is-typing"); });
  }
})();
