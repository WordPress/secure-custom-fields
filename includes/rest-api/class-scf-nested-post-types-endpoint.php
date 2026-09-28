<?php
/**
 * Nested Post Types REST endpoint.
 *
 * Exposes a single endpoint to set the nested parent of a post. The route is
 * only registered while the nested_post_types beta feature is enabled.
 *
 * @package    Secure Custom Fields
 * @since      SCF {NEXT_MAJOR_VERSION}
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'SCF_Rest_Nested_Post_Types_Endpoint' ) ) :
	/**
	 * Class SCF_Rest_Nested_Post_Types_Endpoint
	 *
	 * Registers and handles the scf/v1 nested parent route.
	 *
	 * @since SCF {NEXT_MAJOR_VERSION}
	 */
	class SCF_Rest_Nested_Post_Types_Endpoint {

		/**
		 * The REST namespace.
		 *
		 * @var string
		 */
		const REST_NAMESPACE = 'scf/v1';

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Registers the nested parent route when the beta feature is enabled.
		 *
		 * @return void
		 */
		public function register_routes() {
			if ( ! SCF_Nested_Post_Types::enabled() ) {
				return;
			}

			register_rest_route(
				self::REST_NAMESPACE,
				'/nested-parent/(?P<id>\d+)',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_parent' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'id'        => array(
							'type'              => 'integer',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						),
						'parent_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						),
					),
				)
			);

			register_rest_route(
				self::REST_NAMESPACE,
				'/nested-parent/(?P<id>\d+)',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_parent' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						),
					),
				)
			);
		}

		/**
		 * Checks that the current user can edit the target post.
		 *
		 * @param WP_REST_Request $request The request.
		 * @return boolean|WP_Error
		 */
		public function permissions_check( $request ) {
			$post_id = absint( $request['id'] );
			if ( ! $post_id || ! get_post( $post_id ) ) {
				return new WP_Error( 'scf_nested_parent_invalid_post', __( 'The post could not be found.', 'secure-custom-fields' ), array( 'status' => 404 ) );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_Error(
					'scf_nested_parent_forbidden',
					__( 'Sorry, you are not allowed to nest this post.', 'secure-custom-fields' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			return true;
		}

		/**
		 * Gets the nested parent of a post.
		 *
		 * @param WP_REST_Request $request The request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_parent( $request ) {
			$nested = SCF_Nested_Post_Types::instance();
			if ( ! $nested ) {
				return new WP_Error( 'scf_nested_parent_disabled', __( 'Nested post types are not enabled.', 'secure-custom-fields' ), array( 'status' => 404 ) );
			}

			$post_id   = absint( $request['id'] );
			$parent_id = $nested->get_parent_id( $post_id );

			return rest_ensure_response(
				array(
					'post_id'   => $post_id,
					'parent_id' => $parent_id,
				)
			);
		}

		/**
		 * Sets the nested parent and returns the updated relationship.
		 *
		 * @param WP_REST_Request $request The request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function update_parent( $request ) {
			$nested = SCF_Nested_Post_Types::instance();
			if ( ! $nested ) {
				return new WP_Error( 'scf_nested_parent_disabled', __( 'Nested post types are not enabled.', 'secure-custom-fields' ), array( 'status' => 404 ) );
			}

			$post_id   = absint( $request['id'] );
			$parent_id = absint( $request['parent_id'] );

			$result = $nested->set_parent( $post_id, $parent_id );
			if ( is_wp_error( $result ) ) {
				$result->add_data( array( 'status' => 400 ) );
				return $result;
			}

			return rest_ensure_response(
				array(
					'post_id'   => $post_id,
					'parent_id' => $parent_id,
					'permalink' => get_permalink( $post_id ),
				)
			);
		}
	}
endif;
