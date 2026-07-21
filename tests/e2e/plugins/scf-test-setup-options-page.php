<?php
/**
 * Plugin Name: SCF Test Setup Options Page
 * Description: Creates SCF options page for E2E testing
 * Version: 1.0.0
 * Author: SCF Testing
 * Requires Plugins: secure-custom-fields
 *
 * @package wordpress/secure-custom-fields
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register an options page for command palette E2E tests.
 */
function scf_test_register_options_page() {
	if ( ! function_exists( 'acf_update_options_page' ) || acf_get_options_page( 'scf-e2e-test-options' ) ) {
		return;
	}

	acf_update_options_page(
		'scf-e2e-test-options',
		array(
			'page_title' => 'SCF E2E Test Options',
			'menu_title' => 'SCF E2E Test Options',
			'menu_slug'  => 'scf-e2e-test-options',
			'capability' => 'edit_posts',
			'redirect'   => false,
		)
	);
}

add_action( 'acf/init', 'scf_test_register_options_page', 20 );
