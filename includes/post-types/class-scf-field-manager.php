<?php
/**
 * SCF Field Manager
 *
 * Manages field operations by wrapping ACF field functions.
 *
 * @package wordpress/secure-custom-fields
 * @since 6.8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_Field_Manager' ) ) :

	/**
	 * SCF Field Manager class.
	 *
	 * @since 6.8.0
	 */
	class SCF_Field_Manager {

		/**
		 * The post type for fields.
		 *
		 * @var string
		 */
		public $post_type = 'acf-field';

		/**
		 * The key prefix for fields.
		 *
		 * @var string
		 */
		public $post_key_prefix = 'field_';

		/**
		 * Gets a field by ID or key.
		 *
		 * @since 6.8.0
		 *
		 * @param int|string $id The field ID or key.
		 * @return array|false The field array or false if not found.
		 */
		public function get_post( $id = 0 ) {
			return acf_get_field( $id );
		}

		/**
		 * Gets all fields across all field groups.
		 *
		 * Unlike internal post types which are top-level, fields are children
		 * of field groups. This method aggregates fields from all field groups.
		 *
		 * @since 6.8.0
		 *
		 * @return array Array of all fields.
		 */
		public function get_posts() {
			$all_fields   = array();
			$field_groups = acf_get_field_groups();

			foreach ( $field_groups as $field_group ) {
				$fields = acf_get_fields( $field_group );
				if ( $fields ) {
					$all_fields = array_merge( $all_fields, $fields );
				}
			}

			return $all_fields;
		}

		/**
		 * Filters fields based on provided arguments.
		 *
		 * Supports filtering by:
		 * - parent: Field group ID or key
		 * - type: Field type (text, image, etc.)
		 * - name: Field name
		 *
		 * @since 6.8.0
		 *
		 * @param array $posts Array of fields to filter.
		 * @param array $args  Filter arguments.
		 * @return array Filtered fields.
		 */
		public function filter_posts( $posts, $args = array() ) {
			if ( isset( $args['parent'] ) ) {
				$parent_filter = $args['parent'];

				// Convert key to ID if not numeric (same pattern as acf_update_field).
				if ( $parent_filter && ! is_numeric( $parent_filter ) ) {
					$parent_post   = acf_get_field_post( $parent_filter );
					$parent_filter = $parent_post ? $parent_post->ID : 0;
				}

				$parent_filter = (int) $parent_filter;
				$posts         = array_filter(
					$posts,
					function ( $post ) use ( $parent_filter ) {
						return (int) $post['parent'] === $parent_filter;
					}
				);
			}

			if ( isset( $args['type'] ) ) {
				$type_filter = $args['type'];
				$posts       = array_filter(
					$posts,
					function ( $post ) use ( $type_filter ) {
						return $post['type'] === $type_filter;
					}
				);
			}

			if ( isset( $args['name'] ) ) {
				$name_filter = $args['name'];
				$posts       = array_filter(
					$posts,
					function ( $post ) use ( $name_filter ) {
						return $post['name'] === $name_filter;
					}
				);
			}

			return array_values( $posts );
		}

		/**
		 * Updates or creates a field.
		 *
		 * @since 6.8.0
		 *
		 * @param array $field The field data.
		 * @return array|false The updated field or false on failure.
		 */
		public function update_post( $field ) {
			return acf_update_field( $field );
		}

		/**
		 * Permanently deletes a field.
		 *
		 * @since 6.8.0
		 *
		 * @param int|string $id The field ID or key.
		 * @return bool True on success, false on failure.
		 */
		public function delete_post( $id = 0 ) {
			return acf_delete_field( $id );
		}

		/**
		 * Duplicates a field.
		 *
		 * @since 6.8.0
		 *
		 * @param int|string $id          The field ID or key to duplicate.
		 * @param int        $new_post_id Optional parent ID for the duplicate.
		 * @return array|false The duplicated field or false on failure.
		 */
		public function duplicate_post( $id = 0, $new_post_id = 0 ) {
			$field = $this->get_post( $id );
			if ( ! $field ) {
				return false;
			}

			$parent_id = $new_post_id ? $new_post_id : $field['parent'];
			return acf_duplicate_field( $field['ID'], $parent_id );
		}

		/**
		 * Prepares a field for export.
		 *
		 * Strips internal fields (ID, local, _valid) that shouldn't be exported.
		 *
		 * @since 6.8.0
		 *
		 * @param array $field The field to prepare.
		 * @return array The prepared field.
		 */
		public function prepare_post_for_export( $field = array() ) {
			acf_extract_vars( $field, array( 'ID', 'local', '_valid', '_name', 'prefix', 'value', 'id', 'class' ) );

			/** This filter is documented in includes/acf-field-group-functions.php */
			return apply_filters( 'acf/prepare_field_for_export', $field );
		}

		/**
		 * Imports a field.
		 *
		 * @since 6.8.0
		 *
		 * @param array $field The field data to import.
		 * @return array|false The imported field or false on failure.
		 */
		public function import_post( $field ) {
			$filters = acf_disable_filters();
			$field   = acf_validate_field( $field );
			$field   = acf_update_field( $field );
			acf_enable_filters( $filters );

			/** This action is documented in includes/acf-field-group-functions.php */
			do_action( 'acf/import_field', $field );

			return $field;
		}
	}

endif;
