=== נווה האירוס — אתר ===
Contributors: koranga
Tags: elementor, hebrew, rtl, accessibility
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Elementor widgets and site module of the design «אותיות שגרים בהן» (v3.2, calm) of מעון נווה האירוס.

== Description ==

A private plugin for one site. It provides:

* Elementor widgets (category «נווה האירוס»): the spreads of the approved prototype v3.1, each with
  every text editable and an optional plain-language twin («בשפה פשוטה»).
* The site module: display modes (plain language, calm mode) applied before the first paint, the
  cookie-settings panel (opened only from «הגדרות עוגיות»; consent stays in the visitor's browser) and
  the live region for announcements.
* A guest on the site: its CSS, scripts and page parts load only on pages that use its widgets (in the
  page itself or in the Theme Builder header or footer). Nothing global is overridden; the site-wide
  parts (skip link, page layout, Hello's CSS off) belong to the child theme.
* The visit and job-application forms: sent with wp_mail() to recipients set in
  Settings → נווה האירוס (empty by default: then the forms do not send). Nonce, honeypot, signed time
  token and a rate limit; nothing a visitor types is stored. A CV is attached to the e-mail and never
  saved in the media library.

Requirements: WordPress 7.0+, PHP 8.0+, Elementor 3.24+ (tested with Elementor and Elementor Pro 4.4.0),
theme Hello Elementor with its child theme hello-elementor-child 1.1.0+ (install both together), pages in
the «Elementor Full Width» layout.

Switching the plugin off clears Elementor's caches: the site keeps working, the pages built from these
widgets show nothing until it is switched on again; the settings are kept.

No external services, no CDN, no tracking. Fonts are served from the plugin.

== Third-party resources ==

* Frank Ruhl Libre (Yanek Iontef), variable font, subset — SIL Open Font License 1.1,
  see assets/fonts/OFL-FrankRuhlLibre.txt.
* Assistant (Ben Nathan; based on Source Sans by Adobe), variable font, subset — SIL Open Font
  License 1.1, see assets/fonts/OFL-Assistant.txt.
* The letter outlines in assets/letters.json are drawn from Frank Ruhl Libre (same licence).

== Changelog ==

= 0.2.1 =
* Asset URLs carry the file time, so a deploy never leaves an old CSS or JS in a browser's cache.
* The door spread: a plain ד beside the heading instead of the stretched one; containers with our widgets never wrap.

= 0.2.0 =
* v3.2 boundaries: assets and page parts only where the widgets are; nothing global; the skip link and
  main moved to the child theme; Elementor's caches cleared on activation, deactivation and update.
* The cookie banner became a settings panel that never opens by itself; the mobile visit bar is gone;
  the reduced-motion switch is gone (the system setting and calm mode remain).
* Settings migrated once from 0.1.0 (removed keys deleted, untouched old defaults replaced).

= 0.1.0 =
* First version: core (site module, forms, settings, letters, pictures) and the widgets of v3.1.
