<?php
/**
 * Tests for the Button Group field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_button_group.
 */
class Test_ACF_Field_Button_Group extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'button_group';
	}

	/**
	 * Get the include path(s) for the field class.
	 *
	 * Button Group depends on Radio which depends on Select.
	 *
	 * @return array
	 */
	protected function get_field_include_path() {
		return array(
			'includes/fields/class-acf-field-select.php',
			'includes/fields/class-acf-field-radio.php',
			'includes/fields/class-acf-field-button-group.php',
		);
	}

	/**
	 * Button Group field instance.
	 *
	 * @var acf_field_button_group
	 */
	protected $field_instance;

	/**
	 * Get a base button group field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_button_group_test',
				'name'          => 'test_button_group',
				'type'          => 'button_group',
				'label'         => 'Test Button Group',
				'required'      => 0,
				'return_format' => 'value',
				'choices'       => array(
					'left'   => 'Left',
					'center' => 'Center',
					'right'  => 'Right',
				),
				'allow_null'    => 0,
				'layout'        => 'horizontal',
			),
			$overrides
		);
	}


	/**
	 * Test format_value with value return format.
	 */
	public function test_format_value_value_format() {
		$field = $this->get_field( array( 'return_format' => 'value' ) );

		$result = $this->field_instance->format_value( 'left', $this->post_id, $field );

		$this->assertEquals( 'left', $result );
	}

	/**
	 * Test format_value with label return format.
	 */
	public function test_format_value_label_format() {
		$field = $this->get_field( array( 'return_format' => 'label' ) );

		$result = $this->field_instance->format_value( 'center', $this->post_id, $field );

		$this->assertEquals( 'Center', $result );
	}

	/**
	 * Test format_value with array return format.
	 */
	public function test_format_value_array_format() {
		$field = $this->get_field( array( 'return_format' => 'array' ) );

		$result = $this->field_instance->format_value( 'right', $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'value', $result );
		$this->assertArrayHasKey( 'label', $result );
		$this->assertEquals( 'right', $result['value'] );
		$this->assertEquals( 'Right', $result['label'] );
	}

	/**
	 * Test format_value returns empty string for empty.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertSame( '', $result );
	}

	/**
	 * Test format_value_for_rest returns the raw value.
	 *
	 * Note: Button group doesn't override format_value_for_rest, so it inherits
	 * the base class behavior which returns the raw value unchanged.
	 * The return_format setting only affects format_value, not REST output.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field( array( 'return_format' => 'value' ) );

		$rest_result = $this->field_instance->format_value_for_rest( 'left', $this->post_id, $field );

		// REST returns raw value, not formatted value.
		$this->assertEquals( 'left', $rest_result );
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
	 * Test load_value returns stored value.
	 */
	public function test_load_value() {
		$field = $this->get_field();

		$result = $this->field_instance->load_value( 'right', $this->post_id, $field );

		$this->assertEquals( 'right', $result );
	}

	/**
	 * Test allow_null option.
	 */
	public function test_allow_null() {
		$field = $this->get_field( array( 'allow_null' => 1 ) );

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertSame( '', $result );
	}

	/**
	 * Test format_value with unknown choice returns value.
	 */
	public function test_format_value_unknown_choice() {
		$field = $this->get_field( array( 'return_format' => 'label' ) );

		$result = $this->field_instance->format_value( 'unknown', $this->post_id, $field );

		// Unknown choices should return the value itself.
		$this->assertEquals( 'unknown', $result );
	}
}
