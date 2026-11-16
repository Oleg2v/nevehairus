<?php
/**
 * Fixture of widget `gallery` (SPEC §4.14, §11.2; research/07 §10.3 item 4): an even grid of 4:3 prints with
 * visible captions, no lightbox.
 *
 * - default:   the widget as dropped on a page: no images yet, so no list (structure only).
 * - prototype: the prototype's six photos of the new wing → program-main-home.html `section#inside`.
 * - states:    seven images, an item without an image (skipped), caption twins (a plain caption; "-" = no
 *              caption in plain language), an alt override, a decorative image, an image without a caption
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
					0 => [ 'image' => $photo( 'v3-dining' ) ],
					1 => [ 'image' => $photo( 'v3-sky-ceiling' ) ],
					2 => [ 'image' => $photo( 'v3-bedroom' ) ],
					3 => [ 'image' => $photo( 'v3-living' ) ],
					4 => [ 'image' => $photo( 'v3-sky-corridor' ) ],
					5 => [ 'image' => $photo( 'v3-lounge' ) ],
				],
			],
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#inside' ],
			],
			'expect_diff' => [],
		],
		'states'    => [
			'settings' => [
				'images' => [
					0 => [
						'image'         => $photo( 'v3-dining' ),
						'caption_plain' => "שולחן האוכל.\nאוכלים כאן ביחד.",
					],
					1 => [
						'image'         => $photo( 'v3-sky-ceiling' ),
						'caption_plain' => '-',
						'image_alt'     => 'תקרה עם עננים',
					],
					2 => [ 'caption' => 'פריט בלי תמונה.' ],
					3 => [
						'image'   => $photo( 'v3-living' ),
						'caption' => '',
					],
					4 => [
						'image'            => $photo( 'v3-sky-corridor' ),
						'image_decorative' => 'yes',
					],
					5 => [ 'image' => $photo( 'v3-lounge' ) ],
					6 => [
						'image'   => $photo( 'v3-bedroom' ),
						'caption' => 'חדר מגורים לשניים.',
					],
				],
			],
		],
	],
];
