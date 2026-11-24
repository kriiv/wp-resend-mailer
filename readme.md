# WP Resend Mailer

Sends every WordPress email through [Resend](https://resend.com): password resets, WooCommerce order emails, admin
notices, anything that uses `wp_mail()`. One settings page, no JavaScript, no upsells.

## How it works

WordPress still builds each email the normal way (From name, HTML or plain text, CC, BCC, Reply-To, attachments, and
every plugin's filters). Only the last step changes: instead of handing the message to the server's `mail()`, the
plugin posts it to the Resend API.

- **Failures are real failures.** If Resend rejects an email, `wp_mail()` returns `false` and fires
  `wp_mail_failed` with Resend's error, so other plugins (and health checks) see it.
- **From address:** always the one you set (Resend only sends from verified domains). The sender *name* a plugin sets
  is kept, e.g. WooCommerce's shop name; WordPress's default "WordPress" is replaced with yours.
- **Reply-To:** kept. If an email claims to be from another address (a contact form, WooCommerce's "From" setting)
  and has no Reply-To, that address becomes the Reply-To, so hitting Reply still reaches the right person.
- **Plain text stays plain text** (WordPress's own emails), HTML is sent as HTML with its text version.
- **Fallback (optional, off by default):** if Resend fails, try the server's own mail instead.

## Setup

1. In Resend: add and verify your domain (DNS records), then create an API key. "Sending access" limited to the
   domain is enough.
2. Install the plugin (Plugins → Add New → Upload Plugin, with this repo's zip) and activate it.
3. **Settings → Resend:** paste the API key, set the From address (on the verified domain) and the From name. Save.
4. Click **Send test email**.

### Keeping the key out of the database (recommended)

Put it in `wp-config.php` instead; the settings page then shows it as set there:

```php
define('RESEND_API_KEY', 're_...');
define('RESEND_FROM_EMAIL', 'hello@yourdomain.com'); // optional
define('RESEND_FROM_NAME', 'Your Site');              // optional
```

## For developers

- `wp_resend_mailer_is_active()`: true when emails are going through Resend.
- `wp_resend_mailer_settings()`: the settings in use (constants win over the settings page).
- `wp_resend_mailer_payload` filter: change the Resend request (e.g. add `tags`) before it's sent.
- The last send (success or error) is in the `wp_resend_mailer_last` option and shown on the settings page.

## License

GPLv2 or later.
