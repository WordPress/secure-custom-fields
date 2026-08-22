<?php
/**
 * Beta feature runtime settings.
 *
 * Bridges each stored beta-feature option to its runtime filter so the toggle
 * in wp-admin works alongside the existing PHP filters.
 *
 * @package wordpress/secure-custom-fields
 * @since SCF 6.9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Enable a setting when its beta feature is enabled.
 *
 * Existing filter callbacks remain in the filter chain, so this adds the
 * beta-feature option as another way to enable the setting.
 *
 * @since SCF 6.9.5
 *
 * @param bool   $enabled The current setting value.
 * @param string $name    The beta feature name.
 * @return bool
 */
function scf_enable_beta_feature_setting( $enabled, $name ) {
	return (bool) $enabled || (bool) get_option( 'scf_beta_feature_' . $name . '_enabled', false );
}

add_filter(
	'acf/settings/enable_datastore',
	function ( $enabled ) {
		return scf_enable_beta_feature_setting( $enabled, 'enable_datastore' );
	}
);
add_filter(
	'acf/settings/enable_acf_ai',
	function ( $enabled ) {
		return scf_enable_beta_feature_setting( $enabled, 'enable_acf_ai' );
	}
);
add_filter(
	'acf/settings/enable_schema',
	function ( $enabled ) {
		return scf_enable_beta_feature_setting( $enabled, 'enable_schema' );
	}
);
