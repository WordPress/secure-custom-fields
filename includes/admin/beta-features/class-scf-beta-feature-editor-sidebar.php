<?php
/**
 * Editor Sidebar Beta Feature
 *
 * This beta feature allows moving field group elements to the editor sidebar.
 *
 * @package    Secure Custom Fields
 * @since      6.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_Editor_Sidebar' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_Editor_Sidebar
	 *
	 * Implements a beta feature to move field group elements to the editor sidebar
	 * for a cleaner interface.
	 *
	 * @package    Secure Custom Fields
	 * @since      6.5.0
	 */
	class SCF_Admin_Beta_Feature_Editor_Sidebar extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'editor-sidebar';
			$this->title       = __( 'Move Elements to Editor Sidebar', 'secure-custom-fields' );
			$this->description = __( 'Moves field group elements to the editor sidebar for a cleaner interface.', 'secure-custom-fields' );

			if ( $this->is_enabled() ) {
				add_action( 'admin_init', array( $this, 'setup_beta_feature' ) );
			}
		}
	}
endif;
