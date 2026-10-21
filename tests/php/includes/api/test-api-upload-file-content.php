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
	 * Number of times WordPress asked for an image editor.
	 *
	 * @var int
	 */
	private $image_editor_requests = 0;

	/**
	 * Image editor observer callback.
	 *
	 * @var callable
	 */
	private $image_editor_observer;

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

		$this->image_editor_requests = 0;
		$this->image_editor_observer = function ( $editors ) {
			++$this->image_editor_requests;
			return $editors;
		};

		add_filter( 'wp_handle_upload_overrides', $this->upload_overrides );
		add_action( 'add_attachment', $this->attachment_action );
		add_filter( 'wp_image_editors', $this->image_editor_observer );

		// Register the sizes WordPress renders PDF previews at, so preview generation is attempted.
		update_option( 'thumbnail_size_w', 150 );
		update_option( 'thumbnail_size_h', 150 );
	}

	/**
	 * Clean up request state and test data.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'wp_handle_upload_overrides', $this->upload_overrides );
		remove_action( 'add_attachment', $this->attachment_action );
		remove_filter( 'wp_image_editors', $this->image_editor_observer );
		remove_all_filters( 'scf/upload/generate_previews' );
		delete_option( 'thumbnail_size_w' );
		delete_option( 'thumbnail_size_h' );
		remove_theme_support( 'post-thumbnails' );
		wp_set_current_user( 0 );

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
	 * Leading whitespace before the marker is accepted.
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
	 * A PDF from a visitor who cannot upload files is stored without loading an image editor.
	 *
	 * @return void
	 */
	public function test_pdf_from_visitor_skips_preview_generation(): void {
		$this->prepare_upload_request( "%PDF-1.4\n1 0 obj<<>>endobj\n", 'visitor.pdf' );
		acf_upload_files();

		$this->assertCount( 1, $this->attachment_ids, 'The PDF is still stored.' );
		$this->assertSame( 0, $this->image_editor_requests, 'No image editor should be loaded for this PDF.' );
	}

	/**
	 * A PDF from a user without the upload_files capability skips preview generation.
	 *
	 * @return void
	 */
	public function test_pdf_from_subscriber_skips_preview_generation(): void {
		$this->set_current_user_role( 'subscriber' );

		$this->prepare_upload_request( "%PDF-1.4\n1 0 obj<<>>endobj\n", 'subscriber.pdf' );
		acf_upload_files();

		$this->assertCount( 1, $this->attachment_ids );
		$this->assertSame( 0, $this->image_editor_requests );
	}

	/**
	 * A PDF from a user who can upload to the media library still gets preview images.
	 *
	 * @return void
	 */
	public function test_pdf_from_user_who_can_upload_generates_preview(): void {
		$this->set_current_user_role( 'author' );

		$this->prepare_upload_request( "%PDF-1.4\n1 0 obj<<>>endobj\n", 'author.pdf' );
		acf_upload_files();

		$this->assertCount( 1, $this->attachment_ids );
		$this->assertSame( 1, $this->image_editor_requests, 'Trusted uploaders keep PDF previews.' );
	}

	/**
	 * The filter can enable PDF previews for visitors.
	 *
	 * @return void
	 */
	public function test_pdf_preview_filter_can_enable_previews_for_visitors(): void {
		add_filter( 'scf/upload/generate_previews', '__return_true' );

		$this->prepare_upload_request( "%PDF-1.4\n1 0 obj<<>>endobj\n", 'visitor-preview.pdf' );
		acf_upload_files();

		$this->assertSame( 1, $this->image_editor_requests );
	}

	/**
	 * Sites can turn PDF previews off for every basic upload.
	 *
	 * @return void
	 */
	public function test_pdf_preview_filter_can_disable_previews_for_trusted_users(): void {
		$this->set_current_user_role( 'author' );
		add_filter( 'scf/upload/generate_previews', '__return_false' );

		$this->prepare_upload_request( "%PDF-1.4\n1 0 obj<<>>endobj\n", 'no-preview.pdf' );
		acf_upload_files();

		$this->assertSame( 0, $this->image_editor_requests );
	}

	/**
	 * Skipping a visitor's PDF preview does not affect later metadata generation.
	 *
	 * @return void
	 */
	public function test_skipped_pdf_preview_does_not_leak_into_later_requests(): void {
		$this->prepare_upload_request( "%PDF-1.4\n1 0 obj<<>>endobj\n", 'visitor-first.pdf' );
		acf_upload_files();

		$this->assertSame(
			array( 'thumbnail', 'medium', 'large' ),
			apply_filters( 'fallback_intermediate_image_sizes', array( 'thumbnail', 'medium', 'large' ), array() ),
			'The preview size override must be removed after the upload.'
		);
	}

	/**
	 * Cover art in a visitor's audio file is not saved.
	 *
	 * @return void
	 */
	public function test_audio_cover_art_from_visitor_is_not_saved(): void {
		add_theme_support( 'post-thumbnails' );
		$this->mock_upload_type = 'audio/flac';

		$this->prepare_upload_request( $this->flac_with_cover_art( 'image/jpeg', 'cover' ), 'visitor.flac' );
		acf_upload_files();

		$this->assertCount( 1, $this->attachment_ids, 'Only the audio file is stored.' );
		$this->assertSame( 0, $this->image_editor_requests );
	}

	/**
	 * Cover art in audio uploaded by a user who can upload files is still saved.
	 *
	 * @return void
	 */
	public function test_audio_cover_art_from_user_who_can_upload_is_saved(): void {
		add_theme_support( 'post-thumbnails' );
		$this->set_current_user_role( 'author' );
		$this->mock_upload_type = 'audio/flac';

		$this->prepare_upload_request( $this->flac_with_cover_art( 'image/jpeg', 'cover' ), 'author.flac' );
		acf_upload_files();

		$this->assertCount( 2, $this->attachment_ids, 'The audio file and its cover art are stored.' );
	}

	/**
	 * Removing a visitor's cover art does not affect later metadata reads.
	 *
	 * @return void
	 */
	public function test_skipped_cover_art_does_not_leak_into_later_requests(): void {
		$this->mock_upload_type = 'audio/flac';
		$this->prepare_upload_request( $this->flac_with_cover_art( 'image/jpeg', 'cover' ), 'visitor-first.flac' );
		acf_upload_files();

		$metadata = array( 'image' => array( 'data' => 'cover' ) );
		$this->assertSame( $metadata, apply_filters( 'wp_read_audio_metadata', $metadata, '', '', array() ) );
		$this->assertSame( $metadata, apply_filters( 'wp_read_video_metadata', $metadata, '', '', array() ) );
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
	 * Builds a minimal FLAC file with an embedded picture block.
	 *
	 * @param string $mime The MIME type the picture declares.
	 * @param string $data The picture bytes.
	 * @return string
	 */
	private function flac_with_cover_art( string $mime, string $data ): string {
		$stream_info  = pack( 'n', 4096 ) . pack( 'n', 4096 ) . "\0\0\0\0\0\0";
		$stream_info .= pack( 'J', ( 44100 << 44 ) | ( 1 << 41 ) | ( 15 << 36 ) ) . str_repeat( "\0", 16 );
		$flac         = 'fLaC' . chr( 0 ) . substr( pack( 'N', strlen( $stream_info ) ), 1 ) . $stream_info;

		$picture = pack( 'N', 3 ) . pack( 'N', strlen( $mime ) ) . $mime . pack( 'N', 0 ) . pack( 'N', 1 ) . pack( 'N', 1 ) . pack( 'N', 24 ) . pack( 'N', 0 ) . pack( 'N', strlen( $data ) ) . $data;
		$flac   .= chr( 0x80 | 6 ) . substr( pack( 'N', strlen( $picture ) ), 1 ) . $picture;

		// Audio frames, so the metadata reader can seek past the header blocks.
		return $flac . str_repeat( "\xff\xf8\x69\x08\0\0", 1000 );
	}

	/**
	 * Log in as a new user with the given role.
	 *
	 * @param string $role Role name.
	 * @return void
	 */
	private function set_current_user_role( string $role ): void {
		$user_id = wp_insert_user(
			array(
				'user_login' => $role . '_' . uniqid(),
				'user_pass'  => 'password',
				'role'       => $role,
			)
		);
		$this->assertIsInt( $user_id );
		wp_set_current_user( $user_id );
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
