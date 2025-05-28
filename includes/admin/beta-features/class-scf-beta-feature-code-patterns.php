<?php
/**
 * Editor Sidebar Beta Feature
 *
 * This beta feature allows moving field group elements to the editor sidebar.
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_Code_Patterns' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_Code_Patterns
	 *
	 * Implements a beta feature for managing code patterns.
	 *
	 * @package    Secure Custom Fields
	 * @since      SCF 6.5.0
	 */
	class SCF_Admin_Beta_Feature_Code_Patterns extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'code_patterns';
			$this->title       = __( 'Add SCF Code Patterns', 'secure-custom-fields' );
			$this->description = __( 'Provides an API to register code patterns.', 'secure-custom-fields' );
		}
	}
endif;
