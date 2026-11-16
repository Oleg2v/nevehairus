<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `glance` — במבט אחד · מספרים ועובדות (work package "residence").
 *
 * Numbers standing on the ruling line and a short list of facts. Both lists are full-reading
 * only (`v3-full`); plain language gets the summary block after them (§3.5).
 * Target: prototype `program-main-home.html` `section#glance`.
 *
 * @package nevehairus-site
 */
final class NH_Widget_Glance extends NH_Widget_Base {

	public const ID       = 'glance';
	public const KEYWORDS = [ 'glance', 'numbers', 'facts', 'stats', 'במבט אחד', 'מספרים', 'עובדות' ];

	public function get_title() {
		return __( 'במבט אחד · מספרים ועובדות', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-number-field';
	}

	protected function register_controls() {
		/* ---------- תוכן ---------- */
		$this->start_controls_section(
			'nh_content',
			[
				'label' => __( 'תוכן', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		NH_Controls::heading(
			$this,
			[
				'default' => 'בית עם צוות שמכיר אותך.',
				'plain'   => 'צוות שמכיר אותך',
				'dot'     => '',
			]
		);
		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 4,
				'default' => 'דירות המעון מחולקות לשתי יחידות, ולכל יחידה צוות קבוע משלה — כך כל דייר ודיירת יודעים למי לפנות, והצוות מכיר כל אחד ואחת לעומק.',
				'plain'   => implode(
					"\n",
					[
						'המעון מחולק לשני חלקים.',
						'לכל חלק יש צוות משלו.',
						'הצוות מכיר כל דייר וכל דיירת.',
					]
				),
			]
		);
		NH_Controls::pair(
			$this,
			'summary',
			[
				'label'   => __( 'סיכום אחרי המספרים והעובדות', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 3,
				'default' => '',
				'plain'   => implode(
					"\n",
					[
						'בנווה האירוס גרים יותר מ־100 דיירים ודיירות.',
						'המעון נפתח בשנת 1957.',
						'משרד הרווחה בודק שהכול בסדר.',
					]
				),
			]
		);

		$this->end_controls_section();

		/* ---------- מספרים (repeater) ---------- */
		$this->start_controls_section(
			'nh_stats',
			[
				'label' => __( 'מספרים', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$stat = new \Elementor\Repeater();
		$stat->add_control(
			'num',
			[
				'label'       => __( 'מספר', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);
		$stat->add_control(
			'label',
			[
				'label'       => __( 'מה הוא סופר', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);
		$this->add_control(
			'stats',
			[
				'label'       => __( 'המספרים', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $stat->get_controls(),
				'default'     => [
					[
						'num'   => '2',
						'label' => 'יחידות, לכל אחת צוות קבוע',
					],
					[
						'num'   => '4',
						'label' => 'תפקידים בכל צוות: ניהול, עבודה סוציאלית, אם בית, ריכוז דירות',
					],
					[
						'num'   => '100+',
						'label' => 'דיירים ודיירות בנווה האירוס',
					],
					[
						'num'   => '1957',
						'label' => 'שנת הקמה',
					],
				],
				'title_field' => '{{ num }} {{ label }}',
				'description' => __( 'הרשימה לא מוצגת בשפה פשוטה: את התוכן הפשוט כותבים בשדה «סיכום אחרי המספרים והעובדות — בשפה פשוטה».', 'nevehairus-site' ),
			]
		);

		$this->end_controls_section();

		/* ---------- עובדות (repeater) ---------- */
		$this->start_controls_section(
			'nh_facts',
			[
				'label' => __( 'עובדות', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$fact = new \Elementor\Repeater();
		$fact->add_control(
			'term',
			[
				'label'       => __( 'נושא', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);
		$fact->add_control(
			'desc',
			[
				'label'       => __( 'פירוט', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);
		$this->add_control(
			'facts',
			[
				'label'       => __( 'העובדות', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $fact->get_controls(),
				'default'     => [
					[
						'term' => 'למי',
						'desc' => 'בוגרים ובוגרות עם מוגבלות שכלית התפתחותית, כולל אבחנה כפולה והתנהגות מאתגרת',
					],
					[
						'term' => 'איפה',
						'desc' => 'בנס ציונה',
					],
					[
						'term' => 'פיקוח',
						'desc' => 'משרד הרווחה והביטחון החברתי',
					],
				],
				'title_field' => '{{ term }}',
				'description' => __( 'הרשימה לא מוצגת בשפה פשוטה: את התוכן הפשוט כותבים בשדה «סיכום אחרי המספרים והעובדות — בשפה פשוטה».', 'nevehairus-site' ),
			]
		);

		$this->end_controls_section();

		/* ---------- מקטע (always last) ---------- */
		NH_Controls::section( $this, [ 'anchor' => 'glance' ] );
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$h2 = $this->nh_h2( $s );

		$html  = $this->nh_section_open(
			$s,
			[
				'class'      => 'v3-glance v3-spread',
				'labelledby' => '' !== $h2 ? 'title' : null,
			]
		);
		$html .= '<div class="nh-container">';
		$html .= $this->nh_rule();
		$html .= $h2;
		$html .= $this->nh_lead( $s );
		$html .= $this->stats_html( $s );
		$html .= $this->facts_html( $s );
		$html .= NH_Text::block(
			self::str( $s, 'summary' ),
			self::str( $s, 'summary_plain' ),
			[ 'plain_class' => 'v3-plain--after' ]
		);
		$html .= '</div>';
		$html .= $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * `<ul class="v3-stats v3-full">`: a number with a symbol (e.g. "100+") is isolated as LTR.
	 * An item without a number is skipped.
	 */
	private function stats_html( array $s ): string {
		$items = '';
		foreach ( (array) ( $s['stats'] ?? [] ) as $stat ) {
			if ( ! is_array( $stat ) ) {
				continue;
			}
			$num = self::str( $stat, 'num' );
			if ( '' === $num ) {
				continue;
			}
			$big = esc_html( $num );
			if ( preg_match( '/[^0-9 ,.]/u', $num ) ) {
				$big = '<span class="nh-ltr">' . $big . '</span>';
			}
			$label = self::str( $stat, 'label' );

			$items .= '<li><b>' . $big . '</b>' . ( '' !== $label ? '<span>' . NH_Text::esc( $label ) . '</span>' : '' ) . '</li>';
		}

		return '' !== $items ? '<ul class="v3-stats v3-full" role="list">' . $items . '</ul>' : '';
	}

	/**
	 * `<dl class="v3-facts v3-full">`: a fact needs both its term and its description
	 * (a `dt` without a `dd` is not a valid group).
	 */
	private function facts_html( array $s ): string {
		$items = '';
		foreach ( (array) ( $s['facts'] ?? [] ) as $fact ) {
			if ( ! is_array( $fact ) ) {
				continue;
			}
			$term = self::str( $fact, 'term' );
			$desc = self::str( $fact, 'desc' );
			if ( '' === $term || '' === $desc ) {
				continue;
			}
			$items .= '<div><dt>' . NH_Text::esc( $term ) . '</dt><dd>' . NH_Text::esc( $desc ) . '</dd></div>';
		}

		return '' !== $items ? '<dl class="v3-facts v3-full">' . $items . '</dl>' : '';
	}

	/**
	 * A text setting as a trimmed string ('' when missing or not scalar).
	 */
	private static function str( array $a, string $key ): string {
		return isset( $a[ $key ] ) && is_scalar( $a[ $key ] ) ? trim( (string) $a[ $key ] ) : '';
	}
}
