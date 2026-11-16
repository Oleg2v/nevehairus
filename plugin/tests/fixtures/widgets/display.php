<?php
/**
 * Harness fixture: widget «display» (research/07 §12.6, §13) — the display switches of the footer.
 *
 * The footer is built from Elementor's own widgets on the site, so there is no compare target for the whole
 * footer; the `prototype` variant compares this widget with the switch group of the prototype's footer.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

return [
	'variants' => [

		// As the site's footer uses it (templates/pages/footer.json): «מצב רגוע» only, on the night ground.
		'default'   => [
			'settings' => [],
		],

		// The prototype's footer group: the same tree (the tone class is on the group itself here; in the
		// prototype it comes from footer.nh-footer.nh-on-dark around it).
		'prototype' => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'footer.nh-footer .v3-switches' ],
			],
			'expect_diff' => [
				'^[-+]\s*div\.(nh-on-dark\.)?v3-switches ',   // the group: + nh-on-dark (the tone of the widget)
			],
		],

		// Both switches on a light ground, with plain-language twins and other state words.
		'both'      => [
			'settings' => [
				'show_plain'        => 'yes',
				'label_calm_plain'  => 'פחות תנועה וצבעים רכים',
				'state_on'          => 'כן',
				'state_off'         => 'לא',
				'tone'              => 'light',
			],
		],

		// Both switched off: nothing is printed (run.php accepts an empty render only for this variant).
		'none'      => [
			'settings' => [
				'show_calm' => '',
			],
			'empty'    => true,
		],
	],
];
