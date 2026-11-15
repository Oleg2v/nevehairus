#!/usr/bin/env python3
"""
Read-only check of the TEST site's public state (Шаг 0 verification).

Never touches nevehairus.com. Only GET requests to the test site; no login,
no credentials required. Prints a Markdown table you can paste into README.

Usage:
    python3 -I scripts/check_test_site.py [--base https://nevehairus.ussl.co.il]
"""
import argparse
import json
import re
import ssl
import urllib.error
import urllib.request
from datetime import datetime, timezone

LIVE_HOSTS = ("nevehairus.com", "www.nevehairus.com")
UA = "Mozilla/5.0 (compatible; nevehairus-test-site-audit)"


def get(url, follow=True):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    ctx = ssl.create_default_context()

    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None

    opener = urllib.request.build_opener(urllib.request.HTTPSHandler(context=ctx))
    if not follow:
        opener.add_handler(NoRedirect())
    try:
        with opener.open(req, timeout=30) as r:
            return r.status, dict(r.headers), r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read().decode("utf-8", "replace")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="https://nevehairus.ussl.co.il")
    base = ap.parse_args().base.rstrip("/")
    host = re.sub(r"^https?://", "", base).split("/")[0]
    if host in LIVE_HOSTS:
        raise SystemExit("Refusing to run against the live site.")

    rows = []
    st, hd, home = get(base + "/")
    gen = re.findall(r'<meta name="generator" content="([^"]+)"', home)
    robots_meta = re.findall(r"<meta name=['\"]robots['\"] content=['\"]([^'\"]+)", home)
    theme = re.findall(r"wp-content/themes/([a-z0-9_-]+)/", home)
    body_cls = re.search(r'<body[^>]*class="([^"]*)"', home)
    cache_hdr = {k: v for k, v in hd.items() if k.lower() in ("x-cached-with", "x-cache", "cache-control", "x-ezcache")}

    st_r, _, robots = get(base + "/robots.txt")
    noindex_robots = "Disallow: /\n" in robots.replace("\r", "") or robots.strip().endswith("Disallow: /")
    st_j, _, root = get(base + "/index.php?rest_route=/")
    try:
        rj = json.loads(root)
    except Exception:
        rj = {}

    # permalink structure: pretty if /?p=1 redirects to a pretty URL
    st_p, hd_p, _ = get(base + "/?p=1", follow=False)
    loc = hd_p.get("Location") or hd_p.get("location") or ""

    rows.append(("Дата проверки (UTC)", datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M")))
    rows.append(("HTTP главной", str(st)))
    rows.append(("generator", "; ".join(gen) or "—"))
    rows.append(("Активная тема (по пути к ассетам)", ", ".join(sorted(set(theme))) or "—"))
    rows.append(("Запрет индексации: meta robots", ", ".join(robots_meta) or "—"))
    rows.append(("Запрет индексации: robots.txt содержит «Disallow: /»", "да" if noindex_robots else "нет"))
    rows.append(("Постоянные ссылки: /?p=1 →", f"{st_p} {loc or '(без редиректа — простые ссылки)'}"))
    rows.append(("Часовой пояс (REST: timezone_string / gmt_offset)",
                 f"{rj.get('timezone_string') or '—'} / {rj.get('gmt_offset')}"))
    rows.append(("Название / описание сайта", f"{rj.get('name')} / {rj.get('description') or '—'}"))
    rows.append(("Заголовки кэша", json.dumps(cache_hdr, ensure_ascii=False) or "—"))
    ns = rj.get("namespaces", [])
    rows.append(("REST namespaces (плагины)", ", ".join(ns)))
    rows.append(("Elementor MCP Composer", next((n for n in ns if n.startswith("elementor-mcp-composer")), "нет")))
    rows.append(("WordPress MCP Adapter (/mcp)", "да" if "mcp" in ns else "нет"))
    rows.append(("Abilities API", "да" if "wp-abilities/v1" in ns else "нет"))
    rows.append(("body class (фрагмент)", (body_cls.group(1)[:120] if body_cls else "—")))

    print("| Параметр | Значение |\n|---|---|")
    for k, v in rows:
        print(f"| {k} | {str(v).replace('|', '/')} |")


if __name__ == "__main__":
    main()
