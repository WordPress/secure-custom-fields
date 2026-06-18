<?php
/**
 * Test Secure Custom Fields REST API Types Endpoint functionality.
 *
 * @package wordpress/secure-custom-fields
 * @group rest-api
 */

use WorDBless\BaseTestCase;

acf_include( 'includes/rest-api/class-acf-rest-types-endpoint.php' );

/**
 * Tests for the SCF_Rest_Types_Endpoint class.
 */
class Test_REST_Types_Endpoint extends BaseTestCase {

	/**
	 * The endpoint instance being tested.
	 *
	 * @var SCF_Rest_Types_Endpoint
	 */
	protected $endpoint;

	/**
	 * Reflection for accessing private methods.
	 *
	 * @var ReflectionClass
	 */
	protected $reflection;

	/**
	 * The get_source_post_types method.
	 *
	 * @var ReflectionMethod
	 */
	protected $source_method;

	/**
	 * The test post type name.
	 *
	 * @var string
	 */
	protected $test_post_type = 'test-post-type';

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		// Create the endpoint instance.
		$this->endpoint = new SCF_Rest_Types_Endpoint();

		// Set up reflection for accessing private methods.
		$this->reflection = new ReflectionClass( $this->endpoint );

		// Access the get_source_post_types method.
		$this->source_method = $this->reflection->getMethod( 'get_source_post_types' );
		$this->source_method->setAccessible( true );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		// Check if our test post type needs to be unregistered.
		if ( post_type_exists( $this->test_post_type ) ) {
			unregister_post_type( $this->test_post_type );
		}

		// Clean up any SCF post type definitions created by tests.
		$posts = get_posts(
			array(
				'post_type'   => 'acf-post-type',
				'numberposts' => -1,
				'post_status' => 'any',
			)
		);
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		if ( $this->load_post_types_filter ) {
			remove_filter( 'acf/load_post_types', $this->load_post_types_filter );
			$this->load_post_types_filter = null;
		}

		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Filter callback injecting the test post type definition.
	 *
	 * @var callable|null
	 */
	private $load_post_types_filter;

	/**
	 * Create an SCF-managed post type definition for testing.
	 *
	 * WorDBless cannot query posts by post type, so the definition is also
	 * injected via the acf/load_post_types filter to make it visible to
	 * acf_get_acf_post_types(). The underlying post is real, so capability
	 * checks against it behave as in production.
	 *
	 * @return int The internal acf-post-type post ID.
	 */
	private function create_scf_post_type() {
		// Ensure ACF internal post type instances are initialized.
		// WorDBless fires plugins_loaded and init BEFORE our plugin is loaded,
		// so we need to fire them again to run our plugin's callbacks.
		if ( ! acf_get_internal_post_type_instance( 'acf-post-type' ) ) {
			do_action( 'plugins_loaded' );
			do_action( 'init' );
		}

		$post_type = acf_update_post_type(
			array(
				'key'       => 'post_type_' . uniqid(),
				'title'     => 'Test Post Type',
				'post_type' => 'test_cpt',
				'active'    => true,
			)
		);

		$this->load_post_types_filter = function ( $post_types ) use ( $post_type ) {
			$post_types[] = $post_type;
			return $post_types;
		};
		add_filter( 'acf/load_post_types', $this->load_post_types_filter );

		return (int) $post_type['ID'];
	}

	/**
	 * Create a user with the given role and set it as the current user.
	 *
	 * @param string $role The role for the new user.
	 * @return int The user ID.
	 */
	private function login_as( $role ) {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'scf_' . $role . '_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => $role,
			)
		);
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Test that the SCF_Rest_Types_Endpoint class exists.
	 */
	public function test_endpoint_class_exists() {
		$this->assertTrue( class_exists( 'SCF_Rest_Types_Endpoint' ) );
	}

	/**
	 * Test that the source parameter is properly added to collection params.
	 */
	public function test_add_collection_params() {
		// Test with empty parameters.
		$empty_params          = array();
		$modified_empty_params = $this->endpoint->add_collection_params( $empty_params );

		$this->assertArrayHasKey( 'source', $modified_empty_params );
		$this->assertCount( 1, $modified_empty_params );

		// Test with existing parameters.
		$existing_params          = array(
			'context' => array(
				'default' => 'view',
				'enum'    => array( 'view', 'embed', 'edit' ),
			),
		);
		$modified_existing_params = $this->endpoint->add_collection_params( $existing_params );

		$this->assertArrayHasKey( 'source', $modified_existing_params );
		$this->assertArrayHasKey( 'context', $modified_existing_params );
		$this->assertCount( 2, $modified_existing_params );
		$this->assertEquals( 'view', $modified_existing_params['context']['default'] );

		// Check parameter properties.
		$source_param = $modified_existing_params['source'];
		$this->assertEquals( 'string', $source_param['type'] );
		$this->assertFalse( $source_param['required'] );
		$this->assertContains( 'core', $source_param['enum'] );
		$this->assertContains( 'scf', $source_param['enum'] );
		$this->assertContains( 'other', $source_param['enum'] );
		$this->assertArrayHasKey( 'validate_callback', $source_param );
		$this->assertArrayHasKey( 'sanitize_callback', $source_param );
	}

	/**
	 * Test the get_source_post_types method for SCF post types
	 */
	public function test_get_source_post_types_scf() {
		$scf_types = $this->source_method->invoke( $this->endpoint, 'scf' );

		$this->assertIsArray( $scf_types, 'SCF types should be an array' );

		// Should not include core types
		$this->assertNotContains( 'post', $scf_types );
		$this->assertNotContains( 'page', $scf_types );
	}

	/**
	 * Test the get_source_post_types method for core post types
	 */
	public function test_get_source_post_types_core() {
		$core_types = $this->source_method->invoke( $this->endpoint, 'core' );

		$this->assertIsArray( $core_types, 'Core types should be an array' );

		// Check for core post types.
		$this->assertContains( 'post', $core_types );
		$this->assertContains( 'page', $core_types );

		// Should not include SCF types.
		$this->assertNotContains( 'acf-field-group', $core_types );
		$this->assertNotContains( 'acf-post-type', $core_types );
	}

	/**
	 * Test the get_source_post_types method for other post types
	 */
	public function test_get_source_post_types_other() {
		// Register a test post type.
		register_post_type(
			$this->test_post_type,
			array(
				'labels' => array( 'name' => 'Test Post Type' ),
				'public' => true,
			)
		);

		$other_types = $this->source_method->invoke( $this->endpoint, 'other' );

		$this->assertIsArray( $other_types, 'Other types should be an array' );

		// Should include our test post type.
		$this->assertContains( $this->test_post_type, $other_types );

		// Should not include core types
		$this->assertNotContains( 'post', $other_types );
		$this->assertNotContains( 'page', $other_types );
	}

	/**
	 * Test the get_source_post_types method with an invalid source parameter
	 */
	public function test_get_source_post_types_invalid() {
		$invalid_types = $this->source_method->invoke( $this->endpoint, 'invalid' );

		$this->assertIsArray( $invalid_types );
		$this->assertEmpty( $invalid_types, 'Invalid source should return empty array' );
	}

	/**
	 * Test the source parameter definition.
	 */
	public function test_source_parameter_definition() {
		$param_method = $this->reflection->getMethod( 'get_source_param_definition' );
		$param_method->setAccessible( true );

		$param_def = $param_method->invoke( $this->endpoint );
		$this->assertEquals( 'string', $param_def['type'] );
		$this->assertFalse( $param_def['required'] );
		$this->assertContains( 'core', $param_def['enum'] );
		$this->assertContains( 'scf', $param_def['enum'] );
		$this->assertContains( 'other', $param_def['enum'] );
		$this->assertCount( 3, $param_def['enum'] );
		$this->assertArrayHasKey( 'validate_callback', $param_def );
		$this->assertArrayHasKey( 'sanitize_callback', $param_def );
		$this->assertEquals( 'rest_validate_request_arg', $param_def['validate_callback'] );
		$this->assertEquals( 'sanitize_text_field', $param_def['sanitize_callback'] );
	}

	/**
	 * Test the is_valid_source method.
	 */
	public function test_is_valid_source() {
		$is_valid_method = $this->reflection->getMethod( 'is_valid_source' );
		$is_valid_method->setAccessible( true );

		// Valid sources
		$this->assertTrue( $is_valid_method->invoke( $this->endpoint, 'core' ) );
		$this->assertTrue( $is_valid_method->invoke( $this->endpoint, 'scf' ) );
		$this->assertTrue( $is_valid_method->invoke( $this->endpoint, 'other' ) );

		// Invalid sources
		$this->assertFalse( $is_valid_method->invoke( $this->endpoint, 'invalid' ) );
		$this->assertFalse( $is_valid_method->invoke( $this->endpoint, '' ) );
		$this->assertFalse( $is_valid_method->invoke( $this->endpoint, null ) );
		$this->assertFalse( $is_valid_method->invoke( $this->endpoint, 'CORE' ) );
	}

	/**
	 * Test filter_types_request with invalid source.
	 */
	public function test_filter_types_request_invalid_source() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types/post' );
		$request->set_param( 'source', 'invalid' );

		$response = $this->endpoint->filter_types_request( null, array(), $request );

		// Should not modify response for invalid source
		$this->assertNull( $response );
	}

	/**
	 * Test filter_types_request without source parameter.
	 */
	public function test_filter_types_request_no_source() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types/post' );

		$response = $this->endpoint->filter_types_request( null, array(), $request );

		// Should not modify response when no source parameter
		$this->assertNull( $response );
	}

	/**
	 * Test filter_types_request for collection endpoint.
	 */
	public function test_filter_types_request_collection() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types' );
		$request->set_param( 'source', 'core' );

		$response = $this->endpoint->filter_types_request( null, array(), $request );

		// Should not modify collection requests
		$this->assertNull( $response );
	}

	/**
	 * Test filter_types_request with matching source.
	 */
	public function test_filter_types_request_matching_source() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types/post' );
		$request->set_param( 'source', 'core' );

		$response = $this->endpoint->filter_types_request( null, array(), $request );

		// Should not modify response for matching source
		$this->assertNull( $response );
	}

	/**
	 * Test filter_types_request with non-matching source.
	 */
	public function test_filter_types_request_non_matching_source() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types/post' );
		$request->set_param( 'source', 'scf' );

		$response = $this->endpoint->filter_types_request( null, array(), $request );

		// Should return error for non-matching source
		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'rest_post_type_invalid', $response->get_error_code() );
		$this->assertEquals( 404, $response->get_error_data()['status'] );
	}

	/**
	 * Test filter_post_type without source parameter.
	 */
	public function test_filter_post_type_no_source() {
		$request   = new WP_REST_Request( 'GET', '/wp/v2/types' );
		$response  = new WP_REST_Response( array( 'slug' => 'post' ) );
		$post_type = get_post_type_object( 'post' );

		$filtered = $this->endpoint->filter_post_type( $response, $post_type, $request );

		// Should not filter when no source parameter
		$this->assertEquals( $response, $filtered );
	}

	/**
	 * Test filter_post_type with invalid source.
	 */
	public function test_filter_post_type_invalid_source() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types' );
		$request->set_param( 'source', 'invalid' );
		$response  = new WP_REST_Response( array( 'slug' => 'post' ) );
		$post_type = get_post_type_object( 'post' );

		$filtered = $this->endpoint->filter_post_type( $response, $post_type, $request );

		// Should not filter with invalid source
		$this->assertEquals( $response, $filtered );
	}

	/**
	 * Test filter_post_type with matching source.
	 */
	public function test_filter_post_type_matching_source() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types' );
		$request->set_param( 'source', 'core' );
		$response  = new WP_REST_Response( array( 'slug' => 'post' ) );
		$post_type = get_post_type_object( 'post' );

		$filtered = $this->endpoint->filter_post_type( $response, $post_type, $request );

		// Should return response for matching source
		$this->assertEquals( $response, $filtered );
	}

	/**
	 * Test filter_post_type with non-matching source.
	 */
	public function test_filter_post_type_non_matching_source() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/types' );
		$request->set_param( 'source', 'scf' );
		$response  = new WP_REST_Response( array( 'slug' => 'post' ) );
		$post_type = get_post_type_object( 'post' );

		$filtered = $this->endpoint->filter_post_type( $response, $post_type, $request );

		// Should return null for non-matching source
		$this->assertNull( $filtered );
	}

	/**
	 * Test clean_types_response with collection.
	 */
	public function test_clean_types_response_collection() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/types' );
		$server   = rest_get_server();
		$response = array(
			'post' => array( 'slug' => 'post' ),
			'page' => array( 'slug' => 'page' ),
			null,
		);

		$cleaned = $this->endpoint->clean_types_response( $response, $server, $request );

		// Should remove null entries
		$this->assertCount( 2, $cleaned );
		$this->assertArrayHasKey( 'post', $cleaned );
		$this->assertArrayHasKey( 'page', $cleaned );
		$this->assertArrayNotHasKey( 2, $cleaned );
	}

	/**
	 * Test clean_types_response with single post type.
	 */
	public function test_clean_types_response_single() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/types/post' );
		$server   = rest_get_server();
		$response = array(
			'slug' => 'post',
			'name' => 'Posts',
		);

		$cleaned = $this->endpoint->clean_types_response( $response, $server, $request );

		// Should not modify single post type response
		$this->assertEquals( $response, $cleaned );
	}

	/**
	 * Test clean_types_response for non-types endpoint.
	 */
	public function test_clean_types_response_other_endpoint() {
		$request  = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$server   = rest_get_server();
		$response = array( 'data' => 'value' );

		$cleaned = $this->endpoint->clean_types_response( $response, $server, $request );

		// Should not modify other endpoints
		$this->assertEquals( $response, $cleaned );
	}

	/**
	 * Test get_scf_fields with mock data.
	 */
	public function test_get_scf_fields() {
		$post_type_object = array( 'slug' => 'post' );

		$fields = $this->endpoint->get_scf_fields( $post_type_object );

		$this->assertIsArray( $fields );
	}

	/**
	 * Test that get_scf_post_id returns the internal post ID for users who
	 * can edit the post type definition.
	 */
	public function test_get_scf_post_id_visible_to_privileged_user() {
		$scf_post_id = $this->create_scf_post_type();
		$this->login_as( 'administrator' );

		$result = $this->endpoint->get_scf_post_id( array( 'slug' => 'test_cpt' ) );

		$this->assertSame( $scf_post_id, $result );
	}

	/**
	 * Test that get_scf_post_id returns null for users who cannot edit the
	 * post type definition.
	 */
	public function test_get_scf_post_id_hidden_from_unprivileged_user() {
		$this->create_scf_post_type();
		$this->login_as( 'subscriber' );

		$result = $this->endpoint->get_scf_post_id( array( 'slug' => 'test_cpt' ) );

		$this->assertNull( $result );
	}

	/**
	 * Test that get_scf_post_id returns null for logged-out users.
	 */
	public function test_get_scf_post_id_hidden_from_logged_out_user() {
		$this->create_scf_post_type();
		wp_set_current_user( 0 );

		$result = $this->endpoint->get_scf_post_id( array( 'slug' => 'test_cpt' ) );

		$this->assertNull( $result );
	}

	/**
	 * Test that get_scf_post_id returns null for post types not managed by SCF.
	 */
	public function test_get_scf_post_id_null_for_unmanaged_post_type() {
		$this->login_as( 'administrator' );

		$result = $this->endpoint->get_scf_post_id( array( 'slug' => 'post' ) );

		$this->assertNull( $result );
	}

	/**
	 * Test add_parameter_to_endpoints.
	 */
	public function test_add_parameter_to_endpoints() {
		$endpoints = array(
			'/wp/v2/types'                  => array(
				array(
					'methods'  => 'GET',
					'callback' => 'test_callback',
					'args'     => array(),
				),
			),
			'/wp/v2/types/(?P<type>[\w-]+)' => array(
				array(
					'methods'  => 'GET',
					'callback' => 'test_callback',
					'args'     => array(),
				),
			),
		);

		$modified = $this->endpoint->add_parameter_to_endpoints( $endpoints );

		$this->assertArrayHasKey( 'source', $modified['/wp/v2/types'][0]['args'] );
		$this->assertArrayHasKey( 'source', $modified['/wp/v2/types/(?P<type>[\w-]+)'][0]['args'] );
	}
}
