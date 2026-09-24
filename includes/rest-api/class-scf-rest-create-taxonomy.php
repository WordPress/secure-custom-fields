<?php
/**
 * SCF Create Taxonomy REST Endpoint
 *
 * Adds a REST route used by the block editor sidebar to create new
 * taxonomies without leaving the editing screen.
 *
 * @package SCF
 * @since SCF 6.9.6
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SCF_Rest_Create_Taxonomy_Endpoint' ) ) :

	/**
	 * Class SCF_Rest_Create_Taxonomy_Endpoint
	 *
	 * Registers a POST route that validates and creates an SCF taxonomy using
	 * the same store and validation rules as the classic taxonomy editor.
	 *
	 * @since SCF 6.9.6
	 */
	class SCF_Rest_Create_Taxonomy_Endpoint {

		/**
		 * REST namespace for the route.
		 *
		 * @var string
		 */
		const NAMESPACE_NAME = 'scf/v1';

		/**
		 * REST route for creating a taxonomy.
		 *
		 * @var string
		 */
		const ROUTE = '/taxonomies';

		/**
		 * Constructor.
		 *
		 * @since SCF 6.9.6
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Registers the create taxonomy route.
		 *
		 * The route only exists while the create_taxonomies beta feature is
		 * enabled, so the feature is genuinely opt-in on the REST side too and
		 * not just in the editor UI. The beta feature registry is admin only,
		 * so the stored option is read directly here.
		 *
		 * @since SCF 6.9.6
		 *
		 * @return void
		 */
		public function register_routes() {
			if ( ! get_option( 'scf_beta_feature_create_taxonomies_enabled', false ) ) {
				return;
			}

			register_rest_route(
				self::NAMESPACE_NAME,
				self::ROUTE,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_taxonomy' ),
					'permission_callback' => 'scf_current_user_has_capability',
					'args'                => array(
						'singular_label' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'plural_label'   => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'taxonomy'       => array(
							'required' => true,
							'type'     => 'string',
						),
						'object_type'    => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array( 'type' => 'string' ),
						),
						'hierarchical'   => array(
							'required' => false,
							'type'     => 'boolean',
							'default'  => false,
						),
					),
				)
			);
		}

		/**
		 * Creates an SCF taxonomy from the sidebar request.
		 *
		 * @since SCF 6.9.6
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return WP_REST_Response|WP_Error
		 */
		public function create_taxonomy( WP_REST_Request $request ) {
			if ( ! scf_current_user_has_capability() ) {
				return new WP_Error(
					'scf_rest_cannot_create',
					__( 'Sorry, you are not allowed to create taxonomies.', 'secure-custom-fields' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			$taxonomy_key = (string) $request->get_param( 'taxonomy' );

			$taxonomy = array(
				'key'          => uniqid( 'taxonomy_' ),
				'title'        => (string) $request->get_param( 'plural_label' ),
				'taxonomy'     => $taxonomy_key,
				'object_type'  => $this->sanitize_object_type( $request->get_param( 'object_type' ) ),
				'hierarchical' => (bool) $request->get_param( 'hierarchical' ),
				'labels'       => array(
					'name'          => (string) $request->get_param( 'plural_label' ),
					'singular_name' => (string) $request->get_param( 'singular_label' ),
				),
			);

			$validator = acf_get_instance( 'SCF_JSON_Schema_Validator' );
			if ( ! $validator->validate( $taxonomy, 'taxonomy' ) ) {
				$validation_message = $validator->get_validation_errors_string();

				if ( empty( $validation_message ) ) {
					$validation_message = __( 'The taxonomy could not be validated.', 'secure-custom-fields' );
				}

				return new WP_Error( 'scf_invalid_taxonomy', $validation_message, array( 'status' => 400 ) );
			}

			// Reject a key already used by an SCF taxonomy, or one registered elsewhere.
			if ( $this->taxonomy_key_in_use( $taxonomy_key ) ) {
				return new WP_Error(
					'scf_taxonomy_exists',
					__( 'This taxonomy key is already in use and cannot be used.', 'secure-custom-fields' ),
					array( 'status' => 409 )
				);
			}

			$created = acf_update_internal_post_type( $taxonomy, 'acf-taxonomy' );

			if ( empty( $created['ID'] ) ) {
				return new WP_Error(
					'scf_taxonomy_create_failed',
					__( 'The taxonomy could not be created.', 'secure-custom-fields' ),
					array( 'status' => 500 )
				);
			}

			return new WP_REST_Response(
				array(
					'id'       => (int) $created['ID'],
					'key'      => (string) $created['key'],
					'taxonomy' => (string) $created['taxonomy'],
				),
				201
			);
		}

		/**
		 * Checks whether a taxonomy key is already used by SCF or WordPress.
		 *
		 * Mirrors the duplicate check the classic taxonomy editor performs, so
		 * the sidebar cannot create a taxonomy the admin screen would reject.
		 * Taxonomies that failed to register are ignored, matching the editor,
		 * which lets that key be reused.
		 *
		 * @since SCF 6.9.6
		 *
		 * @param string $taxonomy_key The requested taxonomy key.
		 * @return bool
		 */
		private function taxonomy_key_in_use( $taxonomy_key ) {
			$instance = acf_get_internal_post_type_instance( 'acf-taxonomy' );

			if ( $instance && $instance->store ) {
				$store = acf_get_store( $instance->store );

				$matches = array_filter(
					$instance->get_posts(),
					function ( $item ) use ( $taxonomy_key, $store ) {
						if ( ! isset( $item['taxonomy'] ) || $item['taxonomy'] !== $taxonomy_key ) {
							return false;
						}

						// A taxonomy that exists but failed to register does not
						// reserve its key. register_taxonomies() flags those on the
						// store rather than in the database.
						$stored = $store->get( $item['key'] );

						return empty( $stored['not_registered'] );
					}
				);

				if ( ! empty( $matches ) ) {
					return true;
				}
			}

			return taxonomy_exists( $taxonomy_key );
		}

		/**
		 * Sanitizes and filters the requested post types.
		 *
		 * Only post types that actually exist are kept, so the taxonomy cannot be
		 * bound to an arbitrary string.
		 *
		 * @since SCF 6.9.6
		 *
		 * @param mixed $object_type The requested post type list.
		 * @return array
		 */
		private function sanitize_object_type( $object_type ) {
			$object_type = is_array( $object_type ) ? $object_type : array();
			$object_type = array_map( 'sanitize_key', $object_type );

			return array_values( array_filter( $object_type, 'post_type_exists' ) );
		}
	}

	acf_new_instance( 'SCF_Rest_Create_Taxonomy_Endpoint' );

endif; // class_exists check.
