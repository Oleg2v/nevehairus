/* =========================================================================
   nevehairus-site · widget «masthead» (SPEC §4.18) — script handle nh-w-masthead
   Ported from the prototype (as committed in 4173d31):
   - site.js L59–95   header state after 24px, header theme from the [data-header] spread under it
   - site.js L135–150 menu (native <dialog>: focus trap and Esc for free, focus returns)
   - v3.js  L83–104   display panel «תצוגה» (a disclosure: Esc and outside click close it, focus returns)
   - v3.js  L106–114  running head: the name of the spread being read
   Changed for the plugin:
   - the running head shows data-running-plain instead of data-running in plain mode when the
     spread has one, and is updated on nh:displaychange;
   - spreads are found by attribute only ([data-header], [data-running]), never by DOM structure
     (Elementor puts its own wrappers between <main> and the spreads);
   - the page-wide listeners are bound once; they act on the masthead initialised last (the editor
     re-renders the widget); one masthead per page (SPEC §2.5);
   - the dialog is a sibling of the widget root, found through the menu button's aria-controls;
   - the first layout read happens in a frame, not while the page loads (SPEC §8.28);
   - no window.nhUpdateHeaderTheme global (nothing used it): the theme is re-probed on
     nh:displaychange instead.
   The three display switches (plain / motion / calm) are handled by nh-core.js (delegated).
   ========================================================================= */
(() => {
  "use strict";
  const d = document, w = window, html = d.documentElement;
  const nh = w.nh;
  if (!nh || typeof nh.widget !== "function") return;

  const page = { el: null, running: null, spread: null, bound: false };

  /* ---------- running head ------------------------------------------------------- */
  const runningName = (s) => {
    if (html.classList.contains("nh-plainmode") && s.hasAttribute("data-running-plain")) {
      const p = s.getAttribute("data-running-plain") || "";
      return p === "-" ? "" : p;                                         // "-" = hidden in plain mode
    }
    return s.getAttribute("data-running") || "";
  };
  const showRunning = () => {
    if (page.running && page.spread) page.running.textContent = runningName(page.spread);
  };

  /* ---------- header state + theme follows the spread beneath it ------------------ */
  const update = () => {
    const header = page.el;
    if (!header || !header.isConnected) return;
    header.classList.toggle("is-scrolled", w.scrollY > 24);
    const y = header.getBoundingClientRect().bottom - 6;
    let theme = "light";
    for (const s of d.querySelectorAll("[data-header]")) {
      const b = s.getBoundingClientRect();
      if (b.top <= y && b.bottom > y) { theme = s.dataset.header; break; }
    }
    if (header.dataset.theme !== theme) header.dataset.theme = theme;
  };

  const bindPage = () => {
    if (page.bound) return;
    page.bound = true;
    let ticking = false;
    const later = () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => { ticking = false; update(); });
    };
    w.addEventListener("scroll", later, { passive: true });
    w.addEventListener("resize", later);
    d.addEventListener("nh:displaychange", () => { showRunning(); later(); });

    // the band just under the masthead; by attribute, so page-builder wrappers do not matter
    const spreads = d.querySelectorAll("[data-running]:not(.v3-mast__running)");
    if (spreads.length && "IntersectionObserver" in w) {
      const io = new IntersectionObserver((entries) => entries.forEach((e) => {
        if (e.isIntersecting) { page.spread = e.target; showRunning(); }
      }), { rootMargin: "-10% 0px -85% 0px" });
      spreads.forEach((s) => io.observe(s));
    }
  };

  /* ---------- display panel ------------------------------------------------------- */
  const initDisplay = (el) => {
    const btn = el.querySelector("[data-display-open]");
    const id = btn && btn.getAttribute("aria-controls");
    const panel = id ? d.getElementById(id) : null;
    if (!btn || !panel || !el.contains(panel)) return;
    const close = (focusBack) => {
      if (panel.hidden) return;
      panel.hidden = true; btn.setAttribute("aria-expanded", "false");
      if (focusBack) btn.focus();
    };
    btn.addEventListener("click", () => {
      const open = panel.hidden;
      panel.hidden = !open; btn.setAttribute("aria-expanded", String(open));
      if (open) {
        const first = panel.querySelector("button");
        if (first) first.focus();
      }
    });
    panel.addEventListener("keydown", (e) => { if (e.key === "Escape") { e.preventDefault(); close(true); } });
    btn.addEventListener("keydown", (e) => { if (e.key === "Escape") close(true); });
    d.addEventListener("click", (e) => {
      if (panel.isConnected && !panel.contains(e.target) && !btn.contains(e.target)) close(false);
    });
    panel.addEventListener("focusout", (e) => {
      const to = e.relatedTarget;
      if (to && !panel.contains(to) && to !== btn) close(false);
    });
  };

  /* ---------- menu ------------------------------------------------------------------ */
  const initMenu = (el) => {
    const btn = el.querySelector("[data-menu-open]");
    const id = btn && btn.getAttribute("aria-controls");
    const menu = id ? d.getElementById(id) : null;                       // <dialog id="nh-menu">, the root's sibling
    if (!btn || !menu || typeof menu.showModal !== "function") return;
    btn.addEventListener("click", () => {
      if (!menu.open) menu.showModal();
      btn.setAttribute("aria-expanded", "true");
    });
    menu.addEventListener("close", () => {
      btn.setAttribute("aria-expanded", "false");
      btn.focus();
    });
    menu.querySelectorAll("[data-menu-close], nav a").forEach((a) =>
      a.addEventListener("click", () => menu.close())
    );
  };

  nh.widget("masthead", ".v3-mast", (el) => {
    page.el = el;
    page.running = el.querySelector(".v3-mast__running");
    bindPage();
    showRunning();                                                        // a re-rendered masthead keeps the name
    requestAnimationFrame(update);                                        // first read inside a frame, not at load
    initDisplay(el);
    initMenu(el);
  });
})();
