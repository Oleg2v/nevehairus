<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of widget `kaf` (SPEC §4.6, §11.2; research/07 §10.2 item 5): variants = settings overrides on top of
 * the control defaults.
 *
 * - default:   what an editor sees after dropping the widget (no photo yet: the palm shows the calm sky and the
 *              caption, which describes the photo, is left out; no button: its link is empty by default).
 * - prototype: the work in the palm with the prototype's photo; compared with index.html `section#made`.
 * - button:    a link to another page shows the ink button; the same with a link back to this spread shows
 *              nothing (editors see a note) — see `self-link`.
 * - self-link: the button's link is #made (this spread): no button.
 * - edge:      an empty anchor, a plain twin of the caption, an external button link, an alt override on a
 *              photo that is not decorative.
 */
return [
	'variants' => [
		'default'   => [
			'settings' => [],
		],
		'prototype' => [
			'settings'    => [
				'photo' => [
					'id'  => nh_test_media_id( 'v3-pot-b' ),
					'url' => '',
				],
			],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#made' ],
			],
			'expect_diff' => [],
		],
		'button'    => [
			'settings' => [
				'photo'       => [
					'id'  => nh_test_media_id( 'v3-pot-b' ),
					'url' => '',
				],
				'button_link' => [ 'url' => 'https://nh.test/sample-page/' ],
			],
		],
		'self-link' => [
			'settings' => [
				'button_link' => [ 'url' => '#made' ],
			],
		],
		'edge'      => [
			'settings' => [
				'anchor'           => '',
				'caption_plain'    => 'בכף היד יש כד.',
				'button_link'      => [ 'url' => 'https://example.com/gallery/' ],
				'photo'            => [
					'id'  => nh_test_media_id( 'v3-owls' ),
					'url' => '',
				],
				'photo_decorative' => '',
				'photo_alt'        => 'ינשופים בעבודת יד',
				'photo_pos'        => 'center',
			],
		],
	],
];
