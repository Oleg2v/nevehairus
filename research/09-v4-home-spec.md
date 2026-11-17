# v4 home page — build spec (the live site's structure, new calm design)

17.11.2026. The contract for everyone who builds the new home page. Oleg approved the plan
(reports/2026-11-17/home-plan.md). Itzik rejected v3.2: the new site follows the live site's structure
(same sections, order, menu); only the design is new.

## 0. Hard rules

- Work only on the TEST site https://nevehairus.ussl.co.il . The live site https://www.nevehairus.com is
  read-only: look at it and download its public texts, photos, the PDF and the logo with GET. Never log in,
  never post anything there.
- Texts come from the live home page, word for word. Invent nothing: no new facts, numbers, stories,
  quotes, names. If a section would be empty without invented text, put «טקסט זמני» there.
  Nothing from the old v3/v3.2 copy («יותר מ־100 דיירים», «אגף חדש 2021», «סנטוריום 1957», stories…).
- Plain-language twins (§6) are the one exception. They say the SAME facts in simpler words. They are drafts
  for the home to check.
- Only Elementor's own widgets (Elementor 4.4.0 and Pro 4.4.0, classic widgets) on the pages. The client edits
  every text and photo in the Elementor editor. Our nevehairus-site plugin's widgets are not used on v4 pages.
- No new plugins. Site-wide code goes into the child theme `theme/hello-elementor-child` (§5).
- Nothing loads from other hosts except YouTube, after a click on a video. No Google Fonts, no CDN, no
  Font Awesome files (the site renders icons as inline SVG: Elementor's e_font_icon_svg experiment is on).
- No motion: no sliders, no entrance animations, no Motion Effects, no parallax, no Ken Burns, no
  transitions longer than 150ms (colour only). Nested Accordion animation duration 0. Under
  `prefers-reduced-motion` nothing moves at all.
- RTL Hebrew; WCAG 2.1 AA / IS 5568: contrast, alt text, keyboard focus, heading order h1→h2→h3.
- No «מצב רגוע» switch anywhere on v4.

## 1. Design tokens

Colours (from the current logo): purple and green are the logo's, the blue is its sky.

| Token | Hex | Use |
|---|---|---|
| purple-700 | #4A2D84 | primary: buttons, links, icons, H1/H2 accents (white text on it: 10.6:1) |
| purple-800 | #3A2269 | hover of purple things |
| purple-50 | #F3F0F9 | quiet panels (testimonial card, framed box) |
| sky-500 | #5BBDEB | decoration only (lines, icon circles on purple). Never text |
| sky-50 | #EAF6FC | background of «קצת עלינו» |
| green-500 | #8CC05C | decoration only. Never text |
| green-50 | #EFF6E8 | background of the contacts band |
| ink | #1F2421 | body text (16:1 on white) |
| ink-2 | #4F5550 | secondary text (7.5:1 on white, ≥7:1 on the tints) |
| line | #D9DCE3 | hairlines, card borders |
| white | #FFFFFF | page background |

Type: **Assistant** only, served by the child theme (variable woff2, weights 400–800, §5.1).
- H1: 700, clamp(2rem, 1.4rem + 2.4vw, 3.25rem), line-height 1.15.
- H2: 700, clamp(1.75rem, 1.4rem + 1.2vw, 2.5rem), 1.2.
- H3: 700, 1.375rem (1.25rem on phones), 1.3.
- Body: 400, 1.1875rem (19px) on desktop, 1.125rem (18px) on phones, line-height 1.65.
- Small: 1rem.
- UI and buttons: 600, 1.0625rem.
- Sizes in rem, so the accessibility menu's text size (§5.3) scales everything.

Layout:
- Content width 1200px (`boxed_width`).
- Side gutters 20px phones / 32px tablet / 40px desktop.
- Section padding 5rem top and bottom (3.5rem on phones).
- Grid gap 24px.

Shape:
- Cards and panels: radius 12px.
- Buttons: radius 8px, min-height 48px, padding .75rem 1.5rem.
- Images: radius 12px (in cards only the top corners).
- Every photo is 4:3, by CSS (§5.2, `nh-ratio-4x3`), except the hero photo (fills its half) and videos (16:9; the
  Snoezelen short 9:16).

Elementor breakpoints stay as they are: mobile ≤767, tablet ≤1024.

## 2. Elementor Kit (Site Settings → global colours and fonts)

Set only the Kit's global colours and global fonts. Do not set Theme Style: no body, link, heading or button
styles. Theme Style would also restyle the not-yet-redone v3.2 pages.

- **system_colors:**
  - primary «סגול» #4A2D84;
  - secondary «תכלת» #5BBDEB;
  - text «טקסט» #1F2421;
  - accent «ירוק» #8CC05C.
- **custom_colors** (`_id` 7 lowercase hex chars):
  - «סגול כהה» #3A2269;
  - «רקע סגול» #F3F0F9;
  - «רקע תכלת» #EAF6FC;
  - «רקע ירוק» #EFF6E8;
  - «טקסט משני» #4F5550;
  - «קו» #D9DCE3;
  - «לבן» #FFFFFF.
- **system_typography** (Assistant, sizes in rem/custom):
  - primary = headings 700;
  - secondary = 600;
  - text = 400, 1.1875rem / 1.65, phones 1.125rem;
  - accent = 600, 1.0625rem.
- **custom_typography:**
  - «כותרת H1»;
  - «כותרת H2»;
  - «כותרת H3»;
  - «טקסט קטן» 1rem.

Widgets reference these through `__globals__`, for example:
- `"__globals__": {"title_color": "globals/colors?id=primary", "typography_typography": "globals/typography?id=<id>"}`;
- for a container: `"__globals__": {"background_color": "globals/colors?id=<id>"}`.

So the client changes a colour or font once and it changes everywhere. Fallback literal values may be set
too.

## 3. Documents (Elementor data, built by `scripts/build_v4.py`)

Same document format as `templates/pages/*.json` (key, title, slug, front_page, post_type, document_type,
template, conditions, page_settings, elementor_data). Output goes to `templates/v4/`. Tokens are resolved on
deploy:
- `@media:<name>` → attachment id/url of `templates/v4/media.json` entries;
- `@page:<key>` → permalink of one of our documents (`home`, `main-home`);
- `@page-id:<key>` → its post id;
- `@home:/path/` → home_url(path).

Element ids are 7 lowercase hex chars, stable: a hash of the element's path, so rebuilds diff cleanly.

| key | post | what |
|---|---|---|
| `home` | page #32 (found by `_nh_page_key` = home), the front page, template `elementor_header_footer` | §4 |
| `header-v4` | elementor_library, type `header`, conditions `["include/singular/front_page"]`, page_settings `content_wrapper_html_tag: header` | §4.0 |
| `footer-v4` | elementor_library, type `footer`, same condition, `content_wrapper_html_tag: footer` | §4.9 |
| `contacts` | elementor_library saved template (type `section`; use `container` only if the site lists that type), no conditions | §4.7 |

The v3.2 header #34 and footer #35 stay untouched (condition `include/general`). The more specific
front-page condition wins on the home page. The house page keeps v3.2 until it is redone.

The menu is a WordPress menu «תפריט ראשי», slug `nh-main`, created by the deploy step (§7). The header's
nav-menu widget uses `"menu": "nh-main"`.

## 4. The home page, top to bottom (the live order)

Every section is a top-level container:
- `content_width: boxed`, `boxed_width` 1200px;
- `css_classes: "nh-sec nh-sec--<name>"`, `_element_id: "<name>"`;
- vertical padding §1, background as listed.

Widgets carry `_css_classes` where listed. Plain twins (§6) sit next to their full widget: the full one has
class `nh-full`, the plain one `nh-plain`, and the plain one's Navigator label (`_title`) is
«שפה פשוטה — טיוטה».

### 4.0 Header (`header-v4`)

**Desktop: two rows**, as on the live site (logo row, menu row). The 7 menu items with sub-menus do not fit
in one row with the logo and buttons.
- **Row 1** — white, ~84px, boxed:
  - right: the live logo (Image widget, `@media:logo`, height 64px, links to home, alt «מעון נווה האירוס — דף הבית»);
  - left:
    - «בשפה פשוטה» — Button, outline purple, `_css_classes: nh-plain-toggle`, link `#plain`; the theme JS makes it a toggle;
    - the phone «08-9403047» — Button, text style with a phone icon, link `tel:089403047`;
    - «צור קשר» — Button, primary, link `@home:/צור-קשר/`.
- **Row 2** — white with a 1px `line` top border, boxed: nav-menu (Pro):
  - `menu: nh-main`, layout horizontal, align start;
  - pointer `none` (hover = colour, by theme CSS);
  - `submenu_icon` chevron-down;
  - dropdown `tablet`;
  - `menu_name: «תפריט ראשי»`.
- **≤1024: one row:**
  - logo 48px;
  - «בשפה פשוטה» (compact);
  - a phone icon button — `tel:`, aria-label «התקשרו: 08-9403047»;
  - the nav-menu toggle with the label «תפריט» (the theme adds the visible word; the widget's toggle stays the control);
  - the dropdown is full width under the header.
- The header's bottom border is 1px `line`. No shadow.
- **Sticky:** from 1025px the whole header is `position: sticky; top: 0` (theme CSS on
  `.elementor-location-header`; admin bar offset). On phones it is static, as on the live site.
- **Classes:** `nh-header`, `nh-header__top`, `nh-header__nav`, `nh-header__actions` on the containers.

### 4.1 Banner `hero`

The live slider becomes one static band: two halves on desktop, stacked on phones.
- **Container** `nh-hero`, full width (`content_width: full`), min-height 480px desktop, row direction.
- **Text half** (container `nh-hero__text`, purple-700 background, padding 4rem 3rem; phones 2.5rem 1.25rem; flex column, centred vertically):
  - H1 «לחיות בבית שעוצב והותאם ממש בשבילך» — white, «כותרת H1»;
  - subline «שיפור מקצועי והתאמה אישית» — Heading widget with `header_size: p`, white, 1.375rem 600. Both phrases are printed on the live banner images;
  - buttons:
    - «צור קשר» — white background, purple text, link `@home:/צור-קשר/`;
    - «08-9403047» — white 2px outline, white text, phone icon, `tel:089403047`.
- **Photo half** (container `nh-hero__media`, no padding): Image widget `_css_classes: nh-cover-img`, `@media:hero`, `image_size: full`, alt from media.json. The CSS makes it fill its half (`object-fit: cover`).
- **≤767:** column, the photo first (the container's direction `column-reverse` on mobile, or the text half's `_flex_order`), the photo 4:3.
- The DOM order stays text first. Plain twins for the H1 and the subline.

### 4.2 `specialties` — «תחומי התמחות» (white)

- H2 centred «תחומי התמחות».
- **Grid of 4 cards:** grid container, 4 columns / 2 on tablet / 1 on phones, gap 24px.
- **Each card** is a container `nh-card`: white, 1px `line` border, radius 12px, overflow hidden, flex column. It holds:
  1. Image widget `nh-card__img nh-ratio-4x3`, `@media:card-<n>`, linked to the card's target with `custom_attributes: "tabindex|-1,aria-hidden|true"`, so the card has one tab stop;
  2. container `nh-card__body` (padding 1.25rem 1.5rem 1.5rem):
     - H3 `nh-card__title` with a link to the target. The theme's stretched link makes the whole card clickable;
     - Text Editor `nh-card__sub` — the live subline, ink-2, 1rem;
     - Text Editor `nh-card__more` — `<span aria-hidden="true">למידע נוסף ←</span>`, purple, 600.

  Hover: the border and the title turn purple-800 (theme CSS, ≤150ms).
- **The cards:**

  | # | Title (live) | Subline (live) | Target |
  |---|---|---|---|
  | 1 | מעון - בית לחיים | אוכלוסיית מש"ה | `@page:main-home` (live links it wrongly to «תעסוקה») |
  | 2 | מערך דיור בקהילה | אוכלוסיית מש"ה | `@home:/מערך-דיור-בקהילה/` |
  | 3 | מערך דיור לאוטיזם | אוכלוסייה על הרצף האוטיסטי | `@home:/מערך-דיור-בקהילה-אוטיזם/` |
  | 4 | חדשות ועדכונים | (none on live; leave empty) | `@home:/חדשות-ועדכונים/` |

- Photos come from the live pages of each target, not the live card images (two identical «hands», the logo).
- Plain twins for the sublines only.

### 4.3 `about` — «נווה האירוס - קצת עלינו» (sky-50)

- **Grid:** 2 columns on desktop (text 5fr, videos 6fr), 1 on tablet and phones.
- **Text column:** H2 + Text Editor with the live 5 paragraphs, verbatim.
- **Video column:** two Video widgets, one under the other, 16:9:
  - YouTube `kLAGTCOIWQE` and `JQXm_Dku7tI`;
  - settings: `lazy_load: yes`, `yt_privacy: yes`, `rel: ""`, `show_image_overlay: yes`;
  - `image_overlay: @media:video-<id>` — the video's own YouTube thumbnail, hosted by us;
  - `show_play_icon: yes`, `lightbox: ""`.

  So nothing loads from YouTube until a click.
- **Captions** under each video: Text Editor `nh-video__cap`, small, ink-2, with the video's YouTube title
  (the client's own channel):
  - «אגף המגורים החדש בנוה האירוס…»;
  - «נוה האירוס מעון חוסים נס ציונה».

  This is not invented copy. If in doubt, leave the captions out and say so.
- Plain twin for the text.

### 4.4 `testimonials` — «תודות והמלצות» (white)

- H2 centred.
- **Card:** container `nh-quote`, max-width 760px, centred, purple-50, radius 12px, padding 2.5rem. The theme
  draws a large decorative purple «”» in its corner. It holds:
  - H3 «נווה האירוס - המלצה של אמא לדייר במוסד»;
  - Text Editor with the live excerpt verbatim (it ends with «..»);
  - Button «קרא עוד» — primary, `@home:/תודות-והמלצות/`, `aria-label`-free; the text is enough.
- Plain twin for the excerpt (and the H3 if needed).

### 4.5 `special-needs` — «מעון לבעלי צרכים מיוחדים» (white)

- **Container** with max-width 760px, aligned to the start (right).
- **H2.**
- **Framed box** `nh-framed`: purple-50 background, 4px purple-700 border on the inline start (right), radius 12px,
  padding 1.5rem. It holds a Text Editor with:
  - the two live questions as a `<ul>`;
  - «לא כזו איכותית ולא כזו נעימה, נכון? בדיוק.»
- **Text Editor** with the next two live paragraphs (from «נווה האירוס הוא מעון…» through «…מעון לבעלי צרכים
  מיוחדים הוא מקום. בית. …» as on live).
- **Nested Accordion** `nh-more`:
  - one item, title «להמשך קריאה», `default_state: all_collapsed`;
  - `n_accordion_animation_duration` 0 ms; plus/minus icons; `title_tag: div` (the summary is the control).

  Its content container holds a Text Editor with the remaining live paragraphs, verbatim.
- The full parts carry `nh-full`. **One plain twin** (`nh-plain`) replaces the whole section body: a short plain
  text of the same facts.

### 4.6 (no gallery)

The live home hides «גלריית תמונות» (display none on every screen). The new home has no gallery.

### 4.7 `contacts` (green-50) — saved template `contacts`, inserted with the Template widget

On the live site this block is shared by every page. Here it is one saved template: the client edits it once,
and the later pages insert the same template.
- **H2** «צור קשר», visually hidden (`_css_classes: nh-visually-hidden`). The live block has no heading; the H2
  keeps the heading order. It is the menu's own label, not new copy.
- **Grid:** 4 columns / 2 on tablet / 1 on phones.
- **Each column** (`nh-contact`), as on live:
  - an Icon widget: stacked circle, purple-700 with a white icon;
  - H3 with the live title;
  - a Text Editor with the lines and links.

| Title | Content (live, verbatim) |
|---|---|
| כתובתינו | מעון נווה האירוס ת.ד. 28 נס ציונה 70450 · link «מפרט כניסות נגישות» → the PDF (uploaded to our media, `@media:pdf-entrances`), shown with «(PDF)» |
| פרטי התקשרות | טלפון: 08-9403047 (tel link) · פקס: 08-9400805 |
| כתובת מייל | דוד אליהו - מנהל המעון · irus1@nevehairus.com (mailto) · גל שטיינהרט - נציג הבעלים · galsh@inter.net.il (mailto) |
| קישורים נוספים | Facebook (the live page's Facebook URL, text «נווה האירוס בפייסבוק» or the live label) · «מפת אתר» → `@home:/מפת-אתר/` |

Icons:
- `fas fa-map-marker-alt` or location-dot;
- `fas fa-phone-alt`;
- `fas fa-envelope`;
- `fas fa-link`.

Plain twins only where a label is not plain: «כתובתינו» → «הכתובת שלנו». Keep the lines themselves.

### 4.8 `snoezelen` — «חדר סנוזלן בנוה האירוס» (white)

- **Grid:** 2 columns on desktop (text 7fr, video 5fr), 1 on phones.
- **Text column:**
  - H2;
  - Text Editor with the live intro paragraph verbatim;
  - Text Editor `nh-checklist` with the live 4 items as `<ul><li><strong>…</strong>: …</li></ul>`. The theme draws green check marks;
  - Text Editor `nh-credit`, small italic, ink-2: «חדר הוקם בסיוע ובאדיבות המוסד לביטוח לאומי».
- **Video column:** Video widget, YouTube `Tkbqa08a5Ro` (a Short), `aspect_ratio: 916`, max-width 320px, centred,
  lazy and privacy as in §4.3, overlay `@media:video-Tkbqa08a5Ro` (a 9:16 crop of its thumbnail).
- The live intro starts «חדר הוא סביבה…»: the word «סנוזלן» seems missing. Keep it verbatim and report it.
- Plain twin for the text column.

### 4.9 Footer (`footer-v4`)

One thin purple-700 band, white text, boxed, flex row with wrap (centred column on phones):
- «© מעון נווה האירוס»;
- links: הצהרת נגישות `@home:/הצהרת-נגישות/`, מדיניות פרטיות `@home:/מדיניות-פרטיות/`, מפת אתר `@home:/מפת-אתר/`.

Links are white and underlined, with a white focus ring. The accessibility statement link is required on every
page. That page does not exist yet on the test site, nor on the live one: report it.

## 5. Child theme (version 1.2.0)

### 5.1 Fonts

Copy `plugin/nevehairus-site/assets/fonts/assistant-{core,latin}-var.woff2` and `OFL-Assistant.txt` into
`theme/hello-elementor-child/assets/fonts/`. Declare the `@font-face` rules as the plugin's base.css does
(family «Assistant», weights 200–800, `font-display: swap`, the two unicode ranges). Elementor's Google Fonts
stay off (functions.php already does that).

### 5.2 `assets/css/v4.css` (enqueued site-wide after elementor-frontend)

All rules are scoped to the classes below, so v3.2 pages are not affected.
- **Fonts:** body `.nh-sec` and the v4 header/footer inherit Assistant via the Kit; the theme adds the font face only.
- **Visibility:** `.nh-visually-hidden` — the standard visually-hidden rule.
- **Images:**
  - `.nh-ratio-4x3 img`: aspect-ratio 4/3, object-fit cover, width 100%, height auto;
  - `.nh-cover-img`: fills its container (height 100%, object-fit cover); 4:3 on phones.
- **Cards:**
  - `.nh-card`: position relative, overflow hidden, border colour transition 150ms;
  - `.nh-card__title a::after`: the stretched link (inset 0);
  - hover/focus-within: border and title purple-800;
  - focus ring on the title link, also visible around the card (`:focus-within` outline).
- **Quote:** `.nh-quote::before` — a large decorative «”», purple-700 at 25% opacity, top inline-start corner.
- **Framed box:** `.nh-framed` — the start border (fallback if not set in Elementor).
- **Checklist:** `.nh-checklist ul` — no bullets, a green-500 check mark drawn with an inline SVG background or
  `::before` mask (no external file), aligned to the first line.
- **Captions:** `.nh-credit`, `.nh-video__cap` — sizes and colours (fallback).
- **Accordion:** `.nh-more` — the summary styled as a clear button-like row: purple text, 2px purple underline on
  hover, focus ring, no motion.
- **Header:**
  - `.elementor-location-header` sticky from 1025px with `top: var(--wp-admin--admin-bar--height, 0)` when
    signed in;
  - `.nh-header__nav` menu: hover colour purple-800 with a 2px underline;
  - the current item 700 with a 3px purple bar under it, static;
  - sub-menus white with a 1px line border and a radius of 8px;
  - the mobile toggle shows the word «תפריט» next to its icon (`::after` with `content: "תפריט"`, the button
    keeps its own aria-label), min 44×44.
- **Links in text:** `.nh-sec .elementor-widget-text-editor a` — underline (WCAG 1.4.1), purple, thicker on hover.
- **Focus:** `:focus-visible` 3px solid purple-700 (offset 2px) inside v4 sections; on purple
  backgrounds (`.nh-hero__text`, `.nh-footer-v4`) a white ring.
- **Plain mode:** `html:not(.nh-plainmode) .nh-plain { display: none !important }` and
  `html.nh-plainmode .nh-full { display: none !important }`.
- **Editor helpers:**
  - in the Elementor editor (`body.elementor-editor-active`) both twins show;
  - the plain ones get a dashed olive outline and a small label «שפה פשוטה — טיוטה» (`::before`), so the client
    can edit both.
- **No motion:** `@media (prefers-reduced-motion: reduce)` → no transitions.
- **Accessibility menu:** the `html.nh-a11y-*` classes (§5.3).
- **Floating buttons:** styles for both (§5.3, §5.4).

### 5.3 Accessibility menu (our own, no plugin)

Printed by functions.php on `wp_footer` on every page:
- **Button:** `<button class="nh-a11y-btn" aria-expanded="false" aria-controls="nh-a11y-panel">`.
  - Fixed, inline-end bottom (left in RTL), 52×52, purple-700 circle, white inline-SVG accessibility icon.
  - Label «נגישות»: visible as a tooltip-free `aria-label` and a visually-hidden span.
- **Panel:** `<div id="nh-a11y-panel" class="nh-a11y-panel" role="dialog" aria-label="תפריט נגישות" hidden>`.
  - White, line border, radius 12px, above the button.
  - Heading «נגישות».
  - Buttons with `aria-pressed`:
    - «הגדלת טקסט» — cycles 100% → 115% → 130%; shows the current step; resets after 130%;
    - «ניגודיות גבוהה»;
    - «הדגשת קישורים».
  - «איפוס» resets all.
  - A link «הצהרת נגישות» → home_url('/הצהרת-נגישות/').
- **Effects** (classes on `<html>`, applied before paint by a tiny inline script in `wp_head`):
  - `nh-a11y-text-1` / `-2` → `html { font-size: 112.5% / 125% }`. Everything in rem scales;
  - `nh-a11y-contrast` — black background, white text, yellow (#FFE600) links and focus, buttons yellow on black,
    borders white, images unchanged, on v4 and on the theme's own areas;
  - `nh-a11y-links` — every link underlined 2px, plus a yellow background on hover/focus.
- **Behaviour:**
  - stored in localStorage `nh-a11y` (JSON), always inside try/catch;
  - Esc closes and returns focus to the button;
  - opening moves focus to the panel's first control;
  - a click outside closes;
  - no animation.
- Text-to-speech comes later: leave a comment where it will go. Do not build it.

### 5.4 Call button on phones (as on the live site)

Printed on `wp_footer`:
- `<a class="nh-call-btn" href="tel:089403047" aria-label="התקשרו אלינו: 08-9403047">` with an inline-SVG phone icon;
- green (#2E7D32 or darker: white icon at least 3:1);
- 56×56 circle, fixed inline-end bottom;
- shown only ≤767px; the accessibility button sits above it there.

Neither floating button may cover the other, the cookie panel or the footer links at the end of the page: add
bottom padding to `body` on phones equal to their height.

### 5.5 Plain-language toggle (`assets/js/v4.js`, deferred, site-wide)

- Finds `.nh-plain-toggle a, a.nh-plain-toggle` (the header's Button widget) and makes it a toggle:
  - `role="button"`, `aria-pressed`;
  - Space and Enter;
  - `preventDefault` on its `#plain` link.
- Toggles `html.nh-plainmode`, stores `"on"`/`"off"` in localStorage `nh-plain` (the plugin's key, so the choice
  carries between v3.2 and v4 pages), and applies the stored choice before paint (inline head script).
- Announces in a polite live region: «הדף מוצג עכשיו בשפה פשוטה» / «הדף מוצג בשפה הרגילה».
- Pressed state:
  - purple-700 background, white text;
  - the label stays «בשפה פשוטה».
- Does nothing on pages that have the plugin's own masthead toggle (`.v3-plainbtn`). That page is handled by the
  plugin.
- In the Elementor editor (`body.elementor-editor-active` or `elementorFrontend.isEditMode()`) it does nothing:
  both twins show there.

### 5.6 What stays

Everything the theme does today: the skip link, `<main>`, no Hello CSS, no Google Fonts, `scroll-behavior: auto`,
the v3.2 footer styles, the element-cache guard. Bump the version in style.css to 1.2.0 and describe the changes
in theme/README.md.

## 6. Plain-language twins

- **Rules:**
  - same facts as the full text, simpler words, short sentences, one idea per sentence;
  - person first («אנשים עם מוגבלות שכלית התפתחותית», «דיירים ודיירות»; avoid «חוסים», «מש"ה», «מוסד» in the
    plain twin);
  - no new facts, numbers, names, stories, promises;
  - a plain twin may leave details out, but never adds one.
- **Every twin is a draft:** Navigator label «שפה פשוטה — טיוטה», and listed in
  `content/v4/plain-drafts.md` (section, full text, plain text) for the home's staff to check.
- **Stored** in `content/v4/home.json` next to each full block as `"plain"`, with `"plain_status": "draft"`.

## 7. Deploy (`python3 -I scripts/test_site.py v4 [--go]`)

Without `--go` it prints the plan. With `--go`, in this order, each step idempotent, every old value written to
the local change log:

1. **Backup of the current home (once).** A new draft page «דף הבית — גיבוי v3.2 (17.11.2026)», a copy of page
   #32's `_elementor_data`, `_elementor_page_settings`, `_wp_page_template`, `_elementor_edit_mode`,
   `_elementor_template_type` and `_elementor_version`. Post meta `_nh_backup_of` = 32. Found by that meta on a
   later run and not repeated. Read back and compared.
2. **Media.**
   - Upload `templates/v4/media.json` files once, titled `nh4:<name>`, with the Hebrew alt. Reused by title on a
     later run.
   - Ids are kept in `templates/v4/media-map.json`.
   - The PDF goes up as an attachment too.
3. **Menu** «תפריט ראשי» (slug `nh-main`) via the REST menus endpoints.
   - Items: custom links to the live slugs on the test host, plus page #32 / #33 for the home and the house page.
   - The sub-menus follow the live menu.
   - Old items replaced. Not assigned to a theme location (the widget names it).
4. **Kit.** The active Kit's `system_colors`, `custom_colors`, `system_typography`, `custom_typography`
   (§2) via `$kit->update_settings()`. Old values are logged.
5. **Documents** (§3): home, header-v4, footer-v4, contacts.
   - Written as the existing `pages` PHP step does: same meta, CSS caches cleared, conditions cache regenerated.
   - Without the plugin-hash check: v4 uses no plugin widgets.
   - Page #32 stays the front page and keeps its slug.
6. **Checks.** ezCache purged, then the home page smoke-tested: 200, no PHP errors, every widget type and every
   section id present, no unresolved token, no request in the HTML to a host other than ours or YouTube.

Rollback: copy the backup's meta back to page #32 (a `v4 --rollback` path that only does that), and trash
`header-v4` and `footer-v4`.

## 8. Checks before the report

- **Widths** 360, 390, 768, 1024, 1440. RTL is right; nothing overlaps or is cut; no horizontal scroll; both
  modes (normal and plain); the accessibility options on.
- **The Elementor editor** opens the home page without errors, and a text and a photo can be changed (not saved).
- **Every link** of the menu, the buttons and the cards goes where it should. Targets that do not exist yet on the
  test site are listed, not hidden.
- **Accessibility:**
  - axe without serious or critical issues;
  - contrast;
  - alt text on every meaningful image;
  - keyboard focus visible and in order;
  - headings h1 → h2 → h3 without gaps.
- **Network:** no request to a host other than nevehairus.ussl.co.il before a video is clicked.
