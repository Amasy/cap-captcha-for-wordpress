=== Cap CAPTCHA ===
Tags: captcha, anti-spam, woocommerce, contact form 7, gravity forms
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
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
* Contact Form 7 (add the [cap_captcha] form-tag)
* Gravity Forms (add the Cap CAPTCHA field from Advanced Fields)

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

== Contact Form 7 ==

Add [cap_captcha] to any form, or use the Cap CAPTCHA button in the form editor. Only forms that contain the tag are checked.

== Gravity Forms ==

Add the Cap CAPTCHA field from Advanced Fields. Only forms that contain the field are checked. On multi-page forms, put it on the last page.

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
* `cap_captcha_integrations( $list )` — register your own integration (a Cap_Captcha_Integration subclass).
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

= 1.1.0 =
* New: Contact Form 7 support via a [cap_captcha] form-tag, with an editor button and inline errors.
* New: Gravity Forms support via a Cap CAPTCHA field (Advanced Fields).
* The token is only checked once other fields are valid, so it isn't wasted on submissions that fail anyway.
* Settings page groups forms by plugin and shows which ones are active.
* Integrations are now separate modules, and you can add your own with the cap_captcha_integrations filter.
* Plugin author set to Evans (https://zenevan.co.ke).

= 1.0.0 =
* Initial release. Pins cap-widget 0.1.58.
