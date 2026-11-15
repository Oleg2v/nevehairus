/* =========================================================================
   נווה האירוס — home page behaviours that do NOT need GSAP
   - giant years fill with colour when reached (static under reduced motion)
   (duotone → colour lives in site.js; the Mandala Lock is CSS masks + GSAP)
   ========================================================================= */
(() => {
  "use strict";
  const d = document, w = window, html = d.documentElement;
  const reduced = () =>
    html.classList.contains("nh-reduce-motion") ||
    (w.matchMedia && w.matchMedia("(prefers-reduced-motion: reduce)").matches);

  /* ---------- Giant years fill when reached --------------------------------- */
  const years = d.querySelectorAll(".v2-year");
  if (reduced() || !("IntersectionObserver" in w)) years.forEach((y) => y.classList.add("is-filled"));
  else {
    const io2 = new IntersectionObserver((entries) => entries.forEach((e) => {
      if (e.isIntersecting) { e.target.classList.add("is-filled"); io2.unobserve(e.target); }
    }), { rootMargin: "0px 0px -35% 0px" });
    years.forEach((y) => io2.observe(y));
  }
})();
