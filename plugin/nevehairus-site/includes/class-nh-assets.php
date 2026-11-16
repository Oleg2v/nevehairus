<?php
defined( 'ABSPATH' ) || exit;

/**
 * Style and script handles (SPEC §2.4, §3.2; research/07 §12.1).
 *
 * Core handles are fixed; widget handles are registered by convention: every
 * `assets/css/widgets/<id>.css` becomes style `nh-w-<id>` and every `assets/js/widgets/<id>.js` script
 * `nh-w-<id>`. Every handle is registered on every front-end page, but nothing is enqueued on a page that
 * does not use our widgets: the widgets name nh-base and nh-core in get_style_depends() /
 * get_script_depends(), Elementor stores those in the document's `_elementor_page_assets` and enqueues
 * them only where a widget is; enqueue_site() puts tokens, base CSS and nh-core in the `<head>` early,
 * but only when NH_Site::uses_widgets().
 *
 * @package nevehairus-site
 */
final class NH_Assets {

	/**
	 * Hooks registration on the front end (Elementor's own handles are registered by then).
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 20 );
	}

	/**
	 * `wp_enqueue_scripts` @20: registration always; the site-wide part only on pages that use our widgets
	 * (in the requested post or in the Theme Builder header/footer shown on it), so the core CSS is printed
	 * in the `<head>` there and nowhere else.
	 */
	public static function enqueue(): void {
		self::register();
		if ( NH_Site::uses_widgets() ) {
			self::enqueue_site();
		}
	}

	/**
	 * Registers the core handles and one handle per widget CSS/JS file found.
	 */
	public static function register(): void {
		$base_deps = [ 'nh-tokens' ];
		if ( wp_style_is( 'elementor-frontend', 'registered' ) ) {
			$base_deps[] = 'elementor-frontend'; // our CSS prints after Elementor's (SPEC §10.2)
		}
		self::style( 'nh-tokens', 'assets/css/tokens.css', [] );
		self::style( 'nh-base', 'assets/css/base.css', $base_deps );
		self::style( 'nh-letters', 'assets/css/letters.css', [ 'nh-base' ] );
		self::style( 'nh-forms', 'assets/css/forms.css', [ 'nh-base' ] );
		self::script( 'nh-core', 'assets/js/nh-core.js', [] );
		// window.nhData is attached to nh-core itself: it is printed only together with the script.
		wp_add_inline_script(
			'nh-core',
			'window.nhData=' . wp_json_encode( NH_Site::js_data(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
			'before'
		);
		self::script( 'nh-forms', 'assets/js/nh-forms.js', [ 'nh-core' ] );

		foreach ( self::files( 'assets/css/widgets', 'css' ) as $id ) {
			self::style( 'nh-w-' . $id, 'assets/css/widgets/' . $id . '.css', [ 'nh-base' ] );
		}
		foreach ( self::files( 'assets/js/widgets', 'js' ) as $id ) {
			self::script( 'nh-w-' . $id, 'assets/js/widgets/' . $id . '.js', [ 'nh-core' ] );
		}
	}

	/**
	 * The core part, front end only: tokens, base CSS and nh-core (with window.nhData). Called by enqueue()
	 * only when NH_Site::uses_widgets().
	 */
	public static function enqueue_site(): void {
		if ( is_admin() ) {
			return;
		}
		wp_enqueue_style( 'nh-tokens' );
		wp_enqueue_style( 'nh-base' );
		wp_enqueue_script( 'nh-core' );
	}

	/**
	 * Plugin URL of a file: NH_URL . $rel.
	 */
	public static function url( string $rel ): string {
		return NH_URL . ltrim( $rel, '/' );
	}

	/**
	 * Asset version: NH_VERSION, or the file's mtime when WP_DEBUG.
	 */
	public static function ver( string $rel ): string {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$file = NH_DIR . ltrim( $rel, '/' );
			if ( is_readable( $file ) ) {
				return (string) filemtime( $file );
			}
		}
		return NH_VERSION;
	}

	/**
	 * `nh-w-<id>` when `assets/css/widgets/<id>.css` exists, else null.
	 */
	public static function widget_style( string $id ): ?string {
		return self::valid_id( $id ) && is_file( NH_DIR . 'assets/css/widgets/' . $id . '.css' ) ? 'nh-w-' . $id : null;
	}

	/**
	 * `nh-w-<id>` when `assets/js/widgets/<id>.js` exists, else null.
	 */
	public static function widget_script( string $id ): ?string {
		return self::valid_id( $id ) && is_file( NH_DIR . 'assets/js/widgets/' . $id . '.js' ) ? 'nh-w-' . $id : null;
	}

	/**
	 * Registers one style.
	 */
	private static function style( string $handle, string $rel, array $deps ): void {
		wp_register_style( $handle, self::url( $rel ), $deps, self::ver( $rel ) );
	}

	/**
	 * Registers one deferred footer script (SPEC §2.4).
	 */
	private static function script( string $handle, string $rel, array $deps ): void {
		wp_register_script(
			$handle,
			self::url( $rel ),
			$deps,
			self::ver( $rel ),
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);
	}

	/**
	 * Widget ids that have a file with this extension in this folder. Sorted.
	 *
	 * @return string[]
	 */
	private static function files( string $dir, string $ext ): array {
		$ids   = [];
		$found = glob( NH_DIR . $dir . '/*.' . $ext );
		foreach ( is_array( $found ) ? $found : [] as $file ) {
			$id = basename( $file, '.' . $ext );
			if ( self::valid_id( $id ) ) {
				$ids[] = $id;
			}
		}
		sort( $ids, SORT_STRING );
		return $ids;
	}

	/**
	 * Widget id syntax (SPEC §2.1).
	 */
	private static function valid_id( string $id ): bool {
		return (bool) preg_match( '/^[a-z][a-z0-9-]*$/', $id );
	}
}
