# Cap CAPTCHA for WordPress

Protect WordPress and WooCommerce forms with [Cap](https://trycap.dev), the self-hosted, privacy-first proof-of-work CAPTCHA.

Visitors click one checkbox. Their browser solves a small proof-of-work challenge, and your own Cap server checks the result. There are no image puzzles, no cookies and no third-party tracking.

## Features

- **Ready-made protection** for:
  - Login (`wp-login.php`)
  - Registration
  - Lost password (core and WooCommerce)
  - Comments
  - WooCommerce My Account login and registration
  - WooCommerce classic checkout
- **Server-side verification** of every submission against your Cap server's `/siteverify` endpoint.
- **Built-in setup test** on the settings page: solve a live widget and confirm your server accepts the token.
- **Custom forms** via the `[cap_captcha]` shortcode or PHP helpers.
- **Self-hosting option** for the widget script and WASM solvers, so nothing loads from a CDN.
- **Safe by default:**
  - Nothing is enforced until the plugin is fully configured, so activating it can't lock you out.
  - XML-RPC, the REST API, application passwords and WP-CLI are not affected.
  - Logins are blocked if your Cap server is unreachable, with an opt-in setting to allow them instead.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- A running [Cap Standalone](https://trycap.dev/guide/standalone/) server at a public HTTPS URL

## Installation

1. **Run a Cap server.** Follow the [Cap quickstart](https://trycap.dev/guide/). A short `docker-compose.yml` gets you running in a few minutes.
2. **Create a site key** in the Cap dashboard. Copy the **site key** and its **secret key**.
3. **Install the plugin.** Either:
   - download the latest `cap-captcha.zip` from [Releases](../../releases) and upload it via **Plugins → Add New → Upload Plugin**, or
   - clone this repository into `wp-content/plugins/cap-captcha`.
4. **Configure it.** Go to **Settings → Cap CAPTCHA** and enter your instance URL, site key and secret key.
5. **Test it.** Use the **Test your setup** box at the bottom of the settings page.
6. **Choose your forms.** Tick the forms you want protected and save.

> **Tip:** keep the secret key out of the database by adding it to `wp-config.php`:
>
> ```php
> define( 'CAP_CAPTCHA_SECRET_KEY', 'your-secret-key' );
> ```

## Protecting your own forms

Place the widget inside any `<form>`:

```text
[cap_captcha]
```

or from a theme template:

```php
<?php echo cap_captcha_widget(); ?>
```

The widget adds a hidden `cap-token` field to the form. Verify it when you handle the submission:

```php
$result = cap_captcha_verify(); // reads $_POST['cap-token']

if ( is_wp_error( $result ) ) {
    wp_die( esc_html( $result->get_error_message() ) );
}
```

Tokens are single-use. The plugin caches each result for the rest of the request, so checking the same token more than once in a request is safe.

## Filters

| Filter | Purpose |
| --- | --- |
| `cap_captcha_form_enabled( $enabled, $form )` | Turn a built-in integration on or off in code |
| `cap_captcha_widget_attributes( $attrs, $context )` | Add widget attributes, e.g. `data-cap-worker-count` or any `data-cap-i18n-*` label |
| `cap_captcha_verify_result( $result, $token )` | Adjust the verification outcome |
| `cap_captcha_error_message( $message, $type )` | Change error text (`missing`, `invalid`, `unavailable`) |
| `cap_captcha_script_url( $url )` | Change where the widget script loads from |
| `cap_captcha_forms( $forms )` | Add entries to the settings checklist |

## Troubleshooting

**Verification always fails.**
Check that you entered the site key's **secret key**, not the Cap dashboard `ADMIN_KEY`. Also check that the instance URL is the same public URL the widget uses.

**The widget shows an error.**
Visitors' browsers must be able to reach your Cap server. Check the instance URL, HTTPS, and Cap's CORS settings.

**I'm locked out of wp-admin.**
Rename or delete `wp-content/plugins/cap-captcha` over FTP or SSH to deactivate the plugin, then sign in normally.

## Limitations

- The WooCommerce **block** checkout is not supported yet. The classic (shortcode) checkout is.
- The plugin pins `cap-widget` **0.1.58**. You can point it at a newer build with the self-hosting settings or the `cap_captcha_script_url` filter.

## Credits

Cap is created by [tiago.zip](https://tiago.zip) and is available at [tiagozip/cap](https://github.com/tiagozip/cap). This plugin is an independent integration and is not affiliated with the Cap project.

## License

[Apache 2.0](LICENSE), matching Cap.
