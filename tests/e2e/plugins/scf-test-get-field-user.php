<?php
/**
 * Plugin Name: SCF Test Plugin, Get Field User Title
 * Plugin URI: https://github.com/WordPress/secure-custom-fields
 * Author: SCF Team
 *
 * @package scf-test-plugins
 */

/**
 * Add post-formats support to pages
 *
 * @return string Modified content.
 */
function scf_add_get_field_at_the_end_option() {

	// Get the field object to validate it exists.
	$field_object = get_field_object( 'user_title', 'user_1' );

	// Only proceed if the field exists and is a valid type.
	if ( $field_object && isset( $field_object['type'] ) && 'text' === $field_object['type'] ) {
		$field = get_field( 'user_title', 'user_1' );
		// Ensure we have a string value and sanitize it.
		$field = is_string( $field ) ? sanitize_text_field( $field ) : '';

		return '<p id="scf-test-user-title">User title: ' . $field . '</p>';
	}

	return '';
}

add_filter( 'the_content', 'scf_add_get_field_at_the_end_option' );
