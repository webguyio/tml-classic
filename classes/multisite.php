<?php
/*
 * @package TML_Classic
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Multisite' ) ) :

class TML_Classic_Multisite extends TML_Classic_Abstract {
	public static function get_object( $class = null ) {
		return parent::get_object( __CLASS__ );
	}

	public function load() {
		$tml = TML_Classic::get_object();
		add_action( 'tml_request_register', array( $this, 'tml_request_register' ) );
		add_action( 'tml_request_activate', array( $this, 'tml_request_activate' ) );
		add_action( 'tml_display_register', array( $this, 'tml_display_register' ) );
		add_action( 'tml_display_activate', array( $this, 'tml_display_activate' ) );
		add_filter( 'tml_title',            array( $this, 'tml_title'            ), 10, 2 );
		add_action( 'switch_blog',          array( $tml,  'load_options'         ) );
		add_action( 'wpmu_new_blog',        array( $this, 'wpmu_new_blog'        ), 10, 2 );
		add_filter( 'site_url',             array( $this, 'site_url'             ),  9, 3 );
		add_filter( 'home_url',             array( $this, 'site_url'             ),  9, 3 );
		add_filter( 'network_site_url',     array( $this, 'network_site_url'     ), 10, 3 );
		add_filter( 'network_home_url',     array( $this, 'network_site_url'     ), 10, 3 );
		add_filter( 'clean_url',            array( $this, 'clean_url'            ), 10, 3 );
	}

	public function tml_request_register( &$tml ) {
		global $current_site;
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_action( 'wp_head', array( $this, 'signup_header' ) );
		if ( is_array( get_site_option( 'illegal_names' ) ) && isset( $_GET['new'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['new'] ) ), get_site_option( 'illegal_names' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( network_home_url() );
			exit;
		}
		if ( !is_main_site() ) {
			wp_safe_redirect( network_home_url( 'wp-signup.php' ) );
			exit;
		}
	}

	public function tml_display_register( &$template ) {
		global $wpdb, $blogname, $blog_title, $domain, $path, $active_signup;
		$tml = TML_Classic::get_object();
		do_action( 'before_signup_form' );
		echo '<div class="login mu_register" id="tml-classic' . esc_attr( $template->get_option( 'instance' ) ) . '">';
		$active_signup = get_site_option( 'registration' );
		if ( !$active_signup )
			$active_signup = 'all';
		$active_signup = apply_filters( 'wpmu_active_signup', $active_signup );
		$i18n_signup = array(
			'all'  => _x( 'all',  'Multisite active signup type', 'tml-classic' ),
			'none' => _x( 'none', 'Multisite active signup type', 'tml-classic' ),
			'blog' => _x( 'blog', 'Multisite active signup type', 'tml-classic' ),
			'user' => _x( 'user', 'Multisite active signup type', 'tml-classic' ),
		);
		if ( is_super_admin() )
			/* translators: 1: Registration type, 2: Options page URL */
			echo '<p class="message">' . sprintf( __( 'Greetings Site Administrator!You are currently allowing &#8220;%1$s&#8221; registrations. To change or disable registration go to your <a href="%2$s">Options page</a>.', 'tml-classic' ), esc_html( $i18n_signup[ $active_signup ] ), esc_url( network_admin_url( 'ms-options.php' ) ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$newblogname  = isset( $_GET['new'] ) ? strtolower( preg_replace( '/^-|-$|[^-a-zA-Z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['new'] ) ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_user = wp_get_current_user();
		if ( 'none' == $active_signup ) {
			esc_html_e( 'Registration has been disabled.', 'tml-classic' );
		} elseif ( 'blog' == $active_signup && !is_user_logged_in() ) {
			/* translators: %s: Login URL */
			printf( __( 'You must first <a href="%s">log in</a>, and then you can create a new site.', 'tml-classic' ), esc_url( wp_login_url( TML_Classic_Common::get_current_url() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			$stage = isset( $_POST['stage'] ) ? sanitize_text_field( wp_unslash( $_POST['stage'] ) ) : 'default'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			switch ( $stage ) {
				case 'validate-user-signup':
					if ( 'all' == $active_signup || ( 'blog' == $_POST['signup_for'] && 'blog' == $active_signup ) || ( 'user' == $_POST['signup_for'] && 'user' == $active_signup ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
						$result    = wpmu_validate_user_signup( $_POST['user_name'], $_POST['user_email'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
						extract( $result );
						$tml->errors = $errors;
						if ( $errors->get_error_code() ) {
							$this->signup_user( $user_name, $user_email );
							break;
						}
						if ( 'blog' == $_POST['signup_for'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
							$this->signup_blog( $user_name, $user_email );
							break;
						}
						wpmu_signup_user( $user_name, $user_email, apply_filters( 'add_signup_meta', array() ) );
						?>
						<h2><?php
						/* translators: %s: Username */
						printf( __( '%s is your new username', 'tml-classic' ), esc_html( $user_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
						</h2>
						<p><?php esc_html_e( 'But, before you can start using your new username, <strong>you must activate it</strong>.', 'tml-classic' ); ?></p>
						<p><?php
						/* translators: %1$s: Email address */
						printf( __( 'Check your inbox at <strong>%1$s</strong> and click the link given.', 'tml-classic' ), esc_html( $user_email ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?></p>
						<p><?php esc_html_e( 'If you do not activate your username within two days, you will have to sign up again.', 'tml-classic' ); ?></p>
						<?php
						do_action( 'signup_finished' );
					} else {
						esc_html_e( 'User registration has been disabled.', 'tml-classic' );
					}
					break;
				case 'validate-blog-signup':
					if ( 'all' == $active_signup || 'blog' == $active_signup ) {
						$result      = wpmu_validate_user_signup( $_POST['user_name'], $_POST['user_email'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
						extract( $result );
						$tml->errors = $errors;
						if ( $errors->get_error_code() ) {
							$this->signup_user( $user_name, $user_email );
							break;
						}
						$result      = wpmu_validate_blog_signup( $_POST['blogname'], $_POST['blog_title'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
						extract( $result );
						$tml->errors = $errors;
						if ( $errors->get_error_code() ) {
							$this->signup_blog( $user_name, $user_email, $blogname, $blog_title );
							break;
						}
						$public = isset( $_POST['blog_public'] ) ? (int) $_POST['blog_public'] : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
						$meta   = apply_filters( 'add_signup_meta', array( 'lang_id' => 1, 'public' => $public ) );
						wpmu_signup_blog( $domain, $path, $blog_title, $user_name, $user_email, $meta );
						?>
						<h2><?php
						/* translators: %s: Site link (HTML anchor) */
						printf( __( 'Congratulations! Your new site, %s, is almost ready.', 'tml-classic' ), '<a href="' . esc_url( 'http://' . $domain . $path ) . '">' . esc_html( $blog_title ) . '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?></h2>
						<p><?php esc_html_e( 'But, before you can start using your site, <strong>you must activate it</strong>.', 'tml-classic' ); ?></p>
						<p><?php
						/* translators: %s: Email address */
						printf( __( 'Check your inbox at <strong>%s</strong> and click the link given.', 'tml-classic' ), esc_html( $user_email ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?></p>
						<p><?php esc_html_e( 'If you do not activate your site within two days, you will have to sign up again.', 'tml-classic' ); ?></p>
						<h2><?php esc_html_e( 'Still waiting for your email?', 'tml-classic' ); ?></h2>
						<p><?php esc_html_e( 'If you haven&#8217;t received your email yet, there are a number of things you can do:', 'tml-classic' ); ?>
						<ul id="noemail-tips">
							<li><p><strong><?php esc_html_e( 'Wait a little longer. Sometimes delivery of email can be delayed by processes outside of our control.', 'tml-classic' ); ?></strong></p></li>
							<li><p><?php esc_html_e( 'Check the junk or spam folder of your email client. Sometime emails wind up there by mistake.', 'tml-classic' ); ?></p></li>
							<li><?php
							/* translators: %s: Email address */
							printf( __( 'Have you entered your email correctly? You have entered %s, if it&#8217;s incorrect, you will not receive your email.', 'tml-classic' ), esc_html( $user_email ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?></li>
						</ul>
						</p>
						<?php
						do_action( 'signup_finished' );
					} else {
						esc_html_e( 'Site registration has been disabled.', 'tml-classic' );
					}
					break;
				case 'gimmeanotherblog':
					$current_user = wp_get_current_user();
					if ( !is_user_logged_in() )
						die();
					$result      = wpmu_validate_blog_signup( $_POST['blogname'], $_POST['blog_title'], $current_user ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
					extract( $result );
					$tml->errors = $errors;
					if ( $errors->get_error_code() ) {
						$this->signup_another_blog( $blogname, $blog_title );
						break;
					}
					$public = isset( $_POST['blog_public'] ) ? (int) $_POST['blog_public'] : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$meta   = apply_filters( 'add_signup_meta', array( 'lang_id' => 1, 'public' => $public ) );
					wpmu_create_blog( $domain, $path, $blog_title, $current_user->ID, $meta, $wpdb->siteid );
					?>
					<h2><?php
					/* translators: %s: Site link (HTML anchor) */
					printf( __( 'The site %s is yours.', 'tml-classic' ), '<a href="' . esc_url( 'http://' . $domain . $path ) . '">' . esc_html( $blog_title ) . '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?></h2>
					<p><?php
					/* translators: 1: Site domain+path, 2: Site domain+path, 3: Login URL, 4: Username */
					printf( __( '<a href="http://%1$s">http://%2$s</a> is your new site. <a href="%3$s">Log in</a> as &#8220;%4$s&#8221; using your existing password.', 'tml-classic' ), esc_attr( $domain . $path ), esc_html( $domain . $path ), esc_url( 'http://' . $domain . $path . 'wp-login.php' ), esc_html( $current_user->user_login ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?></p>
					<?php
					do_action( 'signup_finished' );
					break;
				case 'default':
				default:
					$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					do_action( 'preprocess_signup_form' );
					if ( is_user_logged_in() && ( 'all' == $active_signup || 'blog' == $active_signup ) )
						$this->signup_another_blog( $newblogname );
					elseif ( !is_user_logged_in() && ( 'all' == $active_signup || 'user' == $active_signup ) )
						$this->signup_user( $newblogname, $user_email );
					elseif ( !is_user_logged_in() && 'blog' == $active_signup )
						esc_html_e( 'Sorry, new registrations are not allowed at this time.', 'tml-classic' );
					else
						esc_html_e( 'You are logged in already. No need to register again!', 'tml-classic' );
						if ( $newblogname ) {
							$newblog = get_blogaddress_by_name( $newblogname );
							if ( 'blog' == $active_signup || 'all' == $active_signup ) {
								/* translators: %s: Site address */
								echo '<p><em>' . sprintf( __( 'The site you were looking for, <strong>%s</strong> does not exist, but you can create it now!', 'tml-classic' ), esc_url( $newblog ) ) . '</em></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} else {
								/* translators: %s: Site address */
								echo '<p><em>' . sprintf( __( 'The site you were looking for, <strong>%s</strong>, does not exist.', 'tml-classic' ), esc_url( $newblog ) ) . '</em></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
						}
					break;
			}
		}
		echo '</div>';
		do_action( 'after_signup_form' );
	}

	public function signup_header() {
		do_action( 'signup_header' );
	}

	public function signup_user( $user_name = '', $user_email = '' ) {
		global $current_site, $active_signup;
		$tml      = TML_Classic::get_object();
		$template = $tml->get_active_instance();
		$filtered = apply_filters( 'signup_user_init', array(
			'user_name'  => $user_name,
			'user_email' => $user_email,
			'errors'     => $tml->errors,
		) );
		$user_name  = $filtered['user_name'];
		$user_email = $filtered['user_email'];
		$errors     = $filtered['errors'];
		$templates   = (array) $template->get_option( 'ms_signup_user_template', array() );
		$templates[] = 'ms-signup-user-form.php';
		$template->get_template( $templates, true, compact( 'current_site', 'active_signup', 'user_name', 'user_email', 'errors' ) );
	}

	public function signup_blog( $user_name = '', $user_email = '', $blogname = '', $blog_title = '' ) {
		global $current_site;
		$tml      = TML_Classic::get_object();
		$template = $tml->get_active_instance();
		$filtered   = apply_filters( 'signup_blog_init', array(
			'user_name'  => $user_name,
			'user_email' => $user_email,
			'blogname'   => $blogname,
			'blog_title' => $blog_title,
			'errors'     => $tml->errors,
		) );
		$user_name  = $filtered['user_name'];
		$user_email = $filtered['user_email'];
		$blogname   = $filtered['blogname'];
		$blog_title = $filtered['blog_title'];
		$errors     = $filtered['errors'];
		if ( empty( $blogname ) )
			$blogname = $user_name;
		$templates   = (array) $template->get_option( 'ms_signup_blog_template', array() );
		$templates[] = 'ms-signup-blog-form.php';
		$template->get_template( $templates, true, compact( 'current_site', 'user_name', 'user_email', 'blogname', 'blog_title', 'errors' ) );
	}

	public function signup_another_blog( $blogname = '', $blog_title = '' ) {
		global $current_site;
		$tml      = TML_Classic::get_object();
		$template = $tml->get_active_instance();
		$filtered   = apply_filters( 'signup_another_blog_init', array(
			'blogname'   => $blogname,
			'blog_title' => $blog_title,
			'errors'     => $tml->errors,
		) );
		$blogname   = $filtered['blogname'];
		$blog_title = $filtered['blog_title'];
		$errors     = $filtered['errors'];
		$templates   = (array) $template->get_option( 'ms_signup_another_blog_template', array() );
		$templates[] = 'ms-signup-another-blog-form.php';
		$template->get_template( $templates, true, compact( 'current_site', 'blogname', 'blog_title', 'errors' ) );
	}

	public function tml_request_activate( &$tml ) {
		global $wp_object_cache;
		if ( is_object( $wp_object_cache ) )
			$wp_object_cache->cache_enabled = false;
		add_action( 'wp_head', array( $this, 'activate_header' ) );
	}

	public function tml_display_activate( &$template ) {
		global $blog_id;
		echo '<div class="login" id="tml-classic' . esc_attr( $template->get_option( 'instance' ) ) . '">';
		if ( empty( $_GET['key'] ) && empty( $_POST['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
			?>
			<h2><?php esc_html_e( 'Activation Key Required', 'tml-classic' ); ?></h2>
			<form name="activateform" id="activateform" method="post" action="<?php $template->the_action_url( 'activate' ); ?>">
				<p>
					<label for="key<?php $template->the_instance(); ?>"><?php esc_html_e( 'Activation Key:', 'tml-classic' ); ?></label>
					<br><input type="text" name="key<?php $template->the_instance(); ?>" id="key" value="" size="50">
				</p>
				<p class="submit">
					<input id="submit<?php $template->the_instance(); ?>" type="submit" name="Submit" class="submit" value="<?php esc_attr_e( 'Activate', 'tml-classic' ); ?>">
				</p>
			</form>
			<?php
		} else {
			$key    = !empty( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : sanitize_text_field( wp_unslash( $_POST['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
			$result = wpmu_activate_signup( $key );
			if ( is_wp_error( $result ) ) {
				if ( 'already_active' == $result->get_error_code() || 'blog_taken' == $result->get_error_code() ) {
					$signup = $result->get_error_data();
					?>
					<h2><?php esc_html_e( 'Your account is now active!', 'tml-classic' ); ?></h2>
					<?php
					echo '<p class="lead-in">';
					if ( $signup->domain . $signup->path == '' ) {
						/* translators: 1: Login URL, 2: Username, 3: Email address, 4: Lost password URL */
						printf( __( 'Your account has been activated. You may now <a href="%1$s">login</a> to the site using your chosen username of &#8220;%2$s&#8221;. Please check your email inbox at %3$s for your password and login instructions. If you do not receive an email, please check your junk or spam folder. If you still do not receive an email within an hour, you can <a href="%4$s">reset your password</a>.', 'tml-classic' ), esc_url( network_site_url( 'wp-login.php', 'login' ) ), esc_html( $signup->user_login ), esc_html( $signup->user_email ), esc_url( network_site_url( 'wp-login.php?action=lostpassword', 'login' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						/* translators: 1: Site URL, 2: Site domain, 3: Username, 4: Email address, 5: Lost password URL */
						printf( __( 'Your site at <a href="%1$s">%2$s</a> is active. You may now log in to your site using your chosen username of &#8220;%3$s&#8221;. Please check your email inbox at %4$s for your password and login instructions. If you do not receive an email, please check your junk or spam folder. If you still do not receive an email within an hour, you can <a href="%5$s">reset your password</a>.', 'tml-classic' ), esc_url( 'http://' . $signup->domain ), esc_html( $signup->domain ), esc_html( $signup->user_login ), esc_html( $signup->user_email ), esc_url( network_site_url( 'wp-login.php?action=lostpassword' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					echo '</p>';
				} else {
					?>
					<h2><?php esc_html_e( 'An error occurred during the activation', 'tml-classic' ); ?></h2>
					<?php
					echo '<p>' . wp_kses_post( $result->get_error_message() ) . '</p>';
				}
			} else {
				extract( $result );
				$url  = get_blogaddress_by_id( (int) $blog_id );
				$user = new WP_User( (int) $user_id );
				?>
				<h2><?php esc_html_e( 'Your account is now active!', 'tml-classic' ); ?></h2>
				<div id="signup-welcome">
					<p><span class="h3"><?php esc_html_e( 'Username:', 'tml-classic' ); ?></span> <?php echo esc_html( $user->user_login ); ?></p>
					<p><span class="h3"><?php esc_html_e( 'Password:', 'tml-classic' ); ?></span> <?php echo esc_html( $password ); ?></p>
				</div>
				<?php if ( $url != network_home_url( '', 'http' ) ) : switch_to_blog( (int) $blog_id ); ?>
					<p class="view"><?php
					/* translators: 1: Site URL, 2: Login URL */
					printf( __( 'Your account is now activated. <a href="%1$s">View your site</a> or <a href="%2$s">Login</a>', 'tml-classic' ), esc_url( $url ), esc_url( wp_login_url() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?></p>
				<?php restore_current_blog(); else : ?>
					<p class="view"><?php
					/* translators: 1: Login URL, 2: Homepage URL */
					printf( __( 'Your account is now activated. <a href="%1$s">Login</a> or go back to the <a href="%2$s">homepage</a>.', 'tml-classic' ), esc_url( network_site_url( 'wp-login.php', 'login' ) ), esc_url( network_home_url() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?></p>
				<?php endif;
			}
		}
		echo '</div>';
	}

	public function activate_header() {
		do_action( 'activate_header' );
		do_action( 'activate_wp_head' );
	}

	public function tml_title( $title, $action ) {
		if ( 'activate' == $action )
			$title = __( 'Activate', 'tml-classic' );
		return $title;
	}

	public function wpmu_new_blog( $blog_id, $user_id ) {
		require_once ABSPATH . '/wp-admin/includes/plugin.php';
		if ( is_plugin_active_for_network( plugin_basename( TML_CLASSIC_PATH ) . '/tml-classic.php' ) ) {
			switch_to_blog( $blog_id );
			$admin = TML_Classic_Admin::get_object();
			$admin->install();
			unset( $admin );
			restore_current_blog();
		}
	}

	public function site_url( $url, $path, $orig_scheme ) {
		global $pagenow;
		if ( in_array( $pagenow, array( 'wp-login.php', 'wp-signup.php', 'wp-activate.php' ) ) )
			return $url;
		$actions = array(
			'wp-signup.php'   => 'register',
			'wp-activate.php' => 'activate',
		);
		foreach ( $actions as $page => $action ) {
			if ( false !== strpos( $url, $page ) ) {
				$url = add_query_arg( 'action', $action, str_replace( $page, 'wp-login.php', $url ) );
				break;
			}
		}
		return $url;
	}

	public function network_site_url( $url, $path, $orig_scheme ) {
		global $current_site;
		$url = $this->site_url( $url, $path, $orig_scheme );
		switch_to_blog( $current_site->blog_id );
		$url = TML_Classic::get_object()->site_url( $url, $path, $orig_scheme );
		restore_current_blog();
		return $url;
	}

	public function clean_url( $url, $original_url, $context ) {
		if ( strpos( $original_url, 'action=activate' ) !== false )
			return $original_url;
		return $url;
	}
}

endif;