<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `page-cover` — ב׳ · שער עמוד (work package "residence").
 *
 * The cover of a house page: breadcrumbs («דף הבית ‹ הבתים שלנו ‹ מעון ראשי»), the still ב (weight 600) with a
 * photo in its room, the H1 (it repeats the last crumb), the lead, the visit button and the line «או בטלפון»
 * with the phone number from the settings. v3.2 (research/07 §10.3 item 1): no ruling line, no page
 * transition, nothing moves. Target: prototype `program-main-home.html` `section#cover`.
 * It reuses the cover's CSS (`nh-w-cover`, decision D5).
 *
 * @package nevehairus-site
 */
final class NH_Widget_Page_Cover extends NH_Widget_Base {

	public const ID         = 'page-cover';
	public const STYLE_DEPS = [ 'nh-letters', 'nh-w-cover' ];
	public const DYNAMIC    = true; // the phone line reads NH_Settings (a cached copy would keep an old number)
	public const KEYWORDS   = [ 'cover', 'page', 'bet', 'breadcrumbs', 'שער', 'עמוד', 'ב', 'נתיב ניווט' ];

	/** `sizes` of the photo in the room (the room is 22rem wide from 1024px). */
	private const SIZES = '(min-width: 1024px) 22rem, 44vw';

	/** The one weight of the ב (v3.2: every letter keeps one weight for good). */
	public const WEIGHT = 600;

	/** Accessible name of the breadcrumb navigation (also its fallback when the field is emptied). */
	private const CRUMBS_LABEL = 'נתיב ניווט';

	public function get_title() {
		return __( 'ב׳ · שער עמוד', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-image-box';
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

		$this->add_control(
			'title_1',
			[
				'label'       => __( 'כותרת ראשית — שורה ראשונה', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'מעון ראשי —',
				'label_block' => true,
			]
		);
		$this->add_control(
			'title_1_dot',
			[
				'label'   => __( 'הנקודה בסוף השורה הראשונה', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => NH_Controls::dot_options(),
				'default' => '',
			]
		);
		$this->add_control(
			'title_2',
			[
				'label'       => __( 'כותרת ראשית — שורה שנייה', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'בית לחיים.',
				'label_block' => true,
			]
		);
		$this->add_control(
			'title_2_dot',
			[
				'label'   => __( 'הנקודה בסוף השורה השנייה', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => NH_Controls::dot_options(),
				'default' => '',
			]
		);
		$this->add_control(
			'title_plain',
			[
				'label'       => __( 'כותרת ראשית — בשפה פשוטה', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'default'     => 'המעון הראשי בנס ציונה',
				'description' => __( 'ריק = שתי השורות של הכותרת, כטקסט רגיל. הכותרת הראשית מוצגת תמיד, גם בשפה פשוטה.', 'nevehairus-site' ),
				'label_block' => true,
			]
		);

		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 4,
				'default' => 'בית בנס ציונה לבוגרים ובוגרות עם מוגבלות שכלית התפתחותית, גם עם אבחנה כפולה או התנהגות מאתגרת. צוות קבוע, טיפול מקצועי ויום מלא בעשייה.',
				'plain'   => implode(
					"\n",
					[
						'גרים כאן גברים ונשים עם מוגבלות שכלית התפתחותית.',
						'הצוות קבוע, ומכיר את כולם.',
						'אפשר לבוא לראות את המקום.',
					]
				),
			]
		);
		NH_Controls::pair(
			$this,
			'cta_text',
			[
				'label'   => __( 'כפתור', 'nevehairus-site' ),
				'default' => 'תיאום ביקור',
				'plain'   => 'לקבוע ביקור',
			]
		);
		NH_Controls::link(
			$this,
			'cta_link',
			[
				'label'   => __( 'קישור הכפתור', 'nevehairus-site' ),
				'default' => '#visit',
			]
		);
		$this->add_control(
			'show_call',
			[
				'label'        => __( 'שורת הטלפון מתחת לכפתור', 'nevehairus-site' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'המספר נלקח מההגדרות: הגדרות ← נווה האירוס.', 'nevehairus-site' ),
			]
		);
		NH_Controls::pair(
			$this,
			'call_label',
			[
				'label'     => __( 'שורת הטלפון — המילים לפני המספר', 'nevehairus-site' ),
				'default'   => 'או בטלפון:',
				'plain'     => 'אפשר גם להתקשר:',
				'condition' => [ 'show_call' => 'yes' ],
			]
		);

		$this->end_controls_section();

		/* ---------- תמונות ---------- */
		$this->start_controls_section(
			'nh_images',
			[
				'label' => __( 'תמונות', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		NH_Controls::image(
			$this,
			'photo',
			[
				'label'      => __( 'התמונה בחדר של ה־ב׳', 'nevehairus-site' ),
				'decorative' => false,
				'alt'        => 'תקרת „שמיים” באגף החדש של המעון: לוח תאורה עם עננים',
				'pos'        => '55% 45%',
				'sizes'      => self::SIZES,
			]
		);
		$this->end_controls_section();

		/* ---------- נתיב ניווט (repeater) ---------- */
		$this->start_controls_section(
			'nh_crumbs',
			[
				'label' => __( 'נתיב ניווט', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'crumbs_label',
			[
				'label'       => __( 'שם הניווט (לקוראי מסך)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => self::CRUMBS_LABEL,
				'label_block' => true,
			]
		);

		$crumb = new \Elementor\Repeater();
		NH_Controls::pair(
			$crumb,
			'label',
			[
				'label'   => __( 'שם הדף', 'nevehairus-site' ),
				'default' => '',
			]
		);
		NH_Controls::link(
			$crumb,
			'link',
			[
				'label'   => __( 'קישור (ריק = העמוד הנוכחי)', 'nevehairus-site' ),
				'default' => '',
			]
		);
		$this->add_control(
			'crumbs',
			[
				'label'       => __( 'הדפים בנתיב', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $crumb->get_controls(),
				'default'     => [
					[
						'label' => 'דף הבית',
						'link'  => [ 'url' => home_url( '/' ) ],
					],
					[
						'label' => 'הבתים שלנו',
						'link'  => [ 'url' => home_url( '/#homes' ) ],
					],
					[
						'label' => 'מעון ראשי',
						'link'  => [ 'url' => '' ],
					],
				],
				'title_field' => '{{ label }}',
			]
		);

		$this->end_controls_section();

		/* ---------- מקטע (always last) ---------- */
		NH_Controls::section( $this, [ 'anchor' => 'cover' ] );
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$h1 = $this->title_html( $s );

		$html  = $this->nh_section_open(
			$s,
			[
				'class'      => 'v3-cover v3-cover--page v3-spread',
				'labelledby' => '' !== $h1 ? 'title' : null,
			]
		);
		$html .= '<div class="nh-container v3-cover__grid">';
		$html .= $this->crumbs_html( $s );
		$html .= '<div class="v3-cover__letter">' . $this->room_html( $s ) . '</div>';
		$html .= '<div class="v3-cover__text">';
		$html .= $h1;
		$html .= NH_Text::block(
			self::str( $s, 'lead' ),
			self::str( $s, 'lead_plain' ),
			[
				'full_class'  => 'v3-cover__lead',
				'plain_class' => 'v3-cover__plain',
			]
		);
		$html .= $this->cta_html( $s );
		$html .= self::call_line( $s );
		$html .= '</div>';
		$html .= '</div>';
		$html .= $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * `<nav class="nh-crumbs v3-crumbs">`: one `li` per item with a name; an item without a link is
	 * the current page (`<span aria-current="page">`). No items, no nav.
	 */
	private function crumbs_html( array $s ): string {
		$items = '';
		foreach ( (array) ( $s['crumbs'] ?? [] ) as $crumb ) {
			if ( ! is_array( $crumb ) ) {
				continue;
			}
			$label = self::str( $crumb, 'label' );
			if ( '' === $label ) {
				continue;
			}
			$text = NH_Text::pair( $label, self::str( $crumb, 'label_plain' ) );
			$url  = NH_Html::url( $crumb['link'] ?? '' );

			$items .= '' !== $url
				? '<li><a href="' . $url . '">' . $text . '</a></li>'
				: '<li><span aria-current="page">' . $text . '</span></li>';
		}
		if ( '' === $items ) {
			return '';
		}

		$name = self::str( $s, 'crumbs_label' );
		if ( '' === $name ) {
			$name = self::CRUMBS_LABEL;
		}

		return '<nav class="nh-crumbs v3-crumbs" aria-label="' . esc_attr( $name ) . '"><ol>' . $items . '</ol></nav>';
	}

	/**
	 * The ב with the photo in its room; without a photo the room shows the calm sky field.
	 */
	private function room_html( array $s ): string {
		$img   = NH_Controls::image_args( $s, 'photo' );
		$sizes = trim( (string) ( $img['sizes'] ?? '' ) );
		$pos   = trim( (string) ( $img['pos'] ?? '' ) );

		$o = [
			'photo'      => (int) ( $img['photo'] ?? 0 ),
			'sizes'      => '' !== $sizes ? $sizes : self::SIZES,
			'priority'   => true,
			'weight'     => self::WEIGHT,
			'alt'        => $img['alt'] ?? null,
			'decorative' => ! empty( $img['decorative'] ),
			'class'      => 'v3-cover__bet',
			'field'      => 'sky',
		];
		if ( '' !== $pos ) {
			$o['pos'] = $pos;
		}

		return NH_Letters::room( 'bet', $o );
	}

	/**
	 * The H1: the two title lines (each a `span`, the last "." drawn as a tessera) inside `.v3-full`,
	 * then the plain twin. The H1 is never hidden: an empty (or "-") twin repeats the lines as text.
	 */
	private function title_html( array $s ): string {
		$lines = [];
		foreach ( [ 'title_1', 'title_2' ] as $key ) {
			$text = self::str( $s, $key );
			if ( '' === $text ) {
				continue;
			}
			$lines[] = '<span>' . NH_Text::dot( NH_Text::esc( $text ), self::str( $s, $key . '_dot' ) ) . '</span>';
		}

		$plain = self::str( $s, 'title_plain' );
		if ( '' === $plain || NH_Text::HIDE === $plain ) {
			$plain = trim( self::str( $s, 'title_1' ) . ' ' . self::str( $s, 'title_2' ) );
		}
		if ( ! $lines && '' === $plain ) {
			return '';
		}

		$open = '<h1 class="v3-cover__title v3-cover__title--page" id="' . esc_attr( $this->nh_uid( 'title' ) ) . '">';
		if ( ! $lines ) {
			return $open . NH_Text::esc( $plain ) . '</h1>';
		}

		return $open . '<span class="v3-full">' . implode( ' ', $lines ) . '</span><span class="v3-plain">' . NH_Text::esc( $plain ) . '</span></h1>';
	}

	/**
	 * The visit button; omitted without a text or a link.
	 */
	private function cta_html( array $s ): string {
		$text = self::str( $s, 'cta_text' );
		$url  = NH_Html::url( $s['cta_link'] ?? '' );
		if ( '' === $text || '' === $url ) {
			return '';
		}

		// data-visit-cta: nh-core.js points it at this page's door when there is one (research/07 §5.5)
		return '<p class="v3-cover__cta"><a class="nh-btn nh-btn--primary" href="' . $url . '" data-visit-cta>'
			. NH_Text::pair( $text, self::str( $s, 'cta_text_plain' ) )
			. NH_Html::icon( 'arrow-fwd', 'nh-btn__icon nh-btn__icon--fwd' )
			. '</a></p>';
	}

	/**
	 * `<p class="v3-cover__call">{call_label} <a href="tel:…"><span class="nh-ltr">{phone}</span></a></p>`
	 * (the same line as the home cover's); '' when switched off or when the settings hold no phone.
	 *
	 * @param array $s Settings for display.
	 */
	private static function call_line( array $s ): string {
		if ( 'yes' !== ( $s['show_call'] ?? '' ) ) {
			return '';
		}
		$phone   = NH_Settings::phone();
		$display = trim( (string) ( $phone['display'] ?? '' ) );
		$tel     = trim( (string) ( $phone['tel'] ?? '' ) );
		if ( '' === $display || '' === $tel ) {
			return '';
		}
		$label = NH_Text::pair( self::str( $s, 'call_label' ), self::str( $s, 'call_label_plain' ) );
		return '<p class="v3-cover__call">' . ( '' !== $label ? $label . ' ' : '' )
			. '<a href="' . esc_url( NH_Html::tel( $tel ) ) . '">' . NH_Html::ltr( $display ) . '</a></p>';
	}

	/**
	 * A text setting as a trimmed string ('' when missing or not scalar).
	 */
	private static function str( array $a, string $key ): string {
		return isset( $a[ $key ] ) && is_scalar( $a[ $key ] ) ? trim( (string) $a[ $key ] ) : '';
	}
}
