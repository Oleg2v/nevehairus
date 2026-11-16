<?php
/**
 * Widget «masthead» — the site header, placed alone in an Elementor Pro Theme Builder
 * header template (condition: entire site). SPEC.md §4.18; research/07 §5, §6, §10.1 items 1–2.
 *
 * v3.2: fixed and always the same — the mark (the logo's emblem from the media library and the
 * name as live text), at most five links, «בשפה פשוטה», the phone, «תיאום ביקור» and «תפריט»; no
 * running head, no progress line, no display panel, nothing changes while the page scrolls. Then the
 * phone menu: a native <dialog> opened only by «תפריט», with a visible title and a visible «סגירה»,
 * the five links, the visit button, the call line and two switches (plain language, calm mode).
 * Target: prototype/src/partials/header.html (built: index.html `header.nh-header`, `dialog#nh-menu`).
 * The skip link and the global live region are printed by the theme and the site module, not here.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

/**
 * Masthead: `<div class="nh-header v3-mast">` (or `<header>`, D3) + `<dialog id="nh-menu">`.
 * Uses the fixed ids of the prototype (`nh-menu`, `nh-menu-title`): one masthead per page (SPEC §2.5).
 */
final class NH_Widget_Masthead extends NH_Widget_Base {

	public const ID       = 'masthead';
	public const DYNAMIC  = true; // reads NH_Settings (phone, visit URL) and the current page (aria-current).
	public const KEYWORDS = [ 'header', 'masthead', 'menu', 'navigation', 'כותרת', 'תפריט', 'ניווט' ];

	/** The menu shows at most five links (research/07 §5.1: a short menu; a submenu would be a pop-up). */
	public const MAX_ITEMS = 5;

	/** Words a button may never lack: the fallbacks when the editor empties a field. */
	private const FALLBACK = [
		'menu_label' => 'תפריט',
		'menu_close' => 'סגירה',
	];

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
			'name',
			[
				'label'       => esc_html__( 'שם האתר ליד הסמל', 'nevehairus-site' ),
				'default'     => 'מעון נווה האירוס',
				'description' => esc_html__( 'טקסט חי ליד הסמל. בטלפון הוא נשבר לשתי שורות ברווח האחרון: „מעון נווה / האירוס”.', 'nevehairus-site' ),
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
				'label'       => esc_html__( 'קישור הכפתור (ריק = כתובת הביקור מההגדרות)', 'nevehairus-site' ),
				'default'     => '',
				'description' => esc_html__( 'בעמוד שיש בו טופס ביקור (ד׳) הכפתור מוביל אליו בכל מקרה.', 'nevehairus-site' ),
			]
		);

		$this->end_controls_section();

		/* ---------- תפריט הטלפון ---------- */
		$this->start_controls_section(
			'nh_menu',
			[
				'label' => esc_html__( 'תפריט (טלפון וטאבלט)', 'nevehairus-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);
		$this->add_control(
			'menu_label',
			[
				'label'       => esc_html__( 'המילה על כפתור התפריט וכותרת התפריט', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'תפריט',
				'label_block' => true,
			]
		);
		$this->add_control(
			'menu_close',
			[
				'label'       => esc_html__( 'כפתור הסגירה של התפריט', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'סגירה',
				'label_block' => true,
			]
		);
		NH_Controls::pair(
			$this,
			'menu_phone_label',
			[
				'label'   => esc_html__( 'שורת הטלפון בתפריט — המילה לפני המספר', 'nevehairus-site' ),
				'default' => 'התקשרו:',
			]
		);
		$this->add_control(
			'menu_toggles_label',
			[
				'label'       => esc_html__( 'שם קבוצת המתגים (לקורא מסך)', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'הגדרות תצוגה',
				'label_block' => true,
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
			'toggle_calm',
			[
				'label'   => esc_html__( 'מתג — מצב רגוע', 'nevehairus-site' ),
				'default' => 'מצב רגוע',
			]
		);
		$this->add_control(
			'state_on',
			[
				'label'       => esc_html__( 'מצב המתג — פועל', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'פועל',
				'description' => esc_html__( 'המילה שליד המתג כשהוא פועל (המצב עצמו נמסר לקורא המסך מהכפתור).', 'nevehairus-site' ),
			]
		);
		$this->add_control(
			'state_off',
			[
				'label'   => esc_html__( 'מצב המתג — כבוי', 'nevehairus-site' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 'כבוי',
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
		$this->add_control(
			'logo',
			[
				'label'       => esc_html__( 'סמל הלוגו (תמונה קטנה, PNG שקוף)', 'nevehairus-site' ),
				'type'        => Controls_Manager::MEDIA,
				'default'     => [
					'url' => '',
					'id'  => '',
				],
				'media_types' => [ 'image' ],
				'label_block' => true,
				'description' => esc_html__( 'רק הסמל, בלי שם: השם כתוב לידו כטקסט. הסמל דקורטיבי (בלי טקסט חלופי). בלי תמונה מוצג רק השם.', 'nevehairus-site' ),
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
				'label'       => esc_html__( 'המקטע הנוכחי בעמודים (מזהים)', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'description' => esc_html__( 'מזהי עמודים מופרדים בפסיקים, למשל 12,34. בעמודים האלה ובעמודי המשנה שלהם הקישור מסומן כמקטע שבו נמצאים (קו מתחת למילה). קישור לעמוד עצמו (בלי #) מסומן גם בלי זה.', 'nevehairus-site' ),
			]
		);
		$this->add_control(
			'nav',
			[
				'label'       => esc_html__( 'קישורים (עד חמישה)', 'nevehairus-site' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $item->get_controls(),
				'title_field' => '{{ label }}',
				'description' => esc_html__( 'תפריט קצר: עד חמישה קישורים, עד שתי מילים בכל אחד, בלי תפריטי משנה. קישור שישי והלאה לא מוצג.', 'nevehairus-site' ),
				'default'     => [
					[
						'label'       => 'הבתים שלנו',
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
						'label'       => 'מי אנחנו',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#story' ) ],
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
	 * Render the masthead and the menu dialog (header.html).
	 */
	protected function render() {
		$s   = $this->get_settings_for_display();
		$tag = ( 'header' === self::str( $s, 'html_tag' ) ) ? 'header' : 'div';

		/* links of both navigations: at most five (research/07 §5.1) */
		$items = [];
		$extra = 0;
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
			if ( count( $items ) >= self::MAX_ITEMS ) {
				++$extra;
				continue;
			}
			// NH_Html::url() is escaped for output; current_state() compares the plain URL. 'page' only for a
			// link to this very page, 'true' for the section the page belongs to (research/07 §5.3).
			$state   = NH_Html::current_state( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), self::str( $row, 'current_ids' ) );
			$items[] = '<li><a href="' . $href . '"' . ( '' !== $state ? ' aria-current="' . esc_attr( $state ) . '"' : '' ) . '>' . $label . '</a></li>';
		}
		$nav_label = trim( self::str( $s, 'nav_label' ) );
		$nav_attr  = ( '' !== $nav_label ) ? ' aria-label="' . esc_attr( $nav_label ) . '"' : '';
		$nav_list  = $items ? '<ul role="list">' . implode( '', $items ) . '</ul>' : '';

		/* phone from the settings (D9) */
		$phone   = NH_Settings::phone();
		$display = trim( self::str( $phone, 'display' ) );
		$tel     = trim( self::str( $phone, 'tel' ) );
		$tel_url = ( '' !== $display && '' !== $tel ) ? esc_url( NH_Html::tel( $tel ) ) : '';

		/* the visit button: own link, or the visit URL from the settings; data-visit-cta lets nh-core.js
			point it at this page's door when the page has one (research/07 §5.5) */
		$cta_label = self::pair_of( $s, 'cta_text' );
		$cta_url   = NH_Html::url( $s['cta_link'] ?? '' );
		if ( '' === $cta_url ) {
			$cta_url = esc_url( NH_Settings::visit_url() );
		}
		$has_cta = '' !== $cta_label && '' !== $cta_url;

		$menu_label = self::word( $s, 'menu_label' );

		/* ---------- header ---------- */
		$html  = '<' . $tag . ' class="nh-header v3-mast">';
		$html .= '<div class="nh-container nh-header__inner">';
		$html .= $this->logo_link( $s );

		if ( '' !== $nav_list ) {
			$html .= '<nav class="nh-nav"' . $nav_attr . '>' . $nav_list . '</nav>';
		}

		$html .= '<div class="nh-header__actions">';

		$html .= '<button class="v3-plainbtn" type="button" data-plain-toggle aria-pressed="false">'
			. NH_Html::icon( 'speech' )
			. '<span>' . self::pair_of( $s, 'plain_label' ) . '</span></button>';

		if ( '' !== $tel_url ) {
			$aria  = trim( trim( self::str( $s, 'phone_label' ) ) . ' ' . $display );
			$html .= '<a class="nh-phone" href="' . $tel_url . '" aria-label="' . esc_attr( $aria ) . '">'
				. NH_Html::icon( 'phone' )
				. '<span class="nh-phone__num nh-ltr">' . esc_html( $display ) . '</span></a>';
		}

		if ( $has_cta ) {
			$html .= '<a class="nh-btn nh-btn--primary nh-btn--sm v3-mast__cta" href="' . $cta_url . '" data-visit-cta>' . $cta_label . '</a>';
		}

		$html .= '<button class="nh-menu-btn" type="button" data-menu-open aria-haspopup="dialog" aria-expanded="false" aria-controls="nh-menu">'
			. NH_Html::icon( 'menu' )
			. '<span>' . esc_html( $menu_label ) . '</span></button>';

		$html .= '</div>'; // .nh-header__actions
		$html .= '</div>'; // .nh-header__inner
		$html .= '</' . $tag . '>' . "\n";

		/* ---------- menu (native <dialog>: focus trap and Esc for free; opened only by «תפריט») ---------- */
		$html .= '<dialog class="nh-menu" id="nh-menu" aria-labelledby="nh-menu-title">';
		$html .= '<div class="nh-menu__inner">';
		$html .= '<div class="nh-menu__top">'
			. '<p class="nh-menu__title" id="nh-menu-title">' . esc_html( $menu_label ) . '</p>'
			. '<button class="nh-menu__close" type="button" data-menu-close>'
			. NH_Html::icon( 'close' )
			. '<span>' . esc_html( self::word( $s, 'menu_close' ) ) . '</span></button></div>';

		$html .= '<div class="nh-menu__body">';
		if ( '' !== $nav_list ) {
			$html .= '<nav' . $nav_attr . '>' . $nav_list . '</nav>';
		}

		$html .= '<div class="nh-menu__meta">';
		if ( $has_cta ) {
			$html .= '<a class="nh-btn nh-btn--primary nh-menu__cta" href="' . $cta_url . '" data-visit-cta>' . $cta_label . '</a>';
		}
		if ( '' !== $tel_url ) {
			$prefix = self::pair_of( $s, 'menu_phone_label' );
			$html  .= '<p class="nh-menu__call"><a href="' . $tel_url . '">' . ( '' !== $prefix ? $prefix . ' ' : '' )
				. NH_Html::ltr( $display ) . '</a></p>';
		}
		$group = trim( self::str( $s, 'menu_toggles_label' ) );
		$html .= '<div class="v3-switches" role="group"' . ( '' !== $group ? ' aria-label="' . esc_attr( $group ) . '"' : '' ) . '>'
			. NH_Html::display_toggles(
				[
					'plain' => self::pair_of( $s, 'toggle_plain' ),
					'calm'  => self::pair_of( $s, 'toggle_calm' ),
				],
				[
					'on'  => self::str( $s, 'state_on' ),
					'off' => self::str( $s, 'state_off' ),
				]
			)
			. '</div>';
		// a modal dialog makes the rest of the page inert, so the menu has its own live region.
		$html .= '<p class="nh-sr-only" role="status" aria-live="polite" data-announce-menu></p>';
		$html .= '</div>'; // .nh-menu__meta
		$html .= '</div>'; // .nh-menu__body
		$html .= '</div>'; // .nh-menu__inner
		$html .= '</dialog>';

		if ( $extra > 0 ) {
			$html .= $this->nh_admin_note( __( 'בתפריט יותר מחמישה פריטים: מוצגים חמשת הראשונים', 'nevehairus-site' ) );
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * The link to the home page (research/07 §6): the emblem of the logo (decorative: alt="", its width and
	 * height from the attachment) and the name as live text in two spans, split at the last space, so the
	 * compact masthead sets it in two lines («מעון נווה / האירוס»); then the screen-reader suffix
	 * (« — לדף הבית»). Without an emblem only the name is shown.
	 *
	 * @param array $s Settings.
	 * @return string `<a class="nh-logo" …>…</a>`.
	 */
	private function logo_link( array $s ): string {
		$mark = '';
		$logo = ( isset( $s['logo'] ) && is_array( $s['logo'] ) ) ? $s['logo'] : [];
		$id   = absint( $logo['id'] ?? 0 );
		if ( $id > 0 && NH_Picture::exists( $id ) ) {
			$src  = NH_Picture::src( $id );
			$dims = NH_Picture::dims( $id );
			if ( '' !== $src ) {
				$mark = '<img class="nh-logo__mark" src="' . esc_url( $src ) . '"'
					. ( ! empty( $dims[0] ) && ! empty( $dims[1] ) ? ' width="' . (int) $dims[0] . '" height="' . (int) $dims[1] . '"' : '' )
					. ' alt="">';
			}
		}

		$name = self::name_html( self::str( $s, 'name' ), self::str( $s, 'name_plain' ) );
		$sr   = self::suffix( self::str( $s, 'home_sr' ) );
		if ( '' === $name ) {
			// the link must keep a name: the screen-reader text carries the site's name then
			$sr = self::plain_text( self::str( $s, 'name' ) ) . $sr;
		}
		$sr = ( '' !== trim( $sr ) ) ? '<span class="nh-sr-only">' . esc_html( $sr ) . '</span>' : '';
		return '<a class="nh-logo" href="' . esc_url( home_url( '/' ) ) . '">' . $mark . $name . $sr . '</a>';
	}

	/**
	 * `<span class="nh-logo__name"><span>מעון</span> <span>נווה האירוס</span></span>`, by the five states of
	 * a pair (SPEC §3.5): one element when both modes read the same, else the full and the plain name with
	 * their mode classes.
	 *
	 * @param string $full  The name.
	 * @param string $plain Its plain twin.
	 * @return string HTML ('' when there is no name to show).
	 */
	private static function name_html( string $full, string $plain ): string {
		$full  = self::plain_text( $full );
		$plain = self::plain_text( $plain );
		switch ( NH_Text::state( $full, $plain ) ) {
			case 1:
				return self::name_lines( $full, '' );
			case 2:
				return self::name_lines( $full, 'v3-full' ) . self::name_lines( $plain, 'v3-plain' );
			case 3:
				return self::name_lines( $full, 'v3-full' );
			case 4:
				return self::name_lines( $plain, 'v3-plain' );
		}
		return '';
	}

	/**
	 * The name in two spans split at its first space (one span when it is one word): on phones the two spans are two
	 * lines, and the proper name after the first word keeps together («מעון / נווה האירוס»; 16.11: «מעון נווה / האירוס»).
	 *
	 * @param string $name  Plain text.
	 * @param string $mode  Mode class ('' = none).
	 * @return string
	 */
	private static function name_lines( string $name, string $mode ): string {
		$cut   = strpos( $name, ' ' );
		$inner = ( false === $cut )
			? '<span>' . esc_html( $name ) . '</span>'
			: '<span>' . esc_html( substr( $name, 0, $cut ) ) . '</span> <span>' . esc_html( substr( $name, $cut + 1 ) ) . '</span>';
		return '<span class="nh-logo__name' . ( '' !== $mode ? ' ' . $mode : '' ) . '">' . $inner . '</span>';
	}

	/**
	 * A text a button must not lack (the word on «תפריט», «סגירה»): the setting, or its fallback when emptied.
	 *
	 * @param array  $s   Settings.
	 * @param string $key Control id.
	 * @return string Plain text (escape on output).
	 */
	private static function word( array $s, string $key ): string {
		$text = trim( self::str( $s, $key ) );
		return '' !== $text ? $text : self::FALLBACK[ $key ];
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
	 * Plain text of a control value (tags removed, whitespace collapsed), e.g. for the name of the mark.
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
