<?php
/*
 * Addon Name: Email
 * Description: Adds the ability to disable admin emails and customize user emails.
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Custom_Email' ) ) :
class TML_Classic_Custom_Email extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic_email';
	protected $mail_from;
	protected $mail_from_name;
	protected $mail_content_type;

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		return array(
			'new_user' => array(
				'mail_from'               => '',
				'mail_from_name'          => '',
				'mail_content_type'       => '',
				'title'                   => '',
				'message'                 => '',
				'admin_mail_to'           => '',
				'admin_mail_from'         => '',
				'admin_mail_from_name'    => '',
				'admin_mail_content_type' => '',
				'admin_title'             => '',
				'admin_message'           => '',
			),
			'retrieve_pass' => array(
				'mail_from'         => '',
				'mail_from_name'    => '',
				'mail_content_type' => '',
				'title'             => '',
				'message'           => '',
			),
			'reset_pass' => array(
				'admin_mail_to'           => '',
				'admin_mail_from'         => '',
				'admin_mail_from_name'    => '',
				'admin_mail_content_type' => '',
				'admin_title'             => '',
				'admin_message'           => '',
			),
		);
	}

	protected function load() {
		add_filter( 'wp_mail_from',               array( $this, 'mail_from_filter'         ) );
		add_filter( 'wp_mail_from_name',          array( $this, 'mail_from_name_filter'    ) );
		add_filter( 'wp_mail_content_type',       array( $this, 'mail_content_type_filter' ) );
		add_action( 'retrieve_password',          array( $this, 'apply_retrieve_pass_filters'  ) );
		add_action( 'password_reset',             array( $this, 'apply_password_reset_filters' ) );
		add_action( 'tml_new_user_notification',  array( $this, 'apply_new_user_filters'       ) );
		remove_action( 'register_new_user',       'wp_send_new_user_notifications'        );
		remove_action( 'edit_user_created_user',  'wp_send_new_user_notifications', 10, 2 );
		remove_action( 'after_password_reset',    'wp_password_change_notification'       );
		add_action( 'register_new_user',          array( $this, 'new_user_notification'        )        );
		add_action( 'edit_user_created_user',     array( $this, 'new_user_notification'        ), 10, 2 );
		add_action( 'after_password_reset',       array( $this, 'password_change_notification' )        );
		add_action( 'register_post',              array( $this, 'apply_user_moderation_notification_filters' ) );
		add_action( 'tml_user_activation_resend', array( $this, 'apply_user_moderation_notification_filters' ) );
		add_action( 'approve_user',               array( $this, 'apply_user_approval_notification_filters'   ) );
		add_action( 'deny_user',                  array( $this, 'apply_user_denial_notification_filters'     ) );
		add_action( 'phpmailer_init',             array( $this, 'phpmailer_init' ) );
		if ( is_admin() ) {
			add_action( 'tml_uninstall_email.php', array( $this, 'uninstall' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}
	}

	public function set_mail_headers( $mail_from = '', $mail_from_name = '', $mail_content_type = 'text' ) {
		$this->mail_from         = $mail_from;
		$this->mail_from_name    = $mail_from_name;
		$this->mail_content_type = $mail_content_type;
	}

	public function apply_retrieve_pass_filters() {
		$this->set_mail_headers(
			$this->get_option( array( 'retrieve_pass', 'mail_from'         ) ),
			$this->get_option( array( 'retrieve_pass', 'mail_from_name'    ) ),
			$this->get_option( array( 'retrieve_pass', 'mail_content_type' ) )
		);
		add_filter( 'retrieve_password_title',   array( $this, 'retrieve_pass_title_filter'   ), 10, 3 );
		add_filter( 'retrieve_password_message', array( $this, 'retrieve_pass_message_filter' ), 10, 4 );
	}

	public function apply_password_reset_filters() {
		$this->set_mail_headers(
			$this->get_option( array( 'reset_pass', 'admin_mail_from'         ) ),
			$this->get_option( array( 'reset_pass', 'admin_mail_from_name'    ) ),
			$this->get_option( array( 'reset_pass', 'admin_mail_content_type' ) )
		);
		add_filter( 'password_change_notification_mail_to', array( $this, 'password_change_notification_mail_to_filter' )        );
		add_filter( 'password_change_notification_title',   array( $this, 'password_change_notification_title_filter'   ), 10, 2 );
		add_filter( 'password_change_notification_message', array( $this, 'password_change_notification_message_filter' ), 10, 2 );
		add_filter( 'send_password_change_notification',    array( $this, 'send_password_change_notification_filter'    )        );
	}

	public function apply_new_user_filters() {
		add_filter( 'new_user_notification_title',         array( $this, 'new_user_notification_title_filter'         ), 10, 2 );
		add_filter( 'new_user_notification_message',       array( $this, 'new_user_notification_message_filter'       ), 10, 3 );
		add_filter( 'send_new_user_notification',          array( $this, 'send_new_user_notification_filter'          )        );
		add_filter( 'new_user_admin_notification_mail_to', array( $this, 'new_user_admin_notification_mail_to_filter' )        );
		add_filter( 'new_user_admin_notification_title',   array( $this, 'new_user_admin_notification_title_filter'   ), 10, 2 );
		add_filter( 'new_user_admin_notification_message', array( $this, 'new_user_admin_notification_message_filter' ), 10, 2 );
		add_filter( 'send_new_user_admin_notification',    array( $this, 'send_new_user_admin_notification_filter'    )        );
	}

	public function mail_from_filter( $from_email ) {
		return empty( $this->mail_from ) ? $from_email : $this->mail_from;
	}

	public function mail_from_name_filter( $from_name ) {
		return empty( $this->mail_from_name ) ? $from_name : $this->mail_from_name;
	}

	public function mail_content_type_filter( $content_type ) {
		return empty( $this->mail_content_type ) ? $content_type : 'text/' . $this->mail_content_type;
	}

	public function retrieve_pass_title_filter( $title, $user_login, $user_data ) {
		$_title = $this->get_option( array( 'retrieve_pass', 'title' ) );
		return empty( $_title ) ? $title : TML_Classic_Common::replace_vars( $_title, $user_data->ID );
	}

	public function retrieve_pass_message_filter( $message, $key, $user_login, $user_data ) {
		$_message = $this->get_option( array( 'retrieve_pass', 'message' ) );
		if ( !empty( $_message ) ) {
			$message = TML_Classic_Common::replace_vars( $_message, $user_data->ID, array(
				'%loginurl%' => site_url( 'wp-login.php', 'login' ),
				'%reseturl%' => site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ),
			) );
		}
		return $message;
	}

	public function password_change_notification_mail_to_filter( $to ) {
		$_to = $this->get_option( array( 'reset_pass', 'admin_mail_to' ) );
		return empty( $_to ) ? $to : $_to;
	}

	public function password_change_notification_title_filter( $title, $user_id ) {
		$_title = $this->get_option( array( 'reset_pass', 'admin_title' ) );
		return empty( $_title ) ? $title : TML_Classic_Common::replace_vars( $_title, $user_id );
	}

	public function password_change_notification_message_filter( $message, $user_id ) {
		$_message = $this->get_option( array( 'reset_pass', 'admin_message' ) );
		return empty( $_message ) ? $message : TML_Classic_Common::replace_vars( $_message, $user_id );
	}

	public function send_password_change_notification_filter( $enable ) {
		$this->set_mail_headers(
			$this->get_option( array( 'reset_pass', 'admin_mail_from'         ) ),
			$this->get_option( array( 'reset_pass', 'admin_mail_from_name'    ) ),
			$this->get_option( array( 'reset_pass', 'admin_mail_content_type' ) )
		);
		if ( $this->get_option( array( 'reset_pass', 'admin_disable' ) ) )
			return false;
		return $enable;
	}

	public function new_user_notification_title_filter( $title, $user_id ) {
		$_title = $this->get_option( array( 'new_user', 'title' ) );
		return empty( $_title ) ? $title : TML_Classic_Common::replace_vars( $_title, $user_id );
	}

	public function new_user_notification_message_filter( $message, $key, $user_id ) {
		$_message = $this->get_option( array( 'new_user', 'message' ) );
		if ( !empty( $_message ) ) {
			$user = get_userdata( $user_id );
			$message = TML_Classic_Common::replace_vars( $_message, $user_id, array(
				'%reseturl%' => network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' ),
				'%loginurl%' => site_url( 'wp-login.php', 'login' ),
			) );
		}
		return $message;
	}

	public function send_new_user_notification_filter( $enable ) {
		$this->set_mail_headers(
			$this->get_option( array( 'new_user', 'mail_from'         ) ),
			$this->get_option( array( 'new_user', 'mail_from_name'    ) ),
			$this->get_option( array( 'new_user', 'mail_content_type' ) )
		);
		return $enable;
	}

	public function new_user_admin_notification_mail_to_filter( $to ) {
		$_to = $this->get_option( array( 'new_user', 'admin_mail_to' ) );
		return empty( $_to ) ? $to : $_to;
	}

	public function new_user_admin_notification_title_filter( $title, $user_id ) {
		$_title = $this->get_option( array( 'new_user', 'admin_title' ) );
		return empty( $_title ) ? $title : TML_Classic_Common::replace_vars( $_title, $user_id );
	}

	public function new_user_admin_notification_message_filter( $message, $user_id ) {
		$_message = $this->get_option( array( 'new_user', 'admin_message' ) );
		return empty( $_message ) ? $message : TML_Classic_Common::replace_vars( $_message, $user_id );
	}

	public function send_new_user_admin_notification_filter( $enable ) {
		$this->set_mail_headers(
			$this->get_option( array( 'new_user', 'admin_mail_from'         ) ),
			$this->get_option( array( 'new_user', 'admin_mail_from_name'    ) ),
			$this->get_option( array( 'new_user', 'admin_mail_content_type' ) )
		);
		if ( $this->get_option( array( 'new_user', 'admin_disable' ) ) )
			return false;
		return $enable;
	}

	public function apply_user_moderation_notification_filters() {
		if ( !class_exists( 'TML_Classic_User_Moderation' ) )
			return;
		$moderation_type = TML_Classic_User_Moderation::get_object()->get_option( 'moderation_type' );
		switch ( $moderation_type ) {
			case 'email':
				$this->set_mail_headers(
					$this->get_option( array( 'user_activation', 'mail_from'         ) ),
					$this->get_option( array( 'user_activation', 'mail_from_name'    ) ),
					$this->get_option( array( 'user_activation', 'mail_content_type' ) )
				);
				add_filter( 'user_activation_notification_title',   array( $this, 'user_activation_notification_title_filter'   ), 10, 2 );
				add_filter( 'user_activation_notification_message', array( $this, 'user_activation_notification_message_filter' ), 10, 3 );
				break;
			case 'admin':
				$this->set_mail_headers(
					$this->get_option( array( 'user_approval', 'admin_mail_from'         ) ),
					$this->get_option( array( 'user_approval', 'admin_mail_from_name'    ) ),
					$this->get_option( array( 'user_approval', 'admin_mail_content_type' ) )
				);
				add_filter( 'user_approval_admin_notification_mail_to',  array( $this, 'user_approval_admin_notification_mail_to_filter'  )        );
				add_filter( 'user_approval_admin_notification_title',    array( $this, 'user_approval_admin_notification_title_filter'    ), 10, 2 );
				add_filter( 'user_approval_admin_notification_message',  array( $this, 'user_approval_admin_notification_message_filter'  ), 10, 2 );
				add_filter( 'send_new_user_approval_admin_notification', array( $this, 'send_new_user_approval_admin_notification_filter' )        );
				break;
		}
	}

	public function apply_user_approval_notification_filters() {
		$this->set_mail_headers(
			$this->get_option( array( 'user_approval', 'mail_from'         ) ),
			$this->get_option( array( 'user_approval', 'mail_from_name'    ) ),
			$this->get_option( array( 'user_approval', 'mail_content_type' ) )
		);
		add_filter( 'user_approval_notification_title',   array( $this, 'user_approval_notification_title_filter'   ), 10, 2 );
		add_filter( 'user_approval_notification_message', array( $this, 'user_approval_notification_message_filter' ), 10, 3 );
	}

	public function apply_user_denial_notification_filters() {
		$this->set_mail_headers(
			$this->get_option( array( 'user_denial', 'mail_from'         ) ),
			$this->get_option( array( 'user_denial', 'mail_from_name'    ) ),
			$this->get_option( array( 'user_denial', 'mail_content_type' ) )
		);
		add_filter( 'user_denial_notification_title',    array( $this, 'user_denial_notification_title_filter'    ), 10, 2 );
		add_filter( 'user_denial_notification_message',  array( $this, 'user_denial_notification_message_filter'  ), 10, 2 );
		add_filter( 'send_new_user_denial_notification', array( $this, 'send_new_user_denial_notification_filter' )        );
	}

	public function user_activation_notification_title_filter( $title, $user_id ) {
		$_title = $this->get_option( array( 'user_activation', 'title' ) );
		return empty( $_title ) ? $title : TML_Classic_Common::replace_vars( $_title, $user_id );
	}

	public function user_activation_notification_message_filter( $message, $activation_url, $user_id ) {
		$_message = $this->get_option( array( 'user_activation', 'message' ) );
		if ( !empty( $_message ) ) {
			$message = TML_Classic_Common::replace_vars( $_message, $user_id, array(
				'%activateurl%' => $activation_url,
			) );
		}
		return $message;
	}

	public function user_approval_notification_title_filter( $title, $user_id ) {
		$_title = $this->get_option( array( 'user_approval', 'title' ) );
		return empty( $_title ) ? $title : TML_Classic_Common::replace_vars( $_title, $user_id );
	}

	public function user_approval_notification_message_filter( $message, $key, $user_id ) {
		$_message = $this->get_option( array( 'user_approval', 'message' ) );
		if ( !empty( $_message ) ) {
			$user = get_user_by( 'id', $user_id );
			$message = TML_Classic_Common::replace_vars( $_message, $user_id, array(
				'%loginurl%' => TML_Classic::get_object()->get_page_link( 'login' ),
				'%reseturl%' => site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' ),
			) );
		}
		return $message;
	}

	public function user_approval_admin_notification_mail_to_filter( $to ) {
		$_to = $this->get_option( array( 'user_approval', 'admin_mail_to' ) );
		return empty( $_to ) ? $to : $_to;
	}

	public function user_approval_admin_notification_title_filter( $title, $user_id ) {
		$_title = $this->get_option( array( 'user_approval', 'admin_title' ) );
		return empty( $_title ) ? $title : TML_Classic_Common::replace_vars( $_title, $user_id );
	}

	public function user_approval_admin_notification_message_filter( $message, $user_id ) {
		$_message = $this->get_option( array( 'user_approval', 'admin_message' ) );
		if ( !empty( $_message ) ) {
			$message = TML_Classic_Common::replace_vars( $_message, $user_id, array(
				'%pendingurl%' => admin_url( 'users.php?role=pending' ),
			) );
		}
		return $message;
	}

	public function send_new_user_approval_admin_notification_filter( $enable ) {
		if ( $this->get_option( array( 'user_approval', 'admin_disable' ) ) )
			return false;
		return $enable;
	}

	public function user_denial_notification_title_filter( $title, $user_id ) {
		$_title = $this->get_option( array( 'user_denial', 'title' ) );
		return empty( $_title ) ? $title : TML_Classic_Common::replace_vars( $_title, $user_id );
	}

	public function user_denial_notification_message_filter( $message, $user_id ) {
		$_message = $this->get_option( array( 'user_denial', 'message' ) );
		return empty( $_message ) ? $message : TML_Classic_Common::replace_vars( $_message, $user_id );
	}

	public function send_new_user_denial_notification_filter( $enable ) {
		if ( $this->get_option( array( 'user_denial', 'disable' ) ) )
			return false;
		return $enable;
	}

	public function new_user_notification( $user_id, $notify = 'both' ) {
		global $wpdb;
		$user = get_userdata( $user_id );
		do_action( 'tml_new_user_notification', $user_id, $notify );
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		if ( apply_filters( 'send_new_user_admin_notification', true ) ) {
			/* translators: %s: Site name */
			$message  = sprintf( __( 'New user registration on your site %s:', 'tml-classic' ), $blogname           ) . "\r\n\r\n";
			/* translators: %s: Username */
			$message .= sprintf( __( 'Username: %s',                           'tml-classic' ), $user->user_login  ) . "\r\n\r\n";
			/* translators: %s: Email address */
			$message .= sprintf( __( 'Email: %s',                              'tml-classic' ), $user->user_email  ) . "\r\n";
			/* translators: %s: Site name */
			$title    = sprintf( __( '[%s] New User Registration',             'tml-classic' ), $blogname           );
			$title    = apply_filters( 'new_user_admin_notification_title',   $title,   $user_id );
			$message  = apply_filters( 'new_user_admin_notification_message', $message, $user_id );
			$to       = apply_filters( 'new_user_admin_notification_mail_to', get_option( 'admin_email' ) );
			@wp_mail( $to, $title, $message );
		}
		if ( 'admin' == $notify || empty( $notify ) )
			return;
		$key = wp_generate_password( 20, false );
		do_action( 'retrieve_password_key', $user->user_login, $key );
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$wp_hasher = new PasswordHash( 8, true );
		$hashed = time() . ':' . $wp_hasher->HashPassword( $key );
		$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user->user_login ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( apply_filters( 'send_new_user_notification', true ) ) {
			/* translators: %s: Username */
			$message  = sprintf( __( 'Username: %s', 'tml-classic' ), $user->user_login ) . "\r\n\r\n";
			$message .= __( 'To set your password, visit the following address:', 'tml-classic' ) . "\r\n\r\n";
			$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' ) . ">\r\n\r\n";
			$message .= wp_login_url() . "\r\n";
			/* translators: %s: Site name */
			$title = sprintf( __( '[%s] Your username and password info', 'tml-classic' ), $blogname );
			$title   = apply_filters( 'new_user_notification_title',   $title,   $user_id       );
			$message = apply_filters( 'new_user_notification_message', $message, $key, $user_id );
			wp_mail( $user->user_email, $title, $message );
		}
	}

	public function password_change_notification( $user ) {
		$to = apply_filters( 'password_change_notification_mail_to', get_option( 'admin_email' ) );
		if ( $user->user_email != $to && apply_filters( 'send_password_change_notification', true ) ) {
			$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
			/* translators: %s: Site name */
			$title   = sprintf( __( '[%s] Password Lost/Changed',             'tml-classic' ), $blogname         );
			/* translators: %s: Username */
			$message = sprintf( __( 'Password Lost and Changed for user: %s', 'tml-classic' ), $user->user_login ) . "\r\n";
			$title   = apply_filters( 'password_change_notification_title',   $title,   $user->ID );
			$message = apply_filters( 'password_change_notification_message', $message, $user->ID );
			wp_mail( $to, $title, $message );
		}
	}

	public function phpmailer_init( $phpmailer ) {
		if ( 'text/html' == $phpmailer->ContentType && empty( $phpmailer->AltBody ) )
			$phpmailer->AltBody = wp_strip_all_tags( $phpmailer->Body );
	}

	public function uninstall() {
		delete_option( $this->options_key );
	}

	public function admin_menu() {
		$hook = add_submenu_page(
			'tml_classic',
			__( 'TML Classic Email Settings', 'tml-classic' ),
			__( 'Email', 'tml-classic' ),
			'manage_options',
			$this->options_key,
			array( $this, 'settings_page' )
		);
		add_action( 'load-' . $hook, array( $this, 'load_settings_page' ) );
	}

	public function admin_init() {
		register_setting( $this->options_key, $this->options_key, array( $this, 'save_settings' ) );
	}

	public function load_settings_page() {
		$screen = get_current_screen();
		wp_enqueue_script( 'postbox' );
		wp_add_inline_script( 'postbox', 'postboxes.add_postbox_toggles(pagenow);' );
		add_meta_box( 'new_user',       __( 'New User',          'tml-classic' ), array( $this, 'new_user_meta_box' ),       $screen->id, 'normal' );
		add_meta_box( 'new_user_admin', __( 'New User Admin',    'tml-classic' ), array( $this, 'new_user_admin_meta_box' ), $screen->id, 'normal' );
		add_meta_box( 'retrieve_pass',  __( 'Retrieve Password', 'tml-classic' ), array( $this, 'retrieve_pass_meta_box' ),  $screen->id, 'normal' );
		add_meta_box( 'reset_pass',     __( 'Reset Password',    'tml-classic' ), array( $this, 'reset_pass_meta_box' ),     $screen->id, 'normal' );
		if ( class_exists( 'TML_Classic_User_Moderation' ) ) {
			add_meta_box( 'user_activation',     __( 'User Activation',     'tml-classic' ), array( $this, 'user_activation_meta_box' ),     $screen->id, 'normal' );
			add_meta_box( 'user_approval',       __( 'User Approval',       'tml-classic' ), array( $this, 'user_approval_meta_box' ),       $screen->id, 'normal' );
			add_meta_box( 'user_approval_admin', __( 'User Approval Admin', 'tml-classic' ), array( $this, 'user_approval_admin_meta_box' ), $screen->id, 'normal' );
			add_meta_box( 'user_denial',         __( 'User Denial',         'tml-classic' ), array( $this, 'user_denial_meta_box' ),         $screen->id, 'normal' );
		}
	}

	public function settings_page() {
		$screen = get_current_screen();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TML Classic Email Settings', 'tml-classic' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( $this->options_key );
				wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
				wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
				?>
				<div id="<?php echo esc_attr( $this->options_key ); ?>" class="metabox-holder">
					<?php do_meta_boxes( $screen->id, 'normal', null ); ?>
				</div>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function email_format_select( $group, $key ) {
		$val = $this->get_option( array( $group, $key ) );
		$id  = esc_attr( $this->options_key . '_' . $group . '_' . $key );
		$nm  = esc_attr( $this->options_key . '[' . $group . '][' . $key . ']' );
		?>
		<select name="<?php echo esc_attr( $nm ); ?>" id="<?php echo esc_attr( $id ); ?>"><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<option value="plain"<?php selected( $val, 'plain' ); ?>><?php esc_html_e( 'Plain Text', 'tml-classic' ); ?></option>
			<option value="html"<?php  selected( $val, 'html' ); ?>><?php  esc_html_e( 'HTML', 'tml-classic' ); ?></option>
		</select>
		<?php
	}

	public function new_user_meta_box() {
		$k = $this->options_key;
		?>
		<p class="description">
			<?php esc_html_e( 'This email will be sent to a new user upon registration. Be sure to include %reseturl% so the user can set their password. Leave any field empty to use the default.', 'tml-classic' ); ?>
		</p>
		<table class="form-table">
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_mail_from_name"><?php esc_html_e( 'From Name', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[new_user][mail_from_name]" type="text" id="<?php echo esc_attr( $k ); ?>_new_user_mail_from_name" value="<?php echo esc_attr( $this->get_option( array( 'new_user', 'mail_from_name' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_mail_from"><?php esc_html_e( 'From Email', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[new_user][mail_from]" type="text" id="<?php echo esc_attr( $k ); ?>_new_user_mail_from" value="<?php echo esc_attr( $this->get_option( array( 'new_user', 'mail_from' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_mail_content_type"><?php esc_html_e( 'Email Format', 'tml-classic' ); ?></label></th><td><?php $this->email_format_select( 'new_user', 'mail_content_type' ); ?></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_title"><?php esc_html_e( 'Subject', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[new_user][title]" type="text" id="<?php echo esc_attr( $k ); ?>_new_user_title" value="<?php echo esc_attr( $this->get_option( array( 'new_user', 'title' ) ) ); ?>" class="large-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_message"><?php esc_html_e( 'Message', 'tml-classic' ); ?></label></th><td><p class="description"><?php esc_html_e( 'Available Variables', 'tml-classic' ); ?>: %blogname%, %siteurl%, %reseturl%, %user_login%, %user_email%, %user_ip%</p><textarea name="<?php echo esc_attr( $k ); ?>[new_user][message]" id="<?php echo esc_attr( $k ); ?>_new_user_message" class="large-text" rows="10"><?php echo esc_textarea( $this->get_option( array( 'new_user', 'message' ) ) ); ?></textarea></td></tr>
		</table>
		<?php
	}

	public function new_user_admin_meta_box() {
		$k = $this->options_key;
		?>
		<p class="description"><?php esc_html_e( 'This email will be sent to the specified address(es) upon new user registration. Leave any field empty to use the default.', 'tml-classic' ); ?></p>
		<table class="form-table">
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_admin_mail_to"><?php esc_html_e( 'To', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[new_user][admin_mail_to]" type="text" id="<?php echo esc_attr( $k ); ?>_new_user_admin_mail_to" value="<?php echo esc_attr( $this->get_option( array( 'new_user', 'admin_mail_to' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_admin_mail_from_name"><?php esc_html_e( 'From Name', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[new_user][admin_mail_from_name]" type="text" id="<?php echo esc_attr( $k ); ?>_new_user_admin_mail_from_name" value="<?php echo esc_attr( $this->get_option( array( 'new_user', 'admin_mail_from_name' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_admin_mail_from"><?php esc_html_e( 'From Email', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[new_user][admin_mail_from]" type="text" id="<?php echo esc_attr( $k ); ?>_new_user_admin_mail_from" value="<?php echo esc_attr( $this->get_option( array( 'new_user', 'admin_mail_from' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_admin_mail_content_type"><?php esc_html_e( 'Email Format', 'tml-classic' ); ?></label></th><td><?php $this->email_format_select( 'new_user', 'admin_mail_content_type' ); ?></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_admin_title"><?php esc_html_e( 'Subject', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[new_user][admin_title]" type="text" id="<?php echo esc_attr( $k ); ?>_new_user_admin_title" value="<?php echo esc_attr( $this->get_option( array( 'new_user', 'admin_title' ) ) ); ?>" class="large-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_new_user_admin_message"><?php esc_html_e( 'Message', 'tml-classic' ); ?></label></th><td><p class="description"><?php esc_html_e( 'Available Variables', 'tml-classic' ); ?>: %blogname%, %siteurl%, %user_login%, %user_email%, %user_ip%</p><textarea name="<?php echo esc_attr( $k ); ?>[new_user][admin_message]" id="<?php echo esc_attr( $k ); ?>_new_user_admin_message" class="large-text" rows="10"><?php echo esc_textarea( $this->get_option( array( 'new_user', 'admin_message' ) ) ); ?></textarea></td></tr>
			<tr><th>&nbsp;</th><td><label><input name="<?php echo esc_attr( $k ); ?>[new_user][admin_disable]" type="checkbox" id="<?php echo esc_attr( $k ); ?>_new_user_admin_disable" value="1"<?php checked( 1, $this->get_option( array( 'new_user', 'admin_disable' ) ) ); ?>> <?php esc_html_e( 'Disable Admin Notification', 'tml-classic' ); ?></label></td></tr>
		</table>
		<?php
	}

	public function retrieve_pass_meta_box() {
		$k = $this->options_key;
		?>
		<p class="description"><?php esc_html_e( 'This email will be sent to a user who requests a password reset. Include %reseturl% so they can reset their password. Leave any field empty to use the default.', 'tml-classic' ); ?></p>
		<table class="form-table">
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_retrieve_pass_mail_from_name"><?php esc_html_e( 'From Name', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[retrieve_pass][mail_from_name]" type="text" id="<?php echo esc_attr( $k ); ?>_retrieve_pass_mail_from_name" value="<?php echo esc_attr( $this->get_option( array( 'retrieve_pass', 'mail_from_name' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_retrieve_pass_mail_from"><?php esc_html_e( 'From Email', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[retrieve_pass][mail_from]" type="text" id="<?php echo esc_attr( $k ); ?>_retrieve_pass_mail_from" value="<?php echo esc_attr( $this->get_option( array( 'retrieve_pass', 'mail_from' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_retrieve_pass_mail_content_type"><?php esc_html_e( 'Email Format', 'tml-classic' ); ?></label></th><td><?php $this->email_format_select( 'retrieve_pass', 'mail_content_type' ); ?></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_retrieve_pass_title"><?php esc_html_e( 'Subject', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[retrieve_pass][title]" type="text" id="<?php echo esc_attr( $k ); ?>_retrieve_pass_title" value="<?php echo esc_attr( $this->get_option( array( 'retrieve_pass', 'title' ) ) ); ?>" class="large-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_retrieve_pass_message"><?php esc_html_e( 'Message', 'tml-classic' ); ?></label></th><td><p class="description"><?php esc_html_e( 'Available Variables', 'tml-classic' ); ?>: %blogname%, %siteurl%, %reseturl%, %loginurl%, %user_login%, %user_email%, %user_ip%</p><textarea name="<?php echo esc_attr( $k ); ?>[retrieve_pass][message]" id="<?php echo esc_attr( $k ); ?>_retrieve_pass_message" class="large-text" rows="10"><?php echo esc_textarea( $this->get_option( array( 'retrieve_pass', 'message' ) ) ); ?></textarea></td></tr>
		</table>
		<?php
	}

	public function reset_pass_meta_box() {
		$k = $this->options_key;
		?>
		<p class="description"><?php esc_html_e( 'This email will be sent to the admin when a user resets their password. Leave any field empty to use the default.', 'tml-classic' ); ?></p>
		<table class="form-table">
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_reset_pass_admin_mail_to"><?php esc_html_e( 'To', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[reset_pass][admin_mail_to]" type="text" id="<?php echo esc_attr( $k ); ?>_reset_pass_admin_mail_to" value="<?php echo esc_attr( $this->get_option( array( 'reset_pass', 'admin_mail_to' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_reset_pass_admin_mail_from_name"><?php esc_html_e( 'From Name', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[reset_pass][admin_mail_from_name]" type="text" id="<?php echo esc_attr( $k ); ?>_reset_pass_admin_mail_from_name" value="<?php echo esc_attr( $this->get_option( array( 'reset_pass', 'admin_mail_from_name' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_reset_pass_admin_mail_from"><?php esc_html_e( 'From Email', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[reset_pass][admin_mail_from]" type="text" id="<?php echo esc_attr( $k ); ?>_reset_pass_admin_mail_from" value="<?php echo esc_attr( $this->get_option( array( 'reset_pass', 'admin_mail_from' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_reset_pass_admin_mail_content_type"><?php esc_html_e( 'Email Format', 'tml-classic' ); ?></label></th><td><?php $this->email_format_select( 'reset_pass', 'admin_mail_content_type' ); ?></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_reset_pass_admin_title"><?php esc_html_e( 'Subject', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[reset_pass][admin_title]" type="text" id="<?php echo esc_attr( $k ); ?>_reset_pass_admin_title" value="<?php echo esc_attr( $this->get_option( array( 'reset_pass', 'admin_title' ) ) ); ?>" class="large-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_reset_pass_admin_message"><?php esc_html_e( 'Message', 'tml-classic' ); ?></label></th><td><p class="description"><?php esc_html_e( 'Available Variables', 'tml-classic' ); ?>: %blogname%, %siteurl%, %user_login%, %user_email%, %user_ip%</p><textarea name="<?php echo esc_attr( $k ); ?>[reset_pass][admin_message]" id="<?php echo esc_attr( $k ); ?>_reset_pass_admin_message" class="large-text" rows="10"><?php echo esc_textarea( $this->get_option( array( 'reset_pass', 'admin_message' ) ) ); ?></textarea></td></tr>
			<tr><th>&nbsp;</th><td><label><input name="<?php echo esc_attr( $k ); ?>[reset_pass][admin_disable]" type="checkbox" id="<?php echo esc_attr( $k ); ?>_reset_pass_admin_disable" value="1"<?php checked( 1, $this->get_option( array( 'reset_pass', 'admin_disable' ) ) ); ?>> <?php esc_html_e( 'Disable Admin Notification', 'tml-classic' ); ?></label></td></tr>
		</table>
		<?php
	}

	public function user_activation_meta_box() {
		$k = $this->options_key;
		?>
		<p class="description"><?php esc_html_e( 'Sent to a new user when Email Confirmation moderation is active. Include %activateurl%. Leave any field empty to use the default.', 'tml-classic' ); ?></p>
		<table class="form-table">
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_activation_mail_from_name"><?php esc_html_e( 'From Name', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_activation][mail_from_name]" type="text" id="<?php echo esc_attr( $k ); ?>_user_activation_mail_from_name" value="<?php echo esc_attr( $this->get_option( array( 'user_activation', 'mail_from_name' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_activation_mail_from"><?php esc_html_e( 'From Email', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_activation][mail_from]" type="text" id="<?php echo esc_attr( $k ); ?>_user_activation_mail_from" value="<?php echo esc_attr( $this->get_option( array( 'user_activation', 'mail_from' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_activation_mail_content_type"><?php esc_html_e( 'Email Format', 'tml-classic' ); ?></label></th><td><?php $this->email_format_select( 'user_activation', 'mail_content_type' ); ?></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_activation_title"><?php esc_html_e( 'Subject', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_activation][title]" type="text" id="<?php echo esc_attr( $k ); ?>_user_activation_title" value="<?php echo esc_attr( $this->get_option( array( 'user_activation', 'title' ) ) ); ?>" class="large-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_activation_message"><?php esc_html_e( 'Message', 'tml-classic' ); ?></label></th><td><p class="description"><?php esc_html_e( 'Available Variables', 'tml-classic' ); ?>: %blogname%, %siteurl%, %activateurl%, %user_login%, %user_email%, %user_ip%</p><textarea name="<?php echo esc_attr( $k ); ?>[user_activation][message]" id="<?php echo esc_attr( $k ); ?>_user_activation_message" class="large-text" rows="10"><?php echo esc_textarea( $this->get_option( array( 'user_activation', 'message' ) ) ); ?></textarea></td></tr>
		</table>
		<?php
	}

	public function user_approval_meta_box() {
		$k = $this->options_key;
		?>
		<p class="description"><?php esc_html_e( 'Sent to a user upon admin approval when Admin Approval moderation is active. Include %reseturl%. Leave any field empty to use the default.', 'tml-classic' ); ?></p>
		<table class="form-table">
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_mail_from_name"><?php esc_html_e( 'From Name', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_approval][mail_from_name]" type="text" id="<?php echo esc_attr( $k ); ?>_user_approval_mail_from_name" value="<?php echo esc_attr( $this->get_option( array( 'user_approval', 'mail_from_name' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_mail_from"><?php esc_html_e( 'From Email', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_approval][mail_from]" type="text" id="<?php echo esc_attr( $k ); ?>_user_approval_mail_from" value="<?php echo esc_attr( $this->get_option( array( 'user_approval', 'mail_from' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_mail_content_type"><?php esc_html_e( 'Email Format', 'tml-classic' ); ?></label></th><td><?php $this->email_format_select( 'user_approval', 'mail_content_type' ); ?></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_title"><?php esc_html_e( 'Subject', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_approval][title]" type="text" id="<?php echo esc_attr( $k ); ?>_user_approval_title" value="<?php echo esc_attr( $this->get_option( array( 'user_approval', 'title' ) ) ); ?>" class="large-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_message"><?php esc_html_e( 'Message', 'tml-classic' ); ?></label></th><td><p class="description"><?php esc_html_e( 'Available Variables', 'tml-classic' ); ?>: %blogname%, %siteurl%, %reseturl%, %loginurl%, %user_login%, %user_email%</p><textarea name="<?php echo esc_attr( $k ); ?>[user_approval][message]" id="<?php echo esc_attr( $k ); ?>_user_approval_message" class="large-text" rows="10"><?php echo esc_textarea( $this->get_option( array( 'user_approval', 'message' ) ) ); ?></textarea></td></tr>
		</table>
		<?php
	}

	public function user_approval_admin_meta_box() {
		$k = $this->options_key;
		?>
		<p class="description"><?php esc_html_e( 'Sent to admin upon registration when Admin Approval moderation is active. Leave any field empty to use the default.', 'tml-classic' ); ?></p>
		<table class="form-table">
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_admin_mail_to"><?php esc_html_e( 'To', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_approval][admin_mail_to]" type="text" id="<?php echo esc_attr( $k ); ?>_user_approval_admin_mail_to" value="<?php echo esc_attr( $this->get_option( array( 'user_approval', 'admin_mail_to' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_admin_mail_from_name"><?php esc_html_e( 'From Name', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_approval][admin_mail_from_name]" type="text" id="<?php echo esc_attr( $k ); ?>_user_approval_admin_mail_from_name" value="<?php echo esc_attr( $this->get_option( array( 'user_approval', 'admin_mail_from_name' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_admin_mail_from"><?php esc_html_e( 'From Email', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_approval][admin_mail_from]" type="text" id="<?php echo esc_attr( $k ); ?>_user_approval_admin_mail_from" value="<?php echo esc_attr( $this->get_option( array( 'user_approval', 'admin_mail_from' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_admin_mail_content_type"><?php esc_html_e( 'Email Format', 'tml-classic' ); ?></label></th><td><?php $this->email_format_select( 'user_approval', 'admin_mail_content_type' ); ?></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_admin_title"><?php esc_html_e( 'Subject', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_approval][admin_title]" type="text" id="<?php echo esc_attr( $k ); ?>_user_approval_admin_title" value="<?php echo esc_attr( $this->get_option( array( 'user_approval', 'admin_title' ) ) ); ?>" class="large-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_approval_admin_message"><?php esc_html_e( 'Message', 'tml-classic' ); ?></label></th><td><p class="description"><?php esc_html_e( 'Available Variables', 'tml-classic' ); ?>: %blogname%, %siteurl%, %pendingurl%, %user_login%, %user_email%, %user_ip%</p><textarea name="<?php echo esc_attr( $k ); ?>[user_approval][admin_message]" id="<?php echo esc_attr( $k ); ?>_user_approval_admin_message" class="large-text" rows="10"><?php echo esc_textarea( $this->get_option( array( 'user_approval', 'admin_message' ) ) ); ?></textarea></td></tr>
			<tr><th>&nbsp;</th><td><label><input name="<?php echo esc_attr( $k ); ?>[user_approval][admin_disable]" type="checkbox" id="<?php echo esc_attr( $k ); ?>_user_approval_admin_disable" value="1"<?php checked( 1, $this->get_option( array( 'user_approval', 'admin_disable' ) ) ); ?>> <?php esc_html_e( 'Disable Admin Notification', 'tml-classic' ); ?></label></td></tr>
		</table>
		<?php
	}

	public function user_denial_meta_box() {
		$k = $this->options_key;
		?>
		<p class="description"><?php esc_html_e( 'Sent to a user who is deleted or denied when Admin Approval moderation is active. Leave any field empty to use the default.', 'tml-classic' ); ?></p>
		<table class="form-table">
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_denial_mail_from_name"><?php esc_html_e( 'From Name', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_denial][mail_from_name]" type="text" id="<?php echo esc_attr( $k ); ?>_user_denial_mail_from_name" value="<?php echo esc_attr( $this->get_option( array( 'user_denial', 'mail_from_name' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_denial_mail_from"><?php esc_html_e( 'From Email', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_denial][mail_from]" type="text" id="<?php echo esc_attr( $k ); ?>_user_denial_mail_from" value="<?php echo esc_attr( $this->get_option( array( 'user_denial', 'mail_from' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_denial_mail_content_type"><?php esc_html_e( 'Email Format', 'tml-classic' ); ?></label></th><td><?php $this->email_format_select( 'user_denial', 'mail_content_type' ); ?></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_denial_title"><?php esc_html_e( 'Subject', 'tml-classic' ); ?></label></th><td><input name="<?php echo esc_attr( $k ); ?>[user_denial][title]" type="text" id="<?php echo esc_attr( $k ); ?>_user_denial_title" value="<?php echo esc_attr( $this->get_option( array( 'user_denial', 'title' ) ) ); ?>" class="large-text"></td></tr>
			<tr><th><label for="<?php echo esc_attr( $k ); ?>_user_denial_message"><?php esc_html_e( 'Message', 'tml-classic' ); ?></label></th><td><p class="description"><?php esc_html_e( 'Available Variables', 'tml-classic' ); ?>: %blogname%, %siteurl%, %user_login%, %user_email%</p><textarea name="<?php echo esc_attr( $k ); ?>[user_denial][message]" id="<?php echo esc_attr( $k ); ?>_user_denial_message" class="large-text" rows="10"><?php echo esc_textarea( $this->get_option( array( 'user_denial', 'message' ) ) ); ?></textarea></td></tr>
			<tr><th>&nbsp;</th><td><label><input name="<?php echo esc_attr( $k ); ?>[user_denial][disable]" type="checkbox" id="<?php echo esc_attr( $k ); ?>_user_denial_disable" value="1"<?php checked( 1, $this->get_option( array( 'user_denial', 'disable' ) ) ); ?>> <?php esc_html_e( 'Disable Notification', 'tml-classic' ); ?></label></td></tr>
		</table>
		<?php
	}

	public function save_settings( $settings ) {
		$settings['new_user']['admin_disable']   = !empty( $settings['new_user']['admin_disable'] );
		$settings['reset_pass']['admin_disable']  = !empty( $settings['reset_pass']['admin_disable'] );
		if ( class_exists( 'TML_Classic_User_Moderation' ) ) {
			$settings['user_approval']['admin_disable'] = !empty( $settings['user_approval']['admin_disable'] );
			$settings['user_denial']['disable']         = !empty( $settings['user_denial']['disable'] );
		}
		return TML_Classic_Common::array_merge_recursive( $this->get_options(), $settings );
	}
}

TML_Classic_Custom_Email::get_object();

endif;