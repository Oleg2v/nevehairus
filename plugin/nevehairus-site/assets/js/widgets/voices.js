/* =========================================================================
   nevehairus-site · widget `voices` (handle nh-w-voices, deps nh-core)
   YouTube facade: nothing is requested from YouTube before consent. The play
   button plays at once when media cookies are already allowed, otherwise it
   opens the consent question and focuses «כן», which aria-describedby ties to
   the question's wording on screen; "load" stores the consent (nh.consent), hides
   the cookie-settings panel (nh.consent.hideBanner, kept by nh-core.js for
   this call, research/07 §9) and swaps the facade for a youtube-nocookie
   iframe, which takes focus; "not now" closes the question and returns focus
   to play. Nothing animates.
   Port of the v3.2 prototype's site.js «Video facade» (SPEC §4.9), scoped to
   the [data-video] element; no page-wide logic.
   ========================================================================= */
(() => {
  "use strict";
  const nh = window.nh;
  if (!nh || typeof nh.widget !== "function") return;

  const readConsent = () => (nh.consent && typeof nh.consent.read === "function" ? nh.consent.read() : null);
  let asked = 0;   // ids of the consent questions: a page may hold more than one video

  nh.widget("voices", "[data-video]", (box) => {
    const id = box.dataset.video;
    const play = box.querySelector(".nh-video__play");
    const ask = box.querySelector(".nh-video__consent");
    const yes = ask ? ask.querySelector("[data-video-load]") : null;
    const no = ask ? ask.querySelector("[data-video-cancel]") : null;
    if (!id || !play) return;

    // focus goes straight to «כן»: it is described by the question it answers, in the wording on screen (full or
    // plain language — the hidden twin is never named), so a screen reader says what the visitor agrees to
    const describe = () => {
      if (!yes || !ask || ask.hidden) return;
      const q = Array.from(ask.children).find((el) => !el.matches(".nh-btn-row") && el.getClientRects().length > 0);
      if (!q) { yes.removeAttribute("aria-describedby"); return; }
      if (!q.id) q.id = "nh-video-ask-" + (++asked);
      yes.setAttribute("aria-describedby", q.id);
    };
    document.addEventListener("nh:displaychange", describe);

    // the iframe is never untitled: the widget's video title, the site's word for "video", or the play button's own name
    const title = () =>
      box.dataset.title || (nh.data && nh.data.i18n && nh.data.i18n.videoTitle) || play.textContent.trim();

    const load = () => {
      const f = document.createElement("iframe");
      f.src = "https://www.youtube-nocookie.com/embed/" + encodeURIComponent(id) + "?autoplay=1&rel=0";
      f.title = title();
      // "fullscreen" here and no legacy allowfullscreen: Chrome warns in the console when both are set (v3.2 prototype)
      f.allow = "accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen";
      f.referrerPolicy = "strict-origin-when-cross-origin";
      box.replaceChildren(f);
      f.focus();
    };

    play.addEventListener("click", () => {
      const c = readConsent();
      if (c && c.media) return load();
      if (!ask) return;
      ask.hidden = false;
      describe();
      (yes || no || play).focus();
    });
    if (yes) yes.addEventListener("click", () => {
      const c = readConsent() || {};
      nh.consent.save({ stats: !!c.stats, media: true });
      nh.consent.hideBanner();
      load();
    });
    if (no) no.addEventListener("click", () => {
      ask.hidden = true;
      play.focus();
    });
  });
})();
