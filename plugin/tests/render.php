<?php
/**
 * One render of the stub harness (SPEC §11.2):
 *
 *     php plugin/tests/render.php <id> [variant] [--page] [--plain] [--out]
 *
 * Prints the widget's HTML for a fixture variant (default: `default`). --page prints the full test page
 * (the site's CSS/JS and parts around the widget), --plain the same page in plain-language mode
 * (html.nh-plainmode), --out writes it to out/<id>--<variant>[-plain].html instead of printing.
 *
 * @package nevehairus-site
 */

require __DIR__ . '/bootstrap.php';

$args    = array_values( array_filter( array_slice( $argv, 1 ), static fn( $a ) => 0 !== strpos( $a, '--' ) ) );
$flags   = array_filter( array_slice( $argv, 1 ), static fn( $a ) => 0 === strpos( $a, '--' ) );
$id      = $args[0] ?? '';
$variant = $args[1] ?? 'default';
$plain   = in_array( '--plain', $flags, true );
$page    = $plain || in_array( '--page', $flags, true );

if ( '' === $id ) {
	fwrite( STDERR, "usage: php plugin/tests/render.php <id> [variant] [--page] [--plain] [--out]\nwidgets: " . implode( ', ', array_keys( NH_Plugin::widgets() ) ) . "\n" );
	exit( 2 );
}
try {
	$r    = nh_test_render( $id, $variant );
	$html = $page
		? nh_test_page(
			[
				[
					'id'     => $id,
					'html'   => $r['html'],
					'widget' => $r['widget'],
				],
			],
			[
				'title' => "nh-$id — $variant",
				'plain' => $plain,
			]
		)
		: $r['html'];
} catch ( Throwable $e ) {
	fwrite( STDERR, get_class( $e ) . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ")\n" );
	exit( 1 );
}
if ( in_array( '--out', $flags, true ) ) {
	echo nh_test_write( $id . '--' . $variant . ( $plain ? '-plain' : '' ) . '.html', $html ) . "\n";
} else {
	echo $html . "\n";
}
