#!/usr/bin/env python3
"""
Export the architectural letters of v3 «אותיות שגרים בהן» from Frank Ruhl Libre (OFL).

For each letter it writes, in font units with y flipped for SVG (0 = top of the glyph box):
- the outline at wght 800 and wght 300 (and the token template, so the browser can
  interpolate between them: ב, כ, ס and ד have identical command sequences at both weights);
- the "room" (counter) measured on a raster of each weight: roof underside, base top,
  inner face of the wall, the open side; for closed counters (ס, ם) the inner contour.

Usage:
    python3 -I scripts/build_letters.py <FrankRuhlLibre[wght].ttf> prototype/assets/letters.json

Needs fontTools and Pillow (build-time only; nothing of this runs in the browser).
"""
import json
import re
import sys

from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.recordingPen import RecordingPen
from fontTools.ttLib import TTFont
from PIL import Image, ImageChops, ImageDraw

LETTERS = {           # name: (code point, weights to export)
    "bet": (0x05D1, (800, 300)),
    "kaf": (0x05DB, (800, 300)),
    "samekh": (0x05E1, (800, 300)),
    "dalet": (0x05D3, (800, 300)),
    "lamed": (0x05DC, (800, 300)),
    "mem_final": (0x05DD, (800, 300)),
}
TOP = 760          # y of the glyph box top in font units (above the ascender of ל: 0..~760)
PAD = 10


def record(gs, gname):
    pen = RecordingPen()
    gs[gname].draw(pen)
    b = BoundsPen(gs)
    gs[gname].draw(b)
    return pen.value, b.bounds, gs[gname].width


def to_tokens(rec):
    """RecordingPen value -> (commands, numbers) with y flipped (SVG down)."""
    cmds, nums = [], []
    for op, pts in rec:
        if op == "moveTo":
            cmds.append("M"); x, y = pts[0]; nums += [x, TOP - y]
        elif op == "lineTo":
            cmds.append("L"); x, y = pts[0]; nums += [x, TOP - y]
        elif op == "qCurveTo":
            # TrueType quadratic splines may carry several off-curve points: split into Q segments
            offs, on = pts[:-1], pts[-1]
            for i, c in enumerate(offs):
                if i < len(offs) - 1:
                    nxt = offs[i + 1]
                    end = ((c[0] + nxt[0]) / 2, (c[1] + nxt[1]) / 2)
                else:
                    end = on
                cmds.append("Q"); nums += [c[0], TOP - c[1], end[0], TOP - end[1]]
        elif op == "curveTo":
            cmds.append("C")
            for x, y in pts:
                nums += [x, TOP - y]
        elif op in ("closePath", "endPath"):
            cmds.append("Z")
    return cmds, [round(v, 1) for v in nums]


def to_d(cmds, nums):
    out, i = [], 0
    arity = {"M": 2, "L": 2, "Q": 4, "C": 6, "Z": 0}
    for c in cmds:
        n = arity[c]
        out.append(c + " ".join(f"{v:g}" for v in nums[i:i + n]))
        i += n
    return "".join(out)


def polys(cmds, nums):
    """Flatten to polygons for rasterising."""
    out, poly, i, cur = [], [], 0, (0, 0)
    for c in cmds:
        if c == "M":
            if poly: out.append(poly)
            cur = (nums[i], nums[i + 1]); poly = [cur]; i += 2
        elif c == "L":
            cur = (nums[i], nums[i + 1]); poly.append(cur); i += 2
        elif c == "Q":
            cx, cy, ex, ey = nums[i:i + 4]
            for k in range(1, 11):
                s = k / 10
                poly.append(((1 - s) ** 2 * cur[0] + 2 * (1 - s) * s * cx + s * s * ex,
                             (1 - s) ** 2 * cur[1] + 2 * (1 - s) * s * cy + s * s * ey))
            cur = (ex, ey); i += 4
        elif c == "C":
            x1, y1, x2, y2, ex, ey = nums[i:i + 6]
            for k in range(1, 13):
                s = k / 12
                poly.append(((1 - s) ** 3 * cur[0] + 3 * (1 - s) ** 2 * s * x1 + 3 * (1 - s) * s * s * x2 + s ** 3 * ex,
                             (1 - s) ** 3 * cur[1] + 3 * (1 - s) ** 2 * s * y1 + 3 * (1 - s) * s * s * y2 + s ** 3 * ey))
            cur = (ex, ey); i += 6
        elif c == "Z":
            if poly: out.append(poly); poly = []
    if poly: out.append(poly)
    return out


def _xor(a, b):
    """Even-odd fill: XOR each contour into the mask (mode "1")."""
    return ImageChops.logical_xor(a, b)


def room_open(img, x0, x1):
    """Counter of an open letter (ב, כ): scan the column at 45% of the ink width."""
    px = img.load()
    w, h = img.size
    cx = int(x0 + 0.45 * (x1 - x0))
    y = 0
    while y < h and px[cx, y] == 0: y += 1
    while y < h and px[cx, y] > 0: y += 1
    roof_under = y
    while y < h and px[cx, y] == 0: y += 1
    base_top = y
    cy = (roof_under + base_top) // 2
    x = int(x1)
    while x > 0 and px[x, cy] == 0: x -= 1
    while x > 0 and px[x, cy] > 0: x -= 1
    wall_inner = x
    return {"x": round(x0, 1), "y": roof_under, "w": round(wall_inner - x0, 1), "h": base_top - roof_under}


def room_closed(img, x0, x1, y0, y1):
    """Closed counter (ס, ם): bounding box of the empty area enclosed by ink."""
    px = img.load()
    cx, cy = int((x0 + x1) / 2), int((y0 + y1) / 2)
    l = cx
    while l > 0 and px[l, cy] == 0: l -= 1
    r = cx
    while r < img.size[0] - 1 and px[r, cy] == 0: r += 1
    t = cy
    while t > 0 and px[cx, t] == 0: t -= 1
    b = cy
    while b < img.size[1] - 1 and px[cx, b] == 0: b += 1
    return {"x": l + 1, "y": t + 1, "w": r - l - 1, "h": b - t - 1}


def main():
    font_path, out_json = sys.argv[1], sys.argv[2]
    f = TTFont(font_path)
    cmap = f.getBestCmap()
    res = {"upm": f["head"].unitsPerEm, "top": TOP, "source": "Frank Ruhl Libre (SIL OFL 1.1), variable wght"}
    for name, (cp, weights) in LETTERS.items():
        gname = cmap[cp]
        entry = {}
        tokens = {}
        for wv in weights:
            gs = f.getGlyphSet(location={"wght": wv})
            rec, bounds, adv = record(gs, gname)
            cmds, nums = to_tokens(rec)
            tokens[wv] = (cmds, nums)
            x0, y0b, x1, y1b = bounds
            box = {"x": round(x0, 1), "y": round(TOP - y1b, 1), "w": round(x1 - x0, 1), "h": round(y1b - y0b, 1)}
            img = Image.new("1", (int(adv) + 40, TOP + 220), 0)
            for p in polys(cmds, nums):
                layer = Image.new("1", img.size, 0)
                ImageDraw.Draw(layer).polygon(p, fill=1)
                img = _xor(img, layer)
            img = img.convert("L")
            if name in ("bet", "kaf"):
                room = room_open(img, x0, x1)
            elif name in ("samekh", "mem_final"):
                room = room_closed(img, x0, x1, TOP - y1b, TOP - y0b)
            else:
                room = None
            entry[str(wv)] = {"d": to_d(cmds, nums), "box": box, "adv": round(adv, 1), "room": room}
        a, b = tokens[weights[0]], tokens[weights[1]]
        entry["lerp"] = a[0] == b[0] and len(a[1]) == len(b[1])
        if entry["lerp"]:
            entry["cmds"] = "".join(a[0])
            entry["a"] = a[1]
            entry["b"] = b[1]
        res[name] = entry
        r8, r3 = entry["800"]["room"], entry["300"]["room"]
        print(f"{name:10s} lerp={entry['lerp']!s:5s} box800={entry['800']['box']} room800={r8} room300={r3}")
    json.dump(res, open(out_json, "w"), separators=(",", ":"))


if __name__ == "__main__":
    main()
