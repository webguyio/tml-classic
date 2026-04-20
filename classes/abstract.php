<?php
/*
 * @package TML_Classic
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Abstract' ) ) :
abstract class TML_Classic_Abstract {
	private static $objects = array();
	protected $options_key;
	protected $options = array();

	protected function __construct() {
		$this->load_options();
		$this->load();
	}

	private function __clone() {}

	public static function get_object( $class = null ) {
		if ( !class_exists( $class ) )
			return null;
		if ( !isset( self::$objects[ $class ] ) )
			self::$objects[ $class ] = new $class;
		return self::$objects[ $class ];
	}

	protected function load() {}

	public function load_options() {
		if ( method_exists( $this, 'default_options' ) )
			$this->options = (array) $this->default_options();
		if ( !$this->options_key )
			return;
		$options = get_option( $this->options_key, array() );
		$options = wp_parse_args( $options, $this->options );
		$this->options = $options;
	}

	public function save_options() {
		if ( $this->options_key )
			update_option( $this->options_key, $this->options );
	}

	public function get_option( $option, $default = false ) {
		if ( !is_array( $option ) )
			$option = array( $option );
		return self::_get_option( $option, $default, $this->options );
	}

	private function _get_option( $option, $default, &$options ) {
		$key = array_shift( $option );
		if ( !isset( $options[ $key ] ) )
			return $default;
		if ( !empty( $option ) )
			return self::_get_option( $option, $default, $options[ $key ] );
		return $options[ $key ];
	}

	public function get_options() {
		return $this->options;
	}

	public function set_option( $option, $value = '' ) {
		if ( !is_array( $option ) )
			$option = array( $option );
		self::_set_option( $option, $value, $this->options );
	}

	private function _set_option( $option, $value, &$options ) {
		$key = array_shift( $option );
		if ( !empty( $option ) ) {
			if ( !isset( $options[ $key ] ) )
				$options[ $key ] = array();
			return self::_set_option( $option, $value, $options[ $key ] );
		}
		$options[ $key ] = $value;
	}

	public function set_options( $options ) {
		$this->options = (array) $options;
	}

	public function delete_option( $option ) {
		if ( !is_array( $option ) )
			$option = array( $option );
		self::_delete_option( $option, $this->options );
	}

	private function _delete_option( $option, &$options ) {
		$key = array_shift( $option );
		if ( !empty( $option ) )
			return self::_delete_option( $option, $options[ $key ] );
		unset( $options[ $key ] );
	}
}

endif;