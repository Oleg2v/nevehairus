<?php
/**
 * Core tests 7 — the plugin is a guest (research/07 §12): NH_Site::uses_widgets() on the request (the
 * requested post, the Theme Builder header and footer through Elementor Pro's conditions manager, Canvas,
 * the preview, memoised, never before wp_enqueue_scripts); a page without our widgets gets none of our
 * CSS, JS, head or body parts, a page with them — or with our header or footer — gets them; every widget
 * carries the core handles; base.css has no global rule; activation and deactivation clear Elementor's
 * caches; uninstall removes both options; the child theme takes over what applies to the whole site.
 *
 * Elementor Pro is absent for the first tests and loaded (stubs/elementor-pro.php) from the test
 * "with Elementor Pro" on: keep that order.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elementor data of a page made of these widget types (in one container).
 *
 * @param string[] $types Widget types, e.g. 'heading', 'nh-cover'.
 */
function nh_test_scope_data( array $types ): string {
	$elements = [];
	foreach ( $types as $n => $type ) {
		$elements[] = [
			'id'         => 'w' . $n,
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => [],
		];
	}
	return (string) wp_json_encode( [ [ 'elType' => 'container', 'elements' => $elements ] ] );
}

/**
 * Everything the site module and the assets put on one request: enqueued handles, head, body class, body
 * open, footer. Runs wp_enqueue_scripts first, as WordPress does.
 */
function nh_test_scope_request(): array {
	do_action( 'wp_enqueue_scripts' );
	$enq = $GLOBALS['nh_test']['enqueued'];
	return [
		'styles'  => array_keys( $enq['style'] ),
		'scripts' => array_keys( $enq['script'] ),
		'head'    => nh_test_capture( [ 'NH_Site', 'head' ] ),
		'class'   => NH_Site::body_class( [] ),
		'open'    => nh_test_capture( [ 'NH_Site', 'body_open' ] ),
		'footer'  => nh_test_capture( [ 'NH_Site', 'footer' ] ),
	];
}

/**
 * Asserts that a request got none of our parts (research/07 §17.8: the Canvas page of standard widgets).
 */
function nh_test_scope_none( array $r, string $what ): void {
	nh_assert_same( [], array_values( array_filter( $r['styles'], static fn( $h ) => 0 === strpos( $h, 'nh-' ) ) ), "$what: no nh-* style" );
	nh_assert_same( [], array_values( array_filter( $r['scripts'], static fn( $h ) => 0 === strpos( $h, 'nh-' ) ) ), "$what: no nh-* script" );
	nh_assert_same( '', $r['head'], "$what: no head part (preloads, #nh-prepaint)" );
	nh_assert( ! in_array( 'v3', $r['class'], true ), "$what: no body.v3" );
	nh_assert_same( '', $r['open'], "$what: no sprite" );
	nh_assert_same( '', $r['footer'], "$what: no #nh-cookies, no live region" );
}

/**
 * Asserts that a request got every part.
 */
function nh_test_scope_all( array $r, string $what ): void {
	foreach ( [ 'nh-tokens', 'nh-base' ] as $h ) {
		nh_assert( in_array( $h, $r['styles'], true ), "$what: $h enqueued (in <head>)" );
	}
	nh_assert( in_array( 'nh-core', $r['scripts'], true ), "$what: nh-core enqueued" );
	nh_assert_contains( '<script id="nh-prepaint"', $r['head'], $what );
	nh_assert_contains( 'frank-ruhl-libre-core-var.woff2" as="font"', $r['head'], $what );
	nh_assert( in_array( 'v3', $r['class'], true ), "$what: body.v3" );
	nh_assert_contains( '<symbol id="i-menu"', $r['open'], "$what: the sprite" );
	nh_assert_contains( '<section class="nh-cookies nh-on-dark" id="nh-cookies"', $r['footer'], "$what: the cookie panel" );
	nh_assert_contains( 'data-announce', $r['footer'], "$what: the live region" );
}

return [
	'uses_widgets(): admin false; the preview true; before wp_enqueue_scripts false and not remembered' => static function () {
		$memo = new ReflectionProperty( 'NH_Site', 'uses' );
		$memo->setAccessible( true );
		update_post_meta( 10, '_elementor_data', nh_test_scope_data( [ 'nh-cover' ] ) );
		$GLOBALS['nh_test']['is_admin'] = true;
		nh_assert( ! NH_Site::uses_widgets(), 'admin' );
		$GLOBALS['nh_test']['is_admin'] = false;
		$done = $GLOBALS['nh_test']['actions_done']['wp_enqueue_scripts'] ?? 0;
		$GLOBALS['nh_test']['actions_done']['wp_enqueue_scripts'] = 0;
		try {
			nh_assert( ! NH_Site::uses_widgets(), 'too early: the main query is not ready' );
			nh_assert_same( null, $memo->getValue(), 'an early answer is not remembered' );
		} finally {
			$GLOBALS['nh_test']['actions_done']['wp_enqueue_scripts'] = $done;
		}
		nh_assert( NH_Site::uses_widgets(), 'in time: the post holds nh-cover' );
		nh_assert_same( true, $memo->getValue() );
		nh_test_reset_request();
		\Elementor\Plugin::$instance->preview->preview = true;
		nh_assert( NH_Site::uses_widgets(), 'the Elementor preview: any widget may be dropped in' );
		\Elementor\Plugin::$instance->preview->preview = false;
	},

	'uses_widgets(): the requested post — raw _elementor_data, array meta, spaces, malformed; remembered per request' => static function () {
		nh_assert( ! NH_Site::uses_widgets(), 'no Elementor data' );
		$cases = [
			[ nh_test_scope_data( [ 'heading', 'text-editor' ] ), false, 'standard widgets only' ],
			[ nh_test_scope_data( [ 'heading', 'nh-related' ] ), true, 'one of ours, nested' ],
			[ '[{"elType":"widget","widgetType" : "nh-door"}]', true, 'JSON with spaces' ],
			[ '[{"elType":"widget","widgetType":"nhx-door"}]', false, 'a look-alike prefix' ],
			[ '[{"elType":"widget","settings":{"title":"widgetType nh-"}}]', false, 'the words in a text' ],
			[ [ [ 'elType' => 'widget', 'widgetType' => 'nh-cover' ] ], true, 'meta stored as an array' ],
			[ '{not json "widgetType":"nh-cover"', true, 'malformed JSON still names our widget (Elementor would not render it; harmless)' ],
			[ '', false, 'empty' ],
		];
		foreach ( $cases as [ $data, $want, $what ] ) {
			nh_test_reset_request();
			update_post_meta( 10, '_elementor_data', $data );
			nh_assert_same( $want, NH_Site::uses_widgets(), $what );
		}
		nh_test_reset_request();
		update_post_meta( 10, '_elementor_data', nh_test_scope_data( [ 'nh-cover' ] ) );
		nh_assert( NH_Site::uses_widgets() );
		update_post_meta( 10, '_elementor_data', nh_test_scope_data( [ 'heading' ] ) );
		nh_assert( NH_Site::uses_widgets(), 'remembered for the request: a later change of the meta is not seen' );
		nh_test_reset_request();
		update_post_meta( 10, '_elementor_data', nh_test_scope_data( [ 'nh-cover' ] ) );
		$GLOBALS['nh_test']['is_singular'] = false;
		nh_assert( ! NH_Site::uses_widgets(), 'not singular (archive, search, 404): the queried id is not a post' );
	},

	'without Elementor Pro: no Theme Builder documents, nothing called, nothing fatal' => static function () {
		nh_assert( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ), 'this test runs before the Pro stub is loaded' );
		nh_assert_same( [], NH_Site::chrome_documents() );
		nh_assert( ! NH_Site::uses_widgets() );
	},

	'scoping: a page without our widgets gets none of our CSS, JS, head or body parts' => static function () {
		update_post_meta( 10, '_elementor_data', nh_test_scope_data( [ 'heading', 'text-editor', 'image' ] ) );
		$r = nh_test_scope_request();
		nh_test_scope_none( $r, 'standard widgets only' );
		nh_assert( wp_style_is( 'nh-base', 'registered' ) && wp_script_is( 'nh-core', 'registered' ), 'registered, for Elementor to enqueue with a widget' );
		nh_assert_contains( 'window.nhData=', $GLOBALS['nh_test']['inline']['nh-core']['before'][0], 'nhData rides on nh-core: printed only with it' );
		nh_test_reset_request();
		$GLOBALS['nh_test']['is_singular'] = false;
		nh_test_scope_none( nh_test_scope_request(), 'an archive without Pro' );
	},

	'scoping: a page with our widgets gets every part' => static function () {
		update_post_meta( 10, '_elementor_data', nh_test_scope_data( [ 'heading', 'nh-plainbox' ] ) );
		nh_test_scope_all( nh_test_scope_request(), 'a page with nh-plainbox' );
	},

	'with Elementor Pro: the header and footer shown on the request are read through the conditions manager' => static function () {
		require_once NH_TEST_DIR . '/stubs/elementor-pro.php';
		$posts = [ 20 => [ 'header', 'nh-masthead' ], 21 => [ 'footer', 'text-editor' ], 22 => [ 'footer', 'nh-display' ] ];
		foreach ( $posts as $id => [ $type, $widget ] ) {
			$GLOBALS['nh_test']['posts'][ $id ] = [
				'type'   => 'elementor_library',
				'status' => 'publish',
				'url'    => "https://nh.test/?elementor_library=$id",
				'parent' => 0,
				'title'  => $type,
			];
		}
		$set = static function () use ( $posts ): void {
			foreach ( $posts as $id => [ , $widget ] ) {
				update_post_meta( $id, '_elementor_data', nh_test_scope_data( [ 'heading', $widget ] ) );
			}
		};
		$set();
		$GLOBALS['nh_test']['locations'] = [
			'header' => [ 20 ],
			'footer' => [ 21 ],
		];
		nh_assert_same( [ 20, 21 ], NH_Site::chrome_documents() );
		nh_assert_same( 2, $GLOBALS['nh_test']['pro_calls'], 'one call per location' );
		$GLOBALS['nh_test']['pro_calls'] = 0;
		nh_assert( NH_Site::uses_widgets(), 'our masthead in the header' );
		NH_Site::uses_widgets();
		NH_Site::body_class( [] );
		nh_assert_same( 2, $GLOBALS['nh_test']['pro_calls'], 'remembered for the request: Pro is asked once' );

		nh_test_reset_request();
		$set();
		$GLOBALS['nh_test']['locations'] = [ 'footer' => [ 22 ] ];
		nh_assert( NH_Site::uses_widgets(), 'our display switches in the footer, no header' );

		nh_test_reset_request();
		$set();
		$GLOBALS['nh_test']['locations'] = [ 'footer' => [ 21 ] ];
		nh_assert( ! NH_Site::uses_widgets(), 'a footer of standard widgets only' );

		nh_test_reset_request();
		$set();
		$GLOBALS['nh_test']['locations']  = [ 'header' => [ 20 ] ];
		$GLOBALS['nh_test']['pro_throws'] = true;
		nh_assert_same( [], NH_Site::chrome_documents(), 'Pro throwing: no documents, nothing fatal' );
		nh_assert( ! NH_Site::uses_widgets() );
	},

	'scoping: our header or footer brings every part to pages without our widgets — not on Canvas' => static function () {
		$GLOBALS['nh_test']['posts'][20] = [ 'type' => 'elementor_library', 'status' => 'publish', 'url' => 'https://nh.test/?elementor_library=20', 'parent' => 0, 'title' => 'header' ];
		$prep = static function ( array $page ): void {
			update_post_meta( 20, '_elementor_data', nh_test_scope_data( [ 'nh-masthead' ] ) );
			update_post_meta( 15, '_elementor_data', nh_test_scope_data( $page ) );
			$GLOBALS['nh_test']['queried']   = 15;                     // מדיניות פרטיות
			$GLOBALS['nh_test']['locations'] = [ 'header' => [ 20 ] ];
		};
		$prep( [ 'heading' ] );
		nh_test_scope_all( nh_test_scope_request(), 'the privacy page under our masthead' );

		nh_test_reset_request();
		$prep( [] );
		$GLOBALS['nh_test']['is_singular'] = false;
		$GLOBALS['nh_test']['queried']     = 0;
		nh_test_scope_all( nh_test_scope_request(), 'a 404 / search / archive under our masthead' );

		nh_test_reset_request();
		$prep( [ 'heading', 'text-editor' ] );
		update_post_meta( 15, '_wp_page_template', 'elementor_canvas' );
		nh_test_scope_none( nh_test_scope_request(), 'a Canvas page of standard widgets (§17.8): no header there' );

		nh_test_reset_request();
		$prep( [ 'heading', 'nh-cover' ] );
		update_post_meta( 15, '_wp_page_template', 'elementor_canvas' );
		nh_test_scope_all( nh_test_scope_request(), 'a Canvas page with our widgets' );
	},

	'every widget carries the core: nh-base first in its styles, nh-core first in its scripts' => static function () {
		$widgets = NH_Plugin::widgets();
		nh_assert( count( $widgets ) > 0 );
		foreach ( $widgets as $id => $class ) {
			$w = nh_test_widget( $id, 'deps' );
			$s = $w->get_style_depends();
			$j = $w->get_script_depends();
			nh_assert_same( 'nh-base', $s[0] ?? null, "$id styles" );
			nh_assert_same( 'nh-core', $j[0] ?? null, "$id scripts" );
			nh_assert_same( count( $s ), count( array_unique( $s ) ), "$id: no handle twice" );
			foreach ( $s as $h ) {
				nh_assert( isset( $GLOBALS['nh_test']['styles'][ $h ] ), "$id: style $h is registered" );
			}
			foreach ( $j as $h ) {
				nh_assert( isset( $GLOBALS['nh_test']['scripts'][ $h ] ), "$id: script $h is registered" );
			}
		}
	},

	'base.css: nothing global — every rule reaches only our widgets, the cookie panel or our classes' => static function () {
		$css = (string) file_get_contents( NH_TEST_PLUGIN . '/assets/css/base.css' );
		$css = (string) preg_replace( '~/\*.*?\*/~s', '', $css );
		nh_assert( ! preg_match( '/@keyframes|animation-timeline|view-transition|@property|scroll-behavior\s*:\s*smooth|position\s*:\s*sticky/i', $css ), 'no motion that v3.2 forbids (§11.3 item 5, §17.1)' );
		$ours  = '~\[class\*="elementor-widget-nh-"\]|\.(?:nh|v3)-?[a-z0-9_-]*~';
		$count = 0;
		// innermost "selector { declarations }" pairs; @media wrappers are not selectors
		preg_match_all( '~([^{}]+)\{([^{}]*)\}~', $css, $m, PREG_SET_ORDER );
		foreach ( $m as [ , $selectors, $decls ] ) {
			$selectors = trim( (string) preg_replace( '~^.*@media[^{]*$~s', '', trim( $selectors ) ) );
			if ( '' === $selectors || 0 === strpos( $selectors, '@' ) ) {
				continue;
			}
			// split on top-level commas
			$parts = [];
			$depth = 0;
			$cur   = '';
			foreach ( str_split( $selectors ) as $ch ) {
				$depth += ( '(' === $ch || '[' === $ch ) ? 1 : ( ( ')' === $ch || ']' === $ch ) ? -1 : 0 );
				if ( ',' === $ch && 0 === $depth ) {
					$parts[] = trim( $cur );
					$cur     = '';
					continue;
				}
				$cur .= $ch;
			}
			$parts[] = trim( $cur );
			foreach ( $parts as $sel ) {
				++$count;
				if ( ':root' === $sel ) {
					foreach ( array_filter( array_map( 'trim', explode( ';', $decls ) ) ) as $d ) {
						nh_assert( 0 === strpos( $d, '--' ), ":root sets custom properties only, not \"$d\"" );
					}
					continue;
				}
				// a leading html(.class) only states a mode; the scope must come after it
				$rest = (string) preg_replace( '~^html(?::[a-z-]+\([^)]*\)|\.[a-z0-9-]+)*\s*~i', '', $sel );
				nh_assert( 1 === preg_match( $ours, $rest ), "global selector in base.css: $sel" );
			}
		}
		nh_assert( $count > 100, "parsed $count selectors" );
	},

	'activation and deactivation clear Elementor\'s caches; never fatal without Elementor' => static function () {
		$basename = plugin_basename( NH_TEST_PLUGIN . '/nevehairus-site.php' );
		$GLOBALS['nh_test']['elementor_cache_cleared'] = 0;
		do_action( 'activate_' . $basename );
		nh_assert_same( 1, $GLOBALS['nh_test']['elementor_cache_cleared'], 'register_activation_hook' );
		do_action( 'deactivate_' . $basename );
		nh_assert_same( 2, $GLOBALS['nh_test']['elementor_cache_cleared'], 'register_deactivation_hook' );
		$files = \Elementor\Plugin::$instance->files_manager;
		\Elementor\Plugin::$instance->files_manager = null;
		try {
			nh_assert( false === NH_Plugin::clear_elementor_cache(), 'no files manager: nothing to clear, no error' );
		} finally {
			\Elementor\Plugin::$instance->files_manager = $files;
		}
		nh_assert( true === NH_Plugin::clear_elementor_cache() );
	},

	'uninstall removes nh_settings and nh_version' => static function () {
		update_option( 'nh_settings', [ 'phone_display' => '1' ] );
		update_option( 'nh_version', NH_VERSION );
		update_option( 'unrelated', 1 );
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'nevehairus-site/nevehairus-site.php' );
		}
		require NH_TEST_PLUGIN . '/uninstall.php';
		nh_assert_same( false, get_option( 'nh_settings', false ) );
		nh_assert_same( false, get_option( 'nh_version', false ) );
		nh_assert_same( 1, get_option( 'unrelated' ) );
	},

	'version: header, constant and readme agree (0.2.0 or later)' => static function () {
		nh_assert_same( true, version_compare( NH_VERSION, '0.2.0', '>=' ) );
		nh_assert_contains( ' * Version: ' . NH_VERSION, (string) file_get_contents( NH_TEST_PLUGIN . '/nevehairus-site.php' ) );
		nh_assert_contains( 'Stable tag: ' . NH_VERSION, (string) file_get_contents( NH_TEST_PLUGIN . '/readme.txt' ) );
	},

	'asset URLs carry the file time (a deploy never leaves a cached CSS/JS behind)' => static function () {
		$v = NH_Assets::ver( 'assets/css/base.css' );
		nh_assert_same( NH_VERSION . '.' . filemtime( NH_TEST_PLUGIN . '/assets/css/base.css' ), $v );
		nh_assert_same( NH_VERSION, NH_Assets::ver( 'assets/css/no-such-file.css' ) );
	},

	'the child theme: Hello\'s CSS, skip link and title off, Google Fonts off, one skip link, main#content on Elementor\'s templates, style.css after Elementor' => static function () {
		if ( ! function_exists( 'get_stylesheet_uri' ) ) {
			function get_stylesheet_uri() { // phpcs:ignore
				return 'https://nh.test/wp-content/themes/hello-elementor-child/style.css';
			}
		}
		if ( ! function_exists( 'wp_get_theme' ) ) {
			function wp_get_theme() { // phpcs:ignore
				return new class() {
					public function get( $key ) {
						return 'Version' === $key ? '1.1.0' : '';
					}
				};
			}
		}
		require_once NH_TEST_ROOT . '/theme/hello-elementor-child/functions.php';
		foreach ( [ 'hello_elementor_enqueue_style', 'hello_elementor_enqueue_theme_style', 'hello_elementor_enable_skip_link', 'elementor/frontend/print_google_fonts' ] as $hook ) {
			nh_assert( false === apply_filters( $hook, true ), $hook );
		}
		nh_assert( true === apply_filters( 'hello_elementor_page_title', true ), 'a page not built with Elementor keeps Hello\'s title' );
		update_post_meta( 10, '_elementor_edit_mode', 'builder' );
		nh_assert( false === apply_filters( 'hello_elementor_page_title', true ), 'built with Elementor: the cover is the H1' );
		nh_assert_same( '<a class="nh-skip" href="#content">דילוג לתוכן הראשי</a>', nh_test_capture( 'nh_child_skip_link' ) );
		nh_assert_same( 5, has_action( 'wp_body_open', 'nh_child_skip_link' ) );
		foreach ( [ 'header-footer', 'canvas' ] as $t ) {
			nh_assert_same( '<main id="content" class="site-main" tabindex="-1">', nh_test_capture( static fn() => do_action( "elementor/page_templates/$t/before_content" ) ), "$t opens main#content" );
			nh_assert_same( '</main>', nh_test_capture( static fn() => do_action( "elementor/page_templates/$t/after_content" ) ), "$t closes it" );
		}
		nh_child_styles();
		nh_assert( wp_style_is( 'nh-child' ), 'enqueued' );
		nh_assert_same( [], $GLOBALS['nh_test']['styles']['nh-child']['deps'], 'no Elementor: no dependency' );
		unset( $GLOBALS['nh_test']['styles']['nh-child'] );
		wp_register_style( 'elementor-frontend', 'https://nh.test/frontend.min.css' );
		try {
			nh_child_styles();
			nh_assert_same( [ 'elementor-frontend' ], $GLOBALS['nh_test']['styles']['nh-child']['deps'], 'printed after Elementor\'s frontend CSS' );
			nh_assert_same( 'https://nh.test/wp-content/themes/hello-elementor-child/style.css', $GLOBALS['nh_test']['styles']['nh-child']['src'] );
		} finally {
			unset( $GLOBALS['nh_test']['styles']['nh-child'], $GLOBALS['nh_test']['styles']['elementor-frontend'] );
		}
		$css = (string) file_get_contents( NH_TEST_ROOT . '/theme/hello-elementor-child/style.css' );
		foreach ( [ 'Template:     hello-elementor', '.nh-skip', 'overflow-x: clip', '.elementor-location-footer a', 'prefers-reduced-motion: reduce', ':where(:focus-visible)', '.site-main a:where(:not([class]))' ] as $needle ) {
			nh_assert_contains( $needle, $css );
		}
	},
];
