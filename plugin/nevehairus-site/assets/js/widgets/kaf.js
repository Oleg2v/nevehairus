/* =========================================================================
   nh-kaf — כ׳ · the palm holds the chosen work (SPEC §4.6; prototype v3.js L188–224).
   Every work button carries its own image set (data-src, data-srcset, data-avif) and its
   name (data-name); choosing a work puts it in the palm and names it in «בכף היד: …».
   Changes from the prototype: no URL building and no #nh-works JSON; the caption takes
   data-name (textContent would include the plain-language twin); aria-pressed and is-on
   are updated inside this widget only. The crossfade runs only when nh.motionOK().
   ========================================================================= */
(() => {
  "use strict";
  const nh = window.nh;
  if (!nh || typeof nh.widget !== "function") return;

  nh.widget("kaf", ".v3-kaf", (el) => {
    const works = Array.from(el.querySelectorAll(".v3-work[data-work]"));
    if (!works.length) return;
    const palm = el.querySelector(".v3-kaf__palm");
    const img = palm ? palm.querySelector("img") : null;
    const name = el.querySelector("[data-work-name]");                     // «בכף היד: …»

    // the chosen work's image set replaces the palm's; its AVIF source is added or dropped to match
    const swap = (b) => {
      const pic = img.parentElement;
      const inPicture = !!pic && pic.tagName === "PICTURE";
      let source = inPicture ? pic.querySelector("source[type='image/avif']") : null;
      const avif = b.dataset.avif || "";
      if (avif && inPicture) {
        if (!source) {
          source = document.createElement("source");
          source.type = "image/avif";
          pic.insertBefore(source, img);
        }
        const sizes = img.getAttribute("sizes");
        if (sizes) source.setAttribute("sizes", sizes);
        source.srcset = avif;
      } else if (source) {
        source.remove();
      }
      if (b.dataset.srcset) img.srcset = b.dataset.srcset;
      else img.removeAttribute("srcset");
      img.src = b.dataset.src;
    };

    const put = (b) => {
      if (!img || !b.dataset.src) return;                                  // a work without a photo keeps the palm as it is
      if (!nh.motionOK()) { swap(b); return; }
      // crossfade: the work in the palm stays as a backdrop while the chosen one fades in over it (never an empty letter)
      const box = img.closest(".v3-room__photo");
      if (box && img.currentSrc) {
        box.style.backgroundImage = `url("${img.currentSrc}")`;
        box.style.backgroundSize = "cover";
        box.style.backgroundPosition = getComputedStyle(img).objectPosition;
      }
      img.style.transition = "none"; img.style.opacity = "0";
      requestAnimationFrame(() => {
        const show = () => { img.style.transition = ""; img.style.opacity = ""; };
        img.addEventListener("load", show, { once: true });
        img.addEventListener("error", show, { once: true });
        swap(b);
        if (img.complete) show();
      });
    };

    works.forEach((b) => b.addEventListener("click", () => {
      if (b.getAttribute("aria-pressed") === "true") return;                // already in the palm
      works.forEach((o) => {
        const on = o === b;
        o.setAttribute("aria-pressed", String(on));
        o.classList.toggle("is-on", on);
      });
      if (name) name.textContent = b.dataset.name || b.textContent.trim();
      put(b);
    }));
  });
})();
