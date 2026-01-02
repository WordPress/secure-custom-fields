<?php
/**
 * Tests for the File field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_file.
 */
class Test_ACF_Field_File extends Abstract_ACF_Field_Test {

	/**
	 * Test attachment ID.
	 *
	 * @var int
	 */
	protected $attachment_id;

	/**
	 * Get the field type for this test class.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'file';
	}

	/**
	 * Get a base file field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_file_test',
				'name'          => 'test_file',
				'type'          => 'file',
				'label'         => 'Test File',
				'required'      => 0,
				'return_format' => 'array',
			),
			$overrides
		);
	}

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		// Create a test attachment.
		$this->attachment_id = wp_insert_attachment(
			array(
				'post_title'     => 'Test File',
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			),
			'/tmp/test-file.pdf',
			$this->post_id
		);

		wp_update_attachment_metadata(
			$this->attachment_id,
			array(
				'file' => 'test-file.pdf',
			)
		);
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		if ( $this->attachment_id ) {
			wp_delete_attachment( $this->attachment_id, true );
		}
		parent::tear_down();
	}

	/**
	 * Data provider for return formats.
	 *
	 * @return array
	 */
	public function return_format_provider() {
		return array(
			'id format'    => array( 'id' ),
			'url format'   => array( 'url' ),
			'array format' => array( 'array' ),
		);
	}

	/**
	 * Test that format_value_for_rest matches format_value for all return formats.
	 *
	 * @dataProvider return_format_provider
	 *
	 * @param string $return_format The return format setting.
	 */
	public function test_rest_format_matches_standard_format( $return_format ) {
		$field = $this->get_field( array( 'return_format' => $return_format ) );

		$rest_result     = $this->field_instance->format_value_for_rest( $this->attachment_id, $this->post_id, $field );
		$standard_result = $this->field_instance->format_value( $this->attachment_id, $this->post_id, $field );

		$this->assertEquals( $standard_result, $rest_result );
	}

	/**
	 * Test URL format returns a string containing the filename.
	 */
	public function test_url_format_returns_string_with_filename() {
		$field = $this->get_field( array( 'return_format' => 'url' ) );

		$result = $this->field_instance->format_value_for_rest( $this->attachment_id, $this->post_id, $field );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'test-file.pdf', $result );
	}

	/**
	 * Test array format returns expected structure.
	 */
	public function test_array_format_returns_expected_structure() {
		$field = $this->get_field( array( 'return_format' => 'array' ) );

		$result = $this->field_instance->format_value_for_rest( $this->attachment_id, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'ID', $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertEquals( $this->attachment_id, $result['ID'] );
	}

	/**
	 * Test format_value returns false for empty value.
	 */
	public function test_format_value_empty_returns_false() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test format_value returns false for invalid attachment.
	 */
	public function test_format_value_invalid_attachment_returns_false() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( 999999, $this->post_id, $field );

		$this->assertFalse( $result );
	}

	/**
	 * Test ID format returns integer.
	 */
	public function test_id_format_returns_integer() {
		$field = $this->get_field( array( 'return_format' => 'id' ) );

		$result = $this->field_instance->format_value( $this->attachment_id, $this->post_id, $field );

		$this->assertIsInt( $result );
		$this->assertEquals( $this->attachment_id, $result );
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
	 * Test update_value saves attachment ID.
	 */
	public function test_update_value_saves_id() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( $this->attachment_id, $this->post_id, $field );

		$this->assertEquals( $this->attachment_id, $result );
	}

	/**
	 * Test update_value handles empty value.
	 */
	public function test_update_value_handles_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( '', $this->post_id, $field );

		$this->assertEmpty( $result );
	}
}
