/* nevehairus-site · widget "homes" (script handle nh-w-homes, depends on nh-core)
   Page transition: the homes-index ב becomes the house page's cover ב (prototype v3.js L226–233, SPEC D11).
   The transition name is unique per post — "home-bet-{post id}", rendered on the row as data-vt — and it is
   given only for this one journey and only while motion is allowed (not with the OS setting, «הפחתת תנועה» or
   «מצב רגוע»). The house page names its cover in `pagereveal` (pre-paint script) when it was reached this way;
   every other navigation is a plain cross-fade, and without support the page simply opens. */
(() => {
  "use strict";
  const nh = window.nh;
  if (!nh || typeof nh.widget !== "function") return;

  nh.widget("homes", ".v3-homes", (el) => {
    el.querySelectorAll(".v3-home__link[data-vt]").forEach((a) => a.addEventListener("click", () => {
      const r = a.querySelector(".v3-room"), vt = a.dataset.vt;
      if (!r || !vt || !nh.motionOK()) return;
      r.style.viewTransitionName = vt;
      try { sessionStorage.setItem("nh-vt", vt); } catch (e) { /* private mode: plain cross-fade */ }
    }));
    // coming back (back/forward cache): the rows lose the name again, so only a new click names a room
    window.addEventListener("pageshow", () => el.querySelectorAll(".v3-home__link .v3-room").forEach((r) => { r.style.viewTransitionName = ""; }));
  });
})();
