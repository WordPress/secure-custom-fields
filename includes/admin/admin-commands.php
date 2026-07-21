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

		// Localize registered options pages so the JS can register a palette
		// command per page. Filtered by capability so users only see pages
		// they can access.
		$pages = acf_get_options_pages();
		if ( ! empty( $pages ) ) {
			$localized = array();
			foreach ( $pages as $page ) {
				if ( ! current_user_can( $page['capability'] ) ) {
					continue;
				}
				$localized[] = array(
					'menu_slug'  => $page['menu_slug'],
					'menu_title' => $page['menu_title'],
					'page_title' => $page['page_title'],
				);
			}
			if ( ! empty( $localized ) ) {
				wp_localize_script(
					'scf-commands-admin',
					'scfOptionsPages',
					$localized
				);
			}
		}
	}
}

add_action( 'admin_enqueue_scripts', 'acf_commands_init' );
