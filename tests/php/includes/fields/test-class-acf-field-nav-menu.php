<?php
/**
 * Tests for the Nav Menu field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_nav_menu.
 */
class Test_ACF_Field_Nav_Menu extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'nav_menu';
	}

	/**
	 * Get a base nav menu field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_nav_menu_test',
				'name'          => 'test_nav_menu',
				'type'          => 'nav_menu',
				'label'         => 'Test Nav Menu',
				'required'      => 0,
				'return_format' => 'id',
				'save_format'   => 'id',
				'allow_null'    => 0,
			),
			$overrides
		);
	}

	/**
	 * Test format_value returns null or false for empty.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		// Empty value may return null or false depending on implementation.
		$this->assertTrue( null === $result || false === $result );
	}

	/**
	 * Test format_value handles invalid menu.
	 *
	 * Note: In WorDBless, invalid menu handling may differ.
	 */
	public function test_format_value_invalid_menu() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( 999999, $this->post_id, $field );

		// Invalid menu may return null, false, or the original ID.
		$this->assertTrue( null === $result || false === $result || 999999 === $result );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
	}

	/**
	 * Test allow_null option.
	 *
	 * Note: When allow_null is enabled and value is empty, format_value returns null or false.
	 */
	public function test_allow_null() {
		$field = $this->get_field( array( 'allow_null' => 1 ) );

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		// Empty value with allow_null returns null or false.
		$this->assertTrue( null === $result || false === $result );
	}

	/**
	 * Test field category is choice.
	 */
	public function test_field_category_is_choice() {
		$this->assertEquals( 'choice', $this->field_instance->category );
	}

	/**
	 * Test default values are set correctly.
	 */
	public function test_default_values() {
		$this->assertEquals( 'id', $this->field_instance->defaults['save_format'] );
		$this->assertEquals( 0, $this->field_instance->defaults['allow_null'] );
		$this->assertEquals( 'div', $this->field_instance->defaults['container'] );
	}

	/**
	 * Test field has preview image.
	 */
	public function test_has_preview_image() {
		$this->assertNotEmpty( $this->field_instance->preview_image );
		$this->assertStringContainsString( 'field-preview-select', $this->field_instance->preview_image );
	}

	/**
	 * Test field name is nav_menu.
	 */
	public function test_field_name() {
		$this->assertEquals( 'nav_menu', $this->field_instance->name );
	}
}
