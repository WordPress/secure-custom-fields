<?php
/**
 * Tests for the Email field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_email.
 */
class Test_ACF_Field_Email extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'email';
	}

	/**
	 * Get a base email field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'         => 'field_email_test',
				'name'        => 'test_email',
				'type'        => 'email',
				'label'       => 'Test Email',
				'required'    => 0,
				'prepend'     => '',
				'append'      => '',
				'placeholder' => '',
			),
			$overrides
		);
	}

	/**
	 * Data provider for valid emails.
	 *
	 * @return array
	 */
	public function valid_email_provider() {
		return array(
			'simple email'    => array( 'user@example.com' ),
			'subdomain email' => array( 'user@mail.example.com' ),
			'plus email'      => array( 'user+tag@example.com' ),
			'dots email'      => array( 'first.last@example.com' ),
			'numbers email'   => array( 'user123@example.com' ),
		);
	}

	/**
	 * Data provider for invalid emails.
	 *
	 * @return array
	 */
	public function invalid_email_provider() {
		return array(
			'no at symbol'  => array( 'userexample.com' ),
			'no domain'     => array( 'user@' ),
			'no local part' => array( '@example.com' ),
			'spaces'        => array( 'user @example.com' ),
		);
	}

	/**
	 * Test validate_value with valid email.
	 *
	 * @dataProvider valid_email_provider
	 *
	 * @param string $email The email to test.
	 */
	public function test_validate_value_valid( $email ) {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, $email, $field, 'acf[field_email_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with invalid email.
	 *
	 * @dataProvider invalid_email_provider
	 *
	 * @param string $email The invalid email to test.
	 */
	public function test_validate_value_invalid( $email ) {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, $email, $field, 'acf[field_email_test]' );

		// Invalid emails should return an error message string.
		$this->assertIsString( $valid );
		$this->assertStringContainsString( 'is not a valid email address', $valid );
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
}
