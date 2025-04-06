<?php

if (!defined('WPINC')) {
    die;
}

class WP_Resend_Mailer_Admin {

    /**
     * Instance of the API handler class.
     * Needed for the AJAX test email.
     * @var WP_Resend_Mailer_Api
     */
    private $api_handler;

    const SETTINGS_PAGE_SLUG = 'wp-resend-mailer';

    public function __construct(WP_Resend_Mailer_Api $api_handler) {
        $this->api_handler = $api_handler;
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Hook the AJAX action to the method in the API handler instance
        add_action('wp_ajax_wp_resend_mailer_test', array($this->api_handler, 'handle_test_email_ajax'));
        // Hook the AJAX action for health check
        add_action('wp_ajax_wp_resend_mailer_health_check', array($this->api_handler, 'handle_health_check_ajax'));
    }

    public function add_admin_menu() {
        add_options_page(
            __('WP Resend Mailer Settings', 'wp-resend-mailer'),
            __('Resend Mailer', 'wp-resend-mailer'),
            'manage_options',
            self::SETTINGS_PAGE_SLUG,
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        $settings_group = 'wp_resend_mailer_settings';

        register_setting($settings_group, 'wp_resend_mailer_api_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting($settings_group, 'wp_resend_mailer_from_email', ['sanitize_callback' => 'sanitize_email']);
        register_setting($settings_group, 'wp_resend_mailer_from_name', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting($settings_group, 'wp_resend_mailer_enabled', ['sanitize_callback' => 'rest_sanitize_boolean']);
        register_setting($settings_group, 'wp_resend_mailer_fallback_enabled', ['sanitize_callback' => 'rest_sanitize_boolean']);

        add_settings_section(
            'wp_resend_mailer_settings_section',
            __('API Settings', 'wp-resend-mailer'),
            array($this, 'settings_section_callback'),
            $settings_group 
        );

        add_settings_field(
            'wp_resend_mailer_api_key',
            __('Resend API Key', 'wp-resend-mailer'),
            array($this, 'api_key_field_callback'),
            $settings_group,
            'wp_resend_mailer_settings_section' 
        );

        add_settings_field(
            'wp_resend_mailer_from_email',
            __('From Email Address', 'wp-resend-mailer'),
            array($this, 'from_email_field_callback'),
            $settings_group,
            'wp_resend_mailer_settings_section'
        );

        add_settings_field(
            'wp_resend_mailer_from_name',
            __('From Name', 'wp-resend-mailer'),
            array($this, 'from_name_field_callback'),
            $settings_group,
            'wp_resend_mailer_settings_section'
        );

        add_settings_field(
            'wp_resend_mailer_enabled',
            __('Enable Resend', 'wp-resend-mailer'),
            array($this, 'enabled_field_callback'),
            $settings_group,
            'wp_resend_mailer_settings_section'
        );

        add_settings_field(
            'wp_resend_mailer_fallback_enabled',
            __('Enable Fallback', 'wp-resend-mailer'),
            array($this, 'fallback_enabled_field_callback'),
            $settings_group,
            'wp_resend_mailer_settings_section'
        );
    }

    public function enqueue_admin_scripts($hook_suffix) {
        if ('settings_page_' . self::SETTINGS_PAGE_SLUG !== $hook_suffix) {
            return;
        }

        wp_enqueue_script(
            'wp-resend-mailer-admin-js',
            WP_RESEND_MAILER_PLUGIN_URL . 'assets/js/admin-settings.js',
            array('jquery'),
            WP_RESEND_MAILER_VERSION,
            true
        );

        wp_localize_script('wp-resend-mailer-admin-js', 'wpResendMailer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_resend_mailer_test_nonce'),
            'health_check_nonce' => wp_create_nonce('wp_resend_mailer_health_check_nonce'),
            'sending_text' => esc_html__('Sending test email...', 'wp-resend-mailer'),
            'checking_text' => esc_html__('Checking connection...', 'wp-resend-mailer'),
            'error_generic' => esc_html__('An error occurred. Please try again.', 'wp-resend-mailer'),
            'error_no_email' => esc_html__('Please enter a valid email address.', 'wp-resend-mailer'),
        ));
    }

    public function settings_section_callback() {
        echo '<p>' . esc_html__('Configure your Resend API settings below.', 'wp-resend-mailer') . '</p>';
    }

    public function api_key_field_callback() {
        $api_key = get_option('wp_resend_mailer_api_key');
        echo '<input type="password" id="wp_resend_mailer_api_key" name="wp_resend_mailer_api_key" value="' . esc_attr($api_key) . '" class="regular-text" autocomplete="new-password" />';
        echo '<p class="description">';
        printf(
            wp_kses(
                /* translators: %s: URL to Resend API keys page */
                __('Enter your Resend API key. <a href="%s" target="_blank">Get it here</a>.', 'wp-resend-mailer'),
                ['a' => ['href' => [], 'target' => []]]
            ),
            esc_url('https://resend.com/api-keys')
        );
        echo '</p>';
    }

    public function from_email_field_callback() {
        $from_email = get_option('wp_resend_mailer_from_email');
        echo '<input type="email" id="wp_resend_mailer_from_email" name="wp_resend_mailer_from_email" value="' . esc_attr($from_email) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('The email address that emails will be sent from. This must be a verified domain in your Resend account.', 'wp-resend-mailer') . '</p>';
    }

    public function from_name_field_callback() {
        $from_name = get_option('wp_resend_mailer_from_name');
        echo '<input type="text" id="wp_resend_mailer_from_name" name="wp_resend_mailer_from_name" value="' . esc_attr($from_name) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('The name that emails will be sent from.', 'wp-resend-mailer') . '</p>';
    }

    public function enabled_field_callback() {
        $enabled = get_option('wp_resend_mailer_enabled');
        echo '<input type="checkbox" id="wp_resend_mailer_enabled" name="wp_resend_mailer_enabled" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label for="wp_resend_mailer_enabled">' . esc_html__('Enable to use Resend for sending WordPress emails.', 'wp-resend-mailer') . '</label>';
    }

    public function fallback_enabled_field_callback() {
        $enabled = get_option('wp_resend_mailer_fallback_enabled');
        echo '<input type="checkbox" id="wp_resend_mailer_fallback_enabled" name="wp_resend_mailer_fallback_enabled" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label for="wp_resend_mailer_fallback_enabled">' . esc_html__('If sending via Resend fails, attempt to send using the default WordPress mailer instead.', 'wp-resend-mailer') . '</label>';
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Resend Email Settings', 'wp-resend-mailer' ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('wp_resend_mailer_settings');
                do_settings_sections('wp_resend_mailer_settings');
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php echo esc_html__( 'Send Test Email', 'wp-resend-mailer' ); ?></h2>
            <p><?php echo esc_html__( 'Send a test email to verify your Resend configuration.', 'wp-resend-mailer' ); ?></p>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="test_email"><?php echo esc_html__( 'Recipient Email', 'wp-resend-mailer' ); ?></label>
                        </th>
                        <td>
                            <input type="email" id="test_email" placeholder="<?php echo esc_attr__( 'Enter email address', 'wp-resend-mailer' ); ?>" class="regular-text" />
                            <button type="button" id="send_test_email" class="button button-secondary" style="margin-left: 10px;">
                                <?php echo esc_html__( 'Send Test Email', 'wp-resend-mailer' ); ?>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div id="test_result" style="margin-top: 10px;"></div>
            
            <?php // Note: The JavaScript is now enqueued via enqueue_admin_scripts ?>
        </div>
        <hr>
        <h2><?php echo esc_html__( 'Connection Status', 'wp-resend-mailer' ); ?></h2>
        <p><?php echo esc_html__( 'Check if the plugin can connect to the Resend API with your current settings.', 'wp-resend-mailer' ); ?></p>
        <button type="button" id="check_connection" class="button button-secondary">
            <?php echo esc_html__( 'Check Connection', 'wp-resend-mailer' ); ?>
        </button>
        <div id="connection_status" style="margin-top: 10px;"></div>
        <?php
    }
} 