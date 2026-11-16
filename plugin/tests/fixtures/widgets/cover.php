<?php
/**
 * Fixture of widget `cover` (SPEC §4.1, §11.2).
 *
 * - default:   as dropped on a page — no photo yet, so the room shows the calm sky.
 * - prototype: the prototype's photo (v3-walk); compared with index.html `section#cover`: no
 *              differences besides the §11.4 normalisation.
 * - states:    a one-line title with an empty plain twin (the plain H1 repeats the line), the lead
 *              hidden in plain mode (state 3), the button text without a twin (state 1), no anchor
 *              (ids nh-{element id}-…), a decorative photo, the phone line's words hidden in plain
 *              language (state 3).
 * - no-button: an empty button link (no button), an alt override, a plain-only lead (state 4), the
 *              phone line switched off.
 * - no-phone:  no phone in the settings: no phone line (the switch is on).
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

return [
	'variants' => [
		'default'   => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#cover' ],
			],
			// Without a photo the room holds the sky field instead of the picture.
			'expect_diff' => [
				'^-\s+picture\.v3-room__photo$',
				'^-\s+(source|img) ',
				'^\+\s+span\.v3-room__field\.v3-room__field--sky\.v3-room__photo \[aria-hidden=true\]$',
			],
		],
		'prototype' => [
			'settings'    => [
				'photo' => [
					'id'  => nh_test_media_id( 'v3-walk' ),
					'url' => '',
				],
			],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#cover' ],
			],
			'expect_diff' => [],
		],
		'states'    => [
			'settings' => [
				'anchor'           => '',
				'title_1'          => 'בית לחיים.',
				'title_1_dot'      => 'citrus',
				'title_2'          => '',
				'title_plain'      => '',
				'lead_plain'       => '-',
				'cta_text_plain'   => '',
				'photo'            => [
					'id'  => nh_test_media_id( 'v3-sky-room' ),
					'url' => '',
				],
				'photo_decorative' => 'yes',
				'photo_pos'        => '55% 45%',
				'call_label_plain' => '-',
			],
		],
		'no-button' => [
			'settings' => [
				'cta_link'   => [ 'url' => '' ],
				'lead'       => '',
				'title_plain' => '-',
				'photo'      => [
					'id'  => nh_test_media_id( 'v3-walk' ),
					'url' => '',
				],
				'photo_alt'  => 'שביל בין עצים',
				'show_call'  => '',
			],
		],
		'no-phone'  => [
			'settings' => [],
			'options'  => [
				'phone_display' => '',
				'phone_tel'     => '',
			],
		],
	],
];
