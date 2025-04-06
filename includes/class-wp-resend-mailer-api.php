<?php

if (!defined('WPINC')) {
    die;
}

class WP_Resend_Mailer_Api {

    /**
     * Send email using Resend API.
     *
     * @param string $from_email
     * @param string $from_name
     * @param array $to Array of recipient arrays like [[email, name], ...]
     * @param string $subject
     * @param string $message HTML content
     * @param string $alt_body Plain text body
     * @param array $headers Array of custom headers
     * @param array $attachments Array of attachment details from PHPMailer
     * @param array $cc Array of CC recipient arrays like [[email, name], ...]
     * @param array $bcc Array of BCC recipient arrays like [[email, name], ...]
     * @param array $reply_to_recipients Array of Reply-To recipient arrays like [[email, name], ...]
     * @return bool True on success, false on failure.
     */
    public function send_email($from_email, $from_name, $to_recipients, $subject, $message, $alt_body, $headers, $attachments, $cc_recipients = [], $bcc_recipients = [], $reply_to_recipients = []) {
        $api_key = get_option('wp_resend_mailer_api_key');

        if (empty($api_key)) {
            error_log('WP Resend Mailer Error: API key is missing.');
            return false;
        }

        // Format recipients (extract emails)
        $to = array_map(function($recipient) { return $recipient[0]; }, $to_recipients);

        $data = [
            'from' => !empty($from_name) ? $from_name . ' <' . $from_email . '>' : $from_email,
            'to' => $to,
            'subject' => $subject,
            'html' => $message,
            'text' => !empty($alt_body) ? $alt_body : null,
        ];

        if (!empty($cc_recipients)) {
            $data['cc'] = array_map(function($recipient) { return $recipient[0]; }, $cc_recipients);
        }
        if (!empty($bcc_recipients)) {
            $data['bcc'] = array_map(function($recipient) { return $recipient[0]; }, $bcc_recipients);
        }

        // Add Reply-To if present
        if (!empty($reply_to_recipients)) {
            // Resend expects a single email address string or an array of emails for reply_to
            // PHPMailer reply_to is an associative array [email => name]
            $reply_to_emails = array_keys($reply_to_recipients);
            if (!empty($reply_to_emails)) {
                 // Use the first reply-to email, as Resend API might prefer a single primary one or format might vary.
                 // If multiple are strictly needed, check Resend documentation for array format.
                $data['reply_to'] = $reply_to_emails[0]; 
            }
        }

        // Handle attachments
        if (!empty($attachments)) {
            $data['attachments'] = [];
            foreach ($attachments as $attachment) {
                // $attachment structure from PHPMailer: [path, name, encoding, type, disposition]
                $file_path = $attachment[0];
                $filename = !empty($attachment[1]) ? $attachment[1] : basename($file_path);
                
                if (file_exists($file_path)) {
                    $content = file_get_contents($file_path);
                    if ($content !== false) {
                        $data['attachments'][] = [
                            'filename' => $filename,
                            'content' => base64_encode($content),
                        ];
                    } else {
                         error_log('WP Resend Mailer Error: Could not read attachment file: ' . $file_path);
                    }
                } else {
                     error_log('WP Resend Mailer Error: Attachment file not found: ' . $file_path);
                }
            }

            if (empty($data['attachments'])) {
                unset($data['attachments']);
            }
        }

        $response = wp_remote_post('https://api.resend.com/emails', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'body' => json_encode($data),
            'timeout' => 30,
            'httpversion' => '1.1'
        ]);

        if (is_wp_error($response)) {
            error_log('WP Resend Mailer Error (wp_remote_post): ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body_decoded = json_decode($response_body, true);

        if ($response_code >= 400) {
            $error_message = 'Unknown error';
            if (isset($body_decoded['message'])) {
                $error_message = $body_decoded['message'];
            } elseif (isset($body_decoded['error']['message'])) {
                $error_message = $body_decoded['error']['message']; // Handle nested error structure
            }
            error_log('WP Resend Mailer Error (API Status ' . $response_code . '): ' . $error_message);
            return false;
        }
        
        return true;
    }

    public function handle_test_email_ajax() {
        check_ajax_referer('wp_resend_mailer_test_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (!is_email($email)) {
            wp_send_json_error('Please provide a valid email address.');
            return;
        }

        $api_key = get_option('wp_resend_mailer_api_key');
        $from_email = get_option('wp_resend_mailer_from_email');
        $from_name = get_option('wp_resend_mailer_from_name');

        if (empty($api_key)) {
            wp_send_json_error('Resend API key is not set. Please configure it in the settings.');
            return;
        }
        if (empty($from_email) || !is_email($from_email)) {
             wp_send_json_error('"From Email Address" is not configured or invalid. Please check the settings.');
            return;
        }

        $to_recipients = [[$email, '']];

        $subject = 'Test Email from ' . get_bloginfo('name');
        $message = '<p>This is a test email sent from your WordPress site (' . site_url() . ') via the WP Resend Mailer plugin.</p><p>If you received this, your Resend API configuration is likely working correctly.</p>';

        $success = $this->send_email(
            $from_email,
            $from_name,
            $to_recipients,
            $subject,
            $message,
            '', // No alt_body for test
            [], // No custom headers for test
            [], // No attachments for test
            [], // No CC for test
            []  // No BCC for test
        );

        if ($success) {
            wp_send_json_success('Test email sent successfully to ' . esc_html($email) . '!');
        } else {
            wp_send_json_error('Failed to send test email. Please check the plugin settings and the error log for more details.');
        }
    }

    /**
     * Performs a basic health check by attempting to list domains.
     * @return array ['success' => bool, 'message' => string]
     */
    public function check_api_connection() {
        $api_key = get_option('wp_resend_mailer_api_key');

        if (empty($api_key)) {
            return ['success' => false, 'message' => esc_html__('API Key is not set.', 'wp-resend-mailer')];
        }

        $response = wp_remote_get('https://api.resend.com/domains', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json'
            ],
            'timeout' => 15, // Shorter timeout for a quick check
            'httpversion' => '1.1'
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('WP Resend Mailer Health Check Error (wp_remote_get): ' . $error_message);
            return ['success' => false, 'message' => sprintf(esc_html__('Connection Error: %s', 'wp-resend-mailer'), esc_html($error_message))];
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200) {
            // Optionally, check if the configured 'from_email' domain is in the list
            $from_email = get_option('wp_resend_mailer_from_email');
            $from_domain = substr(strrchr($from_email, "@"), 1);
            $body = wp_remote_retrieve_body($response);
            $decoded_body = json_decode($body, true);
            $domain_verified = false;
            if ($from_domain && isset($decoded_body['data']) && is_array($decoded_body['data'])) {
                foreach ($decoded_body['data'] as $domain_data) {
                    if (isset($domain_data['name']) && $domain_data['name'] === $from_domain) {
                         // Found the domain, check its status (adjust based on actual API response structure)
                         if (isset($domain_data['status']) && $domain_data['status'] === 'verified') { 
                            $domain_verified = true;
                         }
                         break;
                    }
                }
            }
            if ($from_domain && $domain_verified) {
                return ['success' => true, 'message' => esc_html__('API Key is valid and the From Email domain is verified in Resend.', 'wp-resend-mailer')];
            } elseif ($from_domain) {
                return ['success' => true, 'message' => esc_html__('API Key is valid, but the From Email domain was not found or is not verified in Resend. Emails may fail to send.', 'wp-resend-mailer')];
            } else {
                 return ['success' => true, 'message' => esc_html__('API Key is valid. Could not verify From Email domain status.', 'wp-resend-mailer')];
            }

        } elseif ($response_code === 401) {
            return ['success' => false, 'message' => esc_html__('Authentication failed: Invalid API Key.', 'wp-resend-mailer')];
        } else {
            $response_body = wp_remote_retrieve_body($response);
            $body_decoded = json_decode($response_body, true);
            $error_message = 'Status Code: ' . $response_code;
             if (isset($body_decoded['message'])) {
                $error_message .= ': ' . $body_decoded['message'];
            } elseif (isset($body_decoded['error']['message'])) {
                $error_message .= ': ' . $body_decoded['error']['message'];
            }
            error_log('WP Resend Mailer Health Check Error (API Status ' . $response_code . '): ' . $error_message);
            return ['success' => false, 'message' => sprintf(esc_html__('API Error: %s', 'wp-resend-mailer'), esc_html($error_message))];
        }
    }

    /**
     * Ajax handler for running the health check.
     */
    public function handle_health_check_ajax() {
        check_ajax_referer('wp_resend_mailer_health_check_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Permission denied.', 'wp-resend-mailer'));
            return;
        }

        $result = $this->check_api_connection();

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
} 