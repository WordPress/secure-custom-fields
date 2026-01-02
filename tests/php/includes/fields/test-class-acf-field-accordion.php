<?php
/**
 * Tests for the Accordion field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_accordion.
 *
 * Note: Accordion is a layout-only field with no data storage.
 * Tests focus on load_field behavior and REST schema.
 */
class Test_ACF_Field_Accordion extends Abstract_ACF_Field_Test {

	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'accordion';
	}

	/**
	 * Get a base accordion field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'          => 'field_accordion_test',
				'name'         => 'test_accordion',
				'type'         => 'accordion',
				'label'        => 'Test Accordion',
				'open'         => 0,
				'multi_expand' => 0,
				'endpoint'     => 0,
			),
			$overrides
		);
	}

	/**
	 * Test load_field removes name to avoid caching issues.
	 */
	public function test_load_field_removes_name() {
		$field = $this->get_field( array( 'name' => 'my_accordion' ) );

		$loaded = $this->field_instance->load_field( $field );

		$this->assertEmpty( $loaded['name'] );
	}

	/**
	 * Test load_field sets required to 0.
	 */
	public function test_load_field_sets_required_zero() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$loaded = $this->field_instance->load_field( $field );

		$this->assertEquals( 0, $loaded['required'] );
	}

	/**
	 * Test load_field sets value to false.
	 */
	public function test_load_field_sets_value_false() {
		$field = $this->get_field();

		$loaded = $this->field_instance->load_field( $field );

		$this->assertFalse( $loaded['value'] );
	}

	/**
	 * Test get_rest_schema returns null type for layout field.
	 */
	public function test_get_rest_schema_returns_null_type() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'null', $schema['type'] );
	}

	/**
	 * Test field has correct category.
	 */
	public function test_field_category_is_layout() {
		$this->assertEquals( 'layout', $this->field_instance->category );
	}

	/**
	 * Test field does not show in REST by default.
	 */
	public function test_show_in_rest_is_false() {
		$this->assertFalse( $this->field_instance->show_in_rest );
	}

	/**
	 * Test field does not support required.
	 */
	public function test_does_not_support_required() {
		$this->assertArrayHasKey( 'required', $this->field_instance->supports );
		$this->assertFalse( $this->field_instance->supports['required'] );
	}

	/**
	 * Test field does not support bindings.
	 */
	public function test_does_not_support_bindings() {
		$this->assertArrayHasKey( 'bindings', $this->field_instance->supports );
		$this->assertFalse( $this->field_instance->supports['bindings'] );
	}

	/**
	 * Test default values are set correctly.
	 */
	public function test_default_values() {
		$this->assertEquals( 0, $this->field_instance->defaults['open'] );
		$this->assertEquals( 0, $this->field_instance->defaults['multi_expand'] );
		$this->assertEquals( 0, $this->field_instance->defaults['endpoint'] );
	}

	/**
	 * Test load_field with open option enabled.
	 */
	public function test_load_field_preserves_open_option() {
		$field = $this->get_field( array( 'open' => 1 ) );

		$loaded = $this->field_instance->load_field( $field );

		$this->assertEquals( 1, $loaded['open'] );
	}

	/**
	 * Test load_field with multi_expand option enabled.
	 */
	public function test_load_field_preserves_multi_expand_option() {
		$field = $this->get_field( array( 'multi_expand' => 1 ) );

		$loaded = $this->field_instance->load_field( $field );

		$this->assertEquals( 1, $loaded['multi_expand'] );
	}

	/**
	 * Test load_field with endpoint option enabled.
	 */
	public function test_load_field_preserves_endpoint_option() {
		$field = $this->get_field( array( 'endpoint' => 1 ) );

		$loaded = $this->field_instance->load_field( $field );

		$this->assertEquals( 1, $loaded['endpoint'] );
	}
}
