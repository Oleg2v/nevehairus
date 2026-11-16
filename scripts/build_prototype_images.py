#!/usr/bin/env python3
"""
Build responsive, privacy-safe images for the prototype.

- applies EXIF orientation, then DROPS all metadata (EXIF/GPS) on export;
- unifies the look of mixed phone photos with one grade (v1/v2: grade(); v3: darkroom());
- optional crop box per image (fractions of width/height), then (v3) an exact frame ratio:
  4:3 for every print, 16:9 for the video poster, None for letter rooms and residents' works.
  The ratio is cut from the centre and never upscales (fit_ratio);
- exports AVIF + WebP at several widths (never upscales; the file's own width is exported too);
- --logo: cuts the emblem out of the public logo file into logo-emblem.png (research/07 §6).

Usage:
    python3 -I scripts/build_prototype_images.py --src <dir_with_originals> --out prototype/assets/img --v3
    python3 -I scripts/build_prototype_images.py --out prototype/assets/img --logo <path/to/logo-transp-01.png>

Originals are the TEMPORARY photos taken from the client's current site
(see content/photos.csv), files named by media id (1315.jpg ...). They must be
replaced by consented, professional photos before launch. The logo file is
https://www.nevehairus.com/wp-content/uploads/2017/06/logo-transp-01.png (561x88),
downloaded once, read-only, until the client sends a vector logo.
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
    "dining":         ("1204", None, "שולחן אוכל מעץ בהיר עם כיסאות, בפינת האוכל המשותפת באגף החדש", "אגף חדש, 2021"),
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

# v3 «אותיות שגרים בהן», v3.2 frame system (research/07 §8): one grade for every photo (darkroom), and only
# three kinds of frame: a letter room (ratio None: the letter gives the shape), a 4:3 print, the 16:9 video.
# No faces, no TV, no dolls, no swimwear, no "hospital" corridor. A slot's CSS width x 2 must not exceed the
# widest exported file: when a source is small, the slot gets smaller, the photo is never stretched.
# name -> (source media id, crop (l, t, r, b) as fractions or None, ratio (w, h) or None, alt (he), note)
IMAGES_V3 = {
    # letter rooms (the frame is the letter's counter)
    "v3-walk":      ("1315", (0.10, 0.18, 0.90, 0.95), None, "שני אנשים צועדים יחד, מחובקים, בשביל מוצל בין עצים ודקלים", "מצולמים מאחור, ללא פנים"),
    "v3-sky-room":  ("1216", (0.02, 0.02, 0.40, 0.355), None, "תקרת „שמיים” באגף החדש: עננים על רקע כחול", "אגף חדש, 2021; לוח אחד, בלי מסגרות"),
    "v3-villa":     ("1170", (0.0, 0.34, 1.0, 1.0), None, "פינת ישיבה עם מפה סרוגה ועציץ סחלבים ליד קיר אבן", "להשלים: באיזה בית צולם; החדר של ב׳ באינדקס הבתים"),
    # residents' works (shown whole or in the palm: no ratio of ours)
    "v3-pot-a":     ("421", (0.12, 0.10, 0.88, 0.74), None, "כד פסיפס בגוני זהב, ירוק ואדום, על מפה צהובה", "עבודת דיירים"),
    "v3-pot-b":     ("419", (0.08, 0.02, 0.92, 0.66), None, "כד פסיפס בגוני כחול וירוק עם שפה כהה, על מפה צהובה", "עבודת דיירים"),
    "v3-pot-c":     ("424", (0.12, 0.08, 0.88, 0.70), None, "כד פסיפס לבן ואפור עם פרח, על מפה צהובה", "עבודת דיירים"),
    # the residents' mandala, whole: full height, centred on its glass heart (bead at 3128,1659 in the 5312x2988
    # original), wide enough for the whole work (x 1516-4595). The source photo itself lacks the bottom ~2.5 rows
    # at the bottom centre; we add no crop of our own.
    "v3-mandala":   ("629", (1473 / 5312, 0.0, 4783 / 5312, 1.0), None,
                     "מנדלה בפסיפס, עבודת יד של הדיירים: טבעות של אבן לבנה, אפורה ואדומה סביב לב של זכוכית צבעונית",
                     "עבודת דיירים; בלי חיתוך שלנו. בצילום המקורי חסרים כ־2.5 שורות במרכז התחתון"),
    # the video poster, 16:9: owls, hamsas and key rings from the top of the stall, no people
    "v3-owls":      ("985", (0.0, 0.03, 1.0, 0.451875), (16, 9), "ינשופים, חמסות ומחזיקי מפתחות בעבודת יד על דוכן היצירות", "עבודת דיירים; כרזת הסרטון 16:9"),
    # prints, 4:3: the four moments of «Day» and the courtyard in «Years»
    "v3-crafts":    ("1091", (0.02, 0.46, 0.98, 1.0), (4, 3), "שולחן עמוס עבודות פסיפס וקרמיקה על מפה צהובה ביום מכירה", "נחתך כך שלא יופיעו אנשים"),
    "v3-riding":    ("1334", (0.0, 0.20, 0.575, 0.9667), (4, 3), "סוס ליד הגדר בחווה, ביום של רכיבה טיפולית", "נחתך כך שלא יופיעו אנשים: האיש שבצד ימין של הצילום נשאר מחוץ לחיתוך"),
    "v3-orchard":   ("519", (0.20, 0.0, 0.95, 1.0), (4, 3), "קבוצה צועדת בטיול בין עצי הדר", "מצולמים מאחור"),
    "v3-villa-print": ("1170", (0.0, 0.40, 1.0, 0.9625), (4, 3), "פינת ישיבה עם מפה סרוגה ועציץ סחלבים ליד קיר אבן", "להשלים: באיזה בית צולם; בלי הבובות שעל הקיר"),
    "v3-courtyard": ("244", (0.125, 0.0, 0.875, 1.0), (4, 3), "החצר במעון: עצים, ספסלים ומדרגות לבניין", ""),
    # the gallery of the new wing, 4:3 (alt from the v1 records; final texts come from the alt-text task)
    "v3-dining":    ("1204", (0.0, 0.40, 1.0, 0.9625), (4, 3), "שולחן אוכל מעץ בהיר עם כיסאות, בפינת האוכל המשותפת באגף החדש", "אגף חדש, 2021"),
    "v3-sky-ceiling": ("1216", (0.0, 0.0, 1.0, 0.5625), (4, 3), "תקרת „שמיים” באגף החדש: לוחות תאורה עם עננים", "אגף חדש, 2021"),
    "v3-bedroom":   ("1208", None, (4, 3), "חדר מגורים עם שתי מיטות מוצעות בכיסוי ורוד", "אגף חדש, 2021"),
    "v3-living":    ("1214", None, (4, 3), "סלון משותף עם ספות כחולות ושולחן אוכל", "אגף חדש, 2021"),
    "v3-sky-corridor": ("1189", (0.0, 0.0, 1.0, 0.5625), (4, 3), "מסדרון באגף החדש עם תאורת תקרה בדמות שמיים כחולים ועננים", "אגף חדש, 2021"),
    "v3-lounge":    ("1215", (0.0, 0.35, 1.0, 0.9125), (4, 3), "פינת אוכל וסלון משותף באגף החדש", "אגף חדש, 2021"),
}

# The emblem of the public logo (iris, seated figure, hill, house, sky), in pixels of logo-transp-01.png (561x88).
# The letterhead lines (the legal name, «(מיסודו של סנטוריום נס ציונה)», «עוסק מורשה», the rule) are left out.
LOGO_SIZE = (561, 88)
LOGO_BOX = (443, 0, 548, 74)

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


def fit_ratio(im, ratio):
    """Cut the image to exactly ratio = (w, h) around its centre. Never upscales; None leaves it as it is."""
    if not ratio:
        return im
    rw, rh = ratio
    w, h = im.size
    if w * rh > h * rw:                                  # too wide
        nw = round(h * rw / rh); x = (w - nw) // 2
        return im.crop((x, 0, x + nw, h))
    nh = round(w * rh / rw); y = (h - nh) // 2
    return im.crop((0, y, w, y + nh))


def build_logo(path, out_dir, manifest):
    """The emblem of logo-transp-01.png (PNG-8, transparent) -> logo-emblem.png: PNG-24 with alpha, no metadata,
    the edge as it is (research/07 §6: no defringing). Its manifest record has another shape than a photo's:
    one file, no widths, decorative (alt "")."""
    src = Image.open(path)
    if src.size != LOGO_SIZE:
        raise SystemExit(f"{path}: expected logo-transp-01.png at {LOGO_SIZE[0]}x{LOGO_SIZE[1]}, "
                         f"got {src.size[0]}x{src.size[1]}; the emblem box {LOGO_BOX} would not fit")
    em = src.convert("RGBA").crop(LOGO_BOX)
    clean = Image.frombytes("RGBA", em.size, em.tobytes())   # a new image: no palette, text chunks or other metadata
    clean.save(os.path.join(out_dir, "logo-emblem.png"), optimize=True)
    manifest["logo-emblem"] = {"file": "logo-emblem.png", "width": clean.width, "height": clean.height,
                               "alt": "", "decorative": True}
    print(f"{'logo-emblem':14s} {clean.width}x{clean.height} -> logo-emblem.png")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", help="folder with the originals, named by media id (not needed with --logo alone)")
    ap.add_argument("--out", required=True)
    ap.add_argument("--only", nargs="*", help="rebuild only these names")
    ap.add_argument("--v3", action="store_true", help="build the v3 set (darkroom grade) instead of v1/v2")
    ap.add_argument("--logo", metavar="PNG", help="cut the emblem out of logo-transp-01.png into logo-emblem.png")
    a = ap.parse_args()
    if not a.src and not a.logo:
        ap.error("give --src (photos) or --logo (the emblem), or both")
    os.makedirs(a.out, exist_ok=True)
    mpath = os.path.join(a.out, "manifest.json")
    keep = a.only or a.v3 or a.logo
    manifest = json.load(open(mpath, encoding="utf-8")) if (keep and os.path.exists(mpath)) else {}
    if a.logo:
        build_logo(a.logo, a.out, manifest)
    files = {f.split(".")[0]: f for f in os.listdir(a.src)} if a.src else {}
    todo = (IMAGES_V3 if a.v3 else IMAGES) if a.src else {}
    for name, spec in todo.items():
        if a.only and name not in a.only:
            continue
        mid, crop, *rest = spec                          # v3: (crop, ratio, alt, note); v1: (crop, alt, note)
        ratio, alt, note = rest if len(rest) == 3 else (None, *rest)
        im = Image.open(os.path.join(a.src, files[mid]))
        im = ImageOps.exif_transpose(im).convert("RGB")
        if crop:
            w, h = im.size
            px = lambda f, n: int(f * n + 1e-6)          # 0.575 * 1600 = 919.99999…: a fraction meant as 920 stays 920
            im = im.crop((px(crop[0], w), px(crop[1], h), px(crop[2], w), px(crop[3], h)))
        im = fit_ratio(im, ratio)
        im = darkroom(im, seed=int(mid)) if a.v3 else grade(im)
        w, h = im.size
        outs = sorted({t for t in WIDTHS if t < w} | {min(w, WIDTHS[-1])})   # the file's own width too, never upscaled
        for tw in outs:
            th = round(h * tw / w)
            r = im.resize((tw, th), Image.LANCZOS)
            base = os.path.join(a.out, f"{name}-{tw}")
            r.save(base + ".avif", quality=52, speed=6)
            r.save(base + ".webp", quality=74, method=6)
        manifest[name] = {"media_id": int(mid), "width": w, "height": h, "widths": outs,
                          "ratio": f"{ratio[0]}:{ratio[1]}" if ratio else None,
                          "grade": "darkroom" if a.v3 else "grade", "alt": alt, "note": note}
        print(f"{name:14s} {w}x{h} {manifest[name]['ratio'] or '':5s} -> {outs}")
    with open(mpath, "w", encoding="utf-8") as fh:
        json.dump(manifest, fh, ensure_ascii=False, indent=1)


if __name__ == "__main__":
    main()
