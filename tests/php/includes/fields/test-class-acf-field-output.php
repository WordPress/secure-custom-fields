<?php
/**
 * Tests for the Output field type (deprecated).
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_output.
 *
 * Note: This field type is deprecated since ACF 6.3.2 and does not output anything.
 */
class Test_ACF_Field_Output extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'output';
	}

	/**
	 * Get a base output field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'   => 'field_output_test',
				'name'  => 'test_output',
				'type'  => 'output',
				'label' => 'Test Output',
				'html'  => false,
			),
			$overrides
		);
	}

	/**
	 * Test that the field type is registered but not public.
	 */
	public function test_field_is_not_public() {
		$this->assertFalse( $this->field_instance->public );
	}

	/**
	 * Test render_field returns false (deprecated functionality).
	 *
	 * @expectedDeprecated acf_field_output::render_field
	 */
	public function test_render_field_returns_false() {
		$field = $this->get_field();

		$result = $this->field_instance->render_field( $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test default values.
	 */
	public function test_defaults() {
		$this->assertArrayHasKey( 'html', $this->field_instance->defaults );
		$this->assertFalse( $this->field_instance->defaults['html'] );
	}
}
