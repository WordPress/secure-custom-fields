<?php
/**
 * Plugin Name: SCF Test Setup Post Types
 * Description: Creates SCF post types for E2E testing
 * Version: 1.0.0
 * Author: SCF Testing
 *
 * @package wordpress/secure-custom-fields
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
 * Register a local SCF post type (no database entry needed).
 *
 * Uses the acf/include_post_types hook, which fires before SCF's
 * register_post_types(), so the post type is available on the very
 * first request after plugin activation — no second page-load required.
 */
function scf_test_add_local_scf_post_type() {
	acf_add_local_internal_post_type(
		array(
			'key'                => 'scf_e2e_test_post_type',
			'title'              => 'SCF E2E Test Type',
			'post_type'          => 'scf-e2e-test-type',
			'description'        => 'Test post type for SCF E2E testing',
			'active'             => true,
			'public'             => true,
			'show_in_rest'       => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'has_archive'        => true,
			'supports'           => array( 'title', 'editor' ),
			'labels'             => array(
				'name'          => 'SCF E2E Test Types',
				'singular_name' => 'SCF E2E Test Item',
				'add_new_item'  => 'Add New SCF E2E Test Item',
				'all_items'     => 'All SCF E2E Test Types',
			),
		),
		'acf-post-type'
	);
}

// Register hooks
add_action( 'init', 'scf_test_register_post_types', 20 );
add_action( 'acf/include_post_types', 'scf_test_add_local_scf_post_type' );
