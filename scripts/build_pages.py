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
each variant's settings into the shape Elementor stores (see its header). The header and footer are the
masthead and colophon `prototype` variants; the masthead also marks «מסגרות המגורים» as the current section
on the house page, as the `house` variant and prototype/program-main-home.html do.

Tokens, resolved by `scripts/test_site.py pages` on deploy (the plugin's own link tokens — NH_Html::TOKENS:
#privacy #accessibility #sitemap #entrances #jobs-page — only name pages chosen in the plugin settings, so
they cannot name these two pages):
  @media:<name>      the attachment id of a photo of prototype/assets/img/manifest.json (media controls)
  @page:<key>[#x]    the permalink of one of our pages (get_permalink(); the home page is the front page)
  @page-id:<key>     the post id of one of our pages (the masthead's current_ids)
  @home:<path>       home_url(<path>) (control defaults the plugin computes with home_url())

The render check (plugin/tests/elementor_data.php check) renders every widget of the four files with the tokens
mapped back to the harness's values and compares it byte for byte with its fixture variant, then compares
each assembled page with the run.php assembly of pages.php.

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
# setting content_wrapper_html_tag, Theme_Document::print_elements_with_wrapper()); with the widgets' own
# <header>/<footer> roots (the prototype variants) that would nest two banner/contentinfo landmarks, so the
# templates set the wrapper to div: one landmark each, as in the prototype (SPEC D3).
CHROME = {
    "header": {
        "widget": "masthead", "variant": "prototype",
        # the `house` fixture variant's current_ids '10' (the harness's queried post = the house page) on nav row 1
        "extra": {"nav": {"1": {"current_ids": "@page-id:main-home"}}},
        "title": "כותרת עליונה — נווה האירוס", "slug": "nh-header", "document_type": "header",
    },
    "footer": {
        "widget": "colophon", "variant": "prototype", "extra": {},
        "title": "כותרת תחתונה — נווה האירוס", "slug": "nh-footer", "document_type": "footer",
    },
}

# The render check: for each page, the chrome fixture variants it must equal and how the tokens map back to the
# harness (its queried post is 10: on the house page the masthead's current_ids holds the queried post's id).
CHECK = {
    "dir": OUT,
    "chrome": {"header": "header", "footer": "footer"},
    "contexts": {
        "home": {"page": "page-home", "header_ref": "prototype", "footer_ref": "prototype",
                 "tokens": {"@page:main-home": "program-main-home.html", "@page:home": "index.html",
                            "@page-id:main-home": "11", "@page-id:home": "10"}},
        "main-home": {"page": "page-main-home", "header_ref": "house", "footer_ref": "prototype",
                      "tokens": {"@page:main-home": "program-main-home.html", "@page:home": "index.html",
                                 "@page-id:main-home": "10", "@page-id:home": "11"}},
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
    "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True},
}

HEX7 = re.compile(r"^[0-9a-f]{7}$")
TOKEN = re.compile(r"^@(?:media:[a-z0-9-]+|page:[a-z0-9-]+(?:#[A-Za-z0-9_-]*)?|page-id:[a-z0-9-]+|home:/\S*)$")


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
        docs[key] = {
            "key": key, "title": c["title"], "slug": c["slug"], "front_page": False,
            "post_type": "elementor_library", "document_type": c["document_type"], "template": "",
            "conditions": ["include/general"], "page_settings": {"content_wrapper_html_tag": "div"},
            "built_from": {"widgets": [f"{w['widget']}/{w['variant']}"],
                           "extra": c["extra"] or None, **source},
            "elementor_data": container(key, [widget_element(w, f"{key}/{w['widget']}")]),
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


def lint(docs):
    """Structure checks of the generated data; returns the media names used."""
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
        ids = [con["id"]]
        for el in con["elements"]:
            ids.append(el["id"])
            if list(el) != ["id", "elType", "settings", "elements", "widgetType"] or el["elType"] != "widget" \
                    or el["elements"] != [] or not el["widgetType"].startswith("nh-"):
                problems.append(f"{key}/{el.get('id')}: not a widget in Elementor's shape")
            for path, v in walk(el["settings"], f"{key}/{el['widgetType']}"):
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
        n = len(doc["elementor_data"][0]["elements"])
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
