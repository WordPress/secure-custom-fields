<?php
/**
 * Tests for the Time Picker field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_time_picker.
 */
class Test_ACF_Field_Time_Picker extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'time_picker';
	}

	/**
	 * Time Picker field instance.
	 *
	 * @var acf_field_time_picker
	 */
	protected $field_instance;

	/**
	 * Get a base time picker field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'            => 'field_time_picker_test',
				'name'           => 'test_time_picker',
				'type'           => 'time_picker',
				'label'          => 'Test Time Picker',
				'required'       => 0,
				'display_format' => 'g:i a',
				'return_format'  => 'g:i a',
			),
			$overrides
		);
	}

	/**
	 * Data provider for time formats.
	 *
	 * @return array
	 */
	public function time_format_provider() {
		return array(
			'12 hour lowercase' => array( 'g:i a', '14:30:00', '2:30 pm' ),
			'12 hour uppercase' => array( 'g:i A', '14:30:00', '2:30 PM' ),
			'24 hour format'    => array( 'H:i', '14:30:00', '14:30' ),
			'24 hour seconds'   => array( 'H:i:s', '14:30:45', '14:30:45' ),
			'12 hour padded'    => array( 'h:i a', '09:05:00', '09:05 am' ),
		);
	}

	/**
	 * Test format_value with various time formats.
	 *
	 * @dataProvider time_format_provider
	 *
	 * @param string $format         The return format.
	 * @param string $stored_value   The stored database value.
	 * @param string $expected_value The expected formatted value.
	 */
	public function test_format_value_formats( $format, $stored_value, $expected_value ) {
		$field = $this->get_field( array( 'return_format' => $format ) );

		$result = $this->field_instance->format_value( $stored_value, $this->post_id, $field );

		$this->assertEquals( $expected_value, $result );
	}

	/**
	 * Test format_value returns empty for empty input.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test format_value_for_rest returns formatted value.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( '14:30:00', $this->post_id, $field );

		$this->assertNotEmpty( $result );
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
	 * Test display_format vs return_format.
	 */
	public function test_display_vs_return_format() {
		$field = $this->get_field(
			array(
				'display_format' => 'g:i a',
				'return_format'  => 'H:i:s',
			)
		);

		$this->assertEquals( 'g:i a', $field['display_format'] );
		$this->assertEquals( 'H:i:s', $field['return_format'] );
	}

	/**
	 * Test format_value with midnight.
	 */
	public function test_format_value_midnight() {
		$field = $this->get_field( array( 'return_format' => 'H:i:s' ) );

		$result = $this->field_instance->format_value( '00:00:00', $this->post_id, $field );

		$this->assertEquals( '00:00:00', $result );
	}

	/**
	 * Test format_value with noon.
	 */
	public function test_format_value_noon() {
		$field = $this->get_field( array( 'return_format' => 'g:i a' ) );

		$result = $this->field_instance->format_value( '12:00:00', $this->post_id, $field );

		$this->assertEquals( '12:00 pm', $result );
	}

	/**
	 * Test format_value with end of day.
	 */
	public function test_format_value_end_of_day() {
		$field = $this->get_field( array( 'return_format' => 'H:i:s' ) );

		$result = $this->field_instance->format_value( '23:59:59', $this->post_id, $field );

		$this->assertEquals( '23:59:59', $result );
	}
}
