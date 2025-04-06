<?php

if (!defined('WPINC')) {
    die;
}

class WP_Resend_Mailer {

    /**
     * Instance of the API handler class.
     * @var WP_Resend_Mailer_Api
     */
    private $api_handler;

    /**
     * Instance of the Admin handler class.
     * @var WP_Resend_Mailer_Admin
     */
    private $admin_handler;

    private $to;
    private $subject;
    private $message;
    private $headers;
    private $attachments;
    private $cc;
    private $bcc;

    public function __construct(WP_Resend_Mailer_Api $api_handler, WP_Resend_Mailer_Admin $admin_handler) {
        $this->api_handler = $api_handler;
        $this->admin_handler = $admin_handler;

        // hook into phpmailer
        add_action('phpmailer_init', array($this, 'setup_phpmailer'), 10, 1);
    }

    public static function activate() {
        // Add default options
        add_option('wp_resend_mailer_api_key', '');
        add_option('wp_resend_mailer_from_email', get_option('admin_email'));
        add_option('wp_resend_mailer_from_name', get_option('blogname'));
        add_option('wp_resend_mailer_enabled', '0');
    }

    public static function deactivate() {
        // We'll keep the settings in the database
    }

    public function setup_phpmailer($phpmailer) {
        if (get_option('wp_resend_mailer_enabled') != '1') {
            return;
        }
        $api_key = get_option('wp_resend_mailer_api_key');
        if (empty($api_key)) {
            error_log('WP Resend Mailer: API key is not set.');
            return;
        }

        $this->to = $phpmailer->getToAddresses();
        $this->cc = $phpmailer->getCcAddresses();
        $this->bcc = $phpmailer->getBccAddresses();
        $this->subject = $phpmailer->Subject;
        $this->message = $phpmailer->Body; // Assuming HTML body, might need AltBody too
        $this->headers = $phpmailer->getCustomHeaders();
        $this->attachments = $phpmailer->getAttachments();

        $phpmailer->clearAllRecipients();
        $phpmailer->clearAttachments();
        $phpmailer->clearCustomHeaders();
        $phpmailer->clearReplyTos();
        $phpmailer->Sender = '';

        $phpmailer->Mailer = 'wp_resend_mailer'; // Set a custom mailer type

        $this->send_via_resend_api();
    }

    private function send_via_resend_api() {
        $from_email = get_option('wp_resend_mailer_from_email');
        $from_name = get_option('wp_resend_mailer_from_name');

        $result = $this->api_handler->send_email(
            $from_email,
            $from_name,
            $this->to,
            $this->subject,
            $this->message,
            $this->headers,
            $this->attachments,
            $this->cc,
            $this->bcc
        );

        if (!$result) {
            do_action('wp_resend_mailer_send_failed', $this->to, $this->subject);
        }
    }
} 