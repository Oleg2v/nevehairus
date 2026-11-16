/* =========================================================================
   nevehairus-site · widget `voices` (handle nh-w-voices, deps nh-core)
   YouTube facade: nothing is requested from YouTube before consent. The play
   button plays at once when media cookies are already allowed, otherwise it
   opens the consent question; "load" stores the consent (nh.consent), hides
   the cookie banner and swaps the facade for a youtube-nocookie iframe, which
   takes focus; "not now" closes the question and returns focus to play.
   Port of prototype/assets/js/site.js L218–250 (SPEC §4.9), scoped to the
   [data-video] element; no page-wide logic.
   ========================================================================= */
(() => {
  "use strict";
  const nh = window.nh;
  if (!nh || typeof nh.widget !== "function") return;

  const readConsent = () => (nh.consent && typeof nh.consent.read === "function" ? nh.consent.read() : null);

  nh.widget("voices", "[data-video]", (box) => {
    const id = box.dataset.video;
    const play = box.querySelector(".nh-video__play");
    const ask = box.querySelector(".nh-video__consent");
    const yes = ask ? ask.querySelector("[data-video-load]") : null;
    const no = ask ? ask.querySelector("[data-video-cancel]") : null;
    if (!id || !play) return;

    // the iframe is never untitled: the widget's video title, the site's word for "video", or the play button's own name
    const title = () =>
      box.dataset.title || (nh.data && nh.data.i18n && nh.data.i18n.videoTitle) || play.textContent.trim();

    const load = () => {
      const f = document.createElement("iframe");
      f.src = "https://www.youtube-nocookie.com/embed/" + encodeURIComponent(id) + "?autoplay=1&rel=0";
      f.title = title();
      f.allow = "accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen";
      f.allowFullscreen = true;
      f.referrerPolicy = "strict-origin-when-cross-origin";
      box.replaceChildren(f);
      f.focus();
    };

    play.addEventListener("click", () => {
      const c = readConsent();
      if (c && c.media) return load();
      if (!ask) return;
      ask.hidden = false;
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
