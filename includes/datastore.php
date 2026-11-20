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
 * Requires WordPress 6.7+. Controlled by the "Datastore" setting on the Settings page, or the
 * `acf/settings/enable_datastore` filter (applied by acf_get_setting()).
 *
 * @since ACF 6.8.1
 * @since SCF 6.9.10 Reads the "enable_datastore" setting.
 *
 * @return boolean
 */
function acf_is_using_datastore() {
	if ( ! version_compare( get_bloginfo( 'version' ), '6.7', '>=' ) ) {
		return false;
	}

	return (bool) acf_get_setting( 'enable_datastore' );
}
