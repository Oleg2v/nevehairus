<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of widget `samekh` (SPEC §4.7, §11.2; research/07 §10.2 item 6): the mandala whole in a still ס, the
 * circles as a plain list (no rings, no glint, no links).
 *
 * - default:   no photo yet: the calm sky in the room, the figure hidden from assistive technology, no caption.
 * - prototype: the prototype's mandala; compared with index.html `section#support`.
 * - edge:      an empty alt override (the media library's alt), an out-of-range radius (the default is used), a
 *              plain caption only, twins in the list, a circle without a name (skipped).
 */
return [
	'variants' => [
		'default'   => [
			'settings' => [],
		],
		'prototype' => [
			'settings'    => [
				'photo' => [
					'id'  => nh_test_media_id( 'v3-mandala' ),
					'url' => '',
				],
			],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#support' ],
			],
			'expect_diff' => [],
		],
		'edge'      => [
			'settings' => [
				'photo'         => [
					'id'  => nh_test_media_id( 'v3-mandala' ),
					'url' => '',
				],
				'photo_alt'     => '',
				'work_r'        => '0',
				'caption'       => '',
				'caption_plain' => 'מנדלה שהדיירים והדיירות יצרו.',
				'circles'       => [
					0 => [ 'name_plain' => '' ],
					1 => [ 'desc_plain' => 'הם באים לבקר.' ],
					5 => [ 'name' => '' ],
				],
			],
		],
	],
];
