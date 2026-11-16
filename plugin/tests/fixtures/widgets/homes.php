<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of the widget "homes" (package index) — SPEC.md §4.3, §11.2.
 *
 * default   : the widget as dropped on a page. No photos, so every room shows the calm sky (no compare target:
 *             the prototype has photos in rows 1–2).
 * prototype : the prototype's photos in rows 1–2, compared with prototype/index.html section#homes. Row 1 links to
 *             the house page as in the prototype (by default it links to #homes until the page exists), so the
 *             only differences §4.3 expects — row 1's href, and the chooser's id fit-title → homes-fit-title
 *             (ids are compared by presence only) — do not show up.
 * edge      : structure only — chooser without its question (the nav has no aria-labelledby) and with answer 4
 *             left out in plain language, intro empty, note hidden in plain mode, row 1 left out in plain language
 *             (the li is v3-full, its texts have no mode classes), row 2 without title (skipped), row 3 without
 *             anchor, with an unknown tone, no facts, no weight and a missing attachment (falls back to paper,
 *             560 and the sky).
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
			'options'     => [],
			'get'         => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#homes' ],
			],
			'expect_diff' => [],
		],
		'edge'      => [
			'settings' => [
				'fit_q'       => '',
				'fit'         => [
					3 => [ 'label_plain' => '-' ],
				],
				'intro'       => '',
				'intro_plain' => '',
				'note_plain'  => '-',
				'homes'       => [
					0 => [ 'title_plain' => '-' ],
					1 => [ 'title' => '' ],
					2 => [
						'anchor' => '',
						'tone'   => 'unknown',
						'facts'  => '',
						'weight' => '',
						'photo'  => [
							'id'  => 999999,
							'url' => '',
						],
					],
				],
			],
		],
	],
];
