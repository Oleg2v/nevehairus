<?php
/**
 * Core tests 6 — NH_Settings and the settings page (SPEC §11.5 item 6, §7): sanitize() drops invalid
 * e-mails, cleans URLs, clamps numbers; the getters; the one-time migration to 0.2.0 (research/07 §12.3);
 * a smoke render of the page, the readiness list, the notices and the test e-mail.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

return [
	'sanitize(): e-mails, URLs, numbers, booleans, markup, unknown keys' => static function () {
		$out = NH_Settings::sanitize(
			[
				'visit_recipient'    => 'not an e-mail',
				'jobs_recipient'     => ' jobs@example.test ',
				'email_public'       => 'office@example.test',
				'facebook_url'       => 'javascript:alert(1)',
				'visit_url'          => 'https://nh.test/visit/?a=1&b=2',
				'consent_version'    => '0',
				'jobs_page'          => '-12',
				'phone_tel'          => '+972 (8) 940-3047',
				'privacy_visit'      => "טקסט <script>x</script><a href=\"#privacy\" onclick=\"y\">קישור</a>\r\nשורה",
				'cookie_title'       => "  <b>עוגיות</b>\n באתר ",
				'unknown_key'        => 'dropped',
				'show_bar'           => '1',
			]
		);
		nh_assert_same( '', $out['visit_recipient'], 'invalid e-mail dropped' );
		nh_assert_same( 'jobs@example.test', $out['jobs_recipient'] );
		nh_assert_same( 'office@example.test', $out['email_public'] );
		nh_assert_same( '', $out['facebook_url'], 'javascript: URL removed' );
		nh_assert_same( 'https://nh.test/visit/?a=1&b=2', $out['visit_url'] );
		nh_assert_same( 1, $out['consent_version'], 'clamped to ≥ 1' );
		nh_assert_same( 999, NH_Settings::sanitize( [ 'consent_version' => '5000' ] )['consent_version'], 'clamped to ≤ 999' );
		nh_assert_same( 12, $out['jobs_page'], 'absint' );
		nh_assert_same( '+97289403047', $out['phone_tel'] );
		nh_assert_same( "טקסט x<a href=\"#privacy\">קישור</a>\nשורה", $out['privacy_visit'], 'inline kses, newlines kept' );
		nh_assert_same( 'עוגיות באתר', $out['cookie_title'], 'text field' );
		nh_assert_same( 0, $out['webp_subsizes'], 'a missing checkbox is off' );
		nh_assert_same( 1, NH_Settings::sanitize( [ 'webp_subsizes' => '1' ] )['webp_subsizes'] );
		nh_assert( ! array_key_exists( 'unknown_key', $out ), 'unknown keys dropped' );
		nh_assert( ! array_key_exists( 'show_bar', $out ), 'a key removed in 0.2.0 is unknown now' );
		nh_assert_same( array_keys( NH_Settings::defaults() ), array_keys( $out ), 'every key, in order' );
		nh_assert_same( NH_Settings::defaults()['cookie_text'], $out['cookie_text'], 'missing text keys keep their default' );
		nh_assert_same( 1, count( array_filter( $GLOBALS['nh_test']['notices'], static fn( $n ) => 'nh_visit_recipient' === $n[1] ) ), 'a settings error for the invalid address' );
	},

	'defaults: SPEC §7, recipients and the public e-mail empty; no address anywhere' => static function () {
		$d = NH_Settings::defaults();
		nh_assert_same( array_keys( NH_Settings::types() ), array_keys( $d ) );
		foreach ( [ 'visit_recipient', 'jobs_recipient', 'email_public' ] as $k ) {
			nh_assert_same( '', $d[ $k ], $k );
		}
		foreach ( $d as $k => $v ) {
			if ( is_string( $v ) && preg_match_all( '/[\w.+-]+@[\w-]+(\.[\w-]+)+/', $v, $m ) ) {
				foreach ( $m[0] as $address ) {
					nh_assert( (bool) preg_match( '/@example\.(com|test)$/', $address ), "$k contains $address" );
				}
			}
		}
		nh_assert_same( 'צריך למלא את השדה הזה.', $d['msg_required'], 'the prototype MSG verbatim' );
		nh_assert_contains( '{contact}', $d['privacy_visit'] );
		// v3.2 (research/07 §9, §12.3)
		foreach ( NH_Settings::REMOVED_0_2_0 as $gone ) {
			nh_assert( ! array_key_exists( $gone, $d ), "$gone removed" );
		}
		nh_assert_same( 'הגדרות עוגיות', $d['cookie_title'] );
		nh_assert_same( 'סגירה', $d['cookie_close'] );
		nh_assert_same( '', $d['cookie_stats'], 'no statistics: the row is not printed' );
		nh_assert_same( 0, $d['webp_subsizes'], 'uPress optimises images itself' );
		nh_assert_not_contains( 'סטטיסטיקה', $d['cookie_text'] );
		nh_assert_same( "האתר שומר רק מה שחייבים.\nסרטון מ־YouTube נפתח רק אם מסכימים.\nכאן אפשר לשנות את הבחירה.", $d['cookie_text_plain'] );
	},

	'migrate(): below 0.2.0 once — removed keys go, untouched old defaults take the new ones, edits stay' => static function () {
		$old = NH_Settings::OLD_DEFAULTS_0_1_0;
		update_option(
			'nh_settings',
			[
				'phone_display'     => '08-1111111',                         // a person's value: kept
				'show_bar'          => 1,
				'bar_label'         => 'תיאום ביקור מהיר',
				'bar_cta'           => 'תיאום ביקור',
				'bar_cta_plain'     => 'לקבוע ביקור',
				'bar_call_label'    => 'התקשרו:',
				'skip_label'        => 'דילוג לתוכן הראשי',
				'cookie_more'       => 'מה זה אומר?',
				'cookie_all'        => 'אישור הכול',
				'cookie_necessary_only' => 'רק הכרחיות',
				'cookie_settings'   => 'הגדרות',
				'cookie_title'      => $old['cookie_title'],
				'cookie_stats'      => $old['cookie_stats'],
				'cookie_text'       => $old['cookie_text'],
				'cookie_text_plain' => str_replace( "\n", "\r\n", $old['cookie_text_plain'] ), // saved from a textarea
				'cookie_necessary'  => 'נוסח של עורך הדין',                  // edited: kept
				'webp_subsizes'     => 1,
				'consent_version'   => 3,
			]
		);
		$GLOBALS['nh_test']['elementor_cache_cleared'] = 0;
		NH_Settings::migrate();
		$o = get_option( 'nh_settings' );
		foreach ( NH_Settings::REMOVED_0_2_0 as $gone ) {
			nh_assert( ! array_key_exists( $gone, $o ), "$gone removed" );
		}
		$d = NH_Settings::defaults();
		nh_assert_same( '08-1111111', $o['phone_display'] );
		nh_assert_same( 'הגדרות עוגיות', $o['cookie_title'] );
		nh_assert_same( '', $o['cookie_stats'] );
		nh_assert_same( $d['cookie_text'], $o['cookie_text'] );
		nh_assert_same( $d['cookie_text_plain'], $o['cookie_text_plain'], 'CRLF from the form compared as LF' );
		nh_assert_same( 'נוסח של עורך הדין', $o['cookie_necessary'] );
		nh_assert_same( 0, $o['webp_subsizes'] );
		nh_assert_same( 3, $o['consent_version'] );
		nh_assert_same( NH_VERSION, get_option( 'nh_version' ) );
		nh_assert_same( 1, $GLOBALS['nh_test']['elementor_cache_cleared'], 'a version change clears Elementor\'s caches' );
		// once: a person's later choices survive the next requests
		update_option( 'nh_settings', array_merge( $o, [ 'webp_subsizes' => 1, 'cookie_title' => 'עוגיות באתר' ] ) );
		NH_Settings::migrate();
		nh_assert_same( 1, get_option( 'nh_settings' )['webp_subsizes'] );
		nh_assert_same( 'עוגיות באתר', get_option( 'nh_settings' )['cookie_title'] );
		nh_assert_same( 1, $GLOBALS['nh_test']['elementor_cache_cleared'], 'nothing to do: no second clearing' );
	},

	'migrate(): a fresh site and a newer stored version' => static function () {
		NH_Settings::migrate();
		nh_assert_same( false, get_option( 'nh_settings', false ), 'no option is created' );
		nh_assert_same( NH_VERSION, get_option( 'nh_version' ) );
		update_option( 'nh_settings', [ 'webp_subsizes' => 1 ] );
		update_option( 'nh_version', '9.0.0' );
		NH_Settings::migrate();
		nh_assert_same( [ 'webp_subsizes' => 1 ], get_option( 'nh_settings' ), 'a newer version is left alone' );
		nh_assert_same( '9.0.0', get_option( 'nh_version' ) );
		nh_assert_same( 5, has_action( 'init', [ 'NH_Settings', 'migrate' ] ), 'init @5, before register_setting() adds sanitize()' );
		nh_assert_same( 10, has_action( 'init', [ 'NH_Settings', 'register' ] ) );
	},

	'get(), phone(), email_public(), recipient(), visit_url(), jobs_url(), page_url(), text()' => static function () {
		nh_assert_same( '08-9403047', NH_Settings::get( 'phone_display' ) );
		nh_assert_same( null, NH_Settings::get( 'nope' ) );
		nh_assert_same( [ 'display' => '08-9403047', 'tel' => '+97289403047' ], NH_Settings::phone() );
		nh_assert_same( '', NH_Settings::email_public() );
		nh_assert_same( '', NH_Settings::recipient( 'visit' ) );
		nh_assert_same( '', NH_Settings::recipient( 'admin' ), 'only the two forms' );
		nh_assert_same( 'https://nh.test/#door', NH_Settings::visit_url() );
		nh_assert_same( '', NH_Settings::jobs_url( 'טבח/ית' ), 'no jobs page' );
		update_option(
			'nh_settings',
			[
				'phone_display'   => '08-1234567',
				'phone_tel'       => '',
				'visit_recipient' => 'visits@example.test',
				'jobs_page'       => 12,
				'entrances_pdf'   => 'https://nh.test/wp-content/uploads/entrances.pdf',
				'msg_off'         => 'סגור. {phone} <b>היום</b> <script>x</script>',
			]
		);
		nh_assert_same( [ 'display' => '08-1234567', 'tel' => '081234567' ], NH_Settings::phone(), 'tel from the display when empty' );
		nh_assert_same( 'visits@example.test', NH_Settings::recipient( 'visit' ) );
		nh_assert_same( 'https://nh.test/jobs/', NH_Settings::jobs_url() );
		nh_assert_same( 'https://nh.test/jobs/?role=' . rawurlencode( 'טבח/ית' ) . '#form', NH_Settings::jobs_url( 'טבח/ית' ) );
		nh_assert_same( 'https://nh.test/wp-content/uploads/entrances.pdf', NH_Settings::page_url( 'entrances' ) );
		nh_assert_same( '', NH_Settings::page_url( 'accessibility' ) );
		nh_assert_same( 'סגור. <a href="tel:081234567"><span class="nh-ltr">08-1234567</span></a> <b>היום</b> x', NH_Settings::text( 'msg_off' ) );
	},

	'the settings page: menu, fields, readiness list, render (admins only)' => static function () {
		$GLOBALS['nh_test']['is_admin'] = true;
		NH_Settings_Page::menu();
		NH_Settings_Page::register_fields();
		nh_assert( isset( $GLOBALS['nh_test']['pages']['nevehairus'] ), 'Settings → נווה האירוס' );
		$count = 0;
		foreach ( $GLOBALS['nh_test']['sections']['nevehairus'] as $section ) {
			$count += count( $section[1] );
		}
		nh_assert_same( count( NH_Settings::types() ), $count, 'one field per setting' );
		nh_assert_same( '', nh_test_capture( [ 'NH_Settings_Page', 'render' ] ), 'nothing without manage_options' );
		$GLOBALS['nh_test']['caps']['manage_options'] = true;
		$html = nh_test_capture( [ 'NH_Settings_Page', 'render' ] );
		nh_assert_contains( 'name="nh_settings[visit_recipient]"', $html );
		nh_assert_contains( 'name="nh_settings[webp_subsizes]" value="0">', $html, 'hidden 0 before the checkbox' );
		nh_assert_contains( 'name="nh_settings[cookie_close]"', $html );
		nh_assert_not_contains( 'nh_settings[show_bar]', $html, 'removed in 0.2.0' );
		nh_assert_contains( 'value="nh_test_mail"', $html );
		nh_assert_contains( 'name="option_page" value="nh_settings"', $html );
		$rows = NH_Settings_Page::readiness();
		$by   = [];
		foreach ( $rows as $r ) {
			$by[ $r['label'] ] = $r['ok'];
		}
		nh_assert_same( false, $by['נמען לבקשות ביקור'] );
		nh_assert_same( true, $by['Elementor'] );
		nh_assert_same( true, $by['ערכת עיצוב Hello Elementor עם ערכת בת'] );
		nh_assert_same( [ 'הגדרות' ], array_map( 'wp_strip_all_tags', NH_Settings_Page::action_links( [] ) ) );
	},

	'readiness(): discovers the widget files itself (Elementor builds no widget list on that screen)' => static function () {
		$widgets = new ReflectionProperty( 'NH_Plugin', 'widgets' );
		$widgets->setAccessible( true );
		$before = $widgets->getValue();
		nh_assert( is_array( $before ) && count( $before ) > 0, 'the harness registered the widgets' );
		$widgets->setValue( null, null ); // as on options-general.php?page=nevehairus: nothing discovered yet
		try {
			NH_Settings_Page::readiness();
			nh_assert_same( $before, $widgets->getValue(), 'readiness() ran the discovery, so NH_Plugin::skipped() is filled' );
		} finally {
			$widgets->setValue( null, $before );
		}
	},

	'notices: missing recipients on the four screens, for admins only' => static function () {
		$GLOBALS['nh_test']['caps']['manage_options'] = true;
		foreach ( [ 'dashboard', 'plugins', 'edit-page', 'settings_page_nevehairus' ] as $screen ) {
			$GLOBALS['nh_test']['screen'] = $screen;
			nh_assert_contains( 'טפסי האתר לא שולחים דוא״ל', nh_test_capture( [ 'NH_Settings_Page', 'notices' ] ), $screen );
		}
		$GLOBALS['nh_test']['screen'] = 'edit-post';
		nh_assert_same( '', nh_test_capture( [ 'NH_Settings_Page', 'notices' ] ) );
		$GLOBALS['nh_test']['screen'] = 'dashboard';
		update_option( 'nh_settings', [ 'visit_recipient' => 'a@example.test', 'jobs_recipient' => 'b@example.test' ] );
		nh_assert_same( '', nh_test_capture( [ 'NH_Settings_Page', 'notices' ] ), 'both set' );
		$GLOBALS['nh_test']['caps'] = [];
		update_option( 'nh_settings', [] );
		nh_assert_same( '', nh_test_capture( [ 'NH_Settings_Page', 'notices' ] ), 'not an admin' );
	},

	'the test e-mail: nonce + manage_options, one message per recipient, the result (never the address) in the URL' => static function () {
		update_option( 'nh_settings', [ 'visit_recipient' => 'a@example.test', 'jobs_recipient' => 'a@example.test' ] );
		$GLOBALS['nh_test']['caps']['manage_options'] = true;
		$_REQUEST = [ '_wpnonce' => wp_create_nonce( 'nh_test_mail' ) ];
		try {
			NH_Settings_Page::test_mail();
		} catch ( NH_Test_Halt $e ) {
			nh_assert_same( 'nh_forms_halt', $e->getMessage() );
		}
		nh_assert_same( 1, count( $GLOBALS['nh_test']['mails'] ), 'one message per distinct recipient' );
		nh_assert_same( 'a@example.test', $GLOBALS['nh_test']['mails'][0]['to'] );
		$to = end( $GLOBALS['nh_test']['redirects'] )[0];
		nh_assert_same( 'https://nh.test/wp-admin/options-general.php?page=nevehairus&nh_test=ok', $to );
		nh_assert_not_contains( 'example.test', $to );
		$GLOBALS['nh_test']['mail_result'] = false;
		try {
			NH_Settings_Page::test_mail();
		} catch ( NH_Test_Halt $e ) {
			// redirect
		}
		nh_assert_contains( 'nh_test=fail', end( $GLOBALS['nh_test']['redirects'] )[0] );
		$_REQUEST = [ '_wpnonce' => 'forged' ];
		$refused  = false;
		try {
			NH_Settings_Page::test_mail();
		} catch ( NH_Test_Halt $e ) {
			$refused = 0 === strpos( $e->getMessage(), 'check_admin_referer' );
		}
		nh_assert( $refused, 'a bad nonce is refused' );
		$GLOBALS['nh_test']['caps'] = [];
		$refused                    = false;
		try {
			NH_Settings_Page::test_mail();
		} catch ( NH_Test_Halt $e ) {
			$refused = 0 === strpos( $e->getMessage(), 'wp_die' );
		}
		nh_assert( $refused, 'not an admin: refused' );
	},
];
