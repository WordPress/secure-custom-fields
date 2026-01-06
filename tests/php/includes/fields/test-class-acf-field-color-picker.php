<?php
/**
 * Tests for the Color Picker field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_color_picker.
 */
class Test_ACF_Field_Color_Picker extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'color_picker';
	}

	/**
	 * Color Picker field instance.
	 *
	 * @var acf_field_color_picker
	 */
	protected $field_instance;

	/**
	 * Get a base color picker field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'            => 'field_color_picker_test',
				'name'           => 'test_color_picker',
				'type'           => 'color_picker',
				'label'          => 'Test Color Picker',
				'required'       => 0,
				'default_value'  => '',
				'enable_opacity' => 0,
				'return_format'  => 'string',
			),
			$overrides
		);
	}

	/**
	 * Data provider for color values.
	 *
	 * @return array
	 */
	public function color_value_provider() {
		return array(
			'hex 6 digit'   => array( '#FF5733' ),
			'hex 3 digit'   => array( '#F53' ),
			'hex lowercase' => array( '#ff5733' ),
			'black'         => array( '#000000' ),
			'white'         => array( '#FFFFFF' ),
		);
	}

	/**
	 * Test format_value returns color.
	 *
	 * @dataProvider color_value_provider
	 *
	 * @param string $color The color to test.
	 */
	public function test_format_value( $color ) {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( $color, $this->post_id, $field );

		$this->assertEquals( $color, $result );
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
	 * Test format_value_for_rest returns color.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( '#FF5733', $this->post_id, $field );

		$this->assertEquals( '#FF5733', $result );
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
	 * Test format_value with RGBA when opacity enabled.
	 */
	public function test_format_value_rgba() {
		$field = $this->get_field( array( 'enable_opacity' => 1 ) );
		$rgba  = 'rgba(255, 87, 51, 0.5)';

		$result = $this->field_instance->format_value( $rgba, $this->post_id, $field );

		$this->assertEquals( $rgba, $result );
	}
}
