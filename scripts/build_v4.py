#!/usr/bin/env python3
"""v4.1 home: the Elementor data of the home page, its floating header, its footer with the shared contacts block, the
Kit's global colours and fonts, and the main menu (research/09-v4-home-spec.md §2–§4 and its second pass
research/10-v4.1-home-spec.md §1, §1.11, §2, §3, which wins where the two differ).

    python3 -I scripts/build_v4.py           build templates/v4/*.json, then check them
    python3 -I scripts/build_v4.py check     check the files as they are (and that they are what the inputs make)

Inputs: content/v4/home.json "blocks" (the live texts, word for word; a block's "plain" is its plain-language twin, a
draft, or null while none is written: the builder makes a twin wherever "plain" is set and skips it where it is null;
"retired_blocks" are not used) and templates/v4/media.json (the photos, the logo and the PDF, by name; the hero
photo's "focus"). Python 3 standard library only; never talks to a server.

Writes, in templates/v4/:
  home.json       page #32 (found on deploy by post meta _nh_page_key = home), the front page, template
                  elementor_header_footer: the nine sections of spec 10 §1 in Oleg's order, one top-level container
                  each — hero, specialties, life, pro, videos, testimonials, snoezelen, special-needs, contact
  header-v4.json  Theme Builder header, condition include/singular/front_page, wrapper <header> (09 §4.0, 10 §2)
  footer-v4.json  Theme Builder footer, the same condition, wrapper <footer> (10 §1.11): night; the shared contacts
                  block (Pro's Template widget), then a line and the bottom row (©, the three links)
  contacts.json   saved template (library type `container`: the test site lists it, its Container feature is on)
                  holding the contacts block (09 §4.7), styled for the footer: night ground, white text, half size
  kit.json        the Kit's system_colors, custom_colors, system_typography and custom_typography (09 §2)
  menu.json       the WordPress menu «תפריט ראשי» (slug nh-main), the live menu with its targets on the test host
The documents have the shape of templates/pages/*.json (key, title, slug, front_page, post_type, document_type,
template, conditions, page_settings, built_from, elementor_data); elementor_data is the value of _elementor_data as
Elementor 4.4.0 stores it.

Only Elementor's own classic widgets (heading, text-editor, image, button, video, icon, icon-box, nested-accordion) and
Pro's nav-menu, template and form (v4.3: the contact form); none of the nevehairus-site plugin's. Every colour and font goes through the Kit's globals
(settings.__globals__ = {control: "globals/colors?id=…" / "globals/typography?id=…"}), with the literal colour as a
fallback; the one local font setting is the hero's subline (1.375rem 600). No motion setting of Elementor's own (no
entrance animation, Motion Effects, sticky, hover animation or transition): the motion of spec 10 §3 (the hero's slow
zoom, the fade-ins, the cards' lift, the header's change) is the child theme's, keyed to the classes below.

The page (spec 10 §1). Every section is a top-level container: content_width full, css_classes «nh-sec nh-sec--<name>»,
_element_id <name>, its Kit background, SECTION_PAD above and below (v4.4: 3.5rem, tablets 3rem, phones 2.5rem: 112 /
96 / 80px between sections); inside it one boxed container
(nh-sec__inner, the 1320px column with 40/32/20px gutters) unless stated:
  hero           nh-hero, at least 100vh (the theme makes it 100svh and adds the floating header's room), night until
                 the photo shows; the Image widget nh-hero__img (@media:<HERO_PHOTO>, full size) with the focal point of
                 media.json's "focus" as the theme's classes nh-fx-<x> nh-fy-<y> (steps of 5); the boxed nh-hero__content
                 at the bottom (flex end, 12vh under it, phones 9vh): H1 (Kit «כותרת באנר»), the subline, «צור קשר» →
                 #contact and the phone. No inner column, no nh-reveal (the first screen never waits)
  specialties    «הבית שלנו» (v4.3): H2, the four cards (nh-card nh-reveal: white, radius 16, a soft shadow, photo on
                 top, H3 linked, the subline, the round arrow nh-card__arrow — an Icon widget, fas fa-arrow-left,
                 decorative), then the hidden H3 «סרטונים» and the two videos, each a white card too (nh-video-item
                 nh-reveal: the Video widget nh-video, its caption nh-video__cap, Kit «כיתוב»; lazy, privacy mode, our
                 thumbnails: nothing from YouTube before a click)
  life           v4.4: a section like the others (the column, its gutters and padding): a grid 7fr / 5fr (tablets 1fr /
                 1fr, phones one column, the photo first) with the whole 16:9 photo (nh-life__img nh-ratio-16x9, the
                 cards' 16px corners) and nh-life__text with the H2 and the two live sentences
  pro            H2; four cells (nh-pro__cell nh-reveal) with an Icon Box each (nh-pro__item: stacked white circle,
                 the label as H3); then the rest of «קצת עלינו» (nh-pro__text nh-reveal) at the inline start (right),
                 760px at most. v4.3: no large years (Itzik 18.11)
  (v4.3: the videos section joined «הבית שלנו»; «תודות והמלצות» left the home — Itzik 18.11)
  snoezelen      as v4; the Short in a phone (nh-phone-frame on the Video widget, 9:16, 270px since v4.4)
  special-needs  an 860px column: H2, the questions in the framed box (nh-framed, white) and the lead open; at their
                 end «להמשך קריאה» (nh-more, a Nested Accordion, collapsed) opens the rest of the live text, as in
                 v4.0 (v4.3, Oleg 18.11, point 7; SPECIAL_READ_MORE = False opens it all); the plain twin after them
  contact        _element_id contact (the target of «צור קשר»): H2, the form's lead (the inner page's), and the visit
                 form of the inner page in a white card (nh-form-card): Elementor Pro's Form (nh-contact-form), the
                 texts of #33, submissions kept in the admin only («save-to-database», no IP or browser); the e-mail
                 action to FORM_TO only at launch (LAUNCH, from FORM_FROM: an authenticated nevehairus.com mailbox;
                 the answers and the date and time only). v4.3: no phone button, no WhatsApp (Itzik 18.11)
The header (header-v4): logo, the phone, «צור קשר»; the menu. v4.3: «בשפה פשוטה» is in the theme's «נגישות» panel.
Every section is on the Kit's cream (v4.3, Itzik 18.11), the hero on its photo; the cards are white.
nh-reveal (spec 10 §3) is on every section's inner column (the life section's too: since v4.4 its photo and text
reveal as one block), the cards, the pro cells, the pro text and the video items; a block holding other nh-reveal
blocks stays put (the theme moves its children).

Tokens, resolved on deploy (scripts/test_site.py v4):
  @media:<name>    in a media control's id: the attachment (id, url, alt) of templates/v4/media.json's <name>;
                   inside a text (an href): the attachment's URL
  @page:<key>      permalink of one of our documents (home = the front page, main-home = the house page #33)
  @page-id:<key>   its post id (the Template widget's template_id)
  @home:/<path>/   home_url(<path>), also inside a text's href
built_from.inputs_sha256 is the sha256 of the inputs and of this script: `test_site.py v4` refuses files built
from other inputs.

Where the data goes beyond the letter of the specs (each for one of their own rules):
  - the Kit's accent (UI, buttons) has line height 1.5 and «טקסט קטן» 1.5: a button with padding .75rem is then
    ≈49.5px high (09 §1 min-height 48px; Elementor's Button has no min-height control);
  - the Kit gets six more fonts for the sizes of spec 10 (so they stay global): «כותרת באנר» (the hero's H1,
    clamp(2.25rem, 1.5rem + 3vw, 4rem)), «מספר גדול» (the years), «ציטוט» (the excerpt, 1.375rem/1.6), «כיתוב» (the
    video captions, 1.125rem), «כותרת קטנה» and «טקסט זעיר» (the contacts block at half size: 1rem, .9375rem);
  - the accordion's title tag is span, not div: Elementor prints the title inside a <span>, where a div is invalid;
  - the header is v4's (two rows on desktops, one on tablets and phones), now in the 1320px column, so its logo
    stands over the hero's H1: the theme makes it float and turns it transparent at the top (spec 10 §2), so the data
    hold the solid state (white, ink and purple), which is also what a visitor without scripts gets; the logo's white
    badge is the theme's;
  - «צור קשר» in the header links to the contact page /צור-קשר/ (23.11: #contact is only on the home); v4.js points
    it at the page's own contact form where there is one (the home's #contact, the forms of the inner pages);
  - card 4's subline is an empty Text Editor («leave empty»): nothing is printed, the client can fill it;
  - the WhatsApp button (when WHATSAPP is set) has no hover colour of its own (no Kit colour fits): the theme
    darkens it;
  - the footer's line is white at 20% (no Kit colour: a hairline on night);
  - the contacts template's root (night, no padding, nh-contacts, _element_id contacts) is no page section (no nh-sec):
    it lives in the footer, under the footer's own classes.

check: only known widget types; Elementor's element shape; every block of home.json used exactly once (its full
text and, if set, its plain twin, each with the text verbatim); every @media name in media.json; every
@page/@page-id key one of ours; headings h1 → h2 → h3 without gaps in each document and on the page as a whole
(header, home, the footer with the contacts template expanded; normal and plain mode); unique ids; no motion or
animation settings (the accordion's duration is 0); no external URL but youtube.com, the live Facebook page and the
WhatsApp link (none at all while WHATSAPP is empty); every widget styled through the Kit; the sections of spec 10 §1 in order with their ids, classes and
widths; the hero's photo, focus and content; every in-page link (#…) has its target on the page; the contacts template
only in the footer, before the bottom row; nh-reveal where spec 10 §3 puts it; the phone frame.
"""
import argparse
import hashlib
import html
import json
import os
import re
import sys
import urllib.parse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "templates", "v4")
CONTENT = os.path.join(ROOT, "content", "v4", "home.json")
MEDIA = os.path.join(OUT, "media.json")
INPUTS = ("content/v4/home.json", "templates/v4/media.json", "scripts/build_v4.py")
DOC_KEYS = ("home", "header-v4", "footer-v4", "contacts")
PAGE_KEYS = ("home", "main-home") + DOC_KEYS[1:]      # what @page-id:/@page: may name (main-home: the house page)
LINKABLE = ("home", "main-home")                       # @page: (permalinks) only for pages

# the sections of the home page, top to bottom (spec 10 §1; the header floats over the hero)
SECTIONS = ("hero", "specialties", "life", "pro", "snoezelen", "special-needs", "contact")   # v4.3 (Itzik 18.11)
REVEAL_SKIP = ("hero",)                                # the first screen: nothing on it waits (spec 10 §3)

PLAIN_TITLE = "שפה פשוטה — טיוטה"                      # the Navigator label of a plain twin (§4, §6)
FACEBOOK = "https://www.facebook.com/neveirus/"
YOUTUBE = "https://www.youtube.com/watch?v="
OFF_BLOCKS = set()          # blocks of home.json kept, but deliberately not built (WhatsApp: closed, Itzik 18.11)
LAUNCH = False              # True only for the live site: the contact form then also e-mails FORM_TO (Itzik 18.11)
FORM_TO = "irus1@nevehairus.com"                      # the address of the live site's contact block
# the sender of that e-mail, at launch: an existing nevehairus.com mailbox that the domain's mail (Microsoft 365)
# accepts from the web host, i.e. the host is in the SPF record (or the mail goes through Microsoft 365) and signs
# DKIM. Without it Elementor sends as email@<site domain>, which Microsoft 365 is likely to treat as a spoof of its
# own domain and file as Junk, while the visitor reads «תודה!». The build refuses LAUNCH until this is set.
FORM_FROM = None
CONSENT_NAME = "אישור מדיניות הפרטיות"                 # the consent's name in the theme's error summary and the e-mail
# the hero's photo (a templates/v4/media.json name). v4.4 (Itzik 19.11): his new horizontal photo of the same corridor,
# "hero-corridor", in place of "hero-wide" (the file Oleg sent, 1717x916; to be replaced by the original when it comes)
HERO_PHOTO = "hero-corridor"
SPECIAL_READ_MORE = True    # «להמשך קריאה» at the end of the open text, as in v4.0 (Oleg 18.11, point 7); False: all open
PHONE_TEL = "tel:089403047"
PHONE_LABEL = "התקשרו: 08-9403047"                     # aria-label of the icon-only phone button (§4.0)
CONTACT_ANCHOR = "#contact"                            # the contact band (spec 10 §1.1, §1.10)
CONTACT_PAGE = "@home:/צור-קשר/"                       # the header's «צור קשר» (1.5.7; v4.js: the page's own form)
IN_PAGE_OK = ("#nh-cookies",)                          # the theme's cookie settings (printed on every page, v4.3)

# ---- design tokens (09 §1, 10 §0) and the Kit (09 §2) ---------------------------------------------------------------------
# system colours keep Elementor's ids; custom ids are stable 7-hex hashes of the token
SYSTEM_COLORS = (("primary", "purple-700", "סגול", "#4A2D84"), ("secondary", "sky-500", "תכלת", "#5BBDEB"),
                 ("text", "ink", "טקסט", "#1F2421"), ("accent", "green-500", "ירוק", "#8CC05C"))
CUSTOM_COLORS = (("purple-800", "סגול כהה", "#3A2269"), ("purple-50", "רקע סגול", "#F3F0F9"),
                 ("sky-50", "רקע תכלת", "#EAF6FC"), ("green-50", "רקע ירוק", "#EFF6E8"),
                 ("ink-2", "טקסט משני", "#4F5550"), ("line", "קו", "#D9DCE3"), ("white", "לבן", "#FFFFFF"),
                 # v4.1 (spec 10 §0): the footer's night, and the green of WhatsApp (white on it 6.4:1)
                 ("night", "לילה", "#1C1830"), ("green-700", "ירוק כהה", "#2F6B2A"),
                 # v4.3 (Itzik 18.11): one cream ground for every section (the inner page's «paper»; ink 14:1 on it)
                 ("cream", "קרם", "#F7F1E7"),
                 # v4.3: the contact form's errors and its required mark, as the inner page's form (6.3:1 on cream)
                 ("red-700", "שגיאה", "#A8261B"))
FONT = "Assistant"
# (id or token, title, weight, size, size on phones, line height in em). Spec 09 §1/§2. Two additions, both for the
# rules of §1: the accent (UI and buttons) gets line height 1.5, so a button with padding .75rem is 1.0625rem × 1.5
# + 1.5rem ≈ 49.5px high (min-height 48px: Elementor's button has no min-height control); small text gets 1.5.
SYSTEM_TYPOGRAPHY = (("primary", "כותרות", 700, None, None, None),
                     ("secondary", "משני", 600, None, None, None),
                     ("text", "טקסט", 400, "1.1875rem", "1.125rem", 1.65),
                     ("accent", "כפתורים וממשק", 600, "1.0625rem", None, 1.5))
CUSTOM_TYPOGRAPHY = (("h1", "כותרת H1", 700, "clamp(2rem, 1.4rem + 2.4vw, 3.25rem)", None, 1.15),
                     ("h2", "כותרת H2", 700, "clamp(1.75rem, 1.4rem + 1.2vw, 2.5rem)", None, 1.2),
                     ("h3", "כותרת H3", 700, "1.375rem", "1.25rem", 1.3),
                     ("small", "טקסט קטן", 400, "1rem", None, 1.5),
                     # v4.1 (spec 10 §1.1, §1.5, §1.6, §1.7, §1.11)
                     ("display", "כותרת באנר", 700, "clamp(2.25rem, 1.5rem + 3vw, 4rem)", None, 1.1),
                     ("number", "מספר גדול", 700, "clamp(3rem, 2rem + 4vw, 5.5rem)", None, 1),
                     ("quote", "ציטוט", 400, "1.375rem", None, 1.6),
                     ("caption", "כיתוב", 400, "1.125rem", None, 1.5),
                     ("heading-small", "כותרת קטנה", 700, "1rem", None, 1.3),
                     # Oleg 17.11: the footer contacts' text at 14px; the card titles smaller on phones (2 in a row)
                     ("text-tiny", "טקסט זעיר", 400, "0.875rem", None, 1.5),
                     ("card-title", "כותרת כרטיס", 700, "1.375rem", "1.0625rem", 1.3))

CONTENT_WIDTH = 1320                                   # the content column (spec 10 §0)
BPS = ("", "_tablet", "_mobile")                       # desktop, ≤1024, ≤767 (Elementor's breakpoints)
GUTTER = {"": 40, "_tablet": 32, "_mobile": 20}        # px, phones/tablets/desktops (spec 10 §0)
# v4.4 (Itzik 19.11: «одинаковые вертикальные отступы у всех секций на всех ширинах»): every section, «החיים בנווה
# האירוס» too, has the same padding above and below; on the one cream ground two of them make the gap between sections:
# 112px on desktops, 96 on tablets, 80 on phones (it was 160 / 160 / 112, and 80 next to the life photo, which had none)
SECTION_PAD = {"": "3.5rem", "_tablet": "3rem", "_mobile": "2.5rem"}
HERO_BOTTOM = {"": "12vh", "_tablet": "12vh", "_mobile": "9vh"}   # the theme refines them to svh (spec 10 §1.1)
GRID_GAP = 24
HEADER_WIDTH = CONTENT_WIDTH                           # the header keeps to the same column (its logo over the H1)

ALLOWED_WIDGETS = {"heading", "text-editor", "image", "button", "video", "icon", "icon-box",    # Elementor
                   "nested-accordion", "nav-menu", "template", "form",                          # Elementor / Pro
                   "image-gallery", "html",         # the inner pages (23.11): a gallery; the click-to-load map
                   "theme-archive-title", "archive-posts"}     # Pro's archive template (23.11, batch 3)
HEX7 = re.compile(r"^[0-9a-f]{7}$")
TOKEN_IN_TEXT = re.compile(r"@(media|page|page-id|home):([^\s\"'<>]*)")
URL_IN_TEXT = re.compile(r"(?:https?:)?//[^\s\"'<>)]+", re.I)
MOTION = re.compile(r"animation|motion_fx|sticky|parallax|ken_burns|slideshow|_transform|transition|entrance", re.I)


def die(msg):
    raise SystemExit("STOP: " + msg)


def hex7(seed):
    return hashlib.md5(seed.encode()).hexdigest()[:7]


def color_ids():
    """token → (Kit id, hex): system colours by their Elementor id, the rest by a stable hash."""
    out = {token: (cid, value) for cid, token, _, value in SYSTEM_COLORS}
    out.update({token: (hex7(f"kit/colors/{token}"), value) for token, _, value in CUSTOM_COLORS})
    return out


COLORS = color_ids()
TYPO_IDS = {**{t[0]: t[0] for t in SYSTEM_TYPOGRAPHY}, **{t[0]: hex7(f"kit/typography/{t[0]}") for t in CUSTOM_TYPOGRAPHY}}


def gcolor(token):
    return f"globals/colors?id={COLORS[token][0]}"


def gtypo(name):
    return f"globals/typography?id={TYPO_IDS[name]}"


# ---- value shapes (Elementor 4.4.0 controls: slider, dimensions, gaps, url, media, icons) ---------------------------
def size(value):
    """'1.375rem' → rem slider value; 'clamp(…)' or 'auto' → unit custom; a number → px."""
    if isinstance(value, (int, float)):
        return {"unit": "px", "size": value, "sizes": []}
    m = re.fullmatch(r"(-?\d+(?:\.\d+)?)(px|rem|em|%|vw|vh)", value)
    if m:
        number = float(m.group(1))
        return {"unit": m.group(2), "size": int(number) if number.is_integer() else number, "sizes": []}
    return {"unit": "custom", "size": value, "sizes": []}


def dims(top, right=None, bottom=None, left=None):
    """Dimensions (padding, margin, border width, radius). All numbers → px; otherwise unit custom, every side as CSS
    (a number n becomes «npx», 0 stays «0»)."""
    right = top if right is None else right
    bottom = top if bottom is None else bottom
    left = right if left is None else left
    values = (top, right, bottom, left)
    linked = len(set(map(str, values))) == 1
    if all(isinstance(v, (int, float)) for v in values):
        return {"unit": "px", "top": str(top), "right": str(right), "bottom": str(bottom), "left": str(left),
                "isLinked": linked}
    css = [v if isinstance(v, str) else ("0" if v == 0 else f"{v}px") for v in values]
    return {"unit": "custom", "top": css[0], "right": css[1], "bottom": css[2], "left": css[3], "isLinked": linked}


def gaps(column, row):
    return {"column": str(column), "row": str(row), "isLinked": column == row, "unit": "px"}


def link(url, attributes=""):
    return {"url": url, "is_external": "", "nofollow": "", "custom_attributes": attributes}


def media(name):
    return {"url": "", "id": f"@media:{name}", "size": "", "alt": "", "source": "library"}


def icon(name, library="fa-solid"):
    """Font Awesome by name: fa-solid «fas fa-…», fa-brands «fab fa-…» (printed as inline SVG on the site)."""
    prefix = {"fa-solid": "fas", "fa-brands": "fab", "fa-regular": "far"}[library]
    return {"value": f"{prefix} fa-{name}", "library": library}


def join(*classes):
    return " ".join(c for c in " ".join(classes).split() if c)


def styled(settings, globals_=None, **colors):
    """settings with __globals__: colour controls → (literal fallback, global); typography controls → global only."""
    out = dict(settings)
    glob = dict(out.pop("__globals__", {}))
    for control, token in colors.items():
        out[control] = COLORS[token][1]
        glob[control] = gcolor(token)
    for control, name in (globals_ or {}).items():
        glob[control] = gtypo(name)
    if glob:
        out["__globals__"] = glob
    return out


def flex(direction="column", gap_row=0, gap_column=0, wrap="nowrap", **extra):
    """A flex container's layout. Column containers never wrap (Elementor's mobile default for a column is wrap)."""
    out = {"container_type": "flex", "flex_direction": direction, "flex_gap": gaps(gap_column, gap_row)}
    if wrap:
        out.update(flex_wrap=wrap, flex_wrap_tablet=wrap, flex_wrap_mobile=wrap)
    out.update(extra)
    return out


SOFT_SHADOW = {"box_shadow_box_shadow_type": "yes",
               "box_shadow_box_shadow": {"horizontal": 0, "vertical": 8, "blur": 24, "spread": -4,
                                         "color": "rgba(28, 24, 48, 0.14)"}}


def grid(columns, columns_tablet, columns_mobile, gap_column=GRID_GAP, gap_row=GRID_GAP, gap_mobile=None, **extra):
    """A grid container: a number of equal columns, or a template («2fr 3fr») per breakpoint; gap_mobile = (column,
    row) on phones."""
    def cols(v):
        return {"unit": "fr", "size": v, "sizes": []} if isinstance(v, int) else size(v)
    out = {"container_type": "grid", "grid_columns_grid": cols(columns), "grid_columns_grid_tablet": cols(columns_tablet),
           "grid_columns_grid_mobile": cols(columns_mobile), "grid_rows_grid": size("auto"),
           "grid_gaps": gaps(gap_column, gap_row), "padding": ZERO}
    if gap_mobile:
        out["grid_gaps_mobile"] = gaps(*gap_mobile)
    out.update(extra)
    return out


def per_bp(key, make):
    """{key: make(""), key_tablet: make("_tablet"), key_mobile: make("_mobile")}."""
    return {f"{key}{bp}": make(bp) for bp in BPS}


ZERO = dims(0)


# ---- the builder ----------------------------------------------------------------------------------------------------------
class Builder:
    def __init__(self, content, media_list):
        self.content = content
        self.blocks = content["blocks"]
        self.media = media_list
        self.media_names = set(media_list)
        self.uses = []             # (block, mode, doc, element id, field)
        self.doc = None

    # -- elements
    def eid(self, path):
        return hex7(f"{self.doc}/{path}")

    def container(self, path, settings, children, inner=True, title=None):
        s = {"content_width": "full", **settings}
        if title:
            s["_title"] = title
        return {"id": self.eid(path), "elType": "container", "settings": s, "elements": children, "isInner": inner}

    def widget(self, path, wtype, settings, children=None):
        return {"id": self.eid(path), "elType": "widget", "settings": settings, "elements": children or [],
                "widgetType": wtype}

    def section(self, name, title, bg, children, inner=None, gap=32, gap_mobile=24, inner_title="תוכן"):
        """A top-level section (spec 10 §1): full width, its Kit background, SECTION_PAD above and below (v4.4: 3.5rem,
        tablets 3rem, phones 2.5rem); inside it one boxed container, the 1320px column with the gutters, marked
        nh-reveal (§3), holding the children."""
        inner_s = {"content_width": "boxed", "boxed_width": size(CONTENT_WIDTH),
                   **flex("column", gap, gap), "flex_gap_mobile": gaps(gap_mobile, gap_mobile),
                   **per_bp("padding", lambda bp: dims(0, GUTTER[bp], 0, GUTTER[bp])),
                   "css_classes": "nh-sec__inner nh-reveal"}
        inner_s.update(inner or {})
        box = self.container(f"{name}/inner", inner_s, children, title=inner_title)
        s = {**flex("column", 0, 0),
             **per_bp("padding", lambda bp: dims(SECTION_PAD[bp], 0, SECTION_PAD[bp], 0)),
             "background_background": "classic",
             "css_classes": join("nh-sec", f"nh-sec--{name}"), "_element_id": name}
        return self.container(name, styled(s, background_color=bg), [box], inner=False, title=title)

    # -- blocks of content/v4/home.json
    def text(self, block, mode):
        b = self.blocks.get(block)
        if b is None:
            die(f"content/v4/home.json has no block {block}")
        return b.get(mode)

    def has_plain(self, block):
        return self.text(block, "plain") not in (None, "")

    def use(self, block, mode, element, field):
        self.uses.append((block, mode, self.doc, element["id"], field))

    def twins(self, block, path, make, classes=""):
        """The full widget of a block and, where its plain text is set, the plain twin right after it.
        make(text, path, classes) → widget; the field holding the text is the widget's own (see FIELDS)."""
        out = []
        plain = self.has_plain(block)
        full_text = self.text(block, "full")
        if full_text is not None:
            w = make(full_text, path, join(classes, "nh-full" if plain else ""))
            self.use(block, "full", w, FIELDS[w["widgetType"]])
            out.append(w)
        if plain:
            w = make(self.text(block, "plain"), path + "~plain", join(classes, "nh-plain"))
            w["settings"]["_title"] = PLAIN_TITLE
            self.use(block, "plain", w, FIELDS[w["widgetType"]])
            out.append(w)
        return out

    # -- widgets
    def heading(self, text, path, tag, typo, color, classes="", align=None, url=None, typography=None):
        s = {"title": html.escape(text, quote=False), "header_size": tag}
        if align:
            s["align"] = align
        if url:
            s["link"] = link(url)
        if classes:
            s["_css_classes"] = classes
        if typography:                       # a local font (no Kit global has it)
            s.update(typography)
            return self.widget(path, "heading", styled(s, title_color=color))
        return self.widget(path, "heading", styled(s, {"typography_typography": typo}, title_color=color))

    def editor(self, content_html, path, classes="", typo="text", color="ink", links=("purple-700", "purple-800"),
               align=None, align_mobile=None, padding=None):
        s = {"editor": content_html}
        if padding:
            s["_padding"] = padding
        if classes:
            s["_css_classes"] = classes
        if align:
            s["align"] = align
        if align_mobile:
            s["align_mobile"] = align_mobile
        return self.widget(path, "text-editor", styled(s, {"typography_typography": typo}, text_color=color,
                                                        link_color=links[0], link_hover_color=links[1]))

    def text_editor_block(self, block, path, classes="", **kw):
        """A block in a Text Editor: kind html as it is, kind text in one paragraph."""
        kind = self.blocks[block]["kind"]

        def make(text, p, cls):
            content_html = text if kind == "html" else (f"<p>{html.escape(text, quote=False)}</p>" if text else "")
            return self.editor(content_html, p, cls, **kw)
        return self.twins(block, path, make, classes)

    def heading_block(self, block, path, tag, typo, color, classes="", **kw):
        return self.twins(block, path, lambda t, p, cls: self.heading(t, p, tag, typo, color, cls, **kw), classes)

    def button(self, block, path, url, style, icon_name=None, classes="", aria_label=None, hide=(), text=None,
               icon_value=None, padding=None, radius=8, align=None):
        """Buttons of 09 §1: radius 8px, padding .75rem 1.5rem, the accent font (600, 1.0625rem, line height 1.5).
        style: primary (purple, white text), light (white, purple text: on the hero and the purple band), outline
        (white with a 2px purple border), outline-light (transparent with a 2px white border, white text: on the
        photo), text (no fill, purple text), soft (purple-50 fill, purple icon: the phone icon on tablets and phones),
        """
        label = self.text(block, "full") if block else (text or "")
        s = {"text": label, "link": link(url, f"aria-label|{aria_label}" if aria_label else ""),
             "background_background": "classic", "button_background_hover_background": "classic",
             "border_radius": dims(radius), "text_padding": padding or dims(".75rem", "1.5rem")}
        if icon_name or icon_value:
            s.update(selected_icon=icon_value or icon(icon_name), icon_align="row", icon_indent=size(8))
        if align:
            s["align"] = align
        if classes:
            s["_css_classes"] = classes
        for bp in hide:
            s[f"hide_{bp}"] = f"hidden-{bp}"
        colors = {
            "primary": dict(background_color="purple-700", button_text_color="white",
                            button_background_hover_color="purple-800", hover_color="white"),
            "light": dict(background_color="white", button_text_color="purple-700",
                          button_background_hover_color="purple-50", hover_color="purple-800"),
            "outline": dict(background_color="white", button_text_color="purple-700", border_color="purple-700",
                            button_background_hover_color="purple-50", hover_color="purple-800",
                            button_hover_border_color="purple-800"),
            "outline-light": dict(button_text_color="white", border_color="white",
                                  button_background_hover_color="purple-800", hover_color="white"),
            "text": dict(background_color="white", button_text_color="purple-700",
                         button_background_hover_color="purple-50", hover_color="purple-800"),
            "soft": dict(background_color="purple-50", button_text_color="purple-700",
                         button_background_hover_color="purple-800", hover_color="white"),
        }[style]
        if style in ("outline", "outline-light"):
            # the 2px border inside the solid buttons' height (49.5px): 2px less padding above and below (review 17.11)
            s.update(border_border="solid", border_width=dims(2),
                     text_padding=padding or dims("calc(.75rem - 2px)", "1.5rem"))
        if style == "outline-light":
            s["background_color"] = "rgba(255, 255, 255, 0)"     # transparent: the photo shows through
        if style == "text":
            s["text_padding"] = dims(".75rem", ".5rem")
        if style == "soft":
            s["text_padding"] = dims("calc(.8125rem + .5px)")      # 44px high, as the menu toggle beside it
        w = self.widget(path, "button", styled(s, {"typography_typography": "accent"}, **colors))
        if block:
            self.use(block, "full", w, "text")
        return w

    def image(self, path, name, classes="", size_name="large", url=None, link_attributes="", **extra):
        if name not in self.media_names:
            die(f"templates/v4/media.json has no {name}")
        s = {"image": media(name), "image_size": size_name}
        if url:
            s.update(link_to="custom", link=link(url, link_attributes))
        if classes:
            s["_css_classes"] = classes
        s.update(extra)
        return self.widget(path, "image", s)

    def video(self, path, video_id, ratio="169", **extra):
        """A YouTube video that loads nothing from YouTube until a click (09 §4.3): lazy, privacy mode, our own copy of
        its thumbnail as the overlay, the play icon, no lightbox."""
        name = f"video-{video_id}"
        name = self.FACES_BLURRED.get(name, name)
        if name not in self.media_names:
            die(f"templates/v4/media.json has no {name}")
        s = {"video_type": "youtube", "youtube_url": YOUTUBE + video_id, "lazy_load": "yes", "yt_privacy": "yes",
             "rel": "", "show_image_overlay": "yes", "image_overlay": media(name), "image_overlay_size": "full",
             "show_play_icon": "yes", "lightbox": "", "aspect_ratio": ratio, **extra}
        return self.widget(path, "video", s)

    def icon_widget(self, path, value, classes, px, padding_px, primary, secondary, align="start", title=None,
                    mobile=None):
        """Elementor's Icon: a stacked circle (primary = the circle, secondary = the glyph), px + 2 × padding wide; no
        link, so it is decorative (its SVG is aria-hidden). mobile = the glyph's px on phones (Elementor has no phone
        value for the padding: the theme sizes the circle there)."""
        s = {"selected_icon": value, "view": "stacked", "shape": "circle", "align": align, "size": size(px),
             "icon_padding": padding_px if isinstance(padding_px, dict) else size(padding_px)}
        if mobile:
            s["size_mobile"] = size(mobile)
        if classes:
            s["_css_classes"] = classes
        if title:
            s["_title"] = title
        return self.widget(path, "icon", styled(s, primary_color=primary, secondary_color=secondary))

    def icon_box(self, text, path, value, classes):
        """Elementor's Icon Box (spec 10 §1.5): the icon in a purple-50 circle (2rem glyph, 4.5rem circle) above the
        label as H3 («כותרת H3», ink), centred, no description, no link."""
        s = {"selected_icon": value, "view": "stacked", "shape": "circle", "title_text": html.escape(text, quote=False),
             "description_text": "", "title_size": "h3", "position": "block-start", "text_align": "center",
             "icon_space": size(16), "icon_size": size("2rem"), "icon_padding": size("1.25rem")}
        if classes:
            s["_css_classes"] = classes
        return self.widget(path, "icon-box", styled(s, {"title_typography_typography": "h3"}, primary_color="white",
                                                     secondary_color="purple-700", title_color="ink"))

    def focus_classes(self, name):
        """media.json's "focus" ("x% y%", the photo's object-position) → the theme's classes nh-fx-<x> nh-fy-<y> (steps
        of 5: v4.css reads them, and the zoom grows from the same point)."""
        focus = (self.media.get(name) or {}).get("focus")
        if not focus:
            return ""
        m = re.fullmatch(r"\s*(\d+(?:\.\d+)?)%\s+(\d+(?:\.\d+)?)%\s*", str(focus))
        if not m:
            die(f"templates/v4/media.json: {name}: focus {focus!r} is not «x% y%»")
        x, y = (int(5 * round(float(v) / 5)) for v in m.groups())
        if not (0 <= x <= 100 and 0 <= y <= 100):
            die(f"templates/v4/media.json: {name}: focus {focus!r} is outside 0–100%")
        return f"nh-fx-{x} nh-fy-{y}"

    # ---- header (09 §4.0, 10 §2) -------------------------------------------------------------------------------------
    def header(self):
        self.doc = "header-v4"
        logo = self.image("header/top/logo", "logo", size_name="full", url="@page:home",
                          height=size(64), height_tablet=size(48), height_mobile=size(40), width=size("auto"),
                          **{"object-fit": "contain"}, align="start", _flex_size="none", _title="לוגו")
        actions = self.container("header/top/actions", {
            **flex("row", 12, 12, wrap="nowrap", flex_align_items="center", flex_gap_tablet=gaps(8, 8),
                   flex_gap_mobile=gaps(6, 6)),
            # as wide as its buttons (a container is 100% wide by default, and on phones always unless set)
            "width": size("auto"), "width_mobile": size("auto"),
            "padding": ZERO, "css_classes": "nh-header__actions"}, [
            self.button("header.phone", "header/top/actions/phone", PHONE_TEL, "text", icon_name="phone-alt",
                        hide=("tablet", "mobile")),
            self.button(None, "header/top/actions/phone-icon", PHONE_TEL, "soft", icon_name="phone-alt",
                        aria_label=PHONE_LABEL, hide=("desktop",)),
            # 1.5.7 (external check 23.11): #contact exists only on the home; the contact page on every page, and
            # v4.js points the button at the page's own form where it has one (the home's #contact included)
            self.button("header.contact", "header/top/actions/contact", CONTACT_PAGE, "primary",
                        classes="nh-header__contact", hide=("tablet", "mobile")),
        ], title="כפתורים")
        top = self.container("header/top", {
            "content_width": "boxed", "boxed_width": size(HEADER_WIDTH),
            **flex("row", 16, 16, wrap="nowrap", flex_justify_content="space-between", flex_align_items="center",
                   flex_wrap_mobile="wrap", flex_gap_mobile=gaps(6, 6)),
            "min_height": size(84), "min_height_tablet": size(72), "min_height_mobile": size(64),
            # RTL: right is the inline start (the logo side); on tablets and phones the menu toggle sits at the end.
            # It shrinks (Elementor's default) to leave the toggle its room in the one row of ≤1024px.
            "padding": dims("10px", "40px"), "padding_tablet": dims("12px", "32px", "12px", "8px"),
            "padding_mobile": dims("12px", "20px", "12px", "6px"),
            "css_classes": "nh-header__top"}, [logo, actions], title="שורה עליונה")
        nav_widget = self.widget("header/nav/menu", "nav-menu", styled({
            "menu": "nh-main", "menu_name": "תפריט ראשי", "layout": "horizontal", "align_items": "start",
            "pointer": "none", "submenu_icon": icon("chevron-down"), "dropdown": "tablet", "full_width": "stretch",
            "text_align": "aside", "toggle": "burger",
            "dropdown_top_distance": size(15), "dropdown_top_distance_mobile": size(12),
        }, {"menu_typography_typography": "accent", "dropdown_typography_typography": "accent"},
            color_menu_item="ink", color_menu_item_hover="purple-800", color_menu_item_active="purple-700",
            color_dropdown_item="ink", background_color_dropdown_item="white",
            color_dropdown_item_hover="purple-800", background_color_dropdown_item_hover="purple-50",
            color_dropdown_item_active="purple-700", background_color_dropdown_item_active="purple-50",
            toggle_color="purple-700", toggle_background_color="purple-50"))
        # Full width with the 1320px column as padding: a boxed container is always 100% wide, and on ≤1024px this
        # row is only the menu toggle beside the first row (width auto)
        column = f"max({GUTTER['']}px, calc((100% - {HEADER_WIDTH}px) / 2))"
        nav = self.container("header/nav", {
            **flex("row", 0, 0, wrap="nowrap", flex_align_items="center"),
            # Elementor resets a container's width to 100% on phones unless the phone value is set too
            "width_tablet": size("auto"), "width_mobile": size("auto"),
            "padding": dims("0px", column), "padding_tablet": dims("0px", "0px", "0px", f"{GUTTER['_tablet']}px"),
            "padding_mobile": dims("0px", "0px", "0px", f"{GUTTER['_mobile']}px"),
            **styled({"border_border": "solid", "border_width": dims(1, 0, 0, 0), "border_width_tablet": dims(0)},
                     border_color="line"),
            "_flex_size_tablet": "none", "css_classes": "nh-header__nav"}, [nav_widget], title="תפריט")
        root = self.container("header", styled({
            **flex("column", 0, 0, wrap="nowrap", flex_direction_tablet="row", flex_align_items_tablet="center"),
            "padding": ZERO, "background_background": "classic",
            "border_border": "solid", "border_width": dims(0, 0, 1, 0),
            "css_classes": "nh-header"}, background_color="white", border_color="line"), [top, nav], inner=False,
            title="כותרת עליונה v4")
        return [root]

    # ---- 1 hero (10 §1.1) ------------------------------------------------------------------------------------------------
    def hero(self):
        sub_font = {"typography_typography": "custom", "typography_font_family": FONT,
                    "typography_font_size": size("1.375rem"), "typography_font_weight": "600",
                    "typography_line_height": {"unit": "em", "size": 1.4, "sizes": []}}
        photo = self.image("hero/photo", HERO_PHOTO, join("nh-hero__img", self.focus_classes(HERO_PHOTO)),
                           size_name="full", _title="תמונה")
        buttons = self.container("hero/content/buttons", {
            **flex("row", 12, 12, wrap="wrap", flex_align_items="center"), "padding": dims(8, 0, 0, 0)}, [
            self.button("hero.cta_contact", "hero/content/buttons/contact", CONTACT_ANCHOR, "light"),
            self.button("hero.cta_phone", "hero/content/buttons/phone", PHONE_TEL, "outline-light",
                        icon_name="phone-alt"),
        ], title="כפתורים")
        content = self.container("hero/content", {
            "content_width": "boxed", "boxed_width": size(CONTENT_WIDTH), **flex("column", 16, 16),
            **per_bp("padding", lambda bp: dims(0, GUTTER[bp], HERO_BOTTOM[bp], GUTTER[bp])),
            "css_classes": "nh-hero__content"},
            self.heading_block("hero.title", "hero/content/title", "h1", "display", "white")
            + self.heading_block("hero.sub", "hero/content/sub", "p", None, "white", typography=sub_font)
            + [buttons], title="טקסט")
        s = {**flex("column", 0, 0, flex_justify_content="flex-end"), "min_height": size("100vh"),
             "overflow": "hidden", "padding": ZERO, "background_background": "classic",
             "css_classes": "nh-sec nh-sec--hero nh-hero", "_element_id": "hero"}
        # v4.3: the photo's own shades (media.json "shade": the lightest that keep the white text at AA, measured on
        # its pixels) as the hero's Custom CSS; without them the theme's defaults (1.3.1's values) apply
        # v4.4: and their reach (top_hold, top_fade, top_min, text_rise, text_ramp: CSS lengths), so a photo whose middle
        # should stay bright keeps the shades to the header and the text
        shade = dict((self.media.get(HERO_PHOTO) or {}).get("shade") or {})
        # a bare "0" is a <number> inside the theme's calc() (header + hold + fade), which drops the whole declaration:
        # it is written as "0px" (independent review of v4.4)
        shade = {k: ("0px" if v == "0" else v) for k, v in shade.items()}
        alphas, lengths = ("top", "ambient", "text"), ("top_hold", "top_fade", "top_min", "text_rise", "text_ramp")
        bad = {k: v for k, v in shade.items()
               if not ((k in alphas and isinstance(v, (int, float)) and 0 <= float(v) <= 1)
                       or (k in lengths and isinstance(v, str) and re.fullmatch(r"(\d+(\.\d+)?|\.\d+)(px|rem|em)", v)))}
        if bad:
            die(f"templates/v4/media.json: {HERO_PHOTO}: shade {bad} (top / ambient / text 0–1; {' / '.join(lengths)} "
                "a length in px, rem or em)")
        if shade:
            decl = [f"--nh-shade-{k.replace('_', '-')}: {float(v):g};" if k in alphas else f"--nh-shade-{k.replace('_', '-')}: {v};"
                    for k, v in sorted(shade.items())]
            s["custom_css"] = "selector { " + " ".join(decl) + " }"
        return self.container("hero", styled(s, background_color="night"), [photo, content], inner=False, title="באנר")

    # ---- 3 specialties (10 §1.3) -------------------------------------------------------------------------------------------
    VIDEOS = ((1, "kLAGTCOIWQE"), (2, "JQXm_Dku7tI"))     # under the cards since v4.3 (10 §1.6)
    # v4.3 (Itzik 18.11: only the faces blurred): the photos with faces use their blurred copies (media.json). v4.4
    # (Itzik 19.11): the man on the bench of the second video has given his permission, so its own cover again; the
    # group photo stays blurred
    FACES_BLURRED = {"card-3": "card-3-v43"}
    CARDS = ((1, "@page:main-home"), (2, "@home:/מערך-דיור-בקהילה/"), (3, "@home:/מערך-דיור-בקהילה-אוטיזם/"),
             (4, "@home:/חדשות-ועדכונים/"))

    def specialties(self):
        """«הבית שלנו» (v4.3, Itzik 18.11): the four cards (white, a soft shadow, no line), then the two videos, each a
        white card too (the video, its caption), under a visually hidden H3 «סרטונים». The old videos section is gone."""
        cards = []
        for n, url in self.CARDS:
            p = f"specialties/inner/grid/card-{n}"
            body = self.container(f"{p}/body", {
                **flex("column", 8, 8), "flex_gap_mobile": gaps(6, 6),
                "padding": dims("1.25rem", "1.5rem", "1.5rem", "1.5rem"),
                "padding_mobile": dims(".75rem", ".875rem", ".875rem", ".875rem"),
                "_flex_size": "grow", "css_classes": "nh-card__body"},
                self.heading_block(f"card{n}.title", f"{p}/body/title", "h3", "card-title", "ink", "nh-card__title",
                                   url=url)
                + self.text_editor_block(f"card{n}.sub", f"{p}/body/sub", "nh-card__sub", typo="small", color="ink-2")
                # the circle is the widget's own: 18px + 2 × .6111em = 40px, on phones 14px → about 31px (review 17.11)
                + [self.icon_widget(f"{p}/body/arrow", icon("arrow-left"), "nh-card__arrow", 18, size("0.6111em"),
                                    "purple-700", "white", align="end", title="חץ (קישוט)", mobile=14)])
            card = self.container(p, styled({
                **flex("column", 0, 0), "padding": ZERO, "overflow": "hidden",
                "background_background": "classic", "border_radius": dims(16), **SOFT_SHADOW,
                "css_classes": "nh-card nh-reveal"}, background_color="white"),
                [self.image(f"{p}/photo", self.FACES_BLURRED.get(f"card-{n}", f"card-{n}"), "nh-card__img nh-ratio-4x3", url=url,
                            link_attributes="tabindex|-1,aria-hidden|true"), body], title=f"כרטיס {n}")
            cards.append(card)
        # 4 / 2 / 2: on phones two in a row, all four on about one screen (Oleg 17.11: «компактнее»)
        cards_grid = self.container("specialties/inner/grid", grid(4, 2, 2, gap_mobile=(12, 16)), cards,
                                    title="כרטיסים")
        videos = []
        for n, vid in self.VIDEOS:
            p = f"specialties/inner/videos/video-{n}"
            videos.append(self.container(p, styled({
                **flex("column", 0, 0), "padding": ZERO, "overflow": "hidden",
                "background_background": "classic", "border_radius": dims(16), **SOFT_SHADOW,
                "css_classes": "nh-video-item nh-reveal"}, background_color="white"),
                [self.video(f"{p}/video", vid, _css_classes="nh-video")]
                + self.text_editor_block(f"about.video{n}.caption", f"{p}/caption", "nh-video__cap", typo="caption",
                                         color="ink-2", padding=dims(".875rem", "1.25rem", "1.125rem", "1.25rem")),
                title=f"סרטון {n}"))
        videos_grid = self.container("specialties/inner/videos", grid(2, 2, 1, gap_column=32, gap_row=24,
                                                                       padding=dims(16, 0, 0, 0)),
                                     videos, title="סרטונים")
        return self.section("specialties", "הבית שלנו", "cream",
                            self.heading_block("specialties.title", "specialties/inner/title", "h2", "h2", "purple-700",
                                               align="center") + [cards_grid]
                            + self.heading_block("videos.title", "specialties/inner/videos-title", "h3", "h3",
                                                 "purple-700", "nh-visually-hidden")
                            + [videos_grid])

    # ---- 4 life (10 §1.4) ----------------------------------------------------------------------------------------------------
    def life(self):
        """v4.4 (Itzik 19.11: «фото прилипло к правому краю и обрезано. Выровняй по той же ширине и с теми же полями, что
        у остальных секций»): a section like the others, in the 1320px column with its gutters and the same padding. Two
        columns, 7fr / 5fr (tablets 1fr / 1fr): the photo at the inline start (right), whole (it is 16:9) with the cards'
        16px corners, the text beside it, centred on it; on phones the photo first, then the text. Before v4.4 the photo
        ran to the screen's edge, with no padding above or below the section."""
        p = "life/inner/grid"
        photo = self.image(f"{p}/photo", "life", "nh-life__img nh-ratio-16x9", size_name="full", _title="תמונה")
        text = self.container(f"{p}/text", {**flex("column", 16, 16, flex_justify_content="center"), "padding": ZERO,
                                            "css_classes": "nh-life__text"},
                              self.heading_block("life.title", f"{p}/text/title", "h2", "h2", "purple-700")
                              + self.text_editor_block("life.body", f"{p}/text/body"), title="טקסט")
        body = self.container(p, grid("7fr 5fr", "1fr 1fr", 1, gap_column=48, gap_row=24, grid_align_items="center"),
                              [photo, text], title="תמונה וטקסט")
        return self.section("life", "החיים בנווה האירוס", "cream", [body])

    # ---- 5 pro (10 §1.5) -----------------------------------------------------------------------------------------------------
    PRO_ITEMS = (("pro.item1", "award"), ("pro.item2", "clipboard-list"), ("pro.item3", "hands-helping"),
                 ("pro.item4", "users"))

    def pro(self):
        cells = []
        for n, (block, icon_name) in enumerate(self.PRO_ITEMS, 1):
            p = f"pro/inner/items/item-{n}"
            cells.append(self.container(p, {
                **flex("column", 0, 0), "padding": ZERO, "css_classes": "nh-pro__cell nh-reveal"},
                self.twins(block, f"{p}/box", lambda t, pp, cls, v=icon(icon_name): self.icon_box(t, pp, v, cls),
                           "nh-pro__item"), title=self.text(block, "full")))
        # 4 / 2 / 2: on phones a 2×2 block, not a long, sparse column (Itzik: «static and empty»)
        items = self.container("pro/inner/items", grid(4, 2, 2, gap_column=24, gap_row=32), cells, title="ארבעה דברים")
        # v4.3 (Itzik 18.11): no large 1957 / 1985; the rest of «קצת עלינו» moves to the right (the inline start)
        text_col = self.container("pro/inner/text", {
            **flex("column", 16, 16), "padding": dims(8, 0, 0, 0), "width": size("min(760px, 100%)"),
            "_flex_align_self": "flex-start", "css_classes": "nh-pro__text nh-reveal"},
            self.text_editor_block("pro.body", "pro/inner/text/body"), title="טקסט")
        return self.section("pro", "מקצועיות, ניסיון ויחס אישי", "cream",
                            self.heading_block("pro.title", "pro/inner/title", "h2", "h2", "purple-700",
                                               align="center") + [items, text_col])

    # ---- 8 snoezelen (10 §1.8) -----------------------------------------------------------------------------------------------
    def snoezelen(self):
        plain = self.has_plain("snoezelen.plain_body")
        p = "snoezelen/inner/grid"
        text = self.container(f"{p}/text", {**flex("column", 16, 16), "padding": ZERO},
                              self.heading_block("snoezelen.title", f"{p}/text/title", "h2", "h2", "purple-700")
                              + self.text_editor_block("snoezelen.intro", f"{p}/text/intro", "nh-full" if plain else "")
                              + self.text_editor_block("snoezelen.list", f"{p}/text/list",
                                                       join("nh-checklist", "nh-full" if plain else ""))
                              + self.text_editor_block("snoezelen.plain_body", f"{p}/text/plain")
                              + self.text_editor_block("snoezelen.credit", f"{p}/text/credit", "nh-credit",
                                                       typo="small", color="ink-2"), title="טקסט")
        video = self.container(f"{p}/video", {**flex("column", 0, 0, flex_align_items="center"), "padding": ZERO},
                               [self.video(f"{p}/video/short", "Tkbqa08a5Ro", ratio="916",
                                           # v4.4 (Itzik 19.11: the section 15% lower): 270px, was 300
                                           _element_width="initial", _element_custom_width=size(270),
                                           _flex_align_self="center", _css_classes="nh-phone-frame")],
                               title="סרטון")
        body = self.container(p, grid("7fr 5fr", "7fr 5fr", 1, gap_column=48, gap_row=32, grid_align_items="center"),
                              [text, video], title="טקסט וסרטון")
        return self.section("snoezelen", "חדר סנוזלן", "cream", [body])

    # ---- 9 special needs (10 §1.9) -------------------------------------------------------------------------------------------
    def special(self):
        """v4.3 (Itzik and Oleg 18.11, point 7: «текст открыт, как было раньше, без сворачивания под плюс. Если раньше
        в конце была ссылка «להמשך קריאה» на хвост текста, верни её так же»): as in v4.0 (spec 09 §4.5). The questions
        in the framed box (white, its purple inline-start border) and the lead are open; at their end the nested
        accordion «להמשך קריאה» opens the rest of the live text (it stays in the HTML for search engines). v4.1–v4.2
        folded the whole text under it instead. The plain twin follows. SPECIAL_READ_MORE = False opens the rest too,
        as on the live site, which has no «להמשך קריאה»."""
        plain = self.has_plain("special.plain_body")
        full = "nh-full" if plain else ""
        p = "special-needs/inner"
        framed = self.container(f"{p}/framed", styled({
            **flex("column", 0, 0), "padding": dims("1.5rem"), "background_background": "classic",
            # the inline start is the right edge in RTL
            "border_border": "solid", "border_width": dims(0, 4, 0, 0), "border_radius": dims(12),
            "css_classes": join("nh-framed", full)}, background_color="white", border_color="purple-700"),
            self.text_editor_block("special.framed", f"{p}/framed/text"), title="שאלות")
        if SPECIAL_READ_MORE:
            more_title = self.text("special.more_title", "full")
            item = self.container(f"{p}/more/item-1", {**flex("column", 16, 16), "padding": dims(16, 0, 0, 0),
                                                       "_title": more_title},
                                  self.text_editor_block("special.rest", f"{p}/more/item-1/rest"))
            accordion = self.widget(f"{p}/more", "nested-accordion", styled({
                "items": [{"item_title": more_title, "element_css_id": "",
                           "_id": hex7(f"{self.doc}/{p}/more/items/0")}],
                # «multiple», not «one»: with «one» and a 0 ms animation Elementor 4.4 closes the item again right after
                # opening it (its close-all animation of 0 ms finishes after the open; tested 18.11), so «להמשך קריאה»
                # never opened, in v4.0–v4.2 too. There is one item, so nothing else changes. No animation (0 ms)
                "default_state": "all_collapsed", "max_items_expended": "multiple",
                "n_accordion_animation_duration": {"unit": "ms", "size": 0, "sizes": []},
                # a span, not a div: Elementor prints the title inside a <span>; the summary is the control, no heading
                "title_tag": "span", "accordion_item_title_icon": icon("plus"),
                "accordion_item_title_icon_active": icon("minus"),
                "_css_classes": join("nh-more", full)},
                {"title_typography_typography": "accent"},
                normal_title_color="purple-700", hover_title_color="purple-800", active_title_color="purple-700",
                normal_icon_color="purple-700", hover_icon_color="purple-800", active_icon_color="purple-700"), [item])
            self.use("special.more_title", "full", accordion, "items.0.item_title")
            rest = [accordion]
        else:
            rest = self.text_editor_block("special.rest", f"{p}/rest", full)
        return self.section("special-needs", "מעון לבעלי צרכים מיוחדים", "cream",
                            self.heading_block("special.title", f"{p}/title", "h2", "h2", "purple-700")
                            + [framed]
                            + self.text_editor_block("special.lead", f"{p}/lead", full)
                            + rest
                            + self.text_editor_block("special.plain_body", f"{p}/plain"),
                            inner={"boxed_width": size(860)}, gap=24)

    # ---- 10 contact (10 §1.10) -----------------------------------------------------------------------------------------------
    def contact(self):
        """«נשמח שתבואו להכיר אותנו» (v4.3, Itzik 18.11): H2, the form's lead, and the visit form of the inner page in a
        white card (max 640px): Elementor Pro's Form, its texts those of #33. Submissions are kept in the admin
        (Elementor → Submissions, «save-to-database», without the visitor's IP or browser); no e-mail on the test site,
        the e-mail action (to FORM_TO) is added at launch (LAUNCH). The theme brings it to the inner form's
        accessibility (v4.js / v4.css: the error summary, the links between fields, hints and errors)."""
        card = self.container("contact/inner/card", styled({
            **flex("column", 0, 0), "width": size("min(640px, 100%)"), "_flex_align_self": "center",
            # v4.4 (Itzik 19.11: the band 15% lower): 1.5rem, was 2rem; phones keep 1.25rem
            "padding": dims("1.5rem"), "padding_mobile": dims("1.25rem"), "background_background": "classic",
            "border_radius": dims(16), **SOFT_SHADOW, "css_classes": "nh-form-card nh-reveal"},
            background_color="white"), [self.contact_form("contact/inner/card/form")], title="טופס")
        return self.section("contact", "נשמח שתבואו להכיר אותנו", "cream",
                            self.heading_block("contact.title", "contact/inner/title", "h2", "h2", "purple-700",
                                               align="center")
                            + self.text_editor_block("contact.sub", "contact/inner/sub", align="center")
                            + [card], inner={"flex_align_items": "stretch"}, gap=12, gap_mobile=12)

    FORM_FIELDS = (  # (custom_id, type, block, required, extra)
        ("note", "html", "contact.form.required_note", False, {}),
        ("name", "text", "contact.form.label_name", True, {}),
        ("phone", "tel", "contact.form.label_phone", True, {}),
        ("message", "textarea", "contact.form.label_message", False, {"rows": 3}),   # v4.4: 3 rows, was 4
        ("hint", "html", "contact.form.hint", False, {}),
        ("website", "honeypot", None, False, {}),
        ("consent", "acceptance", "contact.form.consent", True, {}),
        ("privacy", "html", "contact.form.privacy", False, {}),
    )

    def twin_html(self, block, tag="span", attrs=""):
        """A text with its plain twin in ONE field (a form field cannot be doubled): <tag><span class="nh-full">…</span>
        <span class="nh-plain">…</span></tag>; the theme shows one of them (spans: inline, so the line stays one)."""
        def part(mode):
            t = self.text(block, mode)
            if t in (None, ""):
                return ""
            body = t if self.blocks[block]["kind"] == "html" else html.escape(t, quote=False)
            return f'<span class="nh-{mode}">{body}</span>' if self.has_plain(block) else body
        inner = part("full") + (part("plain") if self.has_plain(block) else "")
        return f"<{tag}{attrs}>{inner}</{tag}>" if tag else inner

    def contact_form(self, path, prefix="contact.form", fields=None, subject="בקשה לביקור — מעון נווה האירוס",
                     extra_class=""):
        """Elementor Pro's Form with the theme's accessibility (nh-contact-form). prefix: the blocks of its texts
        (<prefix>.name, .submit, .success, .error, .invalid, .required); fields: (custom_id, type, block, required,
        extra), the visit form's by default (23.11: the contact page has its own, with an e-mail field)."""
        items, uses = [], []
        for n, (cid, ftype, block, required, extra) in enumerate(fields or self.FORM_FIELDS):
            it = {"_id": hex7(f"{self.doc}/{path}/fields/{cid}"), "custom_id": cid, "field_type": ftype,
                  "width": "100", **extra}
            if required:
                it["required"] = "true"
            if ftype == "html":
                klass = {"note": "nh-form__note", "hint": "nh-form__hint", "privacy": "nh-form__privacy"}[cid]
                # a name for the editor's list of fields only (Elementor prints no label for an HTML field), so the
                # client sees what each item is instead of «פריט #1»
                it["field_label"] = {"note": "טקסט: שדות חובה", "hint": "טקסט: הסבר להודעה",
                                     "privacy": "טקסט: פרטיות"}[cid]
                it["field_html"] = self.twin_html(block, "p", f' class="{klass}" id="nh-form-{cid}"')
                uses.append((block, f"form_fields.{n}.field_html"))
            elif ftype == "acceptance":
                it["field_label"] = ""
                it["acceptance_text"] = self.twin_html(block, tag=None)
                uses.append((block, f"form_fields.{n}.acceptance_text"))
            elif ftype == "honeypot":
                it["field_label"] = "אתר"                     # never seen; a person never fills it
            elif ftype == "select":                           # 24.11: the job of the CV form; extra: jobs (blocks), choose (block)
                choose, jobs = extra["choose"], extra["jobs"]
                it["field_label"] = self.text(block, "full")
                it["field_options"] = "\n".join([f"{self.text(choose, 'full')}|"] + [self.text(j, "full") for j in jobs])
                it.pop("choose", None)
                it.pop("jobs", None)
                uses.append((block, f"form_fields.{n}.field_label"))
                uses.append((choose, f"form_fields.{n}.field_options#in"))
                uses += [(j, f"form_fields.{n}.field_options#in") for j in jobs]
            elif ftype == "upload":                           # 24.11: the CV; the file is a link in the admin, and at
                it["field_label"] = self.text(block, "full")  # launch also an attachment of the e-mail (Pro: «both»)
                it.update(file_sizes="5", file_types="pdf,doc,docx", attachment_type="both" if LAUNCH else "link")
                uses.append((block, f"form_fields.{n}.field_label"))
            else:
                it["field_label"] = self.text(block, "full")
                uses.append((block, f"form_fields.{n}.field_label"))
            items.append(it)
        s = {"form_name": self.text(f"{prefix}.name", "full"), "form_fields": items, "input_size": "md",
             "show_labels": "true", "mark_required": "yes", "label_position": "above",
             "button_text": self.text(f"{prefix}.submit", "full"), "button_size": "md", "button_width": "100",
             "button_align": "stretch",
             # submissions are kept in the admin; no visitor's IP or browser is stored with them (privacy)
             "submit_actions": ["save-to-database"] + (["email"] if LAUNCH else []), "submissions_metadata": [],
             "form_validation": "custom", "custom_messages": "yes",
             "success_message": self.text(f"{prefix}.success", "full"),
             "error_message": self.text(f"{prefix}.error", "full"),
             "server_message": self.text(f"{prefix}.error", "full"),
             "invalid_message": self.text(f"{prefix}.invalid", "full"),
             "required_field_message": self.text(f"{prefix}.required", "full"),
             # v4.4 (Itzik 19.11): 12px between the fields, was 20; the fields themselves stay 56px (v4.css)
             "column_gap": size(0), "row_gap": size(12), "label_spacing": size(6),
             "field_border_width": dims(2), "field_border_radius": dims(8),
             "button_border_radius": dims(12), "button_text_padding": dims("1rem", "2rem"),
             "_css_classes": join("nh-contact-form", extra_class)}
        if any(f[1] == "select" for f in (fields or self.FORM_FIELDS)):
            s["email_subject"] = subject            # «… [field id="job"] …»: the job's title in the subject (24.11)
        if LAUNCH:
            # only the visitor's answers, each under its own label, and the date and time: no page address (it may
            # carry a query string), no IP or browser (Elementor's defaults for a hand-added action would add them)
            lines = [f"{self.text(b, 'full').rstrip('?')}: [field id=\"{cid}\"]" for cid, ftype, b, _, _ in
                     (fields or self.FORM_FIELDS) if ftype in ("text", "tel", "email", "textarea", "select", "upload")]
            lines.append(f"{CONSENT_NAME}: כן")           # the send is impossible without the box ticked
            s.update(email_to=FORM_TO, email_from=FORM_FROM or "", email_from_name="מעון נווה האירוס",
                     email_subject=subject, email_content="\n".join(lines),
                     email_content_type="plain", form_metadata=["date", "time"])
        w = self.widget(path, "form", styled(s, {"label_typography_typography": "accent",
                                                  "field_typography_typography": "text",
                                                  "button_typography_typography": "accent",
                                                  "html_typography_typography": "small",
                                                  "message_typography_typography": "text"},
                                             label_color="ink", mark_required_color="red-700", html_color="ink-2",
                                             field_text_color="ink", field_background_color="white",
                                             field_border_color="ink-2", button_background_color="purple-700",
                                             button_text_color="white", button_background_hover_color="purple-800",
                                             button_hover_color="white", success_message_color="green-700",
                                             error_message_color="red-700", inline_message_color="red-700"))
        for block, field in uses:
            if self.has_plain(block) and not field.endswith(".field_label") and "#" not in field:
                self.use(block, "full", w, field + "#full")
                self.use(block, "plain", w, field + "#plain")
            else:
                self.use(block, "full", w, field)
        self.use(f"{prefix}.name", "full", w, "form_name")
        self.use(f"{prefix}.submit", "full", w, "button_text")
        self.use(f"{prefix}.success", "full", w, "success_message")
        self.use(f"{prefix}.error", "full", w, "error_message")
        self.use(f"{prefix}.invalid", "full", w, "invalid_message")
        self.use(f"{prefix}.required", "full", w, "required_field_message")
        return w

    # ---- the contacts block: the shared template, in the footer (09 §4.7, 10 §1.11) ----------------------------------------
    CONTACTS = (("address", "map-marker-alt"), ("phone", "phone-alt"), ("email", "envelope"), ("links", "link"))

    def contacts(self):
        """The footer's first part, small (10 §1.11; Oleg 17.11): in each column a row with the icon (24px: 14 + 2 × 5)
        and the title (1rem), aligned to the title's first line, then the text at 14px (Kit «טקסט זעיר»); small gaps;
        4 columns, 2 on tablets and phones; white on night. These settings are the look: the theme only adds what
        Elementor has no control for. The H2 «פרטי קשר» stays visually hidden (a heading for screen readers only)."""
        self.doc = "contacts"
        white = ("white", "white")
        columns = []
        for name, icon_name in self.CONTACTS:
            p = f"contacts/grid/{name}"
            head = self.container(f"{p}/head", {**flex("row", 8, 8, flex_align_items="flex-start"), "padding": ZERO,
                                                "css_classes": "nh-contact__head"},
                                  [self.icon_widget(f"{p}/head/icon", icon(icon_name), "", 14, 5, "purple-700",
                                                    "white")]
                                  + self.heading_block(f"contacts.{name}.title", f"{p}/head/title", "h3",
                                                       "heading-small", "white"),
                                  title="סמל וכותרת")
            columns.append(self.container(p, {**flex("column", 4, 4, flex_align_items="flex-start"),
                                               "padding": ZERO, "css_classes": "nh-contact"},
                                          [head]
                                          + self.text_editor_block(f"contacts.{name}.body", f"{p}/text",
                                                                   typo="text-tiny", color="white", links=white),
                                          title=self.text(f"contacts.{name}.title", "full")))
        cols = self.container("contacts/grid", grid(4, 2, 2, gap_column=GRID_GAP, gap_row=12, gap_mobile=(16, 12)),
                              columns, title="עמודות")
        title = self.heading_block("contacts.title", "contacts/title", "h2", "h2", "white", "nh-visually-hidden")
        root = self.container("contacts", styled({
            **flex("column", 0, 0), "padding": ZERO, "background_background": "classic",
            "css_classes": "nh-contacts", "_element_id": "contacts"}, background_color="night"),
            title + [cols], inner=False, title="פרטי קשר")
        return [root]

    # ---- footer (10 §1.11) ---------------------------------------------------------------------------------------------------
    def footer(self):
        self.doc = "footer-v4"
        white = ("white", "white")
        template = self.widget("footer/contacts", "template", {"template_id": "@page-id:contacts",
                                                                "_title": "פרטי קשר — תבנית משותפת"})
        copyright_ = self.text_editor_block("footer.copyright", "footer/bottom/copyright", typo="text-tiny",
                                            color="white", links=white, align_mobile="center")
        links = self.text_editor_block("footer.links", "footer/bottom/links", typo="text-tiny", color="white",
                                       links=white, align_mobile="center")
        bottom = self.container("footer/bottom", {
            **flex("row", 8, 24, wrap="wrap", flex_direction_mobile="column", flex_justify_content="space-between",
                   flex_align_items="center", flex_justify_content_mobile="center"),
            "padding": dims(16, 0, 0, 0), "border_border": "solid", "border_width": dims(1, 0, 0, 0),
            "border_color": "rgba(255, 255, 255, 0.2)", "css_classes": "nh-footer__bottom"},
            copyright_ + links, title="שורה תחתונה")
        root = self.container("footer", styled({     # Oleg 17.11: smaller paddings and gap (were 3rem / 2.5rem, 24px)
            "content_width": "boxed", "boxed_width": size(CONTENT_WIDTH), **flex("column", 20, 20),
            "padding": dims("2.25rem", GUTTER[""], "1.25rem", GUTTER[""]),
            "padding_tablet": dims("2.25rem", GUTTER["_tablet"], "1.25rem", GUTTER["_tablet"]),
            "padding_mobile": dims("2rem", GUTTER["_mobile"], "1.5rem", GUTTER["_mobile"]),
            "background_background": "classic", "css_classes": "nh-footer-v4"}, background_color="night"),
            [template, bottom], inner=False, title="כותרת תחתונה v4")
        return [root]

    # ---- documents ------------------------------------------------------------------------------------------------------------
    def home(self):
        self.doc = "home"
        return [self.hero(), self.specialties(), self.life(), self.pro(),
                self.snoezelen(), self.special(), self.contact()]



# ---- inner pages (Oleg 23.11: every page of the live menu and footer, on the home's design) ---------------------------
PAGES_SRC = os.path.join(ROOT, "content", "v4", "pages")
PAGES_OUT = os.path.join(OUT, "pages")
POSTS_SRC = os.path.join(ROOT, "content", "v4", "posts")      # 23.11, batch 3: the live posts, one file each
POSTS_OUT = os.path.join(OUT, "posts")
CATEGORIES = {"מאמרים וכתבות": "ספרים-ומאמרים", "חדשות ועדכונים": "חדשות-ועדכונים"}    # the live categories: name → slug
POST_DATE = re.compile(r"^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d$")
READING = 860                      # px: the reading column of a page's text (as the home's special-needs column)
MAP_HOSTS = ("maps.google.com", "www.google.com")
LINK_HOSTS = ("docs.google.com", "posta.co.il", "www.openstreetmap.org")   # batch 3: hosts the live texts link to (a link: nothing loads from them)      # the click-to-load map's frame (data-src, never loaded alone)
CONTACT_FIELDS = (  # the contact page's form (the live «צור קשר»: name, e-mail, phone and the message, all required)
    ("note", "html", "form.required_note", False, {}),
    ("name", "text", "form.label_name", True, {}),
    ("email", "email", "form.label_email", True, {}),
    ("phone", "tel", "form.label_phone", True, {}),
    ("message", "textarea", "form.label_message", True, {"rows": 4}),
    ("hint", "html", "form.hint", False, {}),
    ("website", "honeypot", None, False, {}),
    ("consent", "acceptance", "form.consent", True, {}),
    ("privacy", "html", "form.privacy", False, {}),
)


APPLY_FIELDS = (  # 24.11 (Itzik): the CV form of «דרושים» — one form for the page, opened by each job's button (no duplicated
    # field ids on the page); the job is a field of its own, so its title is in the submission and in the e-mail's subject
    ("note", "html", "apply.required_note", False, {}),
    ("job", "select", "apply.label_job", True, {"choose": "apply.job_choose", "jobs": []}),
    ("name", "text", "apply.label_name", True, {}),
    ("phone", "tel", "apply.label_phone", True, {}),
    ("cv", "upload", "apply.label_cv", True, {}),
    ("website", "honeypot", None, False, {}),
    ("consent", "acceptance", "apply.consent", True, {}),
    ("privacy", "html", "apply.privacy", False, {}),
)
APPLY_SUBJECT = 'קו"ח למשרה: [field id="job"] — מעון נווה האירוס'


def page_specs():
    """content/v4/pages/<key>.json and (batch 3) content/v4/posts/<key>.json in their order ("order"; a parent before
    its children). A page spec may also be a Theme Builder template ("post_type": "elementor_library", with its
    "document_type" and "conditions": the archive template)."""
    out = []
    for src, kind in ((PAGES_SRC, "page"), (POSTS_SRC, "post")):
        if not os.path.isdir(src):
            continue
        for name in sorted(os.listdir(src)):
            if not name.endswith(".json"):
                continue
            rel = os.path.relpath(os.path.join(src, name), ROOT)
            with open(os.path.join(src, name), encoding="utf-8") as fh:
                spec = json.load(fh)
            if spec.get("key") != name[:-5]:
                die(f"{rel}: its key is {spec.get('key')!r}, not {name[:-5]!r}")
            ptype = spec.get("post_type", "page")
            if (kind == "post") != (ptype == "post") or ptype not in ("page", "post", "elementor_library"):
                die(f"{rel}: post_type {ptype!r} does not belong in {os.path.relpath(src, ROOT)}")
            if ptype == "post":
                if not re.fullmatch(r"post-\d+", spec["key"]) or spec.get("parent"):
                    die(f"{rel}: a post's key is post-<live id>, and a post has no parent")
                if not POST_DATE.match(str(spec.get("date"))):
                    die(f"{rel}: date {spec.get('date')!r} is not the live date as YYYY-MM-DDTHH:MM:SS")
                if not spec.get("categories") or any(c not in CATEGORIES for c in spec["categories"]):
                    die(f"{rel}: categories {spec.get('categories')!r}: each one of {', '.join(CATEGORIES)}")
            if ptype == "elementor_library" and (spec.get("document_type") != "archive" or not spec.get("conditions")):
                die(f"{rel}: a template here is the archive template, with its display conditions")
            out.append(spec)
    out.sort(key=lambda sp: (sp.get("order", 999), sp["key"]))
    keys = [sp["key"] for sp in out]
    for sp in out:
        parent = sp.get("parent")
        if parent and (parent not in keys or keys.index(parent) > keys.index(sp["key"])):
            die(f"content/v4/pages/{sp['key']}.json: parent {parent!r} is not a page built before it")
    return out


def page_inputs():
    return sorted(os.path.relpath(os.path.join(src, n), ROOT) for src in (PAGES_SRC, POSTS_SRC) if os.path.isdir(src)
                  for n in os.listdir(src) if n.endswith(".json"))


def spec_out(spec):
    """Where a spec's document is written: templates/v4/posts/ for a post, templates/v4/pages/ otherwise."""
    return os.path.join(POSTS_OUT if spec.get("post_type") == "post" else PAGES_OUT, f"{spec['key']}.json")


def excerpt(spec, words=30):
    """A post's excerpt for the archive's cards: the start of its first text, as the live post has it (full text)."""
    for sec in spec["sections"]:
        blocks = sec.get("text") or []
        for block in (blocks if isinstance(blocks, list) else [blocks]):
            full = spec["blocks"][block]["full"]
            text = html.unescape(re.sub(r"<[^>]+>", " ", full))
            ws = text.split()
            if ws:
                return " ".join(ws[:words]) + ("…" if len(ws) > words else "")
    return ""


class PageBuilder(Builder):
    """An inner page from content/v4/pages/<key>.json, made of the home's parts: sections on the cream ground with the
    same padding (SECTION_PAD), the 1320px column, nh-reveal, Heading and Text Editor widgets through the Kit, every
    text with its plain twin (the «נגישות» panel's «בשפה פשוטה»), photos from templates/v4/media.json. The first
    section opens with the page's H1; then one section per entry of "sections", in the live page's order:
      text        [heading] and text blocks in the reading column (860px)
      text-photo  [heading] and text beside a photo (the live column order: text at the start, the photo at the end;
                  on phones the text first)
      quote       a quotation in the reading column
      gallery     [heading] and Elementor's Basic Gallery (links to the files, Elementor's lightbox)
      table       [heading] and a table (a text block of kind html) in a white card
      form        [heading], [lead] and Elementor Pro's Form in a white card: «visit» = the home's visit form (its texts
                  are the home's blocks), «contact» = the contact page's own (CONTACT_FIELDS, the page's form.* blocks)
      map         the click-to-load map (the theme loads Google's frame only after a click: nothing before)
    Batch 3 (23.11): gallery also takes text blocks under its heading (a news item: heading, text, photos); video = a
    landscape YouTube video with [heading] and text (our cover, nothing from YouTube before a click); a post
    (content/v4/posts/) is built the same way, its H1 is its title; the archive template (post_type elementor_library,
    "archive": true) opens with Pro's Archive Title as the H1 (the category's name, without «קטגוריה:») and lists the
    posts with Pro's Archive Posts (archive_posts)."""

    def __init__(self, spec, home_blocks, media_list):
        super().__init__({"blocks": {**home_blocks, **spec["blocks"]}}, media_list)
        self.spec = spec
        self.doc = spec["key"]

    def column(self, path, children, title="עמודת קריאה"):
        return self.container(path, {**flex("column", 16, 16), "width": size(f"min({READING}px, 100%)"),
                                     "padding": ZERO, "css_classes": "nh-page__col"}, children, title=title)

    def head(self, sec, path, tag="h2"):
        return self.heading_block(sec["heading"], path, tag, tag, "purple-700") if sec.get("heading") else []

    def texts(self, sec, path):
        blocks = sec.get("text") or []
        blocks = blocks if isinstance(blocks, list) else [blocks]
        out = []
        for i, block in enumerate(blocks):
            out += self.text_editor_block(block, f"{path}/t{i}", "nh-page__text")
        return out

    def sec_text(self, name, sec):
        p = f"{name}/inner/col"
        return [self.column(p, self.head(sec, f"{p}/h", sec.get("tag", "h2")) + self.texts(sec, p))]

    def sec_text_photo(self, name, sec):
        p = f"{name}/inner/grid"
        text = self.container(f"{p}/text", {**flex("column", 16, 16), "padding": ZERO, "css_classes": "nh-page__text-col"},
                              self.head(sec, f"{p}/text/h") + self.texts(sec, f"{p}/text"), title="טקסט")
        photo = self.image(f"{p}/photo", sec["photo"], "nh-page__img", size_name="large", _title="תמונה")
        return [self.container(p, grid("7fr 5fr", "1fr 1fr", 1, gap_column=48, gap_row=24, grid_align_items="start"),
                               [text, photo], title="טקסט ותמונה")]

    def sec_quote(self, name, sec):
        p = f"{name}/inner/col"
        return [self.column(p, self.text_editor_block(sec["text"], f"{p}/q", "nh-quote", typo="quote",
                                                      color="purple-800"), title="ציטוט")]

    def sec_gallery(self, name, sec):
        for n in sec["photos"]:
            if n not in self.media_names:
                die(f"templates/v4/media.json has no {n} (page {self.doc})")
        s = {"wp_gallery": [{"id": f"@media:{n}", "url": ""} for n in sec["photos"]], "thumbnail_size": "medium_large",
             "gallery_columns": str(sec.get("columns", 3)),        # one value: phones get 2 columns from the theme
             "gallery_link": "file", "open_lightbox": "yes", "gallery_rand": "", "gallery_display_caption": "none",
             "image_spacing": "custom", "image_spacing_custom": size(16), "image_border_radius": dims(12),
             "_css_classes": "nh-gallery"}
        texts = self.texts(sec, f"{name}/inner") if sec.get("text") else []
        return self.head(sec, f"{name}/inner/h") + texts + [self.widget(f"{name}/inner/gallery", "image-gallery", s)]

    def card(self, path, children, title, extra=None):
        return self.container(path, styled({
            **flex("column", 16, 16), "padding": dims("1.5rem"), "padding_mobile": dims("1.25rem"),
            "background_background": "classic", "border_radius": dims(16), **SOFT_SHADOW,
            "css_classes": "nh-page__card", **(extra or {})}, background_color="white"), children, title=title)

    def sec_table(self, name, sec):
        p = f"{name}/inner"
        return self.head(sec, f"{p}/h") + [self.card(f"{p}/card", self.text_editor_block(
            sec["table"], f"{p}/card/table", "nh-table"), "טבלה")]

    def sec_form(self, name, sec):
        p = f"{name}/inner"
        if sec["form"] == "visit":
            form = self.contact_form(f"{p}/card/form")
        else:
            form = self.contact_form(f"{p}/card/form", prefix="form", fields=CONTACT_FIELDS,
                                     subject="פנייה מהאתר — מעון נווה האירוס")
        card = self.card(f"{p}/card", [form], "טופס", {"width": size("min(640px, 100%)"), "_flex_align_self": "flex-start",
                                                         "css_classes": "nh-page__card nh-form-card"})   # under its heading
        lead = self.text_editor_block(sec["lead"], f"{p}/lead", "nh-page__text") if sec.get("lead") else []
        return self.head(sec, f"{p}/h") + lead + [card]

    def sec_form_details(self, name, sec):
        """The live «צור קשר», 24.11 (Itzik): the form (15% smaller, the theme's nh-form--s85) and beside it the contact
        details, top to bottom (no map any more); phones: the form first."""
        p = f"{name}/inner/grid"
        form = self.contact_form(f"{p}/form/card/form", prefix="form", fields=CONTACT_FIELDS,
                                 subject="פנייה מהאתר — מעון נווה האירוס", extra_class="nh-form--s85")
        left = self.container(f"{p}/form", {**flex("column", 16, 16), "padding": ZERO},
                              self.head(sec, f"{p}/form/h") + [self.card(f"{p}/form/card", [form], "טופס", {
                                  "css_classes": "nh-page__card nh-form-card"})], title="טופס")
        kids = [self.card(f"{p}/details/card", self.text_editor_block(
            sec["details"], f"{p}/details/card/list", "nh-page__text nh-contact-details-box"),
            "פרטי קשר", {"css_classes": "nh-page__card nh-details-card"})]
        if sec.get("map"):
            kids.append(self.static_map(f"{p}/details/map", sec["map"]))
        right = self.container(f"{p}/details", {**flex("column", 16, 16), "padding": ZERO}, kids, title="פרטי קשר")
        return [self.container(p, grid("7fr 5fr", 1, 1, gap_column=48, gap_row=32, grid_align_items="start"),
                               [left, right], title="טופס ופרטי קשר")]

    def static_map(self, path, m):
        """24.11 (Itzik): a static picture of the map (our own file in the media library, its OpenStreetMap tiles cut and
        stitched by scripts/build_contact_map.py: nothing is loaded from another server). The whole picture is a link to Waze
        in a new tab; its alt and caption are «ניווט עם Waze»; the credit «© OpenStreetMap» (a link, loads nothing) under it."""
        cap = self.text(m["caption"], "full")
        img = self.image(f"{path}/img", m["photo"], "nh-map-img", size_name="large", url=m["url"],
                         link_attributes=f"aria-label|{cap} (נפתח בלשונית חדשה),rel|noopener noreferrer", caption_source="custom",
                         caption=html.escape(cap, quote=False), _title="מפה")
        img["settings"]["link"]["is_external"] = "on"
        self.use(m["caption"], "full", img, "caption")
        credit = self.text_editor_block(m["credit"], f"{path}/credit", "nh-map-credit", typo="small", color="ink-2")
        return self.card(path, [img] + credit, "מפה", {"css_classes": "nh-page__card nh-map-card"})

    def sec_text_video(self, name, sec):
        """Text beside a vertical YouTube video in the home's phone frame (the snoezelen Short's look): nothing from
        YouTube before a click (the theme's question), our own cover."""
        p = f"{name}/inner/grid"
        text = self.container(f"{p}/text", {**flex("column", 16, 16), "padding": ZERO, "css_classes": "nh-page__text-col"},
                              self.head(sec, f"{p}/text/h") + self.texts(sec, f"{p}/text"), title="טקסט")
        video = self.container(f"{p}/video", {**flex("column", 0, 0, flex_align_items="center"), "padding": ZERO},
                               [self.video(f"{p}/video/short", sec["video"], ratio="916", _element_width="initial",
                                           _element_custom_width=size(270), _flex_align_self="center",
                                           _css_classes="nh-phone-frame")], title="סרטון")
        return [self.container(p, grid("7fr 5fr", "7fr 5fr", 1, gap_column=48, gap_row=32, grid_align_items="center"),
                               [text, video], title="טקסט וסרטון")]

    def sec_video(self, name, sec):
        """[heading] and text in the reading column, then a landscape YouTube video (16:9) as wide as the column: our
        cover (templates/v4/media.json video-<id>), nothing from YouTube before a click."""
        p = f"{name}/inner/col"
        return [self.column(p, self.head(sec, f"{p}/h", sec.get("tag", "h2")) + self.texts(sec, p)
                            + [self.video(f"{p}/video", sec["youtube"], _css_classes="nh-page__video")],
                            title="סרטון")]

    def archive_title(self, path):
        """Pro's Archive Title as the H1, styled as every page's H1; its tag without the context, so a category's page
        is called by the category's name only (the live «ספרים ומאמרים», renamed «מאמרים וכתבות» 24.11, slug kept)."""
        tag = '[elementor-tag id="nh4arch" name="archive-title" settings="%7B%22include_context%22%3A%22%22%7D"]'
        s = {"header_size": "h1", "__dynamic__": {"title": tag}}
        return self.widget(path, "theme-archive-title", styled(s, {"typography_typography": "h1"},
                                                               title_color="purple-700"))

    def sec_archive_posts(self, name, sec):
        """The archive's posts (Pro's Archive Posts, its Cards skin): the featured image, the title as an H2 link, the
        date and the excerpt; numbered pages. The cards' look is the theme's (.nh-archive in v4.css)."""
        sk = "archive_cards"
        s = {"_skin": sk, f"{sk}_columns": "3", f"{sk}_columns_tablet": "2", f"{sk}_columns_mobile": "1",
             f"{sk}_thumbnail_size_size": "medium_large", f"{sk}_item_ratio": size(0.62),
             f"{sk}_title_tag": "h2", f"{sk}_meta_data": ["date"], f"{sk}_show_excerpt": "yes",
             f"{sk}_excerpt_length": 30, f"{sk}_show_read_more": "yes",
             f"{sk}_read_more_text": self.text(sec["more"], "full"), f"{sk}_show_badge": "", f"{sk}_show_avatar": "",
             "pagination_type": "numbers", "nothing_found_message": self.text(sec["empty"], "full"),
             "_css_classes": "nh-archive"}
        w = self.widget(f"{name}/inner/posts", "archive-posts", s)
        self.use(sec["more"], "full", w, f"{sk}_read_more_text")
        self.use(sec["empty"], "full", w, "nothing_found_message")
        return [w]

    def sec_products(self, name, sec):
        """A grid of photos, each with its caption under it (the product and its price, as the live gallery shows them;
        a photo whose media entry has "caption": "" has none: on the live site its caption is only its file name);
        each opens larger in Elementor's lightbox (one slideshow for the grid)."""
        p = f"{name}/inner/grid"
        cells = []
        for n, photo in enumerate(sec["photos"]):
            if photo not in self.media_names:
                die(f"templates/v4/media.json has no {photo} (page {self.doc})")
            entry = self.media.get(photo) or {}
            caption = entry.get("caption", entry.get("alt")) if sec.get("captions") else ""
            extra = {"link_to": "file", "open_lightbox": "yes", "_title": "תמונה"}
            if caption:
                extra.update(caption_source="custom", caption=caption)
            cells.append(self.image(f"{p}/p{n}", photo, join("nh-product", "nh-product--whole" if sec.get("whole") else ""),
                                    size_name="medium_large", **extra))
        cols = sec.get("columns", 4)
        return self.head(sec, f"{name}/inner/h") + [self.container(
            p, grid(cols, min(cols, 3), 2, gap_column=24, gap_row=24, gap_mobile=(12, 16)), cells, title="תמונות")]

    def sec_cards(self, name, sec):
        """Items of the same kind one under the other, each in a white card with its heading (the jobs of «דרושים»).
        sec["apply"] (24.11): each card ends with the button of the CV form, and the form (one, a card of its own) closes
        the section; the theme opens it in a dialog from the button (the link works without a script too)."""
        p = f"{name}/inner/col"
        apply_ = sec.get("apply")
        cards = []
        for n, item in enumerate(sec["items"]):
            q = f"{p}/c{n}"
            extra = []
            if apply_:
                label = html.escape(self.text(apply_["button"], "full"), quote=False)
                job = html.escape(self.text(item["heading"], "full"), quote=True)
                code = (f'<p class="nh-apply__row"><a class="nh-apply__btn" href="#nh-apply" data-job="{job}">{label}</a></p>')
                w = self.widget(f"{q}/apply", "html", {"html": code, "_css_classes": "nh-apply-widget"})
                self.use(apply_["button"], "full", w, "html#in")
                extra = [w]
            cards.append(self.card(q, self.heading_block(item["heading"], f"{q}/h", "h2", "h3", "purple-700")
                                   + self.texts(item, q) + extra, self.text(item["heading"], "full")))
        tail = []
        if apply_:
            fields = tuple((c, t, b, r, ({**e, "jobs": apply_["jobs"]} if t == "select" else e))
                           for c, t, b, r, e in APPLY_FIELDS)
            form = self.contact_form(f"{p}/apply/card/form", prefix=apply_["prefix"], fields=fields,
                                     subject=APPLY_SUBJECT, extra_class="nh-apply-form")
            card = self.card(f"{p}/apply/card", self.heading_block(apply_["heading"], f"{p}/apply/h", "h2", "h3", "purple-700")
                             + [form], "טופס שליחת קו\"ח", {"css_classes": "nh-page__card nh-form-card"})
            tail = [self.container(f"{p}/apply", {**flex("column", 0, 0), "padding": ZERO, "_element_id": "nh-apply",
                                                   "css_classes": "nh-apply"}, [card], title="טופס שליחת קו\"ח")]
        return [self.column(p, self.head(sec, f"{p}/h") + cards + tail, title="כרטיסים")]

    def sec_map(self, name, sec):
        return self.map_widget(f"{name}/inner", sec)

    def map_widget(self, path, sec):
        src = sec["src"]
        if urllib.parse.urlsplit(src).hostname not in MAP_HOSTS:
            die(f"page {self.doc}: map {src!r} is not on {', '.join(MAP_HOSTS)}")
        label = html.escape(self.text(sec["label"], "full"), quote=True)
        body = self.twin_html(sec["text"], "p", ' class="nh-map__text"')
        button = self.twin_html(sec["button"], "button", ' type="button" class="nh-map__btn"')
        link_ = self.twin_html(sec["link"], "a", f' class="nh-map__link" href="{html.escape(sec["href"], quote=True)}"'
                               ' target="_blank" rel="noopener"')
        code = (f'<div class="nh-map" data-src="{html.escape(src, quote=True)}" data-title="{label}" role="region" '
                f'aria-label="{label}">{body}<p class="nh-map__actions">{button}{link_}</p></div>')
        w = self.widget(f"{path}/map", "html", {"html": code, "_css_classes": "nh-map-widget"})
        for block in (sec["text"], sec["button"], sec["link"]):     # inside the code: checked as contained
            self.use(block, "full", w, "html#full" if self.has_plain(block) else "html#in")
            if self.has_plain(block):
                self.use(block, "plain", w, "html#plain")
        self.use(sec["label"], "full", w, "html#label")
        return [self.card(f"{path}/card", [w], "מפה")]

    def page(self):
        self.doc = self.spec["key"]
        out = []
        for n, sec in enumerate(self.spec["sections"]):
            name = sec.get("id") or f"s{n + 1}"
            make = getattr(self, "sec_" + sec["type"].replace("-", "_"), None)
            if make is None:
                die(f"page {self.doc}: unknown section type {sec['type']!r}")
            kids = []
            if n == 0 and self.spec.get("archive"):     # the archive's H1: the category's (archive's) own name
                kids.append(self.archive_title(f"{name}/inner/h1"))
            elif n == 0:     # the page's H1 (and its lead) open the first section
                kids += self.heading_block(self.spec["h1"], f"{name}/inner/h1", "h1", "h1", "purple-700")
                if self.spec.get("lead"):
                    kids += self.text_editor_block(self.spec["lead"], f"{name}/inner/lead", "nh-page__lead")
            kids += make(name, sec)
            out.append(self.section(name, sec.get("title") or (self.text(sec["heading"], "full") if sec.get("heading")
                                                               else self.spec["title"]),
                                    "cream", kids, gap=24, gap_mobile=16))
        return out


def page_doc(spec, data, uses, src):
    ptype = spec.get("post_type", "page")
    doc = {"key": spec["key"], "title": spec["title"], "slug": spec["slug"], "parent": spec.get("parent"),
           "front_page": False, "post_type": ptype,
           "document_type": {"page": "wp-page", "post": "wp-post"}.get(ptype) or spec["document_type"],
           "template": "" if ptype == "elementor_library" else "elementor_header_footer",
           "conditions": list(spec.get("conditions") or []), "page_settings": {},
           "built_from": {**src, "widgets": sorted({e["widgetType"] for e in walk_elements(data)
                                                    if e["elType"] == "widget"}),
                          "blocks": {k: uses[k] for k in sorted(uses)}},
           "elementor_data": data}
    if ptype == "post":
        # 23.11, batch 3: the live post's date, categories (created with the live slugs) and featured image; the
        # excerpt the archive's cards show (the start of the live text)
        doc["post"] = {"date": spec["date"],
                       "categories": [{"name": c, "slug": CATEGORIES[c]} for c in spec["categories"]],
                       "featured": media(spec["featured"]) if spec.get("featured") else None,
                       "excerpt": excerpt(spec)}
    return doc

# the setting that holds a block's text, per widget type
FIELDS = {"heading": "title", "text-editor": "editor", "button": "text", "icon-box": "title_text"}

DOCS = {
    "home": {"title": "דף הבית", "slug": "דף-הבית", "front_page": True, "post_type": "page",
             "document_type": "wp-page", "template": "elementor_header_footer", "conditions": [], "page_settings": {}},
    "header-v4": {"title": "כותרת עליונה v4 — נווה האירוס", "slug": "nh-header-v4", "front_page": False,
                  "post_type": "elementor_library", "document_type": "header", "template": "",
                  "conditions": ["include/general"],          # 23.11: every page (was the front page only)
                  "page_settings": {"content_wrapper_html_tag": "header"}},
    "footer-v4": {"title": "כותרת תחתונה v4 — נווה האירוס", "slug": "nh-footer-v4", "front_page": False,
                  "post_type": "elementor_library", "document_type": "footer", "template": "",
                  "conditions": ["include/general"],          # 23.11: every page (was the front page only)
                  "page_settings": {"content_wrapper_html_tag": "footer"}},
    "contacts": {"title": "פרטי קשר — בלוק משותף", "slug": "nh-contacts", "front_page": False,
                 "post_type": "elementor_library", "document_type": "container", "template": "", "conditions": [],
                 "page_settings": {}},
}


def files_sha256(paths):
    rows = sorted((rel, hashlib.sha256(open(os.path.join(ROOT, rel), "rb").read()).hexdigest()) for rel in paths)
    return hashlib.sha256("".join(f"{rel}\t{digest}\n" for rel, digest in rows).encode()).hexdigest()


def source_info():
    """What templates/v4/*.json are built from (scripts/test_site.py v4 compares it with the files). 23.11: the inner
    pages' specs (content/v4/pages/*.json) too."""
    inputs = list(INPUTS) + page_inputs()
    return {"inputs": inputs, "inputs_sha256": files_sha256(inputs)}


def load_inputs():
    with open(CONTENT, encoding="utf-8") as fh:
        content = json.load(fh)
    with open(MEDIA, encoding="utf-8") as fh:
        media_list = json.load(fh)
    return content, media_list


def kit_json():
    def typo(item_id, title, weight, size_, size_mobile, line):
        item = {"_id": item_id, "title": title, "typography_typography": "custom", "typography_font_family": FONT,
                "typography_font_weight": str(weight)}
        if size_:
            item["typography_font_size"] = size(size_)
        if size_mobile:
            item["typography_font_size_mobile"] = size(size_mobile)
        if line:
            item["typography_line_height"] = {"unit": "em", "size": line, "sizes": []}
        return item
    return {
        "system_colors": [{"_id": cid, "title": title, "color": value} for cid, _, title, value in SYSTEM_COLORS],
        "custom_colors": [{"_id": COLORS[token][0], "title": title, "color": value} for token, title, value in CUSTOM_COLORS],
        "system_typography": [typo(*t) for t in SYSTEM_TYPOGRAPHY],
        "custom_typography": [typo(TYPO_IDS[t[0]], *t[1:]) for t in CUSTOM_TYPOGRAPHY],
    }


def menu_target(item):
    """The live href → a token on the test host: the front page, the house page, or @home:/<live path>/ decoded."""
    path = urllib.parse.unquote(urllib.parse.urlsplit(item["live_href"]).path) or "/"
    if path == "/":
        return {"url": "@page:home", "page": "home"}
    if path.strip("/") == "מעון-ראשי-בית-לחיים":
        return {"url": "@page:main-home", "page": "main-home"}
    return {"url": "@home:" + path}


def menu_json(content):
    def item(src):
        return {"label": src["label"], **menu_target(src), "children": [item(c) for c in src.get("children") or []]}
    return {"name": "תפריט ראשי", "slug": "nh-main", "source": "content/v4/home.json \"menu\" (the live header menu)",
            "items": [item(i) for i in content["menu"]]}


def build():
    content, media_list = load_inputs()
    b = Builder(content, media_list)
    data = {"header-v4": b.header(), "home": b.home(), "contacts": b.contacts(), "footer-v4": b.footer()}
    src = source_info()
    docs = {}
    for key in DOC_KEYS:
        uses = {}
        for block, mode, doc, element, field in b.uses:
            if doc == key:
                uses.setdefault(block, {})[mode] = {"element": element, "field": field}
        docs[key] = {"key": key, **DOCS[key],
                     "built_from": {**src, "widgets": sorted({e["widgetType"] for e in walk_elements(data[key])
                                                              if e["elType"] == "widget"}),
                                    "blocks": {k: uses[k] for k in sorted(uses)}},
                     "elementor_data": data[key]}
    for spec in page_specs():
        pb = PageBuilder(spec, content["blocks"], media_list)
        data = pb.page()
        uses = {}
        for block, mode, doc, element, field in pb.uses:
            uses.setdefault(block, {})[mode] = {"element": element, "field": field}
        docs[spec["key"]] = page_doc(spec, data, uses, src)
    kit = {"built_from": src, **kit_json()}
    menu = {"built_from": src, **menu_json(content)}
    return docs, kit, menu


# ---- check ---------------------------------------------------------------------------------------------------------------
def walk_elements(elements):
    for el in elements:
        yield el
        yield from walk_elements(el.get("elements") or [])


def walk_values(value, path=""):
    yield path, value
    if isinstance(value, dict):
        for k, v in value.items():
            yield from walk_values(v, f"{path}/{k}")
    elif isinstance(value, list):
        for i, v in enumerate(value):
            yield from walk_values(v, f"{path}/{i}")


def classes_of(el):
    s = el.get("settings") or {}
    return set(str(s.get("_css_classes") or s.get("css_classes") or "").split())


def heading_level(el):
    """The level of a heading the element prints (Heading h1–h6, an Icon Box's title), else None."""
    s = el.get("settings") or {}
    tag = None
    if el.get("widgetType") in ("heading", "theme-archive-title"):
        tag = s.get("header_size")
    elif el.get("widgetType") == "icon-box" and s.get("title_text"):
        tag = s.get("title_size") or "h3"
    return int(tag[1]) if re.fullmatch(r"h[1-6]", str(tag)) else None


def headings(elements, docs, mode, inherited=frozenset(), expand=True):
    """(level, element id) of the headings shown in mode 'full' or 'plain', in document order; the Template widget's
    template (@page-id:<key>) is expanded in place (unless expand is false)."""
    out = []
    for el in elements:
        cls = classes_of(el) | inherited
        if (mode == "full" and "nh-plain" in cls) or (mode == "plain" and "nh-full" in cls):
            continue
        s = el.get("settings") or {}
        level = heading_level(el)
        if level:
            out.append((level, el["id"]))
        if el.get("widgetType") == "template" and expand:
            m = re.fullmatch(r"@page-id:([a-z0-9-]+)", str(s.get("template_id")))
            if m and m.group(1) in docs:
                out += headings(docs[m.group(1)]["elementor_data"], docs, mode)
        out += headings(el.get("elements") or [], docs, mode, frozenset(cls & {"nh-full", "nh-plain"}), expand)
    return out


def heading_order(levels, where, problems, one_h1):
    for (a, _), (b, el_id) in zip(levels, levels[1:]):
        if b > a + 1:
            problems.append(f"{where}: h{a} → h{b} at {el_id} skips a level")
    if one_h1:
        n = [lv for lv, _ in levels].count(1)
        if n != 1:
            problems.append(f"{where}: {n} h1, expected one")
        elif levels[0][0] != 1:
            problems.append(f"{where}: the first heading is h{levels[0][0]}, not h1")


def link_urls(el):
    """(path, url) of the links an element's settings hold (link.url controls)."""
    for path, v in walk_values(el.get("settings") or {}, el.get("id")):
        if path.endswith("/url") and isinstance(v, str):
            yield path, v


def page_problems(docs, media_list):
    """The page as a whole (spec 10 §1–§3): sections, hero, anchors, the contacts template, reveal, the phone frame."""
    problems = []
    home = (docs.get("home") or {}).get("elementor_data") or []
    tops = [(el.get("settings") or {}) for el in home]
    names = [s.get("_element_id") for s in tops]
    if names != list(SECTIONS):
        problems.append(f"home: the sections are {names}, not spec 10 §1's {list(SECTIONS)}")
    for el, s in zip(home, tops):
        name = s.get("_element_id")
        cls = classes_of(el)
        if s.get("content_width") != "full":
            problems.append(f"home/{name}: content_width {s.get('content_width')!r}, not full (spec 10 §1)")
        if not {"nh-sec", f"nh-sec--{name}"} <= cls:
            problems.append(f"home/{name}: css_classes without nh-sec nh-sec--{name}")
        if not s.get("background_background") or "background_color" not in (s.get("__globals__") or {}):
            problems.append(f"home/{name}: no Kit background")
        if any(MOTION.search(k) for k in s):
            problems.append(f"home/{name}: a motion setting")
    by_name = dict(zip(names, home))
    # the hero: the photo (hero-wide, its focus), the content at the bottom with the H1, no reveal
    hero = by_name.get("hero")
    if hero:
        hs = hero["settings"]
        if "nh-hero" not in classes_of(hero) or hs.get("flex_justify_content") != "flex-end" \
                or (hs.get("min_height") or {}).get("unit") != "vh" or hs.get("overflow") != "hidden":
            problems.append("home/hero: not nh-hero, at least 100vh, overflow hidden, content at the bottom")
        kids = hero.get("elements") or []
        photo = next((k for k in kids if "nh-hero__img" in classes_of(k)), None)
        content = next((k for k in kids if "nh-hero__content" in classes_of(k)), None)
        if not photo or photo.get("widgetType") != "image" \
                or (photo["settings"].get("image") or {}).get("id") != f"@media:{HERO_PHOTO}":
            problems.append(f"home/hero: no Image widget nh-hero__img with @media:{HERO_PHOTO}")
        else:
            focus = (media_list.get(HERO_PHOTO) or {}).get("focus")
            fx = sorted(c for c in classes_of(photo) if re.fullmatch(r"nh-f[xy]-\d+", c))
            if focus:
                x, y = (int(5 * round(float(v) / 5)) for v in re.findall(r"\d+(?:\.\d+)?", focus)[:2])
                if fx != [f"nh-fx-{x}", f"nh-fy-{y}"]:
                    problems.append(f"home/hero: the photo's focus classes {fx} do not match media.json's {focus!r}")
        if not content or not any(heading_level(e) == 1 for e in walk_elements(content.get("elements") or [])):
            problems.append("home/hero: no nh-hero__content holding the H1")
        if any("nh-reveal" in classes_of(e) for e in walk_elements([hero])):
            problems.append("home/hero: nh-reveal on the first screen")
    # nh-reveal (spec 10 §3): in every other section; on the cards, the pro cells, the years and the video items
    for name in SECTIONS:
        el = by_name.get(name)
        if el and name not in REVEAL_SKIP and not any("nh-reveal" in classes_of(e) for e in walk_elements([el])):
            problems.append(f"home/{name}: nothing marked nh-reveal")
    for e in walk_elements(home):
        cls = classes_of(e)
        if cls & {"nh-card", "nh-pro__cell", "nh-video-item", "nh-sec__inner"} and "nh-reveal" not in cls:
            problems.append(f"home/{e['id']}: {' '.join(sorted(cls))} without nh-reveal")
        if "nh-pro__item" in cls and e.get("widgetType") != "icon-box":
            problems.append(f"home/{e['id']}: nh-pro__item is not an Icon Box")
    for need, what in (("nh-card", 4), ("nh-pro__cell", 4), ("nh-video-item", 2)):
        n = sum(1 for e in walk_elements(home) if need in classes_of(e))
        if n != what:
            problems.append(f"home: {n} × {need}, expected {what}")
    if sum(1 for e in walk_elements(home) if "nh-card__arrow" in classes_of(e) and e.get("widgetType") == "icon"
           and not (e["settings"].get("link") or {}).get("url")) != 4:
        problems.append("home: each card needs its decorative arrow (an Icon widget nh-card__arrow, no link)")
    # the Snoezelen short in the phone frame
    frames = [e for e in walk_elements(by_name.get("snoezelen", {}).get("elements") or [])
              if "nh-phone-frame" in classes_of(e)]
    if len(frames) != 1 or frames[0].get("widgetType") != "video" or frames[0]["settings"].get("aspect_ratio") != "916":
        problems.append("home/snoezelen: the Video widget (9:16) needs nh-phone-frame")
    # the contact band (v4.3, Itzik 18.11): the visit form, kept in the admin; e-mail only at launch; no WhatsApp
    band = list(walk_elements([by_name.get("contact", {})])) if by_name.get("contact") else []
    forms = [e for e in band if e.get("widgetType") == "form"]
    all_forms = [e for k, d in docs.items() if k in DOC_KEYS
                 for e in walk_elements(d.get("elementor_data") or []) if e.get("widgetType") == "form"]
    if len(forms) != 1 or len(all_forms) != 1:
        problems.append(f"home/contact: one Form widget there and nowhere else ({len(forms)} there, {len(all_forms)} in all)")
    for f in forms:
        fs = f["settings"]
        acts = list(fs.get("submit_actions") or [])
        if acts != (["save-to-database", "email"] if LAUNCH else ["save-to-database"]):
            problems.append(f"home/contact: the form's actions are {acts} (kept in the admin; e-mail only at launch)")
        if fs.get("submissions_metadata") != []:
            problems.append("home/contact: the form must not keep the visitor's IP or browser (submissions_metadata [])")
        if fs.get("show_labels") != "true":
            problems.append("home/contact: the form's labels must be shown")
        req = {i.get("custom_id") for i in fs.get("form_fields") or [] if i.get("required") == "true"}
        if req != {"name", "phone", "consent"}:
            problems.append(f"home/contact: required fields {sorted(req)}, not name, phone, consent")
        if LAUNCH and fs.get("email_to") != FORM_TO:
            problems.append(f"home/contact: at launch the form e-mails {FORM_TO}")
        if LAUNCH and not re.fullmatch(r"[^@\s]+@nevehairus\.com", fs.get("email_from") or ""):
            problems.append("home/contact: LAUNCH needs FORM_FROM, a nevehairus.com mailbox the domain's mail accepts "
                            "from the web host (SPF/DKIM), or the e-mails land in Junk unseen")
        if LAUNCH and any(m in (fs.get("form_metadata") or []) for m in ("remote_ip", "user_agent", "page_url")):
            problems.append("home/contact: the e-mail carries only the date and time, no IP, browser or page address")
    for d in docs.values():
        for path, v in walk_values(d.get("elementor_data") or []):
            if isinstance(v, str) and "wa.me/" in v:
                problems.append(f"{d.get('key')}{path}: a WhatsApp link (not wanted: Itzik 18.11)")
            if isinstance(v, str) and "nh-plain-toggle" in v:
                problems.append(f"{d.get('key')}{path}: «בשפה פשוטה» belongs to the theme's «נגישות» panel now (v4.3)")
    # every in-page link (#…) has its target on the page (the home, the footer and its template)
    targets = {(e.get("settings") or {}).get("_element_id") for d in docs.values()
               for e in walk_elements(d.get("elementor_data") or [])} - {None, ""}
    for key, d in docs.items():
        for e in walk_elements(d.get("elementor_data") or []):
            for path, url in link_urls(e):
                if url.startswith("#") and url not in IN_PAGE_OK and url[1:] not in targets:
                    problems.append(f"{key}/{path}: {url} has no target on the page")
    # «צור קשר»: the hero's to the home's #contact; the header's (on every page, 1.5.7) to the contact page — v4.js
    # points it at a page's own form where there is one
    for key, want in (("home", CONTACT_ANCHOR), ("header-v4", CONTACT_PAGE)):
        n = sum(1 for e in walk_elements((docs.get(key) or {}).get("elementor_data") or [])
                for _, url in link_urls(e) if url == want)
        if n != 1:
            problems.append(f"{key}: {n} link(s) to {want}, expected one («צור קשר», spec 10 §1.1)")
    if "contact" not in targets:
        problems.append("home: no element with the id contact (the target of «צור קשר»)")
    # the contacts template: only in the footer, once, before the bottom row
    for key, d in docs.items():
        tpls = [e for e in walk_elements(d.get("elementor_data") or []) if e.get("widgetType") == "template"]
        if key == "footer-v4":
            root_kids = ((d.get("elementor_data") or [{}])[0]).get("elements") or []
            if len(tpls) != 1 or tpls[0]["settings"].get("template_id") != "@page-id:contacts" \
                    or not root_kids or root_kids[0] is not tpls[0]:
                problems.append("footer-v4: the contacts template (Template widget, @page-id:contacts) must come first")
        elif tpls:
            problems.append(f"{key}: a Template widget (the contacts block lives in the footer, spec 10 §1.11)")
    return problems


def check(docs, kit, menu, content, media_list):
    problems = []
    home_blocks = content["blocks"]
    specs = {sp["key"]: sp for sp in page_specs()}
    all_ids = []
    used = {}
    for key in list(DOC_KEYS) + list(specs):
        page = specs.get(key)
        # a page's texts: its own blocks, and the home's (its visit form uses the home's form texts)
        blocks = {**home_blocks, **page["blocks"]} if page else home_blocks
        doc = docs.get(key)
        if not doc:
            problems.append(f"{key}: missing")
            continue
        for field in ("key", "title", "slug", "front_page", "post_type", "document_type", "template", "conditions",
                      "page_settings", "built_from", "elementor_data"):
            if field not in doc:
                problems.append(f"{key}: no field {field}")
        data = doc.get("elementor_data") or []
        if not data:
            problems.append(f"{key}: no elements")
        for top in data:
            if top.get("elType") != "container" or top.get("isInner") is not False:
                problems.append(f"{key}/{top.get('id')}: a top-level element must be a container with isInner false")
        elements = {}
        for el in walk_elements(data):
            where = f"{key}/{el.get('id')}"
            all_ids.append(el.get("id"))
            elements[el.get("id")] = el
            if el.get("elType") == "container":
                if list(el) != ["id", "elType", "settings", "elements", "isInner"]:
                    problems.append(f"{where}: not a container in Elementor's shape")
            elif el.get("elType") == "widget":
                wt = el.get("widgetType")
                if list(el) != ["id", "elType", "settings", "elements", "widgetType"]:
                    problems.append(f"{where}: not a widget in Elementor's shape")
                if wt not in ALLOWED_WIDGETS or str(wt).startswith("nh-"):
                    problems.append(f"{where}: widget type {wt!r} is not one of Elementor's/Pro's classic widgets here")
                kids = el.get("elements") or []
                if wt == "nested-accordion":
                    items = (el.get("settings") or {}).get("items") or []
                    if len(kids) != len(items) or not all(k.get("elType") == "container" and k.get("isInner") is True
                                                          for k in kids):
                        problems.append(f"{where}: a nested accordion needs one inner container per item")
                    for row in items:
                        all_ids.append(row.get("_id"))
                elif kids:
                    problems.append(f"{where}: a {wt} widget has child elements")
            else:
                problems.append(f"{where}: unknown elType {el.get('elType')!r}")
            s = el.get("settings")
            if not isinstance(s, dict):
                problems.append(f"{where}: settings is not an object")
                continue
            # motion: none of Elementor's own (spec 10 §3: the motion is the theme's)
            for k, v in s.items():
                if MOTION.search(k) and not (k == "n_accordion_animation_duration" and isinstance(v, dict)
                                             and v.get("size") == 0):
                    problems.append(f"{where}: motion/animation setting {k}")
            if el.get("widgetType") == "nested-accordion":
                d = s.get("n_accordion_animation_duration") or {}
                if d.get("size") != 0 or s.get("default_state") != "all_collapsed":
                    problems.append(f"{where}: the accordion must be all collapsed with animation duration 0")
            # tokens, media, URLs
            for path, v in walk_values(s, where):
                if isinstance(v, dict) and v.get("source") == "library":
                    m = re.fullmatch(r"@media:([A-Za-z0-9_-]+)", str(v.get("id")))
                    if not m or m.group(1) not in media_list or list(v) != ["url", "id", "size", "alt", "source"]:
                        problems.append(f"{path}: media value {v!r} is not {{url, id: @media:<media.json name>, …}}")
                if not isinstance(v, str):
                    continue
                for kind, arg in TOKEN_IN_TEXT.findall(v):
                    name = re.split(r"[#/]", arg, maxsplit=1)[0] if kind != "home" else arg
                    if kind == "media" and arg not in media_list:
                        problems.append(f"{path}: @media:{arg} is not in templates/v4/media.json")
                    elif kind == "page" and name not in LINKABLE:
                        problems.append(f"{path}: @page:{arg} names no page of ours ({', '.join(LINKABLE)})")
                    elif kind == "page-id" and arg not in PAGE_KEYS:
                        problems.append(f"{path}: @page-id:{arg} names no document of ours")
                    elif kind == "home" and not re.fullmatch(r"/\S*", arg):
                        problems.append(f"{path}: @home:{arg} is not a path")
                for url in URL_IN_TEXT.findall(v):
                    host = urllib.parse.urlsplit(url if url.startswith("http") else "https:" + url).hostname or ""
                    in_attr = re.search(r'(?:href|src|srcset|data-src)\s*=\s*["\']?' + re.escape(url), v)
                    if not ((host in ("youtube.com", "www.youtube.com") and path.endswith("/youtube_url"))
                            or url == FACEBOOK
                            or (host in MAP_HOSTS and el.get("widgetType") == "html" and "nh-map-widget" in classes_of(el))
                            # batch 3: an address printed as text (the privacy policy names the site), and the few
                            # links of the live texts to other sites (a link loads nothing until it is followed)
                            or not in_attr
                            or (host in LINK_HOSTS and re.search(r'href\s*=\s*"' + re.escape(url), v)
                                and not re.search(r'(?:src|srcset|data-src)\s*=\s*["\']?' + re.escape(url), v))):
                        problems.append(f"{path}: external URL {url}")
            # styled through the Kit
            glob = s.get("__globals__") or {}
            wt = el.get("widgetType")
            need = {"heading": ("title_color",), "text-editor": ("typography_typography", "text_color"),
                    "button": ("typography_typography", "button_text_color"),
                    "nav-menu": ("menu_typography_typography", "color_menu_item"),
                    "nested-accordion": ("title_typography_typography", "normal_title_color"),
                    "icon": ("primary_color", "secondary_color"),
                    "icon-box": ("primary_color", "secondary_color", "title_color",
                                 "title_typography_typography"),
                    "form": ("label_color", "field_text_color", "button_background_color", "button_text_color",
                             "label_typography_typography", "button_typography_typography")}.get(wt, ())
            for control in need:
                if control not in glob:
                    problems.append(f"{where}: {wt} without a Kit global for {control}")
            if wt == "heading" and "typography_typography" not in glob and s.get("typography_typography") != "custom":
                problems.append(f"{where}: heading without a font (Kit global or local)")
            if wt == "image-gallery":
                for i, item in enumerate(s.get("wp_gallery") or []):
                    m = re.fullmatch(r"@media:([A-Za-z0-9_-]+)", str(item.get("id")))
                    if not m or m.group(1) not in media_list or list(item) != ["id", "url"]:
                        problems.append(f"{where}: gallery item {i} {item!r} is not {{id: @media:<media.json name>, url}}")
            if wt == "icon-box" and s.get("description_text") != "":
                problems.append(f"{where}: an Icon Box without description_text \"\" prints Elementor's sample text")
            for control, ref in glob.items():
                m = re.fullmatch(r"globals/(colors|typography)\?id=([a-z0-9]+)", str(ref))
                known = ({c["_id"] for c in kit["system_colors"] + kit["custom_colors"]} if m and m.group(1) == "colors"
                         else {t["_id"] for t in kit["system_typography"] + kit["custom_typography"]})
                if not m or m.group(2) not in known:
                    problems.append(f"{where}: {control} → {ref}: not a global of kit.json")
            if "nh-plain" in classes_of(el) and s.get("_title") != PLAIN_TITLE:
                problems.append(f"{where}: a plain twin without the Navigator label «{PLAIN_TITLE}»")
        # a plain-only twin (a block without full text) stands for its siblings: each of them, but headings and
        # other twins, must hide in plain mode (nh-full)
        for parent in [{"elements": data}] + list(walk_elements(data)):
            kids = parent.get("elements") or []
            plain_only = [k for k in kids if "nh-plain" in classes_of(k) and any(
                ref.get("element") == k["id"] and blocks.get(b, {}).get("full") is None
                for b, modes in ((doc.get("built_from") or {}).get("blocks") or {}).items() for ref in modes.values())]
            if plain_only:
                for k in kids:
                    if k.get("widgetType") != "heading" and not classes_of(k) & {"nh-full", "nh-plain"}:
                        problems.append(f"{key}/{k['id']}: shown in plain mode beside the plain text {plain_only[0]['id']}"
                                        " that replaces it (no class nh-full)")
        # blocks used in this document: the element and its text, verbatim
        for block, modes in ((doc.get("built_from") or {}).get("blocks") or {}).items():
            for mode, ref in modes.items():
                if not page:
                    used.setdefault((block, mode), []).append(key)
                el = elements.get(ref.get("element"))
                text = (blocks.get(block) or {}).get(mode)
                if el is None or text is None:
                    problems.append(f"{key}: block {block} ({mode}) → element {ref.get('element')} not found")
                    continue
                value = el["settings"]
                field, _, twin = ref["field"].partition("#")
                for part in field.split("."):
                    value = value[int(part)] if isinstance(value, list) else (value or {}).get(part)
                kind = blocks[block]["kind"]
                want = (text if kind == "html" or el.get("widgetType") in ("button", "nested-accordion")
                        else html.escape(text, quote=False))
                if el.get("widgetType") == "text-editor" and kind != "html":
                    want = f"<p>{want}</p>" if want else ""
                if twin == "in":            # inside a code (the map): the text, verbatim
                    if want not in str(value or ""):
                        problems.append(f"{key}/{el['id']}: block {block} ({mode}) is not in the code")
                    continue
                if twin == "label":         # the map's name (its region's aria-label)
                    if f'aria-label="{html.escape(text, quote=True)}"' not in str(value or ""):
                        problems.append(f"{key}/{el['id']}: block {block} is not the code's aria-label")
                    continue
                if twin:                    # a form field holds both (v4.3): the span of this mode, verbatim
                    if f'<span class="nh-{twin}">{want}</span>' not in str(value or ""):
                        problems.append(f"{key}/{el['id']}: block {block} ({mode}) is not in the field's nh-{twin} span")
                    continue
                if value != want:
                    problems.append(f"{key}/{el['id']}: block {block} ({mode}) is not verbatim: {str(value)[:80]!r}")
                if mode == "plain" and "nh-plain" not in classes_of(el):
                    problems.append(f"{key}/{el['id']}: the plain twin of {block} has no class nh-plain")
                if mode == "full" and blocks[block].get("plain") and "nh-full" not in classes_of(el):
                    problems.append(f"{key}/{el['id']}: {block} has a plain twin but no class nh-full")
        # headings of the document (the page as a whole below)
        for mode in ("full", "plain"):
            levels = headings(data, docs, mode)
            if key == "header-v4" and levels:
                problems.append(f"{key}: headings in the header ({levels})")
            if key == "footer-v4" and headings(data, docs, mode, expand=False):
                problems.append(f"{key}: headings of the footer's own (only the contacts template brings any)")
            if key == "contacts" and levels and levels[0][0] != 2:
                problems.append(f"{key} ({mode}): the template starts at h{levels[0][0]}, not h2 (a part of the page)")
            heading_order(levels, f"{key} ({mode})", problems, one_h1=key == "home" or key in specs)
    # the page as a whole: the header, the home (or an inner page), the footer with the contacts template (spec 09
    # §8: h1 → h2 → h3)
    if all(k in docs for k in DOC_KEYS):
        for body in ["home"] + [k for k in specs if k in docs]:
            for mode in ("full", "plain"):
                whole = []
                for key in ("header-v4", body, "footer-v4"):
                    whole += headings(docs[key]["elementor_data"], docs, mode)
                heading_order(whole, f"the page {body} ({mode})", problems, one_h1=True)
        problems += page_problems(docs, media_list)
    # the inner pages: their own fields; every block of theirs used once in them
    for key, sp in specs.items():
        doc = docs.get(key) or {}
        for field in ("title", "slug"):
            if not str(sp.get(field) or "").strip():
                problems.append(f"page {key}: no {field}")
        if doc.get("parent") != sp.get("parent"):
            problems.append(f"page {key}: parent {doc.get('parent')!r}, the spec says {sp.get('parent')!r}")
        # a page's forms: kept in the admin only (e-mail at launch), no IP or browser, labels shown, the required
        # fields of their kind (the visit form: name, phone, consent; the contact form: all but the hint)
        for f in [e for e in walk_elements(doc.get("elementor_data") or []) if e.get("widgetType") == "form"]:
            fs = f["settings"]
            if list(fs.get("submit_actions") or []) != (["save-to-database", "email"] if LAUNCH else ["save-to-database"]):
                problems.append(f"page {key}: the form's actions are {fs.get('submit_actions')}")
            if fs.get("submissions_metadata") != [] or fs.get("show_labels") != "true":
                problems.append(f"page {key}: the form keeps metadata or hides its labels")
            ids = [i.get("custom_id") for i in fs.get("form_fields") or []]
            req = {i.get("custom_id") for i in fs.get("form_fields") or [] if i.get("required") == "true"}
            want = {"name", "email", "phone", "message", "consent"} if "email" in ids else {"name", "phone", "consent"}
            if "cv" in ids:                  # 24.11: the CV form of «דרושים»
                want = {"name", "phone", "job", "cv", "consent"}
                up = next(i for i in fs["form_fields"] if i.get("custom_id") == "cv")
                if (up.get("file_types"), up.get("file_sizes")) != ("pdf,doc,docx", "5") \
                        or up.get("attachment_type") != ("both" if LAUNCH else "link"):
                    problems.append(f"page {key}: the CV field takes pdf,doc,docx up to 5 MB ({'both' if LAUNCH else 'link'})")
                if "[field id=\"job\"]" not in str(fs.get("email_subject")):
                    problems.append(f"page {key}: the e-mail's subject must carry the job's title ([field id=\"job\"])")
            if "website" not in ids:         # 24.11: the invisible trap of every form (the theme's checks add the rest)
                problems.append(f"page {key}: the form has no honeypot field «website»")
            if req != want or "nh-contact-form" not in classes_of(f):
                problems.append(f"page {key}: the form's required fields {sorted(req)} (want {sorted(want)}) or no "
                                "nh-contact-form (the theme's checks)")
        page_used = {}
        for block, modes in ((doc.get("built_from") or {}).get("blocks") or {}).items():
            for mode in modes:
                page_used[(block, mode)] = page_used.get((block, mode), 0) + 1
        for block, b in sp["blocks"].items():
            if block in home_blocks:
                problems.append(f"page {key}: block {block} has the name of a block of home.json")
            for mode in ("full", "plain"):
                text = b.get(mode)
                if text is None or (mode == "plain" and text == ""):
                    continue
                if (block, mode) not in page_used:
                    problems.append(f"page {key}: block {block} ({mode}) is not used")
            if b.get("full") and b.get("kind") != "label" and b.get("plain") in (None, "") and not b.get("no_plain"):
                problems.append(f"page {key}: block {block} has no plain twin (every page has a plain version; "
                                "\"no_plain\": a reason, where the text is plain already)")
    # every block of home.json once in the home's documents (its full text, its plain twin if set)
    blocks = home_blocks
    for block, b in blocks.items():
        for mode in ("full", "plain"):
            text = b.get(mode)
            if text is None or (mode == "plain" and text == ""):
                if (block, mode) in used:
                    problems.append(f"block {block} ({mode}) is null in home.json but used")
                continue
            n = len(used.get((block, mode), []))
            expected = 0 if block in OFF_BLOCKS else 1
            if n != expected:
                problems.append(f"block {block} ({mode}) is used {n} times, expected {expected}"
                                + (" (switched off: OFF_BLOCKS)" if block in OFF_BLOCKS else ""))
    for block, mode in used:
        if block not in blocks:
            problems.append(f"block {block} is not in content/v4/home.json")
    # ids
    bad = sorted({i for i in all_ids if not HEX7.match(str(i))})
    dup = sorted({i for i in all_ids if all_ids.count(i) > 1})
    if bad or dup:
        problems.append(f"ids must be unique 7-hex strings (bad {bad}, duplicated {dup})")
    # the Kit
    kit_ids = [c["_id"] for c in kit["system_colors"] + kit["custom_colors"]]
    kit_typo = [t["_id"] for t in kit["system_typography"] + kit["custom_typography"]]
    if [c["_id"] for c in kit["system_colors"]] != ["primary", "secondary", "text", "accent"] \
            or [t["_id"] for t in kit["system_typography"]] != ["primary", "secondary", "text", "accent"]:
        problems.append("kit.json: the system ids must be primary, secondary, text, accent")
    for i in [c["_id"] for c in kit["custom_colors"]] + [t["_id"] for t in kit["custom_typography"]]:
        if not HEX7.match(i):
            problems.append(f"kit.json: custom id {i!r} is not 7 lowercase hex")
    if len(set(kit_ids)) != len(kit_ids) or len(set(kit_typo)) != len(kit_typo):
        problems.append("kit.json: duplicated ids")
    for t in kit["system_typography"] + kit["custom_typography"]:
        if t.get("typography_font_family") != FONT:
            problems.append(f"kit.json: {t['_id']} is not {FONT}")
        fs = t.get("typography_font_size") or {}
        if fs and fs.get("unit") not in ("rem", "custom"):
            problems.append(f"kit.json: {t['_id']} font size in {fs.get('unit')}, not rem/custom")
    for value in ("#1C1830", "#2F6B2A"):           # spec 10 §0: night and green-700
        if value not in [c["color"] for c in kit["custom_colors"]]:
            problems.append(f"kit.json: no custom colour {value}")
    # the menu
    def menu_items(items, depth=0):
        for it in items:
            yield it, depth
            yield from menu_items(it.get("children") or [], depth + 1)
    live = [(i["label"], d) for i, d in menu_items(content["menu"])]
    ours = [(i["label"], d) for i, d in menu_items(menu["items"])]
    if live != ours:
        problems.append("menu.json: not the live menu's labels and levels")
    for it, _ in menu_items(menu["items"]):
        url = it.get("url", "")
        if not re.fullmatch(r"@page:(home|main-home)|@home:/\S*/", url):
            problems.append(f"menu.json: {it['label']}: target {url!r} is not @page:home, @page:main-home or @home:/…/")
        if url.startswith("@page:") and it.get("page") != url[6:]:
            problems.append(f"menu.json: {it['label']}: page {it.get('page')!r} does not match {url}")
    return problems


def write(docs, kit, menu):
    os.makedirs(OUT, exist_ok=True)
    os.makedirs(PAGES_OUT, exist_ok=True)
    os.makedirs(POSTS_OUT, exist_ok=True)
    pages = [k for k in docs if k not in DOC_KEYS]
    folder = {k: "posts" if docs[k]["post_type"] == "post" else "pages" for k in pages}
    for name, obj in ([(f"{k}.json", docs[k]) for k in DOC_KEYS] + [("kit.json", kit), ("menu.json", menu)]
                      + [(f"{folder[k]}/{k}.json", docs[k]) for k in pages]):
        path = os.path.join(OUT, name)
        with open(path, "w", encoding="utf-8") as fh:
            json.dump(obj, fh, ensure_ascii=False, indent=1)
            fh.write("\n")
        if name in [f"{k}.json" for k in DOC_KEYS] + [f"{folder[k]}/{k}.json" for k in pages]:
            els = list(walk_elements(obj["elementor_data"]))
            n_w = sum(1 for e in els if e["elType"] == "widget")
            print(f"· {os.path.relpath(path, ROOT)}: {obj['post_type']} {obj['document_type']}, "
                  f"{len(els) - n_w} container(s), {n_w} widget(s), {os.path.getsize(path) / 1024:.0f} KB")
        else:
            print(f"· {os.path.relpath(path, ROOT)}: {os.path.getsize(path) / 1024:.1f} KB")


def read_outputs():
    docs = {}
    for key in DOC_KEYS:
        path = os.path.join(OUT, f"{key}.json")
        if not os.path.isfile(path):
            die(f"templates/v4/{key}.json is missing: run python3 -I scripts/build_v4.py")
        with open(path, encoding="utf-8") as fh:
            docs[key] = json.load(fh)
    for spec in page_specs():
        path = spec_out(spec)
        if not os.path.isfile(path):
            die(f"{os.path.relpath(path, ROOT)} is missing: run python3 -I scripts/build_v4.py")
        with open(path, encoding="utf-8") as fh:
            docs[spec["key"]] = json.load(fh)
    out = []
    for name in ("kit", "menu"):
        path = os.path.join(OUT, f"{name}.json")
        if not os.path.isfile(path):
            die(f"templates/v4/{name}.json is missing: run python3 -I scripts/build_v4.py")
        with open(path, encoding="utf-8") as fh:
            out.append(json.load(fh))
    return docs, out[0], out[1]


def summary(docs, content):
    twins = sorted(b for b, v in content["blocks"].items() if v.get("plain"))
    missing = sorted(b for b, v in content["blocks"].items() if v.get("plain") is None and v.get("full") is None)
    print(f"· plain twins ({len(twins)}): {', '.join(twins) or 'none yet'}")
    if missing:
        print(f"· blocks with neither text yet: {', '.join(missing)}")
    print("· sections: " + " → ".join((el["settings"] or {}).get("_element_id", "?")
                                       for el in docs["home"]["elementor_data"])
          + "; footer: the contacts template, then the bottom row")


def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0], formatter_class=argparse.RawDescriptionHelpFormatter,
                                 epilog=__doc__)
    ap.add_argument("cmd", nargs="?", choices=["build", "check"], default="build")
    args = ap.parse_args()
    content, media_list = load_inputs()
    if args.cmd == "build":
        docs, kit, menu = build()
        problems = check(docs, kit, menu, content, media_list)
        if problems:
            die("the generated data is not valid:\n  " + "\n  ".join(problems))
        write(docs, kit, menu)
        summary(docs, content)
        print("· check: passed")
        return
    docs, kit, menu = read_outputs()
    problems = check(docs, kit, menu, content, media_list)
    fresh = build()
    if (docs, kit, menu) != (fresh[0], fresh[1], fresh[2]):
        stale = [k for k in fresh[0] if docs.get(k) != fresh[0][k]] + [n for n, a, b in (("kit", kit, fresh[1]),
                                                                                       ("menu", menu, fresh[2])) if a != b]
        problems.append("templates/v4 is not what the current inputs make (" + ", ".join(stale)
                        + "): run python3 -I scripts/build_v4.py")
    if problems:
        die("check failed:\n  " + "\n  ".join(problems))
    summary(docs, content)
    n = sum(1 for k in docs for e in walk_elements(docs[k]["elementor_data"]) if e["elType"] == "widget")
    pages = [k for k in docs if k not in DOC_KEYS]
    print(f"· check: passed ({len(docs)} documents{f', {len(pages)} of them inner pages' if pages else ''}, "
          f"{n} widgets, kit and menu)")


if __name__ == "__main__":
    sys.exit(main())
