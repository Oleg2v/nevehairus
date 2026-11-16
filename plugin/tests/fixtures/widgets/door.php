<?php
/**
 * Fixture of widget `door` (SPEC §4.12, §11.2).
 *
 * - default: the home-page door, as dropped on a page → index.html `section#door`.
 * - page:    the house-page door (the right-hand column of §4.12) → program-main-home.html `section#visit`.
 * - sent / invalid: the server states after a submission without JavaScript (PRG, D7): the success
 *            alert shown; the error summary with linked field errors (structure only).
 * - email:   a public e-mail in the settings → the e-mail link after the address (structure only).
 * - twins:   plain-language twins on the form texts, a twin hidden in plain mode (structure only).
 * - bare:    no anchor (ids nh-{element id}-…), one optional field left alone in its row, no form title,
 *            no lead, no contact block (structure only).
 *
 * The state variants address the instance the harness renders: element id = first 7 hex digits of
 * md5( "door/{variant}" ) (SPEC §11.3).
 *
 * @package nevehairus-site
 */

$instance = static function ( string $variant ): string {
	return substr( md5( 'door/' . $variant ), 0, 7 );
};

return [
	'variants' => [
		'default' => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#door' ],
			],
			'expect_diff' => [
				'^-\s+"נחזור אליכם[^"]* \(באב־הטיפוס הטופס אינו נשלח\.\)"$',   // the success text without the prototype's note
				'^\+\s+"נחזור אליכם[^"(]*"$',
				'^[-+]\s+form\.nh-form \[action\] ',                            // + data-nh-form="visit" (SPEC §4.12)
				'^-\s+"[^"\s]+@[^"\s]+"$',                                        // the public e-mail is empty in the tests:
				'^\+\s+"08-9403047"$',                                           // the privacy note names the phone instead
				'^-\s+"ת״ד 28, נס ציונה · "$',                                     // and the address has no e-mail link after it
				'^\+\s+"ת״ד 28, נס ציונה"$',
				'^-\s+a \[href=URL\]$',
				'^-\s+span\.nh-ltr$',
			],
		],
		'page'    => [
			'settings'    => [
				'title'        => 'בואו לראות בעיניים.',
				'lead'         => 'השאירו פרטים ונחזור אליכם לתיאום מפגש היכרות.',
				'success_text' => 'נחזור אליכם בהקדם לתיאום הביקור.',
				'show_email'   => 'yes',
				'show_who'     => 'yes',
				'show_program' => '',
				'show_message' => 'yes',
				'anchor'       => 'visit',
			],
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#visit' ],
			],
			'expect_diff' => [
				'^-\s+"נחזור אליכם[^"]* \(באב־הטיפוס הטופס אינו נשלח\.\)"$',   // the success text without the prototype's note
				'^\+\s+"נחזור אליכם[^"(]*"$',
				'^[-+]\s+form\.nh-form \[action\] ',                            // + data-nh-form="visit" (SPEC §4.12)
				'^-\s+"[^"\s]+@[^"\s]+"$',                                        // the public e-mail is empty in the tests:
				'^\+\s+"08-9403047"$',                                           // the privacy note names the phone instead
				'^-\s+"ת״ד 28, נס ציונה · "$',                                     // and the address has no e-mail link after it
				'^\+\s+"ת״ד 28, נס ציונה"$',
				'^-\s+a \[href=URL\]$',
				'^-\s+span\.nh-ltr$',
			],
		],
		'sent'    => [
			'settings' => [ 'anchor' => 'door-sent' ],
			'get'      => [
				'nh_form'   => $instance( 'sent' ),
				'nh_status' => 'sent',
			],
		],
		'invalid' => [
			'settings' => [
				'anchor'       => 'door-invalid',
				'show_email'   => 'yes',
				'show_message' => 'yes',
			],
			'get'      => [
				'nh_form'   => $instance( 'invalid' ),
				'nh_status' => 'invalid',
				'nh_err'    => 'name:required,phone:tel,email:email,consent:consent',
			],
		],
		'email'   => [
			'settings' => [ 'anchor' => 'door-email' ],
			'options'  => [ 'email_public' => 'office@example.test' ],
		],
		'twins'   => [
			'settings' => [
				'anchor'              => 'door-twins',
				'form_title_plain'    => 'לקבוע ביקור',
				'success_title_plain' => 'קיבלנו את הבקשה.',
				'success_text_plain'  => '-',
				'label_name_plain'    => 'השם שלך',
				'label_phone_plain'   => 'מספר הטלפון שלך',
				'label_who_plain'     => 'מי אתם?',
				'label_program_plain' => 'איזה בית?',
				'show_message'        => 'yes',
				'label_message_plain' => 'מה עוד חשוב לדעת?',
				'hint_message_plain'  => '-',
				'submit_plain'        => 'לשלוח',
				'contact_label_plain' => 'אפשר להתקשר:',
			],
		],
		'bare'    => [
			'settings' => [
				'anchor'       => '',
				'lead'         => '',
				'lead_plain'   => '',
				'form_title'   => '',
				'show_who'     => '',
				'show_contact' => '',
			],
		],
	],
];
