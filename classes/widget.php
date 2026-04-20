<?php
/*
 * @package TML_Classic
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Widget' ) ) :

class TML_Classic_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( 'tml-classic', __( 'TML Classic', 'tml-classic' ), array(
			'classname'   => 'widget_tml_classic',
			'description' => __( 'A login form for your site.', 'tml-classic' ),
		) );
	}

	public function widget( $args, $instance ) {
		$tml      = TML_Classic::get_object();
		$instance = wp_parse_args( $instance, array(
			'default_action'    => 'login',
			'logged_in_widget'  => true,
			'logged_out_widget' => true,
			'show_title'        => true,
			'show_log_link'     => true,
			'show_reg_link'     => true,
			'show_pass_link'    => true,
			'show_gravatar'     => true,
			'gravatar_size'     => 50,
		) );
		if ( is_user_logged_in() && !$instance['logged_in_widget'] )
			return;
		if ( !is_user_logged_in() && !$instance['logged_out_widget'] )
			return;
		echo $tml->shortcode( array_merge( $args, $instance ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function update( $new_instance, $old_instance ) {
		$instance                      = $old_instance;
		$instance['default_action']    = in_array( $new_instance['default_action'], array( 'login', 'register', 'lostpassword' ) ) ? $new_instance['default_action'] : 'login';
		$instance['logged_in_widget']  = !empty( $new_instance['logged_in_widget'] );
		$instance['logged_out_widget'] = !empty( $new_instance['logged_out_widget'] );
		$instance['show_title']        = !empty( $new_instance['show_title'] );
		$instance['show_log_link']     = !empty( $new_instance['show_log_link'] );
		$instance['show_reg_link']     = !empty( $new_instance['show_reg_link'] );
		$instance['show_pass_link']    = !empty( $new_instance['show_pass_link'] );
		$instance['show_gravatar']     = !empty( $new_instance['show_gravatar'] );
		$instance['gravatar_size']     = absint( $new_instance['gravatar_size'] );
		return $instance;
	}

	public function form( $instance ) {
		$instance = wp_parse_args( $instance, array(
			'default_action'    => 'login',
			'logged_in_widget'  => 1,
			'logged_out_widget' => 1,
			'show_title'        => 1,
			'show_log_link'     => 1,
			'show_reg_link'     => 1,
			'show_pass_link'    => 1,
			'show_gravatar'     => 1,
			'gravatar_size'     => 50,
		) );
		$actions = array(
			'login'        => __( 'Login',         'tml-classic' ),
			'register'     => __( 'Register',      'tml-classic' ),
			'lostpassword' => __( 'Lost Password', 'tml-classic' ),
		);
		echo '<p>' . esc_html__( 'Default Action', 'tml-classic' ) . '<br><select name="' . esc_attr( $this->get_field_name( 'default_action' ) ) . '" id="' . esc_attr( $this->get_field_id( 'default_action' ) ) . '">';
		foreach ( $actions as $action => $title ) {
			$selected = ( $instance['default_action'] == $action ) ? ' selected="selected"' : '';
			echo '<option value="' . esc_attr( $action ) . '"' . esc_attr( $selected ) . '>' . esc_html( $title ) . '</option>';
		}
		echo '</select></p>' . "\n";
		$checkboxes = array(
			'logged_in_widget'  => __( 'Show When Logged In',    'tml-classic' ),
			'logged_out_widget' => __( 'Show When Logged Out',   'tml-classic' ),
			'show_title'        => __( 'Show Title',             'tml-classic' ),
			'show_log_link'     => __( 'Show Login Link',        'tml-classic' ),
			'show_reg_link'     => __( 'Show Register Link',     'tml-classic' ),
			'show_pass_link'    => __( 'Show Lost Password Link','tml-classic' ),
			'show_gravatar'     => __( 'Show Gravatar',          'tml-classic' ),
		);
		foreach ( $checkboxes as $key => $label ) {
			$checked = empty( $instance[ $key ] ) ? '' : 'checked="checked" ';
			echo '<p><input name="' . esc_attr( $this->get_field_name( $key ) ) . '" type="checkbox" id="' . esc_attr( $this->get_field_id( $key ) ) . '" value="1" ' . esc_attr( $checked ) . '/> <label for="' . esc_attr( $this->get_field_id( $key ) ) . '">' . esc_html( $label ) . '</label></p>' . "\n";
		}
		echo '<p>' . esc_html__( 'Gravatar Size', 'tml-classic' ) . ': <input name="' . esc_attr( $this->get_field_name( 'gravatar_size' ) ) . '" type="text" id="' . esc_attr( $this->get_field_id( 'gravatar_size' ) ) . '" value="' . absint( $instance['gravatar_size'] ) . '" size="3"></p>' . "\n";
	}
}

endif;