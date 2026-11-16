<?php
defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Uninstall: the plugin's only stored data are the options nh_settings and nh_version (form submissions
 * are never stored; the rate-limit transients expire by themselves within minutes). Elementor's caches
 * were already cleared when the plugin was deactivated (NH_Plugin::deactivate()).
 *
 * @package nevehairus-site
 */
delete_option( 'nh_settings' );
delete_option( 'nh_version' );
