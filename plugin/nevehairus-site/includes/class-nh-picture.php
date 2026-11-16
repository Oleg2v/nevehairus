<?php
defined( 'ABSPATH' ) || exit;

/**
 * `<picture>` from media-library attachments (SPEC §3.8, D10).
 *
 * The `<img>` uses WordPress's own srcset of the attachment; an AVIF `<source>` is added only when the
 * attachment metadata already holds AVIF alternates (the shape written by the "Modern Image Formats"
 * plugin: `sources['image/avif']['file']` on the full size and on each sub-size). No image processing here.
 *
 * @package nevehairus-site
 */
final class NH_Picture {

	/**
	 * `<picture class>[<source type="image/avif" srcset sizes>]<img src srcset sizes width height alt loading
	 * decoding="async"[ fetchpriority="high"]></picture>` (attribute order of the prototype's picture()).
	 * Options: sizes ('100vw'), class, loading ('lazy'|'eager'), priority (bool), alt (?string: null or ''
	 * = the media library's alt), decorative (bool → alt=""). '' if the attachment is not an image.
	 *
	 * @param int   $id Attachment id.
	 * @param array $o  Options.
	 */
	public static function render( int $id, array $o = [] ): string {
		if ( ! self::exists( $id ) ) {
			return '';
		}
		$o     = array_merge(
			[
				'sizes'      => '100vw',
				'class'      => '',
				'loading'    => 'lazy',
				'priority'   => false,
				'alt'        => null,
				'decorative' => false,
			],
			$o
		);
		$sizes = trim( (string) $o['sizes'] );
		$sizes = '' !== $sizes ? $sizes : '100vw';
		$class = trim( (string) $o['class'] );

		if ( ! empty( $o['decorative'] ) ) {
			$alt = '';
		} elseif ( null !== $o['alt'] && '' !== trim( (string) $o['alt'] ) ) {
			$alt = trim( (string) $o['alt'] );
		} else {
			$alt = self::alt( $id );
		}

		$srcset = self::srcset( $id );
		$avif   = self::avif_srcset( $id );
		[ $w, $h ] = self::dims( $id );

		$html = '<picture' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>';
		if ( '' !== $avif ) {
			$html .= '<source type="image/avif" srcset="' . esc_attr( $avif ) . '" sizes="' . esc_attr( $sizes ) . '">';
		}
		$html .= '<img src="' . esc_url( self::img_src( $id ) ) . '"';
		if ( '' !== $srcset ) {
			$html .= ' srcset="' . esc_attr( $srcset ) . '" sizes="' . esc_attr( $sizes ) . '"';
		}
		if ( $w > 0 && $h > 0 ) {
			$html .= ' width="' . $w . '" height="' . $h . '"';
		}
		$html .= ' alt="' . esc_attr( $alt ) . '" loading="' . ( 'eager' === $o['loading'] ? 'eager' : 'lazy' ) . '" decoding="async"'
			. ( ! empty( $o['priority'] ) ? ' fetchpriority="high"' : '' ) . '></picture>';
		return $html;
	}

	/**
	 * An image attachment.
	 */
	public static function exists( int $id ): bool {
		return $id > 0 && wp_attachment_is_image( $id );
	}

	/**
	 * WordPress's srcset of the full size, or ''.
	 */
	public static function srcset( int $id ): string {
		if ( ! self::exists( $id ) ) {
			return '';
		}
		$set = wp_get_attachment_image_srcset( $id, 'full' );
		return is_string( $set ) ? $set : '';
	}

	/**
	 * AVIF candidates from the metadata (`sources['image/avif']`), "url Ww, …" ascending; '' if there are
	 * none or the attachment itself is AVIF.
	 */
	public static function avif_srcset( int $id ): string {
		if ( ! self::exists( $id ) || 'image/avif' === get_post_mime_type( $id ) ) {
			return '';
		}
		$out = [];
		foreach ( self::candidates( $id, 'image/avif' ) as $w => $url ) {
			$out[] = $url . ' ' . $w . 'w';
		}
		return implode( ', ', $out );
	}

	/**
	 * URL of the widest size ≤ $max px (fallback: the smallest), in the attachment's own format.
	 */
	public static function src( int $id, int $max = 1440 ): string {
		if ( ! self::exists( $id ) ) {
			return '';
		}
		return self::pick( self::candidates( $id, '' ), $max );
	}

	/**
	 * [ width, height ] of the full size ([0, 0] if unknown).
	 *
	 * @return int[]
	 */
	public static function dims( int $id ): array {
		$m = self::exists( $id ) ? wp_get_attachment_metadata( $id ) : [];
		return [ (int) ( $m['width'] ?? 0 ), (int) ( $m['height'] ?? 0 ) ];
	}

	/**
	 * The media library's alt text.
	 */
	public static function alt( int $id ): string {
		if ( $id <= 0 ) {
			return '';
		}
		return trim( wp_strip_all_tags( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) );
	}

	/**
	 * `<link rel="preload" as="image"[ type="image/avif"] href imagesrcset imagesizes fetchpriority="high">`
	 * for the LCP photo (the AVIF set when the metadata has one, as the prototype's head).
	 */
	public static function preload( int $id, string $sizes ): string {
		if ( ! self::exists( $id ) ) {
			return '';
		}
		$sizes = '' !== trim( $sizes ) ? trim( $sizes ) : '100vw';
		$avif  = self::candidates( $id, 'image/avif' );
		if ( $avif && 'image/avif' !== get_post_mime_type( $id ) ) {
			$type = ' type="image/avif"';
			$href = self::pick( $avif, self::width_of( $id, self::img_src( $id ) ) ?: 768 );
			$set  = self::avif_srcset( $id );
		} else {
			$type = '';
			$href = self::img_src( $id );
			$set  = self::srcset( $id );
		}
		return '<link rel="preload" as="image"' . $type . ' href="' . esc_url( $href ) . '"'
			. ( '' !== $set ? ' imagesrcset="' . esc_attr( $set ) . '" imagesizes="' . esc_attr( $sizes ) . '"' : '' )
			. ' fetchpriority="high">';
	}

	/**
	 * The `<img src>`: medium_large, else large, else the full size.
	 */
	private static function img_src( int $id ): string {
		$m    = wp_get_attachment_metadata( $id );
		$base = self::base_url( $id );
		foreach ( [ 'medium_large', 'large' ] as $size ) {
			if ( ! empty( $m['sizes'][ $size ]['file'] ) ) {
				return $base . $m['sizes'][ $size ]['file'];
			}
		}
		return (string) wp_get_attachment_url( $id );
	}

	/**
	 * Width of the candidate whose URL this is (0 if none).
	 */
	private static function width_of( int $id, string $url ): int {
		foreach ( self::candidates( $id, '' ) as $w => $u ) {
			if ( $u === $url ) {
				return (int) $w;
			}
		}
		return 0;
	}

	/**
	 * The widest candidate ≤ $max, else the smallest.
	 *
	 * @param array<int,string> $candidates width => URL, ascending.
	 */
	private static function pick( array $candidates, int $max ): string {
		if ( ! $candidates ) {
			return '';
		}
		$best = '';
		foreach ( $candidates as $w => $url ) {
			if ( $w <= $max ) {
				$best = $url;
			}
		}
		return '' !== $best ? $best : (string) reset( $candidates );
	}

	/**
	 * Sizes of the attachment with the full size's aspect ratio, width => URL ascending. `$mime` '' = the
	 * attachment's own files; 'image/avif' = the AVIF alternates from `sources`.
	 *
	 * @return array<int,string>
	 */
	private static function candidates( int $id, string $mime ): array {
		$m = wp_get_attachment_metadata( $id );
		if ( ! is_array( $m ) || empty( $m['width'] ) || empty( $m['height'] ) ) {
			return [];
		}
		$base    = self::base_url( $id );
		$fw      = (int) $m['width'];
		$fh      = (int) $m['height'];
		$max     = (int) apply_filters( 'max_srcset_image_width', 2048, [ $fw, $fh ] );
		$entries = [ [ $fw, $fh, '' === $mime ? basename( (string) wp_get_attachment_url( $id ) ) : (string) ( $m['sources'][ $mime ]['file'] ?? '' ) ] ];
		foreach ( (array) ( $m['sizes'] ?? [] ) as $s ) {
			if ( ! is_array( $s ) || empty( $s['width'] ) || empty( $s['height'] ) ) {
				continue;
			}
			$file      = '' === $mime ? (string) ( $s['file'] ?? '' ) : (string) ( $s['sources'][ $mime ]['file'] ?? '' );
			$entries[] = [ (int) $s['width'], (int) $s['height'], $file ];
		}
		$out = [];
		foreach ( $entries as [ $w, $h, $file ] ) {
			if ( '' === $file || $w > $max || isset( $out[ $w ] ) || ! self::same_ratio( $fw, $fh, $w, $h ) ) {
				continue;
			}
			$out[ $w ] = $base . $file;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Same aspect ratio within a pixel of rounding (as wp_image_matches_ratio()).
	 */
	private static function same_ratio( int $w1, int $h1, int $w2, int $h2 ): bool {
		if ( $w1 <= 0 || $h1 <= 0 || $w2 <= 0 || $h2 <= 0 ) {
			return false;
		}
		return abs( $h1 * $w2 / $w1 - $h2 ) <= 1.0 || abs( $w1 * $h2 / $h1 - $w2 ) <= 1.0;
	}

	/**
	 * The folder URL of the attachment's files (with a trailing slash).
	 */
	private static function base_url( int $id ): string {
		$url = (string) wp_get_attachment_url( $id );
		$cut = strrpos( $url, '/' );
		return false === $cut ? '' : substr( $url, 0, $cut + 1 );
	}
}
