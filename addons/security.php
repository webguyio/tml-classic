<?php
/*
 * Addon Name: Security
 * Description: Adds security options like disabling wp-login.php, brute-force protection, and making the site private.
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Security' ) ) :
class TML_Classic_Security extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic_security';

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		return array(
			'private_site'  => 0,
			'private_login' => 0,
			'failed_login'  => array(
				'threshold'               => 20,
				'threshold_duration'      => 1,
				'threshold_duration_unit' => 'hour',
				'lockout_duration'        => 24,
				'lockout_duration_unit'   => 'hour'
			)
		);
	}

	protected function load() {
		add_action( 'init',                 array( $this, 'init'                 ) );
		add_action( 'template_redirect',    array( $this, 'template_redirect'    ) );
		add_action( 'tml_request_unlock',   array( $this, 'request_unlock'       ) );
		add_action( 'tml_request',          array( $this, 'action_messages'      ) );
		add_action( 'authenticate',         array( $this, 'authenticate'         ), 100, 3 );
		add_filter( 'allow_password_reset', array( $this, 'allow_password_reset' ),  10, 2 );
		add_filter( 'show_admin_bar',       array( $this, 'show_admin_bar'       ) );
		if ( is_admin() ) {
			add_action( 'tml_uninstall_security.php', array( $this, 'uninstall'           ) );
			add_action( 'admin_menu',                 array( $this, 'admin_menu'          ) );
			add_action( 'admin_init',                 array( $this, 'admin_init'          ) );
			add_action( 'load-users.php',             array( $this, 'load_users_page'     ) );
			add_filter( 'user_row_actions',           array( $this, 'user_row_actions'    ), 10, 2 );
			add_action( 'admin_notices',              array( $this, 'maybe_admin_notices' ) );
		}
	}

	public function uninstall() {
		delete_option( $this->options_key );
	}

	public function init() {
		global $wp_query, $pagenow;
		if ( 'wp-login.php' == $pagenow && $this->get_option( 'private_login' ) ) {
			parse_str( isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '', $q ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( !empty( $q['interim-login'] ) || !empty( $_REQUEST['interim-login'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			$pagenow = 'index.php';
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			if ( !$template = get_404_template() )
				$template = 'index.php';
			include( $template );
			exit;
		}
	}

	public function template_redirect() {
		if ( $private_site = apply_filters( 'tml_enforce_private_site', $this->get_option( 'private_site' ) ) ) {
			if ( !( is_user_logged_in() || TML_Classic::is_tml_page() ) ) {
				$redirect_to = apply_filters( 'tml_security_private_site_redirect', wp_login_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', true ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				wp_safe_redirect( $redirect_to );
				exit;
			}
		}
	}

	public function request_unlock() {
		$user = self::check_user_unlock_key( isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '', isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect_to = TML_Classic_Common::get_current_url();
		if ( is_wp_error( $user ) ) {
			$redirect_to = add_query_arg( 'unlock', 'invalidkey', $redirect_to );
			wp_safe_redirect( $redirect_to );
			exit;
		}
		self::unlock_user( $user->ID );
		$redirect_to = add_query_arg( 'unlock', 'complete', $redirect_to );
		wp_safe_redirect( $redirect_to );
		exit;
	}

	public function action_messages( &$tml_classic ) {
		if ( isset( $_GET['unlock'] ) && 'complete' == $_GET['unlock'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tml_classic->errors->add( 'unlock_complete', __( 'Your account has been unlocked. You may now log in.', 'tml-classic' ), 'message' );
	}

	public static function check_user_unlock_key( $key, $login ) {
		$key = preg_replace( '/[^a-z0-9]/i', '', $key );
		if ( empty( $key ) || !is_string( $key ) )
			return new WP_Error( 'invalid_key', __( 'Invalid key', 'tml-classic' ) );
		if ( empty( $login ) || !is_string( $login ) )
			return new WP_Error( 'invalid_key', __( 'Invalid key', 'tml-classic' ) );
		if ( !$user = get_user_by( 'login', $login ) )
			return new WP_Error( 'invalid_key', __( 'Invalid key', 'tml-classic' ) );
		if ( $key != self::get_user_unlock_key( $user->ID ) )
			return new WP_Error( 'invalid_key', __( 'Invalid key', 'tml-classic' ) );
		return $user;
	}

	public function authenticate( $user, $username, $password ) {
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$time    = time();
		$lockout = self::get_ip_lockout( $ip );
		if ( $lockout && $lockout > $time ) {
			/* translators: %s: Time until IP can try again */
			return new WP_Error( 'locked_account', sprintf( __( '<strong>ERROR</strong>: Too many failed login attempts. You may try again in %s.', 'tml-classic' ), human_time_diff( $time, $lockout ) ) );
		}
		if ( is_wp_error( $user ) && 'incorrect_password' == $user->get_error_code() ) {
			$threshold          = $this->get_option( array( 'failed_login', 'threshold' ) );
			$threshold_duration = self::get_seconds_from_unit( $this->get_option( array( 'failed_login', 'threshold_duration' ) ), $this->get_option( array( 'failed_login', 'threshold_duration_unit' ) ) );
			$lockout_duration   = self::get_seconds_from_unit( $this->get_option( array( 'failed_login', 'lockout_duration' ) ), $this->get_option( array( 'failed_login', 'lockout_duration_unit' ) ) );
			$transient_key      = 'tml_attempts_' . md5( $ip );
			$attempts           = get_transient( $transient_key );
			if ( !is_array( $attempts ) )
				$attempts = array();
			$attempts = array_filter( $attempts, function( $t ) use ( $time, $threshold_duration ) {
				return $t > $time - $threshold_duration;
			} );
			$attempts[] = $time;
			set_transient( $transient_key, $attempts, $threshold_duration );
			if ( count( $attempts ) >= $threshold ) {
				$expiration = $time + $lockout_duration;
				set_transient( 'tml_lockout_' . md5( $ip ), $expiration, $lockout_duration );
				delete_transient( $transient_key );
				/* translators: %s: Time until IP can try again */
				return new WP_Error( 'locked_account', sprintf( __( '<strong>ERROR</strong>: Too many failed login attempts. You may try again in %s.', 'tml-classic' ), human_time_diff( $time, $expiration ) ) );
			}
		} elseif ( !is_wp_error( $user ) ) {
			$field = is_email( $username ) ? 'email' : 'login';
			if ( $userdata = get_user_by( $field, $username ) ) {
				if ( self::is_user_locked( $userdata->ID ) ) {
					if ( $expiration = self::get_user_lock_expiration( $userdata->ID ) ) {
						if ( $time > $expiration )
							self::unlock_user( $userdata->ID );
						else
							return new WP_Error( 'locked_account', __( '<strong>ERROR</strong>: This account has been locked.', 'tml-classic' ) );
					} else {
						return new WP_Error( 'locked_account', __( '<strong>ERROR</strong>: This account has been locked.', 'tml-classic' ) );
					}
				}
			}
		}
		return $user;
	}

	public static function get_ip_lockout( $ip ) {
		return get_transient( 'tml_lockout_' . md5( $ip ) );
	}

	public function allow_password_reset( $allow, $user_id ) {
		if ( self::is_user_locked( $user_id ) && !self::get_user_lock_expiration( $user_id ) )
			$allow = false;
		return $allow;
	}

	public function show_admin_bar( $show ) {
		global $pagenow;
		if ( is_user_logged_in() && 'wp-login.php' == $pagenow && $this->get_option( 'private_login' ) )
			return true;
		return $show;
	}

	public static function lock_user( $user, $expires = 0 ) {
		if ( is_object( $user ) )
			$user = $user->ID;
		$user = (int) $user;
		do_action( 'tml_lock_user', $user );
		$security = self::get_security_meta( $user );
		$security['is_locked']       = true;
		$security['lock_expiration'] = absint( $expires );
		$security['unlock_key']      = wp_generate_password( 20, false );
		update_user_meta( $user, 'tml_classic_security', $security );
	}

	public static function unlock_user( $user ) {
		if ( is_object( $user ) )
			$user = $user->ID;
		$user = (int) $user;
		do_action( 'tml_unlock_user', $user );
		$security = self::get_security_meta( $user );
		$security['is_locked']       = false;
		$security['lock_expiration'] = 0;
		$security['unlock_key']      = '';
		return update_user_meta( $user, 'tml_classic_security', $security );
	}

	public static function is_user_locked( $user ) {
		if ( is_object( $user ) )
			$user = $user->ID;
		$user = (int) $user;
		$security = self::get_security_meta( $user );
		if ( !$security['is_locked'] )
			return false;
		if ( !$expires = self::get_user_lock_expiration( $user ) )
			return true;
		$time = time();
		if ( $time > $expires ) {
			self::unlock_user( $user );
			return false;
		}
		return true;
	}

	protected static function get_security_meta( $user_id ) {
		$defaults = array(
			'is_locked'       => false,
			'lock_expiration' => 0,
			'unlock_key'      => '',
		);
		$meta = get_user_meta( $user_id, 'tml_classic_security', true );
		if ( !is_array( $meta ) )
			$meta = array();
		return array_merge( $defaults, $meta );
	}

	public static function get_user_lock_expiration( $user_id ) {
		$security_meta = self::get_security_meta( $user_id );
		return apply_filters( 'tml_user_lock_expiration', absint( $security_meta['lock_expiration'] ), $user_id );
	}

	public static function get_user_unlock_key( $user_id ) {
		$security_meta = self::get_security_meta( $user_id );
		return apply_filters( 'tml_user_unlock_key', $security_meta['unlock_key'], $user_id );
	}

	public static function get_seconds_from_unit( $value, $unit = 'minute' ) {
		switch ( $unit ) {
			case 'day' :
				$value = $value * 24 * 60 * 60;
				break;
			case 'hour' :
				$value = $value * 60 * 60;
				break;
			case 'minute' :
				$value = $value * 60;
				break;
		}
		return $value;
	}

	public function admin_menu() {
		add_submenu_page(
			'tml_classic',
			__( 'TML Classic Security Settings', 'tml-classic' ),
			__( 'Security', 'tml-classic' ),
			'manage_options',
			$this->options_key,
			array( $this, 'settings_page' )
		);
		add_settings_section( 'general', null, '__return_false', $this->options_key );
		add_settings_field( 'private_site',   __( 'Private Site',   'tml-classic' ), array( $this, 'settings_field_private_site'   ), $this->options_key, 'general' );
		add_settings_field( 'private_login',  __( 'Private Login',  'tml-classic' ), array( $this, 'settings_field_private_login'  ), $this->options_key, 'general' );
		add_settings_field( 'login_attempts', __( 'Login Attempts', 'tml-classic' ), array( $this, 'settings_field_login_attempts' ), $this->options_key, 'general' );
		add_settings_field( 'two_factor', __( 'Two-Factor Authentication', 'tml-classic' ), array( $this, 'settings_field_two_factor' ), $this->options_key, 'general' );
	}

	public function admin_init() {
		register_setting( $this->options_key, $this->options_key, array( $this, 'save_settings' ) );
	}

	public function settings_page() {
		TML_Classic_Admin::settings_page( array(
			'title'       => __( 'TML Classic Security Settings', 'tml-classic' ),
			'options_key' => $this->options_key
		) );
	}

	public function settings_field_two_factor() {
		?>
		<p><?php
		/* translators: 1: Opening link tag, 2: Closing link tag */
		printf( __( 'For stronger protection, we strongly recommend installing %1$sTiny 2FA%2$s.', 'tml-classic' ), '<a href="https://wordpress.org/plugins/tiny-2fa/" target="_blank">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?></p>
		<?php
	}

	public function settings_field_private_site() {
		?>
		<input name="<?php echo esc_attr( $this->options_key ); ?>[private_site]" type="checkbox" id="<?php echo esc_attr( $this->options_key ); ?>_private_site" value="1"<?php checked( $this->get_option( 'private_site' ) ); ?>>
		<label for="<?php echo esc_attr( $this->options_key ); ?>_private_site"><?php esc_html_e( 'Require users to be logged in to view site', 'tml-classic' ); ?></label>
		<?php
	}

	public function settings_field_private_login() {
		?>
		<input name="<?php echo esc_attr( $this->options_key ); ?>[private_login]" type="checkbox" id="<?php echo esc_attr( $this->options_key ); ?>_private_login" value="1"<?php checked( $this->get_option( 'private_login' ) ); ?>>
		<label for="<?php echo esc_attr( $this->options_key ); ?>_private_login"><?php esc_html_e( 'Disable wp-login.php', 'tml-classic' ); ?></label>
		<?php
	}

	public function settings_field_login_attempts() {
		$units = array(
			'minute' => __( 'minute(s)', 'tml-classic' ),
			'hour'   => __( 'hour(s)',   'tml-classic' ),
			'day'    => __( 'day(s)',    'tml-classic' )
		);
		$threshold = '<input type="text" name="' . $this->options_key . '[failed_login][threshold]" id="' . $this->options_key . '_failed_login_threshold" value="' . esc_attr( $this->get_option( array( 'failed_login', 'threshold' ) ) ) . '" size="1">';
		$threshold_duration = '<input type="text" name="' . $this->options_key . '[failed_login][threshold_duration]" id="' . $this->options_key . '_failed_login_threshold_duration" value="' . esc_attr( $this->get_option( array( 'failed_login', 'threshold_duration' ) ) ) . '" size="1">';
		$threshold_duration_unit = '<select name="' . $this->options_key . '[failed_login][threshold_duration_unit]" id="' . $this->options_key . '_failed_login_threshold_duration_unit">';
		foreach ( $units as $unit => $label ) {
			$threshold_duration_unit .= '<option value="' . $unit . '"' . selected( $this->get_option( array( 'failed_login', 'threshold_duration_unit' ) ), $unit, false ) . '>' . $label . '</option>';
		}
		$threshold_duration_unit .= '</select>';
		$lockout_duration = '<input type="text" name="' . $this->options_key . '[failed_login][lockout_duration]" id="' . $this->options_key . '_failed_login_lockout_duration" value="' . esc_attr( $this->get_option( array( 'failed_login', 'lockout_duration' ) ) ) . '" size="1">';
		$lockout_duration_unit = '<select name="' . $this->options_key . '[failed_login][lockout_duration_unit]" id="' . $this->options_key . '_failed_login_lockout_duration_unit">';
		foreach ( $units as $unit => $label ) {
			$lockout_duration_unit .= '<option value="' . $unit . '"' . selected( $this->get_option( array( 'failed_login', 'lockout_duration_unit' ) ), $unit, false ) . '>' . $label . '</option>';
		}
		$lockout_duration_unit .= '</select>';
		/* translators: 1: Threshold count input, 2: Duration input, 3: Duration unit select, 4: Lockout duration input, 5: Lockout unit select */
		printf( __( 'After %1$s failed login attempts within %2$s %3$s, lockout the IP for %4$s %5$s.', 'tml-classic' ), $threshold, $threshold_duration, $threshold_duration_unit, $lockout_duration, $lockout_duration_unit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function save_settings( $settings ) {
		return array(
			'private_site'  => !empty( $settings['private_site']  ),
			'private_login' => !empty( $settings['private_login'] ),
			'failed_login'  => array(
				'threshold'               => absint( $settings['failed_login']['threshold'] ),
				'threshold_duration'      => absint( $settings['failed_login']['threshold_duration'] ),
				'threshold_duration_unit' => $settings['failed_login']['threshold_duration_unit'],
				'lockout_duration'        => absint( $settings['failed_login']['lockout_duration'] ),
				'lockout_duration_unit'   => $settings['failed_login']['lockout_duration_unit']
			)
		);
	}

	public function load_users_page() {
		$security = TML_Classic_Security::get_object();
		wp_add_inline_script( 'common', 'document.querySelectorAll("table .row-actions .unlock-user").forEach(function(el){el.closest("tr").querySelectorAll("td,th").forEach(function(c){c.style.backgroundColor="#ffebe8";});});' );
		if ( isset( $_GET['action'] ) && in_array( sanitize_key( $_GET['action'] ), array( 'lock', 'unlock' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$redirect_to = isset( $_REQUEST['wp_http_referer'] ) ? remove_query_arg( array( 'wp_http_referer', 'updated', 'delete_count' ), sanitize_url( wp_unslash( $_REQUEST['wp_http_referer'] ) ) ) : 'users.php'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user = isset( $_GET['user'] ) ? (int) $_GET['user'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( !$user || !current_user_can( 'edit_user', $user ) )
				wp_die( __( 'You can&#8217;t edit that user.', 'tml-classic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( !$user = get_userdata( $user ) )
				wp_die( __( 'You can&#8217;t edit that user.', 'tml-classic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( 'lock' == sanitize_key( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				check_admin_referer( 'lock-user_' . $user->ID );
				$security->lock_user( $user );
				$redirect_to = add_query_arg( 'update', 'lock', $redirect_to );
			} elseif ( 'unlock' == sanitize_key( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				check_admin_referer( 'unlock-user_' . $user->ID );
				$security->unlock_user( $user );
				$redirect_to = add_query_arg( 'update', 'unlock', $redirect_to );
			}
			wp_safe_redirect( $redirect_to );
			exit;
		}
	}

	public function maybe_admin_notices() {
		global $pagenow;
		if ( 'users.php' != $pagenow || !isset( $_GET['update'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		if ( 'lock' == $_GET['update'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div id="message" class="updated fade"><p>' . esc_html__( 'User locked.',   'tml-classic' ) . '</p></div>';
		elseif ( 'unlock' == $_GET['update'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div id="message" class="updated fade"><p>' . esc_html__( 'User unlocked.', 'tml-classic' ) . '</p></div>';
	}

	public function user_row_actions( $actions, $user_object ) {
		$current_user = wp_get_current_user();
		$security_meta = get_user_meta( $user_object->ID, 'tml_classic_security', true );
		if ( !is_array( $security_meta ) )
			$security_meta = array();
		if ( $current_user->ID != $user_object->ID ) {
			if ( isset( $security_meta['is_locked'] ) && $security_meta['is_locked'] )
				$new_actions['unlock-user'] = '<a href="' . esc_url( add_query_arg( 'wp_http_referer', urlencode( esc_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) ), wp_nonce_url( "users.php?action=unlock&amp;user=$user_object->ID", "unlock-user_$user_object->ID" ) ) ) . '">' . esc_html__( 'Unlock', 'tml-classic' ) . '</a>'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			else
				$new_actions['lock-user'] = '<a href="' . esc_url( add_query_arg( 'wp_http_referer', urlencode( esc_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) ), wp_nonce_url( "users.php?action=lock&amp;user=$user_object->ID", "lock-user_$user_object->ID" ) ) ) . '">' . esc_html__( 'Lock', 'tml-classic' ) . '</a>'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$actions = array_merge( $new_actions, $actions );
		}
		return $actions;
	}
}

TML_Classic_Security::get_object();

endif;