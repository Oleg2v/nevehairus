#!/usr/bin/env python3
"""
Build the URL map for the migration (content/redirects.csv and
content/redirects-attachments.csv) from the read-only snapshot.

Policy (see content/README.md, "Редиректы"):
- the new site uses "Post name" permalinks and KEEPS the existing Hebrew slugs
  of every page and post that survives -> those URLs stay 200, no redirect;
- only URLs that disappear get a 301 to the closest living page;
- attachment pages (one per media item) -> 301 to the page that uses the image,
  or to the gallery page.

Usage: python3 -I scripts/build_redirects.py --src content/_snapshot --out content
"""
import argparse
import csv
import html
import json
import os
import urllib.parse

OLD = "https://www.nevehairus.com"
GALLERY = "/גלריית-תמונות/"


def dec(u):
    return urllib.parse.unquote(u)


def path(u):
    p = urllib.parse.urlsplit(u).path or "/"
    return p


def enc(p):
    return urllib.parse.quote(p, safe="/")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", required=True)
    ap.add_argument("--out", required=True)
    a = ap.parse_args()
    J = lambda n: json.load(open(os.path.join(a.src, n), encoding="utf-8"))
    pages, posts, media = J("pages.json"), J("posts.json"), J("media.json")
    cats, tags = J("categories.json"), J("tags.json")
    by_id = {p["id"]: p for p in pages + posts}

    rows = []

    def add(kind, oid, title, old_url, action, new_path, note):
        op = dec(path(old_url))
        rows.append({
            "type": kind, "id": oid, "title": title,
            "old_path": op, "old_path_encoded": enc(op),
            "action": action, "new_path": new_path, "new_path_encoded": enc(new_path) if new_path else "",
            "http": "200" if action == "keep" else ("301" if action == "301" else "410"),
            "note": note,
        })

    for p in sorted(pages, key=lambda x: (x["parent"], x["id"])):
        t = html.unescape(p["title"]["rendered"])
        op = dec(path(p["link"]))
        note = "slug сохраняется; новая вёрстка"
        if p["id"] == 2:
            note = "главная"
        if p["id"] == 1290:
            note = "slug сохраняется; новый текст политики (проверит юрист)"
        if p["id"] == 408:
            note = "slug сохраняется; станет архивом записей (page_for_posts)"
        if p["id"] == 413:
            note = "slug сохраняется; заголовок → «גלריית יצירות למכירה» (предложение)"
        add("page", p["id"], t, p["link"], "keep", op, note)

    for p in posts:
        t = html.unescape(p["title"]["rendered"])
        op = dec(path(p["link"]))
        note = "slug сохраняется"
        if p["id"] == 1234:
            note = "НЕ публиковать без решения клиента и юриста (имя частного лица) → тогда 410 или 301 на /חדשות-ועדכונים/"
        if p["id"] in (855, 1055, 1045, 1039, 1041, 997, 973):
            note = "slug сохраняется (SEO); заголовок/текст — обновить терминологию"
        add("post", p["id"], t, p["link"], "keep", op, note)

    for c in cats:
        if c["count"] == 0:
            add("category", c["id"], c["name"], c["link"], "301", "/חדשות-ועדכונים/",
                "пустая рубрика «כללי»: не используется")
        else:
            add("category", c["id"], c["name"], c["link"], "keep", dec(path(c["link"])),
                "архив рубрики сохраняется")
    for t in tags:
        add("tag", t["id"], t["name"], t["link"], "301", "/category/ספרים-ומאמרים/",
            "метка с устаревшим термином: удалить метку, 301 на архив статей")

    # broken link found on the current home page (404 today)
    add("legacy", "", "תעסוקה (битая ссылка с главной)", OLD + "/%d7%aa%d7%a2%d7%a1%d7%95%d7%a7%d7%94/",
        "301", "/מעון-ראשי-בית-לחיים/", "сейчас 404; ссылка «מעון - בית לחיים» на главной вела сюда")

    # new pages (no old URL)
    rows.append({"type": "new", "id": "", "title": "הצהרת נגישות", "old_path": "", "old_path_encoded": "",
                 "action": "new", "new_path": "/הצהרת-נגישות/", "new_path_encoded": enc("/הצהרת-נגישות/"),
                 "http": "200", "note": "новая страница (обязательна по תקנה 35)"})

    fields = ["type", "id", "title", "old_path", "old_path_encoded", "action", "http",
              "new_path", "new_path_encoded", "note"]
    with open(os.path.join(a.out, "redirects.csv"), "w", encoding="utf-8", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=fields)
        w.writeheader()
        w.writerows(rows)

    # attachment pages
    att = []
    for m in media:
        parent = by_id.get(m.get("post"))
        target = dec(path(parent["link"])) if parent else GALLERY
        att.append({
            "media_id": m["id"], "old_path": dec(path(m["link"])), "old_path_encoded": enc(dec(path(m["link"]))),
            "http": "301", "new_path": target, "file_url_kept": dec(m["source_url"]).replace(OLD, ""),
            "note": "страница вложения → страница, где используется изображение" if parent else
                    "страница вложения → галерея",
        })
    with open(os.path.join(a.out, "redirects-attachments.csv"), "w", encoding="utf-8", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=list(att[0].keys()))
        w.writeheader()
        w.writerows(att)
    print(f"redirects.csv: {len(rows)} rows "
          f"(keep={sum(r['action']=='keep' for r in rows)}, 301={sum(r['action']=='301' for r in rows)}); "
          f"attachments: {len(att)}")


if __name__ == "__main__":
    main()
