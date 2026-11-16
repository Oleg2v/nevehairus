<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of widget `years` (SPEC §4.8, §11.2): variants = settings overrides on top of the control defaults.
 *
 * - default:   what an editor sees after dropping the widget (no photos yet: no print, the ם shows the calm sky).
 * - prototype: the courtyard print and the sky in «שמיים»; compared with index.html `section#story`.
 * - edge:      another anchor, a full-only intro, a year without a number, the ם without a word or a photo,
 *              a mid print without a caption, a number with a symbol.
 */
return [
	'variants' => [
		'default'   => [
			'settings' => [],
		],
		'prototype' => [
			'settings'    => [
				'years' => [
					0 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-courtyard' ),
							'url' => '',
						],
					],
					1 => [
						'shamayim_photo' => [
							'id'  => nh_test_media_id( 'v3-sky-room' ),
							'url' => '',
						],
					],
				],
			],
			'options'     => [],
			'get'         => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#story' ],
			],
			'expect_diff' => [],
		],
		'edge'      => [
			'settings' => [
				'anchor'      => 'history',
				'intro'       => 'שבעים שנה, שלושה בתים.',
				'intro_plain' => '-',
				'years'       => [
					0 => [ 'num' => '' ],
					1 => [ 'shamayim_text' => '' ],
					2 => [
						'num'     => '100+',
						'photo'   => [
							'id'  => nh_test_media_id( 'v3-courtyard' ),
							'url' => '',
						],
						'print'   => 'mid',
						'caption' => '',
					],
				],
			],
			'options'  => [],
			'get'      => [],
		],
	],
];
