<?php
/**
 * WordPress Abilities API integration for Secure Custom Fields.
 *
 * @package SCF
 * @since 6.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_Abilities_Integration' ) ) {

	/**
	 * Handles integration with WordPress Abilities API.
	 *
	 * @since 6.6.0
	 * @codeCoverageIgnore Glue code tested implicitly via E2E tests.
	 */
	class SCF_Abilities_Integration {

		/**
		 * Constructor.
		 *
		 * @since 6.6.0
		 */
		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		}

		/**
		 * Initialize abilities integration if dependencies are available.
		 *
		 * @since 6.6.0
		 */
		public function init() {
			if ( ! $this->dependencies_available() ) {
				return;
			}

			acf_include( 'includes/abilities/class-scf-internal-post-type-abilities.php' );
			acf_include( 'includes/abilities/class-scf-post-type-abilities.php' );
			acf_include( 'includes/abilities/class-scf-taxonomy-abilities.php' );
			acf_include( 'includes/abilities/class-scf-ui-options-page-abilities.php' );
			acf_include( 'includes/abilities/class-scf-field-group-abilities.php' );
			acf_include( 'includes/abilities/class-scf-field-abilities.php' );
		}

		/**
		 * Check if required dependencies are available.
		 *
		 * @since 6.6.0
		 * @return bool True if dependencies are available.
		 */
		private function dependencies_available() {
			return function_exists( 'wp_register_ability' )
				&& function_exists( 'wp_register_ability_category' );
		}
	}

	acf_new_instance( 'SCF_Abilities_Integration' );
}
