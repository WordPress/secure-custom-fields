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
		add_filter( 'rest_request_after_callbacks', array( $this, 'filter_response' ), 10, 3 );
	}

	/**
	 * Filter REST API response for types endpoint.
	 *
	 * @since 6.5.0
	 *
	 * @param WP_REST_Response|WP_Error $response The response data.
	 * @param array                     $handler  Route handler used for the request.
	 * @param WP_REST_Request           $request  Request used to generate the response.
	 * @return WP_REST_Response|WP_Error The filtered response.
	 */
	public function filter_response( $response, $handler, $request ) {
		// Only filter responses for the types endpoint
		if ( strpos( $request->get_route(), '/wp/v2/types' ) !== 0 ) {
			return $response;
		}

		return $this->maybe_filter_by_origin( $response, $request );
	}

	/**
	 * Filter response by origin if requested.
	 *
	 * @since 6.5.0
	 *
	 * @param mixed           $response The response object.
	 * @param WP_REST_Request $request The request object.
	 * @return mixed Filtered response object.
	 */
	private function maybe_filter_by_origin( $response, $request ) {
		// Get origin parameter from request URL query params directly if not available in request params
		$origin = $request->get_param( 'origin' );

		if ( ! $origin ) {
			// Try to get from request URL if it's not in the params
			$url_params = $request->get_query_params();
			if ( isset( $url_params['origin'] ) ) {
				$origin = $url_params['origin'];
			}
		}

		// Skip filtering if conditions aren't met
		if ( ! $origin || is_wp_error( $response ) || empty( $response->data ) ) {
			return $response;
		}

		// Skip filtering for unsupported origin values
		if ( in_array( $origin, array( 'core', 'scf', 'other' ), true ) ) {
			// Pre-calculate lists of post types by origin (only as needed)
			$core_types = array();
			$scf_types  = array();

			// Only get core post types if needed
			if ( 'core' === $origin || 'other' === $origin ) {
				$all_post_types = get_post_types( array( '_builtin' => true ), 'objects' );
				foreach ( $all_post_types as $post_type ) {
					$core_types[] = $post_type->name;
				}
			}

			// Get all SCF-managed post types (only if we need them)
			if ( 'scf' === $origin || 'other' === $origin ) {
				$scf_post_types = acf_get_internal_post_type_posts( 'acf-post-type' );
				foreach ( $scf_post_types as $scf_post_type ) {
					if ( isset( $scf_post_type['post_type'] ) ) {
						$scf_types[] = $scf_post_type['post_type'];
					}
				}
			}

			// Define a simple function to check if a post type matches the requested origin
			$matches_origin = function ( $post_type_name ) use ( $origin, $core_types, $scf_types ) {
				$result = false;

				switch ( $origin ) {
					case 'core':
						$result = in_array( $post_type_name, $core_types, true );
						break;
					case 'scf':
						$result = in_array( $post_type_name, $scf_types, true );
						break;
					case 'other':
						$result = ! in_array( $post_type_name, $core_types, true ) && ! in_array( $post_type_name, $scf_types, true );
						break;
				}

				return $result;
			};

			// Handle single item response and collections separately
			if ( isset( $response->data['slug'] ) ) {
				if ( ! $matches_origin( $response->data['slug'] ) ) {
					$response = new WP_Error(
						'rest_post_type_invalid_origin',
						__( 'The requested post type is not of the specified origin.', 'secure-custom-fields' ),
						array( 'status' => 404 )
					);
				}
			} elseif ( is_array( $response->data ) ) {
				$response->data = array_filter(
					$response->data,
					function ( $post_type_data ) use ( $matches_origin ) {
						return $matches_origin( $post_type_data['slug'] );
					}
				);
			}
		}

		return $response;
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
			'description' => __( 'Filter post types by their origin.', 'secure-custom-fields' ),
			'type'        => 'string',
			'enum'        => array( 'core', 'scf', 'other' ),
		);

		return $query_params;
	}
}
