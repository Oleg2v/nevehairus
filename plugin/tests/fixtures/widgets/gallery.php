<?php
/**
 * Fixture of widget `gallery` (SPEC §4.14, §11.2).
 *
 * - default:   the widget as dropped on a page: no images yet, so no list and no lightbox
 *              (structure only).
 * - prototype: the prototype's six photos → program-main-home.html `section#inside`.
 *              Expected differences (§4.14): the data-full / data-srcset URLs (media library URLs).
 * - states:    seven images (the 7th is sized like the 1st), an item without an image (skipped),
 *              caption twins (a plain caption; "-" = no caption in plain language), an alt override,
 *              a decorative image, emptied screen-reader texts (they fall back to the defaults)
 *              (structure only).
 *
 * @package nevehairus-site
 */

$photo = static function ( string $name ): array {
	return [
		'id'  => nh_test_media_id( $name ),
		'url' => '',
	];
};

return [
	'variants' => [
		'default'   => [
			'settings' => [],
		],
		'prototype' => [
			'settings'    => [
				'images' => [
					0 => [ 'image' => $photo( 'dining' ) ],
					1 => [ 'image' => $photo( 'sky-ceiling' ) ],
					2 => [ 'image' => $photo( 'bedroom' ) ],
					3 => [ 'image' => $photo( 'living' ) ],
					4 => [ 'image' => $photo( 'sky-corridor' ) ],
					5 => [ 'image' => $photo( 'courtyard' ) ],
				],
			],
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#inside' ],
			],
			'expect_diff' => [
				'data-(full|srcset)=',       // only if compare.py prints their values: media library URLs
			],
		],
		'states'    => [
			'settings' => [
				'zoom_label' => '',
				'lb_close'   => '',
				'lb_count'   => '',
				'images'     => [
					0 => [
						'image'         => $photo( 'dining' ),
						'caption_plain' => "שולחן האוכל.\nאוכלים כאן ביחד.",
					],
					1 => [
						'image'         => $photo( 'sky-ceiling' ),
						'caption_plain' => '-',
						'image_alt'     => 'תקרה עם עננים',
					],
					2 => [ 'caption' => 'פריט בלי תמונה.' ],
					3 => [ 'image' => $photo( 'living' ) ],
					4 => [
						'image'            => $photo( 'sky-corridor' ),
						'image_decorative' => 'yes',
					],
					5 => [ 'image' => $photo( 'courtyard' ) ],
					6 => [
						'image'   => $photo( 'bedroom' ),
						'caption' => 'חדר מגורים לשניים.',
					],
					7 => [
						'image'   => $photo( 'lounge' ),
						'caption' => 'פינת האוכל והסלון.',
					],
				],
			],
		],
	],
];
