<?php
/**
 * Tests for the Image field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_image.
 */
class Test_ACF_Field_Image extends Abstract_ACF_Field_Test {

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
		return 'image';
	}

	/**
	 * Get the include path for the field class.
	 *
	 * @return string
	 */
	protected function get_field_include_path() {
		return 'includes/fields/class-acf-field-image.php';
	}

	/**
	 * Get a base image field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_image_test',
				'name'          => 'test_image',
				'type'          => 'image',
				'label'         => 'Test Image',
				'required'      => 0,
				'return_format' => 'array',
				'preview_size'  => 'medium',
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
				'post_title'     => 'Test Image',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			),
			'/tmp/test-image.jpg',
			$this->post_id
		);

		wp_update_attachment_metadata(
			$this->attachment_id,
			array(
				'width'  => 1200,
				'height' => 800,
				'file'   => 'test-image.jpg',
				'sizes'  => array(),
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
		$this->assertStringContainsString( 'test-image.jpg', $result );
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
	 * Test field category is content.
	 */
	public function test_field_category_is_content() {
		$this->assertEquals( 'content', $this->field_instance->category );
	}

	/**
	 * Test array format includes image dimensions.
	 */
	public function test_array_format_includes_dimensions() {
		$field = $this->get_field( array( 'return_format' => 'array' ) );

		$result = $this->field_instance->format_value( $this->attachment_id, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'width', $result );
		$this->assertArrayHasKey( 'height', $result );
	}
}
