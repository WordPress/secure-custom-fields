<?php
/**
 * Tests for the Gallery field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_gallery.
 */
class Test_ACF_Field_Gallery extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'gallery';
	}

	/**
	 * Get a base gallery field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_gallery_test',
				'name'          => 'test_gallery',
				'type'          => 'gallery',
				'label'         => 'Test Gallery',
				'required'      => 0,
				'min'           => 0,
				'max'           => 0,
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'library'       => 'all',
				'min_width'     => 0,
				'min_height'    => 0,
				'max_width'     => 0,
				'max_height'    => 0,
				'mime_types'    => '',
			),
			$overrides
		);
	}

	/**
	 * Test format_value returns false for empty gallery.
	 */
	public function test_format_value_empty_gallery() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value handles false input.
	 */
	public function test_format_value_handles_false() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( false, $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value handles null input.
	 */
	public function test_format_value_handles_null() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( null, $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test update_value with array of IDs returns array.
	 */
	public function test_update_value_returns_array() {
		$field = $this->get_field();
		$ids   = array( 1, 2, 3 );

		$result = $this->field_instance->update_value( $ids, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test update_value returns empty array for empty input.
	 */
	public function test_update_value_empty_returns_empty_array() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( array(), $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test update_value filters non-numeric values.
	 */
	public function test_update_value_filters_non_numeric() {
		$field = $this->get_field();
		$ids   = array( 1, 'invalid', 2, null, 3 );

		$result = $this->field_instance->update_value( $ids, $this->post_id, $field );

		$this->assertIsArray( $result );
		// Only numeric values should remain.
		foreach ( $result as $id ) {
			$this->assertTrue( is_numeric( $id ) );
		}
	}

	/**
	 * Test validate_value passes for valid gallery.
	 */
	public function test_validate_value_valid() {
		$field = $this->get_field( array( 'required' => 1 ) );
		$ids   = array( 1, 2, 3 );

		$valid = $this->field_instance->validate_value( true, $ids, $field, 'acf[field_gallery_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with min constraint not met returns error.
	 */
	public function test_validate_value_min_not_met() {
		$field = $this->get_field( array( 'min' => 5 ) );
		$ids   = array( 1, 2, 3 );

		$valid = $this->field_instance->validate_value( true, $ids, $field, 'acf[field_gallery_test]' );

		$this->assertIsString( $valid );
		$this->assertStringContainsString( '5', $valid );
	}

	/**
	 * Test validate_value with max constraint.
	 *
	 * Note: The gallery field's max validation is handled client-side only.
	 * Server-side validate_value only checks the min constraint.
	 */
	public function test_validate_value_max_not_server_validated() {
		$field = $this->get_field( array( 'max' => 2 ) );
		$ids   = array( 1, 2, 3 );

		$valid = $this->field_instance->validate_value( true, $ids, $field, 'acf[field_gallery_test]' );

		// Max is not validated server-side, only min is.
		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value passes when within min/max range.
	 */
	public function test_validate_value_within_range() {
		$field = $this->get_field(
			array(
				'min' => 2,
				'max' => 5,
			)
		);
		$ids   = array( 1, 2, 3 );

		$valid = $this->field_instance->validate_value( true, $ids, $field, 'acf[field_gallery_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'array', $schema['type'] );
		$this->assertArrayHasKey( 'items', $schema );
	}

	/**
	 * Test get_rest_schema for ID return format.
	 */
	public function test_get_rest_schema_id_format() {
		$field = $this->get_field( array( 'return_format' => 'id' ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertEquals( 'number', $schema['items']['type'] );
	}

	/**
	 * Test get_rest_schema includes items schema.
	 */
	public function test_get_rest_schema_has_items() {
		$field = $this->get_field( array( 'return_format' => 'array' ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertArrayHasKey( 'items', $schema );
		$this->assertIsArray( $schema['items'] );
	}
}
