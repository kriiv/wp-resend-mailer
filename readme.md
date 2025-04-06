# WP Resend Mailer

[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL%20v2%20or%20later-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![GitHub repository](https://img.shields.io/badge/GitHub-Repo-blue.svg?style=flat-square)](https://github.com/kriiv/wp-resend-mailer)
<!-- Add other relevant badges like WordPress version compatibility, build status etc. -->

Send your WordPress site's emails reliably through the [Resend](https://resend.com) API.

WP Resend Mailer overrides the default `wp_mail()` function to send all outgoing emails from your WordPress installation via the Resend transactional email service API. This ensures higher deliverability compared to standard PHP mail and provides access to Resend's features and tracking capabilities (via your Resend dashboard).

## Features

*   **Reliable Email Delivery:** Leverages the Resend API (`https://api.resend.com/emails`) for sending emails.
*   **Custom Sender:** Define the "From" email address and name (must use a verified domain in Resend).
*   **Enable/Disable:** Easily toggle the Resend integration on or off without losing settings.
*   **Test Email:** Send a test email to verify your configuration directly from the settings page.
*   **Reply-To Header Support:** Correctly passes the `Reply-To` header set in WordPress emails to Resend.
*   **Plain Text Support:** Sends both HTML (`Body`) and Plain Text (`AltBody`) versions of emails when available, improving compatibility.
*   **Attachments:** Supports sending email attachments included via `wp_mail()`.
*   **Fallback Option:** Optionally attempt sending via the default WordPress mailer *only if* the Resend API call fails.
*   **Connection Health Check:** Verify API key validity and check the configured 'From Email' domain's verification status in Resend directly from the settings page.

## Usage

Once configured and enabled, the plugin works automatically in the background. Any WordPress component or plugin that uses the standard `wp_mail()` function will have its emails routed through the Resend API using the settings you provided.

You can use the **Send Test Email** feature on the settings page to quickly verify your setup. Use the **Check Connection** button to diagnose API key and domain verification status.

## Frequently Asked Questions (FAQ)

*   **Where do I get my Resend API Key?**
    Log in to your Resend account and navigate to the [API Keys](https://resend.com/api-keys) section.
*   **Why do I need to verify my domain?**
    Resend requires domain verification to ensure you own the domain you're sending emails from. This is a standard practice to prevent spam and maintain high deliverability. You can manage domains in the [Domains](https://resend.com/domains) section of your Resend account.
*   **Does this plugin support Resend features like tags or webhooks?**
    Currently, the plugin focuses on replacing the core `wp_mail()` functionality. It does not have built-in UI support for adding Resend-specific tags to emails or handling incoming webhooks for tracking. However, filter hooks might be added in the future to allow developers to customize the API payload.
*   **What happens if the Resend API is down or my key is invalid?**
    If the Resend API call fails and the "Enable Fallback" option is checked, the plugin will attempt to use the default WordPress mailer. If fallback is disabled, the email will fail to send, and an error will be logged in your PHP error log. Use the "Check Connection" button to verify your API key.

## Contributing

Contributions are welcome! If you find a bug or have a feature request, please open an issue on the [GitHub repository](https://github.com/kriiv/wp-resend-mailer/issues). Pull requests are also appreciated.

## License

This plugin is licensed under the GPLv2 (or later).