<?php
/**
 * Fixture of widget `glance` (SPEC §4.13, §11.2).
 *
 * - default: the widget as dropped on a page → program-main-home.html `section#glance`.
 * - states:  a lead twin "-" (full only), a number with a percent sign (isolated LTR) and one with
 *            digits and a comma (not isolated), a stat without a number (skipped), a fact without a
 *            description (skipped), a full-reading summary with its twin (both blocks) (structure only).
 *
 * @package nevehairus-site
 */

return [
	'variants' => [
		'default' => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#glance' ],
			],
			'expect_diff' => [],
		],
		'states'  => [
			'settings' => [
				'lead_plain'    => '-',
				'stats'         => [
					0 => [ 'num' => '70%' ],
					1 => [ 'num' => '' ],
					2 => [ 'num' => '1,200' ],
				],
				'facts'         => [
					1 => [ 'desc' => '' ],
				],
				'summary'       => 'יותר מ־100 דיירים ודיירות, מאז 1957.',
				'summary_plain' => "בנווה האירוס גרים יותר מ־100 דיירים ודיירות.\nהמעון נפתח בשנת 1957.",
			],
		],
	],
];
