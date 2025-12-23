<?php
/**
 * Mock functions for WordPress Abilities API
 *
 * These mock functions capture ability registrations for testing
 * when the WordPress Abilities API is not available (e.g., in WorDBless).
 *
 * @package wordpress/secure-custom-fields
 */

global $mock_registered_abilities, $mock_registered_ability_categories, $mock_parent_exists;
$mock_registered_abilities          = array();
$mock_registered_ability_categories = array();
$mock_parent_exists                 = array();

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

/**
 * Helper function to set mock parent existence for testing.
 *
 * @param int|string $parent_id The parent ID to mock as existing.
 * @param bool       $exists    Whether the parent exists.
 */
function mock_parent_exists( $parent_id, $exists = true ) {
	global $mock_parent_exists;
	$mock_parent_exists[ $parent_id ] = $exists;
}

/**
 * Helper function to clear mock parent existence.
 */
function clear_mock_parent_exists() {
	global $mock_parent_exists;
	$mock_parent_exists = array();
}

/**
 * Check if a parent is mocked as existing.
 *
 * @param int|string $parent_id The parent ID to check.
 * @return bool|null True if mocked as existing, false if mocked as not existing, null if not mocked.
 */
function get_mock_parent_exists( $parent_id ) {
	global $mock_parent_exists;
	return isset( $mock_parent_exists[ $parent_id ] ) ? $mock_parent_exists[ $parent_id ] : null;
}
