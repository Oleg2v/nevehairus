<?php
defined( 'ABSPATH' ) || exit;

/**
 * Settings → נווה האירוס (SPEC §7): the option nh_settings through the Settings API, a read-only
 * readiness list, a "send a test e-mail" button and the admin notice for missing form recipients.
 * Only users with manage_options see or save anything here.
 *
 * @package nevehairus-site
 */
final class NH_Settings_Page {

	/** Page slug (options-general.php?page=nevehairus). */
	public const SLUG = 'nevehairus';

	/**
	 * Hooks (admin only).
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_fields' ] );
		add_action( 'admin_notices', [ __CLASS__, 'notices' ] );
		add_action( 'admin_post_nh_test_mail', [ __CLASS__, 'test_mail' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( NH_FILE ), [ __CLASS__, 'action_links' ] );
	}

	/**
	 * Settings → נווה האירוס.
	 */
	public static function menu(): void {
		add_options_page(
			__( 'נווה האירוס', 'nevehairus-site' ),
			__( 'נווה האירוס', 'nevehairus-site' ),
			'manage_options',
			self::SLUG,
			[ __CLASS__, 'render' ]
		);
	}

	/**
	 * "הגדרות" link in the plugins list.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$links   = is_array( $links ) ? $links : [];
		$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'הגדרות', 'nevehairus-site' ) . '</a>';
		return $links;
	}

	/**
	 * Sections and fields: section => [ title, [ key => label ] ] (types come from NH_Settings::types()).
	 */
	public static function sections(): array {
		return [
			'contact' => [
				__( 'פרטי קשר', 'nevehairus-site' ),
				[
					'phone_display' => __( 'טלפון (לתצוגה)', 'nevehairus-site' ),
					'phone_tel'     => __( 'טלפון לקישור (ספרות ו־+ בלבד)', 'nevehairus-site' ),
					'fax'           => __( 'פקס', 'nevehairus-site' ),
					'email_public'  => __( 'דוא״ל ציבורי (ריק = לא מוצג באתר)', 'nevehairus-site' ),
					'address'       => __( 'כתובת', 'nevehairus-site' ),
					'facebook_url'  => __( 'עמוד הפייסבוק', 'nevehairus-site' ),
				],
			],
			'forms'   => [
				__( 'טפסים', 'nevehairus-site' ),
				[
					'visit_recipient'     => __( 'נמען בקשות הביקור (ריק = הטופס לא שולח)', 'nevehairus-site' ),
					'jobs_recipient'      => __( 'נמען המועמדויות (ריק = הטופס לא שולח)', 'nevehairus-site' ),
					'mail_from_name'      => __( 'שם השולח בהודעות', 'nevehairus-site' ),
					'privacy_visit'       => __( 'הודעת פרטיות — טופס ביקור', 'nevehairus-site' ),
					'privacy_visit_plain' => __( 'הודעת פרטיות — טופס ביקור — בשפה פשוטה', 'nevehairus-site' ),
					'privacy_jobs'        => __( 'הודעת פרטיות — טופס מועמדות', 'nevehairus-site' ),
					'privacy_jobs_plain'  => __( 'הודעת פרטיות — טופס מועמדות — בשפה פשוטה', 'nevehairus-site' ),
					'msg_required'        => __( 'הודעה: שדה חובה', 'nevehairus-site' ),
					'msg_email'           => __( 'הודעה: דוא״ל לא תקין', 'nevehairus-site' ),
					'msg_tel'             => __( 'הודעה: טלפון לא תקין', 'nevehairus-site' ),
					'msg_consent'         => __( 'הודעה: אישור חסר', 'nevehairus-site' ),
					'msg_file_size'       => __( 'הודעה: קובץ גדול מדי', 'nevehairus-site' ),
					'msg_file_type'       => __( 'הודעה: סוג קובץ', 'nevehairus-site' ),
					'msg_summary'         => __( 'כותרת סיכום השגיאות', 'nevehairus-site' ),
					'msg_summary_one'     => __( 'כותרת: שגיאה אחת', 'nevehairus-site' ),
					'msg_summary_many'    => __( 'כותרת: כמה שגיאות ({n} = מספר)', 'nevehairus-site' ),
					'msg_expired'         => __( 'הודעה: הטופס פג תוקף', 'nevehairus-site' ),
					'msg_limited'         => __( 'הודעה: יותר מדי בקשות', 'nevehairus-site' ),
					'msg_off'             => __( 'הודעה: הטופס כבוי', 'nevehairus-site' ),
					'msg_error'           => __( 'הודעה: השליחה נכשלה', 'nevehairus-site' ),
				],
			],
			'links'   => [
				__( 'קישורים', 'nevehairus-site' ),
				[
					'visit_url'          => __( 'קישור לתיאום ביקור (ריק = ‎/#door בדף הבית)', 'nevehairus-site' ),
					'jobs_page'          => __( 'עמוד המשרות (#jobs-page)', 'nevehairus-site' ),
					'accessibility_page' => __( 'הצהרת הנגישות (#accessibility)', 'nevehairus-site' ),
					'sitemap_page'       => __( 'מפת האתר (#sitemap)', 'nevehairus-site' ),
					'entrances_pdf'      => __( 'קובץ מפרט הכניסות הנגישות, PDF מספריית המדיה (#entrances)', 'nevehairus-site' ),
				],
			],
			'cookies' => [
				__( 'הגדרות עוגיות (חלונית אחרי הכותרת התחתונה; נפתחת רק מהקישור „הגדרות עוגיות”)', 'nevehairus-site' ),
				[
					'cookie_title'      => __( 'כותרת', 'nevehairus-site' ),
					'cookie_text'       => __( 'הסבר', 'nevehairus-site' ),
					'cookie_text_plain' => __( 'הסבר — בשפה פשוטה', 'nevehairus-site' ),
					'cookie_necessary'  => __( 'עוגיות הכרחיות', 'nevehairus-site' ),
					'cookie_stats'      => __( 'עוגיות סטטיסטיקה (ריק = בלי השורה)', 'nevehairus-site' ),
					'cookie_media'      => __( 'תוכן מוטמע', 'nevehairus-site' ),
					'cookie_save'       => __( 'כפתור שמירה', 'nevehairus-site' ),
					'cookie_close'      => __( 'כפתור סגירה', 'nevehairus-site' ),
					'consent_version'   => __( 'גרסת ההסכמה (העלאה = לשאול את כולם שוב)', 'nevehairus-site' ),
				],
			],
			'general' => [
				__( 'כללי', 'nevehairus-site' ),
				[
					'webp_subsizes' => __( 'לשמור את הגדלים הקטנים של תמונות JPEG/PNG כ־WebP (בדרך כלל כבוי: uPress מטפל בתמונות)', 'nevehairus-site' ),
				],
			],
		];
	}

	/**
	 * Settings API sections and fields.
	 */
	public static function register_fields(): void {
		foreach ( self::sections() as $section => [ $title, $fields ] ) {
			add_settings_section( 'nh_' . $section, $title, '__return_false', self::SLUG );
			foreach ( $fields as $key => $label ) {
				add_settings_field(
					'nh_' . $key,
					$label,
					[ __CLASS__, 'field' ],
					self::SLUG,
					'nh_' . $section,
					[
						'key'       => $key,
						'label_for' => 'nh-setting-' . $key,
					]
				);
			}
		}
	}

	/**
	 * One field.
	 *
	 * @param array $args [ 'key' => setting key ].
	 */
	public static function field( $args ): void {
		$key   = (string) ( $args['key'] ?? '' );
		$type  = NH_Settings::types()[ $key ] ?? 'text';
		$value = NH_Settings::get( $key );
		$id    = 'nh-setting-' . $key;
		$name  = NH_Settings::OPTION . '[' . $key . ']';
		$attrs = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"';

		switch ( $type ) {
			case 'html':
			case 'textarea':
				echo '<textarea' . $attrs . ' class="large-text" rows="4">' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs is escaped above
				if ( 'html' === $type ) {
					echo '<p class="description">' . esc_html__( 'מותר: <a href>, <b>, <strong>, <small>, <span>. אסימונים: {phone}, {email}, {contact}, {year}; קישורים: #privacy, #accessibility, #sitemap, #entrances, #jobs-page. כל שורה = שורה נפרדת.', 'nevehairus-site' ) . '</p>';
				}
				break;
			case 'bool':
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
				echo '<input type="checkbox"' . $attrs . ' value="1"' . checked( ! empty( $value ), true, false ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
				break;
			case 'page':
				wp_dropdown_pages(
					[
						'name'              => esc_attr( $name ),
						'id'                => esc_attr( $id ),
						'selected'          => absint( $value ),
						'show_option_none'  => esc_html__( '— לא נבחר —', 'nevehairus-site' ),
						'option_none_value' => '0',
					]
				);
				break;
			case 'int':
				echo '<input type="number" min="1" max="999" step="1"' . $attrs . ' value="' . esc_attr( (string) absint( $value ) ) . '" class="small-text">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
				break;
			case 'email':
				echo '<input type="email" dir="ltr"' . $attrs . ' value="' . esc_attr( (string) $value ) . '" class="regular-text">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
				break;
			case 'url':
				echo '<input type="url" dir="ltr"' . $attrs . ' value="' . esc_attr( (string) $value ) . '" class="large-text">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
				break;
			case 'tel':
				echo '<input type="text" dir="ltr"' . $attrs . ' value="' . esc_attr( (string) $value ) . '" class="regular-text">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
				break;
			default:
				echo '<input type="text"' . $attrs . ' value="' . esc_attr( (string) $value ) . '" class="large-text">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		}
	}

	/**
	 * The page: settings form, readiness list, test e-mail.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'נווה האירוס — הגדרות האתר', 'nevehairus-site' ) . '</h1>';
		settings_errors( NH_Settings::OPTION );

		echo '<h2>' . esc_html__( 'מוכנות', 'nevehairus-site' ) . '</h2><ul class="nh-readiness">';
		foreach ( self::readiness() as $row ) {
			echo '<li>' . ( $row['ok'] ? '✓ ' : '✗ ' ) . esc_html( $row['label'] ) . ( '' !== $row['detail'] ? ' — ' . esc_html( $row['detail'] ) : '' ) . '</li>';
		}
		echo '</ul>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="nh_test_mail">';
		wp_nonce_field( 'nh_test_mail' );
		echo '<p><button type="submit" class="button">' . esc_html__( 'שליחת דוא״ל בדיקה לנמענים', 'nevehairus-site' ) . '</button></p></form>';

		echo '<form method="post" action="options.php">';
		settings_fields( NH_Settings::OPTION );
		do_settings_sections( self::SLUG );
		submit_button();
		echo '</form></div>';
	}

	/**
	 * The read-only readiness list: [ [ 'label', 'ok', 'detail' ], … ].
	 */
	public static function readiness(): array {
		$rows = [];
		$add  = static function ( string $label, bool $ok, string $detail = '' ) use ( &$rows ): void {
			$rows[] = [
				'label'  => $label,
				'ok'     => $ok,
				'detail' => $detail,
			];
		};
		$add( __( 'נמען לבקשות ביקור', 'nevehairus-site' ), '' !== NH_Settings::recipient( 'visit' ) );
		$add( __( 'נמען למועמדויות', 'nevehairus-site' ), '' !== NH_Settings::recipient( 'jobs' ) );
		$add( __( 'עמוד מדיניות הפרטיות (הגדרות ← פרטיות)', 'nevehairus-site' ), '' !== (string) get_privacy_policy_url() );
		$add( __( 'עמוד המשרות', 'nevehairus-site' ), '' !== NH_Settings::jobs_url() );
		$add( __( 'הצהרת הנגישות', 'nevehairus-site' ), '' !== NH_Settings::page_url( 'accessibility' ) );

		$version = defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : '';
		$add( __( 'Elementor', 'nevehairus-site' ), NH_Plugin::elementor_ok(), '' !== $version ? $version : __( 'לא פעיל', 'nevehairus-site' ) );

		$template = function_exists( 'get_template' ) ? (string) get_template() : '';
		$child    = function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '';
		$add(
			__( 'ערכת עיצוב Hello Elementor עם ערכת בת', 'nevehairus-site' ),
			'hello-elementor' === $template && $child !== $template,
			$child
		);

		$layout = '';
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->kits_manager ) && is_object( \Elementor\Plugin::$instance->kits_manager )
			&& method_exists( \Elementor\Plugin::$instance->kits_manager, 'get_current_settings' ) ) {
			$layout = (string) \Elementor\Plugin::$instance->kits_manager->get_current_settings( 'default_page_template' );
		}
		$add( __( 'פריסת ברירת המחדל של Elementor: Elementor Full Width', 'nevehairus-site' ), 'elementor_header_footer' === $layout, $layout );

		// Widget files are discovered when Elementor builds its widget list, which a request for this page does
		// not do: discover them here (the same files and checks as on any Elementor page), so a skipped one shows.
		if ( NH_Plugin::elementor_ok() ) {
			NH_Plugin::widgets();
		}
		foreach ( NH_Plugin::skipped() as $id => $reason ) {
			/* translators: %s: widget id */
			$add( sprintf( __( 'ווידג׳ט %s', 'nevehairus-site' ), $id ), false, $reason );
		}
		return $rows;
	}

	/**
	 * Admin notices: a missing recipient (Dashboard, Plugins, Pages, this page) and the test e-mail result.
	 */
	public static function notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = is_object( $screen ) && isset( $screen->id ) ? (string) $screen->id : '';

		if ( in_array( $id, [ 'dashboard', 'plugins', 'edit-page', 'settings_page_' . self::SLUG ], true )
			&& ( '' === NH_Settings::recipient( 'visit' ) || '' === NH_Settings::recipient( 'jobs' ) ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( NH_Forms::missing_recipient_text() )
				. ' <a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'להגדרות', 'nevehairus-site' ) . '</a></p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only (result of test_mail())
		$result = isset( $_GET['nh_test'] ) && is_string( $_GET['nh_test'] ) ? sanitize_key( wp_unslash( $_GET['nh_test'] ) ) : '';
		if ( 'settings_page_' . self::SLUG === $id && '' !== $result ) {
			$texts = [
				'none' => __( 'אין נמענים מוגדרים: לא נשלח דבר.', 'nevehairus-site' ),
				'ok'   => __( 'דוא״ל הבדיקה נשלח לכל הנמענים (בדקו גם בתיקיית הספאם).', 'nevehairus-site' ),
				'fail' => __( 'שליחת דוא״ל הבדיקה נכשלה לפחות לנמען אחד: כדאי לבדוק את הגדרות הדואר של השרת.', 'nevehairus-site' ),
			];
			if ( isset( $texts[ $result ] ) ) {
				echo '<div class="notice notice-' . ( 'ok' === $result ? 'success' : 'warning' ) . '"><p>' . esc_html( $texts[ $result ] ) . '</p></div>';
			}
		}
	}

	/**
	 * admin-post `nh_test_mail`: a short plain-text message to each configured recipient; the result
	 * (never the addresses) goes back in the URL.
	 */
	public static function test_mail(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'אין הרשאה.', 'nevehairus-site' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'nh_test_mail' );

		$to = array_values( array_unique( array_filter( [ NH_Settings::recipient( 'visit' ), NH_Settings::recipient( 'jobs' ) ] ) ) );
		if ( ! $to ) {
			$result = 'none';
		} else {
			$ok = true;
			foreach ( $to as $address ) {
				$ok = wp_mail(
					$address,
					sprintf( 'בדיקת דוא״ל — %s', wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) ),
					"זוהי הודעת בדיקה מהגדרות האתר (נווה האירוס).\nאם היא הגיעה, הטפסים יכולים לשלוח לכתובת הזאת.\n" . wp_date( 'Y-m-d H:i' ),
					[ 'Content-Type: text/plain; charset=UTF-8' ]
				) && $ok;
			}
			$result = $ok ? 'ok' : 'fail';
		}
		wp_safe_redirect( add_query_arg( 'nh_test', $result, admin_url( 'options-general.php?page=' . self::SLUG ) ) );
		NH_Forms::halt();
	}
}
