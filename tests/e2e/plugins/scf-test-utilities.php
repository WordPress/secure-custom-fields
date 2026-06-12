<?php
/**
 * Plugin Name: SCF Test Utilities
 * Plugin URI: https://github.com/WordPress/secure-custom-fields
 * Author: SCF Team
 *
 * Provides REST helpers for E2E test isolation, such as purging all
 * SCF internal posts (field groups, fields, post types, taxonomies, and
 * options pages) regardless of status.
 *
 * @package scf-test-plugins
 */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'scf-test/v1',
			'/purge-internal-posts',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => function ( $request ) {
					$types = $request->get_param( 'types' );
					if ( empty( $types ) || ! is_array( $types ) ) {
						$types = array( 'acf-field-group', 'acf-field' );
					}

					// Restrict to SCF internal post types only.
					$allowed = array( 'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page' );
					$types   = array_values( array_intersect( $types, $allowed ) );

					$deleted = array();
					$posts   = get_posts(
						array(
							'post_type'      => $types,
							'post_status'    => 'any',
							'posts_per_page' => -1,
							'fields'         => 'ids',
						)
					);

					foreach ( $posts as $post_id ) {
						if ( wp_delete_post( $post_id, true ) ) {
							$deleted[] = $post_id;
						}
					}

					return rest_ensure_response(
						array(
							'deleted' => count( $deleted ),
							'ids'     => $deleted,
						)
					);
				},
			)
		);
	}
);
