<?php
/**
 * SCF REST Types Endpoint Extension
 *
 * @package SecureCustomFields
 * @subpackage REST_API
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SCF_Rest_Types_Endpoint
 *
 * Extends the /wp/v2/types endpoint to include SCF fields.
 *
 * @since 6.5.0
 */
class SCF_Rest_Types_Endpoint {

	/**
	 * Initialize the class.
	 *
	 * @since 6.5.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_extra_fields' ) );
		add_action( 'rest_api_init', array( $this, 'register_parameters' ) );

		// Add early filter to process types requests directly by route
		add_filter( 'rest_request_before_callbacks', array( $this, 'pre_process_types_request' ), 10, 3 );
	}

	/**
	 * Process types requests before dispatch to filter by origin
	 *
	 * @since 6.5.0
	 *
	 * @param mixed           $response The current response, either response or null.
	 * @param array           $handler The handler for the route.
	 * @param WP_REST_Request $request The request object.
	 * @return mixed The response or null.
	 */
	public function pre_process_types_request( $response, $handler, $request ) {
		// Only process types collection endpoint
		$route = $request->get_route();
		if ( '/wp/v2/types' !== $route ) {
			return $response;
		}

		// Get the origin parameter
		$origin = $request->get_param( 'origin' );

		// Only proceed if origin parameter is provided and valid
		if ( ! $origin || ! in_array( $origin, array( 'core', 'scf', 'other' ), true ) ) {
			return $response;
		}

		// Get filtered types by origin
		$origin_post_types = $this->get_origin_post_types( $origin );

		// Add a filter to process the response after it's generated
		add_filter(
			'rest_pre_serve_request',
			function ( $served, $result ) use ( $origin_post_types ) {
				if ( ! $served && is_array( $result->data ) ) {
					// Filter the response to keep only post types from our filtered list
					$result->data = array_intersect_key(
						$result->data,
						array_flip( $origin_post_types )
					);
				}
				return $served;
			},
			10,
			2
		);

		return $response;
	}

	/**
	 * Get an array of post types for each origin.
	 *
	 * @since 6.5.0
	 *
	 * @param string $origin The origin to get post types for.
	 * @return array An array of post type names for the specified origin.
	 */
	private function get_origin_post_types( $origin ) {
		$core_types = array();
		$scf_types  = array();

		// Get core post types (only if needed)
		if ( 'core' === $origin || 'other' === $origin ) {
			$all_post_types = get_post_types( array( '_builtin' => true ), 'objects' );
			foreach ( $all_post_types as $post_type ) {
				$core_types[] = $post_type->name;
			}
		}

		// Get SCF-managed post types (only if needed)
		if ( 'scf' === $origin || 'other' === $origin ) {
			$scf_post_types = acf_get_internal_post_type_posts( 'acf-post-type' );
			foreach ( $scf_post_types as $scf_post_type ) {
				if ( isset( $scf_post_type['post_type'] ) ) {
					$scf_types[] = $scf_post_type['post_type'];
				}
			}
		}

		// Return appropriate post types based on origin
		switch ( $origin ) {
			case 'core':
				$result = $core_types;
				break;
			case 'scf':
				$result = $scf_types;
				break;
			case 'other':
				$result = array_diff(
					array_keys( get_post_types( array(), 'objects' ) ),
					array_merge( $core_types, $scf_types )
				);
				break;
			default:
				$result = array();
		}

		return $result;
	}

	/**
	 * Register extra SCF fields for the post types endpoint.
	 *
	 * @since 6.5.0
	 *
	 * @return void
	 */
	public function register_extra_fields() {
		register_rest_field(
			'type',
			'scf_field_groups',
			array(
				'get_callback' => array( $this, 'get_scf_fields' ),
				'schema'       => $this->get_field_schema(),
			)
		);
	}

	/**
	 * Get SCF fields for a post type.
	 *
	 * @since 6.5.0
	 *
	 * @param array $post_type_object The post type object.
	 * @return array Array of field data.
	 */
	public function get_scf_fields( $post_type_object ) {
		$post_type         = $post_type_object['slug'];
		$field_groups      = acf_get_field_groups( array( 'post_type' => $post_type ) );
		$field_groups_data = array();

		foreach ( $field_groups as $field_group ) {
			$fields       = acf_get_fields( $field_group );
			$group_fields = array();

			foreach ( $fields as $field ) {
				$group_fields[] = array(
					'label' => $field['label'],
					'type'  => $field['type'],
				);
			}

			$field_groups_data[] = array(
				'title'  => $field_group['title'],
				'fields' => $group_fields,
			);
		}

		return $field_groups_data;
	}

	/**
	 * Get the schema for the SCF fields.
	 *
	 * @since 6.5.0
	 *
	 * @return array The schema for the SCF fields.
	 */
	private function get_field_schema() {
		return array(
			'description' => 'Field groups attached to this post type.',
			'type'        => 'array',
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'title'  => array(
						'type'        => 'string',
						'description' => 'The field group title.',
					),
					'fields' => array(
						'type'        => 'array',
						'description' => 'The fields in this field group.',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'label' => array(
									'type'        => 'string',
									'description' => 'The field label.',
								),
								'type'  => array(
									'type'        => 'string',
									'description' => 'The field type.',
								),
							),
						),
					),
				),
			),
			'context'     => array( 'view', 'edit', 'embed' ),
		);
	}

	/**
	 * Register the origin parameter for the post types endpoint.
	 *
	 * @since 6.5.0
	 */
	public function register_parameters() {
		if ( ! acf_get_setting( 'rest_api_enabled' ) ) {
			return;
		}

		// Register the query parameter with the REST API
		add_filter( 'rest_type_collection_params', array( $this, 'add_collection_params' ) );
		add_filter( 'rest_types_collection_params', array( $this, 'add_collection_params' ) );

		// Direct registration for OpenAPI documentation
		add_filter( 'rest_endpoints', array( $this, 'add_parameter_to_endpoints' ) );
	}

	/**
	 * Add origin parameter directly to the endpoints for proper documentation
	 *
	 * @since 6.5.0
	 *
	 * @param array $endpoints The REST API endpoints.
	 * @return array Modified endpoints
	 */
	public function add_parameter_to_endpoints( $endpoints ) {
		// Define the origin parameter
		$origin_param = array(
			'description' => __( 'Filter post types by their origin.', 'secure-custom-fields' ),
			'type'        => 'string',
			'enum'        => array( 'core', 'scf', 'other' ),
			'required'    => false,
		);

		// Add to the types collection endpoint
		if ( isset( $endpoints['/wp/v2/types'] ) ) {
			foreach ( $endpoints['/wp/v2/types'] as &$endpoint ) {
				if ( isset( $endpoint['args'] ) ) {
					$endpoint['args']['origin'] = $origin_param;
				}
			}
		}

		// Add to the individual type endpoint
		if ( isset( $endpoints['/wp/v2/types/(?P<type>[\w-]+)'] ) ) {
			foreach ( $endpoints['/wp/v2/types/(?P<type>[\w-]+)'] as &$endpoint ) {
				if ( isset( $endpoint['args'] ) ) {
					$endpoint['args']['origin'] = $origin_param;
				}
			}
		}

		return $endpoints;
	}

	/**
	 * Add origin parameter to the collection parameters for the types endpoint.
	 *
	 * @since 6.5.0
	 *
	 * @param array $query_params JSON Schema-formatted collection parameters.
	 * @return array Modified collection parameters.
	 */
	public function add_collection_params( $query_params ) {
		$query_params['origin'] = array(
			'description'       => __( 'Filter post types by their origin.', 'secure-custom-fields' ),
			'type'              => 'string',
			'enum'              => array( 'core', 'scf', 'other' ),
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => null,
			'required'          => false,
			'in'                => 'query',
		);

		return $query_params;
	}
}
