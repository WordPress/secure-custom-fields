<?php
/**
 * Tests for the Separator field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_separator.
 *
 * Note: This is a layout-only field that displays a visual separator.
 * It does not store or process any data.
 */
class Test_ACF_Field_Separator extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'separator';
	}

	/**
	 * Get a base separator field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'   => 'field_separator_test',
				'name'  => 'test_separator',
				'type'  => 'separator',
				'label' => 'Test Separator',
			),
			$overrides
		);
	}

	/**
	 * Test field type is in layout category.
	 */
	public function test_category_is_layout() {
		$this->assertEquals( 'layout', $this->field_instance->category );
	}

	/**
	 * Test required is not supported.
	 */
	public function test_required_not_supported() {
		$this->assertArrayHasKey( 'required', $this->field_instance->supports );
		$this->assertFalse( $this->field_instance->supports['required'] );
	}

	/**
	 * Test load_field removes name to avoid caching issues.
	 */
	public function test_load_field_removes_name() {
		$field = $this->get_field( array( 'name' => 'some_name' ) );

		$result = $this->field_instance->load_field( $field );

		$this->assertEmpty( $result['name'] );
	}

	/**
	 * Test load_field removes required.
	 */
	public function test_load_field_removes_required() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$result = $this->field_instance->load_field( $field );

		$this->assertEquals( 0, $result['required'] );
	}

	/**
	 * Test load_field sets value to false.
	 */
	public function test_load_field_sets_value_false() {
		$field = $this->get_field();

		$result = $this->field_instance->load_field( $field );

		$this->assertFalse( $result['value'] );
	}

	/**
	 * Test field does not support bindings.
	 */
	public function test_does_not_support_bindings() {
		// Separator only has 'required' in supports array.
		$this->assertArrayNotHasKey( 'bindings', $this->field_instance->supports );
	}

	/**
	 * Test render_field returns nothing (void function).
	 */
	public function test_render_field_outputs_nothing() {
		$field = $this->get_field();

		ob_start();
		$this->field_instance->render_field( $field );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test field has preview image.
	 */
	public function test_has_preview_image() {
		$this->assertNotEmpty( $this->field_instance->preview_image );
		$this->assertStringContainsString( 'field-preview-separator', $this->field_instance->preview_image );
	}
}
