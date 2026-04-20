<?php
/*
 * Addon Name: Passwords
 * Description: Adds password fields to the registration form. No additional settings required.
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Custom_Passwords' ) ) :

class TML_Classic_Custom_Passwords extends TML_Classic_Abstract {
	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	protected function load() {
		add_action( 'register_form',               array( $this, 'password_fields'             ) );
		add_filter( 'registration_errors',         array( $this, 'password_errors'             ) );
		add_filter( 'random_password',             array( $this, 'set_password'                ) );
		add_action( 'signup_extra_fields',         array( $this, 'ms_password_fields'          ) );
		add_action( 'signup_hidden_fields',        array( $this, 'ms_hidden_password_field'    ) );
		add_filter( 'wpmu_validate_user_signup',   array( $this, 'ms_password_errors'          ) );
		add_filter( 'add_signup_meta',             array( $this, 'ms_save_password'            ) );
		add_action( 'register_new_user',           array( $this, 'remove_default_password_nag' ) );
		add_action( 'approve_user',                array( $this, 'remove_default_password_nag' ) );
		add_filter( 'tml_register_passmail_template_message', array( $this, 'register_passmail_template_message' ) );
		add_action( 'tml_request',                            array( $this, 'action_messages'                    ) );
		add_filter( 'registration_redirect',                  array( $this, 'registration_redirect' ) );
	}

	public function password_fields() {
		$template = TML_Classic::get_object()->get_current_instance();
		?>
		<p class="tml-user-pass1-wrap">
			<label for="pass1<?php $template->the_instance(); ?>"><?php esc_html_e( 'Password', 'tml-classic' ); ?></label>
			<input autocomplete="off" name="pass1" id="pass1<?php $template->the_instance(); ?>" class="input" size="20" value="" type="password">
		</p>
		<p class="tml-user-pass2-wrap">
			<label for="pass2<?php $template->the_instance(); ?>"><?php esc_html_e( 'Confirm Password', 'tml-classic' ); ?></label>
			<input autocomplete="off" name="pass2" id="pass2<?php $template->the_instance(); ?>" class="input" size="20" value="" type="password">
		</p>
		<?php
	}

	public function ms_password_fields() {
		$tml      = TML_Classic::get_object();
		$template = $tml->get_active_instance();
		$errors   = array();
		foreach ( $tml->errors->get_error_codes() as $code ) {
			if ( in_array( $code, array( 'empty_password', 'password_mismatch', 'password_length' ), true ) )
				$errors[] = $tml->errors->get_error_message( $code );
		}
		?>
		<label for="pass1<?php $template->the_instance(); ?>"><?php esc_html_e( 'Password:', 'tml-classic' ); ?></label>
		<?php if ( !empty( $errors ) ) { ?>
			<p class="error"><?php echo implode( '<br>', array_map( 'esc_html', $errors ) ); ?></p>
		<?php } ?>
		<input autocomplete="off" name="pass1" id="pass1<?php $template->the_instance(); ?>" class="input" size="20" value="" type="password"><br>
		<span class="hint"><?php echo esc_html( apply_filters( 'tml_password_hint', sprintf( /* translators: %d: Minimum password length */ __( '(Must be at least %d characters.)', 'tml-classic' ), apply_filters( 'tml_minimum_password_length', 6 ) ) ) ); ?></span>
		<label for="pass2<?php $template->the_instance(); ?>"><?php esc_html_e( 'Confirm Password:', 'tml-classic' ); ?></label>
		<input autocomplete="off" name="pass2" id="pass2<?php $template->the_instance(); ?>" class="input" size="20" value="" type="password"><br>
		<span class="hint"><?php echo esc_html( apply_filters( 'tml_password_confirm_hint', __( "Confirm that you've typed your password correctly.", 'tml-classic' ) ) ); ?></span>
		<?php
	}

	public function ms_hidden_password_field() {
		if ( isset( $_POST['user_pass'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			echo '<input type="hidden" name="user_pass" value="' . esc_attr( sanitize_text_field( wp_unslash( $_POST['user_pass'] ) ) ) . '">' . "\n"; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public function password_errors( $errors = '' ) {
		if ( empty( $errors ) )
			$errors = new WP_Error();
		if ( empty( $_POST['pass1'] ) || empty( $_POST['pass2'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$errors->add( 'empty_password', __( '<strong>ERROR</strong>: Please enter your password twice.', 'tml-classic' ) );
		} elseif ( false !== strpos( sanitize_text_field( wp_unslash( $_POST['pass1'] ) ), '\\' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
			$errors->add( 'password_backslash', __( '<strong>ERROR</strong>: Passwords may not contain the character "\".', 'tml-classic' ) );
		} elseif ( $_POST['pass1'] !== $_POST['pass2'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
			$errors->add( 'password_mismatch', __( '<strong>ERROR</strong>: Please enter the same password in the two password fields.', 'tml-classic' ) );
		} elseif ( strlen( sanitize_text_field( wp_unslash( $_POST['pass1'] ) ) ) < apply_filters( 'tml_minimum_password_length', 6 ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
			/* translators: %d: Minimum password length */
			$errors->add( 'password_length', sprintf( __( '<strong>ERROR</strong>: Your password must be at least %d characters in length.', 'tml-classic' ), apply_filters( 'tml_minimum_password_length', 6 ) ) );
		} else {
			$_POST['user_pass'] = $_POST['pass1']; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
		}
		return $errors;
	}

	public function ms_password_errors( $result ) {
		if ( isset( $_POST['stage'] ) && 'validate-user-signup' === sanitize_text_field( wp_unslash( $_POST['stage'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$errors = $this->password_errors();
			foreach ( $errors->get_error_codes() as $code ) {
				foreach ( $errors->get_error_messages( $code ) as $error ) {
					$result['errors']->add( $code, preg_replace( '/<strong>([^<]+)<\/strong>: /', '', $error ) );
				}
			}
		}
		return $result;
	}

	public function ms_save_password( $meta ) {
		if ( isset( $_POST['user_pass'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$meta['user_pass'] = sanitize_text_field( wp_unslash( $_POST['user_pass'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
		return $meta;
	}

	public function set_password( $password ) {
		global $wpdb;
		remove_filter( 'random_password', array( $this, 'set_password' ) );
		if ( is_multisite() && isset( $_REQUEST['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$activation_key = sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $meta = $wpdb->get_var( $wpdb->prepare( "SELECT meta FROM $wpdb->signups WHERE activation_key = %s", $activation_key ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$meta = unserialize( $meta );
				if ( isset( $meta['user_pass'] ) ) {
					$password = $meta['user_pass'];
					unset( $meta['user_pass'] );
					$wpdb->update( $wpdb->signups, array( 'meta' => serialize( $meta ) ), array( 'activation_key' => $activation_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}
		} elseif ( !empty( $_POST['user_pass'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$password = sanitize_text_field( wp_unslash( $_POST['user_pass'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
		}
		return $password;
	}

	public function remove_default_password_nag( $user_id ) {
		update_user_meta( $user_id, 'default_password_nag', false );
	}

	public function register_passmail_template_message() {
		return;
	}

	public function action_messages( &$tml ) {
		if ( isset( $_GET['registration'] ) && 'complete' === $_GET['registration'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tml->errors->add( 'registration_complete', __( 'Registration complete. You may now log in.', 'tml-classic' ), 'message' );
	}

	public function registration_redirect( $redirect_to ) {
		$redirect_to = TML_Classic::get_page_link( 'login', 'registration=complete' );
		if ( !empty( $_REQUEST['instance'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$redirect_to = add_query_arg( 'instance', (int) $_REQUEST['instance'], $redirect_to ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $redirect_to;
	}
}

TML_Classic_Custom_Passwords::get_object();

endif;