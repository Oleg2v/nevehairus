#!/usr/bin/env python3
"""
Freeze the built v2 prototype into prototype/v2/ so it can be opened side by side
with later versions. Copies the built pages and the v2-only CSS/JS, rewrites
asset paths so shared fonts, icons and images are reused from ../assets/.

Usage (from the repo root, on a checkout where prototype/ is still v2):
    python3 -I scripts/freeze_v2_snapshot.py
"""
import os
import re
import shutil

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "prototype")
DST = os.path.join(ROOT, "v2")
PAGES = ["index.html", "program-main-home.html", "design-system.html"]
OWN = {  # v2-specific files copied into v2/assets (everything else is shared)
    "assets/css/site.css": "assets/css/site.css",
    "assets/js/site.js": "assets/js/site.js",
    "assets/js/motion.js": "assets/js/motion.js",
    "assets/js/home.js": "assets/js/home.js",
}


def main():
    os.makedirs(os.path.join(DST, "assets", "css"), exist_ok=True)
    os.makedirs(os.path.join(DST, "assets", "js"), exist_ok=True)
    for src, dst in OWN.items():
        shutil.copyfile(os.path.join(ROOT, src), os.path.join(DST, dst))
    # site.css refers to ../fonts and ../img relative to assets/css → point at the shared ones
    css_p = os.path.join(DST, "assets", "css", "site.css")
    css = open(css_p, encoding="utf-8").read()
    css = css.replace("url(../fonts/", "url(../../../assets/fonts/").replace('url("../fonts/', 'url("../../../assets/fonts/')
    css = css.replace("url(../img/", "url(../../../assets/img/").replace('url("../img/', 'url("../../../assets/img/')
    open(css_p, "w", encoding="utf-8").write(css)
    own_paths = {"assets/css/site.css", "assets/js/site.js", "assets/js/motion.js", "assets/js/home.js"}
    for page in PAGES:
        html = open(os.path.join(ROOT, page), encoding="utf-8").read()

        def fix(m):
            q, path = m.group(1), m.group(2)
            if path in own_paths:
                return q + path
            return q + "../" + path
        # attributes and JS strings that start with assets/
        html = re.sub(r'(["\s,(])(assets/[^"\s,)]+)', fix, html)
        html = html.replace('<meta name="robots" content="noindex, nofollow">',
                            '<meta name="robots" content="noindex, nofollow">\n<!-- frozen v2 snapshot: see prototype/v2/README.md -->')
        open(os.path.join(DST, page), "w", encoding="utf-8").write(html)
    with open(os.path.join(DST, "README.md"), "w", encoding="utf-8") as fh:
        fh.write(
            "# v2 «ב׳ — בית» — замороженная копия\n\n"
            "Собранная версия v2 (коммит `7c3cea6`) для сравнения с v3. Не пересобирается: шрифты и фото берутся из `../assets/`, "
            "собственные CSS и JS v2 лежат в `v2/assets/`. Исходники v2 — в истории git. "
            "Раскадровка v2 — в [`../storyboard/v2/`](../storyboard/v2/).\n"
        )
    print("frozen", len(PAGES), "pages into", DST)


if __name__ == "__main__":
    main()
