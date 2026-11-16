<?php
defined( 'ABSPATH' ) || exit;

/**
 * Hello Elementor and Elementor compatibility (SPEC §5.1, §10, D4, D10): Hello's reset and theme CSS,
 * skip link and page title off; Elementor's Google Fonts off; JPEG/PNG sub-sizes written as WebP.
 *
 * @package nevehairus-site
 */
final class NH_Compat {

	/**
	 * Hooks the filters.
	 */
	public static function init(): void {
		add_filter( 'hello_elementor_enqueue_style', '__return_false', 20 );
		add_filter( 'hello_elementor_enqueue_theme_style', '__return_false', 20 );
		add_filter( 'hello_elementor_enable_skip_link', '__return_false', 20 );
		add_filter( 'hello_elementor_page_title', [ __CLASS__, 'page_title' ], 20 );
		add_filter( 'elementor/frontend/print_google_fonts', '__return_false', 10 );
		add_filter( 'image_editor_output_format', [ __CLASS__, 'output_format' ], 10 );
	}

	/**
	 * Hello's page title is off on pages built with Elementor (the cover is the page's H1).
	 *
	 * @param bool $show Show the title.
	 * @return bool
	 */
	public static function page_title( $show ) {
		$id = (int) get_the_ID();
		if ( $id > 0 && 'builder' === get_post_meta( $id, '_elementor_edit_mode', true ) ) {
			return false;
		}
		return $show;
	}

	/**
	 * With the setting `webp_subsizes` on, WordPress writes JPEG and PNG sub-sizes as WebP (it falls back
	 * by itself when the server cannot write WebP).
	 *
	 * @param array $formats Mime type => output mime type.
	 * @return array
	 */
	public static function output_format( $formats ) {
		$formats = is_array( $formats ) ? $formats : [];
		if ( empty( NH_Settings::get( 'webp_subsizes' ) ) ) {
			return $formats;
		}
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}
}
