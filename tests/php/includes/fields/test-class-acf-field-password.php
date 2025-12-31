<?php
/**
 * Tests for the Password field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_password.
 *
 * Note: Password field uses base class behavior for value handling.
 * Tests focus on REST schema and inherited text field behavior.
 */
class Test_ACF_Field_Password extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'password';
	}

	/**
	 * Get a base password field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'         => 'field_password_test',
				'name'        => 'test_password',
				'type'        => 'password',
				'label'       => 'Test Password',
				'required'    => 0,
				'prepend'     => '',
				'append'      => '',
				'placeholder' => '',
			),
			$overrides
		);
	}

	/**
	 * Test get_rest_schema returns string type.
	 */
	public function test_get_rest_schema_returns_string_type() {
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

		$result = $this->field_instance->format_value_for_rest( 'secret123', $this->post_id, $field );

		$this->assertEquals( 'secret123', $result );
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
	 * Test format_value_for_rest handles null value.
	 */
	public function test_format_value_for_rest_null() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( null, $this->post_id, $field );

		$this->assertNull( $result );
	}

	/**
	 * Test password field has correct defaults.
	 */
	public function test_field_defaults() {
		$field = $this->get_field();

		$this->assertEquals( 'password', $field['type'] );
		$this->assertEquals( '', $field['prepend'] );
		$this->assertEquals( '', $field['append'] );
		$this->assertEquals( '', $field['placeholder'] );
	}
}
