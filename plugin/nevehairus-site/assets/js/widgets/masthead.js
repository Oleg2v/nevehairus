/* =========================================================================
   nevehairus-site · widget «masthead» (SPEC §4.18) — script handle nh-w-masthead
   v3.2 (research/07 §5.4, §10.1 item 1): only the phone menu is left. The masthead never changes
   while the page scrolls (no scroll state, no theme from the spread beneath, no running head) and
   the display panel «תצוגה» is gone; the two switches are handled by nh-core.js (delegated).

   The menu (prototype site.js «1. Phone menu»): a native <dialog> opened only by «תפריט», at once.
   Esc and «סגירה» close it and focus returns to «תפריט». A link of the menu (or its visit button)
   closes it too, but then focus does not return to «תפריט»: the link sets menu.dataset.nav, and the
   close handler sees it and clears it. nh-core.js then focuses the heading of the link's target when
   it is on this page (after the dialog's close event), or the browser leaves for another page.

   Changed for the plugin: the dialog is a sibling of the widget root, found through the menu
   button's aria-controls; one masthead per page (SPEC §2.5); Elementor's editor may re-render the
   widget, and nh.widget() then runs this again for the new element only.
   ========================================================================= */
(() => {
  "use strict";
  const d = document, w = window;
  const nh = w.nh;
  if (!nh || typeof nh.widget !== "function") return;

  // a plain left click: a link opened in a new tab or window leaves the menu open
  const plainClick = (e) => e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey;

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
      if (menu.dataset.nav) { delete menu.dataset.nav; return; }        // a link was followed: focus goes to its target
      btn.focus();
    });
    menu.querySelectorAll("[data-menu-close]").forEach((b) => b.addEventListener("click", () => menu.close()));
    menu.querySelectorAll("nav a, a[data-visit-cta]").forEach((a) => a.addEventListener("click", (e) => {
      if (!plainClick(e)) return;
      menu.dataset.nav = "1";
      menu.close();
    }));
  };

  nh.widget("masthead", ".v3-mast", initMenu);
})();
