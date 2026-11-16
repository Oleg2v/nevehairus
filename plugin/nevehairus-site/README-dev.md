# nevehairus-site — developer notes

The Elementor plugin of the v3.2 design («calm»: [`research/07-calm-v3.2.md`](../../research/07-calm-v3.2.md)). The
contract is [`plugin/SPEC.md`](../SPEC.md): read it first, then its §14 (what v3.2 changed).
This page is the practical part: how to build, how to add a widget, how to test. It is not shipped in
the zip.

Everything here runs locally with PHP 8.3 CLI, Python 3 and (optionally) Node 22. Nothing talks to a
server: there is no WordPress in this repository, the tests use a stub harness.

## Layout

```
plugin/
├── SPEC.md                 the contract
├── composer.json           dev tools only (PHPCS), never shipped
├── dist/nevehairus-site.zip
├── nevehairus-site/        the plugin (this folder)
│   ├── nevehairus-site.php     header, constants, loads includes/, boots NH_Plugin on plugins_loaded
│   ├── includes/               the core classes (NH_*), one per file
│   ├── widgets/<id>.php        one widget per file, discovered by name
│   ├── assets/css/             tokens (synced), base, letters, forms, widgets/<id>.css
│   ├── assets/js/              nh-core.js, nh-forms.js, widgets/<id>.js
│   ├── assets/letters.json     synced from the prototype
│   └── assets/fonts/           synced from the prototype (OFL)
└── tests/                  the stub harness (not shipped)
    ├── bootstrap.php           fake WordPress + Elementor, fixtures, helpers, assertions
    ├── stubs/                  wordpress.php, elementor.php, elementor-pro.php
    ├── fixtures/               media.php (fake media library), pages.php, widgets/<id>.php
    ├── elementor_data.php      the Elementor data of the pages for scripts/build_pages.py, and its render check
    ├── core/*.test.php         unit tests of the core
    ├── run.php                 renders every widget variant, asserts, writes out/
    ├── render.php              one render to stdout or a file
    ├── compare.py              structural diff against the built prototype
    └── shots.mjs               optional screenshots + pixel diff (Playwright)
```

## Build

Run from the repository root.

```bash
python3 -I scripts/build_plugin.py sync     # copy letters.json, tokens.css, fonts from the prototype
python3 -I scripts/build_plugin.py check    # drift, structure, forbidden patterns, php -l, node --check, harness
python3 -I scripts/build_plugin.py          # zip (default): sync + check + plugin/dist/nevehairus-site.zip
```

- Never edit the synced files (`assets/letters.json`, `assets/css/tokens.css`, `assets/fonts/*`): change
  the prototype and run `sync`. `check` fails when a copy drifts.
- `check` also fails on: a widget file whose id, class name or `ID` constant do not match; a PHP file
  without the `ABSPATH` guard; PHP 8.1+ syntax (the target is PHP 8.0, the local CLI is 8.3 and would
  not notice); e-mail addresses (only the reserved example domains are allowed); photo paths of the
  prototype; animation libraries; remote fonts, scripts or CDNs; a failing harness; and (v3.2) motion the
  agency's rules forbid — in CSS `@keyframes`, `animation-timeline`, `view-transition`, `@property`,
  `scroll-behavior: smooth`, `position: sticky`; in JS a `"scroll"` listener, `ResizeObserver`, view
  transitions (comments are not searched).
- `--no-harness` skips run.php (useful while a package is half done; never for a release).
- `--phpcs` adds PHPCS (WordPress-Extra, and PHPCompatibilityWP for PHP 8.0+). Install the tools once:
  `cd plugin && composer install` (they go to `plugin/vendor/`, git-ignored).
- The zip is deterministic (sorted entries, fixed timestamps): the same sources give the same SHA-256.

Deploying the zip to the test site is a separate step (`scripts/deploy_test_site.py`), never part of
the build.

## Run the harness

```bash
php plugin/tests/run.php                      # everything: every widget variant, the two pages, core tests
php plugin/tests/run.php --widget=homes       # one widget (all its variants)
php plugin/tests/run.php --core-only          # only core/*.test.php
php plugin/tests/run.php --suite=forms        # one core test file
php plugin/tests/run.php --verbose            # one line per render / test
php plugin/tests/render.php homes prototype   # print one render (add --page for the full page, --plain for plain mode)
python3 -I plugin/tests/compare.py homes prototype   # structural diff of one variant
python3 -I plugin/tests/compare.py homes --all       # every variant that has a compare target
node plugin/tests/shots.mjs                   # optional: screenshots of out/page-*.html vs the prototype
node plugin/tests/shots.mjs --behaviour       # the core scripts in Chrome: nothing opens or moves by itself, the
                                              # soft appearance of prints, the cookie panel, the visit buttons,
                                              # focus after anchors and menu links, the menu, the switches, the form
```

What run.php checks for every render (a failure exits 1): no PHP notice/warning/exception; output not
empty (unless the variant says `'empty' => true`); well-formed HTML; unique ids; every `for`,
`aria-labelledby`, `aria-describedby`, `aria-controls` resolves; every `img` has `alt`; every `svg` is
`aria-hidden` (itself or a parent) and `focusable="false"`; inline `style` only on a letter's room
(`.v3-room`) and only with custom properties; no forbidden pattern; a widget whose root is a `<section>`
has `aria-labelledby` on a heading inside and **no** `data-header` / `data-running` (v3.2: no running
head, no header theme); no empty `v3-full` / `v3-plain` element; the controls section «מקטע» is the last
one.

It writes, under `plugin/tests/out/` (git-ignored):

- `<id>--<variant>.html`: a full page (the site's CSS and JS, Elementor-like wrappers, the site module's
  parts) — open it with any static server from the repository root, e.g.
  `python3 -m http.server 8080` and http://localhost:8080/plugin/tests/out/cover--prototype.html;
- `<id>--<variant>.json`: the compare target of the variant (read by compare.py);
- `page-home.html`, `page-main-home.html`: the two prototype pages assembled from the widgets
  (fixtures/pages.php), to look at and to screenshot: the masthead, the spreads, and in the footer slot
  the `display` widget (v3.2: the site's footer is Elementor's own widgets, which the harness does not
  render; scripts/build_pages.py builds it).

### Reading compare.py

Both sides are normalised to one line per node, indented by depth:

```
section.v3-cover.v3-spread [aria-labelledby] [id]
  div.nh-container.v3-cover__grid
    "a text node, whitespace collapsed"
```

Classes are sorted; attributes are sorted, and some are compared by presence only (`[id]`, `[src]`,
`[srcset]`, `[sizes]`, `[width]`, `[height]`, `[for]`, `[aria-*by]`, `[action]` and the data
attributes listed in SPEC §11.4); `href` keeps only its `#fragment` (or becomes `URL`); `style` keeps
its custom properties with numbers rounded to two decimals. A whitespace-only text node counts only
in running text (inside an element that also has text of its own) and shows as `" "`.

A selector may be a descendant selector (`footer.nh-footer .v3-switches`); in the plugin output, which has
no surroundings, it falls back to its last part.

The diff prints `-` for the prototype and `+` for the plugin. Every such line must be matched by one
of the fixture's `expect_diff` entries, which are **regular expressions** (Python `re.search`) over the
diff lines, for example:

```php
'expect_diff' => [
	'^-\s+picture\.v3-room__photo$',       // the prototype's photo …
	'^\+\s+span\.v3-room__field',          // … is the sky field without a photo
	'^[-+]\s*(header|div)\.nh-header\.',   // the root tag of the masthead (D3)
],
```

compare.py reports patterns that match nothing, so stale expectations are easy to remove.

## Add a widget

`widgets/cover.php` is the complete reference; copy its shape.

1. **File and class.** `widgets/<id>.php` (id: `^[a-z][a-z0-9-]*$`), class `NH_Widget_<Id>` (dashes
   become underscores, each part capitalised: `jobs-form` → `NH_Widget_Jobs_Form`), `extends
   NH_Widget_Base`, `public const ID = '<id>';`. The file starts with `<?php` and the `ABSPATH` guard.
   Nothing else registers it: the file's presence is the registration.
2. **Constants.** `STYLE_DEPS` / `SCRIPT_DEPS` for shared handles (`nh-letters`, `nh-forms`); the
   widget's own `nh-w-<id>` handles are added automatically when `assets/css/widgets/<id>.css` or
   `assets/js/widgets/<id>.js` exist. `DYNAMIC = true` only if `render()` reads settings, the request,
   the user or the current post. `KEYWORDS` for the panel search.
3. **Controls** (`register_controls()`), in this order of sections: `תוכן`, `תמונות`, repeaters,
   `טופס`, then `NH_Controls::section( $this, [ 'anchor' => … ] )` last (it opens and closes its own
   section; v3.2: the anchor only — no running head). Every visible text through `NH_Controls::pair()` (the text and its plain
   twin), the heading through `NH_Controls::heading()`, images through `NH_Controls::image()`, links
   through `NH_Controls::link()`. Defaults are the prototype's text, verbatim, as plain strings (not
   translated); labels are Hebrew UI strings in `__( '…', 'nevehairus-site' )`.
4. **Render.** Read the settings once: `$s = $this->get_settings_for_display();`. A control whose
   `condition` is not met reads as `null` (e.g. an image's alt when it is decorative), so read with a
   small `str()` helper as the cover does. Build the HTML in a string from escaped parts and the core
   helpers (`nh_section_open()`, `nh_rule()`, `nh_h2()`, `nh_lead()`, `NH_Text::pair()` / `block()`,
   `NH_Letters::room()`, `NH_Picture::render()`, `NH_Html::url()` / `icon()`), and `echo` it once at
   the end with the phpcs comment of the cover. Skip an element whose text pair is empty
   (`NH_Text::state() === 5` or a `pair()` that returns `''`), a repeater item without its main text,
   and a list without items.
5. **CSS.** Copy the prototype's lines listed for the widget in SPEC §4, verbatim, in source order; keep
   only your selectors when a rule mixes components. Rules about `a` or `img` that set `box-shadow`,
   `text-decoration`, `height`, `border` or `max-width` need a specificity of at least (0,1,1) to beat
   Elementor; never put margins on `figure` (Elementor resets them); never style by DOM structure
   (`main > section`).
6. **JS.** Only when the widget really needs it (v3.2: just the masthead's menu and the video facade).
   Plain ES2020 in an IIFE: `nh.widget( '<id>', '<root selector>', ( el ) => { … } )`, and query only
   inside `el`. `nh.widget()` runs on the page now and again when Elementor's editor re-renders the
   widget. Nothing moves (research/07 §4): no scroll listener, no `requestAnimationFrame` loop, no
   animation; the one soft appearance of prints is nh-core.js's `nh.reveal()` — give a print
   `figure.v3-print` the attribute `data-reveal` instead. Listen to `nh:displaychange` when the plain or
   calm mode matters.
7. **Fixture.** `tests/fixtures/widgets/<id>.php` returns `[ 'variants' => [ … ] ]`; each variant has
   `settings` (merged into the control defaults with `array_replace_recursive`, so
   `'items' => [ 1 => [ 'title' => 'x' ] ]` changes only item 2), and optionally `options` (settings of
   Settings → נווה האירוס), `get` (`$_GET`), `queried` (the post being shown: 10 = the home page,
   11 = «מעון ראשי», 12 = the jobs page; default 10), `empty` (true: the settings leave nothing to show),
   `compare` (`page`, `selectors`) and `expect_diff` (regular expressions). Photos: `[ 'id' => nh_test_media_id( 'v3-walk' ), 'url' => '' ]`. The element
   id of a render is `substr( md5( "<id>/<variant>" ), 0, 7 )` (needed for form states).
8. **Run** `php plugin/tests/run.php --widget=<id>` and `python3 -I plugin/tests/compare.py <id> --all`
   until the only differences are the expected ones; then `python3 -I scripts/build_plugin.py check`.

## Things that are easy to get wrong

- **The plain twin.** `''` = the same text in both modes (no class), `-` = hidden in plain mode,
  anything else = shown instead in plain mode. Lines of a twin become separate elements in blocks.
- **The tessera.** Only the final "." of a heading becomes `<span class="v3-dot">.</span>`, and only on
  the full text.
- **Link tokens.** `#privacy`, `#accessibility`, `#sitemap`, `#entrances`, `#jobs-page` in a URL control
  or in inline text are resolved from the settings by `NH_Html::url()` / `NH_Text::esc()`; when a page
  is not set they stay in-page anchors — add `$this->nh_admin_note( … )` for editors if it matters.
- **Once per page.** `NH_Letters::data_script()` prints its script once per request;
  `NH_Site::footer_once( $key, $fn )` prints a part once in the footer. On a re-render in the Elementor
  editor (an AJAX request) there is no footer: such parts are absent in the editor only. v3.2: no widget
  uses either any more (no morph, no lightbox).
- **One weight per letter.** v3.2: the letters stand still, each in one weight for good — a `WEIGHT`
  constant in the widget (ב 600, כ 440, ס 380, ל 600), never a control.
- **Links lead somewhere else.** v3.2 (research/07 §3 item 21): a link leads only to a page that exists or
  to another section — a row of the homes index without a page is not a link, a further-reading row or a
  kaf button back to the same page is not printed (editors see `nh_admin_note()`).
- **Escaping.** `NH_Text::pair()` / `block()` / `esc()` escape; `NH_Html::attrs()` escapes values;
  `NH_Html::url()` returns an escaped URL; strings you build yourself go through `esc_html()` /
  `esc_attr()`. Never echo a raw setting.
- **No addresses.** E-mail addresses (recipients, the public address) are settings, empty by default.
  Fixtures and docs may only use the reserved example domains.
