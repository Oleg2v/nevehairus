<?php
/**
 * Widget "טופס הגשת מועמדות" (jobs-form): the job application form of the jobs page. Spec §4.20 and
 * D6; prototype design-system L291–338 (the form, with its fields, notes and alerts) in the chrome
 * of a v3 spread (ruling line, v3-h2, lead) instead of the design-system page's eyebrow and note.
 *
 * The fields, alerts, notes and hidden fields are rendered by NH_Forms (§6.3); this widget passes
 * texts only. The role list follows the role titles of the ל spread, so its links (?role=…#form,
 * NH_Settings::jobs_url()) preselect the role. The recipient and the privacy notice live in
 * Settings → נווה האירוס (§7).
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'NH_Widget_Base' ) ) {
	return;
}

final class NH_Widget_Jobs_Form extends NH_Widget_Base {
	public const ID          = 'jobs-form';
	public const STYLE_DEPS  = [ 'nh-forms' ];
	public const SCRIPT_DEPS = [ 'nh-forms' ];
	public const DYNAMIC     = true;
	public const KEYWORDS    = [ 'jobs', 'careers', 'cv', 'form', 'דרושים', 'משרות', 'מועמדות', 'קורות חיים', 'טופס' ];

	/** The NH_Forms form type (§6.1). */
	private const FORM = 'jobs';

	/** The fields, in the order of the prototype form. */
	private const FIELDS = [ 'role', 'name', 'phone', 'email', 'cv', 'message' ];

	public function get_title() {
		return __( 'טופס הגשת מועמדות', 'nevehairus-site' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	protected function register_controls() {
		// Content: the heading and the lead beside the form.
		$this->start_controls_section( 'nh_content', [ 'label' => __( 'תוכן', 'nevehairus-site' ) ] );
		NH_Controls::heading(
			$this,
			[
				'default' => 'הגשת מועמדות',
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
				'default' => 'מחפשים אנשים שרוצים להשפיע ולהיות חלק מבית: צוות קבוע, ואפשרויות ללמוד ולהתפתח מקצועית.',
				'plain'   => "מחפשים עובדים ועובדות.\nיש עבודה במעון ובבתים בעיר.",
			]
		);
		$this->end_controls_section();

		// The application form.
		$this->start_controls_section( 'nh_form', [ 'label' => __( 'טופס', 'nevehairus-site' ) ] );
		$this->jobs_note( 'form_note', __( 'לאן נשלחות המועמדויות (רצוי תיבה נפרדת), הודעת הפרטיות שמתחת לתיבות הסימון והודעות השגיאה — בהגדרות ← נווה האירוס. בלי כתובת נמען הטופס לא שולח, ועורכי האתר רואים על כך הודעה מעליו. קורות החיים נשלחים כקובץ מצורף ואינם נשמרים באתר.', 'nevehairus-site' ) );
		NH_Controls::pair(
			$this,
			'success_title',
			[
				'label'   => __( 'הודעת תודה — כותרת', 'nevehairus-site' ),
				'default' => 'תודה! המועמדות התקבלה.',
			]
		);
		NH_Controls::pair(
			$this,
			'success_text',
			[
				'label'   => __( 'הודעת תודה — טקסט', 'nevehairus-site' ),
				'default' => 'נעבור על הפרטים ונחזור אליך.',
			]
		);
		NH_Controls::pair(
			$this,
			'required_note',
			[
				'label'   => __( 'הסבר על שדות החובה', 'nevehairus-site' ),
				'default' => 'שדות המסומנים ב־<span aria-hidden="true">*</span><span class="nh-sr-only">כוכבית</span> הם שדות חובה.',
			]
		);
		NH_Controls::pair(
			$this,
			'label_role',
			[
				'label'   => __( 'שדה המשרה — תווית', 'nevehairus-site' ),
				'default' => 'המשרה',
			]
		);
		NH_Controls::lines(
			$this,
			'role_options',
			[
				'label'       => __( 'שדה המשרה — המשרות', 'nevehairus-site' ),
				'description' => __( 'משרה בכל שורה; השורה הראשונה מוצגת כל עוד לא נבחר דבר. כדאי שהשמות יהיו זהים לשמות המשרות בווידג׳ט ל׳: קישור של משרה משם בוחר אותה כאן מראש.', 'nevehairus-site' ),
				'default'     => "בחירת משרה…\nרכז/ת דירה — דיור לצעירים על הרצף האוטיסטי\nעובד/ת סוציאלי/ת\nמרפא/ה בעיסוק\nטבח/ית\nעובד/ת אחזקה\nפנייה כללית",
				'rows'        => 7,
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
		NH_Controls::pair(
			$this,
			'label_email',
			[
				'label'   => __( 'שדה הדוא״ל — תווית', 'nevehairus-site' ),
				'default' => 'דוא״ל',
			]
		);
		NH_Controls::pair(
			$this,
			'label_cv',
			[
				'label'   => __( 'שדה קורות החיים — תווית', 'nevehairus-site' ),
				'default' => 'קורות חיים',
			]
		);
		NH_Controls::pair(
			$this,
			'hint_cv',
			[
				'label'   => __( 'שדה קורות החיים — הסבר מתחת לשדה', 'nevehairus-site' ),
				'default' => 'PDF, DOC או DOCX, עד 5MB.',
			]
		);
		NH_Controls::pair(
			$this,
			'label_message',
			[
				'label'   => __( 'שדה ההודעה — תווית', 'nevehairus-site' ),
				'default' => 'כמה מילים עליך',
			]
		);
		NH_Controls::pair(
			$this,
			'consent',
			[
				'label'   => __( 'תיבת ההסכמה — טקסט (הקישור #privacy מוביל למדיניות הפרטיות)', 'nevehairus-site' ),
				'default' => 'קראתי את <a href="#privacy">מדיניות הפרטיות</a> ואני מסכים/ה לשימוש בפרטים לצורך בדיקת המועמדות למשרה זו.',
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
			'keep',
			[
				'label'   => __( 'תיבת השמירה למשרות בעתיד — טקסט (ריק = בלי התיבה)', 'nevehairus-site' ),
				'default' => 'אפשר לשמור את הפרטים שלי למשרות נוספות בעתיד (לא חובה).',
			]
		);
		NH_Controls::pair(
			$this,
			'submit',
			[
				'label'   => __( 'כפתור השליחה', 'nevehairus-site' ),
				'default' => 'שליחת המועמדות',
			]
		);
		$this->end_controls_section();

		NH_Controls::section(
			$this,
			[
				'anchor'  => 'form',
				'running' => 'דרושים',
			]
		);
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$state   = NH_Forms::state( (string) $this->get_id() );
		$prefix  = $this->jobs_prefix();
		$options = self::jobs_text( $s, 'role_options' );

		$fields = [];
		$labels = [];
		foreach ( self::FIELDS as $key ) {
			$label = self::jobs_pair( $s, 'label_' . $key );
			$o     = [
				'id'    => $this->nh_uid( $key ),
				'label' => $label,
			];
			if ( 'role' === $key ) {
				$o['options']  = $options;
				$o['selected'] = self::jobs_requested_role( $options );
			} elseif ( 'cv' === $key && self::jobs_has_text( $s, 'hint_cv' ) ) {
				$o['hint'] = self::jobs_pair( $s, 'hint_cv' );
			}
			$labels[ $key ] = self::jobs_flat( $label );
			$fields[ $key ] = NH_Forms::field( self::FORM, $key, $o, $state );
		}
		$consent           = self::jobs_pair( $s, 'consent' );
		$error_label       = trim( self::jobs_text( $s, 'consent_error_label' ) );
		$labels['consent'] = '' !== $error_label ? $error_label : self::jobs_flat( $consent );

		// The optional "keep my details" box goes when its text is empty.
		$keep = '';
		if ( self::jobs_has_text( $s, 'keep' ) ) {
			$keep = NH_Forms::check(
				self::FORM,
				'keep',
				[
					'id'    => $this->nh_uid( 'keep' ),
					'label' => self::jobs_pair( $s, 'keep' ),
				],
				$state
			);
		}

		$submit = NH_Text::pair( self::jobs_text( $s, 'submit' ), self::jobs_text( $s, 'submit_plain' ) );
		if ( '' === $submit ) {
			$submit = esc_html__( 'שליחה', 'nevehairus-site' ); // a submit button always has a name
		}

		$form = NH_Forms::summary( $state, $labels, $prefix )
			. NH_Forms::required_note( self::jobs_text( $s, 'required_note' ), self::jobs_text( $s, 'required_note_plain' ) )
			. $fields['role']
			. '<div class="nh-form__row nh-form__row--2">' . $fields['name'] . $fields['phone'] . '</div>'
			. $fields['email']
			. $fields['cv']
			. $fields['message']
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
			. $keep
			. NH_Forms::privacy_note( self::FORM )
			. NH_Forms::hidden_fields( self::FORM, (string) $this->get_id(), $this->jobs_return_url( $s ) )
			. '<div><button class="nh-btn nh-btn--primary" type="submit">' . $submit
			. NH_Html::icon( 'arrow-fwd', 'nh-btn__icon nh-btn__icon--fwd' ) . '</button></div>';

		// The form is named by the spread's heading (prototype aria-labelledby="form-title").
		$titled  = self::jobs_has_text( $s, 'title' );
		$section = [ 'class' => 'v3-jobsform v3-spread' ];
		if ( ! $titled ) {
			$section['labelledby'] = null; // no heading to point to
		}

		$html = $this->nh_section_open( $s, $section )
			. '<div class="nh-container">'
			. $this->nh_rule()
			. '<div class="nh-jobs__grid">'
			. '<div class="v3-jobsform__intro">' . ( $titled ? $this->nh_h2( $s ) : '' ) . $this->nh_lead( $s ) . '</div>'
			. '<div>'
			. NH_Forms::recipient_note( self::FORM )
			. NH_Forms::success( self::jobs_pair( $s, 'success_title' ), self::jobs_pair( $s, 'success_text' ), $state )
			. '<form class="nh-form" data-validate action="' . esc_url( NH_Forms::action_url() ) . '" method="post" enctype="multipart/form-data"'
			. ( $titled ? ' aria-labelledby="' . esc_attr( $this->nh_uid( 'title' ) ) . '"' : '' )
			. ' data-nh-form="jobs">' . $form . '</form>'
			. '</div>'
			. '</div>'
			. '</div>'
			. $this->nh_section_close();

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts
	}

	/**
	 * The role asked for in the link (?role=…, made by NH_Settings::jobs_url() for the rows of the ל spread)
	 * when it is one of the options (not the first, empty-value line), else ''. The request value is only
	 * compared with the editor's own options; it is never printed or stored.
	 */
	private static function jobs_requested_role( string $options ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only preselection, compared with the options only
		$want = isset( $_GET['role'] ) && is_string( $_GET['role'] ) ? trim( stripslashes( $_GET['role'] ) ) : '';
		if ( '' === $want ) {
			return '';
		}
		foreach ( array_slice( NH_Text::lines( $options ), 1 ) as $option ) {
			if ( $option === $want ) {
				return $option;
			}
		}
		return '';
	}

	/** Where the handler sends a visitor without JavaScript back to (PRG, D7): this page, at the spread. */
	private function jobs_return_url( array $s ): string {
		$url    = get_permalink();
		$url    = is_string( $url ) && '' !== $url ? $url : home_url( '/' );
		$anchor = sanitize_html_class( self::jobs_text( $s, 'anchor' ) );
		return '' !== $anchor ? $url . '#' . $anchor : $url;
	}

	/** The id prefix of this instance: the anchor, or nh-{element id} (§2.5) — nh_uid( $k ) is "{prefix}-{k}". */
	private function jobs_prefix(): string {
		return substr( $this->nh_uid( 'x' ), 0, -2 );
	}

	/** A note for the editor in the panel (not printed on the page). */
	private function jobs_note( string $id, string $text ): void {
		$this->add_control(
			$id,
			[
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html( $text ),
				'content_classes' => 'elementor-descriptor',
			]
		);
	}

	/** A setting as a string ('' when it is missing or not a scalar). */
	private static function jobs_text( array $s, string $key ): string {
		return isset( $s[ $key ] ) && is_scalar( $s[ $key ] ) ? (string) $s[ $key ] : '';
	}

	/** The [ full, plain ] pair of a text control and its twin. */
	private static function jobs_pair( array $s, string $id ): array {
		return [ self::jobs_text( $s, $id ), self::jobs_text( $s, $id . '_plain' ) ];
	}

	/** True when the pair renders anything (states 1–4 of §3.5). */
	private static function jobs_has_text( array $s, string $id ): bool {
		$full  = trim( self::jobs_text( $s, $id ) );
		$plain = trim( self::jobs_text( $s, $id . '_plain' ) );
		return '' !== $full || ( '' !== $plain && NH_Text::HIDE !== $plain );
	}

	/**
	 * One line of plain text from a pair, for the error summary: the full text, else the plain twin;
	 * tags dropped. The caller escapes it.
	 */
	private static function jobs_flat( array $pair ): string {
		$full  = trim( (string) ( $pair[0] ?? '' ) );
		$plain = trim( (string) ( $pair[1] ?? '' ) );
		$text  = '' !== $full ? $full : ( NH_Text::HIDE !== $plain ? $plain : '' );
		$text  = strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- plain text, escaped by the caller
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
