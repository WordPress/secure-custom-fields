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

		/**
		 * Handles the list ability callback.
		 *
		 * Overrides parent to ignore location rules. Field groups use location rules
		 * to determine which edit screens they appear on - this is a UX feature for
		 * showing contextually relevant field groups, not access control.
		 *
		 * The abilities API should return all field groups regardless of location
		 * rules, so we bypass that filtering while preserving other filters like
		 * active status.
		 *
		 * @since 6.8.0
		 *
		 * @param array $input The input parameters.
		 * @return array List of field groups.
		 */
		public function list_callback( $input ) {
			// Ensure filter is an array (REST API may pass empty string for empty filter).
			$filter = isset( $input['filter'] ) && is_array( $input['filter'] ) ? $input['filter'] : array();

			// Location rules are a UX feature for edit screens, not access control.
			$filter['ignore_location_rules'] = true;

			$instance = acf_get_internal_post_type_instance( $this->internal_post_type );
			return $instance->filter_posts( $instance->get_posts(), $filter );
		}
	}

	// Initialize abilities instance.
	acf_new_instance( 'SCF_Field_Group_Abilities' );

endif; // class_exists check.
