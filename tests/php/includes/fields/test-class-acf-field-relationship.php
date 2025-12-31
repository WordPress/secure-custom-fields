<?php
/**
 * Tests for the Relationship field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_relationship.
 */
class Test_ACF_Field_Relationship extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'relationship';
	}

	/**
	 * Get a base relationship field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_relationship_test',
				'name'          => 'test_relationship',
				'type'          => 'relationship',
				'label'         => 'Test Relationship',
				'required'      => 0,
				'min'           => 0,
				'max'           => 0,
				'return_format' => 'object',
				'post_type'     => array(),
				'taxonomy'      => array(),
				'filters'       => array( 'search', 'post_type', 'taxonomy' ),
				'elements'      => array(),
			),
			$overrides
		);
	}

	/**
	 * Test format_value with ID return format returns array of integers.
	 */
	public function test_format_value_id_format() {
		$field    = $this->get_field( array( 'return_format' => 'id' ) );
		$post_ids = array( 1, 2, 3 );

		$result = $this->field_instance->format_value( $post_ids, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		foreach ( $result as $id ) {
			$this->assertIsInt( $id );
		}
	}

	/**
	 * Test format_value returns empty for empty input.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test format_value handles false input.
	 */
	public function test_format_value_handles_false() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( false, $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test update_value with valid post IDs.
	 */
	public function test_update_value() {
		$field    = $this->get_field();
		$post_ids = array( 1, 2, 3 );

		$result = $this->field_instance->update_value( $post_ids, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test update_value returns empty for empty input.
	 */
	public function test_update_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( array(), $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test validate_value with valid selection.
	 */
	public function test_validate_value_valid() {
		$field    = $this->get_field( array( 'required' => 1 ) );
		$post_ids = array( 1, 2, 3 );

		$valid = $this->field_instance->validate_value( true, $post_ids, $field, 'acf[field_relationship_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with min constraint not met.
	 */
	public function test_validate_value_min_not_met() {
		$field    = $this->get_field( array( 'min' => 5 ) );
		$post_ids = array( 1, 2, 3 );

		$valid = $this->field_instance->validate_value( true, $post_ids, $field, 'acf[field_relationship_test]' );

		$this->assertIsString( $valid );
		$this->assertStringContainsString( '5', $valid );
	}

	/**
	 * Test validate_value with max constraint.
	 *
	 * Note: The relationship field's max validation is handled client-side only.
	 * Server-side validate_value only checks the min constraint.
	 */
	public function test_validate_value_max_not_server_validated() {
		$field    = $this->get_field( array( 'max' => 2 ) );
		$post_ids = array( 1, 2, 3 );

		$valid = $this->field_instance->validate_value( true, $post_ids, $field, 'acf[field_relationship_test]' );

		// Max is not validated server-side, only min is.
		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value within range passes.
	 */
	public function test_validate_value_within_range() {
		$field    = $this->get_field(
			array(
				'min' => 2,
				'max' => 5,
			)
		);
		$post_ids = array( 1, 2, 3 );

		$valid = $this->field_instance->validate_value( true, $post_ids, $field, 'acf[field_relationship_test]' );

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

		$this->assertEquals( 'integer', $schema['items']['type'] );
	}

	/**
	 * Test relationship maintains order with ID format.
	 */
	public function test_relationship_maintains_order() {
		$field    = $this->get_field( array( 'return_format' => 'id' ) );
		$post_ids = array( 3, 1, 2 );

		$result = $this->field_instance->format_value( $post_ids, $this->post_id, $field );

		$this->assertEquals( $post_ids, $result );
	}
}
