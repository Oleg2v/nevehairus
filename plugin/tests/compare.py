#!/usr/bin/env python3
"""
Structural diff of a widget render against the built prototype (plugin/SPEC.md §11.4).

    python3 -I plugin/tests/compare.py <id> [variant]     (variant defaults to "default")
    python3 -I plugin/tests/compare.py <id> --all         every variant of the widget that has a compare target
    python3 -I plugin/tests/compare.py --tree FILE [SELECTOR]   print the normalised tree of an HTML file
    python3 -I plugin/tests/compare.py --prototype-letters      JSON of every letter directive of
                                          prototype/src/*.html with the build script's own output
                                          (used by plugin/tests/core/letters.test.php)

Reads out/<id>--<variant>.html and out/<id>--<variant>.json (both written by run.php), selects the
target elements in both pages (simple selectors tag#id.class, or descendant selectors of them; in the
plugin output a descendant selector falls back to its last part) and compares normalised trees, one
node per line:

    tag.class.class [attr=value] [attr] …     an element: classes sorted, attributes sorted by name
    "text"                                    a text node, whitespace collapsed
    (indented by two spaces per depth)

Normalisation: data-nh-pic (NH_Picture's marker of its img, taken away on the site in the_content) is dropped;
attributes id, for, aria-labelledby, aria-describedby, aria-controls, src, srcset, sizes,
width, height, action, data-vt, data-src, data-srcset, data-avif, data-name, data-work, data-full,
data-caption-plain, data-count, data-running-plain are compared by presence only ([name]); href keeps
only its #fragment, or becomes URL; style keeps its custom properties sorted, numbers to 2 decimals,
and two lines that differ only in numbers within ±0.01 are equal. Ignored: input[type=hidden], script,
.nh-admin-note, comments. Whitespace-only text counts only in running text: between two inline
elements or texts, inside an element that has non-whitespace text of its own.

Exit 0 when every differing line ("-" prototype, "+" plugin) matches one of the fixture's expect_diff
regular expressions (re.search); 1 otherwise; 2 on usage or missing files.
"""
import difflib
import glob
import html
import importlib.util
import json
import os
import re
import sys
from html.parser import HTMLParser

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
OUT = os.path.join(HERE, "out")
PROTO = os.path.join(ROOT, "prototype")

VOID = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "source", "track", "wbr"}
INLINE = {"a", "abbr", "b", "bdi", "bdo", "cite", "code", "em", "i", "mark", "q", "s", "small", "span",
          "strong", "sub", "sup", "time", "u"}
PRESENCE = {"id", "for", "aria-labelledby", "aria-describedby", "aria-controls", "src", "srcset", "sizes",
            "width", "height", "action", "data-vt", "data-src", "data-srcset", "data-avif", "data-name",
            "data-work", "data-full", "data-caption-plain", "data-count", "data-running-plain"}
NUM = re.compile(r"-?\d+(?:\.\d+)?")


class Node:
    __slots__ = ("tag", "attrs", "children", "text", "parent")

    def __init__(self, tag=None, attrs=None, text=None, parent=None):
        self.tag, self.attrs, self.text, self.parent = tag, dict(attrs or {}), text, parent
        self.children = []


class Builder(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.root = Node("#root")
        self.stack = [self.root]

    def handle_starttag(self, tag, attrs):
        node = Node(tag, [(k, "" if v is None else v) for k, v in attrs], parent=self.stack[-1])
        self.stack[-1].children.append(node)
        if tag not in VOID:
            self.stack.append(node)

    def handle_startendtag(self, tag, attrs):
        node = Node(tag, [(k, "" if v is None else v) for k, v in attrs], parent=self.stack[-1])
        self.stack[-1].children.append(node)

    def handle_endtag(self, tag):
        for i in range(len(self.stack) - 1, 0, -1):
            if self.stack[i].tag == tag:
                del self.stack[i:]
                return

    def handle_data(self, data):
        self.stack[-1].children.append(Node(text=data, parent=self.stack[-1]))


def parse(path_or_html, is_html=False):
    b = Builder()
    b.feed(path_or_html if is_html else open(path_or_html, encoding="utf-8").read())
    b.close()
    return b.root


def walk(node):
    for c in node.children:
        if c.tag is not None:
            yield c
            yield from walk(c)


def matches(node, sel):
    m = re.fullmatch(r"([a-z0-9-]*)((?:[#.][\w-]+)*)", sel.strip())
    if not m:
        raise SystemExit(f"unsupported selector: {sel}")
    tag, rest = m.group(1), m.group(2)
    if tag and node.tag != tag:
        return False
    classes = node.attrs.get("class", "").split()
    for kind, name in re.findall(r"([#.])([\w-]+)", rest):
        if kind == "#" and node.attrs.get("id") != name:
            return False
        if kind == "." and name not in classes:
            return False
    return True


def ancestors_match(node, parts):
    """The ancestors of node match the simple selectors parts (outermost first), in order, gaps allowed."""
    i, n = len(parts) - 1, node.parent
    while i >= 0 and n is not None and n.tag != "#root":
        if matches(n, parts[i]):
            i -= 1
        n = n.parent
    return i < 0


def select(root, sel, loose=False):
    """First element matching tag#id.class, or a descendant selector of such parts ("footer.nh-footer
    .v3-switches"); with loose=True, retry without the tag name (and, for a descendant selector, with its
    last part only: the plugin renders a widget without the prototype's surroundings)."""
    parts = sel.split()
    if len(parts) > 1:
        for n in walk(root):
            if matches(n, parts[-1]) and ancestors_match(n, parts[:-1]):
                return n
        return select(root, parts[-1], loose=True) if loose else None
    for n in walk(root):
        if matches(n, sel):
            return n
    if loose and re.match(r"[a-z]", sel):
        bare = re.sub(r"^[a-z0-9-]+", "", sel)
        if bare:
            for n in walk(root):
                if matches(n, bare):
                    return n
    return None


def ignored(n):
    if n.tag is None:
        return False
    if n.tag == "script":
        return True
    if n.tag == "input" and n.attrs.get("type") == "hidden":
        return True
    return "nh-admin-note" in n.attrs.get("class", "").split()


def is_inline(n):
    if n is None:
        return False
    if n.tag is None:
        return n.text.strip() != ""
    return n.tag in INLINE


def norm_style(v):
    props = []
    for decl in v.split(";"):
        if ":" not in decl:
            continue
        k, val = decl.split(":", 1)
        k, val = k.strip(), " ".join(val.split())
        val = NUM.sub(lambda m: f"{float(m.group(0)):.2f}", val)
        props.append(f"{k}:{val}")
    return ";".join(sorted(props))


def norm_attr(k, v):
    if k in PRESENCE:
        return f"[{k}]"
    if k in ("class", "data-nh-pic"):   # data-nh-pic: NH_Picture's marker of its img, taken away in the_content
        return None
    if k == "href":
        return "[href=" + (v[v.index("#"):] if "#" in v else ("URL" if v else "")) + "]"
    if k == "style":
        return "[style=" + norm_style(v) + "]"
    if v == "":
        return f"[{k}]"
    return f"[{k}=" + " ".join(v.split()) + "]"


def lines_of(node, depth=0, out=None):
    out = [] if out is None else out
    cls = "".join("." + c for c in sorted(set(node.attrs.get("class", "").split())))
    attrs = [a for a in (norm_attr(k, v) for k, v in sorted(node.attrs.items())) if a]
    out.append("  " * depth + node.tag + cls + ("" if not attrs else " " + " ".join(attrs)))
    kids = [c for c in node.children if not ignored(c)]
    # a space between two elements matters only in running text (the element has text of its own);
    # between blocks, flex/grid items or an icon and its label it is formatting
    running = any(c.tag is None and c.text.strip() for c in kids)
    for i, c in enumerate(kids):
        prev = kids[i - 1] if i > 0 else None
        nxt = kids[i + 1] if i + 1 < len(kids) else None
        if c.tag is None:
            t = re.sub(r"\s+", " ", c.text)
            if not t.strip():
                if running and is_inline(prev) and is_inline(nxt):
                    out.append("  " * (depth + 1) + '" "')
                continue
            if not is_inline(prev):
                t = t.lstrip()
            if not is_inline(nxt):
                t = t.rstrip()
            out.append("  " * (depth + 1) + json.dumps(t, ensure_ascii=False))
        else:
            lines_of(c, depth + 1, out)
    return out


def same_within(a, b, tol=0.01):
    """Two lines equal except for numbers within ±tol."""
    if NUM.sub("#", a) != NUM.sub("#", b):
        return False
    na, nb = NUM.findall(a), NUM.findall(b)
    return len(na) == len(nb) and all(abs(float(x) - float(y)) <= tol + 1e-9 for x, y in zip(na, nb))


def diff(a, b):
    """Unified-diff lines between two normalised trees, numbers within tolerance treated as equal."""
    sm = difflib.SequenceMatcher(None, a, b, autojunk=False)
    out = []
    for op, i1, i2, j1, j2 in sm.get_opcodes():
        if op == "equal":
            continue
        if op == "replace" and i2 - i1 == j2 - j1 and all(same_within(a[i1 + k], b[j1 + k]) for k in range(i2 - i1)):
            continue
        out.append(f"@@ prototype {i1 + 1}-{i2} / plugin {j1 + 1}-{j2}")
        out += ["-" + x for x in a[i1:i2]]
        out += ["+" + y for y in b[j1:j2]]
    return out


def compare(wid, variant):
    base = os.path.join(OUT, f"{wid}--{variant}")
    if not os.path.exists(base + ".html") or not os.path.exists(base + ".json"):
        print(f"missing {base}.html/.json: run `php plugin/tests/run.php --widget={wid}` first")
        return 2
    meta = json.load(open(base + ".json", encoding="utf-8"))
    target = meta.get("compare")
    if not target:
        print(f"{wid} {variant}: no compare target in the fixture (nothing to compare)")
        return 0
    expect = meta.get("expect_diff") or []
    rx = []
    for e in expect:
        try:
            rx.append((e, re.compile(e)))
        except re.error as ex:
            print(f"expect_diff {e!r} is not a valid regular expression ({ex})")
            return 1
    proto_root = parse(os.path.join(PROTO, target["page"]))
    ours_root = parse(base + ".html")
    all_diff, failed = [], False
    for sel in target.get("selectors", []):
        p = select(proto_root, sel)
        o = select(ours_root, sel, loose=True)
        if p is None:
            print(f"selector {sel!r} not found in prototype/{target['page']}")
            return 2
        if o is None:
            print(f"selector {sel!r} not found in the plugin output")
            all_diff.append(f"-{sel}")
            failed = True
            continue
        all_diff += [f"=== {sel}"] + diff(lines_of(p), lines_of(o))
    changed = [d for d in all_diff if d[:1] in "+-"]
    unexpected = [d for d in changed if not any(r.search(d) for _, r in rx)]
    unused = [e for e, r in rx if not any(r.search(d) for d in changed)]
    print(f"{wid} {variant} vs prototype/{target['page']} {', '.join(target.get('selectors', []))}")
    if changed:
        print("\n".join(all_diff))
    print(f"-- {len(changed)} differing line(s), {len(unexpected)} not covered by expect_diff")
    for u in unused:
        print(f"   note: expect_diff {u!r} matched no line")
    if unexpected:
        print("UNEXPECTED:")
        print("\n".join("   " + u for u in unexpected))
    return 1 if (unexpected or failed) else 0


def prototype_letters():
    """Every {{letterroom}}, {{letter}} and {{doorway}} of prototype/src/*.html with the build output."""
    spec = importlib.util.spec_from_file_location("nh_proto_build", os.path.join(ROOT, "scripts", "build_prototype_html.py"))
    build = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(build)
    attr = re.compile(r'(\w+)="([^"]*)"')
    items = []
    for page in sorted(glob.glob(os.path.join(PROTO, "src", "*.html"))):
        text = open(page, encoding="utf-8").read()
        for m in re.finditer(r"\{\{(letterroom|letter|doorway)( [^}]*)?\}\}", text):
            kind, attrs = m.group(1), dict(attr.findall(m.group(2) or ""))
            if kind == "letterroom":
                out = build.letter_room(dict(attrs))
            elif kind == "letter":
                out = build.letter_svg(dict(attrs))
            else:
                out = build.doorway({})
            extra = ""
            if attrs.get("extra"):
                extra = open(os.path.join(PROTO, "src", "partials", attrs["extra"] + ".html"), encoding="utf-8").read().strip()
            items.append({"page": os.path.basename(page), "kind": kind, "attrs": attrs, "extra": extra, "html": out})
    # synthetic: every letter at in-between weights and both frames (the interpolation of lerp_d())
    for name, e in build.letters.items():
        if not isinstance(e, dict):
            continue
        for wt in ("301", "333", "450", "555", "667", "799"):
            for frame in ("800", "300"):
                attrs = {"name": name, "w": wt, "frame": frame}
                items.append({"page": "synthetic", "kind": "letter", "attrs": attrs, "extra": "", "html": build.letter_svg(dict(attrs))})
    json.dump(items, sys.stdout, ensure_ascii=False)
    return 0


def main(argv):
    if not argv:
        print(__doc__)
        return 2
    if argv[0] == "--prototype-letters":
        return prototype_letters()
    if argv[0] == "--tree":
        root = parse(argv[1])
        node = select(root, argv[2], loose=True) if len(argv) > 2 else root
        if node is None:
            print("not found")
            return 2
        print("\n".join(lines_of(node) if node.tag != "#root" else sum((lines_of(c) for c in node.children if c.tag), [])))
        return 0
    wid = argv[0]
    if len(argv) > 1 and argv[1] == "--all":
        rc = 0
        for f in sorted(glob.glob(os.path.join(OUT, f"{wid}--*.json"))):
            meta = json.load(open(f, encoding="utf-8"))
            if meta.get("compare"):
                rc = max(rc, compare(wid, meta["variant"]))
                print()
        return rc
    return compare(wid, argv[1] if len(argv) > 1 else "default")


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
