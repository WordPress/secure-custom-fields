<?php
/**
 * Create Taxonomies Beta Feature
 *
 * This beta feature allows creating taxonomies from the block editor sidebar.
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.9.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_Create_Taxonomies' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_Create_Taxonomies
	 *
	 * Implements a beta feature to create new taxonomies directly from the
	 * block editor inspector sidebar, without leaving the editing screen.
	 *
	 * @package    Secure Custom Fields
	 * @since      SCF 6.9.6
	 */
	class SCF_Admin_Beta_Feature_Create_Taxonomies extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'create_taxonomies';
			$this->title       = __( 'Create Taxonomies in the Editor', 'secure-custom-fields' );
			$this->description = __( 'Adds a panel to the block editor sidebar for creating new taxonomies in context.', 'secure-custom-fields' );

			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		}

		/**
		 * Enqueues the sidebar panel script when the feature is enabled.
		 *
		 * @return void
		 */
		public function enqueue_block_editor_assets() {
			if ( ! $this->is_enabled() ) {
				return;
			}

			wp_enqueue_script( 'scf-create-taxonomies' );
		}
	}
endif;
