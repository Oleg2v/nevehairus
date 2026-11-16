<?php
/**
 * Core tests 5 — NH_Site (SPEC §11.5 item 5, §5; research/07 §12.2–12.3): head order and the pre-paint
 * script, body open (the sprite only), footer (cookie-settings panel + live region + queued parts), the
 * preview, the cover preload, and parity of the printed parts with the prototype partials (the live region
 * of header.html, cookies.html, sprite.html reduced to nine symbols). Every part needs a page that uses our
 * widgets: the tests write one into the queried post (10); core/scope.test.php covers the pages without.
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

/**
 * The first line of a prototype partial that contains $needle ('' if none): parity by content, so the
 * test survives the prototype's lines moving.
 */
function nh_test_partial_line( string $name, string $needle ): string {
	foreach ( file( NH_TEST_ROOT . '/prototype/src/partials/' . $name . '.html' ) as $line ) {
		if ( false !== strpos( $line, $needle ) ) {
			return $line;
		}
	}
	return '';
}

/**
 * The queried post (10) holds one of our widgets: the request "uses widgets" (research/07 §12.2).
 */
function nh_test_use_widgets( string $type = 'nh-cover' ): void {
	update_post_meta( 10, '_elementor_data', wp_json_encode( [ [ 'elType' => 'container', 'elements' => [ [ 'elType' => 'widget', 'widgetType' => $type, 'settings' => [] ] ] ] ] ) );
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

	'the pre-paint script: storage keys, ?plain, the preview, calm default; no pagereveal, no js class' => static function () {
		$js = NH_Site::prepaint_js();
		foreach ( [ '"nh-motion") === "reduce"', '"nh-calm") === "on"', 'get("nh-calm") === null && cd', '/[?&]plain=([01])/', 'get("nh-plain") === "on"', 'nh-plainmode', '/[?&]elementor-preview=/' ] as $needle ) {
			nh_assert_contains( $needle, $js );
		}
		foreach ( [ 'classList.add("js")', 'pagereveal', 'viewTransition', 'nh-vt', 'sessionStorage', 'data-vt' ] as $gone ) {
			nh_assert_not_contains( $gone, $js, 'removed in v3.2 (§12.3)' );
		}
		nh_assert_contains( 'var cd = false;', $js );
		update_post_meta( 10, '_elementor_page_settings', [ 'nh_calm_default' => 'yes' ] );
		nh_assert( NH_Site::calm_default() );
		nh_assert_contains( 'var cd = true;', NH_Site::prepaint_js() );
		nh_assert( strpos( $js, 'elementor-preview' ) < strpos( $js, 'nh-motion' ), 'the preview check comes before the stored modes' );
		$GLOBALS['nh_test']['is_singular'] = false;
		nh_assert( ! NH_Site::calm_default(), 'not singular: the queried object is not a post, its meta is not read' );
	},

	'body_open(): only the sprite, once (no skip link: the theme\'s; no banner)' => static function () {
		nh_test_use_widgets();
		$out = nh_test_capture( [ 'NH_Site', 'body_open' ] );
		nh_assert( 0 === strpos( $out, '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">' ) );
		nh_assert_same( 9, substr_count( $out, '<symbol ' ) );
		nh_assert_not_contains( 'nh-skip', $out, 'the skip link is the child theme\'s (§12.5)' );
		nh_assert_not_contains( 'nh-cookies', $out, 'the cookie panel is in the footer (§9)' );
		nh_assert_same( '', nh_test_capture( [ 'NH_Site', 'body_open' ] ), 'printed once' );
	},

	'the Elementor preview: our parts, but no cookie panel' => static function () {
		\Elementor\Plugin::$instance->preview->preview = true;
		nh_assert( NH_Site::is_preview() );
		nh_assert( NH_Site::uses_widgets(), 'any widget may be dropped in' );
		nh_assert_contains( '<symbol id="i-phone"', nh_test_capture( [ 'NH_Site', 'body_open' ] ) );
		$f = nh_test_capture( [ 'NH_Site', 'footer' ] );
		nh_assert_not_contains( 'nh-cookies', $f );
		nh_assert_contains( 'data-announce', $f );
		\Elementor\Plugin::$instance->preview->preview = false;
		$_GET = [ 'elementor-preview' => '10' ];
		nh_assert( NH_Site::is_preview() );
	},

	'footer(): cookie panel, live region, queued parts in order (once); the sprite when wp_body_open never ran' => static function () {
		nh_test_use_widgets();
		NH_Site::footer_once( 'a', static fn() => '<p id="part-a"></p>' );
		NH_Site::footer_once( 'b', static fn() => '<p id="part-b"></p>' );
		NH_Site::footer_once( 'a', static fn() => '<p id="part-a-again"></p>' );
		$f = nh_test_capture( [ 'NH_Site', 'footer' ] );
		nh_assert_contains( '<symbol id="i-phone"', $f, 'wp_body_open never ran: the sprite falls back here' );
		nh_assert_not_contains( 'nh-skip', $f );
		nh_assert_not_contains( 'data-bar', $f, 'no visit bar (§3 item 4)' );
		$order = [ strpos( $f, '<svg' ), strpos( $f, 'id="nh-cookies"' ), strpos( $f, 'data-announce' ), strpos( $f, 'part-a' ), strpos( $f, 'part-b' ) ];
		nh_assert( ! in_array( false, $order, true ), 'all parts' );
		$sorted = $order;
		sort( $sorted );
		nh_assert_same( $sorted, $order, 'sprite, panel, live region, queue order' );
		nh_assert_not_contains( 'part-a-again', $f );
		nh_assert_same( '<p id="late"></p>', nh_test_capture( static fn() => NH_Site::footer_once( 'late', static fn() => '<p id="late"></p>' ) ), 'after the footer: printed at once' );
		nh_test_reset_request();
		nh_test_use_widgets();
		nh_test_capture( [ 'NH_Site', 'body_open' ] );
		$f = nh_test_capture( [ 'NH_Site', 'footer' ] );
		nh_assert_not_contains( '<svg', $f, 'no second sprite after wp_body_open' );
		nh_assert_same( 1, substr_count( $f, 'id="nh-cookies"' ) );
	},

	'footer(): a part queued by a widget is printed even where uses_widgets() found nothing' => static function () {
		nh_assert( ! NH_Site::uses_widgets(), 'post 10 holds no widget of ours' );
		NH_Site::footer_once( 'x', static fn() => '<p id="part-x"></p>' );
		nh_assert_same( '<p id="part-x"></p>' . "\n", nh_test_capture( [ 'NH_Site', 'footer' ] ) );
	},

	'parity: live region = the one in header.html' => static function () {
		$line = nh_test_partial_line( 'header', 'data-announce>' );
		nh_assert( '' !== $line, 'header.html has the live region' );
		nh_assert_same( nh_test_tree( $line ), nh_test_tree( NH_Site::live_region() ) );
	},

	'parity: display switches = the phone menu\'s switches in header.html (research/07 §7.3)' => static function () {
		$proto = '';
		foreach ( file( NH_TEST_ROOT . '/prototype/src/partials/header.html' ) as $line ) {
			if ( false !== strpos( $line, 'class="v3-toggle"' ) ) {
				$proto .= $line;
			}
		}
		nh_assert( '' !== $proto, 'header.html has the switches' );
		nh_assert_same( nh_test_tree( $proto ), nh_test_tree( NH_Html::display_toggles( [ 'plain' => 'בשפה פשוטה', 'calm' => 'מצב רגוע' ] ) ) );
	},

	'parity: cookie panel = cookies.html (research/07 §9), without the statistics row unless it has a text' => static function () {
		nh_assert_same( nh_test_tree( nh_test_partial( 'cookies' ) ), nh_test_tree( NH_Site::cookie_panel() ) );
		$p = NH_Site::cookie_panel();
		nh_assert( 0 === strpos( $p, '<section class="nh-cookies nh-on-dark" id="nh-cookies" aria-labelledby="nh-cookies-title" hidden>' ), 'hidden: it never shows itself' );
		foreach ( [ 'data-cookie-more', 'data-consent="all"', 'data-consent="necessary"', 'data-consent="settings"', 'nh-c-stats', 'סטטיסטיקה' ] as $gone ) {
			nh_assert_not_contains( $gone, $p );
		}
		update_option( 'nh_settings', [ 'cookie_stats' => '<b>סטטיסטיקה</b> — בדיקה.' ] );
		nh_assert_contains( '<input type="checkbox" id="nh-c-stats">', NH_Site::cookie_panel(), 'a text: the row is back' );
		update_option( 'nh_settings', [ 'cookie_title' => '', 'cookie_close' => '  ' ] );
		$p = NH_Site::cookie_panel();
		nh_assert_contains( 'tabindex="-1">הגדרות עוגיות</h2>', $p, 'an empty title falls back to the default' );
		nh_assert_contains( 'data-consent="close">סגירה</button>', $p );
		update_option( 'nh_settings', [] );
		$GLOBALS['nh_test']['privacy_url'] = 'https://nh.test/privacy/';
		nh_assert_contains( '<a href="https://nh.test/privacy/">מדיניות הפרטיות</a>', NH_Site::cookie_panel(), '#privacy resolved' );
	},

	'removed from the plugin: the skip link, main, the template memory, the visit bar, the banner' => static function () {
		foreach ( [ 'skip_link', 'main_open', 'main_close', 'remember_template', 'is_full_width_template', 'visit_bar', 'cookie_banner' ] as $m ) {
			nh_assert( ! method_exists( 'NH_Site', $m ), "NH_Site::$m() is gone (§12.3)" );
		}
		foreach ( [ 'elementor/page_templates/header-footer/before_content', 'elementor/page_templates/header-footer/after_content', 'template_include' ] as $hook ) {
			nh_assert( ! has_filter( $hook ), "no plugin callback on $hook" );
		}
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

	'body class, form-state pages are not cached, js_data' => static function () {
		nh_assert_same( [ 'x' ], NH_Site::body_class( [ 'x' ] ), 'a page without our widgets: no v3' );
		nh_test_reset_request();
		nh_test_use_widgets();
		nh_assert_same( [ 'x', 'v3' ], NH_Site::body_class( [ 'x' ] ) );
		$_GET = [ 'nh_status' => 'sent' ];
		NH_Site::no_cache_for_form_states();
		nh_assert( ! empty( $GLOBALS['nh_test']['headers']['nocache'] ) && defined( 'DONOTCACHEPAGE' ) );
		$d = NH_Site::js_data();
		nh_assert_same( [ 'ajax', 'post', 'visitUrl', 'consent', 'i18n', 'forms' ], array_keys( $d ) );
		nh_assert_same( 'https://nh.test/wp-admin/admin-ajax.php', $d['ajax'] );
		nh_assert_same( 'https://nh.test/#door', $d['visitUrl'] );
		nh_assert_same( [ 'key' => 'nh-consent-v1', 'v' => 1 ], $d['consent'] );
		nh_assert_same( [ 'plainOn', 'plainOff', 'calmOn', 'calmOff', 'videoTitle' ], array_keys( $d['i18n'] ), 'no motionOn / motionOff (§12.3)' );
		nh_assert_same( 'הדף מוצג עכשיו בשפה פשוטה.', $d['i18n']['plainOn'] );
		nh_assert_same( 'נמצאו {n} שגיאות בטופס:', $d['forms']['msg']['summaryMany'] );
		nh_assert_contains( 'tel:+97289403047', $d['forms']['msg']['limited'] );
		nh_assert_same( [ 'required', 'email', 'tel', 'consent', 'fileSize', 'fileType', 'summary', 'summaryOne', 'summaryMany', 'expired', 'limited', 'off', 'error' ], array_keys( $d['forms']['msg'] ) );
	},

	'assets: core handles, convention handles, deps, defer; the core travels with the widgets' => static function () {
		$s = $GLOBALS['nh_test']['styles'];
		$j = $GLOBALS['nh_test']['scripts'];
		nh_assert_same( [ 'nh-tokens' ], $s['nh-base']['deps'] );
		nh_assert_same( [ 'nh-base' ], $s['nh-letters']['deps'] );
		nh_assert_same( [ 'nh-base' ], $s['nh-w-cover']['deps'] );
		nh_assert_same( [ 'nh-core' ], $j['nh-w-masthead']['deps'] );
		nh_assert( ! isset( $j['nh-w-cover'] ), 'v3.2: the cover has no script (no morph, research/07 §13)' );
		nh_assert_same( [ 'nh-core' ], $j['nh-forms']['deps'] );
		nh_assert_same( [ 'in_footer' => true, 'strategy' => 'defer' ], $j['nh-core']['args'] );
		nh_assert_same( 'nh-w-cover', NH_Assets::widget_style( 'cover' ) );
		nh_assert_same( null, NH_Assets::widget_style( 'nothing-here' ) );
		nh_assert_same( null, NH_Assets::widget_script( '../cover' ) );
		$w = nh_test_widget( 'cover', 'x' );
		nh_assert_same( [ 'nh-base', 'nh-letters', 'nh-w-cover' ], $w->get_style_depends(), 'nh-base first (§12.1)' );
		nh_assert_same( [ 'nh-core' ], $w->get_script_depends(), 'nh-core first (§12.1); no script of its own' );
		nh_assert_same( 'nh-cover', $w->get_name() );
		nh_assert_same( [ 'nevehairus' ], $w->get_categories() );
		nh_assert( false === $w->has_widget_inner_wrapper() );
		nh_assert( true === $w->nh_test_is_dynamic(), 'v3.2: the cover prints the phone from the settings' );
		nh_assert( false === nh_test_widget( 'steps', 'x' )->nh_test_is_dynamic(), 'a widget that reads nothing but its settings may be cached' );
		$m = nh_test_widget( 'masthead', 'x' );
		nh_assert_same( [ 'nh-core', 'nh-w-masthead' ], $m->get_script_depends(), 'nh-core, then the widget\'s own script' );
		nh_assert_same( 'נווה האירוס', $GLOBALS['nh_test_elementor']['elements']->categories['nevehairus']['title'] );
		nh_assert( isset( $GLOBALS['nh_test_elementor']['widgets']->widgets['nh-cover'] ), 'registered with Elementor' );
		nh_test_use_widgets();
		do_action( 'wp_enqueue_scripts' );
		nh_assert( wp_style_is( 'nh-base' ) && wp_style_is( 'nh-tokens' ) && wp_script_is( 'nh-core' ), 'the core enqueued early (in <head>) on a page with our widgets' );
		nh_assert( ! wp_style_is( 'nh-letters' ), 'widget handles only through the widgets' );
		nh_assert_contains( 'window.nhData={', $GLOBALS['nh_test']['inline']['nh-core']['before'][0], 'attached to nh-core: printed only with it' );
	},

	'NH_Compat: nothing global — no Hello or Google Fonts filter; WebP sub-sizes only when the setting is on' => static function () {
		foreach ( [ 'hello_elementor_enqueue_style', 'hello_elementor_enqueue_theme_style', 'hello_elementor_enable_skip_link', 'hello_elementor_page_title', 'elementor/frontend/print_google_fonts' ] as $hook ) {
			nh_assert( ! has_filter( $hook ), "the plugin does not hook $hook (→ the child theme, §12.5)" );
		}
		nh_assert( ! has_filter( 'image_editor_output_format' ), 'off by default: not even hooked' );
		nh_assert_same( [], apply_filters( 'image_editor_output_format', [] ) );
		update_option( 'nh_settings', [ 'webp_subsizes' => 1 ] );
		NH_Compat::init();
		try {
			nh_assert_same( [ 'image/jpeg' => 'image/webp', 'image/png' => 'image/webp' ], apply_filters( 'image_editor_output_format', [] ) );
			update_option( 'nh_settings', [ 'webp_subsizes' => 0 ] );
			nh_assert_same( [ 'image/gif' => 'image/gif' ], apply_filters( 'image_editor_output_format', [ 'image/gif' => 'image/gif' ] ), 'switched off later in the request: unchanged' );
		} finally {
			remove_filter( 'image_editor_output_format', [ 'NH_Compat', 'output_format' ], 10 );
		}
	},
];
