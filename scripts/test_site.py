"""Work on the TEST WordPress site only (nevehairus.ussl.co.il): check, snapshot, set up, deploy.

    python3 -I scripts/test_site.py check              read-only: who am I, theme, plugins, key settings
    python3 -I scripts/test_site.py snapshot DIR       read-only: JSON copies of everything later steps may change
    python3 -I scripts/test_site.py setup              step 0 of the README checklist (see below)
    python3 -I scripts/test_site.py deploy [ZIP]       install or update the nevehairus-site plugin
    python3 -I scripts/test_site.py safe-off           emergency: switch the plugin off without loading it

The API key is never in this file or its output: the session's network proxy adds it to every request to
the test site. Every URL is checked against HOST, so nothing can reach the live site.

Why some steps run PHP: core REST cannot install an uploaded plugin or theme zip, set "discourage search
engines" or change the permalink structure. For those steps the script uses the Code Snippets plugin (from
wordpress.org) to run one piece of PHP written in this file: the snippet acts only on the request that
carries a one-time token, answers with a JSON log, and the script deletes it right after. Uploaded zips are
temporary attachments that the same step deletes.

setup (records every old value it changes, prints them for the change log in README.md):
  * search engines discouraged (blog_public 0), time zone Asia/Jerusalem, permalinks /%postname%/
    (checked over HTTP and reverted if pretty URLs do not work);
  * Hello Elementor from wordpress.org (if missing) + the child theme from theme/hello-elementor-child, activated;
  * ezCache development mode (no page cache while we build; switch it back before handover);
  * a must-use "safe switch" (wp-content/mu-plugins/nh-safe-switch.php): a request with the secret key loads
    the site without the nevehairus-site plugin, and with nh_safe_off=1 also deactivates it. The key lives only
    in SAFE_KEY_FILE outside the repo; running setup again replaces it.

deploy: deactivates an older copy, installs the zip with WordPress's own Plugin_Upgrader (replace), activates it
(a fatal error during activation leaves it inactive), then smoke-tests the home page and the API; if a test fails
it uses the safe switch to deactivate the plugin again and stops.
"""
import hashlib
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
import io

HOST = "nevehairus.ussl.co.il"          # the test site; the live site is never touched
BASE = f"https://{HOST}/"
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLUGIN_FILE = "nevehairus-site/nevehairus-site.php"
PLUGIN_ID = PLUGIN_FILE[:-4]             # REST id: folder/file without .php
HELPER_ID = "code-snippets/code-snippets"
CHILD_DIR = os.path.join(ROOT, "theme", "hello-elementor-child")
SAFE_KEY_FILE = os.environ.get("NH_SAFE_KEY_FILE", os.path.expanduser("~/.config/nevehairus/test-site-safe-key"))
CRITICAL = ("critical error", "שגיאה קריטית", "Fatal error", "Parse error")


# --- HTTP ------------------------------------------------------------------------------------------------
def url_for(route=None, query=None, path=""):
    q = {} if route is None else {"rest_route": route}
    q.update(query or {})
    url = BASE + path + ("?" + urllib.parse.urlencode(q) if q else "")
    if urllib.parse.urlsplit(url).hostname != HOST:
        raise SystemExit(f"STOP: refusing a request to another host: {url}")
    return url


def http(url, method="GET", data=None, headers=None, timeout=120, follow=True):
    """Return (status, headers, body bytes). Never raises on HTTP errors."""
    if urllib.parse.urlsplit(url).hostname != HOST:
        raise SystemExit(f"STOP: refusing a request to another host: {url}")
    req = urllib.request.Request(url, data=data, method=method, headers=dict(headers or {}))
    opener = urllib.request.build_opener() if follow else urllib.request.build_opener(_NoRedirect)
    try:
        with opener.open(req, timeout=timeout) as r:
            return r.status, dict(r.headers), r.read()
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers or {}), e.read()


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def api(route, method="GET", body=None, query=None, headers=None, timeout=120):
    """Call the WordPress REST API of the test site (works with plain and pretty permalinks)."""
    h, data = dict(headers or {}), None
    if isinstance(body, (bytes, bytearray)):
        data = bytes(body)
    elif body is not None:
        data, h["Content-Type"] = json.dumps(body).encode(), "application/json"
    st, hdr, raw = http(url_for(route, query), method, data, h, timeout)
    try:
        payload = json.loads(raw or b"null")
    except ValueError:
        payload = {"code": "non_json", "message": raw[:400].decode("utf-8", "replace")}
    return st, payload, hdr


def api_all(route, query=None):
    """All pages of a collection (per_page 100)."""
    out, page = [], 1
    while True:
        q = dict(query or {}, per_page="100", page=str(page))
        st, items, hdr = api(route, query=q)
        if st != 200 or not isinstance(items, list):
            return out if out else (st, items)
        out += items
        if page >= int(hdr.get("X-WP-TotalPages") or hdr.get("x-wp-totalpages") or 1):
            return out
        page += 1


def need(ok, msg):
    if not ok:
        raise SystemExit("STOP: " + msg)


def step(msg):
    print("·", msg, flush=True)


def err(payload):
    return f"{payload.get('code')}: {payload.get('message')}" if isinstance(payload, dict) else str(payload)[:200]


# --- read-only ---------------------------------------------------------------------------------------------
def whoami():
    st, me, _ = api("/wp/v2/users/me", query={"context": "edit"})
    need(st == 200, f"the API key does not work ({st} {err(me)}): WordPress needs an application password")
    return me


def front_state():
    """Things REST does not show: robots.txt, the permalink style, the active theme as seen by visitors."""
    st, _, robots = http(url_for(path="robots.txt", query={"_": str(time.time_ns())}))
    st_p, hdr, _ = http(url_for(query={"p": "1"}), follow=False)
    return {
        "robots_disallow_all": st == 200 and b"Disallow: /\n" in robots.replace(b"\r", b"") + b"\n",
        "p1_status": st_p,
        "p1_location": hdr.get("Location") or hdr.get("location"),
    }


def cmd_check():
    me = whoami()
    caps = me.get("capabilities") or {}
    step(f"signed in as user #{me['id']} ({', '.join(me.get('roles') or [])}); "
         f"install_plugins={bool(caps.get('install_plugins'))}, upload_files={bool(caps.get('upload_files'))}")
    st, themes, _ = api("/wp/v2/themes")
    if st == 200:
        for t in themes:
            print(f"   theme {t['status']:8s} {t['stylesheet']:28s} {t.get('version', '')}")
    st, plugins, _ = api("/wp/v2/plugins")
    need(st == 200, f"cannot list plugins ({st})")
    for p in sorted(plugins, key=lambda p: p["plugin"]):
        print(f"   plugin {p['status']:8s} {p['plugin']:45s} {p.get('version', '')}")
    st, s, _ = api("/wp/v2/settings")
    if st == 200:
        print(f"   settings: title={s.get('title')!r} timezone={s.get('timezone')!r} show_on_front={s.get('show_on_front')} "
              f"page_on_front={s.get('page_on_front')}")
    st, dev, _ = api("/ezcache/v1/dev-mode")
    print(f"   ezCache dev mode: {json.dumps(dev, ensure_ascii=False)[:200] if st == 200 else st}")
    print(f"   front: {front_state()}")
    return plugins


def cmd_snapshot(out_dir):
    """Read-only JSON copies of everything later steps may change. Keep them outside the repo: they hold
    the client's content."""
    whoami()
    os.makedirs(out_dir, exist_ok=True)
    st, types, _ = api("/wp/v2/types", query={"context": "edit"})
    jobs = {
        "settings": ("/wp/v2/settings", None),
        "themes": ("/wp/v2/themes", None),
        "plugins": ("/wp/v2/plugins", None),
        "ezcache-settings": ("/ezcache/v1/settings", None),
        "ezcache-dev-mode": ("/ezcache/v1/dev-mode", None),
        "types": ("/wp/v2/types", {"context": "edit"}),
        "taxonomies": ("/wp/v2/taxonomies", {"context": "edit"}),
    }
    collections = {}
    for slug, t in (types or {}).items() if isinstance(types, dict) else []:
        base = t.get("rest_base")
        if base and slug not in ("attachment",) and t.get("rest_namespace", "wp/v2") == "wp/v2":
            collections[slug] = f"/wp/v2/{base}"
    collections["attachment"] = "/wp/v2/media"
    for name, route in collections.items():
        q = {"context": "edit"}
        if name not in ("attachment", "wp_global_styles", "wp_font_family", "wp_font_face"):
            q["status"] = "any" if name in ("post", "page") else None
        q = {k: v for k, v in q.items() if v}
        jobs[f"collection-{name}"] = (route, q)
    summary = {}
    for name, (route, q) in jobs.items():
        if name.startswith("collection-"):
            data = api_all(route, q)
            if isinstance(data, tuple):
                summary[name] = f"HTTP {data[0]}"
                continue
        else:
            st, data, _ = api(route, query=q)
            if st != 200:
                summary[name] = f"HTTP {st}"
                continue
        with open(os.path.join(out_dir, f"{name}.json"), "w", encoding="utf-8") as fh:
            json.dump(data, fh, ensure_ascii=False, indent=1)
        summary[name] = len(data) if isinstance(data, list) else "ok"
    front = front_state()
    with open(os.path.join(out_dir, "front.json"), "w", encoding="utf-8") as fh:
        json.dump(front, fh, indent=1)
    summary["front"] = front
    with open(os.path.join(out_dir, "summary.json"), "w", encoding="utf-8") as fh:
        json.dump(summary, fh, ensure_ascii=False, indent=1)
    for k, v in summary.items():
        print(f"   {k:34s} {v}")
    step(f"snapshot written to {out_dir}")


# --- one-time PHP through Code Snippets ----------------------------------------------------------------------
WRAPPER = r"""// One-time task "__NH_LABEL__" from scripts/test_site.py (test site only). It acts only on the request that
// carries this task's token, and the script deletes the snippet right after.
add_action( 'init', static function () {
	if ( ! isset( $_GET['nh_task'] ) || ! is_string( $_GET['nh_task'] ) || ! hash_equals( '__NH_TOKEN__', $_GET['nh_task'] ) ) {
		return;
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 );
	}
	$log = array();
	try {
__NH_BODY__
	} catch ( Throwable $e ) {
		$log['exception'] = get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
	}
	wp_send_json( $log );
}, 1 );
"""


def ensure_helper():
    st, plugins, _ = api("/wp/v2/plugins")
    need(st == 200, f"cannot list plugins ({st} {err(plugins)})")
    helper = next((p for p in plugins if p["plugin"] == HELPER_ID), None)
    if not helper:
        step("installing Code Snippets from wordpress.org (used only to run one-time steps)")
        st, res, _ = api("/wp/v2/plugins", "POST", {"slug": "code-snippets", "status": "active"}, timeout=300)
        need(st in (200, 201), f"could not install Code Snippets ({st} {err(res)})")
    elif helper["status"] != "active":
        step("activating Code Snippets")
        st, res, _ = api(f"/wp/v2/plugins/{HELPER_ID}", "POST", {"status": "active"})
        need(st == 200, f"could not activate Code Snippets ({st} {err(res)})")


def upload_temp(data, filename):
    st, media, _ = api("/wp/v2/media", "POST", data, headers={
        "Content-Type": "application/zip", "Content-Disposition": f'attachment; filename="{filename}"'}, timeout=300)
    need(st == 201, f"could not upload {filename} ({st} {err(media)})")
    return int(media["id"])


def run_php(label, body, attachments=()):
    """Run `body` (PHP that fills $log) once on the test site and return the JSON log."""
    ensure_helper()
    token = secrets.token_hex(16)
    code = WRAPPER.replace("__NH_LABEL__", label).replace("__NH_TOKEN__", token).replace("__NH_BODY__", body)
    st, snip, _ = api("/code-snippets/v1/snippets", "POST", {
        "name": f"nevehairus: {label} (temporary)", "desc": "Created and deleted by scripts/test_site.py.",
        "code": code, "scope": "global", "active": True})
    need(st in (200, 201) and isinstance(snip, dict) and snip.get("id"),
         f"could not create the snippet ({st} {err(snip)})")
    sid = int(snip["id"])
    log, st = {}, None
    try:
        if not snip.get("active"):
            st, snip, _ = api(f"/code-snippets/v1/snippets/{sid}/activate", "POST")
            need(st == 200 and snip.get("active"), f"could not activate the snippet ({st} {snip.get('code_error') or err(snip)})")
        st, log, _ = api("/", query={"nh_task": token, "_": str(time.time_ns())}, timeout=300)
    finally:
        api(f"/code-snippets/v1/snippets/{sid}/deactivate", "POST")
        st_del, _, _ = api(f"/code-snippets/v1/snippets/{sid}", "DELETE", query={"force": "true"})
        if st_del not in (200, 204):
            print(f"!! could not delete snippet #{sid} ({st_del}); it is inactive. Delete it in Snippets → All Snippets.")
        for att in attachments:            # the PHP deletes them; this only catches a step that never ran
            st_a, _, _ = api(f"/wp/v2/media/{att}", query={"context": "edit"})
            if st_a == 200:
                api(f"/wp/v2/media/{att}", "DELETE", query={"force": "true"})
    need(st == 200 and isinstance(log, dict), f"the one-time step failed ({st} {err(log)})")
    return log


# --- setup (step 0) ---------------------------------------------------------------------------------------------------
SETUP_PHP = r"""
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		global $wp_rewrite;
		$log['before'] = array(
			'blog_public'         => get_option( 'blog_public' ),
			'permalink_structure' => get_option( 'permalink_structure' ),
			'stylesheet'          => get_stylesheet(),
			'template'            => get_template(),
		);
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
				$log['parent_messages'] = $skin->get_error_messages();
			}
		} else {
			$log['parent'] = 'already installed';
		}
		$zip = get_attached_file( __NH_CHILD_ZIP__ );
		if ( $zip && file_exists( $zip ) ) {
			$skin         = new WP_Ajax_Upgrader_Skin();
			$res          = ( new Theme_Upgrader( $skin ) )->install( $zip, array( 'overwrite_package' => true ) );
			$log['child'] = is_wp_error( $res ) ? $res->get_error_message() : $res;
			$log['child_messages'] = $skin->get_error_messages();
		} else {
			$log['child'] = 'zip not found';
		}
		wp_delete_attachment( __NH_CHILD_ZIP__, true );
		wp_clean_themes_cache();
		$child = wp_get_theme( 'hello-elementor-child' );
		if ( $child->exists() && ! $child->errors() && $child->parent() && $child->parent()->exists() ) {
			switch_theme( 'hello-elementor-child' );
		} else {
			$log['switch'] = 'not switched: child theme missing or broken';
		}
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			wp_mkdir_p( WPMU_PLUGIN_DIR );
		}
		$log['safe_switch'] = false !== file_put_contents( WPMU_PLUGIN_DIR . '/nh-safe-switch.php', base64_decode( '__NH_SAFE_B64__' ) );
		$log['after'] = array(
			'blog_public'         => get_option( 'blog_public' ),
			'permalink_structure' => get_option( 'permalink_structure' ),
			'stylesheet'          => get_stylesheet(),
			'template'            => get_template(),
		);
"""

REVERT_PERMALINKS_PHP = r"""
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '__NH_OLD__' );
		flush_rewrite_rules( false );
		$log['permalink_structure'] = get_option( 'permalink_structure' );
"""

SAFE_SWITCH = r"""<?php
/**
 * Plugin Name: nevehairus-site safe switch (test site)
 * Description: A request with the developer's key loads the site without the nevehairus-site plugin; with nh_safe_off=1 it also deactivates the plugin. Installed by scripts/test_site.py setup on the test site; delete this file to remove it.
 */
if ( isset( $_GET['nh_safe'] ) && is_string( $_GET['nh_safe'] ) && hash_equals( '__NH_HASH__', hash( 'sha256', $_GET['nh_safe'] ) ) ) {
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
	if ( ! headers_sent() ) {
		header( 'X-NH-Safe: on' );
	}
}
"""


def child_zip():
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as z:
        for name in sorted(os.listdir(CHILD_DIR)):
            z.write(os.path.join(CHILD_DIR, name), f"hello-elementor-child/{name}")
    return buf.getvalue()


def safe_key(new=False):
    if not new and os.path.isfile(SAFE_KEY_FILE):
        return open(SAFE_KEY_FILE, encoding="utf-8").read().strip()
    key = secrets.token_hex(24)
    os.makedirs(os.path.dirname(SAFE_KEY_FILE), mode=0o700, exist_ok=True)
    with open(SAFE_KEY_FILE, "w", encoding="utf-8") as fh:
        fh.write(key)
    os.chmod(SAFE_KEY_FILE, 0o600)
    return key


def safe_switch_works(key):
    st, hdr, _ = http(url_for("/", {"nh_safe": key, "_": str(time.time_ns())}))
    return st == 200 and (hdr.get("X-NH-Safe") or hdr.get("x-nh-safe")) == "on"


def cmd_setup():
    me = whoami()
    step(f"signed in as user #{me['id']}")
    st, settings, _ = api("/wp/v2/settings")
    need(st == 200, f"cannot read settings ({st})")
    old_tz = settings.get("timezone")
    front_before = front_state()
    key = safe_key(new=True)
    switch_php = SAFE_SWITCH.replace("__NH_HASH__", hashlib.sha256(key.encode()).hexdigest())
    att = upload_temp(child_zip(), f"hello-elementor-child-{secrets.token_hex(4)}.zip")
    body = SETUP_PHP.replace("__NH_CHILD_ZIP__", str(att)).replace(
        "__NH_SAFE_B64__", __import__("base64").b64encode(switch_php.encode()).decode())
    log = run_php("setup step 0", body, attachments=(att,))
    print(json.dumps(log, ensure_ascii=False, indent=1))
    need("exception" not in log, "the setup step threw an exception (see the log)")
    # time zone over REST
    st, s, _ = api("/wp/v2/settings", "POST", {"timezone": "Asia/Jerusalem"})
    step(f"time zone: {old_tz!r} -> {s.get('timezone') if st == 200 else f'FAILED {st} {err(s)}'}")
    # ezCache development mode (no page cache while we build)
    st, dev, _ = api("/ezcache/v1/dev-mode", "POST", {"duration": "permanent"})
    step(f"ezCache development mode: {'on (permanent)' if st == 200 else f'FAILED {st} {err(dev)}'}")
    # pretty permalinks really work? (nginx must pass them to WordPress); otherwise revert
    st_home, _, home = http(url_for(query={"_": str(time.time_ns())}))
    st_p, hdr, _ = http(url_for(query={"p": "1", "_": str(time.time_ns())}), follow=False)
    loc = hdr.get("Location") or hdr.get("location") or ""
    pretty_ok = st_home == 200
    if st_p in (301, 302) and loc:
        st_loc, _, _ = http(loc if urllib.parse.urlsplit(loc).hostname == HOST else url_for())
        pretty_ok = pretty_ok and st_loc == 200
    if not pretty_ok:
        old = (log.get("before") or {}).get("permalink_structure", "")
        step(f"pretty permalinks do not work here (home {st_home}, /?p=1 {st_p} -> {loc}); reverting to {old!r}")
        run_php("revert permalinks", REVERT_PERMALINKS_PHP.replace("__NH_OLD__", old.replace("'", "")))
    else:
        step(f"permalinks: /?p=1 -> {loc or st_p}")
    step(f"safe switch: {'works' if safe_switch_works(key) else 'NOT WORKING'} (key in {SAFE_KEY_FILE})")
    front_after = front_state()
    step(f"front before: {front_before}")
    step(f"front after:  {front_after}")
    need(st_home == 200 and not any(c.encode() in home for c in CRITICAL), f"the home page is broken after setup ({st_home})")


# --- deploy ----------------------------------------------------------------------------------------------------------------
DEPLOY_PHP = r"""
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$file = 'nevehairus-site/nevehairus-site.php';
		$zip  = get_attached_file( __NH_ZIP__ );
		if ( ! $zip || ! file_exists( $zip ) ) {
			throw new Exception( 'zip not found' );
		}
		$log['was_active'] = is_plugin_active( $file );
		if ( $log['was_active'] ) {
			deactivate_plugins( $file, true );   // a broken new copy must not run on every request
		}
		$skin     = new WP_Ajax_Upgrader_Skin();
		$result   = ( new Plugin_Upgrader( $skin ) )->install( $zip, array( 'overwrite_package' => true ) );
		$log['install']  = is_wp_error( $result ) ? $result->get_error_message() : $result;
		$log['messages'] = $skin->get_error_messages();
		wp_delete_attachment( __NH_ZIP__, true );
		wp_clean_plugins_cache();
		if ( true === $result && file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
			$active          = activate_plugin( $file );   // includes the plugin: a fatal error stops here, inactive
			$log['activate'] = is_wp_error( $active ) ? $active->get_error_message() : 'ok';
			$data            = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
			$log['version']  = $data['Version'];
		}
"""


def smoke(extra_urls=()):
    """Home page, the API and any extra pages must answer without a PHP error."""
    problems = []
    checks = [("home", url_for(query={"_": str(time.time_ns())}))] + [(u, u) for u in extra_urls]
    for name, u in checks:
        st, _, body = http(u)
        if st != 200 or any(c.encode() in body for c in CRITICAL):
            problems.append(f"{name}: HTTP {st}")
    st, idx, _ = api("/", query={"_": str(time.time_ns())})
    if st != 200:
        problems.append(f"REST index: HTTP {st}")
    st, me, _ = api("/wp/v2/users/me")
    if st != 200:
        problems.append(f"REST users/me: HTTP {st}")
    return problems


def cmd_safe_off():
    key = safe_key()
    st, hdr, _ = http(url_for("/", {"nh_safe": key, "nh_safe_off": "1", "_": str(time.time_ns())}))
    on = (hdr.get("X-NH-Safe") or hdr.get("x-nh-safe")) == "on"
    step(f"safe switch answered {st}, {'key accepted' if on else 'key NOT accepted'}")
    return on


def cmd_deploy(zip_path):
    need(os.path.isfile(zip_path), f"no zip at {zip_path}: build it first (python3 -I scripts/build_plugin.py)")
    with zipfile.ZipFile(zip_path) as z:
        names = z.namelist()
        need(PLUGIN_FILE in names, f"{zip_path} has no {PLUGIN_FILE}")
        head = z.read(PLUGIN_FILE).decode("utf-8", "replace")
    m = re.search(r"^\s*\*\s*Version:\s*(\S+)", head, re.M)
    version = m.group(1) if m else ""
    me = whoami()
    step(f"signed in as user #{me['id']}; zip {os.path.basename(zip_path)}: {len(names)} files, "
         f"{os.path.getsize(zip_path) / 1024:.0f} KB, version {version or '?'}")
    key = safe_key()
    need(safe_switch_works(key), "the safe switch does not answer: run setup first")
    before = smoke()
    need(not before, f"the site is not healthy before deploying: {before}")
    with open(zip_path, "rb") as fh:
        att = upload_temp(fh.read(), f"nevehairus-site-{secrets.token_hex(4)}.zip")
    log = run_php("deploy nevehairus-site", DEPLOY_PHP.replace("__NH_ZIP__", str(att)), attachments=(att,))
    print(json.dumps(log, ensure_ascii=False, indent=1))
    need(log.get("install") is True and log.get("activate") == "ok", "install or activation failed (see the log)")
    problems = smoke()
    if problems:
        step(f"SMOKE TEST FAILED: {problems}; switching the plugin off with the safe switch")
        cmd_safe_off()
        after = smoke()
        raise SystemExit(f"STOP: deploy rolled back (plugin inactive); site now: {after or 'healthy'}")
    st, p, _ = api(f"/wp/v2/plugins/{PLUGIN_ID}")
    need(st == 200 and p.get("status") == "active", f"plugin is not active after deploy ({st} {err(p)})")
    need(not version or p.get("version") == version, f"version on the site is {p.get('version')}, expected {version}")
    step(f"done: {p['name']} {p.get('version')} is active on {HOST}; home page and API answer normally")


if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "check"
    rest = [a for a in sys.argv[2:] if not a.startswith("--")]
    if cmd == "check":
        cmd_check()
    elif cmd == "snapshot":
        need(rest, "snapshot needs an output folder outside the repo")
        need(not os.path.abspath(rest[0]).startswith(ROOT + os.sep), "keep snapshots outside the repo (client content)")
        cmd_snapshot(rest[0])
    elif cmd == "setup":
        cmd_setup()
    elif cmd == "deploy":
        cmd_deploy(rest[0] if rest else os.path.join(ROOT, "plugin", "dist", "nevehairus-site.zip"))
    elif cmd == "safe-off":
        cmd_safe_off()
    else:
        raise SystemExit(__doc__)
