<?php
/**
 * Server-side token verification against Cap's /siteverify endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cap_Captcha_Verifier {

	const FIELD = 'cap-token';

	/**
	 * Per-request cache. Cap tokens are single-use, so if several hooks check
	 * the same submission we must only call /siteverify once.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Verify a token.
	 *
	 * @param string|null $token Token, or null to read it from $_POST.
	 * @return true|WP_Error
	 */
	public static function verify( $token = null ) {
		if ( null === $token ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the CAPTCHA token is itself the check.
			$token = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) : '';
		}
		$token = trim( (string) $token );

		if ( '' === $token ) {
			return new WP_Error( 'cap_missing', self::message( 'missing' ) );
		}

		if ( isset( self::$cache[ $token ] ) ) {
			return self::$cache[ $token ];
		}

		$response = wp_remote_post(
			cap_captcha_endpoint() . 'siteverify',
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'secret'   => cap_captcha_option( 'secret_key' ),
						'response' => $token,
					)
				),
			)
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( 0 === $code || $code >= 500 ) {
			$detail = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Cap CAPTCHA: verification server unreachable (' . $detail . ').' );

			$result = cap_captcha_option( 'fail_open' )
				? true
				: new WP_Error( 'cap_unavailable', self::message( 'unavailable' ) );
		} else {
			$body   = json_decode( wp_remote_retrieve_body( $response ), true );
			$result = ( is_array( $body ) && ! empty( $body['success'] ) )
				? true
				: new WP_Error( 'cap_invalid', self::message( 'invalid' ) );
		}

		/**
		 * Filter the verification result.
		 *
		 * @param true|WP_Error $result Result.
		 * @param string        $token  Submitted token.
		 */
		$result = apply_filters( 'cap_captcha_verify_result', $result, $token );

		self::$cache[ $token ] = $result;
		return $result;
	}

	/**
	 * User-facing error messages.
	 *
	 * @param string $type missing|invalid|unavailable.
	 * @return string
	 */
	public static function message( $type ) {
		$messages = array(
			'missing'     => __( 'Please complete the human verification.', 'cap-captcha' ),
			'invalid'     => __( 'Human verification failed. Please try again.', 'cap-captcha' ),
			'unavailable' => __( 'Human verification is temporarily unavailable. Please try again later.', 'cap-captcha' ),
		);
		$message = isset( $messages[ $type ] ) ? $messages[ $type ] : $messages['invalid'];
		return apply_filters( 'cap_captcha_error_message', $message, $type );
	}
}
