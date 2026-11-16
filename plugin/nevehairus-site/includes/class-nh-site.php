<?php
defined( 'ABSPATH' ) || exit;

/**
 * Site module (SPEC §5): head (meta, font and cover preloads, the pre-paint script), body open (sprite,
 * skip link, cookie banner), footer (visit bar, global live region, parts queued by widgets), the
 * `<main>` of Elementor's full-width template and the page setting "calm mode by default".
 *
 * @package nevehairus-site
 */
final class NH_Site {

	/** Widget types whose photo is the LCP image, with their default `sizes`. */
	private const COVERS = [
		'nh-cover'      => '(min-width: 1024px) 30rem, 44vw',
		'nh-page-cover' => '(min-width: 1024px) 22rem, 44vw',
	];

	/** @var bool body_open() ran in this request. */
	private static $opened = false;

	/** @var bool footer() ran in this request. */
	private static $footed = false;

	/** @var array<string,callable> footer_once() queue. */
	private static $queue = [];

	/** @var string the final template file (template_include). */
	private static $template = '';

	/**
	 * Hooks (SPEC §5.1).
	 */
	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'head' ], 1 );
		add_filter( 'body_class', [ __CLASS__, 'body_class' ] );
		add_action( 'wp_body_open', [ __CLASS__, 'body_open' ], 5 );
		add_action( 'elementor/page_templates/header-footer/before_content', [ __CLASS__, 'main_open' ] );
		add_action( 'elementor/page_templates/header-footer/after_content', [ __CLASS__, 'main_close' ] );
		add_action( 'wp_footer', [ __CLASS__, 'footer' ], 5 );
		add_action( 'elementor/documents/register_controls', [ __CLASS__, 'page_settings' ] );
		add_action( 'template_redirect', [ __CLASS__, 'no_cache_for_form_states' ] );
		add_filter( 'template_include', [ __CLASS__, 'remember_template' ], PHP_INT_MAX );
	}

	/**
	 * `wp_head` @1: color-scheme, theme-color, font preloads, the cover photo preload, the pre-paint script.
	 */
	public static function head(): void {
		if ( is_admin() ) {
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
	 * The pre-paint script (prototype head.html L10–31): saved display choices are applied before the
	 * first paint; not inside the Elementor preview (editors see the full text); the page's calm default;
	 * the page-transition name applied only to the cover room whose data-vt was stored.
	 */
	public static function prepaint_js(): string {
		$calm = self::calm_default() ? 'true' : 'false';
		return '(function (w, d) {'
			. 'var h = d.documentElement, get = function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } };'
			. 'h.classList.add("js");'
			. 'if (/[?&]elementor-preview=/.test(w.location.search)) return;'
			. 'var cd = ' . $calm . ';'
			. 'if (get("nh-motion") === "reduce") h.classList.add("nh-reduce-motion");'
			. 'if (get("nh-calm") === "on" || (get("nh-calm") === null && cd)) h.classList.add("nh-calm");'
			. 'var q = /[?&]plain=([01])/.exec(w.location.search);'
			. 'if (q ? q[1] === "1" : get("nh-plain") === "on") h.classList.add("nh-plainmode");'
			. 'w.addEventListener("pagereveal", function (e) {'
			. 'if (!e.viewTransition) return;'
			. 'h.classList.add("nh-vt-in");'
			. 'var vt = null; try { vt = sessionStorage.getItem("nh-vt"); sessionStorage.removeItem("nh-vt"); } catch (x) { /* private mode */ }'
			. 'if (!vt) return;'
			. 'var rooms = d.querySelectorAll(".v3-cover--page .v3-room[data-vt]");'
			. 'for (var i = 0; i < rooms.length; i++) { if (rooms[i].getAttribute("data-vt") === vt) rooms[i].style.viewTransitionName = vt; }'
			. '});'
			. '})(window, document);';
	}

	/**
	 * `body_class`: the v3 page class.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		$classes   = is_array( $classes ) ? $classes : [];
		$classes[] = 'v3';
		return $classes;
	}

	/**
	 * `wp_body_open` @5: the sprite, the skip link and (not in the Elementor preview) the cookie banner,
	 * so the banner is second in Tab order (D1).
	 */
	public static function body_open(): void {
		if ( self::$opened ) {
			return;
		}
		self::$opened = true;
		$out          = self::sprite() . "\n" . self::skip_link() . "\n";
		if ( ! self::is_preview() ) {
			$out .= self::cookie_banner() . "\n";
		}
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * `wp_footer` @5: the sprite and banner if the theme never fired wp_body_open; the visit bar (if enabled,
	 * not in the preview); the global live region; parts queued with footer_once(), in queue order.
	 */
	public static function footer(): void {
		self::$footed = true;
		$out          = '';
		if ( ! self::$opened ) {
			self::$opened = true;
			$out         .= self::sprite() . "\n";
			if ( ! self::is_preview() ) {
				$out .= self::cookie_banner() . "\n";
			}
		}
		if ( ! empty( NH_Settings::get( 'show_bar' ) ) && ! self::is_preview() ) {
			$out .= self::visit_bar() . "\n";
		}
		$out .= self::live_region() . "\n";
		foreach ( self::$queue as $printer ) {
			$out .= (string) call_user_func( $printer ) . "\n";
		}
		self::$queue = [];
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core parts are escaped; queued parts are built by widgets from escaped parts
	}

	/**
	 * Queues `$printer()` (returns HTML) to be printed once in wp_footer (e.g. the gallery's lightbox).
	 * After the footer has run (late renders) the part is printed at once.
	 *
	 * @param string   $key     Part name; a second call with the same key is ignored.
	 * @param callable $printer Returns trusted HTML.
	 */
	public static function footer_once( string $key, callable $printer ): void {
		if ( isset( self::$queue[ $key ] ) ) {
			return;
		}
		if ( self::$footed ) {
			echo (string) call_user_func( $printer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the widget from escaped parts
			self::$queue[ $key ] = '__return_empty_string';
			return;
		}
		self::$queue[ $key ] = $printer;
	}

	/**
	 * `elementor/page_templates/header-footer/before_content`: Elementor's full-width template has no
	 * `<main>` (D2).
	 */
	public static function main_open(): void {
		echo '<main id="main" class="nh-main" tabindex="-1">';
	}

	/**
	 * `elementor/page_templates/header-footer/after_content`.
	 */
	public static function main_close(): void {
		echo '</main>';
	}

	/**
	 * The SVG sprite: the nine symbols v3 uses (prototype partials/sprite.html, paths verbatim).
	 */
	public static function sprite(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false"><defs>'
			. '<symbol id="i-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></symbol>'
			. '<symbol id="i-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M10 17h10"/></symbol>'
			. '<symbol id="i-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></symbol>'
			. '<symbol id="i-arrow-fwd" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></symbol>'
			. '<symbol id="i-arrow-back" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>'
			. '<symbol id="i-play" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></symbol>'
			. '<symbol id="i-expand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M10 20H4v-6M20 4l-7 7M4 20l7-7"/></symbol>'
			. '<symbol id="i-speech" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/></symbol>'
			. '<symbol id="i-sliders" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/></symbol>'
			. '</defs></svg>';
	}

	/**
	 * The skip link: to `#main` under Elementor's full-width template (main_open() prints it), else to
	 * `#content` (Hello's main). nh-core.js repairs it if the target is missing.
	 */
	public static function skip_link(): string {
		$label = trim( (string) NH_Settings::get( 'skip_label' ) );
		if ( '' === $label ) {
			return '';
		}
		$target = self::is_full_width_template() ? '#main' : '#content';
		return '<a class="nh-skip" href="' . esc_attr( $target ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * The cookie banner (prototype partials/cookies.html) with the texts from the settings, hidden until
	 * nh-core.js shows it. Without a statistics text the statistics row is not rendered.
	 */
	public static function cookie_banner(): string {
		$t = static function ( string $key ): string {
			return esc_html( trim( (string) NH_Settings::get( $key ) ) );
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

		return '<section class="nh-cookies" id="nh-cookies" aria-labelledby="nh-cookies-title" hidden>'
			. '<h2 id="nh-cookies-title" tabindex="-1">' . $t( 'cookie_title' ) . '</h2>'
			. '<button class="nh-linkbtn nh-cookies__more" type="button" aria-expanded="false" aria-controls="nh-cookies-text" data-cookie-more>' . $t( 'cookie_more' ) . '</button>'
			. '<div class="nh-cookies__text" id="nh-cookies-text">' . $text . '</div>'
			. '<div class="nh-cookies__prefs" id="nh-cookie-prefs" hidden>' . $rows
			. '<div><button class="nh-btn nh-btn--primary" type="button" data-consent="save">' . $t( 'cookie_save' ) . '</button></div></div>'
			. '<div class="nh-btn-row">'
			. '<button class="nh-btn nh-btn--primary" type="button" data-consent="all">' . $t( 'cookie_all' ) . '</button>'
			. '<button class="nh-btn nh-btn--ghost" type="button" data-consent="necessary">' . $t( 'cookie_necessary_only' ) . '</button>'
			. '<button class="nh-btn nh-btn--ghost" type="button" data-consent="settings" aria-expanded="false" aria-controls="nh-cookie-prefs">' . $t( 'cookie_settings' ) . '</button>'
			. '</div></section>';
	}

	/**
	 * The mobile visit bar (prototype footer.html L60–63).
	 */
	public static function visit_bar(): string {
		$phone = NH_Settings::phone();
		$cta   = NH_Text::pair( (string) NH_Settings::get( 'bar_cta' ), (string) NH_Settings::get( 'bar_cta_plain' ) );
		$out   = '<aside class="v2-bar v3-bar" data-bar aria-label="' . esc_attr( trim( (string) NH_Settings::get( 'bar_label' ) ) ) . '">';
		if ( '' !== $cta ) {
			$out .= '<a class="nh-btn nh-btn--primary" href="' . esc_url( NH_Settings::visit_url() ) . '" data-bar-cta>' . $cta
				. NH_Html::icon( 'arrow-fwd', 'nh-btn__icon nh-btn__icon--fwd' ) . '</a>';
		}
		if ( '' !== $phone['tel'] ) {
			$label = trim( trim( (string) NH_Settings::get( 'bar_call_label' ) ) . ' ' . $phone['display'] );
			$out  .= "\n" . '<a class="nh-btn nh-btn--ghost" href="' . esc_url( NH_Html::tel( $phone['tel'] ) ) . '" aria-label="' . esc_attr( $label ) . '">'
				. NH_Html::icon( 'phone', 'nh-btn__icon' ) . '</a>';
		}
		return $out . '</aside>';
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
		$id = (int) get_queried_object_id();
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
				'calmOn'     => __( 'מצב רגוע פעיל: בלי תנועה ועם צבעים רכים יותר.', 'nevehairus-site' ),
				'calmOff'    => __( 'מצב רגוע כבוי.', 'nevehairus-site' ),
				'motionOn'   => __( 'התנועה באתר מופחתת.', 'nevehairus-site' ),
				'motionOff'  => __( 'התנועה באתר חזרה.', 'nevehairus-site' ),
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
	 * `template_include` @PHP_INT_MAX: remembers the final template (decides the skip-link target).
	 *
	 * @param string $template Template file.
	 * @return string The same template.
	 */
	public static function remember_template( $template ) {
		self::$template = is_string( $template ) ? $template : '';
		return $template;
	}

	/**
	 * The final template is Elementor's "Full Width" (header-footer.php), where main_open() prints main#main.
	 */
	private static function is_full_width_template(): bool {
		$t = str_replace( '\\', '/', self::$template );
		return '' !== $t && false !== strpos( $t, 'page-templates/templates/header-footer.php' );
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
