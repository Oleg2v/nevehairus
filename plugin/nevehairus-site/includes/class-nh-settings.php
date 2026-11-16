<?php
defined( 'ABSPATH' ) || exit;

/**
 * Option `nh_settings`: contact data, form recipients and messages, privacy notices, links, cookie-banner
 * and visit-bar texts (SPEC §7, D9). One source for data that appears in several places.
 *
 * The form recipients and the public e-mail are empty by default: until they are set, the forms do not
 * send and say so.
 *
 * @package nevehairus-site
 */
final class NH_Settings {

	/** Option name (array, autoloaded). */
	public const OPTION = 'nh_settings';

	/** @var array|null */
	private static $defaults = null;

	/**
	 * Registers the setting (group `nh_settings`) with sanitize().
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	/**
	 * `register_setting()` on init (also used by options.php when the page is saved).
	 */
	public static function register(): void {
		register_setting(
			self::OPTION,
			self::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * Field types: text, textarea, html (inline markup, kses), email, url, tel, page, bool, int.
	 *
	 * @return array<string,string>
	 */
	public static function types(): array {
		return [
			'phone_display'         => 'text',
			'phone_tel'             => 'tel',
			'fax'                   => 'text',
			'email_public'          => 'email',
			'address'               => 'text',
			'facebook_url'          => 'url',
			'visit_recipient'       => 'email',
			'jobs_recipient'        => 'email',
			'mail_from_name'        => 'text',
			'privacy_visit'         => 'html',
			'privacy_visit_plain'   => 'html',
			'privacy_jobs'          => 'html',
			'privacy_jobs_plain'    => 'html',
			'msg_required'          => 'text',
			'msg_email'             => 'text',
			'msg_tel'               => 'text',
			'msg_consent'           => 'text',
			'msg_file_size'         => 'text',
			'msg_file_type'         => 'text',
			'msg_summary'           => 'text',
			'msg_summary_one'       => 'text',
			'msg_summary_many'      => 'text',
			'msg_expired'           => 'text',
			'msg_limited'           => 'text',
			'msg_off'               => 'text',
			'msg_error'             => 'text',
			'visit_url'             => 'url',
			'jobs_page'             => 'page',
			'accessibility_page'    => 'page',
			'sitemap_page'          => 'page',
			'entrances_pdf'         => 'url',
			'cookie_title'          => 'text',
			'cookie_more'           => 'text',
			'cookie_text'           => 'html',
			'cookie_text_plain'     => 'html',
			'cookie_necessary'      => 'html',
			'cookie_stats'          => 'html',
			'cookie_media'          => 'html',
			'cookie_save'           => 'text',
			'cookie_all'            => 'text',
			'cookie_necessary_only' => 'text',
			'cookie_settings'       => 'text',
			'consent_version'       => 'int',
			'skip_label'            => 'text',
			'show_bar'              => 'bool',
			'bar_label'             => 'text',
			'bar_cta'               => 'text',
			'bar_cta_plain'         => 'textarea',
			'bar_call_label'        => 'text',
			'webp_subsizes'         => 'bool',
		];
	}

	/**
	 * Every key of SPEC §7 with its default (prototype texts verbatim; content, not UI strings).
	 */
	public static function defaults(): array {
		if ( null !== self::$defaults ) {
			return self::$defaults;
		}
		self::$defaults = [
			// פרטי קשר.
			'phone_display'         => '08-9403047',
			'phone_tel'             => '+97289403047',
			'fax'                   => '08-9400805',
			'email_public'          => '',
			'address'               => 'ת״ד 28, נס ציונה',
			'facebook_url'          => 'https://www.facebook.com/neveirus/',
			// טפסים.
			'visit_recipient'       => '',
			'jobs_recipient'        => '',
			'mail_from_name'        => 'מעון נווה האירוס',
			'privacy_visit'         => 'מסירת הפרטים אינה חובה לפי חוק, אך בלעדיהם לא נוכל לחזור אליכם. הפרטים ישמשו רק לתיאום הביקור ולמענה לבקשה, יישמרו במאגר המידע של נוה האירוס בע״מ ולא יועברו לגורם אחר. אפשר לבקש לעיין במידע ולתקן אותו{contact}.',
			'privacy_visit_plain'   => "לא חייבים לתת לנו את הפרטים.\nבלי הפרטים לא נוכל לחזור אליכם.\nהפרטים נשמרים אצלנו רק כדי לקבוע ביקור.\nלא נותנים אותם לאף אחד אחר.\nאפשר לבקש לראות אותם או לתקן אותם.",
			'privacy_jobs'          => 'מסירת הפרטים אינה חובה לפי חוק, אך בלעדיהם לא נוכל לבדוק את המועמדות. הפרטים ישמשו לבדיקת המועמדות בלבד, יישמרו במאגר המידע של נוה האירוס בע״מ, יימחקו בתום הליך הגיוס אם לא סימנת אחרת, ולא יועברו לגורם אחר. ניתן לבקש לעיין במידע ולתקנו{contact}.',
			'privacy_jobs_plain'    => '',
			'msg_required'          => 'צריך למלא את השדה הזה.',
			'msg_email'             => 'כתובת הדוא״ל לא תקינה. למשל: name@example.com',
			'msg_tel'               => 'מספר הטלפון לא תקין: צריך 9 או 10 ספרות, ובהתחלה 0.',
			'msg_consent'           => 'צריך לסמן את התיבה כדי לשלוח.',
			'msg_file_size'         => 'הקובץ גדול מדי. הגודל המרבי הוא 5MB.',
			'msg_file_type'         => 'ניתן לצרף קובץ PDF, DOC או DOCX בלבד.',
			'msg_summary'           => 'נמצאו שגיאות בטופס:',
			'msg_summary_one'       => 'נמצאה שגיאה אחת בטופס:',
			'msg_summary_many'      => 'נמצאו {n} שגיאות בטופס:',
			'msg_expired'           => 'הטופס פג תוקף. אפשר לרענן את הדף ולשלוח שוב.',
			'msg_limited'           => 'נשלחו כבר כמה בקשות מהמכשיר הזה. אפשר לנסות שוב בעוד כמה דקות, או להתקשר: {phone}',
			'msg_off'               => 'אי אפשר לשלוח את הטופס כרגע. אפשר להתקשר: {phone}',
			'msg_error'             => 'משהו השתבש בשליחה. אפשר לנסות שוב, או להתקשר: {phone}',
			// קישורים.
			'visit_url'             => '',
			'jobs_page'             => 0,
			'accessibility_page'    => 0,
			'sitemap_page'          => 0,
			'entrances_pdf'         => '',
			// באנר עוגיות.
			'cookie_title'          => 'עוגיות באתר',
			'cookie_more'           => 'מה זה אומר?',
			'cookie_text'           => 'אנחנו משתמשים בעוגיות הכרחיות כדי שהאתר יעבוד. עוגיות סטטיסטיקה ותוכן מוטמע (כמו סרטוני YouTube) יופעלו רק בהסכמתכם. אפשר לשנות את הבחירה בכל עת. <a href="#privacy">מדיניות הפרטיות</a>',
			'cookie_text_plain'     => "האתר שומר קבצים קטנים במחשב או בטלפון שלכם.\nחלק מהקבצים חייבים, כדי שהאתר יעבוד.\nאת שאר הקבצים נשמור רק אם תסכימו.\nאפשר לשנות את הבחירה מתי שרוצים. <a href=\"#privacy\">מה עושים עם המידע?</a>",
			'cookie_necessary'      => '<b>הכרחיות</b> — פעילות תקינה של האתר ושמירת הבחירה שלכם. תמיד פעילות.',
			'cookie_stats'          => '<b>סטטיסטיקה</b> — מדידת שימוש אנונימית, כדי לשפר את האתר.',
			'cookie_media'          => '<b>תוכן מוטמע</b> — סרטונים מ־YouTube.',
			'cookie_save'           => 'שמירת הבחירה',
			'cookie_all'            => 'אישור הכול',
			'cookie_necessary_only' => 'רק הכרחיות',
			'cookie_settings'       => 'הגדרות',
			'consent_version'       => 1,
			// כללי.
			'skip_label'            => 'דילוג לתוכן הראשי',
			'show_bar'              => 1,
			'bar_label'             => 'תיאום ביקור מהיר',
			'bar_cta'               => 'תיאום ביקור',
			'bar_cta_plain'         => 'לקבוע ביקור',
			'bar_call_label'        => 'התקשרו:',
			'webp_subsizes'         => 1,
		];
		return self::$defaults;
	}

	/**
	 * A setting: the stored value, else the default (null for an unknown key).
	 *
	 * @return mixed
	 */
	public static function get( string $key ) {
		$stored = get_option( self::OPTION, [] );
		if ( is_array( $stored ) && array_key_exists( $key, $stored ) ) {
			return $stored[ $key ];
		}
		return self::defaults()[ $key ] ?? null;
	}

	/**
	 * Sanitises the whole option (SPEC §7 rules). Unknown keys are dropped; missing checkboxes are off.
	 *
	 * @param mixed $input Raw input.
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : [];
		$out   = [];
		foreach ( self::types() as $key => $type ) {
			$raw = $input[ $key ] ?? null;
			if ( 'bool' === $type ) {
				$out[ $key ] = empty( $raw ) ? 0 : 1;
				continue;
			}
			if ( null === $raw ) {
				$out[ $key ] = self::defaults()[ $key ];
				continue;
			}
			$raw = is_scalar( $raw ) ? wp_unslash( (string) $raw ) : '';
			switch ( $type ) {
				case 'email':
					$email = sanitize_email( $raw );
					if ( '' !== trim( $raw ) && ! is_email( $email ) ) {
						add_settings_error( self::OPTION, 'nh_' . $key, sprintf( /* translators: %s: setting key */ __( 'כתובת הדוא״ל לא תקינה ולא נשמרה (%s).', 'nevehairus-site' ), $key ) );
						$email = '';
					}
					$out[ $key ] = is_email( $email ) ? $email : '';
					break;
				case 'url':
					$out[ $key ] = esc_url_raw( trim( $raw ) );
					break;
				case 'tel':
					$out[ $key ] = (string) preg_replace( '/[^+0-9]/', '', $raw );
					break;
				case 'page':
					$out[ $key ] = absint( $raw );
					break;
				case 'int':
					$out[ $key ] = max( 1, min( 999, absint( $raw ) ) );
					break;
				case 'html':
					$out[ $key ] = trim( (string) wp_kses( str_replace( [ "\r\n", "\r" ], "\n", $raw ), NH_Text::kses_inline() ) );
					break;
				case 'textarea':
					$out[ $key ] = sanitize_textarea_field( $raw );
					break;
				default:
					$out[ $key ] = sanitize_text_field( $raw );
			}
		}
		return $out;
	}

	/**
	 * The phone for links: [ 'display' => '08-9403047', 'tel' => '+97289403047' ].
	 */
	public static function phone(): array {
		$display = trim( (string) self::get( 'phone_display' ) );
		$tel     = (string) preg_replace( '/[^+0-9]/', '', (string) self::get( 'phone_tel' ) );
		if ( '' === $tel ) {
			$tel = (string) preg_replace( '/[^+0-9]/', '', $display );
		}
		return [
			'display' => $display,
			'tel'     => $tel,
		];
	}

	/**
	 * The public e-mail address ('' if not set).
	 */
	public static function email_public(): string {
		$email = trim( (string) self::get( 'email_public' ) );
		return '' !== $email && is_email( $email ) ? $email : '';
	}

	/**
	 * The recipient of a form ('visit'|'jobs'): a valid address or ''.
	 */
	public static function recipient( string $form ): string {
		if ( ! in_array( $form, [ 'visit', 'jobs' ], true ) ) {
			return '';
		}
		$email = trim( (string) self::get( $form . '_recipient' ) );
		return '' !== $email && is_email( $email ) ? $email : '';
	}

	/**
	 * The visit link: the setting, or home_url( '/#door' ).
	 */
	public static function visit_url(): string {
		$url = trim( (string) self::get( 'visit_url' ) );
		return '' !== $url ? $url : home_url( '/#door' );
	}

	/**
	 * The jobs page (setting `jobs_page`): its permalink, with `?role=…#form` when a role is given;
	 * '' when the page is not set.
	 */
	public static function jobs_url( string $role = '' ): string {
		$url = self::page_permalink( absint( self::get( 'jobs_page' ) ) );
		if ( '' === $url ) {
			return '';
		}
		$role = trim( wp_strip_all_tags( $role ) );
		return '' !== $role ? add_query_arg( 'role', rawurlencode( $role ), $url ) . '#form' : $url;
	}

	/**
	 * The page behind a link token: 'accessibility' | 'sitemap' | 'entrances' | 'privacy' → URL or ''.
	 */
	public static function page_url( string $key ): string {
		switch ( $key ) {
			case 'accessibility':
			case 'sitemap':
				return self::page_permalink( absint( self::get( $key . '_page' ) ) );
			case 'entrances':
				return trim( (string) self::get( 'entrances_pdf' ) );
			case 'privacy':
				return (string) get_privacy_policy_url();
			case 'jobs':
			case 'jobs-page':
				return self::jobs_url();
		}
		return '';
	}

	/**
	 * A settings text with tokens ({phone}, {email}, {contact}, {year}) applied, inline markup kept
	 * (kses) and link tokens resolved. Newlines are kept (callers split lines).
	 */
	public static function text( string $key ): string {
		$raw = self::get( $key );
		if ( ! is_scalar( $raw ) || '' === trim( (string) $raw ) ) {
			return '';
		}
		$html = wp_kses( str_replace( [ "\r\n", "\r" ], "\n", trim( (string) $raw ) ), NH_Text::kses_inline() );
		return NH_Text::link_tokens( NH_Text::tokens( $html ) );
	}

	/**
	 * Permalink of a published page, or ''.
	 */
	private static function page_permalink( int $id ): string {
		if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		$url = get_permalink( $id );
		return is_string( $url ) ? $url : '';
	}
}
