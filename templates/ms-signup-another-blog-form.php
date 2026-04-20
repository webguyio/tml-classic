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
printf( __( 'Get <em>another</em> %s site in seconds', 'tml-classic' ), esc_html( $current_site->site_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?></h2>

<?php if ( $errors->get_error_code() ) { ?>
	<p><?php esc_html_e( 'There was a problem, please correct the form below and try again.', 'tml-classic' ); ?></p>
<?php } ?>

<p><?php
/* translators: %s: User display name */
printf( __( 'Welcome back, %s. By filling out the form below, you can <strong>add another site to your account</strong>. There is no limit to the number of sites you can have, so create to your heart&#8217;s content, but write responsibly!', 'tml-classic' ), esc_html( $current_user->display_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
?></p>

<?php
$blogs = get_blogs_of_user( $current_user->ID );
if ( !empty( $blogs ) ) { ?>
	<p><?php esc_html_e( 'Sites you are already a member of:', 'tml-classic' ); ?></p>
	<ul>
		<?php foreach ( $blogs as $blog ) {
			$home_url = get_home_url( $blog->userblog_id );
			echo '<li><a href="' . esc_url( $home_url ) . '">' . esc_html( $home_url ) . '</a></li>';
		} ?>
	</ul>
<?php } ?>

<p><?php esc_html_e( 'If you&#8217;re not going to use a great site domain, leave it for a new user. Now have at it!', 'tml-classic' ); ?></p>
<form id="setupform" method="post" action="<?php $template->the_action_url( 'register', 'login_post' ); ?>">
	<input type="hidden" name="action" value="register">
	<input type="hidden" name="stage" value="gimmeanotherblog">
	<?php do_action( 'signup_hidden_fields' ); ?>

	<?php if ( !is_subdomain_install() ) { ?>
		<label for="blogname<?php $template->the_instance(); ?>"><?php esc_html_e( 'Site Name:', 'tml-classic' ); ?></label>
	<?php } else { ?>
		<label for="blogname<?php $template->the_instance(); ?>"><?php esc_html_e( 'Site Domain:', 'tml-classic' ); ?></label>
	<?php } ?>

	<?php if ( $errmsg = $errors->get_error_message( 'blogname' ) ) { ?>
		<p class="error"><?php echo wp_kses_post( $errmsg ); ?></p>
	<?php } ?>

	<?php if ( !is_subdomain_install() ) { ?>
		<span class="prefix_address"><?php echo esc_html( $current_site->domain . $current_site->path ); ?></span>
		<input name="blogname" type="text" id="blogname<?php $template->the_instance(); ?>" value="<?php echo esc_attr( $blogname ); ?>" maxlength="60"><br>
	<?php } else { ?>
		<input name="blogname" type="text" id="blogname<?php $template->the_instance(); ?>" value="<?php echo esc_attr( $blogname ); ?>" maxlength="60">
		<span class="suffix_address"><?php echo esc_html( $site_domain = preg_replace( '|^www\.|', '', $current_site->domain ) ); ?></span><br>
	<?php } ?>

	<?php if ( !is_user_logged_in() ) {
		if ( !is_subdomain_install() )
			$site = $current_site->domain . $current_site->path . __( 'sitename', 'tml-classic' );
		else
			$site = __( 'domain', 'tml-classic' ) . '.' . $site_domain . $current_site->path;
		/* translators: %s: Site address */
		echo '<p>(<strong>' . sprintf( __( 'Your address will be %s.', 'tml-classic' ), esc_html( $site ) ) . '</strong>) ' . esc_html__( 'Must be at least 4 characters, letters and numbers only. It cannot be changed, so choose carefully!', 'tml-classic' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} ?>

	<label for="blog_title<?php $template->the_instance(); ?>"><?php esc_html_e( 'Site Title:', 'tml-classic' ); ?></label>
	<?php if ( $errmsg = $errors->get_error_message( 'blog_title' ) ) { ?>
		<p class="error"><?php echo wp_kses_post( $errmsg ); ?></p>
	<?php } ?>
	<input name="blog_title" type="text" id="blog_title<?php $template->the_instance(); ?>" value="<?php echo esc_attr( $blog_title ); ?>">

	<div id="privacy">
		<p class="privacy-intro">
			<label for="blog_public_on<?php $template->the_instance(); ?>"><?php esc_html_e( 'Privacy:', 'tml-classic' ); ?></label>
			<?php esc_html_e( 'Allow search engines to index this site.', 'tml-classic' ); ?>
			<br style="clear:both">
			<label class="checkbox" for="blog_public_on<?php $template->the_instance(); ?>">
				<input type="radio" id="blog_public_on<?php $template->the_instance(); ?>" name="blog_public" value="1" <?php if ( !isset( $_POST['blog_public'] ) || '1' == $_POST['blog_public'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput ?>checked="checked"<?php } ?>>
				<strong><?php esc_html_e( 'Yes', 'tml-classic' ); ?></strong>
			</label>
			<label class="checkbox" for="blog_public_off<?php $template->the_instance(); ?>">
				<input type="radio" id="blog_public_off<?php $template->the_instance(); ?>" name="blog_public" value="0" <?php if ( isset( $_POST['blog_public'] ) && '0' == $_POST['blog_public'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput ?>checked="checked"<?php } ?>>
				<strong><?php esc_html_e( 'No', 'tml-classic' ); ?></strong>
			</label>
		</p>
	</div>

	<?php do_action( 'signup_blogform', $errors ); ?>

	<p class="submit"><input type="submit" name="submit" class="submit" value="<?php esc_attr_e( 'Create Site', 'tml-classic' ); ?>"></p>
</form>