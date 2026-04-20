<?php
/*
 * Addon Name: Profiles
 * Description: Adds the ability to block admin access and enable themed user settings on the front-end based on user role.
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Profiles' ) ) :

class TML_Classic_Profiles extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic_profiles';

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
					'theme_settings' => true,
					'restrict_admin' => false,
				);
			}
		}
		return $options;
	}

	protected function load() {
		add_filter( 'tml_default_pages',      array( $this, 'tml_default_pages'      ) );
		add_action( 'tml_addons_loaded',      array( $this, 'addons_loaded'          ) );
		add_action( 'init',                   array( $this, 'init'                   ) );
		add_action( 'template_redirect',      array( $this, 'template_redirect'      ) );
		add_filter( 'show_admin_bar',         array( $this, 'show_admin_bar'         ) );
		add_action( 'tml_request_settings',   array( $this, 'tml_request_settings'   ) );
		add_action( 'tml_display_settings',   array( $this, 'tml_display_settings'   ) );
		add_filter( 'wp_setup_nav_menu_item', array( $this, 'wp_setup_nav_menu_item' ), 12 );
	}

	public function tml_default_pages( $pages ) {
		$pages['settings'] = __( 'Settings', 'tml-classic' );
		return $pages;
	}

	public function addons_loaded() {
		add_filter( 'site_url',  array( $this, 'site_url' ), 10, 3 );
		add_filter( 'admin_url', array( $this, 'site_url' ), 10, 2 );
	}

	public function init() {
		global $current_user, $pagenow;
		if ( !is_user_logged_in() || !is_admin() )
			return;
		$redirect_to = TML_Classic::get_page_link( 'settings' );
		$user_role   = reset( $current_user->roles );
		if ( is_multisite() && empty( $user_role ) )
			$user_role = 'subscriber';
		if ( 'profile.php' === $pagenow && !isset( $_REQUEST['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $this->get_option( array( $user_role, 'theme_settings' ) ) ) {
				if ( !empty( $_GET ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$redirect_to = add_query_arg( (array) $_GET, $redirect_to ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( $redirect_to );
				exit;
			}
		} else {
			if ( $this->get_option( array( $user_role, 'restrict_admin' ) ) ) {
				if ( !defined( 'DOING_AJAX' ) ) {
					wp_safe_redirect( $redirect_to );
					exit;
				}
			}
		}
	}

	public function template_redirect() {
		$tml = TML_Classic::get_object();
		if ( !TML_Classic::is_tml_page() )
			return;
		switch ( $tml->request_action ) {
			case 'settings':
				if ( !is_user_logged_in() ) {
					wp_safe_redirect( TML_Classic::get_page_link( 'login', 'reauth=1' ) );
					exit;
				}
				break;
			case 'logout':
				break;
			case 'register':
				if ( is_multisite() )
					break;
			default:
				if ( is_user_logged_in() ) {
					wp_safe_redirect( TML_Classic::get_page_link( 'settings' ) );
					exit;
				}
		}
	}

	public function show_admin_bar( $show_admin_bar ) {
		global $current_user;
		$user_role = reset( $current_user->roles );
		if ( is_multisite() && empty( $user_role ) )
			$user_role = 'subscriber';
		if ( $this->get_option( array( $user_role, 'restrict_admin' ) ) )
			return false;
		return $show_admin_bar;
	}

	public function tml_request_settings() {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		define( 'IS_PROFILE_PAGE', true );
		load_textdomain( 'default', WP_LANG_DIR . '/admin-' . determine_locale() . '.mo' );
		register_admin_color_schemes();
		wp_enqueue_style( 'tml-classic', plugins_url( 'tml-classic.css', TML_CLASSIC_PATH . '/tml-classic.php' ), array(), TML_CLASSIC_VERSION );
		wp_enqueue_script( 'user-profile' );
		$current_user = wp_get_current_user();
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			check_admin_referer( 'update-user_' . $current_user->ID );
			if ( !current_user_can( 'edit_user', $current_user->ID ) )
				wp_die( esc_html__( 'You do not have permission to edit this user.', 'tml-classic' ) );
			do_action( 'personal_options_update', $current_user->ID );
			$errors = edit_user( $current_user->ID );
			if ( !is_wp_error( $errors ) ) {
				$args = array( 'updated' => 'true' );
				if ( !empty( $_REQUEST['instance'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$args['instance'] = sanitize_key( $_REQUEST['instance'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( add_query_arg( $args ) );
				exit;
			} else {
				TML_Classic::get_object()->errors = $errors;
			}
		}
	}

	public function tml_display_settings( &$template ) {
		global $current_user, $profileuser, $_wp_admin_css_colors, $wp_version;
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		if ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			TML_Classic::get_object()->errors->add( 'settings_updated', __( 'Settings updated.', 'tml-classic' ), 'message' );
		$current_user = wp_get_current_user();
		$profileuser  = get_user_to_edit( $current_user->ID );
		$user_role    = reset( $profileuser->roles );
		if ( is_multisite() && empty( $user_role ) )
			$user_role = 'subscriber';
		$_template   = array();
		if ( !empty( $template->options['settings_template'] ) )
			$_template[] = $template->options['settings_template'];
		if ( !empty( $template->options[ "settings_template_{$user_role}" ] ) )
			$_template[] = $template->options[ "settings_template_{$user_role}" ];
		$_template[] = "profile-form-{$user_role}.php";
		$_template[] = 'profile-form.php';
		$template->get_template( $_template, true, compact( 'current_user', 'profileuser', 'user_role', '_wp_admin_css_colors', 'wp_version' ) );
	}

	public function site_url( $url, $path, $orig_scheme = '' ) {
		global $current_user, $pagenow;
		if ( 'profile.php' === $pagenow || false === strpos( $url, 'profile.php' ) )
			return $url;
		$user_role = reset( $current_user->roles );
		if ( is_multisite() && empty( $user_role ) )
			$user_role = 'subscriber';
		if ( $user_role && !$this->get_option( array( $user_role, 'theme_settings' ) ) )
			return $url;
		$parsed_url = wp_parse_url( $url );
		$new_url    = TML_Classic::get_page_link( 'settings' );
		if ( isset( $parsed_url['query'] ) )
			$new_url = add_query_arg( array_map( 'rawurlencode', wp_parse_args( $parsed_url['query'] ) ), $new_url );
		return $new_url;
	}

	public function wp_setup_nav_menu_item( $menu_item ) {
		if ( is_admin() || 'page' !== $menu_item->object )
			return $menu_item;
		if ( !is_user_logged_in() && TML_Classic::is_tml_page( 'settings', $menu_item->object_id ) )
			$menu_item->_invalid = true;
		return $menu_item;
	}
}

TML_Classic_Profiles::get_object();

endif;

if ( is_admin() && !class_exists( 'TML_Classic_Profiles_Admin' ) ) :

class TML_Classic_Profiles_Admin extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic_profiles';

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		return TML_Classic_Profiles::default_options();
	}

	protected function load() {
		add_action( 'tml_activate_profiles.php',  array( $this, 'activate'  ) );
		add_action( 'tml_uninstall_profiles.php', array( $this, 'uninstall' ) );
		add_action( 'admin_menu',                 array( $this, 'admin_menu' ) );
		add_action( 'admin_init',                 array( $this, 'admin_init' ) );
	}

	public function activate() {
		if ( !TML_Classic::get_page_id( 'settings' ) ) {
			$page_id = wp_insert_post( array(
				'post_title'     => __( 'Settings', 'tml-classic' ),
				'post_name'      => 'settings',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_content'   => '[tml-classic]',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			) );
			if ( $page_id && !is_wp_error( $page_id ) )
				update_post_meta( $page_id, '_tml_action', 'settings' );
		}
	}

	public function uninstall() {
		delete_option( $this->options_key );
	}

	public function admin_menu() {
		add_submenu_page(
			'tml_classic',
			__( 'TML Classic Profiles Settings', 'tml-classic' ),
			__( 'Profiles', 'tml-classic' ),
			'manage_options',
			$this->options_key,
			array( $this, 'settings_page' )
		);
		add_settings_section( 'general', null, '__return_false', $this->options_key );
		add_settings_field( 'settings', __( 'Themed Settings', 'tml-classic' ), array( $this, 'settings_field_settings' ), $this->options_key, 'general' );
		add_settings_field( 'restrict_admin',  __( 'Restrict Admin Access', 'tml-classic' ), array( $this, 'settings_field_restrict_admin_access' ), $this->options_key, 'general' );
	}

	public function admin_init() {
		register_setting( $this->options_key, $this->options_key, array( $this, 'save_settings' ) );
	}

	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TML Classic Profiles Settings', 'tml-classic' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( $this->options_key ); ?>
				<?php do_settings_sections( $this->options_key ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function settings_field_settings() {
		global $wp_roles;
		foreach ( $wp_roles->get_names() as $role => $role_name ) {
			if ( 'pending' === $role )
				continue;
			?>
			<input name="<?php echo esc_attr( $this->options_key ); ?>[<?php echo esc_attr( $role ); ?>][theme_settings]" type="checkbox" id="<?php echo esc_attr( $this->options_key . '_' . $role . '_theme_settings' ); ?>" value="1"<?php checked( $this->get_option( array( $role, 'theme_settings' ) ) ); ?>>
			<label for="<?php echo esc_attr( $this->options_key . '_' . $role . '_theme_settings' ); ?>"><?php echo esc_html( translate_user_role( $role_name ) ); ?></label><br>
			<?php
		}
	}

	public function settings_field_restrict_admin_access() {
		global $wp_roles;
		foreach ( $wp_roles->get_names() as $role => $role_name ) {
			if ( 'pending' === $role )
				continue;
			$disabled = ( 'administrator' === $role ) ? ' disabled="disabled"' : '';
			?>
			<input name="<?php echo esc_attr( $this->options_key ); ?>[<?php echo esc_attr( $role ); ?>][restrict_admin]" type="checkbox" id="<?php echo esc_attr( $this->options_key . '_' . $role . '_restrict_admin' ); ?>" value="1"<?php checked( $this->get_option( array( $role, 'restrict_admin' ) ) ); echo esc_attr( $disabled ); ?>>
			<label for="<?php echo esc_attr( $this->options_key . '_' . $role . '_restrict_admin' ); ?>"><?php echo esc_html( translate_user_role( $role_name ) ); ?></label><br>
			<?php
		}
	}

	public function save_settings( $settings ) {
		global $wp_roles;
		foreach ( $wp_roles->get_names() as $role => $role_name ) {
			if ( 'pending' === $role )
				continue;
			$settings[ $role ] = array(
				'theme_settings' => !empty( $settings[ $role ]['theme_settings'] ),
				'restrict_admin' => ( 'administrator' !== $role ) && !empty( $settings[ $role ]['restrict_admin'] ),
			);
		}
		return $settings;
	}
}

TML_Classic_Profiles_Admin::get_object();

endif;