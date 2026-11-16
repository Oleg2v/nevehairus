<?php
defined( 'ABSPATH' ) || exit;

/**
 * Base of every widget of the plugin (SPEC §3.3). Loaded only when Elementor is available
 * (NH_Plugin::widgets()), because it extends \Elementor\Widget_Base.
 *
 * A child sets the constants and implements get_title(), get_icon(), register_controls() and
 * render(); widgets/cover.php is the complete reference.
 *
 * @package nevehairus-site
 */
abstract class NH_Widget_Base extends \Elementor\Widget_Base {

	/** Widget id (`widgets/<ID>.php`, Elementor name `nh-<ID>`). Set by the child. */
	public const ID = '';

	/** Extra style handles, listed before the widget's own `nh-w-<ID>`. */
	public const STYLE_DEPS = [];

	/** Extra script handles, listed before the widget's own `nh-w-<ID>`. */
	public const SCRIPT_DEPS = [];

	/** True if render() reads NH_Settings, $_GET, the current user or the current post. */
	public const DYNAMIC = false;

	/** Search keywords of the panel (the plugin's own two are added). */
	public const KEYWORDS = [];

	/**
	 * Elementor name: `nh-<ID>`.
	 */
	final public function get_name() {
		return 'nh-' . static::ID;
	}

	/**
	 * The plugin's own category.
	 */
	public function get_categories() {
		return [ NH_Plugin::CATEGORY ];
	}

	/**
	 * Panel search keywords.
	 */
	public function get_keywords() {
		return array_merge( static::KEYWORDS, [ 'nevehairus', 'נווה' ] );
	}

	/**
	 * STYLE_DEPS + `nh-w-<ID>` when the widget has a CSS file.
	 */
	public function get_style_depends() {
		$own = NH_Assets::widget_style( static::ID );
		return array_values( array_unique( array_merge( static::STYLE_DEPS, null === $own ? [] : [ $own ] ) ) );
	}

	/**
	 * SCRIPT_DEPS + `nh-w-<ID>` when the widget has a JS file.
	 */
	public function get_script_depends() {
		$own = NH_Assets::widget_script( static::ID );
		return array_values( array_unique( array_merge( static::SCRIPT_DEPS, null === $own ? [] : [ $own ] ) ) );
	}

	/**
	 * No `.elementor-widget-container` wrapper: the widget root is our own element.
	 */
	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Static widgets may be cached by Elementor's element cache; DYNAMIC ones may not.
	 */
	protected function is_dynamic_content(): bool {
		return (bool) static::DYNAMIC;
	}

	/**
	 * Element id inside the widget: `{anchor}-{suffix}`, or `nh-{element id}-{suffix}` without an anchor.
	 */
	protected function nh_uid( string $suffix ): string {
		$anchor = $this->nh_anchor( $this->get_settings_for_display() );
		$suffix = sanitize_html_class( $suffix );
		return '' !== $anchor ? $anchor . '-' . $suffix : 'nh-' . sanitize_html_class( (string) $this->get_id() ) . '-' . $suffix;
	}

	/**
	 * Opening tag of a spread (SPEC §3.3). Options: class (required), tone, header, labelledby, tag, attrs.
	 * Attribute order: class, id, data-header, data-running, data-running-plain, aria-labelledby, extra.
	 *
	 * @param array $s Settings for display.
	 * @param array $o Options.
	 */
	protected function nh_section_open( array $s, array $o ): string {
		$o    = array_merge(
			[
				'class'      => '',
				'tone'       => '',
				'header'     => null,
				'labelledby' => 'title',
				'tag'        => 'section',
				'attrs'      => [],
			],
			$o
		);
		$tone = in_array( $o['tone'], [ 'sand', 'citrus', 'iris', 'night' ], true ) ? $o['tone'] : '';

		$header = $o['header'];
		if ( ! in_array( $header, [ 'light', 'dark' ], true ) ) {
			$header = in_array( $tone, [ 'iris', 'night' ], true ) ? 'dark' : 'light';
		}

		$classes = trim( (string) $o['class'] . ( '' !== $tone ? ' v3-spread--' . $tone : '' ) );
		$anchor  = $this->nh_anchor( $s );
		$running = self::nh_str( $s, 'running' );
		$plain   = trim( self::nh_str( $s, 'running_plain' ) );

		$attrs = [
			'class'              => $classes,
			'id'                 => '' !== $anchor ? $anchor : null,
			'data-header'        => $header,
			'data-running'       => trim( $running ),
			'data-running-plain' => ( '' !== $plain && NH_Text::HIDE !== $plain ) ? $plain : null,
			'aria-labelledby'    => null === $o['labelledby'] ? null : $this->nh_uid( (string) $o['labelledby'] ),
		];
		foreach ( (array) $o['attrs'] as $k => $v ) {
			$attrs[ $k ] = $v;
		}
		return '<' . self::nh_tag( (string) $o['tag'] ) . NH_Html::attrs( $attrs ) . '>';
	}

	/**
	 * Closing tag of a spread.
	 */
	protected function nh_section_close( string $tag = 'section' ): string {
		return '</' . self::nh_tag( $tag ) . '>';
	}

	/**
	 * The ruling line every spread hangs from (`--draw`: drawn on load, the cover).
	 */
	protected function nh_rule( bool $draw = false ): string {
		return '<div class="v3-rule' . ( $draw ? ' v3-rule--draw' : '' ) . '" aria-hidden="true"></div>';
	}

	/**
	 * The spread's heading: `<h2 class="v3-h2" id="{uid title}">{pair:title + tessera}</h2>`.
	 * Options: class (default 'v3-h2'; '' = no class), prefix (control prefix, default 'title'),
	 * tag (default 'h2'), id (uid suffix, default 'title'). '' when the title pair is empty.
	 *
	 * @param array $s Settings for display.
	 * @param array $o Options.
	 */
	protected function nh_h2( array $s, array $o = [] ): string {
		$o      = array_merge(
			[
				'class'  => 'v3-h2',
				'prefix' => 'title',
				'tag'    => 'h2',
				'id'     => 'title',
			],
			$o
		);
		$prefix = (string) $o['prefix'];
		$dot    = array_key_exists( $prefix . '_dot', $s ) && null !== $s[ $prefix . '_dot' ] ? (string) $s[ $prefix . '_dot' ] : null;
		$inner  = NH_Text::pair( self::nh_str( $s, $prefix ), self::nh_str( $s, $prefix . '_plain' ), [ 'dot' => $dot ] );
		if ( '' === $inner ) {
			return '';
		}
		$tag = self::nh_tag( (string) $o['tag'] );
		return '<' . $tag . NH_Html::attrs(
			[
				'class' => '' !== (string) $o['class'] ? (string) $o['class'] : null,
				'id'    => $this->nh_uid( (string) $o['id'] ),
			]
		) . '>' . $inner . '</' . $tag . '>';
	}

	/**
	 * The spread's lead: NH_Text::block( lead, lead_plain, … ) with defaults full_tag p, full_class v3-lead,
	 * plain_tag div, plain_class '', plain_lines p. Option prefix (default 'lead') picks other controls.
	 *
	 * @param array $s Settings for display.
	 * @param array $o Options passed to NH_Text::block().
	 */
	protected function nh_lead( array $s, array $o = [] ): string {
		$prefix = isset( $o['prefix'] ) ? (string) $o['prefix'] : 'lead';
		unset( $o['prefix'] );
		$o = array_merge(
			[
				'full_tag'    => 'p',
				'full_class'  => 'v3-lead',
				'plain_tag'   => 'div',
				'plain_class' => '',
				'plain_lines' => 'p',
			],
			$o
		);
		return NH_Text::block( self::nh_str( $s, $prefix ), self::nh_str( $s, $prefix . '_plain' ), $o );
	}

	/**
	 * A note only editors see (missing settings, unresolved link tokens): '' for visitors.
	 */
	protected function nh_admin_note( string $text ): string {
		return NH_Html::admin_note( $text );
	}

	/**
	 * The sanitised anchor setting ('' when empty).
	 *
	 * @param array $s Settings for display.
	 */
	private function nh_anchor( array $s ): string {
		return sanitize_html_class( trim( self::nh_str( $s, 'anchor' ) ) );
	}

	/**
	 * A setting as a string ('' when missing, null or not scalar).
	 *
	 * @param array  $s   Settings.
	 * @param string $key Setting key.
	 */
	private static function nh_str( array $s, string $key ): string {
		return isset( $s[ $key ] ) && is_scalar( $s[ $key ] ) ? (string) $s[ $key ] : '';
	}

	/**
	 * A safe tag name (letters and digits), 'section' otherwise.
	 */
	private static function nh_tag( string $tag ): string {
		return preg_match( '/^[a-z][a-z0-9]*$/', $tag ) ? $tag : 'section';
	}
}
