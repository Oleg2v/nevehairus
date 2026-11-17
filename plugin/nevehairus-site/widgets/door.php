<?php
/**
 * Widget "ד׳ · הדלת וטופס ביקור" (door): the wide ד of the door spread, the visit form standing in
 * its doorway and the phone beside it. Spec §4.12; prototype index L334–377 (home page: the defaults)
 * and program-main-home L158–233 (house page: the same widget with other settings).
 *
 * The fields, alerts, notes and hidden fields of the form are rendered by NH_Forms (§6.3); this
 * widget passes texts and switches only. Phone, address, public e-mail, recipients and the privacy
 * notice live in Settings → נווה האירוס (§7, D9), so every door on the site shows the same data.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'NH_Widget_Base' ) ) {
	return;
}

final class NH_Widget_Door extends NH_Widget_Base {
	public const ID = 'door';
	// nh-letters: the ink of the ד (.v3-letter__ink, also in forced colours) lives in letters.css.
	public const STYLE_DEPS  = [ 'nh-letters', 'nh-forms' ];
	public const SCRIPT_DEPS = [ 'nh-forms' ];
	public const DYNAMIC     = true;
	public const KEYWORDS    = [ 'door', 'dalet', 'visit', 'form', 'contact', 'דלת', 'ד', 'ביקור', 'טופס', 'יצירת קשר' ];

	/** The NH_Forms form type (§6.1). */
	private const FORM = 'visit';

	/** The optional fields between the name/phone row and the message, in their normative order (§4.12). */
	private const OPTIONAL = [ 'email', 'who', 'program' ];

	public function get_title() {
		return __( 'ד׳ · הדלת וטופס ביקור', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	protected function register_controls() {
		// Content: heading, lead, and the phone and address beside the form.
		$this->start_controls_section( 'nh_content', [ 'label' => __( 'תוכן', 'nevehairus-site' ) ] );
		NH_Controls::heading(
			$this,
			[
				'default' => 'הדלת פתוחה. בואו להכיר.',
				'plain'   => 'רוצים לבוא לבקר?',
				'dot'     => 'citrus',
			]
		);
		NH_Controls::pair(
			$this,
			'lead',
			[
				'label'   => __( 'פתיח', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'הדרך הטובה ביותר להכיר בית היא לבקר בו. נשמח לארח אתכם למפגש היכרות.',
				'plain'   => "אפשר להתקשר אלינו.\nאפשר גם למלא את הטופס.",
			]
		);
		$this->door_switch( 'show_contact', __( 'טלפון וכתובת ליד הטופס', 'nevehairus-site' ), 'yes', [ 'separator' => 'before' ] );
		NH_Controls::pair(
			$this,
			'contact_label',
			[
				'label'     => __( 'כותרת מעל הטלפון', 'nevehairus-site' ),
				'default'   => 'או בטלפון',
				'condition' => [ 'show_contact' => 'yes' ],
			]
		);
		$this->add_control(
			'call_label',
			[
				'label'       => __( 'שם הקישור לקורא מסך, לפני המספר', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'התקשרו:',
				'label_block' => true,
				'condition'   => [ 'show_contact' => 'yes' ],
			]
		);
		$this->door_note( 'contact_note', __( 'מספר הטלפון, הכתובת והדוא״ל הציבורי נלקחים מהגדרות ← נווה האירוס.', 'nevehairus-site' ), [ 'show_contact' => 'yes' ] );
		$this->end_controls_section();

		// The visit form.
		$this->start_controls_section( 'nh_form', [ 'label' => __( 'טופס', 'nevehairus-site' ) ] );
		$this->door_note( 'form_note', __( 'לאן נשלחות הבקשות, הודעת הפרטיות שמתחת לתיבת ההסכמה והודעות השגיאה — בהגדרות ← נווה האירוס. בלי כתובת נמען הטופס לא שולח, ועורכי האתר רואים על כך הודעה מעליו.', 'nevehairus-site' ) );
		NH_Controls::pair(
			$this,
			'form_title',
			[
				'label'   => __( 'כותרת הטופס (גם השם של הטופס לקורא מסך)', 'nevehairus-site' ),
				'default' => 'בקשה לביקור',
			]
		);
		NH_Controls::pair(
			$this,
			'success_title',
			[
				'label'   => __( 'הודעת תודה — כותרת', 'nevehairus-site' ),
				'default' => 'תודה! הבקשה התקבלה.',
			]
		);
		NH_Controls::pair(
			$this,
			'success_text',
			[
				'label'   => __( 'הודעת תודה — טקסט', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'נחזור אליכם לתיאום.',
			]
		);
		NH_Controls::pair(
			$this,
			'required_note',
			[
				'label'   => __( 'הסבר על שדות החובה', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'שדות המסומנים ב־<span class="nh-req" aria-hidden="true">*</span><span class="nh-sr-only">כוכבית</span> הם שדות חובה.',
				'plain'   => 'צריך למלא את השדות עם כוכבית <span class="nh-req" aria-hidden="true">*</span>.',
			]
		);
		NH_Controls::pair(
			$this,
			'label_name',
			[
				'label'   => __( 'שדה השם — תווית', 'nevehairus-site' ),
				'default' => 'שם מלא',
			]
		);
		NH_Controls::pair(
			$this,
			'label_phone',
			[
				'label'   => __( 'שדה הטלפון — תווית', 'nevehairus-site' ),
				'default' => 'טלפון',
			]
		);

		$this->door_switch( 'show_email', __( 'שדה דוא״ל', 'nevehairus-site' ), '', [ 'separator' => 'before' ] );
		NH_Controls::pair(
			$this,
			'label_email',
			[
				'label'     => __( 'שדה הדוא״ל — תווית', 'nevehairus-site' ),
				'default'   => 'דוא״ל',
				'condition' => [ 'show_email' => 'yes' ],
			]
		);

		$this->door_switch( 'show_who', __( 'שדה „מי ממלא את הטופס”', 'nevehairus-site' ), 'yes', [ 'separator' => 'before' ] );
		NH_Controls::pair(
			$this,
			'label_who',
			[
				'label'     => __( 'שדה „מי ממלא” — תווית', 'nevehairus-site' ),
				'default'   => 'מי ממלא את הטופס?',
				'condition' => [ 'show_who' => 'yes' ],
			]
		);
		NH_Controls::lines(
			$this,
			'who_options',
			[
				'label'       => __( 'שדה „מי ממלא” — אפשרויות', 'nevehairus-site' ),
				'description' => __( 'אפשרות בכל שורה. השורה הראשונה מוצגת כל עוד לא נבחר דבר.', 'nevehairus-site' ),
				'default'     => "בחירה…\nאני, בשביל עצמי\nהורה או קרוב משפחה\nאפוטרופוס או אפוטרופסית\nתומך או תומכת בקבלת החלטות\nעובד סוציאלי או עובדת סוציאלית\nאחר",
				'rows'        => 7,
				'condition'   => [ 'show_who' => 'yes' ],
			]
		);

		$this->door_switch( 'show_program', __( 'שדה „איזה בית מעניין אתכם”', 'nevehairus-site' ), 'yes', [ 'separator' => 'before' ] );
		NH_Controls::pair(
			$this,
			'label_program',
			[
				'label'     => __( 'שדה „איזה בית” — תווית', 'nevehairus-site' ),
				'default'   => 'איזה בית מעניין אתכם?',
				'condition' => [ 'show_program' => 'yes' ],
			]
		);
		NH_Controls::lines(
			$this,
			'program_options',
			[
				'label'       => __( 'שדה „איזה בית” — אפשרויות', 'nevehairus-site' ),
				'description' => __( 'אפשרות בכל שורה. השורה הראשונה מוצגת כל עוד לא נבחר דבר.', 'nevehairus-site' ),
				'default'     => "עוד לא יודעים\nמעון ראשי — בית לחיים\nדיור בקהילה\nדיור לצעירים על הרצף האוטיסטי",
				'rows'        => 4,
				'condition'   => [ 'show_program' => 'yes' ],
			]
		);

		$this->door_switch( 'show_message', __( 'שדה הודעה חופשית', 'nevehairus-site' ), '', [ 'separator' => 'before' ] );
		NH_Controls::pair(
			$this,
			'label_message',
			[
				'label'     => __( 'שדה ההודעה — תווית', 'nevehairus-site' ),
				'default'   => 'משהו שחשוב לנו לדעת?',
				'condition' => [ 'show_message' => 'yes' ],
			]
		);
		NH_Controls::pair(
			$this,
			'hint_message',
			[
				'label'     => __( 'שדה ההודעה — הסבר מתחת לשדה', 'nevehairus-site' ),
				'default'   => 'אין צורך לפרט כאן מידע רפואי. נדבר על הכול בשיחה.',
				'plain'     => 'לא צריך לכתוב כאן מידע רפואי. נדבר על זה בשיחה.',
				'condition' => [ 'show_message' => 'yes' ],
			]
		);

		NH_Controls::pair(
			$this,
			'consent',
			[
				'label'   => __( 'תיבת ההסכמה — טקסט (הקישור #privacy מוביל למדיניות הפרטיות)', 'nevehairus-site' ),
				'type'    => 'textarea',
				'default' => 'קראתי את <a href="#privacy">מדיניות הפרטיות</a> ואני מסכים/ה שתיצרו איתי קשר לגבי' . "\u{00A0}" . 'הביקור.',   // never «הביקור.» alone (16.11)
				'plain'   => 'אני מסכים או מסכימה שתתקשרו אליי.<br><a href="#privacy">מה עושים עם הפרטים' . "\u{00A0}" . 'שלי?</a>',   // easy read: the question on its own line (16.11)
			]
		);
		$this->add_control(
			'consent_error_label',
			[
				'label'       => __( 'תיבת ההסכמה — שמה ברשימת השגיאות', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'אישור מדיניות הפרטיות',
				'label_block' => true,
			]
		);
		NH_Controls::pair(
			$this,
			'submit',
			[
				'label'   => __( 'כפתור השליחה', 'nevehairus-site' ),
				'default' => 'שליחת הבקשה',
			]
		);
		$this->end_controls_section();

		NH_Controls::section( $this, [ 'anchor' => 'door' ] );
	}

	protected function render() {
		$s      = $this->get_settings_for_display();
		$state  = NH_Forms::state( (string) $this->get_id() );
		$prefix = $this->door_prefix();

		// The fields in the normative layout (§4.12): name + phone; the enabled optional fields, two per
		// row (a single leftover field stands alone); then the message, full width.
		$specs = [
			'name'  => [],
			'phone' => [],
		];
		foreach ( self::OPTIONAL as $key ) {
			if ( 'yes' === self::door_text( $s, 'show_' . $key ) ) {
				$specs[ $key ] = 'email' === $key ? [] : [ 'options' => self::door_text( $s, $key . '_options' ) ];
			}
		}
		if ( 'yes' === self::door_text( $s, 'show_message' ) ) {
			$specs['message'] = self::door_has_text( $s, 'hint_message' ) ? [ 'hint' => self::door_pair( $s, 'hint_message' ) ] : [];
		}

		$fields = [];
		$labels = [];
		foreach ( $specs as $key => $extra ) {
			$label          = self::door_pair( $s, 'label_' . $key );
			$labels[ $key ] = self::door_flat( $label );
			$fields[ $key ] = NH_Forms::field(
				self::FORM,
				$key,
				[
					'id'    => $this->nh_uid( $key ),
					'label' => $label,
				] + $extra,
				$state
			);
		}
		$consent           = self::door_pair( $s, 'consent' );
		$error_label       = trim( self::door_text( $s, 'consent_error_label' ) );
		$labels['consent'] = '' !== $error_label ? $error_label : self::door_flat( $consent );

		$rows     = self::door_row( $fields['name'] . $fields['phone'] );
		$optional = array_values( array_intersect_key( $fields, array_flip( self::OPTIONAL ) ) );
		foreach ( array_chunk( $optional, 2 ) as $chunk ) {
			$rows .= 2 === count( $chunk ) ? self::door_row( implode( '', $chunk ) ) : $chunk[0];
		}
		$rows .= $fields['message'] ?? '';

		$submit = NH_Text::pair( self::door_text( $s, 'submit' ), self::door_text( $s, 'submit_plain' ) );
		if ( '' === $submit ) {
			$submit = esc_html__( 'שליחה', 'nevehairus-site' ); // a submit button always has a name
		}

		$form = NH_Forms::summary( $state, $labels, $prefix )
			. NH_Forms::required_note( self::door_text( $s, 'required_note' ), self::door_text( $s, 'required_note_plain' ) )
			. $rows
			. NH_Forms::honeypot( $prefix )
			. NH_Forms::check(
				self::FORM,
				'consent',
				[
					'id'          => $this->nh_uid( 'consent' ),
					'label'       => $consent,
					'error_label' => $error_label,
				],
				$state
			)
			. NH_Forms::privacy_note( self::FORM )
			. NH_Forms::hidden_fields( self::FORM, (string) $this->get_id(), $this->door_return_url( $s ) )
			. '<div><button class="nh-btn nh-btn--primary" type="submit">' . $submit
			. NH_Html::icon( 'arrow-fwd', 'nh-btn__icon nh-btn__icon--fwd' ) . '</button></div>';

		// The form's name for screen readers is the full text of its visible title (prototype aria-label).
		$form_title = NH_Text::pair( self::door_text( $s, 'form_title' ), self::door_text( $s, 'form_title_plain' ) );
		$form_name  = self::door_flat( self::door_pair( $s, 'form_title' ) );

		$box = ( '' !== $form_title ? '<h3>' . $form_title . '</h3>' : '' )
			. NH_Forms::recipient_note( self::FORM )
			. NH_Forms::success( self::door_pair( $s, 'success_title' ), self::door_pair( $s, 'success_text' ), $state )
			. '<form class="nh-form" data-validate action="' . esc_url( NH_Forms::action_url() ) . '" method="post"'
			. ( '' !== $form_name ? ' aria-label="' . esc_attr( $form_name ) . '"' : '' )
			. ' data-nh-form="visit">' . $form . '</form>';

		$titled  = self::door_has_text( $s, 'title' );
		$top     = ( $titled ? $this->nh_h2( $s ) : '' ) . $this->nh_lead( $s );
		$section = [
			'class' => 'v3-door v3-spread',
			'tone'  => 'iris',
		];
		if ( ! $titled ) {
			$section['labelledby'] = null; // no heading to point to
		}

		$html = $this->nh_section_open( $s, $section )
			. '<div class="nh-container v3-door__grid">'
			. $this->nh_rule()
			. ( '' !== $top ? '<div class="v3-door__top">' . $top . '</div>' : '' )
			. '<div class="v3-door__letter" aria-hidden="true">' . NH_Letters::svg( 'dalet', 600 ) . '</div>' // v3.2: a plain ד, wide screens only
			. '<div class="v3-door__way">'
			. '<div class="v3-door__in">'
			. '<div class="v3-door__form">' . $box . '</div>'
			. $this->door_contact( $s )
			. '</div>'
			. '</div>'
			. '</div>'
			. $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * The phone and the address beside the form. The data come from Settings → נווה האירוס; the e-mail
	 * link is added only when a public e-mail is set there.
	 */
	private function door_contact( array $s ): string {
		if ( 'yes' !== self::door_text( $s, 'show_contact' ) ) {
			return '';
		}
		$out   = '';
		$label = NH_Text::pair( self::door_text( $s, 'contact_label' ), self::door_text( $s, 'contact_label_plain' ) );
		if ( '' !== $label ) {
			$out .= '<p class="v3-door__label">' . $label . '</p>';
		}

		$phone   = NH_Settings::phone();
		$display = trim( (string) ( $phone['display'] ?? '' ) );
		$href    = NH_Html::tel( (string) ( $phone['tel'] ?? '' ) );
		if ( '' !== $display && '' !== $href && 'tel:' !== $href ) {
			$name = trim( self::door_text( $s, 'call_label' ) . ' ' . $display );
			$out .= '<p class="v3-door__phone"><a href="' . esc_url( $href ) . '" aria-label="' . esc_attr( $name ) . '">'
				. '<span class="nh-ltr">' . esc_html( $display ) . '</span></a></p>';
		}

		$parts   = [];
		$address = trim( (string) NH_Settings::get( 'address' ) );
		if ( '' !== $address ) {
			$parts[] = esc_html( $address );
		}
		$email = NH_Settings::email_public();
		if ( '' !== $email ) {
			$parts[] = '<a href="' . esc_url( 'mailto:' . $email ) . '"><span class="nh-ltr">' . esc_html( $email ) . '</span></a>';
		}
		if ( $parts ) {
			$out .= '<p class="v3-door__addr">' . implode( ' · ', $parts ) . '</p>';
		}

		return '' !== $out ? '<div class="v3-door__contact">' . $out . '</div>' : '';
	}

	/** Where the handler sends a visitor without JavaScript back to (PRG, D7): this page, at the spread. */
	private function door_return_url( array $s ): string {
		$url    = get_permalink();
		$url    = is_string( $url ) && '' !== $url ? $url : home_url( '/' );
		$anchor = sanitize_html_class( self::door_text( $s, 'anchor' ) );
		return '' !== $anchor ? $url . '#' . $anchor : $url;
	}

	/** The id prefix of this instance: the anchor, or nh-{element id} (§2.5) — nh_uid( $k ) is "{prefix}-{k}". */
	private function door_prefix(): string {
		return substr( $this->nh_uid( 'x' ), 0, -2 );
	}

	/** A yes/no switch. */
	private function door_switch( string $id, string $label, string $default, array $extra = [] ): void {
		$this->add_control(
			$id,
			[
				'label'        => $label,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'כן', 'nevehairus-site' ),
				'label_off'    => __( 'לא', 'nevehairus-site' ),
				'return_value' => 'yes',
				'default'      => $default,
			] + $extra
		);
	}

	/** A note for the editor in the panel (not printed on the page). */
	private function door_note( string $id, string $text, array $condition = [] ): void {
		$args = [
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => esc_html( $text ),
			'content_classes' => 'elementor-descriptor',
		];
		if ( $condition ) {
			$args['condition'] = $condition;
		}
		$this->add_control( $id, $args );
	}

	/** Two fields side by side. */
	private static function door_row( string $fields ): string {
		return '<div class="nh-form__row nh-form__row--2">' . $fields . '</div>';
	}

	/** A setting as a string ('' when it is missing or not a scalar). */
	private static function door_text( array $s, string $key ): string {
		return isset( $s[ $key ] ) && is_scalar( $s[ $key ] ) ? (string) $s[ $key ] : '';
	}

	/** The [ full, plain ] pair of a text control and its twin. */
	private static function door_pair( array $s, string $id ): array {
		return [ self::door_text( $s, $id ), self::door_text( $s, $id . '_plain' ) ];
	}

	/** True when the pair renders anything (states 1–4 of §3.5). */
	private static function door_has_text( array $s, string $id ): bool {
		$full  = trim( self::door_text( $s, $id ) );
		$plain = trim( self::door_text( $s, $id . '_plain' ) );
		return '' !== $full || ( '' !== $plain && NH_Text::HIDE !== $plain );
	}

	/**
	 * One line of plain text from a pair, for an attribute or the error summary: the full text, else the
	 * plain twin; tags dropped. The caller escapes it.
	 */
	private static function door_flat( array $pair ): string {
		$full  = trim( (string) ( $pair[0] ?? '' ) );
		$plain = trim( (string) ( $pair[1] ?? '' ) );
		$text  = '' !== $full ? $full : ( NH_Text::HIDE !== $plain ? $plain : '' );
		$text  = strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- plain text for an attribute, escaped by the caller
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
