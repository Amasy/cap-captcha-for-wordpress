<?php
/**
 * Base class for a form integration (WordPress core, WooCommerce, CF7, ...).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Cap_Captcha_Integration {

	/**
	 * Unique slug, e.g. "woocommerce".
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Human-readable name shown on the settings page.
	 *
	 * @return string
	 */
	abstract public function name();

	/**
	 * Register hooks. Only called when is_available() is true.
	 */
	abstract public function init();

	/**
	 * Whether the plugin this integration targets is active.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Forms that get an on/off checkbox in the settings: slug => label.
	 *
	 * @return array
	 */
	public function forms() {
		return array();
	}

	/**
	 * Optional usage note shown under the integration on the settings page.
	 * May contain basic HTML (<code>, <strong>, <a>).
	 *
	 * @return string
	 */
	public function help() {
		return '';
	}

	/**
	 * Skip checks for logged-in users where the setting allows it.
	 *
	 * @return bool
	 */
	protected function skip_for_user() {
		return cap_captcha_option( 'skip_logged_in' ) && is_user_logged_in();
	}

	/**
	 * Copy a failed verification into an existing WP_Error.
	 *
	 * @param WP_Error      $errors Target.
	 * @param true|WP_Error $result Verification result.
	 */
	protected function merge( $errors, $result ) {
		if ( is_wp_error( $result ) && is_wp_error( $errors ) ) {
			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}
	}

	/**
	 * Whether this request is a POST.
	 *
	 * @return bool
	 */
	protected function is_post() {
		return isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];
	}
}
