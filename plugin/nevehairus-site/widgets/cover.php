<?php
defined( 'ABSPATH' ) || exit;

/**
 * ב׳ · שער דף הבית — the home cover (SPEC §4.1), the reference widget every other widget copies.
 *
 * Target: prototype `index.html` `section#cover`. A still ב (weight 600, research/07 §10.2 item 1) whose room
 * holds the cover photo (the LCP image, eager and preloaded), the H1 in two lines with tesserae, the lead, the
 * visit button and the line «או בטלפון» with the phone number from the settings. One screen, no ruling line,
 * nothing moves.
 *
 * How a widget is built (the pattern to copy):
 * - constants first (ID, deps, keywords); get_title() / get_icon();
 * - register_controls(): content section, images section, … and NH_Controls::section() last; every
 *   visible text through NH_Controls::pair() (a text and its plain-language twin), defaults verbatim
 *   from the prototype;
 * - render(): read settings once with get_settings_for_display(), build the HTML in a string from
 *   escaped parts and core helpers, echo once at the end.
 *
 * @package nevehairus-site
 */
final class NH_Widget_Cover extends NH_Widget_Base {

	public const ID         = 'cover';
	public const STYLE_DEPS = [ 'nh-letters' ];
	public const DYNAMIC    = true; // the phone line reads NH_Settings (a cached copy would keep an old number)
	public const KEYWORDS   = [ 'cover', 'bet', 'שער', 'ב' ];

	/** The one weight of the ב (v3.2: every letter keeps one weight for good). */
	public const WEIGHT = 600;

	/** Default `sizes` of the cover photo (also used by NH_Site::cover_preload()). */
	public const SIZES = '(min-width: 1024px) 30rem, 44vw';

	/**
	 * Panel title.
	 */
	public function get_title() {
		return __( 'ב׳ · שער דף הבית', 'nevehairus-site' );
	}

	/**
	 * Panel icon.
	 */
	public function get_icon() {
		return 'eicon-image-box';
	}

	/**
	 * Controls: תוכן (title lines, their tesserae, the H1's plain twin, lead, button, the phone line),
	 * תמונות (the photo in the ב's room), מקטע.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'nh_content', [ 'label' => __( 'תוכן', 'nevehairus-site' ) ] );

		$this->add_control(
			'title_1',
			[
				'label'       => __( 'כותרת — שורה 1', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'בית.',
				'label_block' => true,
			]
		);
		$this->add_control(
			'title_1_dot',
			[
				'label'   => __( 'הנקודה בסוף שורה 1', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => NH_Controls::dot_options(),
				'default' => '',
			]
		);
		$this->add_control(
			'title_2',
			[
				'label'       => __( 'כותרת — שורה 2', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'לחיים.',
				'label_block' => true,
				'description' => __( 'ריק = כותרת בשורה אחת.', 'nevehairus-site' ),
			]
		);
		$this->add_control(
			'title_2_dot',
			[
				'label'   => __( 'הנקודה בסוף שורה 2', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => NH_Controls::dot_options(),
				'default' => 'clay',
			]
		);
		$this->add_control(
			'title_plain',
			[
				'label'       => __( 'כותרת — בשפה פשוטה', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'rows'        => 3,
				'default'     => "נווה\u{00A0}האירוס הוא בית.",   // the name never breaks (16.11)
				'description' => __( 'הכותרת הראשית של העמוד לא מוסתרת: ריק או מקף = שתי השורות של הכותרת, בלי הנקודות הצבעוניות.', 'nevehairus-site' ),
				'label_block' => true,
			]
		);
		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'בית בנס ציונה לאנשים עם מוגבלות שכלית התפתחותית, גם כשיש התנהגות מאתגרת או אבחנה כפולה.',
				'plain'   => "גרים כאן אנשים עם מוגבלות שכלית התפתחותית.\nהבית נמצא בעיר נס ציונה.\nאפשר לגור אצלנו.\nאפשר לבוא לראות את הבית לפני שמחליטים.",
			]
		);
		NH_Controls::pair(
			$this,
			'cta_text',
			[
				'label'   => __( 'כפתור', 'nevehairus-site' ),
				'default' => 'בואו להכיר — תיאום ביקור',
				'plain'   => 'לקבוע ביקור',
			]
		);
		NH_Controls::link(
			$this,
			'cta_link',
			[
				'label'   => __( 'קישור הכפתור (ריק = בלי כפתור)', 'nevehairus-site' ),
				'default' => '#door',
			]
		);
		self::call_controls( $this );

		$this->end_controls_section();

		$this->start_controls_section( 'nh_images', [ 'label' => __( 'תמונות', 'nevehairus-site' ) ] );

		NH_Controls::image(
			$this,
			'photo',
			[
				'label'      => __( 'התמונה בחדר של ב׳', 'nevehairus-site' ),
				'decorative' => false,
				'alt'        => '',
				'pos'        => '46% 50%',
				'sizes'      => self::SIZES,
			]
		);

		$this->end_controls_section();

		NH_Controls::section( $this, [ 'anchor' => 'cover' ] );
	}

	/**
	 * The phone line under the button (research/07 §10.2 item 1, §3 item 24): `call_label` (pair) and
	 * `show_call` (the page cover has the same two controls).
	 *
	 * @param \Elementor\Widget_Base $w Widget.
	 */
	private static function call_controls( $w ): void {
		$w->add_control(
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
			$w,
			'call_label',
			[
				'label'     => __( 'שורת הטלפון — המילים לפני המספר', 'nevehairus-site' ),
				'default'   => 'או בטלפון:',
				'plain'     => 'אפשר גם להתקשר:',
				'condition' => [ 'show_call' => 'yes' ],
			]
		);
	}

	/**
	 * `<p class="v3-cover__call">{call_label} <a href="tel:…"><span class="nh-ltr">{phone}</span></a></p>`;
	 * '' when switched off or when the settings hold no phone (the page cover prints the same line).
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
	 * Markup of SPEC §4.1 (target: built index.html section#cover).
	 */
	protected function render() {
		$s   = $this->get_settings_for_display();
		$img = NH_Controls::image_args( $s, 'photo' );

		$room = NH_Letters::room(
			'bet',
			[
				'photo'      => $img['photo'],
				'sizes'      => $img['sizes'] ?? self::SIZES,
				'priority'   => true,
				'pos'        => $img['pos'] ?? '50% 50%',
				'weight'     => self::WEIGHT,
				'alt'        => $img['alt'],
				'decorative' => $img['decorative'],
				'class'      => 'v3-cover__bet',
				'field'      => 'sky',
			]
		);

		$lead = NH_Text::block(
			self::str( $s, 'lead' ),
			self::str( $s, 'lead_plain' ),
			[
				'full_class'  => 'v3-cover__lead',
				'plain_class' => 'v3-cover__plain',
			]
		);

		$cta  = '';
		$text = NH_Text::pair( self::str( $s, 'cta_text' ), self::str( $s, 'cta_text_plain' ) );
		$href = NH_Html::url( $s['cta_link'] ?? '' );
		if ( '' !== $text && '' !== $href ) {
			// data-visit-cta: nh-core.js points it at this page's door when there is one (research/07 §5.5)
			$cta = '<p class="v3-cover__cta"><a class="nh-btn nh-btn--primary" href="' . $href . '" data-visit-cta>' . $text
				. NH_Html::icon( 'arrow-fwd', 'nh-btn__icon nh-btn__icon--fwd' ) . '</a></p>';
		}

		$html = $this->nh_section_open( $s, [ 'class' => 'v3-cover v3-spread' ] )
			. '<div class="nh-container v3-cover__grid">'
			. '<div class="v3-cover__letter">' . $room . '</div>'
			. '<div class="v3-cover__text">' . $this->title( $s ) . $lead . $cta . self::call_line( $s ) . '</div>'
			. '</div>'
			. $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * The H1: always the full/plain structure (the CSS lays out the line spans inside .v3-full). A line
	 * whose text is empty is omitted; an empty or "-" plain twin repeats the lines as text (the page's
	 * heading is never hidden).
	 *
	 * @param array $s Settings for display.
	 */
	private function title( array $s ): string {
		$lines = [];
		$texts = [];
		foreach ( [ 'title_1', 'title_2' ] as $key ) {
			$text = trim( self::str( $s, $key ) );
			if ( '' === $text ) {
				continue;
			}
			$texts[] = NH_Text::esc( $text );
			$lines[] = '<span>' . NH_Text::dot( NH_Text::esc( $text ), self::str( $s, $key . '_dot' ) ) . '</span>';
		}
		$plain = trim( self::str( $s, 'title_plain' ) );
		$plain = ( '' === $plain || NH_Text::HIDE === $plain ) ? implode( ' ', $texts ) : NH_Text::esc( $plain );
		if ( ! $lines && '' === $plain ) {
			return '';
		}
		return '<h1 class="v3-cover__title" id="' . esc_attr( $this->nh_uid( 'title' ) ) . '">'
			. ( $lines ? '<span class="v3-full">' . implode( ' ', $lines ) . '</span>' : '' )
			. ( '' !== $plain ? '<span class="v3-plain">' . $plain . '</span>' : '' )
			. '</h1>';
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
