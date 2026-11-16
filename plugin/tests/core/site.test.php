<?php
/**
 * Core tests 5 — NH_Site (SPEC §11.5 item 5, §5): head order and the pre-paint script, body open
 * (sprite + skip link + hidden banner), footer (bar + live region + queued parts), the preview, the
 * cover preload, and parity of the printed parts with the prototype partials (header.html L1–2 and
 * L79, footer.html L60–63, cookies.html, sprite.html reduced to nine symbols).
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * compare.py's normalised tree of an HTML fragment (the same normalisation as the widget compare).
 */
function nh_test_tree( string $html ): string {
	$tmp = tempnam( sys_get_temp_dir(), 'nhtree' );
	file_put_contents( $tmp, '<!doctype html><html><body>' . $html . '</body></html>' );
	$out = (string) shell_exec( 'python3 -I ' . escapeshellarg( NH_TEST_DIR . '/compare.py' ) . ' --tree ' . escapeshellarg( $tmp ) . ' body' );
	unlink( $tmp );
	return $out;
}

/**
 * Lines $from–$to of a prototype partial.
 */
function nh_test_partial( string $name, int $from = 1, int $to = PHP_INT_MAX ): string {
	$lines = file( NH_TEST_ROOT . '/prototype/src/partials/' . $name . '.html' );
	return implode( '', array_slice( $lines, $from - 1, $to - $from + 1 ) );
}

return [
	'head(): meta, font preloads, cover preload, then the pre-paint script' => static function () {
		$id = nh_test_media_id( 'v3-walk' );
		update_post_meta(
			10,
			'_elementor_data',
			wp_json_encode(
				[
					[
						'elType'   => 'container',
						'elements' => [
							[ 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [] ],
							[
								'elType'   => 'container',
								'elements' => [
									[
										'elType'     => 'widget',
										'widgetType' => 'nh-cover',
										'settings'   => [ 'photo' => [ 'id' => $id, 'url' => '' ] ],
									],
								],
							],
						],
					],
				]
			)
		);
		$head = nh_test_capture( [ 'NH_Site', 'head' ] );
		$pos  = [];
		foreach ( [ '<meta name="color-scheme" content="light">', '<meta name="theme-color" content="#F7F1E7">', 'frank-ruhl-libre-core-var.woff2" as="font" type="font/woff2" crossorigin>', 'assistant-core-var.woff2" as="font"', '<link rel="preload" as="image" type="image/avif"', '<script id="nh-prepaint" data-no-optimize="1" data-no-defer="1" data-cfasync="false">' ] as $needle ) {
			$p = strpos( $head, $needle );
			nh_assert( false !== $p, "head has $needle" );
			$pos[] = $p;
		}
		$sorted = $pos;
		sort( $sorted );
		nh_assert_same( $sorted, $pos, 'the order of SPEC §5.1' );
		nh_assert_contains( 'href="../../nevehairus-site/assets/fonts/frank-ruhl-libre-core-var.woff2"', $head, 'no ?ver on fonts' );
		nh_assert_contains( 'imagesizes="(min-width: 1024px) 30rem, 44vw"', $head, 'the cover widget\'s default sizes' );
	},

	'cover_preload(): page cover, sizes override, nothing on malformed data' => static function () {
		$id = nh_test_media_id( 'v3-sky-room' );
		update_post_meta( 10, '_elementor_data', wp_json_encode( [ [ 'elType' => 'widget', 'widgetType' => 'nh-page-cover', 'settings' => [ 'photo' => [ 'id' => $id ], 'photo_sizes' => '50vw' ] ] ] ) );
		nh_assert_contains( 'v3-sky-room-768.avif" imagesrcset=', NH_Site::cover_preload() );
		nh_assert_contains( 'imagesizes="50vw"', NH_Site::cover_preload() );
		update_post_meta( 10, '_elementor_data', wp_json_encode( [ [ 'elType' => 'widget', 'widgetType' => 'nh-page-cover', 'settings' => [ 'photo' => [ 'id' => $id ] ] ] ] ) );
		nh_assert_contains( 'imagesizes="(min-width: 1024px) 22rem, 44vw"', NH_Site::cover_preload() );
		foreach ( [ '{not json', '"a string"', '[{"elements":"x"}]', '[[[[[]]]]]', '' ] as $bad ) {
			update_post_meta( 10, '_elementor_data', $bad );
			nh_assert_same( '', NH_Site::cover_preload(), "malformed: $bad" );
		}
		$GLOBALS['nh_test']['is_singular'] = false;
		nh_assert_same( '', NH_Site::cover_preload(), 'not singular' );
	},

	'the pre-paint script: storage keys, ?plain, the preview, calm default, pagereveal' => static function () {
		$js = NH_Site::prepaint_js();
		foreach ( [ 'h.classList.add("js")', '"nh-motion") === "reduce"', '"nh-calm") === "on"', 'get("nh-calm") === null && cd', '/[?&]plain=([01])/', 'get("nh-plain") === "on"', 'nh-plainmode', '/[?&]elementor-preview=/', '"pagereveal"', 'nh-vt-in', 'sessionStorage.getItem("nh-vt")', '.v3-cover--page .v3-room[data-vt]', 'getAttribute("data-vt") === vt' ] as $needle ) {
			nh_assert_contains( $needle, $js );
		}
		nh_assert_contains( 'var cd = false;', $js );
		update_post_meta( 10, '_elementor_page_settings', [ 'nh_calm_default' => 'yes' ] );
		nh_assert( NH_Site::calm_default() );
		nh_assert_contains( 'var cd = true;', NH_Site::prepaint_js() );
		nh_assert( strpos( $js, 'elementor-preview' ) < strpos( $js, 'nh-motion' ), 'the preview check comes before the stored modes' );
	},

	'body_open(): sprite, skip link (#main under Full Width, else #content), hidden banner — once' => static function () {
		NH_Site::remember_template( '/srv/www/wp-content/plugins/elementor/modules/page-templates/templates/header-footer.php' );
		$out = nh_test_capture( [ 'NH_Site', 'body_open' ] );
		nh_assert( 0 === strpos( $out, '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">' ) );
		nh_assert_same( 9, substr_count( $out, '<symbol ' ) );
		nh_assert_contains( '<a class="nh-skip" href="#main">דילוג לתוכן הראשי</a>', $out );
		nh_assert_contains( '<section class="nh-cookies" id="nh-cookies" aria-labelledby="nh-cookies-title" hidden>', $out );
		nh_assert( strpos( $out, 'nh-skip' ) < strpos( $out, 'nh-cookies' ), 'banner second in Tab order' );
		nh_assert_same( '', nh_test_capture( [ 'NH_Site', 'body_open' ] ), 'printed once' );
		nh_test_reset_request();
		NH_Site::remember_template( '/srv/www/wp-content/themes/hello-elementor/index.php' );
		nh_assert_contains( 'href="#content"', nh_test_capture( [ 'NH_Site', 'body_open' ] ) );
	},

	'the Elementor preview: no banner, no visit bar' => static function () {
		\Elementor\Plugin::$instance->preview->preview = true;
		nh_assert( NH_Site::is_preview() );
		nh_assert_not_contains( 'nh-cookies', nh_test_capture( [ 'NH_Site', 'body_open' ] ) );
		$f = nh_test_capture( [ 'NH_Site', 'footer' ] );
		nh_assert_not_contains( 'data-bar', $f );
		nh_assert_contains( 'data-announce', $f );
		\Elementor\Plugin::$instance->preview->preview = false;
		$_GET = [ 'elementor-preview' => '10' ];
		nh_assert( NH_Site::is_preview() );
	},

	'footer(): bar, live region, queued parts in order (once); fallback when wp_body_open never ran' => static function () {
		NH_Site::footer_once( 'a', static fn() => '<p id="part-a"></p>' );
		NH_Site::footer_once( 'b', static fn() => '<p id="part-b"></p>' );
		NH_Site::footer_once( 'a', static fn() => '<p id="part-a-again"></p>' );
		$f = nh_test_capture( [ 'NH_Site', 'footer' ] );
		nh_assert_contains( '<symbol id="i-phone"', $f, 'wp_body_open never ran: the sprite falls back here' );
		nh_assert_contains( 'nh-cookies', $f, '… and the banner' );
		nh_assert_not_contains( 'nh-skip', $f, 'a skip link at the end of the page would be useless (D1)' );
		$order = [ strpos( $f, 'data-bar' ), strpos( $f, 'data-announce' ), strpos( $f, 'part-a' ), strpos( $f, 'part-b' ) ];
		nh_assert( ! in_array( false, $order, true ), 'all parts' );
		$sorted = $order;
		sort( $sorted );
		nh_assert_same( $sorted, $order, 'bar, live region, queue order' );
		nh_assert_not_contains( 'part-a-again', $f );
		nh_assert_same( '<p id="late"></p>', nh_test_capture( static fn() => NH_Site::footer_once( 'late', static fn() => '<p id="late"></p>' ) ), 'after the footer: printed at once' );
		nh_test_reset_request( [ 'show_bar' => 0 ] );
		nh_test_capture( [ 'NH_Site', 'body_open' ] );
		$f = nh_test_capture( [ 'NH_Site', 'footer' ] );
		nh_assert_not_contains( 'data-bar', $f, 'show_bar off' );
		nh_assert_not_contains( 'nh-cookies', $f, 'no fallback after wp_body_open' );
	},

	'parity: skip link and live region = header.html L1 and L79' => static function () {
		NH_Site::remember_template( 'x/page-templates/templates/header-footer.php' );
		nh_assert_same( nh_test_tree( nh_test_partial( 'header', 1, 1 ) ), nh_test_tree( NH_Site::skip_link() ) );
		nh_assert_same( nh_test_tree( nh_test_partial( 'header', 79, 79 ) ), nh_test_tree( NH_Site::live_region() ) );
	},

	'parity: cookie banner = cookies.html (and without the statistics row when its text is empty)' => static function () {
		nh_assert_same( nh_test_tree( nh_test_partial( 'cookies' ) ), nh_test_tree( NH_Site::cookie_banner() ) );
		update_option( 'nh_settings', [ 'cookie_stats' => '' ] );
		$b = NH_Site::cookie_banner();
		nh_assert_not_contains( 'nh-c-stats', $b );
		nh_assert_contains( 'nh-c-media', $b );
		$GLOBALS['nh_test']['privacy_url'] = 'https://nh.test/privacy/';
		nh_assert_contains( '<a href="https://nh.test/privacy/">מדיניות הפרטיות</a>', NH_Site::cookie_banner(), '#privacy resolved' );
	},

	'parity: visit bar = footer.html L60–63' => static function () {
		nh_assert_same( nh_test_tree( nh_test_partial( 'footer', 60, 63 ) ), nh_test_tree( NH_Site::visit_bar() ) );
	},

	'parity: sprite = sprite.html reduced to the nine symbols of v3' => static function () {
		$proto = nh_test_partial( 'sprite' );
		$proto = (string) preg_replace_callback(
			'~\s*<symbol id="i-([a-z-]+)".*?</symbol>~s',
			static function ( $m ) {
				return in_array( $m[1], NH_Html::ICONS, true ) ? $m[0] : '';
			},
			$proto
		);
		nh_assert_same( nh_test_tree( $proto ), nh_test_tree( NH_Site::sprite() ) );
		foreach ( NH_Html::ICONS as $icon ) {
			nh_assert_contains( 'id="i-' . $icon . '"', NH_Site::sprite() );
		}
	},

	'main, body class, form-state pages are not cached, js_data' => static function () {
		nh_assert_same( '<main id="main" class="nh-main" tabindex="-1">', nh_test_capture( [ 'NH_Site', 'main_open' ] ) );
		nh_assert_same( '</main>', nh_test_capture( [ 'NH_Site', 'main_close' ] ) );
		nh_assert_same( [ 'x', 'v3' ], NH_Site::body_class( [ 'x' ] ) );
		$_GET = [ 'nh_status' => 'sent' ];
		NH_Site::no_cache_for_form_states();
		nh_assert( ! empty( $GLOBALS['nh_test']['headers']['nocache'] ) && defined( 'DONOTCACHEPAGE' ) );
		$d = NH_Site::js_data();
		nh_assert_same( [ 'ajax', 'post', 'visitUrl', 'consent', 'i18n', 'forms' ], array_keys( $d ) );
		nh_assert_same( 'https://nh.test/wp-admin/admin-ajax.php', $d['ajax'] );
		nh_assert_same( 'https://nh.test/#door', $d['visitUrl'] );
		nh_assert_same( [ 'key' => 'nh-consent-v1', 'v' => 1 ], $d['consent'] );
		nh_assert_same( 'הדף מוצג עכשיו בשפה פשוטה.', $d['i18n']['plainOn'] );
		nh_assert_same( 'נמצאו {n} שגיאות בטופס:', $d['forms']['msg']['summaryMany'] );
		nh_assert_contains( 'tel:+97289403047', $d['forms']['msg']['limited'] );
		nh_assert_same( [ 'required', 'email', 'tel', 'consent', 'fileSize', 'fileType', 'summary', 'summaryOne', 'summaryMany', 'expired', 'limited', 'off', 'error' ], array_keys( $d['forms']['msg'] ) );
	},

	'assets: core handles, convention handles, deps, defer' => static function () {
		$s = $GLOBALS['nh_test']['styles'];
		$j = $GLOBALS['nh_test']['scripts'];
		nh_assert_same( [ 'nh-tokens' ], $s['nh-base']['deps'] );
		nh_assert_same( [ 'nh-base' ], $s['nh-letters']['deps'] );
		nh_assert_same( [ 'nh-base' ], $s['nh-w-cover']['deps'] );
		nh_assert_same( [ 'nh-core' ], $j['nh-w-cover']['deps'] );
		nh_assert_same( [ 'nh-core' ], $j['nh-forms']['deps'] );
		nh_assert_same( [ 'in_footer' => true, 'strategy' => 'defer' ], $j['nh-core']['args'] );
		nh_assert_same( 'nh-w-cover', NH_Assets::widget_style( 'cover' ) );
		nh_assert_same( null, NH_Assets::widget_style( 'nothing-here' ) );
		nh_assert_same( null, NH_Assets::widget_script( '../cover' ) );
		$w = nh_test_widget( 'cover', 'x' );
		nh_assert_same( [ 'nh-letters', 'nh-w-cover' ], $w->get_style_depends() );
		nh_assert_same( [ 'nh-w-cover' ], $w->get_script_depends() );
		nh_assert_same( 'nh-cover', $w->get_name() );
		nh_assert_same( [ 'nevehairus' ], $w->get_categories() );
		nh_assert( false === $w->has_widget_inner_wrapper() );
		nh_assert( false === $w->nh_test_is_dynamic() );
		nh_assert_same( 'נווה האירוס', $GLOBALS['nh_test_elementor']['elements']->categories['nevehairus']['title'] );
		nh_assert( isset( $GLOBALS['nh_test_elementor']['widgets']->widgets['nh-cover'] ), 'registered with Elementor' );
		do_action( 'wp_enqueue_scripts' );
		nh_assert( wp_style_is( 'nh-base' ) && wp_style_is( 'nh-tokens' ) && wp_script_is( 'nh-core' ), 'site-wide handles enqueued' );
		nh_assert( ! wp_style_is( 'nh-letters' ), 'widget handles only through the widgets' );
		nh_assert_contains( 'window.nhData={', $GLOBALS['nh_test']['inline']['nh-core']['before'][0] );
	},

	'Hello and Elementor compatibility filters' => static function () {
		nh_assert( false === apply_filters( 'hello_elementor_enqueue_style', true ) );
		nh_assert( false === apply_filters( 'hello_elementor_enqueue_theme_style', true ) );
		nh_assert( false === apply_filters( 'hello_elementor_enable_skip_link', true ) );
		nh_assert( false === apply_filters( 'elementor/frontend/print_google_fonts', true ) );
		nh_assert( true === apply_filters( 'hello_elementor_page_title', true ), 'not built with Elementor' );
		update_post_meta( 10, '_elementor_edit_mode', 'builder' );
		nh_assert( false === apply_filters( 'hello_elementor_page_title', true ) );
		nh_assert_same( [ 'image/jpeg' => 'image/webp', 'image/png' => 'image/webp' ], apply_filters( 'image_editor_output_format', [] ) );
		update_option( 'nh_settings', [ 'webp_subsizes' => 0 ] );
		nh_assert_same( [], apply_filters( 'image_editor_output_format', [] ) );
	},
];
