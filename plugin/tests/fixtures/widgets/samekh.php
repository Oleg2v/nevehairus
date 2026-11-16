<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of widget `samekh` (SPEC §4.7, §11.2): variants = settings overrides on top of the control defaults.
 *
 * - default:   what an editor sees after dropping the widget (no photo yet: the calm sky in the letter, no
 *              rings, no caption; the figure is then hidden from assistive technology).
 * - prototype: the mandala; circles 3–4 link to the house page as in the prototype (step 4 sets the real
 *              URL); compared with index.html `section#support`.
 * - edge:      glint off, an invalid work radius, the media library's alt, a plain-only caption, a ring on
 *              the first circle, a description twin, an external link, an empty name.
 */
return [
	'variants' => [
		'default'   => [
			'settings' => [],
		],
		'prototype' => [
			'settings'    => [
				'photo'   => [
					'id'  => nh_test_media_id( 'v3-mandala' ),
					'url' => '',
				],
				'circles' => [
					2 => [ 'link' => [ 'url' => 'program-main-home.html' ] ],
					3 => [ 'link' => [ 'url' => 'program-main-home.html' ] ],
				],
			],
			'options'     => [],
			'get'         => [],
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
				'glint'         => '',
				'work_r'        => '0',
				'caption'       => '',
				'caption_plain' => 'מנדלה שהדיירים והדיירות יצרו.',
				'circles'       => [
					0 => [
						'name_plain' => '',
						'ring'       => '0.05',
					],
					1 => [
						'desc_plain' => 'הם באים לבקר.',
						'ring'       => '',
					],
					4 => [ 'link' => [ 'url' => 'https://example.com/' ] ],
					5 => [ 'name' => '' ],
				],
			],
			'options'  => [],
			'get'      => [],
		],
	],
];
