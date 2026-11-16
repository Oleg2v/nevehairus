<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of the widget "related" (package index) — SPEC.md §4.17, §11.2.
 *
 * default : the widget as dropped on a page, compared with prototype/program-main-home.html section#related.
 *           Links 2–3 are home_url('/#made') and home_url('/#home-2') here and index.html#… in the prototype:
 *           hrefs are compared by fragment, so no differences are expected.
 * edge    : structure only — an external link (rel="noopener" + the screen-reader suffix) whose title is hidden in
 *           plain mode, a link without URL (an <a> without href), a link without title (skipped), the heading
 *           without its plain twin.
 */
return [
	'variants' => [
		'default' => [
			'settings'    => [],
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
						'link'        => [ 'url' => 'https://example.com/reading/' ],
						'title_plain' => '-',
					],
					1 => [ 'link' => [ 'url' => '' ] ],
					2 => [ 'title' => '' ],
				],
			],
		],
	],
];
