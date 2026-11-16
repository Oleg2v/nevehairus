<?php
/**
 * Core tests 2 — NH_Text and NH_Html (SPEC §11.5 item 2): the five states of pair() and block(), the
 * tessera, lines(), the inline allowlist, link tokens, settings tokens.
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

return [
	'pair(): the five states (table)' => static function () {
		$rows = [
			// full, plain, options, expected
			[ 'שלושה בתים.', '', [ 'dot' => '' ], 'שלושה בתים<span class="v3-dot">.</span>' ],
			[ 'החיים קורים כאן.', 'מה עושים ביום?', [ 'dot' => '' ], '<span class="v3-full">החיים קורים כאן<span class="v3-dot">.</span></span><span class="v3-plain">מה עושים ביום?</span>' ],
			[ 'מוגבלות … מלווים', '-', [ 'class' => 'v3-roles__meta' ], '<span class="v3-roles__meta v3-full">מוגבלות … מלווים</span>' ],
			[ '', 'רק בשפה פשוטה', [], '<span class="v3-plain">רק בשפה פשוטה</span>' ],
			[ '', '-', [], '' ],
			[ '  ', '  ', [], '' ],
			[ 'פיקוח משרד הרווחה והביטחון החברתי', 'משרד הרווחה בודק שהכול בסדר.', [ 'tag' => 'b' ], '<b class="v3-full">פיקוח משרד הרווחה והביטחון החברתי</b><b class="v3-plain">משרד הרווחה בודק שהכול בסדר.</b>' ],
			[ 'הדייר או הדיירת', '', [ 'tag' => 'b', 'bare' => false ], '<b>הדייר או הדיירת</b>' ],
			[ 'טקסט', '', [ 'class' => 'v3-x' ], '<span class="v3-x">טקסט</span>' ],
			[ 'בוגרים', "בית גדול בנס ציונה.\nצוות עוזר שם כל היום.", [ 'class' => 'v3-home__who', 'plain_lines' => true, 'bare' => false ], '<span class="v3-home__who v3-full">בוגרים</span><span class="v3-home__who v3-plain"><span>בית גדול בנס ציונה.</span> <span>צוות עוזר שם כל היום.</span></span>' ],
			[ '<script>x</script>a & b', '', [ 'esc' => 'text' ], '&lt;script&gt;x&lt;/script&gt;a &amp; b' ],
		];
		foreach ( $rows as $i => [ $f, $p, $o, $want ] ) {
			nh_assert_same( $want, NH_Text::pair( $f, $p, $o ), "row $i" );
		}
		nh_assert_same( 1, NH_Text::state( 'a' ) );
		nh_assert_same( 2, NH_Text::state( 'a', 'b' ) );
		nh_assert_same( 3, NH_Text::state( 'a', ' - ' ) );
		nh_assert_same( 4, NH_Text::state( '', 'b' ) );
		nh_assert_same( 5, NH_Text::state( '', '' ) );
	},

	'block(): the five states and the line modes (table)' => static function () {
		$lead  = 'בית בנס ציונה.';
		$plain = "גרים כאן אנשים.\nהבית נמצא בעיר נס ציונה.";
		$cover = [
			'full_class'  => 'v3-cover__lead',
			'plain_class' => 'v3-cover__plain',
		];
		nh_assert_same( '<p class="v3-cover__lead v3-full">בית בנס ציונה.</p><div class="v3-cover__plain v3-plain"><p>גרים כאן אנשים.</p><p>הבית נמצא בעיר נס ציונה.</p></div>', NH_Text::block( $lead, $plain, $cover ), 'state 2' );
		nh_assert_same( '<p class="v3-cover__lead">בית בנס ציונה.</p>', NH_Text::block( $lead, '', $cover ), 'state 1: no mode class' );
		nh_assert_same( '<p class="v3-cover__lead v3-full">בית בנס ציונה.</p>', NH_Text::block( $lead, '-', $cover ), 'state 3' );
		nh_assert_same( '<div class="v3-cover__plain v3-plain"><p>גרים כאן אנשים.</p><p>הבית נמצא בעיר נס ציונה.</p></div>', NH_Text::block( '', $plain, $cover ), 'state 4' );
		nh_assert_same( '', NH_Text::block( '', '-', $cover ), 'state 5' );
		$steps = [
			'full_class'  => 'v3-lead',
			'plain_tag'   => 'p',
			'plain_class' => 'v3-lead',
			'plain_lines' => null,
		];
		nh_assert_same( '<p class="v3-lead v3-full">מלווים אתכם.</p><p class="v3-lead v3-plain">יש ארבעה צעדים.</p>', NH_Text::block( 'מלווים אתכם.', 'יש ארבעה צעדים.', $steps ) );
		$list = [
			'full_tag'    => 'ul',
			'full_lines'  => 'li',
			'full_attrs'  => [ 'role' => 'list' ],
			'plain_tag'   => 'p',
			'plain_lines' => null,
		];
		nh_assert_same( '<ul role="list" class="v3-full"><li>א.</li><li>ב.</li></ul><p class="v3-plain">פשוט.</p>', NH_Text::block( "א.\n\nב.\n", 'פשוט.', $list ), 'one li per non-empty line' );
		nh_assert_same( '<p class="x">שורה 1<br>שורה 2</p>', NH_Text::block( "שורה 1\r\nשורה 2", '', [ 'full_class' => 'x' ] ), 'newlines → br' );
	},

	'dot(): variants, only the final dot, none and null' => static function () {
		nh_assert_same( 'הדלת פתוחה. בואו להכיר<span class="v3-dot v3-dot--citrus">.</span>', NH_Text::dot( 'הדלת פתוחה. בואו להכיר.', 'citrus' ) );
		nh_assert_same( 'בית<span class="v3-dot">.</span>', NH_Text::dot( 'בית.', '' ) );
		nh_assert_same( 'בית<span class="v3-dot v3-dot--clay">.</span>', NH_Text::dot( 'בית.  ', 'clay' ) );
		nh_assert_same( 'בית<span class="v3-dot v3-dot--ink">.</span>', NH_Text::dot( 'בית.', 'ink' ) );
		nh_assert_same( 'בית.', NH_Text::dot( 'בית.', 'none' ) );
		nh_assert_same( 'בית.', NH_Text::dot( 'בית.', null ) );
		nh_assert_same( 'בית', NH_Text::dot( 'בית', '' ), 'no final dot' );
		nh_assert_same( 'בית<span class="v3-dot">.</span>', NH_Text::dot( 'בית.', 'pink' ), 'unknown variant = iris' );
		nh_assert_same( '<span class="v3-full">א<span class="v3-dot">.</span></span><span class="v3-plain">ב.</span>', NH_Text::pair( 'א.', 'ב.', [ 'dot' => '' ] ), 'the plain twin never gets a tessera' );
	},

	'lines()' => static function () {
		nh_assert_same( [ 'א', 'ב', 'ג' ], NH_Text::lines( " א \r\n\r\nב\rג\n\n" ) );
		nh_assert_same( [], NH_Text::lines( "\n \n" ) );
	},

	'esc(): the inline allowlist (kses) and newlines' => static function () {
		$in  = 'קראתי את <a href="#privacy" onclick="x()">מדיניות</a> <script>alert(1)</script><b>חובה</b> <i>נטוי</i> <span class="nh-sr-only" style="color:red" aria-hidden="true">*</span>';
		$out = NH_Text::esc( $in );
		nh_assert_contains( '<a href="#privacy">מדיניות</a>', $out );
		nh_assert_not_contains( 'onclick', $out );
		nh_assert_not_contains( '<script', $out );
		nh_assert_contains( '<b>חובה</b>', $out );
		nh_assert_not_contains( '<i>', $out, 'i is not allowed' );
		nh_assert_contains( 'נטוי', $out, 'the text of a removed tag stays' );
		nh_assert_contains( '<span class="nh-sr-only" aria-hidden="true">*</span>', $out );
		nh_assert_not_contains( 'style=', $out );
		nh_assert_not_contains( 'javascript', NH_Text::esc( '<a href="javascript:alert(1)">x</a>' ) );
		nh_assert_same( 'א<br>ב', NH_Text::esc( "א\nב" ) );
		nh_assert_same( '&lt;b&gt;', NH_Text::esc( '<b>', 'text' ) );
		nh_assert_same( [ 'a', 'b', 'strong', 'small', 'br', 'span' ], array_keys( NH_Text::kses_inline() ) );
	},

	'link tokens: resolved from the settings, unresolved stay in-page anchors' => static function () {
		$html = '<a href="#privacy">פרטיות</a> <a href="#accessibility">נגישות</a> <a href="#door">דלת</a>';
		nh_assert_same( '<a href="#privacy">פרטיות</a> <a href="#accessibility">נגישות</a> <a href="#door">דלת</a>', NH_Text::esc( $html ), 'nothing set' );
		nh_assert_same( [ '#privacy', '#accessibility' ], NH_Text::unresolved_tokens( NH_Text::esc( $html ) ) );
		$GLOBALS['nh_test']['privacy_url'] = 'https://nh.test/privacy/';
		update_option( 'nh_settings', [ 'accessibility_page' => 13, 'sitemap_page' => 16 ] );
		$out = NH_Text::esc( $html );
		nh_assert_contains( '<a href="https://nh.test/privacy/">פרטיות</a>', $out );
		nh_assert_contains( '<a href="https://nh.test/accessibility/">נגישות</a>', $out );
		nh_assert_contains( '<a href="#door">', $out, 'other anchors untouched' );
		nh_assert_same( '#sitemap', NH_Html::resolve_token( '#sitemap' ), 'a draft page does not resolve' );
		nh_assert_same( 'https://nh.test/accessibility/', NH_Html::url( [ 'url' => '#accessibility' ] ) );
		nh_assert_same( '#jobs-page', NH_Html::url( '#jobs-page' ), 'jobs page not set' );
		update_option( 'nh_settings', [ 'jobs_page' => 12 ] );
		nh_assert_same( 'https://nh.test/jobs/', NH_Html::url( '#jobs-page' ) );
	},

	'tokens(): {phone} {email} {contact} {year}, with and without the public e-mail' => static function () {
		$tel = '<a href="tel:+97289403047"><span class="nh-ltr">08-9403047</span></a>';
		nh_assert_same( 'התקשרו: ' . $tel, NH_Text::tokens( 'התקשרו: {phone}' ) );
		nh_assert_same( 'ולתקן אותו.', NH_Text::tokens( 'ולתקן אותו{email}.' ), 'no public e-mail: {email} is empty' );
		nh_assert_same( 'ולתקן אותו: ' . $tel . '.', NH_Text::tokens( 'ולתקן אותו{contact}.' ), 'no public e-mail: {contact} names the phone' );
		nh_assert_same( '© ' . gmdate( 'Y' ), NH_Text::tokens( '© {year}' ) );
		update_option( 'nh_settings', [ 'email_public' => 'office@example.test' ] );
		$mail = ': <a href="mailto:office@example.test"><span class="nh-ltr">office@example.test</span></a>';
		nh_assert_same( 'ולתקן אותו' . $mail . '.', NH_Text::tokens( 'ולתקן אותו{email}.' ) );
		nh_assert_same( 'ולתקן אותו' . $mail . '.', NH_Text::tokens( 'ולתקן אותו{contact}.' ) );
	},

	'NH_Html: attrs, icon, url, ltr, tel, is_external, toggles, admin note' => static function () {
		nh_assert_same( ' class="a b" hidden data-x="&quot;q&quot;"', NH_Html::attrs( [ 'class' => [ 'a', '', 'b' ], 'hidden' => true, 'gone' => false, 'nil' => null, 'data-x' => '"q"' ] ) );
		nh_assert_same( '<svg class="nh-btn__icon nh-btn__icon--fwd" aria-hidden="true" focusable="false"><use href="#i-arrow-fwd"/></svg>', NH_Html::icon( 'arrow-fwd', 'nh-btn__icon nh-btn__icon--fwd' ) );
		nh_assert_same( '<svg aria-hidden="true" focusable="false"><use href="#i-arrow-fwd"/></svg>', NH_Html::icon( 'arrow-fwd' ) );
		nh_assert_same( '<svg focusable="false"><use href="#i-play"/></svg>', NH_Html::icon( 'play', '', false ) );
		nh_assert_same( '', NH_Html::url( [ 'url' => '' ] ) );
		nh_assert_same( '', NH_Html::url( 'javascript:alert(1)' ) );
		nh_assert_same( 'https://nh.test/?a=1&#038;b=2', NH_Html::url( 'https://nh.test/?a=1&b=2' ) );
		nh_assert_same( '<span class="nh-ltr">100+</span>', NH_Html::ltr( '100+' ) );
		nh_assert_same( 'tel:+97289403047', NH_Html::tel( '+972 8-940 3047' ) );
		nh_assert( NH_Html::is_external( 'https://www.facebook.com/neveirus/' ) );
		nh_assert( ! NH_Html::is_external( 'https://nh.test/jobs/' ) );
		nh_assert( ! NH_Html::is_external( '#door' ) );
		nh_assert_same( '<img alt="">', NH_Html::tag( 'img', [ 'alt' => '' ], 'ignored' ) );
		$t = NH_Html::display_toggles(
			[
				'plain'  => NH_Text::pair( 'בשפה פשוטה' ),
				'motion' => [ 'הפחתת תנועה', '' ],
				'calm'   => 'מצב <script>רגוע</script>',
			]
		);
		// v3.2 (research/07 §7.3): two switches; the motion switch is gone; the state said in a silent word
		$state = '<span class="v3-toggle__state" aria-hidden="true" data-on="פועל" data-off="כבוי"></span>';
		nh_assert_same(
			'<button type="button" class="v3-toggle" data-plain-toggle aria-pressed="false"><span class="v3-toggle__label">בשפה פשוטה</span>' . $state . '<i aria-hidden="true"></i></button>'
			. '<button type="button" class="v3-toggle" data-calm-toggle aria-pressed="false"><span class="v3-toggle__label">מצב רגוע</span>' . $state . '<i aria-hidden="true"></i></button>',
			$t
		);
		nh_assert_contains(
			'data-on="On &quot;1&quot;" data-off="Off"',
			NH_Html::display_toggles( [ 'calm' => 'x' ], [ 'on' => 'On "1"', 'off' => 'Off' ] ),
			'the widgets pass their own words, escaped'
		);
		nh_assert_not_contains( 'v3-toggle__state', NH_Html::display_toggles( [ 'calm' => 'x' ], [ 'on' => '', 'off' => '' ] ), 'no words: no state span' );
		$calm = NH_Html::display_toggles( [ 'calm' => 'מצב רגוע' ] );
		nh_assert( false === strpos( $calm, 'data-plain-toggle' ) && 1 === substr_count( $calm, 'data-calm-toggle' ), 'only the switches whose label is passed (the footer: calm only)' );
		nh_assert_same( '', NH_Html::display_toggles( [ 'motion' => 'הפחתת תנועה' ] ), 'no switch without a plain or calm label' );
		nh_assert_same( '', NH_Html::admin_note( 'x' ), 'visitors see no note' );
		$GLOBALS['nh_test']['caps']['edit_posts'] = true;
		nh_assert_same( '<p class="nh-admin-note" role="note">חסר &lt;x&gt;</p>', NH_Html::admin_note( 'חסר <x>' ) );
	},

	'NH_Html::is_current(): ids, ancestors, URLs without fragment' => static function () {
		$GLOBALS['nh_test']['posts'][17] = [ 'type' => 'page', 'status' => 'publish', 'url' => 'https://nh.test/main-home/rooms/', 'parent' => 11, 'title' => 'חדרים' ];
		$GLOBALS['nh_test']['queried']   = 17;
		nh_assert( NH_Html::is_current( 'https://nh.test/#homes', '11' ), 'an ancestor in the ids' );
		nh_assert( NH_Html::is_current( 'https://nh.test/main-home/' ), 'the URL of an ancestor' );
		nh_assert( ! NH_Html::is_current( 'https://nh.test/main-home/#cover' ), 'a URL with a fragment never matches' );
		nh_assert( ! NH_Html::is_current( 'https://nh.test/jobs/', '12, 13' ) );
		nh_assert_same( 11, NH_Html::post_id( '/main-home/' ) );
		nh_assert_same( 0, NH_Html::post_id( 'https://example.com/main-home/' ) );
		unset( $GLOBALS['nh_test']['posts'][17] );
	},

	'NH_Html::current_state(): page, true (the section), never outside is_singular()' => static function () {
		$GLOBALS['nh_test']['queried'] = 11;                             // מעון ראשי
		nh_assert_same( 'page', NH_Html::current_state( 'https://nh.test/main-home/' ), 'the link to this very page' );
		nh_assert_same( 'page', NH_Html::current_state( 'https://nh.test/main-home/', '11' ), 'page wins over the section ids' );
		nh_assert_same( 'true', NH_Html::current_state( 'https://nh.test/#homes', '11' ), 'the section: this page in the ids (§5.3: «הבתים שלנו»)' );
		nh_assert_same( '', NH_Html::current_state( 'https://nh.test/#homes' ), 'a fragment link without ids' );
		nh_assert_same( '', NH_Html::current_state( 'https://nh.test/jobs/', '12' ) );
		$GLOBALS['nh_test']['posts'][17] = [ 'type' => 'page', 'status' => 'publish', 'url' => 'https://nh.test/main-home/rooms/', 'parent' => 11, 'title' => 'חדרים' ];
		$GLOBALS['nh_test']['queried']   = 17;
		nh_assert_same( 'true', NH_Html::current_state( 'https://nh.test/main-home/' ), 'the URL of an ancestor' );
		nh_assert_same( 'true', NH_Html::current_state( 'https://nh.test/#homes', '11' ), 'an ancestor in the ids' );
		nh_assert_same( 'page', NH_Html::current_state( '/main-home/rooms/' ) );
		$GLOBALS['nh_test']['is_singular'] = false;                       // an archive: the queried id is a term's
		$GLOBALS['nh_test']['queried']     = 11;
		nh_assert_same( '', NH_Html::current_state( 'https://nh.test/main-home/', '11' ), 'not singular: never current' );
		nh_assert( ! NH_Html::is_current( 'https://nh.test/main-home/', '11' ), 'is_current() follows' );
		unset( $GLOBALS['nh_test']['posts'][17] );
	},
];
