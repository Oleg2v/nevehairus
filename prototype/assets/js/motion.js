/* =========================================================================
   נווה האירוס — motion layer
   GSAP 3 + ScrollTrigger, served locally (assets/vendor/gsap), no CDN.
   Principles: unhurried, human, never blocks reading or scrolling.
   - no scroll-jacking, no smooth-scroll libraries, no infinite loops;
   - every effect has a reduced-motion variant (OS setting OR on-site toggle);
   - matchMedia contexts revert cleanly when the preference changes.
   Spec for each effect: prototype/STORYBOARD.md
   ========================================================================= */
(() => {
  "use strict";
  const d = document;
  const html = d.documentElement;
  const release = () => html.classList.remove("nh-motion-pending");

  if (!window.gsap || !window.ScrollTrigger) { release(); return; }
  const { gsap, ScrollTrigger } = window;
  gsap.registerPlugin(ScrollTrigger);

  /* Tokens mirrored from tokens.css */
  const EASE = { out: "power3.out", inOut: "power2.inOut", none: "none" };
  const DUR = { m: 0.8, l: 1.2 };

  const userReduced = () => html.classList.contains("nh-reduce-motion");
  const $ = (s, root) => (root || d).querySelector(s);
  const $$ = (s, root) => gsap.utils.toArray(s, root);

  /* Split a sentence into word spans for the scrubbed "reading" effect.
     Screen readers get the intact sentence from a visually-hidden copy. */
  function splitWords(el) {
    if (el.dataset.split === "1") return $$(".w", el);
    const text = el.textContent.trim().replace(/\s+/g, " ");
    const sr = d.createElement("span");
    sr.className = "nh-sr-only";
    sr.textContent = text;
    const vis = d.createElement("span");
    vis.setAttribute("aria-hidden", "true");
    text.split(" ").forEach((w, i, arr) => {
      const s = d.createElement("span");
      s.className = "w";
      s.textContent = w;
      vis.append(s);
      if (i < arr.length - 1) vis.append(" ");
    });
    el.replaceChildren(sr, vis);
    el.dataset.split = "1";
    return $$(".w", el);
  }

  /* (Story image swap lives in site.js — IntersectionObserver, no GSAP needed.) */

  let mm;
  function build() {
    mm = gsap.matchMedia();
    mm.add(
      { motion: "(prefers-reduced-motion: no-preference)", desktop: "(min-width: 1024px)" },
      (ctx) => {
        const { motion, desktop } = ctx.conditions;
        if (!motion || userReduced()) { release(); return; }

        /* A. Generic reveal: opacity 0→1, y 28→0, 0.8s power3.out, stagger 0.09.
              Only elements still below the fold are hidden (no flash, no LCP cost). */
        const vh = window.innerHeight;
        const reveals = $$("[data-reveal]").filter((el) => el.getBoundingClientRect().top > vh * 0.9);
        if (reveals.length) {
          gsap.set(reveals, { opacity: 0, y: 28 });
          ScrollTrigger.batch(reveals, {
            start: "top 88%",
            once: true,
            onEnter: (batch) =>
              gsap.to(batch, { opacity: 1, y: 0, duration: DUR.m, ease: EASE.out, stagger: 0.09, overwrite: true }),
          });
        }
        release();

        /* B. Statement: words go from 55% to 100% opacity as you read (scrub).
              55% keeps unread words at 3.54:1 — still AA for large text (1.4.3). */
        $$("[data-words]").forEach((el) => {
          const words = splitWords(el);
          gsap.fromTo(words, { opacity: 0.55 }, {
            opacity: 1, ease: EASE.none, stagger: 0.1,
            scrollTrigger: { trigger: el, start: "top 82%", end: "bottom 52%", scrub: 0.6 },
          });
        });

        /* C. Highlighter: background-size 0→100% from the inline-start (right). */
        $$(".nh-hl[data-hl]").forEach((el) =>
          gsap.fromTo(el, { backgroundSize: "0% 100%" }, {
            backgroundSize: "100% 100%", duration: 1, ease: EASE.inOut,
            scrollTrigger: { trigger: el, start: "top 86%", once: true },
          })
        );

        /* D. Hero: stamp ring turns with scroll; photo drifts (desktop). */
        const hero = $(".nh-hero");
        if (hero) {
          gsap.to(".nh-stamp__ring", {
            rotation: 160, ease: EASE.none,
            scrollTrigger: { trigger: hero, start: "top top", end: "bottom top", scrub: 0.6 },
          });
          if (desktop) {
            gsap.to(".nh-hero__media .nh-parallax", {
              yPercent: 6, ease: EASE.none,
              scrollTrigger: { trigger: hero, start: "top top", end: "bottom top", scrub: true },
            });
          }
        }

        /* E. Programme cards: inner image parallax ±5% (desktop only). */
        if (desktop) {
          $$(".nh-card .nh-parallax").forEach((p) =>
            gsap.fromTo(p, { yPercent: -5 }, {
              yPercent: 5, ease: EASE.none,
              scrollTrigger: { trigger: p.closest(".nh-card"), start: "top bottom", end: "bottom top", scrub: true },
            })
          );
        }

        /* F. Story: progress line grows with the chapters (scrub). */
        const list = $(".nh-story__list");
        if (list) {
          gsap.fromTo(".nh-story__progress", { scaleY: 0 }, {
            scaleY: 1, ease: EASE.none,
            scrollTrigger: { trigger: list, start: "top 62%", end: "bottom 62%", scrub: true },
          });
        }

        /* G. Made here: mandala turns gently with scroll; tiles assemble once. */
        const made = $(".nh-made");
        if (made) {
          gsap.fromTo(".nh-made__mandala img", { rotation: -10 }, {
            rotation: 14, ease: EASE.none,
            scrollTrigger: { trigger: made, start: "top bottom", end: "bottom top", scrub: true },
          });
          gsap.from(".nh-tiles span", {
            scale: 0, opacity: 0, duration: 0.5, ease: "power2.out",
            stagger: { each: 0.012, from: "random" },
            scrollTrigger: { trigger: ".nh-tiles", start: "top 88%", once: true },
          });
        }

        /* H. Page-hero image on inner pages: slow settle (scale 1.08→1). */
        $$(".nh-page-hero .nh-arch img").forEach((img) =>
          gsap.fromTo(img, { scale: 1.08 }, { scale: 1, duration: 1.6, ease: EASE.out })
        );
      }
    );
  }

  build();
  if (d.fonts && d.fonts.ready) d.fonts.ready.then(() => ScrollTrigger.refresh());
  window.addEventListener("load", () => ScrollTrigger.refresh());
  d.addEventListener("nh:motionchange", () => {
    mm.revert();
    build();
    ScrollTrigger.refresh();
  });
})();
