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

<h2><?php
/* translators: %s: Site name */
printf( __( 'Get your own %s account in seconds', 'tml-classic' ), esc_html( $current_site->site_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?></h2>

<form id="setupform" method="post" action="<?php $template->the_action_url( 'register', 'login_post' ); ?>">
	<input type="hidden" name="action" value="register">
	<input type="hidden" name="stage" value="validate-user-signup">
	<?php do_action( 'signup_hidden_fields' ); ?>

	<label for="user_name<?php $template->the_instance(); ?>"><?php esc_html_e( 'Username:', 'tml-classic' ); ?></label>
	<?php if ( $errmsg = $errors->get_error_message( 'user_name' ) ) { ?>
		<p class="error"><?php echo wp_kses_post( $errmsg ); ?></p>
	<?php } ?>

	<input name="user_name" type="text" id="user_name<?php $template->the_instance(); ?>" value="<?php echo esc_attr( $user_name ); ?>" maxlength="60"><br>
	<span class="hint"><?php esc_html_e( '(Must be at least 4 characters, letters and numbers only.)', 'tml-classic' ); ?></span>

	<label for="user_email<?php $template->the_instance(); ?>"><?php esc_html_e( 'Email&nbsp;Address:', 'tml-classic' ); ?></label>
	<?php if ( $errmsg = $errors->get_error_message( 'user_email' ) ) { ?>
		<p class="error"><?php echo wp_kses_post( $errmsg ); ?></p>
	<?php } ?>

	<input name="user_email" type="text" id="user_email<?php $template->the_instance(); ?>" value="<?php echo esc_attr( $user_email ); ?>" maxlength="200"><br>
	<span class="hint"><?php esc_html_e( 'We send your registration email to this address. (Double-check your email address before continuing.)', 'tml-classic' ); ?></span>
	<?php if ( $errmsg = $errors->get_error_message( 'generic' ) ) { ?>
		<p class="error"><?php echo wp_kses_post( $errmsg ); ?></p>
	<?php } ?>

	<?php do_action( 'signup_extra_fields', $errors ); ?>

	<p>
	<?php if ( 'blog' == $active_signup ) { ?>
		<input id="signupblog<?php $template->the_instance(); ?>" type="hidden" name="signup_for" value="blog">
	<?php } elseif ( 'user' == $active_signup ) { ?>
		<input id="signupblog<?php $template->the_instance(); ?>" type="hidden" name="signup_for" value="user">
	<?php } else { ?>
		<input id="signupblog<?php $template->the_instance(); ?>" type="radio" name="signup_for" value="blog" <?php if ( !isset( $_POST['signup_for'] ) || 'blog' == $_POST['signup_for'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput ?>checked="checked"<?php } ?>>
		<label class="checkbox" for="signupblog"><?php esc_html_e( 'Gimme a site!', 'tml-classic' ); ?></label>
		<br>
		<input id="signupuser<?php $template->the_instance(); ?>" type="radio" name="signup_for" value="user" <?php if ( isset( $_POST['signup_for'] ) && 'user' == $_POST['signup_for'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput ?>checked="checked"<?php } ?>>
		<label class="checkbox" for="signupuser"><?php esc_html_e( 'Just a username, please.', 'tml-classic' ); ?></label>
	<?php } ?>
	</p>

	<p class="submit"><input type="submit" name="submit" class="submit" value="<?php esc_attr_e( 'Next', 'tml-classic' ); ?>"></p>
</form>
<?php $template->the_action_links( array( 'register' => false ) ); ?>