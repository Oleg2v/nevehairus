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
 * - style.css, printed after Elementor's frontend CSS.
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

add_action( 'wp_body_open', 'nh_child_skip_link', 5 );
foreach ( array( 'header-footer', 'canvas' ) as $nh_child_template ) {
	add_action( "elementor/page_templates/{$nh_child_template}/before_content", 'nh_child_main_open' );
	add_action( "elementor/page_templates/{$nh_child_template}/after_content", 'nh_child_main_close' );
}
unset( $nh_child_template );

add_action( 'wp_enqueue_scripts', 'nh_child_styles', 20 );

/**
 * `hello_elementor_page_title`: no Hello title on a page built with Elementor (its cover is the page's H1).
 *
 * @param bool $show Show the title.
 * @return bool
 */
function nh_child_page_title( $show ) {
	$id = (int) get_the_ID();
	if ( $id > 0 && 'builder' === get_post_meta( $id, '_elementor_edit_mode', true ) ) {
		return false;
	}
	return $show;
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
