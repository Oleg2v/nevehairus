<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `years` — «כמעט 70 שנה של בית.»: three equal columns; the first number (1957) is the large one
 * (CSS `:first-child`, no class of its own); the word «שמיים» has a still window in its final ם.
 *
 * Prototype: prototype/src/index.html `section#story` (built: prototype/index.html).
 * Spec: plugin/SPEC.md §4.8; research/07 §10.2 item 7 (v3.2: the ruling line, one print size, the print
 * appears once — `data-reveal` — and nothing else moves). CSS: assets/css/widgets/years.css. No JS.
 *
 * The list is always `v3-full` (no twins inside it, SPEC §3.5); `intro_plain` is its plain-language summary.
 *
 * @package nevehairus-site
 */
final class NH_Widget_Years extends NH_Widget_Base {

	public const ID = 'years';

	public const STYLE_DEPS = [ 'nh-letters' ];

	public const KEYWORDS = [ 'years', 'history', 'timeline', 'story', 'שנים', 'היסטוריה', 'סיפור', 'שמיים', '1957' ];

	/** `sizes` of the print (research/07 §8.1: a print is at most 21rem wide). */
	public const SIZES = '(max-width: 23em) calc(100vw - 2.5rem), 21rem';

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'שנים · 1957 ושמיים', 'nevehairus-site' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-calendar';
	}

	/**
	 * Controls: תוכן, שנים (repeater), then מקטע.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'nh_content', [ 'label' => __( 'תוכן', 'nevehairus-site' ) ] );

		NH_Controls::heading(
			$this,
			[
				'default' => 'כמעט 70 שנה של בית.',
				'plain'   => 'הסיפור שלנו',
				'dot'     => '',
			]
		);

		NH_Controls::pair(
			$this,
			'intro',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => '',
				'plain'   => "נווה האירוס נפתח בשנת 1957.\nבשנת 2021 נבנה אגף חדש.\nהיום יש לנו שלושה בתים.",
			]
		);

		$this->add_control(
			'intro_notice',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => esc_html__( 'לרשימת השנים אין נוסח בשפה פשוטה: במצב שפה פשוטה היא מוסתרת, ובמקומה מוצג הנוסח הפשוט של הפתיח — סיכום קצר של הרשימה.', 'nevehairus-site' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'nh_list', [ 'label' => __( 'שנים', 'nevehairus-site' ) ] );

		$r = new \Elementor\Repeater();

		$r->add_control(
			'num',
			[
				'label'       => __( 'שנה או מילה (מספר גדול, דקורטיבי)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);

		$r->add_control(
			'title',
			[
				'label'       => __( 'כותרת', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);

		$r->add_control(
			'text',
			[
				'label'   => __( 'טקסט', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'rows'    => 4,
				'default' => '',
			]
		);

		NH_Controls::image(
			$r,
			'photo',
			[
				'label'      => __( 'תמונה', 'nevehairus-site' ),
				'decorative' => false,
				'alt'        => '',
				'sizes'      => self::SIZES,
			]
		);

		$r->add_control(
			'caption',
			[
				'label'       => __( 'כיתוב התמונה', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);

		$r->add_control(
			'shamayim',
			[
				'label'        => __( 'מילה עם חלון באות ם (כמו „שמיים”)', 'nevehairus-site' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			]
		);

		$r->add_control(
			'shamayim_text',
			[
				'label'       => __( 'תחילת המילה, לפני האות ם (דקורטיבי)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);

		NH_Controls::image(
			$r,
			'shamayim_photo',
			[
				'label'      => __( 'התמונה בחלון של ם (דקורטיבית)', 'nevehairus-site' ),
				'decorative' => true,
				'alt'        => '',
				'pos'        => '72% 30%',
				'sizes'      => '(min-width: 1024px) 8rem, 24vw',
			]
		);

		$this->add_control(
			'years',
			[
				'label'       => __( 'שנים', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'title_field' => '{{ num }} {{ title }}',
				'default'     => [
					self::default_year(
						[
							'num'     => '1957',
							'title'   => 'סנטוריום נס ציונה',
							'text'    => 'אבן הפינה מונחת במקום שיהפוך לנווה האירוס.',
							'caption' => 'החצר במעון, היום.',
						]
					),
					self::default_year(
						[
							'num'           => '2021',
							'title'         => 'אגף חדש, ובו שמיים',
							'text'          => 'חדרים חדשים, מרחבים משותפים ותאורת „שמיים” בתקרה — אגף שתוכנן במיוחד לצורכי הדיירים והדיירות.',
							'shamayim'      => 'yes',
							'shamayim_text' => 'שמיי',
						]
					),
					self::default_year(
						[
							'num'   => 'היום',
							'title' => 'שלושה בתים',
							'text'  => 'במעון, בבתים בקהילה ובמסגרת לצעירים על הרצף האוטיסטי — יותר מ־100 דיירים ודיירות. יותר מ־25,000 בקרים מאז 1957.',
						]
					),
				],
			]
		);

		$this->end_controls_section();

		NH_Controls::section( $this, [ 'anchor' => 'story' ] );
	}

	/**
	 * Markup of index.html `section#story` (SPEC §4.8).
	 */
	protected function render() {
		$s     = $this->get_settings_for_display();
		$list  = isset( $s['years'] ) && is_array( $s['years'] ) ? $s['years'] : [];
		$items = '';
		foreach ( $list as $y ) {
			if ( ! is_array( $y ) || '' === self::to_str( $y['title'] ?? '' ) ) {
				continue;
			}
			$items .= self::year( $y );
		}

		$html = $this->nh_section_open(
			$s,
			[
				'class' => 'v3-years v3-spread',
				'tone'  => 'sand',
			]
		)
			. '<div class="nh-container">'
			. $this->nh_rule()
			. $this->nh_h2( $s )
			. NH_Text::block(
				self::to_str( $s['intro'] ?? '' ),
				self::to_str( $s['intro_plain'] ?? '' ),
				[ 'plain_tag' => 'div' ]
			)
			. ( '' !== $items ? '<ol class="v3-years__list v3-full" role="list">' . $items . '</ol>' : '' )
			. '</div>'
			. $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * One default item of the `years` repeater: every field present, photos empty.
	 *
	 * @param array $v Values that differ from the empty item.
	 * @return array
	 */
	private static function default_year( array $v ): array {
		$none = [
			'url' => '',
			'id'  => '',
		];
		return array_merge(
			[
				'num'                       => '',
				'title'                     => '',
				'text'                      => '',
				'photo'                     => $none,
				'photo_alt'                 => '',
				'photo_decorative'          => '',
				'photo_sizes'               => self::SIZES,
				'caption'                   => '',
				'shamayim'                  => '',
				'shamayim_text'             => '',
				'shamayim_photo'            => $none,
				'shamayim_photo_alt'        => '',
				'shamayim_photo_decorative' => 'yes',
				'shamayim_photo_pos'        => '72% 30%',
				'shamayim_photo_sizes'      => '(min-width: 1024px) 8rem, 24vw',
			],
			$v
		);
	}

	/**
	 * `li.v3-year`: the decorative number, the H3 (with the number for screen readers), the text,
	 * then the print and/or the «שמיים» word. The first item's number is the large one by CSS
	 * (`.v3-year:first-child`), not by a class (research/07 §10.2 item 7).
	 *
	 * @param array $y Repeater item (with a title).
	 * @return string
	 */
	private static function year( array $y ): string {
		$num   = self::to_str( $y['num'] ?? '' );
		$sky   = ( 'yes' === ( $y['shamayim'] ?? '' ) );
		$class = 'v3-year' . ( $sky ? ' v3-year--sky' : '' );

		$out = '<li class="' . $class . '">';
		if ( '' !== $num ) {
			$digits = (bool) preg_match( '/^\d+$/', $num );
			// A number with a symbol or a range ("100+", "1957–1960") keeps its order inside the RTL line (SPEC §4.0, §8.15).
			$isolate = ! $digits && preg_match( '/\d/', $num ) && ! preg_match( '/[\x{0590}-\x{05FF}]/u', $num );
			$out    .= '<p class="v3-year__num' . ( $digits ? '' : ' v3-year__num--word' ) . '" aria-hidden="true">'
				. ( $isolate ? '<span class="nh-ltr">' . esc_html( $num ) . '</span>' : esc_html( $num ) ) . '</p>';
		}
		$out .= '<h3>' . ( '' !== $num ? '<span class="nh-sr-only">' . esc_html( $num ) . ' — </span>' : '' )
			. NH_Text::esc( self::to_str( $y['title'] ?? '' ) ) . '</h3>';

		$text = self::to_str( $y['text'] ?? '' );
		if ( '' !== $text ) {
			$out .= '<p>' . NH_Text::esc( $text ) . '</p>';
		}

		$out .= self::print_figure( $y );

		if ( $sky ) {
			$word = self::to_str( $y['shamayim_text'] ?? '' );
			$out .= '<div class="v3-shamayim" aria-hidden="true">'
				. ( '' !== $word ? '<span>' . esc_html( $word ) . '</span>' : '' )
				. NH_Letters::room( 'mem_final', self::sky_room( $y ) )
				. '</div>';
		}

		return $out . '</li>';
	}

	/**
	 * `figure.v3-print[data-reveal]` for the item's photo; '' without an image (SPEC §4.0).
	 *
	 * @param array $y Repeater item.
	 * @return string
	 */
	private static function print_figure( array $y ): string {
		$img = NH_Controls::image_args( $y, 'photo' );
		$id  = (int) $img['photo'];
		if ( $id <= 0 || ! NH_Picture::exists( $id ) ) {
			return '';
		}
		$o = [
			'alt'        => $img['alt'],
			'decorative' => (bool) $img['decorative'],
			'sizes'      => self::SIZES,
		];
		if ( null !== $img['sizes'] && '' !== trim( (string) $img['sizes'] ) ) {
			$o['sizes'] = trim( (string) $img['sizes'] );
		}
		$picture = NH_Picture::render( $id, $o );
		if ( '' === $picture ) {
			return '';
		}
		$caption = self::to_str( $y['caption'] ?? '' );
		return '<figure class="v3-print" data-reveal>' . $picture
			. ( '' !== $caption ? '<figcaption>' . NH_Text::esc( $caption ) . '</figcaption>' : '' )
			. '</figure>';
	}

	/**
	 * Options of the ם room: always weight 300 in its 300 frame, decorative (the whole word is aria-hidden).
	 *
	 * @param array $y Repeater item.
	 * @return array
	 */
	private static function sky_room( array $y ): array {
		$img  = NH_Controls::image_args( $y, 'shamayim_photo' );
		$id   = (int) $img['photo'];
		$room = [
			'photo'      => ( $id > 0 && NH_Picture::exists( $id ) ) ? $id : 0,
			'weight'     => 300,
			'frame'      => '300',
			'class'      => 'v3-shamayim__mem',
			'decorative' => true,
			'field'      => 'sky',
		];
		if ( null !== $img['sizes'] && '' !== trim( (string) $img['sizes'] ) ) {
			$room['sizes'] = trim( (string) $img['sizes'] );
		}
		if ( null !== $img['pos'] && '' !== trim( (string) $img['pos'] ) ) {
			$room['pos'] = trim( (string) $img['pos'] );
		}
		return $room;
	}

	/**
	 * A setting as a trimmed string ('' for anything that is not a scalar).
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	private static function to_str( $v ): string {
		return is_scalar( $v ) ? trim( (string) $v ) : '';
	}
}
