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
	 * Get the include path(s) for the field class.
	 *
	 * Image extends File, so we need to load File first.
	 *
	 * @return array
	 */
	protected function get_field_include_path() {
		return array(
			'includes/fields/class-acf-field-file.php',
			'includes/fields/class-acf-field-image.php',
		);
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
	 * Test validate_value accepts an image attachment.
	 */
	public function test_validate_value_accepts_image_attachment() {
		$valid = $this->field_instance->validate_value( true, $this->attachment_id, $this->get_field(), 'acf[field_image_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value rejects an attachment that is not an image.
	 */
	public function test_validate_value_rejects_non_image_attachment() {
		$pdf_id = wp_insert_attachment(
			array(
				'post_title'     => 'Test Document',
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			),
			'/tmp/test-document.pdf',
			$this->post_id
		);

		$valid = $this->field_instance->validate_value( true, $pdf_id, $this->get_field(), 'acf[field_image_test]' );

		wp_delete_attachment( $pdf_id, true );
		$this->assertSame( 'File must be a valid image.', $valid );
	}

	/**
	 * Test validate_value allows an empty value and keeps earlier errors.
	 */
	public function test_validate_value_empty_and_earlier_error() {
		$this->assertTrue( $this->field_instance->validate_value( true, '', $this->get_field(), 'acf[field_image_test]' ) );
		$this->assertSame( 'Earlier error.', $this->field_instance->validate_value( 'Earlier error.', 999999, $this->get_field(), 'acf[field_image_test]' ) );
	}

	/**
	 * Test the basic uploader limits the file picker to images.
	 */
	public function test_basic_uploader_accept_attribute() {
		$uploader = acf_get_setting( 'uploader' );
		acf_update_setting( 'uploader', 'basic' );

		$field = $this->get_field(
			array(
				'id'         => 'acf-field_image_test',
				'value'      => '',
				'library'    => 'all',
				'mime_types' => '',
			)
		);

		ob_start();
		$this->field_instance->render_field( $field );
		$default = ob_get_clean();

		ob_start();
		$this->field_instance->render_field( array_merge( $field, array( 'mime_types' => 'jpg, .png' ) ) );
		$restricted = ob_get_clean();

		acf_update_setting( 'uploader', $uploader );

		$this->assertStringContainsString( 'accept="image/*"', $default );
		$this->assertStringContainsString( 'accept=".jpg,.png"', $restricted );
	}
}
