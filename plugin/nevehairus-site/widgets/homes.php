<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget "homes" — שלושה בתים · אינדקס (package index, SPEC.md §4.3).
 *
 * The homes index of the home page: the quick chooser («האם זה המקום בשבילנו?»), the spread's heading and one
 * row per home. A row is one link; its small ב holds the home's photo in its room (or a calm sky when there is
 * no photo). On the journey "row → that home's page" the ב becomes the page cover's ב: the row carries
 * data-vt="home-bet-{post id}" and homes.js names the room on click (D11).
 * Target markup: prototype/index.html section#homes (source prototype/src/index.html L38–98).
 *
 * @package nevehairus-site
 */
final class NH_Widget_Homes extends NH_Widget_Base {

	public const ID         = 'homes';
	public const STYLE_DEPS = [ 'nh-letters' ];
	public const KEYWORDS   = [ 'homes', 'index', 'בתים', 'בית', 'מסגרות' ];

	/** Row tones: the colour that fills the row on hover and focus (CSS --field). */
	private const TONES = [ 'paper', 'sand', 'sky' ];

	public function get_title() {
		return __( 'שלושה בתים · אינדקס', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-bullet-list';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'nh_content',
			[
				'label' => __( 'תוכן', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);
		NH_Controls::pair(
			$this,
			'fit_q',
			[
				'label'   => __( 'שאלת הבחירה המהירה', 'nevehairus-site' ),
				'default' => 'האם זה המקום בשבילנו?',
				'plain'   => '',
			]
		);
		NH_Controls::heading(
			$this,
			[
				'default' => 'שלושה בתים.',
				'plain'   => '',
				'dot'     => '',
			]
		);
		NH_Controls::pair(
			$this,
			'intro',
			[
				'label'   => __( 'פתיח מתחת לכותרת', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => '',
				'plain'   => "יש לנו שלושה בתים.\nהבתים שונים זה מזה.\nלחצו על בית כדי לקרוא עליו.",
			]
		);
		NH_Controls::pair(
			$this,
			'note',
			[
				'label'   => __( 'הערה מתחת לרשימה', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'לבית השלישי עוד אין צילומים, ולכן במקום תמונה יש בו שמיים שקטים.',
				'plain'   => 'לבית השלישי עוד אין תמונות.',
			]
		);
		$this->end_controls_section();

		// The quick chooser: answers that jump to the matching row.
		$this->start_controls_section(
			'nh_fit',
			[
				'label' => __( 'בחירה מהירה', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);
		$fit = new \Elementor\Repeater();
		NH_Controls::pair(
			$fit,
			'label',
			[
				'label'   => __( 'תשובה', 'nevehairus-site' ),
				'default' => '',
				'plain'   => '',
			]
		);
		NH_Controls::link(
			$fit,
			'link',
			[
				'label'   => __( 'קישור (בדרך כלל לעוגן של שורת בית, למשל #home-1)', 'nevehairus-site' ),
				'default' => '',
			]
		);
		$this->add_control(
			'fit',
			[
				'label'         => __( 'תשובות', 'nevehairus-site' ),
				'type'          => \Elementor\Controls_Manager::REPEATER,
				'fields'        => $fit->get_controls(),
				'title_field'   => '{{ label }}',
				'prevent_empty' => false,
				'default'       => [
					[
						'label'       => 'צריך צוות צמוד כל היום',
						'label_plain' => '',
						'link'        => [ 'url' => '#home-1' ],
					],
					[
						'label'       => 'יש התנהגות מאתגרת או אבחנה כפולה',
						'label_plain' => '',
						'link'        => [ 'url' => '#home-1' ],
					],
					[
						'label'       => 'רוצים לגור בדירה בתוך העיר',
						'label_plain' => '',
						'link'        => [ 'url' => '#home-2' ],
					],
					[
						'label'       => 'צעירים וצעירות על הרצף האוטיסטי',
						'label_plain' => '',
						'link'        => [ 'url' => '#home-3' ],
					],
				],
			]
		);
		$this->end_controls_section();

		// The index: one row per home.
		$this->start_controls_section(
			'nh_list',
			[
				'label' => __( 'בתים', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);
		$row = new \Elementor\Repeater();
		$row->add_control(
			'anchor',
			[
				'label'       => __( 'עוגן השורה (id באנגלית, למשל home-1)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);
		$row->add_control(
			'tone',
			[
				'label'   => __( 'צבע המילוי של השורה (ריחוף ומיקוד)', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => [
					'paper' => __( 'נייר', 'nevehairus-site' ),
					'sand'  => __( 'חול', 'nevehairus-site' ),
					'sky'   => __( 'שמיים', 'nevehairus-site' ),
				],
				'default' => 'paper',
			]
		);
		NH_Controls::pair(
			$row,
			'title',
			[
				'label'   => __( 'שם הבית', 'nevehairus-site' ),
				'default' => '',
				'plain'   => '',
			]
		);
		NH_Controls::pair(
			$row,
			'who',
			[
				'label'   => __( 'למי הבית מתאים', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => '',
				'plain'   => '',
			]
		);
		NH_Controls::pair(
			$row,
			'facts',
			[
				'label'   => __( 'עובדות בשורה אחת', 'nevehairus-site' ),
				'default' => '',
				'plain'   => '-',
			]
		);
		NH_Controls::link(
			$row,
			'link',
			[
				'label'   => __( 'קישור לעמוד הבית', 'nevehairus-site' ),
				'default' => '',
			]
		);
		NH_Controls::image(
			$row,
			'photo',
			[
				'label'      => __( 'תמונה בחדר של ה־ב׳ (ריק = שמיים)', 'nevehairus-site' ),
				'decorative' => true,
				'alt'        => '',
				'pos'        => '50% 50%',
			]
		);
		$row->add_control(
			'weight',
			[
				'label'   => __( 'עובי ה־ב׳ (300–800)', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 300,
				'max'     => 800,
				'step'    => 10,
				'default' => 560,
			]
		);
		$this->add_control(
			'homes',
			[
				'label'       => __( 'בתים', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $row->get_controls(),
				'title_field' => '{{ title }}',
				'default'     => [
					[
						'anchor'           => 'home-1',
						'tone'             => 'paper',
						'title'            => 'מעון ראשי — בית לחיים',
						'title_plain'      => '',
						'who'              => 'בוגרים ובוגרות עם מוגבלות שכלית התפתחותית, גם עם התנהגות מאתגרת או אבחנה כפולה.',
						'who_plain'        => "בית גדול בנס ציונה.\nצוות עוזר שם כל היום.",
						'facts'            => 'שתי יחידות · צוות קבוע · חדר סנוזלן ומרפאת שיניים',
						'facts_plain'      => '-',
						'link'             => [ 'url' => '#homes' ],
						'photo'            => [
							'url' => '',
							'id'  => '',
						],
						'photo_alt'        => '',
						'photo_decorative' => 'yes',
						'photo_pos'        => '55% 45%',
						'weight'           => 600,
					],
					[
						'anchor'           => 'home-2',
						'tone'             => 'sand',
						'title'            => 'דיור בקהילה',
						'title_plain'      => '',
						'who'              => 'בוגרים ובוגרות עם מוגבלות שכלית התפתחותית, בדירות ובבתים בתוך העיר — עם צוות מלווה.',
						'who_plain'        => "דירות ובתים בתוך העיר.\nצוות עוזר לדיירים ולדיירות.",
						'facts'            => 'שגרה ועצמאות · עבודה ופנאי בעיר · ליווי צמוד',
						'facts_plain'      => '-',
						'link'             => [ 'url' => '#homes' ],
						'photo'            => [
							'url' => '',
							'id'  => '',
						],
						'photo_alt'        => '',
						'photo_decorative' => 'yes',
						'photo_pos'        => '50% 45%',
						'weight'           => 560,
					],
					[
						'anchor'           => 'home-3',
						'tone'             => 'sky',
						'title'            => 'דיור לצעירים על הרצף האוטיסטי',
						'title_plain'      => '',
						'who'              => 'בית לצעירים ולצעירות, עם ליווי אישי בשגרת היום־יום.',
						'who_plain'        => "בית לצעירים ולצעירות עם אוטיזם.\nהבית שקט, ויש סדר יום ברור.",
						'facts'            => 'סביבה רגועה · שגרה ברורה',
						'facts_plain'      => '-',
						'link'             => [ 'url' => '#homes' ],
						'photo'            => [
							'url' => '',
							'id'  => '',
						],
						'photo_alt'        => '',
						'photo_decorative' => 'yes',
						'photo_pos'        => '50% 50%',
						'weight'           => 560,
					],
				],
			]
		);
		$this->end_controls_section();

		NH_Controls::section(
			$this,
			[
				'anchor'  => 'homes',
				'running' => 'שלושה בתים',
			]
		);
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$h2   = '' !== NH_Text::pair( self::str( $s, 'title' ), self::str( $s, 'title_plain' ) ) ? $this->nh_h2( $s ) : '';
		$open = [ 'class' => 'v3-homes v3-spread' ];
		if ( '' === $h2 ) {
			$open['labelledby'] = null; // no heading to point to (editor left the title empty)
		}

		$html  = $this->nh_section_open( $s, $open );
		$html .= "\n\t<div class=\"nh-container\">";
		$html .= $this->fit( $s );
		$html .= "\n\t\t" . $this->nh_rule();
		if ( '' !== $h2 ) {
			$html .= "\n\t\t" . $h2;
		}
		$intro = NH_Text::block(
			self::str( $s, 'intro' ),
			self::str( $s, 'intro_plain' ),
			[
				'full_class' => 'v3-lead',
				'plain_tag'  => 'div',
			]
		);
		if ( '' !== $intro ) {
			$html .= "\n\t\t" . $intro;
		}

		$rows = '';
		foreach ( self::items( $s, 'homes' ) as $item ) {
			$rows .= $this->row( $item );
		}
		if ( '' !== $rows ) {
			$html .= "\n\t\t" . '<ol class="v3-homes__list" role="list">' . $rows . "\n\t\t</ol>";
		}

		$note = NH_Text::block(
			self::str( $s, 'note' ),
			self::str( $s, 'note_plain' ),
			[
				'full_class'  => 'v3-note',
				'plain_tag'   => 'p',
				'plain_class' => 'v3-note',
				'plain_lines' => null,
			]
		);
		if ( '' !== $note ) {
			$html .= "\n\t\t" . $note;
		}
		$html .= "\n\t</div>\n" . $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * The quick chooser: a nav named by its question, one link per answer. '' when there are no answers.
	 */
	private function fit( array $s ): string {
		$items = '';
		foreach ( self::items( $s, 'fit' ) as $item ) {
			$label = self::str( $item, 'label' );
			if ( '' === $label ) {
				continue;
			}
			$href   = NH_Html::url( $item['link'] ?? '' );
			$hide   = NH_Text::HIDE === self::str( $item, 'label_plain' ); // the whole answer is left out in plain language
			$items .= "\n\t\t\t\t<li" . ( $hide ? ' class="v3-full"' : '' ) . '><a' . ( '' !== $href ? ' href="' . $href . '"' : '' ) . '>'
				. NH_Text::pair( $label, $hide ? '' : self::str( $item, 'label_plain' ) ) . '</a></li>';
		}
		if ( '' === $items ) {
			return '';
		}

		$q   = NH_Text::pair( self::str( $s, 'fit_q' ), self::str( $s, 'fit_q_plain' ) );
		$qid = $this->nh_uid( 'fit-title' );
		$out = "\n\t\t" . '<nav class="v3-fit"' . ( '' !== $q ? ' aria-labelledby="' . esc_attr( $qid ) . '"' : '' ) . '>';
		if ( '' !== $q ) {
			$out .= "\n\t\t\t" . '<p class="v3-fit__q" id="' . esc_attr( $qid ) . '">' . $q . '</p>';
		}
		return $out . "\n\t\t\t" . '<ul role="list">' . $items . "\n\t\t\t</ul>\n\t\t</nav>";
	}

	/**
	 * One row of the index: the whole row is one link; the ב's room shows the photo or a calm sky.
	 */
	private function row( array $item ): string {
		$title = self::str( $item, 'title' );
		if ( '' === $title ) {
			return '';
		}

		$tone   = self::str( $item, 'tone' );
		$tone   = in_array( $tone, self::TONES, true ) ? $tone : 'paper';
		$anchor = sanitize_html_class( self::str( $item, 'anchor' ) );
		// a title hidden in plain language hides the whole row (a row link must never lose its name);
		// the texts inside are then rendered without mode classes
		$hide = NH_Text::HIDE === self::str( $item, 'title_plain' );

		$link = $item['link'] ?? '';
		$href = NH_Html::url( $link );
		$raw  = is_array( $link ) ? self::str( $link, 'url' ) : ( is_string( $link ) ? trim( $link ) : '' );
		$post = '' !== $raw ? NH_Html::post_id( $raw ) : 0;

		$img   = NH_Controls::image_args( $item, 'photo' );
		$photo = (int) ( $img['photo'] ?? 0 );
		if ( $photo > 0 && ! NH_Picture::exists( $photo ) ) {
			$photo = 0; // a deleted or non-image attachment counts as empty: the room shows the sky
		}
		$room = [
			'photo'      => $photo,
			'sizes'      => '9rem',
			'weight'     => self::weight( $item['weight'] ?? '', 560.0 ),
			'class'      => 'v3-home__bet',
			'decorative' => ! empty( $img['decorative'] ),
			'field'      => 'sky',
		];
		if ( isset( $img['pos'] ) && is_string( $img['pos'] ) && '' !== trim( $img['pos'] ) ) {
			$room['pos'] = trim( $img['pos'] );
		}
		if ( isset( $img['alt'] ) && is_string( $img['alt'] ) ) {
			$room['alt'] = $img['alt'];
		}

		$who   = NH_Text::pair(
			self::str( $item, 'who' ),
			$hide ? '' : self::str( $item, 'who_plain' ),
			[
				'tag'         => 'span',
				'class'       => 'v3-home__who',
				'plain_lines' => true,
				'bare'        => false,
			]
		);
		$facts = NH_Text::pair(
			self::str( $item, 'facts' ),
			$hide ? '' : self::str( $item, 'facts_plain' ),
			[
				'tag'   => 'span',
				'class' => 'v3-home__facts',
				'bare'  => false,
			]
		);

		$out  = "\n\t\t\t<li" . NH_Html::attrs(
			[
				'class' => 'v3-home v3-home--' . $tone . ( $hide ? ' v3-full' : '' ),
				'id'    => '' !== $anchor ? $anchor : null,
			]
		) . '>';
		$out .= "\n\t\t\t\t" . '<a class="v3-home__link"'
			. ( '' !== $href ? ' href="' . $href . '"' : '' )
			. ( $post > 0 ? ' data-vt="' . esc_attr( 'home-bet-' . $post ) . '"' : '' ) . '>';
		$out .= "\n\t\t\t\t\t" . NH_Letters::room( 'bet', $room );
		$out .= "\n\t\t\t\t\t" . '<span class="v3-home__body">';
		$out .= "\n\t\t\t\t\t\t" . '<span class="v3-home__title">' . NH_Text::pair( $title, $hide ? '' : self::str( $item, 'title_plain' ) ) . '</span>';
		if ( '' !== $who ) {
			$out .= "\n\t\t\t\t\t\t" . $who;
		}
		if ( '' !== $facts ) {
			$out .= "\n\t\t\t\t\t\t" . $facts;
		}
		$out .= "\n\t\t\t\t\t</span>";
		$out .= "\n\t\t\t\t\t" . NH_Html::icon( 'arrow-fwd', 'v3-home__arrow' );
		$out .= "\n\t\t\t\t</a>\n\t\t\t</li>";
		return $out;
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

	/**
	 * A letter weight from a NUMBER control, clamped to 300–800.
	 */
	private static function weight( $v, float $fallback ): float {
		if ( ! is_numeric( $v ) ) {
			return $fallback;
		}
		return max( 300.0, min( 800.0, (float) $v ) );
	}
}
