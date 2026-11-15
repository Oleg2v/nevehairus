/* =========================================================================
   נווה האירוס — motion layer v2 ("ב׳ — בית")
   GSAP 3.15 + ScrollTrigger + SplitText, served locally, loaded after `load`
   and never loaded under reduced motion (see partials/head.html).
   Rules: native scrolling only (no smooth-scroll hijack), no autoplay loops,
   every effect has a static equivalent. Spec: prototype/STORYBOARD.md
   ========================================================================= */
(() => {
  "use strict";
  const d = document;
  const html = d.documentElement;
  const release = () => html.classList.remove("nh-motion-pending");
  if (!window.gsap || !window.ScrollTrigger) { release(); return; }
  const { gsap, ScrollTrigger } = window;
  const SplitText = window.SplitText;
  gsap.registerPlugin(ScrollTrigger);
  if (SplitText) gsap.registerPlugin(SplitText);

  const userReduced = () => html.classList.contains("nh-reduce-motion");
  const $ = (s, r) => (r || d).querySelector(s);
  const $$ = (s, r) => gsap.utils.toArray(s, r);
  const headerH = () => (d.querySelector(".nh-header") || { offsetHeight: 76 }).offsetHeight;

  /* Accessible line split: screen readers get one intact sentence (sr-only),
     the visual copy is aria-hidden and is the one that gets split. */
  function prep(el) {
    if (el.dataset.prepped === "1") return el.querySelector(".v2-split");
    const vis = d.createElement("span");
    vis.className = "v2-split";
    vis.setAttribute("aria-hidden", "true");
    vis.innerHTML = el.innerHTML;
    const sr = d.createElement("span");
    sr.className = "nh-sr-only";
    sr.textContent = el.textContent.replace(/\s+/g, " ").trim();
    el.replaceChildren(sr, vis);
    el.dataset.prepped = "1";
    return vis;
  }

  let mm;
  function build() {
    mm = gsap.matchMedia();
    mm.add(
      {
        motion: "(prefers-reduced-motion: no-preference)",
        desktop: "(min-width: 1024px)",
        stacky: "(min-width: 720px) and (min-height: 641px)",
      },
      (ctx) => {
        const { motion, desktop, stacky } = ctx.conditions;
        if (!motion || userReduced()) { release(); return; }
        const splits = [], cleanups = [];

        /* A. Reveal: opacity 0→1, y 32→0, 0.9s expo.out, stagger 0.08 */
        const vh = window.innerHeight;
        const reveals = $$("[data-reveal]").filter((el) => el.getBoundingClientRect().top > vh * 0.92);
        if (reveals.length) {
          gsap.set(reveals, { opacity: 0, y: 32 });
          ScrollTrigger.batch(reveals, {
            start: "top 88%", once: true,
            onEnter: (b) => gsap.to(b, { opacity: 1, y: 0, duration: 0.9, ease: "expo.out", stagger: 0.08, overwrite: true }),
          });
        }
        release();

        /* B. Masked line reveal for big statements (SplitText lines + mask) */
        if (SplitText) {
          $$("[data-lines]").forEach((el) => {
            const vis = prep(el);
            const st = SplitText.create(vis, { type: "lines", mask: "lines", aria: "none", linesClass: "v2-l" });
            splits.push(st);
            gsap.from(st.lines, {
              yPercent: 108, duration: 1.15, ease: "expo.out", stagger: 0.09,
              scrollTrigger: { trigger: el, start: "top 86%", once: true },
            });
          });
        }

        /* C. HERO — walk into the house through the letter ב */
        const hero = $(".v2-hero");
        if (hero) {
          const wrap = $(".v2-hero__bet", hero), bet = $(".v2-bet", hero);
          if (desktop && wrap && bet) {
            const wr = wrap.getBoundingClientRect(), br = bet.getBoundingClientRect();
            // zoom into the counter of the ב — the inside of the house
            const ox = br.left - wr.left + br.width * 0.41, oy = br.top - wr.top + br.height * 0.6;
            gsap.set(wrap, { transformOrigin: `${ox}px ${oy}px` });
            // ×9 already fills every tested viewport (1024×768 … 2560×1080) with the
            // night inside the letter; ×11 leaves a margin. (×22 cost 3 fps.)
            let hideT;
            cleanups.push(() => { clearTimeout(hideT); wrap.classList.remove("is-past"); });
            gsap.timeline({
              defaults: { ease: "none" },
              scrollTrigger: {
                trigger: hero, start: "top top", end: "+=130%", scrub: 0.7, pin: true, anticipatePin: 1,
                // past the zoom the giant layer is dropped from paint, also on slow
                // machines where the scrub has not caught up yet
                onLeave: () => { clearTimeout(hideT); hideT = setTimeout(() => wrap.classList.add("is-past"), 900); },
                onEnterBack: () => { clearTimeout(hideT); wrap.classList.remove("is-past"); },
              },
            })
              .to(".v2-hero__copy", { opacity: 0, y: -90, duration: 0.3, ease: "power1.in" }, 0)
              .to(".v2-hero__meta", { opacity: 0, duration: 0.15 }, 0)
              .to(wrap, { scale: 11, duration: 1, ease: "power3.in" }, 0.05)
              .set(wrap, { autoAlpha: 0 }, 1.05);
          } else if (wrap) {
            gsap.to(wrap, { yPercent: 14, scale: 1.06, scrollTrigger: { trigger: hero, start: "top top", end: "bottom top", scrub: true } });
          }
        }

        /* D. Three homes — stacking cards: the card underneath settles back */
        if (stacky) {
          const cards = $$(".v2-card");
          cards.forEach((card, i) => {
            const next = cards[i + 1];
            if (!next) return;
            let shade = card.querySelector(".v2-card__shade");
            if (!shade) { shade = d.createElement("div"); shade.className = "v2-card__shade"; card.appendChild(shade); }
            gsap.timeline({ scrollTrigger: { trigger: next, start: "top bottom", end: () => `top ${headerH() + 40}px`, scrub: true } })
              .to(card, { scale: 0.93, ease: "none" }, 0)
              .to(shade, { opacity: 0.32, ease: "none" }, 0);
          });
        }

        /* E. A whole day — pinned colour script, sun on the arch (desktop) */
        const day = $(".v2-day");
        if (day && desktop) {
          day.classList.remove("v2-day--static");
          day.classList.add("v2-day--pinned");
          const moments = $$(".v2-moment", day);
          const n = moments.length;
          const sun = $(".v2-day__sun", day), clock = $(".v2-day__clock", day), bars = $$(".v2-day__progress i", day);
          const head = $(".v2-day__head", day);
          const ds = (i) => moments[i].dataset;
          gsap.set(day, { "--top": ds(0).top, "--bottom": ds(0).bottom, "--fg": ds(0).fg });
          // opacity only (never visibility/autoAlpha): inactive moments stay in the
          // accessibility tree, so heading navigation reaches all five
          gsap.set(moments, { opacity: 0, y: 60 });
          gsap.set(moments[0], { opacity: 1, y: 0 });
          let last = -1;
          const tl = gsap.timeline({
            defaults: { ease: "none" },
            scrollTrigger: {
              trigger: day, start: "top top", end: `+=${n * 95}%`, scrub: 0.6, pin: true, anticipatePin: 1,
              onUpdate: (self) => {
                const p = self.progress, th = p * Math.PI;
                sun.setAttribute("cx", (500 + 480 * Math.cos(th)).toFixed(1));
                sun.setAttribute("cy", (500 - 480 * Math.sin(th)).toFixed(1));
                const i = Math.min(n - 1, Math.floor(p * n));
                if (i !== last) {
                  last = i;
                  clock.textContent = ds(i).when;
                  day.dataset.header = ds(i).fg.toUpperCase() === "#F7F1E7" ? "dark" : "light";
                  if (window.nhUpdateHeaderTheme) window.nhUpdateHeaderTheme();
                }
              },
              // after the pin the section keeps its evening colours → dark header
              onLeave: () => { day.dataset.header = "dark"; if (window.nhUpdateHeaderTheme) window.nhUpdateHeaderTheme(); },
              onLeaveBack: () => { day.dataset.header = "light"; if (window.nhUpdateHeaderTheme) window.nhUpdateHeaderTheme(); },
            },
          });
          tl.to(head, { opacity: 0, y: -40, duration: 0.3 }, 0.55);
          moments.forEach((m, i) => {
            if (i > 0) {
              tl.to(day, { "--top": ds(i).top, "--bottom": ds(i).bottom, "--fg": ds(i).fg, duration: 0.45 }, i - 0.25)
                .to(moments[i - 1], { opacity: 0, y: -60, duration: 0.3 }, i - 0.22)
                .to(m, { opacity: 1, y: 0, duration: 0.35 }, i - 0.05);
            }
            tl.to(bars[i], { scaleX: 1, duration: 1 }, i);
          });
          ctx.add(() => () => {        // cleanup when the context reverts
            day.classList.add("v2-day--static");
            day.classList.remove("v2-day--pinned");
            day.dataset.header = "light";
          });
        }

        /* F. Word band moves only with the scroll */
        const band = $("[data-band]");
        if (band) {
          gsap.fromTo(band, { xPercent: -22 }, { xPercent: 4, ease: "none",
            scrollTrigger: { trigger: band.parentElement, start: "top bottom", end: "bottom top", scrub: true } });
        }

        /* G. Years: photo frames drift gently against the giant numbers */
        if (desktop) {
          $$(".v2-year__media").forEach((f) =>
            gsap.fromTo(f, { y: 60 }, { y: -60, ease: "none", scrollTrigger: { trigger: f, start: "top bottom", end: "bottom top", scrub: true } }));
        }

        /* G2. Mandala Lock — the residents' real mandala, cut into six rings that
               turn against each other and lock into the original when the
               section sits in the middle of the screen */
        const lock = $("[data-lock]");
        if (lock) {
          $$("img", lock).forEach((img, i) => {
            const dir = i % 2 ? 1 : -1, A = 16 + i * 9;
            gsap.fromTo(img, { rotation: dir * A }, { rotation: -dir * A, ease: "none",
              scrollTrigger: { trigger: lock, start: "top bottom", end: "bottom top", scrub: 0.8 } });
          });
        }

        /* (the inner-page hero has a CSS-only entrance: GSAP arrives after `load`,
            so a JS settle on the LCP image would pop after it was already shown) */

        /* Pins add space above everything that follows them, so triggers must be
           refreshed in page order (top → bottom), not in creation order. Without
           this the reveals below the pinned "day" fired ~3 screens too early. */
        ScrollTrigger.sort();

        return () => { splits.forEach((s) => s.revert()); cleanups.forEach((f) => f()); };
      }
    );
  }

  build();
  ScrollTrigger.refresh();
  if (d.fonts && d.fonts.ready) d.fonts.ready.then(() => ScrollTrigger.refresh());
  d.addEventListener("nh:motionchange", () => { mm.revert(); build(); ScrollTrigger.refresh(); });
})();
