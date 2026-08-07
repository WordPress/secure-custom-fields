<?php
/**
 * Tests that basic uploads match the contents their reported type requires.
 *
 * @package wordpress/secure-custom-fields
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions -- Tests use throwaway file fixtures.
 */

use WorDBless\BaseTestCase;

/**
 * Tests the content check applied to files uploaded through basic File and Image fields.
 *
 * @group api
 * @group media
 * @group security
 *
 * @covers ::acf_upload_file
 * @covers ::acf_upload_files
 */
class Test_API_Upload_File_Content extends BaseTestCase {

	/** Test field key. */
	private const FIELD_KEY = 'field_scf_upload_content';

	/**
	 * Attachment IDs created by the current test.
	 *
	 * @var int[]
	 */
	private $attachment_ids = array();

	/**
	 * MIME type returned by the mocked upload handler.
	 *
	 * @var string
	 */
	private $mock_upload_type = 'application/pdf';

	/**
	 * Path to the temporary upload fixture.
	 *
	 * @var string
	 */
	private $temp_file = '';

	/**
	 * Upload overrides callback.
	 *
	 * @var callable
	 */
	private $upload_overrides;

	/**
	 * Attachment creation callback.
	 *
	 * @var callable
	 */
	private $attachment_action;

	/**
	 * Set up isolated request state and upload observers.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$_FILES   = array();
		$_POST    = array();
		$_REQUEST = array();

		$this->attachment_ids   = array();
		$this->mock_upload_type = 'application/pdf';

		$this->upload_overrides = function ( $overrides ) {
			$overrides['upload_error_handler'] = array( $this, 'mock_upload_error_as_success' );
			return $overrides;
		};

		$this->attachment_action = function ( $attachment_id ) {
			$this->attachment_ids[] = $attachment_id;
		};

		add_filter( 'wp_handle_upload_overrides', $this->upload_overrides );
		add_action( 'add_attachment', $this->attachment_action );
	}

	/**
	 * Clean up request state and test data.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'wp_handle_upload_overrides', $this->upload_overrides );
		remove_action( 'add_attachment', $this->attachment_action );

		foreach ( $this->attachment_ids as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}

		if ( $this->temp_file && file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file );
		}

		$_FILES   = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * A PostScript program that names the `%PDF-` marker after its own header is rejected.
	 *
	 * Ghostscript searches for `%PDF-` in the bytes following any leading whitespace, but
	 * uses its PostScript interpreter when `%!PS` appears before that marker. Accepting the
	 * marker anywhere in the header would therefore store a file that Ghostscript runs as a
	 * PostScript program.
	 *
	 * @return void
	 */
	public function test_postscript_naming_the_pdf_marker_is_rejected(): void {
		$this->prepare_upload_request( "%!PS-Adobe-3.0\0\0\n%PDF-1.4 decoy\n(decoy) print showpage\n", 'decoy.pdf' );
		acf_upload_files();

		$this->assertSame( array(), $this->attachment_ids, 'A PostScript program naming %PDF- must not create an attachment.' );
		$this->assertFileDoesNotExist( $this->temp_file, 'The rejected file is removed from disk.' );
	}

	/**
	 * A file with no `%PDF-` marker at all is rejected.
	 *
	 * @return void
	 */
	public function test_file_without_the_pdf_marker_is_rejected(): void {
		$this->prepare_upload_request( "%!PS-Adobe-3.0\0\0\nshowpage\n", 'brochure.pdf' );
		acf_upload_files();

		$this->assertSame( array(), $this->attachment_ids );
		$this->assertFileDoesNotExist( $this->temp_file, 'The rejected file is removed from disk.' );
	}

	/**
	 * A `%PDF-` marker pushed past the header window is rejected.
	 *
	 * @return void
	 */
	public function test_pdf_marker_beyond_the_header_window_is_rejected(): void {
		$this->prepare_upload_request( str_repeat( 'A', 2048 ) . "%PDF-1.4\n", 'late.pdf' );
		acf_upload_files();

		$this->assertSame( array(), $this->attachment_ids );
		$this->assertFileDoesNotExist( $this->temp_file, 'The rejected file is removed from disk.' );
	}

	/**
	 * A genuine PDF is accepted.
	 *
	 * @return void
	 */
	public function test_genuine_pdf_is_accepted(): void {
		$this->prepare_upload_request( "%PDF-1.4\n1 0 obj<<>>endobj\n", 'real.pdf' );
		acf_upload_files();

		$this->assertCount( 1, $this->attachment_ids, 'A genuine PDF should be accepted.' );
		$this->assertFileExists( $this->temp_file );
	}

	/**
	 * Leading whitespace before the marker is accepted, matching how Ghostscript reads it.
	 *
	 * Ghostscript consumes bytes up to and including a space before sniffing the header, so
	 * such a file is still read as a PDF and must not be treated as a disguised one.
	 *
	 * @return void
	 */
	public function test_pdf_with_leading_whitespace_is_accepted(): void {
		$this->prepare_upload_request( "\x00\n  %PDF-1.4\n1 0 obj<<>>endobj\n", 'padded.pdf' );
		acf_upload_files();

		$this->assertCount( 1, $this->attachment_ids, 'A PDF behind leading whitespace should be accepted.' );
	}

	/**
	 * The check applies to every uploader, including users who can upload to the library.
	 *
	 * @return void
	 */
	public function test_disguised_pdf_from_an_administrator_is_rejected(): void {
		wp_set_current_user( 1 );

		$this->prepare_upload_request( "%!PS-Adobe-3.0\0\0\n%PDF-1.4 decoy\nshowpage\n", 'decoy-admin.pdf' );
		acf_upload_files();

		$this->assertSame( array(), $this->attachment_ids, 'The check must not depend on who is uploading.' );
		$this->assertFileDoesNotExist( $this->temp_file );

		wp_set_current_user( 0 );
	}

	/**
	 * Types other than PDF are left alone.
	 *
	 * @return void
	 */
	public function test_non_pdf_upload_is_unaffected(): void {
		$this->mock_upload_type = 'text/plain';

		$this->prepare_upload_request( 'Benign SCF upload fixture.', 'benign-upload.txt' );
		acf_upload_files();

		$this->assertCount( 1, $this->attachment_ids, 'A non-PDF upload should be unaffected.' );
	}

	/**
	 * Return a successful upload result when CLI upload validation fails.
	 *
	 * PHPUnit does not receive files over HTTP, so PHP's is_uploaded_file() check fails.
	 * This mocks that boundary. It also stands in for a host whose fileinfo extension is
	 * missing or reports a nonspecific type, since it bypasses the filetype rejection
	 * wp_handle_upload() would otherwise raise.
	 *
	 * @param array  $file    Uploaded file data.
	 * @param string $message Upload validation error message.
	 * @return array
	 */
	public function mock_upload_error_as_success( &$file, $message ) {
		unset( $message );

		return array(
			'file' => $file['tmp_name'],
			'url'  => 'https://example.test/' . basename( $file['tmp_name'] ),
			'type' => $this->mock_upload_type,
		);
	}

	/**
	 * Populate a valid nonce and an upload fixture with the given bytes.
	 *
	 * @param string $contents The file bytes.
	 * @param string $name     The uploaded file name.
	 * @return void
	 */
	private function prepare_upload_request( string $contents, string $name ): void {
		$this->temp_file = tempnam( sys_get_temp_dir(), 'scf-upload-' );
		$this->assertIsString( $this->temp_file );
		$this->assertNotFalse( file_put_contents( $this->temp_file, $contents ) );

		$nonce_name = self::FIELD_KEY . '_file_nonce';
		$nonce      = wp_create_nonce( 'acf/file_uploader_nonce/' . self::FIELD_KEY );

		$_FILES['acf']   = array(
			'name'     => array( self::FIELD_KEY => $name ),
			'type'     => array( self::FIELD_KEY => $this->mock_upload_type ),
			'tmp_name' => array( self::FIELD_KEY => $this->temp_file ),
			'error'    => array( self::FIELD_KEY => UPLOAD_ERR_OK ),
			'size'     => array( self::FIELD_KEY => filesize( $this->temp_file ) ),
		);
		$_POST['acf']    = array(
			self::FIELD_KEY => '',
			$nonce_name     => $nonce,
		);
		$_REQUEST['acf'] = array( $nonce_name => $nonce );
	}
}
