<?php
/** Settings → Resend: the settings, a test email and a connection check. */
if (!defined('ABSPATH')) {
    exit;
}

class WP_Resend_Mailer_Admin {

    const SLUG = 'wp-resend-mailer';

    public function __construct() {
        add_action('admin_menu', fn () => add_options_page('Resend', 'Resend', 'manage_options', self::SLUG, [$this, 'render']));
        add_action('admin_init', [$this, 'register']);
        add_action('admin_post_wp_resend_mailer_test', [$this, 'send_test']);
        add_action('admin_post_wp_resend_mailer_check', [$this, 'check']);
        add_filter('plugin_action_links_' . plugin_basename(WP_RESEND_MAILER_FILE), function ($links) {
            array_unshift($links, '<a href="' . esc_url(self::url()) . '">Settings</a>');
            return $links;
        });
    }

    public static function url(array $args = []): string {
        return add_query_arg($args, admin_url('options-general.php?page=' . self::SLUG));
    }

    public function register(): void {
        $group = 'wp_resend_mailer';
        register_setting($group, 'wp_resend_mailer_api_key', ['sanitize_callback' => fn ($v) => trim(sanitize_text_field((string) $v))]);
        register_setting($group, 'wp_resend_mailer_from_email', ['sanitize_callback' => 'sanitize_email']);
        register_setting($group, 'wp_resend_mailer_from_name', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting($group, 'wp_resend_mailer_enabled', ['sanitize_callback' => fn ($v) => $v ? '1' : '0']);
        register_setting($group, 'wp_resend_mailer_fallback_enabled', ['sanitize_callback' => fn ($v) => $v ? '1' : '0']);
    }

    /** Send a test through wp_mail(), the same path every other email takes. */
    public function send_test(): void {
        check_admin_referer('wp_resend_mailer_test');
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }
        $to = sanitize_email(wp_unslash($_POST['to'] ?? ''));
        if (!is_email($to)) {
            $this->done(false, 'Enter a valid email address.');
        }
        if (!wp_resend_mailer_is_active()) {
            $this->done(false, 'Resend isn’t switched on yet: add the API key and From address, and tick "Send emails through Resend".');
        }
        $error = '';
        $catch = function (WP_Error $e) use (&$error) { $error = $e->get_error_message(); };
        add_action('wp_mail_failed', $catch);
        $sent = wp_mail($to, 'Test email from ' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), "This test email came from " . home_url('/') . " through Resend.\n\nIf you're reading it, the site's emails are working.");
        remove_action('wp_mail_failed', $catch);
        $this->done($sent, $sent ? "Sent to $to. Check the inbox (and the Emails list in Resend)." : "Not sent. $error");
    }

    public function check(): void {
        check_admin_referer('wp_resend_mailer_check');
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }
        $result = WP_Resend_Mailer_Api::check();
        $this->done($result['ok'], $result['message']);
    }

    private function done(bool $ok, string $message): void {
        set_transient('wp_resend_mailer_notice_' . get_current_user_id(), ['ok' => $ok, 'message' => $message], 60);
        wp_safe_redirect(self::url());
        exit;
    }

    public function render(): void {
        $s = wp_resend_mailer_settings();
        $active = wp_resend_mailer_is_active();
        $last = get_option('wp_resend_mailer_last');
        $notice = get_transient('wp_resend_mailer_notice_' . get_current_user_id());
        delete_transient('wp_resend_mailer_notice_' . get_current_user_id());
        $locked = fn (string $const) => defined($const) ? sprintf('<p class="description">Set in wp-config.php (<code>%s</code>).</p>', $const) : '';
        ?>
        <div class="wrap">
            <h1>Resend</h1>
            <?php if ($notice) : ?>
                <div class="notice notice-<?php echo $notice['ok'] ? 'success' : 'error'; ?>"><p><?php echo esc_html($notice['message']); ?></p></div>
            <?php endif; ?>

            <p><strong>Status:</strong>
                <?php echo $active ? 'Emails from this site go through Resend.' : 'Off. Emails use the server’s own mail until the API key and From address are set.'; ?>
                <?php if ($last) : ?>
                    <br><strong>Last email:</strong> <?php echo esc_html(($last['ok'] ? '' : 'FAILED. ') . $last['message'] . ', ' . wp_date('j M Y g:ia', $last['time'])); ?>
                <?php endif; ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields('wp_resend_mailer'); ?>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th scope="row"><label for="wrm-key">API key</label></th><td>
                        <?php if (defined('RESEND_API_KEY')) : echo $locked('RESEND_API_KEY'); else : ?>
                            <input type="password" id="wrm-key" name="wp_resend_mailer_api_key" value="<?php echo esc_attr(get_option('wp_resend_mailer_api_key')); ?>" class="regular-text" autocomplete="new-password" spellcheck="false">
                            <p class="description">From <a href="https://resend.com/api-keys" target="_blank" rel="noopener">resend.com/api-keys</a>. A "Sending access" key limited to your domain is enough. To keep it out of the database, put <code>define('RESEND_API_KEY', 're_…');</code> in wp-config.php instead.</p>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th scope="row"><label for="wrm-from">From address</label></th><td>
                        <?php if (defined('RESEND_FROM_EMAIL')) : echo '<code>' . esc_html(RESEND_FROM_EMAIL) . '</code>' . $locked('RESEND_FROM_EMAIL'); else : ?>
                            <input type="email" id="wrm-from" name="wp_resend_mailer_from_email" value="<?php echo esc_attr(get_option('wp_resend_mailer_from_email')); ?>" class="regular-text" placeholder="hello@yourdomain.com">
                            <p class="description">On a domain verified in Resend. Every email is sent from this address.</p>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th scope="row"><label for="wrm-name">From name</label></th><td>
                        <?php if (defined('RESEND_FROM_NAME')) : echo esc_html(RESEND_FROM_NAME) . $locked('RESEND_FROM_NAME'); else : ?>
                            <input type="text" id="wrm-name" name="wp_resend_mailer_from_name" value="<?php echo esc_attr(get_option('wp_resend_mailer_from_name')); ?>" class="regular-text">
                            <p class="description">Used when the email doesn’t set its own (WooCommerce emails use the shop name).</p>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th scope="row">Sending</th><td>
                        <input type="hidden" name="wp_resend_mailer_enabled" value="0">
                        <label><input type="checkbox" name="wp_resend_mailer_enabled" value="1" <?php checked($s['enabled']); ?>> Send emails through Resend</label><br>
                        <input type="hidden" name="wp_resend_mailer_fallback_enabled" value="0">
                        <label><input type="checkbox" name="wp_resend_mailer_fallback_enabled" value="1" <?php checked($s['fallback']); ?>> If Resend fails, try the server’s own mail</label>
                        <p class="description">Leave the second one off unless your host’s mail is set up: mail from an unverified server usually lands in spam.</p>
                    </td></tr>
                </tbody></table>
                <?php submit_button(); ?>
            </form>

            <h2>Test</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <?php wp_nonce_field('wp_resend_mailer_test'); ?>
                <input type="hidden" name="action" value="wp_resend_mailer_test">
                <input type="email" name="to" class="regular-text" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" aria-label="Send a test email to">
                <?php submit_button('Send test email', 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px">
                <?php wp_nonce_field('wp_resend_mailer_check'); ?>
                <input type="hidden" name="action" value="wp_resend_mailer_check">
                <?php submit_button('Check key and domain', 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }
}
