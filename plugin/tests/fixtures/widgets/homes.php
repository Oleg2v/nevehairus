<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of the widget "homes" (package index) — SPEC.md §4.3, §11.2; research/07 §10.2 item 2.
 *
 * default   : the widget as dropped on a page. No photos (every room shows the calm sky) and no page links: every
 *             row is a `div.v3-home__row` that says «דף מפורט בקרוב» (no compare target).
 * prototype : the prototype's photos in rows 1–2 and row 1 linked to the house page, compared with
 *             prototype/index.html section#homes: row 1 is a link, rows 2–3 are not.
 * edge      : structure only — lead empty, note hidden in plain mode, row 1 left out in plain language (the li is
 *             v3-full, its texts have no mode classes) with a link back to this spread (#homes: not a link), row 2
 *             without title (skipped), row 3 without anchor, with an unknown tone, no facts and a missing
 *             attachment (falls back to paper and the sky), the «soon» words with a plain twin.
 * linked    : structure only — every row has a page: links with the arrow, no «soon».
 */
return [
	'variants' => [
		'default'   => [
			'settings' => [],
		],
		'prototype' => [
			'settings'    => [
				'homes' => [
					0 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-sky-room' ),
							'url' => '',
						],
						'link'  => [ 'url' => 'program-main-home.html' ],
					],
					1 => [
						'photo' => [
							'id'  => nh_test_media_id( 'v3-villa' ),
							'url' => '',
						],
					],
				],
			],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#homes' ],
			],
			'expect_diff' => [],
		],
		'edge'      => [
			'settings' => [
				'lead'            => '',
				'lead_plain'      => '',
				'note_plain'      => '-',
				'soon_text_plain' => 'עוד אין דף',
				'homes'           => [
					0 => [
						'title_plain' => '-',
						'link'        => [ 'url' => '#homes' ],
					],
					1 => [ 'title' => '' ],
					2 => [
						'anchor' => '',
						'tone'   => 'unknown',
						'facts'  => '',
						'photo'  => [
							'id'  => 999999,
							'url' => '',
						],
					],
				],
			],
		],
		'linked'    => [
			'settings' => [
				'homes' => [
					0 => [ 'link' => [ 'url' => 'https://nh.test/main-home/' ] ],
					1 => [ 'link' => [ 'url' => 'https://nh.test/sample-page/' ] ],
					2 => [ 'link' => [ 'url' => 'https://nh.test/sample-page/' ] ],
				],
			],
		],
	],
];
