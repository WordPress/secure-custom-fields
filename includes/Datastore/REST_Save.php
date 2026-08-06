<?php
/**
 * SCF datastore integration.
 *
 * @package wordpress/secure-custom-fields
 */

namespace SCF\Datastore;

/**
 * Handles SCF datastore saves during Gutenberg / REST post requests.
 *
 * Decodes the _acf transport meta included on REST post requests, writes
 * individual field meta to the post and (when applicable) to the revision,
 * then cleans up the transport blob. Also strips the transport blob from
 * REST responses so it never leaks to clients.
 */
class REST_Save {

	/**
	 * Decoded SCF values from the current REST save.
	 * Set in save_post_rest(), consumed in save_revision_meta().
	 *
	 * @since ACF 6.8.1
	 * @var array|null
	 */
	private $current_acf_values = null;

	/**
	 * Post ID pending _acf cleanup.
	 * Set in save_post_rest(), consumed in cleanup_acf_transport_meta().
	 *
	 * @since ACF 6.8.1
	 * @var integer|null
	 */
	private $pending_cleanup_post_id = null;

	/**
	 * Stable bridge to the private datastore preflight.
	 *
	 * @since SCF 6.9.5
	 * @var \Closure
	 */
	private $preflight_datastore_callback;

	/**
	 * Constructor.
	 *
	 * Defers hook registration to rest_api_init so the
	 * acf/settings/enable_datastore filter is available to themes
	 * and plugins by the time the gate is evaluated.
	 *
	 * @since ACF 6.8.1
	 */
	public function __construct() {
		$this->preflight_datastore_callback = \Closure::fromCallable( array( $this, 'preflight_datastore_request' ) );
		add_action( 'rest_api_init', array( $this, 'maybe_register_rest_save_hooks' ) );

		// Skip the legacy ACF_Form_Post::save_post() during the metabox AJAX
		// (meta-box-loader) that follows each Gutenberg REST save -- the REST
		// path has already saved the values, so re-running the legacy save
		// would clobber them.
		add_filter( 'acf/form-post/skip_save', array( $this, 'skip_metabox_loader_save' ), 10, 3 );
	}

	/**
	 * Returns true when the current request is the meta-box-loader AJAX
	 * and the datastore is enabled.
	 *
	 * @since ACF 6.8.1
	 *
	 * @param boolean $skip    Whether the save should be skipped.
	 * @param integer $post_id The post ID being saved.
	 * @param mixed   $post    The post being saved.
	 * @return boolean
	 */
	public function skip_metabox_loader_save( $skip, $post_id, $post ) {
		unset( $post_id, $post );

		if ( $skip ) {
			return $skip;
		}

		return acf_maybe_get_GET( 'meta-box-loader', false ) && acf_is_using_datastore();
	}

	/**
	 * Conditionally registers REST save hooks for all public post types.
	 *
	 * @since ACF 6.8.1
	 *
	 * @return void
	 */
	public function maybe_register_rest_save_hooks() {
		if ( ! acf_is_using_datastore() ) {
			return;
		}

		add_filter( 'rest_dispatch_request', $this->preflight_datastore_callback, 10, 4 );
		add_action( '_wp_put_post_revision', array( $this, 'save_revision_meta' ), 11, 2 );

		foreach ( get_post_types( array( 'show_in_rest' => true ) ) as $post_type ) {
			// Post-save: decode _acf and write individual meta keys to post + revision.
			add_action( "rest_after_insert_{$post_type}", array( $this, 'save_post_rest' ), 10, 2 );

			// Strip _acf from post REST responses. Revisions use
			// rest_prepare_revision instead, so _acf passes through
			// for the revision viewer.
			add_filter( "rest_prepare_{$post_type}", array( $this, 'strip_acf_transport_meta' ) );
		}

		/**
		 * Autosave: WP_REST_Autosaves_Controller does not fire
		 * rest_after_insert_{post_type}, so this response filter is the only
		 * hook available in all autosave paths that carries the request object.
		 * No-ops on GET requests since the request body is empty.
		 */
		add_filter( 'rest_prepare_autosave', array( $this, 'save_autosave_rest' ), 10, 3 );

		// Fallback cleanup for the _acf transport meta. Runs at priority 20,
		// after the revision system (priority 9) has finished deciding whether
		// to create a revision. Catches orphaned _acf when no revision is
		// created (e.g., post type doesn't support revisions).
		add_action( 'wp_after_insert_post', array( $this, 'cleanup_acf_transport_meta' ), 20 );
	}

	/**
	 * Checks bidirectional datastore targets before dispatch.
	 *
	 * @since SCF 6.9.5
	 *
	 * @param mixed            $result  Result from an earlier dispatch filter.
	 * @param \WP_REST_Request $request Current REST request.
	 * @param string           $route   Matched REST route.
	 * @param array            $handler Matched route handler.
	 * @return mixed|\WP_Error
	 */
	private function preflight_datastore_request( $result, $request, $route, $handler ) {
		$meta   = $request->get_param( 'meta' );
		$values = is_array( $meta ) && ! empty( $meta['_acf'] ) ? json_decode( $meta['_acf'], true ) : null;
		if ( null !== $result || ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) || ! is_array( $values ) || ! isset( $handler['callback'][0] ) ) {
			return $result;
		}
		$controller  = $handler['callback'][0];
		$is_autosave = $controller instanceof \WP_REST_Autosaves_Controller;
		if ( ! $is_autosave && ! ( $controller instanceof \WP_REST_Posts_Controller ) ) {
			return $result;
		}

		$post_id        = isset( $request->get_url_params()['id'] ) ? absint( $request->get_param( 'id' ) ) : 0;
		$origin_id      = $post_id;
		$current_values = $post_id ? null : array();
		if ( $is_autosave ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return $result;
			}
			if ( ! function_exists( 'wp_check_post_lock' ) ) {
				require_once ABSPATH . 'wp-admin/includes/post.php';
			}
			if ( in_array( $post->post_status, array( 'draft', 'auto-draft' ), true ) && get_current_user_id() === (int) $post->post_author && ! wp_check_post_lock( $post_id ) ) {
				unset( $meta['_acf'] );
				$request->set_param( 'meta', $meta );
				return $result;
			}
			$autosave       = wp_get_post_autosave( $post_id, get_current_user_id() );
			$origin_id      = $autosave ? $autosave->ID : 0;
			$current_values = $autosave ? null : array();
			$location_args  = array( 'post_id' => $post_id );
		} elseif ( $post_id ) {
			$location_args = array( 'post_id' => $post_id );
		} else {
			$post_status   = $request->get_param( 'status' );
			$template      = $request->get_param( 'template' );
			$location_args = array(
				'post_type'   => $controller->get_item_schema()['title'],
				'post_status' => $post_status ? $post_status : 'draft',
			);
			if ( $template ) {
				$location_args['page_template'] = $template;
			}
		}

		$active_fields = array();
		foreach ( acf_get_field_groups( $location_args ) as $field_group ) {
			foreach ( (array) acf_get_fields( $field_group ) as $field ) {
				$prepared_field = apply_filters( 'acf/prepare_field', $field );
				if ( $prepared_field ) {
					$active_fields[ $prepared_field['key'] ] = true;
				}
			}
		}
		$filtered_values = $values;
		foreach ( $values as $field_key => $value ) {
			if ( ! acf_get_setting( 'enable_bidirection' ) ) {
				continue;
			}
			$is_active = isset( $active_fields[ $field_key ] );
			$field     = acf_get_field( $field_key );
			if ( ! $field ) {
				continue;
			}
			foreach ( _scf_collect_bidirectional_destinations( $field, $value, $origin_id, $current_values, true ) as $destination ) {
				if ( ! acf_current_user_can_edit_in_context( acf_decode_post_id( $destination ) ) ) {
					if ( ! $is_active ) {
						unset( $filtered_values[ $field_key ] );
						break;
					}
					return new \WP_Error( 'acf_rest_cannot_update_bidirectional_target', __( 'Sorry, you are not allowed to update one or more bidirectional targets.', 'secure-custom-fields' ), array( 'status' => 403 ) );
				}
			}
		}
		if ( $filtered_values !== $values ) {
			if ( $filtered_values ) {
				$meta['_acf'] = wp_json_encode( $filtered_values );
			} else {
				unset( $meta['_acf'] );
			}
			$request->set_param( 'meta', $meta );
		}
		return $result;
	}

	/**
	 * Writes SCF field values to the revision after WordPress creates it.
	 *
	 * Called via _wp_put_post_revision, which fires AFTER rest_after_insert
	 * (where save_post_rest writes values to the post). At this point the
	 * decoded values are available in $this->current_acf_values.
	 *
	 * @since ACF 6.8.1
	 *
	 * @param integer $revision_id The revision ID.
	 * @param integer $post_id     The parent post ID.
	 * @return void
	 */
	public function save_revision_meta( $revision_id, $post_id ) {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return;
		}

		$is_autosave = wp_is_post_autosave( $revision_id );

		// Clean up the transport-only _acf blob from the post.
		// wp_save_revisioned_meta_fields (priority 10) has already copied
		// it to the revision for future comparison.
		if ( ! $is_autosave ) {
			delete_post_meta( $post_id, '_acf' );
		}

		if ( empty( $this->current_acf_values ) || $is_autosave ) {
			return;
		}

		$values = $this->current_acf_values;

		if ( ! acf_allow_unfiltered_html() ) {
			$values = wp_kses_post_deep( $values );
		}

		acf_update_values( $values, $revision_id );

		$this->current_acf_values = null;
	}

	/**
	 * Processes SCF field values from the REST request.
	 * Decodes the _acf blob and saves individual meta keys to the post.
	 *
	 * Revision meta is handled separately by save_revision_meta(), which
	 * fires later via _wp_put_post_revision after WordPress creates the
	 * revision inside wp_after_insert_post().
	 *
	 * @since ACF 6.8.1
	 *
	 * @param \WP_Post         $post    The post object.
	 * @param \WP_REST_Request $request The REST request.
	 * @return void
	 */
	public function save_post_rest( $post, $request ) {
		// Check if _acf data was included in the request.
		$meta = $request->get_param( 'meta' );
		if ( empty( $meta['_acf'] ) ) {
			return;
		}

		// Retrieve and decode the JSON.
		$acf_json = get_post_meta( $post->ID, '_acf', true );
		$values   = is_string( $acf_json ) ? json_decode( $acf_json, true ) : null;

		if ( empty( $values ) || ! is_array( $values ) ) {
			return;
		}

		// Save individual meta keys to the post.
		// Fires acf/save_post action, runs all ACF processing hooks.
		acf_save_post( $post->ID, $values );

		// Store values for save_revision_meta(), which fires later
		// via _wp_put_post_revision (after wp_after_insert_post creates
		// the revision). Don't delete _acf yet -- the revision comparison
		// needs it on the post when deciding whether to create a revision.
		// cleanup_acf_transport_meta() handles deletion after the revision
		// system finishes.
		$this->current_acf_values      = $values;
		$this->pending_cleanup_post_id = $post->ID;
	}

	/**
	 * Handles SCF values during autosave REST requests.
	 *
	 * @since ACF 6.8.1
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     The post object.
	 * @param \WP_REST_Request  $request  The REST request.
	 * @return \WP_REST_Response
	 */
	public function save_autosave_rest( $response, $post, $request ) {
		if ( ! wp_is_post_autosave( $post ) ) {
			return $response;
		}
		$this->save_post_rest( $post, $request );
		return $response;
	}

	/**
	 * Cleans up the transport-only _acf meta after the revision system finishes.
	 *
	 * Hooked to wp_after_insert_post at priority 20, which runs after
	 * wp_save_post_revision_on_insert (priority 9). This catches orphaned
	 * _acf when no revision is created (e.g., post type doesn't support
	 * revisions). When a revision IS created, save_revision_meta() already
	 * deleted _acf, so this is a harmless no-op.
	 *
	 * @since ACF 6.8.1
	 *
	 * @param integer $post_id The post ID.
	 * @return void
	 */
	public function cleanup_acf_transport_meta( $post_id ) {
		if ( null === $this->pending_cleanup_post_id || (int) $this->pending_cleanup_post_id !== (int) $post_id ) {
			return;
		}

		delete_post_meta( $post_id, '_acf' );
		$this->current_acf_values      = null;
		$this->pending_cleanup_post_id = null;
	}

	/**
	 * Strips _acf from post REST responses.
	 *
	 * The _acf meta is transport-only and should not appear in post
	 * responses. Revision responses use rest_prepare_revision instead,
	 * so _acf passes through for the revision viewer.
	 *
	 * @since ACF 6.8.1
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @return \WP_REST_Response
	 */
	public function strip_acf_transport_meta( $response ) {
		$data = $response->get_data();

		if ( isset( $data['meta']['_acf'] ) ) {
			$data['meta']['_acf'] = '';
			$response->set_data( $data );
		}

		return $response;
	}
}
