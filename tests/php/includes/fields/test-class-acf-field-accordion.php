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
}
