<?php
/**
 * Contact Form 7: a [cap_captcha] form-tag.
 *
 * Add [cap_captcha] to any form (or use the "Cap CAPTCHA" button in the form
 * editor). Forms without the tag are left alone.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cap_Captcha_Contact_Form_7 extends Cap_Captcha_Integration {

	const TAG = 'cap_captcha';

	/**
	 * Fixed field name, so CF7 can show the error next to the widget.
	 */
	const FIELD_NAME = 'cap-captcha';

	public function id() {
		return 'contact-form-7';
	}

	public function name() {
		return 'Contact Form 7';
	}

	public function is_available() {
		return defined( 'WPCF7_VERSION' );
	}

	public function help() {
		return __( 'Add <code>[cap_captcha]</code> to a form, or use the <strong>Cap CAPTCHA</strong> button in the form editor. Only forms containing the tag are checked.', 'cap-captcha' );
	}

	public function init() {
		if ( did_action( 'wpcf7_init' ) ) {
			$this->register_tag();
		} else {
			add_action( 'wpcf7_init', array( $this, 'register_tag' ) );
		}
		add_action( 'wpcf7_admin_init', array( $this, 'register_tag_generator' ), 60 );

		// Late priority: run after CF7's own field validation.
		add_filter( 'wpcf7_validate', array( $this, 'validate' ), 20, 2 );
	}

	public function register_tag() {
		wpcf7_add_form_tag( self::TAG, array( $this, 'render' ), array( 'display-block' => true ) );
	}

	/**
	 * Form-tag handler.
	 *
	 * @param WPCF7_FormTag $tag Tag.
	 * @return string
	 */
	public function render( $tag ) {
		if ( ! cap_captcha_form_enabled( 'cf7' ) ) {
			return '';
		}

		$classes = array( 'wpcf7-form-control-wrap' );
		$extra   = $tag->get_class_option( '' );
		if ( $extra ) {
			$classes[] = $extra;
		}

		return sprintf(
			'<span class="%1$s" data-name="%2$s">%3$s</span>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( self::FIELD_NAME ),
			Cap_Captcha_Widget::markup( 'cf7' )
		);
	}

	/**
	 * Verify the token once every other field has passed, so a single-use
	 * token isn't spent on a submission CF7 would reject anyway.
	 *
	 * @param WPCF7_Validation $result Result.
	 * @param WPCF7_FormTag[]  $tags   Tags in the submitted form.
	 * @return WPCF7_Validation
	 */
	public function validate( $result, $tags ) {
		if ( ! cap_captcha_form_enabled( 'cf7' ) || ! $result->is_valid() ) {
			return $result;
		}

		$has_tag = false;
		foreach ( (array) $tags as $tag ) {
			if ( isset( $tag->basetype ) && self::TAG === $tag->basetype ) {
				$has_tag = true;
				break;
			}
		}
		if ( ! $has_tag ) {
			return $result;
		}

		$verified = cap_captcha_verify();
		if ( is_wp_error( $verified ) ) {
			$result->invalidate(
				array(
					'type'     => self::TAG,
					'basetype' => self::TAG,
					'name'     => self::FIELD_NAME,
				),
				$verified->get_error_message()
			);
		}
		return $result;
	}

	/**
	 * "Cap CAPTCHA" button in the CF7 form editor.
	 */
	public function register_tag_generator() {
		if ( ! class_exists( 'WPCF7_TagGenerator' ) ) {
			return;
		}
		WPCF7_TagGenerator::get_instance()->add(
			self::TAG,
			__( 'Cap CAPTCHA', 'cap-captcha' ),
			array( $this, 'tag_generator_panel' ),
			array( 'version' => '2' )
		);
	}

	/**
	 * @param WPCF7_ContactForm $contact_form Form.
	 * @param array             $options      Panel options.
	 */
	public function tag_generator_panel( $contact_form, $options ) {
		$tgg = new WPCF7_TagGeneratorGenerator( $options['content'] );
		?>
		<header class="description-box">
			<h3><?php esc_html_e( 'Cap CAPTCHA form-tag generator', 'cap-captcha' ); ?></h3>
			<p>
				<?php esc_html_e( 'Adds the Cap proof-of-work checkbox. Submissions are rejected unless it is solved.', 'cap-captcha' ); ?>
				<?php if ( ! cap_captcha_is_configured() ) : ?>
					<br /><strong><?php esc_html_e( 'Cap CAPTCHA is not configured yet, so the tag renders nothing until you add your keys under Settings → Cap CAPTCHA.', 'cap-captcha' ); ?></strong>
				<?php endif; ?>
			</p>
		</header>
		<div class="control-box">
			<?php
			$tgg->print(
				'field_type',
				array( 'select_options' => array( self::TAG => __( 'Cap CAPTCHA', 'cap-captcha' ) ) )
			);
			$tgg->print( 'class_attr' );
			?>
		</div>
		<footer class="insert-box">
			<?php $tgg->print( 'insert_box_content' ); ?>
		</footer>
		<?php
	}
}
