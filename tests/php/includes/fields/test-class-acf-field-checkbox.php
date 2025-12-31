<?php
/**
 * Tests for the Checkbox field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_checkbox.
 */
class Test_ACF_Field_Checkbox extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'checkbox';
	}

	/**
	 * Get the include path(s) for the field class.
	 *
	 * Checkbox depends on Select for format_value, translate_field, update_value.
	 *
	 * @return array
	 */
	protected function get_field_include_path() {
		return array(
			'includes/fields/class-acf-field-select.php',
			'includes/fields/class-acf-field-checkbox.php',
		);
	}

	/**
	 * Get a base checkbox field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_checkbox_test',
				'name'          => 'test_checkbox',
				'type'          => 'checkbox',
				'label'         => 'Test Checkbox',
				'required'      => 0,
				'return_format' => 'value',
				'toggle'        => 0,
				'allow_custom'  => 0,
				'save_custom'   => 0,
				'choices'       => array(
					'red'   => 'Red',
					'green' => 'Green',
					'blue'  => 'Blue',
				),
			),
			$overrides
		);
	}


	/**
	 * Test format_value with value return format.
	 */
	public function test_format_value_value_format() {
		$field = $this->get_field( array( 'return_format' => 'value' ) );

		$result = $this->field_instance->format_value( array( 'red', 'blue' ), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertContains( 'red', $result );
		$this->assertContains( 'blue', $result );
	}

	/**
	 * Test format_value with label return format.
	 */
	public function test_format_value_label_format() {
		$field = $this->get_field( array( 'return_format' => 'label' ) );

		$result = $this->field_instance->format_value( array( 'red', 'blue' ), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertContains( 'Red', $result );
		$this->assertContains( 'Blue', $result );
	}

	/**
	 * Test format_value with array return format.
	 */
	public function test_format_value_array_format() {
		$field = $this->get_field( array( 'return_format' => 'array' ) );

		$result = $this->field_instance->format_value( array( 'red' ), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'value', $result[0] );
		$this->assertArrayHasKey( 'label', $result[0] );
		$this->assertEquals( 'red', $result[0]['value'] );
		$this->assertEquals( 'Red', $result[0]['label'] );
	}

	/**
	 * Test format_value returns empty array for empty.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test format_value_for_rest returns the raw value.
	 *
	 * Note: Checkbox doesn't override format_value_for_rest, so it inherits
	 * the base class behavior which returns the raw value unchanged.
	 * The return_format setting only affects format_value, not REST output.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field( array( 'return_format' => 'value' ) );

		$rest_result = $this->field_instance->format_value_for_rest( array( 'red' ), $this->post_id, $field );

		// REST returns raw value, not formatted value.
		$this->assertIsArray( $rest_result );
		$this->assertEquals( array( 'red' ), $rest_result );
	}

	/**
	 * Test update_value with selected values.
	 */
	public function test_update_value() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( array( 'red', 'green' ), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
	}

	/**
	 * Test update_value converts values to strings.
	 *
	 * Note: update_value uses array_map('strval', $value) which converts
	 * all values to strings but does not filter out empty strings.
	 */
	public function test_update_value_converts_to_strings() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( array( 'red', '', 'blue' ), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertContains( 'red', $result );
		$this->assertContains( '', $result );
		$this->assertContains( 'blue', $result );
	}

	/**
	 * Test update_value returns empty array for empty array.
	 */
	public function test_update_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( array(), $this->post_id, $field );

		// update_value returns empty value as-is (empty array).
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test validate_value with valid selection.
	 */
	public function test_validate_value_valid() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, array( 'red' ), $field, 'acf[field_checkbox_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with empty when required.
	 *
	 * Note: The checkbox field's validate_value only handles custom value validation,
	 * not required field validation. Required validation is handled by the base class
	 * and would happen before this method is called. Without allow_custom enabled,
	 * the validation simply returns the passed $valid value.
	 */
	public function test_validate_value_empty_required() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, array(), $field, 'acf[field_checkbox_test]' );

		// Without allow_custom, validate_value returns the passed $valid parameter as-is.
		$this->assertTrue( $valid );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'array', $schema['type'] );
	}

	/**
	 * Test all choices selected.
	 */
	public function test_all_choices_selected() {
		$field = $this->get_field( array( 'return_format' => 'value' ) );

		$result = $this->field_instance->format_value( array( 'red', 'green', 'blue' ), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
	}
}
