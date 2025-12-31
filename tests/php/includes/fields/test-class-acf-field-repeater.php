<?php
/**
 * Tests for the Repeater field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_repeater.
 */
class Test_ACF_Field_Repeater extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'repeater';
	}

	/**
	 * Get the include path(s) for the field class.
	 *
	 * Repeater requires the table class to be loaded first.
	 *
	 * @return array
	 */
	protected function get_field_include_path() {
		return array(
			'includes/fields/class-acf-repeater-table.php',
			'includes/fields/class-acf-field-repeater.php',
		);
	}

	/**
	 * Get a base repeater field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'        => 'field_repeater_test',
				'name'       => 'test_repeater',
				'type'       => 'repeater',
				'label'      => 'Test Repeater',
				'required'   => 0,
				'min'        => 0,
				'max'        => 0,
				'layout'     => 'table',
				'pagination' => 0,
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
	 * Test validate_value with empty value when not required.
	 */
	public function test_validate_value_empty_not_required() {
		$field = $this->get_field( array( 'required' => 0 ) );
		$valid = $this->field_instance->validate_value( true, array(), $field, 'acf[field_repeater_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with empty value when required.
	 */
	public function test_validate_value_empty_required() {
		$field = $this->get_field( array( 'required' => 1 ) );
		$valid = $this->field_instance->validate_value( true, array(), $field, 'acf[field_repeater_test]' );

		$this->assertFalse( $valid );
	}

	/**
	 * Test validate_value with value when required.
	 */
	public function test_validate_value_with_rows_required() {
		$field = $this->get_field( array( 'required' => 1 ) );
		$value = array(
			array(
				'field_sub_text'   => 'Hello',
				'field_sub_number' => 42,
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_repeater_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with min rows constraint not met.
	 */
	public function test_validate_value_min_rows_not_met() {
		$field = $this->get_field( array( 'min' => 3 ) );
		$value = array(
			array(
				'field_sub_text'   => 'Row 1',
				'field_sub_number' => 1,
			),
			array(
				'field_sub_text'   => 'Row 2',
				'field_sub_number' => 2,
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_repeater_test]' );

		$this->assertIsString( $valid );
		$this->assertStringContainsString( '3', $valid );
	}

	/**
	 * Test validate_value with min rows constraint met.
	 */
	public function test_validate_value_min_rows_met() {
		$field = $this->get_field( array( 'min' => 2 ) );
		$value = array(
			array(
				'field_sub_text'   => 'Row 1',
				'field_sub_number' => 1,
			),
			array(
				'field_sub_text'   => 'Row 2',
				'field_sub_number' => 2,
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_repeater_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value ignores acfcloneindex.
	 */
	public function test_validate_value_ignores_clone_index() {
		$field = $this->get_field( array( 'min' => 1 ) );
		$value = array(
			'acfcloneindex' => array(
				'field_sub_text'   => '',
				'field_sub_number' => '',
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_repeater_test]' );

		// Should fail because acfcloneindex is ignored and there are no real rows.
		$this->assertIsString( $valid );
	}

	/**
	 * Test validate_value ignores deleted rows in paginated repeaters.
	 */
	public function test_validate_value_ignores_deleted_rows() {
		$field = $this->get_field( array( 'min' => 1 ) );
		$value = array(
			'0_deleted' => array(
				'field_sub_text'   => 'Deleted',
				'field_sub_number' => 99,
			),
			'1'         => array(
				'field_sub_text'   => 'Active',
				'field_sub_number' => 1,
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_repeater_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with pagination enabled bypasses min validation.
	 */
	public function test_validate_value_pagination_bypasses_min() {
		$field = $this->get_field(
			array(
				'min'        => 10,
				'pagination' => 1,
			)
		);
		$value = array(
			array(
				'field_sub_text'   => 'Row 1',
				'field_sub_number' => 1,
			),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_repeater_test]' );

		// Pagination enabled bypasses min validation.
		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with no sub_fields.
	 */
	public function test_validate_value_no_sub_fields() {
		$field = $this->get_field( array( 'sub_fields' => array() ) );
		$value = array(
			array( 'some_value' => 'test' ),
		);
		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_repeater_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test format_value returns empty array for empty value.
	 */
	public function test_format_value_empty_value() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value with valid rows.
	 */
	public function test_format_value_with_rows() {
		$field = $this->get_field();
		$value = array(
			array(
				'sub_text'   => 'Hello',
				'sub_number' => 42,
			),
			array(
				'sub_text'   => 'World',
				'sub_number' => 100,
			),
		);

		$result = $this->field_instance->format_value( $value, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
	}

	/**
	 * Test load_value returns correct row count.
	 */
	public function test_load_value_returns_row_count() {
		$field = $this->get_field();

		// Store test data.
		update_post_meta( $this->post_id, 'test_repeater', 3 );
		update_post_meta( $this->post_id, 'test_repeater_0_sub_text', 'Row 1' );
		update_post_meta( $this->post_id, 'test_repeater_0_sub_number', 10 );
		update_post_meta( $this->post_id, 'test_repeater_1_sub_text', 'Row 2' );
		update_post_meta( $this->post_id, 'test_repeater_1_sub_number', 20 );
		update_post_meta( $this->post_id, 'test_repeater_2_sub_text', 'Row 3' );
		update_post_meta( $this->post_id, 'test_repeater_2_sub_number', 30 );

		$result = $this->field_instance->load_value( 3, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test load_value handles false value.
	 */
	public function test_load_value_handles_false() {
		$field  = $this->get_field();
		$result = $this->field_instance->load_value( false, $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test load_value handles null value.
	 *
	 * Note: Repeater field load_value may return false for null input.
	 */
	public function test_load_value_handles_null() {
		$field  = $this->get_field();
		$result = $this->field_instance->load_value( null, $this->post_id, $field );

		// Repeater field may return false for null input.
		$this->assertFalse( $result );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 */
	public function test_get_rest_schema() {
		$field  = $this->get_field();
		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'array', $schema['type'] );
		$this->assertArrayHasKey( 'items', $schema );
		$this->assertEquals( 'object', $schema['items']['type'] );
		$this->assertArrayHasKey( 'properties', $schema['items'] );
	}

	/**
	 * Test update_row saves row data correctly.
	 */
	public function test_update_row() {
		$field = $this->get_field();
		$row   = array(
			'field_sub_text'   => 'Updated Text',
			'field_sub_number' => 999,
		);

		$this->field_instance->update_row( $row, 0, $field, $this->post_id );

		$sub_text   = get_post_meta( $this->post_id, 'test_repeater_0_sub_text', true );
		$sub_number = get_post_meta( $this->post_id, 'test_repeater_0_sub_number', true );

		$this->assertEquals( 'Updated Text', $sub_text );
		$this->assertEquals( 999, $sub_number );
	}

	/**
	 * Test delete_row removes row data.
	 */
	public function test_delete_row() {
		$field = $this->get_field();

		// First add a row.
		update_post_meta( $this->post_id, 'test_repeater_0_sub_text', 'To Delete' );
		update_post_meta( $this->post_id, 'test_repeater_0_sub_number', 123 );
		update_post_meta( $this->post_id, '_test_repeater_0_sub_text', 'field_sub_text' );
		update_post_meta( $this->post_id, '_test_repeater_0_sub_number', 'field_sub_number' );

		$this->field_instance->delete_row( 0, $field, $this->post_id );

		$this->assertEquals( '', get_post_meta( $this->post_id, 'test_repeater_0_sub_text', true ) );
		$this->assertEquals( '', get_post_meta( $this->post_id, 'test_repeater_0_sub_number', true ) );
	}

	/**
	 * Test update_value with new rows.
	 */
	public function test_update_value_new_rows() {
		$field = $this->get_field();
		$value = array(
			array(
				'field_sub_text'   => 'New Row 1',
				'field_sub_number' => 100,
			),
			array(
				'field_sub_text'   => 'New Row 2',
				'field_sub_number' => 200,
			),
		);

		$result = $this->field_instance->update_value( $value, $this->post_id, $field );

		$this->assertEquals( 2, $result );
	}

	/**
	 * Test update_value with empty value returns empty string.
	 *
	 * Note: Repeater field returns empty string for empty input, not null.
	 */
	public function test_update_value_empty() {
		$field  = $this->get_field();
		$result = $this->field_instance->update_value( array(), $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test update_value removes acfcloneindex.
	 */
	public function test_update_value_removes_clone_index() {
		$field = $this->get_field();
		$value = array(
			'acfcloneindex' => array(
				'field_sub_text'   => '',
				'field_sub_number' => '',
			),
			'row0'          => array(
				'field_sub_text'   => 'Real Row',
				'field_sub_number' => 50,
			),
		);

		$result = $this->field_instance->update_value( $value, $this->post_id, $field );

		$this->assertEquals( 1, $result );
	}

	/**
	 * Data provider for nested repeater scenarios.
	 *
	 * @return array
	 */
	public function nested_repeater_provider() {
		return array(
			'single nested level' => array( 1 ),
			'double nested level' => array( 2 ),
		);
	}

	/**
	 * Test validate_value with nested repeater structure.
	 *
	 * @dataProvider nested_repeater_provider
	 *
	 * @param int $depth The nesting depth.
	 */
	public function test_validate_value_nested_repeater( $depth ) {
		$inner_repeater = array(
			'key'        => 'field_inner_repeater',
			'name'       => 'inner_repeater',
			'_name'      => 'inner_repeater',
			'type'       => 'repeater',
			'label'      => 'Inner Repeater',
			'required'   => 0,
			'min'        => 0,
			'max'        => 0,
			'sub_fields' => array(
				array(
					'key'       => 'field_inner_text',
					'name'      => 'inner_text',
					'_name'     => 'inner_text',
					'type'      => 'text',
					'label'     => 'Inner Text',
					'required'  => 0,
					'maxlength' => '',
				),
			),
		);

		$field = $this->get_field(
			array(
				'sub_fields' => array( $inner_repeater ),
			)
		);

		$value = array(
			array(
				'field_inner_repeater' => array(
					array( 'field_inner_text' => 'Nested value' ),
				),
			),
		);

		$valid = $this->field_instance->validate_value( true, $value, $field, 'acf[field_repeater_test]' );

		$this->assertTrue( $valid );
	}
}
