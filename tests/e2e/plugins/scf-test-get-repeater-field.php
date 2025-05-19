<?php
/**
 * Plugin Name: SCF Test Plugin, Get Repeater Field
 * Plugin URI: https://github.com/WordPress/secure-custom-fields
 * Author: SCF Team
 *
 * @package scf-test-plugins
 */

/**
 * Add repeater field data to the end of content
 */
function scf_add_get_field_at_the_end() {
	$output = '';

	// Check if we have the 'actors' repeater field
	if ( have_rows( 'actors' ) ) {
		$output .= '<div id="scf-test-actors" class="actors-list">';
		$output .= '<h3>Movie Cast</h3>';
		$output .= '<ul>';

		// Loop through rows
		while ( have_rows( 'actors' ) ) {
			the_row();

			// Get subfields
			$actor_name  = get_sub_field( 'actor_name' );
			$actor_email = get_sub_field( 'actor_email' );

			// Sanitize values
			$actor_name  = is_string( $actor_name ) ? sanitize_text_field( $actor_name ) : '';
			$actor_email = is_string( $actor_email ) ? sanitize_email( $actor_email ) : '';

			$output .= '<li>';
			$output .= '<span class="actor-name">Actor: ' . $actor_name . '</span><br>';
			$output .= '<span class="actor-email">Email: ' . $actor_email . '</span>';
			$output .= '</li>';
		}

		$output .= '</ul>';
		$output .= '</div>';
	}

	// Add the repeater data to the end of the content
	return $output;
}

add_filter( 'the_content', 'scf_add_get_field_at_the_end' );
