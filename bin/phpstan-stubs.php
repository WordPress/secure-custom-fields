<?php
/**
 * PHPStan-only stubs for optional runtime APIs.
 *
 * @package WordPress/Secure-Custom-Fields
 */

// phpcs:disable -- These declarations exist only to teach PHPStan about optional APIs.

namespace {
	if ( ! class_exists( 'WP_Ability' ) ) {
		/**
		 * Stub for the WordPress Abilities API base class.
		 */
		class WP_Ability {
		}
	}

	if ( ! class_exists( 'WP_CLI' ) ) {
		/**
		 * Stub for the WP-CLI runtime class.
		 */
		class WP_CLI {
			public static function add_command( $name, $callable, $args = array() ): void {
			}

			public static function log( $message ): void {
			}

			public static function success( $message ): void {
			}

			public static function warning( $message ): void {
			}

			public static function error( $message, $exit = true ): void {
			}
		}
	}
}

namespace WP_CLI\Utils {
	if ( ! function_exists( __NAMESPACE__ . '\format_items' ) ) {
		function format_items( $format, $items, $fields ) {
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\get_flag_value' ) ) {
		function get_flag_value( $assoc_args, $flag, $default = null ) {
			return $default;
		}
	}
}
