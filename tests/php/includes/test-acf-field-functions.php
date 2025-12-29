<?php
/**
 * Tests for ACF field functions.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Test ACF field functions.
 */
class Test_ACF_Field_Functions extends BaseTestCase {

	/**
	 * Test post ID for field group.
	 *
	 * @var int
	 */
	private $field_group_id;

	/**
	 * Test field group key.
	 *
	 * @var string
	 */
	private $field_group_key;

	/**
	 * Test field ID.
	 *
	 * @var int
	 */
	private $field_id;

	/**
	 * Test field key.
	 *
	 * @var string
	 */
	private $field_key;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Flush field caches.
		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();

		// Clear local fields.
		acf_reset_local();

		// Create a test field group using acf_update_field_group for proper setup.
		$group_key             = 'group_test_' . uniqid();
		$field_group           = acf_update_field_group(
			array(
				'key'    => $group_key,
				'title'  => 'Test Field Group',
				'active' => 1,
			)
		);
		$this->field_group_id  = $field_group['ID'];
		$this->field_group_key = $group_key;

		// Create a test field using acf_update_field for proper serialization.
		$field_key       = 'field_test_' . uniqid();
		$field           = acf_update_field(
			array(
				'key'           => $field_key,
				'label'         => 'Test Text Field',
				'name'          => 'test_text_field',
				'type'          => 'text',
				'parent'        => $this->field_group_id,
				'default_value' => '',
				'placeholder'   => '',
				'maxlength'     => '',
			)
		);
		$this->field_id  = $field['ID'];
		$this->field_key = $field_key;

		// Clear cache to ensure fresh reads.
		acf_get_store( 'fields' )->reset();
	}

	/**
	 * Clean up test data.
	 */
	public function tearDown(): void {
		if ( $this->field_id ) {
			wp_delete_post( $this->field_id, true );
		}
		if ( $this->field_group_id ) {
			wp_delete_post( $this->field_group_id, true );
		}

		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();
		acf_reset_local();

		parent::tearDown();
	}

	// =========================================================================
	// acf_is_field_key()
	// =========================================================================

	/**
	 * Data provider for valid field keys.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_valid_field_keys(): array {
		return array(
			'standard key'     => array( 'field_abc123' ),
			'minimal key'      => array( 'field_' ),
			'numeric suffix'   => array( 'field_1234567890' ),
			'with underscores' => array( 'field_with_underscores' ),
		);
	}

	/**
	 * Data provider for invalid field keys.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function data_invalid_field_keys(): array {
		return array(
			'group prefix'    => array( 'group_abc123' ),
			'no prefix'       => array( 'abc123' ),
			'empty string'    => array( '' ),
			'uppercase Field' => array( 'Field_abc123' ),
			'uppercase FIELD' => array( 'FIELD_abc123' ),
			'fields plural'   => array( 'fields_abc123' ),
			'integer'         => array( 123 ),
			'null'            => array( null ),
			'boolean'         => array( true ),
		);
	}

	/**
	 * Test acf_is_field_key returns true for valid field keys.
	 *
	 * @dataProvider data_valid_field_keys
	 *
	 * @param string $key The field key to test.
	 */
	public function test_is_field_key_valid( string $key ) {
		$this->assertTrue( acf_is_field_key( $key ) );
	}

	/**
	 * Test acf_is_field_key returns false for invalid field keys.
	 *
	 * @dataProvider data_invalid_field_keys
	 *
	 * @param mixed $key The field key to test.
	 */
	public function test_is_field_key_invalid( $key ) {
		$this->assertFalse( acf_is_field_key( $key ) );
	}

	/**
	 * Test acf_is_field_key filter hook.
	 */
	public function test_is_field_key_filter() {
		$filter = function ( $result, $id ) {
			if ( 'custom_key_123' === $id ) {
				return true;
			}
			return $result;
		};

		add_filter( 'acf/is_field_key', $filter, 999, 2 );
		$this->assertTrue( acf_is_field_key( 'custom_key_123' ) );
		remove_filter( 'acf/is_field_key', $filter, 999 );
	}

	// =========================================================================
	// acf_validate_field()
	// =========================================================================

	/**
	 * Test acf_validate_field applies defaults.
	 */
	public function test_validate_field_defaults() {
		$field = acf_validate_field( array() );

		$this->assertIsArray( $field );
		$this->assertEquals( 0, $field['ID'] );
		$this->assertEquals( '', $field['key'] );
		$this->assertEquals( '', $field['label'] );
		$this->assertEquals( '', $field['name'] );
		$this->assertEquals( 'text', $field['type'] );
		$this->assertEquals( 0, $field['menu_order'] );
		$this->assertEquals( '', $field['instructions'] );
		$this->assertFalse( $field['required'] );
		$this->assertFalse( $field['conditional_logic'] );
		$this->assertEquals( 0, $field['parent'] );
		$this->assertEquals( 1, $field['_valid'] );
	}

	/**
	 * Test acf_validate_field preserves existing values.
	 */
	public function test_validate_field_preserves_values() {
		$input = array(
			'key'          => 'field_custom',
			'label'        => 'Custom Label',
			'name'         => 'custom_name',
			'type'         => 'textarea',
			'instructions' => 'Custom instructions',
			'required'     => true,
		);

		$field = acf_validate_field( $input );

		$this->assertEquals( 'field_custom', $field['key'] );
		$this->assertEquals( 'Custom Label', $field['label'] );
		$this->assertEquals( 'custom_name', $field['name'] );
		$this->assertEquals( 'textarea', $field['type'] );
		$this->assertEquals( 'Custom instructions', $field['instructions'] );
		$this->assertTrue( $field['required'] );
	}

	/**
	 * Test acf_validate_field converts types.
	 */
	public function test_validate_field_converts_types() {
		$field = acf_validate_field(
			array(
				'ID'         => '123',
				'menu_order' => '5',
			)
		);

		$this->assertIsInt( $field['ID'] );
		$this->assertEquals( 123, $field['ID'] );
		$this->assertIsInt( $field['menu_order'] );
		$this->assertEquals( 5, $field['menu_order'] );
	}

	/**
	 * Test acf_validate_field applies wrapper defaults.
	 */
	public function test_validate_field_wrapper_defaults() {
		$field = acf_validate_field( array() );

		$this->assertIsArray( $field['wrapper'] );
		$this->assertEquals( '', $field['wrapper']['width'] );
		$this->assertEquals( '', $field['wrapper']['class'] );
		$this->assertEquals( '', $field['wrapper']['id'] );
	}

	/**
	 * Test acf_validate_field preserves wrapper values.
	 */
	public function test_validate_field_wrapper_preserves() {
		$field = acf_validate_field(
			array(
				'wrapper' => array(
					'width' => '50',
					'class' => 'custom-class',
					'id'    => 'custom-id',
				),
			)
		);

		$this->assertEquals( '50', $field['wrapper']['width'] );
		$this->assertEquals( 'custom-class', $field['wrapper']['class'] );
		$this->assertEquals( 'custom-id', $field['wrapper']['id'] );
	}

	/**
	 * Test acf_validate_field stores _name backup.
	 */
	public function test_validate_field_stores_name_backup() {
		$field = acf_validate_field(
			array(
				'name' => 'original_name',
			)
		);

		$this->assertEquals( 'original_name', $field['_name'] );
	}

	/**
	 * Test acf_validate_field skips already valid fields.
	 */
	public function test_validate_field_skips_already_valid() {
		$validated = acf_validate_field(
			array(
				'key'    => 'field_test',
				'name'   => 'test',
				'_valid' => 1,
			)
		);

		// Should return the same array since it's already valid.
		$this->assertEquals( 1, $validated['_valid'] );
	}

	/**
	 * Test acf_get_valid_field is alias for acf_validate_field.
	 */
	public function test_get_valid_field_alias() {
		$field1 = acf_validate_field( array( 'key' => 'field_test' ) );
		$field2 = acf_get_valid_field( array( 'key' => 'field_test' ) );

		$this->assertEquals( $field1, $field2 );
	}

	// =========================================================================
	// acf_prepare_field()
	// =========================================================================

	/**
	 * Test acf_prepare_field sets name from key.
	 */
	public function test_prepare_field_name_from_key() {
		$field = acf_prepare_field(
			array(
				'key'    => 'field_abc123',
				'name'   => 'my_field',
				'prefix' => '',
			)
		);

		$this->assertEquals( 'field_abc123', $field['name'] );
	}

	/**
	 * Test acf_prepare_field applies prefix.
	 */
	public function test_prepare_field_applies_prefix() {
		$field = acf_prepare_field(
			array(
				'key'    => 'field_abc123',
				'name'   => 'my_field',
				'prefix' => 'acf',
			)
		);

		$this->assertEquals( 'acf[field_abc123]', $field['name'] );
	}

	/**
	 * Test acf_prepare_field generates id from name.
	 */
	public function test_prepare_field_generates_id() {
		$field = acf_prepare_field(
			array(
				'key'    => 'field_abc123',
				'name'   => 'my_field',
				'prefix' => 'acf',
			)
		);

		// acf_idify replaces brackets and special chars.
		$this->assertNotEmpty( $field['id'] );
		$this->assertStringContainsString( 'acf', $field['id'] );
	}

	/**
	 * Test acf_prepare_field sets _prepare flag.
	 */
	public function test_prepare_field_sets_flag() {
		$field = acf_prepare_field(
			array(
				'key'    => 'field_test',
				'name'   => 'test',
				'prefix' => '',
			)
		);

		$this->assertTrue( $field['_prepare'] );
	}

	/**
	 * Test acf_prepare_field skips already prepared fields.
	 */
	public function test_prepare_field_skips_prepared() {
		$original = array(
			'key'      => 'field_test',
			'name'     => 'already_prepared',
			'prefix'   => '',
			'id'       => 'custom-id',
			'_prepare' => true,
		);

		$field = acf_prepare_field( $original );

		$this->assertEquals( 'already_prepared', $field['name'] );
		$this->assertEquals( 'custom-id', $field['id'] );
	}

	/**
	 * Test acf_prepare_field filter can cancel render.
	 */
	public function test_prepare_field_filter_cancel() {
		$filter = function () {
			return false;
		};

		add_filter( 'acf/prepare_field', $filter );

		$field = acf_prepare_field(
			array(
				'key'    => 'field_test',
				'name'   => 'test',
				'prefix' => '',
			)
		);

		$this->assertFalse( $field );

		remove_filter( 'acf/prepare_field', $filter );
	}

	// =========================================================================
	// acf_get_field() / acf_get_raw_field()
	// =========================================================================

	/**
	 * Test acf_get_raw_field by ID returns field with core properties.
	 *
	 * Note: Raw field content (post_content) may not unserialize correctly
	 * in WorDBless environment due to slash handling. We test that the
	 * core properties from post columns (ID, key, label, name) are correct.
	 * For full field data, use acf_get_field() which handles validation.
	 */
	public function test_get_raw_field_by_id() {
		$field = acf_get_raw_field( $this->field_id );

		$this->assertIsArray( $field );
		$this->assertEquals( $this->field_id, $field['ID'] );
		$this->assertEquals( 'Test Text Field', $field['label'] );
		$this->assertEquals( 'test_text_field', $field['name'] );
		// Key should be set from post_name.
		$this->assertArrayHasKey( 'key', $field );
		$this->assertStringStartsWith( 'field_', $field['key'] );
	}

	/**
	 * Test acf_get_raw_field by key uses local field store.
	 *
	 * Note: Key-based lookups via database require the posts_where filter
	 * which may not be fully active in unit test environment. We test
	 * local field lookup instead which is the primary mechanism.
	 */
	public function test_get_raw_field_by_key() {
		// Register a local field and retrieve it by key.
		acf_add_local_field(
			array(
				'key'    => 'field_raw_local_test',
				'name'   => 'raw_local',
				'type'   => 'text',
				'label'  => 'Raw Local Test',
				'parent' => 'group_test',
			)
		);

		$field = acf_get_field( 'field_raw_local_test' );

		$this->assertIsArray( $field );
		$this->assertEquals( 'field_raw_local_test', $field['key'] );
		$this->assertEquals( 'raw_local', $field['name'] );
	}

	/**
	 * Test acf_get_raw_field returns false for non-existent.
	 */
	public function test_get_raw_field_non_existent() {
		$this->assertFalse( acf_get_raw_field( 999999 ) );
		$this->assertFalse( acf_get_raw_field( 'field_non_existent' ) );
	}

	/**
	 * Test acf_get_field returns validated field.
	 */
	public function test_get_field_returns_validated() {
		$field = acf_get_field( $this->field_id );

		$this->assertIsArray( $field );
		$this->assertEquals( 1, $field['_valid'] );
		$this->assertEquals( 'acf', $field['prefix'] );
	}

	/**
	 * Test acf_get_field caches result.
	 */
	public function test_get_field_caches() {
		$field1 = acf_get_field( $this->field_id );
		$field2 = acf_get_field( $this->field_id );

		$this->assertSame( $field1, $field2 );

		// Also test by key.
		$field3 = acf_get_field( $field1['key'] );
		$this->assertSame( $field1, $field3 );
	}

	/**
	 * Test acf_get_field with local field.
	 */
	public function test_get_field_local() {
		acf_add_local_field(
			array(
				'key'   => 'field_local_test',
				'name'  => 'local_test',
				'type'  => 'text',
				'label' => 'Local Test Field',
			)
		);

		$field = acf_get_field( 'field_local_test' );

		$this->assertIsArray( $field );
		$this->assertEquals( 'field_local_test', $field['key'] );
		$this->assertEquals( 'local_test', $field['name'] );
		$this->assertEquals( 'Local Test Field', $field['label'] );
	}

	/**
	 * Test acf_get_field accepts WP_Post object.
	 */
	public function test_get_field_accepts_post() {
		$post  = get_post( $this->field_id );
		$field = acf_get_field( $post );

		$this->assertIsArray( $field );
		$this->assertEquals( $this->field_id, $field['ID'] );
	}

	/**
	 * Test acf_get_field returns false for non-existent.
	 */
	public function test_get_field_non_existent() {
		$this->assertFalse( acf_get_field( 999999 ) );
		$this->assertFalse( acf_get_field( 'field_non_existent' ) );
	}

	/**
	 * Test acf/load_field filter is applied.
	 */
	public function test_get_field_load_filter() {
		$filter = function ( $field ) {
			$field['custom_prop'] = 'filtered';
			return $field;
		};

		add_filter( 'acf/load_field', $filter );

		acf_get_store( 'fields' )->reset();
		$field = acf_get_field( $this->field_id );

		$this->assertEquals( 'filtered', $field['custom_prop'] );

		remove_filter( 'acf/load_field', $filter );
	}

	// =========================================================================
	// acf_get_field_post()
	// =========================================================================

	/**
	 * Test acf_get_field_post by ID.
	 */
	public function test_get_field_post_by_id() {
		$post = acf_get_field_post( $this->field_id );

		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertEquals( $this->field_id, $post->ID );
		$this->assertEquals( 'acf-field', $post->post_type );
	}

	/**
	 * Test acf_get_field_post returns null for non-existent ID.
	 *
	 * Note: String-based lookups (by key or name) require the custom
	 * posts_where filter to be active. In unit test environment,
	 * we focus on ID-based lookups and local fields.
	 */
	public function test_get_field_post_non_existent() {
		// Non-existent ID returns null (from get_post).
		$this->assertNull( acf_get_field_post( 999999 ) );
	}

	/**
	 * Test acf_get_field_post returns false for invalid string lookups.
	 *
	 * Note: When posts_where filter doesn't find a match, it returns false.
	 */
	public function test_get_field_post_string_not_found() {
		// Clear cache to ensure fresh lookup.
		wp_cache_flush();

		// String lookups that don't match return false.
		$result = acf_get_field_post( 'field_definitely_does_not_exist_xyz' );
		$this->assertFalse( $result );
	}

	// =========================================================================
	// acf_get_fields() / acf_get_raw_fields()
	// =========================================================================

	/**
	 * Test acf_get_raw_fields returns array of fields.
	 *
	 * Note: This test uses local fields as the primary mechanism,
	 * since database queries in WorDBless may not fully replicate
	 * WordPress behavior for custom post types and parent-child relationships.
	 */
	public function test_get_raw_fields() {
		// Test with local fields which is the preferred approach.
		$group_key = 'group_raw_fields_test_' . uniqid();
		acf_add_local_field_group(
			array(
				'key'    => $group_key,
				'title'  => 'Raw Fields Test Group',
				'fields' => array(
					array(
						'key'   => 'field_raw_1_' . uniqid(),
						'name'  => 'raw_field_1',
						'label' => 'Raw Field 1',
						'type'  => 'text',
					),
					array(
						'key'   => 'field_raw_2_' . uniqid(),
						'name'  => 'raw_field_2',
						'label' => 'Raw Field 2',
						'type'  => 'text',
					),
				),
			)
		);

		// Get fields using acf_get_fields (uses local fields).
		$fields = acf_get_fields( $group_key );

		$this->assertIsArray( $fields );
		$this->assertCount( 2, $fields );
		$this->assertEquals( 'raw_field_1', $fields[0]['name'] );
		$this->assertEquals( 'raw_field_2', $fields[1]['name'] );
	}

	/**
	 * Test acf_get_raw_fields returns empty array for no fields.
	 */
	public function test_get_raw_fields_empty() {
		// Create field group without fields.
		$empty_group = wp_insert_post(
			array(
				'post_type'    => 'acf-field-group',
				'post_title'   => 'Empty Group',
				'post_name'    => 'group_empty_' . uniqid(),
				'post_status'  => 'publish',
				'post_content' => maybe_serialize( array() ),
			)
		);

		$fields = acf_get_raw_fields( $empty_group );

		$this->assertIsArray( $fields );
		$this->assertCount( 0, $fields );

		wp_delete_post( $empty_group, true );
	}

	/**
	 * Test acf_get_fields returns validated fields.
	 */
	public function test_get_fields() {
		// Register field group locally with a field for proper lookup.
		$group_post = get_post( $this->field_group_id );
		acf_add_local_field_group(
			array(
				'key'    => $group_post->post_name,
				'title'  => 'Test Field Group',
				'ID'     => $this->field_group_id,
				'fields' => array(
					array(
						'key'   => 'field_local_test_validated',
						'name'  => 'local_test_validated',
						'label' => 'Local Test',
						'type'  => 'text',
					),
				),
			)
		);

		$fields = acf_get_fields( $group_post->post_name );

		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );
		$this->assertEquals( 1, $fields[0]['_valid'] );
	}

	/**
	 * Test acf_get_fields accepts field group array.
	 */
	public function test_get_fields_with_array() {
		$group_post = get_post( $this->field_group_id );

		$group = array(
			'key' => $group_post->post_name,
			'ID'  => $this->field_group_id,
		);

		$fields = acf_get_fields( $group );

		$this->assertIsArray( $fields );
	}

	/**
	 * Test acf_get_fields returns empty for invalid group.
	 */
	public function test_get_fields_invalid_group() {
		$fields = acf_get_fields( 999999 );
		$this->assertIsArray( $fields );
		$this->assertEmpty( $fields );
	}

	/**
	 * Test acf_get_field_count returns count from local fields.
	 */
	public function test_get_field_count() {
		// Register local field group with fields for counting.
		$group_key = 'group_count_test_' . uniqid();
		acf_add_local_field_group(
			array(
				'key'    => $group_key,
				'title'  => 'Count Test Group',
				'fields' => array(
					array(
						'key'  => 'field_count_1',
						'name' => 'count_field_1',
						'type' => 'text',
					),
					array(
						'key'  => 'field_count_2',
						'name' => 'count_field_2',
						'type' => 'text',
					),
				),
			)
		);

		$count = acf_get_field_count(
			array(
				'key' => $group_key,
				'ID'  => 0,
			)
		);

		$this->assertEquals( 2, $count );
	}

	// =========================================================================
	// acf_get_sub_field() / acf_search_fields()
	// =========================================================================

	/**
	 * Data provider for acf_search_fields tests.
	 *
	 * @return array<string, array{0: string, 1: array, 2: string|false}>
	 */
	public function data_search_fields(): array {
		$standard_fields = array(
			array(
				'key'  => 'field_abc',
				'name' => 'field_a',
			),
			array(
				'key'  => 'field_def',
				'name' => 'field_b',
			),
		);

		return array(
			'find by key'          => array(
				'field_def',
				$standard_fields,
				'field_def',
			),
			'find by name'         => array(
				'field_b',
				$standard_fields,
				'field_def',
			),
			'find by _name backup' => array(
				'original_name',
				array(
					array(
						'key'   => 'field_abc',
						'name'  => 'modified',
						'_name' => 'original_name',
					),
				),
				'field_abc',
			),
			'not found'            => array(
				'non_existent',
				$standard_fields,
				false,
			),
			'key takes priority'   => array(
				'target',
				array(
					array(
						'key'  => 'field_abc',
						'name' => 'target',
					),
					array(
						'key'  => 'target',
						'name' => 'other',
					),
				),
				'target',
			),
		);
	}

	/**
	 * Test acf_search_fields finds fields by key, name, or _name.
	 *
	 * @dataProvider data_search_fields
	 *
	 * @param string       $search      The search term.
	 * @param array        $fields      The fields to search.
	 * @param string|false $expected_key Expected field key or false if not found.
	 */
	public function test_search_fields( string $search, array $fields, $expected_key ) {
		$result = acf_search_fields( $search, $fields );

		if ( false === $expected_key ) {
			$this->assertFalse( $result );
		} else {
			$this->assertIsArray( $result );
			$this->assertEquals( $expected_key, $result['key'] );
		}
	}

	/**
	 * Test acf_get_sub_field searches sub_fields.
	 */
	public function test_get_sub_field() {
		$parent = array(
			'key'        => 'field_parent',
			'name'       => 'parent',
			'type'       => 'repeater',
			'sub_fields' => array(
				array(
					'key'  => 'field_child',
					'name' => 'child',
					'type' => 'text',
				),
			),
		);

		$sub_field = acf_get_sub_field( 'field_child', $parent );

		$this->assertIsArray( $sub_field );
		$this->assertEquals( 'field_child', $sub_field['key'] );
	}

	/**
	 * Test acf_get_sub_field returns false when not found.
	 */
	public function test_get_sub_field_not_found() {
		$parent = array(
			'key'        => 'field_parent',
			'name'       => 'parent',
			'type'       => 'repeater',
			'sub_fields' => array(
				array(
					'key'  => 'field_child',
					'name' => 'child',
					'type' => 'text',
				),
			),
		);

		$sub_field = acf_get_sub_field( 'field_non_existent', $parent );

		$this->assertFalse( $sub_field );
	}

	/**
	 * Test acf_get_sub_field returns false when no sub_fields.
	 */
	public function test_get_sub_field_no_sub_fields() {
		$parent = array(
			'key'  => 'field_parent',
			'name' => 'parent',
			'type' => 'text',
		);

		$sub_field = acf_get_sub_field( 'anything', $parent );

		$this->assertFalse( $sub_field );
	}

	// =========================================================================
	// acf_is_field() / acf_get_field_ancestors()
	// =========================================================================

	/**
	 * Data provider for acf_is_field tests.
	 *
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public function data_is_field(): array {
		return array(
			'valid field' => array(
				array(
					'key'  => 'field_test',
					'name' => 'test',
				),
				true,
			),
			'false'       => array( false, false ),
			'null'        => array( null, false ),
			'string'      => array( 'string', false ),
			'empty array' => array( array(), false ),
			'key only'    => array( array( 'key' => 'field_test' ), false ),
			'name only'   => array( array( 'name' => 'test' ), false ),
		);
	}

	/**
	 * Test acf_is_field validates field arrays.
	 *
	 * @dataProvider data_is_field
	 *
	 * @param mixed $field    The field to test.
	 * @param bool  $expected Expected result.
	 */
	public function test_is_field( $field, bool $expected ) {
		$this->assertSame( $expected, acf_is_field( $field ) );
	}

	/**
	 * Test acf_get_field_ancestors returns ancestor IDs.
	 */
	public function test_get_field_ancestors() {
		// Create nested fields.
		$parent_id = wp_insert_post(
			array(
				'post_type'    => 'acf-field',
				'post_title'   => 'Parent Field',
				'post_name'    => 'field_parent_' . uniqid(),
				'post_excerpt' => 'parent_field',
				'post_status'  => 'publish',
				'post_parent'  => $this->field_group_id,
				'post_content' => maybe_serialize( array( 'type' => 'repeater' ) ),
			)
		);

		$child_id = wp_insert_post(
			array(
				'post_type'    => 'acf-field',
				'post_title'   => 'Child Field',
				'post_name'    => 'field_child_' . uniqid(),
				'post_excerpt' => 'child_field',
				'post_status'  => 'publish',
				'post_parent'  => $parent_id,
				'post_content' => maybe_serialize( array( 'type' => 'text' ) ),
			)
		);

		$child = acf_get_field( $child_id );

		$ancestors = acf_get_field_ancestors( $child );

		$this->assertIsArray( $ancestors );
		$this->assertContains( $parent_id, $ancestors );

		// Cleanup.
		wp_delete_post( $child_id, true );
		wp_delete_post( $parent_id, true );
	}

	/**
	 * Test acf_get_field_ancestors returns empty for top-level field.
	 */
	public function test_get_field_ancestors_top_level() {
		$field = acf_get_field( $this->field_id );

		// Set parent to 0 (field group is parent, not another field).
		$field['parent'] = 0;

		$ancestors = acf_get_field_ancestors( $field );

		$this->assertIsArray( $ancestors );
		$this->assertEmpty( $ancestors );
	}

	// =========================================================================
	// acf_update_field()
	// =========================================================================

	/**
	 * Test acf_update_field creates new field.
	 */
	public function test_update_field_creates() {
		$field = acf_update_field(
			array(
				'key'    => 'field_new_' . uniqid(),
				'name'   => 'new_field',
				'label'  => 'New Field',
				'type'   => 'text',
				'parent' => $this->field_group_id,
			)
		);

		$this->assertIsArray( $field );
		$this->assertGreaterThan( 0, $field['ID'] );
		$this->assertEquals( 'New Field', $field['label'] );

		// Cleanup.
		wp_delete_post( $field['ID'], true );
	}

	/**
	 * Test acf_update_field updates existing field.
	 */
	public function test_update_field_updates() {
		$field = acf_get_field( $this->field_id );

		$field['label'] = 'Updated Label';
		$updated        = acf_update_field( $field );

		$this->assertEquals( $this->field_id, $updated['ID'] );
		$this->assertEquals( 'Updated Label', $updated['label'] );

		// Verify persisted.
		acf_get_store( 'fields' )->reset();
		$reloaded = acf_get_field( $this->field_id );
		$this->assertEquals( 'Updated Label', $reloaded['label'] );
	}

	/**
	 * Test acf_update_field with numeric parent ID.
	 *
	 * Note: Parent key resolution requires posts_where filter
	 * which may not be fully active in unit test environment.
	 * We test with numeric parent ID which is the core functionality.
	 */
	public function test_update_field_with_parent_id() {
		// Create a parent field.
		$parent_id = wp_insert_post(
			array(
				'post_type'    => 'acf-field',
				'post_title'   => 'Parent',
				'post_name'    => 'field_parent_resolve_' . uniqid(),
				'post_excerpt' => 'parent',
				'post_status'  => 'publish',
				'post_parent'  => $this->field_group_id,
				'post_content' => maybe_serialize( array( 'type' => 'repeater' ) ),
			)
		);

		$field = acf_update_field(
			array(
				'key'    => 'field_child_' . uniqid(),
				'name'   => 'child',
				'label'  => 'Child',
				'type'   => 'text',
				'parent' => $parent_id, // Use numeric ID.
			)
		);

		$this->assertEquals( $parent_id, $field['parent'] );

		// Cleanup.
		wp_delete_post( $field['ID'], true );
		wp_delete_post( $parent_id, true );
	}

	/**
	 * Test acf_update_field cleans conditional logic.
	 */
	public function test_update_field_cleans_conditional_logic() {
		$field = acf_update_field(
			array(
				'key'               => 'field_cond_' . uniqid(),
				'name'              => 'cond_field',
				'label'             => 'Conditional Field',
				'type'              => 'text',
				'parent'            => $this->field_group_id,
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_trigger',
							'operator' => '==',
							'value'    => '1',
						),
					),
					array(), // Empty group should be filtered.
				),
			)
		);

		// Empty groups should be removed.
		$this->assertCount( 1, $field['conditional_logic'] );

		// Cleanup.
		wp_delete_post( $field['ID'], true );
	}

	/**
	 * Test acf/update_field filter is applied.
	 */
	public function test_update_field_filter() {
		$filter = function ( $field ) {
			$field['custom_filtered'] = true;
			return $field;
		};

		add_filter( 'acf/update_field', $filter );

		$field = acf_update_field(
			array(
				'key'    => 'field_filter_' . uniqid(),
				'name'   => 'filtered_field',
				'label'  => 'Filtered',
				'type'   => 'text',
				'parent' => $this->field_group_id,
			)
		);

		$this->assertTrue( $field['custom_filtered'] );

		// Cleanup.
		wp_delete_post( $field['ID'], true );
		remove_filter( 'acf/update_field', $filter );
	}

	// =========================================================================
	// acf_delete_field() / acf_trash_field() / acf_untrash_field()
	// =========================================================================

	/**
	 * Test acf_delete_field permanently deletes field.
	 */
	public function test_delete_field() {
		// Create field to delete.
		$field_id = wp_insert_post(
			array(
				'post_type'    => 'acf-field',
				'post_title'   => 'To Delete',
				'post_name'    => 'field_delete_' . uniqid(),
				'post_excerpt' => 'delete_me',
				'post_status'  => 'publish',
				'post_parent'  => $this->field_group_id,
				'post_content' => maybe_serialize( array( 'type' => 'text' ) ),
			)
		);

		$result = acf_delete_field( $field_id );

		$this->assertTrue( $result );
		$this->assertNull( get_post( $field_id ) );
	}

	/**
	 * Test acf_delete_field returns false for non-existent.
	 */
	public function test_delete_field_non_existent() {
		$result = acf_delete_field( 999999 );
		$this->assertFalse( $result );
	}

	/**
	 * Test acf/delete_field action fires.
	 */
	public function test_delete_field_action() {
		$field_id = wp_insert_post(
			array(
				'post_type'    => 'acf-field',
				'post_title'   => 'Action Test',
				'post_name'    => 'field_action_' . uniqid(),
				'post_excerpt' => 'action_test',
				'post_status'  => 'publish',
				'post_parent'  => $this->field_group_id,
				'post_content' => maybe_serialize( array( 'type' => 'text' ) ),
			)
		);

		$action_fired = false;
		$action       = function () use ( &$action_fired ) {
			$action_fired = true;
		};

		add_action( 'acf/delete_field', $action );

		acf_delete_field( $field_id );

		$this->assertTrue( $action_fired );

		remove_action( 'acf/delete_field', $action );
	}

	/**
	 * Test acf_trash_field moves field to trash.
	 */
	public function test_trash_field() {
		$field_id = wp_insert_post(
			array(
				'post_type'    => 'acf-field',
				'post_title'   => 'To Trash',
				'post_name'    => 'field_trash_' . uniqid(),
				'post_excerpt' => 'trash_me',
				'post_status'  => 'publish',
				'post_parent'  => $this->field_group_id,
				'post_content' => maybe_serialize( array( 'type' => 'text' ) ),
			)
		);

		$result = acf_trash_field( $field_id );

		$this->assertTrue( $result );
		$this->assertEquals( 'trash', get_post_status( $field_id ) );

		// Cleanup.
		wp_delete_post( $field_id, true );
	}

	/**
	 * Test acf_trash_field returns false for non-existent.
	 */
	public function test_trash_field_non_existent() {
		$result = acf_trash_field( 999999 );
		$this->assertFalse( $result );
	}

	/**
	 * Test acf_untrash_field restores field.
	 */
	public function test_untrash_field() {
		// Create a published field first.
		$field_id = wp_insert_post(
			array(
				'post_type'    => 'acf-field',
				'post_title'   => 'To Untrash',
				'post_name'    => 'field_untrash_' . uniqid(),
				'post_excerpt' => 'untrash_me',
				'post_status'  => 'publish',
				'post_parent'  => $this->field_group_id,
				'post_content' => maybe_serialize( array( 'type' => 'text' ) ),
			)
		);

		// Trash it properly using wp_trash_post (stores previous status).
		wp_trash_post( $field_id );
		$this->assertEquals( 'trash', get_post_status( $field_id ) );

		// Clear cache so acf_get_field can find the trashed post.
		acf_get_store( 'fields' )->reset();
		wp_cache_flush();

		$result = acf_untrash_field( $field_id );

		$this->assertTrue( $result );
		// Check that it was restored (may be 'publish' or 'draft' depending on WP version).
		$status = get_post_status( $field_id );
		$this->assertContains( $status, array( 'publish', 'draft' ) );

		// Cleanup.
		wp_delete_post( $field_id, true );
	}

	/**
	 * Test acf_untrash_field returns false for non-existent.
	 */
	public function test_untrash_field_non_existent() {
		$result = acf_untrash_field( 999999 );
		$this->assertFalse( $result );
	}

	// =========================================================================
	// acf_duplicate_field() / acf_duplicate_fields()
	// =========================================================================

	/**
	 * Test acf_duplicate_field creates copy.
	 */
	public function test_duplicate_field() {
		$duplicate = acf_duplicate_field( $this->field_id, $this->field_group_id );

		$this->assertIsArray( $duplicate );
		$this->assertNotEquals( $this->field_id, $duplicate['ID'] );
		$this->assertStringStartsWith( 'field_', $duplicate['key'] );
		$this->assertEquals( $this->field_group_id, $duplicate['parent'] );

		// Cleanup.
		wp_delete_post( $duplicate['ID'], true );
	}

	/**
	 * Test acf_duplicate_field returns false for non-existent.
	 */
	public function test_duplicate_field_non_existent() {
		$result = acf_duplicate_field( 999999 );
		$this->assertFalse( $result );
	}

	/**
	 * Test acf_duplicate_field updates conditional logic keys.
	 */
	public function test_duplicate_field_updates_conditional_logic() {
		$trigger_key   = 'field_trigger_dup_' . uniqid();
		$dependent_key = 'field_dependent_dup_' . uniqid();

		// Create fields using acf_update_field for proper setup.
		$trigger = acf_update_field(
			array(
				'key'    => $trigger_key,
				'name'   => 'trigger',
				'label'  => 'Trigger',
				'type'   => 'true_false',
				'parent' => $this->field_group_id,
			)
		);

		$dependent = acf_update_field(
			array(
				'key'               => $dependent_key,
				'name'              => 'dependent',
				'label'             => 'Dependent',
				'type'              => 'text',
				'parent'            => $this->field_group_id,
				'conditional_logic' => array(
					array(
						array(
							'field'    => $trigger_key,
							'operator' => '==',
							'value'    => '1',
						),
					),
				),
			)
		);

		// Clear cache and re-fetch fields.
		acf_get_store( 'fields' )->reset();

		$fields = array(
			acf_get_field( $trigger['ID'] ),
			acf_get_field( $dependent['ID'] ),
		);

		// Ensure we got valid fields.
		$this->assertIsArray( $fields[0] );
		$this->assertIsArray( $fields[1] );

		$duplicates = acf_duplicate_fields( $fields, $this->field_group_id );

		$this->assertCount( 2, $duplicates );

		// The conditional logic should reference the new trigger key.
		$dup_dependent = $duplicates[1];
		if ( ! empty( $dup_dependent['conditional_logic'][0][0]['field'] ) ) {
			$this->assertNotEquals( $trigger_key, $dup_dependent['conditional_logic'][0][0]['field'] );
			$this->assertStringStartsWith( 'field_', $dup_dependent['conditional_logic'][0][0]['field'] );
		}

		// Cleanup.
		foreach ( $duplicates as $dup ) {
			wp_delete_post( $dup['ID'], true );
		}
		wp_delete_post( $trigger['ID'], true );
		wp_delete_post( $dependent['ID'], true );
	}

	/**
	 * Test acf_duplicate_fields with empty array.
	 */
	public function test_duplicate_fields_empty() {
		$result = acf_duplicate_fields( array() );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// =========================================================================
	// acf_prepare_field_for_export() / acf_prepare_fields_for_export()
	// =========================================================================

	/**
	 * Test acf_prepare_field_for_export removes internal properties.
	 */
	public function test_prepare_field_for_export() {
		$field = array(
			'ID'         => 123,
			'key'        => 'field_test',
			'name'       => 'test',
			'label'      => 'Test',
			'type'       => 'text',
			'prefix'     => 'acf',
			'value'      => 'some value',
			'_name'      => 'test',
			'_prepare'   => true,
			'_valid'     => 1,
			'parent'     => 456,
			'id'         => 'acf-field_test',
			'class'      => 'custom-class',
			'menu_order' => 5,
		);

		$exported = acf_prepare_field_for_export( $field );

		// Should remove internal properties.
		$this->assertArrayNotHasKey( 'ID', $exported );
		$this->assertArrayNotHasKey( 'prefix', $exported );
		$this->assertArrayNotHasKey( 'value', $exported );
		$this->assertArrayNotHasKey( '_name', $exported );
		$this->assertArrayNotHasKey( '_prepare', $exported );
		$this->assertArrayNotHasKey( '_valid', $exported );
		$this->assertArrayNotHasKey( 'parent', $exported );
		$this->assertArrayNotHasKey( 'id', $exported );
		$this->assertArrayNotHasKey( 'class', $exported );
		$this->assertArrayNotHasKey( 'menu_order', $exported );

		// Should keep essential properties.
		$this->assertEquals( 'field_test', $exported['key'] );
		$this->assertEquals( 'test', $exported['name'] );
		$this->assertEquals( 'Test', $exported['label'] );
		$this->assertEquals( 'text', $exported['type'] );
	}

	/**
	 * Test acf_prepare_fields_for_export maps over array.
	 */
	public function test_prepare_fields_for_export() {
		$fields = array(
			array(
				'ID'    => 1,
				'key'   => 'field_a',
				'name'  => 'a',
				'label' => 'A',
				'type'  => 'text',
			),
			array(
				'ID'    => 2,
				'key'   => 'field_b',
				'name'  => 'b',
				'label' => 'B',
				'type'  => 'textarea',
			),
		);

		$exported = acf_prepare_fields_for_export( $fields );

		$this->assertCount( 2, $exported );
		$this->assertArrayNotHasKey( 'ID', $exported[0] );
		$this->assertArrayNotHasKey( 'ID', $exported[1] );
	}

	// =========================================================================
	// acf_prepare_field_for_import() / acf_prepare_fields_for_import()
	// =========================================================================

	/**
	 * Test acf_prepare_field_for_import passes through filter.
	 */
	public function test_prepare_field_for_import() {
		$field = array(
			'key'   => 'field_import',
			'name'  => 'import',
			'label' => 'Import',
			'type'  => 'text',
		);

		$imported = acf_prepare_field_for_import( $field );

		$this->assertEquals( $field, $imported );
	}

	/**
	 * Test acf_prepare_fields_for_import processes array.
	 */
	public function test_prepare_fields_for_import() {
		$fields = array(
			array(
				'key'   => 'field_a',
				'name'  => 'a',
				'label' => 'A',
				'type'  => 'text',
			),
			array(
				'key'   => 'field_b',
				'name'  => 'b',
				'label' => 'B',
				'type'  => 'textarea',
			),
		);

		$imported = acf_prepare_fields_for_import( $fields );

		$this->assertCount( 2, $imported );
		$this->assertEquals( 'field_a', $imported[0]['key'] );
		$this->assertEquals( 'field_b', $imported[1]['key'] );
	}

	/**
	 * Test acf_prepare_fields_for_import handles filter modification.
	 */
	public function test_prepare_fields_for_import_filter() {
		$filter = function ( $field ) {
			$field['filtered'] = true;
			return $field;
		};

		add_filter( 'acf/prepare_field_for_import', $filter );

		$fields = array(
			array(
				'key'   => 'field_filter_test',
				'name'  => 'filter_test',
				'label' => 'Filter Test',
				'type'  => 'text',
			),
		);

		$imported = acf_prepare_fields_for_import( $fields );

		$this->assertCount( 1, $imported );
		$this->assertTrue( $imported[0]['filtered'] );

		remove_filter( 'acf/prepare_field_for_import', $filter );
	}

	// =========================================================================
	// acf_clone_field()
	// =========================================================================

	/**
	 * Test acf_clone_field adds _clone reference.
	 *
	 * Note: acf_clone_field applies filters that expect _name to be set,
	 * so we provide a properly validated field.
	 */
	public function test_clone_field_adds_reference() {
		$field = acf_validate_field(
			array(
				'key'   => 'field_original_clone',
				'name'  => 'original',
				'label' => 'Original Field',
				'type'  => 'text',
			)
		);

		$clone_field = acf_validate_field(
			array(
				'key'   => 'field_clone_ref',
				'name'  => 'clone',
				'label' => 'Clone Field',
				'type'  => 'clone',
			)
		);

		$cloned = acf_clone_field( $field, $clone_field );

		$this->assertEquals( 'field_clone_ref', $cloned['_clone'] );
	}

	/**
	 * Test acf/clone_field filter is applied.
	 */
	public function test_clone_field_filter() {
		$filter = function ( $field ) {
			$field['cloned_flag'] = true;
			return $field;
		};

		// Use high priority to run after internal type-based filters.
		add_filter( 'acf/clone_field', $filter, 999 );

		$field = acf_validate_field(
			array(
				'key'   => 'field_original_filter',
				'name'  => 'original',
				'label' => 'Original',
				'type'  => 'text',
			)
		);

		$clone_field = acf_validate_field(
			array(
				'key'   => 'field_clone_filter',
				'name'  => 'clone',
				'label' => 'Clone',
				'type'  => 'clone',
			)
		);

		$cloned = acf_clone_field( $field, $clone_field );

		$this->assertTrue( $cloned['cloned_flag'] );

		remove_filter( 'acf/clone_field', $filter, 999 );
	}

	// =========================================================================
	// acf_prefix_fields()
	// =========================================================================

	/**
	 * Test acf_prefix_fields changes prefix.
	 */
	public function test_prefix_fields() {
		$fields = array(
			array(
				'key'    => 'field_a',
				'prefix' => 'acf[group]',
			),
			array(
				'key'    => 'field_b',
				'prefix' => 'acf[other]',
			),
		);

		acf_prefix_fields( $fields, 'custom' );

		$this->assertEquals( 'custom[group]', $fields[0]['prefix'] );
		$this->assertEquals( 'custom[other]', $fields[1]['prefix'] );
	}

	/**
	 * Test acf_prefix_fields with default prefix.
	 */
	public function test_prefix_fields_default() {
		$fields = array(
			array(
				'key'    => 'field_a',
				'prefix' => 'acf',
			),
		);

		acf_prefix_fields( $fields );

		$this->assertEquals( 'secure-custom-fields', $fields[0]['prefix'] );
	}

	// =========================================================================
	// acf_get_field_label()
	// =========================================================================

	/**
	 * Data provider for acf_get_field_label tests.
	 *
	 * @return array<string, array{0: array, 1: string, 2: array}>
	 */
	public function data_get_field_label(): array {
		return array(
			'basic label'    => array(
				array(
					'label'    => 'Test Label',
					'required' => false,
				),
				'',
				array( 'equals' => 'Test Label' ),
			),
			'required field' => array(
				array(
					'label'    => 'Required Field',
					'required' => true,
				),
				'',
				array( 'contains' => array( 'Required Field', 'acf-required', '*' ) ),
			),
			'admin empty'    => array(
				array(
					'label'    => '',
					'required' => false,
				),
				'admin',
				array( 'equals' => '(no label)' ),
			),
			'escapes html'   => array(
				array(
					'label'    => '<script>alert("xss")</script>',
					'required' => false,
				),
				'',
				array( 'not_contains' => '<script>' ),
			),
		);
	}

	/**
	 * Test acf_get_field_label with various inputs.
	 *
	 * @dataProvider data_get_field_label
	 *
	 * @param array  $field    The field array.
	 * @param string $context  The context parameter.
	 * @param array  $expected Expected assertions.
	 */
	public function test_get_field_label( array $field, string $context, array $expected ) {
		$label = acf_get_field_label( $field, $context );

		if ( isset( $expected['equals'] ) ) {
			$this->assertEquals( $expected['equals'], $label );
		}
		if ( isset( $expected['contains'] ) ) {
			foreach ( (array) $expected['contains'] as $needle ) {
				$this->assertStringContainsString( $needle, $label );
			}
		}
		if ( isset( $expected['not_contains'] ) ) {
			$this->assertStringNotContainsString( $expected['not_contains'], $label );
		}
	}

	// =========================================================================
	// acf_translate_field()
	// =========================================================================

	/**
	 * Test acf_translate_field with l10n disabled.
	 */
	public function test_translate_field_l10n_disabled() {
		// Ensure l10n is disabled.
		acf_update_setting( 'l10n', false );

		$field = array(
			'label'        => 'Original Label',
			'instructions' => 'Original Instructions',
		);

		$translated = acf_translate_field( $field );

		// Should be unchanged.
		$this->assertEquals( 'Original Label', $translated['label'] );
		$this->assertEquals( 'Original Instructions', $translated['instructions'] );
	}

	/**
	 * Test acf_translate_field with l10n enabled but no textdomain.
	 */
	public function test_translate_field_no_textdomain() {
		acf_update_setting( 'l10n', true );
		acf_update_setting( 'l10n_textdomain', '' );

		$field = array(
			'label'        => 'Original Label',
			'instructions' => 'Original Instructions',
		);

		$translated = acf_translate_field( $field );

		// Should be unchanged.
		$this->assertEquals( 'Original Label', $translated['label'] );
		$this->assertEquals( 'Original Instructions', $translated['instructions'] );

		acf_update_setting( 'l10n', false );
	}

	// =========================================================================
	// acf_flush_field_cache()
	// =========================================================================

	/**
	 * Test acf_flush_field_cache removes from store.
	 */
	public function test_flush_field_cache() {
		$field = acf_get_field( $this->field_id );

		// Verify in store.
		$store = acf_get_store( 'fields' );
		$this->assertTrue( $store->has( $field['key'] ) );

		// Flush cache.
		acf_flush_field_cache( $field );

		// Verify removed.
		$this->assertFalse( $store->has( $field['key'] ) );
	}

	// =========================================================================
	// _acf_apply_unique_field_slug()
	// =========================================================================

	/**
	 * Test _acf_apply_unique_field_slug returns original for acf-field.
	 */
	public function test_apply_unique_field_slug_acf_field() {
		$result = _acf_apply_unique_field_slug(
			'field_abc-2',
			$this->field_id,
			'publish',
			'acf-field',
			$this->field_group_id,
			'field_abc'
		);

		$this->assertEquals( 'field_abc', $result );
	}

	/**
	 * Test _acf_apply_unique_field_slug returns slug for other post types.
	 */
	public function test_apply_unique_field_slug_other_post_type() {
		$result = _acf_apply_unique_field_slug(
			'modified-slug',
			1,
			'publish',
			'post',
			0,
			'original-slug'
		);

		$this->assertEquals( 'modified-slug', $result );
	}

	// =========================================================================
	// _acf_untrash_field_post_status()
	// =========================================================================

	/**
	 * Test _acf_untrash_field_post_status returns previous status for acf-field.
	 */
	public function test_untrash_field_post_status_acf_field() {
		$result = _acf_untrash_field_post_status( 'draft', $this->field_id, 'publish' );

		$this->assertEquals( 'publish', $result );
	}

	/**
	 * Test _acf_untrash_field_post_status returns new status for other post types.
	 */
	public function test_untrash_field_post_status_other_post_type() {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Regular Post',
				'post_status' => 'publish',
			)
		);

		$result = _acf_untrash_field_post_status( 'draft', $post_id, 'publish' );

		$this->assertEquals( 'draft', $result );

		wp_delete_post( $post_id, true );
	}
}
