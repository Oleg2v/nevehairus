<?php
/**
 * Fixture of widget `page-cover` (SPEC §4.2, §11.2).
 *
 * - default:   the widget as dropped on a page: no photo yet, so the room shows the sky field
 *              (structure only).
 * - prototype: the prototype's photo → program-main-home.html `section#cover`.
 *              Expected differences (§4.2): the crumb hrefs (index.html… → home_url(), equal once
 *              normalised) and `data-vt` on the room (the prototype names the room by script only).
 * - states:    no anchor (ids nh-{element id}-…), a one-line title with a clay tessera, a title twin
 *              "-" (the H1 is never hidden: the plain span repeats the title), a crumb without a name
 *              (skipped), a crumb with a plain twin, an emptied navigation name (falls back), no button
 *              link (no button), an emptied weight (600), a decorative photo (structure only).
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
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#cover' ],
			],
			'expect_diff' => [
				'data-vt',                   // our room line: data-vt="home-bet-{post id}"
				'v3-room--bet',              // the room line on both sides (differs only by data-vt)
				'^[-+ ]?\s*a\s*\[href',      // crumb links (class-less anchors only): index.html / index.html#homes vs home_url()
			],
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
				'weight'             => '',
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
