#!/usr/bin/env python3
"""Elementor data of the two prototype pages and the Theme Builder header and footer (step 4).

    python3 -I scripts/build_pages.py              write templates/pages/*.json, then run the render check
    python3 -I scripts/build_pages.py --no-check   write only
    python3 -I scripts/build_pages.py check        render check of the files as they are

Writes templates/pages/home.json, main-home.json, header.json and footer.json. Each file:
  key, title, slug, front_page, post_type, document_type (_elementor_template_type), template
  (_wp_page_template, '' = none), conditions (Theme Builder display conditions), page_settings
  (_elementor_page_settings), built_from (where the widgets come from, plus what the data was computed from:
  `plugin` = the plugin's version and the sha256 of its PHP files, `inputs_sha256` = those files with the
  harness, the fixtures, the photo manifest and this script) and elementor_data (the value of _elementor_data,
  as Elementor 4.4.0 stores it: one container holding the widgets). `scripts/test_site.py pages` refuses files
  whose inputs_sha256 is not this tree's, and its PHP step refuses a site whose plugin PHP is not the hashed one:
  Elementor ignores keys it does not know, and a repeater stored whole keeps the rows of the plugin it was built
  with, so data built against other widget code would deploy silently wrong.

The composition is the stub harness's: plugin/tests/fixtures/pages.php lists the widgets of each page and the
fixture variant that reproduces the prototype; plugin/tests/elementor_data.php (on the harness bootstrap) turns
each variant's settings into the shape Elementor stores (see its header). The header is the masthead's
`prototype` variant (its emblem: @media:logo-emblem); it also marks «הבתים שלנו» as the current section on the
house page, as the `house` variant and prototype/program-main-home.html do (aria-current="true").

v3.2 (research/07 §12.6): the footer is built here from Elementor's own widgets — Image (the emblem,
@media:logo-emblem), Heading, Text Editor — in containers (a grid of three columns: 5fr 3fr 4fr, two on tablets,
one on phones), plus the plugin's `nh-display` widget («מצב רגוע», the display fixture's `default` variant), so
the footer keeps its navigation, contacts and הצהרת נגישות when the plugin is off. Colours and type are set in
the widgets (the Kit's Theme Style stays empty), no Motion Effects, no Entrance Animations. The template's own
"HTML Tag" is `footer` (the landmark); the header template keeps `div` (the masthead is the landmark, SPEC D3).
In the footer's Text Editor HTML, `#privacy`, `#accessibility`, `#sitemap` and `#entrances` are the plugin's link
tokens and `@nh:email` the public e-mail: `scripts/test_site.py pages` resolves them on deploy from the plugin's
settings on the site (a list item with `@nh:email` is dropped while no public e-mail is set; an unset page stays an
in-page anchor), because the footer itself must not need the plugin.

Tokens, resolved by `scripts/test_site.py pages` on deploy (the plugin's own link tokens — NH_Html::TOKENS:
#privacy #accessibility #sitemap #entrances #jobs-page — only name pages chosen in the plugin settings, so
they cannot name these two pages):
  @media:<name>      the attachment id of a photo of prototype/assets/img/manifest.json (media controls), or of
                     the logo's emblem (`logo-emblem`, a manifest record with `file`: one PNG, alt "")
  @page:<key>[#x]    the permalink of one of our pages (get_permalink(); the home page is the front page)
  @page-id:<key>     the post id of one of our pages (the masthead's current_ids)
  @home:<path>       home_url(<path>) (control defaults the plugin computes with home_url())

The render check (plugin/tests/elementor_data.php check) renders every widget of ours in the four files with the
tokens mapped back to the harness's values and compares it byte for byte with its fixture variant, then compares
each assembled page with the run.php assembly of pages.php. The harness does not render Elementor's own widgets:
in the footer only `nh-display` is compared (the slot of pages.php's footer); the rest of the footer is checked by
lint() (shape, widget types, tokens) and on the site by `test_site.py pages` (every widget is on the page).

Python 3 standard library only. Never talks to a server.
"""
import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import urllib.parse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "templates", "pages")
TOOL = os.path.join(ROOT, "plugin", "tests", "elementor_data.php")
MANIFEST = os.path.join(ROOT, "prototype", "assets", "img", "manifest.json")
PLUGIN_DIR = os.path.join(ROOT, "plugin", "nevehairus-site")

# What the data is computed from (path, only files ending in ext or None = all): the widgets' controls and the
# core that registers them, the stub harness that merges settings, the fixtures, the photo manifest, this script.
INPUTS = (("plugin/nevehairus-site", ".php"), ("plugin/tests/bootstrap.php", None), ("plugin/tests/stubs", ".php"),
          ("plugin/tests/fixtures", ".php"), ("plugin/tests/elementor_data.php", None),
          ("prototype/assets/img/manifest.json", None), ("scripts/build_pages.py", None))

STUB_HOME = "https://nh.test"                 # home_url() of the harness (plugin/tests/stubs/wordpress.php)
PROTOTYPE_PAGES = {"index.html": "home", "program-main-home.html": "main-home"}   # prototype file -> page key

# Our pages: key -> fixtures/pages.php entry, WordPress title and slug (content/redirects.csv keeps the slugs)
PAGES = {
    "home": {"fixture": "page-home", "title": "דף הבית", "slug": "דף-הבית", "front_page": True},
    "main-home": {"fixture": "page-main-home", "title": "מעון ראשי – בית לחיים", "slug": "מעון-ראשי-בית-לחיים",
                  "front_page": False},
}

# Theme Builder templates (Elementor Pro): key -> widget, fixture variant, extra settings, template data.
# Pro 4.4.0 wraps a header template in <header> and a footer template in <footer> by default (the document
# setting content_wrapper_html_tag, Theme_Document::print_elements_with_wrapper()). The masthead's own root is
# <header> (its `prototype` variant), so the header template's wrapper is div: one banner landmark (SPEC D3). The
# footer is Elementor's own widgets (research/07 §12.6), so its template keeps the <footer> wrapper: the landmark.
CHROME = {
    "header": {
        "widget": "masthead", "variant": "prototype",
        # the `house` fixture variant's current_ids '11' (the house page) on nav row 0, «הבתים שלנו»
        "extra": {"nav": {"0": {"current_ids": "@page-id:main-home"}}},
        "title": "כותרת עליונה — נווה האירוס", "slug": "nh-header", "document_type": "header", "wrapper": "div",
    },
    "footer": {
        # the one widget of ours in the footer; the rest is built by footer_data()
        "widget": "display", "variant": "default", "extra": {},
        "title": "כותרת תחתונה — נווה האירוס", "slug": "nh-footer", "document_type": "footer", "wrapper": "footer",
    },
}

# Elementor's own widgets the footer may hold (lint()); pages and the header hold only widgets of ours.
FOOTER_WIDGETS = {"image", "heading", "text-editor"}

# The render check: for each page, the chrome fixture variants it must equal and how the tokens map back to the
# harness (the queried post of each page is pages.php's `queried`: 10 = the home page, 11 = the house page).
CHECK = {
    "dir": OUT,
    "chrome": {"header": "header", "footer": "footer"},
    "contexts": {
        "home": {"page": "page-home", "header_ref": "prototype", "footer_ref": "default",
                 "tokens": {"@page:main-home": "program-main-home.html", "@page:home": "index.html",
                            "@page-id:main-home": "11", "@page-id:home": "10"}},
        "main-home": {"page": "page-main-home", "header_ref": "house", "footer_ref": "default",
                      "tokens": {"@page:main-home": "program-main-home.html", "@page:home": "index.html",
                                 "@page-id:main-home": "11", "@page-id:home": "10"}},
    },
}

# One container per document (SPEC §10.3): Full Width, column, gap 0, padding 0 — control names of
# elementor/4.4.0 includes/elements/container.php (content_width; the `flex` group control's direction and gap
# → flex_direction, flex_gap; padding in the Advanced tab), values in the shape of Control_Gaps /
# Control_Dimensions::get_default_value().
CONTAINER_SETTINGS = {
    "container_type": "flex",
    "content_width": "full",
    "flex_direction": "column",
    "flex_gap": {"column": "0", "row": "0", "isLinked": True, "unit": "px"},
    # no wrapping at any width: Elementor's mobile default (wrap) on a column container made the widget
    # wrappers shorter or taller than the spreads they hold (the Day spread ran into the next one on phones)
    "flex_wrap": "nowrap",
    "flex_wrap_tablet": "nowrap",
    "flex_wrap_mobile": "nowrap",
    "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True},
}

HEX7 = re.compile(r"^[0-9a-f]{7}$")
TOKEN = re.compile(r"^@(?:media:[a-z0-9-]+|page:[a-z0-9-]+(?:#[A-Za-z0-9_-]*)?|page-id:[a-z0-9-]+|home:/\S*)$")
# inside the footer's Text Editor HTML only: the public e-mail (test_site.py pages, from the plugin's settings)
TEXT_TOKEN = re.compile(r"@[a-z][a-z0-9-]*:[A-Za-z0-9_#/-]*")
TEXT_TOKENS_OK = {"@nh:email"}

# The footer (research/07 §10.1 item 3, §12.6): the prototype's texts (prototype/src/partials/footer.html), the
# colours of tokens.css written out (the footer must not need the plugin's CSS variables).
NIGHT, PAPER, ON_NIGHT_2, CITRUS = "#1C1830", "#F7F1E7", "#CFC8DE", "#F2B33D"
DISPLAY, BODY = "Frank Ruhl Libre", "Assistant"
SR_EXTERNAL = '<span class="elementor-screen-only"> (נפתח באתר חיצוני)</span>'   # Elementor's own class: no plugin CSS needed
FOOTER_TEXT = {
    "about": "<p>בית לאנשים עם מוגבלות שכלית התפתחותית בנס ציונה, מאז 1957. בפיקוח משרד הרווחה והביטחון החברתי.</p>",
    # the phone, the fax, the address and Facebook are the plugin settings' defaults; the e-mail comes from the
    # settings on deploy (an address is never written in this repository)
    "contacts": ('<ul class="nh-footer-contacts">'
                 '<li>טלפון: <a href="tel:+97289403047"><span dir="ltr">08-9403047</span></a></li>'
                 '<li>פקס: <span dir="ltr">08-9400805</span></li>'
                 '<li>מזכירות: <a href="mailto:@nh:email"><span dir="ltr">@nh:email</span></a></li>'
                 '<li>ת״ד 28, נס ציונה</li>'
                 '<li><a href="https://www.facebook.com/neveirus/" rel="noopener">נווה האירוס בפייסבוק' + SR_EXTERNAL
                 + '</a></li></ul>'),
    # the menu's five items in its order (research/07 §5.1); root-relative: the sections of the home page
    "nav": ('<ul class="nh-footer-links">'
            '<li><a href="/#homes">הבתים שלנו</a></li>'
            '<li><a href="/#day">החיים אצלנו</a></li>'
            '<li><a href="/#story">מי אנחנו</a></li>'
            '<li><a href="/#jobs">דרושים</a></li>'
            '<li><a href="/#door">יצירת קשר</a></li></ul>'),
    # the plugin's link tokens, resolved on deploy from its settings; #nh-cookies opens the cookie panel (nh-core.js)
    "info": ('<ul class="nh-footer-links">'
             '<li><a href="#accessibility">הצהרת נגישות</a></li>'
             '<li><a href="#privacy">מדיניות פרטיות</a></li>'
             '<li><a href="#nh-cookies">הגדרות עוגיות</a></li>'
             '<li><a href="#sitemap">מפת אתר</a></li>'
             '<li><a href="#entrances">מפרט כניסות נגישות (PDF)</a></li></ul>'),
    # the short colophon (research/07 §10.1 item 3, ⊕): Latin names isolated as in the prototype
    "colophon": ('<p><b>קולופון.</b> גופנים: <span lang="en" dir="ltr">Frank Ruhl Libre</span> (יאנק יונטף) '
                 'ו־<span lang="en" dir="ltr">Assistant</span> (בן נתן ו־<span lang="en" dir="ltr">Adobe</span>), '
                 'ברישיון <span lang="en" dir="ltr">SIL OFL</span>. חמש אותיות שהשמות שלהן מילים של בית — ב׳, כ׳, ס׳, '
                 'ל׳ ו־ד׳ — משמשות כאן כאדריכלות. הכדים, המנדלה והינשופים — עבודות יד של דיירות ודיירי נווה האירוס. '
                 'הצילומים זמניים, ללא פנים מזוהות.</p>'),
    "copyright": "<p>© מעון נווה האירוס. כל הזכויות שמורות.</p>",   # the static fallback of the dynamic year
    "credit": ('<p>עיצוב ופיתוח: <a href="https://koranga.co.il/" rel="noopener">קורנגה' + SR_EXTERNAL + '</a></p>'),
}


def die(msg):
    raise SystemExit("STOP: " + msg)


def php(mode, spec):
    exe = shutil.which("php")
    if not exe:
        die("php not found (the harness needs PHP 8.x CLI)")
    res = subprocess.run([exe, TOOL, mode], input=json.dumps(spec, ensure_ascii=False), capture_output=True,
                         text=True, cwd=ROOT)
    return res


def element_id(seed):
    return hashlib.md5(seed.encode()).hexdigest()[:7]


def tree_files(path, ext=None):
    """The file path, or the files under the folder path (hidden files and folders skipped), ending in ext."""
    if os.path.isfile(path):
        return [path]
    out = []
    for base, dirs, files in os.walk(path):
        dirs[:] = [d for d in dirs if not d.startswith(".") and d != "__pycache__"]
        out += [os.path.join(base, f) for f in files if not f.startswith(".") and (ext is None or f.endswith(ext))]
    return out


def files_sha256(base, paths):
    """sha256 of the lines "<path relative to base, with />\\t<sha256 of the file>\\n", sorted by path. The PHP step
    of scripts/test_site.py pages computes the same over the plugin's *.php on the site (PAGES_PHP)."""
    rows = sorted((os.path.relpath(p, base).replace(os.sep, "/"), hashlib.sha256(open(p, "rb").read()).hexdigest())
                  for p in paths)
    return hashlib.sha256("".join(f"{rel}\t{digest}\n" for rel, digest in rows).encode()).hexdigest()


def source_info():
    """What templates/pages/*.json are built from: the plugin (version, sha256 of its PHP) and every input."""
    with open(os.path.join(PLUGIN_DIR, "nevehairus-site.php"), encoding="utf-8") as fh:
        m = re.search(r"^\s*\*\s*Version:\s*(\S+)", fh.read(), re.M)
    inputs = [f for rel, ext in INPUTS for f in tree_files(os.path.join(ROOT, rel), ext)]
    return {"plugin": {"version": m.group(1) if m else "",
                       "php_sha256": files_sha256(PLUGIN_DIR, tree_files(PLUGIN_DIR, ".php"))},
            "inputs_sha256": files_sha256(ROOT, inputs)}


def link_token(url, where):
    """A fixture link → a token: prototype pages → @page:, the harness's home_url() → @home:."""
    if url == STUB_HOME or url.startswith(STUB_HOME + "/"):
        return "@home:" + (url[len(STUB_HOME):] or "/")
    base, sep, frag = url.partition("#")
    if base in PROTOTYPE_PAGES:
        return f"@page:{PROTOTYPE_PAGES[base]}" + (sep + frag if sep else "")
    if re.search(r"\.html?$", base, re.I) or "nh.test" in url:
        die(f"{where}: link {url!r} points into the prototype or the harness, not to a page of the site")
    return url


def tokenize(value, where):
    """Links (the 'url' of URL controls) to tokens; media values are already tokens."""
    if isinstance(value, dict):
        out = {}
        for k, v in value.items():
            if k == "url" and isinstance(v, str) and v and value.get("source") != "library":
                out[k] = link_token(v, where)
            else:
                out[k] = tokenize(v, f"{where}/{k}")
        return out
    if isinstance(value, list):
        return [tokenize(v, f"{where}/{i}") for i, v in enumerate(value)]
    return value


def widget_element(w, where):
    settings = w["settings"] if isinstance(w["settings"], dict) else {}   # PHP writes [] for no settings
    return {"id": w["id"], "elType": "widget", "settings": tokenize(settings, where), "elements": [],
            "widgetType": "nh-" + w["widget"]}


def container(key, widgets):
    return [{"id": element_id(f"container/{key}"), "elType": "container", "settings": dict(CONTAINER_SETTINGS),
             "elements": widgets, "isInner": False}]


# ---- the footer of Elementor's own widgets (research/07 §12.6) ------------------------------------------------------
# Control names and value shapes of elementor/4.4.0: includes/elements/container.php (content_width, boxed_width,
# padding, grid_column; the `flex` and `grid` group controls → flex_direction, flex_gap, flex_wrap,
# flex_justify_content, grid_columns_grid, grid_rows_grid, grid_gaps; `background` → background_background,
# background_color; `border` → border_border, border_width, border_color), includes/widgets/image.php (image,
# image_size, width, height, align), heading.php (title, header_size, title_color), text-editor.php (editor,
# text_color, link_color), the `typography` group (typography_typography 'custom', typography_font_family, …), and
# core/dynamic-tags/manager.php (a dynamic value: settings.__dynamic__.<control> = '[elementor-tag id name
# settings=urlencode(json)]').

def px(top, right, bottom, left):
    return {"unit": "px", "top": str(top), "right": str(right), "bottom": str(bottom), "left": str(left),
            "isLinked": False}


def gap(column, row):
    return {"column": str(column), "row": str(row), "isLinked": False, "unit": "px"}


def typography(family, size_rem, weight, line_height):
    """Elementor's typography group, set in the widget (rem: the text follows the visitor's font size)."""
    return {"typography_typography": "custom", "typography_font_family": family,
            "typography_font_size": {"unit": "rem", "size": size_rem, "sizes": []},
            "typography_font_weight": str(weight),
            "typography_line_height": {"unit": "em", "size": line_height, "sizes": []}}


def el_widget(name, widget_type, settings):
    return {"id": element_id(f"footer/{name}"), "elType": "widget", "settings": settings, "elements": [],
            "widgetType": widget_type}


def el_container(name, settings, elements, inner=True):
    return {"id": element_id(f"footer/{name}"), "elType": "container", "settings": settings, "elements": elements,
            "isInner": inner}


def text_editor(name, html, color=ON_NIGHT_2, **extra):
    """A Text Editor in the footer: 16px Assistant, links in paper (the theme underlines them, 44px rows)."""
    return el_widget(name, "text-editor", {"editor": html, "text_color": color, "link_color": PAPER,
                                           **typography(BODY, 1, 400, 1.5), **extra})


def footer_heading(name, title):
    """«ניווט», «מידע ונגישות»: h2, Assistant 700 16px, citrus (research/07 §12.6)."""
    return el_widget(name, "heading", {"title": title, "header_size": "h2", "title_color": CITRUS,
                                       **typography(BODY, 1, 700, 1.3)})


def year_line():
    """«© {year} מעון נווה האירוס. …»: Elementor Pro's dynamic tag Current Date Time, format Y (§12.6)."""
    tag = {"date_format": "custom", "custom_format": "Y", "before": "© ",
           "after": " מעון נווה האירוס. כל הזכויות שמורות."}
    text = '[elementor-tag id="{}" name="current-date-time" settings="{}"]'.format(
        element_id("footer/year/tag"),
        urllib.parse.quote_plus(json.dumps(tag, ensure_ascii=False, separators=(",", ":"))))
    w = text_editor("year", FOOTER_TEXT["copyright"])
    w["settings"]["__dynamic__"] = {"editor": text}
    return w


def footer_data(display):
    """The footer's Elementor data: one boxed container on night (1440px, the prototype's .nh-container), a grid
    of three columns, the colophon, and a bottom row (© year, the agency's credit). `display` is the nh-display
    element of the dump."""
    emblem = el_widget("emblem", "image", {
        "image": {"url": "", "id": "@media:logo-emblem", "size": "", "alt": "", "source": "library"},
        "image_size": "full", "align": "start",
        # 36px high straight on night, no plate (research/07 §6); 105×74 → 51px wide
        "width": {"unit": "px", "size": 51, "sizes": []}, "height": {"unit": "px", "size": 36, "sizes": []}})
    name = el_widget("name", "heading", {"title": "מעון נווה האירוס", "header_size": "p", "title_color": PAPER,
                                         **typography(DISPLAY, 1.5, 500, 1.15)})
    column = {"container_type": "flex", "content_width": "full", "flex_direction": "column", "flex_gap": gap(0, 12),
              "padding": px(0, 0, 0, 0)}
    brand = el_container("col-brand", {**column, "grid_column_tablet": "2", "grid_column_mobile": "1"},
                         [emblem, name, text_editor("about", FOOTER_TEXT["about"]),
                          text_editor("contacts", FOOTER_TEXT["contacts"])])
    nav = el_container("col-nav", dict(column), [footer_heading("nav-title", "ניווט"),
                                                 text_editor("nav", FOOTER_TEXT["nav"])])
    info = el_container("col-info", dict(column), [footer_heading("info-title", "מידע ונגישות"),
                                                   text_editor("info", FOOTER_TEXT["info"]), display])
    grid = el_container("grid", {
        "container_type": "grid", "content_width": "full",
        "grid_columns_grid": {"unit": "custom", "size": "5fr 3fr 4fr", "sizes": []},
        "grid_columns_grid_tablet": {"unit": "fr", "size": 2, "sizes": []},
        "grid_columns_grid_mobile": {"unit": "fr", "size": 1, "sizes": []},
        "grid_rows_grid": {"unit": "custom", "size": "auto", "sizes": []},
        "grid_gaps": gap(32, 40), "padding": px(0, 0, 0, 0)}, [brand, nav, info])
    bottom = el_container("bottom", {
        "container_type": "flex", "content_width": "full", "flex_direction": "row", "flex_wrap": "wrap",
        "flex_justify_content": "space-between", "flex_gap": gap(32, 4), "padding": px(20, 0, 0, 0),
        "border_border": "solid", "border_width": px(1, 0, 0, 0), "border_color": "rgba(247, 241, 231, 0.3)"},
        [year_line(), text_editor("credit", FOOTER_TEXT["credit"])])
    outer = {
        "container_type": "flex", "content_width": "boxed", "boxed_width": {"unit": "px", "size": 1440, "sizes": []},
        "flex_direction": "column", "flex_gap": gap(0, 32),
        "background_background": "classic", "background_color": NIGHT,
        # the prototype's .nh-footer: 4rem above, 2rem below; the side gutter of .nh-container (16 → 64px)
        "padding": px(64, 48, 32, 48), "padding_tablet": px(64, 32, 32, 32), "padding_mobile": px(64, 16, 32, 16)}
    return [el_container("outer", outer, [grid, text_editor("colophon", FOOTER_TEXT["colophon"]), bottom],
                         inner=False)]


def build():
    spec = {"chrome": {k: {"widget": c["widget"], "variant": c["variant"], "extra": c["extra"]}
                       for k, c in CHROME.items()}}
    res = php("dump", spec)
    if res.returncode != 0:
        die("plugin/tests/elementor_data.php dump failed:\n" + (res.stdout + res.stderr).strip()[-3000:])
    dump = json.loads(res.stdout)
    source = source_info()
    docs = {}
    for key, p in PAGES.items():
        src = dump["pages"].get(p["fixture"])
        if not src:
            die(f"plugin/tests/fixtures/pages.php has no page {p['fixture']}")
        widgets = [widget_element(w, f"{key}/{w['widget']}") for w in src["widgets"]]
        docs[key] = {
            "key": key, "title": p["title"], "slug": p["slug"], "front_page": p["front_page"],
            "post_type": "page", "document_type": "wp-page", "template": "elementor_header_footer",
            "conditions": [], "page_settings": {},
            "built_from": {"pages.php": p["fixture"], "prototype": src["prototype"],
                           "widgets": [f"{w['widget']}/{w['variant']}" for w in src["widgets"]], **source},
            "elementor_data": container(key, widgets),
        }
    for key, c in CHROME.items():
        w = dump["chrome"][key]
        element = widget_element(w, f"{key}/{w['widget']}")
        data = footer_data(element) if key == "footer" else container(key, [element])
        docs[key] = {
            "key": key, "title": c["title"], "slug": c["slug"], "front_page": False,
            "post_type": "elementor_library", "document_type": c["document_type"], "template": "",
            "conditions": ["include/general"], "page_settings": {"content_wrapper_html_tag": c["wrapper"]},
            "built_from": {"widgets": [f"{w['widget']}/{w['variant']}"]
                           + (sorted(FOOTER_WIDGETS) if key == "footer" else []),
                           "extra": c["extra"] or None, **source},
            "elementor_data": data,
        }
    return docs


def walk(value, path=""):
    """(path, value) for every leaf and container."""
    yield path, value
    if isinstance(value, dict):
        for k, v in value.items():
            yield from walk(v, f"{path}/{k}")
    elif isinstance(value, list):
        for i, v in enumerate(value):
            yield from walk(v, f"{path}/{i}")


def elements(el):
    """el and every element inside it, depth first."""
    yield el
    for child in el.get("elements") or []:
        yield from elements(child)


def lint(docs):
    """Structure checks of the generated data; returns the media names used. Pages and the header: one container
    holding only widgets of ours. The footer (research/07 §12.6): one container, inner containers inside it
    (isInner), Elementor's Image, Heading and Text Editor and widgets of ours; in its Text Editor HTML only the
    tokens test_site.py resolves there (@nh:email)."""
    manifest = json.load(open(MANIFEST, encoding="utf-8"))
    problems, media = [], set()
    for key, doc in docs.items():
        data = doc["elementor_data"]
        if len(data) != 1:
            problems.append(f"{key}: {len(data)} top-level elements, expected one container")
            continue
        con = data[0]
        if list(con) != ["id", "elType", "settings", "elements", "isInner"] or con["elType"] != "container" \
                or con["isInner"] is not False:
            problems.append(f"{key}: the top-level element is not a container in Elementor's shape")
        footer = key == "footer"
        ids = [con["id"]]
        for el in list(elements(con))[1:]:
            ids.append(el["id"])
            if el.get("elType") == "container":
                if not footer:
                    problems.append(f"{key}/{el.get('id')}: a nested container (only the footer has them)")
                elif list(el) != ["id", "elType", "settings", "elements", "isInner"] or el["isInner"] is not True:
                    problems.append(f"{key}/{el.get('id')}: not an inner container in Elementor's shape")
                continue
            wt = str(el.get("widgetType", ""))
            if list(el) != ["id", "elType", "settings", "elements", "widgetType"] or el["elType"] != "widget" \
                    or el["elements"] != [] or not (wt.startswith("nh-") or (footer and wt in FOOTER_WIDGETS)):
                problems.append(f"{key}/{el.get('id')}: not a widget in Elementor's shape, or not allowed here ({wt})")
            for path, v in walk(el["settings"], f"{key}/{wt}"):
                if isinstance(v, list):
                    for row in v:
                        if not isinstance(row, dict) or not HEX7.match(str(row.get("_id", ""))):
                            problems.append(f"{path}: a repeater row without a 7-hex _id")
                elif isinstance(v, dict) and v.get("source") == "library":
                    m = re.match(r"^@media:([a-z0-9-]+)$", str(v.get("id", "")))
                    if not m or m.group(1) not in manifest or list(v) != ["url", "id", "size", "alt", "source"]:
                        problems.append(f"{path}: media value {v!r} is not {{url, id: @media:<manifest name>, …}}")
                    else:
                        media.add(m.group(1))
                elif isinstance(v, str):
                    if v.startswith("@") and not TOKEN.match(v):
                        problems.append(f"{path}: unknown token {v!r}")
                    if "nh.test" in v or re.search(r"\.html?(?:#|$)", v):
                        problems.append(f"{path}: {v!r} points into the harness or the prototype")
                    if not v.startswith("@"):
                        for t in TEXT_TOKEN.findall(v):
                            if not (footer and wt == "text-editor" and t in TEXT_TOKENS_OK):
                                problems.append(f"{path}: token {t!r} inside a text (only @nh:email, in the footer)")
        bad = [i for i in ids if not HEX7.match(i)]
        if bad or len(set(ids)) != len(ids):
            problems.append(f"{key}: element ids must be unique 7-hex strings ({ids})")
    if problems:
        die("the generated data is not valid:\n  " + "\n  ".join(problems))
    return sorted(media)


def write(docs):
    os.makedirs(OUT, exist_ok=True)
    for key, doc in docs.items():
        path = os.path.join(OUT, f"{key}.json")
        with open(path, "w", encoding="utf-8") as fh:
            json.dump(doc, fh, ensure_ascii=False, indent=1)
            fh.write("\n")
        n = sum(1 for el in elements(doc["elementor_data"][0]) if el.get("elType") == "widget")
        print(f"· {os.path.relpath(path, ROOT)}: {doc['post_type']} {doc['document_type']}, {n} widget(s), "
              f"{os.path.getsize(path) / 1024:.0f} KB")


def check():
    for key in ("home", "main-home", "header", "footer"):
        if not os.path.isfile(os.path.join(OUT, f"{key}.json")):
            die(f"templates/pages/{key}.json is missing: run python3 -I scripts/build_pages.py")
    res = php("check", CHECK)
    print((res.stdout + res.stderr).rstrip())
    if res.returncode != 0:
        die("the render check failed (see above)")


def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0],
                                 formatter_class=argparse.RawDescriptionHelpFormatter, epilog=__doc__)
    ap.add_argument("cmd", nargs="?", choices=["build", "check"], default="build")
    ap.add_argument("--no-check", action="store_true", help="build: skip the render check")
    args = ap.parse_args()
    if args.cmd == "build":
        docs = build()
        media = lint(docs)
        write(docs)
        print(f"· photos referenced ({len(media)}): {', '.join(media)}")
        if args.no_check:
            return
    check()


if __name__ == "__main__":
    sys.exit(main())
