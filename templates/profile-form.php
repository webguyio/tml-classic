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

<div class="tml tml-settings" id="tml-classic<?php $template->the_instance(); ?>">
	<?php $template->the_action_template_message( 'settings' ); ?>
	<?php $template->the_errors(); ?>
	<form id="your-profile" action="<?php $template->the_action_url( 'settings', 'login_post' ); ?>" method="post">
		<?php wp_nonce_field( 'update-user_' . $current_user->ID ); ?>

		<input type="hidden" name="from" value="profile">
		<input type="hidden" name="checkuser_id" value="<?php echo esc_attr( $current_user->ID ); ?>">

		<?php if ( apply_filters( 'show_admin_bar', true ) || has_action( 'personal_options' ) ) : ?>
			<h3><?php esc_html_e( 'Personal Options', 'tml-classic' ); ?></h3>

			<table class="tml-form-table">
			<?php if ( apply_filters( 'show_admin_bar', true ) ) : ?>
				<tr class="tml-user-admin-bar-front-wrap">
					<th><label for="admin_bar_front"><?php esc_html_e( 'Toolbar', 'tml-classic' ); ?></label></th>
					<td>
						<label for="admin_bar_front"><input type="checkbox" name="admin_bar_front" id="admin_bar_front" value="1"<?php checked( _get_admin_bar_pref( 'front', $profileuser->ID ) ); ?>>
						<?php esc_html_e( 'Show Toolbar when viewing site', 'tml-classic' ); ?></label>
					</td>
				</tr>
			<?php endif; ?>
			<?php do_action( 'personal_options', $profileuser ); ?>
			</table>
		<?php endif; ?>

		<?php do_action( 'profile_personal_options', $profileuser ); ?>

		<h3><?php esc_html_e( 'Name', 'tml-classic' ); ?></h3>

		<table class="tml-form-table">
		<tr class="tml-user-login-wrap">
			<th><label for="user_login"><?php esc_html_e( 'Username', 'tml-classic' ); ?></label></th>
			<td><input type="text" name="user_login" id="user_login" value="<?php echo esc_attr( $profileuser->user_login ); ?>" disabled="disabled" class="regular-text"> <span class="description"><?php esc_html_e( 'Usernames cannot be changed.', 'tml-classic' ); ?></span></td>
		</tr>

		<tr class="tml-first-name-wrap">
			<th><label for="first_name"><?php esc_html_e( 'First Name', 'tml-classic' ); ?></label></th>
			<td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr( $profileuser->first_name ); ?>" class="regular-text"></td>
		</tr>

		<tr class="tml-last-name-wrap">
			<th><label for="last_name"><?php esc_html_e( 'Last Name', 'tml-classic' ); ?></label></th>
			<td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr( $profileuser->last_name ); ?>" class="regular-text"></td>
		</tr>

		<tr class="tml-nickname-wrap">
			<th><label for="nickname"><?php esc_html_e( 'Nickname', 'tml-classic' ); ?> <span class="description"><?php esc_html_e( '(required)', 'tml-classic' ); ?></span></label></th>
			<td><input type="text" name="nickname" id="nickname" value="<?php echo esc_attr( $profileuser->nickname ); ?>" class="regular-text"></td>
		</tr>

		<tr class="tml-display-name-wrap">
			<th><label for="display_name"><?php esc_html_e( 'Display name publicly as', 'tml-classic' ); ?></label></th>
			<td>
				<select name="display_name" id="display_name">
				<?php
					$public_display = array();
					$public_display['display_nickname']  = $profileuser->nickname;
					$public_display['display_username']  = $profileuser->user_login;
					if ( !empty( $profileuser->first_name ) )
						$public_display['display_firstname'] = $profileuser->first_name;
					if ( !empty( $profileuser->last_name ) )
						$public_display['display_lastname'] = $profileuser->last_name;
					if ( !empty( $profileuser->first_name ) && !empty( $profileuser->last_name ) ) {
						$public_display['display_firstlast'] = $profileuser->first_name . ' ' . $profileuser->last_name;
						$public_display['display_lastfirst'] = $profileuser->last_name . ' ' . $profileuser->first_name;
					}
					if ( !in_array( $profileuser->display_name, $public_display ) )
						$public_display = array( 'display_displayname' => $profileuser->display_name ) + $public_display;
					$public_display = array_unique( array_map( 'trim', $public_display ) );
					foreach ( $public_display as $id => $item ) {
				?>
					<option <?php selected( $profileuser->display_name, $item ); ?>><?php echo esc_html( $item ); ?></option>
				<?php
					}
				?>
				</select>
			</td>
		</tr>
		</table>

		<h3><?php esc_html_e( 'Contact Info', 'tml-classic' ); ?></h3>

		<table class="tml-form-table">
		<tr class="tml-user-email-wrap">
			<th><label for="email"><?php esc_html_e( 'Email', 'tml-classic' ); ?> <span class="description"><?php esc_html_e( '(required)', 'tml-classic' ); ?></span></label></th>
			<td><input type="text" name="email" id="email" value="<?php echo esc_attr( $profileuser->user_email ); ?>" class="regular-text"></td>
			<?php
			$new_email = get_option( $current_user->ID . '_new_email' );
			if ( $new_email && $new_email['newemail'] != $current_user->user_email ) : ?>
			<div class="updated inline">
			<p><?php
				/* translators: 1: New email address (HTML), 2: Cancel URL */
				printf( __( 'There is a pending change of your email to %1$s. <a href="%2$s">Cancel</a>', 'tml-classic' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'<code>' . esc_html( $new_email['newemail'] ) . '</code>',
					esc_url( self_admin_url( 'profile.php?dismiss=' . $current_user->ID . '_new_email' ) )
				); ?></p>
			</div>
			<?php endif; ?>
		</tr>

		<tr class="tml-user-url-wrap">
			<th><label for="url"><?php esc_html_e( 'Website', 'tml-classic' ); ?></label></th>
			<td><input type="text" name="url" id="url" value="<?php echo esc_attr( $profileuser->user_url ); ?>" class="regular-text code"></td>
		</tr>

		<?php foreach ( wp_get_user_contact_methods() as $name => $desc ) { ?>
		<tr class="tml-user-contact-method-<?php echo esc_attr( $name ); ?>-wrap">
			<th><label for="<?php echo esc_attr( $name ); ?>"><?php echo wp_kses_post( apply_filters( 'user_' . $name . '_label', $desc ) ); ?></label></th>
			<td><input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $profileuser->$name ); ?>" class="regular-text"></td>
		</tr>
		<?php } ?>
		</table>

		<h3><?php esc_html_e( 'About Yourself', 'tml-classic' ); ?></h3>

		<table class="tml-form-table">
		<tr class="tml-user-description-wrap">
			<th><label for="description"><?php esc_html_e( 'Biographical Info', 'tml-classic' ); ?></label></th>
			<td><textarea name="description" id="description" rows="5" cols="30"><?php echo esc_html( $profileuser->description ); ?></textarea><br>
			<span class="description"><?php esc_html_e( 'Share a little biographical information to fill out your profile. This may be shown publicly.', 'tml-classic' ); ?></span></td>
		</tr>

		<?php $show_password_fields = apply_filters( 'show_password_fields', true, $profileuser );
		if ( $show_password_fields ) : ?>
		</table>

		<h3><?php esc_html_e( 'Account Management', 'tml-classic' ); ?></h3>
		<table class="tml-form-table">
		<tr id="password" class="user-pass1-wrap">
			<th><label for="pass1"><?php esc_html_e( 'New Password', 'tml-classic' ); ?></label></th>
			<td>
				<input class="hidden" value=" ">
				<button type="button" class="button button-secondary wp-generate-pw hide-if-no-js"><?php esc_html_e( 'Generate Password', 'tml-classic' ); ?></button>
				<div class="wp-pwd hide-if-js">
					<span class="password-input-wrapper">
						<input type="password" name="pass1" id="pass1" class="regular-text" value="" autocomplete="off" data-pw="<?php echo esc_attr( wp_generate_password( 24 ) ); ?>" aria-describedby="pass-strength-result">
					</span>
					<div style="display:none" id="pass-strength-result" aria-live="polite"></div>
					<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password', 'tml-classic' ); ?>">
						<span class="dashicons dashicons-hidden"></span>
						<span class="text"><?php esc_html_e( 'Hide', 'tml-classic' ); ?></span>
					</button>
					<button type="button" class="button button-secondary wp-cancel-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Cancel password change', 'tml-classic' ); ?>">
						<span class="text"><?php esc_html_e( 'Cancel', 'tml-classic' ); ?></span>
					</button>
				</div>
			</td>
		</tr>
		<tr class="user-pass2-wrap hide-if-js">
			<th scope="row"><label for="pass2"><?php esc_html_e( 'Repeat New Password', 'tml-classic' ); ?></label></th>
			<td>
			<input name="pass2" type="password" id="pass2" class="regular-text" value="" autocomplete="off">
			<p class="description"><?php esc_html_e( 'Type your new password again.', 'tml-classic' ); ?></p>
			</td>
		</tr>
		<tr class="pw-weak">
			<th><?php esc_html_e( 'Confirm Password', 'tml-classic' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="pw_weak" class="pw-checkbox">
					<?php esc_html_e( 'Confirm use of weak password', 'tml-classic' ); ?>
				</label>
			</td>
		</tr>
		<?php endif; ?>

		</table>

		<?php do_action( 'show_user_profile', $profileuser ); ?>

		<p class="tml-submit-wrap">
			<input type="hidden" name="action" value="settings">
			<input type="hidden" name="instance" value="<?php $template->the_instance(); ?>">
			<input type="hidden" name="user_id" id="user_id" value="<?php echo esc_attr( $current_user->ID ); ?>">
			<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Update Settings', 'tml-classic' ); ?>" name="submit" id="submit">
		</p>
	</form>
</div>