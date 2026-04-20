<?php
/*
 * @package TML_Classic
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Admin' ) ) :
class TML_Classic_Admin extends TML_Classic_Abstract {
	protected $options_key = 'tml_classic';

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		return TML_Classic::default_options();
	}

	protected function load() {
		add_action( 'admin_init',            array( $this, 'admin_init'            ) );
		add_action( 'admin_menu',            array( $this, 'admin_menu'            ), 8 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 11 );
		add_action( 'add_meta_boxes',        array( $this, 'add_meta_boxes'        ) );
		add_action( 'save_post',             array( $this, 'save_action_meta_box'  ) );
		register_uninstall_hook( TML_CLASSIC_PATH . '/tml-classic.php', array( 'TML_Classic_Admin', 'uninstall' ) );
	}

	public function admin_menu() {
		add_menu_page(
			__( 'TML Classic General Settings', 'tml-classic' ),
			__( 'TML Classic', 'tml-classic' ),
			'manage_options',
			'tml_classic',
			array( 'TML_Classic_Admin', 'settings_page' ),
			'dashicons-businessman'
		);
		add_submenu_page(
			'tml_classic',
			__( 'TML Classic General Settings', 'tml-classic' ),
			__( 'General', 'tml-classic' ),
			'manage_options',
			'tml_classic',
			array( 'TML_Classic_Admin', 'settings_page' )
		);
	}

	public function admin_init() {
		register_setting( 'tml_classic', 'tml_classic', array( $this, 'save_settings' ) );
		if ( version_compare( $this->get_option( 'version', 0 ), TML_CLASSIC_VERSION, '<' ) )
			$this->install();
		add_settings_section( 'general',          __( 'General',    'tml-classic' ), '__return_false', $this->options_key );
		add_settings_section( 'addons',           __( 'Addons',     'tml-classic' ), '__return_false', $this->options_key );
		add_settings_field(   'enable_css',       __( 'Stylesheet', 'tml-classic' ), array( $this, 'settings_field_enable_css'       ), $this->options_key, 'general' );
		add_settings_field(   'login_type',       __( 'Login Type', 'tml-classic' ), array( $this, 'settings_field_login_type'       ), $this->options_key, 'general' );
		add_settings_field(   'regenerate_pages', __( 'Pages',      'tml-classic' ), array( $this, 'settings_field_regenerate_pages' ), $this->options_key, 'general' );
		add_settings_field(   'addons',           __( 'Addons',     'tml-classic' ), array( $this, 'settings_field_addons'           ), $this->options_key, 'addons'  );
	}

	public function admin_enqueue_scripts() {
		wp_add_inline_script( 'common', '(function(){var f=document.getElementById("wp-auth-check-form");if(f&&typeof tmlAdmin!=="undefined"){f.dataset.src=tmlAdmin.interim_login_url;}})();' );
		wp_localize_script( 'common', 'tmlAdmin', array(
			'interim_login_url' => site_url( 'wp-login.php?interim-login=1', 'login' ),
		) );
	}

	public function add_meta_boxes() {
		add_meta_box(
			'tml_action',
			__( 'TML Classic Action', 'tml-classic' ),
			array( $this, 'action_meta_box' ),
			'page',
			'side'
		);
	}

	public function action_meta_box( $post ) {
		$page_action = get_post_meta( $post->ID, '_tml_action', true );
		wp_nonce_field( 'tml_action_meta_box', 'tml_action_nonce' );
		?>
		<select name="tml_action" id="tml_action">
			<option value=""></option>
			<?php foreach ( TML_Classic::default_pages() as $action => $label ) : ?>
				<option value="<?php echo esc_attr( $action ); ?>"<?php selected( $action, $page_action ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function save_action_meta_box( $post_id ) {
		if ( 'page' != get_post_type( $post_id ) )
			return;
		if ( !isset( $_POST['tml_action_nonce'] ) || !wp_verify_nonce( wp_unslash( $_POST['tml_action_nonce'] ), 'tml_action_meta_box' ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		if ( isset( $_POST['tml_action'] ) ) {
			$tml_action = sanitize_key( $_POST['tml_action'] );
			if ( !empty( $tml_action ) ) {
				update_post_meta( $post_id, '_tml_action', $tml_action );
			} else {
				if ( false !== get_post_meta( $post_id, '_tml_action', true ) )
					delete_post_meta( $post_id, '_tml_action' );
			}
		}
	}

	public static function settings_page( $args = '' ) {
		extract( wp_parse_args( $args, array(
			'title'       => __( 'TML Classic General Settings', 'tml-classic' ),
			'options_key' => 'tml_classic',
		) ) );
		?>
		<div id="<?php echo esc_attr( $options_key ); ?>" class="wrap">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( $options_key );
				do_settings_sections( $options_key );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function settings_field_enable_css() {
		?>
		<input name="tml_classic[enable_css]" type="checkbox" id="tml_classic_enable_css" value="1"<?php checked( 1, $this->get_option( 'enable_css' ) ); ?>>
		<label for="tml_classic_enable_css"><?php esc_html_e( 'Enable Default Styles', 'tml-classic' ); ?></label>
		<p class="description"><?php esc_html_e( 'To keep changes between upgrades, store your customized "tml-classic.css" in your current theme directory.', 'tml-classic' ); ?></p>
		<?php
	}

	public function settings_field_login_type() {
		?>
		<ul>
			<li><input name="tml_classic[login_type]" type="radio" id="tml_classic_login_type_default" value="default"<?php checked( 'default', $this->get_option( 'login_type' ) ); ?>>
			<label for="tml_classic_login_type_default"><?php esc_html_e( 'Username or Email', 'tml-classic' ); ?></label></li>
			<li><input name="tml_classic[login_type]" type="radio" id="tml_classic_login_type_username" value="username"<?php checked( 'username', $this->get_option( 'login_type' ) ); ?>>
			<label for="tml_classic_login_type_username"><?php esc_html_e( 'Username only', 'tml-classic' ); ?></label></li>
			<li><input name="tml_classic[login_type]" type="radio" id="tml_classic_login_type_email" value="email"<?php checked( 'email', $this->get_option( 'login_type' ) ); ?>>
			<label for="tml_classic_login_type_email"><?php esc_html_e( 'Email only', 'tml-classic' ); ?></label></li>
		</ul>
		<p class="description"><?php esc_html_e( 'Allow users to login using their username and/or email address.', 'tml-classic' ); ?></p>
		<?php
	}

	public function settings_field_regenerate_pages() {
		?>
		<input type="hidden" name="tml_classic[regenerate_pages]" id="tml_classic_regenerate_pages" value="0">
		<button type="submit" class="button button-secondary" onclick="document.getElementById('tml_classic_regenerate_pages').value='1';"><?php esc_html_e( 'Regenerate Pages', 'tml-classic' ); ?></button>
		<?php
	}

	public function settings_field_addons() {
		$available_addons = array(
			'captcha.php'     => __( 'Captcha',     'tml-classic' ),
			'email.php'       => __( 'Email',       'tml-classic' ),
			'links.php'       => __( 'Links',       'tml-classic' ),
			'moderation.php'  => __( 'Moderation',  'tml-classic' ),
			'passwords.php'   => __( 'Passwords',   'tml-classic' ),
			'profiles.php'    => __( 'Profiles',    'tml-classic' ),
			'redirection.php' => __( 'Redirection', 'tml-classic' ),
			'security.php'    => __( 'Security',    'tml-classic' ),
		);
		$active_addons = (array) $this->get_option( 'active_addons', array() );
		foreach ( $available_addons as $file => $name ) {
			$id          = 'tml_classic_addon_' . sanitize_key( $file );
			$addon_path  = TML_CLASSIC_PATH . '/addons/' . $file;
			$description = '';
			if ( file_exists( $addon_path ) ) {
				$headers = get_file_data( $addon_path, array( 'Description' => 'Description' ) );
				$description = $headers['Description'];
			}
			?>
			<p>
				<input name="tml_classic[active_addons][]" type="checkbox" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $file ); ?>"<?php checked( in_array( $file, $active_addons ) ); ?>>
				<label for="<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $name ); ?></strong><?php if ( $description ) : ?> (<?php echo esc_html( $description ); ?>)<?php endif; ?></label>
			</p>
			<?php
		}
	}

	public function save_settings( $settings ) {
		$settings['enable_css']    = !empty( $settings['enable_css'] );
		$settings['login_type']    = in_array( $settings['login_type'], array( 'default', 'username', 'email' ) ) ? $settings['login_type'] : 'default';
		$settings['active_addons'] = isset( $settings['active_addons'] ) ? array_map( 'sanitize_file_name', (array) $settings['active_addons'] ) : array();
		if ( !empty( $settings['regenerate_pages'] ) ) {
			$this->install();
			if ( in_array( 'profiles.php', $settings['active_addons'] ) ) {
				$settings_admin = TML_Classic_Profiles_Admin::get_object();
				if ( $settings_admin )
					$settings_admin->activate();
			}
		}
		unset( $settings['regenerate_pages'] );
		$old_addons = $this->get_option( 'active_addons', array() );
		if ( $activate = array_diff( $settings['active_addons'], $old_addons ) ) {
			foreach ( $activate as $addon ) {
				if ( file_exists( TML_CLASSIC_PATH . '/addons/' . $addon ) )
					include_once TML_CLASSIC_PATH . '/addons/' . $addon;
				do_action( 'tml_activate_' . $addon );
			}
		}
		if ( $deactivate = array_diff( $old_addons, $settings['active_addons'] ) ) {
			foreach ( $deactivate as $addon ) {
				do_action( 'tml_deactivate_' . $addon );
			}
		}
		$settings = wp_parse_args( $settings, $this->get_options() );
		return $settings;
	}

	public function install() {
		foreach ( TML_Classic::default_pages() as $action => $title ) {
			if ( !TML_Classic::get_page_id( $action ) ) {
				$page_id = wp_insert_post( array(
					'post_title'     => $title,
					'post_name'      => $action,
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'post_content'   => '[tml-classic]',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				) );
				if ( $page_id && !is_wp_error( $page_id ) )
					update_post_meta( $page_id, '_tml_action', $action );
			}
		}
		foreach ( $this->get_option( 'active_addons', array() ) as $addon ) {
			if ( file_exists( TML_CLASSIC_PATH . '/addons/' . $addon ) )
				include_once TML_CLASSIC_PATH . '/addons/' . $addon;
			do_action( 'tml_activate_' . $addon );
		}
		$this->set_option( 'version', TML_CLASSIC_VERSION );
		$this->save_options();
	}

	public static function uninstall() {
		global $wpdb;
		if ( is_multisite() && isset( $_GET['networkwide'] ) && 1 == (int) $_GET['networkwide'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$blogids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			foreach ( $blogids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::_uninstall();
			}
			restore_current_blog();
			return;
		}
		self::_uninstall();
	}

	protected static function _uninstall() {
		$active_addons = get_option( 'tml_classic', array() );
		$active_addons = isset( $active_addons['active_addons'] ) ? $active_addons['active_addons'] : array();
		foreach ( $active_addons as $addon ) {
			if ( file_exists( TML_CLASSIC_PATH . '/addons/' . $addon ) )
				include TML_CLASSIC_PATH . '/addons/' . $addon;
			do_action( 'tml_uninstall_' . $addon );
		}
		delete_option( 'tml_classic' );
		delete_option( 'widget_tml-classic' );
	}
}

endif;