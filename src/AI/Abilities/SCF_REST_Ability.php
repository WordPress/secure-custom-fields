<?php
/**
 * ACF 6.8.0 feature port.
 *
 * @package wordpress/secure-custom-fields
 */

// phpcs:disable -- Upstream ACF 6.8.0 feature-port files are kept close to source.

namespace SCF\AI\Abilities;

use WP_Ability;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * SCF REST Ability
 *
 * Custom ability class that extends WP_Ability to skip output validation.
 * This is needed because REST API schemas don't always match Abilities API schemas exactly,
 * but we want to proxy directly to REST API endpoints.
 */
class SCF_REST_Ability extends WP_Ability {

	/**
	 * Override validate_output to always return true.
	 *
	 * Since we're proxying to WordPress REST API endpoints that have their own
	 * validation, we trust their output and skip Abilities API output validation.
	 *
	 * @since 6.8.0
	 *
	 * @param mixed $output The output to validate.
	 * @return true Always returns true to skip validation.
	 */
	protected function validate_output( $output ) {
		return true;
	}
}
