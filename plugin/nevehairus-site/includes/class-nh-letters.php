<?php
defined( 'ABSPATH' ) || exit;

/**
 * The letters: outlines from `assets/letters.json` (Frank Ruhl Libre at wght 800 and 300, compatible
 * points), rooms (the counters where photos live), the wide ד of the door and the morph data of the
 * cover (SPEC §3.7). A faithful port of scripts/build_prototype_html.py: letter_svg(), lerp_d(),
 * letter_room() (incl. fit="whole"), doorway(), letter_data() — same markup, same numbers and formats.
 *
 * @package nevehairus-site
 */
final class NH_Letters {

	/** The photo bleeds this many font units under the ink on every closed side (no paper seam). */
	private const BLEED = 4;

	/** Letters whose room is open on the left (ב, כ): no bleed there. */
	private const OPEN_LEFT = [ 'bet', 'kaf' ];

	/** Path commands and their number of coordinates. */
	private const ARITY = [
		'M' => 2,
		'L' => 2,
		'Q' => 4,
		'C' => 6,
		'Z' => 0,
	];

	/** @var array|null letters.json, decoded once. */
	private static $data = null;

	/** @var bool data_script() printed in this request. */
	private static $script_done = false;

	/**
	 * letters.json: `upm`, `top`, `source` and per letter `{ "800": {d, box, adv, room}, "300": {…}, lerp, cmds, a, b }`.
	 */
	public static function data(): array {
		if ( null === self::$data ) {
			$file       = NH_DIR . 'assets/letters.json';
			$raw        = is_readable( $file ) ? file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file
			$json       = json_decode( (string) $raw, true );
			self::$data = is_array( $json ) ? $json : [];
		}
		return self::$data;
	}

	/**
	 * The outline `d` at a weight: exact for 800 and 300, interpolated in between (lerp_d()).
	 */
	public static function path( string $name, float $weight = 800 ): string {
		$e = self::entry( $name );
		if ( null === $e ) {
			return '';
		}
		$weight = self::weight( $weight );
		if ( 800.0 === $weight ) {
			return (string) $e['800']['d'];
		}
		if ( 300.0 === $weight ) {
			return (string) $e['300']['d'];
		}
		$t    = ( 800 - $weight ) / 500;
		$cmds = is_array( $e['cmds'] ) ? implode( '', $e['cmds'] ) : (string) $e['cmds'];
		$a    = (array) $e['a'];
		$b    = (array) $e['b'];
		$out  = '';
		$i    = 0;
		foreach ( str_split( $cmds ) as $c ) {
			$nums = [];
			for ( $k = 0, $n = self::ARITY[ $c ] ?? 0; $k < $n; $k++, $i++ ) {
				$nums[] = sprintf( '%.1f', $a[ $i ] + ( $b[ $i ] - $a[ $i ] ) * $t );
			}
			$out .= $c . implode( ' ', $nums );
		}
		return $out;
	}

	/**
	 * Inline, decorative SVG of a letter (letter_svg()). Options: class, frame ('800'|'300': the box of
	 * the viewBox).
	 */
	public static function svg( string $name, float $weight = 800, array $o = [] ): string {
		$e = self::entry( $name );
		if ( null === $e ) {
			return '';
		}
		[ $fx, $fy, $fw, $fh ] = self::frame( $name, self::frame_key( $o['frame'] ?? '800' ) );
		$cls                   = self::classes( (string) ( $o['class'] ?? '' ) );
		return '<svg class="v3-letter v3-letter--' . $name . ( '' !== $cls ? ' ' . $cls : '' ) . '"'
			. ' viewBox="' . self::num( $fx ) . ' ' . self::num( $fy ) . ' ' . self::num( $fw ) . ' ' . self::num( $fh ) . '"'
			. ' preserveAspectRatio="xMidYMax meet" aria-hidden="true" focusable="false" data-letter="' . $name . '">'
			. '<path class="v3-letter__ink" d="' . self::path( $name, $weight ) . '"/></svg>';
	}

	/**
	 * A letter with a room (letter_room()). Options: photo (attachment id, 0 = none), field (''|'sky', only
	 * without photo), weight (300–800, default 800), frame ('800'|'300'), pos (object-position "x% y%"),
	 * sizes (default 50vw), priority, alt (?string), decorative, class, fit ('cover'|'whole'),
	 * work ([cx, cy, r] fractions of the photo), extra (trusted HTML: inside .v3-room__work in whole mode,
	 * after the svg otherwise), attrs (extra attributes of the room div). '' for a letter without a room.
	 */
	public static function room( string $name, array $o = [] ): string {
		$e = self::entry( $name );
		if ( null === $e || empty( $e['800']['room'] ) || empty( $e['300']['room'] ) ) {
			return '';
		}
		$o     = self::options( $o );
		$photo = NH_Picture::exists( $o['photo'] ) ? $o['photo'] : 0;
		$whole = 'whole' === $o['fit'] && $photo > 0;

		$style = '';
		foreach ( self::geometry( $name, $o ) as $prop => $value ) {
			$style .= $prop . ':' . $value . ';';
		}

		$pic   = '';
		$after = $o['extra'];
		if ( $whole ) {
			$pic   = '<div class="v3-room__photo v3-room__photo--whole"><div class="v3-room__work">'
				. NH_Picture::render(
					$photo,
					[
						'class'      => 'v3-room__img',
						'sizes'      => $o['sizes'],
						'loading'    => 'lazy',
						'alt'        => $o['alt'],
						'decorative' => $o['decorative'],
					]
				)
				. $o['extra'] . '</div></div>';
			$after = '';
		} elseif ( $photo > 0 ) {
			$pic = NH_Picture::render(
				$photo,
				[
					'class'      => 'v3-room__photo',
					'sizes'      => $o['sizes'],
					'loading'    => $o['priority'] ? 'eager' : 'lazy',
					'priority'   => $o['priority'],
					'alt'        => $o['alt'],
					'decorative' => $o['decorative'],
				]
			);
		} elseif ( '' !== $o['field'] ) {
			$pic = '<span class="v3-room__photo v3-room__field v3-room__field--' . $o['field'] . '" aria-hidden="true"></span>';
		}

		$cls = self::classes( $o['class'] );
		return '<div class="v3-room v3-room--' . $name . ( '' !== $cls ? ' ' . $cls : '' ) . '"'
			. ' style="' . esc_attr( $style ) . '" data-room="' . $name . '"' . NH_Html::attrs( $o['attrs'] ) . '>'
			. $pic . self::svg( $name, $o['weight'], [ 'frame' => $o['frame'] ] ) . $after . '</div>';
	}

	/**
	 * The CSS custom properties of room(), in order, as strings (for tests and for room() itself).
	 *
	 * @return array<string,string>
	 */
	public static function geometry( string $name, array $o = [] ): array {
		$e = self::entry( $name );
		if ( null === $e || empty( $e['800']['room'] ) || empty( $e['300']['room'] ) ) {
			return [];
		}
		$o                     = self::options( $o );
		[ $fx, $fy, $fw, $fh ] = self::frame( $name, $o['frame'] );

		$open = in_array( $name, self::OPEN_LEFT, true );
		$r8   = self::grow( (array) $e['800']['room'], $open );
		$r3   = self::grow( (array) $e['300']['room'], $open );
		$ux   = min( $r8['x'], $r3['x'] );
		$uy   = min( $r8['y'], $r3['y'] );
		$ur   = max( $r8['x'] + $r8['w'], $r3['x'] + $r3['w'] );
		$ub   = max( $r8['y'] + $r8['h'], $r3['y'] + $r3['h'] );
		$uw   = $ur - $ux;
		$uh   = $ub - $uy;
		$i8   = self::insets( $r8, $ux, $uy, $ur, $ub, $uw, $uh );
		$i3   = self::insets( $r3, $ux, $uy, $ur, $ub, $uw, $uh );

		$v         = [];
		$v['--fx'] = self::f( ( $ux - $fx ) / $fw * 100, 3 ) . '%';
		$v['--fy'] = self::f( ( $uy - $fy ) / $fh * 100, 3 ) . '%';
		$v['--fw'] = self::f( $uw / $fw * 100, 3 ) . '%';
		$v['--fh'] = self::f( $uh / $fh * 100, 3 ) . '%';
		foreach ( [ 't', 'r', 'b', 'l' ] as $k => $side ) {
			$v[ '--a' . $side ] = self::f( $i8[ $k ], 3 ) . '%';
		}
		foreach ( [ 't', 'r', 'b', 'l' ] as $k => $side ) {
			$v[ '--b' . $side ] = self::f( $i3[ $k ], 3 ) . '%';
		}
		$v['--pos']  = $o['pos'];
		$v['--asp']  = self::num( $fw ) . '/' . self::num( $fh );
		$v['--cx']   = self::f( ( $ux + $uw / 2 - $fx ) / $fw * 100, 3 ) . '%';
		$v['--cy']   = self::f( ( $uy + $uh / 2 - $fy ) / $fh * 100, 3 ) . '%';
		$v['--sqw']  = self::f( max( $uw, $uh ) / $fw * 100, 3 ) . '%';
		$v['--em-w'] = self::f( $fw / 1000, 4 );

		$t0 = ( 800 - $o['weight'] ) / 500;
		if ( 'whole' === $o['fit'] && NH_Picture::exists( $o['photo'] ) ) {
			// A resident's work is shown whole, never cropped: the photo is placed so the work's centre sits
			// at the centre of the room at this weight, scaled so the work's radius fits the ellipse inscribed
			// in the room; the room is clipped by that ellipse (build script, fit="whole").
			[ $wcx, $wcy, $wr ] = $o['work'];
			$ins                = [];
			foreach ( $i8 as $k => $a ) {
				$ins[ $k ] = $a + ( $i3[ $k ] - $a ) * $t0;
			}
			$rl  = $ux + $uw * $ins[3] / 100;
			$rt  = $uy + $uh * $ins[0] / 100;
			$rr  = $ur - $uw * $ins[1] / 100;
			$rb  = $ub - $uh * $ins[2] / 100;
			$ecx = ( $rl + $rr ) / 2;
			$ecy = ( $rt + $rb ) / 2;
			$erx = ( $rr - $rl ) / 2;
			$ery = ( $rb - $rt ) / 2;

			[ $pw0, $ph0 ] = NH_Picture::dims( $o['photo'] );
			$pw            = min( $erx, $ery ) * 0.97 / $wr;
			$ph            = ( $pw0 > 0 && $ph0 > 0 ) ? $pw * $ph0 / $pw0 : $pw; // square if unknown
			$left          = $ecx - $wcx * $pw;
			$top           = min( $ecy - $wcy * $ph, $rt ); // the photo's top stays under the roof of the letter
			$cr            = 1.07; // the clip reaches a little under the ink

			$v['--clip'] = 'ellipse(' . self::f( $erx * $cr / $uw * 100, 3 ) . '% ' . self::f( $ery * $cr / $uh * 100, 3 ) . '% at '
				. self::f( ( $ecx - $ux ) / $uw * 100, 3 ) . '% ' . self::f( ( $ecy - $uy ) / $uh * 100, 3 ) . '%)';
			$v['--wl']   = self::f( ( $left - $ux ) / $uw * 100, 3 ) . '%';
			$v['--wt']   = self::f( ( $top - $uy ) / $uh * 100, 3 ) . '%';
			$v['--ww']   = self::f( $pw / $uw * 100, 3 ) . '%';
			$v['--wh']   = self::f( $ph / $uh * 100, 3 ) . '%';
			$v['--wcx']  = self::f( $wcx * 100, 2 ) . '%';
			$v['--wcy']  = self::f( $wcy * 100, 2 ) . '%';
		}
		$v['--t'] = self::num( $t0 );
		return $v;
	}

	/**
	 * The ד of the door spread (doorway()), byte-identical to the prototype: head and cap are two views
	 * of the same dalet-300 outline, the bar and the leg are spans.
	 */
	public static function doorway(): string {
		$e = self::entry( 'dalet' );
		if ( null === $e ) {
			return '';
		}
		$svg = static function ( string $cls, string $vb ) use ( $e ): string {
			return '<svg class="v3-door__' . $cls . '" viewBox="' . $vb . '" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
				. '<path class="v3-letter__ink" d="' . $e['300']['d'] . '"/></svg>';
		};
		return '<div class="v3-door__frame" aria-hidden="true" data-door>'
			. $svg( 'head', '250 180 160 540' )
			. '<span class="v3-door__bar"></span>'
			. $svg( 'cap', '25 180 75 122' )
			. '<span class="v3-door__leg"></span></div>';
	}

	/**
	 * The interpolation data of the cover morph (letter_data()):
	 * `<script type="application/json" id="nh-letters">{"bet":{"c":…,"a":[…],"b":[…]}}</script>`.
	 * Once per request; later calls return ''.
	 *
	 * @param string[] $names Letters.
	 */
	public static function data_script( array $names = [ 'bet' ] ): string {
		if ( self::$script_done ) {
			return '';
		}
		$out = [];
		foreach ( $names as $n ) {
			$e = self::entry( (string) $n );
			if ( null !== $e ) {
				$out[ (string) $n ] = [
					'c' => $e['cmds'],
					'a' => $e['a'],
					'b' => $e['b'],
				];
			}
		}
		if ( ! $out ) {
			return '';
		}
		self::$script_done = true;
		// Shortest round-trip floats, as Python's json.dumps (some hosts still set serialize_precision = 17).
		$precision = ini_get( 'serialize_precision' );
		ini_set( 'serialize_precision', '-1' ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- restored right below
		$json = wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
		ini_set( 'serialize_precision', false === $precision ? '-1' : $precision ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		return '<script type="application/json" id="nh-letters">' . $json . '</script>';
	}

	/**
	 * A number as Python's "{:g}" (six significant digits, no trailing zeros).
	 */
	public static function num( float $v ): string {
		return sprintf( '%g', $v );
	}

	/**
	 * Letter names in letters.json.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		$names = [];
		foreach ( self::data() as $k => $e ) {
			if ( is_array( $e ) && isset( $e['800'], $e['300'] ) ) {
				$names[] = (string) $k;
			}
		}
		return $names;
	}

	/**
	 * A letter's entry, or null.
	 */
	private static function entry( string $name ): ?array {
		$data = self::data();
		if ( ! preg_match( '/^[a-z_]+$/', $name ) || ! isset( $data[ $name ]['800'], $data[ $name ]['300'] ) || ! is_array( $data[ $name ] ) ) {
			return null;
		}
		return $data[ $name ];
	}

	/**
	 * The frame of a letter (its outline box at 800 or 300, font units, y down).
	 *
	 * @return float[] [ x, y, w, h ]
	 */
	private static function frame( string $name, string $at ): array {
		$b = (array) self::entry( $name )[ $at ]['box'];
		return [ (float) $b['x'], (float) $b['y'], (float) $b['w'], (float) $b['h'] ];
	}

	/**
	 * The room grown by the bleed on its closed sides.
	 */
	private static function grow( array $r, bool $open_left ): array {
		$b = self::BLEED;
		return [
			'x' => $r['x'] - ( $open_left ? 0 : $b ),
			'y' => $r['y'] - $b,
			'w' => $r['w'] + $b * ( $open_left ? 1 : 2 ),
			'h' => $r['h'] + 2 * $b,
		];
	}

	/**
	 * Insets of a room in % of the union box, in t r b l order.
	 *
	 * @return float[]
	 */
	private static function insets( array $r, $ux, $uy, $ur, $ub, $uw, $uh ): array {
		return [
			( $r['y'] - $uy ) / $uh * 100,
			( $ur - ( $r['x'] + $r['w'] ) ) / $uw * 100,
			( $ub - ( $r['y'] + $r['h'] ) ) / $uh * 100,
			( $r['x'] - $ux ) / $uw * 100,
		];
	}

	/**
	 * Normalised room options.
	 */
	private static function options( array $o ): array {
		$o = array_merge(
			[
				'photo'      => 0,
				'field'      => '',
				'weight'     => 800,
				'frame'      => '800',
				'pos'        => '50% 50%',
				'sizes'      => '50vw',
				'priority'   => false,
				'alt'        => null,
				'decorative' => false,
				'class'      => '',
				'fit'        => 'cover',
				'work'       => [ 0.5, 0.5, 0.48 ],
				'extra'      => '',
				'attrs'      => [],
			],
			$o
		);

		$pos   = trim( (string) $o['pos'] );
		$work  = is_array( $o['work'] ) ? array_values( $o['work'] ) : preg_split( '/\s+/', trim( (string) $o['work'] ) );
		$work  = [
			isset( $work[0] ) && is_numeric( $work[0] ) ? (float) $work[0] : 0.5,
			isset( $work[1] ) && is_numeric( $work[1] ) ? (float) $work[1] : 0.5,
			isset( $work[2] ) && is_numeric( $work[2] ) && (float) $work[2] > 0 ? (float) $work[2] : 0.48,
		];
		$sizes = trim( (string) $o['sizes'] );

		return [
			'photo'      => absint( $o['photo'] ),
			'field'      => preg_match( '/^[a-z][a-z0-9-]*$/', (string) $o['field'] ) ? (string) $o['field'] : '',
			'weight'     => self::weight( is_numeric( $o['weight'] ) ? (float) $o['weight'] : 800.0 ),
			'frame'      => self::frame_key( $o['frame'] ),
			'pos'        => preg_match( '/^\d{1,3}(\.\d+)?% \d{1,3}(\.\d+)?%$/', $pos ) ? $pos : '50% 50%',
			'sizes'      => '' !== $sizes ? $sizes : '50vw',
			'priority'   => (bool) $o['priority'],
			'alt'        => null === $o['alt'] ? null : (string) $o['alt'],
			'decorative' => (bool) $o['decorative'],
			'class'      => (string) $o['class'],
			'fit'        => 'whole' === $o['fit'] ? 'whole' : 'cover',
			'work'       => $work,
			'extra'      => (string) $o['extra'],
			'attrs'      => is_array( $o['attrs'] ) ? $o['attrs'] : [],
		];
	}

	/**
	 * A weight clamped to 300–800 and rounded to a whole unit (the morph works in steps of 1).
	 */
	private static function weight( float $w ): float {
		return (float) round( max( 300.0, min( 800.0, $w ) ) );
	}

	/**
	 * '800' or '300'.
	 *
	 * @param mixed $frame Frame option.
	 */
	private static function frame_key( $frame ): string {
		return '300' === (string) $frame ? '300' : '800';
	}

	/**
	 * Fixed-point number as Python's "{:.Nf}".
	 */
	private static function f( float $v, int $decimals ): string {
		return sprintf( '%.' . $decimals . 'f', $v );
	}

	/**
	 * Sanitised class list.
	 */
	private static function classes( string $classes ): string {
		$out = [];
		foreach ( preg_split( '/\s+/', trim( $classes ) ) as $c ) {
			$c = sanitize_html_class( $c );
			if ( '' !== $c ) {
				$out[] = $c;
			}
		}
		return implode( ' ', $out );
	}
}
