<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `stories` — שלושה סיפורים (work package "residence").
 *
 * Three short stories on the night spread, step by step; the residents are the subjects,
 * identifying details left out. Each story has a plain twin.
 * Target: prototype `program-main-home.html` `section#stories`.
 *
 * @package nevehairus-site
 */
final class NH_Widget_Stories extends NH_Widget_Base {

	public const ID       = 'stories';
	public const KEYWORDS = [ 'stories', 'steps', 'סיפורים', 'צעד צעד' ];

	public function get_title() {
		return __( 'שלושה סיפורים', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-posts-grid';
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
				'default' => 'צעד צעד: שלושה סיפורים.',
				'plain'   => 'שלושה סיפורים',
				'dot'     => 'citrus',
			]
		);
		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 3,
				'default' => 'שלושה סיפורים מהשנים האחרונות. הפרטים המזהים הושמטו.',
				'plain'   => implode(
					"\n",
					[
						'שלושה סיפורים על דיירים ודיירות.',
						'לא כתבנו את השמות שלהם.',
					]
				),
			]
		);

		$this->end_controls_section();

		/* ---------- סיפורים (repeater) ---------- */
		$this->start_controls_section(
			'nh_list',
			[
				'label' => __( 'סיפורים', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$story = new \Elementor\Repeater();
		NH_Controls::pair(
			$story,
			'title',
			[
				'label'   => __( 'כותרת', 'nevehairus-site' ),
				'default' => '',
			]
		);
		NH_Controls::pair(
			$story,
			'text',
			[
				'label'   => __( 'הסיפור', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 4,
				'default' => '',
			]
		);
		$this->add_control(
			'stories',
			[
				'label'       => __( 'הסיפורים', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $story->get_controls(),
				'default'     => [
					[
						'title'      => 'מהמעון לבית בקהילה',
						'text'       => 'אחרי עבודה עקבית ומשותפת, דייר עבר מהמעון לווילה בקהילה — שלוחה של נווה האירוס.',
						'text_plain' => implode(
							"\n",
							[
								'דייר גר במעון הרבה שנים.',
								'היום הוא גר בבית של נווה האירוס בעיר.',
								'הצוות עזר לו לעבור.',
							]
						),
					],
					[
						'title'      => 'יציאה לפעילויות',
						'text'       => 'דיירת שהגיעה עם קשיים רבים בשגרה היומיומית יוצאת היום לפעילויות, בשיתוף פעולה עם הצוות.',
						'text_plain' => implode(
							"\n",
							[
								'לדיירת היה קשה בכל יום.',
								'היום היא יוצאת לפעילויות.',
								'היא והצוות עשו את זה יחד.',
							]
						),
					],
					[
						'title'      => 'מעבר לשער',
						'text'       => 'דייר שחשש במשך שנים לצאת משטח המעון יוצא היום לטיולי יום, בליווי צמוד של רכזת הדירה.',
						'text_plain' => implode(
							"\n",
							[
								'דייר פחד לצאת מהמעון במשך שנים.',
								'היום הוא יוצא לטיולים.',
								'רכזת הדירה הולכת איתו.',
							]
						),
					],
				],
				'title_field' => '{{ title }}',
			]
		);

		$this->end_controls_section();

		/* ---------- מקטע (always last) ---------- */
		NH_Controls::section(
			$this,
			[
				'anchor'  => 'stories',
				'running' => 'סיפורים',
			]
		);
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$h2 = $this->nh_h2( $s );

		$html  = $this->nh_section_open(
			$s,
			[
				'class'      => 'v3-stories v3-spread',
				'tone'       => 'night',
				'labelledby' => '' !== $h2 ? 'title' : null,
			]
		);
		$html .= '<div class="nh-container">';
		$html .= $this->nh_rule();
		$html .= $h2;
		$html .= $this->nh_lead( $s, [ 'plain_class' => 'v3-lead' ] );
		$html .= $this->list_html( $s );
		$html .= '</div>';
		$html .= $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * `<ol class="v3-stories__list">`: the decorative step number (`aria-hidden`, the list itself
	 * is numbered), the title as `h3` and the story with its plain twin. A story without a title
	 * is skipped.
	 */
	private function list_html( array $s ): string {
		$items = '';
		$n     = 0;
		foreach ( (array) ( $s['stories'] ?? [] ) as $story ) {
			if ( ! is_array( $story ) ) {
				continue;
			}
			$title = self::str( $story, 'title' );
			if ( '' === $title ) {
				continue;
			}
			++$n;

			$items .= '<li><span class="v3-stories__n" aria-hidden="true">' . $n . '</span>'
				. '<h3>' . NH_Text::pair( $title, self::str( $story, 'title_plain' ) ) . '</h3>'
				. NH_Text::block(
					self::str( $story, 'text' ),
					self::str( $story, 'text_plain' ),
					[
						'full_tag'  => 'p',
						'plain_tag' => 'div',
					]
				)
				. '</li>';
		}

		return '' !== $items ? '<ol class="v3-stories__list" role="list">' . $items . '</ol>' : '';
	}

	/**
	 * A text setting as a trimmed string ('' when missing or not scalar).
	 */
	private static function str( array $a, string $key ): string {
		return isset( $a[ $key ] ) && is_scalar( $a[ $key ] ) ? trim( (string) $a[ $key ] ) : '';
	}
}
