<?php
defined( 'ABSPATH' ) || exit;

/**
 * Control helpers (SPEC §3.4). Every visible text is a pair: the text and its plain-language twin.
 *
 * `$target` is a widget or an \Elementor\Repeater (both have add_control()). The helpers add controls
 * into the controls section that is currently open; only section() opens and closes its own.
 *
 * @package nevehairus-site
 */
final class NH_Controls {

	/** Help text under every plain-language twin. */
	public const PLAIN_HELP = 'ריק = אותו טקסט גם בשפה פשוטה. מקף (-) = להסתיר בשפה פשוטה. כל שורה = שורה נפרדת.';

	/**
	 * Adds `{id}` (TEXT or TEXTAREA) and its twin `{id}_plain` (TEXTAREA, rows 3).
	 * Args: label (required), default, plain, type ('text'|'textarea'), rows, condition, description.
	 *
	 * @param object $target Widget or Repeater.
	 * @param string $id     Control id.
	 * @param array  $a      Args.
	 */
	public static function pair( $target, string $id, array $a ): void {
		$label    = (string) ( $a['label'] ?? $id );
		$textarea = 'textarea' === ( $a['type'] ?? 'text' );

		$main = [
			'label'       => $label,
			'type'        => $textarea ? \Elementor\Controls_Manager::TEXTAREA : \Elementor\Controls_Manager::TEXT,
			'default'     => (string) ( $a['default'] ?? '' ),
			'label_block' => true,
		];
		if ( $textarea && isset( $a['rows'] ) ) {
			$main['rows'] = (int) $a['rows'];
		}
		if ( isset( $a['description'] ) ) {
			$main['description'] = (string) $a['description'];
		}
		if ( isset( $a['condition'] ) ) {
			$main['condition'] = $a['condition'];
		}
		$target->add_control( $id, $main );

		$twin = [
			/* translators: %s: the label of the text control */
			'label'       => sprintf( __( '%s — בשפה פשוטה', 'nevehairus-site' ), $label ),
			'type'        => \Elementor\Controls_Manager::TEXTAREA,
			'rows'        => 3,
			'default'     => (string) ( $a['plain'] ?? '' ),
			'description' => self::PLAIN_HELP,
			'label_block' => true,
		];
		if ( isset( $a['condition'] ) ) {
			$twin['condition'] = $a['condition'];
		}
		$target->add_control( $id . '_plain', $twin );
	}

	/**
	 * The spread heading: pair `title` + SELECT `title_dot` (the colour of the final tessera).
	 * Args: default, plain, dot (default ''), label (default 'כותרת').
	 *
	 * @param object $target Widget or Repeater.
	 * @param array  $d      Args.
	 */
	public static function heading( $target, array $d ): void {
		self::pair(
			$target,
			'title',
			[
				'label'   => (string) ( $d['label'] ?? __( 'כותרת', 'nevehairus-site' ) ),
				'default' => (string) ( $d['default'] ?? '' ),
				'plain'   => (string) ( $d['plain'] ?? '' ),
			]
		);
		$target->add_control(
			'title_dot',
			[
				'label'       => __( 'הנקודה בסוף הכותרת', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => self::dot_options(),
				'default'     => (string) ( $d['dot'] ?? '' ),
				'description' => __( 'רק נקודה בסוף הכותרת הופכת לאבן פסיפס.', 'nevehairus-site' ),
			]
		);
	}

	/**
	 * An image: `{id}` MEDIA (no placeholder), `{id}_alt`, `{id}_decorative`[, `{id}_pos`][, `{id}_sizes`].
	 * Args: label, decorative (bool), alt (''), pos (adds _pos), sizes (adds _sizes), condition.
	 *
	 * @param object $target Widget or Repeater.
	 * @param string $id     Control id.
	 * @param array  $a      Args.
	 */
	public static function image( $target, string $id, array $a ): void {
		$cond = isset( $a['condition'] ) ? [ 'condition' => $a['condition'] ] : [];

		$target->add_control(
			$id,
			[
				'label'       => (string) ( $a['label'] ?? __( 'תמונה', 'nevehairus-site' ) ),
				'type'        => \Elementor\Controls_Manager::MEDIA,
				'default'     => [
					'url' => '',
					'id'  => '',
				],
				'media_types' => [ 'image' ],
				'label_block' => true,
			] + $cond
		);
		$target->add_control(
			$id . '_decorative',
			[
				'label'        => __( 'תמונה דקורטיבית (בלי טקסט חלופי)', 'nevehairus-site' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => empty( $a['decorative'] ) ? '' : 'yes',
			] + $cond
		);
		$target->add_control(
			$id . '_alt',
			[
				'label'       => __( 'טקסט חלופי', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => (string) ( $a['alt'] ?? '' ),
				'description' => __( 'ריק = הטקסט החלופי מספריית המדיה.', 'nevehairus-site' ),
				'label_block' => true,
				'condition'   => array_merge( (array) ( $a['condition'] ?? [] ), [ $id . '_decorative!' => 'yes' ] ),
			]
		);
		if ( isset( $a['pos'] ) ) {
			$target->add_control(
				$id . '_pos',
				[
					'label'       => __( 'מיקוד התמונה (אופקי% אנכי%)', 'nevehairus-site' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => (string) $a['pos'],
					'description' => __( 'למשל 50% 40%. החלק של התמונה שנשאר בחדר של האות.', 'nevehairus-site' ),
				] + $cond
			);
		}
		if ( isset( $a['sizes'] ) ) {
			$target->add_control(
				$id . '_sizes',
				[
					'label'       => __( 'רוחב התצוגה (sizes)', 'nevehairus-site' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => (string) $a['sizes'],
					'description' => __( 'לשינוי רק עם שינוי בעיצוב: הדפדפן בוחר לפי זה את גודל הקובץ.', 'nevehairus-site' ),
					'label_block' => true,
				] + $cond
			);
		}
	}

	/**
	 * A URL control without the "new window / nofollow" options. Args: label, default (URL string), condition.
	 *
	 * @param object $target Widget or Repeater.
	 * @param string $id     Control id.
	 * @param array  $a      Args.
	 */
	public static function link( $target, string $id, array $a ): void {
		$control = [
			'label'       => (string) ( $a['label'] ?? __( 'קישור', 'nevehairus-site' ) ),
			'type'        => \Elementor\Controls_Manager::URL,
			'options'     => false,
			'default'     => [ 'url' => (string) ( $a['default'] ?? '' ) ],
			'label_block' => true,
			'description' => (string) ( $a['description'] ?? __( 'כתובת מלאה, או עוגן בעמוד (#door). אפשר גם #privacy, #accessibility, #sitemap, #entrances, #jobs-page.', 'nevehairus-site' ) ),
		];
		if ( isset( $a['condition'] ) ) {
			$control['condition'] = $a['condition'];
		}
		$target->add_control( $id, $control );
	}

	/**
	 * A list, one item per line (TEXTAREA). Args: label, default, rows, description, condition.
	 *
	 * @param object $target Widget or Repeater.
	 * @param string $id     Control id.
	 * @param array  $a      Args.
	 */
	public static function lines( $target, string $id, array $a ): void {
		$control = [
			'label'       => (string) ( $a['label'] ?? $id ),
			'type'        => \Elementor\Controls_Manager::TEXTAREA,
			'rows'        => (int) ( $a['rows'] ?? 6 ),
			'default'     => (string) ( $a['default'] ?? '' ),
			'description' => (string) ( $a['description'] ?? __( 'כל שורה = פריט נפרד.', 'nevehairus-site' ) ),
			'label_block' => true,
		];
		if ( isset( $a['condition'] ) ) {
			$control['condition'] = $a['condition'];
		}
		$target->add_control( $id, $control );
	}

	/**
	 * The section "מקטע" (always last): anchor, running head and its plain twin. Opens and closes its
	 * own controls section. Args: anchor, running, running_plain.
	 *
	 * @param \Elementor\Widget_Base $w Widget.
	 * @param array                  $d Defaults.
	 */
	public static function section( \Elementor\Widget_Base $w, array $d ): void {
		$w->start_controls_section( 'nh_section', [ 'label' => __( 'מקטע', 'nevehairus-site' ) ] );
		$w->add_control(
			'anchor',
			[
				'label'       => __( 'עוגן (id באנגלית)', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => (string) ( $d['anchor'] ?? '' ),
				'description' => __( 'אותיות לטיניות, ספרות ומקפים. קישורים כמו #door מגיעים לכאן; ריק = בלי עוגן.', 'nevehairus-site' ),
			]
		);
		$w->add_control(
			'running',
			[
				'label'       => __( 'שם המקטע בכותרת העליונה', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => (string) ( $d['running'] ?? '' ),
				'label_block' => true,
			]
		);
		$w->add_control(
			'running_plain',
			[
				'label'       => __( 'שם המקטע בכותרת העליונה — בשפה פשוטה', 'nevehairus-site' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => (string) ( $d['running_plain'] ?? '' ),
				'description' => __( 'ריק = אותו שם גם בשפה פשוטה.', 'nevehairus-site' ),
				'label_block' => true,
			]
		);
		$w->end_controls_section();
	}

	/**
	 * Colours of the heading tessera.
	 *
	 * @return array<string,string>
	 */
	public static function dot_options(): array {
		return [
			''       => __( 'סגול', 'nevehairus-site' ),
			'clay'   => __( 'חימר', 'nevehairus-site' ),
			'ink'    => __( 'דיו', 'nevehairus-site' ),
			'citrus' => __( 'הדר', 'nevehairus-site' ),
			'none'   => __( 'נקודה רגילה', 'nevehairus-site' ),
		];
	}

	/**
	 * The image settings of control `$id` in one array:
	 * `[ 'photo' => int, 'alt' => ?string, 'decorative' => bool, 'pos' => ?string, 'sizes' => ?string ]`.
	 * alt is null when empty (= the media library's alt); pos and sizes are null when empty or absent.
	 *
	 * @param array  $s  Settings for display (or a repeater item).
	 * @param string $id Control id.
	 */
	public static function image_args( array $s, string $id ): array {
		$media = isset( $s[ $id ] ) && is_array( $s[ $id ] ) ? $s[ $id ] : [];
		$str   = static function ( string $key ) use ( $s ): ?string {
			$v = isset( $s[ $key ] ) && is_scalar( $s[ $key ] ) ? trim( (string) $s[ $key ] ) : '';
			return '' === $v ? null : $v;
		};
		return [
			'photo'      => absint( $media['id'] ?? 0 ),
			'alt'        => $str( $id . '_alt' ),
			'decorative' => 'yes' === ( $s[ $id . '_decorative' ] ?? '' ),
			'pos'        => $str( $id . '_pos' ),
			'sizes'      => $str( $id . '_sizes' ),
		];
	}
}
