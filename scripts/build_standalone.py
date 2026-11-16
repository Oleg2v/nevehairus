"""Standalone copies of the prototype pages for viewing without a server.

Each page becomes one HTML file with its CSS, JS, fonts, the logo emblem and photos inlined (one WebP per
photo, up to 1080 px). The files open by double-click, as a mail attachment or in the Claude app. Links
between the three files keep working when they sit in one folder.

    python3 -I scripts/build_standalone.py [out_dir]          # default: prototype/standalone (git-ignored)

The originals stay the source of truth: rebuild the prototype first (scripts/build_prototype_html.py).
v3.2 (research/07): there is no lightbox and no palm swap any more, so the pages need nothing but their own
photos; the one soft appearance of the print photos works the same with inlined files.
Photos are temporary (see prototype/README.md): do not publish the standalone files.
"""
import base64
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
P = os.path.join(ROOT, "prototype")
OUT = sys.argv[1] if len(sys.argv) > 1 else os.path.join(P, "standalone")
PAGES = {  # source page -> standalone name
    "index.html": "nevehairus-v3.2-home.html",
    "program-main-home.html": "nevehairus-v3.2-main-home.html",
    "design-system.html": "nevehairus-v3.2-design-system.html",
}
manifest = json.load(open(os.path.join(P, "assets", "img", "manifest.json"), encoding="utf-8"))
_cache = {}


def data_uri(path, mime):
    if path not in _cache:
        with open(path, "rb") as fh:
            _cache[path] = f"data:{mime};base64," + base64.b64encode(fh.read()).decode()
    return _cache[path]


def photo(name, cap=1080):
    widths = manifest[name]["widths"]
    w = max([x for x in widths if x <= cap] or widths[:1])
    return data_uri(os.path.join(P, "assets", "img", f"{name}-{w}.webp"), "image/webp")


css = open(os.path.join(P, "assets", "css", "site.css"), encoding="utf-8").read()
css = re.sub(r'url\("\.\./fonts/([^"]+)"\)',
             lambda m: 'url("' + data_uri(os.path.join(P, "assets", "fonts", m.group(1)), "font/woff2") + '")', css)
js_site = open(os.path.join(P, "assets", "js", "site.js"), encoding="utf-8").read()
js_v3 = open(os.path.join(P, "assets", "js", "v3.js"), encoding="utf-8").read()
for code in (css, js_site, js_v3):
    assert "</script" not in code and "</style" not in code


def build(src):
    s = open(os.path.join(P, src), encoding="utf-8").read()
    s = re.sub(r'<link rel="preload"[^>]*>\n?', "", s)
    s = s.replace('<link rel="stylesheet" href="assets/css/site.css">', "<style>" + css + "</style>")
    # the logo emblem (masthead and footer, research/07 §6)
    s = s.replace('src="assets/img/logo-emblem.png"', 'src="' + data_uri(os.path.join(P, "assets", "img", "logo-emblem.png"), "image/png") + '"')
    s = s.replace('<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">',
                  '<link rel="icon" href="' + data_uri(os.path.join(P, "assets", "img", "favicon.svg"), "image/svg+xml") + '">')
    s = s.replace('<script src="assets/js/site.js" defer></script>\n', "").replace('<script src="assets/js/v3.js" defer></script>\n', "")
    s = re.sub(r'<source type="image/avif" srcset="[^"]*" sizes="[^"]*">', "", s)

    def one_photo(m):
        tag = m.group(0)
        hit = re.search(r'src="assets/img/([\w-]+?)-\d+\.webp"', tag)
        if not hit:
            return tag
        tag = re.sub(r' (srcset|sizes)="[^"]*"', "", tag)
        return re.sub(r'src="assets/img/[^"]+"', 'src="' + photo(hit.group(1)) + '"', tag)
    s = re.sub(r"<img [^>]*>", one_photo, s)
    for a, b in PAGES.items():
        s = s.replace(f'href="{a}', f'href="{b}')
    s = s.replace("</body>", "<script>" + js_site + "</script>\n<script>" + js_v3 + "</script>\n</body>")
    s = s.replace("<!doctype html>\n", "<!doctype html>\n<!-- standalone copy for viewing without a server "
                  "(scripts/build_standalone.py); temporary photos, not for publishing -->\n", 1)
    left = sorted(set(re.findall(r'(?:src|href|srcset)="(assets/[^"]+)"', s)))
    assert not left, f"{src}: unresolved assets {left[:5]}"
    return s


os.makedirs(OUT, exist_ok=True)
for src, name in PAGES.items():
    html = build(src)
    with open(os.path.join(OUT, name), "w", encoding="utf-8") as fh:
        fh.write(html)
    print(f"{name:40s} {len(html.encode()) / 1024 / 1024:.1f} MB")
