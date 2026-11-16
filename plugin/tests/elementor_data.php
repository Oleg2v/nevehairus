<?php
/**
 * Elementor data of the two prototype pages for step 4 (scripts/build_pages.py), and its render check.
 * Runs on the stub harness, like run.php; never talks to a server.
 *
 *     php plugin/tests/elementor_data.php dump  < SPEC.json    the widgets' settings, as JSON on stdout
 *     php plugin/tests/elementor_data.php check < SPEC.json    render check of the files written from them
 *
 * dump — SPEC: { "chrome": { "<key>": { "widget", "variant", "extra"? } } }. The pages come from
 * fixtures/pages.php; a variant a widget lacks falls back to `prototype`, then `default` (as run.php).
 * Each widget gets its fixture variant's settings (plus `extra`, merged as the stub merges settings:
 * array_replace_recursive) in the shape Elementor stores them:
 *   - only the keys the variant sets (Elementor fills every other control with its default on render);
 *   - a repeater the variant touches is written whole: Elementor replaces a saved repeater (it does not merge
 *     it with the default rows), so every row carries all its fields and its `_id` — the stub's
 *     md5( "{element id}/{control}/{n}" ), 7 hex;
 *   - a media control becomes { url: '', id: '@media:<manifest name>', size: '', alt: '', source: 'library' }
 *     (scripts/test_site.py puts the attachment id and URL in on deploy).
 * Element ids are the harness's (md5( "{widget}/{variant}" ), 7 hex), so the site renders the same ids.
 *
 * check — SPEC: { "dir", "contexts": { "<page key>": { "page", "header_ref", "footer_ref", "tokens" } },
 * "chrome": { "header": "<file key>", "footer": "<file key>" } }. For each page context: every widget of ours in
 * <dir>/<page key>.json and in the header and footer files, with the tokens mapped back to the harness's
 * values (photos → fixture attachment ids, the context's `tokens`, @home:… → home_url(…)), must render
 * exactly like its fixture variant (the chrome: like `header_ref` / `footer_ref`), on the variant's queried
 * post; then the page assembled from the files must equal the run.php assembly of fixtures/pages.php with that
 * chrome, on the page's queried post. Exit 1 on any difference. v3.2 (research/07 §12.6): the footer file is
 * Elementor's own Image, Heading and Text Editor in inner containers, which the harness does not render, and one
 * widget of ours (nh-display): only that widget takes part (the footer slot of pages.php).
 *
 * @package nevehairus-site
 */

require __DIR__ . '/bootstrap.php';

/**
 * [ resolved variant name, variant ] with run.php's fallback (the variant, then `prototype`, then `default`).
 */
function nh_ed_variant( string $id, string $variant ): array {
	$fx = nh_test_fixture( $id );
	foreach ( [ $variant, 'prototype', 'default' ] as $try ) {
		if ( isset( $fx['variants'][ $try ] ) ) {
			return [ $try, $fx['variants'][ $try ] ];
		}
	}
	throw new RuntimeException( "widget $id has no variant $variant" );
}

/**
 * A widget instance as Elementor builds it from saved data: element id + settings.
 */
function nh_ed_widget( string $id, string $element_id, array $settings ) {
	$class = NH_Plugin::widgets()[ $id ] ?? null;
	if ( null === $class ) {
		throw new RuntimeException( "widget \"$id\" is not registered" );
	}
	return new $class(
		[
			'id'       => $element_id,
			'settings' => $settings,
		],
		[]
	);
}

/**
 * The stub's merged settings (control defaults + saved settings; repeater rows with their field defaults
 * and `_id`) — Widget_Base::nh_settings() of stubs/elementor.php.
 */
function nh_ed_merged( \Elementor\Widget_Base $widget ): array {
	$widget->get_controls(); // registers the controls
	$m = new ReflectionMethod( \Elementor\Widget_Base::class, 'nh_settings' );
	$m->setAccessible( true );
	return (array) $m->invoke( $widget );
}

/**
 * A media value as Elementor stores it, the photo as a token; an empty one unchanged.
 *
 * @param mixed    $value Saved media value.
 * @param string[] $media Names of the photos used (appended).
 * @param string   $where For the error message.
 */
function nh_ed_media( $value, array &$media, string $where ) {
	if ( ! is_array( $value ) ) {
		throw new RuntimeException( "$where: a media value must be an array" );
	}
	$aid = $value['id'] ?? '';
	if ( '' === $aid || null === $aid || 0 === (int) $aid ) {
		return $value;
	}
	$name = nh_test_media_name( (int) $aid );
	if ( '' === $name ) {
		throw new RuntimeException( "$where: attachment $aid is not a photo of prototype/assets/img/manifest.json" );
	}
	$media[] = $name;
	return [
		'url'    => '',
		'id'     => '@media:' . $name,
		'size'   => '',
		'alt'    => '',
		'source' => 'library',
	];
}

/**
 * One widget of a page in Elementor's shape: [ widget, variant, id, settings, media ].
 *
 * @param string $id      Widget id (without nh-).
 * @param string $variant Fixture variant (falls back as in run.php).
 * @param array  $extra   Settings merged over the variant's (array_replace_recursive).
 */
function nh_ed_shape( string $id, string $variant, array $extra = [] ): array {
	[ $variant, $v ] = nh_ed_variant( $id, $variant );
	$saved           = array_replace_recursive( (array) $v['settings'], $extra );
	$element         = nh_test_element_id( $id, $variant );
	$widget          = nh_ed_widget( $id, $element, $saved );
	$controls        = $widget->get_controls();
	$merged          = nh_ed_merged( $widget );
	$media           = [];
	$out             = [];
	foreach ( $saved as $key => $value ) {
		$c = $controls[ $key ] ?? null;
		if ( null === $c ) {
			throw new RuntimeException( "$id/$variant: \"$key\" is not a control of nh-$id" );
		}
		if ( 'repeater' === $c['type'] ) {
			$types = [];
			foreach ( (array) ( $c['fields'] ?? [] ) as $fid => $f ) {
				$types[ (string) ( $f['name'] ?? $fid ) ] = (string) ( $f['type'] ?? '' );
			}
			$rows = [];
			foreach ( array_values( (array) ( $merged[ $key ] ?? [] ) ) as $n => $row ) {
				foreach ( (array) $row as $fk => $fv ) {
					if ( 'media' === ( $types[ $fk ] ?? '' ) ) {
						$row[ $fk ] = nh_ed_media( $fv, $media, "$id/$variant/$key/$n/$fk" );
					}
				}
				if ( ! isset( $row['_id'] ) ) {
					throw new RuntimeException( "$id/$variant/$key/$n: a repeater row without _id" );
				}
				$rows[] = $row;
			}
			$out[ $key ] = $rows;
		} elseif ( 'media' === $c['type'] ) {
			$out[ $key ] = nh_ed_media( $value, $media, "$id/$variant/$key" );
		} else {
			$out[ $key ] = $value;
		}
	}
	return [
		'widget'   => $id,
		'variant'  => $variant,
		'id'       => $element,
		'settings' => $out,
		'media'    => array_values( array_unique( $media ) ),
	];
}

/**
 * The tokens of a saved value mapped back to the harness's values.
 *
 * @param mixed $value  Settings (or part of them).
 * @param array $tokens Token => harness value (e.g. '@page:main-home' => 'program-main-home.html').
 */
function nh_ed_untoken( $value, array $tokens ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = nh_ed_untoken( $v, $tokens );
		}
		return $value;
	}
	if ( ! is_string( $value ) || '' === $value || '@' !== $value[0] ) {
		return $value;
	}
	if ( preg_match( '/^@media:([a-z0-9-]+)$/', $value, $m ) ) {
		$aid = nh_test_media_id( $m[1] );
		if ( $aid <= 0 ) {
			throw new RuntimeException( "$value: no such photo in the manifest" );
		}
		return $aid;
	}
	if ( 0 === strpos( $value, '@home:' ) ) {
		return home_url( substr( $value, 6 ) );
	}
	if ( preg_match( '/^(@page:[a-z0-9-]+)(#.*)?$/s', $value, $m ) && isset( $tokens[ $m[1] ] ) ) {
		return $tokens[ $m[1] ] . ( $m[2] ?? '' );
	}
	if ( isset( $tokens[ $value ] ) ) {
		return $tokens[ $value ];
	}
	throw new RuntimeException( "unknown token $value" );
}

/** Elementor's own widgets a footer file may hold besides ours (the harness does not render them). */
const NH_ED_FOOTER_WIDGETS = [ 'image', 'heading', 'text-editor' ];

/**
 * A page document written by build_pages.py: its widgets of ours [ [ widget, element id, settings ], … ], in
 * document order. The footer's inner containers are walked and Elementor's own widgets there are left out.
 */
function nh_ed_doc_widgets( string $dir, string $key ): array {
	$file = rtrim( $dir, '/' ) . '/' . $key . '.json';
	$doc  = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $doc ) || ! isset( $doc['elementor_data'][0]['elements'] ) || 1 !== count( $doc['elementor_data'] ) ) {
		throw new RuntimeException( "$file: expected one top-level container with the widgets" );
	}
	$footer = 'footer' === ( $doc['document_type'] ?? '' );
	$out    = [];
	$walk   = static function ( array $list ) use ( &$walk, &$out, $file, $footer ): void {
		foreach ( $list as $el ) {
			$type = (string) ( $el['elType'] ?? '' );
			$wt   = (string) ( $el['widgetType'] ?? '' );
			if ( $footer && 'container' === $type ) {
				$walk( (array) ( $el['elements'] ?? [] ) );
				continue;
			}
			if ( $footer && 'widget' === $type && in_array( $wt, NH_ED_FOOTER_WIDGETS, true ) ) {
				continue;
			}
			if ( 'widget' !== $type || 0 !== strpos( $wt, 'nh-' ) ) {
				throw new RuntimeException( "$file: element " . ( $el['id'] ?? '?' ) . ' is not an nh- widget' );
			}
			$out[] = [ substr( $wt, 3 ), (string) $el['id'], (array) $el['settings'] ];
		}
	};
	$walk( (array) $doc['elementor_data'][0]['elements'] );
	return [ $doc, $out ];
}

/**
 * First difference of two strings, with some context ('' if equal).
 */
function nh_ed_diff( string $a, string $b ): string {
	if ( $a === $b ) {
		return '';
	}
	$n = 0;
	$l = min( strlen( $a ), strlen( $b ) );
	while ( $n < $l && $a[ $n ] === $b[ $n ] ) {
		++$n;
	}
	$from = max( 0, $n - 80 );
	return "first difference at byte $n\n      fixture: …" . substr( $a, $from, 200 ) . "\n      files:   …" . substr( $b, $from, 200 );
}

/**
 * A full test page (run.php's assembly): the parts are rendered first, then the header, then the footer.
 *
 * @param array  $parts   [ [ widget, element id, settings ], … ].
 * @param array  $header  [ widget, element id, settings ].
 * @param array  $footer  [ widget, element id, settings ].
 * @param string $title   Page title.
 * @param array  $options NH settings of the request.
 * @param array  $get     $_GET of the request.
 * @param string $data    `_elementor_data` of the page (the queried post).
 * @param int    $queried The post shown (pages.php `queried`, default 10).
 */
function nh_ed_assemble( array $parts, array $header, array $footer, string $title, array $options, array $get, string $data, int $queried = 10 ): string {
	nh_test_reset_request( $options, $get );
	$GLOBALS['nh_test']['queried'] = $queried;
	update_post_meta( $queried, '_elementor_data', $data );
	$render = static function ( array $p ): array {
		$widget = nh_ed_widget( $p[0], $p[1], $p[2] );
		return [
			'id'     => $p[0],
			'html'   => $widget->nh_test_render(),
			'widget' => $widget,
		];
	};
	$out = [];
	foreach ( $parts as $p ) {
		$out[] = $render( $p );
	}
	$h = $render( $header );
	$f = $render( $footer );
	return nh_test_page(
		$out,
		[
			'title'  => $title,
			'header' => $h,
			'footer' => $f,
		]
	);
}

/**
 * The render check (see the file header). Returns the failures.
 *
 * @return string[]
 */
function nh_ed_check( array $spec ): array {
	$fail  = [];
	$dir   = (string) $spec['dir'];
	$pages = require NH_TEST_DIR . '/fixtures/pages.php';
	$equal = 0;
	foreach ( (array) $spec['contexts'] as $key => $ctx ) {
		$def    = $pages[ $ctx['page'] ] ?? null;
		$tokens = (array) ( $ctx['tokens'] ?? [] );
		if ( null === $def ) {
			$fail[] = "$key: fixtures/pages.php has no page {$ctx['page']}";
			continue;
		}
		[ $doc, $mine ] = nh_ed_doc_widgets( $dir, (string) $key );
		[ , $hdr ]      = nh_ed_doc_widgets( $dir, (string) $spec['chrome']['header'] );
		[ , $ftr ]      = nh_ed_doc_widgets( $dir, (string) $spec['chrome']['footer'] );
		if ( 1 !== count( $hdr ) || 1 !== count( $ftr ) ) {
			$fail[] = 'the header and footer files must hold one widget each';
			continue;
		}

		// The reference composition: pages.php, the chrome variants of this context.
		$ref = [];
		foreach ( $def['widgets'] as [ $id, $variant ] ) {
			$ref[] = array_merge( [ $id ], nh_ed_variant( $id, $variant ) );
		}
		$chrome = [
			'header' => array_merge( [ $hdr[0][0] ], nh_ed_variant( $hdr[0][0], (string) $ctx['header_ref'] ) ),
			'footer' => array_merge( [ $ftr[0][0] ], nh_ed_variant( $ftr[0][0], (string) $ctx['footer_ref'] ) ),
		];
		if ( $chrome['header'][1] !== $ctx['header_ref'] || $chrome['footer'][1] !== $ctx['footer_ref'] ) {
			$fail[] = "$key: the chrome reference variants {$ctx['header_ref']} / {$ctx['footer_ref']} do not exist";
			continue;
		}
		if ( count( $ref ) !== count( $mine ) ) {
			$fail[] = "$key.json: " . count( $mine ) . ' widgets, fixtures/pages.php ' . $ctx['page'] . ' has ' . count( $ref );
			continue;
		}

		// 1. Each widget alone, on a fresh request with its variant's options and $_GET.
		$pairs = [];
		foreach ( $ref as $i => [ $id, $variant, $v ] ) {
			$pairs[] = [ "$key.json #" . ( $i + 1 ), $id, $variant, $v, $mine[ $i ] ];
		}
		$pairs[] = [ $spec['chrome']['header'] . ".json (on $key)", $chrome['header'][0], $chrome['header'][1], $chrome['header'][2], $hdr[0] ];
		$pairs[] = [ $spec['chrome']['footer'] . ".json (on $key)", $chrome['footer'][0], $chrome['footer'][1], $chrome['footer'][2], $ftr[0] ];
		$is_chrome = count( $pairs ) - 2;
		foreach ( $pairs as $n => [ $where, $id, $variant, $v, $el ] ) {
			[ $el_widget, $el_id, $el_settings ] = $el;
			if ( $el_widget !== $id ) {
				$fail[] = "$where: nh-$el_widget, expected nh-$id";
				continue;
			}
			if ( $n < $is_chrome && nh_test_element_id( $id, $variant ) !== $el_id ) {
				$fail[] = "$where: element id $el_id, expected " . nh_test_element_id( $id, $variant ) . " (md5 of $id/$variant)";
			}
			try {
				nh_test_reset_request( (array) $v['options'], (array) $v['get'] );
				$GLOBALS['nh_test']['queried'] = (int) $v['queried'];
				$a = nh_ed_widget( $id, $el_id, (array) $v['settings'] )->nh_test_render();
				nh_test_reset_request( (array) $v['options'], (array) $v['get'] );
				$GLOBALS['nh_test']['queried'] = (int) $v['queried'];
				$b = nh_ed_widget( $id, $el_id, nh_ed_untoken( $el_settings, $tokens ) )->nh_test_render();
			} catch ( Throwable $e ) {
				$fail[] = "$where ($id/$variant): " . get_class( $e ) . ': ' . $e->getMessage();
				continue;
			}
			$d = nh_ed_diff( $a, $b );
			if ( '' !== $d ) {
				$fail[] = "$where: nh-$id differs from fixture variant $variant — $d";
			} else {
				++$equal;
				echo "ok   $where: nh-$id = fixture $id/$variant (" . strlen( $a ) . " bytes)\n";
			}
		}

		// 2. The whole page: the run.php assembly of pages.php (with this context's chrome) against the files.
		try {
			$options = [];
			$get     = [];
			$data    = [];
			foreach ( array_merge( [ $chrome['header'] ], $ref, [ $chrome['footer'] ] ) as [ $id, $variant, $v ] ) {
				$options = array_merge( $options, (array) $v['options'] );
				$get     = array_merge( $get, (array) $v['get'] );
				$data[]  = [
					'id'         => nh_test_element_id( $id, $variant ),
					'elType'     => 'widget',
					'widgetType' => 'nh-' . $id,
					'settings'   => (array) $v['settings'],
				];
			}
			$ref_parts = [];
			foreach ( $ref as $i => [ $id, $variant, $v ] ) {
				$ref_parts[] = [ $id, nh_test_element_id( $id, $variant ), (array) $v['settings'] ];
			}
			// The chrome element ids belong to the template files (the reference variants differ only in settings).
			$a = nh_ed_assemble(
				$ref_parts,
				[ $chrome['header'][0], $hdr[0][1], (array) $chrome['header'][2]['settings'] ],
				[ $chrome['footer'][0], $ftr[0][1], (array) $chrome['footer'][2]['settings'] ],
				(string) $def['title'],
				$options,
				$get,
				(string) wp_json_encode(
					[
						[
							'elType'   => 'container',
							'elements' => $data,
						],
					]
				),
				(int) ( $def['queried'] ?? 10 )
			);
			$mine_parts = [];
			foreach ( $mine as [ $id, $el_id, $settings ] ) {
				$mine_parts[] = [ $id, $el_id, nh_ed_untoken( $settings, $tokens ) ];
			}
			$b = nh_ed_assemble(
				$mine_parts,
				[ $hdr[0][0], $hdr[0][1], nh_ed_untoken( $hdr[0][2], $tokens ) ],
				[ $ftr[0][0], $ftr[0][1], nh_ed_untoken( $ftr[0][2], $tokens ) ],
				(string) $def['title'],
				$options,
				$get,
				(string) wp_json_encode( nh_ed_untoken( $doc['elementor_data'], $tokens ) ),
				(int) ( $def['queried'] ?? 10 )
			);
			$d = nh_ed_diff( $a, $b );
			if ( '' !== $d ) {
				$fail[] = "page $key: the assembled page differs from the pages.php assembly — $d";
			} else {
				++$equal;
				echo "ok   page $key: assembled from the files = pages.php {$ctx['page']} with header {$ctx['header_ref']}, footer {$ctx['footer_ref']} (" . strlen( $a ) . " bytes)\n";
			}
		} catch ( Throwable $e ) {
			$fail[] = "page $key: " . get_class( $e ) . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')';
		}
	}
	echo "render check: $equal equal, " . count( $fail ) . " different\n";
	return $fail;
}

/* ---------- main ------------------------------------------------------------------------------------- */

$nh_mode = $argv[1] ?? '';
$nh_spec = json_decode( (string) stream_get_contents( STDIN ), true );
if ( ! in_array( $nh_mode, [ 'dump', 'check' ], true ) || ! is_array( $nh_spec ) ) {
	fwrite( STDERR, "usage: php plugin/tests/elementor_data.php dump|check < SPEC.json (see the file header)\n" );
	exit( 2 );
}
try {
	if ( 'dump' === $nh_mode ) {
		$nh_pages = require NH_TEST_DIR . '/fixtures/pages.php';
		$nh_out   = [
			'pages'  => [],
			'chrome' => [],
		];
		foreach ( $nh_pages as $nh_name => $nh_def ) {
			$nh_widgets = [];
			foreach ( $nh_def['widgets'] as [ $nh_id, $nh_variant ] ) {
				$nh_widgets[] = nh_ed_shape( $nh_id, $nh_variant );
			}
			$nh_out['pages'][ $nh_name ] = [
				'title'     => $nh_def['title'],
				'prototype' => $nh_def['prototype'],
				'header'    => $nh_def['header'],
				'footer'    => $nh_def['footer'],
				'widgets'   => $nh_widgets,
			];
		}
		foreach ( (array) ( $nh_spec['chrome'] ?? [] ) as $nh_key => $nh_c ) {
			$nh_out['chrome'][ $nh_key ] = nh_ed_shape( (string) $nh_c['widget'], (string) $nh_c['variant'], (array) ( $nh_c['extra'] ?? [] ) );
		}
		echo json_encode( $nh_out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR ) . "\n";
		exit( 0 );
	}
	$nh_fail = nh_ed_check( $nh_spec );
} catch ( Throwable $e ) {
	fwrite( STDERR, get_class( $e ) . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ")\n" );
	exit( 1 );
}
foreach ( $nh_fail as $nh_f ) {
	echo "FAIL $nh_f\n";
}
exit( $nh_fail ? 1 : 0 );
