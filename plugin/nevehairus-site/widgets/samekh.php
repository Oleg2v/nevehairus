<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `samekh` — ס׳ · «יש על מי לסמוך.»: the residents' mandala shown whole in the room of a still ס
 * (weight 380), the circles of support beside it as a plain list. v3.2 (research/07 §10.2 item 6): no rings,
 * no glint, no links in the list.
 *
 * Prototype: prototype/src/index.html `section#support` (built: prototype/index.html).
 * Spec: plugin/SPEC.md §4.7. CSS: assets/css/widgets/samekh.css. No JS.
 *
 * @package nevehairus-site
 */
final class NH_Widget_Samekh extends NH_Widget_Base {

	public const ID = 'samekh';

	public const STYLE_DEPS = [ 'nh-letters' ];

	public const KEYWORDS = [ 'samekh', 'support', 'circles', 'mandala', 'ס', 'סמך', 'מעגלים', 'תמיכה', 'מנדלה' ];

	/** The one weight of the ס (v3.2: every letter keeps one weight for good). */
	public const WEIGHT = 380;

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'ס׳ · יש על מי לסמוך', 'nevehairus-site' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-image';
	}

	/**
	 * Controls: תוכן, תמונות (the work in the letter), מעגלים (repeater), then מקטע.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'nh_content', [ 'label' => __( 'תוכן', 'nevehairus-site' ) ] );

		NH_Controls::heading(
			$this,
			[
				'default' => 'יש על מי לסמוך.',
				'plain'   => 'יש מי שעוזר',
				'dot'     => '',
			]
		);

		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'סביב כל דייר ודיירת יש מעגלים של אנשים — כמו הטבעות בפסיפס שיצרו הדיירים והדיירות.',
				'plain'   => "כל דייר וכל דיירת בוחרים בעצמם.\nויש סביבם אנשים שעוזרים:",
			]
		);

		NH_Controls::pair(
			$this,
			'caption',
			[
				'label'   => __( 'כיתוב התמונה', 'nevehairus-site' ),
				'default' => 'מנדלה בפסיפס — עבודת יד של דיירות ודיירי נווה האירוס.',
				'plain'   => '',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'nh_images', [ 'label' => __( 'תמונות', 'nevehairus-site' ) ] );

		NH_Controls::image(
			$this,
			'photo',
			[
				'label'      => __( 'העבודה בתוך האות ס (מוצגת בשלמותה)', 'nevehairus-site' ),
				'decorative' => false,
				'alt'        => 'טבעות של אבן לבנה, אפורה ואדומה סביב לב של זכוכית צבעונית',
				'sizes'      => '(min-width: 1024px) 28rem, 80vw',
			]
		);

		$this->add_control(
			'work_cx',
			[
				'label'       => __( 'מרכז העבודה: מיקום לרוחב (חלק מרוחב התמונה, 0–1)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 1,
				'step'        => 0.0001,
				'default'     => 0.5,
				'label_block' => true,
			]
		);

		$this->add_control(
			'work_cy',
			[
				'label'       => __( 'מרכז העבודה: מיקום לגובה (חלק מגובה התמונה, 0–1)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'max'         => 1,
				'step'        => 0.0001,
				'default'     => 0.5552,
				'label_block' => true,
			]
		);

		$this->add_control(
			'work_r',
			[
				'label'       => __( 'רדיוס העבודה (חלק מרוחב התמונה)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0.01,
				'max'         => 1,
				'step'        => 0.0001,
				'default'     => 0.4876,
				'label_block' => true,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'nh_list', [ 'label' => __( 'מעגלים', 'nevehairus-site' ) ] );

		$r = new \Elementor\Repeater();

		NH_Controls::pair(
			$r,
			'name',
			[
				'label'   => __( 'שם', 'nevehairus-site' ),
				'default' => '',
			]
		);

		NH_Controls::pair(
			$r,
			'desc',
			[
				'label'   => __( 'תיאור', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => '',
			]
		);

		$this->add_control(
			'circles',
			[
				'label'       => __( 'מעגלים', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'title_field' => '{{ name }}',
				'default'     => [
					self::default_circle( 'הדייר או הדיירת', '-', 'ובחירות משלהם בכל יום.', '' ),
					self::default_circle( 'משפחה וחברים', '', 'ביקורים, ועד הורים פעיל וקשר שוטף.', '-' ),
					self::default_circle( 'צוות הבית', '', 'אם בית ורכזות דירה, צוות קבוע.', '-' ),
					self::default_circle( 'אנשי ונשות מקצוע', '', 'עבודה סוציאלית, ריפוי בעיסוק, רפואה ורפואת שיניים.', '-' ),
					self::default_circle( 'הקהילה בנס ציונה', '', 'תעסוקה, פנאי ודיור בקהילה.', '-' ),
					self::default_circle( 'פיקוח משרד הרווחה והביטחון החברתי', 'משרד הרווחה בודק שהכול בסדר.', '', '' ),
				],
			]
		);

		$this->end_controls_section();

		NH_Controls::section( $this, [ 'anchor' => 'support' ] );
	}

	/**
	 * Markup of index.html `section#support` (SPEC §4.7).
	 */
	protected function render() {
		$s       = $this->get_settings_for_display();
		$img     = NH_Controls::image_args( $s, 'photo' );
		$id      = (int) $img['photo'];
		$photo   = ( $id > 0 && NH_Picture::exists( $id ) ) ? $id : 0;
		$circles = self::parse_circles( $s );

		$room = [
			'weight' => self::WEIGHT,
			'class'  => 'v3-samekh__letter',
		];
		if ( $photo ) {
			// The work is shown whole inside the counter (fit "whole").
			$cx = self::to_num( $s['work_cx'] ?? '', 0.5, 0.0, 1.0 );
			$cy = self::to_num( $s['work_cy'] ?? '', 0.5552, 0.0, 1.0 );
			$r  = self::to_num( $s['work_r'] ?? '', 0.4876, 0.0, 1.0 );
			if ( $r <= 0.0 ) {
				$r = 0.4876;
			}
			$room += [
				'photo'      => $photo,
				'fit'        => 'whole',
				'work'       => [ $cx, $cy, $r ],
				'alt'        => $img['alt'],
				'decorative' => (bool) $img['decorative'],
			];
			if ( null !== $img['sizes'] && '' !== trim( (string) $img['sizes'] ) ) {
				$room['sizes'] = trim( (string) $img['sizes'] );
			}
		} else {
			// No photo: the calm sky in the room.
			$room['field'] = 'sky';
		}

		// The caption describes the work: without the photo there is nothing to caption (as prints, SPEC §4.0).
		$caption = $photo ? NH_Text::pair( self::to_str( $s['caption'] ?? '' ), self::to_str( $s['caption_plain'] ?? '' ) ) : '';

		$html = $this->nh_section_open( $s, [ 'class' => 'v3-samekh v3-spread' ] )
			. '<div class="nh-container v3-samekh__grid">'
			. $this->nh_rule()
			. '<div class="v3-samekh__intro">' . $this->nh_h2( $s ) . $this->nh_lead( $s ) . '</div>'
			// Without the photo the figure holds only the decorative letter and sky: nothing for assistive technology.
			. '<figure class="v3-samekh__art"' . ( $photo ? '' : ' aria-hidden="true"' ) . '>'
			. NH_Letters::room( 'samekh', $room )
			. ( '' !== $caption ? '<figcaption>' . $caption . '</figcaption>' : '' )
			. '</figure>'
			. self::circles_list( $circles )
			. '</div>'
			. $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * One default item of the `circles` repeater (every field present).
	 *
	 * @param string $name       Name (prototype text).
	 * @param string $name_plain Its plain twin ('-' = the whole row is hidden in plain language).
	 * @param string $desc       Description.
	 * @param string $desc_plain Its plain twin.
	 * @return array
	 */
	private static function default_circle( string $name, string $name_plain, string $desc, string $desc_plain ): array {
		return [
			'name'       => $name,
			'name_plain' => $name_plain,
			'desc'       => $desc,
			'desc_plain' => $desc_plain,
		];
	}

	/**
	 * The circles that render (items with a name), in order.
	 *
	 * @param array $s Widget settings.
	 * @return array[] [ name, name_plain, desc, desc_plain ]
	 */
	private static function parse_circles( array $s ): array {
		$out  = [];
		$list = isset( $s['circles'] ) && is_array( $s['circles'] ) ? $s['circles'] : [];
		foreach ( $list as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$name = self::to_str( $item['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$out[] = [
				'name'       => $name,
				'name_plain' => self::to_str( $item['name_plain'] ?? '' ),
				'desc'       => self::to_str( $item['desc'] ?? '' ),
				'desc_plain' => self::to_str( $item['desc_plain'] ?? '' ),
			];
		}
		return $out;
	}

	/**
	 * `ol.v3-circles`, a plain list (the circles are words, not links); a row whose name is hidden in plain
	 * language is full-only as a whole.
	 *
	 * @param array[] $circles Parsed circles.
	 * @return string '' when there are none.
	 */
	private static function circles_list( array $circles ): string {
		if ( ! $circles ) {
			return '';
		}
		$items = '';
		foreach ( $circles as $c ) {
			$full_only = ( NH_Text::HIDE === $c['name_plain'] );
			$inner     = NH_Text::pair(
				$c['name'],
				$full_only ? '' : $c['name_plain'],
				[
					'tag'  => 'b',
					'bare' => false,
				]
			)
				. NH_Text::pair(
					$c['desc'],
					$full_only ? '' : $c['desc_plain'],
					[
						'tag'  => 'span',
						'bare' => false,
					]
				);

			$items .= '<li' . ( $full_only ? ' class="v3-full"' : '' ) . '>' . $inner . '</li>';
		}
		return '<ol class="v3-circles" role="list">' . $items . '</ol>';
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

	/**
	 * A NUMBER setting clamped to [min, max]; the default when empty or not numeric.
	 *
	 * @param mixed $v       Value.
	 * @param float $fallback The value when empty or not numeric.
	 * @param float $min     Minimum.
	 * @param float $max     Maximum.
	 * @return float
	 */
	private static function to_num( $v, float $fallback, float $min, float $max ): float {
		if ( ! is_numeric( $v ) ) {
			return $fallback;
		}
		return max( $min, min( $max, (float) $v ) );
	}
}
