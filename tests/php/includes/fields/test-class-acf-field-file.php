<?php
/**
 * Tests for the File field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for acf_field_file.
 */
class Test_ACF_Field_File extends BaseTestCase {

	/**
	 * Test attachment ID.
	 *
	 * @var int
	 */
	protected $attachment_id;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		// Ensure SCF field types are loaded.
		if ( ! class_exists( 'acf_field_file' ) ) {
			acf_include( 'includes/fields/class-acf-field-file.php' );
		}

		// Create a test post.
		$this->post_id = wp_insert_post(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

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
		if ( $this->post_id ) {
			wp_delete_post( $this->post_id, true );
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
		$field_instance = acf_get_field_type( 'file' );
		$field          = array(
			'type'          => 'file',
			'name'          => 'test_file',
			'return_format' => $return_format,
		);

		$rest_result     = $field_instance->format_value_for_rest( $this->attachment_id, $this->post_id, $field );
		$standard_result = $field_instance->format_value( $this->attachment_id, $this->post_id, $field );

		$this->assertEquals( $standard_result, $rest_result );
	}

	/**
	 * Test URL format returns a string containing the filename.
	 */
	public function test_url_format_returns_string_with_filename() {
		$field_instance = acf_get_field_type( 'file' );
		$field          = array(
			'type'          => 'file',
			'name'          => 'test_file',
			'return_format' => 'url',
		);

		$result = $field_instance->format_value_for_rest( $this->attachment_id, $this->post_id, $field );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'test-file.pdf', $result );
	}

	/**
	 * Test array format returns expected structure.
	 */
	public function test_array_format_returns_expected_structure() {
		$field_instance = acf_get_field_type( 'file' );
		$field          = array(
			'type'          => 'file',
			'name'          => 'test_file',
			'return_format' => 'array',
		);

		$result = $field_instance->format_value_for_rest( $this->attachment_id, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'ID', $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertEquals( $this->attachment_id, $result['ID'] );
	}
}
