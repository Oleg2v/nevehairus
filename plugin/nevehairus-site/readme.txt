=== נווה האירוס — אתר ===
Contributors: koranga
Tags: elementor, hebrew, rtl, accessibility
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.2.3
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

= 0.2.3 =
* Detail fixes after the review of 16.11 (the same in the prototype): the door's phone number fits its column at
  1024–1100px and its focus ring clears the lines around it; the consent box is drawn like the fields (frame, hover,
  error, tick) and sits on the middle of its label's first line; its error goes under the label; its asterisk is red
  and never alone on a line; the form's button lightens on hover; the alerts keep their own title type; links in
  running text get a tight ring; the form box spans the column on tablets.
* The ב and כ photos end on the base's top line (no step at the open side); the ד and the ל stand in front of their
  rules; the job rows' arrows stand on the meta's line, and keep the last column in plain language.
* The homes' facts break only between two facts, with no «·» at a line's start or end; rows without a page have no
  empty arrow column; on narrow phones the ב is smaller; titles of two lines break evenly.
* Day and steps cells share their rows; the stats and the feature list lose the hairline that doubled the next
  rule; prints fill the column on phones up to 27em; a spaced dash never starts a line.
* Plain language: the box's sentence keeps its distance to the button, the homes' note its distance to the rule, the
  homes' sentences the gap between plain paragraphs; the consent sentence is at the plain step; «כן, לפתוח את
  הסרטון» on the video's button.
* The video's focus ring goes round the paper button; the consent buttons are of one width; the menu's «סגירה» has
  the footprint of «תפריט» and its texts one edge; the switches' labels start on the column's edge and underline on
  hover; the breadcrumbs' «‹» points along an RTL trail; a spread reached by an anchor starts right under the
  masthead (also for signed-in users); a focused element at the bottom keeps its ring on screen; the site's name
  breaks «מעון / נווה האירוס» on phones; «יש / על מי לסמוך.».

= 0.2.2 =
* Our photos keep valid markup on the site: no attribute twice after the content filters of WordPress and
  Elementor (loading, fetchpriority), and their own sizes (no "auto").
* The widget «כ׳ · ידיים שיוצרות» is never served from Elementor's element cache (it reads the page and the user).
* A page whose content does not start with a cover (a policy, a post, search, 404) starts below the fixed masthead.
* Plain language on a page without a plain version says so instead of announcing a change; the video's «כן»
  is described by its consent question for screen readers.

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
