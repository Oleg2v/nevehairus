<?php
/**
 * Widget `plainbox` — the plain-language box («בשפה פשוטה»).
 *
 * Prototype: prototype/src/index.html L100–122 (home page, `section#plain`) and
 * prototype/src/program-main-home.html L122–143 (house page, `section#plain-page`).
 * Spec: plugin/SPEC.md §4.4.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * The box whose button switches the whole page to plain language.
 *
 * The button is an action button (`data-plain-toggle="action"`): its label says what it will do
 * and changes with the mode, so it has no `aria-pressed`. nh-core.js handles the click by
 * delegation; this widget has no script of its own.
 */
final class NH_Widget_Plainbox extends NH_Widget_Base {

	public const ID = 'plainbox';

	public const KEYWORDS = [ 'plain', 'plain language', 'שפה פשוטה', 'פשוטה', 'תיבה' ];

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'בשפה פשוטה · תיבה', 'nevehairus-site' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-info-box';
	}

	/**
	 * Controls: תוכן (variant, title, list, button), then מקטע.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'nh_content',
			[
				'label' => __( 'תוכן', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'variant',
			[
				'label'   => __( 'מיקום התיבה', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'home' => __( 'דף הבית', 'nevehairus-site' ),
					'page' => __( 'עמוד פנימי (ריווח גם מעל התיבה)', 'nevehairus-site' ),
				],
				'default' => 'home',
			]
		);

		NH_Controls::pair(
			$this,
			'title',
			[
				'label'   => __( 'כותרת', 'nevehairus-site' ),
				'default' => 'בשפה פשוטה',
				'plain'   => '',
			]
		);

		NH_Controls::pair(
			$this,
			'items',
			[
				'label'   => __( 'רשימה (כל שורה = פריט ברשימה)', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 6,
				'default' => "נווה האירוס הוא בית.\nגרים בו אנשים עם מוגבלות שכלית התפתחותית.\nיש כאן צוות שעוזר בכל יום.\nיש חדרים, אוכל, עבודה, חוגים וטיולים.\nמשפחות וחברים יכולים לבוא לבקר.",
				'plain'   => 'החלקים החשובים בדף כתובים עכשיו בשפה פשוטה.',
			]
		);

		NH_Controls::pair(
			$this,
			'button',
			[
				'label'   => __( 'טקסט הכפתור', 'nevehairus-site' ),
				'default' => 'לקרוא את הדף בשפה פשוטה',
				'plain'   => 'לחזור לקריאה רגילה',
			]
		);

		$this->end_controls_section();

		NH_Controls::section(
			$this,
			[
				'anchor'  => 'plain',
				'running' => 'בשפה פשוטה',
			]
		);
	}

	/**
	 * Markup of index.html `section#plain` (variant `home`) or
	 * program-main-home.html `section#plain-page` (variant `page`).
	 */
	protected function render() {
		$s = $this->get_settings_for_display();

		// No class and no tessera on this heading (prototype: <h2 id="plain-title">; the box has no title_dot control).
		$title = $this->nh_h2( $s, [ 'class' => '' ] );

		$items = NH_Text::block(
			self::life_text( $s, 'items' ),
			self::life_text( $s, 'items_plain' ),
			[
				'full_tag'    => 'ul',
				'full_lines'  => 'li',
				'full_attrs'  => [ 'role' => 'list' ],
				'plain_tag'   => 'p',
				'plain_lines' => null,
			]
		);

		$button = NH_Text::pair( self::life_text( $s, 'button' ), self::life_text( $s, 'button_plain' ) );

		$class = 'v3-plainbox-wrap';
		if ( 'page' === self::life_text( $s, 'variant' ) ) {
			$class .= ' v3-plainbox-wrap--page';
		}

		$html  = $this->nh_section_open(
			$s,
			[
				'class'      => $class,
				'labelledby' => '' !== $title ? 'title' : null,
			]
		);
		$html .= "\n" . '<div class="nh-container">' . "\n" . '<div class="v3-plainbox">' . "\n";
		$html .= NH_Html::icon( 'speech', 'v3-plainbox__icon' ) . "\n";
		$html .= '<div>' . "\n";
		if ( '' !== $title ) {
			$html .= $title . "\n";
		}
		if ( '' !== $items ) {
			$html .= $items . "\n";
		}
		if ( '' !== $button ) {
			$html .= '<button class="nh-btn nh-btn--primary v3-plainbox__btn" type="button" data-plain-toggle="action">' . $button . '</button>' . "\n";
		}
		$html .= '</div>' . "\n" . '</div>' . "\n" . '</div>' . "\n";
		$html .= $this->nh_section_close() . "\n";

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * A setting as a string ('' when missing or not scalar).
	 *
	 * @param array  $s   Settings.
	 * @param string $key Control id.
	 * @return string
	 */
	private static function life_text( array $s, string $key ): string {
		return isset( $s[ $key ] ) && is_scalar( $s[ $key ] ) ? (string) $s[ $key ] : '';
	}
}
