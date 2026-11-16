<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fixture of the widget "lamed" (package index) — SPEC.md §4.11, §11.2.
 *
 * default   : the widget as dropped on a page with no jobs page in the settings: every link falls back to #jobs
 *             (and editors would see the admin note). Compared with prototype/index.html section#jobs; the
 *             prototype links the roles and the button to design-system.html#form.
 * parity    : the same with the prototype's links set on the roles and the button — no differences expected.
 * jobs-page : a jobs page is set: links come from NH_Settings::jobs_url() (?role=… for the roles). Structure only.
 * edge      : structure only — an external role link (rel="noopener" + the screen-reader suffix), a role without
 *             title (skipped), a role without meta whose title is hidden in plain mode, an unresolved link token,
 *             no button, the ל at weight 500 (interpolated outline).
 */
$nh_form = [ 'url' => 'design-system.html#form' ];

return [
	'variants' => [
		'default'   => [
			'settings'    => [],
			'options'     => [ 'jobs_page' => 0 ],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#jobs' ],
			],
			'expect_diff' => [
				'href of the five .v3-roles links (prototype design-system.html#form, here #jobs: no jobs page set)',
				'href of the .nh-btn--primary button (prototype design-system.html#form, here #jobs: no jobs page set)',
			],
		],
		'parity'    => [
			'settings'    => [
				'roles'       => [
					0 => [ 'link' => $nh_form ],
					1 => [ 'link' => $nh_form ],
					2 => [ 'link' => $nh_form ],
					3 => [ 'link' => $nh_form ],
					4 => [ 'link' => $nh_form ],
				],
				'button_link' => $nh_form,
			],
			'options'     => [ 'jobs_page' => 0 ],
			'compare'     => [
				'page'      => 'index.html',
				'selectors' => [ 'section#jobs' ],
			],
			'expect_diff' => [],
		],
		'jobs-page' => [
			'settings' => [],
			'options'  => [ 'jobs_page' => 20 ],
		],
		'edge'      => [
			'settings' => [
				'button' => '',
				'weight' => 500,
				'roles'  => [
					0 => [ 'link' => [ 'url' => 'https://example.com/jobs/' ] ],
					1 => [ 'title' => '' ],
					2 => [
						'meta'        => '',
						'title_plain' => '-',
					],
					3 => [ 'link' => [ 'url' => '#jobs-page' ] ],
				],
			],
			'options'  => [ 'jobs_page' => 0 ],
		],
	],
];
