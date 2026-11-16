<?php
/**
 * Widget «masthead» — the site header, placed alone in an Elementor Pro Theme Builder
 * header template (condition: entire site). SPEC.md §4.18.
 *
 * Reproduces prototype/src/partials/header.html L3–78 (built: index.html
 * `header.nh-header` and `dialog#nh-menu`): wordmark or logo, running head, main
 * navigation, the plain-language button, the «תצוגה» display panel, phone, visit
 * button, menu button, progress line, then the menu dialog with its own live region.
 * The skip link, the cookie banner and the global live region (header L1–2, L79)
 * are printed by the site module (NH_Site), not here.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

/**
 * Masthead: `<div class="nh-header v3-mast">` (or `<header>`, D3) + `<dialog id="nh-menu">`.
 * Uses the fixed ids of the prototype (`nh-menu`, `v3-display-panel`, `v3-display-title`):
 * one masthead per page (SPEC §2.5).
 */
final class NH_Widget_Masthead extends NH_Widget_Base {

	public const ID       = 'masthead';
	public const DYNAMIC  = true; // reads NH_Settings (phone, visit URL) and the current page (aria-current).
	public const KEYWORDS = [ 'header', 'masthead', 'menu', 'navigation', 'כותרת', 'תפריט', 'ניווט' ];

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'כותרת האתר (Theme Builder)', 'nevehairus-site' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-header';
	}

	/**
	 * Controls (content tab only). Defaults are the prototype's texts verbatim.
	 */
	protected function register_controls() {

		/* ---------- תוכן ---------- */
		$this->start_controls_section(
			'nh_content',
			[
				'label' => esc_html__( 'תוכן', 'nevehairus-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		NH_Controls::pair(
			$this,
			'wordmark_small',
			[
				'label'   => esc_html__( 'סמליל טקסט — המילה הקטנה', 'nevehairus-site' ),
				'default' => 'מעון',
			]
		);
		NH_Controls::pair(
			$this,
			'wordmark',
			[
				'label'   => esc_html__( 'סמליל טקסט — השם', 'nevehairus-site' ),
				'default' => 'נווה האירוס',
			]
		);
		$this->add_control(
			'home_sr',
			[
				'label'       => esc_html__( 'קישור לדף הבית — המשך לקורא מסך', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => ' — לדף הבית',
				'label_block' => true,
				'description' => esc_html__( 'לא מוצג על המסך. קורא המסך מקריא: שם האתר ואז הטקסט הזה.', 'nevehairus-site' ),
			]
		);

		NH_Controls::pair(
			$this,
			'plain_label',
			[
				'label'   => esc_html__( 'כפתור „בשפה פשוטה”', 'nevehairus-site' ),
				'default' => 'בשפה פשוטה',
			]
		);
		NH_Controls::pair(
			$this,
			'display_label',
			[
				'label'   => esc_html__( 'כפתור „תצוגה”', 'nevehairus-site' ),
				'default' => 'תצוגה',
			]
		);
		NH_Controls::pair(
			$this,
			'display_title',
			[
				'label'   => esc_html__( 'חלונית התצוגה — כותרת', 'nevehairus-site' ),
				'default' => 'הגדרות תצוגה',
			]
		);
		NH_Controls::pair(
			$this,
			'display_note',
			[
				'label'   => esc_html__( 'חלונית התצוגה — הערה', 'nevehairus-site' ),
				'default' => 'הבחירה נשמרת בדפדפן הזה.',
			]
		);
		NH_Controls::pair(
			$this,
			'toggle_plain',
			[
				'label'   => esc_html__( 'מתג — שפה פשוטה', 'nevehairus-site' ),
				'default' => 'בשפה פשוטה',
			]
		);
		NH_Controls::pair(
			$this,
			'toggle_motion',
			[
				'label'   => esc_html__( 'מתג — הפחתת תנועה', 'nevehairus-site' ),
				'default' => 'הפחתת תנועה',
			]
		);
		NH_Controls::pair(
			$this,
			'toggle_calm',
			[
				'label'   => esc_html__( 'מתג — מצב רגוע', 'nevehairus-site' ),
				'default' => 'מצב רגוע',
			]
		);

		$this->add_control(
			'phone_label',
			[
				'label'       => esc_html__( 'קישור הטלפון — תחילית לקורא מסך', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'טלפון:',
				'label_block' => true,
				'description' => esc_html__( 'המספר עצמו נלקח מההגדרות: הגדרות ← נווה האירוס.', 'nevehairus-site' ),
			]
		);

		NH_Controls::pair(
			$this,
			'cta_text',
			[
				'label'   => esc_html__( 'כפתור תיאום ביקור', 'nevehairus-site' ),
				'default' => 'תיאום ביקור',
				'plain'   => 'לקבוע ביקור',
			]
		);
		NH_Controls::link(
			$this,
			'cta_link',
			[
				'label'   => esc_html__( 'קישור הכפתור (ריק = כתובת הביקור מההגדרות)', 'nevehairus-site' ),
				'default' => '',
			]
		);

		$this->add_control(
			'menu_label',
			[
				'label'       => esc_html__( 'תפריט — שם לקורא מסך', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'תפריט',
				'label_block' => true,
			]
		);
		$this->add_control(
			'menu_close',
			[
				'label'       => esc_html__( 'תפריט — כפתור הסגירה (לקורא מסך)', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'סגירת התפריט',
				'label_block' => true,
			]
		);
		NH_Controls::pair(
			$this,
			'menu_phone_label',
			[
				'label'   => esc_html__( 'תפריט — תחילית הטלפון', 'nevehairus-site' ),
				'default' => 'טלפון:',
			]
		);
		$this->add_control(
			'menu_toggles_label',
			[
				'label'       => esc_html__( 'תפריט — שם קבוצת המתגים (לקורא מסך)', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'הגדרות תצוגה',
				'label_block' => true,
			]
		);

		$this->end_controls_section();

		/* ---------- תמונות ---------- */
		$this->start_controls_section(
			'nh_images',
			[
				'label' => esc_html__( 'תמונות', 'nevehairus-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);
		NH_Controls::image(
			$this,
			'logo',
			[
				'label'      => esc_html__( 'לוגו (לא חובה; מחליף את סמליל הטקסט בקישור לדף הבית)', 'nevehairus-site' ),
				'decorative' => false,
				'alt'        => '',
			]
		);
		$this->end_controls_section();

		/* ---------- ניווט ---------- */
		$this->start_controls_section(
			'nh_nav',
			[
				'label' => esc_html__( 'ניווט', 'nevehairus-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);
		$this->add_control(
			'nav_label',
			[
				'label'       => esc_html__( 'שם הניווט (לקורא מסך, בכותרת ובתפריט)', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'ראשי',
				'label_block' => true,
			]
		);

		$item = new Repeater();
		NH_Controls::pair(
			$item,
			'label',
			[
				'label'   => esc_html__( 'טקסט הקישור', 'nevehairus-site' ),
				'default' => '',
			]
		);
		NH_Controls::link(
			$item,
			'link',
			[
				'label'   => esc_html__( 'קישור', 'nevehairus-site' ),
				'default' => '',
			]
		);
		$item->add_control(
			'current_ids',
			[
				'label'       => esc_html__( 'מסומן כעמוד הנוכחי בעמודים (מזהים)', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'description' => esc_html__( 'מזהי עמודים מופרדים בפסיקים, למשל 12,34. הקישור מסומן כעמוד הנוכחי בעמודים האלה ובעמודי המשנה שלהם. קישור לעמוד (בלי #) מסומן גם בלי זה.', 'nevehairus-site' ),
			]
		);
		$this->add_control(
			'nav',
			[
				'label'       => esc_html__( 'קישורים', 'nevehairus-site' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $item->get_controls(),
				'title_field' => '{{ label }}',
				'default'     => [
					[
						'label'       => 'מי אנחנו',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#story' ) ],
						'current_ids' => '',
					],
					[
						'label'       => 'מסגרות המגורים',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#homes' ) ],
						'current_ids' => '',
					],
					[
						'label'       => 'החיים אצלנו',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#day' ) ],
						'current_ids' => '',
					],
					[
						'label'       => 'דרושים',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#jobs' ) ],
						'current_ids' => '',
					],
					[
						'label'       => 'יצירת קשר',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#door' ) ],
						'current_ids' => '',
					],
				],
			]
		);
		$this->end_controls_section();

		/* ---------- מבנה ---------- */
		$this->start_controls_section(
			'nh_layout',
			[
				'label' => esc_html__( 'מבנה', 'nevehairus-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);
		$this->add_control(
			'html_tag',
			[
				'label'       => esc_html__( 'תגית העטיפה', 'nevehairus-site' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => [
					'div'    => 'div',
					'header' => 'header',
				],
				'default'     => 'div',
				'description' => esc_html__( 'ברירת המחדל div: תבנית הכותרת של Elementor Pro כבר עוטפת את הווידג׳ט באזור header. לבחור header רק אם אין אזור כזה סביבו (אחרת יהיו בעמוד שני אזורי כותרת).', 'nevehairus-site' ),
			]
		);
		$this->end_controls_section();
	}

	/**
	 * Render the masthead and the menu dialog (header L3–78).
	 */
	protected function render() {
		$s   = $this->get_settings_for_display();
		$tag = ( 'header' === self::str( $s, 'html_tag' ) ) ? 'header' : 'div';

		/* links of both navigations */
		$items = [];
		$nav   = ( isset( $s['nav'] ) && is_array( $s['nav'] ) ) ? $s['nav'] : [];
		foreach ( $nav as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = self::pair_of( $row, 'label' );
			$href  = NH_Html::url( $row['link'] ?? '' );
			if ( '' === $label || '' === $href ) {
				continue;
			}
			$items[] = [
				'href'    => $href,
				'label'   => $label,
				// NH_Html::url() is escaped for output; is_current() compares the plain URL.
				'current' => NH_Html::is_current( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), self::str( $row, 'current_ids' ) ),
			];
		}
		$nav_label = trim( self::str( $s, 'nav_label' ) );
		$nav_attr  = ( '' !== $nav_label ) ? ' aria-label="' . esc_attr( $nav_label ) . '"' : '';

		/* phone from the settings (D9) */
		$phone   = NH_Settings::phone();
		$display = trim( self::str( $phone, 'display' ) );
		$tel     = trim( self::str( $phone, 'tel' ) );
		$tel_url = ( '' !== $display && '' !== $tel ) ? esc_url( NH_Html::tel( $tel ) ) : '';

		/* the visit button: own link, or the visit URL from the settings */
		$cta_label = self::pair_of( $s, 'cta_text' );
		$cta_url   = NH_Html::url( $s['cta_link'] ?? '' );
		if ( '' === $cta_url ) {
			$cta_url = esc_url( NH_Settings::visit_url() );
		}

		/* the three display switches (same markup in the panel and in the menu) */
		$toggles = NH_Html::display_toggles(
			[
				'plain'  => self::pair_of( $s, 'toggle_plain' ),
				'motion' => self::pair_of( $s, 'toggle_motion' ),
				'calm'   => self::pair_of( $s, 'toggle_calm' ),
			]
		);

		$menu_label = trim( self::str( $s, 'menu_label' ) );

		/* ---------- header ---------- */
		$html  = '<' . $tag . ' class="nh-header v3-mast">';
		$html .= '<div class="nh-container nh-header__inner">';
		$html .= $this->logo_link( $s );

		// running head: filled by masthead.js with the name of the spread being read.
		$html .= '<p class="v3-mast__running" aria-hidden="true" data-running></p>';

		if ( $items ) {
			$html .= '<nav class="nh-nav"' . $nav_attr . '><ul role="list">';
			foreach ( $items as $it ) {
				$html .= '<li><a href="' . $it['href'] . '"' . ( $it['current'] ? ' aria-current="page"' : '' ) . '>' . $it['label'] . '</a></li>';
			}
			$html .= '</ul></nav>';
		}

		$html .= '<div class="nh-header__actions">';

		$html .= '<button class="v3-plainbtn" type="button" data-plain-toggle aria-pressed="false">'
			. NH_Html::icon( 'speech' )
			. '<span>' . self::pair_of( $s, 'plain_label' ) . '</span></button>';

		$html .= '<div class="v3-display">';
		$html .= '<button class="v3-display__btn" type="button" aria-expanded="false" aria-controls="v3-display-panel" data-display-open>'
			. NH_Html::icon( 'sliders' )
			. '<span>' . self::pair_of( $s, 'display_label' ) . '</span></button>';
		$panel_title = self::pair_of( $s, 'display_title' );
		if ( '' !== $panel_title ) {
			$html .= '<div class="v3-display__panel" id="v3-display-panel" role="group" aria-labelledby="v3-display-title" hidden>';
			$html .= '<p class="v3-display__title" id="v3-display-title">' . $panel_title . '</p>';
		} else {
			// no visible title: the group is named by the button's text.
			$name   = trim( self::str( $s, 'display_label' ) );
			$html  .= '<div class="v3-display__panel" id="v3-display-panel" role="group"' . ( '' !== $name ? ' aria-label="' . esc_attr( $name ) . '"' : '' ) . ' hidden>';
		}
		$html .= $toggles;
		$note  = self::pair_of( $s, 'display_note' );
		if ( '' !== $note ) {
			$html .= '<p class="v3-display__note">' . $note . '</p>';
		}
		$html .= '</div></div>';

		if ( '' !== $tel_url ) {
			$aria  = trim( trim( self::str( $s, 'phone_label' ) ) . ' ' . $display );
			$html .= '<a class="nh-phone" href="' . $tel_url . '" aria-label="' . esc_attr( $aria ) . '">'
				. NH_Html::icon( 'phone' )
				. '<span class="nh-phone__num nh-ltr">' . esc_html( $display ) . '</span></a>';
		}

		if ( '' !== $cta_label && '' !== $cta_url ) {
			$html .= '<a class="nh-btn nh-btn--primary nh-btn--sm v3-mast__cta" href="' . $cta_url . '">' . $cta_label . '</a>';
		}

		$html .= '<button class="nh-menu-btn" type="button" data-menu-open aria-haspopup="dialog" aria-expanded="false" aria-controls="nh-menu">'
			. NH_Html::icon( 'menu' )
			. '<span class="nh-sr-only">' . esc_html( $menu_label ) . '</span></button>';

		$html .= '</div>'; // .nh-header__actions
		$html .= '</div>'; // .nh-header__inner
		$html .= '<div class="v3-progress" aria-hidden="true"></div>';
		$html .= '</' . $tag . '>' . "\n";

		/* ---------- menu (native <dialog>: focus trap and Esc for free) ---------- */
		$html .= '<dialog class="nh-menu" id="nh-menu"' . ( '' !== $menu_label ? ' aria-label="' . esc_attr( $menu_label ) . '"' : '' ) . '>';
		$html .= '<div class="nh-menu__inner">';
		$html .= '<div class="nh-menu__top">' . $this->wordmark( $s, true )
			. '<button class="nh-menu__close" type="button" data-menu-close>'
			. NH_Html::icon( 'close' )
			. '<span class="nh-sr-only">' . esc_html( trim( self::str( $s, 'menu_close' ) ) ) . '</span></button></div>';

		if ( $items ) {
			$html .= '<nav' . $nav_attr . '><ul role="list">';
			foreach ( $items as $it ) {
				$html .= '<li><a href="' . $it['href'] . '">' . $it['label'] . '</a></li>';
			}
			$html .= '</ul></nav>';
		}

		$html .= '<div class="nh-menu__meta">';
		if ( '' !== $cta_label && '' !== $cta_url ) {
			$html .= '<a class="nh-btn nh-btn--light" href="' . $cta_url . '">' . $cta_label . '</a>';
		}
		if ( '' !== $tel_url ) {
			$prefix = self::pair_of( $s, 'menu_phone_label' );
			$html  .= '<a href="' . $tel_url . '">' . ( '' !== $prefix ? $prefix . ' ' : '' )
				. '<span class="nh-ltr">' . esc_html( $display ) . '</span></a>';
		}
		$group = trim( self::str( $s, 'menu_toggles_label' ) );
		$html .= '<div class="v3-menu-toggles" role="group"' . ( '' !== $group ? ' aria-label="' . esc_attr( $group ) . '"' : '' ) . '>' . $toggles . '</div>';
		// a modal dialog makes the rest of the page inert, so the menu has its own live region.
		$html .= '<p class="nh-sr-only" role="status" aria-live="polite" data-announce-menu></p>';
		$html .= '</div>'; // .nh-menu__meta
		$html .= '</div>'; // .nh-menu__inner
		$html .= '</dialog>';

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * The link to the home page: the logo from the media library when one is set, else the wordmark,
	 * then the screen-reader suffix (« — לדף הבית»). The link always has a name: an informative logo
	 * gets the alt override, else the media library's alt, else the wordmark text («מעון נווה האירוס»);
	 * a logo marked decorative gets alt="" and the wordmark text goes into the visually hidden span.
	 *
	 * @param array $s Settings.
	 * @return string `<a class="nh-logo" …>…</a>`.
	 */
	private function logo_link( array $s ): string {
		$sr    = self::suffix( self::str( $s, 'home_sr' ) );
		$name  = self::plain_text( self::str( $s, 'wordmark_small' ) . ' ' . self::str( $s, 'wordmark' ) );
		$inner = '';
		$img   = NH_Controls::image_args( $s, 'logo' );
		$id    = (int) ( $img['photo'] ?? 0 );
		if ( $id > 0 ) {
			$o = [
				'sizes'   => '16rem',
				'loading' => 'eager', // above the fold, but never the LCP image (that is the cover photo).
			];
			if ( ! empty( $img['decorative'] ) ) {
				$o['decorative'] = true;
			} else {
				$alt      = trim( (string) ( $img['alt'] ?? '' ) );
				$alt      = ( '' !== $alt ) ? $alt : NH_Picture::alt( $id );
				$o['alt'] = ( '' !== trim( $alt ) ) ? $alt : $name;
			}
			$inner = NH_Picture::render( $id, $o );
			if ( '' !== $inner && ! empty( $o['decorative'] ) ) {
				$sr = $name . $sr;
			}
		}
		if ( '' === $inner ) {
			$inner = $this->wordmark( $s, false );
		}
		$sr = ( '' !== trim( $sr ) ) ? '<span class="nh-sr-only">' . esc_html( $sr ) . '</span>' : '';
		return '<a class="nh-logo" href="' . esc_url( home_url( '/' ) ) . '">' . $inner . $sr . '</a>';
	}

	/**
	 * The typographic wordmark: `<span class="nh-wordmark"><small>מעון</small> נווה האירוס</span>`.
	 *
	 * @param array $s      Settings.
	 * @param bool  $hidden Decorative copy (the menu's top bar): aria-hidden.
	 * @return string HTML, '' when both texts are empty.
	 */
	private function wordmark( array $s, bool $hidden ): string {
		$small = self::pair_of( $s, 'wordmark_small' );
		$name  = self::pair_of( $s, 'wordmark' );
		if ( '' === $small && '' === $name ) {
			return '';
		}
		$inner = ( '' !== $small ) ? '<small>' . $small . '</small>' . ( '' !== $name ? ' ' : '' ) : '';
		return '<span class="nh-wordmark"' . ( $hidden ? ' aria-hidden="true"' : '' ) . '>' . $inner . $name . '</span>';
	}

	/**
	 * A setting as a string ('' when missing or not scalar).
	 *
	 * @param array  $a   Settings or a repeater row.
	 * @param string $key Control id.
	 * @return string
	 */
	private static function str( array $a, string $key ): string {
		$v = $a[ $key ] ?? '';
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * NH_Text::pair() of control `$key` and its twin `{$key}_plain`.
	 *
	 * @param array  $a   Settings or a repeater row.
	 * @param string $key Control id.
	 * @param array  $o   NH_Text::pair() options.
	 * @return string HTML.
	 */
	private static function pair_of( array $a, string $key, array $o = [] ): string {
		return NH_Text::pair( self::str( $a, $key ), self::str( $a, $key . '_plain' ), $o );
	}

	/**
	 * Plain text of a control value (tags removed, whitespace collapsed), e.g. for an alt text.
	 *
	 * @param string $text Raw control value.
	 * @return string Plain text (escape on output).
	 */
	private static function plain_text( string $text ): string {
		$text = strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- SPEC §2.3: widgets call only the listed WordPress API.
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * A screen-reader suffix such as « — לדף הבית»: one leading space, whatever the editor typed.
	 *
	 * @param string $text Raw control value.
	 * @return string Plain text ('' when empty).
	 */
	private static function suffix( string $text ): string {
		$text = trim( $text );
		return ( '' === $text ) ? '' : ' ' . $text;
	}
}
