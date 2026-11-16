<?php
/**
 * Widget `day` — «החיים קורים כאן.»: a day in four verbs, each with a print.
 *
 * Prototype: prototype/src/index.html L124–157 (`section#day`).
 * Spec: plugin/SPEC.md §4.5.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Four moments of the day. The residents are the subjects: each verb is a real H3 (large and still, the
 * spread's typographic moment), the photo is a numbered 4:3 «print» (the number comes from a CSS counter on
 * `.v3`, hidden from screen readers). v3.2 (research/07 §10.2 item 4): one print size for all, no zigzag;
 * the print is the one element that may appear softly once (`data-reveal`, nh-core.js).
 */
final class NH_Widget_Day extends NH_Widget_Base {

	public const ID = 'day';

	public const KEYWORDS = [ 'day', 'moments', 'activities', 'יום', 'פעילויות', 'רגעים' ];

	/**
	 * `sizes` of every print (research/07 §8.1: a print is at most 21rem wide).
	 */
	public const SIZES = '(max-width: 23em) calc(100vw - 2.5rem), 21rem';

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'היום בארבע מילים', 'nevehairus-site' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-time-line';
	}

	/**
	 * Controls: תוכן (heading, lead), רגעים (repeater), then מקטע.
	 */
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
				'default' => 'החיים קורים כאן.',
				'plain'   => 'מה עושים ביום?',
				'dot'     => '',
			]
		);

		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 3,
				'default' => 'יום רגיל בנווה האירוס בארבע מילים — וכל אחד ואחת בקצב שלהם.',
				'plain'   => '-',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'nh_moments',
			[
				'label' => __( 'רגעים ביום', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$repeater = new \Elementor\Repeater();

		NH_Controls::pair(
			$repeater,
			'verb',
			[
				'label'   => __( 'פועל (כותרת)', 'nevehairus-site' ),
				'default' => '',
			]
		);

		NH_Controls::image(
			$repeater,
			'photo',
			[
				'label'      => __( 'תמונה', 'nevehairus-site' ),
				'decorative' => false,
				'alt'        => '',
				'sizes'      => self::SIZES,
			]
		);

		NH_Controls::pair(
			$repeater,
			'caption',
			[
				'label'   => __( 'כיתוב לתמונה', 'nevehairus-site' ),
				'default' => '',
			]
		);

		NH_Controls::pair(
			$repeater,
			'text',
			[
				'label'   => __( 'טקסט', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 3,
				'default' => '',
			]
		);

		$this->add_control(
			'moments',
			[
				'label'       => __( 'רגעים', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{ verb }}',
				'default'     => [
					self::life_moment(
						'יוצרים',
						'עבודות פסיפס וקרמיקה ביום מכירה.',
						'סדנאות פסיפס, תכשיטים, ציור על משי, מוזיקה ותנועה — וגם תעסוקה בעיר.',
						'עובדים בסדנאות: פסיפס, תכשיטים וציור.'
					),
					self::life_moment(
						'רוכבים',
						'סוס ליד הגדר, ביום של רכיבה טיפולית.',
						'רכיבה טיפולית בחווה, ועוד חוגים מחוץ למעון.',
						'רוכבים על סוסים בחווה.'
					),
					self::life_moment(
						'מטיילים',
						'טיול בין עצי ההדר.',
						'טיולים, בתי קפה וסרטים, בעיר ובטבע.',
						'יוצאים לטיולים, לבית קפה ולסרט.'
					),
					self::life_moment(
						'מארחים',
						'פינה לשבת עם אורחים.',
						'ביקורים של משפחה וחברים, וקשר פתוח עם הצוות.',
						'המשפחות והחברים באים לבקר.'
					),
				],
			]
		);

		$this->end_controls_section();

		NH_Controls::section( $this, [ 'anchor' => 'day' ] );
	}

	/**
	 * Markup of index.html `section#day`.
	 */
	protected function render() {
		$s = $this->get_settings_for_display();

		$title = $this->nh_h2( $s );

		$items   = '';
		$moments = isset( $s['moments'] ) && is_array( $s['moments'] ) ? $s['moments'] : [];
		foreach ( $moments as $m ) {
			if ( ! is_array( $m ) ) {
				continue;
			}
			$verb = NH_Text::pair( self::life_text( $m, 'verb' ), self::life_text( $m, 'verb_plain' ) );
			if ( '' === $verb ) {
				continue; // An item without its verb is skipped (§4.0).
			}
			$text = NH_Text::block(
				self::life_text( $m, 'text' ),
				self::life_text( $m, 'text_plain' ),
				[
					'full_tag'    => 'p',
					'plain_tag'   => 'p',
					'plain_lines' => null,
				]
			);

			$items .= '<li class="v3-moment">' . "\n";
			$items .= '<h3 class="v3-moment__verb">' . $verb . '</h3>' . "\n";
			$figure = $this->life_figure( $m );
			if ( '' !== $figure ) {
				$items .= $figure . "\n";
			}
			if ( '' !== $text ) {
				$items .= $text . "\n";
			}
			$items .= '</li>' . "\n";
		}

		$html  = $this->nh_section_open(
			$s,
			[
				'class'      => 'v3-day v3-spread',
				'labelledby' => '' !== $title ? 'title' : null,
			]
		);
		$html .= "\n" . '<div class="nh-container">' . "\n";
		$html .= $this->nh_rule() . "\n";
		if ( '' !== $title ) {
			$html .= $title . "\n";
		}
		$lead = $this->nh_lead( $s );
		if ( '' !== $lead ) {
			$html .= $lead . "\n";
		}
		if ( '' !== $items ) {
			$html .= '<ol class="v3-day__list" role="list">' . "\n" . $items . '</ol>' . "\n";
		}
		$html .= '</div>' . "\n";
		$html .= $this->nh_section_close() . "\n";

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * `figure.v3-print[data-reveal]` of one moment, or '' when it has no image (prints are omitted, §4.0).
	 *
	 * @param array $m Repeater item.
	 * @return string
	 */
	private function life_figure( array $m ): string {
		$img = NH_Controls::image_args( $m, 'photo' );
		$id  = isset( $img['photo'] ) ? (int) $img['photo'] : 0;
		if ( $id <= 0 ) {
			return '';
		}

		$sizes = isset( $img['sizes'] ) && is_string( $img['sizes'] ) ? trim( $img['sizes'] ) : '';
		$o     = [ 'sizes' => '' !== $sizes ? $sizes : self::SIZES ];
		// The photos are informative: the media library's alt, unless the editor overrides it.
		if ( ! empty( $img['decorative'] ) ) {
			$o['decorative'] = true;
		} elseif ( isset( $img['alt'] ) && is_string( $img['alt'] ) && '' !== trim( $img['alt'] ) ) {
			$o['alt'] = trim( $img['alt'] );
		}

		$picture = NH_Picture::render( $id, $o );
		if ( '' === $picture ) {
			return '';
		}

		$caption = NH_Text::pair( self::life_text( $m, 'caption' ), self::life_text( $m, 'caption_plain' ) );

		return '<figure class="v3-print" data-reveal>' . $picture
			. ( '' !== $caption ? '<figcaption>' . $caption . '</figcaption>' : '' )
			. '</figure>';
	}

	/**
	 * One default repeater item (prototype text; the photo is chosen in the media library).
	 *
	 * @param string $verb       Verb (H3).
	 * @param string $caption    Caption of the print.
	 * @param string $text       Text.
	 * @param string $text_plain Plain twin of the text.
	 * @return array
	 */
	private static function life_moment( string $verb, string $caption, string $text, string $text_plain ): array {
		return [
			'verb'             => $verb,
			'verb_plain'       => '',
			'photo'            => [
				'url' => '',
				'id'  => '',
			],
			'photo_alt'        => '',
			'photo_decorative' => '',
			'photo_sizes'      => self::SIZES,
			'caption'          => $caption,
			'caption_plain'    => '',
			'text'             => $text,
			'text_plain'       => $text_plain,
		];
	}

	/**
	 * A setting as a string ('' when missing or not scalar).
	 *
	 * @param array  $s   Settings or a repeater item.
	 * @param string $key Control id.
	 * @return string
	 */
	private static function life_text( array $s, string $key ): string {
		return isset( $s[ $key ] ) && is_scalar( $s[ $key ] ) ? (string) $s[ $key ] : '';
	}
}
