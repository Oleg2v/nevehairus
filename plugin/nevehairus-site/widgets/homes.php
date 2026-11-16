<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget "homes" — שלושה בתים · אינדקס (package index, SPEC.md §4.3).
 *
 * The homes index of the home page: the spread's heading, a lead that says what each row tells, and one row per
 * home. A row whose home has a page is one link (the whole row, with a still arrow); a row without a page is not
 * a link and says «דף מפורט בקרוב» (research/07 §3 item 21, §10.2 item 2). The small ב (weight 600) holds the
 * home's photo in its room, or a calm sky when there is no photo.
 * Target markup: prototype/index.html section#homes (source prototype/src/index.html).
 *
 * @package nevehairus-site
 */
final class NH_Widget_Homes extends NH_Widget_Base {

	public const ID         = 'homes';
	public const STYLE_DEPS = [ 'nh-letters' ];
	public const KEYWORDS   = [ 'homes', 'index', 'בתים', 'בית', 'מסגרות' ];

	/** Row tones: the colour that fills the row on hover and focus (CSS --field). */
	private const TONES = [ 'paper', 'sand', 'sky' ];

	/** The one weight of the ב in every row (v3.2: every letter keeps one weight for good). */
	public const WEIGHT = 600;

	/** `sizes` of the photo in a row's room (research/07 §8.1: the small ב is 9.5rem wide). */
	public const SIZES = '9.5rem';

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
			'lead',
			[
				'label'   => __( 'פתיח מתחת לכותרת', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'האם זה המקום בשבילנו? ליד כל בית כתוב למי הוא מתאים.',
				'plain'   => "יש לנו שלושה בתים.\nהבתים שונים זה מזה.\nליד כל בית כתוב למי הוא מתאים.",
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
		NH_Controls::pair(
			$this,
			'soon_text',
			[
				'label'       => __( 'בשורה של בית שעוד אין לו עמוד', 'nevehairus-site' ),
				'default'     => 'דף מפורט בקרוב',
				'plain'       => '',
				'description' => __( 'שורה בלי קישור היא לא קישור: אין בה חץ, והמילים האלה נכתבות אחרי העובדות.', 'nevehairus-site' ),
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
				'label'       => __( 'קישור לעמוד הבית (ריק = עוד אין עמוד)', 'nevehairus-site' ),
				'default'     => '',
				'description' => __( 'רק לעמוד שקיים. בלי עמוד השורה אינה קישור וכתוב בה „דף מפורט בקרוב”.', 'nevehairus-site' ),
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
						'link'             => [ 'url' => '' ],
						'photo'            => [
							'url' => '',
							'id'  => '',
						],
						'photo_alt'        => '',
						'photo_decorative' => 'yes',
						'photo_pos'        => '55% 45%',
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
						'link'             => [ 'url' => '' ],
						'photo'            => [
							'url' => '',
							'id'  => '',
						],
						'photo_alt'        => '',
						'photo_decorative' => 'yes',
						'photo_pos'        => '50% 45%',
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
						'link'             => [ 'url' => '' ],
						'photo'            => [
							'url' => '',
							'id'  => '',
						],
						'photo_alt'        => '',
						'photo_decorative' => 'yes',
						'photo_pos'        => '50% 50%',
					],
				],
			]
		);
		$this->end_controls_section();

		NH_Controls::section( $this, [ 'anchor' => 'homes' ] );
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
		$html .= "\n\t\t" . $this->nh_rule();
		if ( '' !== $h2 ) {
			$html .= "\n\t\t" . $h2;
		}
		$lead = $this->nh_lead( $s );
		if ( '' !== $lead ) {
			$html .= "\n\t\t" . $lead;
		}

		$rows   = '';
		$anchor = sanitize_html_class( self::str( $s, 'anchor' ) );
		$soon   = [ self::str( $s, 'soon_text' ), self::str( $s, 'soon_text_plain' ) ];
		foreach ( self::items( $s, 'homes' ) as $item ) {
			$rows .= $this->row( $item, $anchor, $soon );
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
	 * One row of the index. With a link to the home's page the whole row is one link with a still arrow;
	 * without one (or with a link back to this spread) it is a `div.v3-home__row` without an arrow, and its facts
	 * end with «דף מפורט בקרוב». The ב's room shows the photo or a calm sky.
	 *
	 * @param array    $item   Repeater item.
	 * @param string   $anchor The spread's own anchor (a row link to it leads nowhere new).
	 * @param string[] $soon   The «coming soon» text and its plain twin.
	 */
	private function row( array $item, string $anchor, array $soon ): string {
		$title = self::str( $item, 'title' );
		if ( '' === $title ) {
			return '';
		}

		$tone = self::str( $item, 'tone' );
		$tone = in_array( $tone, self::TONES, true ) ? $tone : 'paper';
		$id   = sanitize_html_class( self::str( $item, 'anchor' ) );
		// a title hidden in plain language hides the whole row (a row link must never lose its name);
		// the texts inside are then rendered without mode classes
		$hide = NH_Text::HIDE === self::str( $item, 'title_plain' );

		$link = $item['link'] ?? '';
		$raw  = is_array( $link ) ? self::str( $link, 'url' ) : ( is_string( $link ) ? trim( $link ) : '' );
		$href = NH_Html::url( $raw );
		// a link back to this spread or to the row itself is no page of the home: the row is then not a link
		$here = array_filter( [ '' !== $anchor ? '#' . $anchor : '', '' !== $id ? '#' . $id : '' ] );
		if ( in_array( $raw, $here, true ) ) {
			$href = '';
		}

		$img   = NH_Controls::image_args( $item, 'photo' );
		$photo = (int) ( $img['photo'] ?? 0 );
		if ( $photo > 0 && ! NH_Picture::exists( $photo ) ) {
			$photo = 0; // a deleted or non-image attachment counts as empty: the room shows the sky
		}
		$room = [
			'photo'      => $photo,
			'sizes'      => self::SIZES,
			'weight'     => self::WEIGHT,
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

		$who = NH_Text::pair(
			self::str( $item, 'who' ),
			$hide ? '' : self::str( $item, 'who_plain' ),
			[
				'tag'         => 'span',
				'class'       => 'v3-home__who',
				'plain_lines' => true,
				'bare'        => false,
			]
		);
		if ( '' !== $href ) {
			$facts = NH_Text::pair(
				self::str( $item, 'facts' ),
				$hide ? '' : self::str( $item, 'facts_plain' ),
				[
					'tag'   => 'span',
					'class' => 'v3-home__facts',
					'bare'  => false,
				]
			);
		} else {
			$facts = self::facts_soon( self::str( $item, 'facts' ), $hide ? '' : self::str( $item, 'facts_plain' ), $soon, $hide );
		}

		$body = "\n\t\t\t\t\t" . NH_Letters::room( 'bet', $room )
			. "\n\t\t\t\t\t" . '<span class="v3-home__body">'
			. "\n\t\t\t\t\t\t" . '<span class="v3-home__title">' . NH_Text::pair( $title, $hide ? '' : self::str( $item, 'title_plain' ) ) . '</span>'
			. ( '' !== $who ? "\n\t\t\t\t\t\t" . $who : '' )
			. ( '' !== $facts ? "\n\t\t\t\t\t\t" . $facts : '' )
			. "\n\t\t\t\t\t</span>";

		$out = "\n\t\t\t<li" . NH_Html::attrs(
			[
				'class' => 'v3-home v3-home--' . $tone . ( $hide ? ' v3-full' : '' ),
				'id'    => '' !== $id ? $id : null,
			]
		) . '>';
		if ( '' !== $href ) {
			$out .= "\n\t\t\t\t" . '<a class="v3-home__link" href="' . $href . '">' . $body
				. "\n\t\t\t\t\t" . NH_Html::icon( 'arrow-fwd', 'v3-home__arrow' )
				. "\n\t\t\t\t</a>";
		} else {
			$out .= "\n\t\t\t\t" . '<div class="v3-home__row">' . $body . "\n\t\t\t\t</div>";
		}
		return $out . "\n\t\t\t</li>";
	}

	/**
	 * The facts of a row without a page, ending with «דף מפורט בקרוב» in both modes (the facts themselves may be
	 * hidden in plain language, the «soon» words never are unless their own twin is "-"):
	 * `<span class="v3-home__facts v3-full">{facts} · <span class="v3-home__soon">{soon}</span></span>` and the
	 * plain twin, or one element without mode class when both modes read the same.
	 *
	 * @param string   $facts The facts.
	 * @param string   $plain Their plain twin ('' = the same, '-' = hidden in plain language).
	 * @param string[] $soon  The «soon» text and its twin.
	 * @param bool     $one   Only one mode (the whole row is full-only): no mode classes.
	 */
	private static function facts_soon( string $facts, string $plain, array $soon, bool $one ): string {
		$line       = static function ( string $text, string $word ): string {
			$text = '' !== $text ? NH_Text::esc( $text ) : '';
			$word = '' !== $word ? '<span class="v3-home__soon">' . NH_Text::esc( $word ) . '</span>' : '';
			return $text . ( '' !== $text && '' !== $word ? ' · ' : '' ) . $word;
		};
		$full_word  = (string) ( $soon[0] ?? '' );
		$plain_word = (string) ( $soon[1] ?? '' );
		$full       = $line( $facts, $full_word );
		if ( $one || ( '' === $plain && '' === $plain_word ) ) {
			return '' !== $full ? '<span class="v3-home__facts">' . $full . '</span>' : '';
		}
		$text = NH_Text::HIDE === $plain ? '' : ( '' !== $plain ? $plain : $facts );
		$word = NH_Text::HIDE === $plain_word ? '' : ( '' !== $plain_word ? $plain_word : $full_word );
		$twin = $line( $text, $word );
		$out  = '' !== $full ? '<span class="v3-home__facts v3-full">' . $full . '</span>' : '';
		$out .= '' !== $twin ? '<span class="v3-home__facts v3-plain">' . $twin . '</span>' : '';
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
}
