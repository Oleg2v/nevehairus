<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget "steps" — צעד צעד (package index, SPEC.md §4.10).
 *
 * Four numbered steps to moving in. Above each step the word «צעד» is drawn as decoration only: it is generated
 * content (CSS ::before from data-word) in an aria-hidden span, and it is folded away in plain-language mode.
 * Target markup: prototype/index.html section#start (source prototype/src/index.html L297–311).
 *
 * @package nevehairus-site
 */
final class NH_Widget_Steps extends NH_Widget_Base {

	public const ID       = 'steps';
	public const KEYWORDS = [ 'steps', 'צעד', 'צעדים', 'תהליך' ];

	public function get_title() {
		return __( 'צעד צעד', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-editor-list-ol';
	}

	protected function register_controls() {
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
				'default' => 'צעד צעד: ארבעה צעדים הביתה.',
				'plain'   => 'איך מגיעים לגור אצלנו?',
				'dot'     => '',
			]
		);
		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'המחלקה הסוציאלית שלנו מלווה אתכם לאורך כל הדרך.',
				'plain'   => 'יש ארבעה צעדים.',
			]
		);
		$this->add_control(
			'word',
			[
				'label'       => __( 'מילת הרקע מעל כל צעד (קישוט בלבד, ריק = בלי)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'צעד',
				'label_block' => true,
			]
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'nh_list',
			[
				'label' => __( 'צעדים', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);
		$r = new \Elementor\Repeater();
		NH_Controls::pair(
			$r,
			'title',
			[
				'label'   => __( 'כותרת', 'nevehairus-site' ),
				'default' => '',
				'plain'   => '',
			]
		);
		NH_Controls::pair(
			$r,
			'text',
			[
				'label'   => __( 'טקסט', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => '',
				'plain'   => '',
			]
		);
		$this->add_control(
			'steps',
			[
				'label'       => __( 'צעדים', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'title_field' => '{{ title }}',
				'default'     => [
					[
						'title'       => 'שיחה ראשונה',
						'title_plain' => '',
						'text'        => 'מתקשרים או משאירים פרטים. נסביר את התהליך ונענה על כל שאלה.',
						'text_plain'  => 'מדברים איתנו בטלפון.',
					],
					[
						'title'       => 'ביקור והיכרות',
						'title_plain' => '',
						'text'        => 'מגיעים לראות את המקום, להכיר את הצוות ולשאול הכול.',
						'text_plain'  => 'באים לבקר ולהכיר.',
					],
					[
						'title'       => 'קשר עם לשכת הרווחה',
						'title_plain' => 'הצוות מדבר עם הרווחה',
						'text'        => 'הצוות שלנו מדבר עם לשכת הרווחה שמלווה אתכם, ומתאם איתה את הפרטים.',
						'text_plain'  => 'הצוות שלנו מדבר עם לשכת הרווחה.',
					],
					[
						'title'       => 'תקופת הסתגלות',
						'title_plain' => 'מתחילים לגור אצלנו',
						'text'        => 'בהתחלה אנחנו קרובים במיוחד — עד שמרגישים בבית.',
						'text_plain'  => 'לאט לאט מרגישים בבית.',
					],
				],
			]
		);
		$this->end_controls_section();

		NH_Controls::section(
			$this,
			[
				'anchor'  => 'start',
				'running' => 'צעד צעד',
			]
		);
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$word = self::str( $s, 'word' );

		$items = '';
		$n     = 0;
		foreach ( self::items( $s, 'steps' ) as $item ) {
			$title = self::str( $item, 'title' );
			if ( '' === $title ) {
				continue;
			}
			++$n;
			$items .= "\n\t\t\t<li>"
				. ( '' !== $word ? '<span class="v3-steps__word" aria-hidden="true" data-word="' . esc_attr( $word ) . '"></span>' : '' )
				. '<h3><span class="v3-steps__n">' . $n . '</span> ' . NH_Text::pair( $title, self::str( $item, 'title_plain' ) ) . '</h3>'
				. NH_Text::block(
					self::str( $item, 'text' ),
					self::str( $item, 'text_plain' ),
					[
						'full_tag'    => 'p',
						'plain_tag'   => 'p',
						'plain_lines' => null,
					]
				)
				. '</li>';
		}

		$h2   = '' !== NH_Text::pair( self::str( $s, 'title' ), self::str( $s, 'title_plain' ) ) ? $this->nh_h2( $s ) : '';
		$open = [ 'class' => 'v3-steps v3-spread' ];
		if ( '' === $h2 ) {
			$open['labelledby'] = null; // no heading to point to (editor left the title empty)
		}
		$lead = $this->nh_lead(
			$s,
			[
				'plain_tag'   => 'p',
				'plain_class' => 'v3-lead',
				'plain_lines' => null,
			]
		);

		$html  = $this->nh_section_open( $s, $open );
		$html .= "\n\t<div class=\"nh-container\">";
		$html .= "\n\t\t" . $this->nh_rule();
		if ( '' !== $h2 ) {
			$html .= "\n\t\t" . $h2;
		}
		if ( '' !== $lead ) {
			$html .= "\n\t\t" . $lead;
		}
		if ( '' !== $items ) {
			$html .= "\n\t\t" . '<ol class="v3-steps__list" role="list">' . $items . "\n\t\t</ol>";
		}
		$html .= "\n\t</div>\n" . $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * A text setting as a trimmed string ('' when missing or not scalar).
	 */
	private static function str( array $a, string $key ): string {
		$v = $a[ $key ] ?? '';
		return is_scalar( $v ) ? trim( (string) $v ) : '';
	}

	/**
	 * The items of a repeater setting (only well-formed ones).
	 */
	private static function items( array $s, string $key ): array {
		$list = $s[ $key ] ?? [];
		return is_array( $list ) ? array_filter( $list, 'is_array' ) : [];
	}
}
