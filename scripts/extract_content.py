#!/usr/bin/env python3
"""
Extract pages, posts and media metadata from a *read-only* snapshot of the
live site (nevehairus.com) into Markdown files + CSV tables.

The snapshot is produced beforehand with plain GET requests (see README,
"Шаг 1б"): REST API JSON (pages, posts, media, categories, tags) and the
rendered HTML of each page/post. This script never touches the network.

Usage:
    python3 -I scripts/extract_content.py --src <snapshot_dir> --out content

Snapshot layout expected in <snapshot_dir>:
    pages.clean.json  posts.clean.json  media.clean.json
    categories.clean.json  tags.clean.json
    html/page-<id>.html  html/post-<id>.html
"""
import argparse
import csv
import html
import json
import os
import re
import urllib.parse
from collections import Counter, defaultdict

from bs4 import BeautifulSoup
from markdownify import markdownify as md

# Stable English keys for the 15 legacy pages (file names only; the
# Hebrew slugs/URLs are preserved in the front matter).
PAGE_KEYS = {
    2: "home",
    15: "about",
    19: "main-home",
    931: "main-home-sanatorium",
    856: "main-home-dual-diagnosis",
    413: "main-home-products-gallery",
    21: "community-housing",
    23: "community-housing-autism",
    310: "careers",
    13: "contact",
    111: "gallery",
    408: "news",
    879: "testimonials",
    7: "sitemap",
    1290: "privacy-policy",
}

# Terms the new copy should replace (people-first language). Counted per
# document so editors know what to rewrite. Source of the preferred term:
# the client's brief ("אנשים עם מוגבלות שכלית התפתחותית").
TERM_FLAGS = {
    "פיגור": r"פיגור",
    "מפגר": r"מפגר",
    "חוסה/חוסים": r"\bחוס(?:ה|ים|ות|י)\b",
    "צרכים מיוחדים": r"צרכים מיוחדים",
    "סובלים מ…": r"(?:ה)?סובל(?:ים|ות|ת)?\s+מ",
    "לוקים": r"\bלוק(?:ה|ים|ות)\b",
    "חניך/חניכים (למבוגרים)": r"\bחניכ(?:ים|ות|י)\b|\bחניך\b",
    "מוסד": r"\bמוסד(?:ות|י)?\b|\bבמוסד\b|\bלמוסד\b|\bהמוסד\b",
    "מש\"ה (קיצור)": r"מש\"ה|מש״ה",
}

GLOBAL_FOOTER_MARKER = "כתובתינו"


def load(path):
    with open(path, encoding="utf-8") as fh:
        return json.load(fh)


def decode_url(url):
    return urllib.parse.unquote(url)


def path_of(url):
    p = urllib.parse.urlsplit(url).path
    return p if p else "/"


def best_img_src(img):
    """Lazy-loaded images keep the real URL in data-* attributes."""
    for attr in ("data-lazy-src", "data-src", "data-orig-file", "src"):
        v = img.get(attr)
        if v and not v.startswith("data:"):
            return v
    srcset = img.get("data-lazy-srcset") or img.get("srcset") or ""
    if srcset:
        return srcset.split(",")[-1].strip().split(" ")[0]
    return ""


def original_from_sized(url):
    """wp-content/uploads/x-300x200.jpg -> x.jpg (WordPress size suffix)."""
    return re.sub(r"-\d{2,4}x\d{2,4}(?=\.(?:jpe?g|png|gif|webp)$)", "", url, flags=re.I)


def head_meta(soup):
    meta = {}
    if soup.title:
        meta["seo_title"] = soup.title.get_text(strip=True)
    for m in soup.select("head meta"):
        name = m.get("name") or m.get("property")
        if name in ("description", "og:image", "og:title", "og:description"):
            meta.setdefault(name, m.get("content", ""))
    can = soup.select_one('head link[rel="canonical"]')
    if can:
        meta["canonical"] = can.get("href", "")
    return meta


def clean_article(soup, kind):
    art = soup.select_one("article.fl-post") or soup.select_one(".fl-page-content")
    if art is None:
        return None, False
    for t in art(["script", "style", "noscript", "form", "iframe"]):
        # forms/iframes are recorded separately (see extract_embeds)
        t.decompose()
    had_footer = False
    for row in art.select(".fl-row"):
        txt = row.get_text(" ", strip=True)
        if txt.startswith(GLOBAL_FOOTER_MARKER):
            row.decompose()
            had_footer = True
    # post meta header (date/author line) is noise in Markdown
    for el in art.select(".fl-post-meta, .fl-post-meta-top, .fl-comments, .fl-post-nav"):
        el.decompose()
    # Normalise lazy images so markdownify sees the real URL
    for img in art.select("img"):
        src = best_img_src(img)
        if src:
            img["src"] = src
        for a in ("data-lazy-src", "data-src", "srcset", "data-lazy-srcset", "sizes"):
            if a in img.attrs:
                del img.attrs[a]
    return art, had_footer


def extract_embeds(soup):
    art = soup.select_one("article.fl-post") or soup.select_one(".fl-page-content")
    out = []
    if art is None:
        return out
    for f in art.select("iframe"):
        out.append(("iframe", f.get("src") or f.get("data-lazy-src") or f.get("data-src") or ""))
    for f in art.select("form"):
        fields = [i.get("name") for i in f.select("input,textarea,select") if i.get("name")]
        out.append(("form", f.get("action", "") + " fields=" + ",".join(fields)))
    for v in art.select("video source, video"):
        if v.get("src"):
            out.append(("video", v.get("src")))
    return out


def to_markdown(el):
    text = md(str(el), heading_style="ATX", bullets="-", strip=["span"])
    text = html.unescape(text)
    text = re.sub(r"[ \t]+\n", "\n", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text.strip() + "\n"


def term_counts(text):
    res = {}
    for label, rx in TERM_FLAGS.items():
        n = len(re.findall(rx, text))
        if n:
            res[label] = n
    return res


def yaml_str(v):
    v = "" if v is None else str(v)
    return '"' + v.replace("\\", "\\\\").replace('"', '\\"') + '"'


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", required=True)
    ap.add_argument("--out", required=True)
    args = ap.parse_args()
    src, out = args.src, args.out

    pages = load(os.path.join(src, "pages.clean.json"))
    posts = load(os.path.join(src, "posts.clean.json"))
    media = load(os.path.join(src, "media.clean.json"))
    cats = {c["id"]: c["name"] for c in load(os.path.join(src, "categories.clean.json"))}
    tags = {t["id"]: t["name"] for t in load(os.path.join(src, "tags.clean.json"))}
    media_by_id = {m["id"]: m for m in media}
    by_id = {p["id"]: p for p in pages}

    os.makedirs(os.path.join(out, "pages"), exist_ok=True)
    os.makedirs(os.path.join(out, "posts"), exist_ok=True)

    image_usage = defaultdict(set)  # original image url -> set of doc keys
    image_alts = defaultdict(set)
    index_rows = []
    url_rows = []
    footer_saved = False

    items = [("page", p) for p in pages] + [("post", p) for p in posts]
    for kind, p in items:
        pid = p["id"]
        html_path = os.path.join(src, "html", f"{kind}-{pid}.html")
        soup = BeautifulSoup(open(html_path, encoding="utf-8").read(), "lxml")
        meta = head_meta(soup)
        embeds = extract_embeds(soup)

        if not footer_saved and kind == "page":
            for row in soup.select(".fl-page-content .fl-row"):
                if row.get_text(" ", strip=True).startswith(GLOBAL_FOOTER_MARKER):
                    with open(os.path.join(out, "_global-footer.md"), "w", encoding="utf-8") as fh:
                        fh.write("# בלוק תחתון גלובלי (מופיע בדפים באתר הקיים)\n\n")
                        fh.write("> מקור: " + decode_url(p["link"]) + "\n\n")
                        fh.write(to_markdown(row))
                    footer_saved = True
                    break

        art, had_footer = clean_article(soup, kind)
        body = to_markdown(art) if art is not None else ""

        # images used in this document
        if art is not None:
            for img in art.select("img"):
                s = img.get("src", "")
                if "wp-content/uploads" in s:
                    orig = original_from_sized(s.split("?")[0])
                    image_usage[orig].add(f"{kind}-{pid}")
                    if img.get("alt"):
                        image_alts[orig].add(img.get("alt").strip())
            for a in art.select("a[href]"):
                h = a["href"].split("?")[0]
                if re.search(r"wp-content/uploads/.+\.(jpe?g|png|gif|webp)$", h, re.I):
                    image_usage[original_from_sized(h)].add(f"{kind}-{pid}")

        title = html.unescape(p["title"]["rendered"]).strip()
        old_url = p["link"]
        fm = media_by_id.get(p.get("featured_media") or 0)
        featured = fm["source_url"] if fm else ""
        if featured:
            image_usage[featured].add(f"{kind}-{pid}(featured)")

        if kind == "page":
            key = PAGE_KEYS.get(pid, f"page-{pid}")
            fname = f"{key}.md"
            folder = "pages"
            parent_title = html.unescape(by_id[p["parent"]]["title"]["rendered"]) if p["parent"] in by_id else ""
        else:
            key = f"{p['date'][:10]}-{pid}"
            fname = f"{key}.md"
            folder = "posts"
            parent_title = ""

        terms = term_counts(title + "\n" + body)
        words = len(re.findall(r"\S+", body))

        front = [
            "---",
            f"id: {pid}",
            f"type: {kind}",
            f"title: {yaml_str(title)}",
            f"old_url: {yaml_str(decode_url(old_url))}",
            f"old_url_encoded: {yaml_str(old_url)}",
            f"old_path: {yaml_str(decode_url(path_of(old_url)))}",
            f"slug: {yaml_str(decode_url(p['slug']))}",
            f"published: {yaml_str(p['date'])}",
            f"modified: {yaml_str(p['modified'])}",
        ]
        if kind == "page":
            front += [f"parent_id: {p['parent']}", f"parent_title: {yaml_str(parent_title)}"]
        else:
            front += [
                "categories: [" + ", ".join(yaml_str(cats.get(c, c)) for c in p.get("categories", [])) + "]",
                "tags: [" + ", ".join(yaml_str(tags.get(t, t)) for t in p.get("tags", [])) + "]",
            ]
        front += [
            f"featured_image: {yaml_str(featured)}",
            f"seo_title: {yaml_str(meta.get('seo_title', ''))}",
            f"seo_description: {yaml_str(meta.get('description', ''))}",
            f"canonical: {yaml_str(decode_url(meta.get('canonical', '')))}",
            f"word_count: {words}",
            "terminology_flags: {" + ", ".join(f"{yaml_str(k)}: {v}" for k, v in terms.items()) + "}",
            f"global_footer_removed: {'true' if had_footer else 'false'}",
        ]
        if embeds:
            front.append("embeds:")
            for t, v in dict.fromkeys(embeds):
                front.append(f"  - {yaml_str(t + ': ' + v)}")
        front += [
            "source: \"snapshot of the live site via WP REST API + rendered HTML, 2026-11-15 (read-only)\"",
            "---",
            "",
        ]
        with open(os.path.join(out, folder, fname), "w", encoding="utf-8") as fh:
            fh.write("\n".join(front))
            fh.write(f"# {title}\n\n")
            fh.write(body)

        index_rows.append({
            "type": kind, "id": pid, "file": f"{folder}/{fname}", "title": title,
            "old_path": decode_url(path_of(old_url)), "published": p["date"][:10],
            "modified": p["modified"][:10], "words": words,
            "terminology_flags": "; ".join(f"{k}×{v}" for k, v in terms.items()),
        })
        url_rows.append((kind, pid, title, old_url))

    # ---- index of documents
    with open(os.path.join(out, "index.csv"), "w", encoding="utf-8", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=list(index_rows[0].keys()))
        w.writeheader()
        for r in index_rows:
            w.writerow(r)

    # ---- photo / media list (whole media library + usage)
    with open(os.path.join(out, "photos.csv"), "w", encoding="utf-8", newline="") as fh:
        w = csv.writer(fh)
        w.writerow(["media_id", "type", "file_url", "file_name", "width", "height", "uploaded",
                    "title", "alt_text", "attached_to", "used_on", "people_in_frame", "consent_status", "notes"])
        doc_title = {f"{k}-{p['id']}": html.unescape(p["title"]["rendered"]) for k, p in items}
        for m in sorted(media, key=lambda x: x["date"]):
            url = m["source_url"]
            md_ = m.get("media_details") or {}
            parent = m.get("post")
            attached = ""
            if parent:
                for k, p in items:
                    if p["id"] == parent:
                        attached = f"{k}-{parent}: {html.unescape(p['title']['rendered'])}"
            used = sorted(image_usage.get(url, set()))
            w.writerow([
                m["id"], m["mime_type"], url, decode_url(url.rsplit("/", 1)[-1]),
                md_.get("width", ""), md_.get("height", ""), m["date"][:10],
                html.unescape(m["title"]["rendered"]), m.get("alt_text", ""), attached,
                " | ".join(used), "проверить", "не подтверждено", "",
            ])
        # images referenced in content but missing from the media library listing
        known = {m["source_url"] for m in media}
        for url, used in sorted(image_usage.items()):
            if url not in known:
                w.writerow(["", "image (not in media API)", url, decode_url(url.rsplit("/", 1)[-1]),
                            "", "", "", "", " / ".join(sorted(image_alts.get(url, []))), "",
                            " | ".join(sorted(used)), "проверить", "не подтверждено",
                            "ссылка из контента; оригинал не найден в /wp-json/wp/v2/media"])

    with open(os.path.join(out, "_urls.json"), "w", encoding="utf-8") as fh:
        json.dump(url_rows, fh, ensure_ascii=False, indent=1)

    tot = Counter()
    for r in index_rows:
        for part in filter(None, r["terminology_flags"].split("; ")):
            k, v = part.rsplit("×", 1)
            tot[k] += int(v)
    print("documents:", len(index_rows), "| media:", len(media), "| images used in content:", len(image_usage))
    print("terminology totals:", dict(tot))


if __name__ == "__main__":
    main()
