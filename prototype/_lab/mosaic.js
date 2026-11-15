/* =========================================================================
   NHMosaic — tiny Canvas-2D mosaic engine (no dependencies, ~6 KB min)
   The palette is sampled from the residents' own mosaics (mandala, owls).
   Features
   - cells from TEXT (any font, RTL), from an IMAGE (palette-quantised:
     a photo rendered in the residents' stones), or random FIELD
   - assemble-in, pointer "touch" ripple, scroll-driven disperse/morph
   - draws only while something moves; pauses off-screen
   - reduced motion: one static frame, no listeners
   Canvases are decorative: always aria-hidden, real text lives in the DOM.
   ========================================================================= */
(function (w, d) {
  "use strict";

  /* residents' mosaic palette (sampled from photos, see research/03) */
  const STONE = ["#EDE8E1", "#E0DAD1", "#CFC8BD", "#B9AF9F"];
  const ACCENT = ["#5F6E78", "#46737E", "#BBA957", "#A4614A", "#934F3D", "#939455", "#2E4168", "#4A2D84"];
  const PALETTE = STONE.concat(ACCENT);

  const reducedMotion = () =>
    d.documentElement.classList.contains("nh-reduce-motion") ||
    (w.matchMedia && w.matchMedia("(prefers-reduced-motion: reduce)").matches);

  function rng(seed) {
    let s = (seed >>> 0) || 1;
    return () => ((s = (Math.imul(s, 1664525) + 1013904223) >>> 0) / 4294967296);
  }
  const hexToRgb = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
  const PAL_RGB = PALETTE.map(hexToRgb);
  function nearest(r, g, b, pal) {
    let best = 0, bd = 1e9;
    for (let i = 0; i < pal.length; i++) {
      const p = pal[i], dd = (p[0] - r) ** 2 * 0.3 + (p[1] - g) ** 2 * 0.59 + (p[2] - b) ** 2 * 0.11;
      if (dd < bd) { bd = dd; best = i; }
    }
    return best;
  }
  const easeOut = (t) => 1 - Math.pow(1 - t, 3);
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

  /* ---------- cell generators (all coordinates in CSS px) --------------- */

  /* Text mask: draws the text off-screen and keeps grid cells inside glyphs. */
  function cellsFromText(W, H, opt) {
    const tile = opt.tile, gap = opt.gap;
    const c = d.createElement("canvas");
    c.width = Math.ceil(W); c.height = Math.ceil(H);
    const x = c.getContext("2d", { willReadFrequently: true });
    x.fillStyle = "#000";
    x.textAlign = "center"; x.textBaseline = "middle"; x.direction = opt.dir || "rtl";
    let size = opt.fontSize || H * 0.8;
    x.font = `${opt.weight || 600} ${size}px ${opt.family}`;
    const mw = x.measureText(opt.text).width;
    if (mw > W * 0.96) { size *= (W * 0.96) / mw; x.font = `${opt.weight || 600} ${size}px ${opt.family}`; }
    x.fillText(opt.text, W / 2, H / 2 + size * (opt.baselineShift || 0.04));
    const data = x.getImageData(0, 0, c.width, c.height).data;
    const r = rng(opt.seed || 7), cells = [];
    const step = tile + gap;
    for (let yy = step / 2; yy < H; yy += step) {
      for (let xx = step / 2; xx < W; xx += step) {
        const i = (Math.floor(yy) * c.width + Math.floor(xx)) * 4 + 3;
        if (data[i] > 110) {
          const pick = r();
          const col = pick < (opt.accentShare || 0.32) ? ACCENT[Math.floor(r() * ACCENT.length)] : STONE[Math.floor(r() * STONE.length)];
          const jx = (r() - 0.5) * step * (opt.jitter ?? 0.22), jy = (r() - 0.5) * step * (opt.jitter ?? 0.22);
          const sv = tile * (0.8 + r() * 0.28);
          cells.push({ x: xx + jx, y: yy + jy, s: sv, c: opt.colors ? opt.colors[Math.floor(r() * opt.colors.length)] : col });
        }
      }
    }
    return cells;
  }

  /* Vector shape made of parts (SVG path strings in a viewBox), each part with
     its own colours — e.g. the iris from the logo: violet petals, ochre
     "beards", olive/teal stem and leaves. Fit = contain, anchored by opt.align. */
  function cellsFromPaths(W, H, parts, opt) {
    const vb = opt.viewBox || [0, 0, 400, 600];
    const c = d.createElement("canvas"); c.width = Math.ceil(W); c.height = Math.ceil(H);
    const x = c.getContext("2d", { willReadFrequently: true });
    const sc = Math.min(W / vb[2], H / vb[3]) * (opt.scale || 1);
    const ox = (W - vb[2] * sc) * (opt.alignX ?? 0.5), oy = (H - vb[3] * sc) * (opt.alignY ?? 0.5);
    x.setTransform(sc, 0, 0, sc, ox - vb[0] * sc, oy - vb[1] * sc);
    parts.forEach((p, i) => { x.fillStyle = `rgb(${i + 1},0,0)`; x.fill(new Path2D(p.d)); });
    x.setTransform(1, 0, 0, 1, 0, 0);
    const data = x.getImageData(0, 0, c.width, c.height).data;
    const r = rng(opt.seed || 5), cells = [];
    const tile = opt.tile, step = tile + opt.gap;
    for (let yy = step / 2; yy < H; yy += step) {
      for (let xx = step / 2; xx < W; xx += step) {
        const k = (Math.floor(yy) * c.width + Math.floor(xx)) * 4;
        const id = data[k], a = data[k + 3];
        if (a > 100 && id > 0 && id <= parts.length) {
          const p = parts[id - 1];
          const jx = (r() - 0.5) * step * (opt.jitter ?? 0.22), jy = (r() - 0.5) * step * (opt.jitter ?? 0.22);
          cells.push({ x: xx + jx, y: yy + jy, s: tile * (0.8 + r() * 0.28), c: p.colors[Math.floor(r() * p.colors.length)] });
        } else if (opt.field && r() < opt.field) {
          cells.push({ x: xx, y: yy, s: tile * (0.55 + r() * 0.3), c: STONE[Math.floor(r() * STONE.length)], field: true });
        }
      }
    }
    return cells;
  }

  /* Stylised iris (viewBox 400×600) — standards up, falls down, stem, leaves */
  const IRIS = [
    { d: "M200 182C168 140 166 78 200 34C234 78 232 140 200 182Z", colors: ["#4A2D84", "#5A3C9A", "#3B2470", "#6B52AE"] },
    { d: "M194 180C146 162 112 108 128 64C158 88 186 128 194 180Z", colors: ["#5A3C9A", "#6B52AE", "#4A2D84", "#8270C0"] },
    { d: "M206 180C254 162 288 108 272 64C242 88 214 128 206 180Z", colors: ["#5A3C9A", "#6B52AE", "#4A2D84", "#8270C0"] },
    { d: "M191 184C148 190 92 214 74 266C108 270 160 240 191 194Z", colors: ["#3B2470", "#4A2D84", "#5A3C9A", "#2E1F5C"] },
    { d: "M209 184C252 190 308 214 326 266C292 270 240 240 209 194Z", colors: ["#3B2470", "#4A2D84", "#5A3C9A", "#2E1F5C"] },
    { d: "M200 188C176 218 172 270 200 308C228 270 224 218 200 188Z", colors: ["#2E1F5C", "#3B2470", "#4A2D84"] },
    { d: "M186 196C176 208 168 222 162 236C172 230 182 216 190 202Z M214 196C224 208 232 222 238 236C228 230 218 216 210 202Z M200 196L196 236L204 236Z", colors: ["#BBA957", "#D2BE62", "#F2B33D"] },
    { d: "M195 300L205 300L207 576L193 576Z", colors: ["#939455", "#7C8046", "#46737E"] },
    { d: "M193 576C152 482 140 384 160 300C172 384 182 470 199 576Z", colors: ["#939455", "#46737E", "#3D5832", "#7C8046"] },
    { d: "M207 576C254 492 268 404 254 326C242 410 226 484 201 576Z", colors: ["#46737E", "#939455", "#3D5832", "#5F6E78"] },
  ];

  /* Mandala in concentric rings (opus "andamento" — tiles follow the circle),
     ring colours modelled on the residents' own mandala: ochre/teal/olive
     heart, stone rings, a terracotta band and a checkered outer border. */
  const RINGS = [
    ["#BBA957"], ["#46737E", "#BBA957"], ["#939455", "#46737E"], ["#EDE8E1"], ["#46737E"],
    ["#E0DAD1", "#CFC8BD"], ["#BBA957", "#939455"], ["#EDE8E1"], ["#A4614A"], ["#934F3D", "#A4614A"],
    ["#E0DAD1"], ["#5F6E78", "#2E4168"], ["#CFC8BD", "#EDE8E1"], ["#B9AF9F"], ["#A4614A"],
    ["#EDE8E1", "#E0DAD1"], ["checker"], ["#5F6E78"], ["checker"], ["#E0DAD1", "#CFC8BD"],
    ["#4A2D84", "#5A3C9A"], ["#EDE8E1"],
  ];
  function cellsMandala(W, H, opt) {
    const r = rng(opt.seed || 9), cells = [];
    const cx = W * (opt.cx ?? 0.5), cy = H * (opt.cy ?? 0.5);
    const maxR = Math.min(W, H) * 0.5 * (opt.scale || 0.96);
    const step = opt.tile + opt.gap;
    let ring = 0;
    for (let R = step * 0.6; R < maxR; R += step, ring++) {
      const pal = RINGS[ring % RINGS.length];
      const n = Math.max(6, Math.floor((2 * Math.PI * R) / step));
      for (let i = 0; i < n; i++) {
        const th = (i / n) * Math.PI * 2 + (ring % 2) * (Math.PI / n);
        let c;
        if (pal[0] === "checker") c = i % 2 ? "#1F2421" : "#EDE8E1";
        else c = pal[Math.floor(r() * pal.length)];
        cells.push({ polar: { cx, cy, R, th, ring }, x: cx + R * Math.cos(th), y: cy + R * Math.sin(th),
          s: opt.tile * (0.86 + r() * 0.2), c });
      }
    }
    return cells;
  }

  /* Image: cover-fit into W×H, average per cell, snap to the stone palette. */
  function cellsFromImage(img, W, H, opt) {
    const tile = opt.tile, gap = opt.gap, step = tile + gap;
    const cols = Math.ceil(W / step), rows = Math.ceil(H / step);
    const c = d.createElement("canvas"); c.width = cols; c.height = rows;
    const x = c.getContext("2d", { willReadFrequently: true });
    const ir = img.naturalWidth / img.naturalHeight, cr = cols / rows;
    let sw = img.naturalWidth, sh = img.naturalHeight, sx = 0, sy = 0;
    if (ir > cr) { sw = sh * cr; sx = (img.naturalWidth - sw) * (opt.focusX ?? 0.5); }
    else { sh = sw / cr; sy = (img.naturalHeight - sh) * (opt.focusY ?? 0.5); }
    x.drawImage(img, sx, sy, sw, sh, 0, 0, cols, rows);
    const px = x.getImageData(0, 0, cols, rows).data;
    const r = rng(opt.seed || 3), cells = [];
    for (let j = 0; j < rows; j++) for (let i = 0; i < cols; i++) {
      const k = (j * cols + i) * 4;
      const jitter = (r() - 0.5) * 18;
      const R = px[k] + jitter, G = px[k + 1] + jitter, B = px[k + 2] + jitter;
      const col = opt.quantize === false ? `rgb(${R | 0},${G | 0},${B | 0})` : PALETTE[nearest(R, G, B, PAL_RGB)];
      cells.push({ x: i * step + step / 2, y: j * step + step / 2, s: tile, c: col });
    }
    return cells;
  }

  /* ---------- the engine ---------------------------------------------- */
  class Mosaic {
    constructor(canvas, opt) {
      this.cv = canvas;
      this.cx = canvas.getContext("2d");
      this.opt = Object.assign({ radius: 0.22, rotJitter: 7, touch: true, touchRadius: 120 }, opt || {});
      this.tiles = [];
      this.dpr = Math.min(w.devicePixelRatio || 1, 2);
      this.pointer = null;
      this.spread = 0;          // 0..1 scroll-driven dispersal
      this.visible = true;
      this.raf = 0;
      this.t0 = 0;
      this.onFrame = this.onFrame.bind(this);
      canvas.setAttribute("aria-hidden", "true");
      if ("IntersectionObserver" in w) {
        this.io = new IntersectionObserver((e) => {
          this.visible = e[0].isIntersecting;
          if (this.visible) this.kick();
        });
        this.io.observe(canvas);
      }
      if (this.opt.touch && !reducedMotion()) {
        const host = this.opt.touchTarget || canvas;
        host.addEventListener("pointermove", (e) => {
          const b = canvas.getBoundingClientRect();
          this.pointer = { x: e.clientX - b.left, y: e.clientY - b.top };
          this.kick();
        }, { passive: true });
        host.addEventListener("pointerleave", () => { this.pointer = null; this.kick(); }, { passive: true });
      }
    }

    size() {
      const b = this.cv.getBoundingClientRect();
      this.W = b.width; this.H = b.height;
      this.cv.width = Math.round(b.width * this.dpr);
      this.cv.height = Math.round(b.height * this.dpr);
      return { W: this.W, H: this.H };
    }

    /* set cells; mode "assemble" flies tiles in, "morph" re-targets existing */
    set(cells, mode) {
      const r = rng(this.opt.seed || 11);
      const reduce = reducedMotion();
      const old = this.tiles;
      this.tiles = cells.map((c, i) => {
        const rot0 = (r() - 0.5) * this.opt.rotJitter * (Math.PI / 180);
        const t = {
          hx: c.x, hy: c.y, s: c.s, c: c.c, rot0, rnd: r(), polar: c.polar || null,
          x: c.x, y: c.y, rot: rot0, sc: 1, a: 1,
          fromX: c.x, fromY: c.y, fromRot: rot0, fromA: 1, delay: 0,
        };
        if (!reduce && mode === "assemble") {
          const ang = r() * Math.PI * 2, dist = 80 + r() * Math.max(this.W, this.H) * 0.5;
          t.fromX = c.x + Math.cos(ang) * dist; t.fromY = c.y + Math.sin(ang) * dist;
          t.fromRot = (r() - 0.5) * Math.PI; t.fromA = 0;
          t.delay = (this.opt.assembleOrigin === "start"
            ? (1 - c.x / this.W) * 0.55 : r() * 0.45) + r() * 0.25;   // RTL: start = right
        } else if (!reduce && mode === "morph" && old.length) {
          const o = old[i % old.length];
          t.fromX = o.x; t.fromY = o.y; t.fromRot = o.rot; t.fromA = i < old.length ? o.a : 0;
          t.delay = r() * 0.3;
        }
        return t;
      });
      this.mode = reduce ? "static" : mode;
      this.t0 = performance.now();
      this.kick();
    }

    setSpread(v) { this.spread = clamp(v, 0, 1); this.kick(); }
    setSpin(v) { this.spin = v; this.kick(); }      // radians; rings turn in alternating directions

    kick() { if (!this.raf && this.visible) this.raf = requestAnimationFrame(this.onFrame); }

    onFrame(now) {
      this.raf = 0;
      const dur = (this.opt.duration || 1400) / 1000;
      const T = (now - this.t0) / 1000;
      let moving = false;
      const P = this.pointer, R = this.opt.touchRadius;
      const spin = this.spin || 0;
      for (const t of this.tiles) {
        if (t.polar) {                                   // mandala: recompute home on the ring
          const p = t.polar, ph = spin * (p.ring % 2 ? 1 : -1) * (0.35 + p.ring * 0.025);
          t.hx = p.cx + p.R * Math.cos(p.th + ph); t.hy = p.cy + p.R * Math.sin(p.th + ph);
          t.rot0 = p.th + ph + Math.PI / 2;
        }
        // 1) entrance / morph timeline
        let bx = t.hx, by = t.hy, brot = t.rot0, ba = 1;
        if (this.mode === "assemble" || this.mode === "morph") {
          const k = clamp((T - t.delay) / dur, 0, 1);
          if (k < 1) moving = true;
          const e = easeOut(k);
          bx = t.fromX + (t.hx - t.fromX) * e; by = t.fromY + (t.hy - t.fromY) * e;
          brot = t.fromRot + (t.rot0 - t.fromRot) * e; ba = t.fromA + (1 - t.fromA) * e;
        }
        // 2) scroll dispersal
        if (this.spread > 0) {
          const dx = t.hx - this.W / 2, dy = t.hy - this.H / 2, len = Math.hypot(dx, dy) || 1;
          const push = this.spread * (60 + t.rnd * 220);
          bx += (dx / len) * push; by += (dy / len) * push - this.spread * t.rnd * 120;
          brot += this.spread * (t.rnd - 0.5) * 3; ba *= 1 - this.spread * 0.85;
        }
        // 3) touch ripple (smoothed)
        let tsc = 1, trot = 0, tox = 0, toy = 0;
        if (P) {
          const dx = t.hx - P.x, dy = t.hy - P.y, dist = Math.hypot(dx, dy);
          if (dist < R) {
            const f = 1 - dist / R, ff = f * f;
            tsc = 1 - ff * 0.45; trot = ff * 0.9 * (t.rnd > 0.5 ? 1 : -1);
            tox = (dx / (dist || 1)) * ff * 10; toy = (dy / (dist || 1)) * ff * 10;
          }
        }
        const nx = bx + tox, ny = by + toy, nrot = brot + trot;
        t.x += (nx - t.x) * 0.22; t.y += (ny - t.y) * 0.22; t.rot += (nrot - t.rot) * 0.18;
        t.sc += (tsc - t.sc) * 0.18; t.a += (ba - t.a) * 0.3;
        if (Math.abs(nx - t.x) > 0.1 || Math.abs(nrot - t.rot) > 0.002 || Math.abs(tsc - t.sc) > 0.002 || Math.abs(ba - t.a) > 0.01) moving = true;
      }
      this.draw();
      if (moving && this.visible) this.kick();
    }

    draw() {
      const x = this.cx, dpr = this.dpr, rad = this.opt.radius;
      x.setTransform(1, 0, 0, 1, 0, 0);
      x.clearRect(0, 0, this.cv.width, this.cv.height);
      if (this.opt.grout) { x.fillStyle = this.opt.grout; x.fillRect(0, 0, this.cv.width, this.cv.height); }
      const glaze = this.opt.glaze ?? 0.13;
      for (const t of this.tiles) {
        if (t.a < 0.02) continue;
        const cs = Math.cos(t.rot) * dpr * t.sc, sn = Math.sin(t.rot) * dpr * t.sc;
        x.setTransform(cs, sn, -sn, cs, t.x * dpr, t.y * dpr);
        x.globalAlpha = t.a;
        x.fillStyle = t.c;
        const s = t.s, h = s / 2;
        if (x.roundRect) { x.beginPath(); x.roundRect(-h, -h, s, s, s * rad); x.fill(); }
        else x.fillRect(-h, -h, s, s);
        if (glaze > 0 && s > 5) {           // glazed-ceramic sheen: light top-start corner, shade bottom
          x.globalAlpha = t.a * glaze; x.fillStyle = "#fff";
          x.fillRect(-h + s * 0.14, -h + s * 0.14, s * 0.42, s * 0.18);
          x.globalAlpha = t.a * glaze * 0.9; x.fillStyle = "#000";
          x.fillRect(-h + s * 0.08, h - s * 0.2, s * 0.84, s * 0.12);
        }
      }
      x.setTransform(1, 0, 0, 1, 0, 0);
      x.globalAlpha = 1;
    }

    /* one static frame (reduced motion / print) */
    still() {
      for (const t of this.tiles) { t.x = t.hx; t.y = t.hy; t.rot = t.rot0; t.sc = 1; t.a = 1; }
      this.draw();
    }
  }

  w.NHMosaic = { Mosaic, cellsFromText, cellsFromImage, cellsFromPaths, cellsMandala, IRIS, PALETTE, STONE, ACCENT, reducedMotion };
})(window, document);
