<?php
/**
 * Taxonomy Editor Beta Feature
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_Taxonomy_Editor' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_Taxonomy_Editor
	 *
	 * Enables the experimental DataForm taxonomy editor.
	 */
	class SCF_Admin_Beta_Feature_Taxonomy_Editor extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'taxonomy_editor';
			$this->title       = __( 'Taxonomy Editor', 'secure-custom-fields' );
			$this->description = __( 'Uses WordPress DataForm components to edit taxonomies. The existing editor remains available as a fallback.', 'secure-custom-fields' );
		}
	}
endif;
