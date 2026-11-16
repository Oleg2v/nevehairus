<?php
/**
 * The stub harness (SPEC §11.2):
 *
 *     php plugin/tests/run.php [--widget=<id>] [--no-pages] [--no-core] [--core-only] [--suite=<name>] [--verbose]
 *
 * For every discovered widget and every variant of its fixture (a widget without a fixture gets the
 * `default` variant): build the settings, render, assert (no PHP error, non-empty unless the variant says
 * `'empty' => true`, well-formed, unique ids, resolvable references, alt on every img, decorative svgs,
 * inline style only on a letter's room and only custom properties there, no forbidden pattern, spread root
 * attributes (v3.2: no data-header / data-running), no empty full/plain twin), write
 * out/<id>--<variant>.html
 * (+ .json for compare.py). Then the two assembled prototype pages and the core unit tests
 * (core/*.test.php). Exit 1 on any failure.
 *
 * @package nevehairus-site
 */

require __DIR__ . '/bootstrap.php';

$opts    = getopt( '', [ 'widget:', 'no-pages', 'no-core', 'core-only', 'suite:', 'verbose' ] );
$only    = isset( $opts['widget'] ) ? (string) $opts['widget'] : null;
$verbose = isset( $opts['verbose'] );
$core_only = isset( $opts['core-only'] ) || isset( $opts['suite'] );

/** Ids that may be referenced from outside a widget (the theme's main, the masthead's menu, the site module's
 * cookie panel; SPEC §2.5). v3.2: no display panel, no lightbox. */
const NH_TEST_GLOBAL_IDS = [ 'main', 'content', 'nh-menu', 'nh-menu-title', 'nh-cookies', 'nh-cookies-title', 'nh-c-stats', 'nh-c-media' ];

$failures = [];
$warnings = [];
$fail     = static function ( string $where, string $what ) use ( &$failures ): void {
	$failures[] = "$where: $what";
};
$warn     = static function ( string $where, string $what ) use ( &$warnings ): void {
	$warnings[] = "$where: $what";
};

/**
 * Parses an HTML fragment; returns [ DOMDocument, DOMElement root, errors ] (unknown HTML5/SVG tags,
 * libxml code 801, are fine).
 */
function nh_test_dom( string $html ): array {
	$doc  = new DOMDocument( '1.0', 'UTF-8' );
	$prev = libxml_use_internal_errors( true );
	libxml_clear_errors();
	$doc->loadHTML( '<?xml encoding="UTF-8"><html><body><div id="nh-test-root">' . $html . '</div></body></html>', LIBXML_HTML_NODEFDTD | LIBXML_NONET );
	$errors = [];
	foreach ( libxml_get_errors() as $e ) {
		if ( 801 !== $e->code ) {
			$errors[] = trim( $e->message ) . ' (code ' . $e->code . ')';
		}
	}
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$root = ( new DOMXPath( $doc ) )->query( '//div[@id="nh-test-root"]' )->item( 0 );
	return [ $doc, $root, $errors ];
}

/**
 * The forbidden patterns of SPEC §11.1 found in a text ([] = none).
 *
 * @return string[]
 */
function nh_test_forbidden( string $text ): array {
	$text  = str_replace( NH_TEST_MEDIA_BASE, '', $text ); // the fixture photos live in the prototype on purpose
	$found = [];
	foreach ( [
		'~assets/img/~'              => 'assets/img/',
		'~gsap~i'                    => 'gsap',
		'~fonts\.googleapis~'        => 'fonts.googleapis',
		'~fonts\.gstatic~'           => 'fonts.gstatic',
		'~<script src="http~'        => '<script src="http',
		'~cdn\.~'                    => 'cdn.',
	] as $rx => $label ) {
		if ( preg_match( $rx, $text ) ) {
			$found[] = $label;
		}
	}
	if ( preg_match_all( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}/', $text, $m ) ) {
		foreach ( $m[0] as $email ) {
			if ( ! preg_match( '/@(?:[A-Za-z0-9-]+\.)*(?:example\.com|example\.test)$|\.invalid$/i', $email ) ) {
				$found[] = 'e-mail address ' . $email;
			}
		}
	}
	return $found;
}

/**
 * The generic assertions of one render (SPEC §11.2). Returns [ failures, warnings ].
 *
 * @param string $id     Widget id.
 * @param array  $render nh_test_render() result.
 */
function nh_test_assert( string $id, array $render ): array {
	$f    = [];
	$w    = [];
	$html = $render['html'];
	if ( ! empty( $render['variant']['empty'] ) ) {
		// a variant whose settings leave nothing to show (e.g. the display widget with both switches off)
		return [ '' === trim( $html ) ? [] : [ 'expected an empty render' ], [] ];
	}
	if ( '' === trim( $html ) ) {
		return [ [ 'empty output' ], [] ];
	}
	[ $doc, $root, $errors ] = nh_test_dom( $html );
	foreach ( $errors as $e ) {
		$f[] = 'HTML: ' . $e;
	}
	if ( null === $root ) {
		return [ $f ?: [ 'not parseable' ], $w ];
	}
	$xp  = new DOMXPath( $doc );
	$ids = [];
	foreach ( $xp->query( './/*[@id]', $root ) as $el ) {
		$ids[ $el->getAttribute( 'id' ) ] = ( $ids[ $el->getAttribute( 'id' ) ] ?? 0 ) + 1;
	}
	foreach ( $ids as $i => $n ) {
		if ( $n > 1 ) {
			$f[] = "id \"$i\" used $n times";
		}
	}
	foreach ( [ 'for', 'aria-labelledby', 'aria-describedby', 'aria-controls' ] as $attr ) {
		foreach ( $xp->query( './/*[@' . $attr . ']', $root ) as $el ) {
			foreach ( preg_split( '/\s+/', trim( $el->getAttribute( $attr ) ) ) as $ref ) {
				if ( '' === $ref || ( ! isset( $ids[ $ref ] ) && ! in_array( $ref, NH_TEST_GLOBAL_IDS, true ) ) ) {
					$f[] = "$attr=\"$ref\" on <{$el->nodeName}> does not resolve";
				}
			}
		}
	}
	foreach ( $xp->query( './/img', $root ) as $el ) {
		if ( ! $el->hasAttribute( 'alt' ) ) {
			$f[] = '<img> without alt (' . $el->getAttribute( 'src' ) . ')';
		}
	}
	foreach ( $xp->query( './/svg[not(@role)]', $root ) as $el ) {
		$hidden = 'true' === $el->getAttribute( 'aria-hidden' ) || $xp->query( 'ancestor::*[@aria-hidden="true"]', $el )->length > 0;
		if ( ! $hidden || 'false' !== $el->getAttribute( 'focusable' ) ) {
			$f[] = '<svg class="' . $el->getAttribute( 'class' ) . '"> needs aria-hidden="true" (or an aria-hidden parent) and focusable="false"';
		}
	}
	foreach ( $xp->query( './/*[@style]', $root ) as $el ) {
		// v3.2 (research/07 §13): the only inline style is the geometry of a letter's room (the colour
		// swatches of v3.1 are gone)
		if ( ! in_array( 'v3-room', preg_split( '/\s+/', trim( $el->getAttribute( 'class' ) ) ), true ) ) {
			$f[] = "inline style on <{$el->nodeName} class=\"" . $el->getAttribute( 'class' ) . '"> (only on a letter\'s room, .v3-room)';
			continue;
		}
		foreach ( explode( ';', $el->getAttribute( 'style' ) ) as $decl ) {
			$prop = trim( explode( ':', $decl, 2 )[0] );
			if ( '' !== $prop && 0 !== strpos( $prop, '--' ) ) {
				$f[] = "inline style \"$prop\" on <{$el->nodeName}> (only CSS custom properties)";
			}
		}
	}
	foreach ( nh_test_forbidden( $html ) as $what ) {
		$f[] = 'forbidden: ' . $what;
	}
	foreach ( $xp->query( './/*[contains(concat(" ", normalize-space(@class), " "), " v3-full ") or contains(concat(" ", normalize-space(@class), " "), " v3-plain ")]', $root ) as $el ) {
		if ( '' === trim( $el->textContent ) && 0 === $xp->query( './/img|.//svg|.//input|.//select|.//textarea|.//iframe', $el )->length ) {
			$f[] = '<' . $el->nodeName . ' class="' . $el->getAttribute( 'class' ) . '"> is empty';
		}
	}
	foreach ( $xp->query( './/a', $root ) as $el ) {
		if ( '' === trim( $el->getAttribute( 'href' ) ) ) {
			$w[] = 'a link without href: "' . trim( $el->textContent ) . '"';
		}
	}

	// The controls section "מקטע" is the last one (SPEC §4.0).
	$widget   = $render['widget'];
	$sections = $widget->nh_test_sections();
	if ( isset( $sections['nh_section'] ) && 'nh_section' !== array_key_last( $sections ) ) {
		$f[] = 'the controls section "מקטע" (NH_Controls::section) must be the last one';
	}
	// Spread widgets (the root is a <section>): the labelled heading; v3.2 (research/07 §12.3): no running-head
	// or header-theme attributes any more.
	$first = null;
	foreach ( $root->childNodes as $n ) {
		if ( XML_ELEMENT_NODE === $n->nodeType ) {
			$first = $n;
			break;
		}
	}
	if ( null === $first ) {
		$f[] = 'no root element';
	} elseif ( 'section' === $first->nodeName ) {
		foreach ( [ 'data-header', 'data-running', 'data-running-plain' ] as $a ) {
			if ( $first->hasAttribute( $a ) ) {
				$f[] = "root <section> with $a (removed in v3.2)";
			}
		}
		$lb = $first->getAttribute( 'aria-labelledby' );
		if ( '' === $lb ) {
			$f[] = 'root <section> without aria-labelledby';
		} else {
			$h = $xp->query( './/*[@id="' . $lb . '"]', $first )->item( 0 );
			if ( null === $h || ! preg_match( '/^h[1-6]$/', $h->nodeName ) ) {
				$f[] = "aria-labelledby=\"$lb\" does not point to a heading inside the spread";
			}
		}
	}
	return [ $f, $w ];
}

/* ---------- 1. Widgets ------------------------------------------------------------------------------- */

$registered = NH_Plugin::widgets();
foreach ( NH_Plugin::skipped() as $id => $reason ) {
	if ( null === $only || $only === $id ) {
		$fail( "widget $id", "not registered: $reason" );
	}
}
$count = 0;
foreach ( NH_Plugin::widget_ids_on_disk() as $id ) {
	if ( $core_only || ( null !== $only && $only !== $id ) || ! isset( $registered[ $id ] ) ) {
		continue;
	}
	$fixture = nh_test_fixture( $id );
	foreach ( $fixture['variants'] as $variant => $v ) {
		$where = "$id/$variant";
		try {
			$render = nh_test_render( $id, $variant );
			[ $f, $w ] = nh_test_assert( $id, $render );
			if ( '' === trim( $render['html'] ) ) {
				// nothing to put on a page (an `empty` variant): the empty render is the result
				nh_test_write( "$id--$variant.html", '' );
				++$count;
				if ( $verbose ) {
					echo ( $f ? 'FAIL ' : 'ok   ' ) . $where . "\n";
				}
				foreach ( $f as $x ) {
					$fail( $where, $x );
				}
				continue;
			}
			$page = nh_test_page(
				[
					[
						'id'     => $id,
						'html'   => $render['html'],
						'widget' => $render['widget'],
					],
				],
				[ 'title' => "nh-$id — $variant" ]
			);
		} catch ( Throwable $e ) {
			$fail( $where, get_class( $e ) . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
			continue;
		}
		foreach ( $f as $x ) {
			$fail( $where, $x );
		}
		foreach ( $w as $x ) {
			$warn( $where, $x );
		}
		nh_test_write( "$id--$variant.html", $page );
		nh_test_write(
			"$id--$variant.json",
			(string) json_encode(
				[
					'widget'      => $id,
					'variant'     => $variant,
					'compare'     => $v['compare'],
					'expect_diff' => array_values( (array) $v['expect_diff'] ),
				],
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
			)
		);
		++$count;
		if ( $verbose ) {
			echo ( $f ? 'FAIL ' : 'ok   ' ) . $where . "\n";
		}
	}
}
if ( ! $core_only ) {
	echo "widgets: $count render(s)" . ( $only ? " of $only" : '' ) . "\n";
}

/* ---------- 2. Assembled pages ---------------------------------------------------------------------- */

if ( null === $only && ! $core_only && ! isset( $opts['no-pages'] ) ) {
	$pages = require NH_TEST_DIR . '/fixtures/pages.php';
	foreach ( $pages as $name => $def ) {
		try {
			$pick = static function ( string $id, string $variant ) use ( $registered ): ?array {
				if ( ! isset( $registered[ $id ] ) ) {
					return null;
				}
				$fx = nh_test_fixture( $id );
				foreach ( [ $variant, 'prototype', 'default' ] as $try ) {
					if ( isset( $fx['variants'][ $try ] ) ) {
						return [ $try, $fx['variants'][ $try ] ];
					}
				}
				return null;
			};
			$list    = array_merge( [ $def['header'] ], $def['widgets'], [ $def['footer'] ] );
			$options = [];
			$get     = [];
			$chosen  = [];
			foreach ( $list as [ $id, $variant ] ) {
				$c              = $pick( $id, $variant );
				$chosen[]       = [ $id, $c ];
				$options        = array_merge( $options, (array) ( $c[1]['options'] ?? [] ) );
				$get            = array_merge( $get, (array) ( $c[1]['get'] ?? [] ) );
			}
			nh_test_reset_request( $options, $get );
			$GLOBALS['nh_test']['queried'] = (int) ( $def['queried'] ?? 10 );
			$elements = [];
			foreach ( $chosen as [ $id, $c ] ) {
				if ( null !== $c ) {
					$elements[] = [
						'id'         => nh_test_element_id( $id, $c[0] ),
						'elType'     => 'widget',
						'widgetType' => 'nh-' . $id,
						'settings'   => (array) $c[1]['settings'],
					];
				}
			}
			update_post_meta( (int) get_queried_object_id(), '_elementor_data', wp_json_encode( [ [ 'elType' => 'container', 'elements' => $elements ] ] ) );

			$render = static function ( string $id, ?array $c ): array {
				if ( null === $c ) {
					return [ 'comment' => "widget $id: not built yet" ];
				}
				$widget = nh_test_widget( $id, $c[0], (array) $c[1]['settings'] );
				return [
					'id'     => $id,
					'html'   => $widget->nh_test_render(),
					'widget' => $widget,
				];
			};
			$parts = [];
			foreach ( array_slice( $chosen, 1, -1 ) as [ $id, $c ] ) {
				$parts[] = $render( $id, $c );
			}
			$header = $render( ...$chosen[0] );
			$footer = $render( ...$chosen[ count( $chosen ) - 1 ] );
			$html   = nh_test_page(
				$parts,
				[
					'title'  => $def['title'],
					'header' => isset( $header['widget'] ) ? $header : null,
					'footer' => isset( $footer['widget'] ) ? $footer : null,
				]
			);
			nh_test_write( $name . '.html', $html );
			$built = count( array_filter( $chosen, static fn( $x ) => null !== $x[1] ) );
			echo "page: out/$name.html ($built of " . count( $chosen ) . " widgets)\n";
		} catch ( Throwable $e ) {
			$fail( "page $name", get_class( $e ) . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
		}
	}
}

/* ---------- 3. Core unit tests ------------------------------------------------------------------------ */

if ( null === $only && ! isset( $opts['no-core'] ) ) {
	$passed = 0;
	foreach ( isset( $opts['suite'] ) ? [ (string) $opts['suite'] ] : [ 'letters', 'text', 'picture', 'forms', 'site', 'settings', 'scope' ] as $suite ) {
		$file = NH_TEST_DIR . '/core/' . $suite . '.test.php';
		if ( ! is_file( $file ) ) {
			$fail( "core/$suite", 'test file missing' );
			continue;
		}
		$tests = require $file;
		foreach ( (array) $tests as $name => $test ) {
			nh_test_reset_request();
			try {
				$test();
				++$passed;
				if ( $verbose ) {
					echo "ok   core/$suite: $name\n";
				}
			} catch ( Throwable $e ) {
				$fail( "core/$suite: $name", $e->getMessage() . ( $e instanceof RuntimeException ? '' : ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' ) );
			}
		}
	}
	echo "core: $passed test(s) passed\n";
}

/* ---------- Summary ------------------------------------------------------------------------------------ */

foreach ( $warnings as $w ) {
	echo "warning  $w\n";
}
foreach ( $failures as $f ) {
	echo "FAIL     $f\n";
}
echo $failures ? count( $failures ) . " failure(s)\n" : "all assertions passed\n";
exit( $failures ? 1 : 0 );
