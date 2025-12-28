<?php
/**
 * SCF Internal Post Type Abilities Base Class
 *
 * Abstract base class for WordPress Abilities API registration for SCF internal post types.
 * Delegates operations to ACF_Internal_Post_Type subclass instances.
 *
 * @package wordpress/secure-custom-fields
 * @since 6.7.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_Internal_Post_Type_Abilities' ) ) :

	/**
	 * SCF Internal Post Type Abilities base class.
	 *
	 * Child classes only need to set $internal_post_type property.
	 * Everything else is derived from the ACF_Internal_Post_Type instance.
	 *
	 * @since 6.7.0
	 */
	abstract class SCF_Internal_Post_Type_Abilities {

		/**
		 * The internal post type identifier (e.g., 'acf-post-type', 'acf-taxonomy').
		 * Child classes MUST set this property.
		 *
		 * @var string
		 */
		protected $internal_post_type;

		/**
		 * Cached internal post type instance.
		 *
		 * @var ACF_Internal_Post_Type|null
		 */
		private $instance = null;

		/**
		 * Cached entity schema.
		 *
		 * @var array|null
		 */
		private $entity_schema = null;

		/**
		 * Cached SCF identifier schema.
		 *
		 * @var array|null
		 */
		private $scf_identifier_schema = null;

		/**
		 * Constructor.
		 *
		 * @since 6.7.0
		 */
		public function __construct() {
			if ( empty( $this->internal_post_type ) ) {
				_doing_it_wrong( __METHOD__, 'Child classes must set $internal_post_type property.', '6.7.0' );
				return;
			}

			$validator = acf_get_instance( 'SCF_JSON_Schema_Validator' );
			if ( ! $validator->validate_required_schemas() ) {
				return;
			}

			add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		}

		/**
		 * Gets the internal post type instance.
		 *
		 * @return ACF_Internal_Post_Type
		 */
		private function instance() {
			if ( null === $this->instance ) {
				$this->instance = acf_get_internal_post_type_instance( $this->internal_post_type );
			}
			return $this->instance;
		}

		/**
		 * Gets entity name, e.g., 'post type', 'taxonomy'.
		 *
		 * @return string
		 */
		private function entity_name() {
			return str_replace( '_', ' ', $this->instance()->hook_name );
		}

		/**
		 * Gets entity name plural, e.g., 'post types', 'taxonomies'.
		 *
		 * @return string
		 */
		private function entity_name_plural() {
			return str_replace( '_', ' ', $this->instance()->hook_name_plural );
		}

		/**
		 * Gets schema name, e.g., 'post-type', 'taxonomy'.
		 *
		 * @return string
		 */
		private function schema_name() {
			return str_replace( '_', '-', $this->instance()->hook_name );
		}

		/**
		 * Gets ability category, e.g., 'scf-post-types', 'scf-taxonomies'.
		 *
		 * @return string
		 */
		private function ability_category() {
			return 'scf-' . str_replace( '_', '-', $this->instance()->hook_name_plural );
		}

		/**
		 * Gets ability name for an action.
		 *
		 * @param string $action E.g., 'list', 'get', 'create'.
		 * @return string E.g., 'scf/list-post-types', 'scf/get-post-type'.
		 */
		private function ability_name( $action ) {
			$slug = str_replace( '_', '-', 'list' === $action ? $this->instance()->hook_name_plural : $this->instance()->hook_name );
			return 'scf/' . $action . '-' . $slug;
		}

		// Schema methods.

		/**
		 * Gets the entity schema from JSON schema file.
		 *
		 * @return array
		 */
		private function get_entity_schema() {
			if ( null === $this->entity_schema ) {
				$validator = new SCF_JSON_Schema_Validator();
				$schema    = $validator->load_schema( $this->schema_name() );

				// Convert to array for processing.
				$schema_array = json_decode( wp_json_encode( $schema ), true );

				// Convert hook_name to camelCase for schema definition key (post_type → postType).
				$def_key = lcfirst( str_replace( ' ', '', ucwords( $this->entity_name() ) ) );
				$entity  = $schema_array['definitions'][ $def_key ] ?? array();

				// Resolve $ref references for WordPress Abilities API compatibility.
				$builder             = acf_get_instance( 'SCF_Schema_Builder' );
				$this->entity_schema = $builder->resolve_refs( $entity, $schema_array );
			}
			return $this->entity_schema;
		}

		/**
		 * Gets the SCF identifier schema.
		 *
		 * @return array
		 */
		private function get_scf_identifier_schema() {
			if ( null === $this->scf_identifier_schema ) {
				$validator                   = new SCF_JSON_Schema_Validator();
				$this->scf_identifier_schema = json_decode( wp_json_encode( $validator->load_schema( 'scf-identifier' ) ), true );
			}
			return $this->scf_identifier_schema;
		}

		/**
		 * Gets the internal fields schema.
		 *
		 * Resolves $ref references since WordPress REST API doesn't understand them.
		 *
		 * @return array
		 */
		private function get_internal_fields_schema() {
			$validator      = new SCF_JSON_Schema_Validator();
			$schema         = $validator->load_schema( 'internal-properties' );
			$schema_array   = json_decode( wp_json_encode( $schema ), true );
			$internal_props = $schema_array['definitions']['internalProperties'] ?? array();

			// Resolve $refs for WordPress REST API compatibility.
			$builder = acf_get_instance( 'SCF_Schema_Builder' );
			return $builder->resolve_refs( $internal_props, $schema_array );
		}

		/**
		 * Gets the entity schema merged with internal fields.
		 *
		 * @return array
		 */
		private function get_entity_with_internal_fields_schema() {
			$schema               = $this->get_entity_schema();
			$internal             = $this->get_internal_fields_schema();
			$schema['properties'] = array_merge( $schema['properties'], $internal['properties'] );
			return $schema;
		}

		// Registration.

		/**
		 * Registers the ability category.
		 *
		 * @return void
		 */
		public function register_categories() {
			wp_register_ability_category(
				$this->ability_category(),
				array(
					'label'       => sprintf(
						/* translators: %s: Entity type plural, e.g., 'Post Types' */
						__( 'SCF %s', 'secure-custom-fields' ),
						ucwords( $this->entity_name_plural() )
					),
					'description' => sprintf(
						/* translators: %s: Entity type plural, e.g., 'post types' */
						__( 'Abilities for managing Secure Custom Fields %s.', 'secure-custom-fields' ),
						$this->entity_name_plural()
					),
				)
			);
		}

		/**
		 * Registers all abilities for this entity type.
		 *
		 * @return void
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
		 * @return void
		 */
		private function register_list_ability() {
			wp_register_ability(
				$this->ability_name( 'list' ),
				array(
					'label'               => sprintf(
						/* translators: %s: Entity type plural */
						__( 'List %s', 'secure-custom-fields' ),
						ucwords( $this->entity_name_plural() )
					),
					'description'         => sprintf(
						/* translators: %s: Entity type plural */
						__( 'Retrieves a list of SCF %s. Returns all if no filter provided.', 'secure-custom-fields' ),
						$this->entity_name_plural()
					),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'list_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => true,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'filter' => array(
								'type'        => 'object',
								'description' => __( 'Optional filters to apply to results.', 'secure-custom-fields' ),
								'properties'  => array(
									'active' => array(
										'type'        => 'boolean',
										'description' => __( 'Filter by active status.', 'secure-custom-fields' ),
									),
								),
							),
						),
					),
					'output_schema'       => array(
						'type'  => 'array',
						'items' => $this->get_entity_with_internal_fields_schema(),
					),
				)
			);
		}

		/**
		 * Registers the get ability.
		 *
		 * @return void
		 */
		private function register_get_ability() {
			wp_register_ability(
				$this->ability_name( 'get' ),
				array(
					'label'               => sprintf(
						/* translators: %s: Entity type */
						__( 'Get %s', 'secure-custom-fields' ),
						ucwords( $this->entity_name() )
					),
					'description'         => sprintf(
						/* translators: %s: Entity type */
						__( 'Retrieves SCF %s configuration by ID or key.', 'secure-custom-fields' ),
						$this->entity_name()
					),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'get_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => true,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'identifier' => $this->get_scf_identifier_schema(),
						),
						'required'   => array( 'identifier' ),
					),
					'output_schema'       => $this->get_entity_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Registers the create ability.
		 *
		 * @return void
		 */
		private function register_create_ability() {
			wp_register_ability(
				$this->ability_name( 'create' ),
				array(
					'label'               => sprintf(
						/* translators: %s: Entity type */
						__( 'Create %s', 'secure-custom-fields' ),
						ucwords( $this->entity_name() )
					),
					'description'         => sprintf(
						/* translators: %s: Entity type */
						__( 'Creates a new instance of SCF %s with provided configuration. Omitted optional fields use schema defaults.', 'secure-custom-fields' ),
						$this->entity_name()
					),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'create_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => $this->get_entity_schema(),
					'output_schema'       => $this->get_entity_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Registers the update ability.
		 *
		 * @return void
		 */
		private function register_update_ability() {
			$input_schema             = $this->get_entity_with_internal_fields_schema();
			$input_schema['required'] = array( 'ID' );

			wp_register_ability(
				$this->ability_name( 'update' ),
				array(
					'label'               => sprintf(
						/* translators: %s: Entity type */
						__( 'Update %s', 'secure-custom-fields' ),
						ucwords( $this->entity_name() )
					),
					'description'         => sprintf(
						/* translators: %s: Entity type */
						__( 'Updates an existing instance of SCF %s. Properties not provided are preserved (merge behavior).', 'secure-custom-fields' ),
						$this->entity_name()
					),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'update_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => $input_schema,
					'output_schema'       => $this->get_entity_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Registers the delete ability.
		 *
		 * @return void
		 */
		private function register_delete_ability() {
			wp_register_ability(
				$this->ability_name( 'delete' ),
				array(
					'label'               => sprintf(
						/* translators: %s: Entity type */
						__( 'Delete %s', 'secure-custom-fields' ),
						ucwords( $this->entity_name() )
					),
					'description'         => sprintf(
						/* translators: %s: Entity type */
						__( 'Permanently deletes an instance of SCF %s. This action cannot be undone.', 'secure-custom-fields' ),
						$this->entity_name()
					),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'delete_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => true,
							'idempotent'  => true,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'identifier' => $this->get_scf_identifier_schema(),
						),
						'required'   => array( 'identifier' ),
					),
					'output_schema'       => array(
						'type'        => 'boolean',
						'description' => sprintf(
							/* translators: %s: Entity type */
							__( 'True if %s was deleted successfully.', 'secure-custom-fields' ),
							$this->entity_name()
						),
					),
				)
			);
		}

		/**
		 * Registers the duplicate ability.
		 *
		 * @return void
		 */
		private function register_duplicate_ability() {
			wp_register_ability(
				$this->ability_name( 'duplicate' ),
				array(
					'label'               => sprintf(
						/* translators: %s: Entity type */
						__( 'Duplicate %s', 'secure-custom-fields' ),
						ucwords( $this->entity_name() )
					),
					'description'         => sprintf(
						/* translators: %s: Entity type */
						__( 'Creates a copy of SCF %s with a new ID and unique key. Title gets (copy) appended. Active status is inherited from source.', 'secure-custom-fields' ),
						$this->entity_name()
					),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'duplicate_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'identifier'  => $this->get_scf_identifier_schema(),
							'new_post_id' => array(
								'type'        => 'integer',
								'description' => __( 'Optional ID of an existing post to overwrite. Used for import/sync operations.', 'secure-custom-fields' ),
							),
						),
						'required'   => array( 'identifier' ),
					),
					'output_schema'       => $this->get_entity_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Registers the export ability.
		 *
		 * @return void
		 */
		private function register_export_ability() {
			wp_register_ability(
				$this->ability_name( 'export' ),
				array(
					'label'               => sprintf(
						/* translators: %s: Entity type */
						__( 'Export %s', 'secure-custom-fields' ),
						ucwords( $this->entity_name() )
					),
					'description'         => sprintf(
						/* translators: %s: Entity type */
						__( 'Exports an instance of SCF %s as JSON for backup or transfer. Internal fields (ID, local, _valid) are stripped.', 'secure-custom-fields' ),
						$this->entity_name()
					),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'export_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => true,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'identifier' => $this->get_scf_identifier_schema(),
						),
						'required'   => array( 'identifier' ),
					),
					'output_schema'       => $this->get_entity_schema(),
				)
			);
		}

		/**
		 * Registers the import ability.
		 *
		 * @return void
		 */
		private function register_import_ability() {
			wp_register_ability(
				$this->ability_name( 'import' ),
				array(
					'label'               => sprintf(
						/* translators: %s: Entity type */
						__( 'Import %s', 'secure-custom-fields' ),
						ucwords( $this->entity_name() )
					),
					'description'         => sprintf(
						/* translators: %s: Entity type */
						__( 'Imports an instance of SCF %s from JSON data. If ID is provided, updates existing; otherwise creates new with schema defaults for omitted fields.', 'secure-custom-fields' ),
						$this->entity_name()
					),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'import_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => $this->get_entity_with_internal_fields_schema(),
					'output_schema'       => $this->get_entity_with_internal_fields_schema(),
				)
			);
		}

		// Callbacks.

		/**
		 * Handles the list ability callback.
		 *
		 * @param array $input The input parameters.
		 * @return array List of entities.
		 */
		public function list_callback( $input ) {
			$filter = isset( $input['filter'] ) ? $input['filter'] : array();
			return $this->instance()->filter_posts( $this->instance()->get_posts(), $filter );
		}

		/**
		 * Handles the get ability callback.
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error Entity data or error if not found.
		 */
		public function get_callback( $input ) {
			$entity = $this->instance()->get_post( $input['identifier'] );
			if ( ! $entity ) {
				return $this->not_found_error();
			}
			return $entity;
		}

		/**
		 * Handles the create ability callback.
		 *
		 * @param array $input The entity data to create.
		 * @return array|WP_Error Created entity or error on failure.
		 */
		public function create_callback( $input ) {
			if ( $this->instance()->get_post( $input['key'] ) ) {
				return new WP_Error(
					'already_exists',
					sprintf(
						/* translators: %s: Entity type */
						__( '%s with this key already exists.', 'secure-custom-fields' ),
						ucfirst( $this->entity_name() )
					)
				);
			}

			$entity = $this->instance()->update_post( $input );
			if ( ! $entity ) {
				return new WP_Error(
					'create_failed',
					sprintf(
						/* translators: %s: Entity type */
						__( 'Failed to create %s.', 'secure-custom-fields' ),
						$this->entity_name()
					)
				);
			}
			return $entity;
		}

		/**
		 * Handles the update ability callback.
		 *
		 * @param array $input The entity data to update.
		 * @return array|WP_Error Updated entity or error on failure.
		 */
		public function update_callback( $input ) {
			$existing = $this->instance()->get_post( $input['ID'] );
			if ( ! $existing ) {
				return $this->not_found_error();
			}

			$entity = $this->instance()->update_post( array_merge( $existing, $input ) );
			if ( ! $entity ) {
				return new WP_Error(
					'update_failed',
					sprintf(
						/* translators: %s: Entity type */
						__( 'Failed to update %s.', 'secure-custom-fields' ),
						$this->entity_name()
					)
				);
			}
			return $entity;
		}

		/**
		 * Handles the delete ability callback.
		 *
		 * @param array $input The input parameters.
		 * @return bool|WP_Error True on success or error on failure.
		 */
		public function delete_callback( $input ) {
			if ( ! $this->instance()->get_post( $input['identifier'] ) ) {
				return $this->not_found_error();
			}

			if ( ! $this->instance()->delete_post( $input['identifier'] ) ) {
				return new WP_Error(
					'delete_failed',
					sprintf(
						/* translators: %s: Entity type */
						__( 'Failed to delete %s.', 'secure-custom-fields' ),
						$this->entity_name()
					)
				);
			}
			return true;
		}

		/**
		 * Handles the duplicate ability callback.
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error Duplicated entity or error on failure.
		 */
		public function duplicate_callback( $input ) {
			if ( ! $this->instance()->get_post( $input['identifier'] ) ) {
				return $this->not_found_error();
			}

			$new_post_id = isset( $input['new_post_id'] ) ? $input['new_post_id'] : 0;

			// Validate that new_post_id references an existing WordPress post.
			if ( $new_post_id && ! get_post( $new_post_id ) ) {
				return new WP_Error(
					'invalid_new_post_id',
					sprintf(
						/* translators: %d: Invalid post ID */
						__( 'Invalid new_post_id: %d does not exist.', 'secure-custom-fields' ),
						$new_post_id
					),
					array( 'status' => 400 )
				);
			}

			$duplicated = $this->instance()->duplicate_post( $input['identifier'], $new_post_id );

			if ( ! $duplicated ) {
				return new WP_Error(
					'duplicate_failed',
					sprintf(
						/* translators: %s: Entity type */
						__( 'Failed to duplicate %s.', 'secure-custom-fields' ),
						$this->entity_name()
					)
				);
			}
			return $duplicated;
		}

		/**
		 * Handles the export ability callback.
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error Exported entity data or error on failure.
		 */
		public function export_callback( $input ) {
			$entity = $this->instance()->get_post( $input['identifier'] );
			if ( ! $entity ) {
				return $this->not_found_error();
			}

			$export = $this->instance()->prepare_post_for_export( $entity );
			if ( ! $export ) {
				return new WP_Error(
					'export_failed',
					sprintf(
						/* translators: %s: Entity type */
						__( 'Failed to export %s.', 'secure-custom-fields' ),
						$this->entity_name()
					)
				);
			}
			return $export;
		}

		/**
		 * Handles the import ability callback.
		 *
		 * @param array|object $input The entity data to import.
		 * @return array|WP_Error Imported entity or error on failure.
		 */
		public function import_callback( $input ) {
			$imported = $this->instance()->import_post( $input );
			if ( ! $imported ) {
				return new WP_Error(
					'import_failed',
					sprintf(
						/* translators: %s: Entity type */
						__( 'Failed to import %s.', 'secure-custom-fields' ),
						$this->entity_name()
					)
				);
			}
			return $imported;
		}

		/**
		 * Registers the trash ability.
		 *
		 * @return void
		 */
		private function register_trash_ability() {
			wp_register_ability(
				$this->ability_name( 'trash' ),
				array(
					'label'               => __( 'Trash', 'secure-custom-fields' ),
					'description'         => sprintf(
						/* translators: %s: Entity type */
						__( 'Moves SCF %s to trash. Can be restored using untrash.', 'secure-custom-fields' ),
						$this->entity_name()
					),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'trash_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'identifier' => $this->get_scf_identifier_schema(),
						),
						'required'   => array( 'identifier' ),
					),
					'output_schema'       => array(
						'type'        => 'boolean',
						'description' => __( 'True on success.', 'secure-custom-fields' ),
					),
				)
			);
		}

		/**
		 * Handles the trash ability callback.
		 *
		 * @param array $input The input parameters.
		 * @return bool|WP_Error True on success or error on failure.
		 */
		public function trash_callback( $input ) {
			if ( ! $this->instance()->get_post( $input['identifier'] ) ) {
				return $this->not_found_error();
			}

			if ( ! $this->instance()->trash_post( $input['identifier'] ) ) {
				return new WP_Error(
					'trash_failed',
					__( 'Trash operation failed.', 'secure-custom-fields' )
				);
			}
			return true;
		}

		/**
		 * Registers the untrash ability.
		 *
		 * @return void
		 */
		private function register_untrash_ability() {
			wp_register_ability(
				$this->ability_name( 'untrash' ),
				array(
					'label'               => __( 'Restore', 'secure-custom-fields' ),
					'description'         => sprintf(
						/* translators: %s: Entity type */
						__( 'Restores SCF %s from trash to previous status.', 'secure-custom-fields' ),
						$this->entity_name()
					),
					'category'            => $this->ability_category(),
					'execute_callback'    => array( $this, 'untrash_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array( 'public' => true ),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'identifier' => $this->get_scf_identifier_schema(),
						),
						'required'   => array( 'identifier' ),
					),
					'output_schema'       => array(
						'type'        => 'boolean',
						'description' => __( 'True on success.', 'secure-custom-fields' ),
					),
				)
			);
		}

		/**
		 * Handles the untrash ability callback.
		 *
		 * @param array $input The input parameters.
		 * @return bool|WP_Error True on success or error on failure.
		 */
		public function untrash_callback( $input ) {
			if ( ! $this->instance()->get_post( $input['identifier'] ) ) {
				return $this->not_found_error();
			}

			if ( ! $this->instance()->untrash_post( $input['identifier'] ) ) {
				return new WP_Error(
					'untrash_failed',
					__( 'Restore operation failed.', 'secure-custom-fields' )
				);
			}
			return true;
		}

		/**
		 * Creates a not found error.
		 *
		 * @return WP_Error
		 */
		private function not_found_error() {
			return new WP_Error(
				'not_found',
				sprintf(
					/* translators: %s: Entity type */
					__( '%s not found.', 'secure-custom-fields' ),
					ucfirst( $this->entity_name() )
				),
				array( 'status' => 404 )
			);
		}
	}

endif;
