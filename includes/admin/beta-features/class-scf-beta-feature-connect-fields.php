<?php
/**
 * Connect Fields Feature
 *
 * This beta feature allows moving field group elements to the editor sidebar.
 *
 * @package    Secure Custom Fields
 * @since      SCF 6.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'SCF_Admin_Beta_Feature_Connect_Fields' ) ) :
	/**
	 * Class SCF_Admin_Beta_Feature_Connect_Fields
	 *
	 * Implements a beta feature that connects fields to compatible block attributes.
	 *
	 * @package    Secure Custom Fields
	 * @since      SCF 6.5.0
	 */
	class SCF_Admin_Beta_Feature_Connect_Fields extends SCF_Admin_Beta_Feature {

		/**
		 * Initialize the beta feature.
		 *
		 * @return void
		 */
		protected function initialize() {
			$this->name        = 'connect_fields';
			$this->title       = __( 'Connect Fields', 'secure-custom-fields' );
			$this->description = __( 'Connects field to binding compatible blocks.', 'secure-custom-fields' );
		}
	}
endif;
