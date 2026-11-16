/* =========================================================================
   nevehairus-site · widget `gallery` (script handle nh-w-gallery)
   Accessible lightbox: port of the prototype's site.js L282–337 (section 7).
   - one native <dialog id="nh-lightbox"> (printed once in the footer) serves every gallery
     on the page; each [data-lightbox] list keeps its own list of images;
   - keyboard: arrows (RTL-aware), Home/End, Esc (native dialog); swipe; focus returns to
     the button that opened it; the live region says "תמונה n מתוך m" and the alt text;
   - the count text comes from the dialog's data-count ({n}, {total});
   - the caption is data-caption-plain in plain-language mode when the button has it,
     else data-caption.
   No motion here: nothing to check against nh.motionOK().
   ========================================================================= */
(() => {
  "use strict";
  const nh = window.nh;
  if (!nh || typeof nh.widget !== "function") return;

  const d = document;
  const html = d.documentElement;
  const isRTL = () => (html.getAttribute("dir") || "rtl") === "rtl";

  // the dialog and its parts, set up once for the page (the first gallery that needs it)
  let lb = null;
  let img = null;
  let cap = null;
  let count = null;
  let live = null;
  let items = [];
  let idx = 0;
  let opener = null;

  const caption = (it) =>
    (html.classList.contains("nh-plainmode") && it.captionPlain !== null ? it.captionPlain : it.caption) || "";
  const countText = () =>
    (lb.dataset.count || "{n} / {total}")
      .replace(/\{n\}/g, String(idx + 1))
      .replace(/\{total\}/g, String(items.length));

  const render = () => {
    const it = items[idx];
    img.removeAttribute("srcset");
    img.src = it.src;
    if (it.srcset) { img.srcset = it.srcset; img.sizes = "100vw"; }
    img.alt = it.alt || "";
    cap.textContent = caption(it);
    count.textContent = countText();
    if (live) live.textContent = count.textContent + ". " + (it.alt || "");
  };
  const open = (list, i, from) => {
    items = list; idx = i; opener = from;
    render();
    lb.showModal();
    lb.querySelector("[data-lb-close]").focus();
  };
  const move = (n) => { idx = (idx + n + items.length) % items.length; render(); };

  const setup = () => {
    if (lb) return true;
    const el = d.getElementById("nh-lightbox");
    if (!el || typeof el.showModal !== "function") return false;
    const parts = {
      img: el.querySelector(".nh-lightbox__stage img"),
      cap: el.querySelector(".nh-lightbox__caption"),
      count: el.querySelector(".nh-lightbox__count"),
      close: el.querySelector("[data-lb-close]"),
      prev: el.querySelector("[data-lb-prev]"),
      next: el.querySelector("[data-lb-next]"),
    };
    if (!parts.img || !parts.cap || !parts.count || !parts.close || !parts.prev || !parts.next) return false;
    lb = el;
    img = parts.img;
    cap = parts.cap;
    count = parts.count;
    live = el.querySelector("[data-lb-live]");

    parts.prev.addEventListener("click", () => move(-1));
    parts.next.addEventListener("click", () => move(1));
    parts.close.addEventListener("click", () => lb.close());
    lb.addEventListener("close", () => opener && opener.focus());
    lb.addEventListener("keydown", (e) => {
      if (e.key === "ArrowLeft") { e.preventDefault(); move(isRTL() ? 1 : -1); }
      if (e.key === "ArrowRight") { e.preventDefault(); move(isRTL() ? -1 : 1); }
      if (e.key === "Home") { e.preventDefault(); idx = 0; render(); }
      if (e.key === "End") { e.preventDefault(); idx = items.length - 1; render(); }
    });
    let x0 = null;
    lb.addEventListener("pointerdown", (e) => { x0 = e.clientX; });
    lb.addEventListener("pointerup", (e) => {
      if (x0 === null) return;
      const dx = e.clientX - x0;
      x0 = null;
      if (Math.abs(dx) > 50) move(dx > 0 ? (isRTL() ? 1 : -1) : (isRTL() ? -1 : 1));
    });
    return true;
  };

  nh.widget("gallery", "[data-lightbox]", (el) => {
    if (!setup()) return;                     // no dialog (or no <dialog> support): the prints stay plain buttons
    const btns = Array.from(el.querySelectorAll("button[data-full]"));
    const list = btns.map((b) => ({
      src: b.dataset.full,
      srcset: b.dataset.srcset,
      alt: (b.querySelector("img") || {}).alt,
      caption: b.dataset.caption,
      captionPlain: b.hasAttribute("data-caption-plain") ? b.dataset.captionPlain : null,
    }));
    btns.forEach((b, i) => b.addEventListener("click", () => open(list, i, b)));
  });
})();
