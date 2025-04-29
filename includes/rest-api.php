<?php
/**
 * REST API
 *
 * @package    Secure Custom Fields
 * @since      6.5.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

acf_include( 'includes/rest-api/acf-rest-api-functions.php' );
acf_include( 'includes/rest-api/class-acf-rest-api.php' );
acf_include( 'includes/rest-api/class-acf-rest-embed-links.php' );
acf_include( 'includes/rest-api/class-acf-rest-request.php' );
acf_include( 'includes/rest-api/class-acf-rest-types-endpoint.php' );
// Initialize.
acf_new_instance( 'ACF_Rest_Api' );

// Initialize SCF_Rest_Types_Endpoint only if the editor sidebar beta feature is enabled
if ( get_option( 'scf_beta_feature_editor-sidebar_enabled', true ) ) {
	acf_new_instance( 'SCF_Rest_Types_Endpoint' );
}
