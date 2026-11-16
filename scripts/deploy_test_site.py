"""Install or update the nevehairus-site plugin on the TEST site (nevehairus.ussl.co.il) only.

Why it is done this way: core WordPress cannot install an uploaded plugin zip over its REST API
(POST /wp/v2/plugins only takes wordpress.org slugs), and wp-admin needs a browser login. So:

  1. check that the API key works (users/me) and the account may install plugins; list backups;
  2. make sure the Code Snippets plugin (from wordpress.org) is installed and active;
  3. upload the zip to the media library (temporary);
  4. add a deploy snippet: on a request that carries a one-time token it installs the zip with
     WordPress's own Plugin_Upgrader (replacing an older copy), activates the plugin, deletes the
     temporary zip and answers with a JSON log; on any other request it does nothing;
  5. send that request, then deactivate and delete the snippet;
  6. check that the plugin is active and has the expected version.

The API key is never in this script or its output: the session's network proxy adds it to every
request to the test site. Nothing is ever sent to the live site.

    python3 -I scripts/deploy_test_site.py [path/to/nevehairus-site.zip] [--check]

--check  only steps 1 and 2 read-only: who am I, which plugins, which backups (changes nothing).
"""
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
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLUGIN_FILE = "nevehairus-site/nevehairus-site.php"
HELPER = "code-snippets/code-snippets"   # wordpress.org plugin used only to run the deploy step
args = [a for a in sys.argv[1:] if not a.startswith("--")]
CHECK_ONLY = "--check" in sys.argv
ZIP = args[0] if args else os.path.join(ROOT, "plugin", "dist", "nevehairus-site.zip")


def api(route, method="GET", body=None, headers=None, query=None, raw=False, timeout=120):
    """Call the WordPress REST API of the test site (plain permalinks: ?rest_route=)."""
    q = {"rest_route": route}
    q.update(query or {})
    url = BASE + "?" + urllib.parse.urlencode(q)
    assert urllib.parse.urlsplit(url).hostname == HOST
    data, h = None, dict(headers or {})
    if body is not None and not isinstance(body, (bytes, bytearray)):
        data, h["Content-Type"] = json.dumps(body).encode(), "application/json"
    elif body is not None:
        data = bytes(body)
    req = urllib.request.Request(url, data=data, method=method, headers=h)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            payload = r.read()
            return r.status, (payload if raw else json.loads(payload or b"null"))
    except urllib.error.HTTPError as e:
        payload = e.read()
        try:
            return e.code, json.loads(payload)
        except ValueError:
            return e.code, {"code": "non_json", "message": payload[:300].decode("utf-8", "replace")}


def need(ok, msg):
    if not ok:
        sys.exit("STOP: " + msg)


def step(msg):
    print("·", msg, flush=True)


# 1. who am I -----------------------------------------------------------------------------------
st, me = api("/wp/v2/users/me", query={"context": "edit"})
need(st == 200, f"the API key does not work ({st} {me.get('code')}): WordPress needs an application password")
caps = me.get("capabilities") or {}
step(f"signed in as user #{me['id']} ({', '.join(me.get('roles') or [])})")
need(caps.get("install_plugins") and caps.get("activate_plugins") and caps.get("upload_files"),
     "this account may not install plugins or upload files")

st, plugins = api("/wp/v2/plugins")
need(st == 200, f"cannot list plugins ({st})")
by_file = {p["plugin"]: p for p in plugins}
for p in sorted(plugins, key=lambda p: p["plugin"]):
    print(f"   {p['status']:8s} {p['plugin']:55s} {p.get('version', '')}")

st, backups = api("/ezcache/v1/backups")
if st == 200:
    text = json.dumps(backups, ensure_ascii=False)
    print("   backups (ezCache/uPress):", text[:600] + ("…" if len(text) > 600 else ""))
else:
    print(f"   backups: not readable here ({st}); uPress keeps 30 days of backups in its panel")

if CHECK_ONLY:
    sys.exit(0)

# the zip we are about to install -----------------------------------------------------------------
need(os.path.isfile(ZIP), f"no zip at {ZIP}: build it first (scripts/build_plugin.py)")
with zipfile.ZipFile(ZIP) as z:
    names = z.namelist()
    need(PLUGIN_FILE in names, f"{ZIP} has no {PLUGIN_FILE}")
    head = z.read(PLUGIN_FILE).decode("utf-8", "replace")
m = re.search(r"^\s*\*\s*Version:\s*(\S+)", head, re.M)
version = m.group(1) if m else ""
step(f"zip: {os.path.basename(ZIP)}, {len(names)} files, {os.path.getsize(ZIP) / 1024:.0f} KB, version {version or '?'}")

# 2. helper plugin ---------------------------------------------------------------------------------
helper = by_file.get(HELPER)
if not helper:
    step("installing Code Snippets from wordpress.org (used only to run the deploy step)")
    st, res = api("/wp/v2/plugins", "POST", {"slug": "code-snippets", "status": "active"})
    need(st in (200, 201), f"could not install Code Snippets ({st} {res.get('code')}: {res.get('message')})")
elif helper["status"] != "active":
    step("activating Code Snippets")
    st, res = api(f"/wp/v2/plugins/{HELPER}", "POST", {"status": "active"})
    need(st == 200, f"could not activate Code Snippets ({st} {res.get('code')})")

# 3. upload the zip (temporary) --------------------------------------------------------------------
token = secrets.token_hex(16)
fname = f"nevehairus-site-deploy-{token[:8]}.zip"
with open(ZIP, "rb") as fh:
    st, media = api("/wp/v2/media", "POST", fh.read(), headers={
        "Content-Type": "application/zip", "Content-Disposition": f'attachment; filename="{fname}"'})
need(st == 201, f"could not upload the zip ({st} {media.get('code')}: {media.get('message')})")
att = int(media["id"])
step(f"zip uploaded as temporary attachment #{att}")

# 4. deploy snippet ---------------------------------------------------------------------------------
code = """// One-time deploy of the nevehairus-site plugin (scripts/deploy_test_site.py). Does nothing unless
// the request carries this deploy's token; the script deletes the snippet right after.
add_action( 'init', static function () {
	if ( ! isset( $_GET['nh_deploy'] ) || ! hash_equals( '%(token)s', (string) wp_unslash( $_GET['nh_deploy'] ) ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	$log  = array();
	$zip  = get_attached_file( %(att)d );
	$file = '%(plugin)s';
	if ( ! $zip || ! file_exists( $zip ) ) {
		wp_send_json( array( 'error' => 'zip not found' ), 500 );
	}
	$skin     = new WP_Ajax_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->install( $zip, array( 'overwrite_package' => true ) );
	$log['install']  = is_wp_error( $result ) ? $result->get_error_message() : $result;
	$log['messages'] = $skin->get_upgrade_messages();
	if ( $skin->get_errors()->has_errors() ) {
		$log['errors'] = $skin->get_error_messages();
	}
	wp_clean_plugins_cache();
	if ( file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
		$active          = activate_plugin( $file );
		$log['activate'] = is_wp_error( $active ) ? $active->get_error_message() : 'ok';
		$data            = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
		$log['version']  = $data['Version'];
	}
	$log['zip_deleted'] = (bool) wp_delete_attachment( %(att)d, true );
	wp_send_json( $log );
}, 1 );
""" % {"token": token, "att": att, "plugin": PLUGIN_FILE}
st, snip = api("/code-snippets/v1/snippets", "POST", {
    "name": "nevehairus-site deploy (temporary)", "desc": "Created and deleted by scripts/deploy_test_site.py.",
    "code": code, "scope": "global", "active": True})
need(st in (200, 201) and snip.get("id"), f"could not create the deploy snippet ({st} {snip.get('code')}: {snip.get('message')})")
sid = int(snip["id"])
if not snip.get("active"):
    st, snip = api(f"/code-snippets/v1/snippets/{sid}/activate", "POST")
    need(st == 200 and snip.get("active"), f"could not activate the deploy snippet ({st} {snip.get('code_error') or snip.get('message')})")
step(f"deploy snippet #{sid} active")

# 5. run it, then remove it ----------------------------------------------------------------------------
try:
    st, log = api("/", query={"nh_deploy": token, "_": str(int(time.time()))}, timeout=300)
finally:
    api(f"/code-snippets/v1/snippets/{sid}/deactivate", "POST")
    st_del, _ = api(f"/code-snippets/v1/snippets/{sid}", "DELETE", query={"force": "true"})
    step(f"deploy snippet #{sid} deactivated and deleted ({st_del})")
print(json.dumps(log, ensure_ascii=False, indent=1))
need(st == 200 and log.get("install") is True and log.get("activate") == "ok", "the deploy step failed (see the log above)")

# 6. verify ------------------------------------------------------------------------------------------
st, p = api(f"/wp/v2/plugins/{PLUGIN_FILE[:-4]}")
need(st == 200, f"plugin not found after install ({st})")
need(p["status"] == "active", f"plugin is {p['status']}, not active")
need(not version or p.get("version") == version, f"version on the site is {p.get('version')}, expected {version}")
step(f"done: {p['name']} {p.get('version')} is active on {HOST}")
