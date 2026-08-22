<?php
/**
 * ACF AI Beta Feature
 *
 * This beta feature enables ACF AI support.
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_ACF_AI' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_ACF_AI
	 *
	 * Implements a beta feature to enable ACF AI support.
	 *
	 * @package    Secure Custom Fields
	 * @since      SCF 6.9.5
	 */
	class SCF_Admin_Beta_Feature_ACF_AI extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'enable_acf_ai';
			$this->title       = __( 'Enable ACF AI', 'secure-custom-fields' );
			$this->description = __( 'Enables ACF AI support.', 'secure-custom-fields' );
		}
	}
endif;
