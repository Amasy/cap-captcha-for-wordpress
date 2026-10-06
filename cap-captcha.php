<?php
/**
 * Plugin Name:       Cap CAPTCHA
 * Plugin URI:        https://trycap.dev/guide/
 * Description:       Protect WordPress login, registration, password reset, comments, WooCommerce, Contact Form 7 and Gravity Forms with Cap, the self-hosted proof-of-work CAPTCHA.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Evans
 * Author URI:        https://zenevan.co.ke
 * License:           Apache-2.0
 * License URI:       https://www.apache.org/licenses/LICENSE-2.0
 * Text Domain:       cap-captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CAP_CAPTCHA_VERSION', '1.1.0' );
define( 'CAP_CAPTCHA_WIDGET_VERSION', '0.1.58' );
define( 'CAP_CAPTCHA_OPTION', 'cap_captcha_options' );
define( 'CAP_CAPTCHA_FILE', __FILE__ );
define( 'CAP_CAPTCHA_DIR', plugin_dir_path( __FILE__ ) );

require_once CAP_CAPTCHA_DIR . 'includes/class-cap-captcha-verifier.php';
require_once CAP_CAPTCHA_DIR . 'includes/class-cap-captcha-widget.php';
require_once CAP_CAPTCHA_DIR . 'includes/class-cap-captcha-integrations.php';
require_once CAP_CAPTCHA_DIR . 'includes/class-cap-captcha-settings.php';

/**
 * Default option values.
 *
 * @return array
 */
function cap_captcha_defaults() {
	return array(
		'instance_url'     => '',
		'site_key'         => '',
		'secret_key'       => '',
		'script_url'       => '',
		'wasm_url'         => '',
		'hashwx_url'       => '',
		'forms'            => array( 'login', 'register', 'lostpassword', 'comments' ),
		'skip_logged_in'   => 1,
		'fail_open'        => 0,
		'disable_haptics'  => 0,
		'label_initial'    => '',
		'label_solved'     => '',
	);
}

/**
 * Get a plugin option (merged with defaults). Secret key can be overridden
 * with the CAP_CAPTCHA_SECRET_KEY constant in wp-config.php.
 *
 * @param string|null $key Option key, or null for the full array.
 * @return mixed
 */
function cap_captcha_option( $key = null ) {
	$opts = wp_parse_args( (array) get_option( CAP_CAPTCHA_OPTION, array() ), cap_captcha_defaults() );

	if ( defined( 'CAP_CAPTCHA_SECRET_KEY' ) && CAP_CAPTCHA_SECRET_KEY ) {
		$opts['secret_key'] = CAP_CAPTCHA_SECRET_KEY;
	}

	if ( null === $key ) {
		return $opts;
	}
	return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
}

/**
 * Whether the plugin has everything it needs to render and verify.
 * Until it is configured nothing is enforced, so you can't lock yourself out.
 *
 * @return bool
 */
function cap_captcha_is_configured() {
	return '' !== cap_captcha_option( 'instance_url' )
		&& '' !== cap_captcha_option( 'site_key' )
		&& '' !== cap_captcha_option( 'secret_key' );
}

/**
 * Whether a given form integration is switched on.
 *
 * @param string $form Form slug.
 * @return bool
 */
function cap_captcha_form_enabled( $form ) {
	$enabled = cap_captcha_is_configured();

	// Forms with a settings checkbox follow it. Others (Contact Form 7, Gravity
	// Forms) are opted in per form by adding the Cap tag or field.
	if ( $enabled && array_key_exists( $form, Cap_Captcha_Integrations::forms() ) ) {
		$enabled = in_array( $form, (array) cap_captcha_option( 'forms' ), true );
	}

	return (bool) apply_filters( 'cap_captcha_form_enabled', $enabled, $form );
}

/**
 * The widget's API endpoint: https://<instance>/<site-key>/
 *
 * @return string
 */
function cap_captcha_endpoint() {
	return trailingslashit( untrailingslashit( cap_captcha_option( 'instance_url' ) ) . '/' . rawurlencode( cap_captcha_option( 'site_key' ) ) );
}

/**
 * Public helper for custom forms: verify the submitted token.
 *
 * @param string|null $token Token, or null to read the cap-token POST field.
 * @return true|WP_Error
 */
function cap_captcha_verify( $token = null ) {
	return Cap_Captcha_Verifier::verify( $token );
}

/**
 * Public helper for custom forms/templates: return the widget markup.
 *
 * @return string
 */
function cap_captcha_widget() {
	return Cap_Captcha_Widget::markup( 'custom' );
}

add_action(
	'plugins_loaded',
	static function () {
		Cap_Captcha_Widget::init();
		Cap_Captcha_Integrations::init();
		if ( is_admin() ) {
			Cap_Captcha_Settings::init();
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=cap-captcha' ) ) . '">' . esc_html__( 'Settings', 'cap-captcha' ) . '</a>'
		);
		return $links;
	}
);
