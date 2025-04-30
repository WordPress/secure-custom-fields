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

// Always initialize SCF_Rest_Types_Endpoint for the origin parameter functionality
// The class will internally check for the beta feature status for field groups registration
acf_new_instance( 'SCF_Rest_Types_Endpoint' );