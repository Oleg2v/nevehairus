#!/usr/bin/env python3
"""The static map of «צור קשר» (Itzik 24.11): OpenStreetMap tiles around the registry address (המאה ואחד 1, נס ציונה),
stitched once, a pin and «© OpenStreetMap» drawn on, saved as templates/v4/media/contact-map.jpg. Run by hand when the
point changes; the site loads nothing from OpenStreetMap (only this file, from the media library).
Source of the point: Nominatim (OpenStreetMap) «המאה ואחד 1 נס ציונה» -> house node 2347223818, 31.9365373, 34.7995074."""
import io, math, os, sys, time, urllib.request
from PIL import Image, ImageDraw, ImageFont

LAT, LON, Z = 31.9365373, 34.7995074, 17
W, H = 900, 600
UA = "nevehairus-static-map/1.0 (one image for one page)"
OUT = os.path.join(os.path.dirname(__file__), "..", "templates", "v4", "media", "contact-map.jpg")


def world(lat, lon, z):
    n = 2 ** z * 256
    x = (lon + 180) / 360 * n
    y = (1 - math.asinh(math.tan(math.radians(lat))) / math.pi) / 2 * n
    return x, y


cx, cy = world(LAT, LON, Z)
x0, y0 = cx - W / 2, cy - H / 2
tx0, ty0, tx1, ty1 = int(x0 // 256), int(y0 // 256), int((x0 + W) // 256), int((y0 + H) // 256)
canvas = Image.new("RGB", ((tx1 - tx0 + 1) * 256, (ty1 - ty0 + 1) * 256))
for tx in range(tx0, tx1 + 1):
    for ty in range(ty0, ty1 + 1):
        req = urllib.request.Request(f"https://tile.openstreetmap.org/{Z}/{tx}/{ty}.png", headers={"User-Agent": UA})
        with urllib.request.urlopen(req, timeout=30) as r:
            canvas.paste(Image.open(io.BytesIO(r.read())).convert("RGB"), ((tx - tx0) * 256, (ty - ty0) * 256))
        time.sleep(0.5)
ox, oy = int(x0 - tx0 * 256), int(y0 - ty0 * 256)
img = canvas.crop((ox, oy, ox + W, oy + H))
# the pin (supersampled), its tip on the point
S = 4
pin = Image.new("RGBA", (80 * S, 110 * S), (0, 0, 0, 0))
d = ImageDraw.Draw(pin)
px, py, r = 40 * S, 38 * S, 30 * S
d.polygon([(px - 24 * S, py + 18 * S), (px + 24 * S, py + 18 * S), (px, 106 * S)], fill=(255, 255, 255, 255))
d.ellipse([px - r - 4 * S, py - r - 4 * S, px + r + 4 * S, py + r + 4 * S], fill=(255, 255, 255, 255))
d.polygon([(px - 20 * S, py + 20 * S), (px + 20 * S, py + 20 * S), (px, 98 * S)], fill=(74, 45, 132, 255))
d.ellipse([px - r, py - r, px + r, py + r], fill=(74, 45, 132, 255))
d.ellipse([px - 11 * S, py - 11 * S, px + 11 * S, py + 11 * S], fill=(255, 255, 255, 255))
pin = pin.resize((80, 110), Image.LANCZOS)
img.paste(pin, (W // 2 - 40, H // 2 - 106), pin)
# the attribution
draw = ImageDraw.Draw(img, "RGBA")
try:
    font = ImageFont.truetype("DejaVuSans.ttf", 15)
except OSError:
    font = ImageFont.load_default()
text = "© OpenStreetMap"
tw = draw.textlength(text, font=font)
draw.rectangle([W - tw - 16, H - 26, W, H], fill=(255, 255, 255, 215))
draw.text((W - tw - 8, H - 23), text, fill=(40, 40, 40, 255), font=font)
os.makedirs(os.path.dirname(OUT), exist_ok=True)
img.save(OUT, "JPEG", quality=84, optimize=True, progressive=False)
print(os.path.abspath(OUT), os.path.getsize(OUT), "bytes", img.size)
