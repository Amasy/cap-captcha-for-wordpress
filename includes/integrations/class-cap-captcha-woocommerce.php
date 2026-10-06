<?php
/**
 * WooCommerce: My Account login/registration and classic checkout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cap_Captcha_WooCommerce extends Cap_Captcha_Integration {

	public function id() {
		return 'woocommerce';
	}

	public function name() {
		return 'WooCommerce';
	}

	public function is_available() {
		return class_exists( 'WooCommerce' );
	}

	public function forms() {
		return array(
			'wc_login'    => __( 'My Account login', 'cap-captcha' ),
			'wc_register' => __( 'My Account registration', 'cap-captcha' ),
			'wc_checkout' => __( 'Classic checkout', 'cap-captcha' ),
		);
	}

	public function help() {
		return __( 'The lost-password form is covered by the WordPress "Lost password" option. The block-based checkout is not supported yet.', 'cap-captcha' );
	}

	public function init() {
		add_action( 'woocommerce_login_form', array( $this, 'render_login' ) );
		add_filter( 'woocommerce_process_login_errors', array( $this, 'check_login' ), 10, 1 );

		add_action( 'woocommerce_register_form', array( $this, 'render_register' ) );
		add_filter( 'woocommerce_process_registration_errors', array( $this, 'check_register' ), 10, 1 );

		// After the billing fields: the order-review section is re-rendered by AJAX and would wipe the token.
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_checkout' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_checkout' ), 10, 2 );
	}

	public function render_login() {
		if ( cap_captcha_form_enabled( 'wc_login' ) ) {
			Cap_Captcha_Widget::render( 'wc-login' );
		}
	}

	/**
	 * @param WP_Error $errors Errors.
	 * @return WP_Error
	 */
	public function check_login( $errors ) {
		if ( cap_captcha_form_enabled( 'wc_login' ) ) {
			$this->merge( $errors, cap_captcha_verify() );
		}
		return $errors;
	}

	public function render_register() {
		if ( cap_captcha_form_enabled( 'wc_register' ) ) {
			Cap_Captcha_Widget::render( 'wc-register' );
		}
	}

	/**
	 * My Account registration form only; accounts created during checkout
	 * are covered by the checkout check.
	 *
	 * @param WP_Error $errors Errors.
	 * @return WP_Error
	 */
	public function check_register( $errors ) {
		if ( cap_captcha_form_enabled( 'wc_register' ) ) {
			$this->merge( $errors, cap_captcha_verify() );
		}
		return $errors;
	}

	public function render_checkout() {
		if ( cap_captcha_form_enabled( 'wc_checkout' ) && ! $this->skip_for_user() ) {
			Cap_Captcha_Widget::render( 'wc-checkout' );
		}
	}

	/**
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Errors.
	 */
	public function check_checkout( $data, $errors ) {
		if ( ! cap_captcha_form_enabled( 'wc_checkout' ) || $this->skip_for_user() ) {
			return;
		}
		// Don't spend the single-use token on an order that fails anyway.
		if ( $errors->has_errors() ) {
			return;
		}
		$this->merge( $errors, cap_captcha_verify() );
	}
}
