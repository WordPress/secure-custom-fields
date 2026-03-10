<?php
/**
 * Tests for the Select field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_select.
 */
class Test_ACF_Field_Select extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'select';
	}

	/**
	 * Get a base select field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_select_test',
				'name'          => 'test_select',
				'type'          => 'select',
				'label'         => 'Test Select',
				'required'      => 0,
				'multiple'      => 0,
				'allow_null'    => 0,
				'return_format' => 'value',
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
	 * Test format_value with multiple selections.
	 */
	public function test_format_value_multiple() {
		$field = $this->get_field(
			array(
				'multiple'      => 1,
				'return_format' => 'value',
			)
		);

		$result = $this->field_instance->format_value( array( 'red', 'blue' ), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertContains( 'red', $result );
		$this->assertContains( 'blue', $result );
	}

	/**
	 * Test format_value with multiple array format.
	 */
	public function test_format_value_multiple_array_format() {
		$field = $this->get_field(
			array(
				'multiple'      => 1,
				'return_format' => 'array',
			)
		);

		$result = $this->field_instance->format_value( array( 'red', 'blue' ), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertEquals( 'red', $result[0]['value'] );
		$this->assertEquals( 'blue', $result[1]['value'] );
	}

	/**
	 * Test format_value returns empty string for empty single.
	 */
	public function test_format_value_empty_single() {
		$field = $this->get_field( array( 'multiple' => 0 ) );

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertSame( '', $result );
	}

	/**
	 * Test format_value returns empty array for empty multiple.
	 */
	public function test_format_value_empty_multiple() {
		$field = $this->get_field( array( 'multiple' => 1 ) );

		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test format_value_for_rest returns the raw value.
	 *
	 * Note: Select doesn't override format_value_for_rest, so it inherits
	 * the base class behavior which returns the raw value unchanged.
	 * The return_format setting only affects format_value, not REST output.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field( array( 'return_format' => 'value' ) );

		$rest_result = $this->field_instance->format_value_for_rest( 'red', $this->post_id, $field );

		// REST returns raw value, not formatted value.
		$this->assertEquals( 'red', $rest_result );
	}

	/**
	 * Test update_value with single value.
	 */
	public function test_update_value_single() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( 'red', $this->post_id, $field );

		$this->assertEquals( 'red', $result );
	}

	/**
	 * Test update_value with multiple values.
	 */
	public function test_update_value_multiple() {
		$field = $this->get_field( array( 'multiple' => 1 ) );

		$result = $this->field_instance->update_value( array( 'red', 'blue' ), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
	}

	/**
	 * Test update_value converts values to strings in multiple.
	 *
	 * Note: update_value uses array_map('strval', $value) which converts
	 * all values to strings but does not filter out empty strings.
	 */
	public function test_update_value_converts_to_strings() {
		$field = $this->get_field( array( 'multiple' => 1 ) );

		$result = $this->field_instance->update_value( array( 'red', '', 'blue' ), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertContains( 'red', $result );
		$this->assertContains( '', $result );
		$this->assertContains( 'blue', $result );
	}

	/**
	 * Test save_options does not trigger warnings for JSON-only fields.
	 */
	public function test_update_value_save_options_handles_json_field_without_id() {
		$field = $this->get_field(
			array(
				'save_options' => 1,
				'multiple'     => 1,
			)
		);

		set_error_handler(
			static function ( $errno, $errstr ) {
				throw new \RuntimeException( $errstr, $errno );
			}
		);

		try {
			$result = $this->field_instance->update_value( array( 'custom_value' ), $this->post_id, $field );
			$this->assertSame( array( 'custom_value' ), $result );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Test save_options bails early when field key and ID are both missing.
	 */
	public function test_update_value_save_options_handles_missing_field_identifier() {
		$field = $this->get_field(
			array(
				'save_options' => 1,
				'multiple'     => 1,
			)
		);
		unset( $field['key'] );

		set_error_handler(
			static function ( $errno, $errstr ) {
				throw new \RuntimeException( $errstr, $errno );
			}
		);

		try {
			$result = $this->field_instance->update_value( array( 'custom_value' ), $this->post_id, $field );
			$this->assertSame( array( 'custom_value' ), $result );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
	}

	/**
	 * Test get_rest_schema for multiple select.
	 */
	public function test_get_rest_schema_multiple() {
		$field = $this->get_field( array( 'multiple' => 1 ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'array', $schema['type'] );
	}

	/**
	 * Test load_value returns stored value.
	 */
	public function test_load_value() {
		$field = $this->get_field();

		$result = $this->field_instance->load_value( 'blue', $this->post_id, $field );

		$this->assertEquals( 'blue', $result );
	}

	/**
	 * Test with optgroup choices.
	 */
	public function test_optgroup_choices() {
		$field = $this->get_field(
			array(
				'choices' => array(
					'Primary Colors'   => array(
						'red'    => 'Red',
						'blue'   => 'Blue',
						'yellow' => 'Yellow',
					),
					'Secondary Colors' => array(
						'orange' => 'Orange',
						'green'  => 'Green',
						'purple' => 'Purple',
					),
				),
			)
		);

		$this->assertIsArray( $field['choices'] );
		$this->assertArrayHasKey( 'Primary Colors', $field['choices'] );
	}

	/**
	 * Test allow_null option.
	 */
	public function test_allow_null() {
		$field = $this->get_field( array( 'allow_null' => 1 ) );

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertSame( '', $result );
	}
}
