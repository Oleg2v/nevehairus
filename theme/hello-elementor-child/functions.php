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
