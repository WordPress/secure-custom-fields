<?php
/**
 * Tests for the Text field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_text.
 */
class Test_ACF_Field_Text extends Abstract_ACF_Field_Test {

	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'text';
	}

	/**
	 * Get a base text field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'         => 'field_text_test',
				'name'        => 'test_text',
				'type'        => 'text',
				'label'       => 'Test Text',
				'required'    => 0,
				'maxlength'   => '',
				'placeholder' => '',
				'prepend'     => '',
				'append'      => '',
			),
			$overrides
		);
	}

	/**
	 * Test validate_value with valid text.
	 */
	public function test_validate_value_valid() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, 'Valid Text', $field, 'acf[field_text_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with maxlength exceeded.
	 */
	public function test_validate_value_maxlength() {
		$field = $this->get_field( array( 'maxlength' => 10 ) );

		$valid = $this->field_instance->validate_value( true, 'This is too long', $field, 'acf[field_text_test]' );

		// Returns error message string when validation fails.
		$this->assertIsString( $valid );
		$this->assertStringContainsString( 'must not exceed', $valid );
	}

	/**
	 * Test validate_value with maxlength within limit.
	 */
	public function test_validate_value_maxlength_within_limit() {
		$field = $this->get_field( array( 'maxlength' => 20 ) );

		$valid = $this->field_instance->validate_value( true, 'Short text', $field, 'acf[field_text_test]' );

		$this->assertTrue( $valid );
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
	 * Test format_value_for_rest returns string value.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( 'test value', $this->post_id, $field );

		$this->assertEquals( 'test value', $result );
	}

	/**
	 * Test format_value_for_rest handles empty value.
	 */
	public function test_format_value_for_rest_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( '', $this->post_id, $field );

		$this->assertEmpty( $result );
	}
}
