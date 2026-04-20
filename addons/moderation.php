<?php
/*
 * Addon Name: Moderation
 * Description: Adds the ability to approve/deny new pending users.
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_User_Moderation' ) ) :

class TML_Classic_User_Moderation extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic_moderation';

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		return array(
			'moderation_type' => 'email',
		);
	}

	protected function load() {
		if ( is_multisite() )
			return;
		add_action( 'user_register',          array( $this, 'user_register'        ) );
		add_filter( 'registration_redirect',  array( $this, 'register_redirect'    ) );
		add_filter( 'authenticate',           array( $this, 'authenticate'         ), 100, 2 );
		add_filter( 'allow_password_reset',   array( $this, 'allow_password_reset' ), 10, 2 );
		add_filter( 'tml_action_messages',    array( $this, 'action_messages'      ) );
	}

	public function user_register( $user_id ) {
		if ( 'admin' == $this->get_option( 'moderation_type' ) ) {
			$this->moderate_user( $user_id );
			$this->send_admin_approval_notification( $user_id );
		} else {
			$this->send_activation( $user_id );
		}
	}

	public function register_redirect( $redirect_to ) {
		return TML_Classic::get_page_link( 'login', 'pending=1' );
	}

	public function authenticate( $user, $username ) {
		if ( is_wp_error( $user ) )
			return $user;
		if ( isset( $user->roles ) && in_array( 'pending', (array) $user->roles ) ) {
			return new WP_Error( 'pending_approval', __( '<strong>ERROR</strong>: Your account is pending approval.', 'tml-classic' ) );
		}
		return $user;
	}

	public function allow_password_reset( $allow, $user_id ) {
		$user = new WP_User( $user_id );
		if ( in_array( 'pending', (array) $user->roles ) )
			return false;
		return $allow;
	}

	public function action_messages( $messages ) {
		$messages['login']['pending'] = __( 'Your account is pending approval. You will receive an email when your account has been activated.', 'tml-classic' );
		return $messages;
	}

	public function moderate_user( $user_id ) {
		$user = new WP_User( $user_id );
		$user->set_role( 'pending' );
	}

	public function user_activation( $user_id ) {
		$user = new WP_User( $user_id );
		if ( !in_array( 'pending', (array) $user->roles ) )
			return false;
		$user->set_role( get_option( 'default_role', 'subscriber' ) );
		$this->new_user_activated( $user_id );
		return true;
	}

	public function send_activation( $user_id ) {
		$user = new WP_User( $user_id );
		$this->moderate_user( $user_id );
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) )
			return;
		$activate_url = add_query_arg( array(
			'action' => 'activate',
			'key'    => rawurlencode( $key ),
			'login'  => rawurlencode( $user->user_login ),
		), TML_Classic::get_page_link( 'login' ) );
		do_action( 'register_post', $user->user_login, $user->user_email, new WP_Error() );
		$title   = apply_filters( 'user_activation_notification_title',   __( 'Activate Your Account', 'tml-classic' ), $user_id );
		/* translators: %s: Activation URL */
		$message = apply_filters( 'user_activation_notification_message', sprintf( __( 'To activate your account, visit the following address: %s', 'tml-classic' ), $activate_url ), $activate_url, $user_id );
		wp_mail( $user->user_email, $title, $message );
	}

	public function activate_new_user( $login, $key ) {
		$user = get_user_by( 'login', $login );
		if ( !$user )
			return new WP_Error( 'invalid_key', __( 'Invalid activation key.', 'tml-classic' ) );
		$result = check_password_reset_key( $key, $login );
		if ( is_wp_error( $result ) )
			return $result;
		$this->user_activation( $user->ID );
		return $user;
	}

	public function new_user_activated( $user_id ) {
		do_action( 'approve_user', $user_id );
	}

	public function send_admin_approval_notification( $user_id ) {
		$user       = new WP_User( $user_id );
		$manage_url = admin_url( 'users.php?role=pending' );
		do_action( 'register_post', $user->user_login, $user->user_email, new WP_Error() );
		if ( apply_filters( 'send_new_user_approval_admin_notification', true ) ) {
			$to      = apply_filters( 'user_approval_admin_notification_mail_to',  get_option( 'admin_email' ) );
			$title   = apply_filters( 'user_approval_admin_notification_title',    __( 'New User Pending Approval', 'tml-classic' ), $user_id );
			$message = apply_filters( 'user_approval_admin_notification_message',
				/* translators: %s: Site name */
				sprintf( __( 'New user registration on your site: %s', 'tml-classic' ), get_option( 'blogname' ) ) . "\r\n\r\n" .
				/* translators: %s: Username */
				sprintf( __( 'Username: %s', 'tml-classic' ), $user->user_login ) . "\r\n" .
				/* translators: %s: Email address */
				sprintf( __( 'Email: %s', 'tml-classic' ), $user->user_email ) . "\r\n\r\n" .
				/* translators: %s: Manage users URL */
				sprintf( __( 'Manage users: %s', 'tml-classic' ), $manage_url ),
				$user_id
			);
			wp_mail( $to, $title, $message );
		}
	}
}

TML_Classic_User_Moderation::get_object();

endif;

if ( is_admin() && !class_exists( 'TML_Classic_User_Moderation_Admin' ) ) :

class TML_Classic_User_Moderation_Admin extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic_moderation';

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		return TML_Classic_User_Moderation::default_options();
	}

	protected function load() {
		if ( is_multisite() )
			return;
		add_action( 'tml_activate_moderation.php',  array( $this, 'activate'         ) );
		add_action( 'tml_uninstall_moderation.php', array( $this, 'uninstall'        ) );
		add_action( 'admin_menu',                   array( $this, 'admin_menu'       ) );
		add_action( 'admin_init',                   array( $this, 'admin_init'       ) );
		add_action( 'load-users.php',               array( $this, 'load_users_page'  ) );
		add_action( 'admin_notices',                array( $this, 'admin_notices'    ) );
		add_filter( 'user_row_actions',             array( $this, 'user_row_actions' ), 10, 2 );
	}

	public function activate() {
		add_role( 'pending', __( 'Pending', 'tml-classic' ), array() );
	}

	public function uninstall() {
		delete_option( $this->options_key );
		remove_role( 'pending' );
	}

	public function admin_menu() {
		add_submenu_page(
			'tml_classic',
			__( 'TML Classic Moderation Settings', 'tml-classic' ),
			__( 'Moderation', 'tml-classic' ),
			'manage_options',
			$this->options_key,
			array( $this, 'settings_page' )
		);
		add_settings_section( 'general', null, '__return_false', $this->options_key );
		add_settings_field( 'moderation_type', __( 'Moderation Type', 'tml-classic' ), array( $this, 'settings_field_moderation_type' ), $this->options_key, 'general' );
	}

	public function admin_init() {
		register_setting( $this->options_key, $this->options_key, array( $this, 'save_settings' ) );
	}

	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TML Classic Moderation Settings', 'tml-classic' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( $this->options_key ); ?>
				<?php do_settings_sections( $this->options_key ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function settings_field_moderation_type() {
		$type = $this->get_option( 'moderation_type' );
		?>
		<label>
			<input type="radio" name="<?php echo esc_attr( $this->options_key ); ?>[moderation_type]" value="email"<?php checked( $type, 'email' ); ?>>
			<?php esc_html_e( 'Email Activation', 'tml-classic' ); ?>
		</label><br>
		<label>
			<input type="radio" name="<?php echo esc_attr( $this->options_key ); ?>[moderation_type]" value="admin"<?php checked( $type, 'admin' ); ?>>
			<?php esc_html_e( 'Admin Approval', 'tml-classic' ); ?>
		</label>
		<?php
	}

	public function save_settings( $settings ) {
		$settings['moderation_type'] = in_array( $settings['moderation_type'], array( 'email', 'admin' ), true ) ? $settings['moderation_type'] : 'email';
		return $settings;
	}

	public function load_users_page() {
		if ( isset( $_GET['action'] ) ) {
			$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
			if ( !$user_id || !check_admin_referer( 'tml_classic_moderation_' . $user_id ) )
				return;
			$moderation = TML_Classic_User_Moderation::get_object();
			if ( 'approve' === $_GET['action'] ) {
				$moderation->user_activation( $user_id );
			} elseif ( 'deny' === $_GET['action'] ) {
				do_action( 'deny_user', $user_id );
				wp_delete_user( $user_id );
			}
			wp_safe_redirect( admin_url( 'users.php?role=pending' ) );
			exit;
		}
	}

	public function admin_notices() {
		$screen = get_current_screen();
		if ( !$screen || 'users' !== $screen->id )
			return;
		$pending_count = count( get_users( array( 'role' => 'pending', 'fields' => 'ID' ) ) );
		if ( $pending_count > 0 ) {
			?>
			<div class="notice notice-warning">
				<?php /* translators: %s: Number of pending users (HTML) */ ?>
				<p><?php printf( _n( 'There is %s user pending approval.', 'There are %s users pending approval.', $pending_count, 'tml-classic' ), '<strong>' . number_format_i18n( $pending_count ) . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			</div>
			<?php
		}
	}

	public function user_row_actions( $actions, $user ) {
		if ( !in_array( 'pending', (array) $user->roles ) )
			return $actions;
		$approve_url = wp_nonce_url(
			add_query_arg( array( 'action' => 'approve', 'user_id' => $user->ID ), admin_url( 'users.php' ) ),
			'tml_classic_moderation_' . $user->ID
		);
		$deny_url = wp_nonce_url(
			add_query_arg( array( 'action' => 'deny', 'user_id' => $user->ID ), admin_url( 'users.php' ) ),
			'tml_classic_moderation_' . $user->ID
		);
		$actions['tml-approve'] = '<a href="' . esc_url( $approve_url ) . '">' . esc_html__( 'Approve', 'tml-classic' ) . '</a>';
		$actions['tml-deny']    = '<a href="' . esc_url( $deny_url ) . '" class="submitdelete">' . esc_html__( 'Deny', 'tml-classic' ) . '</a>';
		return $actions;
	}
}

TML_Classic_User_Moderation_Admin::get_object();

endif;