<?php
/**
 * A fake Elementor for the stub harness (SPEC §11.2): exactly the API subset widgets may use (§2.2).
 *
 * Widget_Base stores the controls of register_controls(), builds the settings from the control
 * defaults + `$data['settings']` (array_replace_recursive; repeater items get `_id` and the field
 * defaults) and returns them like Elementor's get_settings_for_display(): controls whose `condition`
 * is not met read as null. Calling anything outside the subset is a fatal error, on purpose.
 *
 * @package nevehairus-site
 */

namespace Elementor;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
	define( 'ELEMENTOR_VERSION', '4.4.0' );
}

/**
 * Control types and tabs (SPEC §2.2).
 */
class Controls_Manager {
	const TAB_CONTENT = 'content';
	const TEXT        = 'text';
	const TEXTAREA    = 'textarea';
	const NUMBER      = 'number';
	const SELECT      = 'select';
	const SWITCHER    = 'switcher';
	const MEDIA       = 'media';
	const URL         = 'url';
	const REPEATER    = 'repeater';
	const HEADING     = 'heading';
	const RAW_HTML    = 'raw_html';
	const NOTICE      = 'notice';
	const HIDDEN      = 'hidden';

	/** Types that hold no value. */
	const UI = [ 'heading', 'raw_html', 'notice' ];

	/** Every allowed type. */
	const ALLOWED = [ 'text', 'textarea', 'number', 'select', 'switcher', 'media', 'url', 'repeater', 'heading', 'raw_html', 'notice', 'hidden' ];
}

/**
 * Shared control registry of Widget_Base and Repeater.
 */
trait NH_Test_Controls {

	/** @var array<string,array> */
	private $nh_controls = [];

	/**
	 * Validates and stores a control.
	 */
	private function nh_store_control( string $id, array $args, bool $in_repeater ): void {
		if ( ! preg_match( '/^[a-z_][a-z0-9_]*$/', $id ) ) {
			throw new \LogicException( "control id \"$id\": lowercase letters, digits and _ only" );
		}
		if ( isset( $this->nh_controls[ $id ] ) ) {
			throw new \LogicException( "control \"$id\" is added twice" );
		}
		$type = $args['type'] ?? '';
		if ( ! in_array( $type, Controls_Manager::ALLOWED, true ) ) {
			throw new \LogicException( "control \"$id\": type \"$type\" is not in the allowed subset (SPEC §2.2)" );
		}
		if ( isset( $args['selectors'] ) || isset( $args['selector'] ) ) {
			throw new \LogicException( "control \"$id\": style controls that write CSS are not allowed (SPEC §2.2)" );
		}
		if ( isset( $args['dynamic'] ) ) {
			throw new \LogicException( "control \"$id\": dynamic tags are not allowed (SPEC §2.2)" );
		}
		if ( 'url' === $type && ( ! array_key_exists( 'options', $args ) || false !== $args['options'] ) ) {
			throw new \LogicException( "control \"$id\": URL controls are registered with 'options' => false (SPEC §2.2)" );
		}
		if ( 'repeater' === $type && $in_repeater ) {
			throw new \LogicException( "control \"$id\": nested repeaters are not supported" );
		}
		$args['name']               = $id;
		$this->nh_controls[ $id ] = $args;
	}
}

/**
 * Repeater fields.
 */
class Repeater {
	use NH_Test_Controls;

	public function add_control( $id, array $args, $options = [] ) {
		$this->nh_store_control( (string) $id, $args, true );
		return true;
	}

	public function add_responsive_control( $id, array $args, $options = [] ) {
		return $this->add_control( $id, $args, $options );
	}

	/**
	 * The fields, keyed by name (as Elementor's Repeater::get_controls()).
	 */
	public function get_controls() {
		return $this->nh_controls;
	}
}

/**
 * The widget base (signatures as Elementor 4.4.0, SPEC §2.2).
 */
abstract class Widget_Base {
	use NH_Test_Controls;

	/** @var array */
	private $nh_data;

	/** @var array<string,array> section id => args */
	private $nh_sections = [];

	/** @var string|null */
	private $nh_open_section = null;

	/** @var bool */
	private $nh_registered = false;

	public function __construct( $data = [], $args = null ) {
		$this->nh_data = is_array( $data ) ? $data : [];
	}

	abstract public function get_name();

	public function get_title() {
		return '';
	}

	public function get_icon() {
		return 'eicon-apps';
	}

	public function get_categories() {
		return [ 'basic' ];
	}

	public function get_keywords() {
		return [];
	}

	public function get_style_depends() {
		return [];
	}

	public function get_script_depends() {
		return [];
	}

	public function has_widget_inner_wrapper(): bool {
		return true;
	}

	protected function is_dynamic_content(): bool {
		return true;
	}

	protected function register_controls() {
	}

	protected function render() {
	}

	public function get_id() {
		return (string) ( $this->nh_data['id'] ?? '' );
	}

	public function start_controls_section( $section_id, array $args = [] ) {
		if ( null !== $this->nh_open_section ) {
			throw new \LogicException( "section \"$section_id\" opened while \"{$this->nh_open_section}\" is still open" );
		}
		if ( isset( $this->nh_sections[ $section_id ] ) ) {
			throw new \LogicException( "section \"$section_id\" is added twice" );
		}
		if ( isset( $args['tab'] ) && Controls_Manager::TAB_CONTENT !== $args['tab'] ) {
			throw new \LogicException( "section \"$section_id\": content tab only (SPEC §4.0)" );
		}
		$this->nh_sections[ $section_id ] = $args + [ 'controls' => [] ];
		$this->nh_open_section            = (string) $section_id;
	}

	public function end_controls_section() {
		if ( null === $this->nh_open_section ) {
			throw new \LogicException( 'end_controls_section() without an open section' );
		}
		$this->nh_open_section = null;
	}

	public function add_control( $id, array $args, $options = [] ) {
		if ( null === $this->nh_open_section ) {
			throw new \LogicException( "control \"$id\" added outside a controls section" );
		}
		$this->nh_store_control( (string) $id, $args, false );
		$this->nh_sections[ $this->nh_open_section ]['controls'][] = (string) $id;
		return true;
	}

	public function add_responsive_control( $id, array $args, $options = [] ) {
		return $this->add_control( $id, $args, $options );
	}

	/**
	 * The registered controls (all, or one).
	 */
	public function get_controls( $control_id = null ) {
		$this->nh_register();
		return null === $control_id ? $this->nh_controls : ( $this->nh_controls[ $control_id ] ?? null );
	}

	/**
	 * Settings as Elementor's get_settings_for_display(): defaults + saved settings, inactive controls null.
	 */
	public function get_settings_for_display( $setting_key = null ) {
		$this->nh_register();
		$settings = $this->nh_active( $this->nh_controls, $this->nh_settings() );
		return null === $setting_key ? $settings : ( $settings[ $setting_key ] ?? null );
	}

	/**
	 * Harness only: render() into a string.
	 */
	final public function nh_test_render(): string {
		$this->nh_register();
		ob_start();
		try {
			$this->render();
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}

	/**
	 * Harness only: the sections and their control ids, in order.
	 */
	final public function nh_test_sections(): array {
		$this->nh_register();
		return $this->nh_sections;
	}

	/**
	 * Harness only: is_dynamic_content() from outside.
	 */
	final public function nh_test_is_dynamic(): bool {
		return $this->is_dynamic_content();
	}

	private function nh_register(): void {
		if ( $this->nh_registered ) {
			return;
		}
		$this->nh_registered = true;
		$this->register_controls();
		if ( null !== $this->nh_open_section ) {
			throw new \LogicException( "section \"{$this->nh_open_section}\" is never closed" );
		}
	}

	/**
	 * Defaults + saved settings.
	 */
	private function nh_settings(): array {
		$defaults = [];
		foreach ( $this->nh_controls as $id => $c ) {
			if ( in_array( $c['type'], Controls_Manager::UI, true ) ) {
				continue;
			}
			$defaults[ $id ] = $c['default'] ?? self::nh_type_default( $c['type'] );
		}
		$saved    = is_array( $this->nh_data['settings'] ?? null ) ? $this->nh_data['settings'] : [];
		$settings = array_replace_recursive( $defaults, $saved );

		// Repeater items: field defaults for missing keys, and an _id.
		foreach ( $this->nh_controls as $id => $c ) {
			if ( 'repeater' !== $c['type'] ) {
				continue;
			}
			$items = is_array( $settings[ $id ] ?? null ) ? array_values( $settings[ $id ] ) : [];
			foreach ( $items as $n => $item ) {
				$item = is_array( $item ) ? $item : [];
				foreach ( (array) ( $c['fields'] ?? [] ) as $fid => $f ) {
					$fid = (string) ( $f['name'] ?? $fid );
					if ( ! array_key_exists( $fid, $item ) && ! in_array( $f['type'] ?? '', Controls_Manager::UI, true ) ) {
						$item[ $fid ] = $f['default'] ?? self::nh_type_default( (string) ( $f['type'] ?? '' ) );
					} elseif ( isset( $item[ $fid ] ) && is_array( $item[ $fid ] ) && is_array( $f['default'] ?? null ) ) {
						$item[ $fid ] = array_replace( $f['default'], $item[ $fid ] );
					}
				}
				$item['_id']   = $item['_id'] ?? substr( md5( $this->get_id() . "/$id/$n" ), 0, 7 );
				$items[ $n ] = $item;
			}
			$settings[ $id ] = $items;
		}
		return $settings;
	}

	/**
	 * Controls whose condition is not met read as null (Elementor's get_active_settings()).
	 */
	private function nh_active( array $controls, array $settings ): array {
		foreach ( $controls as $id => $c ) {
			if ( ! array_key_exists( $id, $settings ) ) {
				continue;
			}
			if ( ! self::nh_condition_met( (array) ( $c['condition'] ?? [] ), $settings ) ) {
				$settings[ $id ] = null;
				continue;
			}
			if ( 'repeater' === $c['type'] && is_array( $settings[ $id ] ) ) {
				foreach ( $settings[ $id ] as $n => $item ) {
					$settings[ $id ][ $n ] = $this->nh_active( (array) ( $c['fields'] ?? [] ), (array) $item );
				}
			}
		}
		return $settings;
	}

	/**
	 * Elementor's simple conditions: [ 'key' => v, 'key!' => v, 'key[sub]' => v ], v scalar or list.
	 */
	private static function nh_condition_met( array $condition, array $settings ): bool {
		foreach ( $condition as $key => $want ) {
			$not = '!' === substr( (string) $key, -1 );
			$key = rtrim( (string) $key, '!' );
			$sub = null;
			if ( preg_match( '/^([a-z0-9_]+)\[([a-z0-9_]+)\]$/', $key, $m ) ) {
				$key = $m[1];
				$sub = $m[2];
			}
			$value = $settings[ $key ] ?? null;
			if ( null !== $sub ) {
				$value = is_array( $value ) ? ( $value[ $sub ] ?? null ) : null;
			}
			$hit = is_array( $want ) ? in_array( $value, $want, false ) : ( (string) $value === (string) $want ); // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse
			if ( $hit === $not ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Elementor's default value of a control type without a default.
	 */
	private static function nh_type_default( string $type ) {
		switch ( $type ) {
			case 'media':
				return [
					'url' => '',
					'id'  => '',
				];
			case 'url':
				return [
					'url'         => '',
					'is_external' => '',
					'nofollow'    => '',
				];
			case 'repeater':
				return [];
		}
		return '';
	}
}

/**
 * Placeholder images (never used by our widgets: images have no placeholder).
 */
class Utils {
	public static function get_placeholder_image_src() {
		return '';
	}
}

/**
 * Category recorder.
 */
class Elements_Manager {
	/** @var array */
	public $categories = [];

	public function add_category( $name, $args ) {
		$this->categories[ $name ] = $args;
	}
}

/**
 * Widget recorder.
 */
class Widgets_Manager {
	/** @var Widget_Base[] */
	public $widgets = [];

	public function register( $widget ) {
		$this->widgets[ $widget->get_name() ] = $widget;
		return true;
	}
}

/**
 * Elementor's singleton: not in the editor, not in the preview (switch with nh_test_preview()); the files
 * manager counts its clear_cache() calls in $GLOBALS['nh_test']['elementor_cache_cleared'].
 */
class Plugin {
	/** @var Plugin */
	public static $instance;

	/** @var object */
	public $editor;

	/** @var object */
	public $preview;

	/** @var object Core\Files\Manager (4.4.0: public property, clear_cache() public). */
	public $files_manager;

	public function __construct() {
		$this->files_manager = new class() {
			public function clear_cache() {
				$GLOBALS['nh_test']['elementor_cache_cleared'] = ( $GLOBALS['nh_test']['elementor_cache_cleared'] ?? 0 ) + 1;
			}
		};
		$this->editor  = new class() {
			public $edit = false;

			public function is_edit_mode() {
				return $this->edit;
			}
		};
		$this->preview = new class() {
			public $preview = false;

			public function is_preview_mode() {
				return $this->preview;
			}
		};
	}
}

Plugin::$instance = new Plugin();
