<?php
/**
 * Plugin Name: WP Resend Mailer
 * Plugin URI: https://github.com/kriiv/wp-resend-mailer
 * Description: WordPress plugin to send emails using Resend API
 * Version: 1.0.0
 * Author: Martin Krivosija
 * Author URI: https://retaind.com
 * License: GPL-2.0+
 * Text Domain: wp-resend-mailer
 */

if (!defined('WPINC')) {
    die('Access denied.');
}

define('WP_RESEND_MAILER_VERSION', '1.0.0');
define('WP_RESEND_MAILER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_RESEND_MAILER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_RESEND_MAILER_PLUGIN_FILE', __FILE__);

require_once WP_RESEND_MAILER_PLUGIN_DIR . 'includes/class-wp-resend-mailer-api.php';
require_once WP_RESEND_MAILER_PLUGIN_DIR . 'includes/class-wp-resend-mailer-admin.php';
require_once WP_RESEND_MAILER_PLUGIN_DIR . 'includes/class-wp-resend-mailer.php';

register_activation_hook(WP_RESEND_MAILER_PLUGIN_FILE, array('WP_Resend_Mailer', 'activate'));
register_deactivation_hook(WP_RESEND_MAILER_PLUGIN_FILE, array('WP_Resend_Mailer', 'deactivate'));

function wp_resend_mailer_init() {
    $api_handler = new WP_Resend_Mailer_Api();
    $admin_handler = new WP_Resend_Mailer_Admin($api_handler);

    $plugin = new WP_Resend_Mailer($api_handler, $admin_handler);
}

add_action('plugins_loaded', 'wp_resend_mailer_init');