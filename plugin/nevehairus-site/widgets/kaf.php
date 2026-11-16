<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget `kaf` — כ׳ · «ידיים שיוצרות.»: the palm of a still letter כ (weight 440) holds one work of the
 * residents; a visible caption says what it is.
 *
 * Prototype: prototype/src/index.html `section#made` (built: prototype/index.html).
 * Spec: plugin/SPEC.md §4.6; research/07 §10.2 item 5 (v3.2: one work, no list of works and no colour
 * swatches, the honey ground, the ruling line first; the button only when it leads to another page).
 * CSS: assets/css/widgets/kaf.css. No JS.
 *
 * @package nevehairus-site
 */
final class NH_Widget_Kaf extends NH_Widget_Base {

	public const ID = 'kaf';

	public const STYLE_DEPS = [ 'nh-letters' ];

	public const KEYWORDS = [ 'kaf', 'crafts', 'works', 'palm', 'כ', 'כף', 'יצירות', 'עבודות יד', 'פסיפס' ];

	/** The one weight of the כ (v3.2: every letter keeps one weight for good). */
	public const WEIGHT = 440;

	/** `sizes` of the photo in the palm (research/07 §8.1: the palm is ≈ 435px wide at 1440). */
	public const SIZES = '(min-width: 64em) 28rem, 70vw';

	/** The section's default anchor: a button to it would lead to this very spread. */
	private const ANCHOR = 'made';

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
	 * Controls: תוכן (heading, lead, caption, button), תמונות (the work in the palm), then מקטע.
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

		NH_Controls::pair(
			$this,
			'caption',
			[
				'label'       => __( 'מה בכף היד (כיתוב גלוי)', 'nevehairus-site' ),
				'type'        => 'textarea',
				'rows'        => 2,
				'default'     => 'בכף היד: כד פסיפס כחול־ירוק — עבודת יד של דיירות ודיירי נווה האירוס.',
				'plain'       => '',
				'description' => __( 'התמונה בכף היד דקורטיבית: הכיתוב הזה אומר מה רואים בה. בלי תמונה הכיתוב לא מוצג.', 'nevehairus-site' ),
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
				'label'       => __( 'קישור הכפתור (ריק = בלי כפתור)', 'nevehairus-site' ),
				'default'     => '',
				'description' => __( 'רק לעמוד אחר, למשל עמוד גלריית היצירות. קישור לאותו מקטע (#made) לא מוצג.', 'nevehairus-site' ),
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

		NH_Controls::image(
			$this,
			'photo',
			[
				'label'      => __( 'העבודה בכף היד של ה־כ׳ (ריק = שמיים)', 'nevehairus-site' ),
				'decorative' => true,
				'alt'        => '',
				'pos'        => '50% 42%',
				'sizes'      => self::SIZES,
			]
		);

		$this->end_controls_section();

		NH_Controls::section( $this, [ 'anchor' => self::ANCHOR ] );
	}

	/**
	 * Markup of index.html `section#made` (SPEC §4.6, research/07 §10.2 item 5).
	 */
	protected function render() {
		$s     = $this->get_settings_for_display();
		$img   = NH_Controls::image_args( $s, 'photo' );
		$id    = (int) $img['photo'];
		$photo = ( $id > 0 && NH_Picture::exists( $id ) ) ? $id : 0;

		// The palm: the work's photo, decorative by default (the caption says what it is); a calm sky without one.
		$room = [
			'photo'      => $photo,
			'weight'     => self::WEIGHT,
			'sizes'      => null !== $img['sizes'] ? $img['sizes'] : self::SIZES,
			'class'      => 'v3-kaf__palm',
			'alt'        => $img['alt'],
			'decorative' => (bool) $img['decorative'],
			'field'      => 'sky',
		];
		if ( null !== $img['pos'] ) {
			$room['pos'] = $img['pos'];
		}

		// The caption describes the work in the palm: without the photo there is nothing to describe.
		$caption = $photo ? NH_Text::pair( self::to_str( $s['caption'] ?? '' ), self::to_str( $s['caption_plain'] ?? '' ) ) : '';

		$html = $this->nh_section_open(
			$s,
			[
				'class' => 'v3-kaf v3-spread',
				'tone'  => 'citrus',
			]
		)
			. '<div class="nh-container v3-kaf__grid">'
			. $this->nh_rule()
			. '<div class="v3-kaf__letter">' . NH_Letters::room( 'kaf', $room ) . '</div>'
			. '<div class="v3-kaf__text">'
			. $this->nh_h2( $s )
			. $this->nh_lead( $s )
			. ( '' !== $caption ? '<p class="v3-kaf__cap">' . $caption . '</p>' : '' )
			. $this->button( $s )
			. '</div>'
			. '</div>'
			. $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * The ink button under the caption: only with a link, and never to this very spread (research/07 §3
	 * item 21: a link leads only to a page or a section that exists elsewhere). A link to the spread itself
	 * shows editors a note instead.
	 *
	 * @param array $s Widget settings.
	 * @return string
	 */
	private function button( array $s ): string {
		$text = NH_Text::pair( self::to_str( $s['button'] ?? '' ), self::to_str( $s['button_plain'] ?? '' ) );
		$link = $s['button_link'] ?? '';
		$raw  = is_array( $link ) ? self::to_str( $link['url'] ?? '' ) : self::to_str( $link );
		if ( '' === $text || '' === $raw ) {
			return '';
		}
		if ( $this->is_this_spread( $raw, $s ) ) {
			return $this->nh_admin_note( __( 'הכפתור מוביל לאותו מקטע ולכן לא מוצג', 'nevehairus-site' ) );
		}
		$url = NH_Html::url( $raw );
		if ( '' === $url ) {
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
	 * Does the link lead to this spread? Its fragment is this widget's anchor (or `#made`) and it has no page
	 * of its own, or its page is the one this widget is on.
	 *
	 * @param string $raw The link as typed (not escaped).
	 * @param array  $s   Widget settings.
	 * @return bool
	 */
	private function is_this_spread( string $raw, array $s ): bool {
		$hash = strpos( $raw, '#' );
		if ( false === $hash ) {
			return false;
		}
		$fragment = substr( $raw, $hash + 1 );
		$anchor   = sanitize_html_class( self::to_str( $s['anchor'] ?? '' ) );
		if ( ! in_array( $fragment, array_filter( [ self::ANCHOR, $anchor ] ), true ) ) {
			return false;
		}
		$page = substr( $raw, 0, $hash );
		if ( '' === $page ) {
			return true;
		}
		$here = (int) get_the_ID();
		return $here > 0 && NH_Html::post_id( $page ) === $here;
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
