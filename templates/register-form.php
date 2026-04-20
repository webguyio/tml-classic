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

<div class="tml tml-register" id="tml-classic<?php $template->the_instance(); ?>">
	<?php $template->the_action_template_message( 'register' ); ?>
	<?php $template->the_errors(); ?>
	<form name="registerform" id="registerform<?php $template->the_instance(); ?>" action="<?php $template->the_action_url( 'register', 'login_post' ); ?>" method="post">
		<?php if ( 'email' != $tml->get_option( 'login_type' ) ) : ?>
		<p class="tml-user-login-wrap">
			<label for="user_login<?php $template->the_instance(); ?>"><?php esc_html_e( 'Username', 'tml-classic' ); ?></label>
			<input type="text" name="user_login" id="user_login<?php $template->the_instance(); ?>" class="input" value="<?php $template->the_posted_value( 'user_login' ); ?>" size="20">
		</p>
		<?php endif; ?>

		<p class="tml-user-email-wrap">
			<label for="user_email<?php $template->the_instance(); ?>"><?php esc_html_e( 'Email', 'tml-classic' ); ?></label>
			<input type="text" name="user_email" id="user_email<?php $template->the_instance(); ?>" class="input" value="<?php $template->the_posted_value( 'user_email' ); ?>" size="20">
		</p>

		<?php do_action( 'register_form' ); ?>

		<p class="tml-registration-confirmation" id="reg_passmail<?php $template->the_instance(); ?>"><?php echo wp_kses_post( apply_filters( 'tml_register_passmail_template_message', __( 'Registration confirmation will be emailed to you.', 'tml-classic' ) ) ); ?></p>

		<p class="tml-submit-wrap">
			<input type="submit" name="wp-submit" id="wp-submit<?php $template->the_instance(); ?>" value="<?php esc_attr_e( 'Register', 'tml-classic' ); ?>">
			<input type="hidden" name="redirect_to" value="<?php $template->the_redirect_url( 'register' ); ?>">
			<input type="hidden" name="instance" value="<?php $template->the_instance(); ?>">
			<input type="hidden" name="action" value="register">
		</p>
	</form>
	<?php $template->the_action_links( array( 'register' => false ) ); ?>
</div>