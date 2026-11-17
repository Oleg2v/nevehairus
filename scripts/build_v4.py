#!/usr/bin/env python3
"""v4 home: the Elementor data of the new home page, its header, footer and contacts block, the Kit's global colours
and fonts, and the main menu (research/09-v4-home-spec.md §2, §3, §4).

    python3 -I scripts/build_v4.py           build templates/v4/*.json, then check them
    python3 -I scripts/build_v4.py check     check the files as they are (and that they are what the inputs make)

Inputs: content/v4/home.json (the live texts, word for word; a block's "plain" is its plain-language twin, a draft,
or null while none is written: the builder makes a twin wherever "plain" is set and skips it where it is null) and
templates/v4/media.json (the photos, the logo and the PDF, by name). Python 3 standard library only; never talks
to a server.

Writes, in templates/v4/:
  home.json       page #32 (found on deploy by post meta _nh_page_key = home), the front page, template
                  elementor_header_footer: the sections of spec §4 in the live order, one top-level container each
  header-v4.json  Theme Builder header, condition include/singular/front_page, wrapper <header> (§4.0)
  footer-v4.json  Theme Builder footer, the same condition, wrapper <footer> (§4.9)
  contacts.json   saved template (library type `container`: the test site lists it, its Container feature is on)
                  holding the green contacts band (§4.7); the home inserts it with Pro's Template widget
  kit.json        the Kit's system_colors, custom_colors, system_typography and custom_typography (§2)
  menu.json       the WordPress menu «תפריט ראשי» (slug nh-main), the live menu with its targets on the test host
The documents have the shape of templates/pages/*.json (key, title, slug, front_page, post_type, document_type,
template, conditions, page_settings, built_from, elementor_data); elementor_data is the value of _elementor_data as
Elementor 4.4.0 stores it.

Only Elementor's own classic widgets (heading, text-editor, image, button, video, icon, nested-accordion) and Pro's
nav-menu and template; none of the nevehairus-site plugin's. Every colour and font goes through the Kit's globals
(settings.__globals__ = {control: "globals/colors?id=…" / "globals/typography?id=…"}), with the literal colour as
a fallback; the one local font setting is the hero's subline (1.375rem 600: no global has that size). The classes
of spec §4/§5.2 are on the elements for the child theme's v4.css. Plain twins are sibling widgets right after
their full widget: the full one has class nh-full, the twin nh-plain and the Navigator label «שפה פשוטה — טיוטה».
Element ids (7 lowercase hex) are a hash of the document key and the element's path, so a rebuild diffs cleanly;
the Kit's custom ids are hashes too.

Tokens, resolved on deploy (scripts/test_site.py v4):
  @media:<name>    in a media control's id: the attachment (id, url, alt) of templates/v4/media.json's <name>;
                   inside a text (an href): the attachment's URL
  @page:<key>      permalink of one of our documents (home = the front page, main-home = the house page #33)
  @page-id:<key>   its post id (the Template widget's template_id)
  @home:/<path>/   home_url(<path>), also inside a text's href
built_from.inputs_sha256 is the sha256 of the inputs and of this script: `test_site.py v4` refuses files built
from other inputs.

Where the data goes beyond the letter of the spec (each for one of its own rules):
  - the Kit's accent (UI, buttons) has line height 1.5 and «טקסט קטן» 1.5: a button with padding .75rem is then
    ≈49.5px high (§1 min-height 48px; Elementor's Button has no min-height control);
  - the accordion's title tag is span, not div: Elementor prints the title inside a <span>, where a div is invalid;
  - the header's menu row is a full-width container whose padding keeps the 1200px column (a boxed container is
    always 100% wide, and on ≤1024px that row is only the menu toggle beside the logo row); the actions container is
    as wide as its buttons; the logo is 48px high on tablets and 40px on phones, so the one row of §4.0 (logo, «בשפה
    פשוטה», phone, menu) fits a 360px phone;
  - card 4's subline is an empty Text Editor («leave empty»): nothing is printed, the client can fill it;
  - the contacts band (with its section classes and id) is the saved template's own top container; the home holds
    it in a plain container (nh-slot) with the Template widget.

check: only known widget types; Elementor's element shape; every block of home.json used exactly once (its full
text and, if set, its plain twin, each with the text verbatim); every @media name in media.json; every
@page/@page-id key one of ours; headings h1 → h2 → h3 without gaps in each document (normal and plain mode; the
contacts template at its place in the home); unique ids; no motion or animation settings (the accordion's
duration is 0); no external URL but youtube.com and the live Facebook page; every widget styled through the Kit.
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

PLAIN_TITLE = "שפה פשוטה — טיוטה"                      # the Navigator label of a plain twin (§4, §6)
FACEBOOK = "https://www.facebook.com/neveirus/"
YOUTUBE = "https://www.youtube.com/watch?v="
PHONE_TEL = "tel:089403047"
PHONE_LABEL = "התקשרו: 08-9403047"                     # aria-label of the icon-only phone button (§4.0)

# ---- design tokens (§1) and the Kit (§2) -------------------------------------------------------------------------------
# system colours keep Elementor's ids; custom ids are stable 7-hex hashes of the token
SYSTEM_COLORS = (("primary", "purple-700", "סגול", "#4A2D84"), ("secondary", "sky-500", "תכלת", "#5BBDEB"),
                 ("text", "ink", "טקסט", "#1F2421"), ("accent", "green-500", "ירוק", "#8CC05C"))
CUSTOM_COLORS = (("purple-800", "סגול כהה", "#3A2269"), ("purple-50", "רקע סגול", "#F3F0F9"),
                 ("sky-50", "רקע תכלת", "#EAF6FC"), ("green-50", "רקע ירוק", "#EFF6E8"),
                 ("ink-2", "טקסט משני", "#4F5550"), ("line", "קו", "#D9DCE3"), ("white", "לבן", "#FFFFFF"))
FONT = "Assistant"
# (id or token, title, weight, size, size on phones, line height in em). Spec §1/§2. Two additions, both for the
# rules of §1: the accent (UI and buttons) gets line height 1.5, so a button with padding .75rem is 1.0625rem × 1.5
# + 1.5rem ≈ 49.5px high (min-height 48px: Elementor's button has no min-height control); small text gets 1.5.
SYSTEM_TYPOGRAPHY = (("primary", "כותרות", 700, None, None, None),
                     ("secondary", "משני", 600, None, None, None),
                     ("text", "טקסט", 400, "1.1875rem", "1.125rem", 1.65),
                     ("accent", "כפתורים וממשק", 600, "1.0625rem", None, 1.5))
CUSTOM_TYPOGRAPHY = (("h1", "כותרת H1", 700, "clamp(2rem, 1.4rem + 2.4vw, 3.25rem)", None, 1.15),
                     ("h2", "כותרת H2", 700, "clamp(1.75rem, 1.4rem + 1.2vw, 2.5rem)", None, 1.2),
                     ("h3", "כותרת H3", 700, "1.375rem", "1.25rem", 1.3),
                     ("small", "טקסט קטן", 400, "1rem", None, 1.5))

CONTENT_WIDTH = 1200                                   # boxed_width
GUTTER = {"": "40px", "_tablet": "32px", "_mobile": "20px"}
SECTION_PAD = {"": "5rem", "_tablet": "5rem", "_mobile": "3.5rem"}
GRID_GAP = 24

ALLOWED_WIDGETS = {"heading", "text-editor", "image", "button", "video", "icon", "nested-accordion",   # Elementor
                   "nav-menu", "template"}                                                           # Pro
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
    """Dimensions (padding, margin, border width, radius). All numbers → px; any string → unit custom, as is."""
    right = top if right is None else right
    bottom = top if bottom is None else bottom
    left = right if left is None else left
    values = (top, right, bottom, left)
    linked = len(set(map(str, values))) == 1
    if all(isinstance(v, (int, float)) for v in values):
        return {"unit": "px", "top": str(top), "right": str(right), "bottom": str(bottom), "left": str(left),
                "isLinked": linked}
    return {"unit": "custom", "top": str(top), "right": str(right), "bottom": str(bottom), "left": str(left),
            "isLinked": linked}


def gaps(column, row):
    return {"column": str(column), "row": str(row), "isLinked": column == row, "unit": "px"}


def link(url, attributes=""):
    return {"url": url, "is_external": "", "nofollow": "", "custom_attributes": attributes}


def media(name):
    return {"url": "", "id": f"@media:{name}", "size": "", "alt": "", "source": "library"}


def icon(name):
    return {"value": f"fas fa-{name}", "library": "fa-solid"}


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


def section_padding(top_bottom=SECTION_PAD, sides=GUTTER):
    return {f"padding{bp}": dims(top_bottom[bp], sides[bp], top_bottom[bp], sides[bp]) for bp in ("", "_tablet", "_mobile")}


def flex(direction="column", gap_row=0, gap_column=0, wrap="nowrap", **extra):
    """A flex container's layout. Column containers never wrap (Elementor's mobile default for a column is wrap)."""
    out = {"container_type": "flex", "flex_direction": direction, "flex_gap": gaps(gap_column, gap_row)}
    if wrap:
        out.update(flex_wrap=wrap, flex_wrap_tablet=wrap, flex_wrap_mobile=wrap)
    out.update(extra)
    return out


ZERO = dims(0)


# ---- the builder ----------------------------------------------------------------------------------------------------------
class Builder:
    def __init__(self, content, media_names):
        self.content = content
        self.blocks = content["blocks"]
        self.media_names = set(media_names)
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

    def section(self, name, title, bg, children, classes="", gap_row=32):
        """A top-level section container (§4): boxed 1200px, side gutters and vertical padding of §1, flex column."""
        s = {"content_width": "boxed", "boxed_width": size(CONTENT_WIDTH),
             **flex("column", gap_row), "flex_gap_mobile": gaps(0, 24), **section_padding(),
             "background_background": "classic",
             "css_classes": join("nh-sec", f"nh-sec--{name}", classes), "_element_id": name}
        s = styled(s, background_color=bg)
        return self.container(name, s, children, inner=False, title=title)

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
               align=None, align_mobile=None):
        s = {"editor": content_html}
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
               compact=False):
        """Buttons of §1: radius 8px, padding .75rem 1.5rem, the accent font (600, 1.0625rem, line height 1.5).
        style: primary (purple, white text), light (white, purple text: on the purple hero), outline (white with a
        2px purple border), outline-light (transparent with a 2px white border, white text: on the purple hero),
        text (no fill, purple text), soft (purple-50 fill, purple icon: the phone icon on tablets and phones)."""
        label = self.text(block, "full") if block else (text or "")
        s = {"text": label, "link": link(url, f"aria-label|{aria_label}" if aria_label else ""),
             "background_background": "classic", "button_background_hover_background": "classic",
             "border_radius": dims(8), "text_padding": dims(".75rem", "1.5rem")}
        if icon_name:
            s.update(selected_icon=icon(icon_name), icon_align="row", icon_indent=size(8))
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
            s.update(border_border="solid", border_width=dims(2))
        if style == "outline-light":
            s["background_color"] = "rgba(255, 255, 255, 0)"     # transparent: the purple half shows through
        if style == "text":
            s["text_padding"] = dims(".75rem", ".5rem")
        if style == "soft":
            s["text_padding"] = dims(".8125rem")
        if compact:                                               # ≤1024: the header's row is narrow
            s["text_padding_tablet"] = dims(".625rem", ".875rem")
            s["text_padding_mobile"] = dims(".5rem")   # the one row of a 360px phone: logo, this, phone, menu
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
        """A YouTube video that loads nothing from YouTube until a click (§4.3): lazy, privacy mode, our own copy of
        its thumbnail as the overlay, the play icon, no lightbox."""
        name = f"video-{video_id}"
        if name not in self.media_names:
            die(f"templates/v4/media.json has no {name}")
        s = {"video_type": "youtube", "youtube_url": YOUTUBE + video_id, "lazy_load": "yes", "yt_privacy": "yes",
             "rel": "", "show_image_overlay": "yes", "image_overlay": media(name), "image_overlay_size": "full",
             "show_play_icon": "yes", "lightbox": "", "aspect_ratio": ratio, **extra}
        return self.widget(path, "video", s)

    # ---- §4.0 header ----------------------------------------------------------------------------------------------------
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
            self.button("header.plain_toggle", "header/top/actions/plain", "#plain", "outline",
                        classes="nh-plain-toggle", compact=True),
            self.button("header.phone", "header/top/actions/phone", PHONE_TEL, "text", icon_name="phone-alt",
                        hide=("tablet", "mobile")),
            self.button(None, "header/top/actions/phone-icon", PHONE_TEL, "soft", icon_name="phone-alt",
                        aria_label=PHONE_LABEL, hide=("desktop",)),
            self.button("header.contact", "header/top/actions/contact", "@home:/צור-קשר/", "primary",
                        hide=("tablet", "mobile")),
        ], title="כפתורים")
        top = self.container("header/top", {
            "content_width": "boxed", "boxed_width": size(CONTENT_WIDTH),
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
        # Full width with the 1200px column as padding: a boxed container is always 100% wide, and on ≤1024px this
        # row is only the menu toggle beside the first row (width auto)
        column = f"max({GUTTER['']}, calc((100% - {CONTENT_WIDTH}px) / 2))"
        nav = self.container("header/nav", {
            **flex("row", 0, 0, wrap="nowrap", flex_align_items="center"),
            # Elementor resets a container's width to 100% on phones unless the phone value is set too
            "width_tablet": size("auto"), "width_mobile": size("auto"),
            "padding": dims("0px", column), "padding_tablet": dims("0px", "0px", "0px", GUTTER["_tablet"]),
            "padding_mobile": dims("0px", "0px", "0px", GUTTER["_mobile"]),
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

    # ---- §4.1 hero ------------------------------------------------------------------------------------------------------
    def hero(self):
        sub_font = {"typography_typography": "custom", "typography_font_family": FONT,
                    "typography_font_size": size("1.375rem"), "typography_font_weight": "600",
                    "typography_line_height": {"unit": "em", "size": 1.4, "sizes": []}}
        buttons = self.container("hero/text/buttons", {
            **flex("row", 12, 12, wrap="wrap", flex_align_items="center"), "padding": dims(8, 0, 0, 0)}, [
            self.button("hero.cta_contact", "hero/text/buttons/contact", "@home:/צור-קשר/", "light"),
            self.button("hero.cta_phone", "hero/text/buttons/phone", PHONE_TEL, "outline-light", icon_name="phone-alt"),
        ], title="כפתורים")
        text = self.container("hero/text", styled({
            **flex("column", 16, 0, flex_justify_content="center"), "width": size("50%"), "width_mobile": size("100%"),
            "padding": dims("4rem", "3rem"), "padding_mobile": dims("2.5rem", "1.25rem"),
            "background_background": "classic", "css_classes": "nh-hero__text"}, background_color="purple-700"),
            self.heading_block("hero.title", "hero/text/title", "h1", "h1", "white")
            + self.heading_block("hero.sub", "hero/text/sub", "p", None, "white", typography=sub_font)
            + [buttons], title="טקסט")
        photo = self.container("hero/media", {
            **flex("column", 0, 0), "width": size("50%"), "width_mobile": size("100%"), "padding": ZERO,
            "css_classes": "nh-hero__media"},
            [self.image("hero/media/photo", "hero", "nh-cover-img", size_name="full")], title="תמונה")
        s = {**flex("row", 0, 0, wrap="nowrap", flex_direction_mobile="column-reverse",
                    flex_align_items="stretch"),
             "min_height": size(480), "padding": ZERO,
             "css_classes": "nh-sec nh-sec--hero nh-hero", "_element_id": "hero"}
        return self.container("hero", s, [text, photo], inner=False, title="באנר")

    # ---- §4.2 specialties -------------------------------------------------------------------------------------------------
    CARDS = ((1, "@page:main-home"), (2, "@home:/מערך-דיור-בקהילה/"), (3, "@home:/מערך-דיור-בקהילה-אוטיזם/"),
             (4, "@home:/חדשות-ועדכונים/"))

    def specialties(self):
        cards = []
        for n, url in self.CARDS:
            p = f"specialties/grid/card-{n}"
            body = self.container(f"{p}/body", {
                **flex("column", 8, 0), "padding": dims("1.25rem", "1.5rem", "1.5rem", "1.5rem"),
                "_flex_size": "grow", "css_classes": "nh-card__body"},
                self.heading_block(f"card{n}.title", f"{p}/body/title", "h3", "h3", "ink", "nh-card__title", url=url)
                + self.text_editor_block(f"card{n}.sub", f"{p}/body/sub", "nh-card__sub", typo="small", color="ink-2")
                + [self.editor('<p><span aria-hidden="true">למידע נוסף ←</span></p>', f"{p}/body/more",
                               "nh-card__more", typo="accent", color="purple-700")])
            card = self.container(p, styled({
                **flex("column", 0, 0), "padding": ZERO, "overflow": "hidden",
                "background_background": "classic", "border_border": "solid", "border_width": dims(1),
                "border_radius": dims(12), "css_classes": "nh-card"}, background_color="white", border_color="line"),
                [self.image(f"{p}/photo", f"card-{n}", "nh-card__img nh-ratio-4x3", url=url,
                            link_attributes="tabindex|-1,aria-hidden|true"), body], title=f"כרטיס {n}")
            cards.append(card)
        grid = self.container("specialties/grid", {
            "container_type": "grid", "grid_columns_grid": {"unit": "fr", "size": 4, "sizes": []},
            "grid_columns_grid_tablet": {"unit": "fr", "size": 2, "sizes": []},
            "grid_columns_grid_mobile": {"unit": "fr", "size": 1, "sizes": []},
            "grid_rows_grid": size("auto"), "grid_gaps": gaps(GRID_GAP, GRID_GAP), "padding": ZERO}, cards,
            title="כרטיסים")
        return self.section("specialties", "תחומי התמחות", "white",
                            self.heading_block("specialties.title", "specialties/title", "h2", "h2", "purple-700",
                                               align="center") + [grid])

    # ---- §4.3 about -------------------------------------------------------------------------------------------------------
    def about(self):
        text = self.container("about/grid/text", {**flex("column", 16, 0), "padding": ZERO},
                              self.heading_block("about.title", "about/grid/text/title", "h2", "h2", "purple-700")
                              + self.text_editor_block("about.body", "about/grid/text/body"), title="טקסט")
        videos = []
        for n, vid in ((1, "kLAGTCOIWQE"), (2, "JQXm_Dku7tI")):
            p = f"about/grid/videos/video-{n}"
            videos.append(self.container(p, {**flex("column", 8, 0), "padding": ZERO},
                                         [self.video(f"{p}/video", vid)]
                                         + self.text_editor_block(f"about.video{n}.caption", f"{p}/caption",
                                                                  "nh-video__cap", typo="small", color="ink-2"),
                                         title=f"סרטון {n}"))
        video_col = self.container("about/grid/videos", {**flex("column", 24, 0), "padding": ZERO}, videos,
                                   title="סרטונים")
        grid = self.container("about/grid", {
            "container_type": "grid", "grid_columns_grid": size("5fr 6fr"),
            "grid_columns_grid_tablet": {"unit": "fr", "size": 1, "sizes": []},
            "grid_columns_grid_mobile": {"unit": "fr", "size": 1, "sizes": []},
            "grid_rows_grid": size("auto"), "grid_gaps": gaps(GRID_GAP, GRID_GAP), "padding": ZERO},
            [text, video_col], title="טקסט וסרטונים")
        return self.section("about", "קצת עלינו", "sky-50", [grid])

    # ---- §4.4 testimonials ------------------------------------------------------------------------------------------------
    def testimonials(self):
        quote = self.container("testimonials/quote", styled({
            **flex("column", 16, 0, flex_align_items="flex-start"), "width": size("min(760px, 100%)"),
            "padding": dims("2.5rem"), "padding_mobile": dims("1.75rem", "1.25rem"),
            "background_background": "classic", "border_radius": dims(12), "_flex_align_self": "center",
            "css_classes": "nh-quote"}, background_color="purple-50"),
            self.heading_block("testimonials.card_title", "testimonials/quote/title", "h3", "h3", "ink")
            + self.text_editor_block("testimonials.excerpt", "testimonials/quote/excerpt")
            + [self.button("testimonials.more", "testimonials/quote/more", "@home:/תודות-והמלצות/", "primary")],
            title="המלצה")
        return self.section("testimonials", "תודות והמלצות", "white",
                            self.heading_block("testimonials.title", "testimonials/title", "h2", "h2", "purple-700",
                                               align="center") + [quote])

    # ---- §4.5 special needs -----------------------------------------------------------------------------------------------
    def special(self):
        framed = self.container("special-needs/body/framed", styled({
            **flex("column", 0, 0), "padding": dims("1.5rem"), "background_background": "classic",
            # the inline start is the right edge in RTL
            "border_border": "solid", "border_width": dims(0, 4, 0, 0), "border_radius": dims(12),
            "css_classes": join("nh-framed", "nh-full" if self.has_plain("special.plain_body") else "")},
            background_color="purple-50", border_color="purple-700"),
            self.text_editor_block("special.framed", "special-needs/body/framed/text"), title="מסגרת")
        rest = self.container("special-needs/body/more/item-1", {
            **flex("column", 16, 0), "padding": dims(16, 0, 0, 0), "_title": self.text("special.more_title", "full")},
            self.text_editor_block("special.rest", "special-needs/body/more/item-1/text"))
        more_title = self.text("special.more_title", "full")
        accordion = self.widget("special-needs/body/more", "nested-accordion", styled({
            "items": [{"item_title": more_title, "element_css_id": "",
                       "_id": hex7(f"{self.doc}/special-needs/body/more/items/0")}],
            "default_state": "all_collapsed", "max_items_expended": "one",
            "n_accordion_animation_duration": {"unit": "ms", "size": 0, "sizes": []},
            # a span, not the spec's div: Elementor prints the title inside a <span>, where a div is not allowed
            # (the summary is the control either way; no heading)
            "title_tag": "span", "accordion_item_title_icon": icon("plus"),
            "accordion_item_title_icon_active": icon("minus"),
            "_css_classes": join("nh-more", "nh-full" if self.has_plain("special.plain_body") else "")},
            {"title_typography_typography": "accent"},
            normal_title_color="purple-700", hover_title_color="purple-800", active_title_color="purple-700",
            normal_icon_color="purple-700", hover_icon_color="purple-800", active_icon_color="purple-700"), [rest])
        self.use("special.more_title", "full", accordion, "items.0.item_title")
        body = self.container("special-needs/body", {
            **flex("column", 24, 0), "width": size("min(760px, 100%)"), "padding": ZERO,
            "_flex_align_self": "flex-start"},
            self.heading_block("special.title", "special-needs/body/title", "h2", "h2", "purple-700")
            + [framed]
            + self.text_editor_block("special.lead", "special-needs/body/lead",
                                     "nh-full" if self.has_plain("special.plain_body") else "")
            + [accordion]
            + self.text_editor_block("special.plain_body", "special-needs/body/plain"), title="תוכן")
        return self.section("special-needs", "מעון לבעלי צרכים מיוחדים", "white", [body])

    # ---- §4.7 contacts: the template and its slot in the home -------------------------------------------------------------
    CONTACTS = (("address", "map-marker-alt"), ("phone", "phone-alt"), ("email", "envelope"), ("links", "link"))

    def contacts(self):
        self.doc = "contacts"
        columns = []
        for name, icon_name in self.CONTACTS:
            p = f"contacts/grid/{name}"
            ico = self.widget(f"{p}/icon", "icon", styled({
                "selected_icon": icon(icon_name), "view": "stacked", "shape": "circle", "align": "start",
                "size": size(22), "icon_padding": {"unit": "em", "size": 0.7, "sizes": []}},
                primary_color="purple-700", secondary_color="white"))
            columns.append(self.container(p, {**flex("column", 12, 0, flex_align_items="flex-start"),
                                               "padding": ZERO, "css_classes": "nh-contact"},
                                          [ico]
                                          + self.heading_block(f"contacts.{name}.title", f"{p}/title", "h3", "h3", "ink")
                                          + self.text_editor_block(f"contacts.{name}.body", f"{p}/text"),
                                          title=self.text(f"contacts.{name}.title", "full")))
        grid = self.container("contacts/grid", {
            "container_type": "grid", "grid_columns_grid": {"unit": "fr", "size": 4, "sizes": []},
            "grid_columns_grid_tablet": {"unit": "fr", "size": 2, "sizes": []},
            "grid_columns_grid_mobile": {"unit": "fr", "size": 1, "sizes": []},
            "grid_rows_grid": size("auto"), "grid_gaps": gaps(GRID_GAP, 32), "padding": ZERO}, columns,
            title="עמודות")
        title = self.heading_block("contacts.title", "contacts/title", "h2", "h2", "purple-700", "nh-visually-hidden")
        return [self.section("contacts", "צור קשר", "green-50", title + [grid], gap_row=0)]

    def contacts_slot(self):
        """In the home: a plain full-width container holding Pro's Template widget, which prints the saved template
        `contacts` (the band itself, with its section classes and id, is the template's top container)."""
        tpl = self.widget("contacts-slot/template", "template", {"template_id": "@page-id:contacts",
                                                                   "_title": "צור קשר — תבנית משותפת"})
        return self.container("contacts-slot", {**flex("column", 0, 0), "padding": ZERO,
                                                "css_classes": "nh-slot nh-slot--contacts"}, [tpl], inner=False,
                              title="צור קשר (תבנית)")

    # ---- §4.8 snoezelen ---------------------------------------------------------------------------------------------------
    def snoezelen(self):
        text = self.container("snoezelen/grid/text", {**flex("column", 16, 0), "padding": ZERO},
                              self.heading_block("snoezelen.title", "snoezelen/grid/text/title", "h2", "h2",
                                                 "purple-700")
                              + self.text_editor_block("snoezelen.intro", "snoezelen/grid/text/intro",
                                                       "nh-full" if self.has_plain("snoezelen.plain_body") else "")
                              + self.text_editor_block("snoezelen.list", "snoezelen/grid/text/list",
                                                       join("nh-checklist", "nh-full"
                                                            if self.has_plain("snoezelen.plain_body") else ""))
                              + self.text_editor_block("snoezelen.plain_body", "snoezelen/grid/text/plain")
                              + self.text_editor_block("snoezelen.credit", "snoezelen/grid/text/credit", "nh-credit",
                                                       typo="small", color="ink-2"), title="טקסט")
        video = self.container("snoezelen/grid/video", {**flex("column", 0, 0, flex_align_items="center"),
                                                        "padding": ZERO},
                               [self.video("snoezelen/grid/video/short", "Tkbqa08a5Ro", ratio="916",
                                           _element_width="initial", _element_custom_width=size(320),
                                           _flex_align_self="center")], title="סרטון")
        grid = self.container("snoezelen/grid", {
            "container_type": "grid", "grid_columns_grid": size("7fr 5fr"),
            "grid_columns_grid_tablet": size("7fr 5fr"),
            "grid_columns_grid_mobile": {"unit": "fr", "size": 1, "sizes": []},
            "grid_rows_grid": size("auto"), "grid_gaps": gaps(GRID_GAP, 32), "padding": ZERO}, [text, video],
            title="טקסט וסרטון")
        return self.section("snoezelen", "חדר סנוזלן", "white", [grid])

    # ---- §4.9 footer --------------------------------------------------------------------------------------------------------
    def footer(self):
        self.doc = "footer-v4"
        white_links = ("white", "white")
        copyright_ = self.text_editor_block("footer.copyright", "footer/copyright", typo="small", color="white",
                                            links=white_links, align_mobile="center")
        links = self.text_editor_block("footer.links", "footer/links", typo="small", color="white", links=white_links,
                                       align_mobile="center")
        root = self.container("footer", styled({
            "content_width": "boxed", "boxed_width": size(CONTENT_WIDTH),
            **flex("row", 8, 24, wrap="wrap", flex_direction_mobile="column", flex_justify_content="space-between",
                   flex_align_items="center", flex_justify_content_mobile="center"),
            **section_padding({"": "1.5rem", "_tablet": "1.5rem", "_mobile": "2rem"}),
            "background_background": "classic", "css_classes": "nh-footer-v4"}, background_color="purple-700"),
            copyright_ + links, inner=False, title="כותרת תחתונה v4")
        return [root]

    # ---- documents ------------------------------------------------------------------------------------------------------------
    def home(self):
        self.doc = "home"
        return [self.hero(), self.specialties(), self.about(), self.testimonials(), self.special(),
                self.contacts_slot(), self.snoezelen()]


# the setting that holds a block's text, per widget type
FIELDS = {"heading": "title", "text-editor": "editor", "button": "text"}

DOCS = {
    "home": {"title": "דף הבית", "slug": "דף-הבית", "front_page": True, "post_type": "page",
             "document_type": "wp-page", "template": "elementor_header_footer", "conditions": [], "page_settings": {}},
    "header-v4": {"title": "כותרת עליונה v4 — נווה האירוס", "slug": "nh-header-v4", "front_page": False,
                  "post_type": "elementor_library", "document_type": "header", "template": "",
                  "conditions": ["include/singular/front_page"],
                  "page_settings": {"content_wrapper_html_tag": "header"}},
    "footer-v4": {"title": "כותרת תחתונה v4 — נווה האירוס", "slug": "nh-footer-v4", "front_page": False,
                  "post_type": "elementor_library", "document_type": "footer", "template": "",
                  "conditions": ["include/singular/front_page"],
                  "page_settings": {"content_wrapper_html_tag": "footer"}},
    "contacts": {"title": "צור קשר — בלוק משותף", "slug": "nh-contacts", "front_page": False,
                 "post_type": "elementor_library", "document_type": "container", "template": "", "conditions": [],
                 "page_settings": {}},
}


def files_sha256(paths):
    rows = sorted((rel, hashlib.sha256(open(os.path.join(ROOT, rel), "rb").read()).hexdigest()) for rel in paths)
    return hashlib.sha256("".join(f"{rel}\t{digest}\n" for rel, digest in rows).encode()).hexdigest()


def source_info():
    """What templates/v4/*.json are built from (scripts/test_site.py v4 compares it with the files)."""
    return {"inputs": list(INPUTS), "inputs_sha256": files_sha256(INPUTS)}


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


def headings(elements, docs, mode, inherited=frozenset()):
    """(level, element id) of the headings shown in mode 'full' or 'plain', in document order; the Template widget's
    template (@page-id:<key>) is expanded in place."""
    out = []
    for el in elements:
        cls = classes_of(el) | inherited
        if (mode == "full" and "nh-plain" in cls) or (mode == "plain" and "nh-full" in cls):
            continue
        s = el.get("settings") or {}
        if el.get("widgetType") == "heading" and re.fullmatch(r"h[1-6]", str(s.get("header_size"))):
            out.append((int(s["header_size"][1]), el["id"]))
        if el.get("widgetType") == "template":
            m = re.fullmatch(r"@page-id:([a-z0-9-]+)", str(s.get("template_id")))
            if m and m.group(1) in docs:
                out += headings(docs[m.group(1)]["elementor_data"], docs, mode)
        out += headings(el.get("elements") or [], docs, mode, frozenset(cls & {"nh-full", "nh-plain"}))
    return out


def check(docs, kit, menu, content, media_list):
    problems = []
    blocks = content["blocks"]
    all_ids = []
    used = {}
    for key in DOC_KEYS:
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
            # motion
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
                    if not ((host in ("youtube.com", "www.youtube.com") and path.endswith("/youtube_url"))
                            or url == FACEBOOK):
                        problems.append(f"{path}: external URL {url}")
            # styled through the Kit
            glob = s.get("__globals__") or {}
            wt = el.get("widgetType")
            need = {"heading": ("title_color",), "text-editor": ("typography_typography", "text_color"),
                    "button": ("typography_typography", "button_text_color"),
                    "nav-menu": ("menu_typography_typography", "color_menu_item"),
                    "nested-accordion": ("title_typography_typography", "normal_title_color"),
                    "icon": ("primary_color", "secondary_color")}.get(wt, ())
            for control in need:
                if control not in glob:
                    problems.append(f"{where}: {wt} without a Kit global for {control}")
            if wt == "heading" and "typography_typography" not in glob and s.get("typography_typography") != "custom":
                problems.append(f"{where}: heading without a font (Kit global or local)")
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
                used.setdefault((block, mode), []).append(key)
                el = elements.get(ref.get("element"))
                text = (blocks.get(block) or {}).get(mode)
                if el is None or text is None:
                    problems.append(f"{key}: block {block} ({mode}) → element {ref.get('element')} not found")
                    continue
                value = el["settings"]
                for part in ref["field"].split("."):
                    value = value[int(part)] if isinstance(value, list) else (value or {}).get(part)
                kind = blocks[block]["kind"]
                want = (text if kind == "html" or el.get("widgetType") in ("button", "nested-accordion")
                        else html.escape(text, quote=False))
                if el.get("widgetType") == "text-editor" and kind != "html":
                    want = f"<p>{want}</p>" if want else ""
                if value != want:
                    problems.append(f"{key}/{el['id']}: block {block} ({mode}) is not verbatim: {str(value)[:80]!r}")
                if mode == "plain" and "nh-plain" not in classes_of(el):
                    problems.append(f"{key}/{el['id']}: the plain twin of {block} has no class nh-plain")
                if mode == "full" and blocks[block].get("plain") and "nh-full" not in classes_of(el) \
                        and block not in ("special.more_title",):
                    problems.append(f"{key}/{el['id']}: {block} has a plain twin but no class nh-full")
        # headings
        for mode in ("full", "plain"):
            levels = headings(doc.get("elementor_data") or [], docs, mode)
            first_max = 1 if doc.get("post_type") == "page" else 2
            if key in ("header-v4", "footer-v4") and levels:
                problems.append(f"{key}: headings in the {key[:6]} ({levels})")
            if levels and levels[0][0] > first_max and key == "home":
                problems.append(f"{key} ({mode}): the first heading is h{levels[0][0]}, not h1")
            if key == "contacts" and levels and levels[0][0] > 2:
                problems.append(f"{key} ({mode}): the template starts at h{levels[0][0]} (inserted under the page's h1)")
            for (a, _), (b, el_id) in zip(levels, levels[1:]):
                if b > a + 1:
                    problems.append(f"{key} ({mode}): h{a} → h{b} at {el_id} skips a level")
            if key == "home" and [lv for lv, _ in levels].count(1) != 1:
                problems.append(f"{key} ({mode}): {[lv for lv, _ in levels].count(1)} h1, expected one")
    # every block once (its full text, its plain twin if set)
    for block, b in blocks.items():
        for mode in ("full", "plain"):
            text = b.get(mode)
            if text is None or (mode == "plain" and text == ""):
                if (block, mode) in used:
                    problems.append(f"block {block} ({mode}) is null in home.json but used")
                continue
            n = len(used.get((block, mode), []))
            if n != 1:
                problems.append(f"block {block} ({mode}) is used {n} times, expected once")
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
    for name, obj in [(f"{k}.json", docs[k]) for k in DOC_KEYS] + [("kit.json", kit), ("menu.json", menu)]:
        path = os.path.join(OUT, name)
        with open(path, "w", encoding="utf-8") as fh:
            json.dump(obj, fh, ensure_ascii=False, indent=1)
            fh.write("\n")
        if name in [f"{k}.json" for k in DOC_KEYS]:
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
        stale = [k for k in DOC_KEYS if docs.get(k) != fresh[0][k]] + [n for n, a, b in (("kit", kit, fresh[1]),
                                                                                       ("menu", menu, fresh[2])) if a != b]
        problems.append("templates/v4 is not what the current inputs make (" + ", ".join(stale)
                        + "): run python3 -I scripts/build_v4.py")
    if problems:
        die("check failed:\n  " + "\n  ".join(problems))
    summary(docs, content)
    n = sum(1 for k in DOC_KEYS for e in walk_elements(docs[k]["elementor_data"]) if e["elType"] == "widget")
    print(f"· check: passed ({len(DOC_KEYS)} documents, {n} widgets, kit and menu)")


if __name__ == "__main__":
    sys.exit(main())
