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
	class SCF_Post_Type_Abilities {

		/**
		 * Post type schema to reuse across ability registrations.
		 *
		 * @var object|null
		 */
		private $post_type_schema = null;

		/**
		 * SCF identifier schema to reuse across ability registrations.
		 *
		 * @var object|null
		 */
		private $scf_identifier_schema = null;

		/**
		 * Constructor.
		 *
		 * @since 6.6.0
		 */
		public function __construct() {
			$validator = acf_get_instance( 'SCF_JSON_Schema_Validator' );

			// Only register abilities if schemas are available
			if ( ! $validator->validate_required_schemas() ) {
				return;
			}

			add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		}

		/**
		 * Get the SCF post type schema, loading it once and caching for reuse.
		 *
		 * @since 6.6.0
		 * @return array The post type schema definition.
		 */
		private function get_post_type_schema() {
			if ( null === $this->post_type_schema ) {
				$validator = new SCF_JSON_Schema_Validator();
				$schema    = $validator->load_schema( 'post-type' );

				$this->post_type_schema = json_decode( wp_json_encode( $schema->definitions->postType ), true );
			}

			return $this->post_type_schema;
		}

		/**
		 * Get the SCF identifier schema, loading it once and caching for reuse.
		 *
		 * @since 6.6.0
		 *
		 * @return array The SCF identifier schema definition.
		 */
		private function get_scf_identifier_schema() {
			if ( null === $this->scf_identifier_schema ) {
				$validator = new SCF_JSON_Schema_Validator();

				$this->scf_identifier_schema = json_decode( wp_json_encode( $validator->load_schema( 'scf-identifier' ) ), true );
			}

			return $this->scf_identifier_schema;
		}

		/**
		 * Get the internal fields schema (ID, _valid, local).
		 *
		 * @since 6.6.0
		 * @return array The internal fields schema.
		 */
		private function get_internal_fields_schema() {
			$validator = new SCF_JSON_Schema_Validator();
			$schema    = $validator->load_schema( 'internal-fields' );

			return json_decode( wp_json_encode( $schema->definitions->internalFields ), true );
		}

		/**
		 * Get the post type schema extended with internal fields for GET/LIST/CREATE/UPDATE/IMPORT/DUPLICATE operations.
		 *
		 * @since 6.6.0
		 *
		 * @return array The extended post type schema with internal fields.
		 */
		private function get_post_type_with_internal_fields_schema() {
			$schema               = $this->get_post_type_schema();
			$internal_fields      = $this->get_internal_fields_schema();
			$schema['properties'] = array_merge( $schema['properties'], $internal_fields['properties'] );

			return $schema;
		}

		/**
		 * Register SCF ability categories.
		 *
		 * @since 6.6.0
		 */
		public function register_categories() {
			wp_register_ability_category(
				'scf-post-types',
				array(
					'label'       => __( 'SCF Post Types', 'secure-custom-fields' ),
					'description' => __( 'Abilities for managing Secure Custom Fields post types.', 'secure-custom-fields' ),
				)
			);
		}

		/**
		 * Register all post type abilities.
		 *
		 * @since 6.6.0
		 */
		public function register_abilities() {
			$this->register_list_post_types_ability();
			$this->register_get_post_type_ability();
			$this->register_create_post_type_ability();
			$this->register_update_post_type_ability();
			$this->register_delete_post_type_ability();
			$this->register_duplicate_post_type_ability();
			$this->register_export_post_type_ability();
			$this->register_import_post_type_ability();
		}

		/**
		 * Register the list post types ability.
		 *
		 * @since 6.6.0
		 */
		private function register_list_post_types_ability() {
			wp_register_ability(
				'scf/list-post-types',
				array(
					'label'               => __( 'List Post Types', 'secure-custom-fields' ),
					'description'         => __( 'Retrieves a list of all SCF post types with optional filtering.', 'secure-custom-fields' ),
					'category'            => 'scf-post-types',
					'execute_callback'    => array( $this, 'list_post_types_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array(
							'public' => true,
						),
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
							'filter' => array(
								'type'        => 'object',
								'description' => __( 'Optional filters to apply to the post type list.', 'secure-custom-fields' ),
								'properties'  => array(
									'active' => array(
										'type'        => 'boolean',
										'description' => __( 'Filter by active status.', 'secure-custom-fields' ),
									),
									'search' => array(
										'type'        => 'string',
										'description' => __( 'Search term to filter post types.', 'secure-custom-fields' ),
									),
								),
							),
						),
					),
					'output_schema'       => array(
						'type'  => 'array',
						'items' => $this->get_post_type_with_internal_fields_schema(),
					),
				)
			);
		}

		/**
		 * Register the get post type ability.
		 *
		 * @since 6.6.0
		 */
		private function register_get_post_type_ability() {
			wp_register_ability(
				'scf/get-post-type',
				array(
					'label'               => __( 'Get Post Type', 'secure-custom-fields' ),
					'description'         => __( 'Retrieves a specific SCF post type configuration by ID or key.', 'secure-custom-fields' ),
					'category'            => 'scf-post-types',
					'execute_callback'    => array( $this, 'get_post_type_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array(
							'public' => true,
						),
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
					'output_schema'       => $this->get_post_type_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Register the create post type ability.
		 *
		 * @since 6.6.0
		 */
		private function register_create_post_type_ability() {
			$input_schema = $this->get_post_type_schema();

			wp_register_ability(
				'scf/create-post-type',
				array(
					'label'               => __( 'Create Post Type', 'secure-custom-fields' ),
					'description'         => __( 'Creates a new custom post type in SCF with the provided configuration.', 'secure-custom-fields' ),
					'category'            => 'scf-post-types',
					'execute_callback'    => array( $this, 'create_post_type_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array(
							'public' => true,
						),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => $input_schema,
					'output_schema'       => $this->get_post_type_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Register the update post type ability.
		 *
		 * @since 6.6.0
		 */
		private function register_update_post_type_ability() {

			// For updates, only ID is required, everything else is optional
			$input_schema             = $this->get_post_type_with_internal_fields_schema();
			$input_schema['required'] = array( 'ID' );

			wp_register_ability(
				'scf/update-post-type',
				array(
					'label'               => __( 'Update Post Type', 'secure-custom-fields' ),
					'description'         => __( 'Updates an existing SCF post type with new configuration.', 'secure-custom-fields' ),
					'category'            => 'scf-post-types',
					'execute_callback'    => array( $this, 'update_post_type_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array(
							'public' => true,
						),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => $input_schema,
					'output_schema'       => $this->get_post_type_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Register the delete post type ability.
		 *
		 * @since 6.6.0
		 */
		private function register_delete_post_type_ability() {
			wp_register_ability(
				'scf/delete-post-type',
				array(
					'label'               => __( 'Delete Post Type', 'secure-custom-fields' ),
					'description'         => __( 'Permanently deletes an SCF post type. This action cannot be undone.', 'secure-custom-fields' ),
					'category'            => 'scf-post-types',
					'execute_callback'    => array( $this, 'delete_post_type_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array(
							'public' => true,
						),
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
						'description' => __( 'True if post type was successfully deleted.', 'secure-custom-fields' ),
					),
				)
			);
		}

		/**
		 * Register the duplicate post type ability.
		 *
		 * @since 6.6.0
		 */
		private function register_duplicate_post_type_ability() {
			wp_register_ability(
				'scf/duplicate-post-type',
				array(
					'label'               => __( 'Duplicate Post Type', 'secure-custom-fields' ),
					'description'         => __( 'Creates a copy of an existing SCF post type with optional modifications.', 'secure-custom-fields' ),
					'category'            => 'scf-post-types',
					'execute_callback'    => array( $this, 'duplicate_post_type_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array(
							'public' => true,
						),
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
								'description' => __( 'Optional new post ID for the duplicated post type.', 'secure-custom-fields' ),
							),
						),
						'required'   => array( 'identifier' ),
					),
					'output_schema'       => $this->get_post_type_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Register the export post type ability.
		 *
		 * @since 6.6.0
		 */
		private function register_export_post_type_ability() {
			wp_register_ability(
				'scf/export-post-type',
				array(
					'label'               => __( 'Export Post Type', 'secure-custom-fields' ),
					'description'         => __( 'Exports an SCF post type configuration as JSON for backup or transfer.', 'secure-custom-fields' ),
					'category'            => 'scf-post-types',
					'execute_callback'    => array( $this, 'export_post_type_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array(
							'public' => true,
						),
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
					'output_schema'       => $this->get_post_type_schema(),
				)
			);
		}

		/**
		 * Register the import post type ability.
		 *
		 * @since 6.6.0
		 */
		private function register_import_post_type_ability() {
			wp_register_ability(
				'scf/import-post-type',
				array(
					'label'               => __( 'Import Post Type', 'secure-custom-fields' ),
					'description'         => __( 'Imports an SCF post type from JSON configuration data.', 'secure-custom-fields' ),
					'category'            => 'scf-post-types',
					'execute_callback'    => array( $this, 'import_post_type_callback' ),
					'meta'                => array(
						'show_in_rest' => true,
						'mcp'          => array(
							'public' => true,
						),
						'annotations'  => array(
							'readonly'    => false,
							'destructive' => false,
							'idempotent'  => false,
						),
					),
					'permission_callback' => 'scf_current_user_has_capability',
					'input_schema'        => $this->get_post_type_with_internal_fields_schema(),
					'output_schema'       => $this->get_post_type_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Callback for the list post types ability.
		 *
		 * @since 6.6.0
		 *
		 * @param array $input The input parameters.
		 * @return array The response data.
		 */
		public function list_post_types_callback( $input ) {
			$filter = isset( $input['filter'] ) ? $input['filter'] : array();

			$post_types = acf_get_acf_post_types( $filter );
			return is_array( $post_types ) ? $post_types : array();
		}

		/**
		 * Callback for the get post type ability.
		 *
		 * @since 6.6.0
		 *
		 * @param array $input The input parameters.
		 * @return array The response data.
		 */
		public function get_post_type_callback( $input ) {
			$post_type = acf_get_post_type( $input['identifier'] );

			if ( ! $post_type ) {
				return new WP_Error( 'post_type_not_found', __( 'Post type not found.', 'secure-custom-fields' ) );
			}

			return $post_type;
		}

		/**
		 * Callback for the create post type ability.
		 *
		 * @since 6.6.0
		 *
		 * @param array $input The input parameters.
		 * @return array The response data.
		 */
		public function create_post_type_callback( $input ) {
			// Check if post type already exists.
			if ( acf_get_post_type( $input['key'] ) ) {
				return new WP_Error( 'post_type_exists', __( 'A post type with this key already exists.', 'secure-custom-fields' ) );
			}

			$post_type = acf_update_post_type( $input );

			if ( ! $post_type ) {
				return new WP_Error( 'create_post_type_failed', __( 'Failed to create post type.', 'secure-custom-fields' ) );
			}

			return $post_type;
		}

		/**
		 * Callback for the update post type ability.
		 *
		 * @since 6.6.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error The post type data on success, WP_Error on failure.
		 */
		public function update_post_type_callback( $input ) {
			$existing_post_type = acf_get_post_type( $input['ID'] );
			if ( ! $existing_post_type ) {
				return new WP_Error( 'post_type_not_found', __( 'Post type not found.', 'secure-custom-fields' ) );
			}

			// Merge input with existing post type data to preserve unmodified fields.
			$input = array_merge( $existing_post_type, $input );

			$post_type = acf_update_post_type( $input );

			if ( ! $post_type ) {
				return new WP_Error( 'update_post_type_failed', __( 'Failed to update post type.', 'secure-custom-fields' ) );
			}

			return $post_type;
		}

		/**
		 * Callback for the delete post type ability.
		 *
		 * @since 6.6.0
		 *
		 * @param array $input The input parameters.
		 * @return bool|WP_Error True on success, WP_Error on failure.
		 */
		public function delete_post_type_callback( $input ) {
			$result = acf_delete_post_type( $input['identifier'] );

			if ( ! $result ) {
				return new WP_Error( 'delete_post_type_failed', __( 'Failed to delete post type.', 'secure-custom-fields' ) );
			}

			return true;
		}

		/**
		 * Callback for the duplicate post type ability.
		 *
		 * @since 6.6.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error The duplicated post type data on success, WP_Error on failure.
		 */
		public function duplicate_post_type_callback( $input ) {
			$new_post_id          = isset( $input['new_post_id'] ) ? $input['new_post_id'] : 0;
			$duplicated_post_type = acf_duplicate_post_type( $input['identifier'], $new_post_id );

			if ( ! $duplicated_post_type ) {
				return new WP_Error( 'duplicate_post_type_failed', __( 'Failed to duplicate post type.', 'secure-custom-fields' ) );
			}

			return $duplicated_post_type;
		}

		/**
		 * Callback for the export post type ability.
		 *
		 * @since 6.6.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error The export data on success, WP_Error on failure.
		 */
		public function export_post_type_callback( $input ) {
			$post_type = acf_get_post_type( $input['identifier'] );
			if ( ! $post_type ) {
				return new WP_Error( 'post_type_not_found', __( 'Post type not found.', 'secure-custom-fields' ) );
			}

			$export_data = acf_prepare_internal_post_type_for_export( $post_type, 'acf-post-type' );

			if ( ! $export_data ) {
				return new WP_Error( 'export_post_type_failed', __( 'Failed to prepare post type for export.', 'secure-custom-fields' ) );
			}

			return $export_data;
		}

		/**
		 * Callback for the import post type ability.
		 *
		 * @since 6.6.0
		 *
		 * @param array|object $input The input parameters.
		 * @return array|WP_Error The imported post type data on success, WP_Error on failure.
		 */
		public function import_post_type_callback( $input ) {
			// Import the post type (handles both create and update based on presence of ID).
			$imported_post_type = acf_import_internal_post_type( $input, 'acf-post-type' );

			if ( ! $imported_post_type ) {
				return new WP_Error( 'import_post_type_failed', __( 'Failed to import post type.', 'secure-custom-fields' ) );
			}

			return $imported_post_type;
		}
	}

	// Initialize abilities instance.
	acf_new_instance( 'SCF_Post_Type_Abilities' );


endif; // class_exists check
