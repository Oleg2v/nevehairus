<?php
defined( 'ABSPATH' ) || exit;

/**
 * Forms: the schema of the visit and jobs forms, the markup helpers the form widgets use, the
 * admin-post handler and the token endpoint (SPEC §6, D7).
 *
 * Security, in this order: POST only; honeypot; nonce; a signed time token (too fast = silently
 * dropped, older than two days = expired); a rate limit by salted IP hash (the only thing stored:
 * a counter in a transient); validation identical to the client; recipient check; wp_mail().
 * Nothing a visitor types is stored, logged or put in a URL. A CV is attached straight from PHP's
 * upload temp file and deleted right after sending; it never enters the media library.
 *
 * @package nevehairus-site
 */
final class NH_Forms {

	/** Form types. */
	public const TYPES = [ 'visit', 'jobs' ];

	/** Response statuses. */
	public const STATUSES = [ 'sent', 'invalid', 'expired', 'limited', 'off', 'error' ];

	/** Field error codes. */
	public const CODES = [ 'required', 'email', 'tel', 'consent', 'file_type', 'file_size' ];

	/** Largest CV in bytes (5 MB). */
	public const MAX_FILE = 5242880;

	/** Accepted CV files (input accept attribute). */
	private const ACCEPT = '.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

	/** Accepted CV types for wp_check_filetype_and_ext(). */
	private const CV_MIMES = [
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	];

	/** The error icon (the same markup as setError() in nh-forms.js). */
	private const ICON_ERR = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v6M12 16.5v.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';

	/** A token older than this is expired (seconds). */
	private const MAX_AGE = 172800;

	/** A form sent faster than this after its token is a bot (seconds). */
	private const MIN_AGE = 3;

	/**
	 * Hooks the admin-post handlers and the token endpoint.
	 */
	public static function init(): void {
		foreach ( self::TYPES as $type ) {
			add_action( 'admin_post_nh_' . $type, [ __CLASS__, 'handle' ] );
			add_action( 'admin_post_nopriv_nh_' . $type, [ __CLASS__, 'handle' ] );
		}
		add_action( 'wp_ajax_nh_form_token', [ __CLASS__, 'token' ] );
		add_action( 'wp_ajax_nopriv_nh_form_token', [ __CLASS__, 'token' ] );
	}

	/**
	 * Fields of a form type, in form order. Each: input, required, rule, max, attrs, label (the fixed
	 * label of the e-mail sent to the office).
	 */
	public static function schema( string $type ): array {
		$name    = [
			'input'    => 'text',
			'required' => true,
			'max'      => 120,
			'attrs'    => [ 'autocomplete' => 'name' ],
			'label'    => 'שם מלא',
		];
		$phone   = [
			'input'    => 'tel',
			'required' => true,
			'rule'     => 'tel',
			'max'      => 40,
			'attrs'    => [
				'autocomplete' => 'tel',
				'inputmode'    => 'tel',
				'dir'          => 'ltr',
			],
			'label'    => 'טלפון',
		];
		$email   = [
			'input'    => 'email',
			'required' => false,
			'rule'     => 'email',
			'max'      => 254,
			'attrs'    => [
				'autocomplete' => 'email',
				'dir'          => 'ltr',
			],
			'label'    => 'דוא״ל',
		];
		$message = [
			'input' => 'textarea',
			'max'   => 5000,
			'label' => 'הודעה',
		];
		$consent = [
			'input'    => 'checkbox',
			'required' => true,
			'label'    => 'אישור מדיניות הפרטיות',
		];

		if ( 'visit' === $type ) {
			return [
				'name'    => $name,
				'phone'   => $phone,
				'email'   => $email,
				'who'     => [
					'input' => 'select',
					'max'   => 200,
					'label' => 'מי ממלא את הטופס',
				],
				'program' => [
					'input' => 'select',
					'max'   => 200,
					'label' => 'בית שמעניין',
				],
				'message' => $message,
				'consent' => $consent,
			];
		}
		if ( 'jobs' === $type ) {
			return [
				'role'    => [
					'input'    => 'select',
					'required' => true,
					'max'      => 200,
					'label'    => 'משרה',
				],
				'name'    => $name,
				'phone'   => $phone,
				'email'   => [ 'required' => true ] + $email,
				'cv'      => [
					'input' => 'file',
					'attrs' => [ 'accept' => self::ACCEPT ],
					'label' => 'קורות חיים',
				],
				'message' => $message,
				'consent' => $consent,
				'keep'    => [
					'input' => 'checkbox',
					'label' => 'שמירת הפרטים למשרות נוספות',
				],
			];
		}
		return [];
	}

	/**
	 * Where the forms post: admin-post.php.
	 */
	public static function action_url(): string {
		return admin_url( 'admin-post.php' );
	}

	/**
	 * The hidden inputs: action, nonce, signed time token, instance, return URL, nh_js (set to 1 by JS).
	 *
	 * @param string $type       'visit'|'jobs'.
	 * @param string $instance   The widget's element id.
	 * @param string $return_url The page URL + #anchor.
	 */
	public static function hidden_fields( string $type, string $instance, string $return_url ): string {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return '';
		}
		$t      = time();
		$fields = [
			'action'      => 'nh_' . $type,
			'_nh_nonce'   => wp_create_nonce( 'nh_form_' . $type ),
			'nh_t'        => (string) $t,
			'nh_s'        => self::sign( $type, $t ),
			'nh_instance' => self::instance( $instance ),
			'nh_return'   => esc_url_raw( $return_url ),
			'nh_js'       => '0',
		];
		$out = '';
		foreach ( $fields as $name => $value ) {
			$out .= '<input type="hidden" name="' . $name . '" value="' . esc_attr( $value ) . '">';
		}
		return $out;
	}

	/**
	 * The honeypot field, hidden from everyone (CSS off-screen, aria-hidden, tabindex -1).
	 */
	public static function honeypot( string $prefix, string $label = 'אתר' ): string {
		$id = sanitize_html_class( $prefix ) . '-website';
		return '<div class="nh-hp" aria-hidden="true"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<input id="' . esc_attr( $id ) . '" name="website" type="text" tabindex="-1" autocomplete="off"></div>';
	}

	/**
	 * The server state of a widget instance after a submission without JS (from $_GET, whitelisted):
	 * `[ 'status' => ''|'sent'|…, 'errors' => [ field => code ] ]`.
	 */
	public static function state( string $instance ): array {
		$none = [
			'status' => '',
			'errors' => [],
		];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display state of a PRG redirect
		$form = isset( $_GET['nh_form'] ) && is_string( $_GET['nh_form'] ) ? self::instance( wp_unslash( $_GET['nh_form'] ) ) : '';
		if ( '' === $instance || '' === $form || self::instance( $instance ) !== $form ) {
			return $none;
		}
		$status = isset( $_GET['nh_status'] ) && is_string( $_GET['nh_status'] ) ? sanitize_key( wp_unslash( $_GET['nh_status'] ) ) : '';
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return $none;
		}
		$errors = [];
		if ( 'invalid' === $status && isset( $_GET['nh_err'] ) && is_string( $_GET['nh_err'] ) ) {
			foreach ( explode( ',', wp_unslash( $_GET['nh_err'] ) ) as $pair ) {
				$parts = explode( ':', $pair, 2 );
				$key   = $parts[0];
				$code  = $parts[1] ?? '';
				if ( preg_match( '/^[a-z_]{1,20}$/', $key ) && in_array( $code, self::CODES, true ) ) {
					$errors[ $key ] = $code;
				}
			}
		}
		// phpcs:enable
		return [
			'status' => $status,
			'errors' => $errors,
		];
	}

	/**
	 * One field (text, tel, email, select, textarea, file) in `.nh-field`. Options: id, label [full, plain],
	 * options (select: lines; the first line is the empty-value option), hint [full, plain], selected.
	 *
	 * @param string $type  Form type.
	 * @param string $key   Field key (schema).
	 * @param array  $o     Options.
	 * @param array  $state State (state()).
	 */
	public static function field( string $type, string $key, array $o, array $state ): string {
		$f = self::schema( $type )[ $key ] ?? null;
		if ( null === $f || 'checkbox' === $f['input'] ) {
			return '';
		}
		$id    = sanitize_html_class( (string) ( $o['id'] ?? $key ) );
		$req   = ! empty( $f['required'] );
		$error = self::error_of( $key, $state );
		$hint  = self::pair_html( $o['hint'] ?? '' );

		$described = [];
		if ( '' !== $hint ) {
			$described[] = $id . '-hint';
		}
		if ( '' !== $error ) {
			$described[] = $id . '-err';
		}
		$attrs = [
			'class' => [
				'select'   => 'nh-select',
				'textarea' => 'nh-textarea',
			][ $f['input'] ] ?? 'nh-input',
			'id'    => $id,
			'name'  => $key,
		];
		if ( ! in_array( $f['input'], [ 'select', 'textarea' ], true ) ) {
			$attrs['type'] = $f['input'];
		}
		$attrs += (array) ( $f['attrs'] ?? [] );
		$attrs['required']         = $req;
		$attrs['aria-invalid']     = '' !== $error ? 'true' : null;
		$attrs['aria-describedby'] = $described ? implode( ' ', $described ) : null;

		if ( 'select' === $f['input'] ) {
			$control = '<select' . NH_Html::attrs( $attrs ) . '>' . self::options( $o['options'] ?? '', (string) ( $o['selected'] ?? '' ) ) . '</select>';
		} elseif ( 'textarea' === $f['input'] ) {
			$control = '<textarea' . NH_Html::attrs( $attrs ) . '></textarea>';
		} else {
			$control = '<input' . NH_Html::attrs( $attrs ) . '>';
		}

		return '<div class="nh-field">'
			. '<label class="nh-label" for="' . esc_attr( $id ) . '">' . self::pair_html( $o['label'] ?? '' ) . ( $req ? ' <span class="nh-req" aria-hidden="true">*</span>' : '' ) . '</label>'
			. $control
			. ( '' !== $hint ? '<p class="nh-hint" id="' . esc_attr( $id . '-hint' ) . '">' . $hint . '</p>' : '' )
			. self::error_html( $id, $error )
			. '</div>';
	}

	/**
	 * A checkbox (consent, keep) in `.nh-check`. Options: id, label [full, plain] (inline HTML; #privacy
	 * resolved), error_label (data-error-label).
	 *
	 * @param string $type  Form type.
	 * @param string $key   Field key (schema).
	 * @param array  $o     Options.
	 * @param array  $state State (state()).
	 */
	public static function check( string $type, string $key, array $o, array $state ): string {
		$f = self::schema( $type )[ $key ] ?? null;
		if ( null === $f || 'checkbox' !== $f['input'] ) {
			return '';
		}
		$id    = sanitize_html_class( (string) ( $o['id'] ?? $key ) );
		$req   = ! empty( $f['required'] );
		$error = self::error_of( $key, $state );
		$label = trim( (string) ( $o['error_label'] ?? '' ) );
		$attrs = [
			'id'               => $id,
			'name'             => $key,
			'type'             => 'checkbox',
			'required'         => $req,
			'data-error-label' => '' !== $label ? $label : null,
			'aria-invalid'     => '' !== $error ? 'true' : null,
			'aria-describedby' => '' !== $error ? $id . '-err' : null,
		];
		return '<div class="nh-check"><input' . NH_Html::attrs( $attrs ) . '>'
			. '<label for="' . esc_attr( $id ) . '">' . self::pair_html( $o['label'] ?? '' ) . ( $req ? ' <span class="nh-req" aria-hidden="true">*</span>' : '' ) . '</label>'
			. self::error_html( $id, $error )
			. '</div>';
	}

	/**
	 * The "fields marked * are required" note (inline HTML allowed).
	 */
	public static function required_note( string $full, string $plain ): string {
		return NH_Text::block(
			$full,
			$plain,
			[
				'full_tag'    => 'p',
				'full_class'  => 'nh-form__note',
				'plain_tag'   => 'p',
				'plain_class' => 'nh-form__note',
				'plain_lines' => null,
			]
		);
	}

	/**
	 * The error summary. Hidden until JS fills it; with a server state (invalid, expired, limited, off,
	 * error) it is rendered visible, with linked field errors or the status message.
	 *
	 * @param array  $state  State (state()).
	 * @param array  $labels Field key => label text (plain text).
	 * @param string $prefix Field id prefix (`{prefix}-{key}`).
	 */
	public static function summary( array $state, array $labels, string $prefix ): string {
		$status = (string) ( $state['status'] ?? '' );
		$errors = is_array( $state['errors'] ?? null ) ? $state['errors'] : [];
		$open   = '<div class="nh-alert nh-alert--error" data-error-summary tabindex="-1"';
		if ( ! in_array( $status, [ 'invalid', 'expired', 'limited', 'off', 'error' ], true ) ) {
			return $open . ' hidden><h3>' . esc_html( (string) NH_Settings::get( 'msg_summary' ) ) . '</h3><ul></ul></div>';
		}
		if ( 'invalid' === $status && $errors ) {
			$n     = count( $errors );
			$title = 1 === $n
				? (string) NH_Settings::get( 'msg_summary_one' )
				: str_replace( '{n}', (string) $n, (string) NH_Settings::get( 'msg_summary_many' ) );
			$items = '';
			foreach ( $errors as $key => $code ) {
				$label  = trim( (string) ( $labels[ $key ] ?? $key ) );
				$items .= '<li><a href="#' . esc_attr( sanitize_html_class( $prefix ) . '-' . $key ) . '">'
					. esc_html( $label . ' — ' . self::code_message( (string) $code ) ) . '</a></li>';
			}
			return $open . '><h3>' . esc_html( $title ) . '</h3><ul>' . $items . '</ul></div>';
		}
		$message = self::message( 'invalid' === $status ? 'error' : $status );
		return $open . '><h3>' . esc_html( (string) NH_Settings::get( 'msg_summary' ) ) . '</h3><ul><li>' . $message . '</li></ul></div>';
	}

	/**
	 * The success alert (visible when the state is "sent").
	 *
	 * @param array $title [ full, plain ].
	 * @param array $text  [ full, plain ].
	 * @param array $state State (state()).
	 */
	public static function success( array $title, array $text, array $state ): string {
		$t = self::pair_html( $title );
		$p = self::pair_html( $text );
		return '<div class="nh-alert nh-alert--success" data-success tabindex="-1" role="status"' . ( 'sent' === ( $state['status'] ?? '' ) ? '' : ' hidden' ) . '>'
			. ( '' !== $t ? '<h3>' . $t . '</h3>' : '' )
			. ( '' !== $p ? '<p>' . $p . '</p>' : '' )
			. '</div>';
	}

	/**
	 * The privacy notice of a form (settings privacy_{type} and its plain twin, tokens applied).
	 */
	public static function privacy_note( string $type ): string {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return '';
		}
		return NH_Text::block(
			NH_Settings::text( 'privacy_' . $type ),
			NH_Settings::text( 'privacy_' . $type . '_plain' ),
			[
				'full_tag'    => 'p',
				'full_class'  => 'nh-form__note',
				'plain_tag'   => 'div',
				'plain_class' => 'nh-form__note',
				'plain_lines' => 'p',
			]
		);
	}

	/**
	 * A note above the form for editors when the form's recipient is not set ('' otherwise).
	 */
	public static function recipient_note( string $type ): string {
		if ( '' !== NH_Settings::recipient( $type ) ) {
			return '';
		}
		return NH_Html::admin_note( self::missing_recipient_text() );
	}

	/**
	 * The sentence shown to editors when a recipient is missing (also the admin notice).
	 */
	public static function missing_recipient_text(): string {
		return __( 'טפסי האתר לא שולחים דוא״ל: לא הוגדרה כתובת נמען (ביקור / משרות). הגדרות ← נווה האירוס.', 'nevehairus-site' );
	}

	/**
	 * admin-post handler of both forms (SPEC §6.2). Answers JSON when sent by nh-forms.js (nh_js=1), else
	 * redirects back (303) with the status in the query string.
	 */
	public static function handle(): void {
		$type = self::type_of_request();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the nonce is verified in process()
		$js       = isset( $_POST['nh_js'] ) && '1' === $_POST['nh_js'];
		$instance = isset( $_POST['nh_instance'] ) && is_string( $_POST['nh_instance'] ) ? self::instance( wp_unslash( $_POST['nh_instance'] ) ) : '';
		$return   = isset( $_POST['nh_return'] ) && is_string( $_POST['nh_return'] ) ? esc_url_raw( wp_unslash( $_POST['nh_return'] ) ) : '';
		// phpcs:enable
		$return = remove_query_arg( [ 'nh_form', 'nh_status', 'nh_err' ], wp_validate_redirect( $return, home_url( '/' ) ) );

		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) || '' === $type ) {
			wp_safe_redirect( home_url( '/' ), 303 );
			self::halt();
			return;
		}
		nocache_headers();

		[ $status, $errors ] = self::process( $type, $return );
		self::respond( $status, $errors, $instance, $return, $js );
	}

	/**
	 * admin-ajax `nh_form_token`: a fresh nonce and time token for a page that may come from a cache.
	 */
	public static function token(): void {
		nocache_headers();
		$type = isset( $_GET['type'] ) && is_string( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- issues a nonce
		if ( ! in_array( $type, self::TYPES, true ) ) {
			wp_send_json( [ 'error' => 'type' ], 400 );
			return;
		}
		$t = time();
		wp_send_json(
			[
				'nonce' => wp_create_nonce( 'nh_form_' . $type ),
				't'     => $t,
				's'     => self::sign( $type, $t ),
			]
		);
	}

	/**
	 * Ends the request after a redirect (`nh_forms_halt` runs first; tests throw there).
	 */
	public static function halt(): void {
		do_action( 'nh_forms_halt' );
		exit;
	}

	/**
	 * The settings message of a status ('' for "sent").
	 */
	public static function message( string $status ): string {
		if ( 'sent' === $status || ! in_array( $status, self::STATUSES, true ) ) {
			return '';
		}
		if ( 'invalid' === $status ) {
			return esc_html( (string) NH_Settings::get( 'msg_summary' ) );
		}
		return NH_Settings::text( 'msg_' . $status );
	}

	/**
	 * Steps 2–10 of the handler: returns [ status, errors ]. The CV temp file is deleted before returning.
	 */
	private static function process( string $type, string $return ): array {
		$tmp = '';
		try {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below, in the order of SPEC §6.2
			if ( isset( $_FILES['cv']['tmp_name'] ) && is_string( $_FILES['cv']['tmp_name'] ) ) {
				$tmp = $_FILES['cv']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a server path, checked with is_uploaded_file()
			}

			// 2. Honeypot: bots see success, nothing is sent.
			if ( isset( $_POST['website'] ) && '' !== trim( (string) wp_unslash( $_POST['website'] ) ) ) {
				return [ 'sent', [] ];
			}
			// 3. Nonce.
			$nonce = isset( $_POST['_nh_nonce'] ) && is_string( $_POST['_nh_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nh_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nh_form_' . $type ) ) {
				return [ 'expired', [] ];
			}
			// 4. Signed time token.
			$t   = isset( $_POST['nh_t'] ) && is_scalar( $_POST['nh_t'] ) ? (int) $_POST['nh_t'] : 0;
			$sig = isset( $_POST['nh_s'] ) && is_string( $_POST['nh_s'] ) ? sanitize_text_field( wp_unslash( $_POST['nh_s'] ) ) : '';
			if ( $t <= 0 || ! hash_equals( self::sign( $type, $t ), $sig ) ) {
				return [ 'expired', [] ];
			}
			$age = time() - $t;
			if ( $age < self::MIN_AGE ) {
				return [ 'sent', [] ];
			}
			if ( $age > self::MAX_AGE ) {
				return [ 'expired', [] ];
			}
			// 5. Rate limit by salted IP hash; only the counter is stored.
			if ( ! self::rate_ok() ) {
				return [ 'limited', [] ];
			}
			// 6. Sanitise and validate.
			[ $values, $errors, $file ] = self::validate( $type, $tmp );
			if ( $errors ) {
				return [ 'invalid', $errors ];
			}
			// 7. Recipient.
			$to = NH_Settings::recipient( $type );
			if ( '' === $to ) {
				return [ 'off', [] ];
			}
			// 8–10. Mail (with the CV attached from the temp file).
			return [ self::send( $type, $to, $values, $return, $file ) ? 'sent' : 'error', [] ];
			// phpcs:enable
		} finally {
			if ( '' !== $tmp && is_file( $tmp ) ) {
				wp_delete_file( $tmp );
			}
		}
	}

	/**
	 * Sanitises the posted values and validates them with the client's rules.
	 *
	 * @return array [ values, errors (key => code), file (null or [ 'tmp' => path, 'ext' => ext ]) ]
	 */
	private static function validate( string $type, string $tmp ): array {
		$values = [];
		$errors = [];
		$file   = null;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in process()
		foreach ( self::schema( $type ) as $key => $f ) {
			$raw = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			switch ( $f['input'] ) {
				case 'checkbox':
					$on             = '' !== $raw;
					$values[ $key ] = $on;
					if ( ! empty( $f['required'] ) && ! $on ) {
						$errors[ $key ] = 'consent';
					}
					break;
				case 'file':
					$file = self::cv( $tmp, $errors );
					break;
				case 'textarea':
					$values[ $key ] = self::cut( sanitize_textarea_field( $raw ), (int) $f['max'] );
					break;
				case 'email':
					$v = trim( $raw );
					if ( '' !== $v && ! is_email( $v ) ) {
						$errors[ $key ] = 'email';
					}
					$values[ $key ] = '' !== $v && is_email( $v ) ? sanitize_email( $v ) : '';
					break;
				default:
					$values[ $key ] = self::cut( sanitize_text_field( $raw ), (int) ( $f['max'] ?? 200 ) );
					if ( 'tel' === ( $f['rule'] ?? '' ) && '' !== $values[ $key ] && ! preg_match( '/^0\d{8,9}$/', (string) preg_replace( '/\D/', '', $values[ $key ] ) ) ) {
						$errors[ $key ] = 'tel';
					}
			}
			if ( ! empty( $f['required'] ) && 'checkbox' !== $f['input'] && '' === ( $values[ $key ] ?? '' ) && ! isset( $errors[ $key ] ) ) {
				$errors[ $key ] = 'required';
			}
		}
		// phpcs:enable
		return [ $values, $errors, $file ];
	}

	/**
	 * Checks the uploaded CV (optional): real upload, size, extension and real type.
	 *
	 * @param string $tmp    Upload temp file.
	 * @param array  $errors Errors (by reference).
	 * @return array|null [ 'tmp' => path, 'ext' => ext ] or null.
	 */
	private static function cv( string $tmp, array &$errors ): ?array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in process()
		if ( empty( $_FILES['cv'] ) || ! is_array( $_FILES['cv'] ) ) {
			return null;
		}
		$err  = isset( $_FILES['cv']['error'] ) ? (int) $_FILES['cv']['error'] : UPLOAD_ERR_NO_FILE;
		$size = isset( $_FILES['cv']['size'] ) ? (int) $_FILES['cv']['size'] : 0;
		$name = isset( $_FILES['cv']['name'] ) && is_string( $_FILES['cv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['cv']['name'] ) ) : '';
		// phpcs:enable
		if ( UPLOAD_ERR_NO_FILE === $err ) {
			return null;
		}
		if ( UPLOAD_ERR_INI_SIZE === $err || UPLOAD_ERR_FORM_SIZE === $err || $size > self::MAX_FILE ) {
			$errors['cv'] = 'file_size';
			return null;
		}
		$uploaded = '' !== $tmp && (bool) apply_filters( 'nh_forms_is_uploaded_file', is_uploaded_file( $tmp ), $tmp );
		if ( UPLOAD_ERR_OK !== $err || ! $uploaded || '' === $name ) {
			$errors['cv'] = 'file_type';
			return null;
		}
		$check = wp_check_filetype_and_ext( $tmp, $name, self::CV_MIMES );
		$ext   = strtolower( (string) ( $check['ext'] ?? '' ) );
		if ( '' === $ext || ! isset( self::CV_MIMES[ $ext ] ) || empty( $check['type'] ) ) {
			$errors['cv'] = 'file_type';
			return null;
		}
		return [
			'tmp' => $tmp,
			'ext' => $ext,
		];
	}

	/**
	 * Sends the e-mail (plain text, one "label: value" line per filled field, the page and the date).
	 *
	 * @param string     $type   Form type.
	 * @param string     $to     Recipient.
	 * @param array      $values Sanitised values.
	 * @param string     $return Page URL.
	 * @param array|null $file   CV.
	 */
	private static function send( string $type, string $to, array $values, string $return, ?array $file ): bool {
		$site    = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$subject = 'visit' === $type
			? sprintf( 'בקשה לביקור — %s', $site )
			: sprintf( 'מועמדות: %1$s — %2$s', (string) ( $values['role'] ?? '' ), $site );

		$attachments = [];
		$lines       = [];
		foreach ( self::schema( $type ) as $key => $f ) {
			$label = $f['label'];
			if ( 'file' === $f['input'] ) {
				if ( null !== $file ) {
					$attach                 = sanitize_file_name( 'cv-' . wp_date( 'Y-m-d' ) . '.' . $file['ext'] );
					$attachments[ $attach ] = $file['tmp'];
					$lines[]                = $label . ': ' . $attach;
				}
				continue;
			}
			if ( 'checkbox' === $f['input'] ) {
				$lines[] = $label . ': ' . ( empty( $values[ $key ] ) ? 'לא' : 'כן' );
				continue;
			}
			$v = (string) ( $values[ $key ] ?? '' );
			if ( '' === $v ) {
				continue;
			}
			$lines[] = 'textarea' === $f['input'] ? $label . ":\n" . $v : $label . ': ' . $v;
		}
		$lines[] = '';
		$lines[] = 'עמוד: ' . $return;
		$lines[] = 'נשלח: ' . wp_date( 'Y-m-d H:i' );

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
		$email   = (string) ( $values['email'] ?? '' );
		if ( '' !== $email && is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . self::header_text( (string) ( $values['name'] ?? '' ) ) . ' <' . $email . '>';
		}

		$from   = trim( (string) NH_Settings::get( 'mail_from_name' ) );
		$filter = static function () use ( $from ) {
			return $from;
		};
		if ( '' !== $from ) {
			add_filter( 'wp_mail_from_name', $filter );
		}
		try {
			$ok = wp_mail( $to, self::header_text( $subject ), implode( "\n", $lines ), $headers, $attachments );
		} finally {
			remove_filter( 'wp_mail_from_name', $filter );
		}
		return (bool) $ok;
	}

	/**
	 * Step 11: JSON for nh-forms.js, else a 303 redirect back with the status.
	 */
	private static function respond( string $status, array $errors, string $instance, string $return, bool $js ): void {
		if ( $js ) {
			wp_send_json(
				[
					'ok'      => 'sent' === $status,
					'status'  => $status,
					'errors'  => (object) $errors,
					'message' => self::message( $status ),
				]
			);
			return;
		}
		$args = [
			'nh_form'   => $instance,
			'nh_status' => $status,
		];
		if ( $errors ) {
			$pairs = [];
			foreach ( $errors as $key => $code ) {
				$pairs[] = $key . ':' . $code;
			}
			$args['nh_err'] = implode( ',', $pairs );
		}
		wp_safe_redirect( add_query_arg( $args, $return ), 303 );
		self::halt();
	}

	/**
	 * Rate limit: 5 accepted attempts per 10 minutes per salted IP hash (filters nh_rate_limit,
	 * nh_rate_window, nh_client_ip).
	 */
	private static function rate_ok(): bool {
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip     = (string) apply_filters( 'nh_client_ip', $ip );
		$key    = 'nh_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 32 );
		$limit  = max( 1, (int) apply_filters( 'nh_rate_limit', 5 ) );
		$window = max( 60, (int) apply_filters( 'nh_rate_window', 10 * MINUTE_IN_SECONDS ) );
		$n      = (int) get_transient( $key );
		if ( $n >= $limit ) {
			return false;
		}
		set_transient( $key, $n + 1, $window );
		return true;
	}

	/**
	 * Signature of a time token.
	 */
	private static function sign( string $type, int $t ): string {
		return hash_hmac( 'sha256', $type . '|' . $t, wp_salt( 'nonce' ) );
	}

	/**
	 * The form type of the current admin-post request ('' if unknown).
	 */
	private static function type_of_request(): string {
		$action = (string) current_action();
		if ( '' === $action || 0 !== strpos( $action, 'admin_post' ) ) {
			$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only
		}
		$type = (string) preg_replace( '/^(admin_post_(nopriv_)?)?nh_/', '', $action );
		return in_array( $type, self::TYPES, true ) ? $type : '';
	}

	/**
	 * An element id as sent back ([A-Za-z0-9_-], 40 chars).
	 */
	private static function instance( string $id ): string {
		return substr( (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $id ), 0, 40 );
	}

	/**
	 * The error code of a field in the state, or ''.
	 */
	private static function error_of( string $key, array $state ): string {
		$code = $state['errors'][ $key ] ?? '';
		return is_string( $code ) && in_array( $code, self::CODES, true ) ? $code : '';
	}

	/**
	 * `<p class="nh-error" id="{id}-err">{icon}<span>{message}</span></p>` (as setError() in JS), or ''.
	 */
	private static function error_html( string $id, string $code ): string {
		if ( '' === $code ) {
			return '';
		}
		return '<p class="nh-error" id="' . esc_attr( $id . '-err' ) . '">' . self::ICON_ERR . '<span>' . esc_html( self::code_message( $code ) ) . '</span></p>';
	}

	/**
	 * The settings message of an error code.
	 */
	private static function code_message( string $code ): string {
		$keys = [
			'required'  => 'msg_required',
			'email'     => 'msg_email',
			'tel'       => 'msg_tel',
			'consent'   => 'msg_consent',
			'file_type' => 'msg_file_type',
			'file_size' => 'msg_file_size',
		];
		return isset( $keys[ $code ] ) ? trim( (string) NH_Settings::get( $keys[ $code ] ) ) : '';
	}

	/**
	 * `<option>`s from lines: the first line is the empty-value option, the others are value = text.
	 *
	 * @param string|array $options  Lines.
	 * @param string       $selected Text of the selected option.
	 */
	private static function options( $options, string $selected ): string {
		$lines    = is_array( $options ) ? array_values( array_filter( array_map( 'trim', array_map( 'strval', $options ) ), 'strlen' ) ) : NH_Text::lines( (string) $options );
		$selected = trim( $selected );
		$out      = '';
		foreach ( $lines as $i => $line ) {
			if ( 0 === $i ) {
				$out .= '<option value="">' . esc_html( $line ) . '</option>';
				continue;
			}
			$out .= '<option' . ( '' !== $selected && $line === $selected ? ' selected' : '' ) . '>' . esc_html( $line ) . '</option>';
		}
		return $out;
	}

	/**
	 * A [ full, plain ] pair (or a string) → NH_Text::pair().
	 *
	 * @param array|string $pair Pair.
	 */
	private static function pair_html( $pair ): string {
		if ( is_array( $pair ) ) {
			return NH_Text::pair( (string) ( $pair[0] ?? '' ), (string) ( $pair[1] ?? '' ) );
		}
		return NH_Text::pair( (string) $pair );
	}

	/**
	 * At most $max characters.
	 */
	private static function cut( string $v, int $max ): string {
		return $max > 0 && mb_strlen( $v ) > $max ? mb_substr( $v, 0, $max ) : $v;
	}

	/**
	 * Text that is safe in a mail header (no line breaks, no address delimiters).
	 */
	private static function header_text( string $v ): string {
		return trim( (string) preg_replace( '/[\r\n\t<>",;\\\\]+/u', ' ', $v ) );
	}
}
