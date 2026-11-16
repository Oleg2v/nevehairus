<?php
/**
 * Stub harness bootstrap (SPEC §11.2): PHP 8.3 CLI, no Composer, no WordPress.
 *
 * Every notice, warning and deprecation becomes an ErrorException. Loads the fake WordPress and
 * Elementor, the fake media library, then the plugin, and fires the hooks a real site fires
 * (elementor/loaded, plugins_loaded, init, the Elementor registration hooks, wp_enqueue_scripts).
 * Also holds the helpers run.php, render.php and the core tests share.
 *
 * @package nevehairus-site
 */

error_reporting( E_ALL );
ini_set( 'display_errors', 'stderr' );
set_error_handler(
	static function ( int $no, string $str, string $file = '', int $line = 0 ): bool {
		if ( ! ( error_reporting() & $no ) ) {
			return false;
		}
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

define( 'NH_TEST_DIR', __DIR__ );
define( 'NH_TEST_ROOT', dirname( __DIR__, 2 ) );
define( 'NH_TEST_OUT', __DIR__ . '/out' );
define( 'NH_TEST_PLUGIN', NH_TEST_ROOT . '/plugin/nevehairus-site' );
define( 'ABSPATH', NH_TEST_ROOT . '/' );
define( 'WPINC', 'wp-includes' );
define( 'NH_TESTING', true );
define( 'WP_DEBUG', true );

require __DIR__ . '/stubs/wordpress.php';
require __DIR__ . '/stubs/elementor.php';
require __DIR__ . '/fixtures/media.php';

/** Pages of the fake site: id => [ type, status, url, parent, title ]. */
const NH_TEST_PAGES = [
	10 => [ 'page', 'publish', 'https://nh.test/', 0, 'דף הבית' ],
	11 => [ 'page', 'publish', 'https://nh.test/main-home/', 0, 'מעון ראשי' ],
	12 => [ 'page', 'publish', 'https://nh.test/jobs/', 0, 'דרושים' ],
	13 => [ 'page', 'publish', 'https://nh.test/accessibility/', 0, 'הצהרת נגישות' ],
	14 => [ 'page', 'publish', 'https://nh.test/sitemap/', 0, 'מפת אתר' ],
	15 => [ 'page', 'publish', 'https://nh.test/privacy/', 0, 'מדיניות פרטיות' ],
	16 => [ 'page', 'draft', 'https://nh.test/?page_id=16', 0, 'טיוטה' ],
];

/**
 * The whole fake site, from scratch (hooks included). Used once, before the plugin is loaded.
 */
function nh_test_reset_site(): void {
	$GLOBALS['nh_test'] = nh_test_state_defaults();
	foreach ( NH_TEST_PAGES as $id => [ $type, $status, $url, $parent, $title ] ) {
		$GLOBALS['nh_test']['posts'][ $id ] = [
			'type'   => $type,
			'status' => $status,
			'url'    => $url,
			'parent' => $parent,
			'title'  => $title,
		];
	}
	nh_test_media_load();
}

/**
 * A fresh request on the same site: options, transients, recorders, superglobals, the user, the
 * queried post and the plugin's per-request statics are reset; hooks, handles and media stay.
 *
 * @param array $options NH settings overrides (option nh_settings).
 * @param array $get     $_GET.
 */
function nh_test_reset_request( array $options = [], array $get = [] ): void {
	$keep = [ 'hooks', 'posts', 'media', 'styles', 'scripts', 'actions_done', 'settings', 'pages', 'sections' ];
	$old  = $GLOBALS['nh_test'];
	$GLOBALS['nh_test'] = nh_test_state_defaults();
	foreach ( $keep as $k ) {
		if ( array_key_exists( $k, $old ) ) {
			$GLOBALS['nh_test'][ $k ] = $old[ $k ];
		}
	}
	// Attachment metadata lives in meta: keep the attachments' meta, drop the pages' meta.
	foreach ( (array) ( $old['meta'] ?? [] ) as $id => $meta ) {
		if ( 'attachment' === ( $old['posts'][ $id ]['type'] ?? '' ) ) {
			$GLOBALS['nh_test']['meta'][ $id ] = $meta;
		}
	}
	if ( $options ) {
		$GLOBALS['nh_test']['options']['nh_settings'] = $options;
	}
	$_GET     = $get;
	$_POST    = [];
	$_FILES   = [];
	$_REQUEST = $get;
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REMOTE_ADDR']    = '192.0.2.10';

	foreach ( [
		[ 'NH_Letters', 'script_done', false ],
		[ 'NH_Site', 'opened', false ],
		[ 'NH_Site', 'footed', false ],
		[ 'NH_Site', 'queue', [] ],
		[ 'NH_Site', 'template', '' ],
	] as [ $class, $prop, $value ] ) {
		if ( class_exists( $class ) ) {
			$p = new ReflectionProperty( $class, $prop );
			$p->setAccessible( true );
			$p->setValue( null, $value );
		}
	}
	\Elementor\Plugin::$instance->preview->preview = false;
	\Elementor\Plugin::$instance->editor->edit     = false;
}

nh_test_reset_site();
require NH_TEST_PLUGIN . '/nevehairus-site.php';

// Elementor is loaded before us (plugins load in alphabetical order) and fires elementor/loaded then.
do_action( 'elementor/loaded' );
do_action( 'plugins_loaded' );
do_action( 'init' );
$GLOBALS['nh_test_elementor'] = [
	'elements' => new \Elementor\Elements_Manager(),
	'widgets'  => new \Elementor\Widgets_Manager(),
];
do_action( 'elementor/elements/categories_registered', $GLOBALS['nh_test_elementor']['elements'] );
do_action( 'elementor/widgets/register', $GLOBALS['nh_test_elementor']['widgets'] );
do_action( 'wp_enqueue_scripts' );
add_action(
	'nh_forms_halt',
	static function () {
		throw new NH_Test_Halt( 'nh_forms_halt' );
	}
);

/* ---------- Fixtures ---------------------------------------------------------------------------------- */

/**
 * The fixture of a widget (fixtures/widgets/<id>.php), or the default variant only.
 */
function nh_test_fixture( string $id ): array {
	$file = NH_TEST_DIR . '/fixtures/widgets/' . $id . '.php';
	$data = is_file( $file ) ? require $file : null;
	if ( ! is_array( $data ) || empty( $data['variants'] ) || ! is_array( $data['variants'] ) ) {
		$data = [ 'variants' => [ 'default' => [ 'settings' => [] ] ] ];
	}
	foreach ( $data['variants'] as $name => $v ) {
		$data['variants'][ $name ] = array_merge(
			[
				'settings'    => [],
				'options'     => [],
				'get'         => [],
				'compare'     => null,
				'expect_diff' => [],
			],
			is_array( $v ) ? $v : []
		);
	}
	return $data;
}

/**
 * The element id of a render (SPEC §11.3).
 */
function nh_test_element_id( string $id, string $variant ): string {
	return substr( md5( $id . '/' . $variant ), 0, 7 );
}

/**
 * A widget instance as Elementor builds it from saved data (SPEC §11.3).
 */
function nh_test_widget( string $id, string $variant, array $settings = [] ) {
	$class = NH_Plugin::widgets()[ $id ] ?? null;
	if ( null === $class ) {
		throw new RuntimeException( "widget \"$id\" is not registered" . ( isset( NH_Plugin::skipped()[ $id ] ) ? ': ' . NH_Plugin::skipped()[ $id ] : '' ) );
	}
	return new $class(
		[
			'id'       => nh_test_element_id( $id, $variant ),
			'settings' => $settings,
		],
		[]
	);
}

/**
 * Renders one widget variant on a fresh request: [ 'html' => …, 'widget' => …, 'variant' => … ].
 */
function nh_test_render( string $id, string $variant ): array {
	$fixture = nh_test_fixture( $id );
	if ( ! isset( $fixture['variants'][ $variant ] ) ) {
		throw new RuntimeException( "widget \"$id\" has no variant \"$variant\"" );
	}
	$v = $fixture['variants'][ $variant ];
	nh_test_reset_request( (array) $v['options'], (array) $v['get'] );
	$widget = nh_test_widget( $id, $variant, (array) $v['settings'] );
	return [
		'html'    => $widget->nh_test_render(),
		'widget'  => $widget,
		'variant' => $v,
	];
}

/**
 * Echo output of a callable as a string.
 */
function nh_test_capture( callable $fn ): string {
	ob_start();
	try {
		$fn();
	} finally {
		$out = (string) ob_get_clean();
	}
	return $out;
}

/* ---------- Pages ------------------------------------------------------------------------------------- */

/**
 * Style or script handles in dependency order (deps first, each once).
 *
 * @param string   $kind    'styles'|'scripts'.
 * @param string[] $handles Handles.
 * @return string[]
 */
function nh_test_resolve( string $kind, array $handles ): array {
	$out   = [];
	$visit = static function ( string $h ) use ( &$visit, &$out, $kind ): void {
		if ( in_array( $h, $out, true ) ) {
			return;
		}
		$reg = $GLOBALS['nh_test'][ $kind ][ $h ] ?? null;
		if ( null === $reg ) {
			throw new RuntimeException( "$kind handle \"$h\" is not registered" );
		}
		foreach ( $reg['deps'] as $dep ) {
			$visit( (string) $dep );
		}
		$out[] = $h;
	};
	foreach ( $handles as $h ) {
		$visit( (string) $h );
	}
	return $out;
}

/**
 * A full test page around rendered widgets: `html[lang=he][dir=rtl].js`, the head of the site module,
 * the CSS of every widget (tokens, base, …), body.v3 with the body-open parts, main#main with the widgets
 * inside Elementor-like wrappers, the footer parts, nhData and the scripts.
 *
 * @param array  $parts   [ [ 'id' => widget id, 'html' => …, 'widget' => obj ] ] in page order.
 * @param array  $o       title, plain (bool), header (part, outside main), footer (part, outside main).
 */
function nh_test_page( array $parts, array $o = [] ): string {
	$o       = array_merge(
		[
			'title'  => 'nevehairus-site test page',
			'plain'  => false,
			'header' => null,
			'footer' => null,
		],
		$o
	);
	$styles  = [ 'nh-tokens', 'nh-base' ];
	$scripts = [ 'nh-core' ];
	foreach ( array_filter( array_merge( [ $o['header'] ], $parts, [ $o['footer'] ] ) ) as $p ) {
		$styles  = array_merge( $styles, (array) $p['widget']->get_style_depends() );
		$scripts = array_merge( $scripts, (array) $p['widget']->get_script_depends() );
	}
	$css = '';
	foreach ( nh_test_resolve( 'styles', $styles ) as $h ) {
		$css .= '<link rel="stylesheet" id="' . $h . '-css" href="' . esc_attr( $GLOBALS['nh_test']['styles'][ $h ]['src'] ) . '">' . "\n";
	}
	$js = '<script>window.nhData=' . wp_json_encode( NH_Site::js_data(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';</script>' . "\n";
	foreach ( nh_test_resolve( 'scripts', $scripts ) as $h ) {
		$js .= '<script src="' . esc_attr( $GLOBALS['nh_test']['scripts'][ $h ]['src'] ) . '" id="' . $h . '-js" defer></script>' . "\n";
	}

	$wrap = static function ( array $p ): string {
		$name = $p['widget']->get_name();
		return '<div class="elementor-element elementor-element-' . esc_attr( $p['widget']->get_id() ) . ' elementor-widget elementor-widget-' . $name . '" data-id="' . esc_attr( $p['widget']->get_id() ) . '" data-element_type="widget" data-widget_type="' . $name . '.default">'
			. "\n" . $p['html'] . "\n</div>\n";
	};
	$body = '';
	foreach ( $parts as $p ) {
		$body .= isset( $p['comment'] ) ? '<!-- ' . $p['comment'] . " -->\n" : $wrap( $p );
	}

	$head   = nh_test_capture( [ 'NH_Site', 'head' ] );
	$open   = nh_test_capture( [ 'NH_Site', 'body_open' ] );
	$footer = nh_test_capture( [ 'NH_Site', 'footer' ] );
	$class  = 'js' . ( $o['plain'] ? ' nh-plainmode' : '' );

	return "<!doctype html>\n<html lang=\"he\" dir=\"rtl\" class=\"" . $class . "\">\n<head>\n<meta charset=\"utf-8\">\n"
		. "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<meta name=\"robots\" content=\"noindex, nofollow\">\n"
		. '<title>' . esc_html( $o['title'] ) . "</title>\n" . $head . $css . "</head>\n<body class=\"v3\">\n" . $open
		. ( $o['header'] ? $wrap( $o['header'] ) : '' )
		. "<main id=\"main\" class=\"nh-main\" tabindex=\"-1\">\n<div class=\"elementor elementor-10\" data-elementor-type=\"wp-page\">\n<div class=\"elementor-element e-con-full e-flex e-con e-parent\" data-element_type=\"container\">\n"
		. $body . "</div>\n</div>\n</main>\n"
		. ( $o['footer'] ? $wrap( $o['footer'] ) : '' )
		. $footer . $js . "</body>\n</html>\n";
}

/**
 * Writes a file under out/ (creates the folder).
 */
function nh_test_write( string $name, string $content ): string {
	if ( ! is_dir( NH_TEST_OUT ) ) {
		mkdir( NH_TEST_OUT, 0775, true );
	}
	$path = NH_TEST_OUT . '/' . $name;
	file_put_contents( $path, $content );
	return $path;
}

/* ---------- Assertions (core/*.test.php) ----------------------------------------------------------- */

/**
 * Assertion helpers for core/*.test.php.
 */
function nh_assert( bool $ok, string $message = 'assertion failed' ): void {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
}
function nh_assert_same( $expected, $actual, string $message = '' ): void {
	if ( $expected !== $actual ) {
		$e = is_string( $expected ) ? $expected : var_export( $expected, true );
		$a = is_string( $actual ) ? $actual : var_export( $actual, true );
		throw new RuntimeException( ( '' !== $message ? $message . ': ' : '' ) . "expected\n    " . $e . "\n  got\n    " . $a );
	}
}
function nh_assert_contains( string $needle, string $haystack, string $message = '' ): void {
	if ( false === strpos( $haystack, $needle ) ) {
		throw new RuntimeException( ( '' !== $message ? $message . ': ' : '' ) . "missing\n    " . $needle . "\n  in\n    " . substr( $haystack, 0, 2000 ) );
	}
}
function nh_assert_not_contains( string $needle, string $haystack, string $message = '' ): void {
	if ( false !== strpos( $haystack, $needle ) ) {
		throw new RuntimeException( ( '' !== $message ? $message . ': ' : '' ) . "unexpected\n    " . $needle );
	}
}
