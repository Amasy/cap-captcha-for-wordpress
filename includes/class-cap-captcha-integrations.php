<?php
/**
 * Registry of form integrations. Each one is loaded only if the plugin it
 * targets is active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/integrations/class-cap-captcha-integration.php';
require_once __DIR__ . '/integrations/class-cap-captcha-core.php';
require_once __DIR__ . '/integrations/class-cap-captcha-woocommerce.php';
require_once __DIR__ . '/integrations/class-cap-captcha-contact-form-7.php';
require_once __DIR__ . '/integrations/class-cap-captcha-gravity-forms.php';

class Cap_Captcha_Integrations {

	/**
	 * @var Cap_Captcha_Integration[]|null
	 */
	private static $all = null;

	/**
	 * All known integrations, active or not.
	 *
	 * @return Cap_Captcha_Integration[]
	 */
	public static function all() {
		if ( null === self::$all ) {
			$list = array(
				new Cap_Captcha_Core(),
				new Cap_Captcha_WooCommerce(),
				new Cap_Captcha_Contact_Form_7(),
				new Cap_Captcha_Gravity_Forms(),
			);

			/**
			 * Add your own integrations (instances of Cap_Captcha_Integration).
			 *
			 * @param Cap_Captcha_Integration[] $list Integrations.
			 */
			$list = apply_filters( 'cap_captcha_integrations', $list );

			self::$all = array();
			foreach ( $list as $integration ) {
				if ( $integration instanceof Cap_Captcha_Integration ) {
					self::$all[ $integration->id() ] = $integration;
				}
			}
		}
		return self::$all;
	}

	/**
	 * Hook up every integration whose plugin is active.
	 */
	public static function init() {
		foreach ( self::all() as $integration ) {
			if ( $integration->is_available() ) {
				$integration->init();
			}
		}
	}

	/**
	 * Every form with an on/off checkbox: slug => label.
	 *
	 * @return array
	 */
	public static function forms() {
		$forms = array();
		foreach ( self::all() as $integration ) {
			$forms += $integration->forms();
		}
		return apply_filters( 'cap_captcha_forms', $forms );
	}
}
