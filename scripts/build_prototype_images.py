#!/usr/bin/env python3
"""
Build responsive, privacy-safe images for the prototype.

- applies EXIF orientation, then DROPS all metadata (EXIF/GPS) on export;
- unifies the look of mixed phone photos with a gentle warm grade;
- optional crop box per image (fractions of width/height);
- exports AVIF + WebP at several widths (never upscales).

Usage:
    python3 -I scripts/build_prototype_images.py --src <dir_with_originals> --out prototype/assets/img

Originals are the TEMPORARY photos taken from the client's current site
(see content/photos.csv). They must be replaced by consented, professional
photos before launch.
"""
import argparse
import json
import os

from PIL import Image, ImageEnhance, ImageOps

# name -> (source media id, crop (l, t, r, b) as fractions or None, alt (he), note)
IMAGES = {
    "hero-walk":      ("1315", None, "שני אנשים צועדים יחד, יד ביד, בשביל מוצל בין עצים ודקלים", "אנשים מצולמים מאחור, ללא פנים"),
    "sky-corridor":   ("1189", None, "מסדרון באגף החדש עם תאורת תקרה בדמות שמיים כחולים ועננים", "אגף חדש, 2021"),
    "sky-ceiling":    ("1216", (0.0, 0.0, 1.0, 0.62), "תקרת „שמיים” באגף החדש: לוחות תאורה עם עננים", "אגף חדש, 2021"),
    "dining":         ("1204", None, "שולחן אוכל מעץ בהיר עם כיסאות, בחלל המשותף באגף החדש", "אגף חדש, 2021"),
    "bedroom":        ("1208", None, "חדר מגורים עם שתי מיטות מוצעות בכיסוי ורוד", "אגף חדש, 2021"),
    "living":         ("1214", None, "סלון משותף עם ספות כחולות ושולחן אוכל", "אגף חדש, 2021"),
    "lounge":         ("1215", None, "פינת אוכל וסלון משותף באגף החדש", "אגף חדש, 2021"),
    "villa-living":   ("1171", None, "סלון ביתי עם ספה, שולחן קפה וטלוויזיה בבית בקהילה", "להשלים: באיזה בית צולם"),
    "villa-corner":   ("1170", None, "פינת ישיבה עם מפה סרוגה ועציץ סחלבים ליד קיר אבן", "להשלים: באיזה בית צולם"),
    "mandala":        ("629", (0.18, 0.0, 0.82, 1.0), "מנדלה פסיפס עגולה בגוונים כחולים, אדומים וצהובים, בעבודת יד", "עבודת דיירים"),
    # square crop centred on the black bead at the heart of the mandala (3128,1659 in the 5312×2988 original), r = 1320
    "mandala-ring":   ("629", ((3128 - 1320) / 5312, (1659 - 1320) / 2988, (3128 + 1320) / 5312, (1659 + 1320) / 2988),
                       "מנדלה פסיפס עגולה בעבודת יד של הדיירים: טבעות של אבן לבנה, אפורה ואדומה סביב לב של זכוכית צבעונית", "עבודת דיירים; חיתוך ריבועי ממורכז"),
    "mosaic-pot":     ("421", (0.0, 0.12, 1.0, 0.86), "כד פסיפס צבעוני בעבודת יד", "עבודת דיירים, מתוך גלריית מוצרים"),
    "mosaic-pot-2":   ("419", (0.0, 0.10, 1.0, 0.84), "כד פסיפס בגווני כחול וירוק עם צלחת", "עבודת דיירים, מתוך גלריית מוצרים"),
    "crafts-owls":    ("985", None, "דוכן יצירות: ינשופים צבעוניים, חמסות ותליונים בעבודת יד", "עבודת דיירים"),
    "crafts-table":   ("1091", None, "שולחן עמוס עבודות פסיפס וקרמיקה ביום מכירה", "אירוע מכירות"),
    "pool":           ("1083", None, "בריכה מוצלת ביום קיץ, אנשים נהנים במים", "יום כיף בבריכה, 2019"),
    "riding":         ("1334", (0.0, 0.04, 0.54, 1.0), "סוסים בחווה, ביום של רכיבה טיפולית", "נחתך כך שלא יופיעו אנשים"),
    "orchard-walk":   ("519", None, "קבוצה צועדת בטיול בין עצי הדר", "מצולמים מאחור"),
    "garden":         ("1168", None, "גינה ירוקה עם עץ פרי ודשא", ""),
    "courtyard":      ("244", None, "החצר במעון: עצים, ספסלים ומדרגות לבניין", ""),
}

WIDTHS = [480, 768, 1080, 1440]


def grade(im):
    im = ImageEnhance.Color(im).enhance(0.9)
    im = ImageEnhance.Contrast(im).enhance(1.03)
    r, g, b = im.split()
    r = r.point(lambda v: min(255, int(6 + v * 0.985 * 1.02)))
    g = g.point(lambda v: min(255, int(6 + v * 0.985)))
    b = b.point(lambda v: min(255, int(8 + v * 0.985 * 0.965)))
    return Image.merge("RGB", (r, g, b))


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", required=True)
    ap.add_argument("--out", required=True)
    ap.add_argument("--only", nargs="*", help="rebuild only these names")
    a = ap.parse_args()
    os.makedirs(a.out, exist_ok=True)
    files = {f.split(".")[0]: f for f in os.listdir(a.src)}
    mpath = os.path.join(a.out, "manifest.json")
    manifest = json.load(open(mpath, encoding="utf-8")) if (a.only and os.path.exists(mpath)) else {}
    for name, (mid, crop, alt, note) in IMAGES.items():
        if a.only and name not in a.only:
            continue
        im = Image.open(os.path.join(a.src, files[mid]))
        im = ImageOps.exif_transpose(im).convert("RGB")
        if crop:
            w, h = im.size
            im = im.crop((int(crop[0] * w), int(crop[1] * h), int(crop[2] * w), int(crop[3] * h)))
        im = grade(im)
        w, h = im.size
        outs = []
        for tw in WIDTHS:
            if tw > w and tw != WIDTHS[0]:
                continue
            tw = min(tw, w)
            th = round(h * tw / w)
            r = im.resize((tw, th), Image.LANCZOS)
            base = os.path.join(a.out, f"{name}-{tw}")
            r.save(base + ".avif", quality=52, speed=6)
            r.save(base + ".webp", quality=74, method=6)
            outs.append(tw)
        manifest[name] = {"media_id": int(mid), "width": w, "height": h, "widths": outs,
                          "alt": alt, "note": note}
        print(f"{name:14s} {w}x{h} -> {outs}")
    with open(os.path.join(a.out, "manifest.json"), "w", encoding="utf-8") as fh:
        json.dump(manifest, fh, ensure_ascii=False, indent=1)


if __name__ == "__main__":
    main()
