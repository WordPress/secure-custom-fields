<?php
/**
 * Tests for the User field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_user.
 */
class Test_ACF_Field_User extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'user';
	}

	/**
	 * Get a base user field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_user_test',
				'name'          => 'test_user',
				'type'          => 'user',
				'label'         => 'Test User',
				'required'      => 0,
				'multiple'      => 0,
				'return_format' => 'array',
				'role'          => array(),
				'allow_null'    => 0,
			),
			$overrides
		);
	}

	/**
	 * Test format_value returns false for empty multiple.
	 */
	public function test_format_value_empty_multiple() {
		$field = $this->get_field( array( 'multiple' => 1 ) );

		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value handles null input.
	 */
	public function test_format_value_handles_null() {
		$field = $this->get_field( array( 'multiple' => 0 ) );

		$result = $this->field_instance->format_value( null, $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value handles empty string.
	 */
	public function test_format_value_handles_empty_string() {
		$field = $this->get_field( array( 'multiple' => 0 ) );

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test update_value with single user ID.
	 */
	public function test_update_value_single() {
		$field   = $this->get_field( array( 'multiple' => 0 ) );
		$user_id = 42;

		$result = $this->field_instance->update_value( $user_id, $this->post_id, $field );

		$this->assertEquals( $user_id, $result );
	}

	/**
	 * Test update_value with multiple user IDs.
	 */
	public function test_update_value_multiple() {
		$field    = $this->get_field( array( 'multiple' => 1 ) );
		$user_ids = array( 1, 2, 3 );

		$result = $this->field_instance->update_value( $user_ids, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
	}

	/**
	 * Test update_value returns empty string for empty input.
	 */
	public function test_update_value_empty() {
		$field = $this->get_field( array( 'multiple' => 0 ) );

		$result = $this->field_instance->update_value( '', $this->post_id, $field );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test get_rest_schema for single user.
	 */
	public function test_get_rest_schema_single() {
		$field = $this->get_field( array( 'multiple' => 0 ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
	}

	/**
	 * Test get_rest_schema for multiple users.
	 */
	public function test_get_rest_schema_multiple() {
		$field = $this->get_field( array( 'multiple' => 1 ) );

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'array', $schema['type'] );
	}

	/**
	 * Test get_rest_schema includes null type for allow_null.
	 */
	public function test_get_rest_schema_allow_null() {
		$field = $this->get_field(
			array(
				'multiple'   => 0,
				'allow_null' => 1,
			)
		);

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'null', $schema['type'] );
	}

	/**
	 * Test format_value_for_rest returns user ID.
	 */
	public function test_format_value_for_rest_returns_id() {
		$field   = $this->get_field( array( 'multiple' => 0 ) );
		$user_id = 42;

		$result = $this->field_instance->format_value_for_rest( $user_id, $this->post_id, $field );

		$this->assertEquals( $user_id, $result );
	}

	/**
	 * Test format_value_for_rest returns array of IDs for multiple.
	 */
	public function test_format_value_for_rest_multiple() {
		$field    = $this->get_field( array( 'multiple' => 1 ) );
		$user_ids = array( 1, 2, 3 );

		$result = $this->field_instance->format_value_for_rest( $user_ids, $this->post_id, $field );

		$this->assertEquals( $user_ids, $result );
	}
}
