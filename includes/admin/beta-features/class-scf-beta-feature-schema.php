<?php
/**
 * Schema Beta Feature
 *
 * This beta feature enables schema support.
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_Schema' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_Schema
	 *
	 * Implements a beta feature to enable schema support.
	 *
	 * @package    Secure Custom Fields
	 * @since      SCF 6.9.5
	 */
	class SCF_Admin_Beta_Feature_Schema extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'enable_schema';
			$this->title       = __( 'Enable Schema Support', 'secure-custom-fields' );
			$this->description = __( 'Enables schema support.', 'secure-custom-fields' );
		}
	}
endif;
