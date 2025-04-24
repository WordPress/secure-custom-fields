<?php
/**
 * Base Experiment Class
 *
 * This class serves as the base for all experiments in Secure Custom Fields.
 *
 * @package    Secure Custom Fields
 * @since      6.4.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Experiment' ) ) :
	/**
	 * Class SCF_Admin_Experiment
	 *
	 * Base class that all experiments must extend. Provides common functionality
	 * for managing experiment settings and UI.
	 *
	 * @package    Secure Custom Fields
	 * @since      6.4.3
	 */
	class SCF_Admin_Experiment {

		/**
		 * The experiment name (unique identifier).
		 *
		 * @var string
		 */
		public $name = '';

		/**
		 * The experiment title.
		 *
		 * @var string
		 */
		public $title = '';

		/**
		 * The experiment description.
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
		 * Initialize the experiment.
		 *
		 * @return void
		 */
		public function initialize() {
			// Override in child classes to initialize experiments.
		}

		/**
		 * Get the experiment status (enabled/disabled).
		 *
		 * @return bool
		 */
		public function is_enabled() {
			return (bool) get_option( 'scf_experiment_' . $this->name . '_enabled', false );
		}

		/**
		 * Enable or disable the experiment.
		 *
		 * @param bool $enabled Whether to enable or disable the experiment.
		 * @return void
		 */
		public function set_enabled( $enabled ) {
			update_option( 'scf_experiment_' . $this->name . '_enabled', (bool) $enabled );
		}

		/**
		 * Clean up any experiment-specific data.
		 * Child classes should override this if they store additional data.
		 *
		 * @return void
		 */
		public function cleanup() {
			delete_option( 'scf_experiment_' . $this->name . '_enabled' );
		}
	}
endif;
