/* =========================================================================
   נווה האירוס — prototype UI behaviours (vanilla JS, no dependencies)
   v3.2 (research/07 §11.1): nothing here moves anything; no scroll listeners, no animation loops.
   1. phone menu (<dialog>, opened only by «תפריט»)
   2. the visit button and in-page anchors: reached at once, focus on the target's heading
   3. cookie settings: a panel in the flow after the footer, never shown by itself
      (prototype: localStorage; production: cookie + server log)
   4. YouTube facade (nothing loads from YouTube before consent)
   5. form validation with Hebrew messages, error summary, honeypot
   6. the year in the footer
   (v3.2 removed the v1 story and colour handlers, the day track and the gallery lightbox.)
   Everything degrades gracefully: without JS, content is readable.
   ========================================================================= */
(() => {
  "use strict";
  const d = document;
  const store = {
    get(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* private mode */ } },
  };

  /* ---------- 1. Phone menu (<dialog>, research/07 §5.4) ------------------
     Opens only on «תפריט», at once. Esc and «סגירה» close it and focus returns to «תפריט». A link closes it
     too, but then focus goes to the heading of the link's target (2), not back to «תפריט»: the link sets
     menu.dataset.nav, and the close handler sees it and clears it. */
  const menu = d.getElementById("nh-menu");
  const menuBtn = d.querySelector("[data-menu-open]");
  const plainClick = (e) => e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey;
  if (menu && menuBtn && typeof menu.showModal === "function") {
    menuBtn.addEventListener("click", () => {
      menu.showModal();
      menuBtn.setAttribute("aria-expanded", "true");
    });
    menu.addEventListener("close", () => {
      menuBtn.setAttribute("aria-expanded", "false");
      if (menu.dataset.nav) { delete menu.dataset.nav; return; }
      menuBtn.focus();
    });
    menu.querySelectorAll("[data-menu-close]").forEach((el) => el.addEventListener("click", () => menu.close()));
    menu.querySelectorAll("nav a, a[data-visit-cta]").forEach((el) => el.addEventListener("click", (e) => {
      if (!plainClick(e)) return;
      menu.dataset.nav = "1";
      menu.close();
    }));
  }

  /* ---------- 2. The visit button and in-page anchors (research/07 §5.5) ------
     Every [data-visit-cta] (masthead, menu) leads to this page's door when it has one, else to the home page's
     (index.html#door in the markup). An anchor is reached at once (no smooth scrolling, no preventDefault);
     then focus goes to the heading of the target, so the keyboard and a screen reader go on from where the
     eyes are. "This page" is decided by the address, so index.html#homes counts on the home page. */
  const door = d.querySelector(".v3-door[id]");
  if (door) d.querySelectorAll("a[data-visit-cta]").forEach((a) => a.setAttribute("href", "#" + door.id));
  const headingOf = (t) => {
    if (/^H[1-3]$/.test(t.tagName)) return t;
    const ids = (t.getAttribute("aria-labelledby") || "").split(/\s+/).filter(Boolean);
    return (ids.length && d.getElementById(ids[0])) || t.querySelector("h2, h3");
  };
  const focusTarget = (hash) => {
    let id = hash.slice(1);
    try { id = decodeURIComponent(id); } catch (e) { /* keep it as written */ }
    if (!id || id === "main" || id === "nh-cookies") return;     // the skip link's main takes focus itself; the panel opens in 3
    const t = d.getElementById(id);
    const h = t && headingOf(t);
    if (!h) return;
    // after the browser's own fragment navigation (which resets focus), on the next frame
    requestAnimationFrame(() => {
      if (!h.hasAttribute("tabindex")) h.setAttribute("tabindex", "-1");
      h.focus({ preventScroll: true });
    });
  };
  const samePage = (a) => a.origin === location.origin && a.pathname === location.pathname &&
    a.search === location.search && a.hash.length > 1;
  d.addEventListener("click", (e) => {
    if (e.defaultPrevented || !plainClick(e)) return;
    const a = e.target.closest("a[href]");
    if (!a || !samePage(a) || a.classList.contains("nh-skip") || a.hash === "#nh-cookies") return;
    // a link in the menu: after the dialog's close event, or the return to «תפריט» would take the focus back
    if (menu && menu.contains(a)) menu.addEventListener("close", () => focusTarget(a.hash), { once: true });
    else focusTarget(a.hash);
  });
  if (location.hash.length > 1) focusTarget(location.hash);     // arriving from another page, e.g. index.html#homes

  /* ---------- 3. Cookie settings (research/07 §9) ----------------------------
     Never opens by itself: there are no statistics and no advertising pixels, and the one third party
     (YouTube) asks inside the video. «הגדרות עוגיות» (or any link to #nh-cookies) opens the panel in the flow
     right after the footer, at once, with focus on its heading; «שמירת הבחירה», «סגירה» and Esc hide it and
     return focus to the link. Display choices and the consent record are necessary storage. */
  const CONSENT_KEY = "nh-consent-v1";
  const CONSENT_V = 1;                                            // a record of an older version counts as none
  const banner = d.getElementById("nh-cookies");
  const readConsent = () => {
    let rec = null;
    try { rec = JSON.parse(store.get(CONSENT_KEY) || "null"); } catch (e) { return null; }
    return rec && (rec.v || 0) >= CONSENT_V ? rec : null;
  };
  const saveConsent = (c) => {
    const rec = { necessary: true, stats: !!c.stats, media: !!c.media, date: new Date().toISOString(), v: CONSENT_V };
    store.set(CONSENT_KEY, JSON.stringify(rec));
    d.dispatchEvent(new CustomEvent("nh:consent", { detail: rec }));
  };
  let opener = null;
  // the name stays (research/07 §9): the video's «כן, לטעון את הסרטון» calls it before it loads the film
  const hideBanner = (focusBack) => {
    if (!banner || banner.hidden) return;
    banner.hidden = true;
    if (focusBack === true && opener && opener.isConnected) opener.focus();
    opener = null;
  };
  const openSettings = (from) => {
    if (!banner) return;
    opener = from || null;
    const media = banner.querySelector("#nh-c-media");
    if (media) media.checked = !!(readConsent() || {}).media;
    banner.hidden = false;
    banner.scrollIntoView({ block: "start" });                    // at once: no smooth scrolling anywhere
    const h = banner.querySelector("h2");
    if (h) h.focus({ preventScroll: true });
  };
  if (banner) {
    d.addEventListener("click", (e) => {
      const link = e.target.closest('[data-cookie-settings], a[href="#nh-cookies"]');
      if (!link) return;
      e.preventDefault();
      openSettings(link);
    });
    banner.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-consent]");
      if (!btn) return;
      if (btn.dataset.consent === "save") {
        const media = banner.querySelector("#nh-c-media");
        saveConsent({ stats: false, media: !!(media && media.checked) });
      }
      hideBanner(true);
    });
    banner.addEventListener("keydown", (e) => {
      if (e.key === "Escape") { e.preventDefault(); hideBanner(true); }
    });
  }

  /* ---------- 4. Video facade (privacy: no YouTube request before consent) */
  let asked = 0;   // ids of the consent questions' wordings (full and plain each get their own)
  d.querySelectorAll("[data-video]").forEach((box) => {
    const id = box.dataset.video;
    const play = box.querySelector(".nh-video__play");
    const ask = box.querySelector(".nh-video__consent");
    const yes = ask && ask.querySelector("[data-video-load]");
    // focus goes straight to «כן»: it is described by the question it answers, in the wording on screen (full or
    // plain language — the hidden twin is never named), so a screen reader says what the visitor agrees to
    const describe = () => {
      if (!yes || ask.hidden) return;
      const q = Array.from(ask.children).find((el) => !el.matches(".nh-btn-row") && el.getClientRects().length > 0);
      if (!q) { yes.removeAttribute("aria-describedby"); return; }
      if (!q.id) q.id = "nh-video-ask-" + (++asked);
      yes.setAttribute("aria-describedby", q.id);
    };
    d.addEventListener("nh:displaychange", describe);
    const load = () => {
      const f = d.createElement("iframe");
      f.src = "https://www.youtube-nocookie.com/embed/" + encodeURIComponent(id) + "?autoplay=1&rel=0";
      f.title = box.dataset.title || "סרטון";
      f.allow = "accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen";   // "fullscreen" here: no legacy allowfullscreen (Chrome warns when both are set)
      f.referrerPolicy = "strict-origin-when-cross-origin";
      box.replaceChildren(f);
      f.focus();
    };
    if (!play) return;
    play.addEventListener("click", () => {
      const c = readConsent();
      if (c && c.media) return load();
      ask.hidden = false;
      describe();
      yes.focus();
    });
    ask && ask.querySelector("[data-video-load]").addEventListener("click", () => {
      const c = readConsent() || {};
      saveConsent({ stats: !!c.stats, media: true });
      hideBanner();
      load();
    });
    ask && ask.querySelector("[data-video-cancel]").addEventListener("click", () => {
      ask.hidden = true;
      play.focus();
    });
  });

  /* ---------- 5. Forms ---------------------------------------------------- */
  const ICON_ERR =
    '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v6M12 16.5v.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
  const MSG = {
    required: "צריך למלא את השדה הזה.",
    email: "כתובת הדוא״ל לא תקינה. למשל: name@example.com",
    tel: "מספר הטלפון לא תקין: צריך 9 או 10 ספרות, ובהתחלה 0.",
    consent: "צריך לסמן את התיבה כדי לשלוח.",
    fileSize: "הקובץ גדול מדי. הגודל המרבי הוא 5MB.",
    fileType: "ניתן לצרף קובץ PDF, DOC או DOCX בלבד.",
  };
  const labelText = (form, el) => {
    if (el.dataset.errorLabel) return el.dataset.errorLabel;       // e.g. the consent box, whose label is a whole sentence
    const l = form.querySelector('label[for="' + el.id + '"]');
    return l ? l.textContent.replace("*", "").replace("(שדה חובה)", "").trim() : el.name;
  };
  const describe = (el, id, on) => {
    const ids = (el.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean).filter((x) => x !== id);
    if (on) ids.push(id);
    ids.length ? el.setAttribute("aria-describedby", ids.join(" ")) : el.removeAttribute("aria-describedby");
  };
  const check = (el) => {
    const v = (el.value || "").trim();
    if (el.type === "checkbox") return el.required && !el.checked ? MSG.consent : "";
    if (el.type === "file") {
      const f = el.files && el.files[0];
      if (!f) return el.required ? MSG.required : "";
      if (!/\.(pdf|docx?)$/i.test(f.name)) return MSG.fileType;
      if (f.size > 5 * 1024 * 1024) return MSG.fileSize;
      return "";
    }
    if (el.required && !v) return MSG.required;
    if (v && el.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return MSG.email;
    if (v && el.type === "tel") {
      const digits = v.replace(/\D/g, "");
      if (!/^0\d{8,9}$/.test(digits)) return MSG.tel;
    }
    return "";
  };
  const setError = (el, msg) => {
    const id = el.id + "-err";
    let e = d.getElementById(id);
    if (msg) {
      if (!e) {
        e = d.createElement("p");
        e.className = "nh-error";
        e.id = id;
        e.innerHTML = ICON_ERR + "<span></span>";
        (el.closest(".nh-field") || el.closest(".nh-check") || el.parentNode).appendChild(e);
      }
      e.querySelector("span").textContent = msg;
      el.setAttribute("aria-invalid", "true");
      describe(el, id, true);
    } else {
      if (e) e.remove();
      el.removeAttribute("aria-invalid");
      describe(el, id, false);
    }
  };
  d.querySelectorAll("form[data-validate]").forEach((form) => {
    form.setAttribute("novalidate", "");
    const summary = form.querySelector("[data-error-summary]");
    const success = form.parentNode.querySelector("[data-success]");
    const fields = () =>
      Array.from(form.querySelectorAll("input, select, textarea")).filter(
        (el) => !el.closest(".nh-hp") && el.type !== "hidden" && el.type !== "submit"
      );
    /* Errors appear only on submit. While the person fixes a field, its error is
       cleared as soon as the value becomes valid (input/change) — never on blur,
       so the layout does not jump under the pointer (a click on the next field
       or on the consent checkbox always lands). */
    const clearIfValid = (e) => {
      const el = e.target;
      if (!el.matches("input, select, textarea") || el.closest(".nh-hp")) return;
      if (el.getAttribute("aria-invalid") === "true" && !check(el)) setError(el, "");
    };
    form.addEventListener("input", clearIfValid);
    form.addEventListener("change", clearIfValid);
    form.addEventListener("submit", (ev) => {
      ev.preventDefault();
      form.dataset.touched = "1";
      const hp = form.querySelector(".nh-hp input");
      if (hp && hp.value) return; // bot: silently ignore
      const errs = [];
      fields().forEach((el) => {
        const m = check(el);
        setError(el, m);
        if (m) errs.push({ el, m });
      });
      if (errs.length) {
        if (success) success.hidden = true;
        summary.hidden = false;
        summary.querySelector("h3").textContent =
          errs.length === 1 ? "נמצאה שגיאה אחת בטופס:" : "נמצאו " + errs.length + " שגיאות בטופס:";
        const ul = summary.querySelector("ul");
        ul.replaceChildren(
          ...errs.map(({ el, m }) => {
            const li = d.createElement("li");
            const a = d.createElement("a");
            a.href = "#" + el.id;
            a.textContent = labelText(form, el) + "\u00A0— " + m;   // the dash stays with the label
            a.addEventListener("click", (e) => { e.preventDefault(); el.focus(); });
            li.append(a);
            return li;
          })
        );
        summary.focus();
        return;
      }
      summary.hidden = true;
      // Prototype only: nothing is sent. In WordPress this posts to admin-post.php
      // (nonce + honeypot + time trap + rate limit) and wp_mail() delivers it.
      form.reset();
      delete form.dataset.touched;
      if (success) { success.hidden = false; success.focus(); }
    });
  });

  /* ---------- 6. Small things ------------------------------------------- */
  d.querySelectorAll("[data-year]").forEach((el) => { el.textContent = String(new Date().getFullYear()); });
})();
