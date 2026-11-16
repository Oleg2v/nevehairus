<?php
/**
 * Harness fixture: widget «masthead» (SPEC §4.18, §11.2–11.4; research/07 §5, §6, §10.1 items 1–2).
 *
 * Variants: settings overrides (merged into the control defaults), NH settings overrides
 * (`options`), the queried post, the compare target in the built prototype and the expected differences.
 * Texts in the non-compared variants are test data only.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

$nh_emblem = [
	'id'  => nh_test_media_id( 'logo-emblem' ),
	'url' => '',
];

return [
	'variants' => [

		// What an editor sees after dropping the widget: the root is a <div> (D3: the default, for a header
		// template that keeps Pro's own <header> wrapper; the site's template sets that wrapper to div and this
		// control to header, as the `prototype` variant) and no emblem is chosen yet (only the name shows).
		'default'         => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'header.nh-header', 'dialog#nh-menu' ],
			],
			'expect_diff' => [
				'^[-+]div\.nh-header\.v3-mast$|^[-+]header\.nh-header\.v3-mast$',   // the root tag (D3)
				'^-\s+img\.nh-logo__mark ',                                        // no emblem chosen yet
			],
		],

		// The root switched to <header> and the emblem chosen: the same tree as the prototype's header and menu.
		'prototype'       => [
			'settings'    => [
				'html_tag' => 'header',
				'logo'     => $nh_emblem,
			],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'header.nh-header', 'dialog#nh-menu' ],
			],
			'expect_diff' => [],
		],

		// On a house page (the queried post is 11): «הבתים שלנו» marks the section the page belongs to
		// (aria-current="true" from current_ids), as in program-main-home.html.
		'house'           => [
			'settings'    => [
				'html_tag' => 'header',
				'logo'     => $nh_emblem,
				'nav'      => [
					0 => [ 'current_ids' => '11' ],
				],
			],
			'queried'     => 11,
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'header.nh-header', 'dialog#nh-menu' ],
			],
			'expect_diff' => [],
		],

		// A link to the page itself: aria-current="page" (the stub's queried post 12 is the jobs page).
		'page'            => [
			'settings' => [
				'nav' => [
					3 => [ 'link' => [ 'url' => 'https://nh.test/jobs/' ] ],
				],
			],
			'queried'  => 12,
		],

		// Plain-language twins in states 2 (both), 3 (hidden in plain mode) and 4 (plain only); the name in two
		// modes (each split at its last space).
		'plain'           => [
			'settings' => [
				'name_plain'             => 'נווה האירוס',
				'toggle_calm_plain'      => 'פחות תנועה וצבעים רכים',
				'menu_phone_label_plain' => 'להתקשר:',
				'nav'                    => [
					0 => [ 'label_plain' => 'הבתים' ],
					4 => [ 'label_plain' => 'לדבר איתנו' ],
				],
			],
		],

		// Six links: only the first five are shown (editors see a note).
		'six'             => [
			'settings' => [
				'nav' => [
					5 => [
						'label' => 'עוד',
						'link'  => [ 'url' => 'https://nh.test/sitemap/' ],
					],
				],
			],
		],

		// Parts with nothing to show are left out: no phone in the settings, no visit-button text, no name
		// (the link keeps its screen-reader text), a link row without text and a link row without a URL; the
		// state words, the menu's word and «סגירה» emptied (the switches lose their words, the buttons fall back).
		'sparse'          => [
			'settings' => [
				'cta_text'       => '',
				'cta_text_plain' => '',
				'name'           => '',
				'menu_label'     => '',
				'menu_close'     => '',
				'state_on'       => '',
				'state_off'      => '',
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

		// Own visit-button link instead of the visit URL from the settings; one-word name; other state words.
		'cta-link'        => [
			'settings' => [
				'cta_link'  => [ 'url' => '#visit' ],
				'name'      => 'נווה',
				'state_on'  => 'כן',
				'state_off' => 'לא',
			],
		],
	],
];
