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

		// Add filter to process each post type individually. We are using this filter since rest_post_types_query was introduced in WP 6.5
		add_filter( 'rest_prepare_post_type', array( $this, 'filter_post_type' ), 10, 3 );

		// Clean up null entries from the response
		add_filter( 'rest_pre_echo_response', array( $this, 'clean_types_response' ), 10, 3 );
	}

	/**
	 * Filter each post type in the response.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post_Type     $post_type The post type object.
	 * @param WP_REST_Request  $request The request object.
	 * @return WP_REST_Response|WP_Error The filtered response.
	 */
	public function filter_post_type( $response, $post_type, $request ) {
		// Get the origin parameter
		$origin = $request->get_param( 'origin' );

		// Only apply filtering if origin parameter is provided and valid
		if ( ! $origin || ! in_array( $origin, array( 'core', 'scf', 'other' ), true ) ) {
			return $response;
		}

		// Static cache for origin post types
		static $origin_post_types = null;

		// Get post types for the requested origin (using cache if available)
		if ( null === $origin_post_types ) {
			$origin_post_types = $this->get_origin_post_types( $origin );
		}

		// For a post type to pass, its name must be in the origin post types list
		$post_type_name = $post_type->name;

		// If this post type doesn't match the origin, return null to filter it out
		if ( ! in_array( $post_type_name, $origin_post_types, true ) ) {
			// Return null to indicate this post type should be filtered out
			return null;
		}

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

	/**
	 * Clean up null entries from the response
	 *
	 * @since 6.5.0
	 *
	 * @param array|WP_REST_Response $response The response data.
	 * @param WP_REST_Server         $server   The REST server instance.
	 * @param WP_REST_Request        $request  The original request.
	 * @return array|WP_REST_Response The filtered response data.
	 */
	public function clean_types_response( $response, $server, $request ) {
		// Only process types endpoint responses
		if ( strpos( $request->get_route(), '/wp/v2/types' ) !== 0 ) {
			return $response;
		}

		// Get response data
		if ( is_a( $response, 'WP_REST_Response' ) ) {
			$data = $response->get_data();
		} else {
			$data = $response;
		}

		// Check if we're dealing with a collection of types
		if ( is_array( $data ) && ! isset( $data['slug'] ) ) {
			// Remove null entries
			$data = array_filter(
				$data,
				function ( $entry ) {
					return null !== $entry;
				}
			);
		}

		// Put the filtered data back into the response
		if ( is_a( $response, 'WP_REST_Response' ) ) {
			$response->set_data( $data );
		} else {
			$response = $data;
		}

		return $response;
	}
}
