<?php
/**
 * Plugin Name: SCF Test Setup Post Types
 * Description: Creates SCF post types for E2E testing
 * Version: 1.0.0
 * Author: SCF Testing
 *
 * @package wordpress/secure-custom-fields
 *
 * IMPORTANT NOTE:
 * This plugin uses a hacky approach to create a test post type that SCF will recognize as its own, don't replicate in production code:
 *
 * - We use SCF's internal APIs (acf_get_internal_post_type_instance) that aren't meant for public use
 *    and could change between versions without notice.
 *
 * - We're directly creating database entries that SCF normally manages through its UI,
 *    bypassing the normal workflow and validation that the UI might provide.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register post types for testing
 * - One regular WordPress post type that will show up in the "other" source
 * - Product post type for block bindings testing
 */
function scf_test_register_post_types() {
	// Register a standard WordPress post type that will show up in the "other" source
	register_post_type(
		'other-e2e-test-type',
		array(
			'labels'       => array(
				'name'          => 'Other E2E Test Type',
				'singular_name' => 'Other E2E Test Item',
			),
			'public'       => true,
			'hierarchical' => false,
			'show_in_rest' => true,
			'has_archive'  => true,
			'supports'     => array( 'title', 'editor' ),
		)
	);

	// Register product post type for block bindings testing
	register_post_type(
		'product',
		array(
			'labels'       => array(
				'name'          => 'Products',
				'singular_name' => 'Product',
			),
			'public'       => true,
			'show_in_rest' => true,
			'has_archive'  => true,
			'supports'     => array( 'title', 'editor', 'custom-fields' ),
		)
	);
}

/**
 * Create an SCF post type entry in the database
 *
 * This function creates a post of type 'acf-post-type' in the database, which is how SCF
 * stores its post type definitions. When the REST API endpoint calls
 * acf_get_internal_post_type_posts('acf-post-type'), it will return our custom post type,
 * causing it to be categorized as an SCF post type.
 *
 * NOTE: This is a hacky approach that uses SCF's internal APIs and should not be used
 * in production. Ideally, SCF would provide a public API for registering post types
 * programmatically.
 */
function scf_test_create_scf_post_type_entry() {
	// Check if we've already created this post type to avoid duplicates
	if ( get_option( 'scf_test_post_type_created' ) ) {
		return;
	}

	// Make sure SCF is fully loaded
	if ( ! function_exists( 'acf_get_internal_post_type_instance' ) ) {
		return;
	}

	// Get the internal post type instance for managing acf-post-type entries
	$instance = acf_get_internal_post_type_instance( 'acf-post-type' );
	if ( ! $instance ) {
		return;
	}

	// Define our post type configuration (similar to what you'd fill in the UI)
	// This structure mirrors what SCF creates when you use the UI to create a post type
	$post_type_config = array(
		'key'                => 'scf_e2e_test_post_type',
		'title'              => 'SCF E2E Test Type',
		'post_type'          => 'scf-e2e-test-type',
		'description'        => 'Test post type for SCF E2E testing',
		'active'             => 1,
		'public'             => 1,
		'show_in_rest'       => 1,
		'publicly_queryable' => 1,
		'show_ui'            => 1,
		'show_in_menu'       => 1,
		'has_archive'        => 1,
		'supports'           => array( 'title', 'editor' ),
		'labels'             => array(
			'name'          => 'SCF E2E Test Type',
			'singular_name' => 'SCF E2E Test Item',
		),
	);

	// Create the post type entry in the database using SCF's internal API
	$result = $instance->update_post( $post_type_config );

	if ( is_array( $result ) && isset( $result['ID'] ) ) {
		// Store the post ID so we can delete it later
		update_option( 'scf_test_post_type_created', $result['ID'] );
	}
}

/**
 * Clean up on plugin deactivation
 */
function scf_test_cleanup() {
	// Get the stored post ID and delete the post
	$post_id = get_option( 'scf_test_post_type_created' );
	if ( $post_id ) {
		wp_delete_post( $post_id, true );
	}

	// Clean up the option
	delete_option( 'scf_test_post_type_created' );
}

// Register hooks
add_action( 'init', 'scf_test_register_post_types', 20 );
add_action( 'acf/init', 'scf_test_create_scf_post_type_entry', 15 );
register_deactivation_hook( __FILE__, 'scf_test_cleanup' );
