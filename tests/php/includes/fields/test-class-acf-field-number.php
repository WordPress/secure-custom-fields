<?php
/**
 * Tests for the Number field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_number.
 */
class Test_ACF_Field_Number extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'number';
	}

	/**
	 * Get a base number field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'         => 'field_number_test',
				'name'        => 'test_number',
				'type'        => 'number',
				'label'       => 'Test Number',
				'required'    => 0,
				'min'         => '',
				'max'         => '',
				'step'        => '',
				'prepend'     => '',
				'append'      => '',
				'placeholder' => '',
			),
			$overrides
		);
	}

	/**
	 * Test format_value_for_rest returns number.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( 42, $this->post_id, $field );

		$this->assertEquals( 42, $result );
	}

	/**
	 * Test update_value stores number.
	 */
	public function test_update_value() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( 100, $this->post_id, $field );

		$this->assertEquals( 100, $result );
	}

	/**
	 * Test update_value with empty returns empty.
	 */
	public function test_update_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( '', $this->post_id, $field );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test validate_value with valid number.
	 */
	public function test_validate_value_valid() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, 42, $field, 'acf[field_number_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with empty when required.
	 *
	 * Note: The base validate_value method returns the $valid parameter passed to it.
	 * Empty value checking for required fields is handled separately by ACF.
	 */
	public function test_validate_value_empty_required() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, '', $field, 'acf[field_number_test]' );

		// The base validate_value returns the $valid parameter (true).
		// Required field empty checking is handled by ACF core, not the field type.
		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with min constraint.
	 */
	public function test_validate_value_min() {
		$field = $this->get_field( array( 'min' => 10 ) );

		$valid = $this->field_instance->validate_value( true, 5, $field, 'acf[field_number_test]' );

		$this->assertIsString( $valid );
	}

	/**
	 * Test validate_value with max constraint.
	 */
	public function test_validate_value_max() {
		$field = $this->get_field( array( 'max' => 100 ) );

		$valid = $this->field_instance->validate_value( true, 150, $field, 'acf[field_number_test]' );

		$this->assertIsString( $valid );
	}

	/**
	 * Test validate_value within range.
	 */
	public function test_validate_value_in_range() {
		$field = $this->get_field(
			array(
				'min' => 0,
				'max' => 100,
			)
		);

		$valid = $this->field_instance->validate_value( true, 50, $field, 'acf[field_number_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 *
	 * Note: Number schema type may be an array containing multiple types
	 * (e.g., ['number', 'string', 'null']) to allow various input types.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		// Schema type may be 'number' or an array containing 'number'.
		if ( is_array( $schema['type'] ) ) {
			$this->assertContains( 'number', $schema['type'] );
		} else {
			$this->assertEquals( 'number', $schema['type'] );
		}
	}
}
