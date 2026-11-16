<?php
/**
 * The two prototype pages as widget lists, for the assembled test pages (SPEC §11.2):
 * out/page-home.html ≈ prototype/index.html, out/page-main-home.html ≈ prototype/program-main-home.html.
 *
 * Each entry: [ widget id, fixture variant ]. A variant that a widget's fixture does not have falls back
 * to `prototype`, then `default`; a widget that is not built yet is skipped with an HTML comment.
 * Both pages are rendered as the stub's queried post (id 10); its `_elementor_data` is set from the list,
 * so the head's cover preload (NH_Site::cover_preload()) is exercised too.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

return [
	'page-home'      => [
		'title'     => 'מעון נווה האירוס — בית. לחיים. | נס ציונה',
		'prototype' => 'index.html',
		'header'    => [ 'masthead', 'default' ],
		'footer'    => [ 'colophon', 'prototype' ],
		'widgets'   => [
			[ 'cover', 'prototype' ],
			[ 'homes', 'prototype' ],
			[ 'plainbox', 'default' ],
			[ 'day', 'prototype' ],
			[ 'kaf', 'prototype' ],
			[ 'samekh', 'prototype' ],
			[ 'years', 'prototype' ],
			[ 'voices', 'prototype' ],
			[ 'steps', 'default' ],
			[ 'lamed', 'default' ],
			[ 'door', 'default' ],
		],
	],
	'page-main-home' => [
		'title'     => 'מעון ראשי — בית לחיים | מעון נווה האירוס',
		'prototype' => 'program-main-home.html',
		'header'    => [ 'masthead', 'house' ],
		'footer'    => [ 'colophon', 'prototype' ],
		'widgets'   => [
			[ 'page-cover', 'prototype' ],
			[ 'glance', 'default' ],
			[ 'gallery', 'prototype' ],
			[ 'features', 'default' ],
			[ 'stories', 'default' ],
			[ 'plainbox', 'page' ],
			[ 'related', 'default' ],
			[ 'door', 'page' ],
		],
	],
];
