#!/usr/bin/env python3
"""
Tiny static build for the prototype (no dependencies).

prototype/src/*.html  ->  prototype/*.html

Directives inside source pages:
  {{page current="home"}}                 declare the page (for aria-current)
  {{> header}}  {{> footer}} ...          include prototype/src/partials/<name>.html
  {{current:programs}}                    -> aria-current="page" when it matches
  {{pic name="hero-walk" sizes="..." class="..." loading="lazy|eager"
        priority="high" alt="..." decorative="1"}}
                                          -> responsive <picture> (AVIF + WebP)
  {{srcset name="dining"}}                -> WebP srcset string (for lightbox)
  {{src name="dining" w="1080"}}          -> single WebP URL

Usage: python3 -I scripts/build_prototype_html.py
"""
import html
import json
import os
import re

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "prototype")
SRC = os.path.join(ROOT, "src")
IMG_DIR = os.path.join(ROOT, "assets", "img")
manifest = json.load(open(os.path.join(IMG_DIR, "manifest.json"), encoding="utf-8"))

ATTR = re.compile(r'(\w+)="([^"]*)"')


def dims(name, w):
    m = manifest[name]
    return w, round(m["height"] * w / m["width"])


def picture(attrs):
    name = attrs["name"]
    m = manifest[name]
    widths = m["widths"]
    sizes = attrs.get("sizes", "100vw")
    alt = "" if attrs.get("decorative") == "1" else attrs.get("alt", m["alt"])
    loading = attrs.get("loading", "lazy")
    pri = ' fetchpriority="high"' if attrs.get("priority") == "high" else ""
    cls = f' class="{attrs["class"]}"' if attrs.get("class") else ""
    avif = ", ".join(f"assets/img/{name}-{w}.avif {w}w" for w in widths)
    webp = ", ".join(f"assets/img/{name}-{w}.webp {w}w" for w in widths)
    fallback_w = 768 if 768 in widths else widths[-1]
    W, H = dims(name, widths[-1])
    return (
        f'<picture{cls}>'
        f'<source type="image/avif" srcset="{avif}" sizes="{sizes}">'
        f'<img src="assets/img/{name}-{fallback_w}.webp" srcset="{webp}" sizes="{sizes}" '
        f'width="{W}" height="{H}" alt="{html.escape(alt, quote=True)}" loading="{loading}" decoding="async"{pri}>'
        f'</picture>'
    )


LETTERS_PATH = os.path.join(ROOT, "assets", "letters.json")
letters = json.load(open(LETTERS_PATH, encoding="utf-8")) if os.path.exists(LETTERS_PATH) else {}


def _frame(name, at="800"):
    """The letter's frame = its outline box at wght 800 (or 300) in font units, y down."""
    b = letters[name][at]["box"]
    return b["x"], b["y"], b["w"], b["h"]


def letter_svg(attrs):
    """{{letter name="kaf" w="800" class="..."}} -> inline, decorative SVG outline."""
    name, w = attrs["name"], attrs.get("w", "800")
    fx, fy, fw, fh = _frame(name, attrs.get("frame", "800"))
    cls = (" " + attrs["class"]) if attrs.get("class") else ""
    d = letters[name][w]["d"] if w in ("800", "300") else lerp_d(name, float(w))
    return (f'<svg class="v3-letter v3-letter--{name}{cls}" viewBox="{fx:g} {fy:g} {fw:g} {fh:g}" '
            f'preserveAspectRatio="xMidYMax meet" aria-hidden="true" focusable="false" data-letter="{name}">'
            f'<path class="v3-letter__ink" d="{d}"/></svg>')


def lerp_d(name, weight):
    """Outline at an in-between weight (800 -> 300), for static states."""
    e = letters[name]
    t = (800 - weight) / 500
    nums = [a + (b - a) * t for a, b in zip(e["a"], e["b"])]
    arity = {"M": 2, "L": 2, "Q": 4, "C": 6, "Z": 0}
    out, i = [], 0
    for c in e["cmds"]:
        n = arity[c]
        out.append(c + " ".join(f"{v:.1f}" for v in nums[i:i + n]))
        i += n
    return "".join(out)


def letter_room(attrs):
    """{{letterroom letter="bet" photo="v3-walk" sizes="..." priority="high" pos="50% 40%" w="800"}}
    A photo that lives only in the letter's room (counter): positioned on the union of the
    room at wght 800 and 300, clipped with inset() values interpolated by --t (0 = 800, 1 = 300)."""
    name = attrs["letter"]
    fx, fy, fw, fh = _frame(name, attrs.get("frame", "800"))
    r8, r3 = letters[name]["800"]["room"], letters[name]["300"]["room"]
    ux, uy = min(r8["x"], r3["x"]), min(r8["y"], r3["y"])
    ur, ub = max(r8["x"] + r8["w"], r3["x"] + r3["w"]), max(r8["y"] + r8["h"], r3["y"] + r3["h"])
    uw, uh = ur - ux, ub - uy

    def ins(r):
        return ((r["y"] - uy) / uh * 100, (ur - (r["x"] + r["w"])) / uw * 100,
                (ub - (r["y"] + r["h"])) / uh * 100, (r["x"] - ux) / uw * 100)
    i8, i3 = ins(r8), ins(r3)
    style = (f'--fx:{(ux - fx) / fw * 100:.3f}%;--fy:{(uy - fy) / fh * 100:.3f}%;'
             f'--fw:{uw / fw * 100:.3f}%;--fh:{uh / fh * 100:.3f}%;'
             + "".join(f"--a{k}:{v:.3f}%;" for k, v in zip("trbl", i8))
             + "".join(f"--b{k}:{v:.3f}%;" for k, v in zip("trbl", i3))
             + f'--pos:{attrs.get("pos", "50% 50%")};--asp:{fw:g}/{fh:g};'
             # centre of the photo area and the side of a square photo covering it (for overlays)
             + f'--cx:{(ux + uw / 2 - fx) / fw * 100:.3f}%;--cy:{(uy + uh / 2 - fy) / fh * 100:.3f}%;'
             + f'--sqw:{max(uw, uh) / fw * 100:.3f}%;--em-w:{fw / 1000:.4f};')
    w = attrs.get("w", "800")
    t0 = (800 - float(w)) / 500
    pic = ""
    if attrs.get("photo"):
        pic = picture({"name": attrs["photo"], "sizes": attrs.get("sizes", "50vw"),
                       "loading": "eager" if attrs.get("priority") == "high" else "lazy",
                       "priority": attrs.get("priority", ""), "class": "v3-room__photo",
                       **({"decorative": "1"} if attrs.get("decorative") == "1" else {}),
                       **({"alt": attrs["alt"]} if attrs.get("alt") else {})})
    elif attrs.get("field"):
        pic = f'<span class="v3-room__photo v3-room__field v3-room__field--{attrs["field"]}" aria-hidden="true"></span>'
    cls = (" " + attrs["class"]) if attrs.get("class") else ""
    vt = f'view-transition-name:{attrs["vt"]};' if attrs.get("vt") else ""
    extra = open(os.path.join(SRC, "partials", attrs["extra"] + ".html"), encoding="utf-8").read().strip() if attrs.get("extra") else ""
    return (f'<div class="v3-room v3-room--{name}{cls}" style="{style}--t:{t0:g};{vt}" data-room="{name}">'
            f'{pic}{letter_svg({"name": name, "w": w, "frame": attrs.get("frame", "800")})}{extra}</div>')


def letter_data(attrs):
    """{{letterdata names="bet"}} -> inline JSON with the interpolation template."""
    out = {}
    for n in attrs["names"].split():
        e = letters[n]
        out[n] = {"c": e["cmds"], "a": e["a"], "b": e["b"]}
    return ('<script type="application/json" id="nh-letters">'
            + json.dumps(out, separators=(",", ":")) + "</script>")


def img_data(attrs):
    """{{imgdata id="nh-works" names="a b c"}} -> inline JSON {name: {widths}} for JS swaps."""
    out = {n: {"widths": manifest[n]["widths"]} for n in attrs["names"].split()}
    return (f'<script type="application/json" id="{attrs.get("id", "nh-img")}">'
            + json.dumps(out, separators=(",", ":")) + "</script>")


def build_page(fname):
    text = open(os.path.join(SRC, fname), encoding="utf-8").read()
    # includes (one level of nesting is enough)
    for _ in range(2):
        text = re.sub(
            r"\{\{>\s*([\w-]+)\s*\}\}",
            lambda m: open(os.path.join(SRC, "partials", m.group(1) + ".html"), encoding="utf-8").read(),
            text,
        )
    cur = re.search(r'\{\{page current="([\w-]+)"\}\}', text)
    current = cur.group(1) if cur else ""
    text = re.sub(r'\{\{page [^}]*\}\}\n?', "", text)
    text = re.sub(r"\{\{current:([\w-]+)\}\}",
                  lambda m: 'aria-current="page"' if m.group(1) == current else "", text)
    text = re.sub(r"\{\{pic ([^}]*)\}\}", lambda m: picture(dict(ATTR.findall(m.group(1)))), text)
    text = re.sub(r"\{\{letterroom ([^}]*)\}\}", lambda m: letter_room(dict(ATTR.findall(m.group(1)))), text)
    text = re.sub(r"\{\{letterdata ([^}]*)\}\}", lambda m: letter_data(dict(ATTR.findall(m.group(1)))), text)
    text = re.sub(r"\{\{imgdata ([^}]*)\}\}", lambda m: img_data(dict(ATTR.findall(m.group(1)))), text)
    text = re.sub(r"\{\{letter ([^}]*)\}\}", lambda m: letter_svg(dict(ATTR.findall(m.group(1)))), text)
    text = re.sub(
        r"\{\{srcset ([^}]*)\}\}",
        lambda m: ", ".join(
            f"assets/img/{dict(ATTR.findall(m.group(1)))['name']}-{w}.webp {w}w"
            for w in manifest[dict(ATTR.findall(m.group(1)))["name"]]["widths"]
        ),
        text,
    )

    def one_src(m):
        a = dict(ATTR.findall(m.group(1)))
        widths = manifest[a["name"]]["widths"]
        w = int(a.get("w", widths[-1]))
        w = max([x for x in widths if x <= w] or [widths[0]])
        return f"assets/img/{a['name']}-{w}.webp"

    text = re.sub(r"\{\{src ([^}]*)\}\}", one_src, text)

    def tiles(m):
        # deterministic "mosaic" (decorative): palette taken from residents' mosaics
        a = dict(ATTR.findall(m.group(1)))
        n, seed = int(a.get("n", 36)), int(a.get("seed", 7))
        pal = ["--nh-iris-700", "--nh-clay-700", "--nh-olive-700", "--nh-citrus-400",
               "--nh-sky-500", "--nh-clay-300", "--nh-iris-100", "--nh-olive-300", "--nh-night"]
        x, out = seed, []
        for _ in range(n):
            x = (x * 1103515245 + 12345) % (2 ** 31)
            out.append(f'<span style="--c:var({pal[x % len(pal)]})"></span>')
        return "".join(out)

    text = re.sub(r"\{\{tiles ([^}]*)\}\}", tiles, text)
    left = re.findall(r"\{\{[^}]*\}\}", text)
    if left:
        raise SystemExit(f"{fname}: unresolved directives {left[:3]}")
    with open(os.path.join(ROOT, fname), "w", encoding="utf-8") as fh:
        fh.write(text)
    print("built", fname)


STRING_RE = re.compile(r'"(?:[^"\\]|\\.)*"|\'(?:[^\'\\]|\\.)*\'')


def minify_css(css):
    """Conservative minifier: strips comments and collapses whitespace outside
    string literals; never touches spaces around ':' (keeps `a :hover` safe)."""
    out, pos = [], 0
    for m in STRING_RE.finditer(css):
        out.append(("code", css[pos:m.start()]))
        out.append(("str", m.group(0)))
        pos = m.end()
    out.append(("code", css[pos:]))
    res = []
    for kind, chunk in out:
        if kind == "str":
            res.append(chunk)
            continue
        chunk = re.sub(r"/\*.*?\*/", "", chunk, flags=re.S)
        chunk = re.sub(r"\s+", " ", chunk)
        chunk = re.sub(r"\s*([{};,>])\s*", r"\1", chunk)
        res.append(chunk)
    return "".join(res).replace(";}", "}").strip()


def build_css():
    """tokens.css + main.css + v3.css -> one minified site.css (one render-blocking request)."""
    css_dir = os.path.join(ROOT, "assets", "css")
    parts = [open(os.path.join(css_dir, n), encoding="utf-8").read() for n in ("tokens.css", "main.css", "v3.css") if os.path.exists(os.path.join(css_dir, n))]
    banner = "/* GENERATED by scripts/build_prototype_html.py from tokens.css + main.css + v3.css (minified) — do not edit */\n"
    with open(os.path.join(css_dir, "site.css"), "w", encoding="utf-8") as fh:
        fh.write(banner + minify_css("\n".join(parts)) + "\n")
    print("built assets/css/site.css")


if __name__ == "__main__":
    build_css()
    for f in sorted(os.listdir(SRC)):
        if f.endswith(".html"):
            build_page(f)
