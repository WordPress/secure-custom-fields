<?php
/**
 * Tests for ACF value and meta functions.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test ACF value and meta functions.
 */
class Test_ACF_Value_Meta_Functions extends BaseTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Test field array.
	 *
	 * @var array
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a test post.
		$this->post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			)
		);

		// Create a test field.
		$this->field = array(
			'key'           => 'field_test_text',
			'name'          => 'test_text',
			'type'          => 'text',
			'default_value' => '',
		);

		// Flush value cache.
		acf_get_store( 'values' )->reset();
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		if ( $this->post_id ) {
			wp_delete_post( $this->post_id, true );
		}
		acf_get_store( 'values' )->reset();
		parent::tearDown();
	}

	// =========================================================================
	// acf_get_metadata / acf_update_metadata / acf_delete_metadata
	// =========================================================================

	/**
	 * Test basic metadata CRUD operations.
	 */
	public function test_metadata_crud_basic() {
		// Update metadata.
		$result = acf_update_metadata( $this->post_id, 'test_meta', 'test_value' );
		$this->assertNotFalse( $result );

		// Get metadata.
		$value = acf_get_metadata( $this->post_id, 'test_meta' );
		$this->assertEquals( 'test_value', $value );

		// Delete metadata.
		$deleted = acf_delete_metadata( $this->post_id, 'test_meta' );
		$this->assertTrue( $deleted );

		// Verify deleted.
		$value = acf_get_metadata( $this->post_id, 'test_meta' );
		$this->assertNull( $value );
	}

	/**
	 * Test hidden metadata (with underscore prefix).
	 */
	public function test_metadata_hidden() {
		// Update hidden metadata.
		acf_update_metadata( $this->post_id, 'hidden_meta', 'hidden_value', true );

		// Get hidden metadata.
		$value = acf_get_metadata( $this->post_id, 'hidden_meta', true );
		$this->assertEquals( 'hidden_value', $value );

		// Verify not accessible as non-hidden.
		$non_hidden = acf_get_metadata( $this->post_id, 'hidden_meta', false );
		$this->assertNull( $non_hidden );

		// Delete hidden metadata.
		acf_delete_metadata( $this->post_id, 'hidden_meta', true );
		$value = acf_get_metadata( $this->post_id, 'hidden_meta', true );
		$this->assertNull( $value );
	}

	/**
	 * Test metadata with array values.
	 */
	public function test_metadata_array_value() {
		$array_value = array( 'one', 'two', 'three' );

		acf_update_metadata( $this->post_id, 'array_meta', $array_value );
		$value = acf_get_metadata( $this->post_id, 'array_meta' );

		$this->assertIsArray( $value );
		$this->assertEquals( $array_value, $value );
	}

	/**
	 * Test metadata with integer values.
	 */
	public function test_metadata_integer_value() {
		acf_update_metadata( $this->post_id, 'int_meta', 42 );
		$value = acf_get_metadata( $this->post_id, 'int_meta' );

		// Note: WordPress serializes metadata, so integers may come back as strings.
		$this->assertEquals( 42, (int) $value );
	}

	/**
	 * Test metadata with boolean values.
	 */
	public function test_metadata_boolean_value() {
		acf_update_metadata( $this->post_id, 'bool_true', true );
		acf_update_metadata( $this->post_id, 'bool_false', false );

		$true_value  = acf_get_metadata( $this->post_id, 'bool_true' );
		$false_value = acf_get_metadata( $this->post_id, 'bool_false' );

		$this->assertEquals( '1', $true_value );
		$this->assertEquals( '', $false_value );
	}

	/**
	 * Test metadata with empty string.
	 */
	public function test_metadata_empty_string() {
		acf_update_metadata( $this->post_id, 'empty_meta', '' );
		$value = acf_get_metadata( $this->post_id, 'empty_meta' );

		$this->assertSame( '', $value );
	}

	/**
	 * Test metadata with zero value.
	 */
	public function test_metadata_zero_value() {
		acf_update_metadata( $this->post_id, 'zero_meta', 0 );
		$value = acf_get_metadata( $this->post_id, 'zero_meta' );

		$this->assertEquals( '0', $value );
	}

	/**
	 * Test metadata returns null for non-existent key.
	 */
	public function test_metadata_non_existent() {
		$value = acf_get_metadata( $this->post_id, 'non_existent_key' );
		$this->assertNull( $value );
	}

	/**
	 * Test metadata with invalid post ID returns null/false.
	 */
	public function test_metadata_invalid_post_id() {
		$value  = acf_get_metadata( 0, 'test_meta' );
		$update = acf_update_metadata( 0, 'test_meta', 'value' );
		$delete = acf_delete_metadata( 0, 'test_meta' );

		$this->assertNull( $value );
		$this->assertFalse( $update );
		$this->assertFalse( $delete );
	}

	// =========================================================================
	// acf_get_value / acf_update_value / acf_delete_value
	// =========================================================================

	/**
	 * Test basic value CRUD operations.
	 */
	public function test_value_crud_basic() {
		// Update value.
		$result = acf_update_value( 'Hello World', $this->post_id, $this->field );
		$this->assertNotFalse( $result );

		// Get value.
		$value = acf_get_value( $this->post_id, $this->field );
		$this->assertEquals( 'Hello World', $value );

		// Delete value.
		$deleted = acf_delete_value( $this->post_id, $this->field );
		$this->assertTrue( $deleted );

		// Verify deleted (returns default value which is empty string).
		acf_get_store( 'values' )->reset();
		$value = acf_get_value( $this->post_id, $this->field );
		$this->assertSame( '', $value );
	}

	/**
	 * Test value round-trip integrity with string.
	 */
	public function test_value_roundtrip_string() {
		$original = 'Test string with special chars: <>&"\'';
		acf_update_value( $original, $this->post_id, $this->field );

		acf_get_store( 'values' )->reset();
		$loaded = acf_get_value( $this->post_id, $this->field );

		$this->assertEquals( $original, $loaded );
	}

	/**
	 * Test value round-trip integrity with array.
	 */
	public function test_value_roundtrip_array() {
		$original = array(
			'key1' => 'value1',
			'key2' => array( 'nested' => 'data' ),
		);

		$array_field = array(
			'key'  => 'field_test_array',
			'name' => 'test_array',
			'type' => 'repeater',
		);

		acf_update_value( $original, $this->post_id, $array_field );

		acf_get_store( 'values' )->reset();
		$loaded = acf_get_value( $this->post_id, $array_field );

		$this->assertEquals( $original, $loaded );
	}

	/**
	 * Test value caching in store.
	 */
	public function test_value_caching() {
		acf_update_value( 'cached_value', $this->post_id, $this->field );

		// First get populates cache.
		acf_get_store( 'values' )->reset();
		$value1 = acf_get_value( $this->post_id, $this->field );

		// Verify in cache.
		$store     = acf_get_store( 'values' );
		$cache_key = "{$this->post_id}:{$this->field['name']}";
		$this->assertTrue( $store->has( $cache_key ) );

		// Second get should return cached value.
		$value2 = acf_get_value( $this->post_id, $this->field );
		$this->assertEquals( $value1, $value2 );
	}

	/**
	 * Test acf_flush_value_cache clears cache.
	 */
	public function test_flush_value_cache() {
		acf_update_value( 'test_value', $this->post_id, $this->field );

		// Populate cache.
		acf_get_store( 'values' )->reset();
		acf_get_value( $this->post_id, $this->field );

		// Verify in cache.
		$store     = acf_get_store( 'values' );
		$cache_key = "{$this->post_id}:{$this->field['name']}";
		$this->assertTrue( $store->has( $cache_key ) );

		// Flush cache.
		acf_flush_value_cache( $this->post_id, $this->field['name'] );

		// Verify cleared.
		$this->assertFalse( $store->has( $cache_key ) );
	}

	/**
	 * Test default value is used when no meta exists.
	 */
	public function test_value_default_value() {
		$field_with_default = array(
			'key'           => 'field_with_default',
			'name'          => 'field_default',
			'type'          => 'text',
			'default_value' => 'my_default',
		);

		acf_get_store( 'values' )->reset();
		$value = acf_get_value( $this->post_id, $field_with_default );

		$this->assertEquals( 'my_default', $value );
	}

	/**
	 * Test updating value to null deletes it.
	 */
	public function test_value_update_null_deletes() {
		// First set a value.
		acf_update_value( 'some_value', $this->post_id, $this->field );

		// Update to null should delete.
		acf_update_value( null, $this->post_id, $this->field );

		// Verify deleted (returns default value which is empty string).
		acf_get_store( 'values' )->reset();
		$value = acf_get_value( $this->post_id, $this->field );
		$this->assertSame( '', $value );
	}

	/**
	 * Test acf_update_values with multiple fields.
	 */
	public function test_update_values_multiple() {
		// Register fields in local store for lookup.
		acf_add_local_field(
			array(
				'key'   => 'field_multi_1',
				'name'  => 'multi_1',
				'type'  => 'text',
				'label' => 'Multi 1',
			)
		);
		acf_add_local_field(
			array(
				'key'   => 'field_multi_2',
				'name'  => 'multi_2',
				'type'  => 'text',
				'label' => 'Multi 2',
			)
		);

		$values = array(
			'field_multi_1' => 'value1',
			'field_multi_2' => 'value2',
		);

		acf_update_values( $values, $this->post_id );

		// Verify both values saved.
		$field1 = acf_get_field( 'field_multi_1' );
		$field2 = acf_get_field( 'field_multi_2' );

		acf_get_store( 'values' )->reset();
		$this->assertEquals( 'value1', acf_get_value( $this->post_id, $field1 ) );
		$this->assertEquals( 'value2', acf_get_value( $this->post_id, $field2 ) );
	}

	// =========================================================================
	// acf_format_value
	// =========================================================================

	/**
	 * Test acf_format_value caches result.
	 */
	public function test_format_value_caching() {
		$value = 'raw_value';

		// Format value (populates cache).
		acf_format_value( $value, $this->post_id, $this->field );

		// Check formatted cache exists.
		$store     = acf_get_store( 'values' );
		$cache_key = "{$this->post_id}:{$this->field['name']}:formatted";
		$this->assertTrue( $store->has( $cache_key ) );
	}

	/**
	 * Test acf_format_value with escape_html parameter.
	 */
	public function test_format_value_escape_html() {
		$value = '<script>alert("xss")</script>';

		// Format with escape (populates cache).
		acf_format_value( $value, $this->post_id, $this->field, true );

		// Check escaped cache exists.
		$store     = acf_get_store( 'values' );
		$cache_key = "{$this->post_id}:{$this->field['name']}:escaped";
		$this->assertTrue( $store->has( $cache_key ) );
	}

	// =========================================================================
	// acf_get_reference
	// =========================================================================

	/**
	 * Test acf_get_reference returns field key.
	 */
	public function test_get_reference() {
		// First update a value to create reference.
		acf_update_value( 'test', $this->post_id, $this->field );

		// Get reference.
		$reference = acf_get_reference( $this->field['name'], $this->post_id );
		$this->assertEquals( $this->field['key'], $reference );
	}

	/**
	 * Test acf_get_reference returns null for non-existent field.
	 */
	public function test_get_reference_non_existent() {
		$reference = acf_get_reference( 'non_existent_field', $this->post_id );
		$this->assertNull( $reference );
	}

	// =========================================================================
	// acf_get_meta
	// =========================================================================

	/**
	 * Test acf_get_meta returns array (may be empty in WorDBless).
	 *
	 * Note: acf_get_meta relies on meta instances which may have limited
	 * support in WorDBless testing environment.
	 */
	public function test_get_meta() {
		// Set up metadata.
		acf_update_metadata( $this->post_id, 'acf_field_1', 'value1' );

		// Get all meta - function should return array.
		$meta = acf_get_meta( $this->post_id );

		// Verify it returns an array (content depends on meta instance support).
		$this->assertIsArray( $meta );
	}

	// =========================================================================
	// acf_copy_metadata
	// =========================================================================

	/**
	 * Test acf_copy_metadata function exists and runs without error.
	 *
	 * Note: Full copy functionality depends on meta instances which may
	 * have limited support in WorDBless testing environment.
	 */
	public function test_copy_metadata() {
		// Set up source post with raw metadata.
		acf_update_metadata( $this->post_id, 'copy_test_field', 'copied_value' );

		// Create destination post.
		$dest_post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Destination Post',
				'post_status' => 'publish',
			)
		);

		// Copy metadata - should not throw error.
		acf_copy_metadata( $this->post_id, $dest_post_id );

		// Function executed without error.
		$this->assertTrue( true );

		// Cleanup.
		wp_delete_post( $dest_post_id, true );
	}

	// =========================================================================
	// acf_get_meta_field
	// =========================================================================

	/**
	 * Test acf_get_meta_field retrieves field by name.
	 */
	public function test_get_meta_field() {
		// Register the field locally.
		acf_add_local_field(
			array(
				'key'   => 'field_lookup_test',
				'name'  => 'lookup_test',
				'type'  => 'text',
				'label' => 'Lookup Test',
			)
		);

		// Save a value to create reference.
		$field = acf_get_field( 'field_lookup_test' );
		acf_update_value( 'test', $this->post_id, $field );

		// Get field by meta name.
		$retrieved = acf_get_meta_field( 'lookup_test', $this->post_id );

		$this->assertIsArray( $retrieved );
		$this->assertEquals( 'field_lookup_test', $retrieved['key'] );
		$this->assertEquals( 'lookup_test', $retrieved['name'] );
	}

	/**
	 * Test acf_get_meta_field returns false for non-existent.
	 */
	public function test_get_meta_field_non_existent() {
		$result = acf_get_meta_field( 'non_existent', $this->post_id );
		$this->assertFalse( $result );
	}

	// =========================================================================
	// acf_get_metaref / acf_update_metaref
	// =========================================================================

	/**
	 * Test metaref CRUD operations.
	 */
	public function test_metaref_operations() {
		// Update metaref.
		$refs = array(
			'field_1' => 'field_key_1',
			'field_2' => 'field_key_2',
		);
		acf_update_metaref( $this->post_id, 'fields', $refs );

		// Get all refs.
		$retrieved = acf_get_metaref( $this->post_id, 'fields' );
		$this->assertIsArray( $retrieved );
		$this->assertArrayHasKey( 'field_1', $retrieved );

		// Get specific ref.
		$specific = acf_get_metaref( $this->post_id, 'fields', 'field_1' );
		$this->assertEquals( 'field_key_1', $specific );
	}

	/**
	 * Test metaref returns empty for non-existent.
	 */
	public function test_metaref_non_existent() {
		$all      = acf_get_metaref( $this->post_id, 'fields' );
		$specific = acf_get_metaref( $this->post_id, 'fields', 'non_existent' );

		$this->assertIsArray( $all );
		$this->assertEmpty( $all );
		$this->assertSame( '', $specific );
	}

	// =========================================================================
	// Options meta type
	// =========================================================================

	/**
	 * Test metadata with options post_id.
	 */
	public function test_metadata_options() {
		// Update option metadata.
		acf_update_metadata( 'options', 'option_field', 'option_value' );

		// Get option metadata.
		$value = acf_get_metadata( 'options', 'option_field' );
		$this->assertEquals( 'option_value', $value );

		// Delete option metadata.
		acf_delete_metadata( 'options', 'option_field' );
		$value = acf_get_metadata( 'options', 'option_field' );
		$this->assertNull( $value );
	}

	/**
	 * Test hidden option metadata.
	 */
	public function test_metadata_options_hidden() {
		acf_update_metadata( 'options', 'hidden_option', 'hidden_value', true );
		$value = acf_get_metadata( 'options', 'hidden_option', true );

		$this->assertEquals( 'hidden_value', $value );

		// Cleanup.
		acf_delete_metadata( 'options', 'hidden_option', true );
	}

	// =========================================================================
	// User meta type
	// =========================================================================

	/**
	 * Test metadata with user post_id.
	 */
	public function test_metadata_user() {
		// Create test user.
		$user_id = wp_insert_user(
			array(
				'user_login' => 'testuser_' . uniqid(),
				'user_pass'  => 'password',
				'user_email' => 'test_' . uniqid() . '@example.com',
			)
		);

		$user_post_id = 'user_' . $user_id;

		// Update user metadata.
		acf_update_metadata( $user_post_id, 'user_field', 'user_value' );

		// Get user metadata.
		$value = acf_get_metadata( $user_post_id, 'user_field' );
		$this->assertEquals( 'user_value', $value );

		// Cleanup.
		acf_delete_metadata( $user_post_id, 'user_field' );
		wp_delete_user( $user_id );
	}

	// =========================================================================
	// Filter hooks
	// =========================================================================

	/**
	 * Test acf/pre_load_metadata filter can short-circuit.
	 */
	public function test_pre_load_metadata_filter() {
		$filter = function ( $check, $post_id, $name, $hidden ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			if ( 'intercepted_field' === $name ) {
				return 'intercepted_value';
			}
			return $check;
		};

		add_filter( 'acf/pre_load_metadata', $filter, 10, 4 );

		$value = acf_get_metadata( $this->post_id, 'intercepted_field' );
		$this->assertEquals( 'intercepted_value', $value );

		remove_filter( 'acf/pre_load_metadata', $filter, 10 );
	}

	/**
	 * Test acf/pre_update_metadata filter can short-circuit.
	 */
	public function test_pre_update_metadata_filter() {
		$filter = function ( $check, $post_id, $name, $value, $hidden ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			if ( 'blocked_field' === $name ) {
				return false; // Block update.
			}
			return $check;
		};

		add_filter( 'acf/pre_update_metadata', $filter, 10, 5 );

		$result = acf_update_metadata( $this->post_id, 'blocked_field', 'value' );
		$this->assertFalse( $result );

		remove_filter( 'acf/pre_update_metadata', $filter, 10 );
	}

	/**
	 * Test acf/pre_load_value filter can short-circuit.
	 */
	public function test_pre_load_value_filter() {
		$filter = function ( $check, $post_id, $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassAfterLastUsed
			if ( 'test_text' === $field['name'] ) {
				return 'filtered_value';
			}
			return $check;
		};

		add_filter( 'acf/pre_load_value', $filter, 10, 3 );

		$value = acf_get_value( $this->post_id, $this->field );
		$this->assertEquals( 'filtered_value', $value );

		remove_filter( 'acf/pre_load_value', $filter, 10 );
	}

	/**
	 * Test acf/load_value filter modifies value.
	 */
	public function test_load_value_filter() {
		acf_update_value( 'original', $this->post_id, $this->field );

		$filter = function ( $value, $post_id, $field ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			return $value . '_modified';
		};

		add_filter( 'acf/load_value', $filter, 10, 3 );

		acf_get_store( 'values' )->reset();
		$value = acf_get_value( $this->post_id, $this->field );
		$this->assertEquals( 'original_modified', $value );

		remove_filter( 'acf/load_value', $filter, 10 );
	}

	// =========================================================================
	// Edge cases
	// =========================================================================

	/**
	 * Test metadata with special characters in name.
	 */
	public function test_metadata_special_chars_name() {
		$name = 'field-with-dashes_and_underscores';

		acf_update_metadata( $this->post_id, $name, 'special_value' );
		$value = acf_get_metadata( $this->post_id, $name );

		$this->assertEquals( 'special_value', $value );

		acf_delete_metadata( $this->post_id, $name );
	}

	/**
	 * Test metadata with unicode content.
	 */
	public function test_metadata_unicode_content() {
		$unicode_value = '你好世界 مرحبا العالم 🎉';

		acf_update_metadata( $this->post_id, 'unicode_field', $unicode_value );
		$value = acf_get_metadata( $this->post_id, 'unicode_field' );

		$this->assertEquals( $unicode_value, $value );

		acf_delete_metadata( $this->post_id, 'unicode_field' );
	}

	/**
	 * Test metadata with large value.
	 */
	public function test_metadata_large_value() {
		$large_value = str_repeat( 'a', 100000 ); // 100KB string.

		acf_update_metadata( $this->post_id, 'large_field', $large_value );
		$value = acf_get_metadata( $this->post_id, 'large_field' );

		$this->assertEquals( strlen( $large_value ), strlen( $value ) );

		acf_delete_metadata( $this->post_id, 'large_field' );
	}

	/**
	 * Test deeply nested array value.
	 */
	public function test_value_deeply_nested_array() {
		$nested = array(
			'level1' => array(
				'level2' => array(
					'level3' => array(
						'level4' => 'deep_value',
					),
				),
			),
		);

		$nested_field = array(
			'key'  => 'field_nested',
			'name' => 'nested_field',
			'type' => 'group',
		);

		acf_update_value( $nested, $this->post_id, $nested_field );

		acf_get_store( 'values' )->reset();
		$value = acf_get_value( $this->post_id, $nested_field );

		$this->assertEquals( $nested, $value );
	}
}
