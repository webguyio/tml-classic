<?php
/*
 * @package TML_Classic
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic' ) ) :

class TML_Classic extends TML_Classic_Abstract {
	const VERSION = TML_CLASSIC_VERSION;

	protected $options_key = 'tml_classic';

	public $errors;
	public $request_page;
	public $request_action;
	public $request_instance = 0;
	public $current_instance = 0;
	protected $loaded_instances = array();

	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public static function default_options() {
		return apply_filters( 'tml_default_options', array(
			'enable_css'     => true,
			'login_type'     => 'default',
			'active_addons'  => array(),
		) );
	}

	public static function default_pages() {
		return apply_filters( 'tml_default_pages', array(
			'login'    => __( 'Log In',         'tml-classic' ),
			'logout'   => __( 'Log Out',        'tml-classic' ),
			'register' => __( 'Register',       'tml-classic' ),
			'lost'     => __( 'Lost Password',  'tml-classic' ),
			'reset'    => __( 'Reset Password', 'tml-classic' ),
		) );
	}

	protected function load() {
		$this->request_action   = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->request_instance = isset( $_REQUEST['instance'] ) ? (int) $_REQUEST['instance'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->load_instance();
		add_action( 'plugins_loaded',          array( $this, 'plugins_loaded'          ) );
		add_action( 'init',                    array( $this, 'init'                    ) );
		add_action( 'widgets_init',            array( $this, 'widgets_init'            ) );
		add_action( 'wp',                      array( $this, 'wp'                      ) );
		add_action( 'pre_get_posts',           array( $this, 'pre_get_posts'           ) );
		add_action( 'template_redirect',       array( $this, 'template_redirect'       ) );
		add_action( 'wp_enqueue_scripts',      array( $this, 'wp_enqueue_scripts'      ) );
		add_action( 'wp_head',                 array( $this, 'wp_head'                 ) );
		add_action( 'wp_footer',               array( $this, 'wp_footer'               ) );
		add_action( 'wp_print_footer_scripts', array( $this, 'wp_print_footer_scripts' ) );
		add_filter( 'site_url',                array( $this, 'site_url'                ), 10, 3 );
		add_filter( 'logout_url',              array( $this, 'logout_url'              ), 10, 2 );
		add_filter( 'single_post_title',       array( $this, 'single_post_title'       )        );
		add_filter( 'the_title',               array( $this, 'the_title'               ), 10, 2 );
		add_filter( 'document_title_parts',    array( $this, 'document_title_parts'    )        );
		add_filter( 'wp_setup_nav_menu_item',  array( $this, 'wp_setup_nav_menu_item'  )        );
		add_filter( 'wp_list_pages_excludes',  array( $this, 'wp_list_pages_excludes'  )        );
		add_filter( 'page_link',               array( $this, 'page_link'               ), 10, 2 );
		add_filter( 'authenticate',            array( $this, 'authenticate'            ), 20, 3 );
		add_shortcode( 'tml-classic', array( $this, 'shortcode' ) );
		if ( 'username' == $this->get_option( 'login_type' ) ) {
			remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
		} elseif ( 'email' == $this->get_option( 'login_type' ) ) {
			remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
		}
	}

	public function plugins_loaded() {
		foreach ( $this->get_option( 'active_addons', array() ) as $addon ) {
			if ( file_exists( TML_CLASSIC_PATH . '/addons/' . $addon ) )
				include_once TML_CLASSIC_PATH . '/addons/' . $addon;
		}
		do_action_ref_array( 'tml_addons_loaded', array( &$this ) );
	}

	public function init() {
		global $pagenow;
		$this->errors = new WP_Error();
		if ( !is_admin() && 'wp-login.php' != $pagenow && $this->get_option( 'enable_css' ) )
			wp_enqueue_style( 'tml-classic', self::get_stylesheet(), array( 'dashicons' ), TML_CLASSIC_VERSION );
	}

	public function widgets_init() {
		if ( class_exists( 'TML_Classic_Widget' ) )
			register_widget( 'TML_Classic_Widget' );
	}

	public function wp() {
		if ( self::is_tml_page() ) {
			$this->request_page = self::get_page_action( get_the_id() );
			if ( empty( $this->request_action ) )
				$this->request_action = $this->request_page;
			do_action( 'login_init' );
			remove_action( 'wp_head', 'feed_links',                       2 );
			remove_action( 'wp_head', 'feed_links_extra',                 3 );
			remove_action( 'wp_head', 'rsd_link'                            );
			remove_action( 'wp_head', 'wlwmanifest_link'                    );
			remove_action( 'wp_head', 'parent_post_rel_link',            10 );
			remove_action( 'wp_head', 'start_post_rel_link',             10 );
			remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
			remove_action( 'wp_head', 'rel_canonical'                       );
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
			if ( force_ssl_admin() && !is_ssl() ) {
				if ( 0 === strpos( $_SERVER['REQUEST_URI'], 'http' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					wp_safe_redirect( set_url_scheme( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'https' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					exit;
				} else {
					wp_safe_redirect( 'https://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					exit;
				}
			}
			nocache_headers();
		}
	}

	public function pre_get_posts( $query ) {
		if ( is_admin() )
			return;
		if ( !$query->is_main_query() )
			return;
		if ( !$query->is_search )
			return;
		$post_type = $query->get( 'post_type' );
		if ( !empty( $post_type ) && !in_array( 'page', (array) $post_type ) )
			return;
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'any',
			'meta_key'       => '_tml_action', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'posts_per_page' => -1,
		) );
		$pages    = wp_list_pluck( $pages, 'ID' );
		$excludes = array_merge( (array) $query->get( 'post__not_in' ), $pages );
		$query->set( 'post__not_in', $excludes );
	}

	public function template_redirect() {
		do_action_ref_array( 'tml_request', array( &$this ) );
		do_action( 'login_form_' . $this->request_action );
		if ( has_action( 'tml_request_' . $this->request_action ) ) {
			do_action_ref_array( 'tml_request_' . $this->request_action, array( &$this ) );
		} else {
			$http_post = ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' == $_SERVER['REQUEST_METHOD'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			switch ( $this->request_action ) {
				case 'postpass':
					if ( !array_key_exists( 'post_password', $_POST ) ) {
						wp_safe_redirect( wp_get_referer() );
						exit();
					}
					require_once ABSPATH . 'wp-includes/class-phpass.php';
					$hasher  = new PasswordHash( 8, true );
					$expire  = apply_filters( 'post_password_expires', time() + 10 * DAY_IN_SECONDS );
					$referer = wp_get_referer();
					$secure  = $referer ? ( 'https' === wp_parse_url( $referer, PHP_URL_SCHEME ) ) : false;
					setcookie( 'wp-postpass_' . COOKIEHASH, $hasher->HashPassword( sanitize_text_field( wp_unslash( $_POST['post_password'] ) ) ), $expire, COOKIEPATH, COOKIE_DOMAIN, $secure ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
					wp_safe_redirect( wp_get_referer() );
					exit;
				case 'logout':
					check_admin_referer( 'log-out' );
					$user = wp_get_current_user();
					wp_logout();
					if ( !empty( $_REQUEST['redirect_to'] ) ) {
						$redirect_to           = $requested_redirect_to = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					} else {
						$redirect_to           = site_url( 'wp-login.php?loggedout=true' );
						$requested_redirect_to = '';
					}
					$redirect_to = apply_filters( 'logout_redirect', $redirect_to, $requested_redirect_to, $user );
					wp_safe_redirect( $redirect_to );
					exit;
				case 'lostpassword':
				case 'retrievepassword':
					if ( $http_post ) {
						$this->errors = self::retrieve_password();
						if ( !is_wp_error( $this->errors ) ) {
							$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : site_url( 'wp-login.php?checkemail=confirm' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							wp_safe_redirect( $redirect_to );
							exit;
						}
					}
					if ( isset( $_REQUEST['error'] ) ) {
						if ( 'invalidkey' == $_REQUEST['error'] )
							$this->errors->add( 'invalidkey', __( 'Your password reset link appears to be invalid. Please request a new link below.', 'tml-classic' ) );
						elseif ( 'expiredkey' == $_REQUEST['error'] )
							$this->errors->add( 'expiredkey', __( 'Your password reset link has expired. Please request a new link below.', 'tml-classic' ) );
					}
					do_action( 'lost_password' );
					break;
				case 'resetpass':
				case 'rp':
					global $rp_login, $rp_key;
					$rp_cookie = 'wp-resetpass-' . COOKIEHASH;
					if ( isset( $_GET['key'] ) ) {
						$value = sprintf( '%s:%s', sanitize_user( wp_unslash( $_GET['login'] ) ), sanitize_text_field( wp_unslash( $_GET['key'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
						setcookie( $rp_cookie, $value, 0, '/', COOKIE_DOMAIN, is_ssl(), true );
						wp_safe_redirect( remove_query_arg( array( 'key', 'login' ) ) );
						exit;
					}
					if ( isset( $_COOKIE[ $rp_cookie ] ) && 0 < strpos( sanitize_text_field( wp_unslash( $_COOKIE[ $rp_cookie ] ) ), ':' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
						list( $rp_login, $rp_key ) = explode( ':', sanitize_text_field( wp_unslash( $_COOKIE[ $rp_cookie ] ) ), 2 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
						$user = check_password_reset_key( $rp_key, $rp_login );
						if ( isset( $_POST['pass1'] ) && !hash_equals( $rp_key, sanitize_text_field( wp_unslash( $_POST['rp_key'] ?? '' ) ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
							$user = false;
					} else {
						$user = false;
					}
					if ( !$user || is_wp_error( $user ) ) {
						setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, '/', COOKIE_DOMAIN, is_ssl(), true );
						if ( $user && $user->get_error_code() === 'expired_key' )
							wp_safe_redirect( self::get_page_link( 'lostpassword', 'error=expiredkey' ) );
						else
							wp_safe_redirect( self::get_page_link( 'lostpassword', 'error=invalidkey' ) );
						exit;
					}
					if ( isset( $_POST['pass1'] ) && ( !isset( $_POST['pass2'] ) || $_POST['pass1'] != $_POST['pass2'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
						$this->errors->add( 'password_reset_mismatch', __( 'The passwords do not match.', 'tml-classic' ) );
					do_action( 'validate_password_reset', $this->errors, $user );
					if ( !$this->errors->get_error_code() && isset( $_POST['pass1'] ) && !empty( $_POST['pass1'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
						reset_password( $user, sanitize_text_field( wp_unslash( $_POST['pass1'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
						setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, '/', COOKIE_DOMAIN, is_ssl(), true );
						wp_safe_redirect( self::get_page_link( 'login', 'resetpass=complete' ) );
						exit;
					}
					wp_enqueue_script( 'utils' );
					wp_enqueue_script( 'user-profile' );
					break;
				case 'register':
					if ( !get_option( 'users_can_register' ) ) {
						wp_safe_redirect( site_url( 'wp-login.php?registration=disabled' ) );
						exit;
					}
					$user_login = '';
					$user_email = '';
					if ( $http_post ) {
						if ( 'email' == $this->get_option( 'login_type' ) ) {
							$user_login = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
						} else {
							$user_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
						}
						$user_email   = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
						$this->errors = register_new_user( $user_login, $user_email );
						if ( !is_wp_error( $this->errors ) ) {
							$redirect_to = !empty( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : site_url( 'wp-login.php?checkemail=registered' );
							wp_safe_redirect( $redirect_to );
							exit;
						}
					}
					break;
				case 'activate':
					if ( !class_exists( 'TML_Classic_User_Moderation' ) )
						break;
					$login = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ) ) : '';
					$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
					if ( empty( $login ) || empty( $key ) ) {
						$this->errors->add( 'invalid_key', __( 'Invalid activation link.', 'tml-classic' ) );
						break;
					}
					$result = TML_Classic_User_Moderation::get_object()->activate_new_user( $login, $key );
					if ( is_wp_error( $result ) ) {
						$this->errors->add( $result->get_error_code(), $result->get_error_message() );
					} else {
						wp_safe_redirect( self::get_page_link( 'login', 'activated=1' ) );
						exit;
					}
					break;
				case 'confirmaction':
					if ( !isset( $_GET['request_id'] ) )
						wp_die( __( 'Invalid request.', 'tml-classic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$request_id = (int) $_GET['request_id'];
					if ( isset( $_GET['confirm_key'] ) ) {
						$key    = sanitize_text_field( wp_unslash( $_GET['confirm_key'] ) );
						$result = wp_validate_user_request_key( $request_id, $key );
					} else {
						$result = new WP_Error( 'invalid_key', __( 'Invalid key', 'tml-classic' ) );
					}
					if ( is_wp_error( $result ) )
						wp_die( $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					do_action( 'user_request_action_confirmed', $request_id );
					break;
				case 'login':
				default:
					$secure_cookie = '';
					$interim_login = isset( $_REQUEST['interim-login'] );
					if ( !empty( $_POST['log'] ) && !force_ssl_admin() ) {
						$user_name = sanitize_user( wp_unslash( $_POST['log'] ) );
						if ( $user = get_user_by( 'login', $user_name ) ) {
							if ( get_user_option( 'use_ssl', $user->ID ) ) {
								$secure_cookie = true;
								force_ssl_admin( true );
							}
						}
					}
					if ( !empty( $_REQUEST['redirect_to'] ) ) {
						$redirect_to = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						if ( $secure_cookie && false !== strpos( $redirect_to, 'wp-admin' ) )
							$redirect_to = preg_replace( '|^http://|', 'https://', $redirect_to );
					} else {
						$redirect_to = admin_url();
					}
					$reauth = empty( $_REQUEST['reauth'] ) ? false : true;
					if ( isset( $_POST['log'] ) || isset( $_GET['testcookie'] ) ) {
						$user        = wp_signon( array(), $secure_cookie );
						$redirect_to = apply_filters( 'login_redirect', $redirect_to, isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '', $user ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						if ( !is_wp_error( $user ) && empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
							$redirect_to = add_query_arg( array(
								'testcookie' => 1,
								'redirect_to' => $redirect_to,
							) );
							wp_safe_redirect( $redirect_to );
							exit;
						}
						if ( empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
							if ( headers_sent() ) {
								$user = new WP_Error( 'test_cookie', sprintf(
									/* translators: 1: Browser cookie documentation URL, 2: Support forums URL */
									__( '<strong>ERROR</strong>: Cookies are blocked due to unexpected output. For help, please see <a href="%1$s">this documentation</a> or try the <a href="%2$s">support forums</a>.', 'tml-classic' ),
									__( 'https://codex.wordpress.org/Cookies', 'tml-classic' ), __( 'https://wordpress.org/support/', 'tml-classic' )
								) );
							} elseif ( isset( $_GET['testcookie'] ) ) {
								$user = new WP_Error( 'test_cookie', sprintf(
									/* translators: 1: Browser cookie documentation URL */
									__( '<strong>ERROR</strong>: Cookies are blocked or not supported by your browser. You must <a href="%s">enable cookies</a> to use WordPress.', 'tml-classic' ),
									__( 'https://codex.wordpress.org/Cookies', 'tml-classic' )
								) );
							}
						} else {
							$user = wp_get_current_user();
						}
						if ( !is_wp_error( $user ) && !$reauth ) {
							if ( empty( $redirect_to ) || 'wp-admin/' == $redirect_to || admin_url() == $redirect_to ) {
								if ( is_multisite() && !get_active_blog_for_user( $user->ID ) && !is_super_admin( $user->ID ) )
									$redirect_to = user_admin_url();
								elseif ( is_multisite() && !$user->has_cap( 'read' ) )
									$redirect_to = get_dashboard_url( $user->ID );
								elseif ( !$user->has_cap( 'edit_posts' ) )
									$redirect_to = $user->has_cap( 'read' ) ? admin_url( 'profile.php' ) : home_url();
								wp_safe_redirect( $redirect_to );
								exit;
							}
							wp_safe_redirect( $redirect_to );
							exit;
						}
						$this->errors = $user;
					}
					if ( !empty( $_GET['loggedout'] ) || $reauth )
						$this->errors = new WP_Error();
					if ( $interim_login ) {
						if ( !$this->errors->get_error_code() )
							$this->errors->add( 'expired', __( 'Your session has expired. Please log in to continue where you left off.', 'tml-classic' ), 'message' );
					} else {
						if ( isset( $_GET['loggedout'] ) && true == $_GET['loggedout'] )
							$this->errors->add( 'loggedout', __( 'You are now logged out.', 'tml-classic' ), 'message' );
						elseif ( isset( $_GET['registration'] ) && 'disabled' == $_GET['registration'] )
							$this->errors->add( 'registerdisabled', __( 'User registration is currently not allowed.', 'tml-classic' ) );
						elseif ( isset( $_GET['checkemail'] ) && 'confirm' == $_GET['checkemail'] )
							$this->errors->add( 'confirm', __( 'Check your email for the confirmation link.', 'tml-classic' ), 'message' );
						elseif ( isset( $_GET['checkemail'] ) && 'newpass' == $_GET['checkemail'] )
							$this->errors->add( 'newpass', __( 'Check your email for your new password.', 'tml-classic' ), 'message' );
						elseif ( isset( $_GET['resetpass'] ) && 'complete' == $_GET['resetpass'] )
							$this->errors->add( 'password_reset', __( 'Your password has been reset.', 'tml-classic' ), 'message' );
						elseif ( isset( $_GET['checkemail'] ) && 'registered' == $_GET['checkemail'] )
							$this->errors->add( 'registered', __( 'Registration complete. Please check your email.', 'tml-classic' ), 'message' );
						elseif ( isset( $_GET['activated'] ) && '1' == $_GET['activated'] )
							$this->errors->add( 'activated', __( 'Your account has been activated. You may now log in.', 'tml-classic' ), 'message' );
						elseif ( strpos( $redirect_to, 'about.php?updated' ) )
							$this->errors->add( 'updated', __( '<strong>You have successfully updated WordPress!</strong> Please log back in to see what&#8217;s new.', 'tml-classic' ), 'message' );
					}
					if ( $reauth )
						wp_clear_auth_cookie();
					break;
			}
		}
	}

	public function wp_enqueue_scripts() {
		if ( self::is_tml_page() )
			do_action( 'login_enqueue_scripts' );
	}

	public function wp_head() {
		if ( self::is_tml_page() ) {
			remove_action( 'login_head', 'wp_print_head_scripts', 9 );
			do_action( 'login_head' );
		}
	}

	public function wp_footer() {
		if ( self::is_tml_page() ) {
			remove_action( 'login_footer', 'wp_print_footer_scripts', 20 );
			do_action( 'login_footer' );
		}
	}

	public function wp_print_footer_scripts() {
		if ( !self::is_tml_page() )
			return;
		switch ( $this->request_action ) {
			case 'lostpassword':
			case 'retrievepassword':
			case 'register':
				?>
				<script>try{document.getElementById('user_login').focus();}catch(e){}if(typeof wpOnload=='function')wpOnload()</script>
				<?php
				break;
			case 'resetpass':
			case 'rp':
				?>
				<script>try{document.getElementById('pass1').focus();}catch(e){}if(typeof wpOnload=='function')wpOnload()</script>
				<?php
				break;
			case 'login':
				$user_login = '';
				if ( isset( $_POST['log'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$user_login = ( 'incorrect_password' == $this->errors->get_error_code() || 'empty_password' == $this->errors->get_error_code() ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
				?>
				<script>function wp_attempt_focus(){setTimeout(function(){try{<?php if ( $user_login ) { ?>d=document.getElementById('user_pass');d.value='';<?php } else { ?>d=document.getElementById('user_login');<?php if ( 'invalid_username' == $this->errors->get_error_code() ) { ?>if(d.value!='')d.value='';<?php } ?><?php } ?>d.focus();d.select();}catch(e){}},200);}wp_attempt_focus();if(typeof wpOnload=='function')wpOnload()</script>
				<?php
				break;
		}
	}

	public function site_url( $url, $path, $orig_scheme ) {
		global $pagenow;
		if ( 'wp-login.php' == $pagenow )
			return $url;
		if ( false === strpos( $url, 'wp-login.php' ) )
			return $url;
		parse_str( wp_parse_url( $url, PHP_URL_QUERY ) ?? '', $query );
		if ( isset( $query['interim-login'] ) )
			return $url;
		$action = isset( $query['action'] ) ? $query['action'] : 'login';
		$url    = self::get_page_link( $action, $query );
		if ( 'https' == strtolower( $orig_scheme ) )
			$url = preg_replace( '|^http://|', 'https://', $url );
		return $url;
	}

	public function logout_url( $logout_url, $redirect ) {
		$logout_url = self::get_page_link( 'logout' );
		if ( $redirect )
			$logout_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $logout_url );
		return $logout_url;
	}

	public function single_post_title( $title ) {
		if ( self::is_tml_page( 'login' ) && is_user_logged_in() )
			$title = $this->get_instance()->get_title( 'login' );
		return $title;
	}

	public function the_title( $title, $post_id = 0 ) {
		if ( is_admin() )
			return $title;
		if ( self::is_tml_page( 'login', $post_id ) ) {
			if ( in_the_loop() ) {
				if ( is_user_logged_in() ) {
					$title = $this->get_instance()->get_title( 'login' );
				} elseif ( 'login' != $this->request_action ) {
					$title = $this->get_instance()->get_title( $this->request_action );
				}
			}
		}
		return $title;
	}

	public function document_title_parts( $parts ) {
		if ( self::is_tml_page( 'login' ) ) {
			if ( is_user_logged_in() ) {
				$parts['title'] = $this->get_instance()->get_title( 'login' );
			} elseif ( 'login' != $this->request_action ) {
				$parts['title'] = $this->get_instance()->get_title( $this->request_action );
			}
		}
		return $parts;
	}

	public function wp_setup_nav_menu_item( $menu_item ) {
		if ( is_admin() )
			return $menu_item;
		if ( 'page' != $menu_item->object )
			return $menu_item;
		if ( is_user_logged_in() ) {
			if ( self::is_tml_page( array( 'login', 'register', 'lostpassword' ), $menu_item->object_id ) )
				$menu_item->_invalid = true;
		} else {
			if ( self::is_tml_page( 'logout', $menu_item->object_id ) )
				$menu_item->_invalid = true;
		}
		return $menu_item;
	}

	public function wp_list_pages_excludes( $exclude ) {
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'any',
			'meta_key'       => '_tml_action', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'posts_per_page' => -1,
		) );
		return array_merge( $exclude, wp_list_pluck( $pages, 'ID' ) );
	}

	public function page_link( $link, $post_id ) {
		if ( self::is_tml_page( 'logout', $post_id ) )
			$link = add_query_arg( '_wpnonce', wp_create_nonce( 'log-out' ), $link );
		return $link;
	}

	public function authenticate( $user, $username, $password ) {
		if ( 'email' == $this->get_option( 'login_type' ) && null == $user )
			return new WP_Error( 'invalid_email', __( '<strong>ERROR</strong>: Invalid email address.', 'tml-classic' ) );
		return $user;
	}

	public function shortcode( $atts = '' ) {
		static $did_main_instance = false;
		$atts = wp_parse_args( $atts );
		if ( self::is_tml_page() && ( in_the_loop() || is_singular() ) && is_main_query() && !$did_main_instance ) {
			$instance = $this->get_instance();
			if ( !empty( $this->request_instance ) )
				$instance->set_active( false );
			if ( 'login' != $this->request_page )
				$atts['default_action'] = $this->request_page;
			if ( !isset( $atts['show_title'] ) )
				$atts['show_title'] = false;
			foreach ( $atts as $option => $value ) {
				if ( 'instance' == $option )
					continue;
				$instance->set_option( $option, $value );
			}
			$did_main_instance = true;
		} else {
			$instance = $this->load_instance( $atts );
		}
		$this->current_instance = $instance->get_option( 'instance' );
		return $instance->display();
	}

	public static function is_tml_page( $action = '', $page = '' ) {
		if ( !$page = get_post( $page ) )
			return false;
		if ( 'page' != $page->post_type )
			return false;
		if ( !$page_action = self::get_page_action( $page->ID ) )
			return false;
		if ( empty( $action ) )
			return true;
		if ( in_array( $page_action, (array) $action ) )
			return true;
		return false;
	}

	public static function get_page_link( $action, $query = '' ) {
		global $wp_rewrite;
		if ( $page_id = self::get_page_id( $action ) ) {
			if ( $wp_rewrite instanceof WP_Rewrite ) {
				$link = get_permalink( $page_id );
			} else {
				$link = home_url( '?page_id=' . $page_id );
			}
		} elseif ( $page_id = self::get_page_id( 'login' ) ) {
			if ( $wp_rewrite instanceof WP_Rewrite ) {
				$link = get_permalink( $page_id );
			} else {
				$link = home_url( '?page_id=' . $page_id );
			}
			$link = add_query_arg( 'action', $action, $link );
		} else {
			remove_filter( 'site_url', array( self::get_object(), 'site_url' ), 10, 3 );
			$link = site_url( "wp-login.php?action=$action" );
		}
		if ( !empty( $query ) ) {
			$args = wp_parse_args( $query );
			if ( isset( $args['action'] ) && $action == $args['action'] )
				unset( $args['action'] );
			$link = add_query_arg( array_map( 'rawurlencode', $args ), $link );
		}
		$link = set_url_scheme( $link, 'login' );
		return apply_filters( 'tml_page_link', $link, $action, $query );
	}

	public static function get_page_id( $action ) {
		global $wpdb;
		if ( 'rp' == $action )
			$action = 'resetpass';
		elseif ( 'retrievepassword' == $action )
			$action = 'lostpassword';
		$page_id = wp_cache_get( $action, 'tml_page_ids', false, $found );
		if ( !$found ) {
			$page_id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				"SELECT p.ID FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pmeta ON p.ID = pmeta.post_id WHERE p.post_type = 'page' AND pmeta.meta_key = '_tml_action' AND pmeta.meta_value = %s",
				$action
			) );
			if ( !$page_id )
				return null;
			wp_cache_add( $action, $page_id, 'tml_page_ids' );
		}
		return apply_filters( 'tml_page_id', $page_id, $action );
	}

	public static function get_page_action( $page ) {
		if ( !$page = get_post( $page ) )
			return false;
		return get_post_meta( $page->ID, '_tml_action', true );
	}

	public static function get_stylesheet( $file = 'tml-classic.css' ) {
		if ( file_exists( get_stylesheet_directory() . '/' . $file ) )
			return get_stylesheet_directory_uri() . '/' . $file;
		if ( file_exists( get_template_directory() . '/' . $file ) )
			return get_template_directory_uri() . '/' . $file;
		return plugins_url( $file, TML_CLASSIC_PATH . '/tml-classic.php' );
	}

	public function get_active_instance() {
		return $this->get_instance( (int) $this->request_instance );
	}

	public function get_current_instance() {
		return $this->get_instance( (int) $this->current_instance );
	}

	public function get_instance( $id = 0 ) {
		if ( isset( $this->loaded_instances[ $id ] ) )
			return $this->loaded_instances[ $id ];
	}

	public function set_instance( $object ) {
		$this->loaded_instances[] = $object;
	}

	public function load_instance( $args = '' ) {
		$instance = new TML_Classic_Template( $args );
		$instance->set_option( 'instance', count( $this->loaded_instances ) );
		if ( $instance->get_option( 'instance' ) === $this->request_instance ) {
			$instance->set_active();
			$instance->set_option( 'default_action', $this->request_action ? $this->request_action : 'login' );
		}
		$this->loaded_instances[] = $instance;
		return $instance;
	}

	public static function retrieve_password() {
		global $wpdb;
		$errors = new WP_Error();
		if ( empty( $_POST['user_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$errors->add( 'empty_username', __( '<strong>ERROR</strong>: Enter a username or email address.', 'tml-classic' ) );
		} elseif ( strpos( wp_unslash( $_POST['user_login'] ), '@' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
			$user_data = get_user_by( 'email', trim( sanitize_email( wp_unslash( $_POST['user_login'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
			if ( empty( $user_data ) )
				$errors->add( 'invalid_email', __( '<strong>ERROR</strong>: There is no user registered with that email address.', 'tml-classic' ) );
		} else {
			$login     = trim( sanitize_user( wp_unslash( $_POST['user_login'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
			$user_data = get_user_by( 'login', $login );
		}
		do_action( 'lostpassword_post', $errors );
		if ( $errors->get_error_code() )
			return $errors;
		if ( !$user_data ) {
			$errors->add( 'invalidcombo', __( '<strong>ERROR</strong>: Invalid username or email.', 'tml-classic' ) );
			return $errors;
		}
		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;
		$key        = get_password_reset_key( $user_data );
		if ( is_wp_error( $key ) )
			return $key;
		$message  = __( 'Someone requested that the password be reset for the following account:', 'tml-classic' ) . "\r\n\r\n";
		$message .= network_home_url( '/' ) . "\r\n\r\n";
		/* translators: %s: Username */
		$message .= sprintf( __( 'Username: %s', 'tml-classic' ), $user_login ) . "\r\n\r\n";
		$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.', 'tml-classic' ) . "\r\n\r\n";
		$message .= __( 'To reset your password, visit the following address:', 'tml-classic' ) . "\r\n\r\n";
		$message .= '<' . self::get_page_link( 'resetpass', "key=$key&login=" . rawurlencode( $user_login ) ) . ">\r\n";
		if ( is_multisite() ) {
			$blogname = $GLOBALS['current_site']->site_name;
		} else {
			$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}
		/* translators: %s: Site name */
		$title   = apply_filters( 'retrieve_password_title', sprintf( __( '[%s] Password Reset', 'tml-classic' ), $blogname ), $user_login, $user_data );
		$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );
		if ( $message && !wp_mail( $user_email, $title, $message ) )
			wp_die( __( 'The email could not be sent.', 'tml-classic' ) . "<br>\n" . __( 'Possible reason: your host may have disabled the mail() function...', 'tml-classic' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return true;
	}
}

endif;