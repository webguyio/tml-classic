<?php
/*
 * @package TML_Classic
 */

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

if ( !class_exists( 'TML_Classic_Common' ) ) :

class TML_Classic_Common {
	public static function get_current_url( $query = '' ) {
		$url = remove_query_arg( array( 'instance', 'action', 'checkemail', 'error', 'loggedout', 'registered', 'redirect_to', 'updated', 'key', '_wpnonce', 'reauth', 'login' ) );
		if ( !empty( $_REQUEST['instance'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$url = add_query_arg( 'instance', (int) $_REQUEST['instance'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( !empty( $query ) ) {
			$r = wp_parse_args( $query );
			foreach ( $r as $k => $v ) {
				if ( strpos( $v, ' ' ) !== false )
					$r[ $k ] = rawurlencode( $v );
			}
			$url = add_query_arg( $r, $url );
		}
		return $url;
	}

	public static function array_merge_recursive() {
		$args = func_get_args();
		$result = array_shift( $args );
		foreach ( $args as $arg ) {
			foreach ( $arg as $key => $value ) {
				if ( is_numeric( $key ) ) {
					if ( !in_array( $value, $result ) )
						$result[] = $value;
				} elseif ( array_key_exists( $key, $result ) && is_array( $result[ $key ] ) && is_array( $value ) ) {
					$result[ $key ] = self::array_merge_recursive( $result[ $key ], $value );
				} else {
					$result[ $key ] = $value;
				}
			}
		}
		return $result;
	}

	public static function replace_vars( $input, $user_id = '', $replacements = array() ) {
		$defaults = array(
			'%site_url%' => get_bloginfo( 'url' ),
			'%siteurl%'  => get_bloginfo( 'url' ),
			'%user_ip%'  => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		);
		$replacements = wp_parse_args( $replacements, $defaults );
		$user = false;
		if ( $user_id )
			$user = get_user_by( 'id', $user_id );
		preg_match_all( '/%([a-zA-Z0-9-_]*)%/', $input, $matches );
		foreach ( $matches[0] as $key => $match ) {
			if ( !isset( $replacements[ $match ] ) ) {
				if ( $user && isset( $user->{$matches[1][ $key ]} ) )
					$replacements[ $match ] = $user->{$matches[1][ $key ]};
				else
					$replacements[ $match ] = get_bloginfo( $matches[1][ $key ] );
			}
		}
		$replacements = apply_filters( 'tml_replace_vars', $replacements, $user_id );
		if ( empty( $replacements ) )
			return $input;
		return str_replace( array_keys( $replacements ), array_values( $replacements ), $input );
	}

	public static function strip_query_args( $url ) {
		return remove_query_arg( array(
			'instance', 'action', 'checkemail', 'error', 'loggedout',
			'registered', 'redirect_to', 'updated', 'key', '_wpnonce', 'reauth',
		), $url );
	}
}

endif;