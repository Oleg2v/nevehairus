"""Work on the TEST WordPress site only (nevehairus.ussl.co.il): check, snapshot, set up, deploy.

    python3 -I scripts/test_site.py check                  read-only: who am I, theme, plugins, key settings
    python3 -I scripts/test_site.py snapshot DIR           read-only: JSON copies of what later steps may change
    python3 -I scripts/test_site.py setup [--go]           step 0 of the README checklist (plan only without --go)
    python3 -I scripts/test_site.py deploy [ZIP] [--go]    install or update the nevehairus-site plugin
    python3 -I scripts/test_site.py pages [--go]           build the two pages + Theme Builder header/footer (step 4)
    python3 -I scripts/test_site.py rekey [--go]           new key for the safe switch (rewrites only that file)
    python3 -I scripts/test_site.py safe-off               emergency: deactivate the plugin without loading it
    python3 -I scripts/test_site.py cleanup                delete one-time snippets a failed run left behind
    python3 -I scripts/test_site.py health [--no-browser]  read-only: plugins unchanged, Elementor/Pro + licence,
                                                           every public page without PHP errors, browser console

Safety rules built in:
  * every request, including redirects, must stay on https://HOST; anything else stops the script;
  * the API key is never in this file or its output: the session's network proxy adds it to requests to HOST;
  * the safe-switch key and the one-time task token travel in request headers, never in URLs (no keys in logs);
  * nothing is deleted except what the script itself created (its snippet, its temporary zip uploads); but note that
    Elementor empties its own CSS file cache (uploads/elementor/css and the per-post CSS/asset meta) whenever any
    plugin is switched on or off, so switching Code Snippets on and off does that too; Elementor rebuilds it on the
    next page view;
  * every changed value is read first and written to the local change log (LOG_FILE, outside the repo).

Why some steps run PHP: core REST cannot install an uploaded plugin or theme zip, set "discourage search
engines" or change the permalink structure. For those steps the script activates the Code Snippets plugin
(installed from wordpress.org if missing) and adds one snippet that acts only on the request carrying a
one-time token (in a header) and only for 15 minutes; it answers with a JSON log on wp_loaded. The script
then deletes the snippet for good (trash + delete, checked) and switches Code Snippets off again.

setup: search engines discouraged (blog_public 0), time zone Asia/Jerusalem, permalinks /%postname%/ (checked
over HTTP with a real pretty URL and reverted if it fails), Hello Elementor from wordpress.org (if missing) and
the child theme from theme/hello-elementor-child (if missing), activated; ezCache development mode on and the
page cache purged; the must-use "safe switch" wp-content/mu-plugins/nh-safe-switch.php: a request with the
header X-NH-Safe-Key loads the site without the nevehairus-site plugin, and with ?nh_safe_off=1 also deactivates
it. Its key lives only in KEY_FILE (outside the repo); `rekey` makes a new one when a new session lost it.

deploy: the task request itself is made with the safe-switch key, so an older copy is not loaded; it deactivates
the old copy, installs the zip with WordPress's Plugin_Upgrader (replace), activates the new copy (which really
loads the new code: a fatal error there leaves it inactive), then smoke-tests the home page and the API. On any
failure it deactivates the plugin through the safe switch and reports the real final state.

pages: builds what templates/pages/*.json describe (made by scripts/build_pages.py; refused unless they were built
from this tree's plugin, fixtures and builder, and, with a local PHP, unless their render check passes): the home
page (front page), the house page and the Elementor Pro Theme Builder header and footer. (a) The photos those files
reference are uploaded once each (the largest .webp; WordPress makes the sub-sizes), titled nh:<name> with the
manifest's alt text; the logo's emblem (a manifest record with `file`, research/07 §11.2 item 5) is uploaded as
its PNG, as it is, with an empty alt text (it is decorative); a later run finds them by that title and reuses them;
the name -> id map is kept in templates/media-map.json. (b) The photo tokens get the attachment ids and URLs.
(c) One PHP step, acting as the
signed-in user, first checks that the plugin's PHP on the site is the one the files were built against and that
Elementor's Container feature is on; then it creates (as drafts) or updates the four posts, found by their post meta
_nh_page_key (no other post is ever touched), writes the Elementor data and meta, publishes them, regenerates the
Theme Builder conditions and sets the front page last; its answer carries every old value. The footer is Elementor's
own widgets (research/07 §12.6): what the plugin's colophon read from its settings at render time is read there
once, on this step — the plugin's link tokens in the footer's links (#privacy, #accessibility, #sitemap,
#entrances: the pages chosen in Settings → נווה האירוס; an unset one stays an in-page anchor) and the public e-mail
(@nh:email; its list item is left out while none is set). (d) ezCache is purged and both pages are smoke-tested:
HTTP 200 without PHP errors, every widget, the header (in a div) and the footer (in a footer element), no
unresolved token.
"""
import argparse
import base64
import hashlib
import http.client
import importlib.util
import io
import json
import os
import re
import secrets
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile

HOST = "nevehairus.ussl.co.il"          # the test site; the live site is never touched
BASE = f"https://{HOST}/"
ROOT = os.path.realpath(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PLUGIN_FILE = "nevehairus-site/nevehairus-site.php"
PLUGIN_ID = PLUGIN_FILE[:-4]             # REST id: folder/file without .php
HELPER_ID = "code-snippets/code-snippets"
CHILD_DIR = os.path.join(ROOT, "theme", "hello-elementor-child")
CONF = os.path.expanduser("~/.config/nevehairus")
KEY_FILE = os.environ.get("NH_SAFE_KEY_FILE", os.path.join(CONF, "test-site-safe-key"))
LOG_FILE = os.path.join(CONF, "test-site-changes.jsonl")
CRITICAL = ("critical error", "שגיאה קריטית", "Fatal error", "Parse error", "Warning</b>:", "Notice</b>:")


# --- HTTP, locked to the test host -------------------------------------------------------------------------
def _check_url(url):
    p = urllib.parse.urlsplit(url)
    if p.scheme != "https" or p.hostname != HOST or p.username or p.password or p.port not in (None, 443):
        raise SystemExit(f"STOP: refusing a request outside https://{HOST} (got {p.scheme}://{p.hostname})")


class _SameHostRedirects(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        _check_url(newurl)
        return super().redirect_request(req, fp, code, msg, headers, newurl)


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


_FOLLOW = urllib.request.build_opener(_SameHostRedirects)
_STAY = urllib.request.build_opener(_NoRedirect)


def url_for(route=None, query=None, path=""):
    q = {} if route is None else {"rest_route": route}
    q.update(query or {})
    url = BASE + path + ("?" + urllib.parse.urlencode(q) if q else "")
    _check_url(url)
    return url


def http(url, method="GET", data=None, headers=None, timeout=120, follow=True):
    """Return (status, headers with lower-case names, body). Status 0 = network error; never raises for HTTP."""
    _check_url(url)
    req = urllib.request.Request(url, data=data, method=method, headers=dict(headers or {}))
    try:
        with (_FOLLOW if follow else _STAY).open(req, timeout=timeout) as r:
            return r.status, {k.lower(): v for k, v in r.headers.items()}, r.read()
    except urllib.error.HTTPError as e:
        return e.code, {k.lower(): v for k, v in (e.headers or {}).items()}, e.read()
    except (urllib.error.URLError, OSError, http.client.HTTPException) as e:
        return 0, {}, f"network error: {type(e).__name__}".encode()


def api(route, method="GET", body=None, query=None, headers=None, timeout=120):
    """Call the WordPress REST API of the test site (works with plain and pretty permalinks)."""
    h, data = {"Accept": "application/json", **(headers or {})}, None
    if isinstance(body, (bytes, bytearray)):
        data = bytes(body)
    elif body is not None:
        data, h["Content-Type"] = json.dumps(body).encode(), "application/json"
    if method == "DELETE":                 # nginx on uPress answers 405 to DELETE; WordPress reads _method
        method, query = "POST", {**(query or {}), "_method": "DELETE"}
    st, hdr, raw = http(url_for(route, query), method, data, h, timeout)
    try:
        payload = json.loads(raw or b"null")
    except ValueError:
        payload = {"code": "non_json", "message": raw[:300].decode("utf-8", "replace")}
    return st, payload, hdr


def api_all(route, query=None):
    """All pages of a collection (per_page 100); (status, payload) on error."""
    out, page = [], 1
    while True:
        st, items, hdr = api(route, query=dict(query or {}, per_page="100", page=str(page)))
        if st != 200 or not isinstance(items, list):
            return out if out else (st, items)
        out += items
        if page >= int(hdr.get("x-wp-totalpages") or 1):
            return out
        page += 1


def need(ok, msg):
    if not ok:
        raise SystemExit("STOP: " + msg)


def step(msg):
    print("·", msg, flush=True)


def err(payload):
    return f"{payload.get('code')}: {payload.get('message')}" if isinstance(payload, dict) else str(payload)[:200]


def bust():
    return {"_": str(time.time_ns())}


def record(entry):
    """Append to the local change log (outside the repo)."""
    os.makedirs(CONF, mode=0o700, exist_ok=True)
    entry = dict(entry, at=time.strftime("%Y-%m-%d %H:%M:%S UTC", time.gmtime()), host=HOST)
    with open(LOG_FILE, "a", encoding="utf-8") as fh:
        fh.write(json.dumps(entry, ensure_ascii=False) + "\n")


# --- read-only ----------------------------------------------------------------------------------------------
def whoami(caps_needed=()):
    st, me, _ = api("/wp/v2/users/me", query={"context": "edit"})
    if st in (401, 403):
        raise SystemExit(f"STOP: the API key does not work ({st} {err(me)}): WordPress needs an application password")
    need(st == 200, f"the site answered {st} ({err(me)}); if the plugin broke it, run safe-off")
    caps = me.get("capabilities") or {}
    missing = [c for c in caps_needed if not caps.get(c)]
    need(not missing, f"this account lacks {', '.join(missing)}")
    return me


def front_state():
    """What visitors see: noindex meta on the home page, the theme in the HTML, the ?p= redirect style."""
    st, _, home = http(url_for(query=bust()))
    noindex = bool(re.search(rb"<meta[^>]+name=['\"]robots['\"][^>]+noindex", home))
    theme = re.search(rb"/wp-content/themes/([\w.-]+)/", home)
    st_p, hdr, _ = http(url_for(query={"p": "1", **bust()}), follow=False)
    return {"home": st, "noindex": noindex, "theme_in_html": theme.group(1).decode() if theme else None,
            "p1": st_p, "p1_location": hdr.get("location")}


def settings_state():
    st, s, _ = api("/wp/v2/settings")
    st_t, themes, _ = api("/wp/v2/themes", query={"status": "active"})
    st_d, dev, _ = api("/ezcache/v1/dev-mode")
    return {
        "timezone": s.get("timezone") if st == 200 else f"HTTP {st}",
        "active_theme": themes[0]["stylesheet"] if st_t == 200 and themes else f"HTTP {st_t}",
        "ezcache_dev_mode": dev.get("data", dev) if st_d == 200 and isinstance(dev, dict) else f"HTTP {st_d}",
    }


def cmd_check(_args):
    me = whoami()
    caps = me.get("capabilities") or {}
    step(f"signed in as user #{me['id']} ({', '.join(me.get('roles') or [])}); "
         + ", ".join(f"{c}={bool(caps.get(c))}" for c in ("install_plugins", "install_themes", "upload_files", "manage_options")))
    st, plugins, _ = api("/wp/v2/plugins")
    need(st == 200, f"cannot list plugins ({st})")
    for p in sorted(plugins, key=lambda p: p["plugin"]):
        print(f"   plugin {p['status']:8s} {p['plugin']:45s} {p.get('version', '')}")
    print("   settings:", settings_state())
    print("   front:", front_state())


def cmd_snapshot(args):
    target = os.path.realpath(args.dir)
    need(target != ROOT and not target.startswith(ROOT + os.sep), "keep snapshots outside the repo (client content)")
    out_dir = os.path.join(target, time.strftime("snapshot-%Y%m%d-%H%M%S", time.gmtime()))
    whoami()
    os.makedirs(out_dir, mode=0o700)
    jobs = {"settings": ("/wp/v2/settings", None), "themes": ("/wp/v2/themes", None), "plugins": ("/wp/v2/plugins", None),
            "ezcache-settings": ("/ezcache/v1/settings", None), "ezcache-dev-mode": ("/ezcache/v1/dev-mode", None),
            "types": ("/wp/v2/types", {"context": "edit"}), "taxonomies": ("/wp/v2/taxonomies", {"context": "edit"})}
    st, types, _ = api("/wp/v2/types", query={"context": "edit"})
    for slug, t in (types.items() if st == 200 and isinstance(types, dict) else []):
        if t.get("rest_base") and t.get("rest_namespace", "wp/v2") == "wp/v2" and slug != "attachment":
            q = {"context": "edit", **({"status": "any"} if slug in ("post", "page") else {})}
            jobs[f"collection-{slug}"] = (f"/wp/v2/{t['rest_base']}", q)
    jobs["collection-attachment"] = ("/wp/v2/media", {"context": "edit"})
    summary = {}
    for name, (route, q) in jobs.items():
        if name.startswith("collection-"):
            data = api_all(route, q)
            ok = not isinstance(data, tuple)
        else:
            st, data, _ = api(route, query=q)
            ok = st == 200
        if not ok:
            summary[name] = f"HTTP {data[0] if isinstance(data, tuple) else st}"
            continue
        with open(os.path.join(out_dir, f"{name}.json"), "w", encoding="utf-8") as fh:
            json.dump(data, fh, ensure_ascii=False, indent=1)
        summary[name] = len(data) if isinstance(data, list) else "ok"
    summary["front"], summary["state"] = front_state(), settings_state()
    with open(os.path.join(out_dir, "summary.json"), "w", encoding="utf-8") as fh:
        json.dump(summary, fh, ensure_ascii=False, indent=1)
    for k, v in summary.items():
        print(f"   {k:34s} {v}")
    step(f"snapshot written to {out_dir}")


# --- one-time PHP through Code Snippets ---------------------------------------------------------------------
WRAPPER = r"""// One-time task "__NH_LABEL__" from scripts/test_site.py (test site only). It acts only on the request that
// carries this task's token in the X-NH-Task header, only until __NH_EXPIRES__ (Unix time), and the script
// deletes the snippet right after.
add_action( 'wp_loaded', static function () {
	if ( time() > __NH_EXPIRES__ || ! isset( $_SERVER['HTTP_X_NH_TASK'] ) || ! is_string( $_SERVER['HTTP_X_NH_TASK'] )
		|| ! hash_equals( '__NH_TOKEN__', $_SERVER['HTTP_X_NH_TASK'] ) ) {
		return;
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 );
	}
	$log = array( 'nh_task' => '__NH_TOKEN__' );
	$att = isset( $_GET['nh_att'] ) ? array_map( 'absint', explode( ',', (string) $_GET['nh_att'] ) ) : array();
	try {
__NH_BODY__
		$log['done'] = true;
	} catch ( Throwable $e ) {
		$log['exception'] = get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
	}
	foreach ( $att as $id ) {
		if ( $id && get_post( $id ) && 'attachment' === get_post_type( $id ) ) {
			wp_delete_attachment( $id, true );
		}
	}
	for ( $i = ob_get_level(); $i > 0; $i-- ) {
		@ob_end_clean();
	}
	wp_send_json( $log );
}, 1 );
"""


class Helper:
    """Code Snippets: on for the duration of a command, then back to how it was."""

    def __init__(self):
        self.was = None

    def __enter__(self):
        st, plugins, _ = api("/wp/v2/plugins")
        need(st == 200, f"cannot list plugins ({st} {err(plugins)})")
        helper = next((p for p in plugins if p["plugin"] == HELPER_ID), None)
        self.was = helper["status"] if helper else "absent"
        if self.was == "absent":
            step("installing Code Snippets from wordpress.org (only to run the one-time PHP steps)")
            st, res, _ = api("/wp/v2/plugins", "POST", {"slug": "code-snippets", "status": "active"}, timeout=300)
            need(st in (200, 201), f"could not install Code Snippets ({st} {err(res)})")
            record({"change": "installed plugin code-snippets from wordpress.org (left inactive after use)", "version": res.get("version")})
        elif self.was != "active":
            st, res, _ = api(f"/wp/v2/plugins/{HELPER_ID}", "POST", {"status": "active"})
            need(st == 200, f"could not activate Code Snippets ({st} {err(res)})")
        return self

    def __exit__(self, *exc):
        if self.was != "active":
            for _ in range(3):
                st, res, _ = api(f"/wp/v2/plugins/{HELPER_ID}", "POST", {"status": "inactive"})
                if st == 200 and res.get("status") == "inactive":
                    step("Code Snippets switched off again")
                    break
                time.sleep(2)
            else:
                print("!! could not switch Code Snippets off; do it in Plugins (it is not needed between runs)")
        return False


def _delete_snippet(sid):
    """Code Snippets 4.1.0: the first DELETE moves to the trash, the second deletes; confirm with a GET."""
    api(f"/code-snippets/v1/snippets/{sid}/deactivate", "POST")
    st1 = st2 = st3 = None
    for _ in range(3):
        st3, snip, _ = api(f"/code-snippets/v1/snippets/{sid}")
        if st3 == 404:
            return True
        st1, _, _ = api(f"/code-snippets/v1/snippets/{sid}", "DELETE")     # trash (200), or delete if trashed (204)
        st2, _, _ = api(f"/code-snippets/v1/snippets/{sid}", "DELETE")     # delete from the trash (204)
        time.sleep(1)
    print(f"!! snippet #{sid} is not confirmed deleted (last answers {st1}/{st2}/{st3}); it expires by itself, "
          "but delete it in Snippets → Trash")
    return False


def run_php(label, body, uploads=(), extra_headers=None):
    """Run `body` (PHP that fills $log; $att holds the uploaded attachment IDs) once; return the JSON log."""
    token = secrets.token_hex(16)
    code = (WRAPPER.replace("__NH_LABEL__", label).replace("__NH_TOKEN__", token)
            .replace("__NH_EXPIRES__", str(int(time.time()) + 900)).replace("__NH_BODY__", body))
    st, snip, _ = api("/code-snippets/v1/snippets", "POST", {
        "name": f"nevehairus: {label} (temporary)", "desc": "Created and deleted by scripts/test_site.py.",
        "code": code, "scope": "global", "active": True})
    need(st in (200, 201) and isinstance(snip, dict) and snip.get("id"), f"could not create the snippet ({st} {err(snip)})")
    sid = int(snip["id"])
    step(f"created temporary snippet #{sid} ({label})")
    atts, log, st = [], {}, 0
    try:
        need(snip.get("active"), f"Code Snippets refused the code: {snip.get('code_error')}")
        for data, filename in uploads:
            st_u, media, _ = api("/wp/v2/media", "POST", data, headers={
                "Content-Type": "application/zip", "Content-Disposition": f'attachment; filename="{filename}"'}, timeout=300)
            need(st_u == 201, f"could not upload {filename} ({st_u} {err(media)})")
            atts.append(int(media["id"]))
        q = {**bust(), **({"nh_att": ",".join(map(str, atts))} if atts else {})}
        st, log, _ = api("/", query=q, headers={"X-NH-Task": token, **(extra_headers or {})}, timeout=300)
    finally:
        _delete_snippet(sid)
        for att in atts:                     # the PHP deletes them; this catches a step that never ran
            if api(f"/wp/v2/media/{att}", query={"context": "edit"})[0] == 200:
                api(f"/wp/v2/media/{att}", "DELETE", query={"force": "true"})
    need(st == 200 and isinstance(log, dict) and log.get("nh_task") == token,
         f"the one-time step did not run or did not answer ({st} {err(log) if isinstance(log, dict) else log})")
    log.pop("nh_task", None)
    if not log.pop("done", False):
        print("!! the one-time step stopped early:", json.dumps(log, ensure_ascii=False)[:600])
    return log


# --- safe switch ------------------------------------------------------------------------------------------------
SAFE_SWITCH = r"""<?php
/**
 * Plugin Name: nevehairus-site safe switch (test site)
 * Description: A request with the developer's key in the X-NH-Safe-Key header loads the site without the nevehairus-site plugin; with ?nh_safe_off=1 it also deactivates the plugin, with ?nh_safe_theme=1 it uses Twenty Twenty-Five for that request. Installed by scripts/test_site.py on the test site; delete this file to remove it.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
if ( isset( $_SERVER['HTTP_X_NH_SAFE_KEY'] ) && is_string( $_SERVER['HTTP_X_NH_SAFE_KEY'] )
	&& hash_equals( '__NH_HASH__', hash( 'sha256', $_SERVER['HTTP_X_NH_SAFE_KEY'] ) ) ) {
	$nh_safe_file = 'nevehairus-site/nevehairus-site.php';
	if ( isset( $_GET['nh_safe_off'] ) ) {
		$nh_safe_active = (array) get_option( 'active_plugins', array() );
		if ( in_array( $nh_safe_file, $nh_safe_active, true ) ) {
			update_option( 'active_plugins', array_values( array_diff( $nh_safe_active, array( $nh_safe_file ) ) ) );
		}
	}
	add_filter( 'option_active_plugins', static function ( $plugins ) use ( $nh_safe_file ) {
		return array_values( array_diff( (array) $plugins, array( $nh_safe_file ) ) );
	} );
	if ( isset( $_GET['nh_safe_theme'] ) ) {   // load a stock theme for this request, if the child or Hello breaks
		add_filter( 'pre_option_template', static function () { return 'twentytwentyfive'; } );
		add_filter( 'pre_option_stylesheet', static function () { return 'twentytwentyfive'; } );
	}
	if ( ! headers_sent() ) {
		header( 'X-NH-Safe: on' );
	}
}
"""

WRITE_SWITCH_PHP = r"""
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			wp_mkdir_p( WPMU_PLUGIN_DIR );
		}
		$nh_path            = WPMU_PLUGIN_DIR . '/nh-safe-switch.php';
		$log['safe_switch'] = false !== file_put_contents( $nh_path, base64_decode( '__NH_SAFE_B64__' ) );
		if ( function_exists( 'wp_opcache_invalidate' ) ) {
			wp_opcache_invalidate( $nh_path, true );
		}
"""


def read_key():
    need(os.path.isfile(KEY_FILE), f"no safe-switch key in {KEY_FILE}: run `rekey --go` (or setup) first")
    return open(KEY_FILE, encoding="utf-8").read().strip()


def save_key(key):
    os.makedirs(os.path.dirname(KEY_FILE), mode=0o700, exist_ok=True)
    tmp = KEY_FILE + ".tmp"
    fd = os.open(tmp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, "w", encoding="utf-8") as fh:
        fh.write(key)
    os.replace(tmp, KEY_FILE)


def safe_headers(key):
    return {"X-NH-Safe-Key": key}


def safe_switch_works(key):
    st, hdr, _ = http(url_for("/", bust()), headers=safe_headers(key))
    return st == 200 and hdr.get("x-nh-safe") == "on"


def switch_body(key):
    php = SAFE_SWITCH.replace("__NH_HASH__", hashlib.sha256(key.encode()).hexdigest())
    return WRITE_SWITCH_PHP.replace("__NH_SAFE_B64__", base64.b64encode(php.encode()).decode())


def install_switch(key):
    """Write the switch with a new key (the caller holds Helper); keep the old key file until it works."""
    log = run_php("safe switch", switch_body(key))
    need(log.get("safe_switch") is True, f"could not write the safe switch: {log}")
    for _ in range(5):
        if safe_switch_works(key):
            save_key(key)
            record({"change": "wrote wp-content/mu-plugins/nh-safe-switch.php (new key)"})
            return True
        time.sleep(3)
    raise SystemExit("STOP: the safe switch was written but does not answer to the new key (old key file kept)")


def cmd_rekey(args):
    whoami(("manage_options", "install_plugins"))
    if not args.go:
        print("rekey will write a new wp-content/mu-plugins/nh-safe-switch.php with a new key (Code Snippets is "
              "switched on for the step and off again). Run with --go.")
        return
    with Helper():
        install_switch(secrets.token_hex(24))
    step(f"safe switch works with the new key ({KEY_FILE})")


HEALTH_LAST = os.path.join(CONF, "test-site-health-last.json")


def cmd_health(args):
    """Read-only check after any change (agent off, plugin installed): plugins unchanged, Elementor and Pro
    active with a valid licence, every public page answers without PHP errors, browser console clean."""
    me = whoami()
    report, bad = {}, []
    st, plugins, _ = api("/wp/v2/plugins")
    need(st == 200, f"cannot list plugins ({st})")
    now = {p["plugin"]: [p["status"], p.get("version")] for p in plugins}
    report["plugins"] = now
    prev = json.load(open(HEALTH_LAST, encoding="utf-8")).get("plugins") if os.path.isfile(HEALTH_LAST) else None
    if prev is not None:
        changed = {k: (prev.get(k), now.get(k)) for k in set(prev) | set(now) if prev.get(k) != now.get(k)}
        report["plugins_changed_since_last_check"] = changed
    for slug in ("elementor/elementor", "elementor-pro/elementor-pro"):
        if now.get(slug, [None])[0] != "active":
            bad.append(f"{slug} is {now.get(slug)}")
    st, lic, _ = api("/elementor-pro/v1/license/get-license-status")
    st_f, feats, _ = api("/elementor-pro/v1/license/tier-features")
    report["pro_licence"] = {"status": lic if st == 200 else f"HTTP {st}",
                             "features": len(feats.get("features", [])) if st_f == 200 and isinstance(feats, dict) else f"HTTP {st_f}"}
    if not (st == 200 and isinstance(lic, dict) and lic.get("isExpired") is False):
        bad.append(f"Pro licence: {report['pro_licence']}")
    urls = [BASE]
    for route in ("/wp/v2/pages", "/wp/v2/posts"):
        items = api_all(route, {"status": "publish"})
        urls += [i["link"] for i in (items if isinstance(items, list) else [])]
    urls += [url_for(query={"s": "nh"}), url_for(path=f"nh-health-404-{secrets.token_hex(3)}/"), url_for(path="feed/"),
             url_for(path="wp-json/"), url_for(path="wp-login.php")]
    pages = {}
    for u in dict.fromkeys(urls):
        _check_url(u)
        st, _, body = http(u + ("&" if "?" in u else "?") + urllib.parse.urlencode(bust()))
        php = [c for c in CRITICAL if c.encode() in body]
        pages[u] = st if not php else f"{st} + {php}"
        expected = 404 if "nh-health-404" in u else 200
        if st != expected or php:
            bad.append(f"{u} -> {pages[u]}")
    report["pages"] = pages
    st_a, hdr, _ = http(url_for(path="wp-admin/"), follow=False)
    report["wp_admin"] = f"{st_a} -> {hdr.get('location', '')[:60]}"
    if not args.no_browser:
        browse = [u for u in dict.fromkeys(urls) if "wp-json" not in u and "feed/" not in u and "wp-login" not in u]
        import subprocess
        try:
            out = subprocess.run(["node", os.path.join(ROOT, "scripts", "console_check.mjs"), *browse],
                                 capture_output=True, text=True, timeout=600)
            data = json.loads(out.stdout or "{}")
        except Exception as e:  # noqa: BLE001
            data = {"error": f"{type(e).__name__}: {e}"}
        for k, v in data.items():          # the deliberate 404 page reports its own 404: expected, not a problem
            if isinstance(v, dict) and "nh-health-404" in k:
                v["console"] = [c for c in v["console"] if "status of 404" not in c]
                v["failed"] = [f for f in v["failed"] if "nh-health-404" not in f]
        noisy = {k: v for k, v in data.items() if isinstance(v, dict) and (v["console"] or v["pageErrors"] or v["failed"])}
        report["browser"] = noisy or ("clean" if data and "error" not in data else data)
        for k, v in noisy.items():
            bad.append(f"browser {k}: {len(v['console'])} console, {len(v['pageErrors'])} errors, {len(v['failed'])} failed")
    report["not_checked_here"] = ["the Elementor editor (needs a wp-admin browser login)", "PHP error logs (uPress panel)"]
    print(json.dumps(report, ensure_ascii=False, indent=1))
    with open(HEALTH_LAST, "w", encoding="utf-8") as fh:
        json.dump(report, fh, ensure_ascii=False, indent=1)
    record({"change": "health check (read-only)", "ok": not bad, "problems": bad})
    step(f"signed in as user #{me['id']}; {'ALL OK' if not bad else f'{len(bad)} problem(s): ' + '; '.join(bad)}")


def cmd_cleanup(_args):
    """Delete for good any one-time snippet this script left behind (names start with 'nevehairus:')."""
    whoami(("manage_options",))
    with Helper():
        found = []
        for q in ({}, {"status": "trashed"}):
            st, items, _ = api("/code-snippets/v1/snippets", query={"per_page": "100", **q})
            if st == 200 and isinstance(items, list):
                found += [i for i in items if str(i.get("name", "")).startswith("nevehairus:") and i["id"] not in [f["id"] for f in found]]
        for snip in found:
            ok = _delete_snippet(int(snip["id"]))
            step(f"snippet #{snip['id']} ({snip.get('name')}): {'deleted' if ok else 'NOT deleted'}")
        if not found:
            step("no leftover snippets")
        record({"change": "cleanup: deleted leftover one-time snippets", "ids": [f["id"] for f in found]})


def cmd_safe_off(_args=None):
    key = read_key()
    st, hdr, _ = http(url_for("/", {"nh_safe_off": "1", **bust()}), headers=safe_headers(key))
    on = hdr.get("x-nh-safe") == "on"
    st_p, p, _ = api(f"/wp/v2/plugins/{PLUGIN_ID}")
    status = p.get("status") if st_p == 200 else f"HTTP {st_p}"
    step(f"safe switch answered {st}, key {'accepted' if on else 'NOT accepted'}; plugin is now: {status}")
    if on:
        record({"change": "safe switch: nevehairus-site deactivated", "plugin_status": status})
    return on and status == "inactive"


# --- setup (step 0) --------------------------------------------------------------------------------------------
REPORT_PHP = r"""
		$log['blog_public']         = get_option( 'blog_public' );
		$log['permalink_structure'] = get_option( 'permalink_structure' );
		$log['stylesheet']          = get_stylesheet();
		$log['template']            = get_template();
		$log['child_exists']        = wp_get_theme( 'hello-elementor-child' )->exists();
		$log['parent_exists']       = wp_get_theme( 'hello-elementor' )->exists();
"""

SETUP_PHP = r"""
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		global $wp_rewrite;
		update_option( 'blog_public', '0' );
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		flush_rewrite_rules( false );
		if ( ! wp_get_theme( 'hello-elementor' )->exists() ) {
			$api = themes_api( 'theme_information', array( 'slug' => 'hello-elementor', 'fields' => array( 'sections' => false ) ) );
			if ( is_wp_error( $api ) ) {
				$log['parent'] = $api->get_error_message();
			} else {
				$skin          = new WP_Ajax_Upgrader_Skin();
				$res           = ( new Theme_Upgrader( $skin ) )->install( $api->download_link );
				$log['parent'] = is_wp_error( $res ) ? $res->get_error_message() : $res;
				$log['parent_errors'] = $skin->get_error_messages();
			}
		} else {
			$log['parent'] = 'already installed';
		}
		if ( ! wp_get_theme( 'hello-elementor-child' )->exists() ) {
			$zip = $att ? get_attached_file( $att[0] ) : '';
			if ( $zip && file_exists( $zip ) ) {
				$skin         = new WP_Ajax_Upgrader_Skin();
				$res          = ( new Theme_Upgrader( $skin ) )->install( $zip );
				$log['child'] = is_wp_error( $res ) ? $res->get_error_message() : $res;
				$log['child_errors'] = $skin->get_error_messages();
			} else {
				$log['child'] = 'zip not found';
			}
		} else {
			$log['child'] = 'already installed (left as it is)';
		}
		wp_clean_themes_cache();
		$child = wp_get_theme( 'hello-elementor-child' );
		if ( $child->exists() && ! $child->errors() && $child->parent() && $child->parent()->exists() ) {
			if ( 'hello-elementor-child' !== get_stylesheet() ) {
				switch_theme( 'hello-elementor-child' );
			}
		} else {
			$log['switch'] = 'not switched: child theme missing or broken';
		}
		$log['blog_public']         = get_option( 'blog_public' );
		$log['permalink_structure'] = get_option( 'permalink_structure' );
		$log['stylesheet']          = get_stylesheet();
		$log['template']            = get_template();
"""

REVERT_PERMALINKS_PHP = r"""
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '__NH_OLD__' );
		flush_rewrite_rules( false );
		$log['permalink_structure'] = get_option( 'permalink_structure' );
"""


def child_zip():
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as z:
        for name in sorted(os.listdir(CHILD_DIR)):
            z.write(os.path.join(CHILD_DIR, name), f"hello-elementor-child/{name}")
    return buf.getvalue()


def pretty_urls_work():
    """Pretty URLs must reach WordPress: /wp-json/ must answer the REST index (with plain permalinks it shows
    the home page), and a published post's pretty link, if there is one, must answer 200."""
    st, _, body = http(url_for(path="wp-json/", query=bust()))
    try:
        rest_ok = st == 200 and "namespaces" in json.loads(body)
    except ValueError:
        rest_ok = False
    if not rest_ok:
        return False, f"/wp-json/ -> {st}, not the REST index"
    st, posts, _ = api("/wp/v2/posts", query={"per_page": "1", **bust()})
    if st == 200 and posts:
        link = posts[0]["link"]
        if "?" in link:
            return False, f"links are not pretty: {link}"
        _check_url(link)
        st_l, _, _ = http(link + "?" + urllib.parse.urlencode(bust()))
        return st_l == 200, f"/wp-json/ ok; {link} -> {st_l}"
    return True, "/wp-json/ ok (no published post to test)"


SETUP_PLAN = """setup will change the test site:
  1. read and record: blog_public, permalinks, theme, time zone, ezCache dev mode, what visitors see
  2. discourage search engines (blog_public 0)
  3. permalinks /%postname%/ (reverted automatically if a pretty URL does not answer 200)
  4. Hello Elementor from wordpress.org if missing; hello-elementor-child from theme/ if missing; activate the child
  5. time zone Asia/Jerusalem
  6. ezCache development mode on (permanent; switch it off before handover) and purge the page cache
  7. write wp-content/mu-plugins/nh-safe-switch.php with a new key (kept in {key})
  Code Snippets is switched on for the PHP steps and off again; every old value goes to {log}.
Run again with --go to apply."""


def cmd_setup(args):
    me = whoami(("manage_options", "install_plugins", "install_themes", "switch_themes", "upload_files"))
    if not args.go:
        print(SETUP_PLAN.format(key=KEY_FILE, log=LOG_FILE))
        return
    step(f"signed in as user #{me['id']}")
    before = {"settings": settings_state(), "front": front_state()}
    with Helper():
        before["php"] = run_php("setup: read", REPORT_PHP)
        record({"change": "setup: values before", **before})
        print(json.dumps(before, ensure_ascii=False, indent=1))
        log = run_php("setup step 0", SETUP_PHP,
                      uploads=[(child_zip(), f"hello-elementor-child-{secrets.token_hex(4)}.zip")])
        record({"change": "setup: blog_public, permalinks, themes", "log": log})
        print(json.dumps(log, ensure_ascii=False, indent=1))
        need("exception" not in log, "the setup step threw an exception (see the log)")
        ok, why = pretty_urls_work()
        if ok:
            step(f"pretty permalinks work: {why}")
        else:
            old = (before["php"] or {}).get("permalink_structure", "")
            step(f"pretty permalinks do not work ({why}); reverting to {old!r}")
            rv = run_php("revert permalinks", REVERT_PERMALINKS_PHP.replace("__NH_OLD__", old.replace("'", "")))
            record({"change": "setup: permalinks reverted", "log": rv})
        install_switch(secrets.token_hex(24))
        step(f"safe switch works (key in {KEY_FILE})")
    st, s, _ = api("/wp/v2/settings", "POST", {"timezone": "Asia/Jerusalem"})
    step(f"time zone: {before['settings']['timezone']!r} -> {s.get('timezone') if st == 200 else f'FAILED {st} {err(s)}'}")
    st, dev, _ = api("/ezcache/v1/dev-mode", "POST", {"duration": "permanent"})
    step(f"ezCache development mode: {before['settings']['ezcache_dev_mode']} -> {'on (permanent)' if st == 200 else f'FAILED {st} {err(dev)}'}")
    st_c, _, _ = api("/ezcache/v1/cache", "DELETE")
    step(f"ezCache page cache purged ({st_c})")
    after = {"settings": settings_state(), "front": front_state()}
    record({"change": "setup: values after", **after})
    print(json.dumps(after, ensure_ascii=False, indent=1))
    need(after["front"]["home"] == 200, f"the home page answers {after['front']['home']} after setup")


# --- deploy ----------------------------------------------------------------------------------------------------------
DEPLOY_PHP = r"""
		remove_all_filters( 'option_active_plugins' );   // the safe switch kept the old copy unloaded; read the real list
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$file = 'nevehairus-site/nevehairus-site.php';
		$zip  = $att ? get_attached_file( $att[0] ) : '';
		if ( ! $zip || ! file_exists( $zip ) ) {
			throw new Exception( 'zip not found' );
		}
		$log['was_active'] = is_plugin_active( $file );
		if ( $log['was_active'] ) {
			deactivate_plugins( $file, true );
		}
		$skin            = new WP_Ajax_Upgrader_Skin();
		$result          = ( new Plugin_Upgrader( $skin ) )->install( $zip, array( 'overwrite_package' => true ) );
		$log['install']  = is_wp_error( $result ) ? $result->get_error_message() : $result;
		$log['errors']   = $skin->get_error_messages();
		wp_clean_plugins_cache();
		if ( true !== $result && $log['was_active'] && file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
			$back                 = activate_plugin( $file );
			$log['old_reactivated'] = is_wp_error( $back ) ? $back->get_error_message() : 'ok';
		}
		if ( true === $result && file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
			$active          = activate_plugin( $file );   // loads the new code: a fatal error stops here, inactive
			$log['activate'] = is_wp_error( $active ) ? $active->get_error_code() . ': ' . $active->get_error_message() : 'ok';
			$data            = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
			$log['version']  = $data['Version'];
		}
		$log['active_after'] = in_array( $file, (array) get_option( 'active_plugins', array() ), true );
"""


def smoke():
    """The home page and the API must answer without a PHP error."""
    problems = []
    st, _, body = http(url_for(query=bust()))
    if st != 200 or any(c.encode() in body for c in CRITICAL):
        problems.append(f"home page: HTTP {st}{' with a PHP error' if st == 200 else ''}")
    st, _, _ = api("/", query=bust())
    if st != 200:
        problems.append(f"REST index: HTTP {st}")
    st, _, _ = api("/wp/v2/users/me")
    if st != 200:
        problems.append(f"REST users/me: HTTP {st}")
    return problems


def plugin_status():
    st, p, _ = api(f"/wp/v2/plugins/{PLUGIN_ID}")
    return (p.get("status"), p.get("version")) if st == 200 else (f"HTTP {st}", None)


def cmd_deploy(args):
    zip_path = args.zip or os.path.join(ROOT, "plugin", "dist", "nevehairus-site.zip")
    need(os.path.isfile(zip_path), f"no zip at {zip_path}: build it first (python3 -I scripts/build_plugin.py zip)")
    with zipfile.ZipFile(zip_path) as z:
        names = z.namelist()
        need(PLUGIN_FILE in names, f"{zip_path} has no {PLUGIN_FILE}")
        head = z.read(PLUGIN_FILE).decode("utf-8", "replace")
    m = re.search(r"^\s*\*\s*Version:\s*(\S+)", head, re.M)
    version = m.group(1) if m else ""
    me = whoami(("manage_options", "install_plugins", "activate_plugins", "upload_files"))
    step(f"signed in as user #{me['id']}; zip {os.path.basename(zip_path)}: {len(names)} files, "
         f"{os.path.getsize(zip_path) / 1024:.0f} KB, version {version or '?'}; on the site now: {plugin_status()}")
    if not args.go:
        print("deploy will upload the zip (temporary), deactivate the old copy, install and activate the new one, "
              "smoke-test the site and roll back with the safe switch on any failure. Run with --go.")
        return
    key = read_key()
    need(safe_switch_works(key), "the safe switch does not answer to the saved key: run `rekey --go` first")
    before = smoke()
    need(not before, f"the site is not healthy before deploying: {before}")
    with open(zip_path, "rb") as fh:
        data = fh.read()
    log, failure = {}, None
    try:
        with Helper():
            log = run_php("deploy nevehairus-site", DEPLOY_PHP, uploads=[(data, f"nevehairus-site-{secrets.token_hex(4)}.zip")],
                          extra_headers=safe_headers(key))
        print(json.dumps(log, ensure_ascii=False, indent=1))
        if log.get("install") is not True or log.get("activate") != "ok":
            failure = f"install/activate: {log.get('install')} / {log.get('activate')}"
    except BaseException as e:                     # noqa: BLE001 - always fall through to the checks below
        failure = f"deploy step: {e}"
    api("/ezcache/v1/cache", "DELETE")
    problems = smoke()
    status, site_version = plugin_status()
    if failure or problems:
        step(f"FAILED ({failure or ''} {problems or ''}); plugin is {status}: switching it off with the safe switch")
        if status != "inactive":
            cmd_safe_off()
        status, _ = plugin_status()
        after = smoke()
        record({"change": "deploy failed", "version": version, "log": log, "failure": failure, "problems": problems,
                "plugin_status": status, "site_after": after})
        raise SystemExit(f"STOP: deploy failed; plugin is now {status}; site: {after or 'healthy'}")
    need(status == "active", f"plugin is {status} after deploy")
    need(not version or site_version == version, f"version on the site is {site_version}, expected {version}")
    record({"change": f"deployed nevehairus-site {site_version}", "log": log})
    step(f"done: nevehairus-site {site_version} is active on {HOST}; home page and API answer normally")


# --- pages (step 4) --------------------------------------------------------------------------------------------------
PAGES_DIR = os.path.join(ROOT, "templates", "pages")
PAGE_KEYS = ("home", "main-home", "header", "footer")
MEDIA_MAP = os.path.join(ROOT, "templates", "media-map.json")
IMG_DIR = os.path.join(ROOT, "prototype", "assets", "img")
MEDIA_TOKEN = re.compile(r"^@media:([a-z0-9-]+)$")
UNRESOLVED = re.compile(r"@(?:media|page|page-id|home|nh):")

# The one PHP step of `pages`. Our posts are the ones whose post meta _nh_page_key names a document; besides them it
# changes only Settings → Reading (the front page) and the Theme Builder conditions cache, which Pro rebuilds from every
# template's own conditions. The meta is what Elementor 4.4.0 writes when it saves a document (core/base/document.php:
# save_elements(), save_template_type() — for library documents also the elementor_library_type term —
# save_version(); Pro adds _elementor_pro_version on elementor/document/save_version) and what Pro 4.4.0's Theme
# Builder reads (_elementor_conditions, the option elementor_pro_theme_builder_conditions rebuilt by
# Conditions_Cache::regenerate()). Order: every check before the first write; a new post is a draft until its data is
# written; the front page is switched after all that, so a step that throws leaves the front page as it was (a post
# that is already published gets its new data in place, as Elementor's own save does). The answer has every old value.
PAGES_PHP = r"""
		$nh_docs   = json_decode( base64_decode( '__NH_DOCS_B64__' ), true );
		$nh_media  = json_decode( base64_decode( '__NH_MEDIA_B64__' ), true );
		$nh_author = __NH_AUTHOR__;
		if ( ! is_array( $nh_docs ) || 4 !== count( $nh_docs ) || ! is_array( $nh_media ) ) {
			throw new Exception( 'the page data did not decode' );
		}

		// The snippet runs on wp_loaded, before WordPress accepts the application password (it does so only once
		// REST_REQUEST is defined, at parse_request), so the request has no user: act as the user the script signed
		// in as, so that kses, capabilities and Elementor's meta sanitizers treat the writes as that user's own save.
		wp_set_current_user( $nh_author );
		$log['user'] = array(
			'id'              => get_current_user_id(),
			'unfiltered_html' => current_user_can( 'unfiltered_html' ),
		);
		if ( get_current_user_id() !== $nh_author || ! current_user_can( 'edit_pages' ) ) {
			throw new Exception( "user #$nh_author does not exist or cannot edit pages" );
		}
		if ( ! defined( 'ELEMENTOR_VERSION' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			throw new Exception( 'Elementor is not active' );
		}
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			throw new Exception( 'Elementor Pro is not active (the header and footer are Theme Builder templates)' );
		}
		if ( ! class_exists( 'NH_Plugin' ) || ! defined( 'NH_DIR' ) ) {
			throw new Exception( 'the nevehairus-site plugin is not active' );
		}
		$log['versions'] = array(
			'wordpress'       => get_bloginfo( 'version' ),
			'elementor'       => ELEMENTOR_VERSION,
			'elementor_pro'   => ELEMENTOR_PRO_VERSION,
			'nevehairus_site' => defined( 'NH_VERSION' ) ? NH_VERSION : null,
		);

		// Each page is one container, and Elementor 4.4.0 registers the container element only while its Container
		// feature is active (managers/elements.php; off by default on a site first installed before Elementor 3.16):
		// without it the pages would render empty.
		$nh_exp = isset( \Elementor\Plugin::$instance->experiments ) ? \Elementor\Plugin::$instance->experiments : null;
		if ( ! $nh_exp || ! $nh_exp->is_feature_active( 'container' ) ) {
			throw new Exception( 'the Elementor feature Container is not active (Elementor settings, Features): the pages are made of containers' );
		}

		// The plugin on the site must be the one templates/pages/*.json were built against (build_pages.py writes the
		// sha256 of its PHP files into built_from; files_sha256() there is this computation): Elementor ignores keys
		// it does not know, and a repeater stored whole keeps the rows of the plugin it was built with.
		$nh_dir   = rtrim( str_replace( '\\', '/', NH_DIR ), '/' );
		$nh_files = array();
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $nh_dir, FilesystemIterator::SKIP_DOTS ) ) as $nh_file ) {
			$nh_rel = substr( str_replace( '\\', '/', $nh_file->getPathname() ), strlen( $nh_dir ) + 1 );
			if ( $nh_file->isFile() && '.php' === substr( $nh_rel, -4 ) && ! preg_match( '#(^|/)(\.|__pycache__/)#', $nh_rel ) ) {
				$nh_files[ $nh_rel ] = hash_file( 'sha256', $nh_file->getPathname() );
			}
		}
		ksort( $nh_files, SORT_STRING );
		$nh_lines = '';
		foreach ( $nh_files as $nh_rel => $nh_hash ) {
			$nh_lines .= $nh_rel . "\t" . $nh_hash . "\n";
		}
		$nh_built      = isset( $nh_docs[0]['built_from']['plugin']['php_sha256'] ) ? (string) $nh_docs[0]['built_from']['plugin']['php_sha256'] : '';
		$log['plugin'] = array(
			'php_files'  => count( $nh_files ),
			'php_sha256' => hash( 'sha256', $nh_lines ),
		);
		if ( $log['plugin']['php_sha256'] !== $nh_built ) {
			throw new Exception( 'the PHP of nevehairus-site on the site is not the one templates/pages/*.json were built against (sha256 ' . substr( $nh_built, 0, 12 ) . '…): deploy the plugin of this tree, or rebuild the pages' );
		}

		// 0. Every photo is the image attachment the upload step titled nh:<name>.
		foreach ( $nh_media as $nh_name => $nh_att ) {
			if ( ! wp_attachment_is_image( (int) $nh_att ) || 'nh:' . $nh_name !== get_post_field( 'post_title', (int) $nh_att, 'raw' ) ) {
				throw new Exception( "photo $nh_name: attachment #$nh_att is not the image titled nh:$nh_name" );
			}
		}

		// The old values of what this step may change (our own posts: in step 1).
		$log['before']   = array(
			'show_on_front'            => get_option( 'show_on_front' ),
			'page_on_front'            => (int) get_option( 'page_on_front' ),
			'theme_builder_conditions' => get_option( 'elementor_pro_theme_builder_conditions' ),
			'docs'                     => array(),
		);
		$log['warnings'] = array();
		$log['docs']     = array();

		// 1. Our post of each document (post meta _nh_page_key); a new one is a draft until step 4. Its slug is the one
		// publishing gives it (WordPress makes a draft's slug unique only on publishing), so the links of step 2 hold.
		$nh_ids = array();
		foreach ( $nh_docs as $nh_doc ) {
			$nh_key  = (string) $nh_doc['key'];
			$nh_type = (string) $nh_doc['post_type'];
			if ( ! in_array( $nh_type, array( 'page', 'elementor_library' ), true ) ) {
				throw new Exception( "$nh_key: unexpected post type $nh_type" );
			}
			$nh_found = get_posts(
				array(
					'post_type'        => $nh_type,
					'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
					'meta_key'         => '_nh_page_key',
					'meta_value'       => $nh_key,
					'fields'           => 'ids',
					'posts_per_page'   => -1,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => true,
				)
			);
			$nh_id  = $nh_found ? (int) $nh_found[0] : 0;
			$nh_was = $nh_id ? get_post_status( $nh_id ) : null;
			if ( count( $nh_found ) > 1 ) {
				$log['warnings'][] = "$nh_key: several posts carry _nh_page_key $nh_key (" . implode( ', ', $nh_found ) . "); #$nh_id is used, the others are left as they are";
			}
			if ( ! $nh_id && 'page' === $nh_type ) {
				$nh_other = get_page_by_path( (string) $nh_doc['slug'], OBJECT, 'page' );
				if ( $nh_other ) {
					$log['warnings'][] = "$nh_key: the slug belongs to page #{$nh_other->ID} (not ours, left as it is); WordPress gives the new page another slug";
				}
			}
			if ( $nh_id ) {
				$log['before']['docs'][ $nh_key ] = array(
					'id'         => $nh_id,
					'status'     => $nh_was,
					'title'      => get_post_field( 'post_title', $nh_id, 'raw' ),
					'slug'       => urldecode( (string) get_post_field( 'post_name', $nh_id, 'raw' ) ),
					'template'   => get_post_meta( $nh_id, '_wp_page_template', true ),
					'type'       => get_post_meta( $nh_id, '_elementor_template_type', true ),
					'settings'   => get_post_meta( $nh_id, '_elementor_page_settings', true ),
					'conditions' => get_post_meta( $nh_id, '_elementor_conditions', true ),
					'data'       => get_post_meta( $nh_id, '_elementor_data', true ),
				);
			}
			if ( 'trash' === $nh_was ) {
				wp_untrash_post( $nh_id );   // WordPress restores it as a draft
			}
			$nh_arr = array(
				'post_type'  => $nh_type,
				'post_title' => (string) $nh_doc['title'],
				'post_name'  => wp_unique_post_slug( sanitize_title( (string) $nh_doc['slug'] ), $nh_id, 'publish', $nh_type, 0 ),
			);
			if ( $nh_id ) {
				$nh_arr['ID'] = $nh_id;
				$nh_res       = wp_update_post( wp_slash( $nh_arr ), true );
			} else {
				$nh_arr['post_status']    = 'draft';
				$nh_arr['post_author']    = $nh_author;
				$nh_arr['post_content']   = '';
				$nh_arr['comment_status'] = 'closed';
				$nh_arr['ping_status']    = 'closed';
				// Saved before save_post fires: Elementor's Source_Local::on_save_post() would type a library post
				// without _elementor_template_type as 'page'.
				$nh_arr['meta_input'] = array(
					'_nh_page_key'             => $nh_key,
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => (string) $nh_doc['document_type'],
				);
				$nh_res = wp_insert_post( wp_slash( $nh_arr ), true );
			}
			if ( is_wp_error( $nh_res ) || ! $nh_res ) {
				throw new Exception( "$nh_key: " . ( is_wp_error( $nh_res ) ? $nh_res->get_error_message() : 'the post was not saved' ) );
			}
			$nh_ids[ $nh_key ]      = (int) $nh_res;
			$log['docs'][ $nh_key ] = array(
				'id'     => (int) $nh_res,
				'type'   => $nh_type . '/' . $nh_doc['document_type'],
				'action' => $nh_id ? ( 'trash' === $nh_was ? 'restored from the trash, updated' : 'updated' ) : 'created',
				'was'    => $nh_was,
			);
		}

		// 2. The links of our pages: the front-page document's is home_url( '/' ) (it becomes the front page in step 6),
		// any other page's the permalink it has once published (the sample permalink: its slug is already final).
		// Step 7 checks them against the real permalinks.
		$nh_links = array();
		foreach ( $nh_docs as $nh_doc ) {
			if ( 'page' === $nh_doc['post_type'] ) {
				$nh_links[ $nh_doc['key'] ] = empty( $nh_doc['front_page'] ) ? get_page_link( $nh_ids[ $nh_doc['key'] ], false, true ) : home_url( '/' );
			}
		}

		// 3. Tokens: @page:<key>[#x] → the link of step 2, @page-id:<key> → post id, @home:<path> → home_url( <path> );
		// then the Elementor document of each post, as Elementor 4.4.0 saves one.
		$nh_resolve = static function ( $value ) use ( &$nh_resolve, &$nh_links, $nh_ids ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $k => $v ) {
					$value[ $k ] = $nh_resolve( $v );
				}
				return $value;
			}
			if ( ! is_string( $value ) || '' === $value || '@' !== $value[0] ) {
				return $value;
			}
			if ( preg_match( '/^@page:([a-z0-9-]+)(#[A-Za-z0-9_-]*)?$/', $value, $m ) && isset( $nh_links[ $m[1] ] ) ) {
				if ( ! is_string( $nh_links[ $m[1] ] ) || '' === $nh_links[ $m[1] ] ) {
					throw new Exception( "no permalink for $value" );
				}
				return $nh_links[ $m[1] ] . ( isset( $m[2] ) ? $m[2] : '' );
			}
			if ( preg_match( '/^@page-id:([a-z0-9-]+)$/', $value, $m ) && isset( $nh_ids[ $m[1] ] ) ) {
				return (string) $nh_ids[ $m[1] ];
			}
			if ( 0 === strpos( $value, '@home:/' ) ) {
				return home_url( substr( $value, 6 ) );
			}
			throw new Exception( "unresolved token $value" );
		};
		// The footer is Elementor's own widgets (research/07 §12.6): what the plugin's colophon read from its settings
		// on every view is read here, once. In the HTML of its Text Editors: the plugin's link tokens (#privacy,
		// #accessibility, #sitemap, #entrances → the pages chosen in Settings → נווה האירוס; an unset one stays an
		// in-page anchor, as in the plugin's own widgets) and the public e-mail (@nh:email; while none is set, the list
		// item that holds it is left out). Only strings of the footer document are touched.
		$nh_email  = is_callable( array( 'NH_Settings', 'email_public' ) ) ? (string) NH_Settings::email_public() : '';
		$nh_footer = static function ( $value ) use ( &$nh_footer, $nh_email ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $k => $v ) {
					$value[ $k ] = $nh_footer( $v );
				}
				return $value;
			}
			if ( ! is_string( $value ) || '' === $value ) {
				return $value;
			}
			if ( false !== strpos( $value, '@nh:email' ) ) {
				$value = '' !== $nh_email && is_email( $nh_email )
					? str_replace( '@nh:email', esc_html( $nh_email ), $value )
					: (string) preg_replace( '~<li>(?:(?!</li>).)*@nh:email(?:(?!</li>).)*</li>~su', '', $value );
			}
			return (string) preg_replace_callback(
				'~href="(#(?:privacy|accessibility|sitemap|entrances))"~',
				static function ( $m ) {
					return 'href="' . esc_url( NH_Html::resolve_token( $m[1] ) ) . '"';
				},
				$value
			);
		};
		$nh_write = static function ( $nh_doc ) use ( $nh_resolve, $nh_ids, $nh_footer ) {
			$nh_key  = (string) $nh_doc['key'];
			$nh_id   = $nh_ids[ $nh_key ];
			$nh_data = $nh_resolve( $nh_doc['elementor_data'] );
			if ( 'footer' === (string) $nh_doc['document_type'] ) {
				$nh_data = $nh_footer( $nh_data );
				if ( false !== strpos( (string) wp_json_encode( $nh_data ), '@nh:' ) ) {
					throw new Exception( "$nh_key: a token @nh:… is left in the footer" );
				}
			}
			$nh_json = wp_json_encode( $nh_data );
			if ( ! is_string( $nh_json ) || '' === $nh_json ) {
				throw new Exception( "$nh_key: the Elementor data did not encode" );
			}
			update_post_meta( $nh_id, '_elementor_data', wp_slash( $nh_json ) );
			update_post_meta( $nh_id, '_elementor_edit_mode', 'builder' );
			update_post_meta( $nh_id, '_elementor_template_type', (string) $nh_doc['document_type'] );
			update_post_meta( $nh_id, '_elementor_version', ELEMENTOR_VERSION );
			update_post_meta( $nh_id, '_elementor_pro_version', ELEMENTOR_PRO_VERSION );
			if ( '' !== (string) $nh_doc['template'] ) {
				update_post_meta( $nh_id, '_wp_page_template', (string) $nh_doc['template'] );
			}
			if ( ! empty( $nh_doc['page_settings'] ) ) {
				update_post_meta( $nh_id, '_elementor_page_settings', wp_slash( (array) $nh_doc['page_settings'] ) );
			}
			if ( 'elementor_library' === $nh_doc['post_type'] ) {
				$nh_terms = wp_set_object_terms( $nh_id, (string) $nh_doc['document_type'], 'elementor_library_type' );
				if ( is_wp_error( $nh_terms ) ) {
					throw new Exception( "$nh_key: elementor_library_type: " . $nh_terms->get_error_message() );
				}
				update_post_meta( $nh_id, '_elementor_conditions', wp_slash( (array) $nh_doc['conditions'] ) );
			}
			// This post's CSS and asset caches: Elementor rebuilds them on the next view.
			delete_post_meta( $nh_id, '_elementor_css' );
			delete_post_meta( $nh_id, '_elementor_element_cache' );
			delete_post_meta( $nh_id, '_elementor_page_assets' );
			if ( json_decode( (string) get_post_meta( $nh_id, '_elementor_data', true ), true ) !== $nh_data ) {
				return "$nh_key: _elementor_data does not read back equal to what was written";
			}
			return '';
		};
		foreach ( $nh_docs as $nh_doc ) {
			$nh_warn = $nh_write( $nh_doc );
			if ( '' !== $nh_warn ) {
				$log['warnings'][] = $nh_warn;
			}
		}

		// 4. Publish (the front page is still the old one).
		foreach ( $nh_docs as $nh_doc ) {
			$nh_id = $nh_ids[ $nh_doc['key'] ];
			if ( 'publish' !== get_post_status( $nh_id ) ) {
				$nh_res = wp_update_post( wp_slash( array( 'ID' => $nh_id, 'post_status' => 'publish' ) ), true );
				if ( is_wp_error( $nh_res ) || ! $nh_res ) {
					throw new Exception( $nh_doc['key'] . ': not published: ' . ( is_wp_error( $nh_res ) ? $nh_res->get_error_message() : 'the post was not saved' ) );
				}
			}
		}

		// 5. Theme Builder: Pro reads the templates of a location from the option elementor_pro_theme_builder_conditions,
		// which Conditions_Cache::regenerate() rebuilds from every template's _elementor_conditions (old value: before).
		$nh_tb    = class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ? \ElementorPro\Modules\ThemeBuilder\Module::instance() : null;
		$nh_cm    = ( $nh_tb && method_exists( $nh_tb, 'get_conditions_manager' ) ) ? $nh_tb->get_conditions_manager() : null;
		$nh_cache = ( $nh_cm && method_exists( $nh_cm, 'get_cache' ) ) ? $nh_cm->get_cache() : null;
		if ( $nh_cache && method_exists( $nh_cache, 'regenerate' ) ) {
			$nh_cache->regenerate();
			$log['conditions'] = 'cache regenerated';
		} else {
			$log['conditions'] = 'cache NOT regenerated: no Conditions_Cache::regenerate() in this Elementor Pro (open the templates in Theme Builder and save their display conditions)';
		}
		$nh_opt = get_option( 'elementor_pro_theme_builder_conditions' );
		foreach ( $nh_docs as $nh_doc ) {
			if ( 'elementor_library' !== $nh_doc['post_type'] ) {
				continue;
			}
			$nh_loc = (string) $nh_doc['document_type'];
			$nh_id  = $nh_ids[ $nh_doc['key'] ];
			$nh_all = ( is_array( $nh_opt ) && isset( $nh_opt[ $nh_loc ] ) && is_array( $nh_opt[ $nh_loc ] ) ) ? $nh_opt[ $nh_loc ] : array();
			$log['conditions_cache'][ $nh_doc['key'] ] = isset( $nh_all[ $nh_id ] ) ? $nh_all[ $nh_id ] : 'missing';
			$nh_others = array_diff( array_map( 'intval', array_keys( $nh_all ) ), array( $nh_id ) );
			if ( $nh_others ) {
				$log['warnings'][] = "$nh_loc: other Theme Builder templates have display conditions too (#" . implode( ', #', $nh_others ) . '); Pro may show one of those instead';
			}
		}

		// 6. Settings → Reading, once the posts, their data and the conditions are in place: the home document becomes
		// the front page.
		foreach ( $nh_docs as $nh_doc ) {
			if ( ! empty( $nh_doc['front_page'] ) ) {
				update_option( 'page_on_front', $nh_ids[ $nh_doc['key'] ] );
				update_option( 'show_on_front', 'page' );
			}
		}
		$log['front_after'] = array(
			'show_on_front' => get_option( 'show_on_front' ),
			'page_on_front' => (int) get_option( 'page_on_front' ),
		);

		// 7. The links as they are now: one that is not the link step 2 used (WordPress changed a slug on publishing):
		// the data is written again with the real one.
		$nh_moved = array();
		foreach ( $nh_links as $nh_key => $nh_url ) {
			$nh_now = get_permalink( $nh_ids[ $nh_key ] );
			if ( $nh_now !== $nh_url ) {
				$nh_moved[ $nh_key ]  = array( $nh_url, $nh_now );
				$nh_links[ $nh_key ] = $nh_now;
			}
		}
		if ( $nh_moved ) {
			$log['warnings'][] = 'a link was not the expected one, so the data was written again: ' . wp_json_encode( $nh_moved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			foreach ( $nh_docs as $nh_doc ) {
				$nh_warn = $nh_write( $nh_doc );
				if ( '' !== $nh_warn ) {
					$log['warnings'][] = $nh_warn;
				}
			}
		}

		// 8. Where each post is now; an old _elementor_data equal to the new one is not repeated in the answer.
		foreach ( $nh_docs as $nh_doc ) {
			$nh_key  = (string) $nh_doc['key'];
			$nh_id   = $nh_ids[ $nh_key ];
			$nh_now  = (string) get_post_meta( $nh_id, '_elementor_data', true );
			$nh_slug = urldecode( (string) get_post_field( 'post_name', $nh_id ) );
			$log['docs'][ $nh_key ]['status'] = get_post_status( $nh_id );
			$log['docs'][ $nh_key ]['slug']   = $nh_slug;
			$log['docs'][ $nh_key ]['url']    = get_permalink( $nh_id );
			$log['docs'][ $nh_key ]['edit']   = admin_url( 'post.php?post=' . $nh_id . '&action=elementor' );
			$log['docs'][ $nh_key ]['bytes']  = strlen( $nh_now );
			if ( $nh_slug !== (string) $nh_doc['slug'] ) {
				$log['warnings'][] = "$nh_key: the slug is $nh_slug, not {$nh_doc['slug']}";
			}
			if ( isset( $log['before']['docs'][ $nh_key ]['data'] ) && $log['before']['docs'][ $nh_key ]['data'] === $nh_now ) {
				$log['before']['docs'][ $nh_key ]['data'] = '(the same as now)';
			}
		}
"""


def build_pages_module():
    """scripts/build_pages.py as a module: its hashes of the inputs and its render check (it never talks to a server)."""
    spec = importlib.util.spec_from_file_location("nh_build_pages", os.path.join(ROOT, "scripts", "build_pages.py"))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def load_page_docs():
    """The four documents of templates/pages/ (scripts/build_pages.py), in PAGE_KEYS order, built from this tree."""
    docs = []
    for key in PAGE_KEYS:
        path = os.path.join(PAGES_DIR, f"{key}.json")
        need(os.path.isfile(path), f"no {os.path.relpath(path, ROOT)}: run python3 -I scripts/build_pages.py first")
        with open(path, encoding="utf-8") as fh:
            doc = json.load(fh)
        ok = (doc.get("key") == key and doc.get("post_type") in ("page", "elementor_library")
              and isinstance(doc.get("elementor_data"), list) and len(doc["elementor_data"]) == 1
              and all(isinstance(doc.get(k), str) for k in ("title", "slug", "document_type", "template")))
        need(ok, f"{os.path.relpath(path, ROOT)} is not a page document of scripts/build_pages.py")
        docs.append(doc)
    need(sum(1 for d in docs if d.get("front_page")) == 1, "exactly one document must be the front page")
    # What they were built from (plugin version and PHP hash, every input): the same for all four, and this tree's.
    built = [{k: (d.get("built_from") or {}).get(k) for k in ("plugin", "inputs_sha256")} for d in docs]
    need(all(b == built[0] for b in built) and isinstance(built[0]["plugin"], dict)
         and built[0]["plugin"].get("php_sha256") and built[0]["inputs_sha256"],
         "templates/pages/*.json do not all name the plugin and inputs they were built from: "
         "run python3 -I scripts/build_pages.py")
    need(built[0] == build_pages_module().source_info(),
         "templates/pages/*.json were built from another version of the plugin, its fixtures or scripts/build_pages.py "
         "than this tree has: run python3 -I scripts/build_pages.py")
    return docs


def render_check():
    """The render check of templates/pages/*.json (build_pages.py check, needs a local PHP CLI): its summary line,
    or None without PHP. Stops on any difference."""
    if not shutil.which("php"):
        return None
    bp = build_pages_module()
    res = bp.php("check", bp.CHECK)
    out = (res.stdout + res.stderr).strip()
    need(res.returncode == 0, "the render check of templates/pages/*.json fails (python3 -I scripts/build_pages.py "
         "check):\n" + "\n".join(line for line in out.splitlines() if not line.startswith("ok "))[-2000:])
    return out.splitlines()[-1] if out else "passed"


def media_tokens(value, found=None):
    """Names of the photos referenced as {"id": "@media:<name>"} in the documents."""
    found = set() if found is None else found
    if isinstance(value, dict):
        m = MEDIA_TOKEN.match(str(value.get("id", "")))
        if m:
            found.add(m.group(1))
        for v in value.values():
            media_tokens(v, found)
    elif isinstance(value, list):
        for v in value:
            media_tokens(v, found)
    return found


def widget_types(doc):
    """The widget types of a document, depth first (the footer's widgets sit in inner containers)."""
    out = []

    def walk(el):
        if el.get("elType") == "widget":
            out.append(el.get("widgetType"))
        for child in el.get("elements") or []:
            walk(child)
    walk(doc["elementor_data"][0])
    return out


def webp_size(path):
    """(width, height) from a WebP header (VP8X, VP8L or VP8), (0, 0) if unknown."""
    with open(path, "rb") as fh:
        head = fh.read(30)
    if len(head) < 30 or head[:4] != b"RIFF" or head[8:12] != b"WEBP":
        return 0, 0
    kind = head[12:16]
    if kind == b"VP8X":
        return 1 + int.from_bytes(head[24:27], "little"), 1 + int.from_bytes(head[27:30], "little")
    if kind == b"VP8L":
        bits = int.from_bytes(head[21:25], "little")
        return 1 + (bits & 0x3FFF), 1 + ((bits >> 14) & 0x3FFF)
    if kind == b"VP8 ":
        return int.from_bytes(head[26:28], "little") & 0x3FFF, int.from_bytes(head[28:30], "little") & 0x3FFF
    return 0, 0


def png_size(path):
    """(width, height) from a PNG header (IHDR), (0, 0) if it is not a PNG."""
    with open(path, "rb") as fh:
        head = fh.read(24)
    if len(head) < 24 or head[:8] != b"\x89PNG\r\n\x1a\n" or head[12:16] != b"IHDR":
        return 0, 0
    return int.from_bytes(head[16:20], "big"), int.from_bytes(head[20:24], "big")


def photo_files(names):
    """name -> the file to upload, its size, type and the manifest's alt text: the largest .webp of a photo, or, for a
    record with `file` (the logo's emblem, research/07 §11.2 item 5), that PNG as it is; an empty alt text only for a
    record marked decorative."""
    with open(os.path.join(IMG_DIR, "manifest.json"), encoding="utf-8") as fh:
        manifest = json.load(fh)
    out = {}
    for name in sorted(names):
        need(name in manifest, f"photo {name} is not in prototype/assets/img/manifest.json")
        rec = manifest[name]
        if "widths" not in rec and rec.get("file"):
            path = os.path.join(IMG_DIR, os.path.basename(str(rec["file"])))
            need(path.endswith(".png") and os.path.isfile(path), f"missing {os.path.relpath(path, ROOT)} (a PNG)")
            w, h = png_size(path)
            need((w, h) != (0, 0) and (not rec.get("width") or (w, h) == (int(rec["width"]), int(rec["height"]))),
                 f"{os.path.relpath(path, ROOT)} is not the {rec.get('width')}×{rec.get('height')} PNG the manifest names")
            alt = (rec.get("alt") or "").strip()
            need(alt or rec.get("decorative") is True, f"image {name} has no alt text and is not marked decorative")
            out[name] = {"path": path, "file": os.path.basename(path), "width": w, "height": h, "alt": alt,
                         "bytes": os.path.getsize(path), "type": "image/png", "ext": "png"}
            continue
        width = max(int(w) for w in rec["widths"])
        path = os.path.join(IMG_DIR, f"{name}-{width}.webp")
        need(os.path.isfile(path), f"missing {os.path.relpath(path, ROOT)}")
        w, h = webp_size(path)
        alt = (rec.get("alt") or "").strip()
        need(alt, f"photo {name} has no alt text in the manifest")
        out[name] = {"path": path, "file": os.path.basename(path), "width": w, "height": h, "alt": alt,
                     "bytes": os.path.getsize(path), "type": "image/webp", "ext": "webp"}
    return out


def load_media_map():
    if not os.path.isfile(MEDIA_MAP):
        return {}
    with open(MEDIA_MAP, encoding="utf-8") as fh:
        data = json.load(fh)
    return {str(k): int(v) for k, v in data.items()} if isinstance(data, dict) else {}


def save_media_map(ids):
    tmp = MEDIA_MAP + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump({k: ids[k] for k in sorted(ids)}, fh, indent=1)
        fh.write("\n")
    os.replace(tmp, MEDIA_MAP)


MEDIA_FIELDS = "id,title,alt_text,source_url,mime_type,media_details"


def our_media(media_map):
    """The attachments this script uploaded (title nh:<name>): name -> [attachments], lowest id first. The ids of
    media-map.json are read one by one (so a search that misses them cannot cause a second upload); a search for
    the title marker finds the rest."""
    out, seen = {}, set()

    def add(item):
        title = str((item.get("title") or {}).get("raw") or "").strip()
        if title.startswith("nh:") and int(item["id"]) not in seen and str(item.get("mime_type", "")).startswith("image/"):
            seen.add(int(item["id"]))
            out.setdefault(title[3:], []).append(item)
    for name, att in sorted(media_map.items()):
        st, item, _ = api(f"/wp/v2/media/{att}", query={"context": "edit", "_fields": MEDIA_FIELDS})
        if st == 200 and isinstance(item, dict) and item.get("id"):
            add(item)
    items = api_all("/wp/v2/media", {"search": "nh:", "media_type": "image", "context": "edit", "orderby": "id",
                                     "order": "asc", "_fields": MEDIA_FIELDS})
    need(not isinstance(items, tuple), f"cannot list the media library ({items[0] if isinstance(items, tuple) else ''})")
    for item in items:
        add(item)
    for group in out.values():
        group.sort(key=lambda i: int(i["id"]))
    return out


def pick_media(name, mine, media_map):
    """The attachment to reuse for a photo (the one in media-map.json if it still carries the title), or None."""
    items = mine.get(name, [])
    for item in items:
        if int(item["id"]) == media_map.get(name):
            return item
    return items[0] if items else None


def upload_photo(name, info, media_map):
    """Upload the photo with its title marker and alt text in the same request; WordPress makes the sub-sizes. The
    attachment goes to the change log and media-map.json as soon as WordPress names its id, before anything can fail."""
    with open(info["path"], "rb") as fh:
        data = fh.read()
    st, media, hdr = api("/wp/v2/media", "POST", data, query={"title": f"nh:{name}", "alt_text": info["alt"]},
                         headers={"Content-Type": info["type"],
                                  "Content-Disposition": f'attachment; filename="{name}.{info["ext"]}"'}, timeout=300)
    att = str((media.get("id") if st == 201 and isinstance(media, dict) else None)
              or hdr.get("x-wp-upload-attachment-id") or "").strip()
    need(att.isdigit() and int(att) > 0, f"could not upload {info['file']} ({st} {err(media)})")
    att = int(att)
    record({"change": f"uploaded photo {name}", "id": att, "file": info["file"], "title": f"nh:{name}",
            "alt": info["alt"], **({} if st == 201 else {"upload_answer": st})})
    media_map[name] = att
    save_media_map(media_map)
    if st != 201:
        # the attachment exists but the sub-sizes did not finish: let WordPress finish them (as the block editor does)
        for _ in range(3):
            st, media, _ = api(f"/wp/v2/media/{att}/post-process", "POST", {"action": "create-image-subsizes"},
                               timeout=300)
            if st in (200, 201):
                break
        need(st in (200, 201) and isinstance(media, dict) and media.get("id"), f"{info['file']} is attachment #{att} "
             f"(recorded; a later run reuses it), but its sub-sizes failed ({st} {err(media)})")
    if str((media.get("title") or {}).get("raw") or "") != f"nh:{name}" or media.get("alt_text") != info["alt"]:
        st, media, _ = api(f"/wp/v2/media/{media['id']}", "POST", {"title": f"nh:{name}", "alt_text": info["alt"]})
        need(st == 200, f"could not set the title and alt text of #{media.get('id')} ({st} {err(media)})")
    return media


def resolve_media(value, atts):
    """Photo tokens -> the attachment's id, URL and alt text (the shape Elementor stores for a library image)."""
    if isinstance(value, dict):
        out = {k: resolve_media(v, atts) for k, v in value.items()}
        m = MEDIA_TOKEN.match(str(value.get("id", "")))
        if m:
            out.update(id=atts[m.group(1)]["id"], url=atts[m.group(1)]["url"], alt=atts[m.group(1)]["alt"])
        return out
    if isinstance(value, list):
        return [resolve_media(v, atts) for v in value]
    return value


def pages_body(docs, media_ids, author):
    """The PHP of the page step, with the documents and the photo ids inside (base64 JSON: no quoting issues)."""
    def b64(obj):
        return base64.b64encode(json.dumps(obj, ensure_ascii=False, separators=(",", ":")).encode()).decode()
    return (PAGES_PHP.replace("__NH_DOCS_B64__", b64(docs)).replace("__NH_MEDIA_B64__", b64(media_ids))
            .replace("__NH_AUTHOR__", str(int(author))))


def lint_php(label, body):
    """php -l (local PHP CLI, if any) on the snippet exactly as run_php() wraps it — before anything is sent."""
    exe = shutil.which("php")
    if not exe:
        step("no local php: the PHP step is not linted here (Code Snippets still refuses code that does not parse)")
        return
    code = "<?php\n" + (WRAPPER.replace("__NH_LABEL__", label).replace("__NH_TOKEN__", "0" * 32)
                        .replace("__NH_EXPIRES__", "0").replace("__NH_BODY__", body))
    res = subprocess.run([exe, "-l"], input=code, capture_output=True, text=True)
    need(res.returncode == 0, f"the PHP step does not parse: {(res.stdout + res.stderr).strip()[:400]}")
    step(f"the PHP step parses (php -l, {len(code) / 1024:.0f} KB)")


def page_problems(url, doc, chrome, wrappers):
    """What a visitor gets at url: 200 without PHP errors, every widget of the page and the chrome, the Theme Builder
    header in a div (the masthead is the banner landmark) and the footer in the element its template names (footer:
    the contentinfo landmark of a footer of Elementor's own widgets), no unresolved token. -> (problems, photos)"""
    _check_url(url)
    st, _, body = http(url + ("&" if "?" in url else "?") + urllib.parse.urlencode(bust()))
    html = body.decode("utf-8", "replace")
    problems = [] if st == 200 else [f"HTTP {st}"]
    if any(c in html for c in CRITICAL):
        problems.append("a PHP error on the page")
    for wt in widget_types(doc) + chrome:
        if f"elementor-widget-{wt}" not in html:
            problems.append(f"widget {wt} is missing")
    for loc in ("header", "footer"):
        m = re.search(r'<(\w+)\s[^>]*\bdata-elementor-type="' + loc + '"', html)
        want = wrappers.get(loc, "div")
        if not m:
            problems.append(f"no Theme Builder {loc} on the page")
        elif m.group(1) != want:
            problems.append(f"the Theme Builder {loc} is wrapped in <{m.group(1)}>, not <{want}>: "
                            + ("two banner landmarks with the masthead" if loc == "header" else "no contentinfo landmark"))
    if UNRESOLVED.search(html):
        problems.append("an unresolved token (@media:/@page:/@home:/@nh:) in the page")
    return problems, len(re.findall(r'<img\s[^>]*src="[^"]*/wp-content/uploads/', html))


PAGES_PLAN = """pages will change the test site:
  1. photos: {upload} to upload (the largest .webp; WordPress makes the sub-sizes; the logo's emblem as its PNG),
     titled nh:<name>, alt text from the manifest ('' for the decorative emblem); {reuse} already there (found by that
     title) are reused; ids kept in templates/media-map.json
  2. one PHP step, as user #{user}. It stops before writing anything unless the plugin's PHP on the site is the one
     these files were built against (sha256 {sha}…) and Elementor's Container feature is on. Then it creates (as
     drafts) or updates the posts found by post meta _nh_page_key (no other post is touched):
{docs}
     writes their Elementor data, publishes them, rebuilds Pro's Theme Builder conditions cache, and then
  3. Settings → Reading: the home page becomes the front page (now: show_on_front {sof}, page_on_front {pof})
  4. ezCache is purged and both pages are smoke-tested
  Every old value goes to {log} (the posts' old Elementor data too). Code Snippets is switched on for the PHP step
  and off again; Elementor empties its CSS file cache on both switches and rebuilds it on the next page view.
Run again with --go to apply."""


def cmd_pages(args):
    docs = load_page_docs()
    built = docs[0]["built_from"]["plugin"]
    render = render_check()
    files = photo_files(media_tokens(docs))
    me = whoami(("manage_options", "upload_files", "edit_pages"))
    st, plugins, _ = api("/wp/v2/plugins")
    need(st == 200 and isinstance(plugins, list), f"cannot list plugins ({st} {err(plugins)})")
    active = {p["plugin"].split("/")[0]: p for p in plugins if p.get("status") == "active"}
    missing = [slug for slug in ("elementor", "elementor-pro", "nevehairus-site") if slug not in active]
    site_version = (active.get("nevehairus-site") or {}).get("version")
    step(f"signed in as user #{me['id']}; active: " + ", ".join(
        f"{s} {active[s].get('version', '')}" for s in ("elementor", "elementor-pro", "nevehairus-site") if s in active)
         + (f"; NOT active: {', '.join(missing)}" if missing else ""))
    step(f"templates/pages/*.json: built from this tree for nevehairus-site {built['version']} (PHP sha256 "
         f"{built['php_sha256'][:12]}…); {render or 'render check not run (no local php)'}")
    wrong_version = bool(site_version) and site_version != built["version"]
    if wrong_version:
        print(f"!! the site runs nevehairus-site {site_version}, these files were built for {built['version']}: "
              "deploy this tree's plugin first (python3 -I scripts/build_plugin.py zip; test_site.py deploy --go)")
    media_map = load_media_map()
    mine = our_media(media_map)
    reuse = {name: pick_media(name, mine, media_map) for name in files}
    for name, info in files.items():
        item = reuse[name]
        what = (f"reuse #{item['id']}" if item else
                f"upload {info['file']} ({info['width']}×{info['height']}, {info['bytes'] / 1024:.0f} KB)")
        if media_map.get(name) is not None and (not item or int(item["id"]) != media_map[name]):
            what += f" (media-map.json says #{media_map[name]}: no longer an image titled nh:{name})"
        if len(mine.get(name, [])) > 1:
            what += f" ({len(mine[name])} attachments carry nh:{name}; the others stay as they are)"
        print(f"   photo {name:14s} {what}")
    if not args.go:
        known = {n: {"id": int(i["id"]) if i else 0, "url": str(i.get("source_url") or "") if i else "",
                     "alt": files[n]["alt"]} for n, i in reuse.items()}
        lint_php("pages", pages_body([resolve_media(d, known) for d in docs], {n: k["id"] for n, k in known.items()},
                                     me["id"]))
        st_s, s, _ = api("/wp/v2/settings")
        lines = []
        for d in docs:
            where = (f"/{d['slug']}/" + (" (front page)" if d.get("front_page") else "")) if d["post_type"] == "page" \
                else f"Theme Builder {d['document_type']}, display conditions {', '.join(d.get('conditions') or [])}"
            lines.append(f"     {d['key']:10s} {d['post_type']:18s} «{d['title']}» {where}; "
                         f"{len(widget_types(d))} widget(s): {' '.join(widget_types(d))}")
        print(PAGES_PLAN.format(upload=sum(1 for v in reuse.values() if not v),
                                reuse=sum(1 for v in reuse.values() if v), docs="\n".join(lines),
                                user=me["id"], sha=built["php_sha256"][:12],
                                sof=s.get("show_on_front", "?") if st_s == 200 else f"HTTP {st_s}",
                                pof=s.get("page_on_front", "?") if st_s == 200 else f"HTTP {st_s}", log=LOG_FILE))
        return
    need(not missing, f"activate first: {', '.join(missing)}")
    need(not wrong_version, f"the site runs nevehairus-site {site_version}, not {built['version']}: deploy first")
    before = smoke()
    need(not before, f"the site is not healthy before building the pages: {before}")
    st_s, s, _ = api("/wp/v2/settings")
    need(st_s == 200 and isinstance(s, dict), f"cannot read the settings ({st_s} {err(s)})")
    record({"change": "pages: values before (Settings → Reading)", "show_on_front": s.get("show_on_front"),
            "page_on_front": s.get("page_on_front")})

    # (a) photos: reuse ours, upload the rest; (b) their tokens -> attachment id, URL and alt text
    atts = {}
    for name, info in files.items():
        item = reuse[name]
        if item is None:
            item = upload_photo(name, info, media_map)
            step(f"uploaded {info['file']} as #{item['id']} (nh:{name})")
        elif item.get("alt_text") != info["alt"]:
            old = item.get("alt_text")
            st_a, item, _ = api(f"/wp/v2/media/{item['id']}", "POST", {"alt_text": info["alt"]})
            need(st_a == 200, f"could not set the alt text of photo {name} ({st_a} {err(item)})")
            record({"change": f"alt text of photo {name} (#{item['id']})", "old": old, "new": info["alt"]})
        size = item.get("media_details") or {}
        if (size.get("width"), size.get("height")) != (info["width"], info["height"]):
            print(f"!! photo {name} (#{item['id']}) is {size.get('width')}×{size.get('height')} on the site, "
                  f"{info['width']}×{info['height']} here")
        if not size.get("sizes") and info["type"] == "image/webp":   # the 105×74 emblem has none, by design
            print(f"!! photo {name} (#{item['id']}) has no sub-sizes on the site (no WebP in the server's image "
                  "editor?): pages will use the full file without a srcset")
        atts[name] = {"id": int(item["id"]), "url": str(item.get("source_url") or ""), "alt": info["alt"]}
        media_map[name] = atts[name]["id"]
        save_media_map(media_map)
    step(f"photos ready: {len(atts)} (ids in {os.path.relpath(MEDIA_MAP, ROOT)})")
    resolved = [resolve_media(d, atts) for d in docs]
    need(not re.search(r'"@media:', json.dumps(resolved)), "a photo token is left in the data")

    # (c) one PHP step: checks, posts, Elementor data and meta, publishing, Theme Builder conditions, front page
    body = pages_body(resolved, {n: a["id"] for n, a in atts.items()}, me["id"])
    lint_php("pages", body)
    with Helper():
        try:
            log = run_php("pages", body)
        except SystemExit as e:
            record({"change": "pages: the PHP step failed or did not answer; what it changed is not known here (posts "
                    "it made carry _nh_page_key, so the next run finds and updates them)", "error": str(e)})
            raise
    record({"change": "pages: home page, house page, Theme Builder header and footer (old values: log.before)",
            "log": log, "photos": {n: a["id"] for n, a in atts.items()}})
    shown = json.loads(json.dumps(log))
    for old in ((shown.get("before") or {}).get("docs") or {}).values():
        if isinstance(old, dict) and isinstance(old.get("data"), str) and len(old["data"]) > 40:
            old["data"] = f"({len(old['data'])} bytes: in the change log)"
    print(json.dumps(shown, ensure_ascii=False, indent=1))
    need("exception" not in log and len(log.get("docs") or {}) == len(docs),
         f"the page step failed: {log.get('exception') or 'not every document was written'}")
    for w in log.get("warnings") or []:
        print("!!", w)

    # (d) caches, smoke test, the addresses
    st_c, _, _ = api("/ezcache/v1/cache", "DELETE")
    step(f"ezCache page cache purged ({st_c})")
    chrome = [wt for d in docs if d["post_type"] == "elementor_library" for wt in widget_types(d)]
    wrappers = {d["document_type"]: str((d.get("page_settings") or {}).get("content_wrapper_html_tag") or "div")
                for d in docs if d["post_type"] == "elementor_library"}
    problems, report = {}, {}
    for d in docs:
        if d["post_type"] != "page":
            continue
        info = log["docs"][d["key"]]
        found, photos = page_problems(str(info.get("url") or ""), d, chrome, wrappers)
        report[d["key"]] = {"url": info.get("url"), "id": info.get("id"), "photos": photos, "problems": found}
        if found:
            problems[d["key"]] = found
    site = smoke()
    record({"change": "pages: ezCache purged, smoke test", "ezcache": st_c, "pages": report, "site": site})
    for key, r in report.items():
        url = str(r["url"])
        step(f"{key}: {url}" + (f"  ({urllib.parse.unquote(url)})" if "%" in url else "")
             + f"  — {r['photos']} photo(s) from the media library" + (f"; PROBLEMS: {r['problems']}" if r["problems"] else ""))
    for key in ("header", "footer"):
        info = log["docs"].get(key) or {}
        step(f"{key}: Theme Builder template #{info.get('id')}, conditions {log.get('conditions_cache', {}).get(key)}")
    for key, info in log["docs"].items():
        print(f"   edit {key}: {info.get('edit')}")
    need(not problems and not site, f"the pages were built, but: {problems or ''} {site or ''} "
         "(a PHP error from our widgets: `safe-off` switches the plugin off)")
    step(f"done: both pages and the header and footer are on {HOST}")


def main():
    ap = argparse.ArgumentParser(description="Work on the TEST site only: " + HOST,
                                 formatter_class=argparse.RawDescriptionHelpFormatter, epilog=__doc__)
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("check").set_defaults(fn=cmd_check)
    p = sub.add_parser("snapshot")
    p.add_argument("dir")
    p.set_defaults(fn=cmd_snapshot)
    for name, fn in (("setup", cmd_setup), ("rekey", cmd_rekey), ("pages", cmd_pages)):
        p = sub.add_parser(name)
        p.add_argument("--go", action="store_true", help="apply (without it: print the plan only)")
        p.set_defaults(fn=fn)
    p = sub.add_parser("deploy")
    p.add_argument("zip", nargs="?")
    p.add_argument("--go", action="store_true", help="apply (without it: print the plan only)")
    p.set_defaults(fn=cmd_deploy)
    sub.add_parser("safe-off").set_defaults(fn=cmd_safe_off)
    sub.add_parser("cleanup").set_defaults(fn=cmd_cleanup)
    p = sub.add_parser("health")
    p.add_argument("--no-browser", action="store_true", help="skip the Chromium console check")
    p.set_defaults(fn=cmd_health)
    args = ap.parse_args()
    args.fn(args)


if __name__ == "__main__":
    main()
