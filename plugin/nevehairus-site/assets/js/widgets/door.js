/* =========================================================================
   nevehairus-site · widget "door" (ד׳ · הדלת וטופס ביקור) — SPEC §4.12
   Port of prototype/assets/js/v3.js L186: once, when the doorway is reached, it gets .is-open and the
   lintel of the wide ד extends from 60% to the full width (door.css, @property --open, 0.9 s). The
   doorway is closed only under html.v3-ready and never under reduced motion or calm mode, so without
   JavaScript, or with motion off, the door is open from the start.
   Validation and sending of the visit form are in nh-forms.js.
   ========================================================================= */
(() => {
  "use strict";
  const nh = window.nh;
  if (!nh || typeof nh.widget !== "function") return;
  nh.widget("door", ".v3-door__way", (el) => nh.once(el, "is-open", "0px 0px -20% 0px"));
})();
