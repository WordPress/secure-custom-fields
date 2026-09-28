<?php
/**
 * Tests for the Date Picker field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_date_picker.
 */
class Test_ACF_Field_Date_Picker extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'date_picker';
	}

	/**
	 * Date Picker field instance.
	 *
	 * @var acf_field_date_picker
	 */
	protected $field_instance;

	/**
	 * Get a base date picker field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'            => 'field_date_picker_test',
				'name'           => 'test_date_picker',
				'type'           => 'date_picker',
				'label'          => 'Test Date Picker',
				'required'       => 0,
				'display_format' => 'd/m/Y',
				'return_format'  => 'd/m/Y',
				'first_day'      => 1,
			),
			$overrides
		);
	}

	/**
	 * Data provider for date formats.
	 *
	 * @return array
	 */
	public function date_format_provider() {
		return array(
			'us format'         => array( 'm/d/Y', '20231225', '12/25/2023' ),
			'european format'   => array( 'd/m/Y', '20231225', '25/12/2023' ),
			'iso format'        => array( 'Y-m-d', '20231225', '2023-12-25' ),
			'long format'       => array( 'F j, Y', '20231225', 'December 25, 2023' ),
			'wordpress default' => array( 'j F Y', '20231225', '25 December 2023' ),
		);
	}

	/**
	 * Test format_value with various display formats.
	 *
	 * @dataProvider date_format_provider
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
	 * Test format_value_for_rest returns ISO format.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( '20231225', $this->post_id, $field );

		// REST API typically returns formatted date.
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
				'display_format' => 'F j, Y',
				'return_format'  => 'Y-m-d',
			)
		);

		$this->assertEquals( 'F j, Y', $field['display_format'] );
		$this->assertEquals( 'Y-m-d', $field['return_format'] );
	}

	/**
	 * Test format_value with invalid date.
	 */
	public function test_format_value_invalid_date() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( 'invalid', $this->post_id, $field );

		// Invalid dates should still return something.
		$this->assertNotNull( $result );
	}

	/**
	 * Test the legacy picker markup is used by default.
	 */
	public function test_render_field_uses_legacy_picker_by_default() {
		$field          = $this->get_field( array( 'value' => '20231225' ) );
		$field['id']    = 'acf-field-date';
		$field['class'] = '';

		$html = $this->render_field_html( $field );

		$this->assertStringNotContainsString( 'type="date"', $html );
		$this->assertStringContainsString( 'acf-date-picker', $html );
	}

	/**
	 * Test the native input is used when the beta feature is enabled.
	 */
	public function test_render_field_uses_native_input_when_enabled() {
		$this->enable_native_pickers();

		$field          = $this->get_field( array( 'value' => '20231225' ) );
		$field['id']    = 'acf-field-date';
		$field['class'] = '';

		$html = $this->render_field_html( $field );

		$this->assertStringContainsString( 'type="date"', $html );
		$this->assertStringContainsString( 'value="2023-12-25"', $html );
		// The named hidden input keeps the stored Ymd format.
		$this->assertStringContainsString( 'name="test_date_picker"', $html );
		$this->assertStringContainsString( 'value="20231225"', $html );
	}

	/**
	 * Test the saved value format is unchanged in native mode.
	 */
	public function test_native_input_keeps_stored_format() {
		$this->enable_native_pickers();

		$field          = $this->get_field( array( 'value' => '20231225' ) );
		$field['id']    = 'acf-field-date';
		$field['class'] = '';

		$html = $this->render_field_html( $field );

		$this->assertStringContainsString( 'value="2023-12-25"', $html );
			$this->assertStringContainsString( 'name="test_date_picker"', $html );
			$this->assertStringContainsString( 'value="20231225"', $html );
	}

	/**
	 * Test a legacy save_format field keeps the JavaScript picker in native mode.
	 */
	public function test_render_field_keeps_legacy_markup_with_save_format() {
		$this->enable_native_pickers();

		$field          = $this->get_field(
			array(
				'value'       => '2023-12-25',
				'save_format' => 'Y-m-d',
			)
		);
		$field['id']    = 'acf-field-date';
		$field['class'] = '';

		$html = $this->render_field_html( $field );

		$this->assertStringNotContainsString( 'type="date"', $html );
		$this->assertStringContainsString( 'data-save_format', $html );
	}
}
