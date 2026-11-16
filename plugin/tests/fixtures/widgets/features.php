<?php
/**
 * Fixture of widget `features` (SPEC §4.15, §11.2).
 *
 * - default:     the widget as dropped on a page → program-main-home.html `section#features`.
 * - states:      a full-reading intro with its twin, a feature without a name (skipped), a feature
 *                without a description, a quote twin "-" (the quote is full-reading only, no plain
 *                block), no cite (no footer) (structure only).
 * - plain-quote: no quote, only its plain twin (the plain block alone); no quote twin on the intro
 *                (structure only).
 *
 * @package nevehairus-site
 */

return [
	'variants' => [
		'default'     => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#features' ],
			],
			'expect_diff' => [],
		],
		'states'      => [
			'settings' => [
				'intro'       => 'במעון יש כל מה שצריך ליום מלא.',
				'features'    => [
					1 => [ 'title' => '' ],
					2 => [ 'text' => '' ],
				],
				'quote_plain' => '-',
				'cite'        => '',
			],
		],
		'plain-quote' => [
			'settings' => [
				'intro_plain' => '',
				'quote'       => '',
			],
		],
	],
];
