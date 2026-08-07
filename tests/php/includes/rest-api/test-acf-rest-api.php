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
	 * Original bidirection setting.
	 *
	 * @var bool
	 */
	protected $original_bidirection_setting;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Ensure location types are loaded for location matching tests.
		if ( ! class_exists( 'ACF_Location_Post_Type' ) ) {
			acf_include( 'includes/locations/class-acf-location-post-type.php' );
		}
		if ( ! class_exists( 'ACF_Location_Post_Status' ) ) {
			acf_include( 'includes/locations/class-acf-location-post-status.php' );
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
		$field_types = array(
			'relationship'     => array( 'acf_field_relationship', 'includes/fields/class-acf-field-relationship.php' ),
			'text'             => array( 'acf_field_text', 'includes/fields/class-acf-field-text.php' ),
			'group'            => array( 'acf_field__group', 'includes/fields/class-acf-field-group.php' ),
			'repeater'         => array( 'acf_field_repeater', 'includes/fields/class-acf-field-repeater.php' ),
			'flexible_content' => array( 'acf_field_flexible_content', 'includes/fields/class-acf-field-flexible-content.php' ),
			'clone'            => array( 'acf_field_clone', 'includes/fields/class-acf-field-clone.php' ),
		);
		if ( ! class_exists( 'acf_repeater_table' ) ) {
			acf_include( 'includes/fields/class-acf-repeater-table.php' );
		}
		foreach ( $field_types as $type => list( $class, $file ) ) {
			if ( ! class_exists( $class ) ) {
				acf_include( $file );
			} elseif ( ! has_filter( "acf/update_value/type={$type}" ) ) {
				acf_register_field_type( $class );
			}
		}
		if ( ! class_exists( 'acf_field_user' ) ) {
			acf_include( 'includes/fields/class-acf-field-user.php' );
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
		$this->original_bidirection_setting  = acf_get_setting( 'enable_bidirection' );

		// Enable REST API.
		acf_update_setting( 'rest_api_enabled', true );
		acf_update_setting( 'rest_api_format', 'light' );
		acf_update_setting( 'rest_api_embed_links', false );
		acf_update_setting( 'enable_bidirection', true );

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
		acf_update_setting( 'enable_bidirection', $this->original_bidirection_setting );
		remove_filter( 'acf/settings/enable_datastore', '__return_true' );
		unregister_meta_key( 'post', '_acf' );
		acf_set_data( 'acf_doing_bidirectional_update', false );
		wp_set_current_user( 0 );
		foreach ( acf_get_local_field_groups() as $field_group ) {
			if ( strpos( $field_group['key'], 'group_test_rest_security_' ) === 0 ) {
				acf_remove_local_field_group( $field_group['key'] );
			}
		}

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

	/**
	 * Creates a REST bidirectional fixture.
	 *
	 * @param string  $suffix       Unique field suffix.
	 * @param boolean $authorized   Whether the contributor owns the target.
	 * @param string  $location     Field group post type location.
	 * @param boolean $show_in_rest Whether the group is exposed through `acf`.
	 * @param string  $post_status  Optional field group post status location.
	 * @return array
	 */
	private function create_bidirectional_rest_fixture( $suffix, $authorized = false, $location = 'post', $show_in_rest = true, $post_status = '' ) {
		$administrator  = wp_insert_user(
			array(
				'user_login' => "rest_security_admin_{$suffix}",
				'user_pass'  => 'password',
				'user_email' => "admin_{$suffix}@example.com",
				'role'       => 'administrator',
			)
		);
		$contributor    = wp_insert_user(
			array(
				'user_login' => "rest_security_contributor_{$suffix}",
				'user_pass'  => 'password',
				'user_email' => "contributor_{$suffix}@example.com",
				'role'       => 'contributor',
			)
		);
		$source_field   = array(
			'key'                  => "field_test_rest_security_source_{$suffix}",
			'name'                 => "test_rest_security_source_{$suffix}",
			'label'                => 'Security source',
			'type'                 => 'relationship',
			'post_type'            => array(),
			'taxonomy'             => array(),
			'bidirectional'        => true,
			'bidirectional_target' => array( "field_test_rest_security_target_{$suffix}" ),
		);
		$target_field   = array(
			'key'       => "field_test_rest_security_target_{$suffix}",
			'name'      => "test_rest_security_target_{$suffix}",
			'label'     => 'Security target',
			'type'      => 'relationship',
			'post_type' => array(),
			'taxonomy'  => array(),
		);
		$location_rules = array(
			array(
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => $location,
			),
		);
		if ( $post_status ) {
			$location_rules[] = array(
				'param'    => 'post_status',
				'operator' => '==',
				'value'    => $post_status,
			);
		}

		acf_add_local_field_group(
			array(
				'key'          => "group_test_rest_security_{$suffix}",
				'title'        => 'REST security fixture',
				'fields'       => array( $source_field, $target_field ),
				'location'     => array( $location_rules ),
				'show_in_rest' => $show_in_rest,
				'active'       => true,
			)
		);

		$source = wp_insert_post(
			array(
				'post_title'  => 'Security source before',
				'post_status' => 'draft',
				'post_author' => $contributor,
			)
		);
		$target = wp_insert_post(
			array(
				'post_title'  => 'Security target',
				'post_status' => $authorized ? 'draft' : 'private',
				'post_author' => $authorized ? $contributor : $administrator,
			)
		);

		wp_set_current_user( $contributor );
		return compact( 'contributor', 'source', 'target', 'source_field', 'target_field' );
	}

	/**
	 * Creates a nested REST bidirectional fixture.
	 *
	 * @param string  $suffix         Unique field suffix.
	 * @param string  $container_type Container field type.
	 * @param boolean $authorized     Whether the contributor owns the target.
	 * @param boolean $pagination     Whether repeater pagination is enabled.
	 * @return array
	 */
	private function create_nested_bidirectional_rest_fixture( $suffix, $container_type, $authorized = false, $pagination = false ) {
		$administrator = wp_insert_user(
			array(
				'user_login' => "rest_nested_admin_{$suffix}",
				'user_pass'  => 'password',
				'user_email' => "nested_admin_{$suffix}@example.com",
				'role'       => 'administrator',
			)
		);
		$contributor   = wp_insert_user(
			array(
				'user_login' => "rest_nested_contributor_{$suffix}",
				'user_pass'  => 'password',
				'user_email' => "nested_contributor_{$suffix}@example.com",
				'role'       => 'contributor',
			)
		);
		$source_field  = array(
			'key'                  => "field_test_rest_security_nested_source_{$suffix}",
			'name'                 => "test_rest_security_nested_source_{$suffix}",
			'label'                => 'Nested security source',
			'type'                 => 'relationship',
			'post_type'            => array(),
			'taxonomy'             => array(),
			'bidirectional'        => true,
			'bidirectional_target' => array( "field_test_rest_security_nested_target_{$suffix}" ),
		);
		$target_field  = array(
			'key'       => "field_test_rest_security_nested_target_{$suffix}",
			'name'      => "test_rest_security_nested_target_{$suffix}",
			'label'     => 'Nested security target',
			'type'      => 'relationship',
			'post_type' => array(),
			'taxonomy'  => array(),
		);
		$root_field    = array(
			'key'   => "field_test_rest_security_nested_root_{$suffix}",
			'name'  => "test_rest_security_nested_root_{$suffix}",
			'label' => 'Nested security root',
			'type'  => $container_type,
		);

		if ( 'flexible_content' === $container_type ) {
			$root_field['layouts'] = array(
				array(
					'key'        => "layout_test_rest_security_{$suffix}",
					'name'       => 'security_layout',
					'label'      => 'Security layout',
					'display'    => 'block',
					'sub_fields' => array( $source_field ),
				),
			);
		} elseif ( 'clone' === $container_type ) {
			$root_field += array(
				'clone'        => array( $source_field['key'] ),
				'display'      => 'group',
				'prefix_label' => 0,
				'prefix_name'  => 0,
			);
			acf_add_local_field_group(
				array(
					'key'          => "group_test_rest_security_nested_clone_source_{$suffix}",
					'title'        => 'Nested clone source',
					'fields'       => array( $source_field ),
					'location'     => array(
						array(
							array(
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'page',
							),
						),
					),
					'show_in_rest' => false,
					'active'       => true,
				)
			);
		} else {
			$root_field['sub_fields'] = array( $source_field );
			if ( 'repeater' === $container_type ) {
				$root_field['pagination'] = $pagination;
			}
		}

		acf_add_local_field_group(
			array(
				'key'          => "group_test_rest_security_nested_{$suffix}",
				'title'        => 'Nested REST security fixture',
				'fields'       => array( $root_field, $target_field ),
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

		$source = wp_insert_post(
			array(
				'post_title'  => 'Nested security source before',
				'post_status' => 'draft',
				'post_author' => $contributor,
			)
		);
		$target = wp_insert_post(
			array(
				'post_title'  => 'Nested security target',
				'post_status' => $authorized ? 'draft' : 'private',
				'post_author' => $authorized ? $contributor : $administrator,
			)
		);

		wp_set_current_user( $contributor );
		return compact( 'administrator', 'contributor', 'source', 'target', 'source_field', 'target_field', 'root_field', 'container_type' );
	}

	/**
	 * Builds a nested value for a REST transport.
	 *
	 * @param array   $fixture  Nested fixture.
	 * @param boolean $datastore Whether field keys are required.
	 * @return array
	 */
	private function prepare_nested_rest_value( $fixture, $datastore ) {
		$selector = $datastore ? $fixture['source_field']['key'] : $fixture['source_field']['name'];
		$row      = array( $selector => array( $fixture['target'] ) );
		if ( 'repeater' === $fixture['container_type'] ) {
			return array( $row );
		}
		if ( 'flexible_content' === $fixture['container_type'] ) {
			$row['acf_fc_layout'] = 'security_layout';
			return array( $row );
		}
		return $row;
	}

	/**
	 * Dispatches a post update through the REST server.
	 *
	 * @param integer $post_id Post ID.
	 * @param array   $body    Request body.
	 * @return WP_REST_Response
	 */
	private function dispatch_post_update( $post_id, $body ) {
		unset( $GLOBALS['wp_rest_additional_fields']['post']['acf'] );
		$route   = $post_id ? "/wp/v2/posts/{$post_id}" : '/wp/v2/posts';
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_body_params( $body );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Enables datastore REST handling for the current test.
	 *
	 * @return \SCF\Datastore\REST_Save
	 */
	private function enable_datastore_rest() {
		add_filter( 'acf/settings/enable_datastore', '__return_true' );
		( new \SCF\Datastore\Revisions() )->register_meta();
		$rest_save = new \SCF\Datastore\REST_Save();
		$rest_save->maybe_register_rest_save_hooks();
		return $rest_save;
	}

	/**
	 * REST `acf` rejects an unauthorized bidirectional addition atomically.
	 */
	public function test_rest_acf_rejects_unauthorized_bidirectional_addition_atomically() {
		$fixture   = $this->create_bidirectional_rest_fixture( 'addition' );
		$callbacks = did_action( 'rest_after_insert_post' );
		$result    = $this->dispatch_post_update(
			$fixture['source'],
			array(
				'title'    => 'Security source after',
				'child_id' => 999999,
				'acf'      => array( $fixture['source_field']['name'] => array( $fixture['target'] ) ),
			)
		);

		$this->assertSame( 403, $result->get_status() );
		$this->assertSame( 'acf_rest_cannot_update_bidirectional_target', $result->get_data()['code'] );
		$this->assertSame( 'Security source before', get_post( $fixture['source'] )->post_title );
		$this->assertSame( '', get_post_meta( $fixture['source'], $fixture['source_field']['name'], true ) );
		$this->assertSame( '', get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );
		$this->assertSame( $callbacks, did_action( 'rest_after_insert_post' ) );

		$create = $this->dispatch_post_update(
			0,
			array(
				'title' => 'Security create',
				'acf'   => array( $fixture['source_field']['name'] => array( $fixture['target'] ) ),
			)
		);
		$this->assertSame( 403, $create->get_status() );
		$this->assertSame( 'acf_rest_cannot_update_bidirectional_target', $create->get_data()['code'] );
		$this->assertSame( $callbacks, did_action( 'rest_after_insert_post' ) );

		acf_update_setting( 'enable_bidirection', false );
		$disabled = $this->dispatch_post_update(
			$fixture['source'],
			array( 'acf' => array( $fixture['source_field']['name'] => array( $fixture['target'] ) ) )
		);
		$this->assertSame( 200, $disabled->get_status() );
		$this->assertSame( array( (string) $fixture['target'] ), get_field( $fixture['source_field']['key'], $fixture['source'], false ) );
		$this->assertSame( '', get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );

		acf_update_setting( 'enable_bidirection', true );
		$location_fixture = $this->create_bidirectional_rest_fixture( 'location_change', false, 'post', true, 'pending' );
		$location_change  = $this->dispatch_post_update(
			$location_fixture['source'],
			array(
				'status' => 'pending',
				'acf'    => array( $location_fixture['source_field']['name'] => array( $location_fixture['target'] ) ),
			)
		);
		$this->assertSame( 200, $location_change->get_status() );
		$this->assertSame( 'pending', get_post_status( $location_fixture['source'] ) );
		$this->assertSame( '', get_post_meta( $location_fixture['source'], $location_fixture['source_field']['name'], true ) );
		$this->assertSame( '', get_post_meta( $location_fixture['target'], $location_fixture['target_field']['name'], true ) );

		$inactive_fixture  = $this->create_bidirectional_rest_fixture( 'location_inactive', true, 'post', true, 'draft' );
		$location_inactive = $this->dispatch_post_update(
			$inactive_fixture['source'],
			array(
				'status' => 'pending',
				'acf'    => array( $inactive_fixture['source_field']['name'] => array( $inactive_fixture['target'] ) ),
			)
		);
		$this->assertSame( 200, $location_inactive->get_status() );
		$this->assertSame( 'pending', get_post_status( $inactive_fixture['source'] ) );
		$this->assertSame( '', get_post_meta( $inactive_fixture['source'], $inactive_fixture['source_field']['name'], true ) );
		$this->assertSame( '', get_post_meta( $inactive_fixture['target'], $inactive_fixture['target_field']['name'], true ) );
	}

	/**
	 * REST `acf` rejects a self-user update that targets an administrator.
	 */
	public function test_rest_acf_rejects_unauthorized_bidirectional_user_target_from_self_update() {
		$administrator = wp_insert_user(
			array(
				'user_login' => 'rest_security_user_target_admin',
				'user_pass'  => 'password',
				'user_email' => 'rest-security-user-target-admin@example.com',
				'role'       => 'administrator',
			)
		);
		$subscriber    = wp_insert_user(
			array(
				'user_login' => 'rest_security_user_source_subscriber',
				'user_pass'  => 'password',
				'user_email' => 'rest-security-user-source-subscriber@example.com',
				'role'       => 'subscriber',
			)
		);
		$source_field  = array(
			'key'                  => 'field_test_rest_security_user_source',
			'name'                 => 'test_rest_security_user_source',
			'label'                => 'User security source',
			'type'                 => 'user',
			'role'                 => array(),
			'allow_null'           => 0,
			'multiple'             => 1,
			'return_format'        => 'id',
			'bidirectional'        => true,
			'bidirectional_target' => array( 'field_test_rest_security_user_target' ),
		);
		$target_field  = array(
			'key'           => 'field_test_rest_security_user_target',
			'name'          => 'test_rest_security_user_target',
			'label'         => 'User security target',
			'type'          => 'user',
			'role'          => array(),
			'allow_null'    => 0,
			'multiple'      => 1,
			'return_format' => 'id',
		);
		acf_add_local_field_group(
			array(
				'key'          => 'group_test_rest_security_user_target',
				'title'        => 'REST user security fixture',
				'fields'       => array( $source_field, $target_field ),
				'location'     => array(
					array(
						array(
							'param'    => 'user_form',
							'operator' => '==',
							'value'    => 'all',
						),
					),
				),
				'show_in_rest' => true,
				'active'       => true,
			)
		);

		wp_set_current_user( $subscriber );
		$this->assertTrue( current_user_can( 'edit_user', $subscriber ) );
		$this->assertFalse( current_user_can( 'edit_user', $administrator ) );
		$source_meta = get_user_meta( $subscriber );
		$target_meta = get_user_meta( $administrator );

		unset( $GLOBALS['wp_rest_additional_fields']['user']['acf'] );
		$request = new WP_REST_Request( 'POST', '/wp/v2/users/me' );
		$request->set_body_params(
			array(
				'acf' => array( $source_field['name'] => array( $administrator ) ),
			)
		);
		$result = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $result->get_status() );
		$this->assertSame( 'acf_rest_cannot_update_bidirectional_target', $result->get_data()['code'] );
		$this->assertSame( $source_meta, get_user_meta( $subscriber ) );
		$this->assertSame( $target_meta, get_user_meta( $administrator ) );
	}

	/**
	 * REST `acf` rejects an unauthorized bidirectional removal atomically.
	 */
	public function test_rest_acf_rejects_unauthorized_bidirectional_removal_atomically() {
		$fixture = $this->create_bidirectional_rest_fixture( 'removal' );
		acf_set_data( 'acf_doing_bidirectional_update', true );
		update_field( $fixture['source_field']['key'], array( $fixture['target'] ), $fixture['source'] );
		update_field( $fixture['target_field']['key'], array( $fixture['source'] ), $fixture['target'] );
		acf_set_data( 'acf_doing_bidirectional_update', false );
		$source_value = get_post_meta( $fixture['source'], $fixture['source_field']['name'], true );
		$target_value = get_post_meta( $fixture['target'], $fixture['target_field']['name'], true );
		$callbacks    = did_action( 'rest_after_insert_post' );

		$result = $this->dispatch_post_update(
			$fixture['source'],
			array(
				'title' => 'Security source after',
				'acf'   => array( $fixture['source_field']['name'] => array() ),
			)
		);

		$this->assertSame( 403, $result->get_status() );
		$this->assertSame( 'acf_rest_cannot_update_bidirectional_target', $result->get_data()['code'] );
		$this->assertSame( 'Security source before', get_post( $fixture['source'] )->post_title );
		$this->assertSame( $source_value, get_post_meta( $fixture['source'], $fixture['source_field']['name'], true ) );
		$this->assertSame( $target_value, get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );
		$this->assertSame( $callbacks, did_action( 'rest_after_insert_post' ) );

		wp_delete_post( $fixture['target'], true );
		$clear = $this->dispatch_post_update(
			$fixture['source'],
			array( 'acf' => array( $fixture['source_field']['name'] => array() ) )
		);
		$this->assertSame( 200, $clear->get_status() );
		$this->assertEmpty( get_post_meta( $fixture['source'], $fixture['source_field']['name'], true ) );

		$readd = $this->dispatch_post_update(
			$fixture['source'],
			array( 'acf' => array( $fixture['source_field']['name'] => array( $fixture['target'] ) ) )
		);
		$this->assertSame( 403, $readd->get_status() );
		$this->assertEmpty( get_post_meta( $fixture['source'], $fixture['source_field']['name'], true ) );
	}

	/**
	 * REST `acf` leaves the request intact and rejects values changed after preflight.
	 */
	public function test_rest_acf_preflight_is_reused_without_mutating_the_request() {
		$fixture             = $this->create_bidirectional_rest_fixture( 'request_plan', true );
		$original            = array( $fixture['source_field']['name'] => array( $fixture['target'] ) );
		$observed            = null;
		$get_fields_calls    = 0;
		$insert_callbacks    = did_action( 'rest_after_insert_post' );
		$preflight_callbacks = 0;
		foreach ( $GLOBALS['wp_filter']['rest_dispatch_request']->callbacks[10] as $registered_filter ) {
			if ( $registered_filter['function'] instanceof Closure && ( new ReflectionFunction( $registered_filter['function'] ) )->getClosureThis() instanceof ACF_Rest_Api ) {
				++$preflight_callbacks;
			}
		}
		$get_fields = function ( $fields, $resource_data, $http_method ) use ( &$get_fields_calls, $fixture, &$insert_callbacks ) {
			unset( $http_method );
			if ( did_action( 'rest_after_insert_post' ) === $insert_callbacks && true === in_array( $fixture['source_field']['key'], array_column( $fields, 'key' ), true ) && (int) $resource_data['id'] === $fixture['source'] ) {
				++$get_fields_calls;
			}
			return $fields;
		};
		$observer   = function ( $result, $request ) use ( &$observed, $fixture ) {
			if ( "/wp/v2/posts/{$fixture['source']}" === $request->get_route() ) {
				$observed = $request->get_param( 'acf' );
			}
			return $result;
		};
		add_filter( 'acf/rest/get_fields', $get_fields, 10, 3 );
		add_filter( 'rest_dispatch_request', $observer, 11, 2 );

		$saved = $this->dispatch_post_update( $fixture['source'], array( 'acf' => $original ) );

		$this->assertSame( 200, $saved->get_status() );
		$this->assertSame( $original, $observed );
		$this->assertSame( $preflight_callbacks + 1, $get_fields_calls );
		$this->assertSame( array( (string) $fixture['target'] ), get_field( $fixture['source_field']['key'], $fixture['source'], false ) );

		acf_update_setting( 'enable_bidirection', false );
		update_field( $fixture['source_field']['key'], array(), $fixture['source'] );
		update_field( $fixture['target_field']['key'], array(), $fixture['target'] );
		acf_update_setting( 'enable_bidirection', true );

		$administrator  = wp_insert_user(
			array(
				'user_login' => 'rest_security_request_plan_admin',
				'user_pass'  => 'password',
				'user_email' => 'request_plan_admin@example.com',
				'role'       => 'administrator',
			)
		);
		$private_target = wp_insert_post(
			array(
				'post_title'  => 'Private post-preflight target',
				'post_status' => 'private',
				'post_author' => $administrator,
			)
		);
		$mutator        = function ( $result, $request ) use ( $fixture, $private_target ) {
			if ( "/wp/v2/posts/{$fixture['source']}" === $request->get_route() ) {
				$request->set_param( 'acf', array( $fixture['source_field']['name'] => array( $private_target ) ) );
			}
			return $result;
		};
		add_filter( 'rest_dispatch_request', $mutator, 12, 2 );
		$insert_callbacks = did_action( 'rest_after_insert_post' );

		$changed_after_preflight = $this->dispatch_post_update( $fixture['source'], array( 'acf' => $original ) );

		remove_filter( 'rest_dispatch_request', $mutator, 12 );
		remove_filter( 'rest_dispatch_request', $observer, 11 );
		remove_filter( 'acf/rest/get_fields', $get_fields, 10 );

		$this->assertSame( 200, $changed_after_preflight->get_status() );
		$this->assertSame( 2 * ( $preflight_callbacks + 1 ), $get_fields_calls );
		$this->assertEmpty( get_post_meta( $fixture['source'], $fixture['source_field']['name'], true ) );
		$this->assertEmpty( get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );
		$this->assertEmpty( get_post_meta( $private_target, $fixture['target_field']['name'], true ) );
	}

	/**
	 * Datastore rejects unauthorized destinations before Core or ACF saves.
	 */
	public function test_datastore_rejects_unauthorized_bidirectional_destination_atomically() {
		$fixture   = $this->create_bidirectional_rest_fixture( 'datastore_forbidden', false, 'post', false );
		$save_post = 0;
		$callback  = function () use ( &$save_post ) {
			++$save_post;
		};
		wp_update_post(
			array(
				'ID'          => $fixture['source'],
				'post_status' => 'pending',
			)
		);
		$rest_save = $this->enable_datastore_rest();
		add_action( 'acf/save_post', $callback );
		$prepare_field = function ( $field ) use ( $fixture ) {
			if ( $fixture['source_field']['key'] === $field['key'] ) {
				$field['bidirectional'] = false;
			}
			return $field;
		};
		add_filter( 'acf/prepare_field', $prepare_field );

		$result           = $this->dispatch_post_update(
			$fixture['source'],
			array(
				'title' => 'Security source after',
				'meta'  => array( '_acf' => wp_json_encode( array( $fixture['source_field']['key'] => array( $fixture['target'] ) ) ) ),
			)
		);
		$create           = $this->dispatch_post_update(
			0,
			array(
				'title' => 'Datastore security create',
				'meta'  => array( '_acf' => wp_json_encode( array( $fixture['source_field']['key'] => array( $fixture['target'] ) ) ) ),
			)
		);
		$autosave_request = new WP_REST_Request( 'POST', "/wp/v2/posts/{$fixture['source']}/autosaves" );
		$autosave_request->set_body_params(
			array(
				'title' => 'Security autosave after',
				'meta'  => array( '_acf' => wp_json_encode( array( $fixture['source_field']['key'] => array( $fixture['target'] ) ) ) ),
			)
		);
		$autosave = rest_get_server()->dispatch( $autosave_request );

		$draft_fixture = $this->create_bidirectional_rest_fixture( 'datastore_draft_autosave', false, 'post', false );
		$draft_request = new WP_REST_Request( 'POST', "/wp/v2/posts/{$draft_fixture['source']}/autosaves" );
		$draft_request->set_url_params( array( 'id' => $draft_fixture['source'] ) );
		$draft_request->set_body_params(
			array(
				'title' => 'Draft autosave after',
				'meta'  => array( '_acf' => wp_json_encode( array( $draft_fixture['source_field']['key'] => array( $draft_fixture['target'] ) ) ) ),
			)
		);
		apply_filters(
			'rest_dispatch_request',
			null,
			$draft_request,
			$draft_request->get_route(),
			array( 'callback' => array( new WP_REST_Autosaves_Controller( 'post' ), 'create_item' ) )
		);
		$this->assertArrayNotHasKey( '_acf', $draft_request->get_param( 'meta' ) );
		$draft_request->set_param(
			'meta',
			array( '_acf' => wp_json_encode( array( $draft_fixture['source_field']['key'] => array( $draft_fixture['target'] ) ) ) )
		);
		update_post_meta( $draft_fixture['source'], '_acf', $draft_request->get_param( 'meta' )['_acf'] );
		$draft_response = new WP_REST_Response();
		$this->assertSame( $draft_response, $rest_save->save_autosave_rest( $draft_response, get_post( $draft_fixture['source'] ), $draft_request ) );
		remove_filter( 'acf/prepare_field', $prepare_field );
		remove_action( 'acf/save_post', $callback );

		$this->assertSame( 403, $result->get_status() );
		$this->assertSame( 'acf_rest_cannot_update_bidirectional_target', $result->get_data()['code'] );
		$this->assertSame( 403, $create->get_status() );
		$this->assertSame( 'acf_rest_cannot_update_bidirectional_target', $create->get_data()['code'] );
		$this->assertSame( 403, $autosave->get_status() );
		$this->assertSame( 'acf_rest_cannot_update_bidirectional_target', $autosave->get_data()['code'] );
		$this->assertFalse( wp_get_post_autosave( $fixture['source'], $fixture['contributor'] ) );
		$this->assertSame( '', get_post_meta( $draft_fixture['target'], $draft_fixture['target_field']['name'], true ) );
		$this->assertSame( 0, $save_post );
		$this->assertSame( 'Security source before', get_post( $fixture['source'] )->post_title );
		$this->assertSame( '', get_post_meta( $fixture['source'], $fixture['source_field']['name'], true ) );
		$this->assertSame( '', get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );
	}

	/**
	 * Datastore drops only unsafe bidirectional roots outside the active context.
	 */
	public function test_datastore_drops_only_unsafe_out_of_context_root() {
		$fixture  = $this->create_bidirectional_rest_fixture( 'datastore_hidden', false, 'page', false );
		$active   = array(
			'key'   => 'field_test_rest_security_active_text',
			'name'  => 'test_rest_security_active_text',
			'label' => 'Active text',
			'type'  => 'text',
		);
		$inactive = array(
			'key'   => 'field_test_rest_security_inactive_text',
			'name'  => 'test_rest_security_inactive_text',
			'label' => 'Inactive text',
			'type'  => 'text',
		);
		acf_add_local_field_group(
			array(
				'key'          => 'group_test_rest_security_active',
				'title'        => 'Active datastore field',
				'fields'       => array( $active ),
				'location'     => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
						array(
							'param'    => 'post_status',
							'operator' => '==',
							'value'    => 'draft',
						),
					),
				),
				'show_in_rest' => false,
				'active'       => true,
			)
		);
		acf_add_local_field_group(
			array(
				'key'          => 'group_test_rest_security_inactive',
				'title'        => 'Inactive datastore field',
				'fields'       => array( $inactive ),
				'location'     => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'page',
						),
					),
				),
				'show_in_rest' => false,
				'active'       => true,
			)
		);
		$this->enable_datastore_rest();

		$observed_values = null;
		$observer        = function ( $result, $request ) use ( &$observed_values, $fixture ) {
			if ( "/wp/v2/posts/{$fixture['source']}" === $request->get_route() ) {
				$meta            = $request->get_param( 'meta' );
				$observed_values = json_decode( $meta['_acf'], true );
			}
			return $result;
		};
		add_filter( 'rest_dispatch_request', $observer, 11, 2 );
		$result = $this->dispatch_post_update(
			$fixture['source'],
			array(
				'meta' => array(
					'_acf' => wp_json_encode(
						array(
							$active['key']   => 'allowed',
							$inactive['key'] => 'also allowed',
							$fixture['source_field']['key'] => array( $fixture['target'] ),
						)
					),
				),
			)
		);
		remove_filter( 'rest_dispatch_request', $observer, 11 );

		$this->assertSame( 200, $result->get_status() );
		$this->assertSame(
			array(
				$active['key']   => 'allowed',
				$inactive['key'] => 'also allowed',
			),
			$observed_values
		);
		$this->assertSame( 'allowed', get_post_meta( $fixture['source'], $active['name'], true ) );
		$this->assertSame( 'also allowed', get_post_meta( $fixture['source'], $inactive['name'], true ) );
		$this->assertSame( '', get_post_meta( $fixture['source'], $fixture['source_field']['name'], true ) );
		$this->assertSame( '', get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );

		$raw_transport = "{\n  \"{$inactive['key']}\": \"byte preserved\"\n}";
		$observed_blob = null;
		$blob_observer = function ( $filter_result, $request ) use ( &$observed_blob, $fixture ) {
			if ( "/wp/v2/posts/{$fixture['source']}" === $request->get_route() ) {
				$observed_blob = $request->get_param( 'meta' )['_acf'];
			}
			return $filter_result;
		};
		add_filter( 'rest_dispatch_request', $blob_observer, 11, 2 );
		$preserved = $this->dispatch_post_update(
			$fixture['source'],
			array( 'meta' => array( '_acf' => $raw_transport ) )
		);
		remove_filter( 'rest_dispatch_request', $blob_observer, 11 );

		$this->assertSame( 200, $preserved->get_status() );
		$this->assertSame( $raw_transport, $observed_blob );
		$this->assertSame( 'byte preserved', get_post_meta( $fixture['source'], $inactive['name'], true ) );

		$conditional_create = $this->dispatch_post_update(
			0,
			array(
				'title' => 'Conditional datastore create',
				'meta'  => array( '_acf' => wp_json_encode( array( $active['key'] => 'created' ) ) ),
			)
		);
		$conditional_id     = $conditional_create->get_data()['id'];
		$this->assertSame( 201, $conditional_create->get_status() );
		$this->assertSame( 'created', get_post_meta( $conditional_id, $active['name'], true ) );

		$filtered_create = $this->dispatch_post_update(
			0,
			array(
				'title' => 'Fully filtered datastore create',
				'meta'  => array( '_acf' => wp_json_encode( array( $fixture['source_field']['key'] => array( $fixture['target'] ) ) ) ),
			)
		);
		$filtered_id     = $filtered_create->get_data()['id'];
		$this->assertSame( 201, $filtered_create->get_status() );
		$this->assertFalse( metadata_exists( 'post', $filtered_id, '_acf' ) );
		$this->assertSame( '', get_post_meta( $filtered_id, $fixture['source_field']['name'], true ) );
		$this->assertSame( '', get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );
	}

	/**
	 * Datastore creates keep fields conditioned on the requested page template.
	 */
	public function test_datastore_create_keeps_template_conditioned_field() {
		// Core registers these locations on init; load them for the test harness.
		foreach ( array( 'post-template', 'page-template' ) as $location ) {
			if ( ! class_exists( 'ACF_Location_' . str_replace( '-', '_', ucwords( $location, '-' ) ) ) ) {
				acf_include( "includes/locations/class-acf-location-{$location}.php" );
			}
		}
		add_filter(
			'theme_page_templates',
			static function ( $templates ) {
				$templates['custom-template.php'] = 'Custom Template';
				return $templates;
			}
		);
		// The template list is cached; reset it so the filter above is honoured.
		acf_set_data( 'post_templates', null );
		acf_add_local_field_group(
			array(
				'key'          => 'group_template_conditioned',
				'title'        => 'Template conditioned',
				'fields'       => array(
					array(
						'key'   => 'field_template_text',
						'name'  => 'template_text',
						'label' => 'Template text',
						'type'  => 'text',
					),
				),
				'location'     => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'page',
						),
						array(
							'param'    => 'page_template',
							'operator' => '==',
							'value'    => 'custom-template.php',
						),
					),
				),
				'show_in_rest' => false,
				'active'       => true,
			)
		);
		$rest_save = $this->enable_datastore_rest();

		$request = new WP_REST_Request( 'POST', '/wp/v2/pages' );
		$request->set_body_params(
			array(
				'title'    => 'Templated page',
				'template' => 'custom-template.php',
				'meta'     => array( '_acf' => wp_json_encode( array( 'field_template_text' => 'kept' ) ) ),
			)
		);
		apply_filters(
			'rest_dispatch_request',
			null,
			$request,
			$request->get_route(),
			array( 'callback' => array( new WP_REST_Posts_Controller( 'page' ), 'create_item' ) )
		);

		$meta = $request->get_param( 'meta' );
		$this->assertArrayHasKey( '_acf', $meta, 'Template-conditioned field must survive the create preflight' );
		$this->assertSame( array( 'field_template_text' => 'kept' ), json_decode( $meta['_acf'], true ) );
	}

	/**
	 * A template-conditioned bidirectional field is rejected, not silently dropped.
	 */
	public function test_datastore_create_rejects_template_conditioned_bidirectional_target() {
		// Core registers these locations on init; load them for the test harness.
		foreach ( array( 'post-template', 'page-template' ) as $location ) {
			if ( ! class_exists( 'ACF_Location_' . str_replace( '-', '_', ucwords( $location, '-' ) ) ) ) {
				acf_include( "includes/locations/class-acf-location-{$location}.php" );
			}
		}
		add_filter(
			'theme_page_templates',
			static function ( $templates ) {
				$templates['custom-template.php'] = 'Custom Template';
				return $templates;
			}
		);
		// The template list is cached; reset it so the filter above is honoured.
		acf_set_data( 'post_templates', null );

		$administrator = wp_insert_user(
			array(
				'user_login' => 'rest_template_bidi_admin',
				'user_pass'  => 'password',
				'user_email' => 'template_bidi_admin@example.com',
				'role'       => 'administrator',
			)
		);
		$contributor   = wp_insert_user(
			array(
				'user_login' => 'rest_template_bidi_contributor',
				'user_pass'  => 'password',
				'user_email' => 'template_bidi_contributor@example.com',
				'role'       => 'contributor',
			)
		);
		$target        = wp_insert_post(
			array(
				'post_title'  => 'Template bidirectional target',
				'post_status' => 'private',
				'post_author' => $administrator,
			)
		);
		acf_add_local_field_group(
			array(
				'key'          => 'group_template_conditioned_bidirectional',
				'title'        => 'Template conditioned bidirectional',
				'fields'       => array(
					array(
						'key'                  => 'field_template_bidi_source',
						'name'                 => 'template_bidi_source',
						'label'                => 'Template bidirectional source',
						'type'                 => 'relationship',
						'post_type'            => array(),
						'taxonomy'             => array(),
						'bidirectional'        => true,
						'bidirectional_target' => array( 'field_template_bidi_target' ),
					),
					array(
						'key'       => 'field_template_bidi_target',
						'name'      => 'template_bidi_target',
						'label'     => 'Template bidirectional target',
						'type'      => 'relationship',
						'post_type' => array(),
						'taxonomy'  => array(),
					),
				),
				'location'     => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'page',
						),
						array(
							'param'    => 'page_template',
							'operator' => '==',
							'value'    => 'custom-template.php',
						),
					),
				),
				'show_in_rest' => false,
				'active'       => true,
			)
		);
		$this->enable_datastore_rest();
		wp_set_current_user( $contributor );

		$request = new WP_REST_Request( 'POST', '/wp/v2/pages' );
		$request->set_body_params(
			array(
				'title'    => 'Templated page',
				'template' => 'custom-template.php',
				'meta'     => array( '_acf' => wp_json_encode( array( 'field_template_bidi_source' => array( $target ) ) ) ),
			)
		);
		$result = apply_filters(
			'rest_dispatch_request',
			null,
			$request,
			$request->get_route(),
			array( 'callback' => array( new WP_REST_Posts_Controller( 'page' ), 'create_item' ) )
		);

		// The requested template makes the group active, so the unauthorized target is
		// reported instead of the field being quietly discarded.
		$this->assertInstanceOf( WP_Error::class, $result, 'An active template-conditioned field must be rejected' );
		$this->assertSame( 'acf_rest_cannot_update_bidirectional_target', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertSame( '', get_post_meta( $target, 'template_bidi_target', true ) );
	}

	/**
	 * Authorized bidirectional updates succeed through both REST transports.
	 */
	public function test_authorized_bidirectional_updates_succeed_through_both_transports() {
		$fixture = $this->create_bidirectional_rest_fixture( 'authorized', true );
		$normal  = $this->dispatch_post_update(
			$fixture['source'],
			array( 'acf' => array( $fixture['source_field']['name'] => array( $fixture['target'] ) ) )
		);

		$this->assertSame( 200, $normal->get_status() );
		$this->assertSame( array( (string) $fixture['target'] ), get_field( $fixture['source_field']['key'], $fixture['source'], false ) );
		$this->assertSame( array( (string) $fixture['source'] ), get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );

		$datastore_source = wp_insert_post(
			array(
				'post_title'  => 'Datastore authorized source',
				'post_status' => 'draft',
				'post_author' => $fixture['contributor'],
			)
		);
		$datastore_target = wp_insert_post(
			array(
				'post_title'  => 'Datastore authorized target',
				'post_status' => 'draft',
				'post_author' => $fixture['contributor'],
			)
		);
		$this->enable_datastore_rest();
		$datastore = $this->dispatch_post_update(
			$datastore_source,
			array( 'meta' => array( '_acf' => wp_json_encode( array( $fixture['source_field']['key'] => array( $datastore_target ) ) ) ) )
		);

		$this->assertSame( 200, $datastore->get_status() );
		$this->assertSame( array( (string) $datastore_target ), get_field( $fixture['source_field']['key'], $datastore_source, false ) );
		$this->assertSame( array( (string) $datastore_source ), get_post_meta( $datastore_target, $fixture['target_field']['name'], true ) );
	}

	/**
	 * Nested bidirectional fields are preflighted in every core container.
	 *
	 * @dataProvider nested_container_provider
	 *
	 * @param string $container_type Container field type.
	 */
	public function test_nested_bidirectional_fields_are_preflighted_in_both_rest_transports( $container_type ) {
		foreach ( array( 'acf', 'datastore' ) as $transport ) {
			$datastore = 'datastore' === $transport;
			if ( $datastore ) {
				$this->enable_datastore_rest();
			}

			$forbidden   = $this->create_nested_bidirectional_rest_fixture( "{$container_type}_{$transport}_forbidden", $container_type );
			$value       = $this->prepare_nested_rest_value( $forbidden, $datastore );
			$callbacks   = did_action( 'rest_after_insert_post' );
			$source_meta = get_post_meta( $forbidden['source'] );
			$target_meta = get_post_meta( $forbidden['target'] );
			$body        = array( 'title' => 'Nested security source after' );
			if ( $datastore ) {
				$body['meta'] = array( '_acf' => wp_json_encode( array( $forbidden['root_field']['key'] => $value ) ) );
			} else {
				$body['acf'] = array( $forbidden['root_field']['name'] => $value );
			}
			$result = $this->dispatch_post_update( $forbidden['source'], $body );

			$this->assertSame( 403, $result->get_status(), "{$container_type} via {$transport} should be rejected" );
			$this->assertSame( 'acf_rest_cannot_update_bidirectional_target', $result->get_data()['code'] );
			$this->assertSame( 'Nested security source before', get_post( $forbidden['source'] )->post_title );
			$this->assertSame( $source_meta, get_post_meta( $forbidden['source'] ) );
			$this->assertSame( $target_meta, get_post_meta( $forbidden['target'] ) );
			$this->assertSame( $callbacks, did_action( 'rest_after_insert_post' ) );

			$allowed       = $this->create_nested_bidirectional_rest_fixture( "{$container_type}_{$transport}_allowed", $container_type, true );
			$allowed_value = $this->prepare_nested_rest_value( $allowed, $datastore );
			$allowed_body  = array();
			if ( $datastore ) {
				$allowed_body['meta'] = array( '_acf' => wp_json_encode( array( $allowed['root_field']['key'] => $allowed_value ) ) );
			} else {
				$allowed_body['acf'] = array( $allowed['root_field']['name'] => $allowed_value );
			}
			$allowed_result = $this->dispatch_post_update( $allowed['source'], $allowed_body );

			$this->assertSame( 200, $allowed_result->get_status(), "{$container_type} via {$transport} should be saved" );
			$this->assertSame( array( (string) $allowed['source'] ), get_post_meta( $allowed['target'], $allowed['target_field']['name'], true ) );

			if ( 'clone' === $container_type ) {
				wp_update_post(
					array(
						'ID'          => $allowed['target'],
						'post_author' => $allowed['administrator'],
						'post_status' => 'private',
					)
				);
				$partial_body = $datastore
					? array( 'meta' => array( '_acf' => wp_json_encode( array( $allowed['root_field']['key'] => array() ) ) ) )
					: array( 'acf' => array( $allowed['root_field']['name'] => array() ) );
				$partial      = $this->dispatch_post_update( $allowed['source'], $partial_body );

				$this->assertSame( 200, $partial->get_status(), "Omitting a clone child via {$transport} should not be treated as clearing it" );
				$this->assertSame( array( (string) $allowed['source'] ), get_post_meta( $allowed['target'], $allowed['target_field']['name'], true ) );

				$child_selector = $datastore ? $allowed['source_field']['key'] : $allowed['source_field']['name'];
				$clear_value    = array( $child_selector => array() );
				$clear_body     = $datastore
					? array( 'meta' => array( '_acf' => wp_json_encode( array( $allowed['root_field']['key'] => $clear_value ) ) ) )
					: array( 'acf' => array( $allowed['root_field']['name'] => $clear_value ) );
				$clear          = $this->dispatch_post_update( $allowed['source'], $clear_body );

				$this->assertSame( 403, $clear->get_status(), "Explicitly clearing a clone child via {$transport} should require target permission" );
				$this->assertSame( array( (string) $allowed['source'] ), get_post_meta( $allowed['target'], $allowed['target_field']['name'], true ) );
			}
		}
	}

	/**
	 * Core nested container types.
	 *
	 * @return array
	 */
	public function nested_container_provider() {
		return array(
			'group'            => array( 'group' ),
			'clone group'      => array( 'clone' ),
			'repeater'         => array( 'repeater' ),
			'flexible content' => array( 'flexible_content' ),
		);
	}

	/**
	 * Datastore preflights rows re-saved by paginated repeaters.
	 */
	public function test_datastore_preflights_untouched_paginated_repeater_rows() {
		$fixture = $this->create_nested_bidirectional_rest_fixture( 'paginated_datastore', 'repeater', false, true );
		acf_update_setting( 'enable_bidirection', false );
		update_field(
			$fixture['root_field']['key'],
			array(
				array( $fixture['source_field']['key'] => array( $fixture['target'] ) ),
				array( $fixture['source_field']['key'] => array() ),
			),
			$fixture['source']
		);
		acf_update_setting( 'enable_bidirection', true );
		$this->assertSame( '', get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );
		$this->enable_datastore_rest();

		$result = $this->dispatch_post_update(
			$fixture['source'],
			array(
				'meta' => array(
					'_acf' => wp_json_encode(
						array(
							$fixture['root_field']['key'] => array(
								'row-1' => array( $fixture['source_field']['key'] => array() ),
							),
						)
					),
				),
			)
		);

		$this->assertSame( '', get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );
		$this->assertSame( 403, $result->get_status() );
		$this->assertSame( 'acf_rest_cannot_update_bidirectional_target', $result->get_data()['code'] );

		$delete = $this->dispatch_post_update(
			$fixture['source'],
			array(
				'meta' => array(
					'_acf' => wp_json_encode(
						array(
							$fixture['root_field']['key'] => array(
								'row-0' => array(
									'acf_deleted' => 1,
									$fixture['source_field']['key'] => array( $fixture['target'] ),
								),
								'row-1' => array( $fixture['source_field']['key'] => array() ),
							),
						)
					),
				),
			)
		);

		$this->assertSame( 200, $delete->get_status() );
		$this->assertSame( '', get_post_meta( $fixture['target'], $fixture['target_field']['name'], true ) );
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
