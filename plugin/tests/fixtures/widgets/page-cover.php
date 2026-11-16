<?php
/**
 * Fixture of widget `page-cover` (SPEC §4.2, §11.2).
 *
 * - default:   the widget as dropped on a page: no photo yet, so the room shows the sky field
 *              (structure only).
 * - prototype: the prototype's photo on the house page (the queried post is 11) →
 *              program-main-home.html `section#cover`. The crumb hrefs (index.html… → home_url()) are equal
 *              once normalised.
 * - states:    no anchor (ids nh-{element id}-…), a one-line title with a clay tessera, a title twin
 *              "-" (the H1 is never hidden: the plain span repeats the title), a crumb without a name
 *              (skipped), a crumb with a plain twin, an emptied navigation name (falls back), no button
 *              link (no button), a decorative photo, the phone line switched off (structure only).
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
				'photo' => [
					'id'  => nh_test_media_id( 'v3-sky-room' ),
					'url' => '',
				],
			],
			'queried'     => 11,
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#cover' ],
			],
			'expect_diff' => [],
		],
		'states'    => [
			'settings' => [
				'anchor'             => '',
				'title_1'            => 'בית.',
				'title_1_dot'        => 'clay',
				'title_2'            => '',
				'title_plain'        => '-',
				'lead_plain'         => '-',
				'cta_link'           => [ 'url' => '' ],
				'show_call'          => '',
				'photo'              => [
					'id'  => nh_test_media_id( 'v3-sky-room' ),
					'url' => '',
				],
				'photo_decorative'   => 'yes',
				'photo_pos'          => '',
				'photo_sizes'        => '',
				'crumbs_label'       => '',
				'crumbs'             => [
					0 => [ 'label_plain' => 'הבית' ],
					1 => [ 'label' => '' ],
				],
			],
		],
	],
];
