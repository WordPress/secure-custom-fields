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
	class SCF_Taxonomy_Abilities {

		/**
		 * Taxonomy schema to reuse across ability registrations.
		 *
		 * @var array|null
		 */
		private $taxonomy_schema = null;

		/**
		 * SCF identifier schema to reuse across ability registrations.
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
			$validator = acf_get_instance( 'SCF_JSON_Schema_Validator' );

			// Only register abilities if schemas are available.
			if ( ! $validator->validate_required_schemas() ) {
				return;
			}

			add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
			add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		}

		/**
		 * Get the SCF taxonomy schema, loading it once and caching for reuse.
		 *
		 * @since 6.7.0
		 * @return array The taxonomy schema definition.
		 */
		private function get_taxonomy_schema() {
			if ( null === $this->taxonomy_schema ) {
				$validator = new SCF_JSON_Schema_Validator();
				$schema    = $validator->load_schema( 'taxonomy' );

				$this->taxonomy_schema = json_decode( wp_json_encode( $schema->definitions->taxonomy ), true );
			}

			return $this->taxonomy_schema;
		}

		/**
		 * Get the SCF identifier schema, loading it once and caching for reuse.
		 *
		 * @since 6.7.0
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
		 * @since 6.7.0
		 * @return array The internal fields schema.
		 */
		private function get_internal_fields_schema() {
			$validator = new SCF_JSON_Schema_Validator();
			$schema    = $validator->load_schema( 'internal-fields' );

			return json_decode( wp_json_encode( $schema->definitions->internalFields ), true );
		}

		/**
		 * Get the taxonomy schema extended with internal fields for GET/LIST/CREATE/UPDATE/IMPORT/DUPLICATE operations.
		 *
		 * @since 6.7.0
		 *
		 * @return array The extended taxonomy schema with internal fields.
		 */
		private function get_taxonomy_with_internal_fields_schema() {
			$schema               = $this->get_taxonomy_schema();
			$internal_fields      = $this->get_internal_fields_schema();
			$schema['properties'] = array_merge( $schema['properties'], $internal_fields['properties'] );

			return $schema;
		}

		/**
		 * Register SCF ability categories.
		 *
		 * @since 6.7.0
		 */
		public function register_categories() {
			wp_register_ability_category(
				'scf-taxonomies',
				array(
					'label'       => __( 'SCF Taxonomies', 'secure-custom-fields' ),
					'description' => __( 'Abilities for managing Secure Custom Fields taxonomies.', 'secure-custom-fields' ),
				)
			);
		}

		/**
		 * Register all taxonomy abilities.
		 *
		 * @since 6.7.0
		 */
		public function register_abilities() {
			$this->register_list_taxonomies_ability();
			$this->register_get_taxonomy_ability();
			$this->register_create_taxonomy_ability();
			$this->register_update_taxonomy_ability();
			$this->register_delete_taxonomy_ability();
			$this->register_duplicate_taxonomy_ability();
			$this->register_export_taxonomy_ability();
			$this->register_import_taxonomy_ability();
		}

		/**
		 * Register the list taxonomies ability.
		 *
		 * @since 6.7.0
		 */
		private function register_list_taxonomies_ability() {
			wp_register_ability(
				'scf/list-taxonomies',
				array(
					'label'               => __( 'List Taxonomies', 'secure-custom-fields' ),
					'description'         => __( 'Retrieves a list of all SCF taxonomies with optional filtering.', 'secure-custom-fields' ),
					'category'            => 'scf-taxonomies',
					'execute_callback'    => array( $this, 'list_taxonomies_callback' ),
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
								'description' => __( 'Optional filters to apply to the taxonomy list.', 'secure-custom-fields' ),
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
						'items' => $this->get_taxonomy_with_internal_fields_schema(),
					),
				)
			);
		}

		/**
		 * Register the get taxonomy ability.
		 *
		 * @since 6.7.0
		 */
		private function register_get_taxonomy_ability() {
			wp_register_ability(
				'scf/get-taxonomy',
				array(
					'label'               => __( 'Get Taxonomy', 'secure-custom-fields' ),
					'description'         => __( 'Retrieves a specific SCF taxonomy configuration by ID or key.', 'secure-custom-fields' ),
					'category'            => 'scf-taxonomies',
					'execute_callback'    => array( $this, 'get_taxonomy_callback' ),
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
					'output_schema'       => $this->get_taxonomy_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Register the create taxonomy ability.
		 *
		 * @since 6.7.0
		 */
		private function register_create_taxonomy_ability() {
			$input_schema = $this->get_taxonomy_schema();

			wp_register_ability(
				'scf/create-taxonomy',
				array(
					'label'               => __( 'Create Taxonomy', 'secure-custom-fields' ),
					'description'         => __( 'Creates a new custom taxonomy in SCF with the provided configuration.', 'secure-custom-fields' ),
					'category'            => 'scf-taxonomies',
					'execute_callback'    => array( $this, 'create_taxonomy_callback' ),
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
					'output_schema'       => $this->get_taxonomy_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Register the update taxonomy ability.
		 *
		 * @since 6.7.0
		 */
		private function register_update_taxonomy_ability() {

			// For updates, only ID is required, everything else is optional.
			$input_schema             = $this->get_taxonomy_with_internal_fields_schema();
			$input_schema['required'] = array( 'ID' );

			wp_register_ability(
				'scf/update-taxonomy',
				array(
					'label'               => __( 'Update Taxonomy', 'secure-custom-fields' ),
					'description'         => __( 'Updates an existing SCF taxonomy with new configuration.', 'secure-custom-fields' ),
					'category'            => 'scf-taxonomies',
					'execute_callback'    => array( $this, 'update_taxonomy_callback' ),
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
					'output_schema'       => $this->get_taxonomy_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Register the delete taxonomy ability.
		 *
		 * @since 6.7.0
		 */
		private function register_delete_taxonomy_ability() {
			wp_register_ability(
				'scf/delete-taxonomy',
				array(
					'label'               => __( 'Delete Taxonomy', 'secure-custom-fields' ),
					'description'         => __( 'Permanently deletes an SCF taxonomy. This action cannot be undone.', 'secure-custom-fields' ),
					'category'            => 'scf-taxonomies',
					'execute_callback'    => array( $this, 'delete_taxonomy_callback' ),
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
						'description' => __( 'True if taxonomy was successfully deleted.', 'secure-custom-fields' ),
					),
				)
			);
		}

		/**
		 * Register the duplicate taxonomy ability.
		 *
		 * @since 6.7.0
		 */
		private function register_duplicate_taxonomy_ability() {
			wp_register_ability(
				'scf/duplicate-taxonomy',
				array(
					'label'               => __( 'Duplicate Taxonomy', 'secure-custom-fields' ),
					'description'         => __( 'Creates a copy of an existing SCF taxonomy. The duplicate receives a new unique key but retains the same taxonomy slug, so it will not register until the slug is changed.', 'secure-custom-fields' ),
					'category'            => 'scf-taxonomies',
					'execute_callback'    => array( $this, 'duplicate_taxonomy_callback' ),
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
								'description' => __( 'Optional new post ID for the duplicated taxonomy.', 'secure-custom-fields' ),
							),
						),
						'required'   => array( 'identifier' ),
					),
					'output_schema'       => $this->get_taxonomy_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Register the export taxonomy ability.
		 *
		 * @since 6.7.0
		 */
		private function register_export_taxonomy_ability() {
			wp_register_ability(
				'scf/export-taxonomy',
				array(
					'label'               => __( 'Export Taxonomy', 'secure-custom-fields' ),
					'description'         => __( 'Exports an SCF taxonomy configuration as JSON for backup or transfer.', 'secure-custom-fields' ),
					'category'            => 'scf-taxonomies',
					'execute_callback'    => array( $this, 'export_taxonomy_callback' ),
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
					'output_schema'       => $this->get_taxonomy_schema(),
				)
			);
		}

		/**
		 * Register the import taxonomy ability.
		 *
		 * @since 6.7.0
		 */
		private function register_import_taxonomy_ability() {
			wp_register_ability(
				'scf/import-taxonomy',
				array(
					'label'               => __( 'Import Taxonomy', 'secure-custom-fields' ),
					'description'         => __( 'Imports an SCF taxonomy from JSON configuration data.', 'secure-custom-fields' ),
					'category'            => 'scf-taxonomies',
					'execute_callback'    => array( $this, 'import_taxonomy_callback' ),
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
					'input_schema'        => $this->get_taxonomy_with_internal_fields_schema(),
					'output_schema'       => $this->get_taxonomy_with_internal_fields_schema(),
				)
			);
		}

		/**
		 * Callback for the list taxonomies ability.
		 *
		 * @since 6.7.0
		 *
		 * @param array $input The input parameters.
		 * @return array The response data.
		 */
		public function list_taxonomies_callback( $input ) {
			$filter = isset( $input['filter'] ) ? $input['filter'] : array();

			$taxonomies = acf_get_acf_taxonomies( $filter );
			return is_array( $taxonomies ) ? $taxonomies : array();
		}

		/**
		 * Callback for the get taxonomy ability.
		 *
		 * @since 6.7.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error The taxonomy data on success, WP_Error on failure.
		 */
		public function get_taxonomy_callback( $input ) {
			$taxonomy = acf_get_taxonomy( $input['identifier'] );

			if ( ! $taxonomy ) {
				return $this->taxonomy_not_found_error();
			}

			return $taxonomy;
		}

		/**
		 * Callback for the create taxonomy ability.
		 *
		 * @since 6.7.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error The taxonomy data on success, WP_Error on failure.
		 */
		public function create_taxonomy_callback( $input ) {
			// Check if taxonomy already exists.
			if ( acf_get_taxonomy( $input['key'] ) ) {
				return new WP_Error( 'taxonomy_exists', __( 'A taxonomy with this key already exists.', 'secure-custom-fields' ) );
			}

			$taxonomy = acf_update_taxonomy( $input );

			if ( ! $taxonomy ) {
				return new WP_Error( 'create_taxonomy_failed', __( 'Failed to create taxonomy.', 'secure-custom-fields' ) );
			}

			return $taxonomy;
		}

		/**
		 * Callback for the update taxonomy ability.
		 *
		 * @since 6.7.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error The taxonomy data on success, WP_Error on failure.
		 */
		public function update_taxonomy_callback( $input ) {
			$existing_taxonomy = acf_get_taxonomy( $input['ID'] );
			if ( ! $existing_taxonomy ) {
				return $this->taxonomy_not_found_error();
			}

			// Merge input with existing taxonomy data to preserve unmodified fields.
			$input = array_merge( $existing_taxonomy, $input );

			$taxonomy = acf_update_taxonomy( $input );

			if ( ! $taxonomy ) {
				return new WP_Error( 'update_taxonomy_failed', __( 'Failed to update taxonomy.', 'secure-custom-fields' ) );
			}

			return $taxonomy;
		}

		/**
		 * Callback for the delete taxonomy ability.
		 *
		 * @since 6.7.0
		 *
		 * @param array $input The input parameters.
		 * @return bool|WP_Error True on success, WP_Error on failure.
		 */
		public function delete_taxonomy_callback( $input ) {
			$taxonomy = acf_get_taxonomy( $input['identifier'] );
			if ( ! $taxonomy ) {
				return $this->taxonomy_not_found_error();
			}

			$result = acf_delete_taxonomy( $input['identifier'] );

			if ( ! $result ) {
				return new WP_Error( 'delete_taxonomy_failed', __( 'Failed to delete taxonomy.', 'secure-custom-fields' ) );
			}

			return true;
		}

		/**
		 * Callback for the duplicate taxonomy ability.
		 *
		 * @since 6.7.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error The duplicated taxonomy data on success, WP_Error on failure.
		 */
		public function duplicate_taxonomy_callback( $input ) {
			$taxonomy = acf_get_taxonomy( $input['identifier'] );
			if ( ! $taxonomy ) {
				return $this->taxonomy_not_found_error();
			}

			$new_post_id         = isset( $input['new_post_id'] ) ? $input['new_post_id'] : 0;
			$duplicated_taxonomy = acf_duplicate_taxonomy( $input['identifier'], $new_post_id );

			if ( ! $duplicated_taxonomy ) {
				return new WP_Error( 'duplicate_taxonomy_failed', __( 'Failed to duplicate taxonomy.', 'secure-custom-fields' ) );
			}

			return $duplicated_taxonomy;
		}

		/**
		 * Callback for the export taxonomy ability.
		 *
		 * @since 6.7.0
		 *
		 * @param array $input The input parameters.
		 * @return array|WP_Error The export data on success, WP_Error on failure.
		 */
		public function export_taxonomy_callback( $input ) {
			$taxonomy = acf_get_taxonomy( $input['identifier'] );
			if ( ! $taxonomy ) {
				return $this->taxonomy_not_found_error();
			}

			$export_data = acf_prepare_internal_post_type_for_export( $taxonomy, 'acf-taxonomy' );

			if ( ! $export_data ) {
				return new WP_Error( 'export_taxonomy_failed', __( 'Failed to prepare taxonomy for export.', 'secure-custom-fields' ) );
			}

			return $export_data;
		}

		/**
		 * Callback for the import taxonomy ability.
		 *
		 * @since 6.7.0
		 *
		 * @param array|object $input The input parameters.
		 * @return array|WP_Error The imported taxonomy data on success, WP_Error on failure.
		 */
		public function import_taxonomy_callback( $input ) {
			// Import the taxonomy (handles both create and update based on presence of ID).
			$imported_taxonomy = acf_import_internal_post_type( $input, 'acf-taxonomy' );

			if ( ! $imported_taxonomy ) {
				return new WP_Error( 'import_taxonomy_failed', __( 'Failed to import taxonomy.', 'secure-custom-fields' ) );
			}

			return $imported_taxonomy;
		}

		/**
		 * Returns a WP_Error for taxonomy not found.
		 *
		 * @since 6.7.0
		 * @return WP_Error The error object with 404 status.
		 */
		private function taxonomy_not_found_error() {
			return new WP_Error(
				'taxonomy_not_found',
				__( 'Taxonomy not found.', 'secure-custom-fields' ),
				array( 'status' => 404 )
			);
		}
	}

	// Initialize abilities instance.
	acf_new_instance( 'SCF_Taxonomy_Abilities' );


endif; // class_exists check.
