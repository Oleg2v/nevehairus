<?php
/**
 * Harness fixture: widget «colophon» (SPEC §4.19, §11.2–11.4).
 *
 * Variants: settings overrides (merged into the control defaults), NH settings overrides
 * (`options`), the compare target in the built prototype and the expected differences.
 * Texts in the non-compared variants are test data only. The only address-shaped string
 * is on the reserved domain example.com (SPEC §0.1).
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

return [
	'variants' => [

		// What an editor sees after dropping the widget, with the default settings: the root is a
		// <div> (D3: the default, for a footer template that keeps Pro's own <footer> wrapper; the
		// site's template sets that wrapper to div and this control to footer, as the `prototype`
		// variant), the public e-mail is not set, the link tokens are not connected yet.
		'default'   => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'footer.nh-footer' ],
			],
			'expect_diff' => [
				'root tag of .nh-footer: div instead of footer (D3)',
				'e-mail item of the contact list not rendered (setting email_public is empty by default)',
				'href of «מפרט כניסות נגישות (PDF)»: #entrances (token unresolved) instead of the PDF URL',
			],
		],

		// Root <footer>, a public e-mail and the entrances PDF set in the settings: the prototype's tree.
		'prototype' => [
			'settings'    => [ 'html_tag' => 'footer' ],
			'options'     => [
				'email_public'  => 'office@example.com',
				'entrances_pdf' => 'https://nh.test/wp-content/uploads/2017/08/entrances.pdf',
			],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'footer.nh-footer' ],
			],
			'expect_diff' => [
				'text of the e-mail item: office@example.com (fixture address) instead of the prototype address',
			],
		],

		// Plain-language twins in states 2, 3 and 4, and a plain running-head name.
		'plain'     => [
			'settings' => [
				'running_plain'     => 'סוף הדף',
				'about_plain'       => "בית בנס ציונה.\nגרים כאן אנשים עם מוגבלות שכלית התפתחותית.",
				'credits_plain'     => '-',
				'copyright_plain'   => 'כל הזכויות שמורות.',
				'bottom_note'       => '',
				'bottom_note_plain' => 'הנוסח בשפה פשוטה עוד בבדיקה.',
				'nav'               => [
					1 => [ 'label_plain' => 'איפה גרים' ],
				],
				'info'              => [
					2 => [ 'label_plain' => 'עוגיות' ],
				],
			],
		],

		// Logos from the media library: the site logo with an alt override; the agency logo marked
		// decorative (alt="", the name in a visually hidden span inside the link).
		'logo'      => [
			'settings' => [
				'logo'                => [
					'id'  => nh_test_media_id( 'v3-courtyard' ),
					'url' => '',
				],
				'logo_alt'            => 'מעון נווה האירוס',
				'dev_logo'            => [
					'id'  => nh_test_media_id( 'v3-owls' ),
					'url' => '',
				],
				'dev_logo_decorative' => 'yes',
			],
		],

		// Parts with nothing to show are left out: no navigation texts, no fax, address or Facebook
		// in the settings, no bottom note, a row without text, a credit without a link.
		'sparse'    => [
			'settings' => [
				'bottom_note' => '',
				'nav'         => [
					0 => [ 'label' => '' ],
					1 => [ 'label' => '' ],
					2 => [ 'label' => '' ],
					3 => [ 'label' => '' ],
					4 => [ 'label' => '' ],
				],
				'info'        => [
					2 => [ 'label' => '' ],
				],
				'dev_link'    => [ 'url' => '' ],
			],
			'options'  => [
				'fax'          => '',
				'facebook_url' => '',
				'address'      => '',
			],
		],
	],
];
