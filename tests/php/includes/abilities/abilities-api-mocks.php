<?php
/**
 * Mock functions for WordPress Abilities API
 *
 * These mock functions capture ability registrations for testing
 * when the WordPress Abilities API is not available (e.g., in WorDBless).
 *
 * @package wordpress/secure-custom-fields
 */

global $mock_registered_abilities, $mock_registered_ability_categories;
$mock_registered_abilities          = array();
$mock_registered_ability_categories = array();

if ( ! function_exists( 'wp_register_ability' ) ) {
	/**
	 * Mock wp_register_ability for testing.
	 *
	 * @param string $name Ability name.
	 * @param array  $args Ability arguments.
	 * @return bool Always returns true.
	 */
	function wp_register_ability( $name, $args ) {
		global $mock_registered_abilities;
		$mock_registered_abilities[ $name ] = $args;
		return true;
	}
}

if ( ! function_exists( 'wp_register_ability_category' ) ) {
	/**
	 * Mock wp_register_ability_category for testing.
	 *
	 * @param string $name Category name.
	 * @param array  $args Category arguments.
	 * @return bool Always returns true.
	 */
	function wp_register_ability_category( $name, $args ) {
		global $mock_registered_ability_categories;
		$mock_registered_ability_categories[ $name ] = $args;
		return true;
	}
}
