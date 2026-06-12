<?php
/**
 * Plugin Name: SCF Test Plugin, Get Field User Title
 * Plugin URI: https://github.com/WordPress/secure-custom-fields
 * Author: SCF Team
 *
 * @package scf-test-plugins
 */

/**
 * Resolve the user ID whose `user_title` field should be rendered.
 *
 * The target user is configurable via the `scf_test_user_id` query arg
 * (validated with absint). When absent, it falls back to the queried
 * author, and finally to user 1 (the historical default) so existing
 * consumers keep working unchanged.
 *
 * @return int Target user ID.
 */
function scf_test_get_field_user_target_id() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only E2E test plugin parameter.
	if ( isset( $_GET['scf_test_user_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only E2E test plugin parameter.
		$user_id = absint( wp_unslash( $_GET['scf_test_user_id'] ) );
		if ( $user_id > 0 ) {
			return $user_id;
		}
	}

	$author_id = absint( get_query_var( 'author' ) );
	if ( $author_id > 0 ) {
		return $author_id;
	}

	return 1;
}

/**
 * Add post-formats support to pages
 *
 * @return string Modified content.
 */
function scf_add_get_field_at_the_end_option() {

	$user_ref = 'user_' . scf_test_get_field_user_target_id();

	// Get the field object to validate it exists.
	$field_object = get_field_object( 'user_title', $user_ref );

	// Only proceed if the field exists and is a valid type.
	if ( $field_object && isset( $field_object['type'] ) && 'text' === $field_object['type'] ) {
		$field = get_field( 'user_title', $user_ref );
		// Ensure we have a string value and sanitize it.
		$field = is_string( $field ) ? sanitize_text_field( $field ) : '';

		return '<p id="scf-test-user-title">User title: ' . $field . '</p>';
	}

	return '';
}

add_filter( 'the_content', 'scf_add_get_field_at_the_end_option' );
