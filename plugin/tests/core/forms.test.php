<?php
/**
 * Core tests 4 — NH_Forms (SPEC §11.5 item 4, §6): the handler's security steps in order, the e-mail,
 * the CV, what is (not) stored; the token endpoint; the markup helpers (§6.3 exact output).
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prepares a POST of a form: valid nonce and time token ($age seconds old), the given fields.
 */
function nh_test_post( string $type, array $fields, int $age = 30, array $over = [] ): void {
	$t     = time() - $age;
	$_POST = array_merge(
		[
			'action'      => 'nh_' . $type,
			'_nh_nonce'   => wp_create_nonce( 'nh_form_' . $type ),
			'nh_t'        => (string) $t,
			'nh_s'        => hash_hmac( 'sha256', $type . '|' . $t, wp_salt( 'nonce' ) ),
			'nh_instance' => 'a1b2c3d',
			'nh_return'   => 'https://nh.test/#door',
			'nh_js'       => '1',
			'website'     => '',
		],
		$fields,
		$over
	);
	$_REQUEST                  = $_POST;
	$_SERVER['REQUEST_METHOD'] = 'POST';
}

/**
 * Runs the handler; returns the JSON answer (nh_js=1) or the redirect.
 */
function nh_test_submit(): array {
	try {
		NH_Forms::handle();
	} catch ( NH_Test_Halt $e ) {
		// expected: wp_send_json() / the redirect end the request
	}
	$json = end( $GLOBALS['nh_test']['json'] );
	$redirect = end( $GLOBALS['nh_test']['redirects'] );
	return [
		'json'     => false === $json ? null : $json[0],
		'redirect' => false === $redirect ? null : $redirect,
	];
}

/** A valid visit request. */
const NH_TEST_VISIT = [
	'name'    => 'דנה כהן',
	'phone'   => '050-1234567',
	'email'   => 'dana@example.test',
	'who'     => 'הורה או קרוב משפחה',
	'program' => 'דיור בקהילה',
	'message' => "שלום,\nנשמח לבקר.",
	'consent' => 'on',
];

return [
	'honeypot filled → "sent", no mail' => static function () {
		update_option( 'nh_settings', [ 'visit_recipient' => 'office@example.test' ] );
		nh_test_post( 'visit', NH_TEST_VISIT, 30, [ 'website' => 'http://spam.example.com' ] );
		$r = nh_test_submit();
		nh_assert_same( 'sent', $r['json']['status'] );
		nh_assert_same( true, $r['json']['ok'] );
		nh_assert_same( [], $GLOBALS['nh_test']['mails'] );
		nh_assert( ! empty( $GLOBALS['nh_test']['headers']['nocache'] ), 'nocache_headers()' );
	},

	'bad nonce → "expired"; bad signature → "expired"' => static function () {
		update_option( 'nh_settings', [ 'visit_recipient' => 'office@example.test' ] );
		nh_test_post( 'visit', NH_TEST_VISIT, 30, [ '_nh_nonce' => 'nonce-nh_form_jobs' ] );
		nh_assert_same( 'expired', nh_test_submit()['json']['status'] );
		nh_test_post( 'visit', NH_TEST_VISIT, 30, [ 'nh_s' => str_repeat( '0', 64 ) ] );
		nh_assert_same( 'expired', nh_test_submit()['json']['status'] );
		nh_assert_same( [], $GLOBALS['nh_test']['mails'] );
	},

	'too fast → "sent" without sending; older than two days → "expired"' => static function () {
		update_option( 'nh_settings', [ 'visit_recipient' => 'office@example.test' ] );
		nh_test_post( 'visit', NH_TEST_VISIT, 1 );
		nh_assert_same( 'sent', nh_test_submit()['json']['status'] );
		nh_assert_same( [], $GLOBALS['nh_test']['mails'] );
		nh_test_post( 'visit', NH_TEST_VISIT, 3 * DAY_IN_SECONDS );
		nh_assert_same( 'expired', nh_test_submit()['json']['status'] );
		nh_assert_same( [], $GLOBALS['nh_test']['mails'] );
	},

	'the 6th request within 10 minutes → "limited"; only a hashed counter is stored' => static function () {
		update_option( 'nh_settings', [ 'visit_recipient' => '' ] );
		for ( $i = 1; $i <= 5; $i++ ) {
			nh_test_post( 'visit', NH_TEST_VISIT );
			nh_assert_same( 'off', nh_test_submit()['json']['status'], "request $i" );
		}
		nh_test_post( 'visit', NH_TEST_VISIT );
		nh_assert_same( 'limited', nh_test_submit()['json']['status'], 'request 6' );
		$keys = array_keys( $GLOBALS['nh_test']['transients'] );
		nh_assert_same( 1, count( $keys ) );
		nh_assert( (bool) preg_match( '/^nh_rl_[0-9a-f]{32}$/', $keys[0] ), 'key nh_rl_ + 32 hex: ' . $keys[0] );
		nh_assert_not_contains( '192.0.2.10', $keys[0], 'the IP itself is not stored' );
		nh_assert_same( 5, $GLOBALS['nh_test']['transients'][ $keys[0] ]['value'] );
		nh_assert_same( 600, $GLOBALS['nh_test']['transients'][ $keys[0] ]['ttl'] );
		$_SERVER['REMOTE_ADDR'] = '192.0.2.99';
		nh_test_post( 'visit', NH_TEST_VISIT );
		nh_assert_same( 'off', nh_test_submit()['json']['status'], 'another visitor is not limited' );
	},

	'IPv6: one bucket per /64 network; an IPv4-mapped address counts as its IPv4 address' => static function () {
		update_option( 'nh_settings', [ 'visit_recipient' => '' ] );
		for ( $i = 1; $i <= 5; $i++ ) {
			$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2::' . dechex( $i * 4099 ); // one client, a new address each time
			nh_test_post( 'visit', NH_TEST_VISIT );
			nh_assert_same( 'off', nh_test_submit()['json']['status'], "request $i" );
		}
		$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:aaaa:bbbb:cccc:dddd';
		nh_test_post( 'visit', NH_TEST_VISIT );
		nh_assert_same( 'limited', nh_test_submit()['json']['status'], 'the same /64' );
		$_SERVER['REMOTE_ADDR'] = '2001:db8:1:3::1';
		nh_test_post( 'visit', NH_TEST_VISIT );
		nh_assert_same( 'off', nh_test_submit()['json']['status'], 'another /64' );
		nh_assert_same( 2, count( $GLOBALS['nh_test']['transients'] ), 'two buckets' );

		$GLOBALS['nh_test']['transients'] = [];
		for ( $i = 1; $i <= 5; $i++ ) {
			$_SERVER['REMOTE_ADDR'] = 0 === $i % 2 ? '::ffff:192.0.2.10' : '192.0.2.10';
			nh_test_post( 'visit', NH_TEST_VISIT );
			nh_test_submit();
		}
		$_SERVER['REMOTE_ADDR'] = '::ffff:192.0.2.10';
		nh_test_post( 'visit', NH_TEST_VISIT );
		nh_assert_same( 'limited', nh_test_submit()['json']['status'], 'IPv4 and IPv4-mapped share one bucket' );
		nh_assert_same( 1, count( $GLOBALS['nh_test']['transients'] ) );
		$_SERVER['REMOTE_ADDR'] = '::ffff:192.0.2.11';
		nh_test_post( 'visit', NH_TEST_VISIT );
		nh_assert_same( 'off', nh_test_submit()['json']['status'], 'another IPv4-mapped address is another visitor' );
	},

	'invalid fields → "invalid" with the right codes (server rules = client rules)' => static function () {
		update_option( 'nh_settings', [ 'visit_recipient' => 'office@example.test' ] );
		nh_test_post(
			'visit',
			[
				'name'  => '   ',
				'phone' => '12345',
				'email' => 'not-an-email',
			]
		);
		$r = nh_test_submit();
		nh_assert_same( 'invalid', $r['json']['status'] );
		nh_assert_same(
			[
				'name'    => 'required',
				'phone'   => 'tel',
				'email'   => 'email',
				'consent' => 'consent',
			],
			$r['json']['errors']
		);
		nh_assert_same( [], $GLOBALS['nh_test']['mails'] );
		nh_test_post( 'jobs', [ 'name' => 'א', 'phone' => '0501234567', 'consent' => 'on' ] );
		$r = nh_test_submit();
		nh_assert_same(
			[
				'role'  => 'required',
				'email' => 'required',
			],
			$r['json']['errors'],
			'jobs: the role and the e-mail are required'
		);
		nh_test_post( 'visit', array_merge( NH_TEST_VISIT, [ 'phone' => '+972-50-123-4567' ] ) );
		nh_assert_same( [ 'phone' => 'tel' ], nh_test_submit()['json']['errors'], 'digits must start with 0 (site.js L372–375)' );
	},

	'without a recipient → "off", nothing sent' => static function () {
		nh_test_post( 'visit', NH_TEST_VISIT );
		$r = nh_test_submit();
		nh_assert_same( 'off', $r['json']['status'] );
		nh_assert_same( false, $r['json']['ok'] );
		nh_assert_contains( 'tel:+97289403047', $r['json']['message'], 'the message names the phone' );
		nh_assert_same( [], $GLOBALS['nh_test']['mails'] );
	},

	'success → one plain-text mail with the right subject and headers' => static function () {
		update_option(
			'nh_settings',
			[
				'visit_recipient' => 'office@example.test',
				'mail_from_name'  => 'מעון נווה האירוס',
			]
		);
		nh_test_post( 'visit', NH_TEST_VISIT );
		$r = nh_test_submit();
		nh_assert_same( 'sent', $r['json']['status'] );
		nh_assert_same( 1, count( $GLOBALS['nh_test']['mails'] ) );
		$m = $GLOBALS['nh_test']['mails'][0];
		nh_assert_same( 'office@example.test', $m['to'] );
		nh_assert_same( 'בקשה לביקור — מעון נווה האירוס', $m['subject'] );
		nh_assert_same( [ 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: דנה כהן <dana@example.test>' ], $m['headers'] );
		nh_assert_same( 'מעון נווה האירוס', $m['from_name'], 'From name from the settings, during the call' );
		nh_assert_same( 'WordPress', apply_filters( 'wp_mail_from_name', 'WordPress' ), 'the From filter is removed again' );
		nh_assert_contains( "שם מלא: דנה כהן\nטלפון: 050-1234567\nדוא״ל: dana@example.test\nמי ממלא את הטופס: הורה או קרוב משפחה\nבית שמעניין: דיור בקהילה\nהודעה:\nשלום,\nנשמח לבקר.\nאישור מדיניות הפרטיות: כן\n\nעמוד: https://nh.test/#door", $m['message'] );
		nh_assert_same( [], $m['attachments'] );
	},

	'a header injection attempt stays in one header line' => static function () {
		update_option( 'nh_settings', [ 'visit_recipient' => 'office@example.test' ] );
		nh_test_post( 'visit', array_merge( NH_TEST_VISIT, [ 'name' => "דנה\r\nBcc: victim@example.test" ] ) );
		nh_test_submit();
		$h = $GLOBALS['nh_test']['mails'][0]['headers'];
		nh_assert_same( 2, count( $h ) );
		nh_assert( false === strpos( $h[1], "\n" ) && false === strpos( $h[1], "\r" ), 'no line break in Reply-To' );
	},

	'the CV is attached as cv-YYYY-MM-DD.pdf, the temp file deleted, the media library never used' => static function () {
		update_option( 'nh_settings', [ 'jobs_recipient' => 'jobs@example.test' ] );
		$tmp = tempnam( sys_get_temp_dir(), 'nhcv' );
		file_put_contents( $tmp, "%PDF-1.4\n%test cv\n" );
		$_FILES = [
			'cv' => [
				'name'     => 'קורות חיים של דנה.pdf',
				'type'     => 'application/pdf',
				'tmp_name' => $tmp,
				'error'    => UPLOAD_ERR_OK,
				'size'     => filesize( $tmp ),
			],
		];
		$real = static function () {
			return true;
		};
		add_filter( 'nh_forms_is_uploaded_file', $real );
		nh_test_post(
			'jobs',
			[
				'role'    => 'עובד/ת סוציאלי/ת',
				'name'    => 'דנה כהן',
				'phone'   => '0501234567',
				'email'   => 'dana@example.test',
				'consent' => 'on',
			]
		);
		$r = nh_test_submit();
		remove_filter( 'nh_forms_is_uploaded_file', $real );
		nh_assert_same( 'sent', $r['json']['status'] );
		$m    = $GLOBALS['nh_test']['mails'][0];
		$name = 'cv-' . gmdate( 'Y-m-d' ) . '.pdf';
		nh_assert_same( [ $name => $tmp ], $m['attachments'] );
		nh_assert_same( "%PDF-1.4\n%test cv\n", $m['files'][ $name ], 'attached from the temp file' );
		nh_assert_same( 'מועמדות: עובד/ת סוציאלי/ת — מעון נווה האירוס', $m['subject'] );
		nh_assert_contains( 'קורות חיים: ' . $name, $m['message'] );
		nh_assert_contains( 'שמירת הפרטים למשרות נוספות: לא', $m['message'] );
		nh_assert( ! file_exists( $tmp ), 'temp file deleted' );
		nh_assert_same( [ $tmp ], $GLOBALS['nh_test']['deleted'] );
	},

	'a CV that is not a real PDF, too large, or not an upload → "invalid"; a path that is not an upload is never deleted' => static function () {
		update_option( 'nh_settings', [ 'jobs_recipient' => 'jobs@example.test' ] );
		$job = [
			'role'    => 'טבח/ית',
			'name'    => 'דנה',
			'phone'   => '0501234567',
			'email'   => 'dana@example.test',
			'consent' => 'on',
		];
		$cases = [
			[ "MZ\x90\x00 not a pdf", 'cv.pdf', 100, true, 'file_type' ],
			[ "%PDF-1.4\n", 'cv.exe', 100, true, 'file_type' ],
			[ "%PDF-1.4\n", 'cv.pdf', NH_Forms::MAX_FILE + 1, true, 'file_size' ],
			[ "%PDF-1.4\n", 'cv.pdf', 100, false, 'file_type' ],
		];
		foreach ( $cases as $i => [ $content, $file, $size, $uploaded, $code ] ) {
			$tmp = tempnam( sys_get_temp_dir(), 'nhcv' );
			file_put_contents( $tmp, $content );
			$_FILES = [
				'cv' => [
					'name'     => $file,
					'type'     => 'application/pdf',
					'tmp_name' => $tmp,
					'error'    => UPLOAD_ERR_OK,
					'size'     => $size,
				],
			];
			$cb = static function () use ( $uploaded ) {
				return $uploaded;
			};
			add_filter( 'nh_forms_is_uploaded_file', $cb );
			nh_test_post( 'jobs', $job );
			$r = nh_test_submit();
			remove_filter( 'nh_forms_is_uploaded_file', $cb );
			nh_assert_same( [ 'cv' => $code ], $r['json']['errors'], "case $i" );
			if ( $uploaded ) {
				nh_assert( ! file_exists( $tmp ), "case $i: temp file deleted" );
			} else {
				nh_assert( file_exists( $tmp ), "case $i: not an upload, left alone" );
				unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		nh_assert_same( [], $GLOBALS['nh_test']['mails'] );
		// a request that fails before the CV is looked at (here: the nonce) touches no file that is not an upload
		$other = tempnam( sys_get_temp_dir(), 'nhcv' );
		$_FILES = [
			'cv' => [
				'name'     => 'cv.pdf',
				'type'     => 'application/pdf',
				'tmp_name' => $other,
				'error'    => UPLOAD_ERR_OK,
				'size'     => 0,
			],
		];
		nh_test_post( 'jobs', $job, 30, [ '_nh_nonce' => 'forged' ] );
		nh_assert_same( 'expired', nh_test_submit()['json']['status'] );
		nh_assert( file_exists( $other ), 'a path that is not an upload is left alone, whatever the answer' );
		unlink( $other ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	},

	'without JS: 303 back to the page with the status, never the typed values' => static function () {
		update_option( 'nh_settings', [ 'visit_recipient' => 'office@example.test' ] );
		nh_test_post( 'visit', array_merge( NH_TEST_VISIT, [ 'phone' => '999' ] ), 30, [ 'nh_js' => '0', 'nh_return' => 'https://nh.test/main-home/?nh_status=sent#visit' ] );
		$r = nh_test_submit();
		nh_assert_same( null, $r['json'] );
		nh_assert_same( [ 'https://nh.test/main-home/?nh_form=a1b2c3d&nh_status=invalid&nh_err=phone:tel#visit', 303 ], $r['redirect'] );
		nh_assert_not_contains( '999', $r['redirect'][0] );
		nh_test_post( 'visit', NH_TEST_VISIT, 30, [ 'nh_js' => '0', 'nh_return' => 'https://evil.example.com/' ] );
		$r = nh_test_submit();
		nh_assert_same( 'https://nh.test/?nh_form=a1b2c3d&nh_status=sent', $r['redirect'][0], 'a foreign return URL falls back to the home page' );
	},

	'GET or an unknown action is refused without processing' => static function () {
		nh_test_post( 'visit', NH_TEST_VISIT );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$r = nh_test_submit();
		nh_assert_same( [ 'https://nh.test/', 303 ], $r['redirect'] );
		nh_assert_same( [], $GLOBALS['nh_test']['transients'] );
	},

	'nothing is written to the options; no posts are created' => static function () {
		update_option( 'nh_settings', [ 'visit_recipient' => 'office@example.test' ] );
		$before = $GLOBALS['nh_test']['options'];
		nh_test_post( 'visit', NH_TEST_VISIT );
		nh_test_submit();
		nh_assert_same( $before, $GLOBALS['nh_test']['options'] );
		foreach ( array_keys( $GLOBALS['nh_test']['transients'] ) as $k ) {
			nh_assert( 0 === strpos( $k, 'nh_rl_' ), "only rate-limit transients ($k)" );
		}
	},

	'token(): a fresh nonce and signed time; unknown type → 400' => static function () {
		$_GET = [ 'type' => 'jobs' ];
		try {
			NH_Forms::token();
		} catch ( NH_Test_Halt $e ) {
			// wp_send_json()
		}
		[ $j, $status ] = end( $GLOBALS['nh_test']['json'] );
		nh_assert_same( 'nonce-nh_form_jobs', $j['nonce'] );
		nh_assert_same( hash_hmac( 'sha256', 'jobs|' . $j['t'], wp_salt( 'nonce' ) ), $j['s'] );
		$_GET = [ 'type' => 'x' ];
		try {
			NH_Forms::token();
		} catch ( NH_Test_Halt $e ) {
			// wp_send_json()
		}
		nh_assert_same( 400, end( $GLOBALS['nh_test']['json'] )[1] );
	},

	'hidden_fields(), honeypot(): exact output' => static function () {
		$h = NH_Forms::hidden_fields( 'visit', 'a1b2c3d', 'https://nh.test/#door' );
		$t = (int) preg_replace( '/.*name="nh_t" value="(\d+)".*/s', '$1', $h );
		nh_assert_same(
			'<input type="hidden" name="action" value="nh_visit"><input type="hidden" name="_nh_nonce" value="nonce-nh_form_visit">'
			. '<input type="hidden" name="nh_t" value="' . $t . '"><input type="hidden" name="nh_s" value="' . hash_hmac( 'sha256', 'visit|' . $t, wp_salt( 'nonce' ) ) . '">'
			. '<input type="hidden" name="nh_instance" value="a1b2c3d"><input type="hidden" name="nh_return" value="https://nh.test/#door"><input type="hidden" name="nh_js" value="0">',
			$h
		);
		nh_assert( abs( time() - $t ) < 5 );
		nh_assert_same( '<div class="nh-hp" aria-hidden="true"><label for="door-website">אתר</label><input id="door-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>', NH_Forms::honeypot( 'door' ) );
	},

	'field(), check(): the prototype\'s markup (index L354–364, page L211–219, ds L324–328)' => static function () {
		$none = NH_Forms::state( '' );
		nh_assert_same(
			'<div class="nh-field"><label class="nh-label" for="door-name">שם מלא&nbsp;<span class="nh-req" aria-hidden="true">*</span></label><input class="nh-input" id="door-name" name="name" type="text" autocomplete="name" required></div>',
			NH_Forms::field( 'visit', 'name', [ 'id' => 'door-name', 'label' => [ 'שם מלא', '' ] ], $none )
		);
		nh_assert_same(
			'<div class="nh-field"><label class="nh-label" for="door-phone">טלפון&nbsp;<span class="nh-req" aria-hidden="true">*</span></label><input class="nh-input" id="door-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" required></div>',
			NH_Forms::field( 'visit', 'phone', [ 'id' => 'door-phone', 'label' => [ 'טלפון', '' ] ], $none )
		);
		nh_assert_same(
			'<div class="nh-field"><label class="nh-label" for="door-who">מי ממלא את הטופס?</label><select class="nh-select" id="door-who" name="who"><option value="">בחירה…</option><option>אני, בשביל עצמי</option><option selected>אחר</option></select></div>',
			NH_Forms::field( 'visit', 'who', [ 'id' => 'door-who', 'label' => [ 'מי ממלא את הטופס?', '' ], 'options' => "בחירה…\nאני, בשביל עצמי\nאחר", 'selected' => 'אחר' ], $none )
		);
		nh_assert_same(
			'<div class="nh-field"><label class="nh-label" for="visit-message">משהו שחשוב לנו לדעת?</label><textarea class="nh-textarea" id="visit-message" name="message" aria-describedby="visit-message-hint"></textarea><p class="nh-hint" id="visit-message-hint"><span class="v3-full">אין צורך לפרט כאן מידע רפואי. נדבר על הכול בשיחה.</span><span class="v3-plain">לא צריך לכתוב כאן מידע רפואי. נדבר על זה בשיחה.</span></p></div>',
			NH_Forms::field( 'visit', 'message', [ 'id' => 'visit-message', 'label' => [ 'משהו שחשוב לנו לדעת?', '' ], 'hint' => [ 'אין צורך לפרט כאן מידע רפואי. נדבר על הכול בשיחה.', 'לא צריך לכתוב כאן מידע רפואי. נדבר על זה בשיחה.' ] ], $none )
		);
		nh_assert_same(
			'<div class="nh-field"><label class="nh-label" for="form-cv">קורות חיים</label><input class="nh-input" id="form-cv" name="cv" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" aria-describedby="form-cv-hint"><p class="nh-hint" id="form-cv-hint">PDF, DOC או DOCX, עד 5MB.</p></div>',
			NH_Forms::field( 'jobs', 'cv', [ 'id' => 'form-cv', 'label' => [ 'קורות חיים', '' ], 'hint' => [ 'PDF, DOC או DOCX, עד 5MB.', '' ] ], $none )
		);
		nh_assert_same(
			'<div class="nh-check"><input id="door-consent" name="consent" type="checkbox" required data-error-label="אישור מדיניות הפרטיות"><label for="door-consent"><span class="v3-full">קראתי את <a href="#privacy">מדיניות הפרטיות</a> ואני מסכים/ה.</span><span class="v3-plain">אני מסכים. <a href="#privacy">מה עושים עם הפרטים שלי?</a></span>&nbsp;<span class="nh-req" aria-hidden="true">*</span></label></div>',
			NH_Forms::check( 'visit', 'consent', [ 'id' => 'door-consent', 'label' => [ 'קראתי את <a href="#privacy">מדיניות הפרטיות</a> ואני מסכים/ה.', 'אני מסכים. <a href="#privacy">מה עושים עם הפרטים שלי?</a>' ], 'error_label' => 'אישור מדיניות הפרטיות' ], $none )
		);
		nh_assert_same(
			'<div class="nh-check"><input id="form-keep" name="keep" type="checkbox"><label for="form-keep">אפשר לשמור את הפרטים שלי.</label></div>',
			NH_Forms::check( 'jobs', 'keep', [ 'id' => 'form-keep', 'label' => [ 'אפשר לשמור את הפרטים שלי.', '' ] ], $none )
		);
		nh_assert_same( '', NH_Forms::field( 'visit', 'consent', [], $none ), 'a checkbox is not a field' );
		nh_assert_same( '', NH_Forms::field( 'visit', 'role', [], $none ), 'not in the visit schema' );
	},

	'state(), summary(), success() after a redirect' => static function () {
		$_GET = [
			'nh_form'   => 'a1b2c3d',
			'nh_status' => 'invalid',
			'nh_err'    => 'name:required,phone:tel,evil:<x>,email:bogus',
		];
		$state = NH_Forms::state( 'a1b2c3d' );
		nh_assert_same(
			[
				'status' => 'invalid',
				'errors' => [
					'name'  => 'required',
					'phone' => 'tel',
				],
			],
			$state
		);
		nh_assert_same( '', NH_Forms::state( 'zzz' )['status'], 'another instance' );
		$_GET['nh_err'] = 'call_this_number:required,name:required,scam_offer_here:email,cv:file_type';
		nh_assert_same(
			[
				'name' => 'required',
				'cv'   => 'file_type',
			],
			NH_Forms::state( 'a1b2c3d' )['errors'],
			'only field keys of the forms: a crafted link cannot put its own words in the summary'
		);
		$_GET['nh_err'] = 'name:required,phone:tel,evil:<x>,email:bogus';
		$s = NH_Forms::summary( $state, [ 'name' => 'שם מלא', 'phone' => 'טלפון' ], 'door' );
		nh_assert_same(
			'<div class="nh-alert nh-alert--error" data-error-summary tabindex="-1"><h3>נמצאו 2 שגיאות בטופס:</h3><ul><li><a href="#door-name">שם מלא' . "\u{00A0}" . '— צריך למלא את השדה הזה.</a></li><li><a href="#door-phone">טלפון' . "\u{00A0}" . '— מספר הטלפון לא תקין: צריך 9 או 10 ספרות, ובהתחלה 0.</a></li></ul></div>',
			$s
		);
		$f = NH_Forms::field( 'visit', 'phone', [ 'id' => 'door-phone', 'label' => [ 'טלפון', '' ] ], $state );
		nh_assert_contains( 'required aria-invalid="true" aria-describedby="door-phone-err">', $f );
		nh_assert_contains( '<p class="nh-error" id="door-phone-err"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">', $f );
		nh_assert_same(
			'<div class="nh-alert nh-alert--error" data-error-summary tabindex="-1" hidden><h3>נמצאו שגיאות בטופס:</h3><ul></ul></div>',
			NH_Forms::summary( NH_Forms::state( '' ), [], 'door' ),
			'no state: hidden, as the prototype'
		);
		$off = NH_Forms::summary(
			[
				'status' => 'off',
				'errors' => [],
			],
			[],
			'door'
		);
		nh_assert_contains( '<li>אי אפשר לשלוח את הטופס כרגע. אפשר להתקשר: <a href="tel:+97289403047">', $off );
		nh_assert_same(
			'<div class="nh-alert nh-alert--success" data-success tabindex="-1" role="status" hidden><h3>תודה! הבקשה התקבלה.</h3><p>נחזור אליכם לתיאום.</p></div>',
			NH_Forms::success( [ 'תודה! הבקשה התקבלה.', '' ], [ 'נחזור אליכם לתיאום.', '' ], NH_Forms::state( '' ) )
		);
		nh_assert_not_contains( ' hidden', NH_Forms::success( [ 'תודה', '' ], [ '', '' ], [ 'status' => 'sent' ] ) );
	},

	'required_note(), privacy_note(), recipient_note()' => static function () {
		nh_assert_same(
			'<p class="nh-form__note v3-full">שדות המסומנים ב־<span aria-hidden="true">*</span><span class="nh-sr-only">כוכבית</span> הם שדות חובה.</p><p class="nh-form__note v3-plain">צריך למלא את השדות עם כוכבית <span aria-hidden="true">*</span>.</p>',
			NH_Forms::required_note( 'שדות המסומנים ב־<span aria-hidden="true">*</span><span class="nh-sr-only">כוכבית</span> הם שדות חובה.', 'צריך למלא את השדות עם כוכבית <span aria-hidden="true">*</span>.' )
		);
		$p = NH_Forms::privacy_note( 'visit' );
		nh_assert_contains( '<p class="nh-form__note v3-full">מסירת הפרטים אינה חובה', $p );
		nh_assert_contains( 'ולתקן אותו: <a href="tel:+97289403047"><span class="nh-ltr">08-9403047</span></a>.</p>', $p );
		nh_assert_contains( '<div class="nh-form__note v3-plain"><p>לא חייבים לתת לנו את הפרטים.</p>', $p );
		nh_assert( 0 === strpos( NH_Forms::privacy_note( 'jobs' ), '<p class="nh-form__note">' ), 'jobs: one note without twin' );
		nh_assert_same( '', NH_Forms::recipient_note( 'visit' ), 'visitors see nothing' );
		$GLOBALS['nh_test']['caps']['edit_posts'] = true;
		nh_assert_contains( 'nh-admin-note', NH_Forms::recipient_note( 'visit' ) );
		update_option( 'nh_settings', [ 'visit_recipient' => 'office@example.test' ] );
		nh_assert_same( '', NH_Forms::recipient_note( 'visit' ) );
	},
];
