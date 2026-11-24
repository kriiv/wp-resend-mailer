<?php
/** The Resend API calls, and a record of the last send. */
if (!defined('ABSPATH')) {
    exit;
}

class WP_Resend_Mailer_Api {

    const URL = 'https://api.resend.com';

    /** Send one email. Returns Resend's id; throws Exception with Resend's message on failure. */
    public static function send(array $payload): string {
        [$code, $body] = self::request('POST', '/emails', $payload);
        if ($code >= 200 && $code < 300 && !empty($body['id'])) {
            return (string) $body['id'];
        }
        throw new Exception(self::error_message($code, $body));
    }

    /**
     * Check the key and the From domain. Returns ['ok' => bool, 'message' => string].
     * A "sending access" key can't list domains; that is reported as fine, with a pointer to the test email.
     */
    public static function check(): array {
        $s = wp_resend_mailer_settings();
        if ($s['api_key'] === '') {
            return ['ok' => false, 'message' => 'No API key yet.'];
        }
        if (!is_email($s['from_email'])) {
            return ['ok' => false, 'message' => 'Set the From address (on your domain verified in Resend).'];
        }
        try {
            [$code, $body] = self::request('GET', '/domains');
        } catch (Exception $e) {
            return ['ok' => false, 'message' => 'Couldn’t reach Resend: ' . $e->getMessage()];
        }
        if ($code === 401 && ($body['name'] ?? '') === 'restricted_api_key') {
            return ['ok' => true, 'message' => 'The key is a sending-only key, so the domain can’t be checked from here. Send a test email to confirm.'];
        }
        if ($code !== 200) {
            return ['ok' => false, 'message' => self::error_message($code, $body)];
        }
        $domain = strtolower(substr(strrchr($s['from_email'], '@'), 1));
        foreach ($body['data'] ?? [] as $d) {
            if (strtolower($d['name'] ?? '') === $domain) {
                return ($d['status'] ?? '') === 'verified'
                    ? ['ok' => true, 'message' => "Connected. $domain is verified."]
                    : ['ok' => false, 'message' => "$domain is in Resend but not verified yet (status: " . ($d['status'] ?? 'unknown') . '). Add its DNS records.'];
            }
        }
        return ['ok' => false, 'message' => "$domain isn’t in this Resend account. Add and verify it under Domains."];
    }

    /** Remember the last send, for the settings page. */
    public static function record(bool $ok, string $message): void {
        update_option('wp_resend_mailer_last', ['ok' => $ok, 'time' => time(), 'message' => $message], false);
    }

    /** @return array{0:int,1:array} status code and decoded body */
    private static function request(string $method, string $path, ?array $body = null): array {
        $args = [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . wp_resend_mailer_settings()['api_key'],
                'Accept'        => 'application/json',
                'User-Agent'    => 'wp-resend-mailer/' . WP_RESEND_MAILER_VERSION,
            ],
        ];
        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }
        $response = wp_remote_request(self::URL . $path, $args);
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        return [(int) wp_remote_retrieve_response_code($response), is_array($decoded) ? $decoded : []];
    }

    private static function error_message(int $code, array $body): string {
        $message = $body['message'] ?? ($body['error']['message'] ?? 'unexpected response');
        return "$message (HTTP $code)";
    }
}
