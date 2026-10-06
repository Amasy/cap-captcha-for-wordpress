<?php
/**
 * Gravity Forms field type: "Cap CAPTCHA" (Advanced Fields).
 *
 * Loaded only after Gravity Forms has defined GF_Field.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cap_Captcha_GF_Field extends GF_Field {

	/**
	 * @var string
	 */
	public $type = 'cap_captcha';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Cap CAPTCHA', 'cap-captcha' );
	}

	public function get_form_editor_field_description() {
		return esc_attr__( 'Adds the Cap proof-of-work checkbox. Place it on the last page of multi-page forms.', 'cap-captcha' );
	}

	public function get_form_editor_field_icon() {
		return 'gform-icon--recaptcha';
	}

	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	public function get_form_editor_field_settings() {
		return array(
			'label_setting',
			'label_placement_setting',
			'description_setting',
			'error_message_setting',
			'css_class_setting',
			'admin_label_setting',
		);
	}

	public function is_conditional_logic_supported() {
		return false;
	}

	/**
	 * @param array      $form  Form.
	 * @param string     $value Value.
	 * @param array|null $entry Entry.
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		if ( $this->is_form_editor() || $this->is_entry_detail() ) {
			return '<div class="ginput_container"><p>' . esc_html__( 'The Cap CAPTCHA checkbox appears here on the live form.', 'cap-captcha' ) . '</p></div>';
		}

		$markup = Cap_Captcha_Widget::markup( 'gravityforms' );
		if ( '' === $markup ) {
			return '';
		}
		return '<div class="ginput_container ginput_container_cap_captcha">' . $markup . '</div>';
	}

	/**
	 * Verification happens in the gform_validation filter, once every other
	 * field is valid, so the single-use token isn't spent early.
	 *
	 * @param string|array $value Value.
	 * @param array        $form  Form.
	 */
	public function validate( $value, $form ) {}

	/**
	 * Nothing worth storing in the entry.
	 *
	 * @return string
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		return '';
	}
}
