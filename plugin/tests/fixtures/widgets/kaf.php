<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of widget `kaf` (SPEC §4.6, §11.2): variants = settings overrides on top of the control defaults.
 *
 * - default:   what an editor sees after dropping the widget (no photos yet: the palm shows the calm sky).
 * - prototype: the four works with the prototype's photos; compared with index.html `section#made`.
 * - edge:      empty anchor and labels, twins in a button, a hidden row, invalid colour lines, a work
 *              without a photo in the palm, an external button link, an out-of-range weight.
 */
return [
	'variants' => [
		'default'   => [
			'settings' => [],
		],
		'prototype' => [
			'settings'    => [
				'works' => [
					0 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-pot-b' ),
							'url' => '',
						],
					],
					1 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-pot-a' ),
							'url' => '',
						],
					],
					2 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-pot-c' ),
							'url' => '',
						],
					],
					3 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-owls' ),
							'url' => '',
						],
					],
				],
			],
			'options'     => [],
			'get'         => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#made' ],
			],
			'expect_diff' => [
				// SPEC §4.6: data-* on the work buttons (attachment id instead of the image name; data-name/src/srcset/avif are new).
				'^.*button\.(?:[\w-]+\.)*v3-work\b.*\[data-.*$',
				// SPEC §4.6: the prototype's script#nh-works JSON is replaced by those attributes.
				'^.*script.*nh-works.*$',
			],
		],
		'edge'      => [
			'settings' => [
				'anchor'        => '',
				'cap_label'     => '',
				'works_label'   => '',
				'weight'        => '1200',
				'palm_pos'      => 'center',
				'credit'        => '',
				'credit_plain'  => 'כל העבודות נעשו בידי הדיירים והדיירות.',
				'button_link'   => [ 'url' => 'https://example.com/gallery/' ],
				'works'         => [
					0 => [ 'name_plain' => 'כד כחול' ],
					1 => [ 'name' => '' ],
					2 => [
						'name_plain' => '-',
						'swatches'   => "nope שם\n#12345 שגוי\n#ABC\n#abc קצר",
					],
					3 => [
						'photo'          => [
							'id'  => nh_test_media_id( 'v3-owls' ),
							'url' => '',
						],
						'swatches_label' => '',
						'swatches'       => '',
					],
				],
			],
			'options'  => [],
			'get'      => [],
		],
	],
];
