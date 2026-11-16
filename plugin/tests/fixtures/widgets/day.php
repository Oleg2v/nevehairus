<?php
/**
 * Fixture of widget `day` (SPEC §4.5, §11.2).
 *
 * - default:   the widget as dropped on a page: no photos yet, so no prints (structure only).
 * - prototype: the prototype's four photos → index.html `section#day`.
 * - states:    a skipped item (no verb), a print without a photo, twin states of lead, text and
 *              caption, a clay tessera, an editor's alt override (structure only).
 *
 * @package nevehairus-site
 */

return [
	'variants' => [
		'default'   => [
			'settings' => [],
		],
		'prototype' => [
			'settings'    => [
				'moments' => [
					0 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-crafts' ),
							'url' => '',
						],
					],
					1 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-riding' ),
							'url' => '',
						],
					],
					2 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-orchard' ),
							'url' => '',
						],
					],
					3 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-villa' ),
							'url' => '',
						],
					],
				],
			],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#day' ],
			],
			'expect_diff' => [],
		],
		'states'    => [
			'settings' => [
				'anchor'     => 'day-states',
				'title_dot'  => 'clay',
				'lead_plain' => "ככה עובר יום אצלנו.\nכל אחד בקצב שלו.",
				'moments'    => [
					0 => [
						'photo'         => [
							'id'  => nh_test_media_id( 'v3-crafts' ),
							'url' => '',
						],
						'photo_alt'     => 'עבודות פסיפס על שולחן',
						'caption_plain' => 'עבודות שעשו הדיירים.',
						'text_plain'    => '-',
					],
					1 => [
						'verb' => '',
					],
					2 => [
						'photo'       => [
							'id'  => nh_test_media_id( 'v3-orchard' ),
							'url' => '',
						],
						'photo_sizes' => '',
						'caption'     => '',
					],
				],
			],
		],
	],
];
