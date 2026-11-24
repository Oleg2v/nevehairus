<?php
/**
 * Child theme of Hello Elementor for the site of מעון נווה האירוס.
 *
 * What applies to the whole site lives here, not in the nevehairus-site plugin, so it stays when the plugin
 * is switched off (research/07-calm-v3.2.md §12.5; the plugin only styles its own widgets):
 * - Hello's reset.css and theme.css, Hello's skip link and, on pages built with Elementor, Hello's page
 *   title are off; Elementor's Google Fonts are off (the fonts are served from the plugin);
 * - one skip link, first in Tab order, to `#content`;
 * - `<main id="content">` around the content of Elementor's "Full Width" and "Canvas" templates (Hello's own
 *   templates — single, archive, search, 404 — already print `main#content`), so the skip link has a target
 *   on every template;
 * - style.css, printed after Elementor's frontend CSS;
 * - the page title's separator «|» (as the prototype's titles: «מעון ראשי — בית לחיים | מעון נווה האירוס»);
 * - no Elementor element cache while the nevehairus-site plugin cannot register its widgets (switched off, skipped
 *   for one request by the test site's safe switch, or unable to run): that cache is shared by every visitor and
 *   keeps the HTML of the plugin's static widgets, which would otherwise print as raw markup without their CSS and
 *   JS, and a page rendered without the plugin would be cached for the visitors who have it.
 * 1.2.0, the v4 pages built from Elementor's own widgets (research/09-v4-home-spec.md §5):
 * - the font Assistant from the theme (assets/fonts, assets/css/fonts.css) wherever the plugin's tokens.css is not
 *   printed, with a preload of its Hebrew subset;
 * - assets/css/v4.css (every rule scoped to the v4 classes, so the v3.2 pages do not change) and assets/js/v4.js
 *   (deferred): the accessibility menu, the «בשפה פשוטה» toggle of the v4 header, the header's small needs;
 * - a tiny inline script in the head that applies the stored display choices before the first paint;
 * - on wp_footer, on every page but the Elementor editor: the floating «נגישות» button with its panel, the green
 *   call button for phones and a polite live region.
 * 1.3.0, v4.1 «more life» (research/10-v4.1-home-spec.md §1–§3): the full-screen hero, the floating header
 * (transparent over the hero, solid from 24px of scroll), the new blocks' look, the soft motion (fade-ins, the
 * hero's slow zoom, the cards' lift; none under prefers-reduced-motion) — all in v4.css and v4.js — and here the
 * panel's «עצירת אנימציות» and the head script's `nh-js` and `nh-a11y-still`.
 * 1.4.0, v4.3 (research/11-v4.3-home-spec.md, Itzik 18.11):
 * - «בשפה פשוטה» is the first option of the «נגישות» panel (it left the v4 header), shown only on a page with plain
 *   texts; the same mode and stored choice as before;
 * - our cookie settings «הגדרות עוגיות» on EVERY page (the theme declares `nh-cookies`, so the plugin, which printed
 *   them only beside its widgets, steps aside): a banner on the first visit (only necessary cookies by default), the
 *   settings, and a question in place before an Elementor video loads YouTube while embedded content is not allowed;
 * - the contact form of the v4 home (Elementor Pro's Form): its texts for the theme's error summary, the server-side
 *   check of the phone and the consent, and a rate limit.
 * 1.4.1 (18.11, after the checks and the independent review on the test site):
 * - the form: a stored submission holds only the visitor's answers (its HTML fields are taken out of the record);
 *   a phone number with spaces or other dashes is normalised before Elementor Pro's own check; the send limit is
 *   a fixed 10-minute window and answers with one message;
 * - the consent version is the plugin's setting + 1, so every raise of the setting asks everyone again;
 * - no banner where the browser blocks site data (nothing could be kept there).
 * 1.4.2 (18.11, after the check of the fixes): a valid phone number is kept as its digits; on phones the «נגישות»
 * panel opened over the banner stands beside the floating column, the column and the panel keep below the v3.2
 * pages' fixed masthead too, and a banner too tall for the column stands beside it (v4.js, v4.css).
 * 1.4.3: the form's check runs after Elementor Pro's own (priority 20), so a send its trap refused is not counted;
 * «להמשך קריאה»: its open region is named by its summary and has no frame; phone numbers in texts never break.
 * 1.4.4–1.4.6 (v4.4, research/12-v4.4-home-spec.md, Itzik 19.11): v4.css and v4.js only (the life photo's corners,
 * the snoezelen phone's width, the message field's height, the hero shades' reach as variables, the «הבית שלנו»
 * videos' play button off the face).
 * 1.4.7: the hero's landscape photo gets a `sizes` that follows the screen's height where the photo is shown by it
 * (phones held upright, most desktops), so the browser picks a file big enough; the shades' edges are eased (v4.css).
 * 1.4.8: the hero shades' defaults weigh nothing (:where), so a photo's own values in the hero's Custom CSS apply.
 * 1.4.9: the snoezelen phone's cover sits in its screen, so the bezel shows (v4.css).
 * 1.5.0–1.5.1 (the inner pages, Oleg 23.11): v4.css and v4.js only (the inner pages' blocks: text, photo, quote,
 * gallery, table, the products' grid, the map that loads on a click).
 * 1.5.2: no emoji script on the site's pages: where a device has no emoji font it fetched pictures from s.w.org.
 * 1.5.3 (batch 3): v4.css only (the posts' archive cards, a post's video, the sitemap's list, the marker of a
 * missing fact).
 * 1.5.4 (Oleg 23.11): «הקראה» in the «נגישות» panel: the page read aloud by the browser's own Hebrew voice (v4.js 9).
 * 1.5.5: after the independent review of «הקראה» (v4.js 9); <main id="content"> around Pro's archive template, so the
 * category pages have the skip link's target; a photo smaller than its column keeps its own size (v4.css).
 * 1.5.6: a photo smaller than its tile fills the tile in a photo grid (v4.css).
 * 1.5.7 (Oleg 23.11, after an external check): ≤1024px the floating buttons are a labelled bar at the foot (v4.css);
 * the header's «צור קשר» reaches the page's own form or the contact page (v4.js 10); a photo grid browses as one
 * slideshow and the lightbox never shows a media title «nh4:…» (v4.js 11, and nh_child_media_title here); «הקראה»
 * looks for the voices again when the panel opens (v4.js 9).
 * 1.5.8: from 1025 to 1415px a section's column ends clear of the round «נגישות» button (v4.css).
 * 1.5.9 (after the independent check of 1.5.7): the bar only on screens at least 501px high, a short one keeps the
 * column and the text a gutter (v4.css); the lightbox's arrows (Elementor's Swiper styles on pages with a photo grid,
 * nh_child_swiper_style), its alt, Hebrew arrow names, focus, our parts hidden under it (v4.js 11); a focused form
 * field and its hint above the bar (v4.js 12); the header's «צור קשר» focuses the form's first field (v4.js 10).
 * 1.5.10: with the bar the cookie banner opens under the header, so the first screen's text stays seen (v4.css).
 * 1.5.11: the header hidden under the lightbox, its counter «1 / 86» (v4.css).
 * 1.5.12: the independent re-check of 1.5.11: on the inner pages up to 1024px the cookie banner above the bar again,
 *         not over the header; the banner's focus code moves only what the keys focus (a tap on a photo half under it
 *         was lost); a focused field with its label; the round button's room up to 1479px; a one-photo set without
 *         «1 / 1»; the lightbox watched however it opens, with the focus inside; Back after the header's «צור קשר»;
 *         «הקראה» skips page numbers and reads what «להמשך קריאה» folds; one <main> on the archives (v4.js, v4.css).
 * 1.5.13: (Oleg 23.11) no share button in the enlarged photo (v4.css; Elementor's Site Settings → Lightbox → Share is off,
 *         set by `test_site.py tidy`); its arrows as Hebrew reads: «next» at the left, «previous» at the right (v4.css).
 * 1.5.14: the third independent check: Enter and Space work on the enlarged photo's «הגדלה» and «מסך מלא», white focus
 *         rings, Windows high-contrast icons, the page behind not left scrolled, full-screen state kept right (v4.js 11,
 *         v4.css); a mouse click on the consent box no longer lost, the banner not thrown off by a lightbox (v4.js 5, 12);
 *         autocomplete="email"; the skip target's tabindex on the 404 page; the nh4 marker never printed into the page
 *         (this file); no role="list" on an empty list; no empty band under the footer on a landscape phone; the round
 *         button's room up to 1494px; a tall banner never over the header of an inner page.
 * 1.5.15: (Oleg 24.11, from his iPhone) the enlarged photo's arrows never lie over the photo: up to 1024px they are two round
 *         buttons under it, from 1025px the photo keeps side room for them (v4.css); its focus rings show only after a
 *         key press, not after a touch (html.nh-kbd, v4.js).
 * 1.5.16: (Oleg 24.11) no author archives (/?author=1, /author/<name>/ go to the home page: the user's login name showed);
 *         a category without posts answers 404 (the default «Uncategorized» is empty).
 * 1.6.0: (Itzik 24.11) an invisible guard on every form (honeypot, a minimum fill time, a signed token fetched by the page's script:
 *         nh_child_form_guard and /wp-json/nh/v1/form-token, v4.js part 13); the CV form of «דרושים» in a dialog (v4.js part 14);
 *         the headings of the inner pages 15% smaller and the contact form 15% smaller (v4.css); no map on «צור קשר».
 * 1.6.1: (Itzik 24.11) the job cards of «דרושים» 15% smaller; the static map picture of «צור קשר» (v4.css).
 * Verified against Hello Elementor 3.5.2 and Elementor 4.4.0 (16.11.2026).
 *
 * @package hello-elementor-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'hello_elementor_enqueue_style', '__return_false' );
add_filter( 'hello_elementor_enqueue_theme_style', '__return_false' );
add_filter( 'hello_elementor_enable_skip_link', '__return_false' );
add_filter( 'hello_elementor_page_title', 'nh_child_page_title', 20 );
add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
add_filter( 'document_title_separator', 'nh_child_title_separator' );
add_filter( 'pre_option_elementor_element_cache_ttl', 'nh_child_element_cache' );

add_action( 'after_setup_theme', 'nh_child_supports' );
add_action( 'wp_body_open', 'nh_child_skip_link', 5 );
add_action( 'wp_body_open', 'nh_child_cookies', 6 );
foreach ( array( 'header-footer', 'canvas' ) as $nh_child_template ) {
	add_action( "elementor/page_templates/{$nh_child_template}/before_content", 'nh_child_main_open' );
	add_action( "elementor/page_templates/{$nh_child_template}/after_content", 'nh_child_main_close' );
}
unset( $nh_child_template );
// 1.5.5: Pro's archive template (the category pages) is printed by Hello's index.php without <main>: wrap it, so the
// skip link has its target there too (Pro fires these only when it prints the location)
add_action( 'elementor/theme/before_do_archive', 'nh_child_main_open' );
add_action( 'elementor/theme/after_do_archive', 'nh_child_main_close' );

add_action( 'wp_enqueue_scripts', 'nh_child_styles', 20 );
add_action( 'wp_enqueue_scripts', 'nh_child_v4_assets', 30 );
add_action( 'wp_head', 'nh_child_v4_head', 2 );
add_action( 'wp_footer', 'nh_child_v4_footer', 10 );
add_action( 'elementor_pro/forms/validation', 'nh_child_form_validation', 20, 2 );   // after Pro's own (its trap): a refused send is not counted
add_filter( 'elementor_pro/forms/sanitize/tel', 'nh_child_form_tel', 20 );
add_filter( 'wp_get_attachment_image_attributes', 'nh_child_hero_sizes', 10, 2 );
add_action( 'init', 'nh_child_no_emoji' );
add_filter( 'the_title', 'nh_child_media_title', 10, 2 );
add_filter( 'elementor/widget/render_content', 'nh_child_widget_content', 10, 2 );
add_action( 'wp_enqueue_scripts', 'nh_child_swiper_style', 40 );
add_action( 'template_redirect', 'nh_child_no_author_archives', 1 );
add_action( 'template_redirect', 'nh_child_no_empty_category', 2 );
add_action( 'elementor_pro/forms/validation', 'nh_child_form_guard', 1, 2 );   // 1.6.0: the invisible guard of every form, before Pro's own checks
add_action( 'rest_api_init', 'nh_child_form_token_route' );

/**
 * `hello_elementor_page_title`: no Hello title on a page built with Elementor (its cover is the page's H1).
 * Only on a single post or page, and for the post being shown: on search results and archives get_the_ID() is the
 * first post of the list, and a first result built with Elementor took away the page's only H1 («תוצאות חיפוש
 * עבור: …», the archive's title).
 *
 * @param bool $show Show the title.
 * @return bool
 */
function nh_child_page_title( $show ) {
	if ( ! is_singular() ) {
		return $show;
	}
	$id = (int) get_queried_object_id();
	if ( $id > 0 && 'builder' === get_post_meta( $id, '_elementor_edit_mode', true ) ) {
		return false;
	}
	return $show;
}

/**
 * `document_title_separator`: «|» between the page's title and the site's name, as in the prototype.
 *
 * @return string
 */
function nh_child_title_separator() {
	return '|';
}

/**
 * `pre_option_elementor_element_cache_ttl`: Elementor's element cache is off on the front end while the
 * nevehairus-site plugin cannot register its widgets — it is switched off, skipped for one request by the test
 * site's safe switch (which does not deactivate it, so no deactivation hook clears the cache), or loaded but unable
 * to run (no PHP 8, an Elementor older than it needs). Elementor 4.4.0 keeps the rendered HTML of a widget that is
 * not dynamic in the post meta `_elementor_element_cache` for 24 hours and serves it to every visitor: without the
 * widgets that HTML (raw spreads, every text twice, giant letters) would be printed from the cache, and a page
 * rendered without them (empty spreads) would be cached for the visitors who have them. Not in the admin: the
 * Elementor settings page must show the stored value. With the widgets there, the stored value is kept.
 *
 * @param mixed $pre The value short-circuiting get_option() (false = none).
 * @return mixed
 */
function nh_child_element_cache( $pre ) {
	if ( is_admin() || ( class_exists( 'NH_Plugin', false ) && is_callable( array( 'NH_Plugin', 'elementor_ok' ) ) && NH_Plugin::elementor_ok() ) ) {
		return $pre;
	}
	return 'disable';
}

/**
 * `wp_body_open` @5: the skip link (Hello's own is off). Its target `#content` exists on every template.
 */
function nh_child_skip_link() {
	echo '<a class="nh-skip" href="#content">' . esc_html__( 'דילוג לתוכן הראשי', 'hello-elementor-child' ) . '</a>';
}

/**
 * `elementor/page_templates/{header-footer|canvas}/before_content`: Elementor's templates have no `<main>`.
 */
function nh_child_main_open() {
	if ( 1 === nh_child_main_depth( 1 ) ) {
		echo '<main id="content" class="site-main" tabindex="-1">';
	}
}

/**
 * `elementor/page_templates/{header-footer|canvas}/after_content`.
 */
function nh_child_main_close() {
	if ( nh_child_main_depth( 0 ) > 0 && 0 === nh_child_main_depth( -1 ) ) {
		echo '</main>';
	}
}

/**
 * 1.5.12: one <main> a page. An archive printed through the Full Width template ran both pairs of hooks (the
 * template's and the archive's), and the category pages had two nested <main id="content">: the inner pair now
 * prints nothing, and a close without an open neither.
 *
 * @param int $step +1 for an open, -1 for a close, 0 to read.
 * @return int How many are open after the step.
 */
function nh_child_main_depth( $step ) {
	static $depth = 0;
	$depth = max( 0, $depth + (int) $step );
	return $depth;
}

/**
 * `wp_enqueue_scripts` @20: style.css after Elementor's frontend CSS (Elementor registers `elementor-frontend`
 * on @5). Without the dependency the theme would print first and `.elementor a { text-decoration: none }`
 * (the same specificity as the theme's link rule) would win by order: the links of the footer and of the
 * Text Editor would lose their underline (WCAG 1.4.1).
 */
function nh_child_styles() {
	$deps = wp_style_is( 'elementor-frontend', 'registered' ) ? array( 'elementor-frontend' ) : array();
	wp_enqueue_style( 'nh-child', get_stylesheet_uri(), $deps, (string) wp_get_theme()->get( 'Version' ) );
}

/**
 * The theme's version (style.css), the version of every asset it prints.
 *
 * @return string
 */
function nh_child_version() {
	return (string) wp_get_theme()->get( 'Version' );
}

/**
 * True inside the Elementor editor's preview (the page the editor shows in its frame): no floating buttons and no
 * stored display choices there, the client edits the page as it is designed.
 *
 * @return bool
 */
function nh_child_is_elementor_preview() {
	if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only check of the request
		return true;
	}
	if ( ! class_exists( 'Elementor\Plugin', false ) ) {
		return false;
	}
	$preview = isset( \Elementor\Plugin::$instance->preview ) ? \Elementor\Plugin::$instance->preview : null;
	return is_object( $preview ) && method_exists( $preview, 'is_preview_mode' ) && $preview->is_preview_mode();
}

/**
 * `wp_enqueue_scripts` @30 (after the plugin's and the theme's own @20, so it knows what they enqueued):
 * - assets/css/fonts.css (Assistant from the theme) only where the plugin's tokens.css is not printed: it declares
 *   the same font from the plugin's folder, and two faces with different URLs would download the font twice;
 * - assets/css/v4.css after style.css (itself after Elementor's frontend CSS);
 * - assets/js/v4.js, deferred, no dependencies.
 * Version: the theme's (a deploy with a new version gives every file a new URL).
 */
function nh_child_v4_assets() {
	$ver = nh_child_version();
	$dir = get_stylesheet_directory_uri();
	if ( ! wp_style_is( 'nh-tokens', 'enqueued' ) ) {
		wp_enqueue_style( 'nh-child-fonts', $dir . '/assets/css/fonts.css', array(), $ver );
	}
	$deps = array( 'nh-child' );
	if ( wp_style_is( 'elementor-frontend', 'registered' ) ) {
		$deps[] = 'elementor-frontend';
	}
	wp_enqueue_style( 'nh-child-v4', $dir . '/assets/css/v4.css', $deps, $ver );
	wp_enqueue_script(
		'nh-child-v4',
		$dir . '/assets/js/v4.js',
		array(),
		$ver,
		array(
			'strategy'  => 'defer',
			'in_footer' => false,
		)
	);
	// v4.3: the texts of the contact form's checks (the plugin's settings, else its defaults)
	wp_add_inline_script( 'nh-child-v4', 'window.nhFormText = ' . wp_json_encode( nh_child_form_texts(), JSON_UNESCAPED_UNICODE ) . ';window.nhFormToken = ' . wp_json_encode( esc_url_raw( rest_url( 'nh/v1/form-token' ) ) ) . ';', 'before' );
}

/**
 * `wp_head` @2 (after `wp_enqueue_scripts`, which core runs at @1; before the stylesheets at @8): the preload of the
 * theme's Hebrew font subset (when fonts.css is enqueued) and the pre-paint script.
 */
function nh_child_v4_head() {
	if ( wp_style_is( 'nh-child-fonts', 'enqueued' ) ) {
		// No ?ver: the URL must equal the one fonts.css resolves to, or the font is downloaded twice.
		echo '<link rel="preload" href="' . esc_url( get_stylesheet_directory_uri() . '/assets/fonts/assistant-core-var.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	}
	wp_print_inline_script_tag(
		nh_child_prepaint_js(),
		array(
			'id'               => 'nh-v4-prepaint',
			'data-no-optimize' => '1',
			'data-no-defer'    => '1',
			'data-cfasync'     => 'false',
		)
	);
}

/**
 * The pre-paint script: the stored choices of the accessibility menu (localStorage `nh-a11y`, JSON: text 0–2,
 * contrast, links, still) and of plain language (`nh-plain` = "on", or `?plain=1|0` in the address, as the plugin
 * reads it) become classes on `<html>` before the first paint, so nothing flashes; and `nh-js` says the scripts run
 * (v4.css keeps the floating header solid without it, and hides nothing). Every storage access is inside try/catch
 * (private mode, blocked storage). Not in the Elementor editor's preview.
 *
 * @return string
 */
function nh_child_prepaint_js() {
	return '(function (w, d) {'
		. 'var h = d.documentElement, s = w.location.search;'
		. 'if (/[?&]elementor-preview=/.test(s)) return;'
		. 'h.classList.add("nh-js");'
		. 'var g = function (k) { try { return w.localStorage.getItem(k); } catch (e) { return null; } };'
		. 'var a = null; try { a = JSON.parse(g("nh-a11y") || "null"); } catch (e) { a = null; }'
		. 'if (a && typeof a === "object") {'
		. 'var t = Number(a.text); if (t === 1 || t === 2) h.classList.add("nh-a11y-text-" + t);'
		. 'if (a.contrast === true) h.classList.add("nh-a11y-contrast");'
		. 'if (a.links === true) h.classList.add("nh-a11y-links");'
		. 'if (a.still === true) h.classList.add("nh-a11y-still");'
		. '}'
		. 'var q = /[?&]plain=([01])/.exec(s);'
		. 'if (q ? q[1] === "1" : g("nh-plain") === "on") h.classList.add("nh-plainmode");'
		. 'var c = null; try { c = JSON.parse(g("nh-consent-v1") || "null"); } catch (e) { c = null; }'
		// no banner where the browser blocks site data: nothing could be kept, it would come back on every page
		. 'var ok = true; try { w.localStorage.setItem("nh-t", "1"); w.localStorage.removeItem("nh-t"); } catch (e) { ok = false; }'
		. 'if (ok && (!c || typeof c !== "object" || !(Number(c.v) >= ' . (int) nh_child_consent_version() . '))) h.classList.add("nh-consent-ask");'
		. '})(window, document);';
}

/**
 * `wp_footer` @10, on every page but the Elementor editor's preview: one landmark at the inline end (left) holding
 * the «נגישות» button and its panel (§5.3), the green call button, shown on phones only (§5.4), and the polite live
 * region v4.js speaks in. In the DOM the panel follows its button, so Tab goes from the button into it; it is
 * `hidden` until opened, so it is out of the tab order.
 */
function nh_child_v4_footer() {
	if ( nh_child_is_elementor_preview() ) {
		return;
	}
	$a11y_icon  = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
		. '<circle cx="12" cy="4.25" r="2.25" fill="currentColor"/>'
		. '<path d="M4.5 8.5 12 10l7.5-1.5M12 10v4.75M12 14.75 8.75 21.5M12 14.75l3.25 6.75" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg>';
	$phone_icon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
		. '<path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
		. '</svg>';

	$out  = '<aside class="nh-float" aria-label="' . esc_attr__( 'נגישות ויצירת קשר', 'hello-elementor-child' ) . '">';
	$out .= '<div class="nh-a11y">';
	$out .= '<button type="button" class="nh-a11y-btn" aria-expanded="false" aria-controls="nh-a11y-panel" aria-label="' . esc_attr__( 'נגישות', 'hello-elementor-child' ) . '">'
		. $a11y_icon . '<span class="nh-float__label">' . esc_html__( 'נגישות', 'hello-elementor-child' ) . '</span></button>';
	$out .= '<div id="nh-a11y-panel" class="nh-a11y-panel" role="dialog" aria-label="' . esc_attr__( 'תפריט נגישות', 'hello-elementor-child' ) . '" hidden>';
	$out .= '<h2 class="nh-a11y-panel__title">' . esc_html__( 'נגישות', 'hello-elementor-child' ) . '</h2>';
	$out .= '<ul class="nh-a11y-panel__list" role="list">';
	// v4.3: «בשפה פשוטה» (it left the v4 header): first, a toggle; shown by v4.js only where the page has plain texts
	$out .= '<li class="nh-a11y-panel__plain" hidden><button type="button" class="nh-a11y-opt" data-a11y="plain" data-plain-toggle aria-pressed="false"><span>' . esc_html__( 'בשפה פשוטה', 'hello-elementor-child' ) . '</span></button></li>';
	// 1.5.4 (Oleg 23.11): «הקראה» reads the page aloud with the browser's own voice (speechSynthesis, a Hebrew voice of
	// the device itself: nothing is sent anywhere); v4.js shows it only where such a voice exists. «השהיה» (then
	// «המשך») and «עצירה» show while it reads.
	$out .= '<li class="nh-a11y-panel__tts" hidden>'
		. '<button type="button" class="nh-a11y-opt" data-a11y="tts" aria-pressed="false"><span>' . esc_html__( 'הקראה', 'hello-elementor-child' ) . '</span></button>'
		. '<div class="nh-a11y-tts" hidden>'
		. '<button type="button" class="nh-a11y-act" data-a11y="tts-pause">' . esc_html__( 'השהיה', 'hello-elementor-child' ) . '</button>'
		. '<button type="button" class="nh-a11y-act" data-a11y="tts-stop">' . esc_html__( 'עצירה', 'hello-elementor-child' ) . '</button>'
		. '</div></li>';
	$out .= '<li><button type="button" class="nh-a11y-opt" data-a11y="text" aria-pressed="false"><span>' . esc_html__( 'הגדלת טקסט', 'hello-elementor-child' ) . '</span> <span class="nh-a11y-opt__state" dir="ltr">100%</span></button></li>';
	$out .= '<li><button type="button" class="nh-a11y-opt" data-a11y="contrast" aria-pressed="false"><span>' . esc_html__( 'ניגודיות גבוהה', 'hello-elementor-child' ) . '</span></button></li>';
	$out .= '<li><button type="button" class="nh-a11y-opt" data-a11y="links" aria-pressed="false"><span>' . esc_html__( 'הדגשת קישורים', 'hello-elementor-child' ) . '</span></button></li>';
	$out .= '<li><button type="button" class="nh-a11y-opt" data-a11y="still" aria-pressed="false"><span>' . esc_html__( 'עצירת אנימציות', 'hello-elementor-child' ) . '</span></button></li>';
	$out .= '</ul>';
	$out .= '<div class="nh-a11y-panel__row">'
		. '<button type="button" class="nh-a11y-act" data-a11y="reset">' . esc_html__( 'איפוס', 'hello-elementor-child' ) . '</button>'
		. '<button type="button" class="nh-a11y-act" data-a11y="close">' . esc_html__( 'סגירה', 'hello-elementor-child' ) . '</button>'
		. '</div>';
	$out .= '<a class="nh-a11y-panel__statement" href="' . esc_url( home_url( '/הצהרת-נגישות/' ) ) . '">' . esc_html__( 'הצהרת נגישות', 'hello-elementor-child' ) . '</a>';
	$out .= '</div>'; // .nh-a11y-panel
	$out .= '</div>'; // .nh-a11y
	$out .= '<a class="nh-call-btn" href="tel:089403047" aria-label="' . esc_attr__( 'התקשרו אלינו: 08-9403047', 'hello-elementor-child' ) . '">' . $phone_icon . '<span class="nh-float__label">' . esc_html__( 'התקשרו', 'hello-elementor-child' ) . '</span></a>';
	$out .= '<p id="nh-v4-live" class="nh-visually-hidden" role="status" aria-live="polite"></p>';
	$out .= '</aside>';

	echo $out . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts and static SVG
}

/**
 * `after_setup_theme`: the theme provides the site's cookie settings (#nh-cookies); the nevehairus-site plugin
 * (0.2.7+) then prints none of its own.
 */
function nh_child_supports() {
	add_theme_support( 'nh-cookies' );
}

/**
 * The consent version: a stored choice older than this counts as none, so the banner asks again. It is the plugin's
 * «גרסת ההסכמה» + 1: the plugin's panel of the v3.2 pages saved version 1 (the setting's default) before the banner
 * existed, so the banner asks everyone once (v4.3, 18.11.2026); and every raise of the setting asks everyone again,
 * as its label says (1.4.1: with a floor of 2 instead, raising it from 1 to 2 changed nothing). The plugin's own
 * check (record version ≥ the setting) accepts the theme's records.
 *
 * @return int
 */
function nh_child_consent_version() {
	$v = 1;
	if ( class_exists( 'NH_Settings', false ) && method_exists( 'NH_Settings', 'get' ) ) {
		$v = (int) NH_Settings::get( 'consent_version' );
	}
	return max( 2, $v + 1 );
}

/**
 * A text of the plugin's settings (Settings → נווה האירוס), or the same default when the plugin is off. Inline
 * tags only; `#privacy` becomes the privacy policy's address.
 *
 * @param string $key      Setting key.
 * @param string $fallback The plugin's default (class-nh-settings.php).
 * @return string Safe HTML.
 */
function nh_child_setting_text( $key, $fallback ) {
	$v = '';
	if ( class_exists( 'NH_Settings', false ) && method_exists( 'NH_Settings', 'get' ) ) {
		$v = (string) NH_Settings::get( $key );
	}
	if ( '' === trim( $v ) ) {
		$v = $fallback;
	}
	$privacy = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
	if ( '' === $privacy ) {
		$privacy = home_url( '/מדיניות-פרטיות/' );
	}
	$v = str_replace( 'href="#privacy"', 'href="' . esc_url( $privacy ) . '"', $v );
	return wp_kses(
		$v,
		array(
			'a'      => array( 'href' => true ),
			'b'      => array(),
			'strong' => array(),
			'br'     => array(),
		)
	);
}

/**
 * Plain lines («\n») as HTML lines.
 *
 * @param string $html Safe HTML.
 * @return string
 */
function nh_child_lines( $html ) {
	return str_replace( "\n", '<br>', $html );
}

/**
 * `wp_body_open` @6, right after the skip link, on every page but the Elementor editor's preview: our cookie settings
 * (v4.3, Itzik 18.11; the texts are the plugin's settings, research/07 §9). One region, not a dialog (no focus trap):
 * - while no current choice is stored (html.nh-consent-ask, set before the first paint): a banner at the foot of the
 *   screen with the text and «רק הכרחיות» · «אישור הכול» · «הגדרות»;
 * - «הגדרות» and every link to #nh-cookies (the footers' «הגדרות עוגיות») open the settings: necessary (always) and
 *   embedded content (YouTube), «שמירת הבחירה», «סגירה».
 * By default only necessary storage; YouTube loads only after a click and, while embedded content is not allowed, a
 * question in place (the template #nh-video-ask; v4.js part 6).
 */
function nh_child_cookies() {
	if ( nh_child_is_elementor_preview() ) {
		return;
	}
	$t = array(
		'title'     => nh_child_setting_text( 'cookie_title', 'הגדרות עוגיות' ),
		'text'      => nh_child_setting_text( 'cookie_text', 'באתר יש רק עוגיות הכרחיות. סרטון מ־YouTube נטען רק אחרי שמאשרים אותו, ואז YouTube עשוי לשמור עוגיות. כאן אפשר לשנות את הבחירה. <a href="#privacy">מדיניות הפרטיות</a>' ),
		'plain'     => nh_child_setting_text( 'cookie_text_plain', "האתר שומר רק מה שחייבים.\nסרטון מ־YouTube נפתח רק אם מסכימים.\nכאן אפשר לשנות את הבחירה." ),
		'necessary' => nh_child_setting_text( 'cookie_necessary', '<b>הכרחיות</b> — פעילות תקינה של האתר ושמירת הבחירות שלכם. תמיד פעילות.' ),
		'media'     => nh_child_setting_text( 'cookie_media', '<b>תוכן מוטמע</b> — סרטונים מ־YouTube.' ),
		'save'      => nh_child_setting_text( 'cookie_save', 'שמירת הבחירה' ),
		'close'     => nh_child_setting_text( 'cookie_close', 'סגירה' ),
	);
	$out  = '<section id="nh-cookies" class="nh-consent" aria-labelledby="nh-cookies-title" data-owner="theme" data-v="' . (int) nh_child_consent_version() . '">';
	$out .= '<h2 id="nh-cookies-title" class="nh-consent__title" tabindex="-1">' . $t['title'] . '</h2>';
	$out .= '<div class="nh-consent__text"><p class="nh-full">' . $t['text'] . '</p><p class="nh-plain">' . nh_child_lines( $t['plain'] ) . '</p></div>';
	$out .= '<div class="nh-consent__ask">'
		. '<button type="button" class="nh-consent__btn" data-consent="necessary">' . esc_html__( 'רק הכרחיות', 'hello-elementor-child' ) . '</button>'
		. '<button type="button" class="nh-consent__btn" data-consent="all">' . esc_html__( 'אישור הכול', 'hello-elementor-child' ) . '</button>'
		. '<button type="button" class="nh-consent__btn nh-consent__btn--quiet" data-consent="prefs" aria-expanded="false" aria-controls="nh-cookies-prefs">' . esc_html__( 'הגדרות', 'hello-elementor-child' ) . '</button>'
		. '</div>';
	$out .= '<div id="nh-cookies-prefs" class="nh-consent__prefs" hidden>';
	$out .= '<ul class="nh-consent__list" role="list">'
		. '<li><input type="checkbox" id="nh-c-necessary" checked disabled><label for="nh-c-necessary">' . $t['necessary'] . '</label></li>'
		. '<li><input type="checkbox" id="nh-c-media"><label for="nh-c-media">' . $t['media'] . '</label></li>'
		. '</ul>';
	$out .= '<div class="nh-consent__row">'
		. '<button type="button" class="nh-consent__btn" data-consent="save">' . $t['save'] . '</button>'
		. '<button type="button" class="nh-consent__btn nh-consent__btn--quiet" data-consent="close">' . $t['close'] . '</button>'
		. '</div>';
	$out .= '</div>';
	$out .= '</section>';
	// the question before a video loads YouTube (the texts of the plugin's voices widget, research/07 §9)
	$out .= '<template id="nh-video-ask"><div class="nh-video-ask" role="group">'
		. '<p class="nh-video-ask__q"><span class="nh-full">' . esc_html__( 'הסרטון מוטמע מ־YouTube, שעשוי לשמור עוגיות. לטעון אותו?', 'hello-elementor-child' ) . '</span>'
		. '<span class="nh-plain">' . esc_html__( 'הסרטון נמצא באתר YouTube.', 'hello-elementor-child' ) . '<br>' . esc_html__( 'YouTube יכול לשמור מידע עליכם.', 'hello-elementor-child' ) . '<br>' . esc_html__( 'לפתוח את הסרטון?', 'hello-elementor-child' ) . '</span></p>'
		. '<div class="nh-video-ask__row">'
		. '<button type="button" class="nh-consent__btn" data-ask="yes"><span class="nh-full">' . esc_html__( 'כן, לטעון את הסרטון', 'hello-elementor-child' ) . '</span><span class="nh-plain">' . esc_html__( 'כן, לפתוח את הסרטון', 'hello-elementor-child' ) . '</span></button>'
		. '<button type="button" class="nh-consent__btn nh-consent__btn--quiet" data-ask="no">' . esc_html__( 'לא עכשיו', 'hello-elementor-child' ) . '</button>'
		. '</div></div></template>';
	echo $out . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts and wp_kses'd settings
}

/**
 * The texts of the contact form's checks (the plugin's settings, else its defaults), for v4.js part 7.
 *
 * @return array
 */
function nh_child_form_texts() {
	$get = static function ( $key, $fallback ) {
		$v = '';
		if ( class_exists( 'NH_Settings', false ) && method_exists( 'NH_Settings', 'get' ) ) {
			$v = (string) NH_Settings::get( $key );
		}
		return wp_strip_all_tags( '' === trim( $v ) ? $fallback : $v );
	};
	return array(
		'required' => $get( 'msg_required', 'צריך למלא את השדה הזה.' ),
		'tel'      => $get( 'msg_tel', 'מספר הטלפון לא תקין: צריך 9 או 10 ספרות, ובהתחלה 0.' ),
		'consent'  => $get( 'msg_consent', 'צריך לסמן את התיבה כדי לשלוח.' ),
		'summary1' => $get( 'msg_summary_one', 'נמצאה שגיאה אחת בטופס:' ),
		'summaryN' => $get( 'msg_summary_many', 'נמצאו {n} שגיאות בטופס:' ),
		'limited'  => str_replace( '{phone}', '08-9403047', $get( 'msg_limited', 'נשלחו כבר כמה בקשות מהמכשיר הזה. אפשר לנסות שוב בעוד כמה דקות, או להתקשר: {phone}' ) ),
		'consentName' => 'אישור מדיניות הפרטיות',
		'spam'     => str_replace( '{phone}', '08-9403047', $get( 'msg_spam', 'לא הצלחנו לשלוח את הטופס. אפשר לרענן את הדף ולנסות שוב, או להתקשר: {phone}' ) ),
	);
}

/**
 * `elementor_pro/forms/validation`: the contact form of the v4 home (its fields name / phone / consent, class
 * nh-contact-form), checked on the server as the inner page's form is: required fields, an Israeli phone number
 * (0 and 8–9 more digits, after removing spaces and dashes), the consent; and at most 5 sends in 10 minutes from one
 * address (only a counter under a salted hash of it is kept, in a transient; nothing the visitor typed). The form's
 * HTML fields are taken out of the record, so a stored submission holds only the visitor's answers.
 *
 * @param object $record       ElementorPro\Modules\Forms\Classes\Form_Record.
 * @param object $ajax_handler ElementorPro\Modules\Forms\Classes\Ajax_Handler.
 */
function nh_child_form_validation( $record, $ajax_handler ) {
	if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) || ! method_exists( $record, 'get_form_settings' ) ) {
		return;
	}
	$classes = (string) $record->get_form_settings( '_css_classes' );
	if ( false === strpos( ' ' . $classes . ' ', ' nh-contact-form ' ) ) {
		return;
	}
	$t      = nh_child_form_texts();
	$fields = (array) $record->get( 'fields' );
	// the HTML fields (the required note, the hint, the privacy note) are text, not answers: they are not stored with
	// the submission or sent by e-mail, so the name is the first column in Elementor → Submissions
	if ( method_exists( $record, 'remove_field' ) ) {
		foreach ( $fields as $id => $field ) {
			if ( isset( $field['type'] ) && 'html' === $field['type'] ) {
				$record->remove_field( $id );
			}
		}
	}
	foreach ( array( 'name', 'phone' ) as $id ) {
		if ( isset( $fields[ $id ] ) && '' === trim( (string) $fields[ $id ]['value'] ) ) {
			$ajax_handler->add_error( $id, $t['required'] );
		}
	}
	if ( isset( $fields['phone'] ) && '' !== trim( (string) $fields['phone']['value'] ) ) {
		$digits = preg_replace( '/\D+/', '', (string) $fields['phone']['value'] );
		if ( ! preg_match( '/^0\d{8,9}$/', $digits ) ) {
			$ajax_handler->add_error( 'phone', $t['tel'] );
		}
	}
	if ( isset( $fields['consent'] ) && '' === trim( (string) $fields['consent']['value'] ) ) {
		$ajax_handler->add_error( 'consent', $t['consent'] );
	}
	// the rate limit: a counter per salted hash of the address, 10 minutes; never the address itself. A send with
	// errors is not counted (nobody is locked out by their own typing mistakes)
	if ( property_exists( $ajax_handler, 'errors' ) && ! empty( $ajax_handler->errors ) ) {
		return;
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'nh_form_rate_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
	// a fixed window from the first counted send (1.4.1: the expiry is not pushed on by later sends, so sends spread
	// over an hour are never refused); a count and its start, nothing else
	$now = time();
	$rec = get_transient( $key );
	if ( ! is_array( $rec ) || ! isset( $rec['n'], $rec['t0'] ) || $now - (int) $rec['t0'] >= 10 * MINUTE_IN_SECONDS ) {
		$rec = array( 'n' => 0, 't0' => $now );
	}
	$n = (int) $rec['n'];
	if ( $n >= 5 ) {
		// answer here, before Elementor adds its general error and before any action runs: one message, no field is
		// blamed (an error on the hidden trap field would print under it, and Elementor's «something went wrong»
		// would follow the message)
		wp_send_json_error(
			array(
				'message' => esc_html( $t['limited'] ),
				'errors'  => array(),
				'data'    => array(),
			)
		);
	}
	// what is left of the window; two sends at the same moment may both pass (no lock): harmless at this traffic
	set_transient( $key, array( 'n' => $n + 1, 't0' => (int) $rec['t0'] ), max( 1, (int) $rec['t0'] + 10 * MINUTE_IN_SECONDS - $now ) );
}

/**
 * `elementor_pro/forms/sanitize/tel` @20 (after Pro's own sanitize_text_field): a phone number as people write it.
 * Pro's own check of a tel field (run before the theme's) allows only digits and ()#&+*-=. — so «050 123 4567» or
 * «050–1234567» would be refused by the server after the page said the form was fine. A valid number is kept as its
 * digits; otherwise spaces go and the other dashes become «-»; the theme's check (0 and 8–9 more digits) decides, as
 * on the inner page's form (1.4.1).
 *
 * @param mixed $value The field's value.
 * @return mixed
 */
function nh_child_form_tel( $value ) {
	if ( ! is_string( $value ) ) {
		return $value;
	}
	// a valid Israeli number (0 and 8–9 more digits, the theme's own check) is kept as its digits, whatever was
	// around them («050-1234567 (אמא)», «050_1234567»): Pro's check then always agrees with the theme's
	$digits = preg_replace( '/\D+/', '', $value );
	if ( preg_match( '/^0\d{8,9}$/', (string) $digits ) ) {
		return (string) $digits;
	}
	$value = preg_replace( '/[\x{2010}-\x{2015}\x{2212}]/u', '-', $value );
	// spaces of every kind, and the direction marks a number copied from Hebrew text may carry
	return (string) preg_replace( '/[\s\x{00A0}\x{2007}\x{202F}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', '', (string) $value );
}

/**
 * `wp_get_attachment_image_attributes`: the `sizes` of a landscape hero photo (an attachment titled «nh4:hero-…», the
 * v4 home's first screen; 1.4.7, v4.4). The hero fills the screen (object-fit: cover), so wherever the screen is
 * narrower than the photo's shape — every phone held upright, a 1440x900 or 1920x1080 desktop — the photo is shown by
 * the screen's HEIGHT and is wider than the screen. WordPress's `sizes` (100vw up to the photo's width) then makes the
 * browser pick a file too small: on an iPhone the 1536px-wide one, 819px high, for a screen 664–844px high at 3x.
 * Here, while the screen is narrower than the photo, it is 100vh × the photo's width/height; otherwise 100vw. Portrait
 * photos keep WordPress's value: the width decides for them.
 *
 * @param array<string,string> $attr       The img's attributes (srcset and sizes are set by now).
 * @param mixed                $attachment The attachment's post.
 * @return array<string,string>
 */
function nh_child_hero_sizes( $attr, $attachment ) {
	if ( empty( $attr['srcset'] ) || ! ( $attachment instanceof WP_Post ) || 0 !== strpos( (string) $attachment->post_title, 'nh4:hero-' ) ) {
		return $attr;
	}
	$meta = wp_get_attachment_metadata( $attachment->ID );
	$w    = is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : 0;
	$h    = is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : 0;
	if ( $h <= 0 || $w <= $h ) {
		return $attr;
	}
	$ratio         = rtrim( rtrim( number_format( $w / $h, 4, '.', '' ), '0' ), '.' );
	$attr['sizes'] = sprintf( '(max-aspect-ratio: %1$d/%2$d) calc(100vh * %3$s), 100vw', $w, $h, $ratio );
	return $attr;
}

/**
 * `init`: WordPress's emoji script and its style off on the site's pages (1.5.2). Where a device has no emoji font, the
 * script swaps every emoji for a picture from s.w.org: a request to another host, sent before any consent (the inner
 * pages keep a few emoji of the live texts, e.g. «📍 מיקום» in «דרושים»). Every current phone and computer draws emoji
 * itself. Feeds, e-mails and the admin stay as WordPress has them (hooks as registered by WordPress 7.1.5).
 */
function nh_child_no_emoji() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'embed_head', 'print_emoji_detection_script' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
}

/**
 * `the_title`: on the site's pages an attachment titled «nh4:…» (the deploy's marker, templates/v4/media.json) has no
 * title (1.5.7), wherever WordPress's get_the_title() prints it. Elementor's lightbox reads the post's title directly,
 * so there v4.js part 11 replaces it (the photo's own caption, or nothing) before any click. The admin and the REST
 * API keep the title: the deploy finds its files by it.
 *
 * @param string $title The title.
 * @param int    $id    The post's ID.
 * @return string
 */
function nh_child_media_title( $title, $id = 0 ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! $id || 0 !== strpos( (string) $title, 'nh4:' ) ) {
		return $title;
	}
	return 'attachment' === get_post_type( $id ) ? '' : $title;
}

/**
 * `elementor/widget/render_content` (1.5.14): what a widget prints.
 * (1) The test site's photos carry the deploy's marker «nh4:…» as their title, and Elementor writes the title and the
 * description into data-elementor-lightbox-title / -description of every photo link (it reads the attachment itself, so the
 * `the_title` filter above never reaches it): the marker showed as the enlarged photo's caption wherever v4.js did not run.
 * Those attributes go when they hold the marker; v4.js adds the photo's real caption.
 * (2) An empty list of posts (a search without hits) was printed with role="list" and no item: the role goes with it
 * (WCAG 1.3.1).
 *
 * @param string                 $content The widget's HTML.
 * @param \Elementor\Widget_Base $widget  The widget.
 * @return string
 */
function nh_child_widget_content( $content, $widget ) {
	unset( $widget );
	if ( ! is_string( $content ) || is_admin() ) {
		return $content;
	}
	if ( false !== strpos( $content, 'nh4:' ) ) {
		$content = preg_replace( '/\sdata-elementor-lightbox-(?:title|description)="nh4:[^"]*"/u', '', $content );
	}
	if ( false !== strpos( $content, 'elementor-posts-nothing-found' ) ) {
		$content = str_replace( ' role="list"', '', $content );
	}
	return $content;
}

/**
 * `template_redirect` @1 (1.5.16, Oleg 24.11): no author archives. /?author=1 and /author/<name>/ (and their feeds) showed
 * the WordPress user's login name as the page's title; they go to the home page (301), before WordPress's own canonical
 * redirect (to /author/<name>/) can run.
 */
function nh_child_no_author_archives() {
	if ( is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}

/**
 * `template_redirect` @2 (1.5.16, Oleg 24.11): a category without posts is not a page (WordPress answers 200 with «אין עדיין
 * פוסטים כאן»; the default «Uncategorized»/«כללי» is empty here): 404, as for a page beyond the last one of a list.
 */
function nh_child_no_empty_category() {
	if ( is_category() && ! is_feed() && ! have_posts() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}

/**
 * `wp_enqueue_scripts` @40: Elementor's Swiper styles (registered by Elementor, served from this site) on a page with a
 * photo grid (.nh-product, 1.5.9). Its lightbox browses such a grid as a slideshow (v4.js part 11), and without these
 * styles the previous/next arrows were drawn below the screen (a Basic Gallery loads them by itself).
 */
function nh_child_swiper_style() {
	if ( is_admin() || ! is_singular() || ! wp_style_is( 'e-swiper', 'registered' ) ) {
		return;
	}
	$data = (string) get_post_meta( (int) get_queried_object_id(), '_elementor_data', true );
	if ( false !== strpos( $data, 'nh-product' ) ) {
		wp_enqueue_style( 'e-swiper' );
	}
}

/**
 * 1.6.0 (Itzik 24.11): an invisible guard on every form, with no box to tick and no picture, no new plugin and no outside
 * service: (1) a honeypot field a person never sees (every Elementor Form of the site has «website»; a filled one is a
 * robot); (2) a minimum time to fill the form (3 seconds from the page's load: a robot posts at once); (3) a token the
 * page's script fetches from this site (v4.js part 13) and sends with the form: a signed time (HMAC with the site's salt),
 * so a robot that does not run the page's script has none, and a made-up or too old one fails. Nothing about the visitor
 * is stored: the token holds a time only.
 */
const NH_CHILD_FORM_MIN_SECONDS = 3;
const NH_CHILD_FORM_MAX_SECONDS = 86400;

/**
 * A fresh token: «<unix time>.<first 32 hex of HMAC-SHA256 of the time>».
 *
 * @return string
 */
function nh_child_form_token_make() {
	$t = time();
	return $t . '.' . substr( hash_hmac( 'sha256', 'nh-form|' . $t, wp_salt( 'auth' ) ), 0, 32 );
}

/**
 * What a token is worth: 'ok', 'missing', 'bad' (not ours), 'fast' (under the minimum time) or 'old'.
 *
 * @param string $token The token as posted.
 * @return string
 */
function nh_child_form_token_state( $token ) {
	$token = is_string( $token ) ? trim( $token ) : '';
	if ( '' === $token ) {
		return 'missing';
	}
	if ( ! preg_match( '/^(\d{9,11})\.([0-9a-f]{32})$/', $token, $m ) ) {
		return 'bad';
	}
	$want = substr( hash_hmac( 'sha256', 'nh-form|' . $m[1], wp_salt( 'auth' ) ), 0, 32 );
	if ( ! hash_equals( $want, $m[2] ) ) {
		return 'bad';
	}
	$age = time() - (int) $m[1];
	if ( $age < NH_CHILD_FORM_MIN_SECONDS ) {
		return 'fast';
	}
	return $age > NH_CHILD_FORM_MAX_SECONDS ? 'old' : 'ok';
}

/**
 * `rest_api_init`: GET /wp-json/nh/v1/form-token → {"token": "…"}, never cached. Public (a robot may ask too: the minimum
 * time and the signature still hold).
 */
function nh_child_form_token_route() {
	register_rest_route(
		'nh/v1',
		'/form-token',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => static function () {
				$response = new WP_REST_Response( array( 'token' => nh_child_form_token_make() ) );
				$response->header( 'Cache-Control', 'no-store, max-age=0' );
				return $response;
			},
		)
	);
}

/**
 * `elementor_pro/forms/validation` @1 (every Elementor Form of the site, before Pro's own checks and before the rate limit):
 * a filled honeypot, a missing, made-up, too fast or too old token ends the send with one general message (no field is
 * blamed: the robot learns nothing, a person who was too quick or left the page open for a day refreshes and sends again).
 *
 * @param object $record       ElementorPro\Modules\Forms\Classes\Form_Record.
 * @param object $ajax_handler ElementorPro\Modules\Forms\Classes\Ajax_Handler.
 */
function nh_child_form_guard( $record, $ajax_handler ) {
	if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) ) {
		return;
	}
	$bad = false;
	foreach ( (array) $record->get( 'fields' ) as $field ) {
		if ( isset( $field['type'], $field['value'] ) && 'honeypot' === $field['type'] && '' !== trim( (string) $field['value'] ) ) {
			$bad = true;
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- a public form: the token below is the proof.
	$token = isset( $_POST['nh_tk'] ) ? sanitize_text_field( wp_unslash( $_POST['nh_tk'] ) ) : '';
	if ( $bad || 'ok' !== nh_child_form_token_state( $token ) ) {
		$t = nh_child_form_texts();
		wp_send_json_error(
			array(
				'message' => esc_html( $t['spam'] ),
				'errors'  => array(),
				'data'    => array(),
			)
		);
	}
}
