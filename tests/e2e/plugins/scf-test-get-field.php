<?php
/**
 * Plugin Name: SCF Test Plugin, Get Field Movie Title
 * Plugin URI: https://github.com/WordPress/secure-custom-fields
 * Author: SCF Team
 *
 * @package scf-test-plugins
 */

add_filter( 'the_content', 'scf_add_get_field_at_the_end' );

/**
 * Add post-formats support to pages
 */
function scf_add_get_field_at_the_end() {
	// Get the field object to validate it exists.
	$field_object = get_field_object( 'movie_title' );

	// Only proceed if the field exists and is a valid type.
	if ( $field_object && isset( $field_object['type'] ) && 'text' === $field_object['type'] ) {
		$field = get_field( 'movie_title' );

		// Ensure we have a string value and sanitize it.
		$field = is_string( $field ) ? $field : '';

		// Sanitize the field value using WordPress sanitization functions.
		$field = sanitize_text_field( $field );

		// Escape the output for HTML context.
		$escaped_field = esc_html( $field );

		// Use wp_kses_post to allow safe HTML if needed, but escape by default.
		$output = wp_kses_post( '<br><p id="scf-test-movie-title">Movie title: ' . $escaped_field . '</p>' );

		return $output;
	}

	return '';
}
