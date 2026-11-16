<?php
/**
 * Fixture of widget `stories` (SPEC §4.16, §11.2).
 *
 * - default: the widget as dropped on a page → program-main-home.html `section#stories`.
 * - states:  a title with the ordinary "." (no tessera), a lead without a twin (one block for both
 *            readings), a story without a title (skipped: the numbers stay 1, 2, 3), a title twin,
 *            a story twin "-" (full only), a fourth story (structure only).
 *
 * @package nevehairus-site
 */

return [
	'variants' => [
		'default' => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#stories' ],
			],
			'expect_diff' => [],
		],
		'states'  => [
			'settings' => [
				'title_dot'  => 'none',
				'lead_plain' => '',
				'stories'    => [
					0 => [ 'title_plain' => 'עבר לגור בעיר' ],
					1 => [ 'title' => '' ],
					2 => [ 'text_plain' => '-' ],
					3 => [
						'title'      => 'סיפור רביעי',
						'text'       => 'דיירת חדשה מצאה חברים בחוג המוזיקה.',
						'text_plain' => "דיירת חדשה הגיעה למעון.\nהיום יש לה חברים.",
					],
				],
			],
		],
	],
];
