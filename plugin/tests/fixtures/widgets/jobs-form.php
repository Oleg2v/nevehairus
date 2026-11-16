<?php
/**
 * Fixture of widget `jobs-form` (SPEC §4.20, D6, §11.2). The prototype shows this form only as a
 * component sample (design-system.html `section#form`, in other chrome: an eyebrow and developer notes
 * instead of the v3 ruling line, H2 and lead), so only the form itself has a compare target.
 *
 * - default: the widget as dropped on the jobs page; its form is compared with design-system.html
 *            `section#form form.nh-form`. Expected differences: `data-nh-form="jobs"` (§6.4), role 1 of the
 *            ל spread (D6), the consent's `data-error-label` (⊕, §4.20) and the privacy notice, which comes
 *            from the settings (`privacy_jobs`: «…ולתקנו{contact}.» names the phone while the public e-mail is
 *            empty; the sample ends with a hidden «draft for the lawyer» note).
 * - role:    ?role= set to role 2 of the ל spread (the link NH_Settings::jobs_url() makes) → that
 *            option is `selected`.
 * - sent / invalid / off: the server states after a submission without JavaScript (PRG, D7).
 * - twins:   plain-language twins on the form texts; no "keep my details" box (structure only).
 *
 * The state variants address the instance the harness renders: element id = first 7 hex digits of
 * md5( "jobs-form/{variant}" ) (SPEC §11.3).
 *
 * @package nevehairus-site
 */

$instance = static function ( string $variant ): string {
	return substr( md5( 'jobs-form/' . $variant ), 0, 7 );
};

return [
	'variants' => [
		'default' => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'design-system.html',
				'selectors' => [ 'section#form form.nh-form' ],
			],
			'expect_diff' => [
				// + data-nh-form="jobs" (SPEC §6.4)
				'^[-+]form\.nh-form \[action\] \[aria-labelledby\] ',
				// role 1 follows the ל spread (D6)
				'^-\s+"רכז/ת דירה — דיור בקהילה, אוטיזם"$',
				'^\+\s+"רכז/ת דירה — דיור לצעירים על הרצף האוטיסטי"$',
				// + the consent's name in the error summary (⊕, SPEC §4.20)
				'^[-+]\s+input (\[data-error-label=[^\]]+\] )?\[id\] \[name=consent\] \[required\] \[type=checkbox\]$',
				// the privacy notice comes from the settings: the sample's hidden draft note is not there, and
				// {contact} names the phone while the public e-mail is empty in the tests
				'^[-+]\s+"מסירת הפרטים אינה חובה לפי חוק, [^"]*ולתקנו[.:] "$',
				'^-\s+span\.nh-sr-only$',
				'^-\s+"\(נוסח טיוטה לבדיקת עורך דין\)"$',
				'^\+\s+a \[href=URL\]$',
				'^\+\s+span\.nh-ltr$',
				'^\+\s+"08-9403047"$',
				'^\+\s+"\."$',
			],
		],
		'role'    => [
			'settings' => [],
			'get'      => [ 'role' => 'עובד/ת סוציאלי/ת' ],
		],
		'sent'    => [
			'settings' => [ 'anchor' => 'form-sent' ],
			'get'      => [
				'nh_form'   => $instance( 'sent' ),
				'nh_status' => 'sent',
			],
		],
		'invalid' => [
			'settings' => [ 'anchor' => 'form-invalid' ],
			'get'      => [
				'nh_form'   => $instance( 'invalid' ),
				'nh_status' => 'invalid',
				'nh_err'    => 'role:required,email:email,cv:file_type,consent:consent',
			],
		],
		'off'     => [
			'settings' => [ 'anchor' => 'form-off' ],
			'get'      => [
				'nh_form'   => $instance( 'off' ),
				'nh_status' => 'off',
			],
		],
		'twins'   => [
			'settings' => [
				'anchor'              => 'form-twins',
				'title_plain'         => 'רוצים לעבוד אצלנו?',
				'success_title_plain' => 'קיבלנו את הפרטים.',
				'required_note_plain' => 'צריך למלא את השדות עם כוכבית <span aria-hidden="true">*</span>.',
				'label_role_plain'    => 'איזו עבודה?',
				'label_cv_plain'      => 'קובץ עם קורות החיים',
				'hint_cv_plain'       => '-',
				'consent_plain'       => 'אני מסכים או מסכימה שתקראו את הפרטים שלי. <a href="#privacy">מה עושים עם הפרטים?</a>',
				'keep'                => '',
				'submit_plain'        => 'לשלוח',
			],
		],
	],
];
