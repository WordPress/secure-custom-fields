<?php
/**
 * Command Palette Integration
 *
 * @package Secure Custom Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Loads the command palette script and its dependencies
 *
 * This function handles the integration with WordPress Command Palette (Cmd+K / Ctrl+K),
 * providing navigation commands for SCF admin pages and custom post types.
 *
 * The implementation follows these principles:
 * 1. Only loads in admin screens
 * 2. Performs capability checks to ensure users only see commands they can access
 * 3. Core administrative commands are only shown to users with SCF admin capabilities
 * 4. Custom post type commands are conditionally shown based on edit_posts capability
 *    for each specific post type
 * 5. Post types must have UI enabled (show_ui setting) to appear in the command palette
 *
 * @since 6.5.0
 */
function acf_command_palette_init() {
	// Only load on admin screens.
	if ( ! is_admin() ) {
		return;
	}

	$custom_post_types = array();

	if ( function_exists( 'acf_get_acf_post_types' ) ) {
		$scf_post_types = acf_get_acf_post_types();

		foreach ( $scf_post_types as $post_type ) {
			// Skip if post type name is not set (in theory it should always be) or post type is inactive.
			if ( empty( $post_type['post_type'] ) || ( isset( $post_type['active'] ) && ! $post_type['active'] ) ) {
				continue;
			}

			$plural_label   = $post_type['labels']['name'] ?? $post_type['label'] ?? $post_type['post_type'];
			$singular_label = $post_type['labels']['singular_name'] ?? $post_type['singular_label'] ?? $plural_label;

			$post_type_obj = get_post_type_object( $post_type['post_type'] );

			// Three conditions must be met to include this post type in the command palette:
			// 1. Post type object must exist
			// 2. Current user must have permission to edit posts of this type.
			// 3. Post type must have admin UI enabled (show_ui setting).
			if ( $post_type_obj &&
				current_user_can( $post_type_obj->cap->edit_posts ) &&
				$post_type_obj->show_ui ) {
				$custom_post_types[] = array(
					'name'           => $post_type['post_type'],
					'label'          => $plural_label,
					'singular_label' => $singular_label,
					'icon'           => $post_type['menu_icon'] ?? '',
				);
			}
		}
	}

	acf_localize_data(
		array(
			'customPostTypes' => $custom_post_types,
		)
	);

	if ( ! empty( $custom_post_types ) ) {
		wp_enqueue_script( 'acf-command-palette-post-types' );
	}

	// Only load admin commands if user has SCF admin capabilities.
	if ( current_user_can( acf_get_setting( 'capability' ) ) ) {
		wp_enqueue_script( 'acf-command-palette-core' );
	}
}

add_action( 'admin_enqueue_scripts', 'acf_command_palette_init' );
