<?php
/**
 * Tests for the Textarea field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_textarea.
 */
class Test_ACF_Field_Textarea extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'textarea';
	}

	/**
	 * Get a base textarea field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'         => 'field_textarea_test',
				'name'        => 'test_textarea',
				'type'        => 'textarea',
				'label'       => 'Test Textarea',
				'required'    => 0,
				'maxlength'   => '',
				'rows'        => 4,
				'new_lines'   => 'wpautop',
				'placeholder' => '',
			),
			$overrides
		);
	}

	/**
	 * Data provider for new_lines options.
	 *
	 * @return array
	 */
	public function new_lines_provider() {
		return array(
			'wpautop'   => array( 'wpautop' ),
			'br'        => array( 'br' ),
			'no format' => array( '' ),
		);
	}

	/**
	 * Test format_value returns text.
	 */
	public function test_format_value() {
		$field = $this->get_field( array( 'new_lines' => '' ) );

		$result = $this->field_instance->format_value( "Line 1\nLine 2", $this->post_id, $field );

		$this->assertStringContainsString( 'Line 1', $result );
		$this->assertStringContainsString( 'Line 2', $result );
	}

	/**
	 * Test format_value with wpautop.
	 */
	public function test_format_value_wpautop() {
		$field = $this->get_field( array( 'new_lines' => 'wpautop' ) );

		$result = $this->field_instance->format_value( "Paragraph 1\n\nParagraph 2", $this->post_id, $field );

		$this->assertStringContainsString( '<p>', $result );
	}

	/**
	 * Test format_value with br.
	 */
	public function test_format_value_br() {
		$field = $this->get_field( array( 'new_lines' => 'br' ) );

		$result = $this->field_instance->format_value( "Line 1\nLine 2", $this->post_id, $field );

		$this->assertStringContainsString( '<br', $result );
	}

	/**
	 * Test format_value returns empty for empty input.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test validate_value with valid text.
	 */
	public function test_validate_value_valid() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, 'Valid Text', $field, 'acf[field_textarea_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with maxlength exceeded.
	 */
	public function test_validate_value_maxlength() {
		$field = $this->get_field( array( 'maxlength' => 10 ) );

		$valid = $this->field_instance->validate_value( true, 'This is too long for the maxlength', $field, 'acf[field_textarea_test]' );

		// Returns error message string when validation fails.
		$this->assertIsString( $valid );
		$this->assertStringContainsString( 'must not exceed', $valid );
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
	 * Test validate_value with a non-scalar value and maxlength set.
	 *
	 * A crafted submission (e.g. `acf[field_key][]=x`) can deliver an array to a
	 * textarea field. The maxlength check must not run a string operation on it,
	 * which would emit an "Array to string conversion" warning.
	 */
	public function test_validate_value_array_with_maxlength_does_not_convert() {
		$field = $this->get_field( array( 'maxlength' => 10 ) );

		$caught = null;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Capturing the conversion notice deterministically for this regression test.
		set_error_handler(
			static function ( $errno, $errstr ) use ( &$caught ) {
				if ( false !== strpos( $errstr, 'Array to string conversion' ) ) {
					$caught = $errstr;
				}
				return true;
			}
		);

		try {
			$valid = $this->field_instance->validate_value( true, array( 'x' ), $field, 'acf[field_textarea_test]' );
		} finally {
			restore_error_handler();
		}

		$this->assertNull( $caught, 'maxlength check must not trigger an array-to-string conversion' );
		// A non-scalar value is not valid text, so the passed $valid state is preserved.
		$this->assertTrue( $valid );
	}
}
