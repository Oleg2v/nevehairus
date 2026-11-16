<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget "related" — להמשך קריאה (package index, SPEC.md §4.17).
 *
 * Further reading at the end of an inner page: row links in the shape of the ל spread's roles (.v3-roles, whose
 * rules live in base.css); a long description wraps. Links that leave the site say so to screen readers.
 * Target markup: prototype/program-main-home.html section#related (source prototype/src/program-main-home.html
 * L145–156).
 *
 * @package nevehairus-site
 */
final class NH_Widget_Related extends NH_Widget_Base {

	public const ID       = 'related';
	public const KEYWORDS = [ 'related', 'links', 'להמשך קריאה', 'קישורים' ];

	public function get_title() {
		return __( 'להמשך קריאה', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-link';
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
				'default' => 'להמשך קריאה.',
				'plain'   => 'עוד דפים',
				'dot'     => '',
			]
		);
		$this->add_control(
			'external_sr',
			[
				'label'       => __( 'טקסט לקורא מסך אחרי קישור חיצוני', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '(נפתח באתר חיצוני)',
				'label_block' => true,
			]
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'nh_list',
			[
				'label' => __( 'קישורים', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);
		$r = new \Elementor\Repeater();
		NH_Controls::pair(
			$r,
			'title',
			[
				'label'   => __( 'כותרת', 'nevehairus-site' ),
				'default' => '',
				'plain'   => '',
			]
		);
		NH_Controls::pair(
			$r,
			'meta',
			[
				'label'   => __( 'תיאור', 'nevehairus-site' ),
				'default' => '',
				'plain'   => '-',
			]
		);
		NH_Controls::link(
			$r,
			'link',
			[
				'label'   => __( 'קישור', 'nevehairus-site' ),
				'default' => '',
			]
		);
		$this->add_control(
			'links',
			[
				'label'       => __( 'קישורים', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $r->get_controls(),
				'title_field' => '{{ title }}',
				'default'     => [
					[
						'title'       => 'אבחנה כפולה',
						'title_plain' => '',
						'meta'        => 'מוגבלות שכלית התפתחותית לצד אבחנה נפשית — ואיך אנחנו מלווים',
						'meta_plain'  => '-',
						'link'        => [ 'url' => '#related' ],
					],
					[
						'title'       => 'גלריית יצירות למכירה',
						'title_plain' => '',
						'meta'        => 'פסיפס, תכשיטים וקרמיקה — עבודות יד של דיירות ודיירי נווה האירוס',
						'meta_plain'  => '-',
						'link'        => [ 'url' => home_url( '/#made' ) ],
					],
					[
						'title'       => 'דיור בקהילה',
						'title_plain' => '',
						'meta'        => 'הצעד הבא: בית בלב הקהילה, בליווי צוות',
						'meta_plain'  => '-',
						'link'        => [ 'url' => home_url( '/#home-2' ) ],
					],
				],
			]
		);
		$this->end_controls_section();

		NH_Controls::section(
			$this,
			[
				'anchor'  => 'related',
				'running' => 'להמשך קריאה',
			]
		);
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$sr = self::str( $s, 'external_sr' );

		$items = '';
		foreach ( self::items( $s, 'links' ) as $item ) {
			$title = self::str( $item, 'title' );
			if ( '' === $title ) {
				continue;
			}
			$href   = NH_Html::url( $item['link'] ?? '' );
			$ext    = '' !== $href && self::external( $href );
			$hide   = NH_Text::HIDE === self::str( $item, 'title_plain' ); // the whole row is left out in plain language
			$items .= "\n\t\t\t" . '<li' . ( $hide ? ' class="v3-full"' : '' ) . '><a' . ( '' !== $href ? ' href="' . $href . '"' : '' ) . ( $ext ? ' rel="noopener"' : '' ) . '>'
				. '<b>' . NH_Text::pair( $title, $hide ? '' : self::str( $item, 'title_plain' ) ) . ( $ext ? self::sr( $sr ) : '' ) . '</b>'
				. NH_Text::pair(
					self::str( $item, 'meta' ),
					$hide ? '' : self::str( $item, 'meta_plain' ),
					[
						'tag'   => 'span',
						'class' => 'v3-roles__meta',
						'bare'  => false,
					]
				)
				. NH_Html::icon( 'arrow-fwd' ) . '</a></li>';
		}

		$h2   = '' !== NH_Text::pair( self::str( $s, 'title' ), self::str( $s, 'title_plain' ) ) ? $this->nh_h2( $s ) : '';
		$open = [ 'class' => 'v3-related v3-spread' ];
		if ( '' === $h2 ) {
			$open['labelledby'] = null; // no heading to point to (editor left the title empty)
		}

		$html  = $this->nh_section_open( $s, $open );
		$html .= "\n\t<div class=\"nh-container\">";
		$html .= "\n\t\t" . $this->nh_rule();
		if ( '' !== $h2 ) {
			$html .= "\n\t\t" . $h2;
		}
		if ( '' !== $items ) {
			$html .= "\n\t\t" . '<ul class="v3-roles" role="list">' . $items . "\n\t\t</ul>";
		}
		$html .= "\n\t</div>\n" . $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * Whether a link leaves the site. Only an absolute URL can; fragments, paths, tel and mailto links stay.
	 */
	private static function external( string $href ): bool {
		return 1 === preg_match( '#^(?:https?:)?//#i', $href ) && NH_Html::is_external( $href );
	}

	/**
	 * The screen-reader suffix of an external link (prototype footer: «<span class="nh-sr-only"> (…)</span>»).
	 */
	private static function sr( string $text ): string {
		return '' !== $text ? '<span class="nh-sr-only"> ' . esc_html( $text ) . '</span>' : '';
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
