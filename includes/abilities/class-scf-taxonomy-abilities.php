<?php
/**
 * SCF Taxonomy Abilities
 *
 * Handles WordPress Abilities API registration for SCF taxonomy management.
 *
 * @package wordpress/secure-custom-fields
 * @since 6.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_Taxonomy_Abilities' ) ) :

	/**
	 * SCF Taxonomy Abilities class.
	 *
	 * Registers and handles all taxonomy management abilities for the
	 * WordPress Abilities API integration. Provides programmatic access
	 * to SCF taxonomy operations.
	 *
	 * @since 6.7.0
	 */
	class SCF_Taxonomy_Abilities extends SCF_Internal_Post_Type_Abilities {

		/**
		 * The internal post type identifier.
		 *
		 * @var string
		 */
		protected $internal_post_type = 'acf-taxonomy';
	}

	// Initialize abilities instance.
	acf_new_instance( 'SCF_Taxonomy_Abilities' );

endif; // class_exists check.
