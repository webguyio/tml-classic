<?php
/*
If you would like to edit this file, copy it to your current theme's directory and edit it there.
TML Classic will always look in your theme's directory first, before using this default template.
*/

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

?>

<div class="tml tml-login" id="tml-classic<?php $template->the_instance(); ?>">
	<?php $template->the_action_template_message( 'login' ); ?>
	<?php $template->the_errors(); ?>
	<form name="loginform" id="loginform<?php $template->the_instance(); ?>" action="<?php $template->the_action_url( 'login', 'login_post' ); ?>" method="post">
		<p class="tml-user-login-wrap">
			<label for="user_login<?php $template->the_instance(); ?>"><?php
				if ( 'username' == $tml->get_option( 'login_type' ) ) {
					esc_html_e( 'Username', 'tml-classic' );
				} elseif ( 'email' == $tml->get_option( 'login_type' ) ) {
					esc_html_e( 'Email', 'tml-classic' );
				} else {
					esc_html_e( 'Username or Email', 'tml-classic' );
				}
			?></label>
			<input type="text" name="log" id="user_login<?php $template->the_instance(); ?>" class="input" value="<?php $template->the_posted_value( 'log' ); ?>" size="20">
		</p>

		<p class="tml-user-pass-wrap">
			<label for="user_pass<?php $template->the_instance(); ?>"><?php esc_html_e( 'Password', 'tml-classic' ); ?></label>
			<input type="password" name="pwd" id="user_pass<?php $template->the_instance(); ?>" class="input" value="" size="20" autocomplete="off">
		</p>

		<?php do_action( 'login_form' ); ?>

		<div class="tml-rememberme-submit-wrap">
			<p class="tml-rememberme-wrap">
				<input name="rememberme" type="checkbox" id="rememberme<?php $template->the_instance(); ?>" value="forever">
				<label for="rememberme<?php $template->the_instance(); ?>"><?php esc_html_e( 'Remember Me', 'tml-classic' ); ?></label>
			</p>

			<p class="tml-submit-wrap">
				<input type="submit" name="wp-submit" id="wp-submit<?php $template->the_instance(); ?>" value="<?php esc_attr_e( 'Log In', 'tml-classic' ); ?>">
				<input type="hidden" name="redirect_to" value="<?php $template->the_redirect_url( 'login' ); ?>">
				<input type="hidden" name="instance" value="<?php $template->the_instance(); ?>">
				<input type="hidden" name="action" value="login">
			</p>
		</div>
	</form>
	<?php $template->the_action_links( array( 'login' => false ) ); ?>
</div>