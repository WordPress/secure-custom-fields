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
	 * Get the include path(s) for the field class.
	 *
	 * Range extends Number, so we need to load Number first.
	 *
	 * @return array
	 */
	protected function get_field_include_path() {
		return array(
			'includes/fields/class-acf-field-number.php',
			'includes/fields/class-acf-field-range.php',
		);
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

	/**
	 * Test get_rest_schema includes min and max.
	 */
	public function test_get_rest_schema_includes_min_max() {
		$field = $this->get_field( array( 'min' => 10, 'max' => 200 ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertEquals( 10, $schema['minimum'] );
		$this->assertEquals( 200, $schema['maximum'] );
	}

	/**
	 * Test get_rest_schema uses defaults when min/max empty.
	 */
	public function test_get_rest_schema_uses_defaults() {
		$field = $this->get_field( array( 'min' => '', 'max' => '' ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertEquals( 0, $schema['minimum'] );
		$this->assertEquals( 100, $schema['maximum'] );
	}

	/**
	 * Test get_rest_schema includes default value when set.
	 */
	public function test_get_rest_schema_includes_default_value() {
		$field = $this->get_field( array( 'default_value' => 50 ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertArrayHasKey( 'default', $schema );
		$this->assertEquals( 50, $schema['default'] );
	}

	/**
	 * Test format_value_for_rest handles string numbers.
	 */
	public function test_format_value_for_rest_handles_string() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( '75', $this->post_id, $field );

		$this->assertIsNumeric( $result );
		$this->assertEquals( 75, $result );
	}

	/**
	 * Test format_value_for_rest handles empty value.
	 */
	public function test_format_value_for_rest_handles_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( '', $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test format_value_for_rest handles decimal values.
	 */
	public function test_format_value_for_rest_handles_decimal() {
		$field = $this->get_field( array( 'step' => 0.5 ) );

		$result = $this->field_instance->format_value_for_rest( 25.5, $this->post_id, $field );

		$this->assertEquals( 25.5, $result );
	}

	/**
	 * Test field category is basic.
	 */
	public function test_field_category() {
		$this->assertEquals( 'basic', $this->field_instance->category );
	}
}
