<?php
/*
 * Addon Name: Captcha
 * Description: Adds captcha verification to forms. Supports reCAPTCHA v2, hCaptcha, and Cloudflare Turnstile.
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Captcha' ) ) :

class TML_Classic_Captcha extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic_captcha';

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		return array(
			'provider'   => '',
			'site_key'   => '',
			'secret_key' => '',
		);
	}

	protected function load() {
		$provider = $this->get_option( 'provider' );
		if ( empty( $provider ) || empty( $this->get_option( 'site_key' ) ) )
			return;
		add_action( 'login_form',            array( $this, 'render_widget'       ) );
		add_action( 'register_form',         array( $this, 'render_widget'       ) );
		add_action( 'lostpassword_form',     array( $this, 'render_widget'       ) );
		add_filter( 'authenticate',          array( $this, 'verify'              ), 20, 3 );
		add_filter( 'registration_errors',   array( $this, 'verify_registration' ), 20, 3 );
		add_action( 'lostpassword_post',     array( $this, 'verify_lostpassword' ), 20 );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_script'      ) );
		add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_script'      ) );
	}

	protected function get_script_url() {
		switch ( $this->get_option( 'provider' ) ) {
			case 'hcaptcha':
				return 'https://js.hcaptcha.com/1/api.js';
			case 'turnstile':
				return 'https://challenges.cloudflare.com/turnstile/v0/api.js';
			default:
				return 'https://www.google.com/recaptcha/api.js';
		}
	}

	protected function get_widget_class() {
		switch ( $this->get_option( 'provider' ) ) {
			case 'hcaptcha':
				return 'h-captcha';
			case 'turnstile':
				return 'cf-turnstile';
			default:
				return 'g-recaptcha';
		}
	}

	protected function get_response_field() {
		switch ( $this->get_option( 'provider' ) ) {
			case 'hcaptcha':
				return 'h-captcha-response';
			case 'turnstile':
				return 'cf-turnstile-response';
			default:
				return 'g-recaptcha-response';
		}
	}

	protected function get_verify_url() {
		switch ( $this->get_option( 'provider' ) ) {
			case 'hcaptcha':
				return 'https://hcaptcha.com/siteverify';
			case 'turnstile':
				return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
			default:
				return 'https://www.google.com/recaptcha/api/siteverify';
		}
	}

	public function enqueue_script() {
		wp_enqueue_script( 'tml-classic-captcha', $this->get_script_url(), array(), TML_CLASSIC_VERSION, true );
	}

	public function render_widget() {
		echo '<div class="' . esc_attr( $this->get_widget_class() ) . '" data-sitekey="' . esc_attr( $this->get_option( 'site_key' ) ) . '"></div>';
	}

	protected function verify_token( $token ) {
		if ( empty( $token ) )
			return false;
		$response = wp_remote_post( $this->get_verify_url(), array(
			'body' => array(
				'secret'   => $this->get_option( 'secret_key' ),
				'response' => $token,
			),
		) );
		if ( is_wp_error( $response ) )
			return false;
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return !empty( $body['success'] );
	}

	public function verify( $user, $username, $password ) {
		if ( empty( $username ) && empty( $password ) )
			return $user;
		if ( is_wp_error( $user ) )
			return $user;
		$token = isset( $_POST[ $this->get_response_field() ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->get_response_field() ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( !$this->verify_token( $token ) ) {
			return new WP_Error( 'captcha_failed', __( '<strong>ERROR</strong>: Captcha verification failed.', 'tml-classic' ) );
		}
		return $user;
	}

	public function verify_registration( $errors, $sanitized_user_login, $user_email ) {
		$token = isset( $_POST[ $this->get_response_field() ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->get_response_field() ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( !$this->verify_token( $token ) ) {
			$errors->add( 'captcha_failed', __( '<strong>ERROR</strong>: Captcha verification failed.', 'tml-classic' ) );
		}
		return $errors;
	}

	public function verify_lostpassword( $errors ) {
		$token = isset( $_POST[ $this->get_response_field() ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->get_response_field() ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( !$this->verify_token( $token ) ) {
			if ( is_wp_error( $errors ) ) {
				$errors->add( 'captcha_failed', __( '<strong>ERROR</strong>: Captcha verification failed.', 'tml-classic' ) );
			}
		}
	}
}

TML_Classic_Captcha::get_object();

endif;

if ( is_admin() && !class_exists( 'TML_Classic_Captcha_Admin' ) ) :

class TML_Classic_Captcha_Admin extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic_captcha';

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		return TML_Classic_Captcha::default_options();
	}

	protected function load() {
		add_action( 'tml_uninstall_captcha.php', array( $this, 'uninstall'  ) );
		add_action( 'admin_menu',                array( $this, 'admin_menu' ) );
		add_action( 'admin_init',                array( $this, 'admin_init' ) );
	}

	public function uninstall() {
		delete_option( $this->options_key );
	}

	public function admin_menu() {
		add_submenu_page(
			'tml_classic',
			__( 'TML Classic Captcha Settings', 'tml-classic' ),
			__( 'Captcha', 'tml-classic' ),
			'manage_options',
			$this->options_key,
			array( $this, 'settings_page' )
		);
	}

	public function admin_init() {
		register_setting( $this->options_key, $this->options_key, array( $this, 'save_settings' ) );
		add_settings_section( 'general', null, '__return_false', $this->options_key );
		add_settings_field( 'provider',   __( 'Provider',   'tml-classic' ), array( $this, 'field_provider'   ), $this->options_key, 'general' );
		add_settings_field( 'site_key',   __( 'Site Key',   'tml-classic' ), array( $this, 'field_site_key'   ), $this->options_key, 'general' );
		add_settings_field( 'secret_key', __( 'Secret Key', 'tml-classic' ), array( $this, 'field_secret_key' ), $this->options_key, 'general' );
	}

	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TML Classic Captcha Settings', 'tml-classic' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( $this->options_key ); ?>
				<?php do_settings_sections( $this->options_key ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function field_provider() {
		$provider = $this->get_option( 'provider' );
		$options  = array(
			''          => __( 'Disabled',               'tml-classic' ),
			'recaptcha' => __( 'reCAPTCHA v2 (Google)',  'tml-classic' ),
			'hcaptcha'  => __( 'hCaptcha',               'tml-classic' ),
			'turnstile' => __( 'Turnstile (Cloudflare)', 'tml-classic' ),
		);
		echo '<select name="' . esc_attr( $this->options_key ) . '[provider]">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $provider, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function field_site_key() {
		echo '<input type="text" name="' . esc_attr( $this->options_key ) . '[site_key]" value="' . esc_attr( $this->get_option( 'site_key' ) ) . '" class="regular-text">';
	}

	public function field_secret_key() {
		echo '<input type="text" name="' . esc_attr( $this->options_key ) . '[secret_key]" value="' . esc_attr( $this->get_option( 'secret_key' ) ) . '" class="regular-text">';
	}

	public function save_settings( $settings ) {
		$allowed_providers = array( '', 'recaptcha', 'hcaptcha', 'turnstile' );
		return array(
			'provider'   => in_array( $settings['provider'], $allowed_providers, true ) ? $settings['provider'] : '',
			'site_key'   => sanitize_text_field( $settings['site_key'] ),
			'secret_key' => sanitize_text_field( $settings['secret_key'] ),
		);
	}
}

TML_Classic_Captcha_Admin::get_object();

endif;