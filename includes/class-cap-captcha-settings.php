<?php
/**
 * Settings → Cap CAPTCHA admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cap_Captcha_Settings {

	const PAGE = 'cap-captcha';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'wp_ajax_cap_captcha_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	public static function menu() {
		add_options_page(
			__( 'Cap CAPTCHA', 'cap-captcha' ),
			__( 'Cap CAPTCHA', 'cap-captcha' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}

	public static function register() {
		register_setting(
			'cap_captcha',
			CAP_CAPTCHA_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => cap_captcha_defaults(),
			)
		);
	}

	/**
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = (array) $input;
		$old   = (array) get_option( CAP_CAPTCHA_OPTION, array() );
		$out   = cap_captcha_defaults();

		foreach ( array( 'instance_url', 'script_url', 'wasm_url', 'hashwx_url' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? esc_url_raw( trim( $input[ $key ] ), array( 'https', 'http' ) ) : '';
		}
		$out['instance_url'] = untrailingslashit( $out['instance_url'] );

		$out['site_key'] = isset( $input['site_key'] ) ? sanitize_text_field( trim( $input['site_key'] ) ) : '';

		// Blank secret field means "keep the stored one".
		$secret            = isset( $input['secret_key'] ) ? sanitize_text_field( trim( $input['secret_key'] ) ) : '';
		$out['secret_key'] = '' !== $secret ? $secret : ( isset( $old['secret_key'] ) ? $old['secret_key'] : '' );

		$valid        = array_keys( Cap_Captcha_Integrations::forms() );
		$out['forms'] = isset( $input['forms'] ) ? array_values( array_intersect( (array) $input['forms'], $valid ) ) : array();

		foreach ( array( 'skip_logged_in', 'fail_open', 'disable_haptics' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$out['label_initial'] = isset( $input['label_initial'] ) ? sanitize_text_field( $input['label_initial'] ) : '';
		$out['label_solved']  = isset( $input['label_solved'] ) ? sanitize_text_field( $input['label_solved'] ) : '';

		return $out;
	}

	public static function notice() {
		if ( cap_captcha_is_configured() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'settings_page_' . self::PAGE === $screen->id ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Cap CAPTCHA is installed but not protecting anything yet.', 'cap-captcha' ),
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
			esc_html__( 'Add your Cap instance and keys.', 'cap-captcha' )
		);
	}

	/**
	 * AJAX: verify a token from the preview widget, end-to-end.
	 */
	public static function ajax_test() {
		check_ajax_referer( 'cap_captcha_test' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'cap-captcha' ) ), 403 );
		}
		$token  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$result = Cap_Captcha_Verifier::verify( $token );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Token verified by your Cap server. Everything is wired up correctly.', 'cap-captcha' ) ) );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o            = cap_captcha_option();
		$name         = CAP_CAPTCHA_OPTION;
		$secret_const = defined( 'CAP_CAPTCHA_SECRET_KEY' ) && CAP_CAPTCHA_SECRET_KEY;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cap CAPTCHA', 'cap-captcha' ); ?></h1>
			<p>
				<?php esc_html_e( 'Cap is a self-hosted, privacy-first proof-of-work CAPTCHA. Run a Cap Standalone server, create a site key in its dashboard, then enter the details below.', 'cap-captcha' ); ?>
				<a href="https://trycap.dev/guide/" target="_blank" rel="noopener"><?php esc_html_e( 'Setup guide', 'cap-captcha' ); ?></a>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'cap_captcha' ); ?>

				<h2><?php esc_html_e( 'Connection', 'cap-captcha' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cap-instance"><?php esc_html_e( 'Instance URL', 'cap-captcha' ); ?></label></th>
						<td>
							<input id="cap-instance" type="url" class="regular-text" name="<?php echo esc_attr( $name ); ?>[instance_url]" value="<?php echo esc_attr( $o['instance_url'] ); ?>" placeholder="https://cap.example.com" />
							<p class="description"><?php esc_html_e( 'Public URL of your Cap server. Visitors\' browsers must be able to reach it, so not localhost.', 'cap-captcha' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cap-site-key"><?php esc_html_e( 'Site key', 'cap-captcha' ); ?></label></th>
						<td><input id="cap-site-key" type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[site_key]" value="<?php echo esc_attr( $o['site_key'] ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cap-secret"><?php esc_html_e( 'Secret key', 'cap-captcha' ); ?></label></th>
						<td>
							<?php if ( $secret_const ) : ?>
								<p><?php esc_html_e( 'Set by CAP_CAPTCHA_SECRET_KEY in wp-config.php.', 'cap-captcha' ); ?></p>
							<?php else : ?>
								<input id="cap-secret" type="password" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[secret_key]" value="" autocomplete="new-password" placeholder="<?php echo $o['secret_key'] ? esc_attr__( '•••••••• (saved — leave blank to keep)', 'cap-captcha' ) : ''; ?>" />
								<p class="description"><?php esc_html_e( 'The site key\'s secret from your Cap dashboard — not the dashboard ADMIN_KEY. You can also define CAP_CAPTCHA_SECRET_KEY in wp-config.php.', 'cap-captcha' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Protected forms', 'cap-captcha' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$help_html = array(
						'code'   => array(),
						'strong' => array(),
						'em'     => array(),
						'a'      => array( 'href' => array() ),
					);
					foreach ( Cap_Captcha_Integrations::all() as $integration ) :
						$active = $integration->is_available();
						$forms  = $integration->forms();
						?>
						<tr>
							<th scope="row">
								<?php echo esc_html( $integration->name() ); ?>
								<?php if ( ! $active ) : ?>
									<br /><em style="font-weight:normal;"><?php esc_html_e( 'Not active', 'cap-captcha' ); ?></em>
								<?php endif; ?>
							</th>
							<td<?php echo $active ? '' : ' style="opacity:.6;"'; ?>>
								<?php if ( $forms ) : ?>
									<fieldset>
										<legend class="screen-reader-text"><?php echo esc_html( $integration->name() ); ?></legend>
										<?php foreach ( $forms as $slug => $label ) : ?>
											<label style="display:block;margin-bottom:6px;">
												<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[forms][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $o['forms'], true ) ); ?> />
												<?php echo esc_html( $label ); ?>
											</label>
										<?php endforeach; ?>
									</fieldset>
								<?php endif; ?>
								<?php if ( $integration->help() ) : ?>
									<p class="description"><?php echo wp_kses( $integration->help(), $help_html ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Other forms', 'cap-captcha' ); ?></th>
						<td><p class="description"><?php echo wp_kses( __( 'Place <code>[cap_captcha]</code> or <code>cap_captcha_widget()</code> inside the form, and check it with <code>cap_captcha_verify()</code> when handling the submission.', 'cap-captcha' ), $help_html ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logged-in users', 'cap-captcha' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[skip_logged_in]" value="1" <?php checked( $o['skip_logged_in'] ); ?> /> <?php esc_html_e( 'Skip the check on comments and checkout for logged-in users', 'cap-captcha' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'If Cap is down', 'cap-captcha' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[fail_open]" value="1" <?php checked( $o['fail_open'] ); ?> /> <?php esc_html_e( 'Allow submissions when the Cap server can\'t be reached', 'cap-captcha' ); ?></label>
							<p class="description"><?php esc_html_e( 'Off by default (safer). Turn on if you\'d rather not lock users out of login during a Cap outage. Invalid tokens are always rejected.', 'cap-captcha' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Widget', 'cap-captcha' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cap-label-initial"><?php esc_html_e( 'Checkbox label', 'cap-captcha' ); ?></label></th>
						<td><input id="cap-label-initial" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[label_initial]" value="<?php echo esc_attr( $o['label_initial'] ); ?>" placeholder="Verify you're human" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="cap-label-solved"><?php esc_html_e( 'Solved label', 'cap-captcha' ); ?></label></th>
						<td><input id="cap-label-solved" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[label_solved]" value="<?php echo esc_attr( $o['label_solved'] ); ?>" placeholder="You're human" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Haptics', 'cap-captcha' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[disable_haptics]" value="1" <?php checked( $o['disable_haptics'] ); ?> /> <?php esc_html_e( 'Disable vibration on mobile', 'cap-captcha' ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Self-hosting the widget (optional)', 'cap-captcha' ); ?></h2>
				<p><?php echo wp_kses_post( sprintf( __( 'By default the widget (v%s) and its WASM solvers load from jsDelivr. To serve them yourself, set <code>ENABLE_ASSETS_SERVER=true</code> on Cap Standalone and fill these in.', 'cap-captcha' ), CAP_CAPTCHA_WIDGET_VERSION ) ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					$assets = array(
						'script_url' => array( __( 'Widget script URL', 'cap-captcha' ), '/assets/widget.js' ),
						'wasm_url'   => array( __( 'Solver WASM URL', 'cap-captcha' ), '/assets/cap_wasm_bg.wasm' ),
						'hashwx_url' => array( __( 'HashWX WASM URL', 'cap-captcha' ), '/assets/hashwx.wasm' ),
					);
					foreach ( $assets as $key => $info ) :
						?>
						<tr>
							<th scope="row"><label for="cap-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $info[0] ); ?></label></th>
							<td><input id="cap-<?php echo esc_attr( $key ); ?>" type="url" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $o[ $key ] ); ?>" placeholder="https://cap.example.com<?php echo esc_attr( $info[1] ); ?>" /></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Test your setup', 'cap-captcha' ); ?></h2>
			<?php if ( ! cap_captcha_is_configured() ) : ?>
				<p><?php esc_html_e( 'Save your instance URL, site key and secret key, then come back here to test.', 'cap-captcha' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Solve the widget below. The token is sent to your Cap server\'s /siteverify, exactly as a real form would.', 'cap-captcha' ); ?></p>
				<div id="cap-captcha-test"><?php Cap_Captcha_Widget::render( 'admin-test' ); ?></div>
				<p id="cap-captcha-test-result" aria-live="polite"></p>
				<script>
				( function () {
					var wrap = document.getElementById( 'cap-captcha-test' );
					var out  = document.getElementById( 'cap-captcha-test-result' );
					var w    = wrap && wrap.querySelector( 'cap-widget' );
					if ( ! w ) { return; }
					function show( ok, msg ) {
						out.innerHTML = '';
						var s = document.createElement( 'strong' );
						s.style.color = ok ? '#008a20' : '#d63638';
						s.textContent = msg;
						out.appendChild( s );
					}
					w.addEventListener( 'error', function ( e ) {
						show( false, '<?php echo esc_js( __( 'Widget error:', 'cap-captcha' ) ); ?> ' + ( e.detail && e.detail.message ? e.detail.message : '' ) + ' <?php echo esc_js( __( '(check the instance URL, site key and CORS settings)', 'cap-captcha' ) ); ?>' );
					} );
					w.addEventListener( 'solve', function ( e ) {
						var body = new FormData();
						body.append( 'action', 'cap_captcha_test' );
						body.append( '_ajax_nonce', '<?php echo esc_js( wp_create_nonce( 'cap_captcha_test' ) ); ?>' );
						body.append( 'token', e.detail.token );
						fetch( ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' } )
							.then( function ( r ) { return r.json(); } )
							.then( function ( r ) { show( !! r.success, r.data && r.data.message ? r.data.message : 'Unknown response' ); } )
							.catch( function () { show( false, 'Request failed.' ); } );
					} );
				} )();
				</script>
			<?php endif; ?>
		</div>
		<?php
	}
}
