<?php
/**
 * Fixture of widget `jobs-form` (SPEC §4.20, D6, §11.2). The prototype shows this form only as a
 * component sample (design-system.html `section#form`, other chrome), so there is no compare target:
 * the structure is checked by run.php.
 *
 * - default: the widget as dropped on the jobs page.
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
			'settings' => [],
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
