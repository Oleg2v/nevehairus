<?php
defined( 'ABSPATH' ) || exit;

/**
 * Boot, widget discovery, the Elementor category and widget registration (SPEC §3.1).
 *
 * Widgets are discovered from the file system: every `widgets/<id>.php` that declares
 * `NH_Widget_<Id>` (extends NH_Widget_Base, `ID === '<id>'`) is registered. Nothing else is a registry.
 *
 * @package nevehairus-site
 */
final class NH_Plugin {

	/** Elementor category of every widget of this plugin. */
	public const CATEGORY = 'nevehairus';

	/** @var bool */
	private static $booted = false;

	/** @var array<string,string>|null id => class, after discovery. */
	private static $widgets = null;

	/** @var array<string,string> id => reason, for widget files that were not registered. */
	private static $skipped = [];

	/**
	 * Hooks everything (plugins_loaded). Idempotent.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', [ __CLASS__, 'load_textdomain' ] );

		NH_Settings::init();
		NH_Site::init();
		NH_Forms::init();
		NH_Compat::init();
		NH_Assets::init();
		if ( is_admin() ) {
			NH_Settings_Page::init();
		}

		if ( self::elementor_ok() ) {
			add_action( 'elementor/elements/categories_registered', [ __CLASS__, 'register_category' ] );
			add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widgets' ] );
		} else {
			add_action( 'admin_notices', [ __CLASS__, 'elementor_notice' ] );
		}
	}

	/**
	 * `load_plugin_textdomain()` on init (the site is Hebrew; the folder is kept for later translations).
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'nevehairus-site', false, dirname( plugin_basename( NH_FILE ) ) . '/languages' );
	}

	/**
	 * Elementor loaded and recent enough.
	 */
	public static function elementor_ok(): bool {
		return did_action( 'elementor/loaded' ) > 0
			&& defined( 'ELEMENTOR_VERSION' )
			&& version_compare( (string) ELEMENTOR_VERSION, NH_MIN_ELEMENTOR, '>=' );
	}

	/**
	 * Widget ids from `widgets/*.php`, without loading the files (for NH_Assets). Sorted.
	 *
	 * @return string[]
	 */
	public static function widget_ids_on_disk(): array {
		$ids   = [];
		$files = glob( NH_DIR . 'widgets/*.php' );
		foreach ( is_array( $files ) ? $files : [] as $file ) {
			$id = basename( $file, '.php' );
			if ( '' === $id || '_' === $id[0] || ! preg_match( '/^[a-z][a-z0-9-]*$/', $id ) ) {
				continue;
			}
			$ids[] = $id;
		}
		sort( $ids, SORT_STRING );
		return $ids;
	}

	/**
	 * Discovered widgets: `[ 'cover' => 'NH_Widget_Cover', … ]`. Requires the widget files (and the
	 * widget base class) on first call; returns [] when Elementor's Widget_Base is not available.
	 *
	 * @return array<string,string>
	 */
	public static function widgets(): array {
		if ( null !== self::$widgets ) {
			return self::$widgets;
		}
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return [];
		}
		require_once NH_DIR . 'includes/class-nh-widget-base.php';

		self::$widgets = [];
		foreach ( self::widget_ids_on_disk() as $id ) {
			$class = self::class_name( $id );
			try {
				require_once NH_DIR . 'widgets/' . $id . '.php';
			} catch ( \Throwable $e ) {
				// A broken widget file (parse error, missing class) must not take the site down.
				self::skip( $id, $class, sprintf( 'the file could not be loaded: %s', $e->getMessage() ) );
				continue;
			}
			if ( ! class_exists( $class, false ) ) {
				self::skip( $id, $class, 'the class is missing' );
				continue;
			}
			if ( ! is_subclass_of( $class, 'NH_Widget_Base' ) ) {
				self::skip( $id, $class, 'the class does not extend NH_Widget_Base' );
				continue;
			}
			if ( constant( $class . '::ID' ) !== $id ) {
				self::skip( $id, $class, 'the ID constant does not match the file name' );
				continue;
			}
			self::$widgets[ $id ] = $class;
		}
		return self::$widgets;
	}

	/**
	 * Widget files that were found but not registered, with the reason (for the readiness list and tests).
	 *
	 * @return array<string,string>
	 */
	public static function skipped(): array {
		return self::$skipped;
	}

	/**
	 * Class name of a widget id: `page-cover` → `NH_Widget_Page_Cover`.
	 */
	public static function class_name( string $id ): string {
		return 'NH_Widget_' . str_replace( ' ', '_', ucwords( str_replace( '-', ' ', $id ) ) );
	}

	/**
	 * `elementor/elements/categories_registered`.
	 *
	 * @param object $elements_manager \Elementor\Elements_Manager.
	 */
	public static function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			self::CATEGORY,
			[
				'title' => __( 'נווה האירוס', 'nevehairus-site' ),
				'icon'  => 'eicon-apps',
			]
		);
	}

	/**
	 * `elementor/widgets/register`: one instance per discovered class.
	 *
	 * @param object $widgets_manager \Elementor\Widgets_Manager.
	 */
	public static function register_widgets( $widgets_manager ): void {
		foreach ( self::widgets() as $class ) {
			$widgets_manager->register( new $class() );
		}
	}

	/**
	 * Admin notice when Elementor is missing or too old: no widgets, the site module still works.
	 */
	public static function elementor_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$text = sprintf(
			/* translators: %s: minimal Elementor version */
			__( 'התוסף „נווה האירוס — אתר” צריך את Elementor בגרסה %s ומעלה. הווידג׳טים לא נטענו; שאר חלקי האתר (הגדרות, טפסים, עוגיות) פעילים.', 'nevehairus-site' ),
			NH_MIN_ELEMENTOR
		);
		echo '<div class="notice notice-warning"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Remembers why a widget file was skipped and, with WP_DEBUG, reports it.
	 */
	private static function skip( string $id, string $class_name, string $reason ): void {
		self::$skipped[ $id ] = $reason;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( '_doing_it_wrong' ) ) {
			_doing_it_wrong(
				__CLASS__ . '::widgets',
				esc_html( sprintf( 'Widget "%1$s" (%2$s) was not registered: %3$s.', $id, $class_name, $reason ) ),
				'0.1.0'
			);
		}
	}
}
