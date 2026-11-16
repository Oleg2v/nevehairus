<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of the widget "steps" (package index) — SPEC.md §4.10, §11.2.
 *
 * default : the widget as dropped on a page, compared with prototype/index.html section#start (no differences).
 * edge    : structure only — no decorative word (the span is left out), the lead only in plain language,
 *           step 2 without title (skipped: the numbers stay 1, 2, 3), step 3's text hidden in plain mode.
 */
return [
	'variants' => [
		'default' => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#start' ],
			],
			'expect_diff' => [],
		],
		'edge'    => [
			'settings' => [
				'word'  => '',
				'lead'  => '',
				'steps' => [
					1 => [ 'title' => '' ],
					2 => [ 'text_plain' => '-' ],
				],
			],
		],
	],
];
