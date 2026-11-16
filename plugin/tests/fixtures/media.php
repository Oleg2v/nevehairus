<?php
/**
 * The fake media library (SPEC §11.2), built at runtime from prototype/assets/img/manifest.json.
 *
 * Attachment ids are 101, 102, … in manifest order; nh_test_media_id( 'v3-walk' ) returns the id.
 * Each image: the full size = the manifest's original width × height; sub-sizes `nh-{w}` for the
 * manifest widths (the 768 one also answers to `medium_large`, so NH_Picture picks the same src as the
 * prototype), WebP files with AVIF alternates in `sources['image/avif']` (the Modern Image Formats
 * shape); alt = the manifest alt. URLs point from plugin/tests/out/ to the prototype's real photos —
 * test data only, so rendered test pages show the real pictures.
 *
 * A record with `file` and no `widths` (the logo emblem, research/07 §11.2 item 5: `{"file":
 * "logo-emblem.png", "width": 105, "height": 74, "alt": "", "decorative": true}`) is one PNG without
 * sub-sizes, as WordPress stores a small upload.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

/** Base URL of the fixture photos, as seen from plugin/tests/out/. */
const NH_TEST_MEDIA_BASE = '../../../prototype/assets/img/';

/**
 * Loads the manifest into the fake site (attachments + their metadata). Idempotent per state.
 */
function nh_test_media_load(): void {
	$manifest = json_decode( (string) file_get_contents( NH_TEST_ROOT . '/prototype/assets/img/manifest.json' ), true );
	$id       = 101;
	$GLOBALS['nh_test']['media'] = [];
	foreach ( (array) $manifest as $name => $m ) {
		if ( ! is_array( $m ) ) {
			continue;
		}
		if ( ! isset( $m['widths'] ) && isset( $m['file'] ) ) {
			$file                               = basename( (string) $m['file'] );
			$GLOBALS['nh_test']['posts'][ $id ] = [
				'type'   => 'attachment',
				'status' => 'inherit',
				'mime'   => 'image/png',
				'title'  => $name,
				'url'    => NH_TEST_MEDIA_BASE . $file,
			];
			$GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata'] = [
				'width'   => (int) ( $m['width'] ?? 0 ),
				'height'  => (int) ( $m['height'] ?? 0 ),
				'file'    => $file,
				'sizes'   => [],
				'sources' => [],
			];
			$GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_image_alt'] = (string) ( $m['alt'] ?? '' );
			$GLOBALS['nh_test']['media'][ $name ]                          = $id;
			++$id;
			continue;
		}
		$widths = array_map( 'intval', (array) ( $m['widths'] ?? [] ) );
		if ( ! $widths ) {
			continue;
		}
		sort( $widths );
		$w     = (int) $m['width'];
		$h     = (int) $m['height'];
		$sizes = [];
		foreach ( $widths as $sw ) {
			$sizes[ 'nh-' . $sw ] = [
				'file'      => $name . '-' . $sw . '.webp',
				'width'     => $sw,
				'height'    => (int) round( $h * $sw / $w ),
				'mime-type' => 'image/webp',
				'sources'   => [
					'image/webp' => [ 'file' => $name . '-' . $sw . '.webp' ],
					'image/avif' => [ 'file' => $name . '-' . $sw . '.avif' ],
				],
			];
		}
		if ( isset( $sizes['nh-768'] ) ) {
			$sizes['medium_large'] = $sizes['nh-768'];
		}
		$largest = end( $widths );
		$GLOBALS['nh_test']['posts'][ $id ] = [
			'type'   => 'attachment',
			'status' => 'inherit',
			'mime'   => 'image/webp',
			'title'  => $name,
			// The full size has no file of its own in the prototype: the largest derivative stands in.
			'url'    => NH_TEST_MEDIA_BASE . $name . '-' . $largest . '.webp',
		];
		$GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata'] = [
			'width'   => $w,
			'height'  => $h,
			'file'    => $name . '-' . $largest . '.webp',
			'sizes'   => $sizes,
			'sources' => [],
		];
		$GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_image_alt'] = (string) ( $m['alt'] ?? '' );
		$GLOBALS['nh_test']['media'][ $name ]                          = $id;
		++$id;
	}
}

/**
 * The attachment id of a prototype photo name (e.g. 'v3-walk'), 0 if unknown.
 */
function nh_test_media_id( string $name ): int {
	if ( empty( $GLOBALS['nh_test']['media'] ) ) {
		nh_test_media_load();
	}
	return (int) ( $GLOBALS['nh_test']['media'][ $name ] ?? 0 );
}

/**
 * The photo name of an attachment id ('' if none).
 */
function nh_test_media_name( int $id ): string {
	$name = array_search( $id, (array) ( $GLOBALS['nh_test']['media'] ?? [] ), true );
	return false === $name ? '' : (string) $name;
}
