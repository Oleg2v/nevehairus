<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `kaf` — כ׳ · «ידיים שיוצרות.»: the palm of the letter כ holds the chosen work.
 *
 * Prototype: prototype/src/index.html L159–204 (built: prototype/index.html `section#made`).
 * Spec: plugin/SPEC.md §4.6. CSS: assets/css/widgets/kaf.css. JS: assets/js/widgets/kaf.js.
 *
 * The first work is in the palm when the page loads; every work button carries its own image set
 * (`data-src`, `data-srcset`, `data-avif`) and its name (`data-name`) for the swap in kaf.js.
 *
 * @package nevehairus-site
 */
final class NH_Widget_Kaf extends NH_Widget_Base {

	public const ID = 'kaf';

	public const STYLE_DEPS = [ 'nh-letters' ];

	public const KEYWORDS = [ 'kaf', 'crafts', 'works', 'palm', 'כ', 'כף', 'יצירות', 'עבודות יד', 'פסיפס' ];

	/**
	 * Width (px) of the image the work buttons hand to the palm: the prototype's 768 fallback.
	 */
	private const SRC_MAX = 768;

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'כ׳ · ידיים שיוצרות', 'nevehairus-site' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	/**
	 * Controls: תוכן, תמונות (the palm), עבודות (repeater), then מקטע.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'nh_content', [ 'label' => __( 'תוכן', 'nevehairus-site' ) ] );

		NH_Controls::heading(
			$this,
			[
				'default' => 'ידיים שיוצרות.',
				'plain'   => 'עבודות יד',
				'dot'     => 'ink',
			]
		);

		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'פסיפס, תכשיטים, קרמיקה וציור: עבודות שיוצרים הדיירים והדיירות בסדנאות. חלק מהיצירות מוצעות למכירה.',
				'plain'   => "הדיירים והדיירות יוצרים בפסיפס ובקרמיקה.\nהם מכינים כדים, ינשופים ותכשיטים.\nאפשר לקנות חלק מהעבודות.",
			]
		);

		$this->add_control(
			'cap_label',
			[
				'label'       => __( 'הכיתוב לפני שם העבודה שבכף היד', 'nevehairus-site' ),
				'description' => __( 'שורה דקורטיבית, מוסתרת מקוראי מסך. שם העבודה אחריה מתעדכן לפי הבחירה ברשימה.', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'בכף היד:',
				'label_block' => true,
			]
		);

		$this->add_control(
			'works_label',
			[
				'label'       => __( 'שם רשימת העבודות לקוראי מסך (aria-label)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'עבודות מהסדנאות: בחירה בעבודה מציגה אותה בכף היד',
				'label_block' => true,
			]
		);

		NH_Controls::pair(
			$this,
			'credit',
			[
				'label'   => __( 'קרדיט', 'nevehairus-site' ),
				'default' => 'כל העבודות: עבודת יד של דיירות ודיירי נווה האירוס.',
				'plain'   => '',
			]
		);

		NH_Controls::pair(
			$this,
			'button',
			[
				'label'   => __( 'כפתור', 'nevehairus-site' ),
				'default' => 'לגלריית היצירות',
				'plain'   => 'לראות עוד עבודות',
			]
		);

		NH_Controls::link(
			$this,
			'button_link',
			[
				'label'   => __( 'קישור הכפתור', 'nevehairus-site' ),
				'default' => '#made',
			]
		);

		$this->add_control(
			'external_sr',
			[
				'label'       => __( 'תוספת לקוראי מסך כשהכפתור מוביל לאתר חיצוני', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '(נפתח באתר חיצוני)',
				'label_block' => true,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'nh_images', [ 'label' => __( 'תמונות', 'nevehairus-site' ) ] );

		$this->add_control(
			'palm_notice',
			[
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => esc_html__( 'בכף היד מוצגת תמונת העבודה הראשונה ברשימה. התמונה דקורטיבית: שם העבודה כתוב ברשימה.', 'nevehairus-site' ),
			]
		);

		$this->add_control(
			'palm_pos',
			[
				'label'       => __( 'מיקוד התמונה בכף היד (object-position, למשל 50% 42%)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '50% 42%',
				'label_block' => true,
			]
		);

		$this->add_control(
			'palm_sizes',
			[
				'label'       => __( 'רוחב התמונה בכף היד (sizes)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '(min-width: 1024px) 24rem, 60vw',
				'label_block' => true,
			]
		);

		$this->add_control(
			'weight',
			[
				'label'   => __( 'עובי האות כ (300–800)', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 300,
				'max'     => 800,
				'step'    => 10,
				'default' => 440,
			]
		);

		$this->end_controls_section();

		$this->start_controls_section( 'nh_list', [ 'label' => __( 'עבודות', 'nevehairus-site' ) ] );

		$r = new \Elementor\Repeater();

		NH_Controls::pair(
			$r,
			'name',
			[
				'label'   => __( 'שם העבודה', 'nevehairus-site' ),
				'default' => '',
			]
		);

		NH_Controls::image(
			$r,
			'photo',
			[
				'label'      => __( 'תמונה', 'nevehairus-site' ),
				'decorative' => true,
				'alt'        => '',
			]
		);

		$r->add_control(
			'swatches_label',
			[
				'label'       => __( 'שם רשימת הצבעים לקוראי מסך (aria-label)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
			]
		);

		NH_Controls::lines(
			$r,
			'swatches',
			[
				'label'   => __( 'צבעים מהעבודה: בכל שורה קוד צבע, רווח ושם הצבע (למשל: #213546 כחול עמוק)', 'nevehairus-site' ),
				'default' => '',
			]
		);

		$this->add_control(
			'works',
			[
				'label'       => __( 'עבודות', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'title_field' => '{{ name }}',
				'default'     => [
					self::default_work( 'כד פסיפס כחול־ירוק', 'צבעים מהכד', "#213546 כחול עמוק — שפת הכד\n#49627F כחול אבן\n#7B8DAC תכלת זכוכית" ),
					self::default_work( 'כד פסיפס בגוני זהב', 'צבעים מהכד', "#49331B חום כהה — הפס התחתון\n#6D5B44 חום חם\n#796D53 זהב עמום" ),
					self::default_work( 'כד פסיפס לבן עם פרח', 'צבעים מהכד', "#413C32 חום־אפור — שפת הכד\n#9C9772 זית בהיר\n#9DA0A3 אפור אבן" ),
					self::default_work( 'ינשופים, חמסות ותליונים', 'צבעים מהינשופים', "#E0B592 אפרסק\n#7EA49A ירוק ים\n#7D5C57 חום אדמה" ),
				],
			]
		);

		$this->end_controls_section();

		NH_Controls::section(
			$this,
			[
				'anchor'  => 'made',
				'running' => 'ידיים שיוצרות',
			]
		);
	}

	/**
	 * Markup of index.html `section#made` (SPEC §4.6).
	 */
	protected function render() {
		$s     = $this->get_settings_for_display();
		$works = self::parse_works( $s );
		$first = $works ? $works[0] : null;

		// The palm: the first work's photo, always decorative (the name is in the list); a calm sky without one.
		$room = [
			'photo'      => $first ? $first['photo'] : 0,
			'weight'     => self::to_num( $s['weight'] ?? '', 440.0, 300.0, 800.0 ),
			'class'      => 'v3-kaf__palm',
			'decorative' => true,
			'field'      => 'sky',
		];
		$sizes = self::to_str( $s['palm_sizes'] ?? '' );
		if ( '' !== $sizes ) {
			$room['sizes'] = $sizes;
		}
		$pos = self::to_str( $s['palm_pos'] ?? '' );
		if ( '' !== $pos ) {
			$room['pos'] = $pos;
		}

		$text = $this->nh_h2( $s ) . $this->nh_lead( $s );

		if ( $first ) {
			$cap   = self::to_str( $s['cap_label'] ?? '' );
			$text .= '<p class="v3-kaf__cap" aria-hidden="true">' . ( '' !== $cap ? esc_html( $cap ) . ' ' : '' )
				. '<b data-work-name>' . esc_html( $first['name'] ) . '</b></p>';
			$text .= self::works_list( $s, $works );
		}

		$text .= NH_Text::block(
			self::to_str( $s['credit'] ?? '' ),
			self::to_str( $s['credit_plain'] ?? '' ),
			[
				'full_class'  => 'v3-credit',
				'plain_tag'   => 'p',
				'plain_class' => 'v3-credit',
				'plain_lines' => null,
			]
		);
		$text .= self::button( $s );

		$html = $this->nh_section_open(
			$s,
			[
				'class' => 'v3-kaf v3-spread',
				'tone'  => 'citrus',
			]
		)
			. '<div class="nh-container v3-kaf__grid">'
			. '<div class="v3-kaf__letter" data-works>' . NH_Letters::room( 'kaf', $room ) . '</div>'
			. '<div class="v3-kaf__text">' . $text . '</div>'
			. '</div>'
			. $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * One default item of the `works` repeater (every field present, photo empty).
	 *
	 * @param string $name     Work name (prototype text).
	 * @param string $label    aria-label of its colour list.
	 * @param string $swatches Lines "#RRGGBB colour name".
	 * @return array
	 */
	private static function default_work( string $name, string $label, string $swatches ): array {
		return [
			'name'             => $name,
			'name_plain'       => '',
			'photo'            => [
				'url' => '',
				'id'  => '',
			],
			'photo_alt'        => '',
			'photo_decorative' => 'yes',
			'swatches_label'   => $label,
			'swatches'         => $swatches,
		];
	}

	/**
	 * The works that render: items with a name, in order; the photo only if it is an image attachment.
	 *
	 * @param array $s Widget settings.
	 * @return array[] [ name, plain, photo (int, 0 = none), label, swatches ]
	 */
	private static function parse_works( array $s ): array {
		$out  = [];
		$list = isset( $s['works'] ) && is_array( $s['works'] ) ? $s['works'] : [];
		foreach ( $list as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$name = self::to_str( $item['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$img   = NH_Controls::image_args( $item, 'photo' );
			$id    = (int) $img['photo'];
			$out[] = [
				'name'     => $name,
				'plain'    => self::to_str( $item['name_plain'] ?? '' ),
				'photo'    => ( $id > 0 && NH_Picture::exists( $id ) ) ? $id : 0,
				'label'    => self::to_str( $item['swatches_label'] ?? '' ),
				'swatches' => self::to_str( $item['swatches'] ?? '' ),
			];
		}
		return $out;
	}

	/**
	 * `ol.v3-works`: one pressed button per work, each followed by its colour credits.
	 *
	 * @param array   $s     Widget settings.
	 * @param array[] $works Parsed works (at least one).
	 * @return string
	 */
	private static function works_list( array $s, array $works ): string {
		$items = '';
		foreach ( $works as $i => $w ) {
			$on = ( 0 === $i );
			// A name hidden in plain language ("-") would leave an empty, unnamed button: the whole row is full-only instead.
			$full_only = ( NH_Text::HIDE === $w['plain'] );

			$attrs = ' type="button" class="v3-work' . ( $on ? ' is-on' : '' ) . '" aria-pressed="' . ( $on ? 'true' : 'false' ) . '"'
				. ' data-work="' . ( $w['photo'] > 0 ? (string) $w['photo'] : '' ) . '"'
				. ' data-name="' . esc_attr( $w['name'] ) . '"';
			if ( $w['photo'] > 0 ) {
				$src    = NH_Picture::src( $w['photo'], self::SRC_MAX );
				$srcset = NH_Picture::srcset( $w['photo'] );
				$avif   = NH_Picture::avif_srcset( $w['photo'] );
				if ( '' !== $src ) {
					$attrs .= ' data-src="' . esc_url( $src ) . '"';
				}
				if ( '' !== $srcset ) {
					$attrs .= ' data-srcset="' . esc_attr( $srcset ) . '"';
				}
				if ( '' !== $avif ) {
					$attrs .= ' data-avif="' . esc_attr( $avif ) . '"';
				}
			}

			// Plain text in the button: it is also the text of data-name and of the caption in the palm.
			$name = NH_Text::pair( $w['name'], $full_only ? '' : $w['plain'], [ 'esc' => 'text' ] );

			$items .= '<li' . ( $full_only ? ' class="v3-full"' : '' ) . '>'
				. '<button' . $attrs . '>' . $name . '</button>'
				. self::swatches( $w['swatches'], $w['label'] )
				. '</li>';
		}

		$label = self::to_str( $s['works_label'] ?? '' );
		return '<ol class="v3-works" role="list"' . ( '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . '>'
			. $items . '</ol>';
	}

	/**
	 * `ul.v3-swatches` from lines "#RRGGBB colour name"; invalid colours and lines without a name are skipped.
	 *
	 * @param string $lines Textarea value.
	 * @param string $label aria-label of the list.
	 * @return string '' when no line is valid.
	 */
	private static function swatches( string $lines, string $label ): string {
		$li = '';
		foreach ( NH_Text::lines( $lines ) as $line ) {
			// Right-to-left text fields may add direction marks or no-break spaces around the colour code.
			$line = (string) preg_replace( '/^[\s\x{00A0}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', '', $line );
			if ( ! preg_match( '/^(\S+)[\s\x{00A0}\x{200E}\x{200F}]+(.+)$/u', $line, $m ) ) {
				continue;
			}
			$hex  = sanitize_hex_color( $m[1] );
			$name = trim( $m[2] );
			if ( ! $hex || '' === $name ) {
				continue;
			}
			$li .= '<li><i style="--c:' . esc_attr( $hex ) . '"></i>' . esc_html( $name ) . '</li>';
		}
		if ( '' === $li ) {
			return '';
		}
		return '<ul class="v3-swatches" role="list"' . ( '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . '>'
			. $li . '</ul>';
	}

	/**
	 * The ink button under the credit; omitted without a text or a link.
	 *
	 * @param array $s Widget settings.
	 * @return string
	 */
	private static function button( array $s ): string {
		$text = NH_Text::pair( self::to_str( $s['button'] ?? '' ), self::to_str( $s['button_plain'] ?? '' ) );
		$url  = NH_Html::url( $s['button_link'] ?? '' );
		if ( '' === $text || '' === $url ) {
			return '';
		}
		$external = NH_Html::is_external( $url );
		$sr       = self::to_str( $s['external_sr'] ?? '' );
		return '<p><a class="nh-btn nh-btn--ink" href="' . $url . '"' . ( $external ? ' rel="noopener"' : '' ) . '>'
			. $text
			. ( $external && '' !== $sr ? '<span class="nh-sr-only"> ' . esc_html( $sr ) . '</span>' : '' )
			. NH_Html::icon( 'arrow-fwd', 'nh-btn__icon nh-btn__icon--fwd' )
			. '</a></p>';
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
	 * @param float $default Default.
	 * @param float $min     Minimum.
	 * @param float $max     Maximum.
	 * @return float
	 */
	private static function to_num( $v, float $default, float $min, float $max ): float {
		if ( ! is_numeric( $v ) ) {
			return $default;
		}
		return max( $min, min( $max, (float) $v ) );
	}
}
