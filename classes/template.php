<?php
/*
 * @package TML_Classic
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Template' ) ) :
class TML_Classic_Template extends TML_Classic_Abstract {
	private $is_active = false;

	public function __construct( $options = '' ) {
		$options = shortcode_atts( self::default_options(), wp_parse_args( $options ) );
		$this->set_options( $options );
	}

	public static function default_options() {
		return array(
			'instance'              => 0,
			'default_action'        => 'login',
			'login_template'        => '',
			'register_template'     => '',
			'lostpassword_template' => '',
			'resetpass_template'    => '',
			'user_template'         => '',
			'show_title'            => true,
			'show_log_link'         => true,
			'show_reg_link'         => true,
			'show_pass_link'        => true,
			'logged_in_widget'      => true,
			'logged_out_widget'     => true,
			'show_gravatar'         => true,
			'gravatar_size'         => 50,
			'before_widget'         => '',
			'after_widget'          => '',
			'before_title'          => '',
			'after_title'           => '',
		);
	}

	public function display( $action = '' ) {
		if ( empty( $action ) )
			$action = $this->get_option( 'default_action' );
		ob_start();
		echo $this->get_option( 'before_widget' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $this->get_option( 'show_title' ) )
			echo $this->get_option( 'before_title' ) . $this->get_title( $action ) . $this->get_option( 'after_title' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( has_action( 'tml_display_' . $action ) ) {
			do_action_ref_array( 'tml_display_' . $action, array( &$this ) );
		} else {
			$template = array();
			if ( is_user_logged_in() && 'login' == $action ) {
				if ( $this->get_option( 'user_template' ) )
					$template[] = $this->get_option( 'user_template' );
				$template[] = 'user-panel.php';
			} else {
				switch ( $action ) {
					case 'lostpassword':
					case 'retrievepassword':
						if ( $this->get_option( 'lostpassword_template' ) )
							$template[] = $this->get_option( 'lostpassword_template' );
						$template[] = 'lost-form.php';
						break;
					case 'resetpass':
					case 'rp':
						if ( $this->get_option( 'resetpass_template' ) )
							$template[] = $this->get_option( 'resetpass_template' );
						$template[] = 'reset-form.php';
						break;
					case 'register':
						if ( $this->get_option( 'register_template' ) )
							$template[] = $this->get_option( 'register_template' );
						$template[] = 'register-form.php';
						break;
					case 'confirmaction':
						echo '<div class="tml">' . wp_kses_post( _wp_privacy_account_request_confirmed_message( isset( $_GET['request_id'] ) ? (int) $_GET['request_id'] : 0 ) ) . '</div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						break;
					case 'login':
					default:
						if ( $this->get_option( 'login_template' ) )
							$template[] = $this->get_option( 'login_template' );
						$template[] = 'login-form.php';
				}
			}
			$this->get_template( $template );
		}
		echo $this->get_option( 'after_widget' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$output = ob_get_clean();
		return apply_filters_ref_array( 'tml_display', array( $output, $action, $this ) );
	}

	public function get_title( $action = '' ) {
		if ( empty( $action ) )
			$action = $this->get_option( 'default_action' );
		if ( is_admin() )
			return;
		if ( is_user_logged_in() && 'login' == $action && $action == $this->get_option( 'default_action' ) ) {
			/* translators: %s: Display name */
			$title = sprintf( __( 'Welcome, %s', 'tml-classic' ), wp_get_current_user()->display_name );
		} else {
			if ( $page_id = TML_Classic::get_page_id( $action ) ) {
				$title = get_post_field( 'post_title', $page_id );
			} else {
				switch ( $action ) {
					case 'register':
						$title = __( 'Register', 'tml-classic' );
						break;
					case 'lostpassword':
					case 'retrievepassword':
					case 'resetpass':
					case 'rp':
						$title = __( 'Lost Password', 'tml-classic' );
						break;
					case 'confirmaction':
						$title = __( 'Your Data Request', 'tml-classic' );
						break;
					case 'login':
					default:
						$title = __( 'Log In', 'tml-classic' );
				}
			}
		}
		return apply_filters( 'tml_title', $title, $action );
	}

	public function the_title( $action = '' ) {
		echo $this->get_title( $action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function get_errors() {
		global $error;
		$tml      = TML_Classic::get_object();
		$wp_error = $tml->errors;
		if ( empty( $wp_error ) )
			$wp_error = new WP_Error();
		if ( !empty( $error ) ) {
			$wp_error->add( 'error', $error );
			unset( $error );
		}
		$output = '';
		if ( $this->is_active() ) {
			if ( $wp_error->get_error_code() ) {
				$errors   = '';
				$messages = '';
				foreach ( $wp_error->get_error_codes() as $code ) {
					$severity = $wp_error->get_error_data( $code );
					foreach ( $wp_error->get_error_messages( $code ) as $error_msg ) {
						if ( 'message' == $severity )
							$messages .= '    ' . $error_msg . "<br>\n";
						else
							$errors .= '    ' . $error_msg . "<br>\n";
					}
				}
				if ( !empty( $errors ) )
					$output .= '<p class="error">' . apply_filters( 'login_errors', $errors ) . "</p>\n";
				if ( !empty( $messages ) )
					$output .= '<p class="message">' . apply_filters( 'login_messages', $messages ) . "</p>\n";
			}
		}
		return $output;
	}

	public function the_errors() {
		echo $this->get_errors(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function get_action_url( $action = '', $scheme = 'login' ) {
		$instance = $this->get_option( 'instance' );
		if ( $action == $this->get_option( 'default_action' ) ) {
			$args = array();
			if ( $instance )
				$args['instance'] = $instance;
			$url = TML_Classic_Common::get_current_url( $args );
		} else {
			$url = TML_Classic::get_page_link( $action );
		}
		$url = set_url_scheme( $url, $scheme );
		return apply_filters( 'tml_action_url', $url, $action, $scheme, $instance );
	}

	public function the_action_url( $action = 'login', $scheme = 'login' ) {
		echo esc_url( $this->get_action_url( $action, $scheme ) );
	}

	public function get_action_links( $args = '' ) {
		$args = wp_parse_args( $args, array(
			'login'        => true,
			'register'     => true,
			'lostpassword' => true,
		) );
		$action_links = array();
		if ( $args['login'] && $this->get_option( 'show_log_link' ) ) {
			$action_links[] = array(
				'title' => $this->get_title( 'login' ),
				'url'   => $this->get_action_url( 'login' ),
			);
		}
		if ( $args['register'] && $this->get_option( 'show_reg_link' ) && get_option( 'users_can_register' ) ) {
			$action_links[] = array(
				'title' => $this->get_title( 'register' ),
				'url'   => $this->get_action_url( 'register' ),
			);
		}
		if ( $args['lostpassword'] && $this->get_option( 'show_pass_link' ) ) {
			$action_links[] = array(
				'title' => $this->get_title( 'lostpassword' ),
				'url'   => $this->get_action_url( 'lostpassword' ),
			);
		}
		return apply_filters( 'tml_action_links', $action_links, $args );
	}

	public function the_action_links( $args = '' ) {
		if ( $action_links = $this->get_action_links( $args ) ) {
			echo '<ul class="tml-action-links">' . "\n";
			foreach ( (array) $action_links as $link ) {
				echo '<li><a href="' . esc_url( $link['url'] ) . '" rel="nofollow">' . esc_html( $link['title'] ) . '</a></li>' . "\n";
			}
			echo '</ul>' . "\n";
		}
	}

	public static function get_user_links() {
		$user_links = array(
			array(
				'title' => __( 'Dashboard', 'tml-classic' ),
				'url'   => admin_url(),
			),
			array(
				'title' => __( 'Settings', 'tml-classic' ),
				'url'   => admin_url( 'profile.php' ),
			),
		);
		return apply_filters( 'tml_user_links', $user_links );
	}

	public function the_user_links() {
		echo '<ul class="tml-user-links">';
		foreach ( (array) self::get_user_links() as $link ) {
			echo '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['title'] ) . '</a></li>' . "\n";
		}
		echo '<li><a href="' . esc_url( wp_logout_url() ) . '">' . esc_html( $this->get_title( 'logout' ) ) . '</a></li>' . "\n";
		echo '</ul>';
	}

	public function the_user_avatar( $size = '' ) {
		if ( empty( $size ) )
			$size = $this->get_option( 'gravatar_size', 50 );
		echo get_avatar( wp_get_current_user()->ID, $size );
	}

	public static function get_action_template_message( $action = '' ) {
		switch ( $action ) {
			case 'register':
				$message = __( 'Register For This Site', 'tml-classic' );
				break;
			case 'lostpassword':
				$message = __( 'Please enter your username or email address. You will receive a link to create a new password via email.', 'tml-classic' );
				break;
			case 'resetpass':
				$message = __( 'Enter your new password below.', 'tml-classic' );
				break;
			default:
				$message = '';
		}
		$message = apply_filters( 'login_message', $message );
		return apply_filters( 'tml_action_template_message', $message, $action );
	}

	public function the_action_template_message( $action = 'login', $before_message = '<p class="message">', $after_message = '</p>' ) {
		if ( $message = self::get_action_template_message( $action ) )
			echo $before_message . $message . $after_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function get_template( $template_names, $load = true, $args = array() ) {
		$tml          = TML_Classic::get_object();
		$template     = $this;
		$current_user = wp_get_current_user();
		extract( apply_filters_ref_array( 'tml_template_args', array( $args, $this ) ) );
		$template_paths = apply_filters( 'tml_template_paths', array(
			get_stylesheet_directory() . '/tml-classic',
			get_stylesheet_directory(),
			get_template_directory() . '/tml-classic',
			get_template_directory(),
			TML_CLASSIC_PATH . '/templates',
			TML_CLASSIC_PATH,
		) );
		$located = '';
		foreach ( (array) $template_names as $template_name ) {
			if ( !$template_name )
				continue;
			if ( preg_match( '/\/|\\\\/', $template_name ) )
				continue;
			foreach ( $template_paths as $template_path ) {
				if ( file_exists( $template_path . '/' . $template_name ) ) {
					$located = $template_path . '/' . $template_name;
					break 2;
				}
			}
		}
		$located = apply_filters_ref_array( 'tml_template', array( $located, $template_names, $this ) );
		if ( $load && '' != $located )
			include $located;
		return $located;
	}

	public function get_redirect_url( $action = '' ) {
		if ( empty( $action ) )
			$action = $this->get_option( 'default_action' );
		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		switch ( $action ) {
			case 'lostpassword':
			case 'retrievepassword':
				$url = apply_filters( 'lostpassword_redirect', !empty( $redirect_to ) ? $redirect_to : TML_Classic::get_page_link( 'login', 'checkemail=confirm' ) );
				break;
			case 'register':
				$url = apply_filters( 'registration_redirect', !empty( $redirect_to ) ? $redirect_to : TML_Classic::get_page_link( 'login', 'checkemail=registered' ) );
				break;
			case 'login':
			default:
				$url = apply_filters( 'login_redirect', !empty( $redirect_to ) ? $redirect_to : admin_url(), $redirect_to, null );
		}
		return apply_filters( 'tml_redirect_url', $url, $action );
	}

	public function the_redirect_url( $action = '' ) {
		echo esc_attr( $this->get_redirect_url( $action ) );
	}

	public function the_instance() {
		if ( $this->get_option( 'instance' ) )
			echo esc_attr( $this->get_option( 'instance' ) );
	}

	public function get_posted_value( $value ) {
		if ( $this->is_active() && isset( $_REQUEST[ $value ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( $_REQUEST[ $value ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return false;
	}

	public function the_posted_value( $value ) {
		echo esc_attr( $this->get_posted_value( $value ) );
	}

	public function is_active() {
		return $this->is_active;
	}

	public function set_active( $active = true ) {
		$this->is_active = $active;
	}
}

endif;