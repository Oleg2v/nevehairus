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

add_action( 'wp_body_open', 'nh_child_skip_link', 5 );
foreach ( array( 'header-footer', 'canvas' ) as $nh_child_template ) {
	add_action( "elementor/page_templates/{$nh_child_template}/before_content", 'nh_child_main_open' );
	add_action( "elementor/page_templates/{$nh_child_template}/after_content", 'nh_child_main_close' );
}
unset( $nh_child_template );

add_action( 'wp_enqueue_scripts', 'nh_child_styles', 20 );
add_action( 'wp_enqueue_scripts', 'nh_child_v4_assets', 30 );
add_action( 'wp_head', 'nh_child_v4_head', 2 );
add_action( 'wp_footer', 'nh_child_v4_footer', 10 );

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
	echo '<main id="content" class="site-main" tabindex="-1">';
}

/**
 * `elementor/page_templates/{header-footer|canvas}/after_content`.
 */
function nh_child_main_close() {
	echo '</main>';
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
		. $a11y_icon . '<span class="nh-visually-hidden">' . esc_html__( 'נגישות', 'hello-elementor-child' ) . '</span></button>';
	$out .= '<div id="nh-a11y-panel" class="nh-a11y-panel" role="dialog" aria-label="' . esc_attr__( 'תפריט נגישות', 'hello-elementor-child' ) . '" hidden>';
	$out .= '<h2 class="nh-a11y-panel__title">' . esc_html__( 'נגישות', 'hello-elementor-child' ) . '</h2>';
	$out .= '<ul class="nh-a11y-panel__list" role="list">';
	$out .= '<li><button type="button" class="nh-a11y-opt" data-a11y="text" aria-pressed="false"><span>' . esc_html__( 'הגדלת טקסט', 'hello-elementor-child' ) . '</span> <span class="nh-a11y-opt__state" dir="ltr">100%</span></button></li>';
	$out .= '<li><button type="button" class="nh-a11y-opt" data-a11y="contrast" aria-pressed="false"><span>' . esc_html__( 'ניגודיות גבוהה', 'hello-elementor-child' ) . '</span></button></li>';
	$out .= '<li><button type="button" class="nh-a11y-opt" data-a11y="links" aria-pressed="false"><span>' . esc_html__( 'הדגשת קישורים', 'hello-elementor-child' ) . '</span></button></li>';
	$out .= '<li><button type="button" class="nh-a11y-opt" data-a11y="still" aria-pressed="false"><span>' . esc_html__( 'עצירת אנימציות', 'hello-elementor-child' ) . '</span></button></li>';
	$out .= '</ul>';
	$out .= '<!-- text-to-speech (research/09 §5.3) comes here later: a «הקראה» button; not built yet -->';
	$out .= '<div class="nh-a11y-panel__row">'
		. '<button type="button" class="nh-a11y-act" data-a11y="reset">' . esc_html__( 'איפוס', 'hello-elementor-child' ) . '</button>'
		. '<button type="button" class="nh-a11y-act" data-a11y="close">' . esc_html__( 'סגירה', 'hello-elementor-child' ) . '</button>'
		. '</div>';
	$out .= '<a class="nh-a11y-panel__statement" href="' . esc_url( home_url( '/הצהרת-נגישות/' ) ) . '">' . esc_html__( 'הצהרת נגישות', 'hello-elementor-child' ) . '</a>';
	$out .= '</div>'; // .nh-a11y-panel
	$out .= '</div>'; // .nh-a11y
	$out .= '<a class="nh-call-btn" href="tel:089403047" aria-label="' . esc_attr__( 'התקשרו אלינו: 08-9403047', 'hello-elementor-child' ) . '">' . $phone_icon . '</a>';
	$out .= '<p id="nh-v4-live" class="nh-visually-hidden" role="status" aria-live="polite"></p>';
	$out .= '</aside>';

	echo $out . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts and static SVG
}
