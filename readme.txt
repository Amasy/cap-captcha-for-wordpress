=== Cap CAPTCHA ===
Tags: captcha, anti-spam, proof-of-work, privacy, woocommerce
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: Apache-2.0
License URI: https://www.apache.org/licenses/LICENSE-2.0

Protect WordPress forms with Cap, the self-hosted, privacy-first proof-of-work CAPTCHA. No puzzles, no cookies, no third-party tracking.

== Description ==

Cap (https://trycap.dev) replaces image puzzles with a one-click checkbox that runs a small proof-of-work challenge in the visitor's browser. Your own Cap server issues challenges and verifies tokens, so visitor data never leaves your infrastructure.

This plugin adds the Cap widget and server-side token verification to:

* Login (wp-login.php)
* Registration
* Lost password (core and WooCommerce)
* Comments
* WooCommerce My Account login and registration
* WooCommerce classic checkout

Anything else can use the `[cap_captcha]` shortcode or the PHP helpers below.

== Installation ==

1. Run a Cap Standalone server (see https://trycap.dev/guide/ — a docker-compose file gets you going in minutes) at a public URL, e.g. https://cap.example.com.
2. In the Cap dashboard, create a site key. Copy the site key and its secret key.
3. Upload the plugin zip via Plugins → Add New → Upload Plugin, and activate it.
4. Go to Settings → Cap CAPTCHA, enter the instance URL, site key and secret key, and save.
5. Use the "Test your setup" box at the bottom of the settings page to confirm a token verifies end to end.
6. Tick the forms you want protected.

Nothing is enforced until the instance URL, site key and secret key are all set, so you can't lock yourself out by activating the plugin.

Tip: keep the secret out of the database by adding this to wp-config.php:

    define( 'CAP_CAPTCHA_SECRET_KEY', 'your-secret-key' );

== Custom forms ==

Render the widget inside any <form>:

    [cap_captcha]

or in a template:

    <?php echo cap_captcha_widget(); ?>

The widget adds a hidden `cap-token` field to the form. Verify it when handling the submission:

    $result = cap_captcha_verify(); // reads $_POST['cap-token']
    if ( is_wp_error( $result ) ) {
        wp_die( $result->get_error_message() );
    }

Tokens are single-use. The plugin caches the result per request, so checking the same token twice in one request is safe.

== Filters ==

* `cap_captcha_form_enabled( $enabled, $form )` — turn a built-in integration on/off programmatically.
* `cap_captcha_widget_attributes( $attrs, $context )` — add widget attributes such as `data-cap-worker-count` or any `data-cap-i18n-*` label.
* `cap_captcha_verify_result( $result, $token )` — adjust the verification outcome.
* `cap_captcha_error_message( $message, $type )` — change user-facing errors (`missing`, `invalid`, `unavailable`).
* `cap_captcha_script_url( $url )` — change where the widget script loads from.
* `cap_captcha_forms( $forms )` — add entries to the settings checklist (pair with your own render/verify hooks).

== Frequently Asked Questions ==

= Verification always fails =

Make sure you used the site key's secret, not the Cap dashboard ADMIN_KEY, and that the instance URL is the same public URL the widget uses.

= The widget shows an error =

The visitor's browser must reach your Cap server. Check the instance URL, HTTPS, and Cap's CORS settings.

= Does it affect XML-RPC, the REST API or application passwords? =

No. The login check only applies to the interactive wp-login.php form.

= WooCommerce block checkout? =

Not yet supported; the classic (shortcode) checkout is.

== Changelog ==

= 1.0.0 =
* Initial release. Pins cap-widget 0.1.58.
