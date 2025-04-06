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

    /**
     * Store original state in case we need to fallback
     */
    private $original_phpmailer_state = [];

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
        add_option('wp_resend_mailer_fallback_enabled', '0');
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

        // Store original state in case we need to fallback
        $this->original_phpmailer_state = [
            'to' => $phpmailer->getToAddresses(),
            'cc' => $phpmailer->getCcAddresses(),
            'bcc' => $phpmailer->getBccAddresses(),
            'reply_to' => $phpmailer->getReplyToAddresses(),
            'subject' => $phpmailer->Subject,
            'body' => $phpmailer->Body,
            'alt_body' => $phpmailer->AltBody,
            'headers' => $phpmailer->getCustomHeaders(),
            'attachments' => $phpmailer->getAttachments(),
            'sender' => $phpmailer->Sender,
            // Store other potentially relevant properties if needed
        ];

        $phpmailer->clearAllRecipients();
        $phpmailer->clearAttachments();
        $phpmailer->clearCustomHeaders();
        $phpmailer->clearReplyTos();
        $phpmailer->Sender = '';

        $phpmailer->Mailer = 'wp_resend_mailer'; // Set a custom mailer type

        $sent_via_resend = $this->send_via_resend_api();

        if ($sent_via_resend) {
            // Prevent default WordPress sending by setting a custom mailer type
            // and clearing properties PHPMailer uses internally for sending.
            $phpmailer->Mailer = 'wp_resend_mailer';
            $phpmailer->clearAllRecipients();
            $phpmailer->clearAttachments();
            $phpmailer->clearCustomHeaders();
            $phpmailer->clearReplyTos();
            $phpmailer->Body = '';
            $phpmailer->AltBody = '';
            $phpmailer->Subject = '';
            $phpmailer->Sender = '';
        } elseif (get_option('wp_resend_mailer_fallback_enabled') == '1') {
            // Resend failed, fallback enabled: Restore original state and let PHPMailer continue.
            error_log('WP Resend Mailer: Sending via Resend failed. Attempting fallback to default WordPress mailer.');

            // Restore original state - PHPMailer setters might be needed for some properties
            $phpmailer->clearAllRecipients(); // Clear previous attempts
            foreach ($this->original_phpmailer_state['to'] as $recipient) {
                $phpmailer->addAddress($recipient[0], $recipient[1]);
            }
            $phpmailer->clearCCs();
            foreach ($this->original_phpmailer_state['cc'] as $recipient) {
                $phpmailer->addCC($recipient[0], $recipient[1]);
            }
            $phpmailer->clearBCCs();
            foreach ($this->original_phpmailer_state['bcc'] as $recipient) {
                $phpmailer->addBCC($recipient[0], $recipient[1]);
            }
            $phpmailer->clearReplyTos();
            foreach ($this->original_phpmailer_state['reply_to'] as $address => $name) {
                 $phpmailer->addReplyTo($address, $name);
            }
            $phpmailer->Subject = $this->original_phpmailer_state['subject'];
            $phpmailer->Body = $this->original_phpmailer_state['body'];
            $phpmailer->AltBody = $this->original_phpmailer_state['alt_body'];

            $phpmailer->clearCustomHeaders();
             foreach ($this->original_phpmailer_state['headers'] as $header) {
                 $phpmailer->addCustomHeader($header[0], $header[1]);
             }
            $phpmailer->clearAttachments();
            foreach ($this->original_phpmailer_state['attachments'] as $attachment) {
                 // Use the correct method signature based on PHPMailer version (path, name, encoding, type, disposition)
                 // Assuming addAttachment exists with this signature
                 try {
                     $phpmailer->addAttachment($attachment[0], $attachment[1], $attachment[2], $attachment[3], $attachment[4]);
                 } catch (Exception $e) {
                     error_log('WP Resend Mailer Fallback Error: Could not re-attach file ' . $attachment[1] . ': ' . $e->getMessage());
                 }
             }
            $phpmailer->Sender = $this->original_phpmailer_state['sender'];

            // IMPORTANT: Do NOT set $phpmailer->Mailer = 'wp_resend_mailer';
            // Let PHPMailer use its default Mailer type (mail, sendmail, smtp)

        } else {
            // Resend failed, fallback disabled: Prevent default sending as well.
             $phpmailer->Mailer = 'wp_resend_mailer'; // Still set custom mailer to prevent WP default sending.
             error_log('WP Resend Mailer: Sending via Resend failed. Fallback is disabled.');
             // Optionally clear properties like above if needed to ensure no sending attempt occurs.
             $phpmailer->clearAllRecipients();
             $phpmailer->Body = ''; 
        }
    }

    /**
     * Prepare and send email using the Resend API handler.
     * @return bool True if sent successfully via Resend, false otherwise.
     */
    private function send_via_resend_api() {
        $from_email = get_option('wp_resend_mailer_from_email');
        $from_name = get_option('wp_resend_mailer_from_name');

        $result = $this->api_handler->send_email(
            $from_email,
            $from_name,
            $this->original_phpmailer_state['to'],
            $this->original_phpmailer_state['subject'],
            $this->original_phpmailer_state['body'],
            $this->original_phpmailer_state['alt_body'],
            $this->original_phpmailer_state['headers'],
            $this->original_phpmailer_state['attachments'],
            $this->original_phpmailer_state['cc'],
            $this->original_phpmailer_state['bcc'],
            $this->original_phpmailer_state['reply_to']
        );

        if (!$result) {
            // Optionally trigger an action or log more details if sending failed
            do_action('wp_resend_mailer_send_failed', $this->original_phpmailer_state['to'], $this->original_phpmailer_state['subject']);
            return false;
        }

        return true; // Sent successfully via Resend
    }
} 