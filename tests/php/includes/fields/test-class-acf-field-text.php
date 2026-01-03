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

	/**
	 * Test validate_value with no maxlength passes.
	 *
	 * Tests the condition:
	 * `if ( isset( $field['maxlength'] ) && $field['maxlength'] && ... )`
	 */
	public function test_validate_value_no_maxlength_passes() {
		$field = $this->get_field( array( 'maxlength' => '' ) );

		$valid = $this->field_instance->validate_value( true, 'Any length text is fine', $field, 'acf[field_text_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with zero maxlength passes.
	 *
	 * Tests that maxlength of 0 is treated as "no limit" (falsy check).
	 */
	public function test_validate_value_zero_maxlength_passes() {
		$field = $this->get_field( array( 'maxlength' => 0 ) );

		$valid = $this->field_instance->validate_value( true, 'Long text with zero maxlength', $field, 'acf[field_text_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value at exact maxlength boundary passes.
	 */
	public function test_validate_value_exact_maxlength_passes() {
		$field = $this->get_field( array( 'maxlength' => 10 ) );

		// Exactly 10 characters.
		$valid = $this->field_instance->validate_value( true, '1234567890', $field, 'acf[field_text_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value one over maxlength fails.
	 */
	public function test_validate_value_one_over_maxlength_fails() {
		$field = $this->get_field( array( 'maxlength' => 10 ) );

		// 11 characters (one over).
		$valid = $this->field_instance->validate_value( true, '12345678901', $field, 'acf[field_text_test]' );

		$this->assertIsString( $valid );
		$this->assertStringContainsString( '10', $valid );  // Should mention the limit.
	}

	/**
	 * Test validate_value preserves passed $valid state.
	 *
	 * Tests that the function returns the passed $valid when no maxlength issue.
	 */
	public function test_validate_value_preserves_valid_state() {
		$field = $this->get_field( array( 'maxlength' => 100 ) );

		// Pass false as initial $valid - should be preserved and returned.
		$valid = $this->field_instance->validate_value( false, 'Short', $field, 'acf[field_text_test]' );

		$this->assertFalse( $valid );
	}

	/**
	 * Test get_rest_schema includes maxLength when set.
	 *
	 * Tests the maxLength addition:
	 * `if ( ! empty( $field['maxlength'] ) ) { $schema['maxLength'] = (int) $field['maxlength']; }`
	 */
	public function test_get_rest_schema_includes_maxlength() {
		$field = $this->get_field( array( 'maxlength' => 50 ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertArrayHasKey( 'maxLength', $schema );
		$this->assertEquals( 50, $schema['maxLength'] );
	}

	/**
	 * Test get_rest_schema excludes maxLength when empty.
	 */
	public function test_get_rest_schema_excludes_empty_maxlength() {
		$field = $this->get_field( array( 'maxlength' => '' ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertArrayNotHasKey( 'maxLength', $schema );
	}

	/**
	 * Test get_rest_schema casts maxLength to integer.
	 */
	public function test_get_rest_schema_casts_maxlength_to_int() {
		$field = $this->get_field( array( 'maxlength' => '25' ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertArrayHasKey( 'maxLength', $schema );
		$this->assertSame( 25, $schema['maxLength'] );  // Should be int, not string.
	}
}
