<?php
/**
 * Tests that basic uploader nonces only verify for the object they were rendered for.
 *
 * @package wordpress/secure-custom-fields
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions -- Tests use throwaway file fixtures.
 */

use WorDBless\BaseTestCase;

/**
 * Tests the render IDs basic uploader nonces are verified against.
 *
 * @group api
 * @group media
 * @group security
 *
 * @covers ::acf_upload_files
 * @covers ::_acf_verify_file_uploader_nonce
 * @covers ::acf_get_file_uploader_nonce_action
 * @covers ::acf_set_upload_render_ids
 */
class Test_API_Upload_Nonce_Render_Ids extends BaseTestCase {

	private const FIELD_KEY = 'field_scf_upload_nonce';

	/**
	 * Attachment IDs created by the current test.
	 *
	 * @var int[]
	 */
	private $attachment_ids = array();

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

		$this->attachment_ids = array();

		$this->upload_overrides = function ( $overrides ) {
			$overrides['upload_error_handler'] = array( $this, 'mock_upload_error_as_success' );
			return $overrides;
		};

		$this->attachment_action = function ( $attachment_id ) {
			$this->attachment_ids[] = $attachment_id;
		};

		add_filter( 'wp_handle_upload_overrides', $this->upload_overrides );
		add_action( 'add_attachment', $this->attachment_action );

		acf_set_form_data( 'upload_render_ids', null );
		acf_set_form_data( 'post_id', null );
	}

	/**
	 * Clean up request state and test data.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'wp_handle_upload_overrides', $this->upload_overrides );
		remove_action( 'add_attachment', $this->attachment_action );
		acf_set_form_data( 'upload_render_ids', null );
		acf_set_form_data( 'post_id', null );

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
	 * A nonce rendered for one object is rejected when the save is for another.
	 *
	 * @return void
	 */
	public function test_nonce_for_another_render_id_is_rejected(): void {
		$this->prepare_upload_request( acf_get_file_uploader_nonce_action( '101', self::FIELD_KEY ) );
		acf_set_upload_render_ids( array( '202' ) );

		acf_upload_files();

		$this->assertSame( array(), $this->attachment_ids );
		$this->assertSame( '', acf_maybe_get_POST( 'acf' )[ self::FIELD_KEY ], 'The submitted value is not replaced with an attachment ID.' );
	}

	/**
	 * A nonce rendered for the object being saved is accepted.
	 *
	 * @return void
	 */
	public function test_nonce_for_matching_render_id_is_accepted(): void {
		$this->prepare_upload_request( acf_get_file_uploader_nonce_action( '202', self::FIELD_KEY ) );
		acf_set_upload_render_ids( array( '101', '202' ) );

		acf_upload_files();

		$this->assertCount( 1, $this->attachment_ids );
		$this->assertSame( $this->attachment_ids[0], acf_maybe_get_POST( 'acf' )[ self::FIELD_KEY ] );
	}

	/**
	 * A nonce for the same render ID but another field is rejected.
	 *
	 * @return void
	 */
	public function test_nonce_for_another_field_is_rejected(): void {
		$this->prepare_upload_request( acf_get_file_uploader_nonce_action( '202', 'field_scf_upload_other' ) );
		acf_set_upload_render_ids( array( '202' ) );

		acf_upload_files();

		$this->assertSame( array(), $this->attachment_ids );
	}

	/**
	 * Without render IDs, the nonce is verified against the post ID being saved.
	 *
	 * @return void
	 */
	public function test_nonce_falls_back_to_saved_post_id(): void {
		$this->prepare_upload_request( acf_get_file_uploader_nonce_action( '303', self::FIELD_KEY ) );
		acf_set_upload_render_ids( array() );
		acf_set_form_data( 'post_id', 303 );

		acf_upload_files();

		$this->assertCount( 1, $this->attachment_ids );
	}

	/**
	 * Without render IDs, a nonce for another post than the one being saved is rejected.
	 *
	 * @return void
	 */
	public function test_nonce_for_another_post_than_saved_post_id_is_rejected(): void {
		$this->prepare_upload_request( acf_get_file_uploader_nonce_action( '404', self::FIELD_KEY ) );
		acf_set_upload_render_ids( array() );
		acf_set_form_data( 'post_id', 303 );

		acf_upload_files();

		$this->assertSame( array(), $this->attachment_ids );
	}

	/**
	 * Saves that never set render IDs accept the render IDs of the submitted field lists.
	 *
	 * @return void
	 */
	public function test_unset_render_ids_accept_submitted_field_list_render_ids(): void {
		$this->prepare_upload_request( acf_get_file_uploader_nonce_action( '505', self::FIELD_KEY ) );
		$_POST['_acf_fields'] = array( '505' => array( 'unverified' ) );

		acf_upload_files();

		$this->assertCount( 1, $this->attachment_ids );
	}

	/**
	 * Saves that set render IDs don't fall back to the render IDs of the submitted field lists.
	 *
	 * @return void
	 */
	public function test_set_render_ids_ignore_submitted_field_list_render_ids(): void {
		$this->prepare_upload_request( acf_get_file_uploader_nonce_action( '505', self::FIELD_KEY ) );
		$_POST['_acf_fields'] = array( '505' => array( 'unverified' ) );
		acf_set_upload_render_ids( array( '606' ) );

		acf_upload_files();

		$this->assertSame( array(), $this->attachment_ids );
	}

	/**
	 * The legacy nonce, signed for the field key alone, is rejected.
	 *
	 * @return void
	 */
	public function test_legacy_field_key_nonce_is_rejected(): void {
		$this->prepare_upload_request( 'acf/file_uploader_nonce/' . self::FIELD_KEY );
		acf_set_upload_render_ids( array( '202' ) );

		acf_upload_files();

		$this->assertSame( array(), $this->attachment_ids );
	}

	/**
	 * The legacy nonce is rejected when the save falls back to the post ID being saved.
	 *
	 * @return void
	 */
	public function test_legacy_field_key_nonce_is_rejected_without_render_ids(): void {
		$this->prepare_upload_request( 'acf/file_uploader_nonce/' . self::FIELD_KEY );
		acf_set_form_data( 'post_id', 303 );

		acf_upload_files();

		$this->assertSame( array(), $this->attachment_ids );
	}

	/**
	 * The uploader input rendered for an object submits a nonce that only uploads when saving that object.
	 *
	 * @return void
	 */
	public function test_rendered_uploader_nonce_is_bound_to_render_id(): void {
		$html = '';
		_acf_in_field_render(
			'707',
			function () use ( &$html ) {
				$html = acf_get_file_input(
					array(
						'name' => 'acf[' . self::FIELD_KEY . ']',
						'key'  => self::FIELD_KEY,
					)
				);
			}
		);

		$nonce = $this->get_rendered_nonce( $html );

		$this->assertNotFalse( wp_verify_nonce( $nonce, acf_get_file_uploader_nonce_action( '707', self::FIELD_KEY ) ) );
		$this->assertFalse( wp_verify_nonce( $nonce, acf_get_file_uploader_nonce_action( '808', self::FIELD_KEY ) ) );
		$this->assertFalse( wp_verify_nonce( $nonce, 'acf/file_uploader_nonce/' . self::FIELD_KEY ) );
	}

	/**
	 * Returns the value of the uploader nonce input in rendered HTML.
	 *
	 * @param string $html The rendered file input.
	 * @return string
	 */
	private function get_rendered_nonce( string $html ): string {
		$processor = new WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag( 'input' ) ) {
			if ( 'acf[' . self::FIELD_KEY . '_file_nonce]' === $processor->get_attribute( 'name' ) ) {
				return (string) $processor->get_attribute( 'value' );
			}
		}

		$this->fail( 'The uploader nonce input was not rendered.' );
	}

	/**
	 * Return a successful upload result when CLI upload validation fails.
	 *
	 * PHPUnit does not receive files over HTTP, so PHP's is_uploaded_file() check fails.
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
			'type' => 'text/plain',
		);
	}

	/**
	 * Populate an upload fixture and a nonce created for the given action.
	 *
	 * @param string $nonce_action The action the submitted nonce is created for.
	 * @return void
	 */
	private function prepare_upload_request( string $nonce_action ): void {
		$this->temp_file = tempnam( sys_get_temp_dir(), 'scf-upload-' );
		$this->assertIsString( $this->temp_file );
		$this->assertNotFalse( file_put_contents( $this->temp_file, 'Benign SCF upload fixture.' ) );

		$nonce_name = self::FIELD_KEY . '_file_nonce';
		$nonce      = wp_create_nonce( $nonce_action );

		$_FILES['acf']   = array(
			'name'     => array( self::FIELD_KEY => 'upload.txt' ),
			'type'     => array( self::FIELD_KEY => 'text/plain' ),
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
