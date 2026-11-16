<?php
/**
 * Core tests 3 — NH_Picture (SPEC §11.5 item 3): a fixture image with its AVIF source, decorative,
 * alt override, priority, a missing attachment; src(), dims(), preload().
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * The `{file} {w}w, …` list of a fixture photo in one format, from the prototype's manifest widths (the
 * A track exports each photo's own width too, research/07 §11.2 item 3: v3-walk 480, 768, 1080, 1224).
 */
function nh_test_picture_set( string $name, string $ext ): string {
	$manifest = json_decode( (string) file_get_contents( NH_TEST_ROOT . '/prototype/assets/img/manifest.json' ), true );
	$widths   = array_map( 'intval', (array) ( $manifest[ $name ]['widths'] ?? [] ) );
	sort( $widths );
	return implode(
		', ',
		array_map(
			static function ( int $w ) use ( $name, $ext ): string {
				return NH_TEST_MEDIA_BASE . $name . '-' . $w . '.' . $ext . ' ' . $w . 'w';
			},
			$widths
		)
	);
}

return [
	'render(): a fixture image with AVIF source, in the prototype\'s attribute order' => static function () {
		$id   = nh_test_media_id( 'v3-walk' );
		$base = NH_TEST_MEDIA_BASE . 'v3-walk-';
		$want = '<picture class="v3-room__photo">'
			. '<source type="image/avif" srcset="' . nh_test_picture_set( 'v3-walk', 'avif' ) . '" sizes="(min-width: 1024px) 30rem, 44vw">'
			. '<img data-nh-pic src="' . $base . '768.webp" srcset="' . nh_test_picture_set( 'v3-walk', 'webp' ) . '" sizes="(min-width: 1024px) 30rem, 44vw" width="1224" height="1571" alt="' . esc_attr( NH_Picture::alt( $id ) ) . '" loading="lazy" decoding="async">'
			. '</picture>';
		nh_assert_same(
			$want,
			NH_Picture::render(
				$id,
				[
					'class' => 'v3-room__photo',
					'sizes' => '(min-width: 1024px) 30rem, 44vw',
				]
			)
		);
	},

	'render(): decorative, alt override, priority, defaults' => static function () {
		$id = nh_test_media_id( 'v3-owls' );
		nh_assert_contains( ' alt="" ', NH_Picture::render( $id, [ 'decorative' => true, 'alt' => 'ignored' ] ) );
		nh_assert_contains( ' alt="ינשופים &quot;שלושה&quot;" ', NH_Picture::render( $id, [ 'alt' => 'ינשופים "שלושה"' ] ) );
		nh_assert_contains( ' alt="' . esc_attr( NH_Picture::alt( $id ) ) . '" ', NH_Picture::render( $id, [ 'alt' => '' ] ), 'empty override = library alt' );
		$p = NH_Picture::render( $id, [ 'priority' => true, 'loading' => 'eager' ] );
		nh_assert_contains( 'loading="eager" decoding="async" fetchpriority="high"></picture>', $p );
		nh_assert_contains( 'sizes="100vw"', $p, 'default sizes' );
		nh_assert( 0 === strpos( $p, '<picture><source' ), 'no class attribute without a class' );
		nh_assert_contains( 'loading="lazy"', NH_Picture::render( $id, [ 'loading' => 'bogus' ] ) );
	},

	'content_img_tag(): our image as render() printed it after the_content (no attribute twice, no auto sizes, no marker); others untouched' => static function () {
		$id   = nh_test_media_id( 'v3-villa' );
		$mine = NH_Picture::render( $id, [ 'sizes' => '9.5rem' ] );
		nh_assert( 1 === preg_match( '~<img [^>]+>~', $mine, $m ), 'render() prints one img' );
		$img   = $m[0];
		$clean = str_replace( ' ' . NH_Picture::MARK, '', $img );
		nh_assert_same( 20, has_filter( 'wp_content_img_tag', [ 'NH_Picture', 'content_img_tag' ] ), 'hooked after Elementor\'s Optimized Image Loading (10)' );
		nh_assert_same( $clean, NH_Picture::content_img_tag( $img ), 'untouched by other filters: only the marker goes' );
		// WordPress 6.7+ (auto sizes of a lazy image), then Elementor 4.4.0 (loading put in front again)
		$filtered = '<img loading="lazy"' . substr( str_replace( 'sizes="9.5rem"', 'sizes="auto, 9.5rem"', $img ), 4 );
		nh_assert_same( 2, substr_count( $filtered, 'loading="lazy"' ), 'the content filters\' output, as on the site' );
		nh_assert_same( $clean, NH_Picture::content_img_tag( $filtered ), 'one loading, the sizes of render(), no marker, render()\'s order' );
		// the cover: fetchpriority put in front again; an attribute another filter added once stays
		$cover    = NH_Picture::render( $id, [ 'priority' => true, 'loading' => 'eager', 'sizes' => '44vw' ] );
		nh_assert( 1 === preg_match( '~<img [^>]+>~', $cover, $c ) );
		$filtered = '<img fetchpriority="high" data-extra="1"' . substr( $c[0], 4 );
		$out      = NH_Picture::content_img_tag( $filtered );
		nh_assert_same( 1, substr_count( $out, 'fetchpriority=' ), 'fetchpriority once' );
		nh_assert_same( '<img data-extra="1"' . substr( str_replace( ' ' . NH_Picture::MARK, '', $c[0] ), 4 ), $out );
		// not ours: as it came, duplicates and all
		$theirs = '<img loading="lazy" src="https://nh.test/x.webp" sizes="auto, 50vw" width="10" height="10" alt="" loading="lazy">';
		nh_assert_same( $theirs, NH_Picture::content_img_tag( $theirs ) );
		nh_assert_same( 7, NH_Picture::content_img_tag( 7 ), 'not a string: as it came' );
	},

	'render(): a missing or non-image attachment gives nothing' => static function () {
		nh_assert_same( '', NH_Picture::render( 0 ) );
		nh_assert_same( '', NH_Picture::render( 999999 ) );
		nh_assert_same( '', NH_Picture::render( 10 ), 'a page is not an image' );
		nh_assert( ! NH_Picture::exists( 10 ) );
		nh_assert_same( '', NH_Picture::srcset( 999999 ) );
		nh_assert_same( [ 0, 0 ], NH_Picture::dims( 999999 ) );
	},

	'render(): no AVIF source when the metadata has none or the file is AVIF; no srcset without one' => static function () {
		$id = nh_test_media_id( 'v3-villa' );
		$m  = $GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata'];
		foreach ( $GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata']['sizes'] as $k => $s ) {
			unset( $GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata']['sizes'][ $k ]['sources']['image/avif'] );
		}
		$p = NH_Picture::render( $id );
		nh_assert_not_contains( '<source', $p );
		nh_assert_same( '', NH_Picture::avif_srcset( $id ) );
		$GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata'] = $m;
		$GLOBALS['nh_test']['posts'][ $id ]['mime']                   = 'image/avif';
		nh_assert_same( '', NH_Picture::avif_srcset( $id ), 'the attachment itself is AVIF' );
		$GLOBALS['nh_test']['posts'][ $id ]['mime'] = 'image/webp';
		$GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata']['sizes'] = [];
		$p = NH_Picture::render( $id );
		nh_assert_not_contains( 'srcset', $p );
		nh_assert_not_contains( 'sizes=', $p );
		$GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata'] = $m;
	},

	'src(), dims(), alt()' => static function () {
		$id = nh_test_media_id( 'dining' );
		nh_assert_same( NH_TEST_MEDIA_BASE . 'dining-1440.webp', NH_Picture::src( $id, 1440 ) );
		nh_assert_same( NH_TEST_MEDIA_BASE . 'dining-768.webp', NH_Picture::src( $id, 768 ) );
		nh_assert_same( NH_TEST_MEDIA_BASE . 'dining-480.webp', NH_Picture::src( $id, 100 ), 'fallback: the smallest' );
		nh_assert_same( [ 3024, 4032 ], NH_Picture::dims( $id ) );
		nh_assert_same( 'שולחן אוכל מעץ בהיר עם כיסאות, בפינת האוכל המשותפת באגף החדש', NH_Picture::alt( $id ) );
	},

	'preload(): the AVIF set as the prototype\'s head, or the image set' => static function () {
		$id   = nh_test_media_id( 'v3-walk' );
		$base = NH_TEST_MEDIA_BASE . 'v3-walk-';
		nh_assert_same(
			'<link rel="preload" as="image" type="image/avif" href="' . $base . '768.avif" imagesrcset="' . nh_test_picture_set( 'v3-walk', 'avif' ) . '" imagesizes="(min-width: 1024px) 30rem, 44vw" fetchpriority="high">',
			NH_Picture::preload( $id, '(min-width: 1024px) 30rem, 44vw' )
		);
		$m = $GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata'];
		foreach ( $m['sizes'] as $k => $s ) {
			unset( $GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata']['sizes'][ $k ]['sources'] );
		}
		$p = NH_Picture::preload( $id, '50vw' );
		nh_assert_not_contains( 'type=', $p );
		nh_assert_contains( 'href="' . $base . '768.webp" imagesrcset="' . $base . '480.webp 480w', $p );
		$GLOBALS['nh_test']['meta'][ $id ]['_wp_attachment_metadata'] = $m;
		nh_assert_same( '', NH_Picture::preload( 999999, '50vw' ) );
	},
];
