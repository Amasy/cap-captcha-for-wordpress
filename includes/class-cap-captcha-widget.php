<?php
/**
 * Widget script loading and <cap-widget> markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cap_Captcha_Widget {

	const HANDLE = 'cap-widget';

	/**
	 * Whether the script has been queued for this page.
	 *
	 * @var bool
	 */
	private static $needed = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_shortcode( 'cap_captcha', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Script URL: a self-hosted override, or the pinned jsDelivr build.
	 *
	 * @return string
	 */
	public static function script_url() {
		$custom = cap_captcha_option( 'script_url' );
		$url    = $custom ? $custom : 'https://cdn.jsdelivr.net/npm/cap-widget@' . CAP_CAPTCHA_WIDGET_VERSION;
		return apply_filters( 'cap_captcha_script_url', $url );
	}

	public static function register() {
		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		wp_register_script( self::HANDLE, self::script_url(), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- version is in the URL.

		// Globals the widget reads before it solves (self-hosted WASM, haptics).
		$globals = array();
		$wasm    = cap_captcha_option( 'wasm_url' );
		$hashwx  = cap_captcha_option( 'hashwx_url' );
		if ( $wasm ) {
			$globals[] = 'window.CAP_CUSTOM_WASM_URL=' . wp_json_encode( esc_url_raw( $wasm ) ) . ';';
		}
		if ( $hashwx ) {
			$globals[] = 'window.CAP_CUSTOM_HASHWX_URL=' . wp_json_encode( esc_url_raw( $hashwx ) ) . ';';
		}
		if ( cap_captcha_option( 'disable_haptics' ) ) {
			$globals[] = 'window.CAP_DISABLE_HAPTICS=true;';
		}
		if ( $globals ) {
			wp_add_inline_script( self::HANDLE, implode( '', $globals ), 'before' );
		}

		// Reset the widget after a failed WooCommerce AJAX checkout, since the token was consumed.
		wp_add_inline_script(
			self::HANDLE,
			'(function(){if(!window.jQuery)return;jQuery(document.body).on("checkout_error",function(){document.querySelectorAll("cap-widget").forEach(function(w){if(typeof w.reset==="function"){w.reset();}else{var c=w.cloneNode(false);w.parentNode.replaceChild(c,w);}});});})();'
		);
	}

	/**
	 * Return the widget markup and make sure the script is printed.
	 *
	 * @param string $context Where it is rendered (for filters/styling).
	 * @return string
	 */
	public static function markup( $context = 'custom' ) {
		if ( ! cap_captcha_is_configured() ) {
			return '';
		}

		self::register();
		wp_enqueue_script( self::HANDLE );
		self::$needed = true;

		$attrs = array(
			'data-cap-api-endpoint' => cap_captcha_endpoint(),
			'required'              => '',
		);
		if ( cap_captcha_option( 'label_initial' ) ) {
			$attrs['data-cap-i18n-initial-state'] = cap_captcha_option( 'label_initial' );
		}
		if ( cap_captcha_option( 'label_solved' ) ) {
			$attrs['data-cap-i18n-solved-label'] = cap_captcha_option( 'label_solved' );
		}
		$attrs['data-cap-i18n-required-label'] = Cap_Captcha_Verifier::message( 'missing' );

		/**
		 * Filter widget attributes (e.g. add data-cap-i18n-* or data-cap-worker-count).
		 *
		 * @param array  $attrs   Attribute => value.
		 * @param string $context Render context.
		 */
		$attrs = apply_filters( 'cap_captcha_widget_attributes', $attrs, $context );

		$html = '';
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . esc_attr( $name ) . ( '' === $value ? '' : '="' . esc_attr( $value ) . '"' );
		}

		return sprintf(
			'<div class="cap-captcha-wrap cap-captcha-%1$s" style="margin:0 0 16px;"><cap-widget%2$s></cap-widget></div>',
			esc_attr( sanitize_html_class( $context ) ),
			$html
		);
	}

	/**
	 * Echo the widget.
	 *
	 * @param string $context Render context.
	 */
	public static function render( $context = 'custom' ) {
		echo self::markup( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup().
	}

	/**
	 * [cap_captcha] shortcode for custom forms.
	 *
	 * @return string
	 */
	public static function shortcode() {
		return self::markup( 'shortcode' );
	}
}
