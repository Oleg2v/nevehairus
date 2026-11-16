<?php
defined( 'ABSPATH' ) || exit;

/**
 * Site module (SPEC §5; research/07 §12.2–12.3): head (meta, font and cover preloads, the pre-paint script),
 * body open (the SVG sprite), footer (the cookie-settings panel, the global live region), body class `v3`, and
 * the page setting "calm mode by default".
 *
 * A guest on the site: every part is printed only on a page that uses our widgets (uses_widgets()) — in
 * the requested post or in the Theme Builder header/footer shown on it. The skip link and `<main>` belong
 * to the child theme (research/07 §12.5).
 *
 * @package nevehairus-site
 */
final class NH_Site {

	/** Widget types whose photo is the LCP image, with their default `sizes`. */
	private const COVERS = [
		'nh-cover'      => '(min-width: 1024px) 30rem, 44vw',
		'nh-page-cover' => '(min-width: 1024px) 22rem, 44vw',
	];

	/** One of our widgets in Elementor's raw data: `"widgetType":"nh-…"` (wp_json_encode() writes no spaces). */
	private const WIDGET_RX = '/"widgetType"\s*:\s*"nh-/';

	/** @var bool body_open() ran in this request. */
	private static $opened = false;

	/** @var bool|null uses_widgets() of this request, once known. */
	private static $uses = null;

	/**
	 * Hooks (SPEC §5.1).
	 */
	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'head' ], 1 );
		add_filter( 'body_class', [ __CLASS__, 'body_class' ] );
		add_action( 'wp_body_open', [ __CLASS__, 'body_open' ], 5 );
		add_action( 'wp_footer', [ __CLASS__, 'footer' ], 5 );
		add_action( 'elementor/documents/register_controls', [ __CLASS__, 'page_settings' ] );
		add_action( 'template_redirect', [ __CLASS__, 'no_cache_for_form_states' ] );
	}

	/**
	 * Does this front-end request show one of our widgets (research/07 §12.2)? Decided on the request, no
	 * stored flag, remembered for the rest of the request:
	 * 1. admin → false;
	 * 2. the Elementor preview → true (any widget may be dropped in);
	 * 3. the Theme Builder header or footer shown on this request holds an `nh-*` widget → true, unless
	 *    the requested post uses the Elementor Canvas template (no header, no footer there);
	 * 4. the requested post (is_singular()) holds an `nh-*` widget in its raw `_elementor_data` → true
	 *    (`_elementor_page_assets` is not read: it does not exist before the first view);
	 * 5. false.
	 * Called before `wp_enqueue_scripts` (the main query and Pro's display conditions are not ready, and
	 * Pro caches what it finds for the request): false, not remembered.
	 */
	public static function uses_widgets(): bool {
		if ( null !== self::$uses ) {
			return self::$uses;
		}
		if ( is_admin() ) {
			return false;
		}
		if ( self::is_preview() ) {
			self::$uses = true;
			return true;
		}
		if ( ! did_action( 'wp_enqueue_scripts' ) ) {
			return false;
		}
		self::$uses = self::chrome_uses_widgets() || ( is_singular() && self::has_widget( (int) get_queried_object_id() ) );
		return self::$uses;
	}

	/**
	 * A post (page, template) whose raw `_elementor_data` holds one of our widgets.
	 */
	public static function has_widget( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_array( $raw ) ) {
			$raw = wp_json_encode( $raw );
		}
		return is_string( $raw ) && '' !== $raw && 1 === preg_match( self::WIDGET_RX, $raw );
	}

	/**
	 * Post ids of the Theme Builder header and footer templates Elementor Pro shows on this request:
	 * `\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()
	 * ->get_documents_for_location( 'header' | 'footer' )` — display conditions applied, unpublished
	 * templates skipped, cached by Pro for the request (it calls the same method to print them). Pro's
	 * source is not public: every step is guarded, [] without Pro or on any surprise.
	 *
	 * @return int[]
	 */
	public static function chrome_documents(): array {
		$class = '\ElementorPro\Modules\ThemeBuilder\Module';
		if ( ! class_exists( $class ) || ! method_exists( $class, 'instance' ) ) {
			return [];
		}
		$ids = [];
		try {
			$module     = $class::instance();
			$conditions = ( is_object( $module ) && method_exists( $module, 'get_conditions_manager' ) ) ? $module->get_conditions_manager() : null;
			if ( ! is_object( $conditions ) || ! method_exists( $conditions, 'get_documents_for_location' ) ) {
				return [];
			}
			foreach ( [ 'header', 'footer' ] as $location ) {
				$documents = $conditions->get_documents_for_location( $location );
				foreach ( is_array( $documents ) ? $documents : [] as $document ) {
					if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
						$ids[] = (int) $document->get_main_id();
					}
				}
			}
		} catch ( \Throwable $e ) {
			return [];
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Rule 3 of uses_widgets(): a header or footer of ours is printed on this request.
	 */
	private static function chrome_uses_widgets(): bool {
		if ( is_singular() && 'elementor_canvas' === (string) get_page_template_slug( (int) get_queried_object_id() ) ) {
			return false;
		}
		foreach ( self::chrome_documents() as $id ) {
			if ( self::has_widget( $id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * `wp_head` @1 (after `wp_enqueue_scripts`, which core hooks at the same priority first): color-scheme,
	 * theme-color, font preloads, the cover photo preload, the pre-paint script — only when uses_widgets().
	 */
	public static function head(): void {
		if ( ! self::uses_widgets() ) {
			return;
		}
		$out  = '<meta name="color-scheme" content="light">' . "\n";
		$out .= '<meta name="theme-color" content="#F7F1E7">' . "\n";
		foreach ( [ 'frank-ruhl-libre-core-var.woff2', 'assistant-core-var.woff2' ] as $font ) {
			// No ?ver: the URL must equal the one in tokens.css, or the font is downloaded twice.
			$out .= '<link rel="preload" href="' . esc_url( NH_URL . 'assets/fonts/' . $font ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
		$preload = self::cover_preload();
		if ( '' !== $preload ) {
			$out .= $preload . "\n";
		}
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
		wp_print_inline_script_tag(
			self::prepaint_js(),
			[
				'id'               => 'nh-prepaint',
				'data-no-optimize' => '1',
				'data-no-defer'    => '1',
				'data-cfasync'     => 'false',
			]
		);
	}

	/**
	 * The pre-paint script (prototype head.html L14–20): saved display choices are applied before the first
	 * paint; not inside the Elementor preview (editors see the full text); the page's calm default; the old
	 * reduced-motion choice (`nh-motion`) is still honoured (research/07 §4.3). v3.2: no `pagereveal` (page
	 * transitions are gone) and no `js` class on `<html>` (nothing used it, and a generic name may clash).
	 */
	public static function prepaint_js(): string {
		$calm = self::calm_default() ? 'true' : 'false';
		return '(function (w, d) {'
			. 'var h = d.documentElement, get = function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } };'
			. 'if (/[?&]elementor-preview=/.test(w.location.search)) return;'
			. 'var cd = ' . $calm . ';'
			. 'if (get("nh-motion") === "reduce") h.classList.add("nh-reduce-motion");'
			. 'if (get("nh-calm") === "on" || (get("nh-calm") === null && cd)) h.classList.add("nh-calm");'
			. 'var q = /[?&]plain=([01])/.exec(w.location.search);'
			. 'if (q ? q[1] === "1" : get("nh-plain") === "on") h.classList.add("nh-plainmode");'
			. '})(window, document);';
	}

	/**
	 * `body_class`: the v3 page class (it carries the type scale and the masthead height), only when
	 * uses_widgets().
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		$classes = is_array( $classes ) ? $classes : [];
		if ( self::uses_widgets() ) {
			$classes[] = 'v3';
		}
		return $classes;
	}

	/**
	 * `wp_body_open` @5: the SVG sprite (the icons are `<use href="#i-…">`), only when uses_widgets(). The
	 * skip link is the child theme's (research/07 §12.5).
	 */
	public static function body_open(): void {
		if ( self::$opened || ! self::uses_widgets() ) {
			return;
		}
		self::$opened = true;
		echo self::sprite() . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup
	}

	/**
	 * `wp_footer` @5, when uses_widgets(): the sprite if the theme never fired wp_body_open; the
	 * cookie-settings panel (not in the Elementor preview) — in the flow, right after the footer; the global
	 * live region. (v3.2: no part is queued by a widget any more — the lightbox is gone, and with it
	 * footer_once().)
	 */
	public static function footer(): void {
		if ( ! self::uses_widgets() ) {
			return;
		}
		$out = '';
		if ( ! self::$opened ) {
			self::$opened = true;
			$out         .= self::sprite() . "\n";
		}
		if ( ! self::is_preview() ) {
			$out .= self::cookie_panel() . "\n";
		}
		$out .= self::live_region() . "\n";
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * The SVG sprite: the six symbols the v3.2 pages use (prototype partials/sprite.html, paths verbatim;
	 * NH_Html::ICONS).
	 */
	public static function sprite(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false"><defs>'
			. '<symbol id="i-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></symbol>'
			. '<symbol id="i-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M10 17h10"/></symbol>'
			. '<symbol id="i-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></symbol>'
			. '<symbol id="i-arrow-fwd" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></symbol>'
			. '<symbol id="i-play" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></symbol>'
			. '<symbol id="i-speech" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/></symbol>'
			. '</defs></svg>';
	}

	/**
	 * The cookie-settings panel (research/07 §9) with the texts from the settings: a section in the flow
	 * after the footer, hidden until «הגדרות עוגיות» (`[data-cookie-settings]`, `a[href="#nh-cookies"]`)
	 * opens it — it never shows itself. Without a statistics text the statistics row is not rendered.
	 */
	public static function cookie_panel(): string {
		$t = static function ( string $key ): string {
			$v = trim( (string) NH_Settings::get( $key ) );
			return esc_html( '' !== $v ? $v : (string) ( NH_Settings::defaults()[ $key ] ?? '' ) );
		};
		$h = static function ( string $key ): string {
			return NH_Text::esc( (string) NH_Settings::get( $key ) );
		};

		$text = NH_Text::block(
			(string) NH_Settings::get( 'cookie_text' ),
			(string) NH_Settings::get( 'cookie_text_plain' ),
			[
				'full_tag'    => 'p',
				'plain_tag'   => 'div',
				'plain_lines' => 'p',
			]
		);

		$rows = '<label class="nh-check"><input type="checkbox" checked disabled><span>' . $h( 'cookie_necessary' ) . '</span></label>';
		if ( '' !== trim( (string) NH_Settings::get( 'cookie_stats' ) ) ) {
			$rows .= '<label class="nh-check"><input type="checkbox" id="nh-c-stats"><span>' . $h( 'cookie_stats' ) . '</span></label>';
		}
		$rows .= '<label class="nh-check"><input type="checkbox" id="nh-c-media"><span>' . $h( 'cookie_media' ) . '</span></label>';

		return '<section class="nh-cookies nh-on-dark" id="nh-cookies" aria-labelledby="nh-cookies-title" hidden>'
			. '<div class="nh-container nh-cookies__inner">'
			. '<h2 class="nh-cookies__title" id="nh-cookies-title" tabindex="-1">' . $t( 'cookie_title' ) . '</h2>'
			. $text
			. '<div class="nh-cookies__prefs">' . $rows . '</div>'
			. '<div class="nh-btn-row">'
			. '<button class="nh-btn nh-btn--light" type="button" data-consent="save">' . $t( 'cookie_save' ) . '</button>'
			. '<button class="nh-btn nh-btn--ghost" type="button" data-consent="close">' . $t( 'cookie_close' ) . '</button>'
			. '</div></div></section>';
	}

	/**
	 * The global live region for display-mode announcements (prototype header.html L79).
	 */
	public static function live_region(): string {
		return '<p class="nh-sr-only" role="status" aria-live="polite" data-announce></p>';
	}

	/**
	 * Preload of the cover photo of the queried post (the LCP image): the first nh-cover / nh-page-cover
	 * in `_elementor_data`, depth first. '' if none; never fatal on malformed data.
	 */
	public static function cover_preload(): string {
		if ( ! is_singular() ) {
			return '';
		}
		$id = (int) get_queried_object_id();
		if ( $id <= 0 ) {
			return '';
		}
		$raw  = get_post_meta( $id, '_elementor_data', true );
		$data = is_array( $raw ) ? $raw : json_decode( is_string( $raw ) ? $raw : '', true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$found = self::find_cover( $data, 0 );
		if ( null === $found ) {
			return '';
		}
		$settings = is_array( $found['settings'] ?? null ) ? $found['settings'] : [];
		$photo    = absint( $settings['photo']['id'] ?? 0 );
		$sizes    = is_string( $settings['photo_sizes'] ?? null ) && '' !== trim( $settings['photo_sizes'] )
			? trim( $settings['photo_sizes'] )
			: (string) apply_filters( 'nh_cover_preload_sizes', self::COVERS[ $found['widgetType'] ], $found['widgetType'] );
		return $photo > 0 ? NH_Picture::preload( $photo, $sizes ) : '';
	}

	/**
	 * Elementor page setting `nh_calm_default` of the queried post.
	 */
	public static function calm_default(): bool {
		$id = is_singular() ? (int) get_queried_object_id() : 0; // elsewhere the queried object is not a post
		if ( $id <= 0 ) {
			return false;
		}
		$s = get_post_meta( $id, '_elementor_page_settings', true );
		return is_array( $s ) && 'yes' === ( $s['nh_calm_default'] ?? '' );
	}

	/**
	 * window.nhData (SPEC §5.6).
	 */
	public static function js_data(): array {
		$msg = static function ( string $key ): string {
			return trim( (string) NH_Settings::get( $key ) );
		};
		return [
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'post'     => admin_url( 'admin-post.php' ),
			'visitUrl' => NH_Settings::visit_url(),
			'consent'  => [
				'key' => 'nh-consent-v1',
				'v'   => max( 1, absint( NH_Settings::get( 'consent_version' ) ) ),
			],
			'i18n'     => [
				'plainOn'    => __( 'הדף מוצג עכשיו בשפה פשוטה.', 'nevehairus-site' ),
				'plainOff'   => __( 'הדף מוצג עכשיו בקריאה רגילה.', 'nevehairus-site' ),
				// v3.2 (⊕, to be shown to the client): a page without a plain-language version does not change
				'plainNone'  => __( 'שפה פשוטה פועלת. לדף הזה אין עדיין נוסח בשפה פשוטה.', 'nevehairus-site' ),
				'calmOn'     => __( 'מצב רגוע פעיל: בלי תנועה ועם צבעים רכים יותר.', 'nevehairus-site' ),
				'calmOff'    => __( 'מצב רגוע כבוי.', 'nevehairus-site' ),
				'videoTitle' => __( 'סרטון', 'nevehairus-site' ),
			],
			'forms'    => [
				'msg' => [
					'required'    => $msg( 'msg_required' ),
					'email'       => $msg( 'msg_email' ),
					'tel'         => $msg( 'msg_tel' ),
					'consent'     => $msg( 'msg_consent' ),
					'fileSize'    => $msg( 'msg_file_size' ),
					'fileType'    => $msg( 'msg_file_type' ),
					'summary'     => $msg( 'msg_summary' ),
					'summaryOne'  => $msg( 'msg_summary_one' ),
					'summaryMany' => $msg( 'msg_summary_many' ),
					'expired'     => NH_Settings::text( 'msg_expired' ),
					'limited'     => NH_Settings::text( 'msg_limited' ),
					'off'         => NH_Settings::text( 'msg_off' ),
					'error'       => NH_Settings::text( 'msg_error' ),
				],
			],
		];
	}

	/**
	 * Inside the Elementor editor preview.
	 */
	public static function is_preview(): bool {
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && is_object( \Elementor\Plugin::$instance->preview )
			&& method_exists( \Elementor\Plugin::$instance->preview, 'is_preview_mode' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return true;
		}
		return isset( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag
	}

	/**
	 * `elementor/documents/register_controls`: page setting "calm mode by default" on page/post documents.
	 *
	 * @param object $document Elementor document.
	 */
	public static function page_settings( $document ): void {
		if ( ! is_object( $document ) || ! is_a( $document, 'Elementor\Core\DocumentTypes\PageBase' ) ) {
			return;
		}
		$document->start_controls_section(
			'nh_page',
			[
				'label' => __( 'נווה האירוס', 'nevehairus-site' ),
				'tab'   => 'settings',
			]
		);
		$document->add_control(
			'nh_calm_default',
			[
				'label'        => __( 'מצב רגוע כברירת מחדל בעמוד הזה', 'nevehairus-site' ),
				'type'         => 'switcher',
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'מבקרים שלא בחרו בעצמם יראו את העמוד בלי תנועה ועם צבעים רכים. הבחירה של המבקר קודמת.', 'nevehairus-site' ),
			]
		);
		$document->end_controls_section();
	}

	/**
	 * `template_redirect`: a page that shows a form state (after a submission without JS) is never cached.
	 */
	public static function no_cache_for_form_states(): void {
		if ( ! isset( $_GET['nh_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag
			return;
		}
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/**
	 * Depth-first search for the first cover widget in Elementor's element tree.
	 *
	 * @param array $elements Elements.
	 * @param int   $depth    Recursion depth (guard).
	 */
	private static function find_cover( array $elements, int $depth ): ?array {
		if ( $depth > 32 ) {
			return null;
		}
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$type = $el['widgetType'] ?? '';
			if ( is_string( $type ) && isset( self::COVERS[ $type ] ) ) {
				return $el;
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$found = self::find_cover( $el['elements'], $depth + 1 );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}
}
