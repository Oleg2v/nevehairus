<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `gallery` — גלריה ולייטבוקס (work package "residence").
 *
 * Square-cut prints in a fixed mosaic; each opens the one native `<dialog id="nh-lightbox">`
 * of the page (queued once for `wp_footer` through `NH_Site::footer_once()`).
 * Target: prototype `program-main-home.html` `section#inside` + `partials/lightbox.html`.
 *
 * DYNAMIC is true on purpose: `render()` queues the lightbox dialog for the footer, and with
 * Elementor's element cache a static widget's `render()` does not run on cached page views,
 * so the dialog would be missing there (see the package report).
 *
 * @package nevehairus-site
 */
final class NH_Widget_Gallery extends NH_Widget_Base {

	public const ID       = 'gallery';
	public const DYNAMIC  = true;
	public const KEYWORDS = [ 'gallery', 'lightbox', 'photos', 'images', 'גלריה', 'לייטבוקס', 'תמונות' ];

	/** `sizes` by position in the mosaic (fixed by CSS); the 7th image on is sized like the 1st. */
	private const SIZES = [
		'(min-width: 900px) 34vw, 46vw',
		'(min-width: 900px) 28vw, 46vw',
		'(min-width: 900px) 22vw, 46vw',
		'(min-width: 900px) 22vw, 46vw',
		'(min-width: 900px) 28vw, 46vw',
		'(min-width: 900px) 16vw, 92vw',
	];

	/**
	 * Screen-reader texts of the buttons and the dialog (control defaults; also used when a field
	 * is emptied, so no button is ever left without a name).
	 */
	private const TEXTS = [
		'zoom_label' => 'הגדלת התמונה',
		'lb_label'   => 'גלריית תמונות',
		'lb_count'   => 'תמונה {n} מתוך {total}',
		'lb_close'   => 'סגירת הגלריה',
		'lb_prev'    => 'התמונה הקודמת',
		'lb_next'    => 'התמונה הבאה',
	];

	public function get_title() {
		return __( 'גלריה ולייטבוקס', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-gallery-justified';
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
				'default' => 'האגף החדש.',
				'plain'   => '',
				'dot'     => '',
			]
		);
		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 4,
				'default' => 'את האגף החדש תכננו אדריכליות במיוחד בשביל הדיירים והדיירות: קו עיצובי נעים ורגוע, ותאורת „שמיים” בתקרה. לחצו על תמונה כדי להגדיל אותה.',
				'plain'   => implode(
					"\n",
					[
						'תמונות מהאגף החדש.',
						'לחצו על תמונה כדי לראות אותה גדולה.',
					]
				),
			]
		);

		$this->end_controls_section();

		/* ---------- תמונות (repeater) ---------- */
		$this->start_controls_section(
			'nh_images',
			[
				'label' => __( 'תמונות', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$image = new \Elementor\Repeater();
		NH_Controls::image(
			$image,
			'image',
			[
				'label'      => __( 'תמונה', 'nevehairus-site' ),
				'decorative' => false,
				'alt'        => '',
			]
		);
		NH_Controls::pair(
			$image,
			'caption',
			[
				'label'   => __( 'כיתוב בגלריה הגדולה', 'nevehairus-site' ),
				'default' => '',
			]
		);
		$this->add_control(
			'images',
			[
				'label'       => __( 'התמונות', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $image->get_controls(),
				'default'     => [
					[ 'caption' => 'פינת האוכל המשותפת.' ],
					[ 'caption' => 'תקרת „שמיים” — לוחות תאורה עם עננים.' ],
					[ 'caption' => 'חדר מגורים לשניים.' ],
					[ 'caption' => 'הסלון המשותף.' ],
					[ 'caption' => 'מסדרון האגף החדש, עם תאורת „שמיים”.' ],
					[ 'caption' => 'החצר במעון.' ],
				],
				'title_field' => '{{ caption }}',
				'description' => __( 'הפריסה קבועה לשש תמונות: הראשונה גדולה, השישית צרה. פריט בלי תמונה לא מוצג.', 'nevehairus-site' ),
			]
		);

		$this->end_controls_section();

		/* ---------- לייטבוקס וקוראי מסך ---------- */
		$this->start_controls_section(
			'nh_lightbox',
			[
				'label' => __( 'לייטבוקס וקוראי מסך', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$labels = [
			'zoom_label' => __( 'טקסט לקוראי מסך בכל תמונה', 'nevehairus-site' ),
			'lb_label'   => __( 'שם חלון הגלריה', 'nevehairus-site' ),
			'lb_count'   => __( 'מונה התמונות ({n} = מספר התמונה, {total} = כמה תמונות יש)', 'nevehairus-site' ),
			'lb_close'   => __( 'כפתור הסגירה', 'nevehairus-site' ),
			'lb_prev'    => __( 'כפתור התמונה הקודמת', 'nevehairus-site' ),
			'lb_next'    => __( 'כפתור התמונה הבאה', 'nevehairus-site' ),
		];
		foreach ( $labels as $key => $label ) {
			$this->add_control(
				$key,
				[
					'label'       => $label,
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => self::TEXTS[ $key ],
					'label_block' => true,
				]
			);
		}

		$this->end_controls_section();

		/* ---------- מקטע (always last) ---------- */
		NH_Controls::section(
			$this,
			[
				'anchor'  => 'inside',
				'running' => 'האגף החדש',
			]
		);
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$h2 = $this->nh_h2( $s );

		$items = $this->items_html( $s );

		$html  = $this->nh_section_open(
			$s,
			[
				'class'      => 'v3-inside v3-spread',
				'tone'       => 'sand',
				'labelledby' => '' !== $h2 ? 'title' : null,
			]
		);
		$html .= '<div class="nh-container">';
		$html .= $this->nh_rule();
		$html .= $h2;
		$html .= $this->nh_lead( $s, [ 'plain_class' => 'v3-lead' ] );
		if ( '' !== $items ) {
			$html .= '<ul class="nh-gallery v3-gallery" role="list" data-lightbox>' . $items . '</ul>';

			$texts = [];
			foreach ( [ 'lb_label', 'lb_count', 'lb_close', 'lb_prev', 'lb_next' ] as $key ) {
				$texts[ $key ] = self::text( $s, $key );
			}
			NH_Site::footer_once(
				'lightbox',
				static function () use ( $texts ) {
					return self::lightbox_html( $texts );
				}
			);
		}
		$html .= '</div>';
		$html .= $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * One `li` per item with an image: a button that carries the large image and the captions
	 * for the lightbox. Items without an image (or with a missing attachment) are skipped.
	 */
	private function items_html( array $s ): string {
		$zoom  = self::text( $s, 'zoom_label' );
		$items = '';
		$n     = 0;
		foreach ( (array) ( $s['images'] ?? [] ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$img = NH_Controls::image_args( $item, 'image' );
			$id  = (int) ( $img['photo'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$picture = NH_Picture::render(
				$id,
				[
					'sizes'      => self::SIZES[ $n ] ?? self::SIZES[0],
					'alt'        => $img['alt'] ?? null,
					'decorative' => ! empty( $img['decorative'] ),
				]
			);
			if ( '' === $picture ) {
				continue;
			}
			++$n;

			$caption = self::str( $item, 'caption' );
			$plain   = self::str( $item, 'caption_plain' );
			$attrs   = ' data-full="' . esc_url( NH_Picture::src( $id, 1440 ) ) . '"'
				. ' data-srcset="' . esc_attr( NH_Picture::srcset( $id ) ) . '"'
				. ' data-caption="' . esc_attr( $caption ) . '"';
			if ( '' !== $plain ) {
				// "-" = no caption in plain language: the attribute is present and empty.
				$plain  = NH_Text::HIDE === $plain ? '' : implode( ' ', NH_Text::lines( $plain ) );
				$attrs .= ' data-caption-plain="' . esc_attr( $plain ) . '"';
			}

			$items .= '<li><button type="button"' . $attrs . '>'
				. $picture
				. '<span class="nh-gallery__zoom" aria-hidden="true">' . NH_Html::icon( 'expand', '', false ) . '</span>'
				. '<span class="nh-sr-only">' . esc_html( $zoom ) . '</span>'
				. '</button></li>';
		}

		return $items;
	}

	/**
	 * `partials/lightbox.html` with the widget's texts. The count line is filled by `gallery.js`
	 * from `data-count` when the dialog opens.
	 *
	 * @param array $t Texts: lb_label, lb_count, lb_close, lb_prev, lb_next.
	 */
	private static function lightbox_html( array $t ): string {
		return '<dialog class="nh-lightbox" id="nh-lightbox" aria-label="' . esc_attr( $t['lb_label'] ) . '" data-count="' . esc_attr( $t['lb_count'] ) . '">'
			. '<div class="nh-lightbox__inner">'
			. '<div class="nh-lightbox__bar">'
			. '<p class="nh-lightbox__count"></p>'
			. '<button class="nh-lightbox__btn" type="button" data-lb-close>' . NH_Html::icon( 'close' ) . '<span class="nh-sr-only">' . esc_html( $t['lb_close'] ) . '</span></button>'
			. '</div>'
			. '<div class="nh-lightbox__stage">'
			. '<img alt="">'
			. '<button class="nh-lightbox__btn nh-lightbox__nav nh-lightbox__nav--prev" type="button" data-lb-prev>' . NH_Html::icon( 'arrow-back' ) . '<span class="nh-sr-only">' . esc_html( $t['lb_prev'] ) . '</span></button>'
			. '<button class="nh-lightbox__btn nh-lightbox__nav nh-lightbox__nav--next" type="button" data-lb-next>' . NH_Html::icon( 'arrow-fwd' ) . '<span class="nh-sr-only">' . esc_html( $t['lb_next'] ) . '</span></button>'
			. '</div>'
			. '<p class="nh-lightbox__caption"></p>'
			. '<p class="nh-sr-only" aria-live="polite" data-lb-live></p>'
			. '</div>'
			. '</dialog>';
	}

	/**
	 * A screen-reader text of the widget, or its default when the field is empty.
	 */
	private static function text( array $s, string $key ): string {
		$text = self::str( $s, $key );

		return '' !== $text ? $text : self::TEXTS[ $key ];
	}

	/**
	 * A text setting as a trimmed string ('' when missing or not scalar).
	 */
	private static function str( array $a, string $key ): string {
		return isset( $a[ $key ] ) && is_scalar( $a[ $key ] ) ? trim( (string) $a[ $key ] ) : '';
	}
}
