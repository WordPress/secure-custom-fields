<?php
/**
 * SCF Commands Integration
 *
 * @package Secure Custom Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Initializes SCF commands integration
 *
 * This function handles the integration with WordPress Commands (Cmd+K / Ctrl+K),
 * providing navigation commands for SCF admin pages and custom post types.
 *
 * The implementation follows these principles:
 * 1. Only loads in screens where WordPress commands are available.
 * 2. Performs capability checks to ensure users only see commands they can access.
 * 3. Core administrative commands are only shown to users with SCF admin capabilities.
 * 4. Custom post type commands are conditionally shown based on edit_posts capability
 *    for each specific post type.
 * 5. Post types must have UI enabled (show_ui setting) to appear in commands.
 *
 * @since SCF 6.5.0
 */
function acf_commands_init() {
	// Ensure we only load our commands where the WordPress commands API is available.
	if ( ! wp_script_is( 'wp-commands', 'registered' ) ) {
		return;
	}

	$custom_post_types = array();

	$scf_post_types = acf_get_acf_post_types();

	foreach ( $scf_post_types as $post_type ) {
		// Skip if post type name is not set (defensive) or post type is inactive.
		if ( empty( $post_type['post_type'] ) || ( isset( $post_type['active'] ) && ! $post_type['active'] ) ) {
			continue;
		}

		$post_type_obj = get_post_type_object( $post_type['post_type'] );

		// Three conditions must be met to include this post type in the commands:
		// 1. Post type object must exist
		// 2. Current user must have permission to edit posts of this type.
		// 3. Post type must have admin UI enabled (show_ui setting).
		if ( $post_type_obj &&
			current_user_can( $post_type_obj->cap->edit_posts ) &&
			$post_type_obj->show_ui ) {

			$labels = get_post_type_labels( $post_type_obj );

			$custom_post_types[] = array(
				'name'         => $post_type['post_type'],
				'all_items'    => $labels->all_items,
				'add_new_item' => $labels->add_new_item,
				'icon'         => $post_type['menu_icon'] ?? '',
				'label'        => $labels->name,
				'id'           => $post_type['ID'],
			);
		}
	}

	if ( ! empty( $custom_post_types ) ) {
		acf_localize_data(
			array(
				'customPostTypes' => $custom_post_types,
			)
		);
		wp_enqueue_script( 'scf-commands-custom-post-types' );
	}

	// Only load admin commands if user has SCF admin capabilities.
	if ( current_user_can( acf_get_setting( 'capability' ) ) ) {
		wp_enqueue_script( 'scf-commands-admin' );
	}
}

add_action( 'admin_enqueue_scripts', 'acf_commands_init' );
