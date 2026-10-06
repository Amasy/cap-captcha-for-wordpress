<?php
/**
 * Remove plugin options on uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cap_captcha_options' );
