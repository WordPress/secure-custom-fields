<?php
/**
 * SCF UI Options Page Abilities
 *
 * Handles WordPress Abilities API registration for SCF UI options page management.
 *
 * @package wordpress/secure-custom-fields
 * @since 6.8.0
 * @codeCoverageIgnore Base class is tested.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_UI_Options_Page_Abilities' ) ) :

	/**
	 * SCF UI Options Page Abilities class.
	 *
	 * Registers and handles all UI options page management abilities for the
	 * WordPress Abilities API integration. Provides programmatic access
	 * to SCF UI options page operations.
	 *
	 * @since 6.8.0
	 */
	class SCF_UI_Options_Page_Abilities extends SCF_Internal_Post_Type_Abilities {

		/**
		 * The internal post type identifier.
		 *
		 * @var string
		 */
		protected $internal_post_type = 'acf-ui-options-page';
	}

	// Initialize abilities instance.
	acf_new_instance( 'SCF_UI_Options_Page_Abilities' );

endif; // class_exists check.
