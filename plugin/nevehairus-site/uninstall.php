<?php
defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Uninstall: the plugin's only stored data is the option nh_settings (form submissions are never
 * stored; the rate-limit transients expire by themselves within minutes).
 *
 * @package nevehairus-site
 */
delete_option( 'nh_settings' );
