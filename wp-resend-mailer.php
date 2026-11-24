<?php
/**
 * Plugin Name: WP Resend Mailer
 * Plugin URI: https://github.com/kriiv/wp-resend-mailer
 * Description: Sends every WordPress email through the Resend API.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Martin Krivosija
 * Author URI: https://retaind.com
 * License: GPL-2.0+
 * Text Domain: wp-resend-mailer
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_RESEND_MAILER_VERSION', '2.0.0');
define('WP_RESEND_MAILER_FILE', __FILE__);

require_once __DIR__ . '/includes/class-wp-resend-mailer-api.php';
require_once __DIR__ . '/includes/class-wp-resend-mailer-admin.php';

/**
 * The settings in use. wp-config.php constants win over the settings page:
 *   define('RESEND_API_KEY', 're_...');           // keeps the key out of the database
 *   define('RESEND_FROM_EMAIL', 'hello@example.com');
 *   define('RESEND_FROM_NAME', 'Example');
 */
function wp_resend_mailer_settings(): array {
    return [
        'api_key'    => defined('RESEND_API_KEY') ? (string) RESEND_API_KEY : (string) get_option('wp_resend_mailer_api_key', ''),
        'from_email' => defined('RESEND_FROM_EMAIL') ? (string) RESEND_FROM_EMAIL : (string) get_option('wp_resend_mailer_from_email', ''),
        'from_name'  => defined('RESEND_FROM_NAME') ? (string) RESEND_FROM_NAME : (string) (get_option('wp_resend_mailer_from_name') ?: get_option('blogname')),
        'enabled'    => get_option('wp_resend_mailer_enabled', '1') === '1',
        'fallback'   => get_option('wp_resend_mailer_fallback_enabled', '0') === '1',
    ];
}

/** True when emails are going through Resend: switched on, with a key and a valid From address. */
function wp_resend_mailer_is_active(): bool {
    $s = wp_resend_mailer_settings();
    return $s['enabled'] && $s['api_key'] !== '' && is_email($s['from_email']);
}

/**
 * Hand wp_mail() our mailer. WordPress still builds each message (From, content type, CC, Reply-To, attachments,
 * and every plugin's filters); only the final send goes to Resend instead of PHP's mail().
 */
add_action('plugins_loaded', function () {
    if (!wp_resend_mailer_is_active()) {
        return;
    }
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
    require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    if (file_exists(ABSPATH . WPINC . '/class-wp-phpmailer.php')) {
        require_once ABSPATH . WPINC . '/class-wp-phpmailer.php';
    }
    require_once __DIR__ . '/includes/class-wp-resend-mailer-phpmailer.php';

    global $phpmailer;
    $phpmailer = new WP_Resend_Mailer_PHPMailer(true);
    $phpmailer::$validator = static fn ($email) => (bool) is_email($email);
}, 1);

register_activation_hook(__FILE__, function () {
    add_option('wp_resend_mailer_from_email', '');
    add_option('wp_resend_mailer_from_name', get_option('blogname'));
    add_option('wp_resend_mailer_enabled', '1');
    add_option('wp_resend_mailer_fallback_enabled', '0');
});

new WP_Resend_Mailer_Admin();
