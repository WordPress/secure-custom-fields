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
 * Extends the /wp/v2/types endpoint to include SCF fields and source filtering.
 *
 * @since SCF 6.5.0
 */
class SCF_Rest_Types_Endpoint {

	/**
	 * Valid source types for filtering post types.
	 *
	 * @since 6.7.0
	 * @var array
	 */
	const VALID_SOURCES = array( 'core', 'scf', 'other' );

	/**
	 * Initialize the class and register hooks.
	 *
	 * @since SCF 6.5.0
	 * @since 6.7.0 Simplified hook registration.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_extra_fields' ) );
		add_action( 'rest_api_init', array( $this, 'register_parameters' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'filter_types_request' ), 10, 3 );
		add_filter( 'rest_prepare_post_type', array( $this, 'filter_post_type' ), 10, 3 );
		add_filter( 'rest_pre_echo_response', array( $this, 'clean_types_response' ), 10, 3 );
	}

	/**
	 * Validate source parameter.
	 *
	 * @since 6.7.0
	 *
	 * @param string $source The source value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_source( $source ) {
		return in_array( $source, self::VALID_SOURCES, true );
	}

	/**
	 * Filter post types requests for individual post type requests.
	 *
	 * @since SCF 6.5.0
	 * @since 6.7.0 Use is_valid_source() helper method.
	 *
	 * @param mixed           $response The current response.
	 * @param array           $handler  The handler for the route.
	 * @param WP_REST_Request $request  The request object.
	 * @return mixed The response or WP_Error.
	 */
	public function filter_types_request( $response, $handler, $request ) {
		// Only handle individual post type requests
		$route = $request->get_route();
		if ( ! preg_match( '#^/wp/v2/types/([^/]+)$#', $route, $matches ) ) {
			return $response;
		}

		$source = $request->get_param( 'source' );

		// Only proceed if source parameter is provided and valid
		if ( ! $source || ! $this->is_valid_source( $source ) ) {
			return $response;
		}

		$source_post_types = $this->get_source_post_types( $source );
		$requested_type    = $matches[1];

		// Check if the requested type matches the source
		if ( ! in_array( $requested_type, $source_post_types, true ) ) {
			return new WP_Error(
				'rest_post_type_invalid',
				__( 'Invalid post type.', 'secure-custom-fields' ),
				array( 'status' => 404 )
			);
		}

		return $response;
	}

	/**
	 * Filter individual post type in the response.
	 *
	 * @since SCF 6.5.0
	 * @since 6.7.0 Use is_valid_source() helper method.
	 *
	 * @param WP_REST_Response $response  The response object.
	 * @param WP_Post_Type     $post_type The post type object.
	 * @param WP_REST_Request  $request   The request object.
	 * @return WP_REST_Response|null The filtered response or null.
	 */
	public function filter_post_type( $response, $post_type, $request ) {
		$source = $request->get_param( 'source' );

		// Only apply filtering if source parameter is provided and valid
		if ( ! $source || ! $this->is_valid_source( $source ) ) {
			return $response;
		}

		$source_post_types = $this->get_source_post_types( $source );

		if ( ! in_array( $post_type->name, $source_post_types, true ) ) {
			return null;
		}

		return $response;
	}

	/**
	 * Get post types for a specific source.
	 *
	 * @since SCF 6.5.0
	 *
	 * @param string $source The source to get post types for (core, scf, other).
	 * @return array An array of post type names for the specified source.
	 */
	private function get_source_post_types( $source ) {
		$core_types = array();
		$scf_types  = array();

		// Get core post types
		if ( 'core' === $source || 'other' === $source ) {
			$all_post_types = get_post_types( array( '_builtin' => true ), 'objects' );
			foreach ( $all_post_types as $post_type ) {
				$core_types[] = $post_type->name;
			}
		}

		// Get SCF-managed post types
		if ( 'scf' === $source || 'other' === $source ) {
			if ( function_exists( 'acf_get_internal_post_type_posts' ) ) {
				$scf_managed_post_types = acf_get_internal_post_type_posts( 'acf-post-type' );
				foreach ( $scf_managed_post_types as $scf_post_type ) {
					$scf_types[] = $scf_post_type['post_type'];
				}
			}
		}

		switch ( $source ) {
			case 'core':
				return $core_types;
			case 'scf':
				return $scf_types;
			case 'other':
				return array_diff(
					array_keys( get_post_types( array(), 'objects' ) ),
					array_merge( $core_types, $scf_types )
				);
			default:
				return array();
		}
	}

	/**
	 * Register extra SCF fields for the post types endpoint.
	 *
	 * @since SCF 6.5.0
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
	 * @since SCF 6.5.0
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

			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					$group_fields[] = array(
						'name'  => $field['name'],
						'label' => $field['label'],
						'type'  => $field['type'],
					);
				}
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
	 * @since SCF 6.5.0
	 *
	 * @return array The schema for the SCF fields.
	 */
	private function get_field_schema() {
		return array(
			'description' => __( 'Field groups attached to this post type.', 'secure-custom-fields' ),
			'type'        => 'array',
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'title'  => array(
						'type'        => 'string',
						'description' => __( 'The field group title.', 'secure-custom-fields' ),
					),
					'fields' => array(
						'type'        => 'array',
						'description' => __( 'The fields in this field group.', 'secure-custom-fields' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'  => array(
									'type'        => 'string',
									'description' => __( 'The field name.', 'secure-custom-fields' ),
								),
								'label' => array(
									'type'        => 'string',
									'description' => __( 'The field label.', 'secure-custom-fields' ),
								),
								'type'  => array(
									'type'        => 'string',
									'description' => __( 'The field type.', 'secure-custom-fields' ),
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
	 * Register the source parameter for the post types endpoint.
	 *
	 * @since SCF 6.5.0
	 */
	public function register_parameters() {
		if ( ! acf_get_setting( 'rest_api_enabled' ) ) {
			return;
		}

		add_filter( 'rest_type_collection_params', array( $this, 'add_collection_params' ) );
		add_filter( 'rest_types_collection_params', array( $this, 'add_collection_params' ) );
		add_filter( 'rest_endpoints', array( $this, 'add_parameter_to_endpoints' ) );
	}

	/**
	 * Get the source parameter definition
	 *
	 * @since SCF 6.5.0
	 * @since 6.7.0 Use VALID_SOURCES constant.
	 *
	 * @return array Parameter definition
	 */
	private function get_source_param_definition() {
		return array(
			'description'       => __( 'Filter post types by their source.', 'secure-custom-fields' ),
			'type'              => 'string',
			'enum'              => self::VALID_SOURCES,
			'required'          => false,
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => null,
		);
	}

	/**
	 * Add source parameter directly to the endpoints for proper documentation
	 *
	 * @since SCF 6.5.0
	 *
	 * @param array $endpoints The REST API endpoints.
	 * @return array Modified endpoints
	 */
	public function add_parameter_to_endpoints( $endpoints ) {
		$source_param        = $this->get_source_param_definition();
		$endpoints_to_modify = array( '/wp/v2/types', '/wp/v2/types/(?P<type>[\w-]+)' );

		foreach ( $endpoints_to_modify as $route ) {
			if ( isset( $endpoints[ $route ] ) ) {
				foreach ( $endpoints[ $route ] as &$endpoint ) {
					if ( isset( $endpoint['args'] ) ) {
						$endpoint['args']['source'] = $source_param;
					}
				}
			}
		}

		return $endpoints;
	}

	/**
	 * Add source parameter to the collection parameters for the types endpoint.
	 *
	 * @since SCF 6.5.0
	 *
	 * @param array $query_params JSON Schema-formatted collection parameters.
	 * @return array Modified collection parameters.
	 */
	public function add_collection_params( $query_params ) {
		$query_params['source'] = $this->get_source_param_definition();
		return $query_params;
	}

	/**
	 * Clean up null entries from the response
	 *
	 * @since SCF 6.5.0
	 *
	 * @param array           $response The response data.
	 * @param WP_REST_Server  $server   The REST server instance.
	 * @param WP_REST_Request $request  The original request.
	 * @return array            The filtered response data.
	 */
	public function clean_types_response( $response, $server, $request ) {
		if ( ! preg_match( '#^/wp/v2/types(?:/|$)#', $request->get_route() ) ) {
			return $response;
		}

		// Only process collection responses (not single post type responses)
		// Single post type responses have a 'slug' property, collections don't
		if ( is_array( $response ) && ! isset( $response['slug'] ) ) {
			$response = array_filter(
				$response,
				function ( $entry ) {
					return null !== $entry;
				}
			);
		}

		return $response;
	}
}
