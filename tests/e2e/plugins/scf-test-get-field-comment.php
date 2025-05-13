<?php
/**
 * Plugin Name: SCF Test Plugin, Get Field Comment Title
 * Plugin URI: https://github.com/WordPress/secure-custom-fields
 * Author: SCF Team
 *
 * @package scf-test-plugins
 */

/**
 * Add post-formats support to pages
 *
 * @param string     $comment_text The comment text.
 * @param WP_Comment $comment The comment object.
 * @return string Modified comment text.
 */
function scf_add_get_field_at_the_end_comment( $comment_text, $comment ) {
	// Get the field object to validate it exists.
	$field_object = get_field_object( 'comment_title', $comment );

	// Only proceed if the field exists and is a valid type.
	if ( $field_object && isset( $field_object['type'] ) && 'text' === $field_object['type'] ) {
		$field = get_field( 'comment_title', $comment );

		// Ensure we have a string value and sanitize it.
		$field = is_string( $field ) ? sanitize_text_field( $field ) : '';

		return '<p id="scf-test-comment-title">Comment title: ' . $field . '</p>';
	}

	return '';
}

add_filter( 'comment_text', 'scf_add_get_field_at_the_end_comment', 10, 2 );
