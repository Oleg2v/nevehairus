<?php
/**
 * Fixture of widget `voices` (SPEC §4.9, §11.2).
 *
 * - default:   the widget as dropped on a page: no poster yet (the facade's dark background
 *              remains), structure only.
 * - prototype: poster v3-owls → index.html `section#voices`.
 * - states:    a quote without a twin (no mode class), consent text without a twin, a plain
 *              label on the load button, no caption, a plain twin of the play word (structure only).
 * - no-word:   no visible word on the play button: its name is the screen-reader text (structure only).
 * - no-video:  a video id that is not a YouTube id → no figure; quote twin `-` (structure only).
 *
 * @package nevehairus-site
 */

return [
	'variants' => [
		'default'   => [
			'settings' => [],
		],
		'prototype' => [
			'settings'    => [
				'poster' => [
					'id'  => nh_test_media_id( 'v3-owls' ),
					'url' => '',
				],
			],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#voices' ],
			],
			'expect_diff' => [],
		],
		'states'    => [
			'settings' => [
				'anchor'             => 'voices-states',
				'quote_plain'        => '',
				'consent_text_plain' => '',
				'load_label_plain'   => 'לפתוח את הסרטון',
				'play_label_plain'   => 'לראות את הסרטון',
				'caption'            => '',
				'poster'             => [
					'id'  => nh_test_media_id( 'v3-owls' ),
					'url' => '',
				],
			],
		],
		'no-word'   => [
			'settings' => [
				'anchor'     => 'voices-no-word',
				'play_label' => '',
				'title_dot'  => '',
			],
		],
		'no-video'  => [
			'settings' => [
				'anchor'      => 'voices-no-video',
				'quote_plain' => '-',
				'video_id'    => 'https://youtu.be/JiaXto-WmJo',
			],
		],
	],
];
