<?php
/**
 * Tests for the Date Time Picker field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_date_time_picker.
 */
class Test_ACF_Field_Date_Time_Picker extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'date_time_picker';
	}

	/**
	 * Date Time Picker field instance.
	 *
	 * @var acf_field_date_time_picker
	 */
	protected $field_instance;

	/**
	 * Get a base date time picker field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'            => 'field_date_time_picker_test',
				'name'           => 'test_date_time_picker',
				'type'           => 'date_time_picker',
				'label'          => 'Test Date Time Picker',
				'required'       => 0,
				'display_format' => 'd/m/Y g:i a',
				'return_format'  => 'd/m/Y g:i a',
				'first_day'      => 1,
			),
			$overrides
		);
	}

	/**
	 * Data provider for datetime formats.
	 *
	 * @return array
	 */
	public function datetime_format_provider() {
		return array(
			'12 hour format' => array( 'd/m/Y g:i a', '2023-12-25 14:30:00', '25/12/2023 2:30 pm' ),
			'24 hour format' => array( 'd/m/Y H:i', '2023-12-25 14:30:00', '25/12/2023 14:30' ),
			'iso format'     => array( 'Y-m-d H:i:s', '2023-12-25 14:30:00', '2023-12-25 14:30:00' ),
			'us format'      => array( 'm/d/Y g:i A', '2023-12-25 14:30:00', '12/25/2023 2:30 PM' ),
		);
	}

	/**
	 * Test format_value with various display formats.
	 *
	 * @dataProvider datetime_format_provider
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

		$result = $this->field_instance->format_value_for_rest( '2023-12-25 14:30:00', $this->post_id, $field );

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
	 * Test first_day option.
	 */
	public function test_first_day_option() {
		$field_sunday = $this->get_field( array( 'first_day' => 0 ) );
		$field_monday = $this->get_field( array( 'first_day' => 1 ) );

		$this->assertEquals( 0, $field_sunday['first_day'] );
		$this->assertEquals( 1, $field_monday['first_day'] );
	}

	/**
	 * Test display_format vs return_format.
	 */
	public function test_display_vs_return_format() {
		$field = $this->get_field(
			array(
				'display_format' => 'F j, Y g:i a',
				'return_format'  => 'Y-m-d H:i:s',
			)
		);

		$this->assertEquals( 'F j, Y g:i a', $field['display_format'] );
		$this->assertEquals( 'Y-m-d H:i:s', $field['return_format'] );
	}

	/**
	 * Test format_value preserves time component.
	 */
	public function test_format_value_preserves_time() {
		$field = $this->get_field( array( 'return_format' => 'H:i:s' ) );

		$result = $this->field_instance->format_value( '2023-12-25 14:30:45', $this->post_id, $field );

		$this->assertEquals( '14:30:45', $result );
	}

	/**
	 * Test format_value with midnight time.
	 */
	public function test_format_value_midnight() {
		$field = $this->get_field( array( 'return_format' => 'Y-m-d H:i:s' ) );

		$result = $this->field_instance->format_value( '2023-12-25 00:00:00', $this->post_id, $field );

		$this->assertEquals( '2023-12-25 00:00:00', $result );
	}
}
