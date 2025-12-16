<?php
/**
 * SCF Field Group Abilities
 *
 * Handles WordPress Abilities API registration for SCF field group management.
 *
 * @package wordpress/secure-custom-fields
 * @since 6.8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_Field_Group_Abilities' ) ) :

	/**
	 * SCF Field Group Abilities class.
	 *
	 * Registers and handles all field group management abilities for the
	 * WordPress Abilities API integration. Provides programmatic access
	 * to SCF field group operations.
	 *
	 * @since 6.8.0
	 */
	class SCF_Field_Group_Abilities extends SCF_Internal_Post_Type_Abilities {

		/**
		 * The internal post type identifier.
		 *
		 * @var string
		 */
		protected $internal_post_type = 'acf-field-group';
	}

	// Initialize abilities instance.
	acf_new_instance( 'SCF_Field_Group_Abilities' );

endif; // class_exists check.
