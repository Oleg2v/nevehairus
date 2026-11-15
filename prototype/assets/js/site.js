/* =========================================================================
   נווה האירוס — prototype UI behaviours (vanilla JS, no dependencies)
   - motion preference toggle (in addition to the OS setting)
   - sticky header state, mobile menu (<dialog>)
   - cookie consent (prototype: localStorage; production: cookie + server log)
   - YouTube facade (nothing loads from YouTube before consent)
   - horizontal "day" track controls (RTL-aware)
   - accessible lightbox (<dialog>, keyboard, swipe, focus return)
   - form validation with Hebrew messages, error summary, honeypot
   Everything degrades gracefully: without JS, content is readable.
   ========================================================================= */
(() => {
  "use strict";
  const d = document;
  const html = d.documentElement;
  const isRTL = () => (html.getAttribute("dir") || "rtl") === "rtl";
  const store = {
    get(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* private mode */ } },
  };
  const prefersReduced = () =>
    html.classList.contains("nh-reduce-motion") ||
    (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);

  /* ---------- 1. Motion preference ------------------------------------- */
  const motionBtns = d.querySelectorAll("[data-motion-toggle]");
  const syncMotionBtns = () =>
    motionBtns.forEach((b) => b.setAttribute("aria-pressed", String(html.classList.contains("nh-reduce-motion"))));
  motionBtns.forEach((b) =>
    b.addEventListener("click", () => {
      const reduce = !html.classList.contains("nh-reduce-motion");
      html.classList.toggle("nh-reduce-motion", reduce);
      store.set("nh-motion", reduce ? "reduce" : "full");
      syncMotionBtns();
      // motion layer was never loaded for this visitor? load it now (no-op if loaded)
      if (!reduce && !window.gsap && typeof window.nhLoadMotion === "function") window.nhLoadMotion();
      d.dispatchEvent(new CustomEvent("nh:motionchange", { detail: { reduce } }));
    })
  );
  syncMotionBtns();

  /* ---------- 1b. Story: swap the sticky image when a chapter crosses the
     middle of the viewport (IntersectionObserver — works with and without
     GSAP; the cross-fade is CSS and is removed under reduced motion). ---- */
  const storyItems = d.querySelectorAll(".nh-story__item");
  const storyFigs = d.querySelectorAll(".nh-story__stack figure");
  if (storyItems.length && storyFigs.length && "IntersectionObserver" in window) {
    const setActive = (i) => storyFigs.forEach((f, k) => f.classList.toggle("is-active", k === i));
    setActive(0);
    const io = new IntersectionObserver(
      (entries) => entries.forEach((e) => {
        if (e.isIntersecting) setActive(Array.prototype.indexOf.call(storyItems, e.target));
      }),
      { rootMargin: "-50% 0px -50% 0px" }
    );
    storyItems.forEach((it) => io.observe(it));
  }

  /* ---------- 2. Header state ------------------------------------------- */
  const header = d.querySelector(".nh-header");
  if (header) {
    let ticking = false;
    const onScroll = () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        header.classList.toggle("is-scrolled", window.scrollY > 24);
        ticking = false;
      });
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  /* ---------- 3. Mobile menu --------------------------------------------- */
  const menu = d.getElementById("nh-menu");
  const menuBtn = d.querySelector("[data-menu-open]");
  if (menu && menuBtn && typeof menu.showModal === "function") {
    menuBtn.addEventListener("click", () => {
      menu.showModal();
      menuBtn.setAttribute("aria-expanded", "true");
    });
    menu.addEventListener("close", () => {
      menuBtn.setAttribute("aria-expanded", "false");
      menuBtn.focus();
    });
    menu.querySelectorAll("[data-menu-close], nav a").forEach((el) =>
      el.addEventListener("click", () => menu.close())
    );
  }

  /* ---------- 4. Cookie consent ------------------------------------------- */
  const CONSENT_KEY = "nh-consent-v1";
  const banner = d.getElementById("nh-cookies");
  const readConsent = () => {
    try { return JSON.parse(store.get(CONSENT_KEY) || "null"); } catch (e) { return null; }
  };
  const saveConsent = (c) => {
    const rec = { necessary: true, stats: !!c.stats, media: !!c.media, date: new Date().toISOString(), v: 1 };
    store.set(CONSENT_KEY, JSON.stringify(rec));
    d.dispatchEvent(new CustomEvent("nh:consent", { detail: rec }));
  };
  const showBanner = () => { if (banner) { banner.hidden = false; html.classList.add("nh-has-cookies"); } };
  const hideBanner = () => { if (banner) { banner.hidden = true; html.classList.remove("nh-has-cookies"); } };
  if (banner) {
    if (!readConsent()) showBanner();
    const prefs = banner.querySelector(".nh-cookies__prefs");
    banner.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-consent]");
      if (!btn) return;
      const act = btn.dataset.consent;
      if (act === "settings") {
        prefs.hidden = !prefs.hidden;
        btn.setAttribute("aria-expanded", String(!prefs.hidden));
        if (!prefs.hidden) prefs.querySelector("input:not([disabled])").focus();
        return;
      }
      const c = { stats: false, media: false };
      if (act === "all") { c.stats = true; c.media = true; }
      if (act === "save") {
        c.stats = banner.querySelector("#nh-c-stats").checked;
        c.media = banner.querySelector("#nh-c-media").checked;
      }
      saveConsent(c);
      hideBanner();
      const main = d.getElementById("main");
      if (main) main.focus({ preventScroll: true });
    });
    d.querySelectorAll("[data-cookie-settings]").forEach((b) =>
      b.addEventListener("click", () => {
        const c = readConsent() || {};
        banner.querySelector("#nh-c-stats").checked = !!c.stats;
        banner.querySelector("#nh-c-media").checked = !!c.media;
        prefs.hidden = false;
        showBanner();
        banner.querySelector("h2").focus();
      })
    );
  }

  /* ---------- 5. Video facade (privacy: no YouTube request before consent) */
  d.querySelectorAll("[data-video]").forEach((box) => {
    const id = box.dataset.video;
    const play = box.querySelector(".nh-video__play");
    const ask = box.querySelector(".nh-video__consent");
    const load = () => {
      const f = d.createElement("iframe");
      f.src = "https://www.youtube-nocookie.com/embed/" + encodeURIComponent(id) + "?autoplay=1&rel=0";
      f.title = box.dataset.title || "סרטון";
      f.allow = "accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen";
      f.allowFullscreen = true;
      f.referrerPolicy = "strict-origin-when-cross-origin";
      box.replaceChildren(f);
      f.focus();
    };
    if (!play) return;
    play.addEventListener("click", () => {
      const c = readConsent();
      if (c && c.media) return load();
      ask.hidden = false;
      ask.querySelector("[data-video-load]").focus();
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

  /* ---------- 6. Horizontal track (native scroll + buttons) --------------- */
  d.querySelectorAll("[data-track]").forEach((wrap) => {
    const track = wrap.querySelector(".nh-day__track");
    const prev = wrap.querySelector("[data-track-prev]");
    const next = wrap.querySelector("[data-track-next]");
    const bar = wrap.querySelector(".nh-day__progress i");
    if (!track) return;
    const gap = () => parseFloat(getComputedStyle(track).columnGap) || 24;
    const step = () => {
      const card = track.querySelector(".nh-day__card");
      return card ? card.getBoundingClientRect().width + gap() : 320;
    };
    const max = () => Math.max(0, track.scrollWidth - track.clientWidth);
    const pos = () => Math.abs(track.scrollLeft); // RTL scrollLeft runs 0 → -max
    const update = () => {
      const m = max();
      const p = m > 0 ? pos() / m : 1;
      if (bar) bar.style.transform = "scaleX(" + Math.max(0.12, Math.min(1, p)).toFixed(3) + ")";
      if (prev) prev.disabled = pos() <= 2;
      if (next) next.disabled = pos() >= m - 2;
    };
    const go = (dir) =>
      track.scrollBy({ left: (isRTL() ? -1 : 1) * dir * step(), behavior: prefersReduced() ? "auto" : "smooth" });
    next && next.addEventListener("click", () => go(1));
    prev && prev.addEventListener("click", () => go(-1));
    track.addEventListener("scroll", () => requestAnimationFrame(update), { passive: true });
    window.addEventListener("resize", update);
    update();
  });

  /* ---------- 7. Lightbox ------------------------------------------------- */
  const lb = d.getElementById("nh-lightbox");
  if (lb && typeof lb.showModal === "function") {
    const img = lb.querySelector(".nh-lightbox__stage img");
    const cap = lb.querySelector(".nh-lightbox__caption");
    const count = lb.querySelector(".nh-lightbox__count");
    const live = lb.querySelector("[data-lb-live]");
    let items = [];
    let idx = 0;
    let opener = null;
    const render = () => {
      const it = items[idx];
      img.removeAttribute("srcset");
      img.src = it.src;
      if (it.srcset) { img.srcset = it.srcset; img.sizes = "100vw"; }
      img.alt = it.alt || "";
      cap.textContent = it.caption || "";
      count.textContent = "תמונה " + (idx + 1) + " מתוך " + items.length;
      if (live) live.textContent = count.textContent + ". " + (it.alt || "");
    };
    const open = (list, i, from) => {
      items = list; idx = i; opener = from;
      render();
      lb.showModal();
      lb.querySelector("[data-lb-close]").focus();
    };
    const move = (n) => { idx = (idx + n + items.length) % items.length; render(); };
    lb.querySelector("[data-lb-prev]").addEventListener("click", () => move(-1));
    lb.querySelector("[data-lb-next]").addEventListener("click", () => move(1));
    lb.querySelector("[data-lb-close]").addEventListener("click", () => lb.close());
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
    d.querySelectorAll("[data-lightbox]").forEach((g) => {
      const btns = Array.from(g.querySelectorAll("button[data-full]"));
      const list = btns.map((b) => ({
        src: b.dataset.full,
        srcset: b.dataset.srcset,
        alt: (b.querySelector("img") || {}).alt,
        caption: b.dataset.caption,
      }));
      btns.forEach((b, i) => b.addEventListener("click", () => open(list, i, b)));
    });
  }

  /* ---------- 8. Forms ---------------------------------------------------- */
  const ICON_ERR =
    '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v6M12 16.5v.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
  const MSG = {
    required: "זהו שדה חובה.",
    email: "נא להזין כתובת דוא״ל תקינה, למשל name@example.com.",
    tel: "נא להזין מספר טלפון תקין: 9 או 10 ספרות, מתחיל ב־0.",
    consent: "כדי לשלוח את הטופס יש לאשר את מדיניות הפרטיות.",
    fileSize: "הקובץ גדול מדי. הגודל המרבי הוא 5MB.",
    fileType: "ניתן לצרף קובץ PDF, DOC או DOCX בלבד.",
  };
  const labelText = (form, el) => {
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
            a.textContent = labelText(form, el) + " — " + m;
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

  /* ---------- 9. Small things ------------------------------------------- */
  d.querySelectorAll("[data-year]").forEach((el) => { el.textContent = String(new Date().getFullYear()); });
})();
