<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of the widget "related" (package index) — SPEC.md §4.17, §11.2; research/07 §10.3 item 8.
 *
 * The widget sits on a house page (the queried post is 11, «מעון ראשי»), so links to the home page are links to
 * another page.
 *
 * default : the widget as dropped on a page, compared with prototype/program-main-home.html section#related:
 *           «שלושת הבתים» → home_url('/#homes'), «ידיים שיוצרות» → home_url('/#made') (index.html#… in the
 *           prototype; hrefs are compared by fragment).
 * edge    : structure only — an external link (rel="noopener" + the screen-reader suffix) whose title is hidden
 *           in plain mode; rows that are not printed: a link without URL, a bare fragment (a section of this
 *           page), a link to this very page, a row without title; the heading without its plain twin.
 * home    : the same defaults on the home page itself (queried post 10): both rows lead back to this page, so
 *           the list is empty and editors see notes (structure only).
 */
return [
	'variants' => [
		'default' => [
			'settings'    => [],
			'queried'     => 11,
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#related' ],
			],
			'expect_diff' => [],
		],
		'edge'    => [
			'settings' => [
				'title_plain' => '',
				'links'       => [
					0 => [
						'title'       => 'קריאה נוספת',
						'link'        => [ 'url' => 'https://example.com/reading/' ],
						'title_plain' => '-',
					],
					1 => [ 'link' => [ 'url' => '' ] ],
					2 => [
						'title' => 'המקטע הבא',
						'link'  => [ 'url' => '#visit' ],
					],
					3 => [
						'title' => 'העמוד הזה',
						'link'  => [ 'url' => 'https://nh.test/main-home/#cover' ],
					],
					4 => [
						'title' => '',
						'link'  => [ 'url' => 'https://nh.test/jobs/' ],
					],
					5 => [
						'title' => 'דרושים',
						'link'  => [ 'url' => 'https://nh.test/jobs/' ],
					],
				],
			],
			'queried'  => 11,
		],
		'home'    => [
			'settings' => [],
			'queried'  => 10,
		],
	],
];
