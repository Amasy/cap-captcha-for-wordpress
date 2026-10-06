<?php
/**
 * Gravity Forms: a "Cap CAPTCHA" field type.
 *
 * Add the field from Advanced Fields in the form editor. Forms without the
 * field are left alone.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cap_Captcha_Gravity_Forms extends Cap_Captcha_Integration {

	const FIELD_TYPE = 'cap_captcha';

	public function id() {
		return 'gravity-forms';
	}

	public function name() {
		return 'Gravity Forms';
	}

	public function is_available() {
		return class_exists( 'GFForms' );
	}

	public function help() {
		return __( 'Add the <strong>Cap CAPTCHA</strong> field from <em>Advanced Fields</em> in the form editor. Only forms containing the field are checked. On multi-page forms, put it on the last page.', 'cap-captcha' );
	}

	public function init() {
		if ( did_action( 'gform_loaded' ) ) {
			$this->register_field();
		} else {
			add_action( 'gform_loaded', array( $this, 'register_field' ), 5 );
		}

		// Late priority: run after Gravity Forms' own field validation.
		add_filter( 'gform_validation', array( $this, 'validate' ), 20 );
	}

	public function register_field() {
		if ( ! class_exists( 'GF_Field' ) || ! class_exists( 'GF_Fields' ) ) {
			return;
		}
		require_once __DIR__ . '/class-cap-captcha-gf-field.php';
		GF_Fields::register( new Cap_Captcha_GF_Field() );
	}

	/**
	 * @param array $validation_result { is_valid: bool, form: array }.
	 * @return array
	 */
	public function validate( $validation_result ) {
		if ( ! cap_captcha_form_enabled( 'gravityforms' ) ) {
			return $validation_result;
		}

		$form   = $validation_result['form'];
		$fields = $this->cap_fields( $form );
		if ( ! $fields ) {
			return $validation_result;
		}

		// Don't spend the single-use token on a submission that fails anyway.
		if ( empty( $validation_result['is_valid'] ) ) {
			return $validation_result;
		}

		// Only on final submission, not on Next/Previous page or Save & Continue.
		$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$target = isset( $_POST[ 'gform_target_page_number_' . $form_id ] ) ? absint( $_POST[ 'gform_target_page_number_' . $form_id ] ) : 0;
		$saving = ! empty( $_POST['gform_save'] );
		// phpcs:enable
		if ( 0 !== $target || $saving ) {
			return $validation_result;
		}

		$verified = cap_captcha_verify();
		if ( ! is_wp_error( $verified ) ) {
			return $validation_result;
		}

		foreach ( $fields as $field ) {
			$custom                     = isset( $field->errorMessage ) ? trim( (string) $field->errorMessage ) : '';
			$field->failed_validation  = true;
			$field->validation_message = '' !== $custom ? $custom : $verified->get_error_message();
		}

		$validation_result['is_valid'] = false;
		$validation_result['form']     = $form;
		return $validation_result;
	}

	/**
	 * Cap fields in a form.
	 *
	 * @param array $form Form.
	 * @return array
	 */
	private function cap_fields( $form ) {
		$found = array();
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $found;
		}
		foreach ( $form['fields'] as $field ) {
			if ( is_object( $field ) && isset( $field->type ) && self::FIELD_TYPE === $field->type ) {
				$found[] = $field;
			}
		}
		return $found;
	}
}
