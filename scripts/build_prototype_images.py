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
    "hero-walk":      ("1315", None, "שני אנשים צועדים יחד, מחובקים, בשביל מוצל בין עצים ודקלים", "אנשים מצולמים מאחור, ללא פנים"),
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

# v3 «אותיות שגרים בהן»: one grade for every photo, crops per slot (see research/06).
# No faces, no TV, no dolls, no swimwear, no "hospital" corridor; a source must be at
# least 1.5x its rendered device-pixel width (sizes below are the largest slot).
IMAGES_V3 = {
    "v3-walk":      ("1315", (0.10, 0.18, 0.90, 0.95), "שני אנשים צועדים יחד, מחובקים, בשביל מוצל בין עצים ודקלים", "מצולמים מאחור, ללא פנים"),
    "v3-sky-room":  ("1216", (0.02, 0.02, 0.40, 0.355), "תקרת „שמיים” באגף החדש: עננים על רקע כחול", "אגף חדש, 2021; לוח אחד, בלי מסגרות"),
    "v3-villa":     ("1170", (0.0, 0.34, 1.0, 1.0), "פינת ישיבה עם מפה סרוגה ועציץ סחלבים ליד קיר אבן", "להשלים: באיזה בית צולם"),
    "v3-riding":    ("1334", (0.0, 0.04, 0.42, 1.0), "סוס ליד הגדר בחווה, ביום של רכיבה טיפולית", "נחתך כך שלא יופיעו אנשים; העמוד בשליש הימני"),
    "v3-pot-a":     ("421", (0.12, 0.10, 0.88, 0.74), "כד פסיפס בגוני זהב, ירוק ואדום, על מפה צהובה", "עבודת דיירים"),
    "v3-pot-b":     ("419", (0.08, 0.02, 0.92, 0.66), "כד פסיפס בגוני כחול וירוק עם שפה כהה, על מפה צהובה", "עבודת דיירים"),
    "v3-pot-c":     ("424", (0.12, 0.08, 0.88, 0.70), "כד פסיפס לבן ואפור עם פרח, על מפה צהובה", "עבודת דיירים"),
    "v3-owls":      ("985", (0.22, 0.04, 0.96, 0.44), "ינשופים צבעוניים בעבודת יד על דוכן היצירות", "עבודת דיירים"),
    "v3-crafts":    ("1091", (0.0, 0.46, 1.0, 1.0), "שולחן עמוס עבודות פסיפס וקרמיקה על מפה צהובה ביום מכירה", "נחתך כך שלא יופיעו אנשים"),
    "v3-orchard":   ("519", None, "קבוצה צועדת בטיול בין עצי הדר", "מצולמים מאחור"),
    "v3-courtyard": ("244", None, "החצר במעון: עצים, ספסלים ומדרגות לבניין", ""),
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


def darkroom(im, seed=7):
    """v3 grade: gentle grey-world balance, -12% saturation, shadows toward #2A2238,
    highlights toward paper #F4EEE3, a little local contrast and 2.5% grain."""
    import random
    from PIL import ImageChops, ImageFilter, ImageStat
    st = ImageStat.Stat(im)
    mean = sum(st.mean) / 3
    gains = [1 + 0.5 * (mean / max(1, c) - 1) for c in st.mean]          # half-strength grey world
    im = Image.merge("RGB", [b.point(lambda v, g=g: max(0, min(255, int(v * g)))) for b, g in zip(im.split(), gains)])
    im = ImageEnhance.Color(im).enhance(0.88)
    im = im.filter(ImageFilter.UnsharpMask(radius=24, percent=18, threshold=2))  # local contrast

    def tone(v, s_, h_):
        t = v / 255
        out = v * (1 - 0.10 * (1 - t) ** 2) + s_ * 0.10 * (1 - t) ** 2      # tint + lift the blacks
        out = out * (1 - 0.06 * t ** 3) + h_ * 0.06 * t ** 3                 # warm, soft highlights
        return max(0, min(255, int(out)))
    sh, hi = (0x2A, 0x22, 0x38), (0xF4, 0xEE, 0xE3)
    im = Image.merge("RGB", [band.point(lambda v, s_=s_, h_=h_: tone(v, s_, h_)) for band, s_, h_ in zip(im.split(), sh, hi)])
    rnd = random.Random(seed)                                               # deterministic grain
    w, h = im.size
    noise = Image.frombytes("L", (w, h), rnd.randbytes(w * h)).point(lambda v: 128 + (v - 128) // 20)
    return ImageChops.add(im, Image.merge("RGB", (noise, noise, noise)), scale=1.0, offset=-128)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", required=True)
    ap.add_argument("--out", required=True)
    ap.add_argument("--only", nargs="*", help="rebuild only these names")
    ap.add_argument("--v3", action="store_true", help="build the v3 set (darkroom grade) instead of v1/v2")
    a = ap.parse_args()
    os.makedirs(a.out, exist_ok=True)
    files = {f.split(".")[0]: f for f in os.listdir(a.src)}
    mpath = os.path.join(a.out, "manifest.json")
    keep = a.only or a.v3
    manifest = json.load(open(mpath, encoding="utf-8")) if (keep and os.path.exists(mpath)) else {}
    todo = IMAGES_V3 if a.v3 else IMAGES
    for name, (mid, crop, alt, note) in todo.items():
        if a.only and name not in a.only:
            continue
        im = Image.open(os.path.join(a.src, files[mid]))
        im = ImageOps.exif_transpose(im).convert("RGB")
        if crop:
            w, h = im.size
            im = im.crop((int(crop[0] * w), int(crop[1] * h), int(crop[2] * w), int(crop[3] * h)))
        im = darkroom(im, seed=int(mid)) if a.v3 else grade(im)
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
