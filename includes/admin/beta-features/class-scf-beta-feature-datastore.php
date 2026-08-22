<?php
/**
 * Datastore Beta Feature
 *
 * This beta feature enables the SCF datastore.
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_Datastore' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_Datastore
	 *
	 * Implements a beta feature to enable the SCF datastore.
	 *
	 * @package    Secure Custom Fields
	 * @since      SCF 6.9.5
	 */
	class SCF_Admin_Beta_Feature_Datastore extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'enable_datastore';
			$this->title       = __( 'Enable Datastore', 'secure-custom-fields' );
			$this->description = __( 'Enables the SCF datastore for supported WordPress versions.', 'secure-custom-fields' );
		}
	}
endif;
