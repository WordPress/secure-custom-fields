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

	$scf_post_types = acf_get_acf_post_types( array( 'active' => true ) );
	if ( ! empty( $scf_post_types ) ) {
		wp_enqueue_script( 'scf-commands-custom-post-types' );
	}

	// Only load admin commands if user has SCF admin capabilities.
	if ( current_user_can( acf_get_setting( 'capability' ) ) ) {
		wp_enqueue_script( 'scf-commands-admin' );

		// Inject field group data for "Edit Field Group" commands.
		$field_groups    = acf_get_field_groups();
		$editable_groups = array();
		foreach ( $field_groups as $field_group ) {
			$post_id = isset( $field_group['ID'] ) ? (int) $field_group['ID'] : 0;
			if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
				$editable_groups[] = array(
					'id'    => $post_id,
					'title' => $field_group['title'],
					'key'   => $field_group['key'],
				);
			}
		}
		wp_localize_script( 'scf-commands-admin', 'scfFieldGroups', $editable_groups );

		// Inject options page data for "Edit Options Page" commands.
		if ( function_exists( 'acf_get_ui_options_pages' ) ) {
			$ui_options_pages = acf_get_ui_options_pages();
			$editable_pages   = array();
			foreach ( $ui_options_pages as $options_page ) {
				$post_id = isset( $options_page['ID'] ) ? (int) $options_page['ID'] : 0;
				if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
					$editable_pages[] = array(
						'id'        => $post_id,
						'title'     => ! empty( $options_page['page_title'] ) ? $options_page['page_title'] : ( $options_page['menu_title'] ?? '' ),
						'menu_slug' => $options_page['menu_slug'],
					);
				}
			}
			wp_localize_script( 'scf-commands-admin', 'scfEditOptionsPages', $editable_pages );
		}
	}
}

add_action( 'admin_enqueue_scripts', 'acf_commands_init' );
