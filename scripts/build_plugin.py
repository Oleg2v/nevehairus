#!/usr/bin/env python3
"""
Build, check and package the Elementor plugin `nevehairus-site` (plugin/SPEC.md §11.1).

    python3 -I scripts/build_plugin.py sync    copy the shared assets from the prototype into the plugin
    python3 -I scripts/build_plugin.py check   sync drift + structure + forbidden patterns + php -l
                                               + node --check + the stub harness (plugin/tests/run.php)
    python3 -I scripts/build_plugin.py zip     sync, check, then plugin/dist/nevehairus-site.zip (default)

Options: --phpcs (check/zip: also run PHPCS with WordPress-Extra and PHPCompatibility 8.0, when
`composer install` has been run in plugin/), --no-harness (check/zip: skip run.php, e.g. while a
widget package is half done; the zip is still refused if anything else fails).

Python 3 standard library only. Never talks to a server.
"""
import hashlib
import io
import os
import re
import shutil
import subprocess
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PROTO = os.path.join(ROOT, "prototype", "assets")
PLUGIN = os.path.join(ROOT, "plugin", "nevehairus-site")
TESTS = os.path.join(ROOT, "plugin", "tests")
DIST = os.path.join(ROOT, "plugin", "dist", "nevehairus-site.zip")

# source (relative to prototype/assets) -> destination (relative to the plugin), copied byte for byte
SYNC = [
    ("letters.json", "assets/letters.json"),
    ("css/tokens.css", "assets/css/tokens.css"),
    ("fonts/frank-ruhl-libre-core-var.woff2", "assets/fonts/frank-ruhl-libre-core-var.woff2"),
    ("fonts/frank-ruhl-libre-latin-var.woff2", "assets/fonts/frank-ruhl-libre-latin-var.woff2"),
    ("fonts/assistant-core-var.woff2", "assets/fonts/assistant-core-var.woff2"),
    ("fonts/assistant-latin-var.woff2", "assets/fonts/assistant-latin-var.woff2"),
    ("fonts/OFL-Assistant.txt", "assets/fonts/OFL-Assistant.txt"),
    ("fonts/OFL-FrankRuhlLibre.txt", "assets/fonts/OFL-FrankRuhlLibre.txt"),
]

SYNCED = {dst for _, dst in SYNC}

# files never shipped in the zip
ZIP_EXCLUDE = re.compile(r"(^|/)(\.DS_Store|Thumbs\.db|README-dev\.md)$|\.map$")

TEXT_EXT = (".php", ".css", ".js", ".json", ".txt", ".md", ".html", ".svg", ".mjs", ".py")

EMAIL = re.compile(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}")
EMAIL_OK = re.compile(r"@(?:[A-Za-z0-9-]+\.)*(?:example\.com|example\.test)$|\.invalid$", re.I)
FORBIDDEN = [
    (re.compile(r"assets/img/"), "assets/img/ (photos come from the media library)"),
    (re.compile(r"gsap", re.I), "gsap (no animation libraries)"),
    (re.compile(r"fonts\.googleapis"), "fonts.googleapis (fonts are served locally)"),
    (re.compile(r"fonts\.gstatic"), "fonts.gstatic (fonts are served locally)"),
    (re.compile(r"<script src=\"http"), "<script src=\"http (no remote scripts)"),
    (re.compile(r"cdn\."), "cdn. (no CDN)"),
]
# PHP >= 8.1 syntax and functions (the target is PHP 8.0; the local CLI is 8.3 and would not notice)
PHP81 = [
    (re.compile(r"\breadonly\s+(?:class|public|protected|private|function|static|\??[A-Za-z_\\][\w\\]*\s+\$)"), "readonly (PHP 8.1)"),
    (re.compile(r"^\s*enum\s+\w+", re.M), "enum (PHP 8.1)"),
    (re.compile(r"\)\s*:\s*never\b"), "never return type (PHP 8.1)"),
    (re.compile(r"[\w\]\)]\(\s*\.\.\.\s*\)"), "first-class callable syntax f(...) (PHP 8.1)"),
    (re.compile(r"\bconst\s+(?:int|string|float|bool|array|mixed|iterable|object|self|static|\?\w+)\s+[A-Z_][A-Z0-9_]*\s*="), "typed class constant (PHP 8.3)"),
    (re.compile(r"function\s+\w*\s*\([^)]*=\s*new\s+\\?[A-Za-z_]", re.S), "new in initializers (PHP 8.1)"),
    (re.compile(r"\b(?:array_is_list|json_validate|mb_str_pad|str_increment|str_decrement|array_find|array_any|array_all)\s*\("), "function from PHP >= 8.1"),
    (re.compile(r"\bfinal\s+(?:public\s+|protected\s+|private\s+)?const\b"), "final class constant (PHP 8.1)"),
]
GUARD = re.compile(r"defined\(\s*'ABSPATH'\s*\)\s*\|\|\s*exit\s*;")


def rel(path):
    return os.path.relpath(path, ROOT)


def sync():
    """Copy the shared assets; returns the number of files that changed."""
    changed = 0
    for src, dst in SYNC:
        s, d = os.path.join(PROTO, src), os.path.join(PLUGIN, dst)
        os.makedirs(os.path.dirname(d), exist_ok=True)
        if not os.path.exists(d) or open(s, "rb").read() != open(d, "rb").read():
            shutil.copyfile(s, d)
            changed += 1
            print("synced", rel(d))
    print(f"sync: {len(SYNC)} files, {changed} updated")
    return changed


def plugin_files():
    out = []
    for base, dirs, files in os.walk(PLUGIN):
        dirs[:] = sorted(d for d in dirs if not d.startswith("."))
        for f in sorted(files):
            out.append(os.path.join(base, f))
    return out


def strip_php_comments(code):
    """PHP code without comments and strings (for the syntax regexes)."""
    out, i, n = [], 0, len(code)
    while i < n:
        c = code[i]
        if code.startswith("//", i) or c == "#" and not code.startswith("#[", i):
            j = code.find("\n", i)
            i = n if j < 0 else j
        elif code.startswith("/*", i):
            j = code.find("*/", i + 2)
            i = n if j < 0 else j + 2
        elif c in "'\"":
            j = i + 1
            while j < n and code[j] != c:
                j += 2 if code[j] == "\\" else 1
            out.append(c + c)
            i = j + 1
        else:
            out.append(c)
            i += 1
    return "".join(out)


def check(run_harness=True, phpcs=False):
    errors = []
    err = errors.append

    # 1. synced files equal their sources
    for src, dst in SYNC:
        s, d = os.path.join(PROTO, src), os.path.join(PLUGIN, dst)
        if not os.path.exists(d):
            err(f"{dst}: missing (run: python3 -I scripts/build_plugin.py sync)")
        elif open(s, "rb").read() != open(d, "rb").read():
            err(f"{dst}: differs from prototype/assets/{src} (run sync; never edit the copy)")

    # 2. widgets: id, file name, class name, ID constant (SPEC §3.1)
    wdir = os.path.join(PLUGIN, "widgets")
    for f in sorted(os.listdir(wdir)) if os.path.isdir(wdir) else []:
        if not f.endswith(".php") or f.startswith("_"):
            continue
        wid = f[:-4]
        if not re.fullmatch(r"[a-z][a-z0-9-]*", wid):
            err(f"widgets/{f}: the id must match ^[a-z][a-z0-9-]*$")
            continue
        cls = "NH_Widget_" + "_".join(p.capitalize() for p in wid.split("-"))
        code = open(os.path.join(wdir, f), encoding="utf-8").read()
        if not re.search(r"\bclass\s+" + re.escape(cls) + r"\s+extends\s+NH_Widget_Base\b", code):
            err(f"widgets/{f}: expected `class {cls} extends NH_Widget_Base`")
        m = re.search(r"\bconst\s+ID\s*=\s*'([^']*)'", code)
        if not m or m.group(1) != wid:
            err(f"widgets/{f}: expected `public const ID = '{wid}';`")

    # 3–5. every file: guard, PHP 8.0 syntax, forbidden patterns
    for path in plugin_files():
        r = os.path.relpath(path, PLUGIN).replace(os.sep, "/")
        if not path.endswith(TEXT_EXT):
            continue
        text = open(path, encoding="utf-8", errors="replace").read()
        if path.endswith(".php"):
            head = text[:4000]
            if not text.startswith("<?php") or not GUARD.search(head):
                err(f"{r}: missing `defined( 'ABSPATH' ) || exit;` at the top")
            code = strip_php_comments(text)
            for rx, what in PHP81:
                if rx.search(code):
                    err(f"{r}: {what}")
        # synced files are byte-for-byte copies of the approved prototype (checked above): their comments may
        # name things the patterns look for (tokens.css compares its easing curves with GSAP's), so they are
        # exempt from the content patterns; the e-mail check still applies
        for rx, what in ([] if r in SYNCED else FORBIDDEN):
            for m in rx.finditer(text):
                line = text.count("\n", 0, m.start()) + 1
                err(f"{r}:{line}: forbidden pattern {what}")
        if not r.startswith("assets/fonts/OFL-"):   # third-party licence texts are copied verbatim
            for m in EMAIL.finditer(text):
                if not EMAIL_OK.search(m.group(0)):
                    line = text.count("\n", 0, m.start()) + 1
                    err(f"{r}:{line}: an e-mail address ({m.group(0)}) — addresses live in the settings, never in code")

    # 6. Version header = NH_VERSION
    main = open(os.path.join(PLUGIN, "nevehairus-site.php"), encoding="utf-8").read()
    hv = re.search(r"^\s*\*\s*Version:\s*(\S+)", main, re.M)
    cv = re.search(r"define\(\s*'NH_VERSION',\s*'([^']+)'", main)
    if not hv or not cv or hv.group(1) != cv.group(1):
        err("nevehairus-site.php: the Version header and NH_VERSION differ")

    # 7. php -l on the plugin and the tests
    php = shutil.which("php")
    if not php:
        err("php not found: cannot lint")
    else:
        targets = [p for p in plugin_files() if p.endswith(".php")]
        for base, dirs, files in os.walk(TESTS):
            dirs[:] = [d for d in dirs if d != "out"]
            targets += [os.path.join(base, f) for f in sorted(files) if f.endswith(".php")]
        for p in targets:
            res = subprocess.run([php, "-l", p], capture_output=True, text=True)
            if res.returncode != 0:
                err(f"php -l {rel(p)}: {(res.stdout + res.stderr).strip()}")
        print(f"php -l: {len(targets)} files")

    # 8. node --check on the plugin's scripts and shots.mjs
    node = shutil.which("node")
    if node:
        js = [p for p in plugin_files() if p.endswith(".js")] + [os.path.join(TESTS, "shots.mjs")]
        for p in js:
            if os.path.exists(p):
                res = subprocess.run([node, "--check", p], capture_output=True, text=True)
                if res.returncode != 0:
                    err(f"node --check {rel(p)}: {(res.stdout + res.stderr).strip()}")
        print(f"node --check: {len(js)} files")
    else:
        print("node not found: JS syntax not checked")

    # 9. the stub harness
    if run_harness and php:
        res = subprocess.run([php, os.path.join(TESTS, "run.php")], cwd=ROOT, capture_output=True, text=True)
        tail = "\n".join((res.stdout + res.stderr).strip().splitlines()[-25:])
        print(tail)
        if res.returncode != 0:
            err("plugin/tests/run.php failed (see above)")

    # 10. optional PHPCS (dev tools from plugin/composer.json)
    if phpcs:
        vendor = os.path.join(ROOT, "plugin", "vendor")
        bin_ = os.path.join(vendor, "bin", "phpcs")
        if not os.path.exists(bin_):
            err("--phpcs: run `composer install` in plugin/ first")
        else:
            paths = ",".join(os.path.join(vendor, p) for p in (
                "wp-coding-standards/wpcs", "phpcsstandards/phpcsutils", "phpcsstandards/phpcsextra",
                "phpcompatibility/php-compatibility", "phpcompatibility/phpcompatibility-paragonie",
                "phpcompatibility/phpcompatibility-wp"))
            # the spec's conventions: short arrays (§4.0 skeleton), widgets/<id>.php file names (§1)
            wpcs = ["--exclude=Universal.Arrays.DisallowShortArraySyntax,WordPress.Files.FileName",
                    "--runtime-set", "ignore_warnings_on_exit", "1"]
            for std, extra in (("WordPress-Extra", wpcs), ("PHPCompatibilityWP", ["--runtime-set", "testVersion", "8.0-"])):
                res = subprocess.run([php, bin_, "--standard=" + std, "--runtime-set", "installed_paths", paths,
                                      "--extensions=php", "-q", "--report=summary"] + extra + [PLUGIN],
                                     capture_output=True, text=True)
                print(f"phpcs {std}:", (res.stdout + res.stderr).strip()[-2000:] or "clean")
                if res.returncode != 0:
                    err(f"phpcs {std} reported problems")

    if errors:
        print(f"\ncheck: {len(errors)} problem(s)")
        for e in errors:
            print("  -", e)
        return False
    print("check: OK")
    return True


def build_zip():
    files = [p for p in plugin_files() if not ZIP_EXCLUDE.search(os.path.relpath(p, PLUGIN).replace(os.sep, "/"))]
    names = set()
    for p in files:
        parts = os.path.relpath(p, PLUGIN).replace(os.sep, "/").split("/")
        for i in range(1, len(parts)):
            names.add("/".join(parts[:i]) + "/")
    entries = sorted(["nevehairus-site/"] + ["nevehairus-site/" + d for d in names]
                     + ["nevehairus-site/" + os.path.relpath(p, PLUGIN).replace(os.sep, "/") for p in files])
    by_name = {"nevehairus-site/" + os.path.relpath(p, PLUGIN).replace(os.sep, "/"): p for p in files}
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for name in entries:
            info = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            info.create_system = 3
            if name.endswith("/"):
                info.external_attr = (0o40755 << 16) | 0x10
                z.writestr(info, b"")
            else:
                info.external_attr = 0o100644 << 16
                info.compress_type = zipfile.ZIP_DEFLATED
                z.writestr(info, open(by_name[name], "rb").read())
    data = buf.getvalue()
    os.makedirs(os.path.dirname(DIST), exist_ok=True)
    with open(DIST, "wb") as fh:
        fh.write(data)
    print(f"zip: {rel(DIST)} — {len(files)} files, {len(data):,} bytes, sha256 {hashlib.sha256(data).hexdigest()}")


def main(argv):
    args = [a for a in argv if not a.startswith("--")]
    flags = {a for a in argv if a.startswith("--")}
    cmd = args[0] if args else "zip"
    if cmd == "sync":
        sync()
        return 0
    if cmd == "check":
        return 0 if check(run_harness="--no-harness" not in flags, phpcs="--phpcs" in flags) else 1
    if cmd == "zip":
        sync()
        if not check(run_harness="--no-harness" not in flags, phpcs="--phpcs" in flags):
            print("zip: not built (check failed)")
            return 1
        build_zip()
        return 0
    print(__doc__)
    return 2


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
