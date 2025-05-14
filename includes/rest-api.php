<?php
/**
 * REST API
 *
 * @package    Secure Custom Fields
 * @since      ACF 6.4.0
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

acf_new_instance( 'ACF_Rest_Api' );
acf_new_instance( 'SCF_Rest_Types_Endpoint' );
