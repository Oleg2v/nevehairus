"""Work on the TEST WordPress site only (nevehairus.ussl.co.il): check, snapshot, set up, deploy.

    python3 -I scripts/test_site.py check                  read-only: who am I, theme, plugins, key settings
    python3 -I scripts/test_site.py snapshot DIR           read-only: JSON copies of what later steps may change
    python3 -I scripts/test_site.py setup [--go]           step 0 of the README checklist (plan only without --go)
    python3 -I scripts/test_site.py deploy [ZIP] [--go]    install or update the nevehairus-site plugin
    python3 -I scripts/test_site.py rekey [--go]           new key for the safe switch (rewrites only that file)
    python3 -I scripts/test_site.py safe-off               emergency: deactivate the plugin without loading it
    python3 -I scripts/test_site.py cleanup                delete one-time snippets a failed run left behind

Safety rules built in:
  * every request, including redirects, must stay on https://HOST; anything else stops the script;
  * the API key is never in this file or its output: the session's network proxy adds it to requests to HOST;
  * the safe-switch key and the one-time task token travel in request headers, never in URLs (no keys in logs);
  * nothing is deleted except what the script itself created (its snippet, its temporary zip uploads);
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
"""
import argparse
import base64
import hashlib
import http.client
import io
import json
import os
import re
import secrets
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


def main():
    ap = argparse.ArgumentParser(description="Work on the TEST site only: " + HOST,
                                 formatter_class=argparse.RawDescriptionHelpFormatter, epilog=__doc__)
    sub = ap.add_subparsers(dest="cmd", required=True)
    sub.add_parser("check").set_defaults(fn=cmd_check)
    p = sub.add_parser("snapshot")
    p.add_argument("dir")
    p.set_defaults(fn=cmd_snapshot)
    for name, fn in (("setup", cmd_setup), ("rekey", cmd_rekey)):
        p = sub.add_parser(name)
        p.add_argument("--go", action="store_true", help="apply (without it: print the plan only)")
        p.set_defaults(fn=fn)
    p = sub.add_parser("deploy")
    p.add_argument("zip", nargs="?")
    p.add_argument("--go", action="store_true", help="apply (without it: print the plan only)")
    p.set_defaults(fn=cmd_deploy)
    sub.add_parser("safe-off").set_defaults(fn=cmd_safe_off)
    sub.add_parser("cleanup").set_defaults(fn=cmd_cleanup)
    args = ap.parse_args()
    args.fn(args)


if __name__ == "__main__":
    main()
