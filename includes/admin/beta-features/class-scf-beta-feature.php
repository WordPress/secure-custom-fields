<?php
/**
 * Base Beta Feature Class
 *
 * This class serves as the base for all beta features in Secure Custom Fields.
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature
	 *
	 * Base class that all beta features must extend. Provides common functionality
	 * for managing beta feature settings and UI.
	 *
	 * @package    Secure Custom Fields
	 * @since      SCF 6.5.0
	 */
	class SCF_Admin_Beta_Feature {

		/**
		 * The beta feature name (unique identifier).
		 *
		 * @var string
		 */
		public $name = '';

		/**
		 * The beta feature title.
		 *
		 * @var string
		 */
		public $title = '';

		/**
		 * The beta feature description.
		 *
		 * @var string
		 */
		public $description = '';

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->initialize();
		}

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			// Override in child classes to initialize beta features.
		}

		/**
		 * Get the beta feature status (enabled/disabled).
		 *
		 * @return bool
		 */
		public function is_enabled() {
			return (bool) get_option( 'scf_beta_feature_' . $this->name . '_enabled', false );
		}

		/**
		 * Enable or disable the beta feature.
		 *
		 * @param bool $enabled Whether to enable or disable the beta feature.
		 * @return void
		 */
		public function set_enabled( $enabled ) {
			update_option( 'scf_beta_feature_' . $this->name . '_enabled', (bool) $enabled );
		}

		/**
		 * Clean up any beta feature-specific data.
		 * Child classes should override this if they store additional data.
		 *
		 * @return void
		 */
		public function cleanup() {
			delete_option( 'scf_beta_feature_' . $this->name . '_enabled' );
		}
	}
endif;
