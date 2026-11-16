<?php
/**
 * Fixture of widget `plainbox` (SPEC §4.4, §11.2).
 *
 * - default: the widget as dropped on a page → index.html `section#plain`.
 * - page:    the house page set-up of step 4 (variant page, anchor plain-page, the house's list)
 *            → program-main-home.html `section#plain-page`.
 * - states:  the other twin states (structure only): list hidden in plain mode, a button and a
 *            title without twins.
 *
 * @package nevehairus-site
 */

return [
	'variants' => [
		'default' => [
			'settings'    => [],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#plain' ],
			],
			'expect_diff' => [],
		],
		'page'    => [
			'settings'    => [
				'variant' => 'page',
				'anchor'  => 'plain-page',
				'items'   => "המעון הראשי נמצא בנס ציונה.\nגרים בו גברים ונשים עם מוגבלות שכלית התפתחותית.\nהצוות קבוע, ומכיר את כולם.\nיש חדר נעים להירגע, מרפאה וחוגים.\nאפשר לבוא לבקר.",
			],
			'compare'     => [
				'page'      => 'program-main-home.html',
				'selectors' => [ 'section#plain-page' ],
			],
			'expect_diff' => [],
		],
		'states'  => [
			'settings' => [
				'anchor'       => 'plain-states',
				'title_plain'  => 'מה כתוב כאן?',
				'items_plain'  => '-',
				'button_plain' => '',
			],
		],
	],
];
