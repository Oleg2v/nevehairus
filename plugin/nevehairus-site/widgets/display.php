<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `display` — מתגי תצוגה: the display switches as a widget of their own (research/07 §12.6, §13).
 *
 * The footer of the site is built from Elementor's own widgets (Image, Heading, Text Editor), so it keeps
 * working without the plugin; this widget is the one part of it that needs the plugin: «מצב רגוע» (and,
 * when switched on, «בשפה פשוטה») in the third column of the footer. Markup (as the prototype's footer,
 * prototype/src/partials/footer.html): `<div class="v3-switches[ nh-on-dark]" role="group"
 * aria-label="…">` + NH_Html::display_toggles(). No script of its own: nh-core.js handles the switches by
 * delegation and keeps every switch of the page in step (`aria-pressed`).
 *
 * @package nevehairus-site
 */
final class NH_Widget_Display extends NH_Widget_Base {

	public const ID       = 'display';
	public const KEYWORDS = [ 'display', 'calm', 'plain', 'toggle', 'switch', 'תצוגה', 'מצב רגוע', 'שפה פשוטה', 'מתג' ];

	/** Tones: on a light ground or on the night ground of the footer (`nh-on-dark`). */
	private const TONES = [ 'light', 'dark' ];

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'מתגי תצוגה', 'nevehairus-site' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-toggle';
	}

	/**
	 * Controls: תוכן (which switches, their labels, the state words, the group's name, the tone).
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
			'group_label',
			[
				'label'       => __( 'שם קבוצת המתגים (לקורא מסך)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'הגדרות תצוגה',
				'label_block' => true,
			]
		);
		$this->add_control(
			'show_plain',
			[
				'label'        => __( 'מתג „בשפה פשוטה”', 'nevehairus-site' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'בכותרת האתר כבר יש כפתור „בשפה פשוטה”: בתחתית מספיק „מצב רגוע”.', 'nevehairus-site' ),
			]
		);
		NH_Controls::pair(
			$this,
			'label_plain',
			[
				'label'     => __( 'מתג — שפה פשוטה', 'nevehairus-site' ),
				'default'   => 'בשפה פשוטה',
				'condition' => [ 'show_plain' => 'yes' ],
			]
		);
		$this->add_control(
			'show_calm',
			[
				'label'        => __( 'מתג „מצב רגוע”', 'nevehairus-site' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);
		NH_Controls::pair(
			$this,
			'label_calm',
			[
				'label'     => __( 'מתג — מצב רגוע', 'nevehairus-site' ),
				'default'   => 'מצב רגוע',
				'condition' => [ 'show_calm' => 'yes' ],
			]
		);
		$this->add_control(
			'state_on',
			[
				'label'       => __( 'מצב המתג — פועל', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'פועל',
				'description' => __( 'המילה שליד המתג כשהוא פועל (המצב עצמו נמסר לקורא המסך מהכפתור).', 'nevehairus-site' ),
			]
		);
		$this->add_control(
			'state_off',
			[
				'label'   => __( 'מצב המתג — כבוי', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'כבוי',
			]
		);
		$this->add_control(
			'tone',
			[
				'label'       => __( 'רקע', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => [
					'light' => __( 'בהיר', 'nevehairus-site' ),
					'dark'  => __( 'כהה (תחתית האתר)', 'nevehairus-site' ),
				],
				'default'     => 'dark',
				'description' => __( 'צבעי המתג מותאמים לרקע: על רקע כהה המתג הפעיל צהוב.', 'nevehairus-site' ),
			]
		);

		$this->end_controls_section();
	}

	/**
	 * `<div class="v3-switches[ nh-on-dark]" role="group" aria-label="{group_label}">` + the switches; '' when
	 * both are off.
	 */
	protected function render() {
		$s      = $this->get_settings_for_display();
		$labels = [];
		if ( 'yes' === ( $s['show_plain'] ?? '' ) ) {
			$labels['plain'] = NH_Text::pair( self::str( $s, 'label_plain' ), self::str( $s, 'label_plain_plain' ) );
		}
		if ( 'yes' === ( $s['show_calm'] ?? '' ) ) {
			$labels['calm'] = NH_Text::pair( self::str( $s, 'label_calm' ), self::str( $s, 'label_calm_plain' ) );
		}
		if ( ! $labels ) {
			return;
		}

		$tone  = self::str( $s, 'tone' );
		$tone  = in_array( $tone, self::TONES, true ) ? $tone : 'dark';
		$group = trim( self::str( $s, 'group_label' ) );

		$html = '<div' . NH_Html::attrs(
			[
				'class'      => 'v3-switches' . ( 'dark' === $tone ? ' nh-on-dark' : '' ),
				'role'       => 'group',
				'aria-label' => '' !== $group ? $group : null,
			]
		) . '>'
			. NH_Html::display_toggles(
				$labels,
				[
					'on'  => self::str( $s, 'state_on' ),
					'off' => self::str( $s, 'state_off' ),
				]
			)
			. '</div>';

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * A setting as a string ('' when missing, null — a hidden control — or not scalar).
	 *
	 * @param array  $s   Settings.
	 * @param string $key Key.
	 */
	private static function str( array $s, string $key ): string {
		return isset( $s[ $key ] ) && is_scalar( $s[ $key ] ) ? (string) $s[ $key ] : '';
	}
}
