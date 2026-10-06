<?php
/**
 * WordPress core forms: login, registration, lost password, comments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cap_Captcha_Core extends Cap_Captcha_Integration {

	public function id() {
		return 'wordpress';
	}

	public function name() {
		return __( 'WordPress', 'cap-captcha' );
	}

	public function forms() {
		return array(
			'login'        => __( 'Login (wp-login.php)', 'cap-captcha' ),
			'register'     => __( 'Registration (wp-login.php)', 'cap-captcha' ),
			'lostpassword' => __( 'Lost password (wp-login.php and WooCommerce)', 'cap-captcha' ),
			'comments'     => __( 'Comments', 'cap-captcha' ),
		);
	}

	public function init() {
		add_action( 'login_form', array( $this, 'render_login' ) );
		add_filter( 'authenticate', array( $this, 'check_login' ), 30, 1 );

		add_action( 'register_form', array( $this, 'render_register' ) );
		add_filter( 'registration_errors', array( $this, 'check_register' ), 10, 1 );

		// WooCommerce's lost-password form also calls retrieve_password(), which fires lostpassword_post.
		add_action( 'lostpassword_form', array( $this, 'render_lostpassword' ) );
		add_action( 'woocommerce_lostpassword_form', array( $this, 'render_lostpassword' ) );
		add_action( 'lostpassword_post', array( $this, 'check_lostpassword' ), 10, 1 );

		add_filter( 'comment_form_submit_field', array( $this, 'render_comments' ), 10, 1 );
		add_filter( 'preprocess_comment', array( $this, 'check_comment' ), 1 );
	}

	/* ---------- Login ---------- */

	public function render_login() {
		if ( cap_captcha_form_enabled( 'login' ) ) {
			Cap_Captcha_Widget::render( 'login' );
		}
	}

	/**
	 * Only the interactive wp-login.php form is checked, so XML-RPC, REST
	 * application passwords, WP-CLI and WooCommerce's own login are untouched.
	 *
	 * @param WP_User|WP_Error|null $user User so far.
	 * @return WP_User|WP_Error|null
	 */
	public function check_login( $user ) {
		if ( ! cap_captcha_form_enabled( 'login' ) ) {
			return $user;
		}
		if ( ! isset( $GLOBALS['pagenow'] ) || 'wp-login.php' !== $GLOBALS['pagenow'] ) {
			return $user;
		}
		if ( ! $this->is_post() || ! isset( $_POST['log'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $user;
		}
		if ( ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $user;
		}

		$result = cap_captcha_verify();
		return is_wp_error( $result ) ? $result : $user;
	}

	/* ---------- Registration ---------- */

	public function render_register() {
		if ( cap_captcha_form_enabled( 'register' ) ) {
			Cap_Captcha_Widget::render( 'register' );
		}
	}

	/**
	 * @param WP_Error $errors Errors.
	 * @return WP_Error
	 */
	public function check_register( $errors ) {
		if ( cap_captcha_form_enabled( 'register' ) ) {
			$this->merge( $errors, cap_captcha_verify() );
		}
		return $errors;
	}

	/* ---------- Lost password ---------- */

	public function render_lostpassword() {
		if ( cap_captcha_form_enabled( 'lostpassword' ) ) {
			Cap_Captcha_Widget::render( 'lostpassword' );
		}
	}

	/**
	 * @param WP_Error $errors Errors.
	 */
	public function check_lostpassword( $errors ) {
		if ( ! cap_captcha_form_enabled( 'lostpassword' ) || ! $this->is_post() ) {
			return;
		}
		// Admins sending a reset link from wp-admin don't submit this form.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		$this->merge( $errors, cap_captcha_verify() );
	}

	/* ---------- Comments ---------- */

	/**
	 * @param string $submit_field Submit button markup.
	 * @return string
	 */
	public function render_comments( $submit_field ) {
		if ( cap_captcha_form_enabled( 'comments' ) && ! $this->skip_for_user() ) {
			$submit_field = Cap_Captcha_Widget::markup( 'comments' ) . $submit_field;
		}
		return $submit_field;
	}

	/**
	 * @param array $commentdata Comment data.
	 * @return array
	 */
	public function check_comment( $commentdata ) {
		if ( ! cap_captcha_form_enabled( 'comments' ) || $this->skip_for_user() ) {
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
}
