=== נווה האירוס — אתר ===
Contributors: koranga
Tags: elementor, hebrew, rtl, accessibility
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Elementor widgets and site module of the v3.1 design «אותיות שגרים בהן» of מעון נווה האירוס.

== Description ==

A private plugin for one site. It provides:

* Elementor widgets (category «נווה האירוס»): the spreads of the approved prototype v3.1, each with
  every text editable and an optional plain-language twin («בשפה פשוטה»).
* The site module: display modes (plain language, calm mode, reduced motion) applied before the
  first paint, the skip link, the cookie banner (consent stays in the visitor's browser), the mobile
  visit bar and the live region for announcements.
* The visit and job-application forms: sent with wp_mail() to recipients set in
  Settings → נווה האירוס (empty by default: then the forms do not send). Nonce, honeypot, signed time
  token and a rate limit; nothing a visitor types is stored. A CV is attached to the e-mail and never
  saved in the media library.

Requirements: WordPress 7.0+, PHP 8.0+, Elementor 3.24+ (tested with Elementor and Elementor Pro 4.4.0),
theme Hello Elementor with a child theme, pages in the «Elementor Full Width» layout.

No external services, no CDN, no tracking. Fonts are served from the plugin.

== Third-party resources ==

* Frank Ruhl Libre (Yanek Iontef), variable font, subset — SIL Open Font License 1.1,
  see assets/fonts/OFL-FrankRuhlLibre.txt.
* Assistant (Ben Nathan; based on Source Sans by Adobe), variable font, subset — SIL Open Font
  License 1.1, see assets/fonts/OFL-Assistant.txt.
* The letter outlines in assets/letters.json are drawn from Frank Ruhl Libre (same licence).

== Changelog ==

= 0.1.0 =
* First version: core (site module, forms, settings, letters, pictures) and the widgets of v3.1.
