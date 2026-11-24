<?php
// Remove the plugin's settings when it's deleted from Plugins.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
foreach (['api_key', 'from_email', 'from_name', 'enabled', 'fallback_enabled', 'last'] as $key) {
    delete_option("wp_resend_mailer_$key");
}
