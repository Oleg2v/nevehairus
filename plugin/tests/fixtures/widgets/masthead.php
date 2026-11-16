<?php
/**
 * Harness fixture: widget «masthead» (SPEC §4.18, §11.2–11.4).
 *
 * Variants: settings overrides (merged into the control defaults), NH settings overrides
 * (`options`), the compare target in the built prototype and the expected differences.
 * Texts in the non-compared variants are test data only.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

return [
	'variants' => [

		// What an editor sees after dropping the widget: the root is a <div> (D3: Elementor Pro
		// wraps the header template in its own landmark). Everything else as index.html.
		'default'   => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'header.nh-header', 'dialog#nh-menu' ],
			],
			'expect_diff' => [
				'root tag of .nh-header: div instead of header (D3)',
			],
		],

		// The root switched to <header>: the same tree as the prototype's header and menu.
		'prototype' => [
			'settings'    => [ 'html_tag' => 'header' ],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'header.nh-header', 'dialog#nh-menu' ],
			],
			'expect_diff' => [],
		],

		// On a house page: «מסגרות המגורים» marks the current section (the stub's queried post is 10),
		// as in program-main-home.html.
		'house'     => [
			'settings'    => [
				'html_tag' => 'header',
				'nav'      => [
					1 => [ 'current_ids' => '10' ],
				],
			],
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'header.nh-header', 'dialog#nh-menu' ],
			],
			'expect_diff' => [],
		],

		// Plain-language twins in states 2 (both), 3 (hidden in plain mode) and 4 (plain only).
		'plain'     => [
			'settings' => [
				'wordmark_plain'         => 'נווה האירוס',
				'display_title'          => '',
				'display_title_plain'    => 'איך רואים את האתר',
				'display_note_plain'     => '-',
				'toggle_calm_plain'      => 'פחות תנועה וצבעים רכים',
				'menu_phone_label_plain' => 'להתקשר:',
				'nav'                    => [
					0 => [ 'label_plain' => 'מי אנחנו?' ],
					4 => [ 'label_plain' => 'לדבר איתנו' ],
				],
			],
		],

		// A logo from the media library replaces the wordmark in the logo link (alt from the media library).
		'logo'      => [
			'settings' => [
				'logo' => [
					'id'  => nh_test_media_id( 'v3-courtyard' ),
					'url' => '',
				],
			],
		],

		// A logo marked decorative: alt="" and the wordmark text names the link in the visually hidden span.
		'logo-decorative' => [
			'settings' => [
				'logo'            => [
					'id'  => nh_test_media_id( 'v3-courtyard' ),
					'url' => '',
				],
				'logo_decorative' => 'yes',
			],
		],

		// Parts with nothing to show are left out: no phone in the settings, no visit-button text,
		// no panel note, a link row without text and a link row without a URL.
		'sparse'    => [
			'settings' => [
				'cta_text'       => '',
				'cta_text_plain' => '',
				'display_note'   => '',
				'nav'            => [
					0 => [ 'label' => '' ],
					3 => [ 'link' => [ 'url' => '' ] ],
				],
			],
			'options'  => [
				'phone_display' => '',
				'phone_tel'     => '',
			],
		],

		// Own visit-button link instead of the visit URL from the settings.
		'cta-link'  => [
			'settings' => [
				'cta_link' => [ 'url' => '#visit' ],
			],
		],
	],
];
