<?php
/**
 * SCF Field Abilities
 *
 * Handles WordPress Abilities API registration for SCF field management.
 * Unlike other entity types, fields use SCF_Field_Manager adapter instead of
 * extending SCF_Internal_Post_Type_Abilities, as fields are nested under
 * field groups and use a functional API.
 *
 * @package wordpress/secure-custom-fields
 * @since 6.8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_Field_Abilities' ) ) :

	/**
	 * SCF Field Abilities class.
	 *
	 * Registers and handles all field management abilities for the
	 * WordPress Abilities API integration. Provides programmatic access
	 * to SCF field operations.
	 *
	 * @since 6.8.0
	 */
	class SCF_Field_Abilities {

		/**
		 * The field manager adapter instance.
		 *
		 * @var SCF_Field_Manager
		 */
		private $manager = null;

		/**
		 * The field schema.
		 *
		 * @var array|null
		 */
		private $field_schema = null;

		/**
		 * The SCF identifier schema.
		 *
		 * @var array|null
		 */
		private $scf_identifier_schema = null;

		/**
		 * Constructor.
		 *
		 * @since 6.8.0
		 */
		public function __construct() {
			$validator = acf_get_instance( 'SCF_JSON_Schema_Validator' );
			if ( ! $validator->validate_required_schemas() ) {
				return;
			}

			add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		}

		/**
		 * Gets the field manager instance.
		 *
		 * @since 6.8.0
		 *
		 * @return SCF_Field_Manager
		 */
		private function manager() {
			if ( null === $this->manager ) {
				$this->manager = new SCF_Field_Manager();
			}
			return $this->manager;
		}

		/**
		 * Gets the ability category name.
		 *
		 * @since 6.8.0
		 *
		 * @return string
		 */
		private function ability_category() {
			return 'scf-fields';
		}

		/**
		 * Generates an ability name.
		 *
		 * @since 6.8.0
		 *
		 * @param string $action The action (list, get, create, etc.).
		 * @return string E.g., 'scf/list-fields', 'scf/get-field'.
		 */
		private function ability_name( $action ) {
			$suffix = 'list' === $action ? 'fields' : 'field';
			return 'scf/' . $action . '-' . $suffix;
		}

		/**
		 * Gets the entity schema.
		 *
		 * @since 6.8.0
		 *
		 * @return array
		 */
		private function get_field_schema() {
			if ( null === $this->field_schema ) {
				$schema_path        = ACF_PATH . 'schemas/field.schema.json';
				$schema_content     = file_get_contents( $schema_path );
				$this->field_schema = json_decode( $schema_content, true );

				// Resolve $ref references.
				$this->field_schema = $this->resolve_schema_refs( $this->field_schema );
			}
			return $this->field_schema;
		}

		/**
		 * Recursively resolves $ref references in a JSON schema.
		 *
		 * @since 6.8.0
		 *
		 * @param array $schema The schema to resolve.
		 * @return array The resolved schema.
		 */
		private function resolve_schema_refs( array $schema ) {
			// If this object has a $ref, resolve it.
			if ( isset( $schema['$ref'] ) ) {
				$ref = $schema['$ref'];
				if ( strpos( $ref, '#/definitions/' ) === 0 ) {
					$def_name   = substr( $ref, 14 );
					$full       = $this->get_field_schema();
					$definition = isset( $full['definitions'][ $def_name ] ) ? $full['definitions'][ $def_name ] : array();
					if ( empty( $definition ) ) {
						_doing_it_wrong( __METHOD__, 'Unresolvable $ref: ' . esc_html( $ref ), '6.8.0' );
						return $schema;
					}
					// Merge resolved definition with any additional properties.
					unset( $schema['$ref'] );
					$schema = array_merge( $definition, $schema );
				}
			}

			// Recurse into nested arrays/objects.
			foreach ( $schema as $key => $value ) {
				if ( is_array( $value ) ) {
					$schema[ $key ] = $this->resolve_schema_refs( $value );
				}
			}

			return $schema;
		}

		/**
		 * Gets the SCF identifier schema.
		 *
		 * @since 6.8.0
		 *
		 * @return array
		 */
		private function get_scf_identifier_schema() {
			if ( null === $this->scf_identifier_schema ) {
				$schema_path                 = ACF_PATH . 'schemas/scf-identifier.schema.json';
				$schema_content              = file_get_contents( $schema_path );
				$this->scf_identifier_schema = json_decode( $schema_content, true );
			}
			return $this->scf_identifier_schema;
		}

		/**
		 * Gets the internal fields schema for fields.
		 *
		 * @since 6.8.0
		 *
		 * @return array
		 */
		private function get_internal_fields_schema() {
			$validator = new SCF_JSON_Schema_Validator();
			$schema    = $validator->load_schema( 'internal-fields' );
			return json_decode( wp_json_encode( $schema->definitions->fieldInternalFields ), true );
		}

		/**
		 * Gets the field schema merged with internal fields.
		 *
		 * Used for output schemas of GET/LIST/CREATE/UPDATE/DUPLICATE abilities.
		 * Export uses get_field_schema() directly (no internal fields).
		 *
		 * The field schema uses oneOf at the top level with the actual field definition
		 * in definitions.field. Internal fields are merged into that definition.
		 *
		 * @since 6.8.0
		 *
		 * @return array
		 */
		private function get_field_with_internal_fields_schema() {
			$schema   = $this->get_field_schema();
			$internal = $this->get_internal_fields_schema();

			// Merge internal fields into the field definition's properties.
			$schema['definitions']['field']['properties'] = array_merge(
				$schema['definitions']['field']['properties'],
				$internal['properties']
			);

			return $schema;
		}

		/**
		 * Registers ability categories.
		 *
		 * @since 6.8.0
		 */
		public function register_categories() {
			wp_register_ability_category(
				$this->ability_category(),
				array(
					'label'       => __( 'SCF Fields', 'secure-custom-fields' ),
					'description' => __( 'Abilities for managing Secure Custom Fields fields.', 'secure-custom-fields' ),
				)
			);
		}

		/**
		 * Registers all field abilities.
		 *
		 * @since 6.8.0
		 */
		public function register_abilities() {
			$this->register_list_ability();
			$this->register_get_ability();
			$this->register_create_ability();
			$this->register_update_ability();
			$this->register_delete_ability();
			$this->register_duplicate_ability();
			$this->register_export_ability();
			$this->register_import_ability();
			$this->register_trash_ability();
			$this->register_untrash_ability();
		}

		/**
		 * Registers the list ability.
		 *
		 * @since 6.8.0
		 */
		private function register_list_ability() {
			wp_register_ability(
				$this->ability_name( 'list' ),
				array(
					'label'               => __( 'List Fields', 'secure-custom-fields' ),
					'description'         => __( 'Retrieves a list of SCF fields. Returns all if no filter provided. Can filter by parent (field group), type, or name.', 'secure-custom-fields' ),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'list_callback' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => true,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'filter' => array(
								'type'        => 'object',
								'description' => __( 'Filter fields by parent, type, or name.', 'secure-custom-fields' ),
								'properties'  => array(
									'parent' => array(
										'type'        => array( 'integer', 'string' ),
										'description' => __( 'Field group ID or key to filter by.', 'secure-custom-fields' ),
									),
									'type'   => array(
										'type'        => 'string',
										'description' => __( 'Field type to filter by (e.g., text, image).', 'secure-custom-fields' ),
									),
									'name'   => array(
										'type'        => 'string',
										'description' => __( 'Field name to filter by.', 'secure-custom-fields' ),
									),
								),
							),
						),
					),
					'output_schema'       => array(
						'type'  => 'array',
						'items' => $this->get_field_with_internal_fields_schema(),
					),
				)
			);
		}

		/**
		 * Registers the get ability.
		 *
		 * @since 6.8.0
		 */
		private function register_get_ability() {
			wp_register_ability(
				$this->ability_name( 'get' ),
				array(
					'label'               => __( 'Get Field', 'secure-custom-fields' ),
					'description'         => __( 'Retrieves a single SCF field by key or ID.', 'secure-custom-fields' ),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'get_callback' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => true,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'input_schema'        => array(
						'type'       => 'object',
						'required'   => array( 'identifier' ),
						'properties' => array(
							'identifier' => $this->get_scf_identifier_schema(),
						),
					),
					'output_schema'       => $this->get_field_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Registers the create ability.
		 *
		 * @since 6.8.0
		 */
		private function register_create_ability() {
			wp_register_ability(
				$this->ability_name( 'create' ),
				array(
					'label'               => __( 'Create Field', 'secure-custom-fields' ),
					'description'         => __( 'Creates a new SCF field with provided configuration. Requires parent (field group ID).', 'secure-custom-fields' ),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'create_callback' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
						),
					),
					'input_schema'        => $this->get_field_schema(),
					'output_schema'       => $this->get_field_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Registers the update ability.
		 *
		 * @since 6.8.0
		 */
		private function register_update_ability() {
			// Get field properties from field schema.
			$field_schema     = $this->get_field_schema();
			$field_properties = array();
			if ( isset( $field_schema['definitions']['field']['properties'] ) ) {
				$field_properties = $field_schema['definitions']['field']['properties'];
			}

			wp_register_ability(
				$this->ability_name( 'update' ),
				array(
					'label'               => __( 'Update Field', 'secure-custom-fields' ),
					'description'         => __( 'Updates an existing SCF field. Properties not provided are preserved (merge behavior).', 'secure-custom-fields' ),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'update_callback' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'input_schema'        => array(
						'type'       => 'object',
						'required'   => array( 'ID' ),
						'properties' => array_merge(
							array(
								'ID' => array(
									'type'        => 'integer',
									'description' => __( 'The field ID.', 'secure-custom-fields' ),
								),
							),
							$field_properties
						),
					),
					'output_schema'       => $this->get_field_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Registers the delete ability.
		 *
		 * @since 6.8.0
		 */
		private function register_delete_ability() {
			wp_register_ability(
				$this->ability_name( 'delete' ),
				array(
					'label'               => __( 'Delete Field', 'secure-custom-fields' ),
					'description'         => __( 'Permanently deletes an SCF field.', 'secure-custom-fields' ),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'delete_callback' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => true,
							'idempotent'  => true,
						),
					),
					'input_schema'        => array(
						'type'       => 'object',
						'required'   => array( 'identifier' ),
						'properties' => array(
							'identifier' => $this->get_scf_identifier_schema(),
						),
					),
					'output_schema'       => array(
						'type' => 'boolean',
					),
				)
			);
		}

		/**
		 * Registers the duplicate ability.
		 *
		 * @since 6.8.0
		 */
		private function register_duplicate_ability() {
			wp_register_ability(
				$this->ability_name( 'duplicate' ),
				array(
					'label'               => __( 'Duplicate Field', 'secure-custom-fields' ),
					'description'         => __( 'Creates a copy of an SCF field with a new unique key. Optionally specify a new parent field group.', 'secure-custom-fields' ),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'duplicate_callback' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
						),
					),
					'input_schema'        => array(
						'type'       => 'object',
						'required'   => array( 'identifier' ),
						'properties' => array(
							'identifier'    => $this->get_scf_identifier_schema(),
							'new_parent_id' => array(
								'type'        => 'integer',
								'description' => __( 'Optional field group ID to place the duplicate in.', 'secure-custom-fields' ),
							),
						),
					),
					'output_schema'       => $this->get_field_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Registers the export ability.
		 *
		 * @since 6.8.0
		 */
		private function register_export_ability() {
			wp_register_ability(
				$this->ability_name( 'export' ),
				array(
					'label'               => __( 'Export Field', 'secure-custom-fields' ),
					'description'         => __( 'Exports an SCF field as JSON for backup or transfer. Internal fields (ID, local, _valid) are stripped.', 'secure-custom-fields' ),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'export_callback' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => true,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'input_schema'        => array(
						'type'       => 'object',
						'required'   => array( 'identifier' ),
						'properties' => array(
							'identifier' => $this->get_scf_identifier_schema(),
						),
					),
					'output_schema'       => $this->get_field_schema(),
				)
			);
		}

		/**
		 * Registers the import ability.
		 *
		 * @since 6.8.0
		 */
		private function register_import_ability() {
			wp_register_ability(
				$this->ability_name( 'import' ),
				array(
					'label'               => __( 'Import Field', 'secure-custom-fields' ),
					'description'         => __( 'Imports an SCF field from JSON data. If key exists, updates existing; otherwise creates new.', 'secure-custom-fields' ),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'import_callback' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'input_schema'        => $this->get_field_schema(),
					'output_schema'       => $this->get_field_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Registers the trash ability.
		 *
		 * @since 6.8.0
		 */
		private function register_trash_ability() {
			wp_register_ability(
				$this->ability_name( 'trash' ),
				array(
					'label'               => __( 'Trash Field', 'secure-custom-fields' ),
					'description'         => __( 'Moves an SCF field to trash. Can be restored with untrash.', 'secure-custom-fields' ),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'trash_callback' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => true,
							'idempotent'  => true,
						),
					),
					'input_schema'        => array(
						'type'       => 'object',
						'required'   => array( 'identifier' ),
						'properties' => array(
							'identifier' => $this->get_scf_identifier_schema(),
						),
					),
					'output_schema'       => array(
						'type' => 'boolean',
					),
				)
			);
		}

		/**
		 * Registers the untrash ability.
		 *
		 * @since 6.8.0
		 */
		private function register_untrash_ability() {
			wp_register_ability(
				$this->ability_name( 'untrash' ),
				array(
					'label'               => __( 'Untrash Field', 'secure-custom-fields' ),
					'description'         => __( 'Restores an SCF field from trash.', 'secure-custom-fields' ),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'untrash_callback' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'input_schema'        => array(
						'type'       => 'object',
						'required'   => array( 'identifier' ),
						'properties' => array(
							'identifier' => $this->get_scf_identifier_schema(),
						),
					),
					'output_schema'       => array(
						'type' => 'boolean',
					),
				)
			);
		}

		/**
		 * Handles the list ability callback.
		 *
		 * @since 6.8.0
		 *
		 * @param array $input The input parameters.
		 * @return array List of fields.
		 */
		public function list_callback( $input ) {
			$filter = isset( $input['filter'] ) && is_array( $input['filter'] ) ? $input['filter'] : array();
			return $this->manager()->filter_posts( $this->manager()->get_posts(), $filter );
		}

		/**
		 * Handles the get ability callback.
		 *
		 * @since 6.8.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error Field data or error if not found.
		 */
		public function get_callback( $input ) {
			$field = $this->manager()->get_post( $input['identifier'] );
			if ( ! $field ) {
				return $this->not_found_error();
			}
			return $field;
		}

		/**
		 * Handles the create ability callback.
		 *
		 * @since 6.8.0
		 *
		 * @param array $input The field data to create.
		 * @return array|WP_Error Created field or error on failure.
		 */
		public function create_callback( $input ) {
			// Check for existing field with same key.
			if ( isset( $input['key'] ) && $this->manager()->get_post( $input['key'] ) ) {
				return new WP_Error(
					'already_exists',
					__( 'Field with this key already exists.', 'secure-custom-fields' )
				);
			}

			if ( empty( $input['parent'] ) ) {
				return new WP_Error(
					'ability_invalid_input',
					__( 'Field requires a parent (field group ID).', 'secure-custom-fields' ),
					array( 'status' => 400 )
				);
			}

			if ( ! $this->parent_exists( $input['parent'] ) ) {
				return new WP_Error(
					'parent_not_found',
					__( 'Parent field group or field does not exist.', 'secure-custom-fields' ),
					array( 'status' => 400 )
				);
			}

			$field = $this->manager()->update_post( $input );
			if ( ! $field ) {
				return new WP_Error(
					'create_failed',
					__( 'Failed to create field.', 'secure-custom-fields' )
				);
			}
			return $field;
		}

		/**
		 * Handles the update ability callback.
		 *
		 * @since 6.8.0
		 *
		 * @param array $input The field data to update.
		 * @return array|WP_Error Updated field or error on failure.
		 */
		public function update_callback( $input ) {
			if ( empty( $input['ID'] ) ) {
				return new WP_Error(
					'ability_invalid_input',
					__( 'Field ID is required for update.', 'secure-custom-fields' ),
					array( 'status' => 400 )
				);
			}

			$existing = $this->manager()->get_post( $input['ID'] );
			if ( ! $existing ) {
				return $this->not_found_error();
			}

			// Merge with existing data.
			$updated_data = array_merge( $existing, $input );
			$field        = $this->manager()->update_post( $updated_data );

			if ( ! $field ) {
				return new WP_Error(
					'update_failed',
					__( 'Failed to update field.', 'secure-custom-fields' )
				);
			}
			return $field;
		}

		/**
		 * Handles the delete ability callback.
		 *
		 * @since 6.8.0
		 *
		 * @param array $input The input parameters.
		 * @return bool|WP_Error True on success or error on failure.
		 */
		public function delete_callback( $input ) {
			if ( ! $this->manager()->get_post( $input['identifier'] ) ) {
				return $this->not_found_error();
			}

			if ( ! $this->manager()->delete_post( $input['identifier'] ) ) {
				return new WP_Error(
					'delete_failed',
					__( 'Failed to delete field.', 'secure-custom-fields' )
				);
			}
			return true;
		}

		/**
		 * Handles the duplicate ability callback.
		 *
		 * @since 6.8.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error Duplicated field or error on failure.
		 */
		public function duplicate_callback( $input ) {
			if ( ! $this->manager()->get_post( $input['identifier'] ) ) {
				return $this->not_found_error();
			}

			$new_parent_id = isset( $input['new_parent_id'] ) ? $input['new_parent_id'] : 0;

			// Validate that new_parent_id references an existing field group.
			if ( $new_parent_id ) {
				$parent_group = acf_get_field_group( $new_parent_id );
				if ( ! $parent_group ) {
					return new WP_Error(
						'invalid_new_parent_id',
						sprintf(
							/* translators: %d: Invalid field group ID */
							__( 'Invalid new_parent_id: %d is not a valid field group.', 'secure-custom-fields' ),
							$new_parent_id
						),
						array( 'status' => 400 )
					);
				}
			}

			$duplicated = $this->manager()->duplicate_post( $input['identifier'], $new_parent_id );

			if ( ! $duplicated ) {
				return new WP_Error(
					'duplicate_failed',
					__( 'Failed to duplicate field.', 'secure-custom-fields' )
				);
			}
			return $duplicated;
		}

		/**
		 * Handles the export ability callback.
		 *
		 * @since 6.8.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error Exported field data or error on failure.
		 */
		public function export_callback( $input ) {
			$field = $this->manager()->get_post( $input['identifier'] );
			if ( ! $field ) {
				return $this->not_found_error();
			}

			$export = $this->manager()->prepare_post_for_export( $field );
			if ( ! $export ) {
				return new WP_Error(
					'export_failed',
					__( 'Failed to export field.', 'secure-custom-fields' )
				);
			}
			return $export;
		}

		/**
		 * Handles the import ability callback.
		 *
		 * @since 6.8.0
		 *
		 * @param array|object $input The field data to import.
		 * @return array|WP_Error Imported field or error on failure.
		 */
		public function import_callback( $input ) {
			if ( empty( $input['parent'] ) ) {
				return new WP_Error(
					'ability_invalid_input',
					__( 'Field requires a parent (field group ID).', 'secure-custom-fields' ),
					array( 'status' => 400 )
				);
			}

			if ( ! $this->parent_exists( $input['parent'] ) ) {
				return new WP_Error(
					'parent_not_found',
					__( 'Parent field group or field does not exist.', 'secure-custom-fields' ),
					array( 'status' => 400 )
				);
			}

			$imported = $this->manager()->import_post( $input );
			if ( ! $imported ) {
				return new WP_Error(
					'import_failed',
					__( 'Failed to import field.', 'secure-custom-fields' )
				);
			}
			return $imported;
		}

		/**
		 * Handles the trash ability callback.
		 *
		 * @since 6.8.0
		 *
		 * @param array $input The input parameters.
		 * @return bool|WP_Error True on success or error on failure.
		 */
		public function trash_callback( $input ) {
			if ( ! $this->manager()->get_post( $input['identifier'] ) ) {
				return $this->not_found_error();
			}

			if ( ! $this->manager()->trash_post( $input['identifier'] ) ) {
				return new WP_Error(
					'trash_failed',
					__( 'Trash operation failed.', 'secure-custom-fields' )
				);
			}
			return true;
		}

		/**
		 * Handles the untrash ability callback.
		 *
		 * @since 6.8.0
		 *
		 * @param array $input The input parameters.
		 * @return bool|WP_Error True on success or error on failure.
		 */
		public function untrash_callback( $input ) {
			if ( ! $this->manager()->untrash_post( $input['identifier'] ) ) {
				return new WP_Error(
					'untrash_failed',
					__( 'Untrash operation failed.', 'secure-custom-fields' )
				);
			}
			return true;
		}

		/**
		 * Checks if the parent field group or field exists.
		 *
		 * @since 6.8.0
		 *
		 * @param int|string $parent_id The parent ID or key.
		 * @return bool True if parent exists, false otherwise.
		 */
		private function parent_exists( $parent_id ) {
			// Test mock support.
			if ( function_exists( 'get_mock_parent_exists' ) ) {
				$mock_result = get_mock_parent_exists( $parent_id );
				if ( null !== $mock_result ) {
					return $mock_result;
				}
			}

			// Parent can be a field group or a parent field (for sub-fields).
			return acf_get_field_group( $parent_id ) || acf_get_field( $parent_id );
		}

		/**
		 * Creates a not found error response.
		 *
		 * @since 6.8.0
		 *
		 * @return WP_Error
		 */
		private function not_found_error() {
			return new WP_Error(
				'not_found',
				__( 'Field not found.', 'secure-custom-fields' ),
				array( 'status' => 404 )
			);
		}
	}

	// Initialize abilities instance.
	acf_new_instance( 'SCF_Field_Abilities' );

endif; // class_exists check.
