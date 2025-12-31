<?php
/**
 * Tests for the Range field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_range.
 */
class Test_ACF_Field_Range extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'range';
	}

	/**
	 * Get a base range field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'      => 'field_range_test',
				'name'     => 'test_range',
				'type'     => 'range',
				'label'    => 'Test Range',
				'required' => 0,
				'min'      => 0,
				'max'      => 100,
				'step'     => 1,
				'prepend'  => '',
				'append'   => '',
			),
			$overrides
		);
	}

	/**
	 * Test format_value_for_rest returns number.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( 50, $this->post_id, $field );

		$this->assertEquals( 50, $result );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 *
	 * Note: Range schema type may be an array containing multiple types
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
