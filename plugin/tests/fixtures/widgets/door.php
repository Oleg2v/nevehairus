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
				'form[action]: NH_Forms::action_url() (admin-post.php) instead of "#"',
				'form[data-nh-form="visit"]: new attribute (SPEC §4.12)',
				'input[type=hidden]: the hidden fields of NH_Forms::hidden_fields()',
				'field ids c-name, c-phone, c-who, c-prog, c-website, c-consent → door-name, door-phone, door-who, door-program, door-website, door-consent',
				'.nh-alert--success p: without the prototype note " (באב־הטיפוס הטופס אינו נשלח.)"',
				'privacy note ({contact} token, public e-mail empty in tests): the phone link (tel:, 08-9403047) instead of the e-mail link',
				'.v3-door__addr: no " · " and mailto link after the address (public e-mail empty in tests)',
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
				'running'      => 'בואו לראות',
			],
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#visit' ],
			],
			'expect_diff' => [
				'form[action]: NH_Forms::action_url() (admin-post.php) instead of "#"',
				'form[data-nh-form="visit"]: new attribute (SPEC §4.12)',
				'input[type=hidden]: the hidden fields of NH_Forms::hidden_fields()',
				'field ids v-name, v-phone, v-email, v-role, v-msg, v-msg-hint, v-website, v-consent → visit-name, visit-phone, visit-email, visit-who, visit-message, visit-message-hint, visit-website, visit-consent',
				'.nh-alert--success p: without the prototype note " (באב־הטיפוס הטופס אינו נשלח.)"',
				'privacy note ({contact} token, public e-mail empty in tests): the phone link (tel:, 08-9403047) instead of the e-mail link',
				'.v3-door__addr: no " · " and mailto link after the address (public e-mail empty in tests)',
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
