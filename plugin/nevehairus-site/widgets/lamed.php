<?php
defined( 'ABSPATH' ) || exit;

/**
 * Widget "lamed" — ל׳ · דרושים (package index, SPEC.md §4.11).
 *
 * The jobs spread: the ל, the one letter allowed to rise above the ruling line, stands at the far end; beside it
 * the heading, the lead and the open roles as row links. A role (or the button) without its own link goes to the
 * jobs page from Settings → נווה האירוס (with ?role=… so the application form preselects it); when that page is
 * not set the links stay inside the spread and editors see a note. Reads settings, so it is never cached
 * (DYNAMIC). Target markup: prototype/index.html section#jobs (source prototype/src/index.html L313–332).
 *
 * @package nevehairus-site
 */
final class NH_Widget_Lamed extends NH_Widget_Base {

	public const ID         = 'lamed';
	public const STYLE_DEPS = [ 'nh-letters' ];
	public const DYNAMIC    = true;
	public const KEYWORDS   = [ 'jobs', 'lamed', 'דרושים', 'משרות', 'עבודה', 'ל' ];

	public function get_title() {
		return __( 'ל׳ · דרושים', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-person';
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
				'default' => 'עבודה עם משמעות. מקום ללמוד ולגדול.',
				'plain'   => 'רוצים לעבוד אצלנו?',
				'dot'     => '',
			]
		);
		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'מחפשים אנשים שרוצים להשפיע ולהיות חלק מבית: צוות קבוע, ואפשרויות ללמוד ולהתפתח מקצועית.',
				'plain'   => "מחפשים עובדים ועובדות.\nיש עבודה במעון ובבתים בעיר.",
			]
		);
		NH_Controls::pair(
			$this,
			'button',
			[
				'label'   => __( 'כפתור', 'nevehairus-site' ),
				'default' => 'לכל המשרות ולהגשת מועמדות',
				'plain'   => 'לראות את כל העבודות',
			]
		);
		NH_Controls::link(
			$this,
			'button_link',
			[
				'label'   => __( 'קישור הכפתור (ריק = עמוד המשרות מההגדרות)', 'nevehairus-site' ),
				'default' => '',
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
		$this->add_control(
			'weight',
			[
				'label'   => __( 'עובי ה־ל׳ (300–800)', 'nevehairus-site' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 300,
				'max'     => 800,
				'step'    => 10,
				'default' => 800,
			]
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'nh_list',
			[
				'label' => __( 'משרות', 'nevehairus-site' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);
		$r = new \Elementor\Repeater();
		NH_Controls::pair(
			$r,
			'title',
			[
				'label'   => __( 'שם המשרה', 'nevehairus-site' ),
				'default' => '',
				'plain'   => '',
			]
		);
		NH_Controls::pair(
			$r,
			'meta',
			[
				'label'   => __( 'היקף המשרה', 'nevehairus-site' ),
				'default' => '',
				'plain'   => '',
			]
		);
		NH_Controls::link(
			$r,
			'link',
			[
				'label'   => __( 'קישור (ריק = עמוד המשרות, והמשרה נבחרת בטופס)', 'nevehairus-site' ),
				'default' => '',
			]
		);
		$this->add_control(
			'roles',
			[
				'label'         => __( 'משרות', 'nevehairus-site' ),
				'type'          => \Elementor\Controls_Manager::REPEATER,
				'fields'        => $r->get_controls(),
				'title_field'   => '{{ title }}',
				'prevent_empty' => false,
				'default'       => [
					[
						'title'       => 'רכז/ת דירה — דיור לצעירים על הרצף האוטיסטי',
						'title_plain' => 'רכז או רכזת דירה',
						'meta'        => 'משרה מלאה, משמרות',
						'meta_plain'  => '',
						'link'        => [ 'url' => '' ],
					],
					[
						'title'       => 'עובד/ת סוציאלי/ת',
						'title_plain' => 'עובד סוציאלי או עובדת סוציאלית',
						'meta'        => '50%–70%',
						'meta_plain'  => '',
						'link'        => [ 'url' => '' ],
					],
					[
						'title'       => 'מרפא/ה בעיסוק',
						'title_plain' => 'מרפא או מרפאה בעיסוק',
						'meta'        => '6 שעות שבועיות',
						'meta_plain'  => '',
						'link'        => [ 'url' => '' ],
					],
					[
						'title'       => 'טבח/ית',
						'title_plain' => 'טבח או טבחית',
						'meta'        => 'משרה מלאה',
						'meta_plain'  => '',
						'link'        => [ 'url' => '' ],
					],
					[
						'title'       => 'עובד/ת אחזקה',
						'title_plain' => 'עובד או עובדת אחזקה',
						'meta'        => 'משרה מלאה',
						'meta_plain'  => '',
						'link'        => [ 'url' => '' ],
					],
				],
			]
		);
		$this->end_controls_section();

		NH_Controls::section(
			$this,
			[
				'anchor'  => 'jobs',
				'running' => 'דרושים',
			]
		);
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$anchor  = sanitize_html_class( self::str( $s, 'anchor' ) );
		$here    = esc_url( '#' . ( '' !== $anchor ? $anchor : $this->nh_uid( 'title' ) ) ); // fallback: stay in the spread
		$sr      = self::str( $s, 'external_sr' );
		$missing = false; // a link fell back because the jobs page is not set

		$roles = '';
		foreach ( self::items( $s, 'roles' ) as $item ) {
			$title = self::str( $item, 'title' );
			if ( '' === $title ) {
				continue;
			}
			$href = NH_Html::url( $item['link'] ?? '' );
			if ( '' === $href ) {
				$href = esc_url( NH_Settings::jobs_url( $title ) );
			}
			if ( '' === $href ) {
				$href    = $here;
				$missing = true;
			}
			$ext    = self::external( $href );
			$hide   = NH_Text::HIDE === self::str( $item, 'title_plain' ); // the whole row is left out in plain language
			$roles .= "\n\t\t\t\t" . '<li' . ( $hide ? ' class="v3-full"' : '' ) . '><a href="' . $href . '"' . ( $ext ? ' rel="noopener"' : '' ) . '>'
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

		$button = '';
		$label  = self::str( $s, 'button' );
		if ( '' !== $label ) {
			$href = NH_Html::url( $s['button_link'] ?? '' );
			if ( '' === $href ) {
				$href = esc_url( NH_Settings::jobs_url() );
			}
			if ( '' === $href ) {
				$href    = $here;
				$missing = true;
			}
			$ext    = self::external( $href );
			$button = '<p><a class="nh-btn nh-btn--primary" href="' . $href . '"' . ( $ext ? ' rel="noopener"' : '' ) . '>'
				. NH_Text::pair( $label, self::str( $s, 'button_plain' ) ) . ( $ext ? self::sr( $sr ) : '' )
				. NH_Html::icon( 'arrow-fwd', 'nh-btn__icon nh-btn__icon--fwd' ) . '</a></p>';
		}

		$h2   = '' !== NH_Text::pair( self::str( $s, 'title' ), self::str( $s, 'title_plain' ) ) ? $this->nh_h2( $s ) : '';
		$open = [ 'class' => 'v3-lamed v3-spread' ];
		if ( '' === $h2 ) {
			$open['labelledby'] = null; // no heading to point to (editor left the title empty)
		}
		$lead = $this->nh_lead( $s );
		$note = $missing ? $this->nh_admin_note( __( 'לא הוגדר עמוד המשרות (הגדרות ← נווה האירוס)', 'nevehairus-site' ) ) : '';

		$html  = $this->nh_section_open( $s, $open );
		$html .= "\n\t" . '<div class="nh-container v3-lamed__grid">';
		$html .= "\n\t\t" . $this->nh_rule();
		$html .= "\n\t\t" . '<div class="v3-lamed__letter" aria-hidden="true">' . NH_Letters::svg( 'lamed', self::weight( $s['weight'] ?? '', 800.0 ) ) . '</div>';
		$html .= "\n\t\t" . '<div class="v3-lamed__text">';
		if ( '' !== $h2 ) {
			$html .= "\n\t\t\t" . $h2;
		}
		if ( '' !== $lead ) {
			$html .= "\n\t\t\t" . $lead;
		}
		if ( '' !== $note ) {
			$html .= "\n\t\t\t" . $note;
		}
		if ( '' !== $roles ) {
			$html .= "\n\t\t\t" . '<ul class="v3-roles" role="list">' . $roles . "\n\t\t\t</ul>";
		}
		if ( '' !== $button ) {
			$html .= "\n\t\t\t" . $button;
		}
		$html .= "\n\t\t</div>\n\t</div>\n" . $this->nh_section_close();

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

	/**
	 * A letter weight from a NUMBER control, clamped to 300–800.
	 */
	private static function weight( $v, float $fallback ): float {
		if ( ! is_numeric( $v ) ) {
			return $fallback;
		}
		return max( 300.0, min( 800.0, (float) $v ) );
	}
}
