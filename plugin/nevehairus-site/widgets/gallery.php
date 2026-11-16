<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `gallery` — «האגף החדש.» (work package "residence").
 *
 * v3.2 (research/07 §10.3 item 4): an even grid of 4:3 prints of one size (3 / 2 / 1 columns), each with its
 * caption visible; no lightbox, no zoom on hover. Each print may appear softly once (`data-reveal`,
 * nh-core.js). Weak photos are never enlarged.
 * Target: prototype `program-main-home.html` `section#inside`.
 *
 * @package nevehairus-site
 */
final class NH_Widget_Gallery extends NH_Widget_Base {

	public const ID       = 'gallery';
	public const KEYWORDS = [ 'gallery', 'photos', 'images', 'prints', 'גלריה', 'תמונות' ];

	/** `sizes` of every print (research/07 §8.1: a grid cell is ≈ 27rem at 1440). */
	public const SIZES = '(min-width: 64em) 27rem, (min-width: 40em) 50vw, calc(100vw - 2.5rem)';

	public function get_title() {
		return __( 'גלריה · תמונות עם כיתוב', 'nevehairus-site' );
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
				'default' => 'את האגף החדש תכננו אדריכליות במיוחד בשביל הדיירים והדיירות: קו עיצובי נעים ורגוע, ותאורת „שמיים” בתקרה.',
				'plain'   => 'תמונות מהאגף החדש.',
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
				'label'      => __( 'תמונה (4:3)', 'nevehairus-site' ),
				'decorative' => false,
				'alt'        => '',
			]
		);
		NH_Controls::pair(
			$image,
			'caption',
			[
				'label'   => __( 'כיתוב מתחת לתמונה', 'nevehairus-site' ),
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
					[ 'caption' => 'פינת אוכל וסלון באגף החדש.' ],
				],
				'title_field' => '{{ caption }}',
				'description' => __( 'רשת אחידה של תמונות 4:3 באותו גודל, עם כיתוב גלוי. שש תמונות = שלוש בשורה. פריט בלי תמונה לא מוצג.', 'nevehairus-site' ),
			]
		);

		$this->end_controls_section();

		/* ---------- מקטע (always last) ---------- */
		NH_Controls::section( $this, [ 'anchor' => 'inside' ] );
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
		$html .= $this->nh_lead(
			$s,
			[
				'plain_class' => 'v3-lead',
			]
		);
		if ( '' !== $items ) {
			$html .= '<ul class="v3-gallery" role="list">' . $items . '</ul>';
		}
		$html .= '</div>';
		$html .= $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * One `li` per item with an image: `figure.v3-print[data-reveal]` with the picture and the caption.
	 * Items without an image (or with a missing attachment) are skipped.
	 */
	private function items_html( array $s ): string {
		$items = '';
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
					'sizes'      => self::SIZES,
					'alt'        => $img['alt'] ?? null,
					'decorative' => ! empty( $img['decorative'] ),
				]
			);
			if ( '' === $picture ) {
				continue;
			}
			$caption = NH_Text::pair( self::str( $item, 'caption' ), self::str( $item, 'caption_plain' ) );

			$items .= '<li><figure class="v3-print" data-reveal>' . $picture
				. ( '' !== $caption ? '<figcaption>' . $caption . '</figcaption>' : '' )
				. '</figure></li>';
		}

		return $items;
	}

	/**
	 * A text setting as a trimmed string ('' when missing or not scalar).
	 */
	private static function str( array $a, string $key ): string {
		return isset( $a[ $key ] ) && is_scalar( $a[ $key ] ) ? trim( (string) $a[ $key ] ) : '';
	}
}
