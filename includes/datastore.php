<?php
/**
 * SCF datastore helpers.
 *
 * @package wordpress/secure-custom-fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Whether the SCF datastore is enabled.
 *
 * The datastore requires WordPress 6.7+ and can be enabled via the
 * `acf/settings/enable_datastore` filter.
 *
 * @since ACF 6.8.1
 *
 * @return boolean
 */
function acf_is_using_datastore() {
	// Bail if not on WordPress 6.7+.
	if ( ! version_compare( get_bloginfo( 'version' ), '6.7', '>=' ) ) {
		return false;
	}

	/**
	 * Filters whether the SCF datastore is enabled.
	 *
	 * @since ACF 6.8.1
	 *
	 * @param boolean $enabled Whether the datastore is enabled. Default false.
	 */
	return (bool) apply_filters( 'acf/settings/enable_datastore', false );
}
