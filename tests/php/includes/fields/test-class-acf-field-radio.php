<?php
/**
 * Tests for the Radio field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_radio.
 */
class Test_ACF_Field_Radio extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'radio';
	}

	/**
	 * Get the include path(s) for the field class.
	 *
	 * Radio depends on Select for format_value and translate_field.
	 *
	 * @return array
	 */
	protected function get_field_include_path() {
		return array(
			'includes/fields/class-acf-field-select.php',
			'includes/fields/class-acf-field-radio.php',
		);
	}

	/**
	 * Get a base radio field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'               => 'field_radio_test',
				'name'              => 'test_radio',
				'type'              => 'radio',
				'label'             => 'Test Radio',
				'required'          => 0,
				'return_format'     => 'value',
				'choices'           => array(
					'red'   => 'Red',
					'green' => 'Green',
					'blue'  => 'Blue',
				),
				'other_choice'      => 0,
				'save_other_choice' => 0,
				'layout'            => 'vertical',
			),
			$overrides
		);
	}


	/**
	 * Test format_value with value return format.
	 */
	public function test_format_value_value_format() {
		$field = $this->get_field( array( 'return_format' => 'value' ) );

		$result = $this->field_instance->format_value( 'red', $this->post_id, $field );

		$this->assertEquals( 'red', $result );
	}

	/**
	 * Test format_value with label return format.
	 */
	public function test_format_value_label_format() {
		$field = $this->get_field( array( 'return_format' => 'label' ) );

		$result = $this->field_instance->format_value( 'red', $this->post_id, $field );

		$this->assertEquals( 'Red', $result );
	}

	/**
	 * Test format_value with array return format.
	 */
	public function test_format_value_array_format() {
		$field = $this->get_field( array( 'return_format' => 'array' ) );

		$result = $this->field_instance->format_value( 'red', $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'value', $result );
		$this->assertArrayHasKey( 'label', $result );
		$this->assertEquals( 'red', $result['value'] );
		$this->assertEquals( 'Red', $result['label'] );
	}

	/**
	 * Test format_value returns empty string for empty.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertSame( '', $result );
	}

	/**
	 * Test format_value_for_rest returns the raw value.
	 *
	 * Note: Radio doesn't override format_value_for_rest, so it inherits
	 * the base class behavior which returns the raw value unchanged.
	 * The return_format setting only affects format_value, not REST output.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field( array( 'return_format' => 'value' ) );

		$rest_result = $this->field_instance->format_value_for_rest( 'green', $this->post_id, $field );

		// REST returns raw value, not formatted value.
		$this->assertEquals( 'green', $rest_result );
	}

	/**
	 * Test update_value with valid choice.
	 */
	public function test_update_value() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( 'blue', $this->post_id, $field );

		$this->assertEquals( 'blue', $result );
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
	 * Test save_other_choice does not trigger warnings for JSON-only fields.
	 */
	public function test_update_value_save_other_choice_handles_json_field_without_id() {
		$field = $this->get_field(
			array(
				'save_other_choice' => 1,
			)
		);

		$result = $this->field_instance->update_value( 'custom_value', $this->post_id, $field );
		$this->assertSame( 'custom_value', $result );
	}

	/**
	 * Test save_other_choice bails early when field key and ID are both missing.
	 */
	public function test_update_value_save_other_choice_handles_missing_field_identifier() {
		$field = $this->get_field(
			array(
				'save_other_choice' => 1,
			)
		);
		unset( $field['key'] );

		$result = $this->field_instance->update_value( 'custom_value', $this->post_id, $field );
		$this->assertSame( 'custom_value', $result );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'string', $schema['type'] );
	}

	/**
	 * Test load_value returns stored value.
	 */
	public function test_load_value() {
		$field = $this->get_field();

		$result = $this->field_instance->load_value( 'green', $this->post_id, $field );

		$this->assertEquals( 'green', $result );
	}

	/**
	 * Test other_choice option allows custom values.
	 */
	public function test_other_choice_option() {
		$field = $this->get_field( array( 'other_choice' => 1 ) );

		$result = $this->field_instance->update_value( 'custom_value', $this->post_id, $field );

		$this->assertEquals( 'custom_value', $result );
	}

	/**
	 * Test format_value with unknown choice returns value.
	 */
	public function test_format_value_unknown_choice() {
		$field = $this->get_field( array( 'return_format' => 'label' ) );

		$result = $this->field_instance->format_value( 'unknown', $this->post_id, $field );

		// Unknown choices should return the value itself.
		$this->assertEquals( 'unknown', $result );
	}

	/**
	 * Test load_value extracts single value from array.
	 *
	 * Tests the load_value array handling:
	 * `if ( is_array( $value ) ) { $value = array_pop( $value ); }`
	 */
	public function test_load_value_extracts_from_array() {
		$field = $this->get_field();

		// Radio is single-select, so if stored as array, should extract last value.
		$result = $this->field_instance->load_value( array( 'red', 'green', 'blue' ), $this->post_id, $field );

		$this->assertEquals( 'blue', $result );
	}

	/**
	 * Test load_value returns single value unchanged.
	 */
	public function test_load_value_single_unchanged() {
		$field = $this->get_field();

		$result = $this->field_instance->load_value( 'green', $this->post_id, $field );

		$this->assertEquals( 'green', $result );
	}

	/**
	 * Test update_value allows numeric zero through.
	 *
	 * Tests the early return condition:
	 * `if ( ! $value && ! is_numeric( $value ) ) { return $value; }`
	 */
	public function test_update_value_allows_numeric_zero() {
		$field = $this->get_field(
			array(
				'choices' => array(
					'0' => 'Zero',
					'1' => 'One',
					'2' => 'Two',
				),
			)
		);

		// Numeric 0 should pass through, not return early.
		$result = $this->field_instance->update_value( 0, $this->post_id, $field );

		$this->assertSame( 0, $result );
	}

	/**
	 * Test update_value allows string zero through.
	 */
	public function test_update_value_allows_string_zero() {
		$field = $this->get_field(
			array(
				'choices' => array(
					'0' => 'Zero',
					'1' => 'One',
				),
			)
		);

		$result = $this->field_instance->update_value( '0', $this->post_id, $field );

		$this->assertSame( '0', $result );
	}

	/**
	 * Test update_value returns empty string early.
	 *
	 * Tests that non-numeric empty values return early.
	 */
	public function test_update_value_empty_returns_early() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( '', $this->post_id, $field );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test update_value returns null early.
	 */
	public function test_update_value_null_returns_early() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( null, $this->post_id, $field );

		$this->assertNull( $result );
	}

	/**
	 * Test get_rest_schema includes enum without other_choice.
	 *
	 * Tests the enum generation:
	 * `if ( empty( $field['other_choice'] ) ) { return $schema; }`
	 */
	public function test_get_rest_schema_includes_enum() {
		$field = $this->get_field( array( 'other_choice' => 0 ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertArrayHasKey( 'enum', $schema );
	}

	/**
	 * Test get_rest_schema excludes enum with other_choice.
	 *
	 * Tests the early return when other_choice is enabled:
	 * `if ( ! empty( $field['other_choice'] ) ) { return $schema; }`
	 */
	public function test_get_rest_schema_no_enum_with_other_choice() {
		$field = $this->get_field( array( 'other_choice' => 1 ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		// With other_choice enabled, enum should NOT be set.
		$this->assertArrayNotHasKey( 'enum', $schema );
	}

	/**
	 * Test get_rest_schema includes null in enum when allow_null.
	 *
	 * Tests the allow_null enum addition:
	 * `if ( ! empty( $field['allow_null'] ) ) { $schema['enum'][] = null; }`
	 */
	public function test_get_rest_schema_enum_includes_null_when_allowed() {
		$field = $this->get_field(
			array(
				'allow_null'   => 1,
				'other_choice' => 0,
			)
		);

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertArrayHasKey( 'enum', $schema );
		$this->assertContains( null, $schema['enum'] );
	}
}
