<?php
/**
 * Nested Post Types Beta Feature
 *
 * This beta feature allows items of one custom post type to nest under items
 * of another custom post type, with a combined URL.
 *
 * @package    Secure Custom Fields
 * @since      SCF {NEXT_MAJOR_VERSION}
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_Nested_Post_Types' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_Nested_Post_Types
	 *
	 * Implements a beta feature for nesting one custom post type under another.
	 *
	 * @since SCF {NEXT_MAJOR_VERSION}
	 */
	class SCF_Admin_Beta_Feature_Nested_Post_Types extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'nested_post_types';
			$this->title       = __( 'Nested Custom Post Types', 'secure-custom-fields' );
			$this->description = __( 'Let items of one custom post type nest under items of another, combining their URLs.', 'secure-custom-fields' );
		}

		/**
		 * Clean up any beta feature-specific data.
		 *
		 * @return void
		 */
		public function cleanup() {
			parent::cleanup();
			delete_option( 'rewrite_rules' );
		}
	}
endif;
