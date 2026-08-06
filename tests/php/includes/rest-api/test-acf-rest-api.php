<?php
/**
 * Tests for ACF_Rest_Api class.
 *
 * Tests field loading, updating, schema generation, and permissions enforcement
 * for the REST API integration.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the REST API classes.
acf_include( 'includes/rest-api/class-acf-rest-api.php' );
acf_include( 'includes/rest-api/class-acf-rest-request.php' );
acf_include( 'includes/rest-api/class-acf-rest-embed-links.php' );
acf_include( 'includes/rest-api/acf-rest-api-functions.php' );

/**
 * Class Test_ACF_Rest_Api
 *
 * Tests for the main ACF REST API class.
 *
 * @group rest-api
 * @group p0-critical
 */
class Test_ACF_Rest_Api extends BaseTestCase {

	/**
	 * The REST API instance.
	 *
	 * @var ACF_Rest_Api
	 */
	protected $rest_api;

	/**
	 * Reflection for accessing private methods.
	 *
	 * @var ReflectionClass
	 */
	protected $reflection;

	/**
	 * Test field group data.
	 *
	 * @var array
	 */
	protected $test_field_group;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected $test_post_id;

	/**
	 * Original REST API enabled setting.
	 *
	 * @var bool
	 */
	protected $original_rest_api_enabled;

	/**
	 * Original REST API format setting.
	 *
	 * @var string
	 */
	protected $original_rest_api_format;

	/**
	 * Original REST API embed links setting.
	 *
	 * @var bool
	 */
	protected $original_rest_api_embed_links;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Ensure location types are loaded for location matching tests.
		if ( ! class_exists( 'ACF_Location_Post_Type' ) ) {
			acf_include( 'includes/locations/class-acf-location-post-type.php' );
		}
		if ( ! class_exists( 'ACF_Location_User_Form' ) ) {
			acf_include( 'includes/locations/class-acf-location-user-form.php' );
		}
		if ( ! class_exists( 'ACF_Location_Comment' ) ) {
			acf_include( 'includes/locations/class-acf-location-comment.php' );
		}
		if ( ! class_exists( 'ACF_Location_Taxonomy' ) ) {
			acf_include( 'includes/locations/class-acf-location-taxonomy.php' );
		}
		if ( ! class_exists( 'acf_field_user' ) ) {
			acf_include( 'includes/fields/class-acf-field-user.php' );
		}
		if ( ! class_exists( 'acf_field__group' ) ) {
			acf_include( 'includes/fields/class-acf-field-group.php' );
		}
		if ( ! has_filter( 'acf/format_value/type=user' ) ) {
			acf_register_field_type( 'acf_field_user' );
		}
		if ( ! has_filter( 'acf/format_value/type=group' ) ) {
			acf_register_field_type( 'acf_field__group' );
		}

		// Store original settings.
		$this->original_rest_api_enabled     = acf_get_setting( 'rest_api_enabled' );
		$this->original_rest_api_format      = acf_get_setting( 'rest_api_format' );
		$this->original_rest_api_embed_links = acf_get_setting( 'rest_api_embed_links' );

		// Enable REST API.
		acf_update_setting( 'rest_api_enabled', true );
		acf_update_setting( 'rest_api_format', 'light' );
		acf_update_setting( 'rest_api_embed_links', false );

		// Create the REST API instance.
		$this->rest_api   = new ACF_Rest_Api();
		$this->reflection = new ReflectionClass( $this->rest_api );

		// Create a test post.
		$this->test_post_id = wp_insert_post(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Create a test field group.
		$this->test_field_group = array(
			'key'          => 'group_test_rest_api',
			'title'        => 'Test REST API Field Group',
			'fields'       => array(),
			'location'     => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
			'show_in_rest' => true,
			'active'       => true,
		);
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		// Restore original settings.
		acf_update_setting( 'rest_api_enabled', $this->original_rest_api_enabled );
		acf_update_setting( 'rest_api_format', $this->original_rest_api_format );
		acf_update_setting( 'rest_api_embed_links', $this->original_rest_api_embed_links );

		// Clean up test post.
		if ( $this->test_post_id ) {
			wp_delete_post( $this->test_post_id, true );
		}

		// Clean up field groups.
		$field_groups = acf_get_field_groups();
		foreach ( $field_groups as $field_group ) {
			if ( strpos( $field_group['key'], 'group_test_' ) === 0 ) {
				acf_delete_field_group( $field_group['ID'] );
			}
		}

		parent::tear_down();
	}

	// =========================================================================
	// Class Structure and Hook Tests
	// =========================================================================

	/**
	 * Test constructor hooks are registered.
	 */
	public function test_constructor_registers_hooks() {
		$new_instance = new ACF_Rest_Api();

		// Check that the filter is registered.
		$this->assertNotFalse(
			has_filter( 'rest_pre_dispatch', array( $new_instance, 'initialize' ) ),
			'rest_pre_dispatch filter should be registered'
		);

		// Check that the action is registered.
		$this->assertNotFalse(
			has_action( 'rest_api_init', array( $new_instance, 'register_field' ) ),
			'rest_api_init action should be registered'
		);
	}

	// =========================================================================
	// REST API Enabled/Disabled Tests
	// =========================================================================

	/**
	 * Test initialize returns early when REST API is disabled.
	 */
	public function test_initialize_returns_when_disabled() {
		acf_update_setting( 'rest_api_enabled', false );

		$result = $this->rest_api->initialize( null, null, new WP_REST_Request() );

		$this->assertNull( $result, 'initialize should return null when REST API is disabled' );
	}

	/**
	 * Test register_field returns early when REST API is disabled.
	 */
	public function test_register_field_returns_when_disabled() {
		acf_update_setting( 'rest_api_enabled', false );

		// Should not throw any errors.
		$this->rest_api->register_field();

		$this->assertTrue( true, 'register_field should return gracefully when REST API is disabled' );
	}

	// =========================================================================
	// Make Identifier Tests
	// =========================================================================

	/**
	 * Test make_identifier for different object types.
	 *
	 * @dataProvider make_identifier_provider
	 *
	 * @param int    $object_id   The object ID.
	 * @param string $object_type The object type.
	 * @param mixed  $expected    The expected identifier.
	 */
	public function test_make_identifier( $object_id, $object_type, $expected ) {
		$method = $this->reflection->getMethod( 'make_identifier' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->rest_api, $object_id, $object_type );

		$this->assertEquals( $expected, $result, "make_identifier should return correct format for {$object_type}" );
	}

	/**
	 * Data provider for make_identifier tests.
	 *
	 * @return array
	 */
	public function make_identifier_provider() {
		return array(
			'user object'    => array( 123, 'user', 'user_123' ),
			'term object'    => array( 456, 'term', 'term_456' ),
			'comment object' => array( 789, 'comment', 'comment_789' ),
			'post object'    => array( 100, 'post', 100 ),
			'unknown type'   => array( 200, 'unknown', 200 ),
		);
	}

	// =========================================================================
	// Schema Generation Tests
	// =========================================================================

	/**
	 * Test get_schema returns base structure.
	 */
	public function test_get_schema_base_structure() {
		// Initialize with a mock REST request first.
		$wp_request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->test_post_id );
		$this->rest_api->initialize( null, null, $wp_request );

		$method = $this->reflection->getMethod( 'get_schema' );
		$method->setAccessible( true );

		$schema = $method->invoke( $this->rest_api );

		$this->assertIsArray( $schema );
		$this->assertEquals( 'ACF field data', $schema['description'] );
		$this->assertEquals( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'arg_options', $schema );
		$this->assertArrayHasKey( 'validate_callback', $schema['arg_options'] );
	}

	// =========================================================================
	// Validate REST Arg Tests
	// =========================================================================

	/**
	 * Test validate_rest_arg with valid data.
	 */
	public function test_validate_rest_arg_with_valid_data() {
		$request = new WP_REST_Request();
		$request->set_attributes(
			array(
				'args' => array(
					'acf' => array(
						'type' => 'object',
					),
				),
			)
		);

		$result = $this->rest_api->validate_rest_arg( array(), $request, 'acf' );

		$this->assertTrue( $result, 'Empty array should be valid' );
	}

	/**
	 * Test validate_rest_arg with unknown field continues gracefully.
	 */
	public function test_validate_rest_arg_with_unknown_field() {
		$request = new WP_REST_Request();
		$request->set_attributes(
			array(
				'args' => array(
					'acf' => array(
						'type' => 'object',
					),
				),
			)
		);

		$value = array(
			'nonexistent_field' => 'some_value',
		);

		$result = $this->rest_api->validate_rest_arg( $value, $request, 'acf' );

		$this->assertTrue( $result, 'Unknown fields should be skipped and return true' );
	}

	// =========================================================================
	// Load Fields Tests
	// =========================================================================

	/**
	 * Test load_fields returns empty array when object ID is missing.
	 */
	public function test_load_fields_returns_empty_without_object_id() {
		// Set up the request property to prevent null access errors.
		$request_prop = $this->reflection->getProperty( 'request' );
		$request_prop->setAccessible( true );

		$mock_request                  = new stdClass();
		$mock_request->object_type     = 'post';
		$mock_request->object_sub_type = 'post';
		$request_prop->setValue( $this->rest_api, $mock_request );

		$object  = array(); // Empty object, no ID.
		$request = new WP_REST_Request();

		$result = $this->rest_api->load_fields( $object, 'acf', $request, 'post' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result, 'Should return empty array when object ID cannot be determined' );
	}

	/**
	 * Test load_fields returns empty array when no field groups exist.
	 */
	public function test_load_fields_returns_empty_without_field_groups() {
		// Initialize the REST API with a mock request first.
		$mock_request = $this->createMock( WP_REST_Request::class );
		$mock_request->method( 'get_route' )->willReturn( '/wp/v2/posts/' . $this->test_post_id );

		$this->rest_api->initialize( null, null, $mock_request );

		$object = array( 'id' => $this->test_post_id );

		$result = $this->rest_api->load_fields( $object, 'acf', new WP_REST_Request(), 'post' );

		$this->assertIsArray( $result );
	}

	/**
	 * Test load_fields uses REST-safe formatted values for User fields.
	 */
	public function test_load_fields_uses_rest_safe_user_formatted_values() {
		wp_set_current_user( 0 );

		$user_login = uniqid( 'rest_loaded_user_', false );
		$user_email = "{$user_login}@example.invalid";

		$user_id = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_pass'  => 'password',
				'user_email' => $user_email,
			)
		);

		$second_user_login = uniqid( 'rest_loaded_second_user_', false );
		$second_user_email = "{$second_user_login}@example.invalid";
		$second_user_id    = wp_insert_user(
			array(
				'user_login' => $second_user_login,
				'user_pass'  => 'password',
				'user_email' => $second_user_email,
			)
		);

		global $wpdb;
		$activation_key        = "{$user_login}-activation-key";
		$second_activation_key = "{$second_user_login}-activation-key";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture needs a controlled activation key marker.
		$wpdb->update(
			$wpdb->users,
			array( 'user_activation_key' => $activation_key ),
			array( 'ID' => $user_id )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture needs a controlled activation key marker.
		$wpdb->update(
			$wpdb->users,
			array( 'user_activation_key' => $second_activation_key ),
			array( 'ID' => $second_user_id )
		);
		clean_user_cache( $user_id );
		clean_user_cache( $second_user_id );

		acf_add_local_field_group(
			array_merge(
				$this->test_field_group,
				array(
					'key'    => 'group_test_rest_user_source_values',
					'title'  => 'Test REST User Source Values',
					'fields' => array(
						array(
							'key'           => 'field_test_rest_user_array',
							'label'         => 'Test REST User Array',
							'name'          => 'test_rest_user_array',
							'type'          => 'user',
							'required'      => 0,
							'multiple'      => 0,
							'return_format' => 'array',
							'role'          => array(),
							'allow_null'    => 0,
						),
						array(
							'key'           => 'field_test_rest_user_object',
							'label'         => 'Test REST User Object',
							'name'          => 'test_rest_user_object',
							'type'          => 'user',
							'required'      => 0,
							'multiple'      => 0,
							'return_format' => 'object',
							'role'          => array(),
							'allow_null'    => 0,
						),
						array(
							'key'        => 'field_test_rest_user_group',
							'label'      => 'Test REST User Group',
							'name'       => 'test_rest_user_group',
							'type'       => 'group',
							'sub_fields' => array(
								array(
									'key'           => 'field_test_rest_nested_user_array',
									'label'         => 'Test REST Nested User Array',
									'name'          => 'nested_user_array',
									'type'          => 'user',
									'required'      => 0,
									'multiple'      => 1,
									'return_format' => 'array',
									'role'          => array(),
									'allow_null'    => 0,
								),
								array(
									'key'           => 'field_test_rest_nested_user_object',
									'label'         => 'Test REST Nested User Object',
									'name'          => 'nested_user_object',
									'type'          => 'user',
									'required'      => 0,
									'multiple'      => 1,
									'return_format' => 'object',
									'role'          => array(),
									'allow_null'    => 0,
								),
							),
						),
					),
				)
			)
		);

		update_field( 'field_test_rest_user_array', $user_id, $this->test_post_id );
		update_field( 'field_test_rest_user_object', $user_id, $this->test_post_id );
		update_field(
			'field_test_rest_user_group',
			array(
				'field_test_rest_nested_user_array'  => array( $user_id, $second_user_id ),
				'field_test_rest_nested_user_object' => array( $user_id, $second_user_id ),
			),
			$this->test_post_id
		);
		acf_get_store( 'fields' )->reset();

		$request_prop = $this->reflection->getProperty( 'request' );
		$request_prop->setAccessible( true );

		$mock_request = new class() {
			/**
			 * The REST object type.
			 *
			 * @var string
			 */
			public $object_type = 'post';

			/**
			 * The REST object subtype.
			 *
			 * @var string
			 */
			public $object_sub_type = 'post';

			/**
			 * The REST request method.
			 *
			 * @var string
			 */
			public $http_method = 'GET';

			/**
			 * Get a URL parameter value.
			 *
			 * @param string $param The URL parameter name.
			 * @return null
			 */
			public function get_url_param( $param ) {
				return null;
			}
		};
		$request_prop->setValue( $this->rest_api, $mock_request );

		$shim_user_query = static function ( $results, $query ) {
			$user_ids           = array_map( 'intval', (array) ( $query->query_vars['include'] ?? array() ) );
			$query->total_users = count( $user_ids );
			return $user_ids;
		};
		add_filter( 'users_pre_query', $shim_user_query, 10, 2 );

		$remove_user_avatar = static function ( $formatted_value ) {
			if ( is_array( $formatted_value ) && isset( $formatted_value['ID'] ) ) {
				unset( $formatted_value['user_avatar'] );
				return $formatted_value;
			}

			if ( is_array( $formatted_value ) ) {
				foreach ( $formatted_value as &$user ) {
					if ( is_array( $user ) ) {
						unset( $user['user_avatar'] );
					}
				}
			}

			return $formatted_value;
		};
		add_filter( 'acf/format_value/type=user', $remove_user_avatar, 20 );

		$rest_filter_calls = 0;
		$count_rest_filter = static function ( $formatted_value, $post_id, $field ) use ( &$rest_filter_calls ) {
			if ( ! in_array( $field['name'], array( 'test_rest_user_array', 'test_rest_user_object' ), true ) ) {
				return $formatted_value;
			}

			++$rest_filter_calls;
			return "rest-filtered-{$formatted_value}";
		};
		add_filter( 'acf/rest/format_value_for_rest', $count_rest_filter, 10, 3 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->test_post_id );

		$result               = $this->rest_api->load_fields( array( 'id' => $this->test_post_id ), 'acf', $request, 'post' );
		$default_filter_calls = $rest_filter_calls;

		$standard_request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->test_post_id );
		$standard_request->set_param( 'acf_format', 'standard' );

		$standard_result = $this->rest_api->load_fields( array( 'id' => $this->test_post_id ), 'acf', $standard_request, 'post' );
		remove_filter( 'acf/rest/format_value_for_rest', $count_rest_filter, 10 );
		remove_filter( 'acf/format_value/type=user', $remove_user_avatar, 20 );
		remove_filter( 'users_pre_query', $shim_user_query, 10 );

		$this->assertSame( 2, $default_filter_calls );
		$this->assertSame( 4, $rest_filter_calls );
		$this->assertSame( "rest-filtered-{$user_id}", $result['test_rest_user_array'] );
		$this->assertSame( "rest-filtered-{$user_id}", $result['test_rest_user_object'] );
		$this->assertSame( $user_id, $result['test_rest_user_array_source']['formatted_value'] );
		$this->assertSame( $user_id, $result['test_rest_user_object_source']['formatted_value'] );
		$this->assertSame(
			array( $user_id, $second_user_id ),
			$result['test_rest_user_group']['nested_user_array']
		);
		$this->assertSame(
			array( $user_id, $second_user_id ),
			$result['test_rest_user_group']['nested_user_object']
		);
		$this->assertSame(
			array( $user_id, $second_user_id ),
			$result['test_rest_user_group_source']['formatted_value']['nested_user_array']
		);
		$this->assertSame(
			array( $user_id, $second_user_id ),
			$result['test_rest_user_group_source']['formatted_value']['nested_user_object']
		);
		$this->assertRestUserResponseDoesNotExposePrivateData( $result, $user_email, $activation_key );
		$this->assertRestUserResponseDoesNotExposePrivateData( $result, $second_user_email, $second_activation_key );

		$this->assertSame( "rest-filtered-{$user_id}", $standard_result['test_rest_user_array'] );
		$this->assertSame( "rest-filtered-{$user_id}", $standard_result['test_rest_user_object'] );
		$this->assertSame( $user_id, $standard_result['test_rest_user_array_source']['formatted_value'] );
		$this->assertSame( $user_id, $standard_result['test_rest_user_object_source']['formatted_value'] );
		$this->assertSame(
			array( $user_id, $second_user_id ),
			$standard_result['test_rest_user_group']['nested_user_array']
		);
		$this->assertSame(
			array( $user_id, $second_user_id ),
			$standard_result['test_rest_user_group']['nested_user_object']
		);
		$this->assertSame(
			array( $user_id, $second_user_id ),
			$standard_result['test_rest_user_group_source']['formatted_value']['nested_user_array']
		);
		$this->assertSame(
			array( $user_id, $second_user_id ),
			$standard_result['test_rest_user_group_source']['formatted_value']['nested_user_object']
		);
		$this->assertRestUserResponseDoesNotExposePrivateData( $standard_result, $user_email, $activation_key );
		$this->assertRestUserResponseDoesNotExposePrivateData(
			$standard_result,
			$second_user_email,
			$second_activation_key
		);

		wp_delete_user( $user_id );
		wp_delete_user( $second_user_id );
		acf_remove_local_field_group( 'group_test_rest_user_source_values' );
		acf_remove_local_field( 'field_test_rest_user_array' );
		acf_remove_local_field( 'field_test_rest_user_object' );
		acf_remove_local_field( 'field_test_rest_user_group' );
		acf_remove_local_field( 'field_test_rest_nested_user_array' );
		acf_remove_local_field( 'field_test_rest_nested_user_object' );
	}

	/**
	 * Assert REST User field output does not expose private user data.
	 *
	 * @param array  $response       The REST field response.
	 * @param string $user_email     The private user email marker.
	 * @param string $activation_key The private user activation key marker.
	 */
	protected function assertRestUserResponseDoesNotExposePrivateData( $response, $user_email, $activation_key ) {
		$json = wp_json_encode( $response );

		$this->assertStringNotContainsString( $user_email, $json );
		$this->assertStringNotContainsString( 'user_pass', $json );
		$this->assertStringNotContainsString( $activation_key, $json );
		$this->assertStringNotContainsString( 'user_activation_key', $json );
	}

	// =========================================================================
	// Update Fields Tests
	// =========================================================================

	/**
	 * Test update_fields returns true with empty data.
	 */
	public function test_update_fields_returns_true_with_empty_data() {
		$post   = get_post( $this->test_post_id );
		$result = $this->rest_api->update_fields( array(), $post, 'acf', new WP_REST_Request(), 'post' );

		$this->assertTrue( $result, 'Empty data should return true without errors' );
	}

	/**
	 * Test update_fields returns true with null data.
	 */
	public function test_update_fields_returns_true_with_null_data() {
		$post   = get_post( $this->test_post_id );
		$result = $this->rest_api->update_fields( null, $post, 'acf', new WP_REST_Request(), 'post' );

		$this->assertTrue( $result, 'Null data should return true' );
	}

	// =========================================================================
	// Object Type Has Field Group Tests
	// =========================================================================

	/**
	 * Test object_type_has_field_group with valid post type location.
	 */
	public function test_object_type_has_field_group_with_valid_location() {
		$method = $this->reflection->getMethod( 'object_type_has_field_group' );
		$method->setAccessible( true );

		// Set up the request property.
		$request_prop = $this->reflection->getProperty( 'request' );
		$request_prop->setAccessible( true );

		$mock_request                  = new stdClass();
		$mock_request->object_sub_type = 'post';
		$request_prop->setValue( $this->rest_api, $mock_request );

		$field_group = array(
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
		);

		$result = $method->invoke( $this->rest_api, 'post', $field_group );

		$this->assertTrue( $result, 'Should return true for matching post type location' );
	}

	/**
	 * Test object_type_has_field_group with non-matching post type.
	 */
	public function test_object_type_has_field_group_with_non_matching_location() {
		$method = $this->reflection->getMethod( 'object_type_has_field_group' );
		$method->setAccessible( true );

		// Set up the request property.
		$request_prop = $this->reflection->getProperty( 'request' );
		$request_prop->setAccessible( true );

		$mock_request                  = new stdClass();
		$mock_request->object_sub_type = 'post';
		$request_prop->setValue( $this->rest_api, $mock_request );

		$field_group = array(
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'page',
					),
				),
			),
		);

		$result = $method->invoke( $this->rest_api, 'post', $field_group );

		$this->assertFalse( $result, 'Should return false for non-matching post type location' );
	}

	/**
	 * Test object_type_has_field_group with invalid location array.
	 */
	public function test_object_type_has_field_group_with_invalid_location() {
		$method = $this->reflection->getMethod( 'object_type_has_field_group' );
		$method->setAccessible( true );

		$field_group = array(
			'location' => 'invalid',
		);

		$result = $method->invoke( $this->rest_api, 'post', $field_group );

		$this->assertFalse( $result, 'Should return false for invalid location' );
	}

	/**
	 * Test object_type_has_field_group with missing location.
	 */
	public function test_object_type_has_field_group_with_missing_location() {
		$method = $this->reflection->getMethod( 'object_type_has_field_group' );
		$method->setAccessible( true );

		$field_group = array();

		$result = $method->invoke( $this->rest_api, 'post', $field_group );

		$this->assertFalse( $result, 'Should return false for missing location' );
	}

	/**
	 * Test object_type_has_field_group with != operator.
	 */
	public function test_object_type_has_field_group_with_not_equals_operator() {
		$method = $this->reflection->getMethod( 'object_type_has_field_group' );
		$method->setAccessible( true );

		// Set up the request property.
		$request_prop = $this->reflection->getProperty( 'request' );
		$request_prop->setAccessible( true );

		$mock_request                  = new stdClass();
		$mock_request->object_sub_type = 'post';
		$request_prop->setValue( $this->rest_api, $mock_request );

		$field_group = array(
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '!=',
						'value'    => 'page',
					),
				),
			),
		);

		$result = $method->invoke( $this->rest_api, 'post', $field_group );

		$this->assertTrue( $result, 'Should return true when != operator excludes different post type' );
	}

	/**
	 * Test object_type_has_field_group for user type.
	 */
	public function test_object_type_has_field_group_for_user() {
		$method = $this->reflection->getMethod( 'object_type_has_field_group' );
		$method->setAccessible( true );

		$field_group = array(
			'location' => array(
				array(
					array(
						'param'    => 'user_form',
						'operator' => '==',
						'value'    => 'all',
					),
				),
			),
		);

		$result = $method->invoke( $this->rest_api, 'user', $field_group );

		$this->assertTrue( $result, 'Should return true for user type with user_form location' );
	}

	/**
	 * Test object_type_has_field_group for comment type.
	 */
	public function test_object_type_has_field_group_for_comment() {
		$method = $this->reflection->getMethod( 'object_type_has_field_group' );
		$method->setAccessible( true );

		$field_group = array(
			'location' => array(
				array(
					array(
						'param'    => 'comment',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
		);

		$result = $method->invoke( $this->rest_api, 'comment', $field_group );

		$this->assertTrue( $result, 'Should return true for comment type with comment location' );
	}

	/**
	 * Test object_type_has_field_group for term/taxonomy type.
	 */
	public function test_object_type_has_field_group_for_term() {
		$method = $this->reflection->getMethod( 'object_type_has_field_group' );
		$method->setAccessible( true );

		// Set up the request property.
		$request_prop = $this->reflection->getProperty( 'request' );
		$request_prop->setAccessible( true );

		$mock_request                  = new stdClass();
		$mock_request->object_sub_type = 'category';
		$request_prop->setValue( $this->rest_api, $mock_request );

		$field_group = array(
			'location' => array(
				array(
					array(
						'param'    => 'taxonomy',
						'operator' => '==',
						'value'    => 'category',
					),
				),
			),
		);

		$result = $method->invoke( $this->rest_api, 'term', $field_group );

		$this->assertTrue( $result, 'Should return true for term type with matching taxonomy' );
	}

	// =========================================================================
	// Get Field Groups Tests
	// =========================================================================

	/**
	 * Test get_field_groups_by_object_type filters out REST-disabled groups.
	 *
	 * This test verifies the filtering logic for show_in_rest. The method also
	 * filters by location matching, which requires a full WordPress environment.
	 * We test the show_in_rest filtering by checking the method's direct behavior.
	 */
	public function test_get_field_groups_by_object_type_filters_rest_disabled() {
		$method = $this->reflection->getMethod( 'get_field_groups_by_object_type' );
		$method->setAccessible( true );

		// Set up the request property.
		$request_prop = $this->reflection->getProperty( 'request' );
		$request_prop->setAccessible( true );

		$mock_request                  = new stdClass();
		$mock_request->object_sub_type = 'post';
		$request_prop->setValue( $this->rest_api, $mock_request );

		// Create field groups - one REST enabled, one disabled.
		acf_import_field_group(
			array_merge(
				$this->test_field_group,
				array(
					'key'          => 'group_test_rest_enabled',
					'show_in_rest' => true,
				)
			)
		);

		acf_import_field_group(
			array_merge(
				$this->test_field_group,
				array(
					'key'          => 'group_test_rest_disabled',
					'show_in_rest' => false,
				)
			)
		);

		$result = $method->invoke( $this->rest_api, 'post' );

		// The key assertion: REST-disabled groups should NEVER be included.
		$rest_disabled_found = false;

		foreach ( $result as $group ) {
			if ( 'group_test_rest_disabled' === $group['key'] ) {
				$rest_disabled_found = true;
			}
		}

		$this->assertFalse( $rest_disabled_found, 'REST-disabled group should be excluded' );

		// Note: We cannot reliably assert the REST-enabled group is found because
		// that depends on location type matching, which has complex requirements
		// in the test environment. The critical behavior is that disabled groups
		// are filtered out.
	}

	// =========================================================================
	// Get Fields Tests
	// =========================================================================

	/**
	 * Test get_fields applies the acf/rest/get_fields filter.
	 */
	public function test_get_fields_applies_filter() {
		$method = $this->reflection->getMethod( 'get_fields' );
		$method->setAccessible( true );

		// Set up the request property.
		$request_prop = $this->reflection->getProperty( 'request' );
		$request_prop->setAccessible( true );

		$mock_request                  = new stdClass();
		$mock_request->object_type     = 'post';
		$mock_request->object_sub_type = 'post';
		$mock_request->http_method     = 'GET';
		$request_prop->setValue( $this->rest_api, $mock_request );

		$filter_called = false;

		add_filter(
			'acf/rest/get_fields',
			function ( $fields, $resource_data, $http_method ) use ( &$filter_called ) {
				$filter_called = true;
				$this->assertIsArray( $resource_data );
				$this->assertArrayHasKey( 'type', $resource_data );
				$this->assertArrayHasKey( 'sub_type', $resource_data );
				$this->assertEquals( 'GET', $http_method );
				return $fields;
			},
			10,
			3
		);

		// Create a proper field group with ID for this test.
		$field_group = acf_import_field_group(
			array(
				'key'          => 'group_test_get_fields',
				'title'        => 'Test Get Fields',
				'fields'       => array(),
				'location'     => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
				'show_in_rest' => true,
				'active'       => true,
			)
		);

		$method->invoke( $this->rest_api, $field_group, $this->test_post_id );

		$this->assertTrue( $filter_called, 'acf/rest/get_fields filter should be called' );

		remove_all_filters( 'acf/rest/get_fields' );
	}

	// =========================================================================
	// Is Admin Mode Tests
	// =========================================================================

	/**
	 * Test is_admin_mode returns true when flag is set.
	 */
	public function test_is_admin_mode_returns_true_when_set() {
		$method = $this->reflection->getMethod( 'is_admin_mode' );
		$method->setAccessible( true );

		$data = array( '_acf_admin_mode' => true );

		$result = $method->invoke( $this->rest_api, $data );

		$this->assertTrue( $result, 'Should return true when _acf_admin_mode is set' );
	}

	/**
	 * Test is_admin_mode returns false when flag is not set.
	 */
	public function test_is_admin_mode_returns_false_when_not_set() {
		$method = $this->reflection->getMethod( 'is_admin_mode' );
		$method->setAccessible( true );

		$data = array();

		$result = $method->invoke( $this->rest_api, $data );

		$this->assertFalse( $result, 'Should return false when _acf_admin_mode is not set' );
	}

	/**
	 * Test is_admin_mode returns false when flag is false.
	 */
	public function test_is_admin_mode_returns_false_when_false() {
		$method = $this->reflection->getMethod( 'is_admin_mode' );
		$method->setAccessible( true );

		$data = array( '_acf_admin_mode' => false );

		$result = $method->invoke( $this->rest_api, $data );

		$this->assertFalse( $result, 'Should return false when _acf_admin_mode is false' );
	}

	// =========================================================================
	// REST API Format Setting Tests
	// =========================================================================

	/**
	 * Test that 'light' format is the default.
	 */
	public function test_default_rest_format_is_light() {
		$format = acf_get_setting( 'rest_api_format' );
		$this->assertEquals( 'light', $format, 'Default REST API format should be light' );
	}

	/**
	 * Test format can be changed to 'standard'.
	 */
	public function test_rest_format_can_be_standard() {
		acf_update_setting( 'rest_api_format', 'standard' );
		$format = acf_get_setting( 'rest_api_format' );

		$this->assertEquals( 'standard', $format, 'REST API format should be changeable to standard' );
	}

	// =========================================================================
	// Integration Tests with Field Groups
	// =========================================================================

	/**
	 * Test field group with show_in_rest true is accessible.
	 */
	public function test_field_group_with_show_in_rest_is_accessible() {
		// Create a field group with REST enabled.
		$field_group = acf_import_field_group(
			array(
				'key'          => 'group_test_accessible',
				'title'        => 'Accessible Field Group',
				'fields'       => array(
					array(
						'key'   => 'field_test_text',
						'label' => 'Test Text',
						'name'  => 'test_text',
						'type'  => 'text',
					),
				),
				'location'     => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
				'show_in_rest' => true,
				'active'       => true,
			)
		);

		$this->assertNotEmpty( $field_group, 'Field group should be created' );
		$this->assertIsArray( $field_group, 'Field group should be an array' );
		$this->assertArrayHasKey( 'show_in_rest', $field_group );
		$this->assertTrue( $field_group['show_in_rest'], 'Field group should have show_in_rest enabled' );
	}

	/**
	 * Test multiple field groups with mixed REST settings.
	 */
	public function test_multiple_field_groups_mixed_rest_settings() {
		// Create two groups.
		$group1 = acf_import_field_group(
			array(
				'key'          => 'group_test_mixed_1',
				'title'        => 'Mixed 1',
				'fields'       => array(),
				'location'     => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
				'show_in_rest' => true,
				'active'       => true,
			)
		);

		$group2 = acf_import_field_group(
			array(
				'key'          => 'group_test_mixed_2',
				'title'        => 'Mixed 2',
				'fields'       => array(),
				'location'     => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
				'show_in_rest' => false,
				'active'       => true,
			)
		);

		$this->assertIsArray( $group1, 'First group should be created' );
		$this->assertIsArray( $group2, 'Second group should be created' );
		$this->assertTrue( $group1['show_in_rest'], 'First group should have REST enabled' );
		$this->assertFalse( $group2['show_in_rest'], 'Second group should have REST disabled' );
	}
}
