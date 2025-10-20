<?php
/**
 * This file is for the converting of sites from acf.php to secure-custom-fields.php as the main plugin file.
 *
 * Under this slug, acf.php would have been the main plugin file in < 6.4.0-beta4.
 *
 * @package wordpress/secure-custom-fields
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_admin() ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	// Network activations first since is_plugin_active will return true for network activated plugins, whereas this won't for single.
	if ( is_plugin_active_for_network( 'secure-custom-fields/acf.php' ) ) {
		if ( ! activate_plugin( 'secure-custom-fields/secure-custom-fields.php', '', true, true ) ) {
			// activate_plugin returns null on success or WP_Error on failure.
			deactivate_plugins( 'secure-custom-fields/acf.php', false, true );
		}
	}

	// Single site activations.
	if ( is_plugin_active( 'secure-custom-fields/acf.php' ) ) {
		if ( ! activate_plugin( 'secure-custom-fields/secure-custom-fields.php', '', false, true ) ) {
			// activate_plugin returns null on success or WP_Error on failure.
			deactivate_plugins( 'secure-custom-fields/acf.php', false, false );
		}
	}
}

// Include the main plugin file to ensure it's loaded if the switch hasn't occurred.
require_once __DIR__ . '/secure-custom-fields.php';
