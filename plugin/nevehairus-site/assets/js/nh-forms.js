/* =========================================================================
   נווה האירוס — nh-forms.js (plugin nevehairus-site, core). No libraries.
   Validation with the prototype's messages (from the settings), the error summary,
   sending with fetch to admin-post.php (JSON answer), a fresh nonce/time token on the
   first focus (pages may come from a full-page cache), and focus on the state the
   server rendered after a submission without JavaScript (SPEC §6.4).
   Ported from prototype/assets/js/site.js section 5 «Forms» (v3.2 L174–290; the same code as
   v3.1 L339–455). Errors appear only on submit and are cleared on input/change when valid —
   never on blur, so nothing jumps under the pointer. Nothing here moves or animates, and it
   listens to no scroll (research/07 §4, §7.5): focus moves to the summary or the thank-you.
   ========================================================================= */
(() => {
  "use strict";
  const d = document, w = window, html = d.documentElement;
  const nh = w.nh || (w.nh = {});
  const data = nh.data || w.nhData || {};
  const msgs = (data.forms && data.forms.msg) || {};
  const M = (k) => msgs[k] || "";
  const ICON_ERR =
    '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v6M12 16.5v.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
  const CODES = { required: "required", email: "email", tel: "tel", consent: "consent", file_type: "fileType", file_size: "fileSize" };
  /* The server takes a form that arrives less than 3 s after its time token for a bot: it answers "sent" and
     sends nothing (NH_Forms::MIN_AGE). A person who autofills and sends right after the first focus (when a
     fresh token is fetched) would lose the enquiry without knowing, so a post never leaves earlier than
     this after the token in the form reached the page (3 s + a margin). */
  const TOKEN_AGE = 3500;
  const now = () => w.performance.now();

  // the visible variant of a label with twins (plain mode: .v3-plain, else .v3-full), without the asterisk
  const labelText = (form, el) => {
    if (el.dataset.errorLabel) return el.dataset.errorLabel;       // e.g. the consent box, whose label is a whole sentence
    const l = form.querySelector('label[for="' + el.id + '"]');
    if (!l) return el.name;
    const c = l.cloneNode(true);
    c.querySelectorAll(".nh-req").forEach((x) => x.remove());
    const plain = html.classList.contains("nh-plainmode");
    const pick = c.querySelector(plain ? ".v3-plain" : ".v3-full") || c.querySelector(plain ? ".v3-full" : ".v3-plain");
    if (pick && c.querySelector(".v3-full, .v3-plain")) return pick.textContent.replace(/\s+/g, " ").trim();
    return c.textContent.replace(/\s+/g, " ").trim();
  };
  const describe = (el, id, on) => {
    const ids = (el.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean).filter((x) => x !== id);
    if (on) ids.push(id);
    ids.length ? el.setAttribute("aria-describedby", ids.join(" ")) : el.removeAttribute("aria-describedby");
  };
  const check = (el) => {
    const v = (el.value || "").trim();
    if (el.type === "checkbox") return el.required && !el.checked ? M("consent") : "";
    if (el.type === "file") {
      const f = el.files && el.files[0];
      if (!f) return el.required ? M("required") : "";
      if (!/\.(pdf|docx?)$/i.test(f.name)) return M("fileType");
      if (f.size > 5 * 1024 * 1024) return M("fileSize");
      return "";
    }
    if (el.required && !v) return M("required");
    if (v && el.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return M("email");
    if (v && el.type === "tel") {
      const digits = v.replace(/\D/g, "");
      if (!/^0\d{8,9}$/.test(digits)) return M("tel");
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

  const bound = new WeakSet();
  const bind = (form) => {
    if (!form || bound.has(form)) return;
    bound.add(form);
    form.setAttribute("novalidate", "");
    const summary = form.querySelector("[data-error-summary]");
    const success = form.parentNode.querySelector("[data-success]");
    const type = form.dataset.nhForm || "";
    const setField = (name, value) => {
      const i = form.querySelector('input[name="' + name + '"]');
      if (i) i.value = String(value);
    };
    const fields = () =>
      Array.from(form.querySelectorAll("input, select, textarea")).filter(
        (el) => !el.closest(".nh-hp") && el.type !== "hidden" && el.type !== "submit"
      );

    /* Errors appear only on submit. While the person fixes a field, its error is
       cleared as soon as the value becomes valid (input/change) — never on blur,
       so the layout does not jump under the pointer. */
    const clearIfValid = (e) => {
      const el = e.target;
      if (!el.matches("input, select, textarea") || el.closest(".nh-hp")) return;
      if (el.getAttribute("aria-invalid") === "true" && !check(el)) setError(el, "");
    };
    form.addEventListener("input", clearIfValid);
    form.addEventListener("change", clearIfValid);

    // links in the summary (also the ones the server rendered) move focus to their field
    if (summary) summary.addEventListener("click", (e) => {
      const a = e.target.closest("a[href^='#']");
      const el = a && d.getElementById(a.getAttribute("href").slice(1));
      if (el) { e.preventDefault(); el.focus(); }
    });

    // when the time token now in the form reached the page: the page's own token is at least as old as this
    let minted = now();
    // a fresh nonce and time token on the first focus: the page itself may come from a cache
    let fresh = false;
    form.addEventListener("focusin", () => {
      if (fresh || !type || !data.ajax || !w.fetch) return;
      fresh = true;
      const url = data.ajax + (data.ajax.indexOf("?") < 0 ? "?" : "&") + "action=nh_form_token&type=" + encodeURIComponent(type);
      w.fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } })
        .then((r) => (r.ok ? r.json() : null))
        .then((j) => {
          if (!j || !j.nonce) return;
          setField("_nh_nonce", j.nonce); setField("nh_t", j.t); setField("nh_s", j.s);
          minted = now();
        })
        .catch(() => { fresh = false; });
    });

    const showErrors = (errs) => {
      if (success) success.hidden = true;
      if (!summary) { errs[0].el.focus(); return; }
      summary.hidden = false;
      summary.querySelector("h3").textContent =
        errs.length === 1 ? M("summaryOne") : M("summaryMany").replace("{n}", String(errs.length));
      summary.querySelector("ul").replaceChildren(
        ...errs.map(({ el, m }) => {
          const li = d.createElement("li");
          const a = d.createElement("a");
          a.href = "#" + el.id;
          a.textContent = labelText(form, el) + "\u00A0— " + m;   // the dash stays with the label (NH_Text::dashes)
          li.append(a);
          return li;
        })
      );
      summary.focus();
    };
    // a status the server answered (expired, limited, off, error): its message, from the settings
    const showStatus = (message) => {
      if (success) success.hidden = true;
      if (!summary) return;
      summary.hidden = false;
      summary.querySelector("h3").textContent = M("summary");
      const li = d.createElement("li");
      li.innerHTML = message || M("error");                               // the server's own text (settings, kses'd)
      summary.querySelector("ul").replaceChildren(li);
      summary.focus();
    };
    const done = () => {
      summary && (summary.hidden = true);
      form.reset();
      delete form.dataset.touched;
      if (success) { success.hidden = false; success.focus(); }
    };
    const send = () => {
      if (!w.fetch || !w.FormData) { form.submit(); return; }            // the server answers with a redirect (PRG)
      setField("nh_js", "1");
      const btn = form.querySelector("[type='submit']");
      if (btn) btn.disabled = true;
      // the attribute, not form.action: the hidden input named "action" (admin-post.php) shadows that property
      const url = new URL(form.getAttribute("action") || "", w.location.href).href;
      const post = () => {
        // a token younger than TOKEN_AGE: wait (again, if a fresh token arrived in the meantime)
        const wait = minted + TOKEN_AGE - now();
        if (wait > 0) { w.setTimeout(post, wait); return; }
        w.fetch(url, { method: "POST", body: new FormData(form), credentials: "same-origin", headers: { Accept: "application/json" } })
          .then((r) => r.json())
          .then((res) => {
            if (res && res.ok) { done(); return; }
            if (res && res.status === "invalid" && res.errors) {
              const errs = [];
              Object.keys(res.errors).forEach((key) => {
                const el = form.querySelector("[name='" + key + "']");
                if (!el || !el.id) return;
                const m = M(CODES[res.errors[key]] || "required");
                setError(el, m);
                errs.push({ el, m });
              });
              if (errs.length) { showErrors(errs); return; }
            }
            showStatus(res && res.message);
          })
          .catch(() => showStatus(M("error")))
          .finally(() => { if (btn) btn.disabled = false; setField("nh_js", "0"); });
      };
      post();
    };

    form.addEventListener("submit", (ev) => {
      ev.preventDefault();
      form.dataset.touched = "1";
      const hp = form.querySelector(".nh-hp input");
      if (hp && hp.value) return;                                         // bot: silently ignore
      const errs = [];
      fields().forEach((el) => {
        const m = check(el);
        setError(el, m);
        if (m) errs.push({ el, m });
      });
      if (errs.length) { showErrors(errs); return; }
      if (summary) summary.hidden = true;
      send();
    });
  };

  // after a submission without JavaScript the server rendered a state: put the reader there
  const focusState = () => {
    const el = d.querySelector("form[data-validate] [data-error-summary]:not([hidden])") || d.querySelector("[data-success]:not([hidden])");
    if (el) el.focus();
  };

  d.querySelectorAll("form[data-validate]").forEach(bind);
  focusState();
  const hook = () => {
    const ef = w.elementorFrontend;
    if (!ef || !ef.hooks || hook.added) return;
    hook.added = true;
    ef.hooks.addAction("frontend/element_ready/global", ($scope) => {
      const root = $scope && $scope[0] ? $scope[0] : null;
      if (root && root.querySelectorAll) root.querySelectorAll("form[data-validate]").forEach(bind);
    });
  };
  hook();
  if (!hook.added) {
    if (w.jQuery) w.jQuery(w).on("elementor/frontend/init", hook);
    w.addEventListener("elementor/frontend/init", hook);
  }

  nh.forms = { bind };
})();
