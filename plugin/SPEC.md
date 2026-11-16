# `nevehairus-site` — plugin specification (step 3)

**Status:** approved for implementation, 16.11.2026. The client approved prototype v3.1 «אותיות שגרים בהן».
**Audience:** the developers (people or agents) who build the plugin in parallel without talking to each other. Everything that two packages must agree on is fixed here. If something is not in this spec, do the simplest thing that keeps the prototype's output and write it in your package report.

**Sources of truth, in this order:**

1. the prototype sources `prototype/src/*.html` and `prototype/src/partials/*.html`, and the built pages `prototype/index.html`, `prototype/program-main-home.html` (the markup to reproduce);
2. `prototype/assets/css/tokens.css`, `main.css`, `v3.css` and `prototype/assets/js/site.js`, `v3.js` (behaviour; line numbers below refer to these files as committed in `4173d31`);
3. `scripts/build_prototype_html.py`, functions `picture()`, `letter_svg()`, `lerp_d()`, `letter_room()`, `doorway()`, `letter_data()` (geometry to port);
4. `prototype/DESIGN-SYSTEM.md` §6–7, `prototype/STORYBOARD.md`, `prototype/README.md`;
5. `research/04-tech-elementor-wp-upress.md` (Elementor 4.4 / WordPress 7.1 / uPress facts).

Facts about Elementor 4.4.0 and Hello Elementor 3.5.2 used below were checked against their released sources (wordpress.org SVN tags `elementor/4.4.0`, `hello-elementor/3.5.2`, WordPress `7.1.4`).

---

## 0. Read me first

### 0.1 Hard rules

- **Never touch the live site** (www.nevehairus.com). **Do not call the test site** from this sandbox, do not install WordPress here, do not create accounts. Testing is static: `php -l`, `node --check` and the stub harness (§11).
- **No secrets and no e-mail addresses** in code, defaults, fixtures or docs. The form recipients and the public e-mail address are plugin settings, **empty by default**. The only allowed address-shaped strings are on the reserved domains `example.com`, `example.test` and `.invalid` (needed for the prototype's e-mail validation message).
- **No GSAP and no other libraries.** Vanilla JS (ES2020, no build step) and CSS only. No CDN, no Google Fonts.
- **Every visible text is an editor control** (widget control or, for site-module parts, a settings field), and **every text has an optional plain-language twin** rendered exactly like the prototype: `<span class="v3-full">…</span><span class="v3-plain">…</span>` or the block equivalent (§3.5).
- **Photos come from the media library** (attachment IDs). Never reference `prototype/assets/img`.
- **Escape all output** (`esc_html`, `esc_attr`, `esc_url`, `wp_kses` with the allowlist in §3.5, `wp_kses_post` where stated).
- **No data from forms is stored in the database**: no posts, no options, no logs with form content. The only database writes caused by a visitor are rate-limit counters in transients, keyed by a salted hash of the IP (§6).
- **Legacy v1 is not ported**: `prototype/assets/css/legacy-v1.css` and the v1/v2 parts of `main.css` / `site.js` listed in §9 stay in the prototype.
- Targets: WordPress 7.1.4, Elementor 4.4.0 + Elementor Pro 4.4.0, Hello Elementor 3.5.2 + child theme, **PHP ≥ 8.0** (write PHP 8.0 syntax: no enums, `readonly`, `never`, first-class callables `f(...)`, typed class constants or `new` in initializers — the local CLI is 8.3 and will not catch them, the build check greps for them).
- Classic `\Elementor\Widget_Base` widgets registered on `elementor/widgets/register`, own category `nevehairus`. Slug `nevehairus-site`, text domain `nevehairus-site`, prefixes `nh_` / `NH_` / `nh-`, CSS classes as in the prototype (`nh-*`, `v3-*`).

### 0.2 Decisions this spec makes

| # | Decision | Why |
|---|---|---|
| D1 | The cookie banner, the SVG sprite and the skip link are printed on **`wp_body_open`** (Hello calls it right after `<body>`), not in `wp_footer`. The visit bar, the global live region and the lightbox dialog are printed in `wp_footer`. If the theme never fires `wp_body_open`, the sprite and the banner fall back to `wp_footer`. | The prototype puts the banner first in Tab order, right after the skip link (DESIGN-SYSTEM 6.2 «первый в Tab»); the icons are referenced with `<use href>` everywhere. |
| D2 | Pages are built with Elementor's **"Elementor Full Width"** layout (`elementor_header_footer`). The site module wraps the content in `<main id="main" class="nh-main" tabindex="-1">` through the hooks `elementor/page_templates/header-footer/before_content` and `…/after_content`. With Hello's default template the existing `<main id="content">` is used and the skip link points there. | Elementor's full-width template has no `<main>` (checked in 4.4.0); the prototype's CSS and JS need one (`main` counter, plain-mode rules, focus return). |
| D3 | Masthead and colophon are widgets placed in Elementor Pro Theme Builder header/footer templates. Their root tag is a control (`html_tag`) defaulting to **`div`**, because Elementor Pro wraps a header template in `<header>` and a footer template in `<footer>` by default (the template's "HTML Tag" document setting `content_wrapper_html_tag`; `Theme_Document::print_elements_with_wrapper()`, read in the Pro Elements 4.4.0 source, 16.11). *(Changed 16.11, step 4.)* The site's own templates (`templates/pages/header.json`, `footer.json`, written by `scripts/test_site.py pages`) set that wrapper to **`div`** and the widgets' `html_tag` to `header` / `footer`: the DOM is then the prototype's (`header.nh-header`, `footer.nh-footer`), with one landmark each. The control keeps its `div` default for a widget dropped into a template that keeps Pro's own wrapper. The `pages` smoke test fails on a `<header>`/`<footer>` wrapper; confirm the landmarks on the test site in step 5 with axe. *(v3.2, B2 16.11: the colophon is gone; the footer template is Elementor's own widgets and keeps Pro's `<footer>` wrapper as its landmark — `footer.json` sets `content_wrapper_html_tag` `footer`, the header template stays `div`; the smoke test checks each against its template, research/07 §12.6.)* | Two `banner`/`contentinfo` landmarks fail axe; none fails the "region" rule. |
| D4 | Hello's `reset.css` and `theme.css`, Hello's skip link and page title are switched off by filters; Elementor's Google Fonts are switched off. The Kit's "Theme Style" must stay empty (step 4). | Hello's reset styles `button`/`[type=button]` with pink borders and hover fills that beat our single-class selectors. |
| D5 | The page cover is a separate widget `page-cover` that reuses the cover's CSS and JS handles. | Different defaults and breadcrumbs; editors pick it by name. |
| D6 | The jobs application form is a widget (`jobs-form`) for the jobs page; its section chrome follows v3 (ruling line, `v3-h2`, lead) instead of the design-system page's eyebrow and developer note; its role list defaults to the ל spread's role titles so `?role=` preselection matches. | The prototype shows the form only as a component sample. |
| D7 | Forms post to `admin-post.php`. With JS they are sent with `fetch` and answered in JSON, so the prototype's in-place success/error UX stays; without JS the handler redirects back (PRG) with a status in the query string. A fresh nonce and time token are fetched from `admin-ajax.php` on the first focus inside a form, because full-page caches (ezCache, Varnish) keep pages longer than a nonce lives. | Keeps the prototype UX and survives page caching. |
| D8 | Cookie consent stays in `localStorage` (`nh-consent-v1`), with no server log. | Nothing is stored server side; today the consent only gates YouTube. |
| D9 | Contact data (phone, fax, address, public e-mail, Facebook), form recipients, privacy notices, form messages, cookie-banner texts and the visit-bar texts live in **Settings → נווה האירוס** and are read by widgets; labels around them are widget controls. | One source for data that appears in five places. |
| D10 | Image `<picture>`: the `<img>` uses WordPress's own `srcset` for the attachment; an AVIF `<source>` is added only when the attachment metadata already holds AVIF alternates (the "Modern Image Formats" metadata shape). A setting (on by default) makes WordPress write JPEG/PNG sub-sizes as WebP (`image_editor_output_format`). | No image processing code in the plugin; keeps most of the prototype's weight savings. |
| D11 | Page-transition names are unique per post: `home-bet-{post_id}`, set by script only for the journey "homes index row → that home's page"; the CSS targets them with `view-transition-class: nh-bet`. | `plugin/README.md` porting notes. |

### 0.3 Notation

- `L12–30` = line range in the file named in the same row or heading. `index` = `prototype/src/index.html`, `page` = `prototype/src/program-main-home.html`, `ds` = `prototype/src/design-system.html`, `header`/`footer`/`cookies`/`lightbox`/`privacy-note`/`rings`/`sprite`/`head` = `prototype/src/partials/*.html`.
- In control tables, **default** values are verbatim prototype text. `⏎` separates lines of a TEXTAREA default (one line = one `<p>`/`<li>`/`<span>`, see §3.5). `-` as a `_plain` default means "hidden in plain mode" (§3.5). `''` = empty. `⊕` marks text that is new (not in the prototype) and needs the client's copy review.
- In markup templates: `{x}` is an escaped value, `{pair:x}` is `NH_Text::pair()` of control `x` and `x_plain`, `{block:x}` is `NH_Text::block()`, `[if …]…[/if]` and `[each …]…[/each]` are template logic, `{icon:arrow-fwd}` is `NH_Html::icon()`.
- "Prototype parity" means: same elements, same classes (order not significant), same attributes and text after the normalisation in §11.4.

---

## 1. File tree and ownership

All plugin code lives in `plugin/nevehairus-site/`. The test harness lives next to it in `plugin/tests/` and is not shipped. Owner = the work package that creates the file (§12). **Nobody edits a file owned by another package**; the only shared registry is the file system itself (widgets and their assets are discovered by name).

```
plugin/
├── SPEC.md                                   this file
├── README.md                                 (exists) overview; core updates the status line at the end
├── composer.json                             core · dev tools only (optional PHPCS + WPCS), never shipped
├── dist/nevehairus-site.zip                  core · built by scripts/build_plugin.py (committed)
├── nevehairus-site/
│   ├── nevehairus-site.php                   core · plugin header, constants, requirement check, boots NH_Plugin
│   ├── uninstall.php                         core · deletes option nh_settings
│   ├── readme.txt                            core · WordPress readme: requirements, third-party licences (fonts OFL)
│   ├── includes/
│   │   ├── class-nh-plugin.php               core · boot, widget discovery, category, widget registration, textdomain
│   │   ├── class-nh-assets.php               core · registers core + per-widget CSS/JS handles by convention
│   │   ├── class-nh-widget-base.php          core · abstract NH_Widget_Base (extends \Elementor\Widget_Base)
│   │   ├── class-nh-controls.php             core · control helpers: text pairs, images, links, headings, section
│   │   ├── class-nh-text.php                 core · plain-language pairs/blocks, lines, tessera dot, kses, link tokens
│   │   ├── class-nh-html.php                 core · attributes, icons, URLs, LTR spans, phone links, aria-current, toggles
│   │   ├── class-nh-letters.php              core · letters.json: svg(), path(), room(), doorway(), data_script()
│   │   ├── class-nh-picture.php              core · <picture>/<img> from attachment IDs, srcset, preload
│   │   ├── class-nh-settings.php             core · option nh_settings: defaults, get(), resolved URLs, phone, tokens
│   │   ├── class-nh-settings-page.php        core · Settings → נווה האירוס (admin only), test e-mail, admin notices
│   │   ├── class-nh-site.php                 core · site module: head, body-open, footer parts, <main>, page setting
│   │   ├── class-nh-forms.php                core · form schema, field rendering helpers, admin-post handler, token
│   │   └── class-nh-compat.php               core · Hello/Elementor filters, image output format
│   ├── widgets/
│   │   ├── cover.php                         core      (reference widget)
│   │   ├── page-cover.php                    residence
│   │   ├── homes.php  steps.php  lamed.php  related.php                     index
│   │   ├── kaf.php  samekh.php  years.php                                   crafts
│   │   ├── plainbox.php  day.php  voices.php                                life
│   │   ├── glance.php  gallery.php  features.php  stories.php               residence
│   │   ├── door.php  jobs-form.php                                          door
│   │   └── masthead.php  display.php                                        chrome (v3.2: colophon.php removed)
│   ├── assets/
│   │   ├── letters.json                      core · copied verbatim from prototype/assets/letters.json by build_plugin.py sync
│   │   ├── fonts/                            core · copied verbatim: 4 × *.woff2 + OFL-Assistant.txt + OFL-FrankRuhlLibre.txt
│   │   ├── css/
│   │   │   ├── tokens.css                    core · copied verbatim from prototype/assets/css/tokens.css (font URLs ../fonts/ still resolve)
│   │   │   ├── base.css                      core · site-wide: reset, type, buttons, spreads, dot, print, roles, quote, toggles, cookies, visit bar, modes, compat
│   │   │   ├── letters.css                   core · letter + room (+ whole mode)
│   │   │   ├── forms.css                     core · fields, checks, alerts, honeypot, form notes
│   │   │   └── widgets/<id>.css              owner of widget <id> (one file per widget that needs CSS)
│   │   └── js/
│   │       ├── nh-core.js                    core · window.nh: helpers, display modes, cookies, visit bar, widget hook
│   │       ├── nh-forms.js                   core · validation, AJAX submit, token refresh, server-state focus
│   │       └── widgets/<id>.js               owner of widget <id> (only widgets that need JS)
│   ├── languages/index.php                   core · "silence is golden" (keeps the Domain Path folder in git)
│   └── README-dev.md                         core · developer notes: build, add a widget, run the harness (not in the zip)
└── tests/                                    not shipped
    ├── bootstrap.php                         core · error→exception, stubs, plugin load, fixtures loader
    ├── stubs/wordpress.php                   core · fake WordPress API (in-memory options, transients, media, mail, hooks)
    ├── stubs/elementor.php                   core · fake \Elementor\Widget_Base, Controls_Manager, Repeater, Plugin, Utils
    ├── fixtures/media.php                    core · fake media library built from prototype/assets/img/manifest.json
    ├── fixtures/pages.php                    core · widget order of the two prototype pages (for assembled pages)
    ├── fixtures/widgets/<id>.php             owner of widget <id> · variants (settings overrides) + compare target + expected diffs
    ├── core/*.test.php                       core · unit tests of the core API and the forms handler
    ├── run.php                               core · lint-free render of every widget/variant + generic assertions + out/ pages
    ├── render.php                            core · render one widget/variant to stdout or out/
    ├── compare.py                            core · structural diff of a widget's output vs the prototype block
    ├── shots.mjs                             core · optional: Playwright screenshots, assembled page vs prototype, pixel diff; --behaviour: the core scripts in a browser
    └── out/                                  generated, git-ignored: <id>--<variant>.html (+ .json for compare.py), page-*.html, shots/
scripts/build_plugin.py                       core · sync | check | zip
```

Files a widget package creates are exactly: `widgets/<id>.php`, optionally `assets/css/widgets/<id>.css`, optionally `assets/js/widgets/<id>.js`, and `tests/fixtures/widgets/<id>.php` — for each of its widget IDs (list in §12). `.gitignore` gets `plugin/tests/out/` and `plugin/vendor/` (core).

---

## 2. Conventions

### 2.1 PHP

- Every PHP file starts with `<?php` + `defined( 'ABSPATH' ) || exit;`. Tabs, WordPress coding style, Yoda conditions optional. One class per file. No global functions except in the main plugin file (none needed).
- Class names `NH_*`; core class files `includes/class-nh-*.php`. Widget class for widget id `foo-bar` is `NH_Widget_Foo_Bar` in `widgets/foo-bar.php` and declares `public const ID = 'foo-bar';`.
- Widget id: `^[a-z][a-z0-9-]*$`. Elementor name = `nh-` + id. CSS/JS handle = `nh-w-` + id.
- No direct DB access, no `$_SESSION`, no cookies set by PHP, no `error_log()` of request data, no remote requests.
- UI strings (control labels, admin texts) are Hebrew and wrapped in `__( '…', 'nevehairus-site' )` / `esc_html__()`. **Default content** (control defaults = prototype text) is plain PHP strings, not wrapped.
- Rendering: build the HTML in a string from escaped parts and core helpers, then `echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts` once at the end of `render()`. Never echo a raw setting.
- Settings from Elementor are read only through `$this->get_settings_for_display()`.

### 2.2 Elementor API that widgets may use

Only this subset (the stub harness implements exactly this): `start_controls_section( $id, $args )`, `end_controls_section()`, `add_control( $id, $args )`, `add_responsive_control()` (treated as `add_control`), `\Elementor\Repeater` + `add_control()` + `get_controls()`, `get_settings_for_display( $key = null )`, `get_id()`, `\Elementor\Controls_Manager` constants `TAB_CONTENT`, `TEXT`, `TEXTAREA`, `NUMBER`, `SELECT`, `SWITCHER`, `MEDIA`, `URL`, `REPEATER`, `HEADING`, `RAW_HTML`, `NOTICE`, `HIDDEN`. **Not allowed:** group controls, `add_render_attribute()`/`print_render_attribute_string()`, `add_link_attributes()`, `content_template()` (the editor renders on the server), style controls that write CSS (`selectors`) — the design is fixed by the prototype; dynamic tags (`'dynamic' => …`).

Method signatures (match Elementor 4.4.0 exactly): `public function get_name()`, `get_title()`, `get_icon()`, `get_categories()`, `get_keywords()`, `get_style_depends()`, `get_script_depends()`, `public function has_widget_inner_wrapper(): bool`, `protected function is_dynamic_content(): bool`, `protected function register_controls()`, `protected function render()`.

Control value shapes: SWITCHER `'yes'|''`; MEDIA `['id' => int|'', 'url' => string]`; URL `['url' => string, …]` (always registered with `'options' => false`, no "new window" option); REPEATER list of arrays each with `_id`; NUMBER string or number.

### 2.3 WordPress API that widgets may call directly

`esc_html`, `esc_attr`, `esc_url`, `esc_html__`, `esc_attr__`, `__`, `_x`, `wp_kses`, `absint`, `sanitize_hex_color`, `sanitize_html_class`, `home_url`, `get_the_ID`, `get_permalink`, `current_user_can`, `wp_date`, `wp_json_encode`, `apply_filters`, `add_query_arg`. Everything else goes through `NH_*` helpers. A widget fixture may define a missing **WordPress core** function for the harness, guarded by `function_exists`, never overriding an existing stub.

### 2.4 Assets

- Core style handles: `nh-tokens` (`assets/css/tokens.css`), `nh-base` (`assets/css/base.css`, deps `nh-tokens` + `elementor-frontend` when that handle is registered), `nh-letters` (deps `nh-base`), `nh-forms` (deps `nh-base`).
- Core script handles: `nh-core` (`assets/js/nh-core.js`, no deps), `nh-forms` (deps `nh-core`). All scripts are registered with `[ 'in_footer' => true, 'strategy' => 'defer' ]`.
- Widget handles are **registered by convention** by `NH_Assets`: every `assets/css/widgets/<id>.css` → style `nh-w-<id>` (deps `nh-base`); every `assets/js/widgets/<id>.js` → script `nh-w-<id>` (deps `nh-core`). Version = `NH_VERSION` (or `filemtime()` when `WP_DEBUG`).
- `nh-tokens`, `nh-base`, `nh-core` are enqueued on every front-end page. Everything else is enqueued only through widgets' `get_style_depends()` / `get_script_depends()`.
- A widget declares extra dependencies with `public const STYLE_DEPS = [ … ];` and `public const SCRIPT_DEPS = [ … ];` (handles; order matters: they are listed before the widget's own handle). Allowed extra handles: `nh-letters`, `nh-forms`, and `nh-w-cover` (page-cover only).
- CSS is copied from the prototype **verbatim** in the source order given in §9 (main.css parts first, then v3.css parts), except the explicit porting changes listed per widget and in §10. No preprocessors, no minification in the plugin (uPress compresses).
- JS is plain ES2020 in an IIFE, `"use strict"`, no globals except `window.nh` (core). Widget scripts start with `nh.widget( '<id>', '<root selector>', ( el ) => { … } )` and query **inside `el`** only (§3.12).

### 2.5 Naming inside the output

- Section anchors (the `id` of a spread) are controls with Latin defaults from the prototype (`cover`, `homes`, `plain`, …). Hebrew anchors are not allowed (sanitised with `sanitize_html_class`).
- Element ids inside a widget are `{anchor}-{suffix}`; if the anchor is empty, `nh-{element id}-{suffix}` (`NH_Widget_Base::nh_uid()`). Form field ids: `{anchor}-{field}` (e.g. `door-name`); the prototype's `c-name`/`v-name` are not kept (invisible).
- The masthead uses the fixed ids of the prototype (`nh-menu`, `nh-menu-title`); the site module uses `nh-cookies`, `nh-cookies-title`, `nh-c-stats` (only when the statistics row is shown), `nh-c-media`. Only one masthead per page. *(v3.2, B2 16.11: no display panel, no lightbox.)*

---

## 3. Core API (exact names and signatures)

All core classes are `final` with `public static` methods unless stated. They never echo; they return strings. Signatures are normative; internals are free.

### 3.1 `nevehairus-site.php` and `NH_Plugin`

Plugin header: `Plugin Name: נווה האירוס — אתר`, `Description: Elementor widgets and site module for the v3.1 design.`, `Version: 0.1.0`, `Requires at least: 7.0`, `Requires PHP: 8.0`, `Author: קורנגה`, `Author URI: https://koranga.co.il/`, `Text Domain: nevehairus-site`, `Domain Path: /languages`, `License: GPL-2.0-or-later`, `Elementor tested up to: 4.4.0`, `Elementor Pro tested up to: 4.4.0`.

Constants: `NH_VERSION = '0.1.0'`, `NH_FILE`, `NH_DIR` (`plugin_dir_path`, trailing slash), `NH_URL` (`plugin_dir_url`), `NH_MIN_ELEMENTOR = '3.24.0'`. The file requires all `includes/class-nh-*.php` **except `class-nh-widget-base.php`** and calls `NH_Plugin::boot()` on `plugins_loaded`. *(Core, 16.11: `NH_Widget_Base` extends `\Elementor\Widget_Base`, so requiring it without Elementor is fatal; `NH_Plugin::widgets()` requires it, and only when that class exists. The main file stays PHP 7 parseable: on PHP < 8.0 it shows an admin notice and loads nothing; a second copy of the plugin returns early when `NH_VERSION` is defined.)*

```php
final class NH_Plugin {
	public static function boot(): void;                    // hooks below; idempotent
	public static function widgets(): array;                 // [ 'cover' => 'NH_Widget_Cover', … ] discovered (requires the files)
	public static function widget_ids_on_disk(): array;      // ids from widgets/*.php without requiring them (for NH_Assets)
	public static function elementor_ok(): bool;             // did_action('elementor/loaded') && version >= NH_MIN_ELEMENTOR
}
```
`boot()` hooks: `init` → `load_plugin_textdomain`; `NH_Settings::init()`, `NH_Site::init()`, `NH_Forms::init()`, `NH_Compat::init()`, `NH_Assets::init()`; if `is_admin()` → `NH_Settings_Page::init()`; if `elementor_ok()` → `elementor/elements/categories_registered` (category `nevehairus`, title `נווה האירוס`, icon `eicon-apps`) and `elementor/widgets/register` (register one instance per discovered class: `$manager->register( new $class() )`). If Elementor is missing or too old: an admin notice (Hebrew) and no widgets; the site module still loads.

Discovery (normative): `glob( NH_DIR . 'widgets/*.php' )`, sorted; skip names starting with `_` or not matching `^[a-z][a-z0-9-]*$`; `require_once` the file; class name = `'NH_Widget_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $id ) ) )`; register only if the class exists, extends `NH_Widget_Base` and `$class::ID === $id`; otherwise skip and, when `WP_DEBUG`, `_doing_it_wrong()`. A widget file may `require_once __DIR__ . '/other.php'` only to extend another widget class (none does in this version). *(Core: the `require_once` is wrapped in `try … catch ( \Throwable )`, so a widget file with a parse error is skipped instead of taking the site down; `NH_Plugin::skipped(): array` (id ⇒ reason) lists skipped files — shown in the settings page's readiness list and failed by `run.php`. Further public members: `CATEGORY = 'nevehairus'`, `class_name( $id )`, and the hook callbacks `load_textdomain()`, `register_category()`, `register_widgets()`, `elementor_notice()`.)*

### 3.2 `NH_Assets`

```php
final class NH_Assets {
	public static function init(): void;               // wp_enqueue_scripts @20: register(); enqueue_site()
	public static function register(): void;           // core handles + nh-w-<id> for every css/js file found (§2.4)
	public static function enqueue_site(): void;       // front end only (not admin): nh-tokens, nh-base, nh-core + inline nhData
	public static function url( string $rel ): string; // NH_URL . $rel
	public static function ver( string $rel ): string; // NH_VERSION, or filemtime when WP_DEBUG
	public static function widget_style( string $id ): ?string;   // 'nh-w-<id>' if the file exists, else null
	public static function widget_script( string $id ): ?string;
}
```
`nhData` is printed with `wp_add_inline_script( 'nh-core', 'window.nhData=' . wp_json_encode( NH_Site::js_data(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' )`. *(Core: `init()` hooks the public callback `enqueue()` = `register()` + `enqueue_site()`. Widget handles come from globbing `assets/css/widgets/*.css` and `assets/js/widgets/*.js`, independent of the widget files.)*

### 3.3 `NH_Widget_Base`

```php
abstract class NH_Widget_Base extends \Elementor\Widget_Base {
	public const ID = '';                    // set by the child
	public const STYLE_DEPS = [];            // extra style handles (before nh-w-<id>)
	public const SCRIPT_DEPS = [];           // extra script handles
	public const DYNAMIC = false;            // true if render() reads NH_Settings, $_GET, the current user or the current post
	public const KEYWORDS = [];

	final public function get_name() { return 'nh-' . static::ID; }
	public function get_categories() { return [ 'nevehairus' ]; }
	public function get_keywords() { return array_merge( static::KEYWORDS, [ 'nevehairus', 'נווה' ] ); }
	public function get_style_depends()  { /* STYLE_DEPS + widget_style(ID) if any */ }
	public function get_script_depends() { /* SCRIPT_DEPS + widget_script(ID) if any */ }
	public function has_widget_inner_wrapper(): bool { return false; }
	protected function is_dynamic_content(): bool { return static::DYNAMIC; }

	// helpers for children
	protected function nh_uid( string $suffix ): string;
	protected function nh_section_open( array $s, array $o ): string;
	protected function nh_section_close( string $tag = 'section' ): string;
	protected function nh_rule(): string;                              // <div class="v3-rule" aria-hidden="true"></div> (v3.2: never drawn)
	protected function nh_h2( array $s, array $o = [] ): string;       // <h2 class="v3-h2" id="{uid title}">{pair:title + dot}</h2>
	protected function nh_lead( array $s, array $o = [] ): string;     // NH_Text::block( lead, lead_plain, … )
	protected function nh_admin_note( string $text ): string;          // visible only to users who can edit_posts
}
```
- `nh_uid( 'title' )` → `"{anchor}-title"` when the `anchor` setting is non-empty (sanitised), else `"nh-{$this->get_id()}-title"`.
- `nh_section_open( $s, $o )` options: `class` (string, required, e.g. `'v3-homes v3-spread'`), `tone` (`''|'sand'|'citrus'|'iris'|'night'` → adds `v3-spread--{tone}`), `labelledby` (suffix, default `'title'`; `null` = no attribute), `tag` (default `'section'`), `attrs` (extra attributes). Output attribute order: `class`, `id` (anchor; omitted if empty), `aria-labelledby`, extra attrs. Example: `<section class="v3-cover v3-spread" id="cover" aria-labelledby="cover-title">`. *(v3.2, research/07 §12.3: no `data-header`, `data-running`, `data-running-plain` — the masthead never changes; an option `header` is ignored.)*
- `nh_h2( $s, $o )` options: `class` (default `'v3-h2'`; `''` for the plain box), `prefix` (control prefix, default `'title'`), `tag` (default `'h2'`), `id` *(core addition: the `nh_uid()` suffix, default `'title'`)*. Uses `title`, `title_plain`, `title_dot` (no tessera when the widget has no `title_dot` control). Returns `''` when the title pair is in state 5.
- `nh_lead( $s, $o )` passes `$o` to `NH_Text::block()` with defaults `full_tag p`, `full_class v3-lead`, `plain_tag div`, `plain_class ''`, `plain_lines p` and reads `lead`/`lead_plain` (or `$o['prefix']`).
- `nh_admin_note()` returns `<p class="nh-admin-note" role="note">{text}</p>` only if `current_user_can( 'edit_posts' )`, else `''`. Used for: form recipient missing, jobs page not set, unresolved link tokens.

### 3.4 `NH_Controls` — adding controls

```php
final class NH_Controls {
	public const PLAIN_HELP = 'ריק = אותו טקסט גם בשפה פשוטה. מקף (-) = להסתיר בשפה פשוטה. כל שורה = שורה נפרדת.';
	public static function pair( $target, string $id, array $a ): void;    // adds {id} and {id}_plain
	public static function heading( $target, array $d ): void;             // pair 'title' + SELECT 'title_dot'
	public static function image( $target, string $id, array $a ): void;   // {id} MEDIA, {id}_alt, {id}_decorative[, {id}_pos][, {id}_sizes]
	public static function link( $target, string $id, array $a ): void;    // URL control, options=false, default ['url' => $a['default']]
	public static function lines( $target, string $id, array $a ): void;   // TEXTAREA, one item per line
	public static function section( \Elementor\Widget_Base $w, array $d ): void; // section "מקטע": anchor
	public static function dot_options(): array;   // ['' => 'סגול', 'clay' => 'חימר', 'ink' => 'דיו', 'citrus' => 'הדר', 'none' => 'נקודה רגילה']
	public static function image_args( array $s, string $id ): array;      // → ['photo' => int, 'alt' => ?string, 'decorative' => bool, 'pos' => ?string, 'sizes' => ?string]
}
```
`$target` is the widget or an `\Elementor\Repeater` (both have `add_control()`).
- `pair()` args: `label` (required), `default` (string), `plain` (default of the twin), `type` (`'text'` → TEXT, `'textarea'` → TEXTAREA; default `'text'`), `rows`, `condition`. The twin `{id}_plain` is always a TEXTAREA (rows 3) labelled `{label} — בשפה פשוטה`, with `description` = `PLAIN_HELP` and the same `condition`. Both get `label_block => true`.
- `heading()` args: `default`, `plain`, `dot` (default `''`), `label` (default `כותרת`).
- `image()` args: `label`, `decorative` (bool default for `{id}_decorative`), `alt` (default for `{id}_alt`, usually `''` = use the media library's alt), `pos` (if set, adds `{id}_pos` TEXT with this default), `sizes` (if set, adds `{id}_sizes` TEXT with this default), `condition`. The MEDIA default is `['url' => '', 'id' => '']` — no placeholder image. *(Core: `{id}_alt` has `condition => [ '{id}_decorative!' => 'yes' ]`: it is hidden for a decorative image and then reads `null`. In general **a control whose condition is not met reads as `null`** from `get_settings_for_display()` (Elementor's own behaviour; the stub does the same), so read settings with a string/array cast, as `widgets/cover.php` does. `pair()` and `link()` also accept `description`.)*
- `section()` args: `anchor` (default `''`). It opens and closes its own controls section (`nh_section`, label `מקטע`). Label: `עוגן (id באנגלית)`. *(v3.2: the running head is gone, and with it the controls `running`, `running_plain`; keys `running*` passed in `$d` are ignored.)*
- `heading()`, `pair()`, `image()`, `link()`, `lines()` add controls into the section that is currently open; the widget opens/closes its sections itself.

### 3.5 `NH_Text` — plain-language twins

```php
final class NH_Text {
	public const HIDE = '-';
	public static function pair( string $full, string $plain = '', array $o = [] ): string;
	public static function block( string $full, string $plain = '', array $o = [] ): string;
	public static function lines( string $text ): array;               // split on \n (\r\n normalised), trim, drop empty lines
	public static function esc( string $text, string $mode = 'inline' ): string; // 'inline' = wp_kses(allowlist)+link tokens+\n→<br>; 'text' = esc_html
	public static function dot( string $html, ?string $variant ): string;      // final "." → tessera
	public static function kses_inline(): array;
	public static function tokens( string $html ): string;             // {phone} {email} {year} → markup (see below)
	// core additions (16.11):
	public static function state( string $full, string $plain = '' ): int;     // 1–5, the table below (skip an element on 5)
	public static function link_tokens( string $html ): string;                // resolves #privacy, #accessibility, … in href="…"
	public static function unresolved_tokens( string $html ): array;           // [ '#accessibility', … ] still unresolved (for nh_admin_note)
}
```
`esc( $text, 'inline' )` joins the non-empty lines with `<br>` (empty lines are dropped, every line is trimmed).

**The five states** (normative for `pair()` and `block()`; `F` = full text, `P` = plain twin, both trimmed):

| state | condition | output |
|---|---|---|
| 1 | `F` ≠ '' and `P` = '' | `F` once, **no** mode class (visible in both modes) |
| 2 | `F` ≠ '' and `P` ≠ '' and `P` ≠ `-` | `F` with class `v3-full`, then `P` with class `v3-plain` |
| 3 | `F` ≠ '' and `P` = `-` | `F` with class `v3-full` only |
| 4 | `F` = '' and `P` ≠ '' and `P` ≠ `-` | `P` with class `v3-plain` only |
| 5 | otherwise | `''` (and the caller omits the surrounding element) |

`pair( $full, $plain, $o )` — inline. Options: `tag` (default `'span'`), `class` (extra classes on every rendered element, e.g. `'v3-roles__meta'`), `dot` (`null` = none; `''` iris; `'clay'|'ink'|'citrus'`; `'none'` = keep the plain "."), `plain_lines` (bool, default false: each line of `P` becomes `<span>…</span>`, joined by a single space — used by `.v3-home__who`), `bare` (default true: in state 1 with no `class`, output the text without a wrapper element), `esc` (`'inline'`|`'text'`, default `'inline'`). Examples:
- `pair( 'החיים קורים כאן.', 'מה עושים ביום?', [ 'dot' => '' ] )` → `<span class="v3-full">החיים קורים כאן<span class="v3-dot">.</span></span><span class="v3-plain">מה עושים ביום?</span>`
- `pair( 'שלושה בתים.', '', [ 'dot' => '' ] )` → `שלושה בתים<span class="v3-dot">.</span>`
- `pair( 'פיקוח משרד הרווחה והביטחון החברתי', 'משרד הרווחה בודק שהכול בסדר.', [ 'tag' => 'b' ] )` → `<b class="v3-full">…</b><b class="v3-plain">…</b>`
- `pair( 'מוגבלות … מלווים', '-', [ 'class' => 'v3-roles__meta' ] )` → `<span class="v3-roles__meta v3-full">…</span>`

`block( $full, $plain, $o )` — block level. Options: `full_tag` (default `'p'`), `full_class`, `full_attrs` (array), `full_lines` (`null` = whole text in one element with `\n` → `<br>`; `'li'`/`'p'`/`'span'` = one child element per line), `plain_tag` (default `'div'`), `plain_class`, `plain_attrs`, `plain_lines` (default `'p'`; `null` = whole text in `plain_tag` with `\n` → `<br>`), `esc`, `dot`. Classes are `"{full_class} v3-full"` and `"{plain_class} v3-plain"` (class order is not significant). Examples:
- cover lead: `block( $lead, $lead_plain, [ 'full_class' => 'v3-cover__lead', 'plain_class' => 'v3-cover__plain' ] )` → `<p class="v3-cover__lead v3-full">…</p><div class="v3-cover__plain v3-plain"><p>…</p><p>…</p>…</div>`
- steps lead: `[ 'full_class' => 'v3-lead', 'plain_tag' => 'p', 'plain_class' => 'v3-lead', 'plain_lines' => null ]` → `<p class="v3-lead v3-full">…</p><p class="v3-lead v3-plain">יש ארבעה צעדים.</p>`
- plain box list: `[ 'full_tag' => 'ul', 'full_lines' => 'li', 'full_attrs' => [ 'role' => 'list' ], 'plain_tag' => 'p', 'plain_lines' => null ]` → `<ul role="list" class="v3-full"><li>…</li>…</ul><p class="v3-plain">…</p>`

`dot( $html, $variant )`: if `$variant` is not `null`/`'none'` and the trimmed HTML ends with `.`, replace that last character by `<span class="v3-dot">.</span>` (iris) or `<span class="v3-dot v3-dot--{variant}">.</span>`. Only the final dot of a heading is a tessera ("הדלת פתוחה. בואו להכיר." keeps its first dot). The plain variant never gets a tessera.

`kses_inline()` allowlist: `a[href|rel|lang|dir]`, `b`, `strong`, `small`, `br`, `span[class|lang|dir|aria-hidden]`. After kses, **link tokens** in `href` are resolved: `#privacy` → `get_privacy_policy_url()`, `#accessibility` / `#sitemap` / `#entrances` / `#jobs-page` → the URL from settings (§7); an unresolved token is left as is (it stays an in-page anchor, as in the prototype) and the widget may add `nh_admin_note()`.

`tokens( $html )` (used by settings texts): `{phone}` → `<a href="tel:{tel}"><span class="nh-ltr">{display}</span></a>`; `{email}` → `: <a href="mailto:{email}"><span class="nh-ltr">{email}</span></a>` when the public e-mail is set, else `''`; `{contact}` → the `{email}` form when the public e-mail is set, else `: ` + the `{phone}` link (the §11 notice must always name a way to reach the controller, so "…ולתקן אותו{contact}." reads correctly both ways); `{year}` → `wp_date( 'Y' )`.

**Which texts get twins.** Every visible text control gets a `_plain` twin through `NH_Controls::pair()`, except: `<option>` texts (HTML cannot hold the spans), numbers drawn as decoration (`v3-year__num`, `v3-steps__n`, `v3-stories__n`), and texts inside a container that is always `v3-full` (years list, stats, facts, feature list) — those widgets have a plain summary control instead. Attribute-only texts (aria-label, alt, `data-*`) have no twin. *(v3.2: the running head (`data-running`, `running_plain`), the swatch names and the gallery's `data-caption-plain` are gone.)*

### 3.6 `NH_Html`

```php
final class NH_Html {
	public static function attrs( array $a ): string;          // ' k="v"' each; null/false skipped; true → bare attribute; arrays joined by ' '; esc_attr
	public static function tag( string $tag, array $a, string $inner ): string;
	public static function icon( string $name, string $class = '', bool $aria_hidden = true ): string;
	public static function url( $link ): string;               // URL control array or string → esc_url'ed URL; resolves link tokens; '' if empty
	public static function is_external( string $url ): bool;    // host differs from home_url() host
	public static function ltr( string $text ): string;        // <span class="nh-ltr">{text}</span>
	public static function tel( string $tel ): string;         // 'tel:' + [+0-9] only
	public static function post_id( string $url ): int;        // url_to_postid() for same-host URLs without a fragment, else 0
	public static function is_current( string $url, string $ids_csv = '' ): bool;
	public static function display_toggles( array $labels, array $states = [] ): string; // v3.2: the switches whose key is in $labels (plain, calm)
	// core additions (16.11):
	public const ICONS  = [ 'phone', 'menu', 'close', 'arrow-fwd', 'arrow-back', 'play', 'expand', 'speech', 'sliders' ];
	public const TOKENS = [ '#privacy', '#accessibility', '#sitemap', '#entrances', '#jobs-page' ];
	public static function resolve_token( string $href ): string; // a link token → its URL from the settings; anything else unchanged
	public static function admin_note( string $text ): string;    // <p class="nh-admin-note" role="note">…</p> for edit_posts users, else ''
}
```
- `display_toggles()` labels are **HTML** — normally `NH_Text::pair()` output (filtered again with the inline allowlist) — or a `[ full, plain ]` array, which is passed to `NH_Text::pair()`.
- `nh_admin_note()` (widgets) and `NH_Forms::recipient_note()` both use `NH_Html::admin_note()`.
- `icon( 'arrow-fwd', 'nh-btn__icon nh-btn__icon--fwd' )` → `<svg class="nh-btn__icon nh-btn__icon--fwd" aria-hidden="true" focusable="false"><use href="#i-arrow-fwd"/></svg>`. With `$class === ''` the `class` attribute is omitted (the arrows in `.v3-roles` rows); `icon( 'arrow-fwd', 'v3-home__arrow' )` for the homes rows. With `$aria_hidden = false` only `focusable="false"` is written — used where the parent is already `aria-hidden` (`.nh-video__icon`, `.nh-gallery__zoom`), exactly as in the prototype. Icon names: `phone menu close arrow-fwd arrow-back play expand speech sliders` (the sprite, §5.3).
- `is_current( $url, $ids )`: true if the queried post or one of its ancestors is in `$ids` (comma-separated post IDs), or if `$url` has no fragment and `post_id( $url )` is the queried post or an ancestor. URLs with a `#fragment` never match by URL (the home page's nav anchors are not "current", as in the prototype).
- *(v3.2, B2 16.11: a switch is printed only when its key is in `$labels`, always in the order plain, calm — the menu passes both, the display widget may pass only `calm`; `motion` is ignored; each switch is `<button type="button" class="v3-toggle" data-{plain|calm}-toggle aria-pressed="false"><span class="v3-toggle__label">{label}</span><span class="v3-toggle__state" aria-hidden="true" data-on="{on}" data-off="{off}"></span><i aria-hidden="true"></i></button>`, research/07 §7.3. The v3.1 form below is history.)*
- `display_toggles( [ 'plain' => …, 'motion' => …, 'calm' => … ] )` → exactly (one per line in the prototype, header L28–30):
  `<button type="button" class="v3-toggle" data-plain-toggle aria-pressed="false"><span>{plain}</span><i aria-hidden="true"></i></button>` + the same with `data-motion-toggle` and `data-calm-toggle`.

### 3.7 `NH_Letters` — port of the build script's letter logic

Data: `assets/letters.json` (copied verbatim), loaded once (static cache). Keys: `upm`, `top`, `source`, and per letter `bet kaf samekh dalet lamed mem_final`: `{ "800": {d, box{x,y,w,h}, adv, room{x,y,w,h}|null}, "300": {…}, lerp, cmds[], a[], b[] }`.

```php
final class NH_Letters {
	public static function data(): array;
	public static function path( string $name, float $weight = 800 ): string;
	public static function svg( string $name, float $weight = 800, array $o = [] ): string;
	public static function room( string $name, array $o = [] ): string;
	public static function doorway(): string;
	public static function data_script( array $names = [ 'bet' ] ): string;
	public static function geometry( string $name, array $o = [] ): array;  // the CSS custom properties of room(), for tests
	public static function num( float $v ): string;                          // Python "{:g}" (PHP sprintf('%g') matches for these values)
}
```
- `path()` = `lerp_d()` (build script L80–91): exact `d` string from JSON for 300/800; otherwise `t = (800 − w) / 500`, numbers `a + (b − a)·t`, each `sprintf( '%.1f' )`, command letters with arity M2 L2 Q4 C6 Z0, format `C` + numbers joined by single spaces (e.g. `M31.0 760.0L…`).
- `svg( $name, $w, [ 'class' => '', 'frame' => '800' ] )` = `letter_svg()` (L69–77): `<svg class="v3-letter v3-letter--{name}[ {class}]" viewBox="{fx} {fy} {fw} {fh}" preserveAspectRatio="xMidYMax meet" aria-hidden="true" focusable="false" data-letter="{name}"><path class="v3-letter__ink" d="{path}"/></svg>`, frame box from `[$frame]['box']`, numbers with `num()`.
- `room( $name, $o )` = `letter_room()` (L94–164). Options: `photo` (int attachment ID, 0 = none), `field` (`''|'sky'`, used only when there is no photo), `weight` (300–800, default 800), `frame` (`'800'|'300'`), `pos` (object-position, validated `^\d{1,3}(\.\d+)?% \d{1,3}(\.\d+)?%$`, default `50% 50%`), `sizes` (default `50vw`), `priority` (bool), `alt` (`?string`; `null` = media library alt), `decorative` (bool), `class`, `fit` (`'cover'|'whole'`), `work` (`[cx, cy, r]` floats, default `[0.5, 0.5, 0.48]`), `extra` (trusted HTML built by the caller: inside `.v3-room__work` in whole mode, after the `<svg>` otherwise), `attrs` (extra attributes on the room `div`, e.g. `data-vt`).
  - Geometry exactly as the Python: bleed `B = 4` font units on closed sides (`bet`, `kaf` open on the left), union box of the grown rooms at 800 and 300, insets `--a*`/`--b*` in `trbl` order, `--fx/--fy/--fw/--fh`, `--pos`, `--asp:{fw}/{fh}`, `--cx/--cy/--sqw/--em-w`, then in whole mode `--clip:ellipse(…)`, `--wl/--wt/--ww/--wh/--wcx/--wcy` (the photo aspect = attachment height/width from metadata; square if unknown), then `--t:{t0}` with `t0 = (800 − weight) / 500` via `num()`. Same property order and formats (`%.3f`, `%.2f` for `--wcx/--wcy`, `%.4f` for `--em-w`). The `style` attribute is `esc_attr()`'d.
  - Photo markup: cover mode → `NH_Picture::render( $photo, [ 'class' => 'v3-room__photo', 'sizes', 'loading' => priority ? 'eager' : 'lazy', 'priority', 'alt', 'decorative' ] )`; whole mode → `<div class="v3-room__photo v3-room__photo--whole"><div class="v3-room__work">{picture class v3-room__img, loading lazy}{extra}</div></div>`; no photo and `field` → `<span class="v3-room__photo v3-room__field v3-room__field--{field}" aria-hidden="true"></span>`.
  - Output: `<div class="v3-room v3-room--{name}[ {class}]" style="{vars}" data-room="{name}"{attrs}>{photo}{svg($name, $weight, frame)}{extra (cover mode)}</div>`.
- `doorway()` = `doorway()` (L167–180), byte-identical: `<div class="v3-door__frame" aria-hidden="true" data-door>` + head svg (`viewBox="250 180 160 540"`) + `<span class="v3-door__bar"></span>` + cap svg (`viewBox="25 180 75 122"`) + `<span class="v3-door__leg"></span></div>`; both svgs `preserveAspectRatio="none" aria-hidden="true" focusable="false"` with the dalet-300 path.
- `data_script( [ 'bet' ] )` = `letter_data()` (L183–190): `<script type="application/json" id="nh-letters">{"bet":{"c":[…],"a":[…],"b":[…]}}</script>` (`JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION`). Returns the script **once per request** (static guard); later calls return `''`.
- *(Core, 16.11: the weight is clamped to 300–800 and rounded to a whole unit; `data_script()` sets `serialize_precision = -1` while encoding (hosts with the old value 17 would print `61.200000000000003`); `names()` lists the letters. The port is exact: every letter directive of `prototype/src/*.html` — and every letter at six in-between weights — gives the build script's markup with **no** numeric tolerance, PHP's `sprintf` rounding equals Python's on all values involved.)*

### 3.8 `NH_Picture` — images from attachment IDs

```php
final class NH_Picture {
	public static function render( int $id, array $o = [] ): string;
	public static function exists( int $id ): bool;            // an image attachment
	public static function srcset( int $id ): string;          // wp_get_attachment_image_srcset( $id, 'full' ) or ''
	public static function avif_srcset( int $id ): string;     // from metadata sources['image/avif'], '' if none or if the attachment itself is AVIF
	public static function src( int $id, int $max = 1440 ): string; // URL of the widest size ≤ $max (fallback: smallest)
	public static function dims( int $id ): array;             // [ width, height ] of 'full'
	public static function alt( int $id ): string;             // _wp_attachment_image_alt
	public static function preload( int $id, string $sizes ): string; // <link rel="preload" as="image" … fetchpriority="high">
}
```
`render( $id, $o )` options: `sizes` (default `100vw`), `class` (on `<picture>`), `loading` (`lazy`|`eager`, default `lazy`), `priority` (bool → `fetchpriority="high"`), `alt` (`?string`), `decorative` (bool → `alt=""`). Returns `''` if the attachment is not an image. Output, attribute order as the prototype (`picture()`, build script L37–56):
```html
<picture class="{class}">[<source type="image/avif" srcset="{avif_srcset}" sizes="{sizes}">]<img src="{src: medium_large → large → full}" srcset="{srcset}" sizes="{sizes}" width="{w}" height="{h}" alt="{alt}" loading="{loading}" decoding="async"[ fetchpriority="high"]></picture>
```
Always a `<picture>` wrapper (CSS targets `picture`). Never add WordPress classes (`wp-image-*`, `attachment-*`). No `srcset`/`sizes` if WordPress returns none. AVIF alternates are read from `wp_get_attachment_metadata( $id )['sizes'][ $size ]['sources']['image/avif']['file']` and `['sources']['image/avif']` for the full size. *(Core: candidates keep only sizes with the full size's aspect ratio (as `wp_image_matches_ratio()`) up to `max_srcset_image_width`; an `alt` of `''` means the media library's alt, like `null` — use `decorative` for `alt=""`; the AVIF preload's `href` is the AVIF candidate of the `<img src>` width.)*

### 3.9 `NH_Settings`

```php
final class NH_Settings {
	public const OPTION = 'nh_settings';
	public static function init(): void;                     // register_setting (group nh_settings) with sanitize()
	public static function defaults(): array;                // every key of §7 with its default
	public static function get( string $key );               // stored value or default
	public static function sanitize( $input ): array;        // §7 rules
	public static function phone(): array;                   // [ 'display' => '08-9403047', 'tel' => '+97289403047' ]
	public static function email_public(): string;           // '' if not set
	public static function recipient( string $form ): string; // 'visit'|'jobs' → valid address or ''
	public static function visit_url(): string;              // setting, or home_url( '/#door' )
	public static function jobs_url( string $role = '' ): string; // permalink of jobs_page (+ ?role=…#form), '' if not set
	public static function page_url( string $key ): string;  // 'accessibility' | 'sitemap' | 'entrances' | 'privacy' → URL or ''
	public static function text( string $key ): string;      // a settings text with NH_Text::tokens() applied, kses'd
}
```
*(Core: `types()` gives the type of every key (text, textarea, html, email, url, tel, page, bool, int) for `sanitize()` and the settings page; `register()` is the `init` callback of `init()`; `text()` keeps newlines (callers split lines) and resolves link tokens too; `page_url()` also accepts `'jobs'`/`'jobs-page'`; `phone()` falls back to the digits of `phone_display` when `phone_tel` is empty; a page setting resolves only to a **published** page.)*

### 3.10 `NH_Site` (details in §5)

```php
final class NH_Site {
	public static function init(): void;
	public static function head(): void;                          // wp_head @1
	public static function prepaint_js(): string;                  // the inline script (§5.2)
	public static function body_open(): void;                     // wp_body_open @5: sprite, skip link, cookie banner
	public static function footer(): void;                        // wp_footer @5: fallbacks, visit bar, live region, footer_once parts
	public static function footer_once( string $key, callable $printer ): void; // print $printer() once in wp_footer
	public static function main_open(): void;  public static function main_close(): void;
	public static function sprite(): string;  public static function skip_link(): string;
	public static function cookie_banner(): string;  public static function visit_bar(): string;  public static function live_region(): string;
	public static function cover_preload(): string;               // preload for the first nh-cover/nh-page-cover photo of the queried post
	public static function calm_default(): bool;                  // Elementor page setting nh_calm_default
	public static function js_data(): array;                      // window.nhData
	public static function is_preview(): bool;                    // Elementor editor preview
}
```
*(Core: public hook callbacks `body_class()`, `page_settings()`, `no_cache_for_form_states()`, `remember_template()`. `footer_once()` called after the footer has run prints the part at once. Only the sprite and the banner fall back to `wp_footer` (D1) — a skip link at the end of the page would be useless. In an Elementor editor re-render (an AJAX request, no footer) queued parts are not printed: the gallery's lightbox is absent in the editor only.)*

### 3.11 `NH_Forms` (details in §6)

```php
final class NH_Forms {
	public const TYPES = [ 'visit', 'jobs' ];
	public static function init(): void;
	public static function schema( string $type ): array;
	public static function action_url(): string;                                   // admin_url( 'admin-post.php' )
	public static function hidden_fields( string $type, string $instance, string $return_url ): string;
	public static function honeypot( string $prefix, string $label = 'אתר' ): string;
	public static function state( string $instance ): array;                       // from $_GET, whitelisted
	public static function field( string $type, string $key, array $o, array $state ): string;   // $o: id, label [full, plain], options (lines), hint [full, plain], selected
	public static function check( string $type, string $key, array $o, array $state ): string;   // $o: id, label [full, plain], error_label
	public static function required_note( string $full, string $plain ): string;
	public static function summary( array $state, array $labels, string $prefix ): string;       // $labels: field key => label text (full)
	public static function success( array $title, array $text, array $state ): string;           // [full, plain] pairs
	public static function privacy_note( string $type ): string;                   // from settings
	public static function recipient_note( string $type ): string;                 // admin-only note when no recipient
	public static function handle(): void;                                         // admin-post callbacks
	public static function token(): void;                                          // admin-ajax: fresh nonce + time token
	// core additions (16.11):
	public const STATUSES = [ 'sent', 'invalid', 'expired', 'limited', 'off', 'error' ];
	public const CODES    = [ 'required', 'email', 'tel', 'consent', 'file_type', 'file_size' ];
	public const MAX_FILE = 5242880;
	public static function halt(): void;                    // do_action( 'nh_forms_halt' ) then exit (the test harness throws there)
	public static function message( string $status ): string;   // the settings text of a status ('' for sent)
	public static function missing_recipient_text(): string;    // the sentence of recipient_note() and of the admin notice
}
```

### 3.12 Core JavaScript: `window.nh` (`assets/js/nh-core.js`)

```js
window.nh = {
  data,                         // window.nhData (§5.6)
  store: { get(k), set(k, v) }, // localStorage with try/catch (site.js L17-20)
  osReduced(),                  // matchMedia('(prefers-reduced-motion: reduce)')
  motionOK(),                   // !nh-reduce-motion && !nh-calm && !osReduced()   (v3.js L22-23)
  announce(msg),                // speaks through dialog[open] [data-announce-menu] or [data-announce] (v3.js L24-30)
  once(target, cls, rootMargin),// adds cls when first seen (v3.js L176-184); target = selector or element(s)
  widget(name, selector, init), // see below
  consent: { read(), save({ stats, media }), showBanner(), hideBanner(), openSettings() },
  syncToggles(),                // aria-pressed of all [data-plain-toggle]:not([data-plain-toggle=action]), [data-calm-toggle], [data-motion-toggle]
  keepPlace(change),            // v3.js L40-60
};
```
`nh.widget( name, selector, init )`: calls `init( el )` once for every element matching `selector` (a) in `document` when the script runs (scripts are deferred, the DOM is parsed), and (b) inside `$scope[0]` on Elementor's `frontend/element_ready/nh-{name}.default` hook (added on `elementor/frontend/init` or immediately if `elementorFrontend.hooks` exists), then calls `nh.syncToggles()`. A `WeakSet` prevents double initialisation. Widget scripts must not run page-wide logic twice (guard with a module-level flag where the prototype logic is page-wide, e.g. the cover morph).

Events (on `document`): `nh:motionchange` (`detail.reduce`), `nh:displaychange`, `nh:consent` (`detail` = consent record). Storage keys: `localStorage` `nh-motion` (`reduce|full`), `nh-calm` (`on|off`), `nh-plain` (`on|off`), `nh-consent-v1` (JSON `{necessary, stats, media, date, v}`); `sessionStorage` `nh-vt`. URL parameter `?plain=1|0`. Classes on `<html>`: `js`, `nh-plainmode`, `nh-calm`, `nh-reduce-motion`, `nh-vt-in`, `v3-ready`, `nh-has-cookies`, `nh-has-bar`; on `<body>`: `v3`.

`assets/js/nh-forms.js` exposes `nh.forms.bind( form )` and binds every `form[data-validate]` on load and on Elementor's `frontend/element_ready/global` hook (§6.4).

---

## 4. Widgets

### 4.0 Rules for every widget

**Overview**

| id | Elementor name | title (panel) | icon | package | prototype block |
|---|---|---|---|---|---|
| cover | nh-cover | ב׳ · שער דף הבית | eicon-image-box | core | index L17–36 |
| page-cover | nh-page-cover | ב׳ · שער עמוד | eicon-image-box | residence | page L17–42 |
| homes | nh-homes | שלושה בתים · אינדקס | eicon-bullet-list | index | index L38–98 |
| plainbox | nh-plainbox | בשפה פשוטה · תיבה | eicon-info-box | life | index L100–122, page L122–143 |
| day | nh-day | היום בארבע מילים | eicon-time-line | life | index L124–157 |
| kaf | nh-kaf | כ׳ · ידיים שיוצרות | eicon-gallery-grid | crafts | index L159–204 |
| samekh | nh-samekh | ס׳ · יש על מי לסמוך | eicon-image | crafts | index `section#support` (v3.2: no rings) |
| years | nh-years | שנים · 1957 ושמיים | eicon-calendar | crafts | index L230–259 |
| voices | nh-voices | קולות · ציטוט ווידאו | eicon-testimonial | life | index L261–295 |
| steps | nh-steps | צעד צעד | eicon-editor-list-ol | index | index L297–311 |
| lamed | nh-lamed | ל׳ · דרושים | eicon-person | index | index L313–332 |
| door | nh-door | ד׳ · הדלת וטופס ביקור | eicon-form-horizontal | door | index L334–377, page L158–233 |
| glance | nh-glance | במבט אחד · מספרים ועובדות | eicon-number-field | residence | page L44–64 |
| gallery | nh-gallery | גלריה · תמונות עם כיתוב | eicon-gallery-justified | residence | page `section#inside` (v3.2: no lightbox) |
| features | nh-features | מה יש בבית + ציטוט | eicon-bullet-list | residence | page L84–105 |
| stories | nh-stories | שלושה סיפורים | eicon-posts-grid | residence | page L107–120 |
| related | nh-related | להמשך קריאה | eicon-link | index | page L145–156 |
| masthead | nh-masthead | כותרת האתר (Theme Builder) | eicon-header | chrome | `partials/header.html` (v3.2) |
| display | nh-display | מתגי תצוגה | eicon-toggle | chrome (v3.2) | `partials/footer.html` `.v3-switches` |
| jobs-form | nh-jobs-form | טופס הגשת מועמדות | eicon-form-horizontal | door | ds L291–338 (D6) |

An icon that does not exist in Elementor's icon font is cosmetic; use `eicon-apps` then.

**Porting procedure (each widget)**

1. Read the prototype block (source with `{{…}}` directives) **and** the same block in the built page (`prototype/index.html` / `program-main-home.html`) — the built page is the exact target.
2. Write `widgets/<id>.php`: class constants, `get_title()`, `get_icon()`, `register_controls()` with the controls below (defaults verbatim), `render()` that outputs the markup below using core helpers.
3. Write `assets/css/widgets/<id>.css` by copying the listed line ranges verbatim (main.css parts first, then v3.css parts, each in source order), splitting combined selectors where a rule mixes components (keep only your selectors, same declarations), and apply the listed porting changes and the rules in §10.
4. Write `assets/js/widgets/<id>.js` by porting the listed ranges into `nh.widget()`, scoped to the root element.
5. Write `tests/fixtures/widgets/<id>.php` (§11.3) and run the harness and `compare.py` until the only differences are the expected ones.

**Controls layout.** Content tab only. Sections in this order (omit empty ones): `תוכן` (heading, lead, texts), `תמונות` (images), the repeater section(s), `טופס` (forms), `מקטע` (the anchor — always last, via `NH_Controls::section()`; v3.2: no running head, and «Section controls: `x` / `y`» below reads «anchor `x`»). Every visible text uses `NH_Controls::pair()` unless §3.5 lists it as an exception. Every image uses `NH_Controls::image()`. Every link uses `NH_Controls::link()`.

**Render rules.**
- Root = `nh_section_open()` … `nh_section_close()`; inside, `<div class="nh-container …">` as in the prototype.
- Skip an element entirely when its text pair is in state 5 (both empty); skip a repeater item when its main text is empty; skip a whole list when it has no items.
- A room or print whose image control is empty: rooms fall back to `field => 'sky'` (the calm sky of the third home); prints (`figure.v3-print`) are omitted; the video poster is omitted (the facade's dark background remains).
- Heading ids come from `nh_uid()`; `aria-labelledby` of the section points to the spread's heading (H1 for covers, H2 otherwise).
- Numbers with symbols (`100+`), phone numbers and e-mail addresses inside RTL text are wrapped in `<span class="nh-ltr">` exactly where the prototype does; Latin words inside Hebrew text keep their `<span lang="en" dir="ltr">` (colophon).
- No inline `style` except the CSS custom properties of rooms (`NH_Letters::room()`) *(v3.2: the swatches are gone; run.php allows a `style` attribute only on `.v3-room`)*.
- `DYNAMIC = true` for widgets that read settings or the request (masthead, cover and page-cover — the phone line —, door, jobs-form, lamed); everything else `false` (Elementor may cache the output). *(v3.2: the colophon is gone; the gallery queues nothing and is static.)*
- *(v3.2, B2 16.11, research/07 §4, §13: no widget script animates anything. The widget scripts left are `masthead.js` (the phone menu) and `voices.js` (the video facade); the one soft appearance of prints is `nh.reveal()` in nh-core.js, on `figure.v3-print[data-reveal]`. A print a widget prints carries `data-reveal`; every letter keeps one weight (`WEIGHT` constants: ב 600, כ 440, ס 380, ל 600).)*

**Reference skeleton** (shape only — `widgets/cover.php` in the core package is the complete reference; long defaults shortened here with `…`):
```php
<?php
defined( 'ABSPATH' ) || exit;

final class NH_Widget_Steps extends NH_Widget_Base {
	public const ID       = 'steps';
	public const KEYWORDS = [ 'steps', 'צעד' ];

	public function get_title() { return __( 'צעד צעד', 'nevehairus-site' ); }
	public function get_icon() { return 'eicon-editor-list-ol'; }

	protected function register_controls() {
		$this->start_controls_section( 'nh_content', [ 'label' => __( 'תוכן', 'nevehairus-site' ) ] );
		NH_Controls::heading( $this, [ 'default' => 'צעד צעד: ארבעה צעדים הביתה.', 'plain' => 'איך מגיעים לגור אצלנו?' ] );
		NH_Controls::pair( $this, 'lead', [ 'label' => __( 'פתיח', 'nevehairus-site' ), 'type' => 'textarea', 'default' => '…', 'plain' => 'יש ארבעה צעדים.' ] );
		$this->end_controls_section();

		$this->start_controls_section( 'nh_list', [ 'label' => __( 'צעדים', 'nevehairus-site' ) ] );
		$r = new \Elementor\Repeater();
		NH_Controls::pair( $r, 'title', [ 'label' => __( 'כותרת', 'nevehairus-site' ) ] );
		NH_Controls::pair( $r, 'text', [ 'label' => __( 'טקסט', 'nevehairus-site' ), 'type' => 'textarea' ] );
		$this->add_control( 'steps', [
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $r->get_controls(),
			'title_field' => '{{{ title }}}',
			'default'     => [ [ 'title' => 'שיחה ראשונה', 'title_plain' => '', 'text' => '…', 'text_plain' => 'מדברים איתנו בטלפון.' ] /* , … */ ],
		] );
		$this->end_controls_section();

		NH_Controls::section( $this, [ 'anchor' => 'start' ] ); // opens and closes its own controls section
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$items = '';
		$k     = 0;
		foreach ( (array) $s['steps'] as $it ) {
			if ( '' === trim( (string) ( $it['title'] ?? '' ) ) ) {
				continue;
			}
			++$k;
			$items .= '<li>'
				. '<h3><span class="v3-steps__n">' . $k . '</span> ' . NH_Text::pair( $it['title'], $it['title_plain'] ) . '</h3>'
				. NH_Text::block( $it['text'], $it['text_plain'], [ 'plain_tag' => 'p', 'plain_lines' => null ] ) . '</li>';
		}
		$html = $this->nh_section_open( $s, [ 'class' => 'v3-steps v3-spread' ] )
			. '<div class="nh-container">' . $this->nh_rule() . $this->nh_h2( $s )
			. $this->nh_lead( $s, [ 'plain_tag' => 'p', 'plain_class' => 'v3-lead', 'plain_lines' => null ] )
			. ( $items ? '<ol class="v3-steps__list" role="list">' . $items . '</ol>' : '' )
			. '</div>' . $this->nh_section_close();
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}
}
```

The tables below give, per widget: constants, controls (id · type · default · twin default), repeaters, markup, CSS, JS, notes and the fixture. Section-control defaults are written as `anchor / running`.

---

### 4.1 `cover` — package core (the reference widget)

*(v3.2, B2 16.11: research/07 §10.2 item 1, §13. The ב stands still at weight 600; no ruling line, no morph, no
`#nh-letters`; the line «או בטלפון» under the button reads the phone from the settings, so the widget is DYNAMIC.)*

Constants: `ID 'cover'`, `STYLE_DEPS ['nh-letters']`, `DYNAMIC true`, `WEIGHT 600`, `KEYWORDS ['cover','bet','שער','ב']`, `SIZES` (the photo's default `sizes`). Section: class `v3-cover v3-spread`, tone none (light). Section control: anchor `cover`.

| control | type | default | `_plain` default |
|---|---|---|---|
| `title_1` | TEXT | `בית.` | — (see below) |
| `title_1_dot` | SELECT dot_options | `''` (iris) | |
| `title_2` | TEXT | `לחיים.` | |
| `title_2_dot` | SELECT | `clay` | |
| `title_plain` | TEXTAREA | `נווה האירוס הוא בית.` | (this is the twin of the whole H1) |
| `lead` | pair, textarea | `בית בנס ציונה לאנשים עם מוגבלות שכלית התפתחותית, גם כשיש התנהגות מאתגרת או אבחנה כפולה.` | `גרים כאן אנשים עם מוגבלות שכלית התפתחותית.⏎הבית נמצא בעיר נס ציונה.⏎אפשר לגור אצלנו.⏎אפשר לבוא לראות את הבית לפני שמחליטים.` |
| `cta_text` | pair | `בואו להכיר — תיאום ביקור` | `לקבוע ביקור` |
| `cta_link` | URL | `#door` | |
| `show_call` | SWITCHER | `yes` | |
| `call_label` | pair (condition `show_call`) | `או בטלפון:` ⊕ | `אפשר גם להתקשר:` ⊕ |
| `photo` | image | — · `_decorative` no · `_alt` '' · `_pos` `46% 50%` · `_sizes` `(min-width: 1024px) 30rem, 44vw` | |

Removed in v3.2: `weight` (the constant 600), `running`.

Markup (target: built `index.html` `section#cover`):
```html
<section class="v3-cover v3-spread" id="cover" aria-labelledby="cover-title">
  <div class="nh-container v3-cover__grid">
    <div class="v3-cover__letter">
      {NH_Letters::room('bet', [photo, sizes, priority => true, pos, weight => 600, alt, decorative, class => 'v3-cover__bet', field => 'sky'])}
    </div>
    <div class="v3-cover__text">
      <h1 class="v3-cover__title" id="cover-title"><span class="v3-full"><span>{dot(title_1)}</span> <span>{dot(title_2)}</span></span><span class="v3-plain">{title_plain}</span></h1>
      {block:lead full_class=v3-cover__lead plain_class=v3-cover__plain}
      [if cta_text and cta_link]<p class="v3-cover__cta"><a class="nh-btn nh-btn--primary" href="{cta_link}" data-visit-cta>{pair:cta_text}{icon:arrow-fwd nh-btn__icon nh-btn__icon--fwd}</a></p>[/if]
      [if show_call and a phone in the settings]<p class="v3-cover__call">{pair:call_label} <a href="{tel}"><span class="nh-ltr">{display}</span></a></p>[/if]
    </div>
  </div>
</section>
```
H1 rule: the H1 always has the `v3-full` / `v3-plain` structure (the CSS lays out the line spans inside `.v3-full`); if `title_plain` is empty (or `-` — the page heading is never hidden), the plain span repeats `title_1 title_2` as text without tesserae. A line whose text is empty is omitted (one-line titles). `data-visit-cta`: nh-core.js points the button at this page's `.v3-door[id]` when there is one (research/07 §5.5).

CSS `cover.css`: v3.css L132–161, L163–191 (v3.1) without the pinning, the drawn rule, the paper «curtain», the settling photo and their `@keyframes` (v3.2: removed by B2), the grid has no row for a rule and a row for `.v3-cover__call`; L452–458, L463. The v3.2 layout and type scale (research/07 §10.2 item 1) are ported with the markup (§15 B4).
JS: none (`cover.js` deleted in v3.2).
*(Core: an empty `cta_link` renders no button; `NH_Widget_Cover::SIZES` holds the default `sizes`.)*
Fixture: `default` (no photo: the sky field, compared with its expected differences); `prototype` = photo `v3-walk`, compare `index.html` `section#cover` — **result: 0 differing lines**; `states`, `no-button`, `no-phone` (edge cases, structure only).

### 4.2 `page-cover` — package residence

*(v3.2, B2 16.11: research/07 §10.3 item 1, §13.)*

Constants: `ID 'page-cover'`, `STYLE_DEPS ['nh-letters','nh-w-cover']`, `DYNAMIC true` (the phone line), `WEIGHT 600`. No `SCRIPT_DEPS` (v3.2: no cover script). Section: class `v3-cover v3-cover--page v3-spread`. Section control: anchor `cover`.

Same controls as `cover` with these defaults: `title_1` `מעון ראשי —`, `title_2` `בית לחיים.` (`title_2_dot` `''`), `title_plain` `המעון הראשי בנס ציונה`, `lead` `בית בנס ציונה לבוגרים ובוגרות עם מוגבלות שכלית התפתחותית, גם עם אבחנה כפולה או התנהגות מאתגרת. צוות קבוע, טיפול מקצועי ויום מלא בעשייה.` / `גרים כאן גברים ונשים עם מוגבלות שכלית התפתחותית.⏎הצוות קבוע, ומכיר את כולם.⏎אפשר לבוא לראות את המקום.`, `cta_text` `תיאום ביקור` / `לקבוע ביקור`, `cta_link` `#visit`, `show_call` / `call_label` as the cover, `photo_pos` `55% 45%`, `photo_sizes` `(min-width: 1024px) 22rem, 44vw`, `photo_alt` `תקרת „שמיים” באגף החדש של המעון: לוח תאורה עם עננים`. Removed in v3.2: `weight` (the constant 600), `running`. Plus:

| control | type | default |
|---|---|---|
| `crumbs_label` | TEXT (aria-label) | `נתיב ניווט` |
| `crumbs` | REPEATER: `label` (pair), `link` (URL) | 1 `דף הבית` → `home_url('/')`; 2 `הבתים שלנו` → `home_url('/#homes')`; 3 `מעון ראשי` → `''` |

Markup (target `program-main-home.html` `section#cover`): as `cover`, with
- class `v3-cover v3-cover--page v3-spread`;
- first child of the grid: `<nav class="nh-crumbs v3-crumbs" aria-label="{crumbs_label}"><ol>[each crumbs]<li><a href="{link}">{pair:label}</a></li>[/each]</ol></nav>` — an item with an empty link renders `<li><span aria-current="page">{pair:label}</span></li>`;
- no ruling line, no `data-vt` on the room, no `#nh-letters` (v3.2: no page transition, no morph);
- H1 class `v3-cover__title v3-cover__title--page`;
- the button with `data-visit-cta` and the line `.v3-cover__call`, as the cover.

CSS `page-cover.css`: main.css L301–305 (`.nh-crumbs`), v3.css L493–501; v3.2: no `view-transition-class` (D11 is gone). The crumbs of research/07 §10.3 item 2 («‹», silent) are ported with the markup (§15 B4).
JS: none.
Fixture: `prototype` = photo `v3-sky-room` on the house page (queried post 11), compare `program-main-home.html` `section#cover`: 0 differing lines (crumb hrefs normalised).

### 4.3 `homes` — package index

*(v3.2, B2 16.11: research/07 §10.2 item 2, §3 item 21, §13. No quick chooser; every row's ב at weight 600; a
row is a link only when its home has a page, otherwise it says «דף מפורט בקרוב».)*

Constants: `ID 'homes'`, `STYLE_DEPS ['nh-letters']`, `WEIGHT 600`, `SIZES '9.5rem'`. Section class `v3-homes v3-spread`. Section control: anchor `homes`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + `title_dot` | heading | `שלושה בתים.` · `''` | '' |
| `lead` | pair, textarea | `האם זה המקום בשבילנו? ליד כל בית כתוב למי הוא מתאים.` ⊕ | `יש לנו שלושה בתים.⏎הבתים שונים זה מזה.⏎ליד כל בית כתוב למי הוא מתאים.` ⊕ |
| `homes` | REPEATER (below) | 3 items | |
| `note` | pair, textarea | `לבית השלישי עוד אין צילומים, ולכן במקום תמונה יש בו שמיים שקטים.` | `לבית השלישי עוד אין תמונות.` |
| `soon_text` | pair | `דף מפורט בקרוב` ⊕ | '' |

Removed in v3.2: `fit_q` and the repeater `fit` (the chooser), `intro` (→ `lead`), the rows' `weight` (the constant 600), `running`.

`homes` repeater fields: `anchor` (TEXT), `tone` (SELECT `paper|sand|sky`), `title` (pair), `who` (pair, textarea), `facts` (pair), `link` (URL; empty = the home has no page yet), `photo` (image: decorative yes, `_pos`). Defaults:

| # | anchor | tone | title | who / who_plain | facts / facts_plain | link | photo · pos |
|---|---|---|---|---|---|---|---|
| 1 | `home-1` | paper | `מעון ראשי — בית לחיים` | `בוגרים ובוגרות עם מוגבלות שכלית התפתחותית, גם עם התנהגות מאתגרת או אבחנה כפולה.` / `בית גדול בנס ציונה.⏎צוות עוזר שם כל היום.` | `שתי יחידות · צוות קבוע · חדר סנוזלן ומרפאת שיניים` / `-` | '' (step 4: the house page) | (v3-sky-room) · `55% 45%` |
| 2 | `home-2` | sand | `דיור בקהילה` | `בוגרים ובוגרות עם מוגבלות שכלית התפתחותית, בדירות ובבתים בתוך העיר — עם צוות מלווה.` / `דירות ובתים בתוך העיר.⏎צוות עוזר לדיירים ולדיירות.` | `שגרה ועצמאות · עבודה ופנאי בעיר · ליווי צמוד` / `-` | '' | (v3-villa) · `50% 45%` |
| 3 | `home-3` | sky | `דיור לצעירים על הרצף האוטיסטי` | `בית לצעירים ולצעירות, עם ליווי אישי בשגרת היום־יום.` / `בית לצעירים ולצעירות עם אוטיזם.⏎הבית שקט, ויש סדר יום ברור.` | `סביבה רגועה · שגרה ברורה` / `-` | '' | none (sky field) · — |

Markup (target `index.html` `section#homes`):
```html
<section class="v3-homes v3-spread" id="homes" aria-labelledby="homes-title">
  <div class="nh-container">
    <div class="v3-rule" aria-hidden="true"></div>
    {nh_h2}
    {nh_lead}
    <ol class="v3-homes__list" role="list">
      [each homes with a link]<li class="v3-home v3-home--{tone}" id="{anchor}">
        <a class="v3-home__link" href="{link}">
          {NH_Letters::room('bet', [photo, sizes => '9.5rem', weight => 600, pos, class => 'v3-home__bet', decorative, field => 'sky'])}
          <span class="v3-home__body">
            <span class="v3-home__title">{pair:title}</span>
            {pair:who tag=span class=v3-home__who plain_lines=true bare=false}
            {pair:facts tag=span class=v3-home__facts bare=false}
          </span>
          {icon:arrow-fwd v3-home__arrow}
        </a>
      </li>
      [each homes without a link]<li class="v3-home v3-home--{tone}" id="{anchor}">
        <div class="v3-home__row">{the same room and body; the facts end with « · <span class="v3-home__soon">{soon_text}</span>»; a plain twin of the facts holds the soon words when the facts are hidden in plain language}</div>
      </li>[/each]
    </ol>
    {block:note full_class=v3-note plain_tag=p plain_class=v3-note plain_lines=null}
  </div>
</section>
```
A link back to the spread itself (`#{anchor}` of the widget or of the row) counts as no link. For the prototype's third row (no photo) the room has no `decorative` image — the sky field `span` is already `aria-hidden`.

CSS `homes.css`: v3.css L193–217 (v3.1) without the chooser; the row fills at once (the `::before` changes `opacity` in 150ms, no «curtain»), the arrow never moves, no `view-transition-class`; `.v3-home__row` is laid out like `.v3-home__link`; L445 third selector only; L449. The v3.2 sizes are ported with the markup (§15 B4).
JS: none (`homes.js` deleted in v3.2).
Fixture: `prototype` = photos `v3-sky-room`, `v3-villa`, row 1 linked to the house page; compare `index.html` `section#homes`: 0 differing lines. `edge`, `linked` (structure only).

### 4.4 `plainbox` — package life

Constants: `ID 'plainbox'`. Section: class `v3-plainbox-wrap` (+ ` v3-plainbox-wrap--page` when `variant = page`), **no** `v3-spread`. Section controls: `plain` / `בשפה פשוטה`.

| control | type | default | `_plain` |
|---|---|---|---|
| `variant` | SELECT `home` (`v3-plainbox-wrap`) / `page` (adds `--page`) | `home` | |
| `title` | pair (no dot control) | `בשפה פשוטה` | '' |
| `items` | pair, textarea (lines → `li`) | `נווה האירוס הוא בית.⏎גרים בו אנשים עם מוגבלות שכלית התפתחותית.⏎יש כאן צוות שעוזר בכל יום.⏎יש חדרים, אוכל, עבודה, חוגים וטיולים.⏎משפחות וחברים יכולים לבוא לבקר.` | `החלקים החשובים בדף כתובים עכשיו בשפה פשוטה.` |
| `button` | pair | `לקרוא את הדף בשפה פשוטה` | `לחזור לקריאה רגילה` |

For the house page (step 4) the editor sets `variant = page`, anchor `plain-page` and `items` = page L130–134 (`המעון הראשי נמצא בנס ציונה.⏎…⏎אפשר לבוא לבקר.`).

Markup (target `index.html` `section#plain`):
```html
<section class="v3-plainbox-wrap" id="plain" aria-labelledby="plain-title">
  <div class="nh-container">
    <div class="v3-plainbox">
      {icon:speech v3-plainbox__icon}
      <div>
        <h2 id="plain-title">{pair:title}</h2>
        {block:items full_tag=ul full_lines=li full_attrs=[role=list] plain_tag=p plain_lines=null}
        <button class="nh-btn nh-btn--primary v3-plainbox__btn" type="button" data-plain-toggle="action">{pair:button}</button>
      </div>
    </div>
  </div>
</section>
```
The button is an action button: **no `aria-pressed`**; core JS handles `[data-plain-toggle]` clicks by delegation.
CSS `plainbox.css`: v3.css L219–227, L535, and from L582–585 the rule `.v3-plainbox { grid-template-columns: minmax(0, 1fr); }` inside `@media (max-width: 479px)`.
JS: none.
Fixture: `default` compare `index.html` `section#plain`; variant `page` compare `program-main-home.html` `section#plain-page`.

### 4.5 `day` — package life

*(v3.2, B2 16.11: research/07 §10.2 item 4, §4.2, §8.1.)*

Constants: `ID 'day'`, `SIZES '(max-width: 23em) calc(100vw - 2.5rem), 21rem'`. Section class `v3-day v3-spread`. Section control: anchor `day`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `החיים קורים כאן.` · `''` | `מה עושים ביום?` |
| `lead` | pair, textarea | `יום רגיל בנווה האירוס בארבע מילים — וכל אחד ואחת בקצב שלהם.` | `-` |
| `moments` | REPEATER: `verb` (pair), `photo` (image, `_sizes` = `SIZES`), `caption` (pair), `text` (pair, textarea) | 4 items below | |

Removed in v3.2: the repeater's `print` (SELECT) and the constants `PRINT_CLASS`, `PRINT_SIZES`; `running`.

| # | verb | photo | caption | text / text_plain |
|---|---|---|---|---|
| 1 | `יוצרים` | (v3-crafts) | `עבודות פסיפס וקרמיקה ביום מכירה.` | `סדנאות פסיפס, תכשיטים, ציור על משי, מוזיקה ותנועה — וגם תעסוקה בעיר.` / `עובדים בסדנאות: פסיפס, תכשיטים וציור.` |
| 2 | `רוכבים` | (v3-riding) | `סוס ליד הגדר, ביום של רכיבה טיפולית.` | `רכיבה טיפולית בחווה, ועוד חוגים מחוץ למעון.` / `רוכבים על סוסים בחווה.` |
| 3 | `מטיילים` | (v3-orchard) | `טיול בין עצי ההדר.` | `טיולים, בתי קפה וסרטים, בעיר ובטבע.` / `יוצאים לטיולים, לבית קפה ולסרט.` |
| 4 | `מארחים` | (v3-villa-print) | `פינה לשבת עם אורחים.` | `ביקורים של משפחה וחברים, וקשר פתוח עם הצוות.` / `המשפחות והחברים באים לבקר.` |

Images are informative (not decorative): alt from the media library.

Markup (target `index.html` `section#day`):
```html
<section class="v3-day v3-spread" id="day" aria-labelledby="day-title">
  <div class="nh-container">
    <div class="v3-rule" aria-hidden="true"></div>
    {nh_h2}
    {nh_lead}
    <ol class="v3-day__list" role="list">
      [each moments]<li class="v3-moment">
        <h3 class="v3-moment__verb">{pair:verb}</h3>
        [if photo]<figure class="v3-print" data-reveal>{NH_Picture::render(photo, [sizes])}<figcaption>{pair:caption}</figcaption></figure>[/if]
        {block:text full_tag=p plain_tag=p plain_lines=null}
      </li>[/each]
    </ol>
  </div>
</section>
```
v3.2: no zigzag (no `v3-moment--a…d`), one print size; `data-reveal`: the print's photo frame may appear softly once (nh-core.js `nh.reveal()`, research/07 §4.2).
CSS `day.css`: v3.css L229–233, L242–249, L462 (v3.1); the v3.2 grid (4 / 2 / 1 columns) and the D-M verbs are ported with the markup (§15 B4). (`.v3 { counter-reset: print }` and `.v3-print` with the reveal are in base.)
JS: none.
Fixture: `prototype` = photos `v3-crafts`, `v3-riding`, `v3-orchard`, `v3-villa-print`; compare `section#day`: 0 differing lines.

### 4.6 `kaf` — package crafts

*(v3.2, B2 16.11: research/07 §10.2 item 5, §13. One still work in the palm of a still כ (weight 440) and a visible
caption; no list of works, no colour swatches, no crossfade; the honey ground; the ruling line first.)*

Constants: `ID 'kaf'`, `STYLE_DEPS ['nh-letters']`, `WEIGHT 440`, `SIZES '(min-width: 64em) 28rem, 70vw'`. Section class `v3-kaf v3-spread`, tone `citrus` («honey», base.css). Section control: anchor `made`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `ידיים שיוצרות.` · `ink` | `עבודות יד` |
| `lead` | pair, textarea | `פסיפס, תכשיטים, קרמיקה וציור: עבודות שיוצרים הדיירים והדיירות בסדנאות. חלק מהיצירות מוצעות למכירה.` | `הדיירים והדיירות יוצרים בפסיפס ובקרמיקה.⏎הם מכינים כדים, ינשופים ותכשיטים.⏎אפשר לקנות חלק מהעבודות.` |
| `caption` | pair, textarea (visible) | `בכף היד: כד פסיפס כחול־ירוק — עבודת יד של דיירות ודיירי נווה האירוס.` ⊕ | '' |
| `button` + `button_link` | pair + URL | `לגלריית היצירות` · `''` | `לראות עוד עבודות` |
| `external_sr` | TEXT | `(נפתח באתר חיצוני)` | |
| `photo` | image: decorative yes (the caption says what it is), `_pos` `50% 42%`, `_sizes` = `SIZES` | (v3-pot-b) | |

Removed in v3.2: `cap_label`, `works_label`, the repeater `works` (`name`, `photo`, `swatches_label`, `swatches`), `palm_pos`, `palm_sizes`, `palm_notice` (→ `photo_pos`, `photo_sizes`), `weight` (the constant 440), `credit`, `running`.

Markup (target `index.html` `section#made`):
```html
<section class="v3-kaf v3-spread v3-spread--citrus" id="made" aria-labelledby="made-title">
  <div class="nh-container v3-kaf__grid">
    <div class="v3-rule" aria-hidden="true"></div>
    <div class="v3-kaf__letter">{NH_Letters::room('kaf', [photo, sizes, weight => 440, pos, class => 'v3-kaf__palm', alt, decorative, field => 'sky'])}</div>
    <div class="v3-kaf__text">
      {nh_h2}
      {nh_lead}
      [if photo]<p class="v3-kaf__cap">{pair:caption}</p>[/if]
      [if button and a button_link to another page]<p><a class="nh-btn nh-btn--ink" href="{button_link}">{pair:button}{icon:arrow-fwd nh-btn__icon nh-btn__icon--fwd}</a></p>[/if]
    </div>
  </div>
</section>
```
The button is printed only with a link, and never with a link back to this spread (`#made`, `#{anchor}`, or this page with that fragment): editors then see `nh_admin_note( 'הכפתור מוביל לאותו מקטע ולכן לא מוצג' )` (research/07 §3 item 21; on staging there is no gallery page yet, §16 item 8). An external link gets `rel="noopener"` and the screen-reader suffix.
CSS `kaf.css`: the prototype's v3.2 «כ׳ · Crafts in the palm» block (`.v3-kaf__letter`, `.v3-kaf__cap`, the two columns from 64em with the base cut by the spread's edge, the text column's bottom padding `var(--v3-pad)`) and the plain-mode letter size. (The grid and the honey ground are in base.css.)
JS: none (`kaf.js` deleted in v3.2).
Fixture: `prototype` = photo `v3-pot-b`; compare `section#made`: 0 differing lines. `button`, `self-link`, `edge` (structure only).

### 4.7 `samekh` — package crafts

*(v3.2, B2 16.11: research/07 §10.2 item 6, §13. A still ס (weight 380), the mandala whole; the circles a plain
list — no rings, no glint, no links.)*

Constants: `ID 'samekh'`, `STYLE_DEPS ['nh-letters']`, `WEIGHT 380`. Section class `v3-samekh v3-spread`. Section control: anchor `support`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `יש על מי לסמוך.` · `''` | `יש מי שעוזר` |
| `lead` | pair, textarea | `סביב כל דייר ודיירת יש מעגלים של אנשים — כמו הטבעות בפסיפס שיצרו הדיירים והדיירות.` | `כל דייר וכל דיירת בוחרים בעצמם.⏎ויש סביבם אנשים שעוזרים:` |
| `caption` | pair | `מנדלה בפסיפס — עבודת יד של דיירות ודיירי נווה האירוס.` | '' |
| `photo` | image: decorative no, `_alt` `טבעות של אבן לבנה, אפורה ואדומה סביב לב של זכוכית צבעונית`, `_sizes` `(min-width: 1024px) 28rem, 80vw` | (v3-mandala) | |
| `work_cx` / `work_cy` / `work_r` | NUMBER (fractions of the photo: centre x of width, centre y of height, outer radius of width) | `0.5` / `0.5552` / `0.4876` | |
| `circles` | REPEATER: `name` (pair), `desc` (pair, textarea) | 6 items below | |

Removed in v3.2: `glint`, `weight` (the constant 380), the circles' `link` and `ring`, `external_sr` (it served the links), the constant `RING_W` and the rings' SVG, `running`.

| # | name / name_plain | desc / desc_plain |
|---|---|---|
| 1 | `הדייר או הדיירת` / `-` | `ובחירות משלהם בכל יום.` / '' |
| 2 | `משפחה וחברים` / '' | `ביקורים, ועד הורים פעיל וקשר שוטף.` / `-` |
| 3 | `צוות הבית` / '' | `אם בית ורכזות דירה, צוות קבוע.` / `-` |
| 4 | `אנשי ונשות מקצוע` / '' | `עבודה סוציאלית, ריפוי בעיסוק, רפואה ורפואת שיניים.` / `-` |
| 5 | `הקהילה בנס ציונה` / '' | `תעסוקה, פנאי ודיור בקהילה.` / `-` |
| 6 | `פיקוח משרד הרווחה והביטחון החברתי` / `משרד הרווחה בודק שהכול בסדר.` | '' / '' |

Item rules: if `name_plain` is `-`, the whole `li` gets `class="v3-full"` and the texts inside are rendered without mode classes; the name pair uses `tag => 'b', bare => false`; the description pair `tag => 'span', bare => false`.

Markup (target `index.html` `section#support`):
```html
<section class="v3-samekh v3-spread" id="support" aria-labelledby="support-title">
  <div class="nh-container v3-samekh__grid">
    <div class="v3-rule" aria-hidden="true"></div>
    <div class="v3-samekh__intro">{nh_h2}{nh_lead}</div>
    <figure class="v3-samekh__art">
      {NH_Letters::room('samekh', [fit => 'whole', work => [cx, cy, r], photo, sizes, weight => 380, class => 'v3-samekh__letter', alt, decorative])}
      <figcaption>{pair:caption}</figcaption>
    </figure>
    <ol class="v3-circles" role="list">[each circles]<li…><b>…</b><span>…</span></li>[/each]</ol>
  </div>
</section>
```
CSS `samekh.css`: v3.css L273–279, L283–309 (v3.1) without the rings, the glint, the links' rules and the `:has()` ring rules; porting change `.v3-samekh__art { margin: 0; justify-self: center; }` (Elementor resets `figure` margins). The v3.2 sizes are ported with the markup (§15 B4).
JS: none (`samekh.js` deleted in v3.2).
Fixture: `prototype` = photo `v3-mandala`; compare `section#support`: 0 differing lines.

### 4.8 `years` — package crafts

*(v3.2, B2 16.11: research/07 §10.2 item 7.)*

Constants: `ID 'years'`, `STYLE_DEPS ['nh-letters']`, `SIZES '(max-width: 23em) calc(100vw - 2.5rem), 21rem'`. Section class `v3-years v3-spread`, tone `sand`. v3.2: **the ruling line first**. Section control: anchor `story`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `כמעט 70 שנה של בית.` · `''` | `הסיפור שלנו` |
| `intro` | pair, textarea | '' | `נווה האירוס נפתח בשנת 1957.⏎בשנת 2021 נבנה אגף חדש.⏎היום יש לנו שלושה בתים.` |
| `years` | REPEATER: `num` (TEXT), `title` (TEXT), `text` (TEXTAREA), `photo` (image, `_sizes` = `SIZES`), `caption` (TEXT), `shamayim` (SWITCHER), `shamayim_text` (TEXT), `shamayim_photo` (image, decorative yes, `_pos`, `_sizes`) | 3 items below | (no twins: the list is always `v3-full`, `intro_plain` is the summary — §3.5) |

Removed in v3.2: the repeater's `print` (SELECT), the class `v3-year--first` (the large first number is `.v3-year:first-child` in CSS), `running`.

| # | num | title | text | photo | shamayim |
|---|---|---|---|---|---|
| 1 | `1957` | `סנטוריום נס ציונה` | `אבן הפינה מונחת במקום שיהפוך לנווה האירוס.` | (v3-courtyard) · caption `החצר במעון, היום.` | no |
| 2 | `2021` | `אגף חדש, ובו שמיים` | `חדרים חדשים, מרחבים משותפים ותאורת „שמיים” בתקרה — אגף שתוכנן במיוחד לצורכי הדיירים והדיירות.` | none | yes · text `שמיי` · photo (v3-sky-room) · pos `72% 30%` · sizes `(min-width: 1024px) 8rem, 24vw` |
| 3 | `היום` | `שלושה בתים` | `במעון, בבתים בקהילה ובמסגרת לצעירים על הרצף האוטיסטי — יותר מ־100 דיירים ודיירות. יותר מ־25,000 בקרים מאז 1957.` | none | no |

Markup (target `index.html` `section#story`):
```html
<section class="v3-years v3-spread v3-spread--sand" id="story" aria-labelledby="story-title">
  <div class="nh-container">
    <div class="v3-rule" aria-hidden="true"></div>
    {nh_h2}
    {block:intro plain_tag=div}
    <ol class="v3-years__list v3-full" role="list">
      [each years]<li class="v3-year[ v3-year--sky if shamayim]">
        <p class="v3-year__num[ v3-year__num--word if num is not all digits]" aria-hidden="true">{num}</p>
        <h3><span class="nh-sr-only">{num} — </span>{title}</h3>
        <p>{text}</p>
        [if photo]<figure class="v3-print" data-reveal>{NH_Picture::render(photo, [sizes])}<figcaption>{caption}</figcaption></figure>[/if]
        [if shamayim]<div class="v3-shamayim" aria-hidden="true"><span>{shamayim_text}</span>{NH_Letters::room('mem_final', [photo => shamayim_photo, sizes, weight => 300, frame => '300', pos, class => 'v3-shamayim__mem', decorative => true, field => 'sky'])}</div>[/if]
      </li>[/each]
    </ol>
  </div>
</section>
```
The `v3-shamayim` block is a `div` (never inside a `<p>`: the parser would close the paragraph — prototype README).
CSS `years.css`: v3.css L311–333 (v3.1) without the cloud drift (v3.2), its `.v3-year--first` rules as `.v3-year:first-child` until the v3.2 layout (three equal columns, «1957» D-L, the numbers on one baseline) is ported with the markup (§15 B4); from L459 the selector `.v3-shamayim`. Porting change: `.v3-year .v3-print { padding-block-start: 1.25rem; }` (figure margins are reset by Elementor, §10).
JS: none.
Fixture: `prototype` = photos `v3-courtyard`, `v3-sky-room`; compare `section#story`: 0 differing lines.

### 4.9 `voices` — package life

*(v3.2, B2 16.11: research/07 §10.2 item 8. The spread starts like every other — the ruling line, a visible H2 —
and the play button on the 16:9 poster shows its word.)*

Constants: `ID 'voices'`. Section class `v3-voices v3-spread`, tone `night`. Section control: anchor `voices`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading (a visible `v3-h2`) | `מה אומרות המשפחות.` · `citrus` | '' |
| `quote` | pair, textarea (the twin replaces the whole blockquote) | `הבן שלי וחבריו מבלים מחוץ לכותלי המוסד מדי שבוע.` | `אמא של דייר כתבה לנו מכתב.⏎היא כתבה שהבן שלה והחברים שלו יוצאים לבלות כל שבוע.` |
| `cite` | TEXT | `ליליה, אמא של דייר בנווה האירוס` | — |
| `video_id` | TEXT (YouTube id, `^[A-Za-z0-9_-]{11}$`, else no video) | `JiaXto-WmJo` | |
| `video_title` | TEXT | `הורה של דייר מספר על נווה האירוס` | |
| `play_label` | pair (the visible word on the button) | `צפו בסרטון` ⊕ | '' |
| `play_prefix` | TEXT (screen-reader text between the word and the title) | `:` | |
| `poster` | image: decorative yes, `_sizes` `(min-width: 64em) 37.5rem, calc(100vw - 2.5rem)` | (v3-owls, the 16:9 frame) | |
| `consent_text` | pair, textarea | `הסרטון מוטמע מ־YouTube, שעשוי לשמור עוגיות. לטעון אותו?` | `הסרטון נמצא באתר YouTube.⏎YouTube יכול לשמור מידע עליכם.⏎לפתוח את הסרטון?` |
| `load_label` / `cancel_label` | pair / pair | `כן, לטעון את הסרטון` / `לא עכשיו` | '' / '' |
| `caption` | pair | `סרטון: הורה של דייר מספר על נווה האירוס. בתמונה: ינשופים, עבודת יד של דיירות ודיירי נווה האירוס.` | '' |

Changed in v3.2: `title` (was a pair for an `nh-sr-only` H2) is the spread's heading with its tessera; `play_prefix` (was `צפו בסרטון:` before the title) is only the screen-reader text after the visible `play_label`; `running` removed.

Markup (target `index.html` `section#voices`):
```html
<section class="v3-voices v3-spread v3-spread--night" id="voices" aria-labelledby="voices-title">
  <div class="nh-container v3-voices__grid">
    <div class="v3-rule" aria-hidden="true"></div>
    <div class="v3-voices__words">
      {nh_h2}
      <blockquote class="v3-quote[ v3-full if quote_plain]"><p>{quote}</p><footer>— <cite>{cite}</cite></footer></blockquote>
      [if quote_plain]<div class="v3-plain"><p>…</p>…</div>[/if]
    </div>
    [if video_id]<figure class="v3-video-fig">
      <div class="nh-video v3-video" data-video="{video_id}" data-title="{video_title}">
        {NH_Picture::render(poster, [sizes, decorative => true])}
        <button class="nh-video__play" type="button"><span class="nh-video__btn"><span class="nh-video__icon" aria-hidden="true"><svg focusable="false"><use href="#i-play"/></svg></span><span>{pair:play_label}</span></span><span class="nh-sr-only">{play_prefix} {video_title}</span></button>
        <div class="nh-video__consent" hidden>
          {block:consent_text full_tag=p plain_tag=div}
          <div class="nh-btn-row"><button class="nh-btn nh-btn--light" type="button" data-video-load>{pair:load_label}</button><button class="nh-btn nh-btn--ghost" type="button" data-video-cancel>{pair:cancel_label}</button></div>
        </div>
      </div>
      <figcaption class="v3-video__cap">{pair:caption}</figcaption>
    </figure>[/if]
  </div>
</section>
```
Porting change: the prototype's inline `style="justify-content:center"` on `.nh-btn-row` moved to CSS (`.v3-video .nh-btn-row { justify-content: center; }`).
CSS `voices.css`: main.css L216–228 (`.nh-video…`) without the round icon, its `scale` and the shifted 2rem svg; the v3.2 button `.nh-video__btn` (paper, icon + word, `--nh-citrus-100` on hover and focus, 150ms) and the explicit 1.25rem `.nh-video__icon` (an svg without a size grows to 300×150; research/07 §10.2 item 8, §18 item 6); `.v3-video` 16:9, at most 37.5rem, the button at the bottom start; the caption 16px; from L428 the `.nh-video` print rule; the `.nh-btn-row` rule above.
JS `voices.js`: site.js L218–250 in `nh.widget('voices', '[data-video]', …)`, unchanged (it needs `nh.consent.hideBanner`, kept by nh-core.js, research/07 §9).
Fixture: `prototype` = poster `v3-owls`; compare `section#voices`: 0 differing lines. `states`, `no-word`, `no-video` (structure only).

### 4.10 `steps` — package index

*(v3.2, B2 16.11: research/07 §10.2 item 9: no background word «צעד», no staircase.)*

Constants: `ID 'steps'`. Section class `v3-steps v3-spread`. Section control: anchor `start`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `צעד צעד: ארבעה צעדים הביתה.` · `''` | `איך מגיעים לגור אצלנו?` |
| `lead` | pair, textarea | `המחלקה הסוציאלית שלנו מלווה אתכם לאורך כל הדרך.` | `יש ארבעה צעדים.` |
| `steps` | REPEATER: `title` (pair), `text` (pair, textarea) | below | |

Removed in v3.2: `word`, `running`.

| # | title / title_plain | text / text_plain |
|---|---|---|
| 1 | `שיחה ראשונה` / '' | `מתקשרים או משאירים פרטים. נסביר את התהליך ונענה על כל שאלה.` / `מדברים איתנו בטלפון.` |
| 2 | `ביקור והיכרות` / '' | `מגיעים לראות את המקום, להכיר את הצוות ולשאול הכול.` / `באים לבקר ולהכיר.` |
| 3 | `קשר עם לשכת הרווחה` / `הצוות מדבר עם הרווחה` | `הצוות שלנו מדבר עם לשכת הרווחה שמלווה אתכם, ומתאם איתה את הפרטים.` / `הצוות שלנו מדבר עם לשכת הרווחה.` |
| 4 | `תקופת הסתגלות` / `מתחילים לגור אצלנו` | `בהתחלה אנחנו קרובים במיוחד — עד שמרגישים בבית.` / `לאט לאט מרגישים בבית.` |

Markup (target `index.html` `section#start`):
```html
<section class="v3-steps v3-spread" id="start" aria-labelledby="start-title">
  <div class="nh-container">
    <div class="v3-rule" aria-hidden="true"></div>
    {nh_h2}
    {nh_lead plain_tag=p plain_class=v3-lead plain_lines=null}
    <ol class="v3-steps__list" role="list">
      [each steps, n = 1…]<li><h3><span class="v3-steps__n">{n}</span> {pair:title}</h3>{block:text full_tag=p plain_tag=p plain_lines=null}</li>[/each]
    </ol>
  </div>
</section>
```
CSS `steps.css`: v3.css L356–367 (v3.1) without the word, the staircase and the extra bottom padding; the v3.2 sizes (the number D-M in clay) are ported with the markup (§15 B4).
JS: none. Fixture: `default` compare `section#start`: 0 differing lines.

### 4.11 `lamed` — package index

Constants: `ID 'lamed'`, `STYLE_DEPS ['nh-letters']`, `DYNAMIC true`, `WEIGHT 600` *(v3.2, B2: the ל stands still at 600, research/07 §10.2 item 10; the control `weight` is removed)*. Section class `v3-lamed v3-spread`. Section control: anchor `jobs`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `עבודה עם משמעות. מקום ללמוד ולגדול.` · `''` | `רוצים לעבוד אצלנו?` |
| `lead` | pair, textarea | `מחפשים אנשים שרוצים להשפיע ולהיות חלק מבית: צוות קבוע, ואפשרויות ללמוד ולהתפתח מקצועית.` | `מחפשים עובדים ועובדות.⏎יש עבודה במעון ובבתים בעיר.` |
| `roles` | REPEATER: `title` (pair), `meta` (pair), `link` (URL; empty = `NH_Settings::jobs_url( title )`) | below | |
| `button` + `button_link` | pair + URL (empty = `NH_Settings::jobs_url()`) | `לכל המשרות ולהגשת מועמדות` · '' | `לראות את כל העבודות` |

| # | title / title_plain | meta |
|---|---|---|
| 1 | `רכז/ת דירה — דיור לצעירים על הרצף האוטיסטי` / `רכז או רכזת דירה` | `משרה מלאה, משמרות` |
| 2 | `עובד/ת סוציאלי/ת` / `עובד סוציאלי או עובדת סוציאלית` | `50%–70%` |
| 3 | `מרפא/ה בעיסוק` / `מרפא או מרפאה בעיסוק` | `6 שעות שבועיות` |
| 4 | `טבח/ית` / `טבח או טבחית` | `משרה מלאה` |
| 5 | `עובד/ת אחזקה` / `עובד או עובדת אחזקה` | `משרה מלאה` |

If the jobs page is not set in settings, empty links fall back to `#{anchor}` and the widget adds `nh_admin_note('לא הוגדר עמוד המשרות (הגדרות ← נווה האירוס)')`.

Markup (target `index.html` `section#jobs`):
```html
<section class="v3-lamed v3-spread" id="jobs" aria-labelledby="jobs-title">
  <div class="nh-container v3-lamed__grid">
    <div class="v3-rule" aria-hidden="true"></div>
    <div class="v3-lamed__letter" aria-hidden="true">{NH_Letters::svg('lamed', 600)}</div>
    <div class="v3-lamed__text">
      {nh_h2}
      {nh_lead}
      <ul class="v3-roles" role="list">[each roles]<li><a href="{url}"><b>{pair:title}</b>{pair:meta tag=span class=v3-roles__meta bare=false}{icon:arrow-fwd}</a></li>[/each]</ul>
      [if button]<p><a class="nh-btn nh-btn--primary" href="{url}">{pair:button}{icon:arrow-fwd nh-btn__icon nh-btn__icon--fwd}</a></p>[/if]
    </div>
  </div>
</section>
```
Note `<b>` contains the `v3-full`/`v3-plain` spans (unlike samekh's two `<b>`), as in the prototype.
CSS `lamed.css`: v3.css L130, L369–371, L380–386; from L459 the selector `.v3-lamed__letter`; L620–622. (`.v3-roles` is in base.)
JS: none.
Fixture: `default` (settings `jobs_page` = 0) compare `section#jobs`. Expected differences: role and button hrefs (prototype `design-system.html#form`).

### 4.12 `door` — package door (visit form front end)

Constants: `ID 'door'`, `STYLE_DEPS ['nh-forms']`, `SCRIPT_DEPS ['nh-forms']`, `DYNAMIC true`. Section class `v3-door v3-spread`, tone `iris`. Section controls: `door` / `בואו להכיר`. One widget serves both prototype doors: the home page (short form) is the default; the house page (full form) is the same widget with other settings (fixture variant `page`).

| control | type | default (home) | `_plain` | house page (variant `page`) |
|---|---|---|---|---|
| `title` + dot | heading | `הדלת פתוחה. בואו להכיר.` · `citrus` | `רוצים לבוא לבקר?` | `בואו לראות בעיניים.` · `citrus` / same plain |
| `lead` | pair, textarea | `הדרך הטובה ביותר להכיר בית היא לבקר בו. נשמח לארח אתכם למפגש היכרות.` | `אפשר להתקשר אלינו.⏎אפשר גם למלא את הטופס.` | `השאירו פרטים ונחזור אליכם לתיאום מפגש היכרות.` / same plain |
| `form_title` | pair (H3; its full text is also the form's `aria-label`) | `בקשה לביקור` | '' | |
| `success_title` | pair | `תודה! הבקשה התקבלה.` | '' | |
| `success_text` | pair, textarea | `נחזור אליכם לתיאום.` | '' | `נחזור אליכם בהקדם לתיאום הביקור.` |
| `required_note` | pair, textarea (inline HTML) | `שדות המסומנים ב־<span aria-hidden="true">*</span><span class="nh-sr-only">כוכבית</span> הם שדות חובה.` | `צריך למלא את השדות עם כוכבית <span aria-hidden="true">*</span>.` | |
| `label_name` / `label_phone` | pair / pair | `שם מלא` / `טלפון` | '' | |
| `show_email` + `label_email` | SWITCHER + pair | `''` (off) · `דוא״ל` | '' | on |
| `show_who` + `label_who` + `who_options` | SWITCHER + pair + lines | on · `מי ממלא את הטופס?` · `בחירה…⏎אני, בשביל עצמי⏎הורה או קרוב משפחה⏎אפוטרופוס או אפוטרופסית⏎תומך או תומכת בקבלת החלטות⏎עובד סוציאלי או עובדת סוציאלית⏎אחר` | '' | on |
| `show_program` + `label_program` + `program_options` | SWITCHER + pair + lines | on · `איזה בית מעניין אתכם?` · `עוד לא יודעים⏎מעון ראשי — בית לחיים⏎דיור בקהילה⏎דיור לצעירים על הרצף האוטיסטי` | '' | off |
| `show_message` + `label_message` + `hint_message` | SWITCHER + pair + pair | `''` · `משהו שחשוב לנו לדעת?` · `אין צורך לפרט כאן מידע רפואי. נדבר על הכול בשיחה.` | '' · `לא צריך לכתוב כאן מידע רפואי. נדבר על זה בשיחה.` | on |
| `consent` | pair, textarea (inline HTML; `href="#privacy"` is resolved) | `קראתי את <a href="#privacy">מדיניות הפרטיות</a> ואני מסכים/ה שתיצרו איתי קשר לגבי הביקור.` | `אני מסכים או מסכימה שתתקשרו אליי. <a href="#privacy">מה עושים עם הפרטים שלי?</a>` | |
| `consent_error_label` | TEXT (`data-error-label`) | `אישור מדיניות הפרטיות` | | |
| `submit` | pair | `שליחת הבקשה` | '' | |
| `show_contact` + `contact_label` + `call_label` | SWITCHER + pair + TEXT | on · `או בטלפון` · `התקשרו:` | '' | |
| section | | `door` / `בואו להכיר` | | `visit` / `בואו לראות` |

The first line of an options list is the empty-value option (`<option value="">…</option>`); other lines render as `<option>{text}</option>` (value = text, as in the prototype). The privacy notice under the consent comes from settings (`NH_Forms::privacy_note('visit')`, §7), so both doors always show the same notice.

Field layout (normative): row 1 = `name` + `phone` in `<div class="nh-form__row nh-form__row--2">`; then the enabled fields among `email`, `who`, `program`, in this order, two per `nh-form__row--2` row; a single leftover field is a bare `.nh-field`; then `message` (full width) if enabled. This reproduces both prototype forms.

Markup (target `index.html` `section#door`; variant `page` → `program-main-home.html` `section#visit`):
```html
<section class="v3-door v3-spread v3-spread--iris" id="door" aria-labelledby="door-title">
  <div class="nh-container v3-door__grid">
    <div class="v3-rule" aria-hidden="true"></div>
    <div class="v3-door__top">{nh_h2}{nh_lead}</div>
    <div class="v3-door__way">
      {NH_Letters::doorway()}
      <div class="v3-door__in">
        <div class="v3-door__form">
          <h3>{pair:form_title}</h3>
          {NH_Forms::recipient_note('visit')}
          {NH_Forms::success(success_title, success_text, state)}
          <form class="nh-form" data-validate action="{NH_Forms::action_url()}" method="post" aria-label="{form_title}" data-nh-form="visit">
            {NH_Forms::summary(state, labels, prefix)}
            {NH_Forms::required_note(required_note, required_note_plain)}
            {rows: NH_Forms::field('visit', key, [id prefix, label pair, options, hint], state)}
            {NH_Forms::honeypot(prefix)}
            {NH_Forms::check('visit', 'consent', [label pair, error_label], state)}
            {NH_Forms::privacy_note('visit')}
            {NH_Forms::hidden_fields('visit', $this->get_id(), get_permalink() . '#' . anchor)}
            <div><button class="nh-btn nh-btn--primary" type="submit">{pair:submit}{icon:arrow-fwd nh-btn__icon nh-btn__icon--fwd}</button></div>
          </form>
        </div>
        [if show_contact]<div class="v3-door__contact">
          <p class="v3-door__label">{pair:contact_label}</p>
          <p class="v3-door__phone"><a href="{tel}" aria-label="{call_label} {display}"><span class="nh-ltr">{display}</span></a></p>
          <p class="v3-door__addr">{address}[ · <a href="mailto:{email}"><span class="nh-ltr">{email}</span></a> — only if the public e-mail is set]</p>
        </div>[/if]
      </div>
    </div>
  </div>
</section>
```
The exact field, summary, alert and note markup is defined by `NH_Forms` (§6.3) — the widget only passes texts and switches.

CSS `door.css`: v3.css L388–433; from L484 the selectors `.v3-door__bar, .v3-door__leg` (`@media (forced-colors: active) { … background: CanvasText; }`).
JS: none *(v3.2, B2 16.11: `door.js` deleted — the ד is drawn open, `--open: 1`; door.css lost its `@property --open` and the lintel's transition, research/07 §10.2 item 11)*. Validation and sending are in `nh-forms.js`.
Fixture: `default` compare `index.html` `section#door`; variant `page` (settings from the right-hand column) compare `program-main-home.html` `section#visit`. Expected differences: form `action`, hidden inputs, field ids (`c-*`/`v-*` → `door-*`/`visit-*`), the success text without the prototype's "(באב־הטיפוס הטופס אינו נשלח.)", the contact e-mail link (setting empty in tests).

### 4.13 `glance` — package residence

Constants: `ID 'glance'`. Section class `v3-glance v3-spread`. Section controls: `glance` / `במבט אחד`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `בית עם צוות שמכיר אותך.` · `''` | `צוות שמכיר אותך` |
| `lead` | pair, textarea | `דירות המעון מחולקות לשתי יחידות, ולכל יחידה צוות קבוע משלה — כך כל דייר ודיירת יודעים למי לפנות, והצוות מכיר כל אחד ואחת לעומק.` | `המעון מחולק לשני חלקים.⏎לכל חלק יש צוות משלו.⏎הצוות מכיר כל דייר וכל דיירת.` |
| `stats` | REPEATER: `num` (TEXT), `label` (TEXT) | `2` · `יחידות, לכל אחת צוות קבוע`; `4` · `תפקידים בכל צוות: ניהול, עבודה סוציאלית, אם בית, ריכוז דירות`; `100+` · `דיירים ודיירות בנווה האירוס`; `1957` · `שנת הקמה` | (list is `v3-full`) |
| `facts` | REPEATER: `term`, `desc` (TEXT) | `למי` · `בוגרים ובוגרות עם מוגבלות שכלית התפתחותית, כולל אבחנה כפולה והתנהגות מאתגרת`; `איפה` · `בנס ציונה`; `פיקוח` · `משרד הרווחה והביטחון החברתי` | (list is `v3-full`) |
| `summary` | pair, textarea (rendered after the lists) | '' | `בנווה האירוס גרים יותר מ־100 דיירים ודיירות.⏎המעון נפתח בשנת 1957.⏎משרד הרווחה בודק שהכול בסדר.` |

Markup (target `program-main-home.html` `section#glance`):
```html
<section class="v3-glance v3-spread" id="glance" aria-labelledby="glance-title">
  <div class="nh-container">
    <div class="v3-rule" aria-hidden="true"></div>
    {nh_h2}{nh_lead}
    <ul class="v3-stats v3-full" role="list">[each stats]<li><b>{num — wrapped in <span class="nh-ltr"> if it has a character other than digits, spaces, "," or "."}</b><span>{label}</span></li>[/each]</ul>
    <dl class="v3-facts v3-full">[each facts]<div><dt>{term}</dt><dd>{desc}</dd></div>[/each]</dl>
    {block:summary plain_class=v3-plain--after}
  </div>
</section>
```
CSS `glance.css`: v3.css L502–514. JS: none. Fixture: `default` compare `section#glance`.

### 4.14 `gallery` — package residence

*(v3.2, B2 16.11: research/07 §10.3 item 4, §4.4 item 20, §13. An even grid of six 4:3 prints of one size with
their captions visible — no mosaic, no zoom, no lightbox; weak photos are never enlarged.)*

Constants: `ID 'gallery'`, `DYNAMIC false` (nothing is queued for the footer any more), `SIZES '(min-width: 64em) 27rem, (min-width: 40em) 50vw, calc(100vw - 2.5rem)'`. Title `גלריה · תמונות עם כיתוב`. Section class `v3-inside v3-spread`, tone `sand`. Section control: anchor `inside`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `האגף החדש.` · `''` | '' |
| `lead` | pair, textarea (plain block has class `v3-lead`) | `את האגף החדש תכננו אדריכליות במיוחד בשביל הדיירים והדיירות: קו עיצובי נעים ורגוע, ותאורת „שמיים” בתקרה.` | `תמונות מהאגף החדש.` |
| `images` | REPEATER: `image` (image, not decorative), `caption` (pair, visible under the print) | 6 items: `פינת האוכל המשותפת.` (v3-dining) · `תקרת „שמיים” — לוחות תאורה עם עננים.` (v3-sky-ceiling) · `חדר מגורים לשניים.` (v3-bedroom) · `הסלון המשותף.` (v3-living) · `מסדרון האגף החדש, עם תאורת „שמיים”.` (v3-sky-corridor) · `פינת אוכל וסלון באגף החדש.` ⊕ (v3-lounge) | '' each |

Removed in v3.2: `zoom_label`, `lb_label`, `lb_count`, `lb_close`, `lb_prev`, `lb_next`, the constant `TEXTS`, the call `NH_Site::footer_once('lightbox', …)`, the buttons' `data-full` / `data-srcset` / `data-caption*`, the sizes by position, `running`; the lead's «לחצו על תמונה…» sentences.

Markup (target `program-main-home.html` `section#inside`):
```html
<section class="v3-inside v3-spread v3-spread--sand" id="inside" aria-labelledby="inside-title">
  <div class="nh-container">
    <div class="v3-rule" aria-hidden="true"></div>
    {nh_h2}
    {nh_lead plain_class=v3-lead}
    <ul class="v3-gallery" role="list">
      [each images with an image]<li><figure class="v3-print" data-reveal>{NH_Picture::render(id, [sizes => SIZES])}<figcaption>{pair:caption}</figcaption></figure></li>[/each]
    </ul>
  </div>
</section>
```
CSS `gallery.css`: the prototype's v3.2 «Gallery» block: `.v3-gallery` grid of 1 / 2 (from 40em) / 3 (from 64em) columns, `.v3-gallery .v3-print { max-inline-size: none; }`. (The print — 4:3, numbered caption, the soft appearance — is in base.css.)
JS: none (`gallery.js` deleted in v3.2).
Fixture: `prototype` = the six photos; compare `section#inside`: 0 differing lines. `states` (structure only).

### 4.15 `features` — package residence

Constants: `ID 'features'`. Section class `v3-features v3-spread`. Section controls: `features` / `מה יש בבית`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `כל מה שצריך, קרוב לבית.` · `''` | `מה יש במעון?` |
| `intro` | pair, textarea (before the list) | '' | `חדר שקט ונעים להירגע.⏎מרפאה ומרפאת שיניים.⏎חוגים ופעילויות.` |
| `features` | REPEATER: `title`, `text` (TEXT/TEXTAREA; list is `v3-full`) | `חדר סנוזלן` · `סביבה רב־חושית מבוקרת, להרפיה ולוויסות רגשי. הוקם בסיוע המוסד לביטוח לאומי.`; `מרפאה ומרפאת שיניים` · `צוות רפואי במקום, ומרפאת שיניים מתקדמת.`; `חדרי טיפול` · `חדשים, מרווחים ומזמינים.`; `תעסוקה ופנאי` · `סדנאות, חוגים ופעילויות לאורך השבוע — בתוך המעון ומחוצה לו.`; `מועדון` · `מקום מפגש לרווחת הדיירים והדיירות.`; `קידום אישי` · `תוכנית אישית לכל דייר ודיירת, לפי הרצונות והיכולות שלהם — כולל אפשרות לעבור לדיור בקהילה.` | |
| `quote` | pair, textarea (twin rendered after, class `v3-plain--after`) | `הצוות הרפואי והמטפל נגישים בכל שעה ולכל שאלה […]. ישנה הקשבה מלאה והתייחסות כנה ואמיתית לצרכים.` | `אמא של דייר כתבה לנו מכתב.⏎היא כתבה שהצוות עונה על כל שאלה.⏎היא כתבה שהצוות מקשיב באמת.` |
| `cite` | TEXT | `ליליה, אמא של דייר בנווה האירוס` | |

Markup (target `program-main-home.html` `section#features`):
```html
<section class="v3-features v3-spread" id="features" aria-labelledby="features-title">
  <div class="nh-container">
    <div class="v3-rule" aria-hidden="true"></div>
    {nh_h2}
    {block:intro}
    <ul class="v3-feature-list v3-full" role="list">[each features]<li><h3>{title}</h3><p>{text}</p></li>[/each]</ul>
    <blockquote class="v3-quote v3-quote--page[ v3-full if quote_plain]"><p>{quote}</p><footer>— <cite>{cite}</cite></footer></blockquote>
    [if quote_plain]<div class="v3-plain v3-plain--after"><p>…</p>…</div>[/if]
  </div>
</section>
```
CSS `features.css`: v3.css L351–354, L524–528. (`.v3-quote` is in base.) JS: none. Fixture: `default` compare `section#features`.

### 4.16 `stories` — package residence

Constants: `ID 'stories'`. Section class `v3-stories v3-spread`, tone `night`. Section controls: `stories` / `סיפורים`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `צעד צעד: שלושה סיפורים.` · `citrus` | `שלושה סיפורים` |
| `lead` | pair, textarea (plain block class `v3-lead`) | `שלושה סיפורים מהשנים האחרונות. הפרטים המזהים הושמטו.` | `שלושה סיפורים על דיירים ודיירות.⏎לא כתבנו את השמות שלהם.` |
| `stories` | REPEATER: `title` (pair), `text` (pair, textarea) | below | |

| # | title | text / text_plain |
|---|---|---|
| 1 | `מהמעון לבית בקהילה` | `אחרי עבודה עקבית ומשותפת, דייר עבר מהמעון לווילה בקהילה — שלוחה של נווה האירוס.` / `דייר גר במעון הרבה שנים.⏎היום הוא גר בבית של נווה האירוס בעיר.⏎הצוות עזר לו לעבור.` |
| 2 | `יציאה לפעילויות` | `דיירת שהגיעה עם קשיים רבים בשגרה היומיומית יוצאת היום לפעילויות, בשיתוף פעולה עם הצוות.` / `לדיירת היה קשה בכל יום.⏎היום היא יוצאת לפעילויות.⏎היא והצוות עשו את זה יחד.` |
| 3 | `מעבר לשער` | `דייר שחשש במשך שנים לצאת משטח המעון יוצא היום לטיולי יום, בליווי צמוד של רכזת הדירה.` / `דייר פחד לצאת מהמעון במשך שנים.⏎היום הוא יוצא לטיולים.⏎רכזת הדירה הולכת איתו.` |

Markup: `<section class="v3-stories v3-spread v3-spread--night" …>` › `nh-container` › rule, `{nh_h2}`, `{nh_lead plain_class=v3-lead}`, `<ol class="v3-stories__list" role="list">[each]<li><span class="v3-stories__n" aria-hidden="true">{n}</span><h3>{pair:title}</h3>{block:text full_tag=p plain_tag=div}</li>[/each]</ol>` (target `program-main-home.html` `section#stories`).
CSS `stories.css`: v3.css L529–534. JS: none. Fixture: `default` compare `section#stories`.

### 4.17 `related` — package index

*(v3.2, B2 16.11: research/07 §10.3 item 8, §3 item 21: a row leads only to something that exists elsewhere.)*

Constants: `ID 'related'`. Section class `v3-related v3-spread`. Section control: anchor `related`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `להמשך קריאה.` · `''` | `עוד דפים` |
| `external_sr` | TEXT | `(נפתח באתר חיצוני)` | |
| `links` | REPEATER: `title` (pair), `meta` (pair), `link` (URL) | `שלושת הבתים` · `מי גר בכל בית ולמי הוא מתאים` ⊕ · `home_url('/#homes')`; `ידיים שיוצרות` · `פסיפס, תכשיטים וקרמיקה — עבודות יד של דיירות ודיירי נווה האירוס` · `home_url('/#made')` | title '' · meta `-` |

Removed in v3.2: the rows «אבחנה כפולה» (a link to itself), «גלריית יצירות למכירה» and «דיור בקהילה» (pages that do not exist yet; they come back first when they do, §16 item 8); `running`.

A row is printed only with a link to another page or to a section of another page: a row without a link, with a bare fragment (`#…`, a section of this page, or a link token whose page is not set) or with a link to the page it is on (`url_to_postid()` of the link without its fragment = `get_the_ID()`) is left out, and editors see `nh_admin_note( 'השורה „…” לא מוצגת: אין לה קישור, או שהיא מקשרת לעמוד הזה עצמו.' )` after the list.

Markup (target `program-main-home.html` `section#related`): section › container › rule, `{nh_h2}`, `<ul class="v3-roles" role="list">[each]<li><a href="{link}"><b>{pair:title}</b>{pair:meta tag=span class=v3-roles__meta bare=false}{icon:arrow-fwd}</a></li>[/each]</ul>`.
CSS `related.css`: v3.css L550–551 (`.v3-related .v3-roles__meta { white-space: normal; }`); the arrows of `.v3-roles` never move (base.css). JS: none.
Fixture: `default` on the house page (queried post 11) compare `section#related`: 0 differing lines; `edge`, `home` (structure only). `build_pages.py` writes the links as `@page:home#homes`, `@page:home#made`.

### 4.18 `masthead` — package chrome (Theme Builder header)

*(v3.2, B2 16.11: research/07 §5, §6, §7.2, §10.1 items 1–2, §13. Fixed and always the same: the mark (the logo's
emblem and the name as live text), at most five links, «בשפה פשוטה», the phone, «תיאום ביקור» / «תפריט»; no
running head, no progress line, no display panel, nothing changes while the page scrolls. The phone menu: a
native dialog with a visible title and «סגירה», the links, the visit button, the call line and two switches.)*

Constants: `ID 'masthead'`, `DYNAMIC true`, `MAX_ITEMS 5`. Not a spread: no section controls. Placed alone in an Elementor Pro header template (condition: entire site).

| control | type | default | `_plain` |
|---|---|---|---|
| `html_tag` | SELECT `div\|header` | `div` (D3) | |
| `name` | pair (live text beside the emblem; split into two lines at its last space) | `מעון נווה האירוס` | '' |
| `home_sr` | TEXT (screen-reader suffix of the home link) | ` — לדף הבית` | |
| `logo` | MEDIA (the emblem: decorative, `alt=""`, width/height from the attachment; without one only the name shows) | — (staging: `@media:logo-emblem`) | |
| `plain_label` | pair | `בשפה פשוטה` | '' |
| `phone_label` | TEXT (aria-label prefix) | `טלפון:` | |
| `cta_text` + `cta_link` | pair + URL (empty = `NH_Settings::visit_url()`) | `תיאום ביקור` · '' | `לקבוע ביקור` |
| `menu_label` | TEXT (the visible word on the menu button and the menu's title) | `תפריט` | |
| `menu_close` | TEXT (visible) | `סגירה` | |
| `menu_phone_label` | pair | `התקשרו:` | '' |
| `menu_toggles_label` | TEXT (the switches' group name) | `הגדרות תצוגה` | |
| `toggle_plain` / `toggle_calm` | pair ×2 | `בשפה פשוטה` / `מצב רגוע` | '' |
| `state_on` / `state_off` | TEXT | `פועל` / `כבוי` ⊕ | |
| `nav_label` | TEXT (aria-label of both navs) | `ראשי` | |
| `nav` | REPEATER: `label` (pair), `link` (URL), `current_ids` (TEXT, comma-separated post IDs) | `הבתים שלנו` → `home_url('/#homes')`; `החיים אצלנו` → `/#day`; `מי אנחנו` → `/#story`; `דרושים` → `/#jobs`; `יצירת קשר` → `/#door` (research/07 §5.1) | '' |

Removed in v3.2: `wordmark_small`, `wordmark` (→ `name` + the emblem), `display_label`, `display_title`, `display_note`, `toggle_motion`.

Markup = `partials/header.html` (target: built `index.html` `header.nh-header` and `dialog#nh-menu`):
```html
<{html_tag} class="nh-header v3-mast">
  <div class="nh-container nh-header__inner">
    <a class="nh-logo" href="{home_url('/')}">[if logo]<img class="nh-logo__mark" src="{src}" width="{w}" height="{h}" alt="">[/if]<span class="nh-logo__name"><span>מעון נווה</span> <span>האירוס</span></span><span class="nh-sr-only">{home_sr}</span></a>
    <nav class="nh-nav" aria-label="{nav_label}"><ul role="list">[each nav, at most 5]<li><a href="{link}"[ aria-current="{page|true}"]>{pair:label}</a></li>[/each]</ul></nav>
    <div class="nh-header__actions">
      <button class="v3-plainbtn" type="button" data-plain-toggle aria-pressed="false">{icon:speech}<span>{pair:plain_label}</span></button>
      [if phone]<a class="nh-phone" href="{tel}" aria-label="{phone_label} {display}">{icon:phone}<span class="nh-phone__num nh-ltr">{display}</span></a>[/if]
      [if cta]<a class="nh-btn nh-btn--primary nh-btn--sm v3-mast__cta" href="{cta}" data-visit-cta>{pair:cta_text}</a>[/if]
      <button class="nh-menu-btn" type="button" data-menu-open aria-haspopup="dialog" aria-expanded="false" aria-controls="nh-menu">{icon:menu}<span>{menu_label}</span></button>
    </div>
  </div>
</{html_tag}>
<dialog class="nh-menu" id="nh-menu" aria-labelledby="nh-menu-title">
  <div class="nh-menu__inner">
    <div class="nh-menu__top"><p class="nh-menu__title" id="nh-menu-title">{menu_label}</p><button class="nh-menu__close" type="button" data-menu-close>{icon:close}<span>{menu_close}</span></button></div>
    <div class="nh-menu__body">
      <nav aria-label="{nav_label}">{the same list}</nav>
      <div class="nh-menu__meta">
        [if cta]<a class="nh-btn nh-btn--primary nh-menu__cta" href="{cta}" data-visit-cta>{pair:cta_text}</a>[/if]
        [if phone]<p class="nh-menu__call"><a href="{tel}">{pair:menu_phone_label} <span class="nh-ltr">{display}</span></a></p>[/if]
        <div class="v3-switches" role="group" aria-label="{menu_toggles_label}">{NH_Html::display_toggles([plain, calm], [state_on, state_off])}</div>
        <p class="nh-sr-only" role="status" aria-live="polite" data-announce-menu></p>
      </div>
    </div>
  </div>
</dialog>
```
- `aria-current` comes from `NH_Html::current_state( url, current_ids )`: `page` only for a link to this very page, `true` for the section the page belongs to (research/07 §5.3) — on the house page «הבתים שלנו» (its `current_ids` hold the house page). A sixth link and beyond are not printed; editors see `nh_admin_note( 'בתפריט יותר מחמישה פריטים: מוצגים חמשת הראשונים' )` after the dialog.
- The name: `name` split at its last space into two `span`s (the compact masthead shows them as two lines); with a plain twin, one `.nh-logo__name.v3-full` and one `.v3-plain`. Without any name the link keeps the site's name in its screen-reader text. `menu_label` / `menu_close` fall back to `תפריט` / `סגירה` when emptied (a button must have a name).
- The skip link (the child theme), the cookie panel and the global live region (the site module) are not printed here.

CSS `masthead.css`: the prototype's «v3.2 masthead» and «Phone menu» blocks (main.css), then v3.css's 1.4.12 wrap and the `max-height: 30em` rule (`.v3-mast { position: absolute; }`), the masthead's selectors of the forced-colours and print groups, `.nh-menu .v3-switches`, `html:has(.v3-mast)` scroll padding, the admin-bar offset. Porting change: the emblem's selector is `.nh-logo .nh-logo__mark` (0,2,0), to beat Elementor's `.elementor img { height: auto }`. Breakpoints: the full menu from 80em, the phone number from 90em, the compact mark below 40em, no emblem below 22.5em.
JS `masthead.js`: the menu only (site.js «1. Phone menu»): «תפריט» opens it, Esc and «סגירה» close it and focus returns to «תפריט»; a link of the menu or its visit button sets `menu.dataset.nav`, closes it, and the close handler then leaves focus to nh-core.js (the target's heading, research/07 §5.4–5.5).
Fixture: `default` compare `index.html` `header.nh-header`, `dialog#nh-menu` (expected: the root tag `div`, no emblem chosen); `prototype` (root `header`, the emblem) and `house` (queried post 11, `current_ids` 11 on «הבתים שלנו») — 0 differing lines; `page`, `plain`, `six`, `sparse`, `cta-link` (structure only).

### 4.19 `colophon` — removed in v3.2

*(B2 16.11: research/07 §10.1 item 3, §12.6, §13.)* The widget, its `colophon.css` and its fixture are deleted. The footer is a Theme Builder template of Elementor's own widgets — Image (the emblem), Heading, Text Editor in inner containers, plus the `display` widget (§4.21) for «מצב רגוע» — written by `scripts/build_pages.py` (`templates/pages/footer.json`), so the footer with its navigation, contacts and הצהרת נגישות keeps working when the plugin is off. Its texts, colours and type are set in those widgets; the child theme underlines its links and keeps its rows at 44px.

### 4.20 `jobs-form` — package door

Constants: `ID 'jobs-form'`, `STYLE_DEPS ['nh-forms']`, `SCRIPT_DEPS ['nh-forms']`, `DYNAMIC true`. Section class `v3-jobsform v3-spread` (D6). Section controls: `form` / `דרושים`.

| control | type | default | `_plain` |
|---|---|---|---|
| `title` + dot | heading | `הגשת מועמדות` · `''` | '' |
| `lead` | pair, textarea | the ל spread's lead: `מחפשים אנשים שרוצים להשפיע ולהיות חלק מבית: צוות קבוע, ואפשרויות ללמוד ולהתפתח מקצועית.` | `מחפשים עובדים ועובדות.⏎יש עבודה במעון ובבתים בעיר.` |
| `success_title` / `success_text` | pair / pair | `תודה! המועמדות התקבלה.` / `נעבור על הפרטים ונחזור אליך.` | '' |
| `required_note` | pair (inline HTML) | `שדות המסומנים ב־<span aria-hidden="true">*</span><span class="nh-sr-only">כוכבית</span> הם שדות חובה.` | '' |
| `label_role` + `role_options` | pair + lines | `המשרה` · `בחירת משרה…⏎רכז/ת דירה — דיור לצעירים על הרצף האוטיסטי⏎עובד/ת סוציאלי/ת⏎מרפא/ה בעיסוק⏎טבח/ית⏎עובד/ת אחזקה⏎פנייה כללית` (D6: role 1 follows the ל spread, the design-system sample said `רכז/ת דירה — דיור בקהילה, אוטיזם`) | '' |
| `label_name` / `label_phone` / `label_email` | pair ×3 | `שם מלא` / `טלפון` / `דוא״ל` | '' |
| `label_cv` + `hint_cv` | pair + pair | `קורות חיים` · `PDF, DOC או DOCX, עד 5MB.` | '' |
| `label_message` | pair | `כמה מילים עליך` | '' |
| `consent` + `consent_error_label` | pair (inline HTML) + TEXT | `קראתי את <a href="#privacy">מדיניות הפרטיות</a> ואני מסכים/ה לשימוש בפרטים לצורך בדיקת המועמדות למשרה זו.` · `אישור מדיניות הפרטיות` ⊕ (the sample has no error label) | '' |
| `keep` | pair | `אפשר לשמור את הפרטים שלי למשרות נוספות בעתיד (לא חובה).` | '' |
| `submit` | pair | `שליחת המועמדות` | '' |

Markup (ds L291–338 with v3 chrome):
```html
<section class="v3-jobsform v3-spread" id="form" aria-labelledby="form-title">
  <div class="nh-container">
    <div class="v3-rule" aria-hidden="true"></div>
    <div class="nh-jobs__grid">
      <div class="v3-jobsform__intro">{nh_h2}{nh_lead}</div>
      <div>
        {NH_Forms::recipient_note('jobs')}
        {NH_Forms::success(…)}
        <form class="nh-form" data-validate action="{NH_Forms::action_url()}" method="post" enctype="multipart/form-data" aria-labelledby="form-title" data-nh-form="jobs">
          {summary}{required_note}
          {field role (select, required, preselected from $_GET['role'] when it equals an option)}
          <div class="nh-form__row nh-form__row--2">{field name}{field phone}</div>
          {field email (required)}{field cv}{field message}
          {honeypot}{check consent}{check keep}{privacy_note('jobs')}{hidden_fields('jobs', …)}
          <div><button class="nh-btn nh-btn--primary" type="submit">{pair:submit}{icon:arrow-fwd nh-btn__icon nh-btn__icon--fwd}</button></div>
        </form>
      </div>
    </div>
  </div>
</section>
```
CSS `jobs-form.css`: main.css L297–299 (`.nh-jobs__grid`), plus `.v3-jobsform .nh-jobs__grid { align-items: start; }` (new, small).
JS: none (`nh-forms.js`).
Fixture: `default` (no compare target: structure checked by run.php); variant `role` with `$_GET['role']` set to role 2 → that option is `selected`.

### 4.21 `display` — package chrome (v3.2, B2 16.11)

*(research/07 §12.6, §13.)* The display switches as a widget of their own, for the footer of Elementor's widgets.

Constants: `ID 'display'`. Title `מתגי תצוגה`, icon `eicon-toggle`. No section controls (not a spread). Static (no settings or request read).

| control | type | default | `_plain` |
|---|---|---|---|
| `group_label` | TEXT (the group's aria-label) | `הגדרות תצוגה` | |
| `show_plain` | SWITCHER | `''` (off: the masthead has «בשפה פשוטה») | |
| `label_plain` | pair (condition `show_plain`) | `בשפה פשוטה` | '' |
| `show_calm` | SWITCHER | `yes` | |
| `label_calm` | pair (condition `show_calm`) | `מצב רגוע` | '' |
| `state_on` / `state_off` | TEXT | `פועל` / `כבוי` | |
| `tone` | SELECT `light` / `dark` | `dark` | |

Markup (the prototype's footer group): `<div class="v3-switches[ nh-on-dark]" role="group" aria-label="{group_label}">{NH_Html::display_toggles( [ the switches that are on ], [ state_on, state_off ] )}</div>`; nothing when both switches are off.
CSS `display.css`: `.v3-switches { display: grid; gap: .25rem; max-inline-size: 20rem; }` (the switch itself is in base.css). JS: none (nh-core.js handles the switches by delegation and keeps every switch of the page in step).
Fixture: `default` (as the footer uses it); `prototype` compare `index.html` `footer.nh-footer .v3-switches` (expected: `nh-on-dark` on the group itself); `both`, `none` (`empty`).

---

## 5. Site module (`NH_Site`, `NH_Compat`, `nh-core.js`)

### 5.1 Hooks

| hook | priority | what |
|---|---|---|
| `wp_head` | 1 | `NH_Site::head()`: `<meta name="color-scheme" content="light">`, `<meta name="theme-color" content="#F7F1E7">`, the two font preloads, the cover image preload, the pre-paint script — in this order, before WordPress prints styles (8) and scripts (9) |
| `body_class` | 10 | add `v3` |
| `wp_body_open` | 5 | `NH_Site::body_open()`: sprite, skip link, cookie banner (D1). Not in the Elementor preview: the banner |
| `elementor/page_templates/header-footer/before_content` | 10 | `NH_Site::main_open()`: `<main id="main" class="nh-main" tabindex="-1">` (D2) |
| `elementor/page_templates/header-footer/after_content` | 10 | `NH_Site::main_close()`: `</main>` |
| `wp_footer` | 5 | `NH_Site::footer()`: fallback sprite + banner if `wp_body_open` did not run; visit bar (if enabled, not in preview); global live region; queued `footer_once()` parts (lightbox) |
| `wp_enqueue_scripts` | 20 | `NH_Assets` (§3.2) |
| `elementor/documents/register_controls` | 10 | page setting `nh_calm_default` (SWITCHER `מצב רגוע כברירת מחדל בעמוד הזה`, section `נווה האירוס`, tab settings) on page/post documents |
| `template_redirect` | 10 | when `$_GET['nh_status']` is present: `nocache_headers()` and `define( 'DONOTCACHEPAGE', true )` |
| `template_include` | `PHP_INT_MAX` | remember the final template file (decides the skip-link target, §5.3); returns it unchanged |
| `hello_elementor_enqueue_style`, `hello_elementor_enqueue_theme_style`, `hello_elementor_enable_skip_link` | 20 | `__return_false` (`NH_Compat`, D4) |
| `hello_elementor_page_title` | 20 | `__return_false` on pages built with Elementor |
| `elementor/frontend/print_google_fonts` | 10 | `__return_false` |
| `image_editor_output_format` | 10 | if setting `webp_subsizes`: map `image/jpeg` and `image/png` to `image/webp` (WordPress falls back by itself when the server cannot write WebP) |

### 5.2 Head

Font preloads (prototype head L7–8): `<link rel="preload" href="{NH_URL}assets/fonts/frank-ruhl-libre-core-var.woff2" as="font" type="font/woff2" crossorigin>` and the same for `assistant-core-var.woff2`. No `?ver=` (the URL must equal the one in `tokens.css`).

Cover preload (`cover_preload()`): on singular pages, read `_elementor_data` of the queried post, walk the element tree depth-first, take the first element with `widgetType` `nh-cover` or `nh-page-cover`, its `settings.photo.id` and `settings.photo_sizes` (or the widget's default sizes); print `NH_Picture::preload( $id, $sizes )` = `<link rel="preload" as="image"[ type="image/avif"] href="{src}" imagesrcset="{avif or img srcset}" imagesizes="{sizes}" fetchpriority="high">`. Nothing if not found. Never fatal on malformed data.

Pre-paint script = prototype head L10–31 printed with `wp_print_inline_script_tag( $js, [ 'id' => 'nh-prepaint', 'data-no-optimize' => '1', 'data-no-defer' => '1', 'data-cfasync' => 'false' ] )` (exclude it from "defer/combine JS" in caching plugins). Changes from the prototype:
1. the first line also skips stored modes inside the Elementor preview (`/[?&]elementor-preview=/.test(location.search)`): editors see the full text by default;
2. calm default: `var cd = {true|false};` printed from `NH_Site::calm_default()`; `if (get("nh-calm") === "on" || (get("nh-calm") === null && cd)) h.classList.add("nh-calm");`
3. `pagereveal`: the stored name is applied only to `.v3-cover--page .v3-room[data-vt]` whose `data-vt` equals the stored value (unique names, D11).

Everything else is verbatim (`js` class, `nh-reduce-motion` from `nh-motion`, `?plain=1|0` before `nh-plain`, `nh-vt-in`).

The scripts `nh-core.js` and widget scripts are deferred footer scripts (§2.4); the prototype's `<script src=… defer>` lines in head L32–33 are not reproduced.

### 5.3 Body open

1. **Sprite** = `partials/sprite.html` reduced to the nine symbols v3 uses: `i-phone`, `i-menu`, `i-close`, `i-arrow-fwd`, `i-arrow-back`, `i-play`, `i-expand`, `i-speech`, `i-sliders` (paths verbatim, same wrapper `svg` with `style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false"`).
2. **Skip link**: `<a class="nh-skip" href="#main">{settings skip_label}</a>`. The target is decided before the body is printed: `NH_Site` hooks `template_include` at `PHP_INT_MAX` (returns the template unchanged and remembers it); if the final template is Elementor's `header-footer.php` (Full Width, where `main_open()` will print `main#main`) the target is `#main`, otherwise `#content` (Hello's `main`). Safety net in `nh-core.js`: if the target does not exist, point the link at the first `main` (adding `id="main"` and `tabindex="-1"` when missing).
3. **Cookie banner** = `partials/cookies.html` with texts from settings (§7), `hidden` by default, `href="#privacy"` resolved. When the setting `cookie_stats` is empty, the statistics checkbox row is not rendered. Not printed in the Elementor preview.

### 5.4 Footer

1. **Visit bar** = footer L60–63: `<aside class="v2-bar v3-bar" data-bar aria-label="{bar_label}">` + CTA `<a class="nh-btn nh-btn--primary" href="{visit_url}" data-bar-cta>{pair bar_cta}{icon:arrow-fwd nh-btn__icon nh-btn__icon--fwd}</a>` + call button `<a class="nh-btn nh-btn--ghost" href="{tel}" aria-label="{bar_call_label} {display}">{icon:phone nh-btn__icon}</a>`. Only if `show_bar`.
2. **Global live region** = header L79: `<p class="nh-sr-only" role="status" aria-live="polite" data-announce></p>`.
3. Parts queued by widgets with `footer_once()` (the lightbox), in queue order.

### 5.5 `nh-core.js` contents (porting map)

| feature | from | notes |
|---|---|---|
| helpers `store`, `osReduced`, `motionOK`, `announce`, `v3-ready` class | v3.js L14–30, site.js L17–23 | |
| motion toggle `[data-motion-toggle]` | site.js L25–40 | **delegated** click on `document`; drop L35–36 (GSAP loader) |
| plain + calm toggles, `sync`, `keepPlace`, URL `plain` param, announcements | v3.js L32–81 | delegated; announcement texts from `nh.data.i18n` |
| cookie consent (banner, «מה זה אומר?», settings, focus return) | site.js L152–216 | `[data-cookie-settings]` delegated; focus returns to `#main`, else the first `main` (gets `tabindex="-1"` if missing), else `h1`; banner shown when no record or `record.v < nh.data.consent.v` |
| visit bar | site.js L108–133, v3.js L235–241 | the bar's CTA becomes `#{id}` of the first `.v3-door[id]` on the page if any; hidden while any `.v3-door`, `.nh-footer` or `[data-bar-hide]` is in view; appears after `.v3-cover__cta a` has scrolled above the viewport (fallback 0.9 screen) |
| `[data-year]` | site.js L457–458 | |
| `nh.once`, `nh.widget`, `nh.consent`, `nh.syncToggles`, `nh.keepPlace` | v3.js L176–184 + new | §3.12 |

Not ported: site.js L42–57 (v1 story), L97–106 (v1 colour bloom), L252–280 (v1 day track).

### 5.6 `window.nhData` (`NH_Site::js_data()`)

```json
{
  "ajax": "…/wp-admin/admin-ajax.php",
  "post": "…/wp-admin/admin-post.php",
  "visitUrl": "…",
  "consent": { "key": "nh-consent-v1", "v": 1 },
  "i18n": {
    "plainOn": "הדף מוצג עכשיו בשפה פשוטה.", "plainOff": "הדף מוצג עכשיו בקריאה רגילה.",
    "calmOn": "מצב רגוע פעיל: בלי תנועה ועם צבעים רכים יותר.", "calmOff": "מצב רגוע כבוי.",
    "motionOn": "התנועה באתר מופחתת.", "motionOff": "התנועה באתר חזרה.",
    "videoTitle": "סרטון"
  },
  "forms": { "msg": { "required": "…", "email": "…", "tel": "…", "consent": "…", "fileSize": "…", "fileType": "…",
                       "summaryOne": "…", "summaryMany": "…", "expired": "…", "limited": "…", "off": "…", "error": "…" } }
}
```
The `i18n` strings are screen-reader announcements (prototype v3.js L71, L78, L80; site.js L226), wrapped in `__()`. The form messages come from settings (§7).

---

## 6. Forms (`NH_Forms`, `nh-forms.js`, `forms.css`)

### 6.1 Schema

| type | key | input | required | rules (server and client identical) |
|---|---|---|---|---|
| visit | `name` | text, `autocomplete="name"` | yes | 1–120 chars |
| visit | `phone` | tel, `autocomplete="tel" inputmode="tel" dir="ltr"` | yes | digits only `^0\d{8,9}$` (site.js L372–375) |
| visit | `email` | email, `autocomplete="email" dir="ltr"` | no | `is_email()` / site.js L371 |
| visit | `who` | select | no | ≤ 200 chars, any text |
| visit | `program` | select | no | ≤ 200 chars |
| visit | `message` | textarea, `aria-describedby` hint | no | ≤ 5000 chars |
| visit | `consent` | checkbox, `data-error-label` | yes | checked |
| jobs | `role` | select | yes | non-empty, ≤ 200 chars |
| jobs | `name`, `phone` | as visit | yes | as visit |
| jobs | `email` | as visit | **yes** | valid |
| jobs | `cv` | file, `accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"`, `aria-describedby` hint | no | extension pdf/doc/docx and real type via `wp_check_filetype_and_ext()`; ≤ 5 MB (5 × 1024 × 1024) |
| jobs | `message` | textarea | no | ≤ 5000 chars |
| jobs | `consent` | checkbox | yes | checked |
| jobs | `keep` | checkbox (not checked by default) | no | |
| both | `website` | honeypot | — | must be empty |

### 6.2 Hidden fields and security

`hidden_fields( $type, $instance, $return )` prints: `action` = `nh_visit` | `nh_jobs`; `_nh_nonce` = `wp_create_nonce( "nh_form_{$type}" )`; `nh_t` = `time()`; `nh_s` = `hash_hmac( 'sha256', "{$type}|{$t}", wp_salt( 'nonce' ) )`; `nh_instance` = the widget element id; `nh_return` = the page URL + `#anchor`; `nh_js` = `0` (set to `1` by JS).

Handler `NH_Forms::handle()` (hooked to `admin_post_nh_visit`, `admin_post_nopriv_nh_visit`, `admin_post_nh_jobs`, `admin_post_nopriv_nh_jobs`), in this order:
1. POST only; `nocache_headers()`.
2. **Honeypot** filled → respond `sent` without sending (bots see success).
3. **Nonce** `wp_verify_nonce( $_POST['_nh_nonce'], "nh_form_{$type}" )` fails → `expired`.
4. **Signed time trap**: signature mismatch (`hash_equals`) → `expired`; `time() − nh_t < 3` s → respond `sent` without sending; `time() − nh_t > 2 days` → `expired`.
5. **Rate limit** by hashed IP: key `nh_rl_` + first 32 hex chars of `hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) )`, `$ip = $_SERVER['REMOTE_ADDR']` (filter `nh_client_ip`); 5 accepted attempts per 10 minutes (filters `nh_rate_limit`, `nh_rate_window`); over the limit → `limited`; otherwise increment with `set_transient( $key, $n + 1, $window )`. Only the counter is stored.
6. **Sanitise and validate** (`sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`, length limits, rules in §6.1) → errors `{ key: code }` with codes `required|email|tel|consent|file_type|file_size` → `invalid`. *(Core: there is no error code for length, and the client checks none: over-long values are cut to the limit server side. The tel rule is applied to the digits of the value, as site.js.)*
7. **Recipient** `NH_Settings::recipient( $type )` empty → `off` (nothing is sent).
8. **Mail**: `wp_mail( $to, $subject, $body, $headers, $attachments )`. Subject: `בקשה לביקור — {site name}` / `מועמדות: {role} — {site name}`. Body: plain text, one `label: value` line per filled field (labels fixed in the schema, Hebrew), then the page URL and `wp_date()`; `keep` as `כן/לא`. Headers: `Content-Type: text/plain; charset=UTF-8`; `Reply-To: {name} <{email}>` when the e-mail is valid; From name from settings `mail_from_name` (filter `wp_mail_from_name` only during this call); the From address stays WordPress's default.
9. **CV**: only after `is_uploaded_file()` and the checks; attached directly from PHP's upload temp file under a clean display name — `[ sanitize_file_name( 'cv-' . wp_date( 'Y-m-d' ) . '.' . $ext ) => $_FILES['cv']['tmp_name'] ]` (WordPress 7.1 uses string keys as attachment names; checked in `pluggable.php`); `wp_delete_file()` of the temp file in a `finally` block. **Never** call `wp_handle_upload()`, `media_handle_upload()`, `wp_insert_attachment()` or move the file into `uploads`. *(Core: the real-type check runs `wp_check_filetype_and_ext( $tmp, 'cv.' . $ext, … )` with the lower-cased extension of the visitor's file name — the name itself is never used; `is_uploaded_file()` goes through the filter `nh_forms_is_uploaded_file` (the harness's only way to simulate an upload); the temp file is deleted before the response is sent, on every path, including rejected requests.)*
10. `wp_mail()` true → `sent`; false → `error`.
11. **Respond**: if `nh_js=1` → `wp_send_json( [ 'ok' => bool, 'status' => …, 'errors' => {…}, 'message' => settings text for the status ] )`; else `wp_safe_redirect( add_query_arg( [ 'nh_form' => $instance, 'nh_status' => $status, 'nh_err' => 'name:required,phone:tel' ], $return ) )` with status 303 and `exit` (in tests: `NH_Forms::halt()` throws instead of exiting). `nh_return` is validated with `wp_validate_redirect( $url, home_url( '/' ) )`. Values typed by the visitor are never put in the URL, a log or the database.

Token endpoint `NH_Forms::token()` on `wp_ajax_nh_form_token` and `wp_ajax_nopriv_nh_form_token` (GET `type=visit|jobs`): `nocache_headers()`, `wp_send_json( [ 'nonce' => …, 't' => time(), 's' => … ] )`.

Admin notice: when a recipient is empty, users with `manage_options` see a notice on the Dashboard, Plugins, Pages and the settings screen: `טפסי האתר לא שולחים דוא״ל: לא הוגדרה כתובת נמען (ביקור / משרות). הגדרות ← נווה האירוס.` with a link; and `NH_Forms::recipient_note( $type )` shows the same sentence above the form to users who can edit posts (front end and editor).

### 6.3 Markup helpers (exact output)

- `field( 'visit', 'name', [ 'id' => 'door-name', 'label' => pair, … ], $state )` → `<div class="nh-field"><label class="nh-label" for="door-name">{label}[ <span class="nh-req" aria-hidden="true">*</span>]</label><input class="nh-input" id="door-name" name="name" type="text" autocomplete="name" required></div>` (prototype index L354). Selects: `<select class="nh-select" id name>` + options (L359); textarea: `<textarea class="nh-textarea" id name aria-describedby="{id}-hint"></textarea><p class="nh-hint" id="{id}-hint">{hint pair}</p>` (page L211–215); file: `<input class="nh-input" id name type="file" accept="…" aria-describedby="{id}-hint">` + hint (ds L324–328). With a server error for the field: `aria-invalid="true"`, `aria-describedby` += `{id}-err`, and after the control `<p class="nh-error" id="{id}-err">{ICON_ERR}<span>{message}</span></p>` — the same markup `setError()` builds in JS (site.js L340–341, L378–397).
- `check( 'visit', 'consent', … )` → `<div class="nh-check"><input id="{p}-consent" name="consent" type="checkbox" required data-error-label="{error label}"><label for="{p}-consent">{label pair} <span class="nh-req" aria-hidden="true">*</span></label></div>`; `keep` without `required`/asterisk.
- `required_note()` → `{block: full_tag p, full_class nh-form__note, plain_tag p, plain_class nh-form__note, plain_lines null}`.
- `summary()` → `<div class="nh-alert nh-alert--error" data-error-summary tabindex="-1" hidden><h3>{msg summary}</h3><ul></ul></div>`; with a server state `invalid`/`expired`/`limited`/`off`/`error` it is rendered without `hidden`, the `h3` says `summaryOne`/`summaryMany` (with `{n}`) for field errors, and the `ul` lists `<li><a href="#{id}">{label} — {message}</a></li>` per error, or one `<li>` with the status message.
- `success()` → `<div class="nh-alert nh-alert--success" data-success tabindex="-1" role="status" hidden><h3>{title pair}</h3><p>{text pair}</p></div>`; not `hidden` when the state is `sent`.
- `honeypot( $p )` → `<div class="nh-hp" aria-hidden="true"><label for="{p}-website">אתר</label><input id="{p}-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>`.
- `privacy_note( 'visit' )` → `<p class="nh-form__note v3-full">{privacy_visit with tokens}</p><div class="nh-form__note v3-plain"><p>…</p>…</div>` (privacy-note.html); jobs → one `<p class="nh-form__note">` unless a plain twin is set.
- *(Core, 16.11 — details the text above left open:)* the server error `<p class="nh-error">` is the **last child** of `.nh-field` / `.nh-check` (after the hint), exactly where `setError()` appends it; a textarea or file field gets `aria-describedby` only when it has a hint (the jobs message field has none, ds L329); for the statuses `expired`/`limited`/`off`/`error` the summary's `h3` is `msg_summary` and its single `<li>` holds the settings message (HTML with the `{phone}` link); `success()` omits the `<h3>`/`<p>` whose pair is empty; the option given as `selected` (jobs-form: `$_GET['role']`) is compared with the option text.

### 6.4 `nh-forms.js` (porting map)

site.js L339–455, changed:
- messages from `nh.data.forms.msg`; `labelText()` reads the visible variant of a label with twins (`.v3-plain` in plain mode, `.v3-full` otherwise) and ignores `.nh-req`;
- on the first `focusin` inside a form: fetch `nh.data.ajax?action=nh_form_token&type=…` once and update `_nh_nonce`, `nh_t`, `nh_s`;
- valid submit: set `nh_js=1`, disable the submit button, `fetch( form.getAttribute( 'action' ), { method: 'POST', body: new FormData( form ), credentials: 'same-origin', headers: { Accept: 'application/json' } } )` — **not `form.action`**: the hidden input named `action` (admin-post.php needs it) shadows that DOM property, so `form.action` is the input element (found by the browser check, core 16.11; the same holds for any widget script that reads a form's properties); `ok` → `form.reset()`, show `[data-success]` and focus it (the prototype's success path); `invalid` → map `errors` to `setError()` + summary (as client errors); other statuses → summary with the server `message`, focused; network error → status `error`;
- on load: focus a visible `[data-success]` or `[data-error-summary]` (server state after a redirect);
- unchanged: errors only on submit, cleared on `input`/`change` when valid, never on blur; honeypot ignored client side; file checks; `novalidate`.

### 6.5 `forms.css`

main.css L265–296 (forms, checks, honeypot, alerts, notes); v3.css L450 and L460 (plain-mode form notes and hints). Plus `.nh-admin-note` styling lives in base.css.

---

## 7. Settings page — Settings → נווה האירוס

`add_options_page( 'נווה האירוס', 'נווה האירוס', 'manage_options', 'nevehairus', … )`, Settings API, one option `nh_settings` (array, autoloaded). Sections and fields (key · type · default · sanitising):

**פרטי קשר**
| key | default | sanitise |
|---|---|---|
| `phone_display` | `08-9403047` | text |
| `phone_tel` | `+97289403047` | `[+0-9]` only |
| `fax` | `08-9400805` | text |
| `email_public` | `''` | `sanitize_email` + `is_email`, else `''` with a settings error |
| `address` | `ת״ד 28, נס ציונה` | text |
| `facebook_url` | `https://www.facebook.com/neveirus/` | `esc_url_raw` |

**טפסים**
| key | default | notes |
|---|---|---|
| `visit_recipient` | `''` | e-mail; empty = the visit form does not send |
| `jobs_recipient` | `''` | e-mail; empty = the jobs form does not send |
| `mail_from_name` | `מעון נווה האירוס` | text |
| `privacy_visit` | privacy-note L2 with the address replaced by the token: `מסירת הפרטים אינה חובה לפי חוק, אך בלעדיהם לא נוכל לחזור אליכם. הפרטים ישמשו רק לתיאום הביקור ולמענה לבקשה, יישמרו במאגר המידע של נוה האירוס בע״מ ולא יועברו לגורם אחר. אפשר לבקש לעיין במידע ולתקן אותו{contact}.` | textarea, inline kses |
| `privacy_visit_plain` | `לא חייבים לתת לנו את הפרטים.⏎בלי הפרטים לא נוכל לחזור אליכם.⏎הפרטים נשמרים אצלנו רק כדי לקבוע ביקור.⏎לא נותנים אותם לאף אחד אחר.⏎אפשר לבקש לראות אותם או לתקן אותם.` | textarea |
| `privacy_jobs` | ds L333 without the screen-reader draft marker, plus `{contact}` ⊕: `מסירת הפרטים אינה חובה לפי חוק, אך בלעדיהם לא נוכל לבדוק את המועמדות. הפרטים ישמשו לבדיקת המועמדות בלבד, יישמרו במאגר המידע של נוה האירוס בע״מ, יימחקו בתום הליך הגיוס אם לא סימנת אחרת, ולא יועברו לגורם אחר. ניתן לבקש לעיין במידע ולתקנו{contact}.` | textarea |
| `privacy_jobs_plain` | `''` | textarea |
| `msg_required` / `msg_email` / `msg_tel` / `msg_consent` / `msg_file_size` / `msg_file_type` | the prototype's `MSG` texts, verbatim (site.js L343–348) | text |
| `msg_summary` / `msg_summary_one` / `msg_summary_many` | `נמצאו שגיאות בטופס:` / `נמצאה שגיאה אחת בטופס:` / `נמצאו {n} שגיאות בטופס:` | text |
| `msg_expired` ⊕ | `הטופס פג תוקף. אפשר לרענן את הדף ולשלוח שוב.` | text |
| `msg_limited` ⊕ | `נשלחו כבר כמה בקשות מהמכשיר הזה. אפשר לנסות שוב בעוד כמה דקות, או להתקשר: {phone}` | text |
| `msg_off` ⊕ | `אי אפשר לשלוח את הטופס כרגע. אפשר להתקשר: {phone}` | text |
| `msg_error` ⊕ | `משהו השתבש בשליחה. אפשר לנסות שוב, או להתקשר: {phone}` | text |

**קישורים**
| key | default | notes |
|---|---|---|
| `visit_url` | `''` | URL; empty = `home_url('/#door')` |
| `jobs_page` | `0` | page select; used by `jobs_url()` and the token `#jobs-page` |
| `accessibility_page` / `sitemap_page` | `0` / `0` | page selects; tokens `#accessibility`, `#sitemap` |
| `entrances_pdf` | `''` | URL of the entrances PDF in the media library; token `#entrances` |
| (read-only) privacy page | — | shows WordPress's Privacy Policy page (`#privacy` uses `get_privacy_policy_url()`) |

**באנר עוגיות** (cookies.html; inline kses where the text has markup)
| key | default |
|---|---|
| `cookie_title` | `עוגיות באתר` |
| `cookie_more` | `מה זה אומר?` |
| `cookie_text` | `אנחנו משתמשים בעוגיות הכרחיות כדי שהאתר יעבוד. עוגיות סטטיסטיקה ותוכן מוטמע (כמו סרטוני YouTube) יופעלו רק בהסכמתכם. אפשר לשנות את הבחירה בכל עת. <a href="#privacy">מדיניות הפרטיות</a>` |
| `cookie_text_plain` | `האתר שומר קבצים קטנים במחשב או בטלפון שלכם.⏎חלק מהקבצים חייבים, כדי שהאתר יעבוד.⏎את שאר הקבצים נשמור רק אם תסכימו.⏎אפשר לשנות את הבחירה מתי שרוצים. <a href="#privacy">מה עושים עם המידע?</a>` |
| `cookie_necessary` | `<b>הכרחיות</b> — פעילות תקינה של האתר ושמירת הבחירה שלכם. תמיד פעילות.` |
| `cookie_stats` | `<b>סטטיסטיקה</b> — מדידת שימוש אנונימית, כדי לשפר את האתר.` (empty = row hidden) |
| `cookie_media` | `<b>תוכן מוטמע</b> — סרטונים מ־YouTube.` |
| `cookie_save` / `cookie_all` / `cookie_necessary_only` / `cookie_settings` | `שמירת הבחירה` / `אישור הכול` / `רק הכרחיות` / `הגדרות` |
| `consent_version` | `1` (raise to ask everyone again) |

**כללי**
| key | default |
|---|---|
| `skip_label` | `דילוג לתוכן הראשי` |
| `show_bar` | `1` |
| `bar_label` | `תיאום ביקור מהיר` |
| `bar_cta` / `bar_cta_plain` | `תיאום ביקור` / `לקבוע ביקור` |
| `bar_call_label` | `התקשרו:` |
| `webp_subsizes` | `1` |

The page also shows a read-only **readiness list** (no writes): recipients set, privacy page set, jobs page set, accessibility page set, Elementor version, active theme is Hello (child), Elementor's default page layout, and a **"send a test e-mail"** button (admin-post `nh_test_mail`, nonce + `manage_options`) that sends a short plain-text message to each configured recipient and reports the `wp_mail()` result. `uninstall.php` deletes `nh_settings`.

---

## 8. Accessibility, RTL, motion and calm rules (every widget keeps them)

These are the prototype's rules (DESIGN-SYSTEM §0, §2, §6; STORYBOARD "Общие правила движения"; prototype README "Проверки v3.1"). A widget that breaks one of them is not done.

**Structure and semantics**
1. One `h1` per page (the cover). Each spread is a `section` with `aria-labelledby` pointing to its heading; the heading may be `nh-sr-only` (voices). Inside a spread: `h2` for the spread, `h3` for items (verbs, years, steps, stories, features, form title).
2. Lists that lose their bullets keep `role="list"` (VoiceOver). Numbered things are `ol`.
3. Decorative SVG (letters, rules, icons, the door) has `aria-hidden="true" focusable="false"`; decorative text (`v3-year__num`, `v3-shamayim`) is `aria-hidden` and the information exists as real text elsewhere. *(v3.2: the steps' word, the running head and the kaf's hidden caption are gone; the kaf's caption is visible text.)*
4. Images: alt from the media library, or the widget's alt override; decorative images `alt=""`. Rooms and prints keep `aspect-ratio`/`width`/`height` so nothing shifts (CLS 0).
5. Links in running text are underlined (1.4.1); a link's name says where it goes; external links say "(נפתח באתר חיצוני)" to screen readers. No `target="_blank"`.
6. Buttons that switch a state are `aria-pressed` with a constant label (masthead, panel, menu, footer toggles); the plain box button is an action with a changing label and **no** `aria-pressed`.
7. Disclosure (display panel): `aria-expanded`/`aria-controls`, focus moves in, Esc and outside click close, focus returns. Menu and lightbox are native `<dialog>` (`showModal`) with focus return; the menu has its own live region because a modal makes the page inert.
8. Forms: visible labels, `autocomplete`, required marked with a visible `*` (`aria-hidden`) plus the explanation sentence; errors only after submit, then an error summary that receives focus and links to fields, inline errors with `aria-invalid` + `aria-describedby`; nothing jumps on blur; honeypot hidden from everyone (`aria-hidden`, `tabindex="-1"`, off-screen); the consent box is required; the §11 privacy notice sits at the form.
9. Live announcements (`role="status"`) for display-mode changes; the gallery announces "תמונה n מתוך m" + alt.

**Keyboard and focus**
10. Every interactive element is reachable in DOM order; skip link first, cookie banner second (first visit).
11. Focus is always visible: `:focus-visible { outline: 3px solid var(--nh-focus); outline-offset: 3px }`; on dark/iris/night spreads `--nh-focus` is citrus. Never `outline: none` without a replacement.
12. Focus is not obscured (2.4.11): `scroll-padding` for the fixed masthead, the visit bar and the cookie banner (main.css L9–13, v3.css L608–610, L632–638) stays.
13. Targets ≥ 44 px (2.5.8): buttons and toggles keep `min-block-size` 2.75rem+.

**RTL and Hebrew**
14. `lang="he" dir="rtl"` from WordPress; CSS uses logical properties only (`inline-start` = right). Arrows: "forward" points left (`i-arrow-fwd`), hover moves `translateX(-…)`.
15. Phones, e-mails, `100+`, Latin names are isolated (`.nh-ltr` or `lang="en" dir="ltr"`), phone inputs `dir="ltr"`.
16. No negative letter-spacing on Hebrew headings; `−0.03em` only on numbers (`v3-year__num`, `v3-stats b`). No italics in Hebrew.

**Text, contrast, zoom**
17. Only colour pairs from DESIGN-SYSTEM §2 (44 pairs, all AA). Inside a toned spread use the variables (`--nh-text`, `--nh-text-muted`, `--nh-link`, `--nh-focus`, `--v3-ink`), never fixed colours.
18. No horizontal scroll at 320–2560 px; content reflows at 400% zoom; text spacing overrides (1.4.12) do not clip; breakpoints of the masthead are in `em`.
19. Forced colours: letters `CanvasText`, rules and door parts `CanvasText`, tessera falls back to a normal ".", toggles keep borders (v3.css L477–488, main.css L419–424).

**Motion (STORYBOARD)**

*(v3.2, B2 16.11: items 20–25 describe v3.1 and are superseded by research/07 §4 — the only motion is the one soft appearance of `figure.v3-print[data-reveal] > picture` (§4.2), hover/focus colour changes and accordions; nothing is pinned (no `position: sticky`), nothing runs on a timer or follows scroll, «הפחתת תנועה» is gone; `build_plugin.py check` rejects the forbidden patterns. To be rewritten in step B5.)*
20. **Text never moves.** Only letters, lines and photos inside rooms move. No reveals, no split text.
21. No JS pinning or scroll hijacking. The only pin is CSS `position: sticky` on the home cover (≥ 64em × 43.75em, 150svh) and only when motion is on and plain mode is off.
22. No infinite loops (2.2.2): time-based motion only for the cover entrance (≤ 1.8 s), the mandala glint (once, 1.7 s) and the door lintel (once, 0.9 s); everything else follows scroll.
23. Three ways to stop motion, all equivalent: OS `prefers-reduced-motion`, «הפחתת תנועה» (`html.nh-reduce-motion`), «מצב רגוע» (`html.nh-calm`). CSS: main.css L410–417 and v3.css L470–475; JS: every animation checks `nh.motionOK()`; the cover morph listens to `nh:motionchange` / `nh:displaychange`; view transitions are off under reduce/calm (v3.css L614–615, main.css L35–40).
24. Calm mode also: citrus spread → sand, photos `saturate(.7)`, no progress line, no hover transitions, no smooth scrolling (v3.css L465–468). Pages can default to calm (`nh_calm_default`).
25. Doors and other "closed until JS" states are closed only when `html.v3-ready` is set (v3.css L431–432): without JS everything is open.

**Plain language**
26. `html.nh-plainmode` set before first paint (pre-paint script, `?plain=1`, stored choice); `.v3-plain` hidden unless plain mode, `.v3-full` hidden in plain mode (v3.css L442–444). The place of reading is kept when switching (`keepPlace`).
27. Big letters fold in plain mode (cover ב beside the words; ל, steps word, שמיים, swatches hidden; kaf letter smaller) — those rules live in the widgets' CSS (§4).

**Performance**
28. The cover photo is the LCP element: `loading="eager" fetchpriority="high"`, preloaded in head; never animate its opacity. Everything else lazy. Scripts deferred; no layout reads during load (the prototype uses IntersectionObserver/ResizeObserver and measures in rAF).

---

## 9. CSS and JS map (prototype → plugin)

Every line of `main.css`, `v3.css`, `site.js`, `v3.js` has exactly one destination or is listed as not ported. "→ widget" refers to the CSS/JS lines in §4.

*(v3.2, B2 16.11: this map is the v3.1 one. Motion, view transitions, the visit bar, the running head, `.nh-wordmark` and the colophon are gone (the §14 tables of B1 and B2 list what changed); the map is redone against the v3.2 prototype with the markup port (step B4) and the documents (step B5).)*

### 9.1 `base.css` (core; site-wide), in this order

| # | source | content |
|---|---|---|
| 1 | main.css L6–33 | reset (includes L9 `html` scroll padding and L10–13 cookie-banner scroll padding) |
| 2 | — | (no separate row: L10–13 are part of row 1) |
| 3 | main.css L35–40 | smooth in-page scrolling, `@view-transition` |
| 4 | main.css L51–64 | `.nh-sr-only`, `.nh-skip`, focus, `.nh-ltr` |
| 5 | main.css L66–90 | typography (`h1–h4`, `.nh-h1…`, `.nh-lead`, `.nh-muted`, `.nh-small`, `.nh-eyebrow`, `.nh-prose`, `a`, `strong`) |
| 6 | main.css L99–114 | layout (`.nh-container`, `.nh-section*`, `.nh-on-dark`, `.nh-section__head`, `.nh-stack`) |
| 7 | main.css L116–143 | buttons and arrow links (with the §10 specificity change for `a.nh-btn--ghost`) |
| 8 | main.css L244–263 | cookie banner |
| 9 | main.css L410–417 | reduced motion (OS and on-site) |
| 10 | main.css L419–424 | forced colours: only `.nh-btn` (drop `.nh-round-btn`, `.nh-arch`, `.nh-frame`, `.nh-hl`; `.nh-menu-btn` → masthead, `.nh-lightbox__btn` → gallery) |
| 11 | main.css L426–430 | print: `.nh-cookies` and `.nh-section--night, .nh-on-dark` rules only (`.nh-header, .nh-menu` → masthead, `.nh-video` → voices; drop `.nh-day__controls`, `.nh-cta`, `body::after`) |
| 12 | v3.css L10–22 | v3 tokens, `.v3` variables, mast height |
| 13 | v3.css L24–28 | square buttons, `.nh-btn--ink` (with the §10 specificity change) |
| 14 | v3.css L80–92 | `.v3-toggle`, `.v3-menu-toggles`, `.v3-footer-toggles`, on-dark toggles |
| 15 | v3.css L94–104 | spreads, tones, `.v3-rule`, `.v3-h2`, `.v3-lead`, `.v3-note`, `.v3-credit` |
| 16 | v3.css L106–116 | `.v3-dot` tessera |
| 17 | v3.css L162 | `@media (min-width: 112.5em) { .v3 { --nh-max: 110rem; } }` |
| 18 | v3.css L234–241 | `main { counter-reset: print }`, `.v3-print*` |
| 19 | v3.css L252–253 | shared two-column grids (kaf, samekh, lamed, door, voices) |
| 20 | v3.css L336–340 | `.v3-quote` |
| 21 | v3.css L372–379, L552–557 | `.v3-roles` (lamed, related) |
| 22 | v3.css L439, L490–491, L598–610 | visit bar (`.v3-bar`, `.v2-bar`, `.is-typing`, `html.nh-has-bar`, cookie interplay) |
| 23 | v3.css L441–444, L445 (first two selectors), L446–448, L451 | plain mode, general rules |
| 24 | v3.css L465–467 | calm mode, general rules |
| 25 | v3.css L470–475 | reduced-motion transitions: `.v3-toggle i`, `.v3-toggle i::after`, `.v3-roles svg` only |
| 26 | v3.css L477–488 | forced colours, base selectors only: `.v3-toggle i`, `.v3-toggle[aria-pressed="true"] i`, `.v3-dot`, `.v3-dot::before`, `.v3-rule` (from the L484 group), `.v3-print picture` (`.v3-letter__ink` → letters.css; `.v3-progress`, `.v3-plainbtn`, `.v3-display__btn` → masthead; `.v3-door__bar`, `.v3-door__leg` → door) |
| 27 | v3.css L581–583 | `.v3 .nh-btn` wraps on narrow phones (`.v3-plainbox` part → plainbox) |
| 28 | v3.css L587–590 | `.nh-wordmark` |
| 29 | v3.css L612–615 | page transitions, generalised: `::view-transition-group(*.nh-bet) { animation-duration: .6s; animation-timing-function: cubic-bezier(.22, 1, .36, 1); }` + the reduce/calm `animation: none` rules |
| 30 | v3.css L632–638 | short screens: `.v2-bar { display: none !important }` and `html { scroll-padding-block: 1rem }` (`.v3-mast` → masthead) |
| 31 | v3.css L644–645 | `.nh-cookies { view-transition-name: nh-cookies; }` |
| 32 | new | `.nh-admin-note` (dashed 1px border, small text, paper background) and the Elementor/Hello compatibility block (§10.1) |

**Changed by the core (16.11): row 32 is printed right after row 5**, not last. `.elementor a:where(:not([class])) { text-decoration: underline }` has the specificity of Elementor's `.elementor a` (0,1,1) and must win over it (it does: base.css loads after Elementor's CSS), but it has the same specificity as the component rules below it — `.v3-roles a { text-decoration: none }` (row 21) and others — and as the last rule it beat them: every row of the ל list and of the related links was underlined (found in the screenshot comparison). Printed after the typography, it beats Elementor and loses to every component rule. Widget CSS files load after base.css and are not affected. In base.css every block names its source lines (`/* ---- v3.css L372–379 ---- */`) and porting changes are marked `plugin:`.

### 9.2 Other core CSS

- `letters.css`: v3.css L118–129 (`.v3-room`, `.v3-room__photo`, `.v3-room__photo img`, `.v3-room__field--sky`, `.v3-letter`, `.v3-letter__ink`), L280–282 (`.v3-room__photo--whole`, `.v3-room__work`, `.v3-room__work img`), from L479 `.v3-letter__ink { fill: CanvasText; }` (forced colours).
- `forms.css`: §6.5.
- `tokens.css`: verbatim copy (no edits).

### 9.3 Not ported (stay in the prototype)

main.css L42–49 (paper grain; v3 switched it off — and v3.css L640–642 with it), L92–97 (`.nh-hl`), L207–214 (`.nh-motion-toggle`), L307–324 and L344–354 (`.nh-page-hero`, `.nh-glance`, `.nh-features`, `.nh-feature`, `.nh-steps`, `.nh-related` — v1 inner page), L379–401 (design-system page helpers), L402–408 (`.nh-accordion`, no widget uses it); v3.css L559–566 (design-system page), L617–618 (empty comment); all of `legacy-v1.css`; `site.css` (generated). JS: site.js L35–36, L42–57, L97–106, L252–280; the design-system page script.

### 9.4 JS destinations

| source | destination |
|---|---|
| head.html L10–31 | pre-paint script (§5.2) |
| site.js L12–23, L25–40, L108–133, L152–216, L457–458; v3.js L14–30, L32–81, L176–184, L235–241 | `nh-core.js` |
| site.js L339–455 | `nh-forms.js` |
| v3.js L116–173 | `cover.js` |
| site.js L59–95, L135–150; v3.js L83–114 | `masthead.js` |
| v3.js L226–233 | `homes.js` |
| v3.js L188–224 | `kaf.js` |
| v3.js L185 | `samekh.js` |
| v3.js L186 | `door.js` |
| site.js L218–250 | `voices.js` |
| site.js L282–337 | `gallery.js` |

*(v3.2, B2 16.11, research/07 §4.4, §11.1, §13: `cover.js`, `homes.js`, `kaf.js`, `samekh.js`, `door.js` and `gallery.js` are deleted with what they moved; `masthead.js` keeps only the menu (prototype site.js «1. Phone menu», with `menu.dataset.nav`); `voices.js` is unchanged; nh-core.js has no `nh.once` any more. The build check refuses a `"scroll"` listener, `ResizeObserver` and view transitions in the plugin's scripts.)*

---

## 10. Elementor and Hello compatibility rules

Checked against `elementor/4.4.0/assets/css/frontend.min.css`, `includes/base/widget-base.php`, `modules/page-templates/templates/header-footer.php` and `hello-elementor/3.5.2`.

### 10.1 Rules in `base.css` (core writes them; widgets rely on them)

```css
/* Elementor containers that hold our full-bleed spreads add no padding, gap or width limit */
.e-con:has(> [class*="elementor-widget-nh-"]),
.e-con:has(> .e-con-inner > [class*="elementor-widget-nh-"]) {
  --padding-top: 0px; --padding-right: 0px; --padding-bottom: 0px; --padding-left: 0px;
  --gap: 0px; --row-gap: 0px; --column-gap: 0px; --content-width: 100%; --min-height: 0px;
}
/* no widget spacing between spreads (frontend.css: .elementor-widget:not(:last-child) { margin-block-end: var(--kit-widget-spacing, 20px) }) */
.elementor-widget[class*="elementor-widget-nh-"]:not(:last-child) { margin-block-end: 0; }
/* frontend.css: `.elementor a { text-decoration: none; box-shadow: none }` (0,1,1) — keep underlines on plain links (1.4.1) */
.elementor a:where(:not([class])) { text-decoration: underline; }
```
These rules sit in base.css right after the typography (§9.1, row 32 note): before every component rule of base.css, after Elementor's CSS. A widget rule that removes the underline of a class-less link (`.v3-x a { text-decoration: none }`, 0,1,1) wins because widget CSS loads after base.css.

### 10.2 Rules every CSS author follows

1. **Load order.** All our styles depend (directly or through `nh-base`) on `elementor-frontend`, so they print after Elementor's CSS; equal specificity then goes to us.
2. **`a` and `img` inside Elementor.** Elementor sets `.elementor a { box-shadow: none; text-decoration: none }` and `.elementor img { height: auto; max-width: 100%; border: none; border-radius: 0; box-shadow: none }` (specificity 0,1,1). Any of our rules that sets `box-shadow`, `text-decoration`, `height`/`block-size`, `border` or `max-width` on an `a` or an `img` must have specificity ≥ (0,1,1): e.g. `.v3-room__photo img` (fine as is), but `.nh-btn--ghost` → `a.nh-btn--ghost, button.nh-btn--ghost` and `.nh-btn--ink` → `a.nh-btn--ink, button.nh-btn--ink` (base.css items 7 and 13).
3. **No margins on `figure`.** Elementor resets figure margins with `.elementor-widget:not(.elementor-widget-text-editor):not(.elementor-widget-theme-post-content) figure { margin: 0 }` — specificity (0,3,1), stronger than our component selectors. Use `padding`, `gap` or `justify-self` instead (the two cases: `.v3-samekh__art`, `.v3-year .v3-print`, §4.7, §4.8).
4. **No reliance on DOM structure** between `main` and our sections (Elementor inserts `div.elementor > div.e-con…`). Query by our classes and data attributes (`.v3-print[data-reveal]`, `[data-visit-cta]`, `.v3-door`), never `main > section`.
5. **No `overflow` on containers.** The ל rises into the previous spread; containers must keep `overflow: visible` and must not use transforms/motion effects. *(v3.2: nothing is pinned any more — no `position: sticky` anywhere, research/07 §4.4.)*
6. **No Elementor style controls** in our widgets and no `selectors`: the design is fixed by the prototype.

### 10.3 Site set-up the plugin expects (step 4 checklist, not plugin code)

- Theme Hello Elementor 3.5.2 + child theme active; Hello settings untouched (our filters switch off its reset/theme CSS, skip link and page title).
- Elementor → Site Settings → Layout → Default page layout: **Elementor Full Width**; Kit **Theme Style empty** (no typography, buttons, links, form fields); Global Colors/Fonts may be synced from V4 variables but are not used by our widgets.
- Elementor Pro Theme Builder: one header template with `nh-masthead` (entire site), its HTML Tag (document setting `content_wrapper_html_tag`) `div` and the widget's `html_tag` `header` (D3); one footer template (entire site) of Elementor's own Image, Heading and Text Editor widgets in inner containers plus `nh-display`, its HTML Tag `footer` *(v3.2, B2: the colophon widget is gone, research/07 §12.6; `templates/pages/footer.json`)*; every page: one container (Full Width, padding 0, gap 0, direction column) holding the spreads in prototype order — so Elementor's **Container** feature must be active (Elementor 4.4.0 still lists it under Features; it is off by default on a site first installed before Elementor 3.16, and without it the containers are not rendered). Page Transitions (Pro) off; Element Caching may stay on (dynamic widgets opt out). Steps 4–5 on the test site: `scripts/test_site.py pages` builds both pages and both templates from `templates/pages/*.json` and checks the Container feature and the wrapper tag.
- Caching plugin (ezCache): exclude `#nh-prepaint` from JS optimisation if it touches inline scripts; never cache `admin-ajax.php` / `admin-post.php`.
- Settings → Privacy: the privacy policy page set; Settings → נווה האירוס filled (recipients by the client).

---

## 11. Build, packaging and the test harness

### 11.1 `scripts/build_plugin.py` (Python 3 standard library; run with `python3 -I`)

```
python3 -I scripts/build_plugin.py sync    # copy shared assets from the prototype into the plugin
python3 -I scripts/build_plugin.py check   # sync drift + structure + forbidden patterns + php -l + node --check + harness
python3 -I scripts/build_plugin.py zip     # sync, check, then plugin/dist/nevehairus-site.zip   (default command)
```
- **sync** copies, byte for byte: `prototype/assets/fonts/*.woff2` and `OFL-*.txt` → `assets/fonts/`; `prototype/assets/letters.json` → `assets/letters.json`; `prototype/assets/css/tokens.css` → `assets/css/tokens.css`. The copies are committed, so the plugin folder is complete in git.
- **check** fails (exit 1) on: a synced file that differs from its source; a `widgets/*.php` whose id, class name or `ID` constant break §3.1; a PHP file without the `ABSPATH` guard; PHP ≥ 8.1 syntax (regex for `readonly `, `enum `, `: never`, `(...)` callables, typed `const`); forbidden patterns anywhere in `plugin/nevehairus-site/` — e-mail addresses (except `@example.com`, `@example.test`, `.invalid`), `assets/img/`, `gsap`, `fonts.googleapis`, `fonts.gstatic`, `<script src="http`, `cdn.`; `php -l` errors (plugin and `plugin/tests`); `node --check` errors (if `node` exists); a non-zero exit of `php plugin/tests/run.php`; `Version:` header ≠ `NH_VERSION`. Optional `--phpcs`: runs `plugin/vendor/bin/phpcs --standard=WordPress-Extra plugin/nevehairus-site` when `composer install` (dev tools in `plugin/composer.json`) has been run.
- **zip** writes `plugin/dist/nevehairus-site.zip` deterministically: top folder `nevehairus-site/`, entries sorted, fixed timestamp 1980-01-01, mode 0644 (dirs 0755), excluding `.DS_Store`, `*.map`, `Thumbs.db`. Prints file count, size and SHA-256. The zip is committed.
- *(v3.2, B2 16.11, research/07 §4, §11.3 item 5, §17.1:)* **check** also refuses forbidden motion: in the plugin's CSS (comments stripped; the synced `tokens.css` included) `@keyframes`, `animation-timeline`, `view-transition`, `@property`, `scroll-behavior: smooth`, `position: sticky`; in its scripts (comments stripped) a `"scroll"` listener, `ResizeObserver`, `startViewTransition`.
- *(Core, 16.11:)* the synced copies are exempt from the content patterns (they are checked byte for byte against the prototype instead; `tokens.css` compares its easing curves with GSAP's in two comments), the OFL licence texts from the e-mail pattern; the PHP 8.1+ regexes run on the code with comments and strings removed and also catch `new` in initializers, `final const` and functions added after 8.0 (`array_is_list`, `json_validate`, …); `README-dev.md` is not zipped; `--no-harness` skips run.php; `--phpcs` runs WordPress-Extra **without** `Universal.Arrays.DisallowShortArraySyntax` (this spec's convention) and `WordPress.Files.FileName` (`widgets/<id>.php` is this spec's convention), warnings do not fail, plus PHPCompatibilityWP with `testVersion 8.0-`. The PHP 8.0 claim is verified by PHPCompatibility: the core passes clean.

### 11.2 Stub harness (`plugin/tests/`, PHP 8.3 CLI, no Composer, no WordPress)

- `bootstrap.php`: `error_reporting( E_ALL )`; every notice/warning/deprecation becomes an `ErrorException`; defines `ABSPATH`, `WPINC`, `NH_TESTING`; loads `stubs/wordpress.php`, `stubs/elementor.php`, `fixtures/media.php`, then `nevehairus-site.php`; fires the stubbed `plugins_loaded`, `elementor/loaded`, `init`, `elementor/widgets/register`.
- `stubs/wordpress.php` (core-owned; every function guarded by `function_exists`): escaping (`esc_html`, `esc_attr`, `esc_url` — `htmlspecialchars` with `ENT_QUOTES`, `esc_url` keeps `http(s)`, `mailto:`, `tel:`, `#…`, relative), `wp_kses`/`wp_kses_post` (small allowlist implementation over `DOMDocument`, strips everything else), i18n (`__`, `esc_html__`, `_x`… return the text), hooks registry (`add_action`, `add_filter`, `do_action`, `apply_filters`, `did_action`, `has_filter`, `remove_filter`), options and transients in memory, `home_url()` → `https://nh.test` + path, `admin_url()`, `get_permalink()`, `get_the_ID()` / `get_queried_object_id()` (default 10), `get_post_ancestors()`, `url_to_postid()` (fixture map), `get_privacy_policy_url()` (''), `current_user_can()` (false by default, switchable), nonces (`wp_create_nonce` = `'nonce-' . $action`, `wp_verify_nonce` accepts it), `wp_salt()`, `wp_date()`, `wp_json_encode()`, `sanitize_*`, `absint`, `is_email`, `add_query_arg`, `wp_validate_redirect`, `wp_safe_redirect` (recorder), `wp_send_json` (recorder + `NH_Test_Halt`), `nocache_headers`, `wp_mail` (recorder), `wp_check_filetype_and_ext`, `is_uploaded_file`/`wp_delete_file` (test hooks), the media functions used by `NH_Picture` (fixture-backed), style/script registration recorders, `wp_print_inline_script_tag`, `plugin_dir_path/url`. `wp_handle_upload`, `media_handle_upload`, `wp_insert_attachment` **throw** if called.
- `stubs/elementor.php` (core-owned): namespace `Elementor` with `Widget_Base` (stores controls from `register_controls()`, merges defaults + `$data['settings']` with `array_replace_recursive`, adds `_id` to repeater items, `get_settings_for_display()`, `get_id()` from `$data['id']`, `get_name()` abstract, a public `nh_test_render(): string` that buffers `render()`), `Controls_Manager` constants (§2.2), `Repeater`, `Utils::get_placeholder_image_src()`, `Plugin::$instance` with `editor->is_edit_mode()` / `preview->is_preview_mode()` false, `Elements_Manager::add_category()` and `Widgets_Manager::register()` recorders.
- `fixtures/media.php`: a fake media library built at runtime from `prototype/assets/img/manifest.json`; attachment ids are 101, 102, … in manifest order; `nh_test_media_id( 'v3-walk' )` returns the id. For each image: full width/height, sub-sizes = the manifest `widths` named `nh-{w}` (the 768 one also answers to `medium_large`, so `NH_Picture` picks the same `src` as the prototype) with WebP files, AVIF alternates in `sources['image/avif']`, alt = manifest alt, URLs relative to `plugin/tests/out/` → `../../../prototype/assets/img/{name}-{w}.webp` (so rendered pages show the real prototype photos; this is test data only).
- `fixtures/widgets/<id>.php` (widget owner) returns:
```php
<?php
return [
	'variants' => [
		'default'   => [ 'settings' => [], 'compare' => [ 'page' => 'index.html', 'selectors' => [ 'section#homes' ] ] ],
		'prototype' => [
			'settings' => [ 'homes' => [ 0 => [ 'photo' => [ 'id' => nh_test_media_id( 'v3-sky-room' ), 'url' => '' ] ],
			                             1 => [ 'photo' => [ 'id' => nh_test_media_id( 'v3-villa' ), 'url' => '' ] ] ] ],
			'options'  => [],     // NH settings overrides
			'get'      => [],     // $_GET for this render
			'compare'  => [ 'page' => 'index.html', 'selectors' => [ 'section#homes' ] ],
			'expect_diff' => [ 'a\.v3-home__link.*href' ],   // regular expressions over compare.py's normalised diff lines
		],
	],
];
```
- `run.php [--widget=<id>]`: for every discovered widget and every variant (a widget without a fixture file gets the `default` variant only): build settings, render, then assert (fail = exit 1): no PHP error; non-empty output; well-formed HTML (`DOMDocument` with libxml errors, ignoring "unknown tag" warnings for HTML5 elements); unique ids; every `for`, `aria-labelledby`, `aria-describedby`, `aria-controls` resolves inside the output (or to the documented global ids); every `img` has `alt`; every `svg` without a role has `aria-hidden="true"` and `focusable="false"`; no `style` attribute other than CSS custom properties (`--…`); no forbidden pattern (§11.1); spread widgets have `aria-labelledby` on the root pointing to a heading inside (v3.2: and no `data-header` / `data-running`); no `v3-full`/`v3-plain` element is empty. It writes `out/<id>--<variant>.html` (a full page: `lang="he" dir="rtl" class="js"`, `body.v3`, sprite, `main#main`, the widget, the site parts, `tokens.css` + `base.css` + `letters.css`/`forms.css` + the widget CSS, `nh-core.js` + widget JS, `window.nhData`) and two assembled pages `out/page-home.html` and `out/page-main-home.html` from `fixtures/pages.php` (masthead, the spreads in prototype order with their `prototype` variants, colophon, site parts). Then it runs `core/*.test.php`.
- `render.php <id> [variant] [--plain]` prints one render (with `--plain` the page has `html.nh-plainmode`).
- *(Core, 16.11 — how the harness behaves, for fixture authors:)*
  - Hook order as on a real site: `elementor/loaded` (Elementor is included before us), `plugins_loaded`, `init`, the two Elementor registration hooks, `wp_enqueue_scripts`. Each render is a fresh request (`nh_test_reset_request()`): options, transients, `$_GET`/`$_POST`, the user's capabilities, the queried post (10) and the plugin's per-request statics are reset; hooks, handles and media stay.
  - The stub `Widget_Base` refuses what §2.2 forbids: controls outside a section, a section on another tab than content, a duplicate control id, a type outside the subset, `selectors`, `dynamic`, a URL control without `'options' => false`. Controls whose `condition` is not met read as `null` (as in Elementor). Missing repeater fields get their field defaults.
  - Assertions, refined: a widget is a *spread* when its root element is a `<section>` (the colophon and masthead use the section controls for their running head but are not spreads); an `svg` may also rely on an `aria-hidden` ancestor (`NH_Html::icon( …, false )`); the forbidden-pattern scan ignores the fixture photos' base path; links without `href` are reported as warnings; the controls section «מקטע» must be the last one.
  - *(v3.2, B2 16.11:)* a `style` attribute is allowed only on a letter's room (`.v3-room`, research/07 §13 «Стенд»: no swatches any more); a spread root with `data-header` / `data-running` fails (B1); the global ids are the theme's `main#content`, the masthead's `nh-menu` / `nh-menu-title` and the cookie panel's; a fixture variant may set `'queried' => <post id>` (the post being shown: 10 = the home page, 11 = «מעון ראשי», 12 = the jobs page; default 10) and `'empty' => true` (the settings leave nothing to show: the render must be empty, e.g. `display/none`); `fixtures/pages.php` may set `queried` per page (the house page is 11). The assembled pages have the masthead in the header slot and, in the footer slot, the `display` widget — the one widget of ours in the footer of Elementor's own widgets.
  - The stub `esc_url()` keeps relative paths starting with `.` (WordPress would prefix `http://`; real attachment URLs are absolute), `wp_kses()` keeps the text of removed tags as WordPress does, `sanitize_file_name()` keeps letters of any script, and the fake media library's full size is the original upload (manifest width × height) while its file is the largest derivative.
  - Options: `run.php --widget=<id> | --core-only | --suite=<name> | --no-pages | --no-core | --verbose`; `render.php <id> [variant] [--page] [--plain] [--out]`. Each render also writes `out/<id>--<variant>.json` (its compare target) for compare.py. Assertion helpers for core tests (`nh_assert()`, `nh_assert_same()`, `nh_assert_contains()`, `nh_assert_not_contains()`) live in bootstrap.php.
  - `fixtures/pages.php`: per page `title`, `prototype`, `header` and `footer` ([ id, variant ]) and `widgets` (a list of [ id, variant ]; a missing variant falls back to `prototype`, then `default`; a missing widget becomes an HTML comment). The options and `$_GET` of all chosen variants are merged; the page's `_elementor_data` is built from the list, so the head's cover preload is exercised too.

### 11.3 How a widget is rendered with its default settings

```php
require __DIR__ . '/bootstrap.php';
$class  = NH_Plugin::widgets()[ $id ];                       // e.g. NH_Widget_Homes
$widget = new $class( [ 'id' => substr( md5( "$id/$variant" ), 0, 7 ), 'settings' => $overrides ], [] );
$html   = $widget->nh_test_render();                          // register_controls() defaults + overrides → render()
```
With `$overrides = []` this is exactly the output an editor sees after dropping the widget on a page.

### 11.4 `compare.py` (structural diff against the prototype)

`python3 -I plugin/tests/compare.py <id> [variant]` reads `out/<id>--<variant>.html` and the built prototype page named in the fixture, selects the target elements, and compares normalised trees: tag names; class **sets**; attributes except `id`, `for`, `aria-labelledby`, `aria-describedby`, `aria-controls` (presence only), `src`/`srcset`/`sizes`/`width`/`height` (presence only), `href` (fragment only, or "URL"), `action`, `style` (custom properties compared numerically ± 0.01), `data-vt`, `data-src`, `data-srcset`, `data-avif`, `data-name`, `data-work`, `data-full`, `data-caption-plain`, `data-count`, `data-running-plain`; ignored elements: `input[type=hidden]`, `script`, `.nh-admin-note`; text with collapsed whitespace. The normalised tree is printed one node per line (`tag.class.class [attr=value] …` for elements, `"text"` for text, indented by depth); `compare.py` prints the unified diff of the two trees and exits 0 when every differing line matches one of the fixture's `expect_diff` regular expressions. The "expected differences" listed per widget in §4 are what those expressions must cover — nothing more.

*(Core, 16.11 — the exact format, so fixture authors can write the expressions.)* One line per node, two spaces of indent per depth. An element: `tag` + `.class` for each class, **sorted**, then its attributes sorted by name: `[name=value]`, or `[name]` for the presence-only ones and for empty values; `href` becomes `[href=#fragment]` or `[href=URL]`; `style` becomes `[style=--a:1.00%;--b:2.00%…]` (properties sorted, numbers with two decimals; two lines that differ only by numbers within ±0.01 are equal). A text node: its JSON string, whitespace collapsed and trimmed next to non-inline neighbours. A **whitespace-only** text node is kept (as `" "`) only in running text: between two inline elements/texts inside an element that also has text of its own — formatting whitespace between blocks, flex/grid items or an icon and its label is not compared. Diff lines start with `-` (prototype) or `+` (plugin) followed by the indented node, e.g. `-          img [alt=…] [decoding=async] …`; `expect_diff` entries are Python regular expressions matched with `re.search` (an invalid expression — prose — fails the compare with a message; patterns that match nothing are reported). A selector that finds nothing in the plugin output is retried without its tag (`header.nh-header` → `.nh-header`), so a changed root tag shows as one diff line. *(v3.2, B2 16.11: a selector may be a descendant selector of such parts, `footer.nh-footer .v3-switches`; in the plugin output, which has no surroundings, it falls back to its last part. `compare.py --prototype-letter-data [NAMES]` prints the build script's `letter_data()`, for the core test of `NH_Letters::data_script()` now that no page prints `#nh-letters`.)* More: `compare.py <id> --all` (every variant with a target), `compare.py --tree FILE [SELECTOR]` (print a normalised tree), `compare.py --prototype-letters` (the build script's letter output, for the parity test).

`shots.mjs` (optional, Node 22 + the globally installed Playwright 1.63 and `/usr/bin/google-chrome`): serves the repository on a local port, opens `out/page-home.html` and `prototype/index.html` (and the house page pair) at 1440×900 and 390×844, normal / `?plain=1` / reduced motion, with consent pre-set in `localStorage`, takes full-page screenshots into `out/shots/`, computes the share of differing pixels in the browser (canvas) and writes `out/shots/report.json`. Target: < 1 % per pair; any difference above that is explained in the package report. *(v3.2, B2 16.11: `--behaviour` now runs 41 checks on `out/page-home.html` in Chrome: nothing opens or moves by itself (`document.getAnimations()` empty), no `scroll`/`resize` listener on window or document (CDP), every `[data-visit-cta]` leads to `#door`, no running head / progress line / display panel / visit bar, the cookie panel (a link to `#nh-cookies` opens it in the flow with focus on its title; Esc and Save hide it and return focus; the choice is stored), focus on the target's heading after an in-page link and after a link of the phone menu (not on «תפריט»), the menu (opened only by «תפריט»; Esc and «סגירה» return focus), the one soft appearance (prints below the first screen wait, captions never hidden, every print shown after a slow scroll, nothing runs afterwards, nothing hidden with reduced motion), the display switches and announcements, and the visit form as before. The morph, the visit bar and the banner's self-show are no longer tested: they are gone.)* *(Core: before each shot the page is walked once and every image is decoded (lazy images otherwise race the screenshot); `node plugin/tests/shots.mjs --behaviour` runs 25 checks of the core scripts on `out/page-home.html` in Chrome — consent and focus return, the display switches and announcements, validation, the token refresh, the server answers mocked (invalid, sent, limited, network error), the visit bar, the cover morph. First results, all 20 widgets assembled: desktop 0.01–0.04 % on both pages in all three modes; phone plain 0.04–0.06 %; phone normal/reduced 1.8 % / 2.7 % only because the test settings' public e-mail is shorter than the prototype's address — the privacy note under the door form is one line shorter and everything below moves up 25 px.)*

### 11.5 Core unit tests (`plugin/tests/core/*.test.php`)

Files: `letters.test.php`, `text.test.php`, `picture.test.php`, `forms.test.php`, `site.test.php`, `settings.test.php` (items 1–6 below, in this order). Each file returns a list of named closures; `run.php` runs them and reports failures.

1. **Letters parity:** every `{{letterroom …}}`, `{{letter …}}` and `{{doorway}}` in `prototype/src/*.html` → the PHP call with the same arguments (photo names → fixture ids) equals the built prototype output (same markup; numbers ± 0.001; URLs by basename). *(Core: the expected output comes from the build script itself (`compare.py --prototype-letters`), plus every letter at six in-between weights in both frames; the `<img>` `width`/`height` are compared by presence (the fake library's full size is the original upload, the build script writes its largest derivative: same aspect ratio). Result: equal without any numeric tolerance.)*
2. `NH_Text`: the five states for `pair()` and `block()` (table-driven), `dot()` variants and the "only the final dot" rule, `lines()`, kses allowlist, link tokens, `tokens()` (`{phone}`, `{email}`, `{contact}`, `{year}`) with and without the public e-mail.
3. `NH_Picture`: output for a fixture image (AVIF source present), decorative, alt override, priority, missing attachment → `''`.
4. `NH_Forms::handle()`: honeypot → `sent` and no mail; bad nonce → `expired`; too fast → `sent`, no mail; stale → `expired`; 6th request in 10 min → `limited`; invalid fields → `invalid` with the right codes; empty recipient → `off`, no mail; success → one mail with the right headers; CV → attached under `cv-YYYY-MM-DD.pdf`, temp file deleted, media functions never called; nothing written to options except the `nh_rl_*` transient.
5. `NH_Site`: head output order (meta, preloads, script), pre-paint script contains the storage keys, `?plain` handling and `pagereveal`; body-open output = sprite + skip link (`#main` vs `#content`) + hidden banner; footer output = bar + live region + queued parts; preview mode prints no banner.
6. `NH_Settings::sanitize()`: invalid e-mails dropped, URLs cleaned, numbers clamped.

---

## 12. Work packages

Every package works only in its own files (§1) and reads the core API (§3) as a contract. The core package lands first; widget packages may start in parallel against this spec and use the core once merged.

| id | widgets | files created |
|---|---|---|
| core | cover | everything marked "core" in §1: `nevehairus-site.php`, `uninstall.php`, `readme.txt`, `README-dev.md`, `includes/*` (13 class files), `assets/css/{tokens,base,letters,forms}.css`, `assets/css/widgets/cover.css`, `assets/js/{nh-core,nh-forms}.js`, `assets/js/widgets/cover.js`, `assets/letters.json`, `assets/fonts/*` (4 woff2 + 2 OFL texts), `languages/index.php`, `widgets/cover.php`, `plugin/tests/{bootstrap.php,run.php,render.php,compare.py,shots.mjs}`, `plugin/tests/stubs/{wordpress,elementor}.php`, `plugin/tests/fixtures/{media.php,pages.php}`, `plugin/tests/fixtures/widgets/cover.php`, `plugin/tests/core/{letters,text,picture,forms,site,settings}.test.php`, `plugin/composer.json`, `scripts/build_plugin.py`, `plugin/dist/nevehairus-site.zip`, `.gitignore` line |
| chrome | masthead, colophon | `widgets/{masthead,colophon}.php`, `assets/css/widgets/{masthead,colophon}.css`, `assets/js/widgets/masthead.js`, `plugin/tests/fixtures/widgets/{masthead,colophon}.php` |
| index | homes, steps, lamed, related | `widgets/{homes,steps,lamed,related}.php`, `assets/css/widgets/{homes,steps,lamed,related}.css`, `assets/js/widgets/homes.js`, `plugin/tests/fixtures/widgets/{homes,steps,lamed,related}.php` |
| crafts | kaf, samekh, years | `widgets/{kaf,samekh,years}.php`, `assets/css/widgets/{kaf,samekh,years}.css`, `assets/js/widgets/{kaf,samekh}.js`, `plugin/tests/fixtures/widgets/{kaf,samekh,years}.php` |
| life | plainbox, day, voices | `widgets/{plainbox,day,voices}.php`, `assets/css/widgets/{plainbox,day,voices}.css`, `assets/js/widgets/voices.js`, `plugin/tests/fixtures/widgets/{plainbox,day,voices}.php` |
| residence | page-cover, glance, gallery, features, stories | `widgets/{page-cover,glance,gallery,features,stories}.php`, `assets/css/widgets/{page-cover,glance,gallery,features,stories}.css`, `assets/js/widgets/gallery.js`, `plugin/tests/fixtures/widgets/{page-cover,glance,gallery,features,stories}.php` |
| door | door, jobs-form | `widgets/{door,jobs-form}.php`, `assets/css/widgets/{door,jobs-form}.css`, `assets/js/widgets/door.js`, `plugin/tests/fixtures/widgets/{door,jobs-form}.php` |

**Definition of done (each package)**
1. Exactly the files above; nothing else touched.
2. `php -l` and `node --check` clean; `python3 -I scripts/build_plugin.py check` passes once core is merged.
3. `php plugin/tests/run.php --widget=<id>` passes for every variant.
4. `python3 -I plugin/tests/compare.py <id> <variant>` shows only the differences listed in the fixture's `expect_diff` (and §4 for the widget).
5. Every visible text is a control with a `_plain` twin, or a §3.5 exception; defaults are verbatim prototype text.
6. All output escaped (review: no raw setting reaches `echo`); no inline styles except custom properties; no libraries; no `assets/img`; no e-mail addresses.
7. A short report: deviations from this spec and why, open issues, and (optional) the `shots.mjs` numbers.

**Core definition of done (additionally):** §11.5 tests pass; the letters parity test is exact; `cover` passes compare with no differences; the site module output matches header L1–2/L79, footer L60–63, cookies.html, sprite.html (nine symbols); the zip builds; `plugin/README.md` status line updated.

---

## 13. Open questions (client decisions)

1. Which addresses receive visit requests and job applications (and is the jobs inbox separate, with 2FA)? Until set, both forms are off and say so.
2. Should the public e-mail appear on the site (door, footer, privacy notice), and which one?
3. Privacy notices, the CV retention period and the new form messages (⊕): wording approved by the client's lawyer?
4. Will there be site statistics? If not, the cookie banner drops the «סטטיסטיקה» category.
5. Calm mode as the default on the autism home page — confirm.
6. Until launch, keep the footer line «אב־טיפוס לעיון פנימי · …»?

---

## 14. Core package — what changed in this spec, and what it means for the widget packages (16.11.2026)

The core is in place (§12 row "core"); every change it made to this spec is marked *(Core …)* in the section it concerns. In short:

| what | where | does a widget package need to act? |
|---|---|---|
| `class-nh-widget-base.php` is loaded by `NH_Plugin::widgets()`, only with Elementor; a widget file that throws while it loads (a parse error, an `Error`) is skipped (`NH_Plugin::skipped()`), not fatal. *(Corrected 16.11, pre-install review: an error PHP raises while declaring the class — an override whose signature, visibility or `static` differs from Elementor's, a redefined `final` method such as `get_name()`, a class name declared twice — is a compile-time fatal that no `catch` stops: keep overrides to the ones `cover.php` uses. All 19 files were linked against Elementor 4.4.0's real base classes on 16.11: no such error.)* | §3.1 | no |
| A control whose `condition` is not met reads as **`null`** (Elementor's behaviour, now also the stub's); `{id}_alt` of an image is hidden when the image is decorative | §3.4 | read settings with a cast (`(string) ( $s[ $k ] ?? '' )`) — every package already does |
| New helpers: `NH_Text::state()`, `link_tokens()`, `unresolved_tokens()`; `NH_Html::resolve_token()`, `admin_note()`, `ICONS`, `TOKENS`; `NH_Forms::halt()`, `message()`, `STATUSES`, `CODES`, `MAX_FILE`; `nh_h2()` option `id` | §3.3–3.11 | optional |
| `display_toggles()` takes HTML labels (`NH_Text::pair()` output) or `[ full, plain ]` | §3.6 | no (chrome already passes pair output) |
| The compat rules of base.css moved up (row 32 after row 5): plain links in components were underlined | §9.1, §10.1 | no — but remove any workaround added for underlined `.v3-roles` / class-less links |
| `nh-forms.js` posts to `form.getAttribute('action')`: `form.action` is shadowed by the hidden input named `action` | §6.4 | any widget script reading a form's properties: use attributes |
| `cover.js` also registers under `page-cover`; the page cover needs only `SCRIPT_DEPS ['nh-w-cover']` | §4.1 | residence: no own morph script |
| Over-long form values are cut server side; the CV is checked as `cv.<ext>`; `is_uploaded_file()` behind `nh_forms_is_uploaded_file` | §6.2 | no |
| compare.py: exact line format; `expect_diff` entries **must be regular expressions** (prose fails); whitespace-only text compared only in running text; selector retried without its tag | §11.4 | write the fixtures' `expect_diff` as regexes against the printed lines (`compare.py <id> --all` shows them) |
| Harness: spreads = widgets whose root is a `<section>`; svg may rely on an `aria-hidden` parent; links without `href` are warnings; the «מקטע» section must be last; URL controls must have `'options' => false` | §11.2 | fix what run.php reports |
| Build: synced files exempt from content patterns; `README-dev.md` not zipped; `--no-harness`; `--phpcs` without the short-array and file-name sniffs | §11.1 | no |

**State of the other packages' work in progress, as the core harness saw it (16.11, 07:40):** all 19 widget files load and register; `php plugin/tests/run.php` passes for all 73 renders (one warning: `related/edge` renders a link without `href`); both assembled pages build with all 23 slots. `compare.py` with no unexpected difference: cover, plainbox, day, years, steps, glance, gallery, features, stories, related, page-cover, kaf, homes. Still to express as `expect_diff` regexes (the differences themselves are the documented expected ones): door (its entries are prose, not regexes), masthead (root tag `div`, D3), colophon (root tag; e-mail item), samekh (external-link `rel`/screen-reader suffix on the items 3–4 links), voices (the moved `style` of `.nh-btn-row`), lamed (role and button hrefs).

**Pre-install review fixes (16.11.2026).** Behaviour that changed after two independent reviews, before the first installation on the test site. Visitors' renders of all 73 fixture variants are byte-identical before and after.

| what changed | where | does a widget package need to act? |
|---|---|---|
| base.css: pages that are not built from our spreads get their layout back. Hello's reset.css and theme.css stay off on **every** page (D4 unchanged — the masthead, the colophon and the cookie banner are on every page, and Hello's reset would restyle their buttons and focus rings), so base.css now gives Hello's default templates (404, search, archive, a page in the block editor such as the privacy policy) the container theme.css gave them (`body:not([class*="elementor-page-"]) .site-main`: gutter on phones, 50rem column) and gives `.page-content`, `.entry-content` and Elementor's Text Editor / Post Content widgets `> * + * { margin-block-start: 1em }` (zero specificity) in place of the margins `* { margin: 0 }` removes | §10.1, D4 | no (none of these classes is ours) |
| `nh-forms.js` never posts a form less than **3.5 s** after the time token in it reached the page (the fresh token of the first focus, else the page's own), waiting with the button disabled: the server still answers `sent` and sends nothing for a token younger than 3 s (§6.2 step 4), which silently lost the enquiry of a visitor who autofilled and sent at once | §6.4 | no |
| `NH_Forms::state()` keeps from `nh_err` only the field keys of the two schemas (was: any `[a-z_]{1,20}`), so a crafted link cannot put its own words in the error summary | §6.2, §6.3 | no |
| The CV temp file is deleted only when it is an upload (`is_uploaded_file()`, filter `nh_forms_is_uploaded_file`); a path that is not an upload is never touched (was: deleted on every path, before any check) | §6.2 step 9 | no |
| Rate limit: an IPv6 client is counted by its /64 network, an IPv4-mapped IPv6 address as its IPv4 address (key = salted hash of that, as before) | §6.2 step 5 | no |
| Settings → נווה האירוס: the readiness list runs the widget discovery itself when Elementor is OK, so a skipped widget file shows there (Elementor builds no widget list on that screen) | §3.1, §7 | no |
| Colophon: the editors-only note of an unresolved credit link (`dev_link` = a link token) follows `<p class="v3-dev">` instead of breaking it, and names the link | §4.19 | no |
| Plugin header `Update URI: false`: WordPress.org never offers an update of another plugin with the slug `nevehairus-site` | §3.1 | no |

**v3.2 — the plugin is a guest (B1, 16.11.2026; [research/07-calm-v3.2.md](../research/07-calm-v3.2.md) §12, §15 column B step 1).** Version 0.2.0. One line per behaviour change; "B2" = the next step (widgets, §13).

| what changed | where | does a widget package need to act? |
|---|---|---|
| `get_style_depends()` = `nh-base` + `STYLE_DEPS` + `nh-w-<ID>`; `get_script_depends()` = `nh-core` + `SCRIPT_DEPS` + `nh-w-<ID>`: the core CSS and JS (with `window.nhData`) travel with every widget in Elementor's `_elementor_page_assets` | §3.3, §2.4; 07 §12.1 | no |
| `NH_Assets::enqueue()` (`wp_enqueue_scripts` @20) registers every handle on every front-end page but enqueues tokens/base/core only when `NH_Site::uses_widgets()`; `window.nhData` is attached to `nh-core` in `register()`, so it prints only with it | §3.2; 07 §12.1 | no |
| New `NH_Site::uses_widgets()`: per request, remembered; admin false; Elementor preview true; true when the Theme Builder header/footer shown on the request (Pro's `get_documents_for_location()`, every call guarded, skipped without Pro, not on `elementor_canvas`) or the requested post (`is_singular()`) holds `"widgetType":"nh-…"` in its raw `_elementor_data`; before `wp_enqueue_scripts` it answers false without remembering (Pro caches its conditions per request). Helpers `has_widget( $post_id )`, `chrome_documents()` | §3.10, §5; 07 §12.2 | no |
| `head()`, `body_class()` (`v3`), `body_open()` (the sprite only), `footer()` (sprite fallback, cookie panel, live region) print nothing on a page that does not use our widgets; parts queued with `footer_once()` still print (a widget that queued one has rendered) | §5.1–5.4; 07 §12.3 | no |
| Pre-paint script: no `html.js`, no `pagereveal`/`nh-vt-in`/`data-vt` (page transitions are gone); `calm_default()` reads the post only on `is_singular()` | §5.2; 07 §12.3 | no (B2 removes `data-vt`) |
| Removed from the plugin (now the child theme's, §12.5): the skip link, `<main>` on Elementor's Full Width template and its hooks, the template memory (`template_include`); removed altogether: the mobile visit bar | §5.1, D1, D2; 07 §12.3 | no |
| `cookie_banner()` → `cookie_panel()`: the §9 markup (`section.nh-cookies.nh-on-dark`, hidden), printed in `wp_footer` right after the footer, never shown by itself, not in the preview; buttons «שמירת הבחירה» / «סגירה» | §5.3–5.4, D1; 07 §9 | no |
| `js_data()` i18n without `motionOn` / `motionOff` | §5.6 | no |
| New `NH_Html::current_state( $url, $ids )`: `'page'` (this very post), `'true'` (the section: the post or an ancestor in `$ids`, or the URL of an ancestor), `''` — always `''` outside `is_singular()`; `is_current()` is its wrapper (the archive bug is gone) | §3.6; 07 §5.3, §12.3 | B2: masthead prints `aria-current` from it |
| `NH_Html::display_toggles( $labels, $states = [] )`: two switches (plain, calm), `span.v3-toggle__label` + silent `span.v3-toggle__state[data-on][data-off]` (fallback «פועל»/«כבוי»); a `motion` label is ignored | §3.6; 07 §7.3 | B2: masthead and display pass `state_on`/`state_off` |
| `NH_Controls::section()` adds only `anchor` (`running`, `running_plain` ignored); `nh_section_open()` writes no `data-header`, `data-running`, `data-running-plain` (option `header` ignored); `nh_rule()` takes no argument and never writes `v3-rule--draw`; run.php now fails a spread that still has those attributes | §3.3, §3.4, §11.2; 07 §12.3 | B2: drop `running` defaults and fixture settings; cover/page-cover drop the rule |
| Settings: removed `show_bar`, `bar_*`, `skip_label`, `cookie_more`, `cookie_all`, `cookie_necessary_only`, `cookie_settings`; added `cookie_close` (סגירה); defaults `cookie_title` הגדרות עוגיות, `cookie_stats` '', `webp_subsizes` 0, `cookie_text`/`cookie_text_plain`/`cookie_necessary` = the §9 texts; the settings page follows | §7; 07 §9, §12.3 | no |
| `NH_Settings::migrate()` on `init` @5 (before `register_setting()`), once while option `nh_version` < 0.2.0 or missing: removed keys deleted, untouched 0.1.0 defaults (`cookie_title`, `cookie_stats`, `cookie_text*`, `cookie_necessary`) replaced, `webp_subsizes` 1 → 0, edited values kept; then `nh_version` = 0.2.0; any version change also clears Elementor's caches | §7; 07 §12.3 | no |
| `NH_Compat`: no Hello or Google Fonts filter any more (→ the child theme); `image_editor_output_format` hooked only when `webp_subsizes` is on | §5.1, D4, D10; 07 §12.3 | no |
| Activation and deactivation hooks: `NH_Plugin::clear_elementor_cache()` = `\Elementor\Plugin::$instance->files_manager->clear_cache()` (checked in 4.4.0; guarded): no cached HTML of our widgets outlives the plugin; `uninstall.php` also deletes `nh_version` | §3.1; 07 §12.3, §12.6 | no |
| `nh-core.js`: gone — the motion switch and `nh:motionchange`, the banner's self-show, `ResizeObserver`, «מה זה אומר?», the visit bar, the skip-link repair, `showBanner`; new — `nh.reveal()` (the one appearance, on `.v3-print[data-reveal] > picture`), the panel (`[data-cookie-settings]` or a link to `#nh-cookies`; Save / Close / Esc return focus to the link; `nh.consent = { read, save, openSettings, hideBanner }`; `read()` ignores older consent versions), `[data-visit-cta]` → this page's `.v3-door[id]`, focus on the target's heading after an in-page link (same page by origin, path and query; after the menu dialog's `close`; on a load with `#…` after `load`), keepPlace by `:is(.v3-spread, .v3-plainbox-wrap) …`, `focusPage()` → `main`. `nh.once` stays, deprecated, until door.js and samekh.js are deleted | §3.12, §5.5; 07 §4.2, §5.5, §9, §12.3 | B2: delete door.js, samekh.js, then `once`; masthead.js sets `menu.dataset.nav` |
| base.css: nothing global — element rules scoped with `:where([class*="elementor-widget-nh-"], .nh-cookies)` (zero specificity); `html`/`body`, the skip link, the Hello fallback layout, smooth scroll, view transitions, the visit bar, `.nh-wordmark` and the global reduced-motion rule removed; v3.2 type tokens (§7.1, with `--v3-poster`/`--v3-lead` kept as aliases for cover.css until the port), buttons (§7.2), switches (§7.3), the panel (§9), «honey» citrus, `.v3-spread` paper ground and `--v3-pad`, the 4:3 print and the reveal (§4.2, §10.2 item 4), the 16px step for notes, captions, quote signature and role meta | §9.1, §10.1; 07 §12.4 | B2/B4: drop the aliases once cover.css is ported; masthead/colophon lose `.nh-wordmark` |
| masthead.css: `html:has(.v3-mast) { scroll-padding-block-start: 6rem }` and its `max-height: 30em` variant (moved from base.css) | §9.1; 07 §12.4 | no |
| `assets/css/tokens.css` synced from the prototype (the A track's v3.2 tokens: field #4F5550, 16px minimum, radius 2px, 150/320 ms, no `--nh-ease-in-out`, `--nh-dur-3/4`) | §11.1; 07 §4.1, §7 | B2: the v3.1 motions in cover/door/samekh.css lose their easing and stop |
| Harness: the fake media library loads the logo record (`file`, no `widths`) as one PNG; picture tests take the widths from the manifest; `get_option()` returns the registered default only without an explicit default (as WordPress); test pages are built as the site prints them with the child theme (`main#content.site-main`, skip link, style.css after ours, body class from NH_Site); new suite `core/scope.test.php` | §11.2, §11.5 | no |
| The child theme (`theme/hello-elementor-child`, 1.1.0) now owns what applies to the whole site: Hello's reset/theme CSS, skip link and Elementor-page title off, Elementor's Google Fonts off, the skip link (`wp_body_open` @5), `main#content.site-main` on Elementor's Full Width **and** Canvas templates, `body`, the focus ring (zero specificity, so the widgets' `--nh-focus` wins), underlines of class-less links, the footer's links, the system reduced-motion rule; style.css after `elementor-frontend`. Install it together with 0.2.0 | D2, D4, §10; 07 §12.5 | no |

**v3.2 — the widgets lose their motion and their removed controls (B2, 16.11.2026; [research/07](../research/07-calm-v3.2.md) §5, §10, §12.6, §13, §15 column B steps 2–3).** Version 0.2.0 (not yet released). One line per behaviour change; "B4" = the port of the widgets' markup and CSS to the finished prototype (§15 B4).

| what changed | where | does the next package need to act? |
|---|---|---|
| Widget scripts deleted: `cover.js`, `homes.js`, `kaf.js`, `samekh.js`, `door.js`, `gallery.js`; `nh.once` removed from nh-core.js; `page-cover` has no `SCRIPT_DEPS`. Left: `masthead.js` (the menu), `voices.js` | §4, §9.4; 07 §4.4, §13 | no |
| Every widget: `NH_Controls::section( $this, [ 'anchor' => … ] )` only (no `running` default) | §4.0; 07 §12.3 | no |
| `cover`, `page-cover`: no `weight` (constant 600), no ruling line, no `#nh-letters`, no `data-vt`; new `show_call` + `call_label` (the line «או בטלפון» with the phone of the settings, `p.v3-cover__call`), `DYNAMIC` true, the button with `data-visit-cta`; cover `photo_pos` `46% 50%`; page-cover crumb 2 «הבתים שלנו» | §4.1, §4.2; 07 §10.2 item 1, §10.3 item 1 | B4: the v3.2 cover CSS (one screen, D-L title, `--v3-poster`/`--v3-lead` aliases out) |
| `homes`: no chooser (`fit_q`, `fit`), `intro` → `lead` (full text added), rows without `weight` (600) and `data-vt`, links default `''`; a row without a page is `div.v3-home__row` with «דף מפורט בקרוב» (`soon_text`), without arrow | §4.3; 07 §10.2 item 2 | B4: the v3.2 sizes; homes.css only lost its motion and lays out `.v3-home__row` |
| `day`, `years`, `gallery`: prints are `figure.v3-print[data-reveal]` of one size (`SIZES`); no `print` select; day without zigzag classes; years with the ruling line and no `v3-year--first` (years.css reads `:first-child` until B4) | §4.5, §4.8, §4.14; 07 §4.2, §8.1 | B4: the v3.2 grids |
| `kaf`: one work (`photo`, decorative, `_pos`, `_sizes`), a visible `caption`, the ruling line; no `works`, swatches, `cap_label`, `works_label`, `credit`, `weight` (440); the button only with a link that is not this spread (an editors' note otherwise); kaf.css ported from the prototype's v3.2 block | §4.6; 07 §10.2 item 5 | no |
| `samekh`: no rings, glint, links, `ring`, `weight` (380), `external_sr` | §4.7; 07 §10.2 item 6 | B4: sizes |
| `voices`: the ruling line, a visible `v3-h2` (heading with a citrus tessera), `play_label` («צפו בסרטון») on a paper button with an explicit 1.25rem icon, `play_prefix` = the screen-reader text after it (`:`), poster `sizes` for 37.5rem; voices.css: the button, 16:9 | §4.9; 07 §10.2 item 8 | no |
| `steps`: no `word`; `lamed`: no `weight` (600) | §4.10, §4.11 | B4: sizes, the ל inside its own padding |
| `gallery`: no lightbox (`zoom_label`, `lb_*`, `TEXTS`, `footer_once`, `data-*`), `DYNAMIC` false, the grid of prints with visible captions; gallery.css ported from the prototype's v3.2 block; default 6th caption «פינת אוכל וסלון באגף החדש.» | §4.14; 07 §10.3 item 4 | no |
| `related`: defaults «שלושת הבתים» (`/#homes`) and «ידיים שיוצרות» (`/#made`); a row without a link, with a bare fragment or with a link to this very page is not printed (editors' note) | §4.17; 07 §10.3 item 8 | no |
| `masthead`: rewritten per `partials/header.html` — `name` (two spans at the last space) + `logo` (the emblem, `alt=""`), at most 5 links (editors' note for more), `aria-current` = `NH_Html::current_state()` (`page` / `true`), `data-visit-cta` on both visit buttons, visible «תפריט» and «סגירה», a menu title (`aria-labelledby="nh-menu-title"`), `nh-menu__call`, the switches `plain` + `calm` with `state_on` / `state_off`; no wordmark, running head, progress line, display panel, `toggle_motion`; masthead.css = the prototype's v3.2 masthead and menu; masthead.js = the menu with `menu.dataset.nav` | §4.18; 07 §5, §6, §10.1 | no |
| `colophon` deleted (widget, CSS, fixture); new widget `display` (מתגי תצוגה): the switches group for the footer of Elementor's own widgets | §4.19, §4.21; 07 §12.6, §13 | no |
| `NH_Html::display_toggles()` prints only the switches whose key is in `$labels` | §3.6 | no |
| base.css: `.nh-btn` padding `.5rem 1.75rem` and `.nh-btn--sm` without its own font size (UI 18/700, as the prototype's §7.2); the dead `.v3-menu-toggles` / `.v3-footer-toggles` rules removed; forms.css: the fields' transition only `border-color .15s ease` (§4.1) | §9.1; 07 §4.1, §7.2 | B4: the rest of forms.css §7.5 |
| CSS motion removed from the widgets' files: cover (pinning, drawn rule, curtain, settling photo, `@keyframes`), page-cover and homes (`view-transition-class`), homes (curtain → opacity, the arrow's shift), samekh (rings, glint), years (cloud drift), door (`@property --open`, the lintel), gallery (zoom), voices (the round icon's scale), steps (the word, the staircase) | §4; 07 §4.4 | no |
| Build check: forbidden motion in the plugin's CSS and JS (§11.1) | §11.1; 07 §17.1 | no |
| Harness: `style` only on `.v3-room`; fixture keys `queried`, `empty`; pages.php without the colophon, with `display` in the footer slot and `queried` 11 for the house page; `NH_TEST_GLOBAL_IDS` trimmed; compare.py descendant selectors and `--prototype-letter-data`; `letters.test.php` compares `data_script()` with the build script's `letter_data()`; `site.test.php` uses the masthead for the script handle; door and lamed `expect_diff` are regexes now (compare clean) | §11.2, §11.4, §11.5 | no |
| `shots.mjs --behaviour`: the v3.2 checks (41, passing in Chrome) | §11.4 | no |
| Step 4 (`scripts/build_pages.py`, `templates/pages/*.json`): the header is the masthead `prototype` variant with the emblem `@media:logo-emblem` and `current_ids` on «הבתים שלנו»; the footer is Elementor's Image (the emblem), Heading and Text Editor in inner containers (grid 5fr 3fr 4fr / 2 / 1) plus `nh-display`, its template HTML Tag `footer`; `lint()` allows those only in the footer; the render check compares the masthead and `nh-display`; `scripts/test_site.py pages` uploads the emblem PNG (`nh:logo-emblem`, alt `''`), resolves the footer's link tokens and `@nh:email` from the plugin's settings on deploy and checks each template's wrapper | D3, §10.3; 07 §11.2 item 5, §12.6 | no |
