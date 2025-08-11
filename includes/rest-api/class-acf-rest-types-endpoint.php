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
	 * Initialize the class.
	 *
	 * @since SCF 6.5.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_extra_fields' ) );
		add_action( 'rest_api_init', array( $this, 'register_parameters' ) );

		// Add filter to process REST API requests by route
		add_filter( 'rest_request_before_callbacks', array( $this, 'filter_types_request' ), 10, 3 );

		// Add filter to process each post type individually
		add_filter( 'rest_prepare_post_type', array( $this, 'filter_post_type' ), 10, 3 );

		// Clean up null entries from the response
		add_filter( 'rest_pre_echo_response', array( $this, 'clean_types_response' ), 10, 3 );
	}

	/**
	 * Filter post types requests, fires for both collection and individual requests.
	 * We only want to handle individual requets to ensure the post type requested matches the source.
	 *
	 * @since SCF 6.5.0
	 *
	 * @param mixed           $response The current response, either response or null.
	 * @param array           $handler  The handler for the route.
	 * @param WP_REST_Request $request  The request object.
	 * @return mixed The response or null.
	 */
	public function filter_types_request( $response, $handler, $request ) {
		// We only want to handle individual requests
		$route = $request->get_route();
		if ( ! preg_match( '#^/wp/v2/types/([^/]+)$#', $route, $matches ) ) {
			return $response;
		}

		$source = $request->get_param( 'source' );

		// Only proceed if source parameter is provided and valid
		if ( ! $source || ! in_array( $source, array( 'core', 'scf', 'other' ), true ) ) {
			return $response;
		}

		$source_post_types = $this->get_source_post_types( $source );

		// Check if the requested type matches the source
		$requested_type = $matches[1];
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
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post_Type     $post_type The post type object.
	 * @param WP_REST_Request  $request The request object.
	 * @return WP_REST_Response|null The filtered response or null to filter it out.
	 */
	public function filter_post_type( $response, $post_type, $request ) {
		$source = $request->get_param( 'source' );

		// Only apply filtering if source parameter is provided and valid
		if ( ! $source || ! in_array( $source, array( 'core', 'scf', 'other' ), true ) ) {
			return $response;
		}

		$source_post_types = $this->get_source_post_types( $source );

		if ( ! in_array( $post_type->name, $source_post_types, true ) ) {
			return null;
		}

		return $response;
	}

	/**
	 * Get an array of post types for each source.
	 *
	 * @since SCF 6.5.0
	 *
	 * @param string $source The source to get post types for.
	 * @return array An array of post type names for the specified source.
	 */
	private function get_source_post_types( $source ) {

		$core_types = array();
		$scf_types  = array();

		if ( 'core' === $source || 'other' === $source ) {
			$all_post_types = get_post_types( array( '_builtin' => true ), 'objects' );
			foreach ( $all_post_types as $post_type ) {
				$core_types[] = $post_type->name;
			}
		}

		if ( 'scf' === $source || 'other' === $source ) {
			// Get SCF-managed post types
			if ( function_exists( 'acf_get_internal_post_type_posts' ) ) {
				$scf_managed_post_types = acf_get_internal_post_type_posts( 'acf-post-type' );
				foreach ( $scf_managed_post_types as $scf_post_type ) {
					$scf_types[] = $scf_post_type['post_type'];
				}
			}
		}

		switch ( $source ) {
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
	 * @since SCF 6.5.0
	 *
	 * @return void
	 */
	public function register_extra_fields() {
		if ( ! (bool) get_option( 'scf_beta_feature_editor_sidebar_enabled', false ) ) {
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
	 * @since SCF 6.5.0
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
	 * Register the source parameter for the post types endpoint.
	 *
	 * @since SCF 6.5.0
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
	 * Get the source parameter definition
	 *
	 * @since SCF 6.5.0
	 *
	 * @return array Parameter definition
	 */
	private function get_source_param_definition() {
		return array(
			'description'       => __( 'Filter post types by their source.', 'secure-custom-fields' ),
			'type'              => 'string',
			'enum'              => array( 'core', 'scf', 'other' ),
			'required'          => false,
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => null,
			'in'                => 'query',
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
