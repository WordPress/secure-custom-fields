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
 * Extends the /wp/v2/types endpoint to include SCF fields and origin filtering.
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

		// Add filter to process REST API requests by route
		add_filter( 'rest_request_before_callbacks', array( $this, 'filter_types_request' ), 10, 3 );
		
		// Add filter to process each post type individually (WP 6.5+ compatibility)
		add_filter( 'rest_prepare_post_type', array( $this, 'filter_post_type' ), 10, 3 );
		
		// Clean up null entries from the response
		add_filter( 'rest_pre_echo_response', array( $this, 'clean_types_response' ), 10, 3 );
	}

	/**
	 * Filter post types requests (both collection and individual)
	 *
	 * @since 6.5.0
	 *
	 * @param mixed           $response The current response, either response or null.
	 * @param array           $handler  The handler for the route.
	 * @param WP_REST_Request $request  The request object.
	 * @return mixed The response or null.
	 */
	public function filter_types_request( $response, $handler, $request ) {
		// Check if this is a types endpoint request
		$route          = $request->get_route();
		$is_collection  = '/wp/v2/types' === $route;
		$is_single_type = preg_match( '#^/wp/v2/types/([^/]+)$#', $route, $matches );

		if ( ! $is_collection && ! $is_single_type ) {
			return $response;
		}

		// Get the origin parameter
		$origin = $request->get_param( 'origin' );

		// Only proceed if origin parameter is provided and valid
		if ( ! $origin || ! in_array( $origin, array( 'core', 'scf', 'other' ), true ) ) {
			return $response;
		}

		// Static cache for origin post types within this request
		static $origin_post_types_cache = array();

		// Get filtered types by origin (using cache if available)
		if ( ! isset( $origin_post_types_cache[ $origin ] ) ) {
			$origin_post_types_cache[ $origin ] = $this->get_origin_post_types( $origin );
		}
		$origin_post_types = $origin_post_types_cache[ $origin ];

		// For single post type requests, check if it matches the origin
		if ( $is_single_type && isset( $matches[1] ) ) {
			$requested_type = $matches[1];

			// If the requested type doesn't match the origin, return 404
			if ( ! in_array( $requested_type, $origin_post_types, true ) ) {
				return new WP_Error(
					'rest_post_type_invalid',
					__( 'Invalid post type.', 'secure-custom-fields' ),
					array( 'status' => 404 )
				);
			}
		} else {
			// For collection requests, add a filter to process the response
			add_filter(
				'rest_pre_serve_request',
				function ( $served, $result ) use ( $origin_post_types ) {
					if ( ! $served && isset( $result->data ) && is_array( $result->data ) ) {
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
		}

		return $response;
	}
	
	/**
	 * Filter individual post type in the response (WP 6.5+ compatibility).
	 *
	 * @since 6.5.0
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post_Type     $post_type The post type object.
	 * @param WP_REST_Request  $request The request object.
	 * @return WP_REST_Response|null The filtered response or null to filter it out.
	 */
	public function filter_post_type( $response, $post_type, $request ) {
		// Get the origin parameter
		$origin = $request->get_param( 'origin' );

		// Only apply filtering if origin parameter is provided and valid
		if ( ! $origin || ! in_array( $origin, array( 'core', 'scf', 'other' ), true ) ) {
			return $response;
		}
		
		// Static cache for origin post types within this request
		static $origin_post_types_cache = array();

		// Get filtered types by origin (using cache if available)
		if ( ! isset( $origin_post_types_cache[ $origin ] ) ) {
			$origin_post_types_cache[ $origin ] = $this->get_origin_post_types( $origin );
		}
		
		// If this post type doesn't match the origin, return null to filter it out
		if ( ! in_array( $post_type->name, $origin_post_types_cache[ $origin ], true ) ) {
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
		// Cache for performance across requests
		static $cached_types = array();

		// Return cached results if available
		if ( isset( $cached_types[ $origin ] ) ) {
			return $cached_types[ $origin ];
		}
		
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
			$scf_post_types = array( 'acf-field-group', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page' );
			
			// Get SCF-created post types
			if ( function_exists( 'acf_get_internal_post_type_posts' ) ) {
				$scf_defined_post_types = acf_get_internal_post_type_posts( 'acf-post-type' );
				foreach ( $scf_defined_post_types as $scf_post_type ) {
					if ( isset( $scf_post_type['post_type'] ) ) {
						$scf_types[] = $scf_post_type['post_type'];
					}
				}
			}
			
			// Combine with SCF internal post types
			$scf_types = array_unique( array_merge( $scf_post_types, $scf_types ) );
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
		
		// Cache the result
		$cached_types[ $origin ] = $result;

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
		// Only register the field groups field if the editor sidebar beta feature is enabled
		if ( ! (bool) get_option( 'scf_beta_feature_editor-sidebar_enabled', false ) ) {
			return;
		}
		
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
	 * Get the origin parameter definition
	 *
	 * @since 6.5.0
	 *
	 * @param bool $include_validation Whether to include validation callbacks.
	 * @return array Parameter definition
	 */
	private function get_origin_param_definition( $include_validation = false ) {
		$param = array(
			'description' => __( 'Filter post types by their origin.', 'secure-custom-fields' ),
			'type'        => 'string',
			'enum'        => array( 'core', 'scf', 'other' ),
			'required'    => false,
		);

		// Add validation for API use (not needed for documentation)
		if ( $include_validation ) {
			$param['validate_callback'] = 'rest_validate_request_arg';
			$param['sanitize_callback'] = 'sanitize_text_field';
			$param['default']           = null;
			$param['in']                = 'query';
		}

		return $param;
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
		$origin_param        = $this->get_origin_param_definition();
		$endpoints_to_modify = array( '/wp/v2/types', '/wp/v2/types/(?P<type>[\w-]+)' );

		foreach ( $endpoints_to_modify as $route ) {
			if ( isset( $endpoints[ $route ] ) ) {
				foreach ( $endpoints[ $route ] as &$endpoint ) {
					if ( isset( $endpoint['args'] ) ) {
						$endpoint['args']['origin'] = $origin_param;
					}
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
		$query_params['origin'] = $this->get_origin_param_definition( true );
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