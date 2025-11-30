<?php
/**
 * SCF Post Type Abilities
 *
 * Handles WordPress Abilities API registration for SCF post type management.
 *
 * @package wordpress/secure-custom-fields
 * @since 6.6.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_Post_Type_Abilities' ) ) :

	/**
	 * SCF Post Type Abilities class.
	 *
	 * Registers and handles all post type management abilities for the
	 * WordPress Abilities API integration. Provides programmatic access
	 * to SCF post type operations.
	 *
	 * @since 6.6.0
	 */
	class SCF_Post_Type_Abilities extends SCF_Internal_Post_Type_Abilities {

		/**
		 * The internal post type identifier.
		 *
		 * @var string
		 */
		protected $internal_post_type = 'acf-post-type';
	}

	// Initialize abilities instance.
	acf_new_instance( 'SCF_Post_Type_Abilities' );

endif; // class_exists check.
