<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget "related" — להמשך קריאה (package index, SPEC.md §4.17).
 *
 * Further reading at the end of an inner page: row links in the shape of the ל spread's roles (.v3-roles, whose
 * rules live in base.css); a long description wraps. Links that leave the site say so to screen readers.
 * v3.2 (research/07 §10.3 item 8, §3 item 21): a row leads only to something that exists elsewhere — a page,
 * or a section of another page. A row without a link, or with a link to this very page (its own sections
 * included), is not printed; editors see a note instead.
 * Target markup: prototype/program-main-home.html section#related (source prototype/src/program-main-home.html).
 *
 * @package nevehairus-site
 */
final class NH_Widget_Related extends NH_Widget_Base {

	public const ID       = 'related';
	public const KEYWORDS = [ 'related', 'links', 'להמשך קריאה', 'קישורים' ];

	/**
	 * Never in Elementor's element cache (SPEC §3.3): render() reads the current post (a row back to this page is
	 * left out), the current user (the editors' note about a left-out row) and the settings (link tokens). Cached
	 * once while an editor looks at the page, that note would be shown to every visitor until the cache expires.
	 */
	public const DYNAMIC = true;

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
				'description' => __( 'רק קישור לעמוד אחר או למקטע בעמוד אחר. שורה בלי קישור, או עם קישור לעמוד הזה עצמו, לא מוצגת.', 'nevehairus-site' ),
				'default'     => [
					[
						'title'       => 'שלושת הבתים',
						'title_plain' => '',
						'meta'        => 'מי גר בכל בית ולמי הוא מתאים',
						'meta_plain'  => '-',
						'link'        => [ 'url' => home_url( '/#homes' ) ],
					],
					[
						'title'       => 'ידיים שיוצרות',
						'title_plain' => '',
						'meta'        => 'פסיפס, תכשיטים וקרמיקה — עבודות יד של דיירות ודיירי נווה האירוס',
						'meta_plain'  => '-',
						'link'        => [ 'url' => home_url( '/#made' ) ],
					],
				],
			]
		);
		$this->end_controls_section();

		NH_Controls::section( $this, [ 'anchor' => 'related' ] );
	}

	protected function render() {
		$s  = $this->get_settings_for_display();
		$sr = self::str( $s, 'external_sr' );

		$items   = '';
		$skipped = [];
		foreach ( self::items( $s, 'links' ) as $item ) {
			$title = self::str( $item, 'title' );
			if ( '' === $title ) {
				continue;
			}
			$link = $item['link'] ?? '';
			$raw  = is_array( $link ) ? self::str( $link, 'url' ) : ( is_scalar( $link ) ? trim( (string) $link ) : '' );
			$href = NH_Html::url( $raw );
			if ( '' === $href || self::is_this_page( $raw ) ) {
				$skipped[] = $title; // research/07 §10.3 item 8: never a row without a target or back to this page
				continue;
			}
			$ext    = self::external( $href );
			$hide   = NH_Text::HIDE === self::str( $item, 'title_plain' ); // the whole row is left out in plain language
			$items .= "\n\t\t\t" . '<li' . ( $hide ? ' class="v3-full"' : '' ) . '><a href="' . $href . '"' . ( $ext ? ' rel="noopener"' : '' ) . '>'
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
		foreach ( $skipped as $title ) {
			/* translators: %s: the title of a further-reading row */
			$html .= $this->nh_admin_note( sprintf( __( 'השורה „%s” לא מוצגת: אין לה קישור, או שהיא מקשרת לעמוד הזה עצמו.', 'nevehairus-site' ), wp_kses( $title, [] ) ) );
		}
		$html .= "\n\t</div>\n" . $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * Does the link lead back to the page this widget is on? A bare fragment (`#…`, a section of this page, or a
	 * link token whose page is not set) does, and so does a URL whose page — without its fragment — is the post
	 * being shown.
	 *
	 * @param string $raw The link as typed (not escaped).
	 */
	private static function is_this_page( string $raw ): bool {
		$raw = NH_Html::resolve_token( $raw ); // #privacy, #accessibility, … are pages when they are set
		if ( '' === $raw || '#' === $raw[0] ) {
			return true;
		}
		$page = strtok( $raw, '#' );
		$here = (int) get_the_ID();
		return $here > 0 && is_string( $page ) && NH_Html::post_id( $page ) === $here;
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
