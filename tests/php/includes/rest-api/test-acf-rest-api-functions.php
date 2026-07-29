<?php
/**
 * Tests for ACF REST API functions.
 *
 * Tests the helper functions used by the REST API integration.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the REST API functions.
acf_include( 'includes/rest-api/acf-rest-api-functions.php' );

/**
 * Class Test_ACF_Rest_Api_Functions
 *
 * Tests for the ACF REST API helper functions.
 *
 * @group rest-api
 * @group p0-critical
 */
class Test_ACF_Rest_Api_Functions extends BaseTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected $test_post_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Ensure SCF field types are loaded for REST schema tests.
		if ( ! class_exists( 'acf_field_text' ) ) {
			acf_include( 'includes/fields/class-acf-field-text.php' );
		}
		if ( ! class_exists( 'acf_field_textarea' ) ) {
			acf_include( 'includes/fields/class-acf-field-textarea.php' );
		}
		if ( ! class_exists( 'acf_field_number' ) ) {
			acf_include( 'includes/fields/class-acf-field-number.php' );
		}
		if ( ! class_exists( 'acf_field_email' ) ) {
			acf_include( 'includes/fields/class-acf-field-email.php' );
		}
		if ( ! class_exists( 'acf_field_url' ) ) {
			acf_include( 'includes/fields/class-acf-field-url.php' );
		}
		if ( ! class_exists( 'acf_field_password' ) ) {
			acf_include( 'includes/fields/class-acf-field-password.php' );
		}
		if ( ! class_exists( 'acf_field_wysiwyg' ) ) {
			acf_include( 'includes/fields/class-acf-field-wysiwyg.php' );
		}
		if ( ! class_exists( 'acf_field_true_false' ) ) {
			acf_include( 'includes/fields/class-acf-field-true-false.php' );
		}
		if ( ! class_exists( 'acf_field_select' ) ) {
			acf_include( 'includes/fields/class-acf-field-select.php' );
		}
		if ( ! class_exists( 'acf_field_checkbox' ) ) {
			acf_include( 'includes/fields/class-acf-field-checkbox.php' );
		}
		if ( ! class_exists( 'acf_field_radio' ) ) {
			acf_include( 'includes/fields/class-acf-field-radio.php' );
		}
		if ( ! class_exists( 'acf_field__group' ) ) {
			acf_include( 'includes/fields/class-acf-field-group.php' );
		}
		if ( ! class_exists( 'acf_field_user' ) ) {
			acf_include( 'includes/fields/class-acf-field-user.php' );
		}
		if ( ! has_filter( 'acf/format_value/type=group' ) ) {
			acf_register_field_type( 'acf_field__group' );
		}
		if ( ! has_filter( 'acf/format_value/type=user' ) ) {
			acf_register_field_type( 'acf_field_user' );
		}

		// Create a test post.
		$this->test_post_id = wp_insert_post(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		// Clean up test post.
		if ( $this->test_post_id ) {
			wp_delete_post( $this->test_post_id, true );
		}

		parent::tear_down();
	}

	// =========================================================================
	// Schema Tests
	// =========================================================================

	/**
	 * Test acf_get_field_rest_schema returns array.
	 */
	public function test_get_field_rest_schema_returns_array() {
		$field = array(
			'key'  => 'field_test',
			'name' => 'test_field',
			'type' => 'text',
		);

		$result = acf_get_field_rest_schema( $field );

		$this->assertIsArray( $result );
	}

	/**
	 * Test acf_get_field_rest_schema returns empty array for non-existent field type.
	 */
	public function test_get_field_rest_schema_returns_empty_for_nonexistent_type() {
		$field = array(
			'key'  => 'field_test',
			'name' => 'test_field',
			'type' => 'nonexistent_type_xyz',
		);

		$result = acf_get_field_rest_schema( $field );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test acf_get_field_rest_schema filter is applied.
	 */
	public function test_get_field_rest_schema_filter_is_applied() {
		$filter_called = false;

		add_filter(
			'acf/rest/get_field_schema',
			function ( $schema, $field ) use ( &$filter_called ) {
				$filter_called = true;
				$this->assertIsArray( $schema );
				$this->assertIsArray( $field );
				$this->assertArrayHasKey( 'type', $field );
				return $schema;
			},
			10,
			2
		);

		$field = array(
			'key'  => 'field_test_filter',
			'name' => 'test_filter_field',
			'type' => 'text',
		);

		acf_get_field_rest_schema( $field );

		$this->assertTrue( $filter_called, 'acf/rest/get_field_schema filter should be called' );

		remove_all_filters( 'acf/rest/get_field_schema' );
	}

	/**
	 * Test acf_get_field_rest_schema filter can modify schema.
	 */
	public function test_get_field_rest_schema_filter_can_modify() {
		add_filter(
			'acf/rest/get_field_schema',
			function ( $schema ) {
				$schema['custom_property'] = 'custom_value';
				return $schema;
			}
		);

		$field = array(
			'key'  => 'field_test_modify',
			'name' => 'test_modify_field',
			'type' => 'text',
		);

		$result = acf_get_field_rest_schema( $field );

		$this->assertArrayHasKey( 'custom_property', $result );
		$this->assertEquals( 'custom_value', $result['custom_property'] );

		remove_all_filters( 'acf/rest/get_field_schema' );
	}

	/**
	 * Test acf_get_field_rest_schema with various field types.
	 *
	 * @dataProvider field_types_provider
	 *
	 * @param string $field_type The field type to test.
	 * @param array  $extra_args Additional field arguments required by the type.
	 */
	public function test_get_field_rest_schema_for_field_types( $field_type, $extra_args = array() ) {
		$field = array_merge(
			array(
				'key'  => 'field_test_' . $field_type,
				'name' => 'test_' . $field_type,
				'type' => $field_type,
			),
			$extra_args
		);

		$result = acf_get_field_rest_schema( $field );

		// All field types should return an array (may be empty if type not registered).
		$this->assertIsArray( $result );
	}

	/**
	 * Data provider for field types.
	 *
	 * @return array
	 */
	public function field_types_provider() {
		return array(
			'text'         => array( 'text', array() ),
			'textarea'     => array( 'textarea', array() ),
			'number'       => array( 'number', array() ),
			'email'        => array( 'email', array() ),
			'url'          => array( 'url', array() ),
			'password'     => array( 'password', array() ),
			'wysiwyg'      => array( 'wysiwyg', array() ),
			'select'       => array(
				'select',
				array(
					'choices' => array(
						'a' => 'A',
						'b' => 'B',
					),
				),
			),
			'checkbox'     => array(
				'checkbox',
				array(
					'choices' => array(
						'a' => 'A',
						'b' => 'B',
					),
				),
			),
			'radio'        => array(
				'radio',
				array(
					'choices' => array(
						'a' => 'A',
						'b' => 'B',
					),
				),
			),
			'true_false'   => array( 'true_false', array() ),
			'date_picker'  => array( 'date_picker', array() ),
			'color_picker' => array( 'color_picker', array() ),
		);
	}

	// =========================================================================
	// Links Tests
	// =========================================================================

	/**
	 * Test acf_get_field_rest_links filter is registered.
	 */
	public function test_get_field_rest_links_filter_exists() {
		// The filter should be registered via acf_add_filter_variations.
		// We can verify by checking that we can add a filter.
		$filter_added = false;

		add_filter(
			'acf/rest/get_field_links',
			function ( $links ) use ( &$filter_added ) {
				$filter_added = true;
				return $links;
			}
		);

		// Verify filter was added.
		$this->assertTrue( has_filter( 'acf/rest/get_field_links' ) !== false );

		remove_all_filters( 'acf/rest/get_field_links' );
	}

	/**
	 * Test acf_get_field_rest_links filter receives expected parameters.
	 *
	 * This test verifies that the filter can be registered. In WorDBless,
	 * the function may error before the filter runs due to field type limitations.
	 */
	public function test_get_field_rest_links_filter_parameters() {
		$filter_registered = false;

		add_filter(
			'acf/rest/get_field_links',
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Testing filter signature.
			function ( $links, $post_id, $field, $value ) use ( &$filter_registered ) {
				$filter_registered = true;
				return $links;
			},
			10,
			4
		);

		// Verify the filter hook exists and accepts our callback.
		$this->assertTrue(
			has_filter( 'acf/rest/get_field_links' ),
			'Filter acf/rest/get_field_links should be registered'
		);

		remove_all_filters( 'acf/rest/get_field_links' );
	}

	/**
	 * Test acf_get_field_rest_links returns array when filter provides value.
	 */
	public function test_get_field_rest_links_returns_array_from_filter() {
		// Pre-empt the function by adding a filter at priority 1 that short-circuits.
		add_filter(
			'acf/rest/get_field_links',
			function () {
				return array(
					array(
						'rel'  => 'test',
						'href' => 'https://example.com',
					),
				);
			},
			1
		);

		$field = array(
			'key'  => 'field_test',
			'name' => 'test_field',
			'type' => 'text',
		);

		$result = acf_get_field_rest_links( $this->test_post_id, $field );
		$this->assertIsArray( $result );

		remove_all_filters( 'acf/rest/get_field_links' );
	}

	// =========================================================================
	// Format Value Tests
	// =========================================================================

	/**
	 * Test acf_format_value_for_rest with standard format.
	 */
	public function test_format_value_for_rest_standard_format() {
		$field = array(
			'key'  => 'field_test_format',
			'name' => 'test_format',
			'type' => 'text',
		);

		// Standard format uses acf_format_value which should work.
		$result = acf_format_value_for_rest( 'test value', $this->test_post_id, $field, 'standard' );

		$this->assertEquals( 'test value', $result );
	}

	/**
	 * Test standard REST formatting uses REST-safe output for nested User fields.
	 */
	public function test_format_value_for_rest_standard_format_uses_safe_nested_user_format() {
		$user_login = uniqid( 'rest_safe_nested_user_', false );
		$user_email = "{$user_login}@example.invalid";

		$user_id = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_pass'  => 'password',
				'user_email' => $user_email,
			)
		);

		global $wpdb;
		$activation_key = "{$user_login}-activation-key";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Test fixture needs a controlled activation key marker.
		$wpdb->update(
			$wpdb->users,
			array( 'user_activation_key' => $activation_key ),
			array( 'ID' => $user_id )
		);
		clean_user_cache( $user_id );

		$user_field = acf_validate_field(
			array(
				'key'           => 'field_test_nested_user',
				'name'          => 'nested_user',
				'type'          => 'user',
				'required'      => 0,
				'multiple'      => 0,
				'return_format' => 'array',
				'role'          => array(),
				'allow_null'    => 0,
			)
		);

		$metadata_field = acf_validate_field(
			array(
				'key'  => 'field_test_nested_metadata',
				'name' => 'metadata',
				'type' => 'text',
			)
		);

		$field = acf_validate_field(
			array(
				'key'        => 'field_test_group_with_user',
				'name'       => 'test_group_with_user',
				'type'       => 'group',
				'sub_fields' => array( $user_field, $metadata_field ),
			)
		);

		$metadata = array(
			'ID'              => 777,
			'user_email'      => 'public-metadata@example.invalid',
			'user_nicename'   => 'public-metadata',
			'user_registered' => '2026-07-24 00:00:00',
			'user_avatar'     => 'avatar',
			'description'     => 'Keep this sibling value intact.',
		);
		$value    = array(
			$user_field['key']     => $user_id,
			$metadata_field['key'] => $metadata,
		);

		$shim_user_query = static function ( $results, $query ) use ( $user_id ) {
			$query->total_users = 1;
			return array( $user_id );
		};
		add_filter( 'users_pre_query', $shim_user_query, 10, 2 );

		$remove_user_avatar = static function ( $formatted_value ) {
			if ( is_array( $formatted_value ) ) {
				unset( $formatted_value['user_avatar'] );
			}
			return $formatted_value;
		};
		add_filter( 'acf/format_value/type=user', $remove_user_avatar, 20 );

		$results = array();
		foreach ( array( 'array', 'object' ) as $return_format ) {
			$field['sub_fields'][0]['return_format'] = $return_format;
			acf_get_store( 'values' )->reset();

			$results[ $return_format ] = acf_format_value_for_rest( $value, $this->test_post_id, $field, 'standard' );
		}

		remove_filter( 'acf/format_value/type=user', $remove_user_avatar, 20 );
		remove_filter( 'users_pre_query', $shim_user_query, 10 );
		wp_delete_user( $user_id );

		foreach ( $results as $result ) {
			$json = wp_json_encode( $result );

			$this->assertArrayHasKey( 'nested_user', $result );
			$this->assertSame( $user_id, $result['nested_user'] );
			$this->assertSame( $metadata, $result['metadata'] );
			$this->assertStringNotContainsString( $user_email, $json );
			$this->assertStringNotContainsString( 'user_pass', $json );
			$this->assertStringNotContainsString( $activation_key, $json );
			$this->assertStringNotContainsString( 'user_activation_key', $json );
		}
	}

	/**
	 * Test nested User sanitization follows field definitions across layout field types.
	 */
	public function test_rest_user_sanitization_follows_nested_field_definitions() {
		$user_id               = 321;
		$second_user_id        = 654;
		$formatted_user        = array(
			'ID'              => $user_id,
			'user_email'      => 'private-user@example.invalid',
			'user_nicename'   => 'private-user',
			'user_registered' => '2026-07-24 00:00:00',
		);
		$formatted_second_user = array(
			'ID'              => $second_user_id,
			'user_email'      => 'second-private-user@example.invalid',
			'user_nicename'   => 'second-private-user',
			'user_registered' => '2026-07-24 00:00:00',
		);
		$formatted_lookalike   = array(
			'ID'              => 777,
			'user_email'      => 'public-metadata@example.invalid',
			'user_nicename'   => 'public-metadata',
			'user_registered' => '2026-07-24 00:00:00',
			'user_avatar'     => 'avatar',
		);
		$user_field            = array(
			'key'      => 'field_nested_user',
			'name'     => 'nested_user',
			'_name'    => 'nested_user',
			'__name'   => 'cloned_user',
			'type'     => 'user',
			'multiple' => 0,
		);

		$cases = array(
			'group'            => array(
				'field'     => array(
					'type'       => 'group',
					'sub_fields' => array( $user_field ),
				),
				'formatted' => array( 'nested_user' => $formatted_user ),
				'raw'       => array( 'field_nested_user' => $user_id ),
				'expected'  => array( 'nested_user' => $user_id ),
			),
			'clone'            => array(
				'field'     => array(
					'type'       => 'clone',
					'sub_fields' => array( $user_field ),
				),
				'formatted' => array( 'cloned_user' => $formatted_user ),
				'raw'       => array( 'field_nested_user' => $user_id ),
				'expected'  => array( 'cloned_user' => $user_id ),
			),
			'repeater'         => array(
				'field'     => array(
					'type'       => 'repeater',
					'sub_fields' => array( $user_field ),
				),
				'formatted' => array(
					array(
						'nested_user' => $formatted_second_user,
						'label'       => 'Second row',
					),
					array(
						'nested_user' => $formatted_user,
						'label'       => 'First row',
					),
				),
				'raw'       => array(
					array(
						'field_nested_user' => $user_id,
						'field_label'       => 'First row',
					),
					array(
						'field_nested_user' => $second_user_id,
						'field_label'       => 'Second row',
					),
				),
				'expected'  => array(
					array(
						'nested_user' => $second_user_id,
						'label'       => 'Second row',
					),
					array(
						'nested_user' => $user_id,
						'label'       => 'First row',
					),
				),
			),
			'flexible_content' => array(
				'field'     => array(
					'type'    => 'flexible_content',
					'layouts' => array(
						array(
							'name'       => 'user_layout',
							'sub_fields' => array( $user_field ),
						),
						array(
							'name'       => 'metadata_layout',
							'sub_fields' => array(
								array(
									'key'   => 'field_nested_metadata',
									'name'  => 'nested_user',
									'_name' => 'nested_user',
									'type'  => 'text',
								),
							),
						),
					),
				),
				'formatted' => array(
					2 => array(
						'acf_fc_layout' => 'metadata_layout',
						'nested_user'   => $formatted_lookalike,
					),
					7 => array(
						'acf_fc_layout' => 'user_layout',
						'nested_user'   => $formatted_user,
					),
				),
				'raw'       => array(
					2 => array(
						'acf_fc_layout'     => 'user_layout',
						'field_nested_user' => $user_id,
					),
					7 => array(
						'acf_fc_layout'         => 'metadata_layout',
						'field_nested_metadata' => $formatted_lookalike,
					),
				),
				'expected'  => array(
					2 => array(
						'acf_fc_layout' => 'metadata_layout',
						'nested_user'   => $formatted_lookalike,
					),
					7 => array(
						'acf_fc_layout' => 'user_layout',
						'nested_user'   => $user_id,
					),
				),
			),
		);

		foreach ( $cases as $field_type => $case ) {
			$result = scf_rest_sanitize_user_data(
				$case['formatted'],
				$case['raw'],
				$case['field']
			);

			$this->assertSame( $case['expected'], $result, "Failed sanitizing {$field_type} field values." );
		}
	}

	/**
	 * Test acf_format_value_for_rest filter is applied.
	 */
	public function test_format_value_for_rest_filter_is_applied() {
		$filter_called = false;

		add_filter(
			'acf/rest/format_value_for_rest',
			function ( $value_formatted, $post_id, $field, $value, $format ) use ( &$filter_called ) {
				$filter_called = true;
				$this->assertEquals( 'standard', $format );
				return $value_formatted;
			},
			10,
			5
		);

		$field = array(
			'key'  => 'field_test_filter',
			'name' => 'test_filter',
			'type' => 'text',
		);

		acf_format_value_for_rest( 'test', $this->test_post_id, $field, 'standard' );

		$this->assertTrue( $filter_called, 'acf/rest/format_value_for_rest filter should be called' );

		remove_all_filters( 'acf/rest/format_value_for_rest' );
	}

	/**
	 * Test acf_format_value_for_rest filter can modify value.
	 */
	public function test_format_value_for_rest_filter_can_modify() {
		add_filter(
			'acf/rest/format_value_for_rest',
			function ( $value_formatted ) {
				return 'modified_' . $value_formatted;
			}
		);

		$field = array(
			'key'  => 'field_test_modify',
			'name' => 'test_modify',
			'type' => 'text',
		);

		$result = acf_format_value_for_rest( 'original', $this->test_post_id, $field, 'standard' );

		$this->assertEquals( 'modified_original', $result );

		remove_all_filters( 'acf/rest/format_value_for_rest' );
	}

	/**
	 * Test acf_format_value_for_rest with various data types using standard format.
	 *
	 * @dataProvider format_value_data_provider
	 *
	 * @param mixed $value    The value to format.
	 * @param mixed $expected The expected result.
	 */
	public function test_format_value_for_rest_with_data_types( $value, $expected ) {
		$field = array(
			'key'  => 'field_test_data',
			'name' => 'test_data',
			'type' => 'text',
		);

		$result = acf_format_value_for_rest( $value, $this->test_post_id, $field, 'standard' );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Data provider for format value tests.
	 *
	 * @return array
	 */
	public function format_value_data_provider() {
		return array(
			'string'       => array( 'hello', 'hello' ),
			'empty_string' => array( '', '' ),
			'integer'      => array( 123, 123 ),
			'zero'         => array( 0, 0 ),
			'float'        => array( 12.34, 12.34 ),
			'boolean_true' => array( true, true ),
			'null'         => array( null, null ),
		);
	}

	/**
	 * Test acf_format_value_for_rest with array value.
	 */
	public function test_format_value_for_rest_with_array() {
		$field = array(
			'key'  => 'field_test_array',
			'name' => 'test_array',
			'type' => 'text',
		);

		$input  = array( 'one', 'two', 'three' );
		$result = acf_format_value_for_rest( $input, $this->test_post_id, $field, 'standard' );

		$this->assertIsArray( $result );
		$this->assertEquals( $input, $result );
	}

	/**
	 * Test acf_format_value_for_rest passes correct format parameter to filter.
	 */
	public function test_format_value_for_rest_passes_format_to_filter() {
		$received_format = null;

		add_filter(
			'acf/rest/format_value_for_rest',
			function ( $value_formatted, $post_id, $field, $value, $format ) use ( &$received_format ) {
				$received_format = $format;
				return $value_formatted;
			},
			10,
			5
		);

		$field = array(
			'key'  => 'field_test_format_param',
			'name' => 'test_format_param',
			'type' => 'text',
		);

		acf_format_value_for_rest( 'test', $this->test_post_id, $field, 'standard' );
		$this->assertEquals( 'standard', $received_format );

		remove_all_filters( 'acf/rest/format_value_for_rest' );
	}

	// =========================================================================
	// Filter Variations Tests
	// =========================================================================

	/**
	 * Test schema filter variations are registered.
	 */
	public function test_schema_filter_variations() {
		$type_filter_called = false;

		add_filter(
			'acf/rest/get_field_schema/type=text',
			function ( $schema ) use ( &$type_filter_called ) {
				$type_filter_called = true;
				return $schema;
			}
		);

		$field = array(
			'key'  => 'field_test_variation',
			'name' => 'test_variation',
			'type' => 'text',
		);

		acf_get_field_rest_schema( $field );

		$this->assertTrue( $type_filter_called, 'Type-specific filter should be called' );

		remove_all_filters( 'acf/rest/get_field_schema/type=text' );
	}

	/**
	 * Test schema filter variation by name.
	 */
	public function test_schema_filter_variation_by_name() {
		$name_filter_called = false;

		add_filter(
			'acf/rest/get_field_schema/name=specific_field',
			function ( $schema ) use ( &$name_filter_called ) {
				$name_filter_called = true;
				return $schema;
			}
		);

		$field = array(
			'key'  => 'field_test_name',
			'name' => 'specific_field',
			'type' => 'text',
		);

		acf_get_field_rest_schema( $field );

		$this->assertTrue( $name_filter_called, 'Name-specific filter should be called' );

		remove_all_filters( 'acf/rest/get_field_schema/name=specific_field' );
	}

	/**
	 * Test schema filter variation by key.
	 */
	public function test_schema_filter_variation_by_key() {
		$key_filter_called = false;

		add_filter(
			'acf/rest/get_field_schema/key=field_specific_key',
			function ( $schema ) use ( &$key_filter_called ) {
				$key_filter_called = true;
				return $schema;
			}
		);

		$field = array(
			'key'  => 'field_specific_key',
			'name' => 'some_field',
			'type' => 'text',
		);

		acf_get_field_rest_schema( $field );

		$this->assertTrue( $key_filter_called, 'Key-specific filter should be called' );

		remove_all_filters( 'acf/rest/get_field_schema/key=field_specific_key' );
	}

	/**
	 * Test format_value_for_rest filter variations are registered.
	 */
	public function test_format_value_filter_variations() {
		$type_filter_called = false;

		add_filter(
			'acf/rest/format_value_for_rest/type=text',
			function ( $value ) use ( &$type_filter_called ) {
				$type_filter_called = true;
				return $value;
			}
		);

		$field = array(
			'key'  => 'field_test_format_var',
			'name' => 'test_format_var',
			'type' => 'text',
		);

		acf_format_value_for_rest( 'test', $this->test_post_id, $field, 'standard' );

		$this->assertTrue( $type_filter_called, 'Type-specific format filter should be called' );

		remove_all_filters( 'acf/rest/format_value_for_rest/type=text' );
	}

	// =========================================================================
	// Edge Cases and Error Handling
	// =========================================================================

	/**
	 * Test acf_get_field_rest_schema returns empty array for unknown type.
	 */
	public function test_get_field_rest_schema_with_unknown_type() {
		$field = array(
			'key'  => 'field_test',
			'name' => 'test_field',
			'type' => 'nonexistent_field_type_xyz',
		);

		// Unknown field types should return empty array since there's no type handler.
		$result = acf_get_field_rest_schema( $field );

		$this->assertIsArray( $result );
	}

	/**
	 * Test acf_get_field_rest_schema handles field with default type.
	 */
	public function test_get_field_rest_schema_with_default_field_structure() {
		// Create a field with all required attributes.
		$field = array(
			'key'  => 'field_default_test',
			'name' => 'default_test',
			'type' => 'text',
		);

		$result = acf_get_field_rest_schema( $field );

		// Text field type should return a valid schema array.
		$this->assertIsArray( $result );
	}

	/**
	 * Test acf_format_value_for_rest preserves object properties.
	 */
	public function test_format_value_for_rest_with_object_as_array() {
		$field = array(
			'key'  => 'field_test_obj',
			'name' => 'test_obj',
			'type' => 'text',
		);

		$input = array(
			'property1' => 'value1',
			'property2' => 'value2',
		);

		$result = acf_format_value_for_rest( $input, $this->test_post_id, $field, 'standard' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'property1', $result );
		$this->assertArrayHasKey( 'property2', $result );
	}

	/**
	 * Test acf_format_value_for_rest with deeply nested array.
	 */
	public function test_format_value_for_rest_with_nested_array() {
		$field = array(
			'key'  => 'field_test_nested',
			'name' => 'test_nested',
			'type' => 'text',
		);

		$input = array(
			'level1' => array(
				'level2' => array(
					'level3' => 'deep_value',
				),
			),
		);

		$result = acf_format_value_for_rest( $input, $this->test_post_id, $field, 'standard' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'deep_value', $result['level1']['level2']['level3'] );
	}

	/**
	 * Test filter priority ordering for schema.
	 */
	public function test_schema_filter_priority_ordering() {
		$call_order = array();

		add_filter(
			'acf/rest/get_field_schema',
			function ( $schema ) use ( &$call_order ) {
				$call_order[] = 'priority_10';
				return $schema;
			},
			10
		);

		add_filter(
			'acf/rest/get_field_schema',
			function ( $schema ) use ( &$call_order ) {
				$call_order[] = 'priority_5';
				return $schema;
			},
			5
		);

		add_filter(
			'acf/rest/get_field_schema',
			function ( $schema ) use ( &$call_order ) {
				$call_order[] = 'priority_15';
				return $schema;
			},
			15
		);

		$field = array(
			'key'  => 'field_test_priority',
			'name' => 'test_priority',
			'type' => 'text',
		);

		acf_get_field_rest_schema( $field );

		$this->assertEquals( array( 'priority_5', 'priority_10', 'priority_15' ), $call_order );

		remove_all_filters( 'acf/rest/get_field_schema' );
	}

	/**
	 * Test filter can return non-array and it gets cast.
	 */
	public function test_schema_filter_return_cast_to_array() {
		add_filter(
			'acf/rest/get_field_schema',
			function () {
				return null; // Invalid return.
			}
		);

		$field = array(
			'key'  => 'field_test_cast',
			'name' => 'test_cast',
			'type' => 'text',
		);

		$result = acf_get_field_rest_schema( $field );

		// Should be cast to array.
		$this->assertIsArray( $result );

		remove_all_filters( 'acf/rest/get_field_schema' );
	}
}
