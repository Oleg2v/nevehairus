<?php
/**
 * Core tests 1 — NH_Letters (SPEC §11.5 item 1): parity with the prototype's build script.
 *
 * Every {{letterroom}}, {{letter}} and {{doorway}} of prototype/src/*.html (plus synthetic in-between
 * weights of every letter) → the PHP call with the same arguments (photo names → fixture ids) equals
 * the build script's output: same markup; numbers ± 0.001; URLs compared by basename; the `<img>`
 * width/height compared by presence (the fake library's full size is the original upload, the
 * prototype writes its largest derivative — same aspect ratio).
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * The prototype's letter directives with the build script's output (compare.py --prototype-letters).
 */
function nh_test_letter_items(): array {
	static $items = null;
	if ( null === $items ) {
		$cmd  = 'python3 -I ' . escapeshellarg( NH_TEST_DIR . '/compare.py' ) . ' --prototype-letters';
		$json = shell_exec( $cmd );
		$items = json_decode( (string) $json, true );
		if ( ! is_array( $items ) || ! $items ) {
			throw new RuntimeException( 'compare.py --prototype-letters gave no items (python3 missing?)' );
		}
	}
	return $items;
}

/**
 * Normalised HTML for parity: URLs by basename, img width/height by presence.
 */
function nh_test_letters_norm( string $html ): string {
	$html = str_replace( ' ' . NH_Picture::MARK, '', $html ); // the plugin's marker of its own img, gone in the_content
	$html = (string) preg_replace( '~[^\s"\',]*/([\w.-]+\.(?:webp|avif|jpe?g|png))~', '$1', $html );
	return (string) preg_replace( '~ width="\d+" height="\d+"~', ' width="#" height="#"', $html );
}

/**
 * Equal markup with numbers ± $tol; '' when equal, else a description of the first difference.
 */
function nh_test_letters_diff( string $want, string $got, float $tol = 0.001 ): string {
	$rx = '/(-?\d+(?:\.\d+)?)/';
	$a  = preg_split( $rx, $want, -1, PREG_SPLIT_DELIM_CAPTURE );
	$b  = preg_split( $rx, $got, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( count( $a ) !== count( $b ) ) {
		return "structure differs\n  want: " . substr( $want, 0, 600 ) . "\n  got:  " . substr( $got, 0, 600 );
	}
	foreach ( $a as $i => $x ) {
		$y = $b[ $i ];
		if ( 1 === $i % 2 ) {
			if ( abs( (float) $x - (float) $y ) > $tol ) {
				return "number #$i: want $x, got $y near …" . substr( implode( '', array_slice( $a, max( 0, $i - 6 ), 6 ) ), -120 );
			}
		} elseif ( $x !== $y ) {
			return "text differs: want «" . substr( $x, 0, 200 ) . "», got «" . substr( $y, 0, 200 ) . '»';
		}
	}
	return '';
}

/**
 * The PHP call of a directive.
 */
function nh_test_letter_call( array $item ): string {
	$a = $item['attrs'];
	switch ( $item['kind'] ) {
		case 'doorway':
			return NH_Letters::doorway();
		case 'letter':
			return NH_Letters::svg(
				$a['name'],
				(float) ( $a['w'] ?? 800 ),
				[
					'class' => $a['class'] ?? '',
					'frame' => $a['frame'] ?? '800',
				]
			);
	}
	$photo = isset( $a['photo'] ) && '' !== $a['photo'] ? nh_test_media_id( $a['photo'] ) : 0;
	if ( isset( $a['photo'] ) && '' !== $a['photo'] && 0 === $photo ) {
		throw new RuntimeException( 'no fixture photo ' . $a['photo'] );
	}
	return NH_Letters::room(
		$a['letter'],
		[
			'photo'      => $photo,
			'field'      => $a['field'] ?? '',
			'weight'     => (float) ( $a['w'] ?? 800 ),
			'frame'      => $a['frame'] ?? '800',
			'pos'        => $a['pos'] ?? '50% 50%',
			'sizes'      => $a['sizes'] ?? '50vw',
			'priority'   => 'high' === ( $a['priority'] ?? '' ),
			'alt'        => isset( $a['alt'] ) && '' !== $a['alt'] ? $a['alt'] : null,
			'decorative' => '1' === ( $a['decorative'] ?? '' ),
			'class'      => $a['class'] ?? '',
			'fit'        => $a['fit'] ?? 'cover',
			'work'       => isset( $a['work'] ) ? array_map( 'floatval', preg_split( '/\s+/', trim( $a['work'] ) ) ) : [ 0.5, 0.5, 0.48 ],
			'extra'      => (string) $item['extra'],
		]
	);
}

return [
	'parity with every letter directive of the prototype (+ synthetic weights)' => static function () {
		$n = 0;
		foreach ( nh_test_letter_items() as $item ) {
			$got  = nh_test_letters_norm( nh_test_letter_call( $item ) );
			$want = nh_test_letters_norm( $item['html'] );
			$diff = nh_test_letters_diff( $want, $got );
			nh_assert( '' === $diff, $item['page'] . ' {{' . $item['kind'] . ' ' . json_encode( $item['attrs'], JSON_UNESCAPED_UNICODE ) . "}}: $diff" );
			++$n;
		}
		nh_assert( $n >= 28, "only $n directives compared" );
	},

	'the parity is exact for the numbers (no tolerance needed)' => static function () {
		foreach ( nh_test_letter_items() as $item ) {
			$got  = nh_test_letters_norm( nh_test_letter_call( $item ) );
			$want = nh_test_letters_norm( $item['html'] );
			nh_assert_same( $want, $got, $item['page'] . ' ' . $item['kind'] . ' ' . json_encode( $item['attrs'], JSON_UNESCAPED_UNICODE ) );
		}
	},

	'v3.2: no morph data — data_script() is gone, no page prints #nh-letters (research/07 §4.4 item 11)' => static function () {
		nh_assert( ! method_exists( 'NH_Letters', 'data_script' ), 'NH_Letters::data_script() removed' );
		foreach ( glob( NH_TEST_ROOT . '/prototype/*.html' ) as $page ) {
			nh_assert_not_contains( 'id="nh-letters"', (string) file_get_contents( $page ), basename( $page ) );
		}
	},

	'path() is exact at 800 and 300 and clamps the weight' => static function () {
		$data = NH_Letters::data();
		foreach ( NH_Letters::names() as $name ) {
			nh_assert_same( $data[ $name ]['800']['d'], NH_Letters::path( $name, 800 ), "$name 800" );
			nh_assert_same( $data[ $name ]['300']['d'], NH_Letters::path( $name, 300 ), "$name 300" );
			nh_assert_same( $data[ $name ]['300']['d'], NH_Letters::path( $name, 120 ), "$name below 300" );
			nh_assert_same( $data[ $name ]['800']['d'], NH_Letters::path( $name, 999 ), "$name above 800" );
		}
		nh_assert_same( '', NH_Letters::path( 'aleph', 800 ), 'unknown letter' );
	},

	'geometry() has the properties of room() in order, --t last' => static function () {
		$g = NH_Letters::geometry( 'bet', [ 'weight' => 600, 'pos' => '55% 45%' ] );
		nh_assert_same( [ '--fx', '--fy', '--fw', '--fh', '--at', '--ar', '--ab', '--al', '--bt', '--br', '--bb', '--bl', '--pos', '--asp', '--cx', '--cy', '--sqw', '--em-w', '--t' ], array_keys( $g ) );
		nh_assert_same( '0.4', $g['--t'] );
		nh_assert_same( '55% 45%', $g['--pos'] );
		nh_assert_same( '432.4/597', $g['--asp'] );
		$whole = NH_Letters::geometry(
			'samekh',
			[
				'photo'  => nh_test_media_id( 'v3-mandala' ),
				'fit'    => 'whole',
				'weight' => 380,
				'work'   => [ 0.5, 0.5552, 0.4876 ],
			]
		);
		foreach ( [ '--clip', '--wl', '--wt', '--ww', '--wh', '--wcx', '--wcy' ] as $k ) {
			nh_assert( isset( $whole[ $k ] ), "whole mode has $k" );
		}
		nh_assert_same( '55.52%', $whole['--wcy'] );
	},

	'room() edge cases: no room, bad position, missing photo, field, attrs' => static function () {
		nh_assert_same( '', NH_Letters::room( 'dalet' ), 'dalet has no room' );
		nh_assert_same( '', NH_Letters::room( 'lamed' ), 'lamed has no room' );
		$r = NH_Letters::room( 'bet', [ 'pos' => 'url(x) 1%', 'photo' => 999999, 'field' => 'sky', 'attrs' => [ 'data-vt' => 'home-bet-11' ] ] );
		nh_assert_contains( '--pos:50% 50%;', $r, 'invalid position falls back' );
		nh_assert_contains( '<span class="v3-room__photo v3-room__field v3-room__field--sky" aria-hidden="true"></span>', $r, 'missing photo → field' );
		nh_assert_contains( 'data-room="bet" data-vt="home-bet-11">', $r, 'extra attributes after data-room' );
		$no = NH_Letters::room( 'bet', [ 'field' => '"><script>' ] );
		nh_assert_not_contains( '<script', $no, 'field is sanitised' );
		nh_assert_not_contains( 'v3-room__field', $no, 'invalid field = no field' );
		$whole_no_photo = NH_Letters::room( 'samekh', [ 'fit' => 'whole', 'field' => 'sky' ] );
		nh_assert_not_contains( '--clip', $whole_no_photo, 'whole mode needs a photo' );
	},

	'svg() and doorway() are decorative' => static function () {
		$s = NH_Letters::svg( 'lamed', 800 );
		nh_assert_contains( 'aria-hidden="true" focusable="false" data-letter="lamed"', $s );
		$d = NH_Letters::doorway();
		nh_assert_same( 2, substr_count( $d, 'aria-hidden="true" focusable="false"' ) );
		nh_assert( 0 === strpos( $d, '<div class="v3-door__frame" aria-hidden="true" data-door>' ) );
		nh_assert_same( '0', NH_Letters::num( 0.0 ) );
		nh_assert_same( '432.4', NH_Letters::num( 432.4 ) );
		nh_assert_same( '0.84', NH_Letters::num( ( 800 - 380 ) / 500 ) );
	},
];
