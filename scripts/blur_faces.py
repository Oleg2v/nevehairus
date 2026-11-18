#!/usr/bin/env python3
"""Blur only the faces in a photo, everything else stays sharp (Itzik 18.11: «размыть только лица»).

    python3 -I scripts/blur_faces.py IN.jpg OUT.jpg x0,y0,x1,y1 [x0,y0,x1,y1 …] [--strength 0.45] [--quality 85]

Each box is a face in IN's pixel coordinates, with some margin. Inside an ellipse fitted to the box the photo is
blurred so strongly that the face cannot be recognised. The radius scales with the box: --strength × the shorter
side, at least 12px. The edge of the ellipse is feathered over a tenth of the box, so no hard ring shows. Outside the
ellipses every pixel is copied unchanged.

The output keeps IN's size and no metadata: no EXIF, no GPS, no camera data. Pillow only.
"""
import argparse
import sys

from PIL import Image, ImageDraw, ImageFilter


def parse_box(text):
    try:
        x0, y0, x1, y1 = (int(round(float(v))) for v in text.split(","))
    except ValueError:
        raise argparse.ArgumentTypeError(f"box {text!r}: four numbers x0,y0,x1,y1")
    if x1 <= x0 or y1 <= y0:
        raise argparse.ArgumentTypeError(f"box {text!r}: x1 > x0 and y1 > y0")
    return x0, y0, x1, y1


def blur_faces(img, boxes, strength=0.45):
    img = img.convert("RGB")
    out = img.copy()
    w, h = img.size
    for x0, y0, x1, y1 in boxes:
        x0, y0, x1, y1 = max(0, x0), max(0, y0), min(w, x1), min(h, y1)
        bw, bh = x1 - x0, y1 - y0
        if bw < 4 or bh < 4:
            continue
        radius = max(12, int(min(bw, bh) * strength))
        feather = max(2, int(min(bw, bh) * 0.1))
        # blur a padded crop, so the blur near the box edge does not pull in a hard border
        pad = radius * 2
        cx0, cy0, cx1, cy1 = max(0, x0 - pad), max(0, y0 - pad), min(w, x1 + pad), min(h, y1 + pad)
        crop = img.crop((cx0, cy0, cx1, cy1))
        # two passes: a coarse pixelation first, so even a strong Gaussian keeps no facial structure
        small = crop.resize((max(1, crop.width // 12), max(1, crop.height // 12)), Image.BILINEAR)
        coarse = small.resize(crop.size, Image.BILINEAR)
        blurred = coarse.filter(ImageFilter.GaussianBlur(radius))
        mask = Image.new("L", crop.size, 0)
        ImageDraw.Draw(mask).ellipse((x0 - cx0, y0 - cy0, x1 - cx0, y1 - cy0), fill=255)
        mask = mask.filter(ImageFilter.GaussianBlur(feather))
        region = out.crop((cx0, cy0, cx1, cy1))
        region.paste(blurred, (0, 0), mask)
        out.paste(region, (cx0, cy0))
    return out


def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("src")
    ap.add_argument("dst")
    ap.add_argument("boxes", nargs="+", type=parse_box)
    ap.add_argument("--strength", type=float, default=0.45)
    ap.add_argument("--quality", type=int, default=85)
    a = ap.parse_args()
    with Image.open(a.src) as img:
        result = blur_faces(img, a.boxes, a.strength)
    result.save(a.dst, "JPEG", quality=a.quality, optimize=True, progressive=True)   # no exif= : no metadata
    print(f"{a.dst}: {len(a.boxes)} face(s) blurred, {result.size[0]}x{result.size[1]}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
