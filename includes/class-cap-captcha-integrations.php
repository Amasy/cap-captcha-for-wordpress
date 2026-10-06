<?php
/**
 * Hooks Cap into WordPress core and WooCommerce forms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cap_Captcha_Integrations {

	/**
	 * Forms the plugin can protect: slug => label.
	 *
	 * @return array
	 */
	public static function forms() {
		$forms = array(
			'login'        => __( 'Login (wp-login.php)', 'cap-captcha' ),
			'register'     => __( 'Registration (wp-login.php)', 'cap-captcha' ),
			'lostpassword' => __( 'Lost password (wp-login.php and WooCommerce)', 'cap-captcha' ),
			'comments'     => __( 'Comments', 'cap-captcha' ),
			'wc_login'     => __( 'WooCommerce: My Account login', 'cap-captcha' ),
			'wc_register'  => __( 'WooCommerce: My Account registration', 'cap-captcha' ),
			'wc_checkout'  => __( 'WooCommerce: Classic checkout', 'cap-captcha' ),
		);
		return apply_filters( 'cap_captcha_forms', $forms );
	}

	public static function init() {
		// Core: login.
		add_action( 'login_form', array( __CLASS__, 'render_login' ) );
		add_filter( 'authenticate', array( __CLASS__, 'check_login' ), 30, 1 );

		// Core: registration.
		add_action( 'register_form', array( __CLASS__, 'render_register' ) );
		add_filter( 'registration_errors', array( __CLASS__, 'check_register' ), 10, 1 );

		// Core + WooCommerce: lost password (WooCommerce also calls retrieve_password()).
		add_action( 'lostpassword_form', array( __CLASS__, 'render_lostpassword' ) );
		add_action( 'woocommerce_lostpassword_form', array( __CLASS__, 'render_lostpassword' ) );
		add_action( 'lostpassword_post', array( __CLASS__, 'check_lostpassword' ), 10, 1 );

		// Comments.
		add_filter( 'comment_form_submit_field', array( __CLASS__, 'render_comments' ), 10, 1 );
		add_filter( 'preprocess_comment', array( __CLASS__, 'check_comment' ), 1 );

		// WooCommerce.
		add_action( 'woocommerce_login_form', array( __CLASS__, 'render_wc_login' ) );
		add_filter( 'woocommerce_process_login_errors', array( __CLASS__, 'check_wc_login' ), 10, 1 );
		add_action( 'woocommerce_register_form', array( __CLASS__, 'render_wc_register' ) );
		add_filter( 'woocommerce_process_registration_errors', array( __CLASS__, 'check_wc_register' ), 10, 1 );
		// Placed after billing fields: the order-review section is re-rendered by AJAX and would wipe the token.
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'render_wc_checkout' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'check_wc_checkout' ), 10, 2 );
	}

	/**
	 * Skip checks for logged-in users where it makes sense (comments, checkout).
	 *
	 * @return bool
	 */
	private static function skip_for_user() {
		return cap_captcha_option( 'skip_logged_in' ) && is_user_logged_in();
	}

	/* ---------- Login ---------- */

	public static function render_login() {
		if ( cap_captcha_form_enabled( 'login' ) ) {
			Cap_Captcha_Widget::render( 'login' );
		}
	}

	/**
	 * Only enforce for the interactive wp-login.php form, so XML-RPC, REST
	 * application passwords, WP-CLI and WooCommerce's own login are untouched.
	 *
	 * @param WP_User|WP_Error|null $user User so far.
	 * @return WP_User|WP_Error|null
	 */
	public static function check_login( $user ) {
		if ( ! cap_captcha_form_enabled( 'login' ) ) {
			return $user;
		}
		if ( ! isset( $GLOBALS['pagenow'] ) || 'wp-login.php' !== $GLOBALS['pagenow'] ) {
			return $user;
		}
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || ! isset( $_POST['log'] ) ) { // phpcs:ignore
			return $user;
		}
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $user;
		}

		$result = cap_captcha_verify();
		return is_wp_error( $result ) ? $result : $user;
	}

	/* ---------- Registration ---------- */

	public static function render_register() {
		if ( cap_captcha_form_enabled( 'register' ) ) {
			Cap_Captcha_Widget::render( 'register' );
		}
	}

	/**
	 * @param WP_Error $errors Errors.
	 * @return WP_Error
	 */
	public static function check_register( $errors ) {
		if ( cap_captcha_form_enabled( 'register' ) ) {
			self::merge( $errors, cap_captcha_verify() );
		}
		return $errors;
	}

	/* ---------- Lost password ---------- */

	public static function render_lostpassword() {
		if ( cap_captcha_form_enabled( 'lostpassword' ) ) {
			Cap_Captcha_Widget::render( 'lostpassword' );
		}
	}

	/**
	 * @param WP_Error $errors Errors.
	 */
	public static function check_lostpassword( $errors ) {
		if ( ! cap_captcha_form_enabled( 'lostpassword' ) ) {
			return;
		}
		// Admins resetting a user's password from wp-admin don't submit the form.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore
			return;
		}
		self::merge( $errors, cap_captcha_verify() );
	}

	/* ---------- Comments ---------- */

	/**
	 * @param string $submit_field Submit button markup.
	 * @return string
	 */
	public static function render_comments( $submit_field ) {
		if ( cap_captcha_form_enabled( 'comments' ) && ! self::skip_for_user() ) {
			$submit_field = Cap_Captcha_Widget::markup( 'comments' ) . $submit_field;
		}
		return $submit_field;
	}

	/**
	 * @param array $commentdata Comment data.
	 * @return array
	 */
	public static function check_comment( $commentdata ) {
		if ( ! cap_captcha_form_enabled( 'comments' ) || self::skip_for_user() ) {
			return $commentdata;
		}
		// Pingbacks/trackbacks have no form; admin replies come from wp-admin AJAX.
		$type = isset( $commentdata['comment_type'] ) ? $commentdata['comment_type'] : '';
		if ( in_array( $type, array( 'pingback', 'trackback' ), true ) ) {
			return $commentdata;
		}
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $commentdata;
		}

		$result = cap_captcha_verify();
		if ( is_wp_error( $result ) ) {
			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Comment submission failure', 'cap-captcha' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
		return $commentdata;
	}

	/* ---------- WooCommerce ---------- */

	public static function render_wc_login() {
		if ( cap_captcha_form_enabled( 'wc_login' ) ) {
			Cap_Captcha_Widget::render( 'wc-login' );
		}
	}

	/**
	 * @param WP_Error $errors Errors.
	 * @return WP_Error
	 */
	public static function check_wc_login( $errors ) {
		if ( cap_captcha_form_enabled( 'wc_login' ) ) {
			self::merge( $errors, cap_captcha_verify() );
		}
		return $errors;
	}

	public static function render_wc_register() {
		if ( cap_captcha_form_enabled( 'wc_register' ) ) {
			Cap_Captcha_Widget::render( 'wc-register' );
		}
	}

	/**
	 * WooCommerce's My Account registration form. (Accounts created during
	 * checkout go through the checkout check instead.)
	 *
	 * @param WP_Error $errors Errors.
	 * @return WP_Error
	 */
	public static function check_wc_register( $errors ) {
		if ( cap_captcha_form_enabled( 'wc_register' ) ) {
			self::merge( $errors, cap_captcha_verify() );
		}
		return $errors;
	}

	public static function render_wc_checkout() {
		if ( cap_captcha_form_enabled( 'wc_checkout' ) && ! self::skip_for_user() ) {
			Cap_Captcha_Widget::render( 'wc-checkout' );
		}
	}

	/**
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Errors.
	 */
	public static function check_wc_checkout( $data, $errors ) {
		if ( ! cap_captcha_form_enabled( 'wc_checkout' ) || self::skip_for_user() ) {
			return;
		}
		// Only fail the CAPTCHA once every other field is valid, so a single
		// token isn't burned on a form that would be rejected anyway.
		if ( $errors->has_errors() ) {
			return;
		}
		self::merge( $errors, cap_captcha_verify() );
	}

	/* ---------- Helpers ---------- */

	/**
	 * Add a verification error to an existing WP_Error.
	 *
	 * @param WP_Error      $errors Target.
	 * @param true|WP_Error $result Verification result.
	 */
	private static function merge( $errors, $result ) {
		if ( is_wp_error( $result ) && is_wp_error( $errors ) ) {
			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}
	}
}
