<?php
/*
 * Addon Name: Redirection
 * Description: Adds the ability to redirect users to custom areas when logging in/out based on user role.
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Custom_Redirection' ) ) :
class TML_Classic_Custom_Redirection extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic_redirection';

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		global $wp_roles;
		if ( empty( $wp_roles ) )
			$wp_roles = new WP_Roles();
		$options = array();
		foreach ( $wp_roles->get_names() as $role => $label ) {
			if ( 'pending' !== $role ) {
				$options[ $role ] = array(
					'login_type'  => 'default',
					'login_url'   => '',
					'logout_type' => 'default',
					'logout_url'  => '',
				);
			}
		}
		return $options;
	}

	protected function load() {
		add_action( 'tml_uninstall_redirection.php', array( $this, 'uninstall' ) );
		add_action( 'login_form',      array( $this, 'login_form'      )        );
		add_filter( 'login_redirect',  array( $this, 'login_redirect'  ), 10, 3 );
		add_filter( 'logout_redirect', array( $this, 'logout_redirect' ), 10, 3 );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}
	}

	public function get_redirect_for_user( $user, $type = 'login', $default = '' ) {
		if ( empty( $default ) )
			$default = admin_url( 'profile.php' );
		if ( !$user instanceof WP_User )
			return $default;
		if ( 'login' !== $type && 'logout' !== $type )
			$type = 'login';
		if ( is_multisite() && empty( $user->roles ) )
			$user->roles = array( 'subscriber' );
		$user_role   = reset( $user->roles );
		$redirection = $this->get_option( $user_role, array() );
		switch ( $redirection["{$type}_type"] ) {
			case 'referer':
				if ( !$referer = wp_get_original_referer() )
					$referer = wp_get_referer();
				$referer = TML_Classic_Common::strip_query_args( $referer );
				if ( $page_id = url_to_postid( $referer ) ) {
					if ( TML_Classic::is_tml_page( null, $page_id ) )
						return $default;
				}
				$redirect_to = $referer;
				break;
			case 'custom':
				$redirect_to = str_replace(
					array( '%user_id%', '%user_nicename%' ),
					array( $user->ID, $user->user_nicename ),
					$redirection["{$type}_url"]
				);
				break;
			default:
				$redirect_to = $default;
				break;
		}
		if ( empty( $redirect_to ) )
			$redirect_to = $default;
		return $redirect_to;
	}

	public function login_form() {
		if ( !empty( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$referer = sanitize_url( wp_unslash( $_REQUEST['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
		} elseif ( wp_get_original_referer() ) {
			$referer = wp_get_original_referer();
		} else {
			$referer = TML_Classic::is_tml_page() ? wp_get_referer() : sanitize_url( wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		echo '<input type="hidden" name="_wp_original_http_referer" value="' . esc_attr( $referer ) . '">';
	}

	public function login_redirect( $redirect_to, $request, $user ) {
		return $this->get_redirect_for_user( $user, 'login', $redirect_to );
	}

	public function logout_redirect( $redirect_to, $request, $user ) {
		$redirect_to = $this->get_redirect_for_user( $user, 'logout', $redirect_to );
		if ( false !== strpos( $redirect_to, 'wp-admin' ) )
			$redirect_to = add_query_arg( 'loggedout', 'true', wp_login_url() );
		return $redirect_to;
	}

	public function uninstall() {
		delete_option( $this->options_key );
	}

	public function admin_menu() {
		$hook = add_submenu_page(
			'tml_classic',
			__( 'TML Classic Redirection Settings', 'tml-classic' ),
			__( 'Redirection', 'tml-classic' ),
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
		global $wp_roles;
		$screen = get_current_screen();
		wp_enqueue_script( 'postbox' );
		wp_add_inline_script( 'postbox', 'postboxes.add_postbox_toggles(pagenow);' );
		foreach ( $wp_roles->get_names() as $role => $role_name ) {
			if ( 'pending' !== $role )
				add_meta_box( $role, translate_user_role( $role_name ), array( $this, 'redirection_meta_box' ), $screen->id, 'normal' );
		}
	}

	public function settings_page() {
		$screen = get_current_screen();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TML Classic Redirection Settings', 'tml-classic' ); ?></h1>
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

	public function redirection_meta_box( $object, $box ) {
		$role = $box['id'];
		$k    = $this->options_key;
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Log in', 'tml-classic' ); ?></th>
				<td>
					<input name="<?php echo esc_attr( $k ); ?>[<?php echo esc_attr( $role ); ?>][login_type]" type="radio" id="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_login_type_default" value="default"<?php checked( 'default', $this->get_option( array( $role, 'login_type' ) ) ); ?>>
					<label for="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_login_type_default"><?php esc_html_e( 'Default', 'tml-classic' ); ?></label>
					<p class="description"><?php esc_html_e( 'Send the user to their WordPress Dashboard/Settings.', 'tml-classic' ); ?></p>
					<input name="<?php echo esc_attr( $k ); ?>[<?php echo esc_attr( $role ); ?>][login_type]" type="radio" id="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_login_type_referer" value="referer"<?php checked( 'referer', $this->get_option( array( $role, 'login_type' ) ) ); ?>>
					<label for="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_login_type_referer"><?php esc_html_e( 'Referer', 'tml-classic' ); ?></label>
					<p class="description"><?php esc_html_e( 'Send the user back to the page they were visiting before logging in.', 'tml-classic' ); ?></p>
					<input name="<?php echo esc_attr( $k ); ?>[<?php echo esc_attr( $role ); ?>][login_type]" type="radio" id="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_login_type_custom" value="custom"<?php checked( 'custom', $this->get_option( array( $role, 'login_type' ) ) ); ?>>
					<input name="<?php echo esc_attr( $k ); ?>[<?php echo esc_attr( $role ); ?>][login_url]" type="text" id="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_login_url" value="<?php echo esc_attr( $this->get_option( array( $role, 'login_url' ) ) ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Send the user to a custom URL specified above.', 'tml-classic' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Log out', 'tml-classic' ); ?></th>
				<td>
					<input name="<?php echo esc_attr( $k ); ?>[<?php echo esc_attr( $role ); ?>][logout_type]" type="radio" id="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_logout_type_default" value="default"<?php checked( 'default', $this->get_option( array( $role, 'logout_type' ) ) ); ?>>
					<label for="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_logout_type_default"><?php esc_html_e( 'Default', 'tml-classic' ); ?></label>
					<p class="description"><?php esc_html_e( 'Send the user to the log in page with a logged-out message.', 'tml-classic' ); ?></p>
					<input name="<?php echo esc_attr( $k ); ?>[<?php echo esc_attr( $role ); ?>][logout_type]" type="radio" id="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_logout_type_referer" value="referer"<?php checked( 'referer', $this->get_option( array( $role, 'logout_type' ) ) ); ?>>
					<label for="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_logout_type_referer"><?php esc_html_e( 'Referer', 'tml-classic' ); ?></label>
					<p class="description"><?php esc_html_e( 'Send the user back to the page they were visiting before logging out.', 'tml-classic' ); ?></p>
					<input name="<?php echo esc_attr( $k ); ?>[<?php echo esc_attr( $role ); ?>][logout_type]" type="radio" id="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_logout_type_custom" value="custom"<?php checked( 'custom', $this->get_option( array( $role, 'logout_type' ) ) ); ?>>
					<input name="<?php echo esc_attr( $k ); ?>[<?php echo esc_attr( $role ); ?>][logout_url]" type="text" id="<?php echo esc_attr( $k ); ?>_<?php echo esc_attr( $role ); ?>_logout_url" value="<?php echo esc_attr( $this->get_option( array( $role, 'logout_url' ) ) ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Send the user to a custom URL specified above.', 'tml-classic' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_settings( $settings ) {
		global $wp_roles;
		foreach ( $wp_roles->get_names() as $role => $label ) {
			if ( 'pending' === $role || !isset( $settings[ $role ] ) )
				continue;
			$settings[ $role ] = array(
				'login_type'  => in_array( $settings[ $role ]['login_type'],  array( 'default', 'referer', 'custom' ), true ) ? $settings[ $role ]['login_type']  : 'default',
				'login_url'   => esc_url_raw( $settings[ $role ]['login_url']  ?? '' ),
				'logout_type' => in_array( $settings[ $role ]['logout_type'], array( 'default', 'referer', 'custom' ), true ) ? $settings[ $role ]['logout_type'] : 'default',
				'logout_url'  => esc_url_raw( $settings[ $role ]['logout_url'] ?? '' ),
			);
		}
		return $settings;
	}
}

TML_Classic_Custom_Redirection::get_object();

endif;