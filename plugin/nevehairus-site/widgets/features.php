<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `features` — מה יש בבית + ציטוט (work package "residence").
 *
 * A typographic list of what the home has (no icons) and a family member's words about the staff.
 * The list is full-reading only (`v3-full`): plain language gets the intro block before it (§3.5);
 * the quote's plain twin is a block of its own after the quote.
 * Target: prototype `program-main-home.html` `section#features`.
 *
 * @package nevehairus-site
 */
final class NH_Widget_Features extends NH_Widget_Base {

	public const ID       = 'features';
	public const KEYWORDS = [ 'features', 'list', 'quote', 'מה יש בבית', 'רשימה', 'ציטוט' ];

	public function get_title() {
		return __( 'מה יש בבית + ציטוט', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-bullet-list';
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
				'default' => 'כל מה שצריך, קרוב לבית.',
				'plain'   => 'מה יש במעון?',
				'dot'     => '',
			]
		);
		NH_Controls::pair(
			$this,
			'intro',
			[
				'label'   => __( 'פתיח לפני הרשימה', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 3,
				'default' => '',
				'plain'   => implode(
					"\n",
					[
						'חדר שקט ונעים להירגע.',
						'מרפאה ומרפאת שיניים.',
						'חוגים ופעילויות.',
					]
				),
			]
		);
		NH_Controls::pair(
			$this,
			'quote',
			[
				'label'   => __( 'ציטוט', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 4,
				'default' => 'הצוות הרפואי והמטפל נגישים בכל שעה ולכל שאלה […]. ישנה הקשבה מלאה והתייחסות כנה ואמיתית לצרכים.',
				'plain'   => implode(
					"\n",
					[
						'אמא של דייר כתבה לנו מכתב.',
						'היא כתבה שהצוות עונה על כל שאלה.',
						'היא כתבה שהצוות מקשיב באמת.',
					]
				),
			]
		);
		$this->add_control(
			'cite',
			[
				'label'       => __( 'מי אמר', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'ליליה, אמא של דייר בנווה האירוס',
				'label_block' => true,
			]
		);

		$this->end_controls_section();

		/* ---------- מה יש בבית (repeater) ---------- */
		$this->start_controls_section(
			'nh_list',
			[
				'label' => __( 'מה יש בבית', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$feature = new \Elementor\Repeater();
		$feature->add_control(
			'title',
			[
				'label'       => __( 'שם', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);
		$feature->add_control(
			'text',
			[
				'label'   => __( 'תיאור', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'rows'    => 3,
				'default' => '',
			]
		);
		$this->add_control(
			'features',
			[
				'label'       => __( 'הרשימה', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $feature->get_controls(),
				'default'     => [
					[
						'title' => 'חדר סנוזלן',
						'text'  => 'סביבה רב־חושית מבוקרת, להרפיה ולוויסות רגשי. הוקם בסיוע המוסד לביטוח לאומי.',
					],
					[
						'title' => 'מרפאה ומרפאת שיניים',
						'text'  => 'צוות רפואי במקום, ומרפאת שיניים מתקדמת.',
					],
					[
						'title' => 'חדרי טיפול',
						'text'  => 'חדשים, מרווחים ומזמינים.',
					],
					[
						'title' => 'תעסוקה ופנאי',
						'text'  => 'סדנאות, חוגים ופעילויות לאורך השבוע — בתוך המעון ומחוצה לו.',
					],
					[
						'title' => 'מועדון',
						'text'  => 'מקום מפגש לרווחת הדיירים והדיירות.',
					],
					[
						'title' => 'קידום אישי',
						'text'  => 'תוכנית אישית לכל דייר ודיירת, לפי הרצונות והיכולות שלהם — כולל אפשרות לעבור לדיור בקהילה.',
					],
				],
				'title_field' => '{{ title }}',
				'description' => __( 'הרשימה לא מוצגת בשפה פשוטה: את התוכן הפשוט כותבים בשדה «פתיח לפני הרשימה — בשפה פשוטה».', 'nevehairus-site' ),
			]
		);

		$this->end_controls_section();

		/* ---------- מקטע (always last) ---------- */
		NH_Controls::section(
			$this,
			[
				'anchor'  => 'features',
				'running' => 'מה יש בבית',
			]
		);
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$h2 = $this->nh_h2( $s );

		$html  = $this->nh_section_open(
			$s,
			[
				'class'      => 'v3-features v3-spread',
				'labelledby' => '' !== $h2 ? 'title' : null,
			]
		);
		$html .= '<div class="nh-container">';
		$html .= $this->nh_rule();
		$html .= $h2;
		$html .= NH_Text::block( self::str( $s, 'intro' ), self::str( $s, 'intro_plain' ) );
		$html .= $this->list_html( $s );
		$html .= $this->quote_html( $s );
		$html .= '</div>';
		$html .= $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * `<ul class="v3-feature-list v3-full">`: an `h3` and a description per item; an item without
	 * a name is skipped.
	 */
	private function list_html( array $s ): string {
		$items = '';
		foreach ( (array) ( $s['features'] ?? [] ) as $feature ) {
			if ( ! is_array( $feature ) ) {
				continue;
			}
			$title = self::str( $feature, 'title' );
			if ( '' === $title ) {
				continue;
			}
			$text = self::str( $feature, 'text' );

			$items .= '<li><h3>' . NH_Text::esc( $title ) . '</h3>' . ( '' !== $text ? '<p>' . NH_Text::esc( $text ) . '</p>' : '' ) . '</li>';
		}

		return '' !== $items ? '<ul class="v3-feature-list v3-full" role="list">' . $items . '</ul>' : '';
	}

	/**
	 * The quote (full reading only when it has a plain twin) and the twin as a block after it.
	 * Five states as `NH_Text::block()`: "-" hides the quote in plain language, a twin without a
	 * quote shows only the plain block.
	 */
	private function quote_html( array $s ): string {
		$quote = self::str( $s, 'quote' );
		$plain = self::str( $s, 'quote_plain' );
		$html  = '';

		if ( '' !== $quote ) {
			$cite  = self::str( $s, 'cite' );
			$html .= '<blockquote class="v3-quote v3-quote--page' . ( '' !== $plain ? ' v3-full' : '' ) . '">'
				. '<p>' . NH_Text::esc( $quote ) . '</p>'
				. ( '' !== $cite ? '<footer>— <cite>' . NH_Text::esc( $cite ) . '</cite></footer>' : '' )
				. '</blockquote>';
		}

		return $html . NH_Text::block( '', $plain, [ 'plain_class' => 'v3-plain--after' ] );
	}

	/**
	 * A text setting as a trimmed string ('' when missing or not scalar).
	 */
	private static function str( array $a, string $key ): string {
		return isset( $a[ $key ] ) && is_scalar( $a[ $key ] ) ? trim( (string) $a[ $key ] ) : '';
	}
}
