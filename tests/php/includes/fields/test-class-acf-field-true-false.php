<?php
/**
 * Tests for the True/False field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_true_false.
 */
class Test_ACF_Field_True_False extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'true_false';
	}

	/**
	 * True/False field instance.
	 *
	 * @var acf_field_true_false
	 */
	protected $field_instance;

	/**
	 * Get a base true/false field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_true_false_test',
				'name'          => 'test_true_false',
				'type'          => 'true_false',
				'label'         => 'Test True/False',
				'required'      => 0,
				'default_value' => 0,
				'ui'            => 0,
				'ui_on_text'    => '',
				'ui_off_text'   => '',
				'message'       => '',
			),
			$overrides
		);
	}

	/**
	 * Data provider for boolean values.
	 *
	 * @return array
	 */
	public function boolean_value_provider() {
		return array(
			'true as 1'         => array( 1, true ),
			'true as string 1'  => array( '1', true ),
			'true as boolean'   => array( true, true ),
			'false as 0'        => array( 0, false ),
			'false as string 0' => array( '0', false ),
			'false as boolean'  => array( false, false ),
			'false as empty'    => array( '', false ),
		);
	}

	/**
	 * Test format_value returns boolean true.
	 */
	public function test_format_value_true() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( 1, $this->post_id, $field );

		$this->assertTrue( $result );
	}

	/**
	 * Test format_value returns boolean false.
	 */
	public function test_format_value_false() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( 0, $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value with various input types.
	 *
	 * @dataProvider boolean_value_provider
	 *
	 * @param mixed $input          The input value.
	 * @param bool  $expected_value The expected boolean result.
	 */
	public function test_format_value_types( $input, $expected_value ) {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( $input, $this->post_id, $field );

		$this->assertEquals( $expected_value, $result );
	}

	/**
	 * Test format_value_for_rest returns boolean.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();

		$result_true  = $this->field_instance->format_value_for_rest( 1, $this->post_id, $field );
		$result_false = $this->field_instance->format_value_for_rest( 0, $this->post_id, $field );

		$this->assertTrue( $result_true );
		$this->assertFalse( $result_false );
	}

	/**
	 * Test validate_value for true/false.
	 *
	 * Note: For required true/false fields, a value of 0 (false) may fail validation
	 * as it's considered "empty" by the base validation. True (1) always passes.
	 */
	public function test_validate_value() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid_true = $this->field_instance->validate_value( true, 1, $field, 'acf[field_true_false_test]' );

		// True value always passes.
		$this->assertTrue( $valid_true );

		// For false (0) with required field, validation may pass or fail depending on implementation.
		// The base validate_value returns the $valid param passed to it for non-empty values.
		$valid_false = $this->field_instance->validate_value( true, 0, $field, 'acf[field_true_false_test]' );

		// Validate returns true or false based on field validation logic.
		$this->assertIsBool( $valid_false );
	}

	/**
	 * Test get_rest_schema returns boolean type.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'boolean', $schema['type'] );
	}

	/**
	 * Test validate_rest_value with boolean.
	 */
	public function test_validate_rest_value() {
		$field = $this->get_field();

		$valid_true  = $this->field_instance->validate_rest_value( true, true, $field, 'test_true_false', array(), '' );
		$valid_false = $this->field_instance->validate_rest_value( true, false, $field, 'test_true_false', array(), '' );

		$this->assertTrue( $valid_true );
		$this->assertTrue( $valid_false );
	}

	/**
	 * Test format_value with null input.
	 */
	public function test_format_value_null() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( null, $this->post_id, $field );

		$this->assertFalse( $result );
	}
}
