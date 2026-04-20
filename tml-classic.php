<?php
/*
Plugin Name: TML Classic
Plugin URI: https://webguyio.github.io/tml-classic/
Description: Themes the WordPress login, registration, and forgot password pages according to your theme.
Version: 0.1
Requires at least: 5.0
Requires PHP: 8.0
Author: Web Guy
Author URI: https://webguy.io/
License: GPL
License URI: https://www.gnu.org/licenses/gpl.html
Text Domain: tml-classic
*/

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( file_exists( WP_PLUGIN_DIR . '/tml-classic-custom.php' ) )
	include_once WP_PLUGIN_DIR . '/tml-classic-custom.php';

if ( !defined( 'TML_CLASSIC_PATH' ) )
	define( 'TML_CLASSIC_PATH', dirname( __FILE__ ) );

if ( !defined( 'TML_CLASSIC_VERSION' ) )
	define( 'TML_CLASSIC_VERSION', '0.1' );

require_once TML_CLASSIC_PATH . '/classes/abstract.php';
require_once TML_CLASSIC_PATH . '/classes/common.php';
require_once TML_CLASSIC_PATH . '/classes/core.php';
require_once TML_CLASSIC_PATH . '/classes/template.php';
require_once TML_CLASSIC_PATH . '/classes/widget.php';

register_activation_hook( __FILE__, 'tml_classic_activate' );

function tml_classic_activate() {
	$map = array(
		'theme_my_login'                 => 'tml_classic',
		'theme_my_login_security'        => 'tml_classic_security',
		'theme_my_login_email'           => 'tml_classic_email',
		'theme_my_login_redirection'     => 'tml_classic_redirection',
		'theme_my_login_user_links'      => 'tml_classic_links',
		'theme_my_login_moderation'      => 'tml_classic_moderation',
		'theme_my_login_captcha'         => 'tml_classic_captcha',
		'theme_my_login_themed_profiles' => 'tml_classic_profiles',
	);
	foreach ( $map as $old => $new ) {
		$val = get_option( $old );
		if ( false !== $val && false === get_option( $new ) )
			add_option( $new, $val );
	}
	$old_settings = get_option( 'tml_classic', array() );
	if ( !empty( $old_settings['active_addons'] ) ) {
		$addon_map = array(
			'modules/security/security.php'                     => 'security.php',
			'modules/custom-email/custom-email.php'             => 'email.php',
			'modules/custom-passwords/custom-passwords.php'     => 'passwords.php',
			'modules/custom-redirection/custom-redirection.php' => 'redirection.php',
			'modules/custom-user-links/custom-user-links.php'   => 'links.php',
			'modules/themed-profiles/themed-profiles.php'       => 'profiles.php',
			'modules/recaptcha/recaptcha.php'                   => 'captcha.php',
			'security.php'                                      => 'security.php',
			'custom-email.php'                                  => 'email.php',
			'custom-passwords.php'                              => 'passwords.php',
			'custom-redirection.php'                            => 'redirection.php',
			'custom-user-links.php'                             => 'links.php',
			'themed-profiles.php'                               => 'profiles.php',
			'user-moderation.php'                               => 'moderation.php',
			'captcha.php'                                       => 'captcha.php',
		);
		$mapped = array();
		foreach ( $old_settings['active_addons'] as $old_addon ) {
			if ( isset( $addon_map[ $old_addon ] ) )
				$mapped[] = $addon_map[ $old_addon ];
		}
		if ( !empty( $mapped ) ) {
			$old_settings['active_addons'] = array_unique( $mapped );
			update_option( 'tml_classic', $old_settings );
		}
	}
}

TML_Classic::get_object();

if ( is_admin() ) {
	require_once TML_CLASSIC_PATH . '/classes/admin.php';
	TML_Classic_Admin::get_object();
	require_once TML_CLASSIC_PATH . '/classes/updates.php';
	new TML_Classic_Updater( __FILE__ );
}

if ( is_multisite() ) {
	require_once TML_CLASSIC_PATH . '/classes/multisite.php';
	TML_Classic_Multisite::get_object();
}

if ( !function_exists( 'tml_classic' ) ) :
function tml_classic( $args = '' ) {
	echo TML_Classic::get_object()->shortcode( wp_parse_args( $args ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output escaped internally by shortcode()
}
endif;