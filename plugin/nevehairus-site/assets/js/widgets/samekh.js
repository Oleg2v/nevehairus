/* =========================================================================
   nh-samekh — ס׳ · once, when the work is first seen, a glint crosses the mandala's
   glass heart (SPEC §4.7; prototype v3.js L185). The animation itself is CSS and runs
   only when motion is allowed; the rings are lit by CSS (:has() on the circles list).
   ========================================================================= */
(() => {
  "use strict";
  const nh = window.nh;
  if (!nh || typeof nh.widget !== "function") return;

  nh.widget("samekh", ".v3-samekh__art", (el) => nh.once(el, "is-lit"));
})();
