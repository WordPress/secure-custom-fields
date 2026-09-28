<?php
/**
 * Tools Redesign Beta Feature
 *
 * This beta feature replaces the classic Tools screen with a prototype
 * built from WordPress core components.
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_Tools_Redesign' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_Tools_Redesign
	 *
	 * Implements a beta feature that swaps the metabox based Tools screen
	 * for a prototype rendered with @wordpress/components.
	 *
	 * @package    Secure Custom Fields
	 * @since      SCF 6.5.0
	 */
	class SCF_Admin_Beta_Feature_Tools_Redesign extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'tools_redesign';
			$this->title       = __( 'Modern Tools Screen (Prototype)', 'secure-custom-fields' );
			$this->description = __( 'Replaces the import and export tools with a prototype built from WordPress core components.', 'secure-custom-fields' );
		}
	}
endif;
