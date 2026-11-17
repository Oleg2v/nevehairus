<?php
/**
 * Plugin Name: נווה האירוס — אתר
 * Description: Elementor widgets and site module for the v3.2 design. Loads its CSS and JS only on pages that use its widgets.
 * Version: 0.2.5
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Author: קורנגה
 * Author URI: https://koranga.co.il/
 * Text Domain: nevehairus-site
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Elementor tested up to: 4.4.0
 * Elementor Pro tested up to: 4.4.0
 * Update URI: false
 *
 * @package nevehairus-site
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'NH_VERSION' ) ) {
	return; // Loaded twice (a copy of the plugin in another folder): keep the first one.
}

define( 'NH_VERSION', '0.2.5' );
define( 'NH_FILE', __FILE__ );
define( 'NH_DIR', plugin_dir_path( __FILE__ ) );
define( 'NH_URL', plugin_dir_url( __FILE__ ) );
define( 'NH_MIN_ELEMENTOR', '3.24.0' );

/*
 * Requirement check. WordPress refuses to activate the plugin on PHP < 8.0 ("Requires PHP"), but a
 * server can be downgraded later: then the classes (PHP 8.0 syntax) are not loaded and an admin
 * notice says why. This file itself stays PHP 7 compatible for that reason.
 */
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'התוסף „נווה האירוס — אתר” צריך PHP 8.0 ומעלה. התוסף כבוי עד לעדכון גרסת ה־PHP.', 'nevehairus-site' ) . '</p></div>';
			}
		}
	);
	return;
}

/*
 * Core classes. NH_Widget_Base extends \Elementor\Widget_Base, so it is loaded only when Elementor is
 * there (NH_Plugin::widgets()); every other class works without Elementor.
 */
foreach (
	[
		'class-nh-plugin.php',
		'class-nh-assets.php',
		'class-nh-controls.php',
		'class-nh-text.php',
		'class-nh-html.php',
		'class-nh-letters.php',
		'class-nh-picture.php',
		'class-nh-settings.php',
		'class-nh-settings-page.php',
		'class-nh-site.php',
		'class-nh-forms.php',
		'class-nh-compat.php',
	] as $nh_class_file
) {
	require_once NH_DIR . 'includes/' . $nh_class_file;
}
unset( $nh_class_file );

/*
 * Activation and deactivation clear Elementor's caches (research/07 §12.3): its element cache keeps the HTML
 * of static widgets for up to a day, which must not outlive the plugin, nor survive into a new version.
 */
register_activation_hook( __FILE__, [ 'NH_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'NH_Plugin', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'NH_Plugin', 'boot' ] );
