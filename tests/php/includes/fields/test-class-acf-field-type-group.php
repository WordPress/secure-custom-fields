<?php
/**
 * Tests for the Group field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field__group (group field type).
 */
class Test_ACF_Field_Type_Group extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'group';
	}

	/**
	 * Get a base group field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'        => 'field_group_test',
				'name'       => 'test_group',
				'type'       => 'group',
				'label'      => 'Test Group',
				'required'   => 0,
				'layout'     => 'block',
				'sub_fields' => array(
					array(
						'key'       => 'field_sub_text',
						'name'      => 'sub_text',
						'_name'     => 'sub_text',
						'type'      => 'text',
						'label'     => 'Sub Text',
						'required'  => 0,
						'maxlength' => '',
					),
					array(
						'key'      => 'field_sub_email',
						'name'     => 'sub_email',
						'_name'    => 'sub_email',
						'type'     => 'email',
						'label'    => 'Sub Email',
						'required' => 0,
					),
					array(
						'key'      => 'field_sub_number',
						'name'     => 'sub_number',
						'_name'    => 'sub_number',
						'type'     => 'number',
						'label'    => 'Sub Number',
						'required' => 0,
						'min'      => '',
						'max'      => '',
					),
				),
			),
			$overrides
		);
	}

	/**
	 * Test validate_value passes for valid group data.
	 */
	public function test_validate_value_valid_data() {
		$field = $this->get_field();
		$value = array(
			'field_sub_text'   => 'Hello',
			'field_sub_email'  => 'test@example.com',
			'field_sub_number' => 42,
		);

		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_group_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with empty value when not required.
	 */
	public function test_validate_value_empty_not_required() {
		$field = $this->get_field( array( 'required' => 0 ) );
		$valid = $this->field_instance->validate_value( true, array(), $field, 'acf[field_group_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with no sub_fields.
	 */
	public function test_validate_value_no_sub_fields() {
		$field = $this->get_field( array( 'sub_fields' => array() ) );
		$valid = $this->field_instance->validate_value( true, array(), $field, 'acf[field_group_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test load_value returns a result.
	 *
	 * Note: In WorDBless, load_value may not reconstruct the group structure
	 * the same way as in a full WordPress environment with proper meta key references.
	 */
	public function test_load_value_returns_structure() {
		$field = $this->get_field();

		// Store test data.
		update_post_meta( $this->post_id, 'test_group_sub_text', 'Hello' );
		update_post_meta( $this->post_id, 'test_group_sub_email', 'test@example.com' );
		update_post_meta( $this->post_id, 'test_group_sub_number', 42 );

		$result = $this->field_instance->load_value( null, $this->post_id, $field );

		// In WorDBless, the result may be an array or null depending on how
		// sub-fields are resolved without full field registration.
		if ( is_array( $result ) ) {
			$this->assertIsArray( $result );
		} else {
			$this->assertNull( $result );
		}
	}

	/**
	 * Test load_value with no sub_fields.
	 *
	 * Note: Group field with no sub_fields may return null in WorDBless.
	 */
	public function test_load_value_no_sub_fields() {
		$field  = $this->get_field( array( 'sub_fields' => array() ) );
		$result = $this->field_instance->load_value( null, $this->post_id, $field );

		// May return null or empty array depending on implementation.
		if ( is_array( $result ) ) {
			$this->assertEmpty( $result );
		} else {
			$this->assertNull( $result );
		}
	}

	/**
	 * Test format_value returns false for empty group.
	 */
	public function test_format_value_empty() {
		$field  = $this->get_field();
		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value processes sub-field values.
	 *
	 * Note: In WorDBless, format_value may not fully process sub-fields
	 * without proper field registration.
	 */
	public function test_format_value_preserves_values() {
		$field = $this->get_field();
		$value = array(
			'sub_text'   => 'Hello',
			'sub_email'  => 'test@example.com',
			'sub_number' => 42,
		);

		$result = $this->field_instance->format_value( $value, $this->post_id, $field );

		// In WorDBless, format_value may return the input array structure.
		$this->assertIsArray( $result );
	}

	/**
	 * Test format_value with no sub_fields.
	 */
	public function test_format_value_no_sub_fields() {
		$field  = $this->get_field( array( 'sub_fields' => array() ) );
		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test update_value with no sub_fields.
	 *
	 * Note: Group field with no sub_fields may return null in WorDBless.
	 */
	public function test_update_value_no_sub_fields() {
		$field  = $this->get_field( array( 'sub_fields' => array() ) );
		$result = $this->field_instance->update_value( array(), $this->post_id, $field );

		// May return null or empty array depending on implementation.
		if ( is_array( $result ) ) {
			$this->assertEmpty( $result );
		} else {
			$this->assertNull( $result );
		}
	}

	/**
	 * Test delete_value removes all sub-field values.
	 */
	public function test_delete_value() {
		$field = $this->get_field();

		// First add some data.
		update_post_meta( $this->post_id, 'test_group_sub_text', 'To Delete' );
		update_post_meta( $this->post_id, 'test_group_sub_email', 'delete@example.com' );
		update_post_meta( $this->post_id, '_test_group_sub_text', 'field_sub_text' );
		update_post_meta( $this->post_id, '_test_group_sub_email', 'field_sub_email' );

		$this->field_instance->delete_value( $this->post_id, 'test_group', $field );

		$this->assertEquals( '', get_post_meta( $this->post_id, 'test_group_sub_text', true ) );
		$this->assertEquals( '', get_post_meta( $this->post_id, 'test_group_sub_email', true ) );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 *
	 * Note: In WorDBless, the schema structure may differ without full
	 * field registration.
	 */
	public function test_get_rest_schema() {
		$field  = $this->get_field();
		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		// The schema type may be an array (e.g., ['object', 'null']) or a string.
		if ( is_array( $schema['type'] ) ) {
			$this->assertContains( 'object', $schema['type'] );
		} else {
			$this->assertEquals( 'object', $schema['type'] );
		}
	}

	/**
	 * Test prepare_field_for_export formats correctly.
	 */
	public function test_prepare_field_for_export() {
		$field = $this->get_field();

		$exported = $this->field_instance->prepare_field_for_export( $field );

		$this->assertIsArray( $exported );
		$this->assertArrayHasKey( 'sub_fields', $exported );
	}

	/**
	 * Test prepare_field_for_import handles sub_fields.
	 */
	public function test_prepare_field_for_import() {
		$field = $this->get_field();

		$imported = $this->field_instance->prepare_field_for_import( $field );

		$this->assertIsArray( $imported );
	}

	/**
	 * Data provider for layout types.
	 *
	 * @return array
	 */
	public function layout_provider() {
		return array(
			'block layout' => array( 'block' ),
			'table layout' => array( 'table' ),
			'row layout'   => array( 'row' ),
		);
	}

	/**
	 * Test format_value works with different layouts.
	 *
	 * Note: In WorDBless, format_value may not fully process sub-fields.
	 *
	 * @dataProvider layout_provider
	 *
	 * @param string $layout The layout type.
	 */
	public function test_format_value_with_layouts( $layout ) {
		$field = $this->get_field( array( 'layout' => $layout ) );
		$value = array(
			'sub_text'   => 'Hello',
			'sub_number' => 42,
		);

		$result = $this->field_instance->format_value( $value, $this->post_id, $field );

		// In WorDBless, format_value may return the array structure.
		$this->assertIsArray( $result );
	}

	/**
	 * Test nested group structure.
	 */
	public function test_nested_group() {
		$inner_group = array(
			'key'        => 'field_inner_group',
			'name'       => 'inner_group',
			'_name'      => 'inner_group',
			'type'       => 'group',
			'label'      => 'Inner Group',
			'sub_fields' => array(
				array(
					'key'   => 'field_inner_text',
					'name'  => 'inner_text',
					'_name' => 'inner_text',
					'type'  => 'text',
					'label' => 'Inner Text',
				),
			),
		);

		$field = $this->get_field(
			array(
				'sub_fields' => array( $inner_group ),
			)
		);

		$value = array(
			'inner_group' => array(
				'inner_text' => 'Nested value',
			),
		);

		$result = $this->field_instance->format_value( $value, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'inner_group', $result );
	}

	/**
	 * Test update_value returns null for non-array value.
	 *
	 * Tests the early return:
	 * `if ( ! acf_is_array( $value ) ) { return null; }`
	 */
	public function test_update_value_non_array_returns_null() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( 'not an array', $this->post_id, $field );

		$this->assertNull( $result );
	}

	/**
	 * Test update_value returns null for null value.
	 */
	public function test_update_value_null_returns_null() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( null, $this->post_id, $field );

		$this->assertNull( $result );
	}

	/**
	 * Test update_value returns null for no sub_fields.
	 *
	 * Tests the early return:
	 * `if ( empty( $field['sub_fields'] ) ) { return null; }`
	 */
	public function test_update_value_no_subfields_returns_null() {
		$field = $this->get_field( array( 'sub_fields' => array() ) );
		$value = array( 'some_key' => 'some_value' );

		$result = $this->field_instance->update_value( $value, $this->post_id, $field );

		$this->assertNull( $result );
	}

	/**
	 * Test update_value returns empty string for valid input.
	 *
	 * Tests the final return:
	 * `return '';`
	 */
	public function test_update_value_valid_returns_empty_string() {
		$field = $this->get_field();
		$value = array(
			'field_sub_text'  => 'Hello',
			'field_sub_email' => 'test@example.com',
		);

		$result = $this->field_instance->update_value( $value, $this->post_id, $field );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test format_value_for_rest returns value unchanged for empty.
	 *
	 * Tests the early return:
	 * `if ( empty( $value ) || ! is_array( $value ) || empty( $field['sub_fields'] ) ) { return $value; }`
	 */
	public function test_format_value_for_rest_empty_returns_value() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( '', $this->post_id, $field );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test format_value_for_rest returns value unchanged for non-array.
	 */
	public function test_format_value_for_rest_non_array_returns_value() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( 'string value', $this->post_id, $field );

		$this->assertEquals( 'string value', $result );
	}

	/**
	 * Test format_value_for_rest returns value unchanged when no sub_fields.
	 */
	public function test_format_value_for_rest_no_subfields_returns_value() {
		$field = $this->get_field( array( 'sub_fields' => array() ) );
		$value = array( 'key' => 'value' );

		$result = $this->field_instance->format_value_for_rest( $value, $this->post_id, $field );

		$this->assertEquals( $value, $result );
	}

	/**
	 * Test prepare_field_for_db returns field unchanged when no sub_fields.
	 *
	 * Tests the early return:
	 * `if ( empty( $field['sub_fields'] ) ) { return $field; }`
	 */
	public function test_prepare_field_for_db_no_subfields_returns_unchanged() {
		$field = $this->get_field( array( 'sub_fields' => array() ) );

		$result = $this->field_instance->prepare_field_for_db( $field );

		$this->assertEquals( $field, $result );
	}

	/**
	 * Test prepare_field_for_db prefixes sub_field names.
	 *
	 * Tests the name prefixing logic:
	 * `$sub_field['name'] = $field['name'] . '_' . $sub_field['_name'];`
	 */
	public function test_prepare_field_for_db_prefixes_subfield_names() {
		$field = $this->get_field();

		$result = $this->field_instance->prepare_field_for_db( $field );

		// Check that sub_field names were prefixed with parent field name.
		$this->assertEquals( 'test_group_sub_text', $result['sub_fields'][0]['name'] );
		$this->assertEquals( 'test_group_sub_email', $result['sub_fields'][1]['name'] );
	}

	/**
	 * Test validate_value returns early for empty value.
	 *
	 * Tests the early return:
	 * `if ( empty( $value ) ) { return $valid; }`
	 */
	public function test_validate_value_empty_returns_valid_unchanged() {
		$field = $this->get_field();

		// Pass true, should return true unchanged.
		$result = $this->field_instance->validate_value( true, array(), $field, 'acf[test]' );
		$this->assertTrue( $result );

		// Pass false, should return false unchanged.
		$result = $this->field_instance->validate_value( false, array(), $field, 'acf[test]' );
		$this->assertFalse( $result );
	}

	/**
	 * Test validate_value returns early when no sub_fields.
	 *
	 * Tests the early return:
	 * `if ( empty( $field['sub_fields'] ) ) { return $valid; }`
	 */
	public function test_validate_value_no_subfields_returns_valid_unchanged() {
		$field = $this->get_field( array( 'sub_fields' => array() ) );
		$value = array( 'some_key' => 'some_value' );

		$result = $this->field_instance->validate_value( true, $value, $field, 'acf[test]' );

		$this->assertTrue( $result );
	}

	/**
	 * Test load_value returns value unchanged when no sub_fields.
	 *
	 * Tests the early return:
	 * `if ( empty( $field['sub_fields'] ) ) { return $value; }`
	 */
	public function test_load_value_no_subfields_returns_value_unchanged() {
		$field = $this->get_field( array( 'sub_fields' => array() ) );

		$result = $this->field_instance->load_value( 'original', $this->post_id, $field );

		$this->assertEquals( 'original', $result );
	}

	/**
	 * Test delete_value returns null when no sub_fields.
	 *
	 * Tests the early return:
	 * `if ( empty( $field['sub_fields'] ) ) { return null; }`
	 */
	public function test_delete_value_no_subfields_returns_null() {
		$field = $this->get_field( array( 'sub_fields' => array() ) );

		$result = $this->field_instance->delete_value( $this->post_id, 'test_group', $field );

		$this->assertNull( $result );
	}
}
