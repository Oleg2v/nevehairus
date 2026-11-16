<?php
/**
 * Widget «colophon» — the site footer, placed alone in an Elementor Pro Theme Builder
 * footer template (condition: entire site). SPEC.md §4.19.
 *
 * Reproduces prototype/src/partials/footer.html L1–58 (built: index.html `footer.nh-footer`):
 * the ruling line, the wordmark and the short description, navigation, contact details
 * (from Settings → נווה האירוס, D9), information and accessibility links with the cookie
 * settings button and the three display switches, the credits (colophon) and the bottom row.
 * The visit bar (footer L60–63) is printed by the site module (NH_Site), not here.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Repeater;

/**
 * Colophon: `<div class="nh-footer nh-on-dark v3-colophon" data-header="dark" data-running="קולופון">`
 * (or `<footer>`, D3).
 */
final class NH_Widget_Colophon extends NH_Widget_Base {

	public const ID       = 'colophon';
	public const DYNAMIC  = true; // reads NH_Settings (contact details, link tokens).
	public const KEYWORDS = [ 'footer', 'colophon', 'credits', 'תחתית', 'קולופון', 'פוטר' ];

	/**
	 * Link tokens (SPEC §3.5) that stay in-page anchors until their target is set.
	 */
	private const TOKENS = [ '#privacy', '#accessibility', '#sitemap', '#entrances', '#jobs-page' ];

	/**
	 * Panel title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'תחתית · קולופון (Theme Builder)', 'nevehairus-site' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-footer';
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
			'about',
			[
				'label'   => esc_html__( 'על המקום (ליד הסמליל)', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 3,
				'default' => 'בית לאנשים עם מוגבלות שכלית התפתחותית בנס ציונה, מאז 1957. בפיקוח משרד הרווחה והביטחון החברתי.',
			]
		);

		NH_Controls::pair(
			$this,
			'contact_title',
			[
				'label'   => esc_html__( 'כותרת — יצירת קשר', 'nevehairus-site' ),
				'default' => 'יצירת קשר',
			]
		);
		NH_Controls::pair(
			$this,
			'phone_label',
			[
				'label'   => esc_html__( 'תחילית — טלפון', 'nevehairus-site' ),
				'default' => 'טלפון:',
			]
		);
		NH_Controls::pair(
			$this,
			'fax_label',
			[
				'label'   => esc_html__( 'תחילית — פקס', 'nevehairus-site' ),
				'default' => 'פקס:',
			]
		);
		NH_Controls::pair(
			$this,
			'email_label',
			[
				'label'   => esc_html__( 'תחילית — דוא״ל (מוצג רק אם הוגדרה כתובת ציבורית)', 'nevehairus-site' ),
				'default' => 'מזכירות:',
			]
		);
		NH_Controls::pair(
			$this,
			'facebook_label',
			[
				'label'   => esc_html__( 'קישור לפייסבוק — טקסט', 'nevehairus-site' ),
				'default' => 'נווה האירוס בפייסבוק',
			]
		);
		$this->add_control(
			'external_sr',
			[
				'label'       => esc_html__( 'קישור לאתר חיצוני — המשך לקורא מסך', 'nevehairus-site' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => ' (נפתח באתר חיצוני)',
				'label_block' => true,
				'description' => esc_html__( 'נוסף לכל קישור לאתר אחר (פייסבוק, קרדיט). הטלפון, הפקס, הכתובת וקישור הפייסבוק נלקחים מההגדרות: הגדרות ← נווה האירוס.', 'nevehairus-site' ),
			]
		);

		$this->add_control(
			'toggles_label',
			[
				'label'       => esc_html__( 'מתגי התצוגה — שם הקבוצה (לקורא מסך)', 'nevehairus-site' ),
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

		NH_Controls::pair(
			$this,
			'credits',
			[
				'label'   => esc_html__( 'קולופון (פסקה אחת; מותר b ו־span עם lang/dir)', 'nevehairus-site' ),
				'type'    => 'textarea',
				'rows'    => 8,
				'default' => '<b>קולופון.</b> גופנים: <span lang="en" dir="ltr">Frank Ruhl Libre</span> (יאנק יונטף) ו־<span lang="en" dir="ltr">Assistant</span> (בן נתן ו־<span lang="en" dir="ltr">Adobe</span>), ברישיון <span lang="en" dir="ltr">SIL OFL</span>. האותיות: חמש אותיות שהשמות שלהן הם מילים של בית — ב׳ (בית), כ׳ (כף), ס׳ (סמך), ל׳ (למד) ו־ד׳ (דלת) — משמשות כאן כאדריכלות, ובתוכן יש חדרים. הכדים, המנדלה והינשופים בעמוד — עבודות יד של דיירות ודיירי נווה האירוס. הצילומים זמניים, מתוך האתר הקיים, ללא פנים מזוהות.',
			]
		);
		NH_Controls::pair(
			$this,
			'copyright',
			[
				'label'   => esc_html__( 'זכויות יוצרים (אחרי © והשנה)', 'nevehairus-site' ),
				'default' => 'מעון נווה האירוס. כל הזכויות שמורות.',
			]
		);
		NH_Controls::pair(
			$this,
			'bottom_note',
			[
				'label'   => esc_html__( 'הערה בשורה התחתונה (ריק = בלי)', 'nevehairus-site' ),
				'default' => 'אב־טיפוס לעיון פנימי · נוסחי „בשפה פשוטה” הם טיוטה לבדיקה של מומחה או מומחית לפישוט לשוני',
			]
		);
		NH_Controls::pair(
			$this,
			'dev_label',
			[
				'label'   => esc_html__( 'קרדיט — תחילית', 'nevehairus-site' ),
				'default' => 'עיצוב ופיתוח:',
			]
		);
		NH_Controls::pair(
			$this,
			'dev_name',
			[
				'label'   => esc_html__( 'קרדיט — שם', 'nevehairus-site' ),
				'default' => 'קורנגה',
			]
		);
		NH_Controls::link(
			$this,
			'dev_link',
			[
				'label'   => esc_html__( 'קרדיט — קישור', 'nevehairus-site' ),
				'default' => 'https://koranga.co.il/',
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
				'label'      => esc_html__( 'לוגו (לא חובה; מחליף את סמליל הטקסט; לרקע כהה)', 'nevehairus-site' ),
				'decorative' => false,
				'alt'        => '',
			]
		);
		NH_Controls::image(
			$this,
			'dev_logo',
			[
				'label'      => esc_html__( 'קרדיט — לוגו (לא חובה; מחליף את השם בקישור; לרקע כהה)', 'nevehairus-site' ),
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
		NH_Controls::pair(
			$this,
			'nav_title',
			[
				'label'   => esc_html__( 'כותרת — ניווט', 'nevehairus-site' ),
				'default' => 'ניווט',
			]
		);
		$nav_item = new Repeater();
		NH_Controls::pair(
			$nav_item,
			'label',
			[
				'label'   => esc_html__( 'טקסט הקישור', 'nevehairus-site' ),
				'default' => '',
			]
		);
		NH_Controls::link(
			$nav_item,
			'link',
			[
				'label'   => esc_html__( 'קישור', 'nevehairus-site' ),
				'default' => '',
			]
		);
		$this->add_control(
			'nav',
			[
				'label'       => esc_html__( 'קישורים', 'nevehairus-site' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $nav_item->get_controls(),
				'title_field' => '{{ label }}',
				'default'     => [
					[
						'label'       => 'מי אנחנו',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#story' ) ],
					],
					[
						'label'       => 'מסגרות המגורים',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#homes' ) ],
					],
					[
						'label'       => 'החיים אצלנו',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#day' ) ],
					],
					[
						'label'       => 'דרושים',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#jobs' ) ],
					],
					[
						'label'       => 'יצירת קשר',
						'label_plain' => '',
						'link'        => [ 'url' => home_url( '/#door' ) ],
					],
				],
			]
		);
		$this->end_controls_section();

		/* ---------- מידע ונגישות ---------- */
		$this->start_controls_section(
			'nh_info',
			[
				'label' => esc_html__( 'מידע ונגישות', 'nevehairus-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);
		NH_Controls::pair(
			$this,
			'info_title',
			[
				'label'   => esc_html__( 'כותרת — מידע ונגישות', 'nevehairus-site' ),
				'default' => 'מידע ונגישות',
			]
		);
		$info_item = new Repeater();
		$info_item->add_control(
			'type',
			[
				'label'   => esc_html__( 'סוג', 'nevehairus-site' ),
				'type'    => Controls_Manager::SELECT,
				'options' => [
					'link'    => esc_html__( 'קישור', 'nevehairus-site' ),
					'cookies' => esc_html__( 'כפתור „הגדרות עוגיות”', 'nevehairus-site' ),
				],
				'default' => 'link',
			]
		);
		NH_Controls::pair(
			$info_item,
			'label',
			[
				'label'   => esc_html__( 'טקסט', 'nevehairus-site' ),
				'default' => '',
			]
		);
		NH_Controls::link(
			$info_item,
			'link',
			[
				'label'   => esc_html__( 'קישור (מותר: #privacy, #accessibility, #sitemap, #entrances — היעד נקבע בהגדרות)', 'nevehairus-site' ),
				'default' => '',
			]
		);
		$this->add_control(
			'info',
			[
				'label'       => esc_html__( 'קישורים', 'nevehairus-site' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $info_item->get_controls(),
				'title_field' => '{{ label }}',
				'default'     => [
					[
						'type'        => 'link',
						'label'       => 'הצהרת נגישות',
						'label_plain' => '',
						'link'        => [ 'url' => '#accessibility' ],
					],
					[
						'type'        => 'link',
						'label'       => 'מדיניות פרטיות',
						'label_plain' => '',
						'link'        => [ 'url' => '#privacy' ],
					],
					[
						'type'        => 'cookies',
						'label'       => 'הגדרות עוגיות',
						'label_plain' => '',
						'link'        => [ 'url' => '' ],
					],
					[
						'type'        => 'link',
						'label'       => 'מפת אתר',
						'label_plain' => '',
						'link'        => [ 'url' => '#sitemap' ],
					],
					[
						'type'        => 'link',
						'label'       => 'מפרט כניסות נגישות (PDF)',
						'label_plain' => '',
						'link'        => [ 'url' => '#entrances' ],
					],
				],
			]
		);
		$this->end_controls_section();

		/* ---------- מקטע ---------- */
		$this->start_controls_section(
			'nh_section',
			[
				'label' => esc_html__( 'מקטע', 'nevehairus-site' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);
		NH_Controls::pair(
			$this,
			'running',
			[
				'label'   => esc_html__( 'שם המקטע בכותרת העליונה', 'nevehairus-site' ),
				'default' => 'קולופון',
			]
		);
		$this->add_control(
			'html_tag',
			[
				'label'       => esc_html__( 'תגית העטיפה', 'nevehairus-site' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => [
					'div'    => 'div',
					'footer' => 'footer',
				],
				'default'     => 'div',
				'description' => esc_html__( 'ברירת המחדל div: תבנית התחתית של Elementor Pro כבר עוטפת את הווידג׳ט באזור footer. לבחור footer רק אם אין אזור כזה סביבו (אחרת יהיו בעמוד שני אזורי תחתית).', 'nevehairus-site' ),
			]
		);
		$this->end_controls_section();
	}

	/**
	 * Render the colophon (footer L1–58).
	 */
	protected function render() {
		$s      = $this->get_settings_for_display();
		$tag    = ( 'footer' === self::str( $s, 'html_tag' ) ) ? 'footer' : 'div';
		$ext_sr = self::suffix( self::str( $s, 'external_sr' ) );

		$html = $this->nh_section_open(
			// not a spread: no anchor control; the running head comes from `running` / `running_plain`.
			array_merge(
				[
					'anchor'        => '',
					'running'       => '',
					'running_plain' => '',
				],
				$s
			),
			[
				'class'      => 'nh-footer nh-on-dark v3-colophon',
				'header'     => 'dark',
				'labelledby' => null,
				'tag'        => $tag,
			]
		);
		$html .= '<div class="nh-container">';
		$html .= $this->nh_rule();
		$html .= '<div class="nh-footer__grid">';

		/* brand: logo link + short description */
		$html .= '<div class="nh-footer__brand">';
		$html .= $this->logo_link( $s );
		$html .= NH_Text::block(
			self::str( $s, 'about' ),
			self::str( $s, 'about_plain' ),
			[
				'full_tag'    => 'p',
				'plain_tag'   => 'p',
				'plain_lines' => null,
			]
		);
		$html .= '</div>';

		/* navigation */
		$links = '';
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
			$links .= '<li>' . $this->link( $href, $label, $ext_sr, self::str( $row, 'label' ) ) . '</li>';
		}
		if ( '' !== $links ) {
			$html .= '<div>' . self::h2( self::pair_of( $s, 'nav_title' ) ) . '<ul role="list">' . $links . '</ul></div>';
		}

		/* contact details from Settings → נווה האירוס (D9) */
		$contact = $this->contact_items( $s, $ext_sr );
		if ( '' !== $contact ) {
			$html .= '<div>' . self::h2( self::pair_of( $s, 'contact_title' ) ) . '<ul role="list">' . $contact . '</ul></div>';
		}

		/* information and accessibility + the display switches */
		$info = '';
		$rows = ( isset( $s['info'] ) && is_array( $s['info'] ) ) ? $s['info'] : [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = self::pair_of( $row, 'label' );
			if ( '' === $label ) {
				continue;
			}
			if ( 'cookies' === self::str( $row, 'type' ) ) {
				// handled by nh-core.js (delegated [data-cookie-settings]).
				$info .= '<li><button class="nh-linkbtn" type="button" data-cookie-settings>' . $label . '</button></li>';
				continue;
			}
			$href = NH_Html::url( $row['link'] ?? '' );
			if ( '' === $href ) {
				continue;
			}
			$info .= '<li>' . $this->link( $href, $label, $ext_sr, self::str( $row, 'label' ) ) . '</li>';
		}
		$group   = trim( self::str( $s, 'toggles_label' ) );
		$toggles = '<div class="v3-footer-toggles" role="group"' . ( '' !== $group ? ' aria-label="' . esc_attr( $group ) . '"' : '' ) . '>'
			. NH_Html::display_toggles(
				[
					'plain'  => self::pair_of( $s, 'toggle_plain' ),
					'motion' => self::pair_of( $s, 'toggle_motion' ),
					'calm'   => self::pair_of( $s, 'toggle_calm' ),
				]
			)
			. '</div>';
		$html   .= '<div>' . self::h2( self::pair_of( $s, 'info_title' ) ) . ( '' !== $info ? '<ul role="list">' . $info . '</ul>' : '' ) . $toggles . '</div>';

		$html .= '</div>'; // .nh-footer__grid

		/* credits: one paragraph, newlines become spaces */
		$html .= NH_Text::block(
			self::one_line( self::str( $s, 'credits' ) ),
			self::one_line( self::str( $s, 'credits_plain' ) ),
			[
				'full_tag'    => 'p',
				'full_class'  => 'v3-colophon__credits',
				'plain_tag'   => 'p',
				'plain_class' => 'v3-colophon__credits',
				'plain_lines' => null,
			]
		);

		/* bottom row */
		$html     .= '<div class="nh-footer__bottom">';
		$copyright = self::pair_of( $s, 'copyright' );
		$html     .= '<p>© <span data-year>' . esc_html( (string) wp_date( 'Y' ) ) . '</span>' . ( '' !== $copyright ? ' ' . $copyright : '' ) . '</p>';
		$note      = self::pair_of( $s, 'bottom_note' );
		if ( '' !== $note ) {
			$html .= '<p>' . $note . '</p>';
		}
		$html .= $this->credit( $s, $ext_sr );
		$html .= '</div>'; // .nh-footer__bottom

		$html .= '</div>'; // .nh-container
		$html .= $this->nh_section_close( $tag );

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * The contact list items: phone, fax, public e-mail, postal address, Facebook.
	 *
	 * @param array  $s      Settings.
	 * @param string $ext_sr Screen-reader suffix of external links.
	 * @return string `<li>` items ('' when there is nothing to show).
	 */
	private function contact_items( array $s, string $ext_sr ): string {
		$out = '';

		$phone   = NH_Settings::phone();
		$display = trim( self::str( $phone, 'display' ) );
		$tel     = trim( self::str( $phone, 'tel' ) );
		if ( '' !== $display ) {
			$num  = '<span class="nh-ltr">' . esc_html( $display ) . '</span>';
			$out .= '<li>' . self::lead_in( self::pair_of( $s, 'phone_label' ) )
				. ( '' !== $tel ? '<a href="' . esc_url( NH_Html::tel( $tel ) ) . '">' . $num . '</a>' : $num ) . '</li>';
		}

		$fax = self::setting( 'fax' );
		if ( '' !== $fax ) {
			$out .= '<li>' . self::lead_in( self::pair_of( $s, 'fax_label' ) ) . '<span class="nh-ltr">' . esc_html( $fax ) . '</span></li>';
		}

		// only when the client chose to publish an address (SPEC §13 q2); empty by default.
		$email = trim( NH_Settings::email_public() );
		if ( '' !== $email ) {
			$out .= '<li>' . self::lead_in( self::pair_of( $s, 'email_label' ) )
				. '<a href="' . esc_url( 'mailto:' . $email ) . '"><span class="nh-ltr">' . esc_html( $email ) . '</span></a></li>';
		}

		$address = self::setting( 'address' );
		if ( '' !== $address ) {
			$out .= '<li>' . esc_html( $address ) . '</li>';
		}

		$facebook = self::setting( 'facebook_url' );
		$fb_label = self::pair_of( $s, 'facebook_label' );
		if ( '' !== $facebook && '' !== $fb_label ) {
			$href = esc_url( $facebook );
			if ( '' !== $href ) {
				$out .= '<li>' . $this->link( $href, $fb_label, $ext_sr, '' ) . '</li>';
			}
		}

		return $out;
	}

	/**
	 * A text link. External links (another host) get rel="noopener" and the screen-reader
	 * suffix, as in the prototype; a link token that is still unresolved (no target page set)
	 * stays an in-page anchor and gets an editors-only note.
	 *
	 * @param string $href   Escaped URL (NH_Html::url() / esc_url()).
	 * @param string $label  Label HTML (NH_Text::pair()).
	 * @param string $ext_sr Screen-reader suffix of external links.
	 * @param string $name   Plain label for the editors' note.
	 * @return string HTML.
	 */
	private function link( string $href, string $label, string $ext_sr, string $name ): string {
		$external = NH_Html::is_external( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$out      = '<a href="' . $href . '"' . ( $external ? ' rel="noopener"' : '' ) . '>' . $label;
		if ( $external && '' !== $ext_sr ) {
			$out .= '<span class="nh-sr-only">' . esc_html( $ext_sr ) . '</span>';
		}
		$out .= '</a>';

		if ( in_array( $href, self::TOKENS, true ) ) {
			$name  = self::plain_text( $name );
			$out  .= $this->nh_admin_note(
				'#privacy' === $href
					/* translators: %s: the link text. */
					? sprintf( __( 'הקישור „%s” עדיין לא מחובר: יש לבחור עמוד מדיניות פרטיות בהגדרות ← פרטיות.', 'nevehairus-site' ), $name )
					/* translators: %s: the link text. */
					: sprintf( __( 'הקישור „%s” עדיין לא מחובר: יש לבחור את היעד בהגדרות ← נווה האירוס.', 'nevehairus-site' ), $name )
			);
		}
		return $out;
	}

	/**
	 * The agency credit: «עיצוב ופיתוח: קורנגה», or the agency logo inside the link when set.
	 *
	 * @param array  $s      Settings.
	 * @param string $ext_sr Screen-reader suffix of external links.
	 * @return string `<p class="v3-dev">…</p>` or ''.
	 */
	private function credit( array $s, string $ext_sr ): string {
		$label = self::pair_of( $s, 'dev_label' );
		$name  = self::pair_of( $s, 'dev_name' );
		$href  = NH_Html::url( $s['dev_link'] ?? '' );

		$inner = '';
		$img   = NH_Controls::image_args( $s, 'dev_logo' );
		$id    = (int) ( $img['photo'] ?? 0 );
		if ( $id > 0 ) {
			// the logo names the link: alt override, else the media library's alt, else the name;
			// a decorative logo gets alt="" and the name goes into a visually hidden span.
			$dev = self::plain_text( self::str( $s, 'dev_name' ) );
			$o   = [ 'sizes' => '10rem' ];
			if ( ! empty( $img['decorative'] ) ) {
				$o['decorative'] = true;
			} else {
				$alt      = trim( (string) ( $img['alt'] ?? '' ) );
				$alt      = ( '' !== $alt ) ? $alt : NH_Picture::alt( $id );
				$o['alt'] = ( '' !== trim( $alt ) ) ? $alt : $dev;
			}
			$inner = NH_Picture::render( $id, $o );
			if ( '' !== $inner && ! empty( $o['decorative'] ) && '' !== $dev ) {
				$inner .= '<span class="nh-sr-only">' . esc_html( $dev ) . '</span>';
			}
		}
		if ( '' === $inner ) {
			$inner = $name;
		}
		if ( '' === $inner ) {
			return '';
		}

		$lead = self::lead_in( $label );
		if ( '' === $href ) {
			return '<p class="v3-dev">' . $lead . $inner . '</p>';
		}
		return '<p class="v3-dev">' . $lead . $this->link( $href, $inner, $ext_sr, '' ) . '</p>';
	}

	/**
	 * The link to the home page: the logo from the media library when one is set, else the wordmark,
	 * then the screen-reader suffix (« — לדף הבית»). The link always has a name: an informative logo
	 * gets the alt override, else the media library's alt, else the wordmark text («מעון נווה האירוס»);
	 * a logo marked decorative gets alt="" and the wordmark text goes into the visually hidden span.
	 *
	 * @param array $s Settings.
	 * @return string `<a class="nh-footer__logo" …>…</a>`.
	 */
	private function logo_link( array $s ): string {
		$sr    = self::suffix( self::str( $s, 'home_sr' ) );
		$name  = self::plain_text( self::str( $s, 'wordmark_small' ) . ' ' . self::str( $s, 'wordmark' ) );
		$inner = '';
		$img   = NH_Controls::image_args( $s, 'logo' );
		$id    = (int) ( $img['photo'] ?? 0 );
		if ( $id > 0 ) {
			$o = [ 'sizes' => '16rem' ];
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
			$small = self::pair_of( $s, 'wordmark_small' );
			$word  = self::pair_of( $s, 'wordmark' );
			if ( '' !== $small || '' !== $word ) {
				$inner = '<span class="nh-wordmark">' . ( '' !== $small ? '<small>' . $small . '</small>' . ( '' !== $word ? ' ' : '' ) : '' ) . $word . '</span>';
			}
		}
		$sr = ( '' !== trim( $sr ) ) ? '<span class="nh-sr-only">' . esc_html( $sr ) . '</span>' : '';
		return '<a class="nh-footer__logo" href="' . esc_url( home_url( '/' ) ) . '">' . $inner . $sr . '</a>';
	}

	/**
	 * `<h2>` of a footer column, '' when its text is empty.
	 *
	 * @param string $inner HTML (NH_Text::pair()).
	 * @return string
	 */
	private static function h2( string $inner ): string {
		return ( '' === $inner ) ? '' : '<h2>' . $inner . '</h2>';
	}

	/**
	 * A label followed by one space («טלפון: »), '' when empty.
	 *
	 * @param string $inner HTML (NH_Text::pair()).
	 * @return string
	 */
	private static function lead_in( string $inner ): string {
		return ( '' === $inner ) ? '' : $inner . ' ';
	}

	/**
	 * Newlines (and the spaces around them) become one space: the credits are one paragraph.
	 *
	 * @param string $text Raw control value.
	 * @return string
	 */
	private static function one_line( string $text ): string {
		return trim( (string) preg_replace( '/\s*[\r\n]+\s*/u', ' ', $text ) );
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
	 * A text value from Settings → נווה האירוס, trimmed ('' when missing or not scalar).
	 *
	 * @param string $key NH_Settings key.
	 * @return string Raw text (escape on output).
	 */
	private static function setting( string $key ): string {
		$v = NH_Settings::get( $key );
		return is_scalar( $v ) ? trim( (string) $v ) : '';
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
	 * A screen-reader suffix such as « (נפתח באתר חיצוני)»: one leading space, whatever the editor typed.
	 *
	 * @param string $text Raw control value.
	 * @return string Plain text ('' when empty).
	 */
	private static function suffix( string $text ): string {
		$text = trim( $text );
		return ( '' === $text ) ? '' : ' ' . $text;
	}
}
