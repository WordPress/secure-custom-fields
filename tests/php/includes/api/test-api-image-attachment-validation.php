<?php
/**
 * Tests that Image and Gallery fields only accept image attachments.
 *
 * @package wordpress/secure-custom-fields
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions -- Tests use throwaway file fixtures.
 */

use WorDBless\BaseTestCase;

/**
 * Tests the image attachment validation helpers.
 *
 * @group api
 * @group media
 * @group security
 *
 * @covers ::acf_is_image_field
 * @covers ::acf_get_image_mime_types
 * @covers ::acf_validate_is_image_attachment
 * @covers ::acf_validate_attachment
 * @covers ::acf_upload_file
 */
class Test_API_Image_Attachment_Validation extends BaseTestCase {

	/**
	 * A valid 1x1 PNG.
	 */
	private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	/**
	 * Temporary files created by the current test.
	 *
	 * @var string[]
	 */
	private $temp_files = array();

	/**
	 * Attachment IDs created by the current test.
	 *
	 * @var int[]
	 */
	private $attachment_ids = array();

	/**
	 * Test filters that must be removed during cleanup.
	 *
	 * @var array
	 */
	private $test_filters = array();

	/**
	 * Removes fixtures and filters.
	 */
	public function tear_down() {
		foreach ( $this->test_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}
		$this->test_filters = array();

		foreach ( $this->attachment_ids as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
		$this->attachment_ids = array();

		foreach ( $this->temp_files as $temp_file ) {
			if ( file_exists( $temp_file ) ) {
				unlink( $temp_file );
			}
		}
		$this->temp_files = array();

		acf_remove_local_field( 'field_image_upload_mimes' );

		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	// =========================================================================
	// acf_is_image_field()
	// =========================================================================

	/**
	 * Image and Gallery fields are image fields; other types are not.
	 */
	public function test_is_image_field_matches_image_and_gallery_types() {
		$this->assertTrue( acf_is_image_field( $this->field( 'image' ) ) );
		$this->assertTrue( acf_is_image_field( $this->field( 'gallery' ) ) );
		$this->assertFalse( acf_is_image_field( $this->field( 'file' ) ) );
		$this->assertFalse( acf_is_image_field( false ) );
	}

	/**
	 * The type, name, key and generic filters can change the result.
	 */
	public function test_is_image_field_filters() {
		$this->add_test_filter( 'acf/is_image_field/type=file', '__return_true' );
		$this->assertTrue( acf_is_image_field( $this->field( 'file' ) ) );

		$this->add_test_filter( 'acf/is_image_field/key=field_test_image', '__return_false' );
		$this->assertFalse( acf_is_image_field( $this->field( 'image' ) ) );
	}

	// =========================================================================
	// acf_get_image_mime_types()
	// =========================================================================

	/**
	 * Only image MIME types are returned.
	 */
	public function test_get_image_mime_types_only_returns_images() {
		$mimes = acf_get_image_mime_types();

		$this->assertNotEmpty( $mimes );
		$this->assertArrayHasKey( 'png', $mimes );
		$this->assertArrayNotHasKey( 'pdf', $mimes );
		$this->assertArrayNotHasKey( 'txt', $mimes );

		foreach ( $mimes as $mime ) {
			$this->assertStringStartsWith( 'image/', $mime );
		}
	}

	/**
	 * Field-specific filters can narrow the allowed types.
	 */
	public function test_get_image_mime_types_field_filter() {
		$this->add_test_filter(
			'acf/get_image_mime_types/key=field_test_image',
			function () {
				return array( 'png' => 'image/png' );
			}
		);

		$this->assertSame( array( 'png' => 'image/png' ), acf_get_image_mime_types( $this->field( 'image' ) ) );
		$this->assertArrayHasKey( 'png', acf_get_image_mime_types() );
		$this->assertGreaterThan( 1, count( acf_get_image_mime_types() ) );
	}

	// =========================================================================
	// acf_validate_is_image_attachment(): upload context
	// =========================================================================

	/**
	 * A real image uploaded to an Image field passes.
	 */
	public function test_upload_accepts_real_image() {
		$errors = acf_validate_is_image_attachment( array(), array(), $this->upload_fixture( $this->png_bytes(), 'photo.png' ), $this->field( 'image' ), 'upload' );

		$this->assertSame( array(), $errors );
	}

	/**
	 * A non-image file renamed with an image extension is rejected.
	 */
	public function test_upload_rejects_non_image_with_image_extension() {
		$errors = acf_validate_is_image_attachment( array(), array(), $this->upload_fixture( '<?php echo 1;', 'photo.png' ), $this->field( 'gallery' ), 'upload' );

		$this->assertSame( array( 'invalid_image' => 'File must be a valid image.' ), $errors );
	}

	/**
	 * A non-image extension is rejected.
	 */
	public function test_upload_rejects_non_image_extension() {
		$errors = acf_validate_is_image_attachment( array(), array(), $this->upload_fixture( 'plain text', 'notes.txt' ), $this->field( 'image' ), 'upload' );

		$this->assertArrayHasKey( 'invalid_image', $errors );
	}

	/**
	 * An SVG passes when SVG is an allowed image type, because the binary
	 * image check cannot read vector images.
	 */
	public function test_upload_accepts_svg_when_allowed() {
		$this->allow_svg();

		$errors = acf_validate_is_image_attachment( array(), array(), $this->upload_fixture( $this->svg_bytes(), 'logo.svg' ), $this->field( 'image' ), 'upload' );

		$this->assertSame( array(), $errors );
	}

	/**
	 * A file named .svg that is not an SVG is still rejected.
	 */
	public function test_upload_rejects_non_svg_with_svg_extension() {
		$this->allow_svg();

		$errors = acf_validate_is_image_attachment( array(), array(), $this->upload_fixture( '<?php echo 1;', 'logo.svg' ), $this->field( 'image' ), 'upload' );

		$this->assertArrayHasKey( 'invalid_image', $errors );
	}

	/**
	 * Removing SVG from the vector types applies the binary image check again.
	 */
	public function test_vector_mime_types_filter_can_remove_svg() {
		if ( PHP_VERSION_ID >= 80500 ) {
			$this->markTestSkipped( 'PHP 8.5+ reads SVG as an image natively, so the binary image check accepts it.' );
		}

		$this->allow_svg();
		$this->add_test_filter( 'acf/validate_is_image_attachment/vector_mime_types', '__return_empty_array' );

		$errors = acf_validate_is_image_attachment( array(), array(), $this->upload_fixture( $this->svg_bytes(), 'logo.svg' ), $this->field( 'image' ), 'upload' );

		$this->assertArrayHasKey( 'invalid_image', $errors );
	}

	/**
	 * A missing temporary file is rejected.
	 */
	public function test_upload_rejects_missing_temp_file() {
		$attachment = array(
			'name'     => 'photo.png',
			'type'     => 'image/png',
			'tmp_name' => '/nonexistent/scf-upload',
		);

		$errors = acf_validate_is_image_attachment( array(), array(), $attachment, $this->field( 'image' ), 'upload' );

		$this->assertArrayHasKey( 'invalid_image', $errors );
	}

	/**
	 * Fields that are not image fields are not checked.
	 */
	public function test_non_image_fields_are_not_checked() {
		$errors = acf_validate_is_image_attachment( array(), array(), $this->upload_fixture( 'plain text', 'notes.txt' ), $this->field( 'file' ), 'upload' );

		$this->assertSame( array(), $errors );
	}

	/**
	 * The error message and the resulting errors can be filtered.
	 */
	public function test_error_message_and_errors_are_filterable() {
		$this->add_test_filter(
			'acf/validate_is_image_attachment/error_message',
			function () {
				return 'Custom message.';
			}
		);
		$errors = acf_validate_is_image_attachment( array(), array(), $this->upload_fixture( 'plain text', 'notes.txt' ), $this->field( 'image' ), 'upload' );
		$this->assertSame( array( 'invalid_image' => 'Custom message.' ), $errors );

		$this->add_test_filter( 'acf/validate_is_image_attachment/key=field_test_image', '__return_empty_array' );
		$errors = acf_validate_is_image_attachment( array(), array(), $this->upload_fixture( 'plain text', 'notes.txt' ), $this->field( 'image' ), 'upload' );
		$this->assertSame( array(), $errors );
	}

	// =========================================================================
	// acf_validate_is_image_attachment(): prepare and basic upload contexts
	// =========================================================================

	/**
	 * Media library attachments are checked by attachment ID.
	 */
	public function test_prepare_checks_attachment_by_id() {
		$image_id = $this->create_attachment( 'photo.jpg', 'image/jpeg' );
		$pdf_id   = $this->create_attachment( 'document.pdf', 'application/pdf' );

		$this->assertSame( array(), acf_validate_is_image_attachment( array(), array(), array( 'id' => $image_id ), $this->field( 'image' ), 'prepare' ) );
		$this->assertArrayHasKey( 'invalid_image', acf_validate_is_image_attachment( array(), array(), array( 'id' => $pdf_id ), $this->field( 'image' ), 'prepare' ) );
	}

	/**
	 * Without an attachment ID, the reported MIME type is checked.
	 */
	public function test_prepare_without_id_checks_mime() {
		$this->assertSame( array(), acf_validate_is_image_attachment( array(), array(), array( 'mime' => 'image/png' ), $this->field( 'image' ), 'prepare' ) );
		$this->assertArrayHasKey( 'invalid_image', acf_validate_is_image_attachment( array(), array(), array( 'mime' => 'application/pdf' ), $this->field( 'image' ), 'prepare' ) );
	}

	/**
	 * Basic uploader pre-save checks use the reported type.
	 */
	public function test_basic_upload_checks_reported_type() {
		$this->assertSame( array(), acf_validate_is_image_attachment( array(), array(), array( 'type' => 'image/png' ), $this->field( 'image' ), 'basic_upload' ) );
		$this->assertArrayHasKey( 'invalid_image', acf_validate_is_image_attachment( array(), array(), array( 'type' => 'text/plain' ), $this->field( 'image' ), 'basic_upload' ) );
	}

	// =========================================================================
	// Wiring
	// =========================================================================

	/**
	 * The Image and Gallery fields run the check through acf_validate_attachment().
	 */
	public function test_validate_attachment_runs_the_check_for_image_and_gallery_fields() {
		acf_include( 'includes/fields/class-acf-field-image.php' );
		acf_include( 'includes/fields/class-acf-field-gallery.php' );
		$field_types = array(
			'image'   => 'acf_field_image',
			'gallery' => 'acf_field_gallery',
		);
		foreach ( $field_types as $type => $class_name ) {
			if ( ! has_filter( "acf/validate_attachment/type={$type}", 'acf_validate_is_image_attachment' ) ) {
				acf_register_field_type( $class_name );
			}
		}

		$attachment = $this->upload_fixture( 'plain text', 'photo.png' );

		$this->assertArrayHasKey( 'invalid_image', acf_validate_attachment( $attachment, $this->field( 'image' ), 'upload' ) );
		$this->assertArrayHasKey( 'invalid_image', acf_validate_attachment( $attachment, $this->field( 'gallery' ), 'upload' ) );
		$this->assertArrayNotHasKey( 'invalid_image', acf_validate_attachment( $attachment, $this->field( 'file' ), 'upload' ) );
	}

	/**
	 * Uploads for an image field only allow image MIME types.
	 */
	public function test_upload_file_restricts_mimes_for_image_fields() {
		acf_add_local_field(
			array(
				'key'    => 'field_image_upload_mimes',
				'name'   => 'image_upload_mimes',
				'label'  => 'Image',
				'type'   => 'image',
				'parent' => 'group_image_upload_mimes',
			)
		);

		$captured = null;
		$this->add_test_filter(
			'wp_handle_upload_overrides',
			function ( $overrides ) use ( &$captured ) {
				$captured = $overrides;

				// Stop before the file is moved; only the overrides matter here.
				$overrides['upload_error_handler'] = function () {
					return array( 'error' => 'stopped' );
				};
				return $overrides;
			}
		);

		$_POST['_acfuploader'] = 'field_image_upload_mimes';
		acf_upload_file( $this->upload_fixture( $this->png_bytes(), 'photo.png' ) );

		$this->assertSame( acf_get_image_mime_types( acf_get_field( 'field_image_upload_mimes' ) ), $captured['mimes'] );
	}

	/**
	 * Uploads without an image field keep the default MIME types.
	 */
	public function test_upload_file_keeps_default_mimes_without_image_field() {
		$captured = null;
		$this->add_test_filter(
			'wp_handle_upload_overrides',
			function ( $overrides ) use ( &$captured ) {
				$captured                          = $overrides;
				$overrides['upload_error_handler'] = function () {
					return array( 'error' => 'stopped' );
				};
				return $overrides;
			}
		);

		acf_upload_file( $this->upload_fixture( 'plain text', 'notes.txt' ) );

		$this->assertArrayNotHasKey( 'mimes', $captured );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Returns the bytes of a valid 1x1 PNG.
	 *
	 * @return string
	 */
	private function png_bytes() {
		return base64_decode( self::PNG_BASE64 ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes a fixed image fixture.
	}

	/**
	 * Returns the bytes of a small SVG.
	 *
	 * @return string
	 */
	private function svg_bytes() {
		return '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="1" height="1"/></svg>';
	}

	/**
	 * Allows SVG uploads site-wide, as an SVG plugin would.
	 *
	 * The site-wide list in acf_get_image_mime_types() is cached for the request,
	 * so SVG is also added through the test field's filter.
	 */
	private function allow_svg() {
		$this->add_test_filter(
			'upload_mimes',
			function ( $mimes ) {
				$mimes['svg'] = 'image/svg+xml';
				return $mimes;
			}
		);
		$this->add_test_filter(
			'acf/get_image_mime_types/key=field_test_image',
			function ( $mimes ) {
				$mimes['svg'] = 'image/svg+xml';
				return $mimes;
			}
		);
	}

	/**
	 * Builds a field array of the given type.
	 *
	 * @param string $type The field type.
	 * @return array
	 */
	private function field( $type ) {
		return array(
			'key'   => 'field_test_' . $type,
			'_name' => 'test_' . $type,
			'name'  => 'test_' . $type,
			'type'  => $type,
		);
	}

	/**
	 * Writes a temporary upload and returns its attachment array.
	 *
	 * @param string $contents The file bytes.
	 * @param string $name     The uploaded file name.
	 * @return array
	 */
	private function upload_fixture( $contents, $name ) {
		$temp_file = tempnam( sys_get_temp_dir(), 'scf-image-' );
		file_put_contents( $temp_file, $contents );
		$this->temp_files[] = $temp_file;

		return array(
			'name'     => $name,
			'type'     => wp_check_filetype( $name )['type'],
			'tmp_name' => $temp_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $temp_file ),
		);
	}

	/**
	 * Creates an attachment for the given file name.
	 *
	 * @param string $file The attached file name.
	 * @param string $mime The attachment MIME type.
	 * @return int
	 */
	private function create_attachment( $file, $mime ) {
		$attachment_id = wp_insert_attachment(
			array(
				'post_title'     => $file,
				'post_type'      => 'attachment',
				'post_mime_type' => $mime,
				'post_status'    => 'inherit',
			),
			'/tmp/' . $file
		);

		$this->attachment_ids[] = $attachment_id;

		return $attachment_id;
	}

	/**
	 * Adds and tracks a test-specific filter.
	 *
	 * @param string   $hook          Filter name.
	 * @param callable $callback      Filter callback.
	 * @param int      $priority      Filter priority.
	 * @param int      $accepted_args Accepted argument count.
	 */
	private function add_test_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook, $callback, $priority, $accepted_args );

		$this->test_filters[] = array(
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		);
	}
}
