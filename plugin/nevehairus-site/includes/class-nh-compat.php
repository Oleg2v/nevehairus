<?php
defined( 'ABSPATH' ) || exit;

/**
 * Image compatibility (SPEC D10): with the setting `webp_subsizes` on (off by default: uPress optimises
 * images itself), JPEG/PNG sub-sizes are written as WebP.
 *
 * Nothing else here touches the site (research/07 §12.3): the Hello Elementor filters (reset and theme CSS,
 * skip link, page title) and Elementor's Google Fonts switch belong to the child theme
 * (theme/hello-elementor-child/functions.php, §12.5), so they survive the plugin being switched off.
 *
 * @package nevehairus-site
 */
final class NH_Compat {

	/**
	 * Hooks `image_editor_output_format` only when the setting is on (read again when the filter runs).
	 */
	public static function init(): void {
		if ( ! empty( NH_Settings::get( 'webp_subsizes' ) ) ) {
			add_filter( 'image_editor_output_format', [ __CLASS__, 'output_format' ], 10 );
		}
	}

	/**
	 * With the setting `webp_subsizes` on, WordPress writes JPEG and PNG sub-sizes as WebP (it falls back
	 * by itself when the server cannot write WebP). Off (e.g. switched off later in the same request by the
	 * settings migration): the formats are returned unchanged.
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
