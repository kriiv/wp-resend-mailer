<?php
/**
 * wp_mail()'s mailer, with the last step swapped: instead of handing the finished message to PHP's mail(), it posts
 * it to Resend. A failure throws, so wp_mail() returns false and fires 'wp_mail_failed' like any other failed email.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('WP_PHPMailer')) {
    abstract class WP_Resend_Mailer_Base extends WP_PHPMailer {}
} else {
    abstract class WP_Resend_Mailer_Base extends PHPMailer\PHPMailer\PHPMailer {}
}

class WP_Resend_Mailer_PHPMailer extends WP_Resend_Mailer_Base {

    public function postSend() {
        $payload = $this->resend_payload();
        try {
            $id = WP_Resend_Mailer_Api::send($payload);
            WP_Resend_Mailer_Api::record(true, sprintf('Sent "%s" (Resend id %s)', $this->Subject, $id));
            return true;
        } catch (Exception $e) {
            WP_Resend_Mailer_Api::record(false, sprintf('"%s" failed: %s', $this->Subject, $e->getMessage()));
            if (wp_resend_mailer_settings()['fallback']) {
                error_log('WP Resend Mailer: Resend failed (' . $e->getMessage() . '), sending with PHP mail() instead.');
                $this->Mailer = 'mail';
                return parent::postSend();
            }
            throw new PHPMailer\PHPMailer\Exception('Resend: ' . $e->getMessage());
        }
    }

    /** The message as a Resend API request. */
    private function resend_payload(): array {
        $s = wp_resend_mailer_settings();
        $emails = fn (array $list) => array_values(array_map(fn ($a) => $a[0], $list));

        // Resend only sends from a verified domain, so the From address is always ours. Keep a sender name a plugin
        // set (WooCommerce sets the shop name); replace WordPress's default "WordPress".
        $name = ($this->FromName !== '' && $this->FromName !== 'WordPress') ? $this->FromName : $s['from_name'];
        $name = trim(str_replace(['"', '<', '>', ','], '', $name));
        $payload = [
            'from'    => $name !== '' ? sprintf('%s <%s>', $name, $s['from_email']) : $s['from_email'],
            'to'      => $emails($this->getToAddresses()),
            'subject' => $this->Subject,
        ];
        if ($this->ContentType === 'text/plain') {
            $payload['text'] = $this->Body;
        } else {
            $payload['html'] = $this->Body;
            if (trim($this->AltBody) !== '') {
                $payload['text'] = $this->AltBody;
            }
        }
        if ($cc = $emails($this->getCcAddresses())) {
            $payload['cc'] = $cc;
        }
        if ($bcc = $emails($this->getBccAddresses())) {
            $payload['bcc'] = $bcc;
        }
        $reply_to = $emails($this->getReplyToAddresses());
        // A message "from" someone else (e.g. a contact form) still reaches them when you hit Reply.
        if (!$reply_to && $this->From && strcasecmp($this->From, $s['from_email']) !== 0 && stripos($this->From, 'wordpress@') !== 0) {
            $reply_to = [$this->From];
        }
        if ($reply_to) {
            $payload['reply_to'] = $reply_to;
        }
        $headers = [];
        foreach ($this->getCustomHeaders() as [$header, $value]) {
            if (stripos($header, 'X-') === 0 || strcasecmp($header, 'List-Unsubscribe') === 0) {
                $headers[$header] = $value;
            }
        }
        if ($headers) {
            $payload['headers'] = $headers;
        }
        foreach ($this->getAttachments() as $a) {
            // [0] path or contents, [2] display name, [5] true when [0] is the contents
            $content = $a[5] ? $a[0] : (is_readable($a[0]) ? file_get_contents($a[0]) : false);
            if ($content === false) {
                throw new PHPMailer\PHPMailer\Exception('Resend: can’t read attachment ' . $a[2]);
            }
            $payload['attachments'][] = ['filename' => $a[2], 'content' => base64_encode($content)];
        }
        return apply_filters('wp_resend_mailer_payload', $payload, $this);
    }
}
