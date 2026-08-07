<?php
/**
 * Tests for Gallery field AJAX attachment visibility.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Exercises the real Gallery attachment AJAX handler and rendered response.
 *
 * @group fields
 * @group ajax
 * @group visibility
 */
class Test_ACF_Field_Gallery_Ajax_Visibility extends BaseTestCase {

	/**
	 * Gallery field key used to create the typed nonce.
	 *
	 * @var string
	 */
	private const FIELD_KEY = 'field_test_gallery_ajax_visibility';

	/**
	 * Subscriber used for object-specific read permission tests.
	 *
	 * @var int
	 */
	private $reader_user_id = 0;

	/**
	 * The only post ID the reader may access through read_post.
	 *
	 * @var int
	 */
	private $readable_post_id = 0;

	/**
	 * Attachment IDs created by the current test.
	 *
	 * @var int[]
	 */
	private $attachment_ids = array();

	/**
	 * Non-attachment post IDs created by the current test.
	 *
	 * @var int[]
	 */
	private $post_ids = array();

	/**
	 * Set up the field, user, request state, and capability shim.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->ensure_field_types();

		wp_set_current_user( 0 );

		$this->reader_user_id = wp_insert_user(
			array(
				'user_login' => 'gallery_ajax_reader',
				'user_pass'  => 'password',
				'user_email' => 'gallery-ajax-reader@example.com',
				'role'       => 'subscriber',
			)
		);
		$this->assertIsInt( $this->reader_user_id );

		acf_add_local_field(
			array(
				'key'   => self::FIELD_KEY,
				'name'  => 'test_gallery_ajax_visibility',
				'label' => 'Gallery AJAX Visibility',
				'type'  => 'gallery',
			)
		);

		add_filter( 'map_meta_cap', array( $this, 'filter_reader_post_capabilities' ), 100, 4 );

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * Remove all fixture and request state.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'map_meta_cap', array( $this, 'filter_reader_post_capabilities' ), 100 );

		foreach ( array_reverse( $this->attachment_ids ) as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}

		foreach ( array_reverse( $this->post_ids ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		acf_remove_local_field( self::FIELD_KEY );

		if ( $this->reader_user_id ) {
			wp_delete_user( $this->reader_user_id );
		}

		$this->reader_user_id   = 0;
		$this->readable_post_id = 0;
		$this->attachment_ids   = array();
		$this->post_ids         = array();

		wp_set_current_user( 0 );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Load only the field types needed to render the handler response.
	 *
	 * @return void
	 */
	private function ensure_field_types() {
		$field_types = array(
			'text'     => 'acf_field_text',
			'textarea' => 'acf_field_textarea',
			'gallery'  => 'acf_field_gallery',
		);

		foreach ( $field_types as $type => $class ) {
			if ( ! class_exists( $class ) ) {
				acf_include( "includes/fields/class-acf-field-{$type}.php" );
			}

			if ( ! has_action( "acf/render_field/type={$type}" ) ) {
				acf_register_field_type( $class );
			}
		}
	}

	/**
	 * Anonymous callers cannot render metadata inherited from a private parent.
	 *
	 * @return void
	 */
	public function test_anonymous_cannot_render_attachment_with_private_parent() {
		$parent_id  = $this->create_post( 'Private Gallery Parent', 'private' );
		$attachment = $this->create_attachment( 'private-parent', $parent_id );

		wp_set_current_user( 0 );
		$output = $this->invoke_ajax_get_attachment( $attachment['id'] );

		$this->assertSame( '', $output );
		$this->assert_attachment_metadata_absent( $attachment, $output );
	}

	/**
	 * An inherited attachment without a parent remains publicly renderable.
	 *
	 * @return void
	 */
	public function test_anonymous_can_render_inherited_attachment_without_parent() {
		$attachment = $this->create_attachment( 'no-parent' );

		wp_set_current_user( 0 );
		$output = $this->invoke_ajax_get_attachment( $attachment['id'] );

		$this->assertNotFalse(
			has_action( 'wp_ajax_nopriv_acf/fields/gallery/get_attachment' ),
			'Unauthenticated galleries must keep the handler registered.'
		);
		$this->assert_attachment_metadata_rendered( $attachment, $output );
	}

	/**
	 * An attachment inheriting from a published parent remains publicly renderable.
	 *
	 * @return void
	 */
	public function test_anonymous_can_render_attachment_with_published_parent() {
		$parent_id  = $this->create_post( 'Published Gallery Parent', 'publish' );
		$attachment = $this->create_attachment( 'published-parent', $parent_id );

		wp_set_current_user( 0 );
		$output = $this->invoke_ajax_get_attachment( $attachment['id'] );

		$this->assert_attachment_metadata_rendered( $attachment, $output );
	}

	/**
	 * Reading only the private parent permits the inherited attachment response.
	 *
	 * @return void
	 */
	public function test_user_who_can_only_read_private_parent_can_render_attachment() {
		$parent_id  = $this->create_post( 'Reader Private Gallery Parent', 'private' );
		$attachment = $this->create_attachment( 'parent-reader', $parent_id );

		$this->readable_post_id = $parent_id;
		wp_set_current_user( $this->reader_user_id );

		$this->assertTrue( current_user_can( 'read_post', $parent_id ) );
		$this->assertFalse( current_user_can( 'read_post', $attachment['id'] ) );

		$output = $this->invoke_ajax_get_attachment( $attachment['id'] );
		$this->assert_attachment_metadata_rendered( $attachment, $output );
	}

	/**
	 * Reading only the attachment permits the response even when its parent is private.
	 *
	 * @return void
	 */
	public function test_user_who_can_only_read_attachment_can_render_attachment() {
		$parent_id  = $this->create_post( 'Attachment Reader Private Parent', 'private' );
		$attachment = $this->create_attachment( 'attachment-reader', $parent_id );

		$this->readable_post_id = $attachment['id'];
		wp_set_current_user( $this->reader_user_id );

		$this->assertTrue( current_user_can( 'read_post', $attachment['id'] ) );
		$this->assertFalse( current_user_can( 'read_post', $parent_id ) );

		$output = $this->invoke_ajax_get_attachment( $attachment['id'] );
		$this->assert_attachment_metadata_rendered( $attachment, $output );
	}

	/**
	 * Missing posts and non-attachment posts both terminate with an empty response.
	 *
	 * @return void
	 */
	public function test_invalid_and_non_attachment_ids_are_denied() {
		$non_attachment_id = $this->create_post( 'Not a Gallery Attachment', 'publish' );

		wp_set_current_user( 0 );

		$this->assertSame( '', $this->invoke_ajax_get_attachment( PHP_INT_MAX ) );
		$this->assertSame( '', $this->invoke_ajax_get_attachment( $non_attachment_id ) );
	}

	/**
	 * Grant read_post for one fixture ID and deny it for every other fixture ID.
	 *
	 * @param string[] $caps    Primitive capabilities required by WordPress.
	 * @param string   $cap     Requested capability.
	 * @param int      $user_id User being checked.
	 * @param array    $args    Capability context, beginning with the post ID.
	 * @return string[]
	 */
	public function filter_reader_post_capabilities( $caps, $cap, $user_id, $args ) {
		if (
			'read_post' !== $cap
			|| $this->reader_user_id !== (int) $user_id
			|| empty( $args[0] )
		) {
			return $caps;
		}

		if ( $this->readable_post_id === (int) $args[0] ) {
			return array( 'exist' );
		}

		return array( 'do_not_allow' );
	}

	/**
	 * Create a post fixture.
	 *
	 * @param string $title  Post title.
	 * @param string $status Post status.
	 * @return int
	 */
	private function create_post( $title, $status ) {
		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => $status,
				'post_type'   => 'post',
			)
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * Create an inherited image attachment with realistic private metadata.
	 *
	 * @param string $slug      Unique fixture slug.
	 * @param int    $parent_id Optional parent post ID.
	 * @return array
	 */
	private function create_attachment( $slug, $parent_id = 0 ) {
		$filename    = 'gallery-visibility-' . $slug . '.jpg';
		$title       = 'Gallery Fixture Title ' . $slug;
		$caption     = 'Gallery Fixture Caption ' . $slug;
		$alt         = 'Gallery Fixture Alt ' . $slug;
		$description = 'Gallery Fixture Description ' . $slug;

		$attachment_id = wp_insert_attachment(
			array(
				'post_title'     => $title,
				'post_excerpt'   => $caption,
				'post_content'   => $description,
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
			),
			'/tmp/' . $filename,
			$parent_id
		);

		$this->assertIsInt( $attachment_id );
		$this->assertGreaterThan( 0, $attachment_id );
		$this->attachment_ids[] = $attachment_id;

		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'    => 640,
				'height'   => 480,
				'file'     => $filename,
				'filesize' => 12345,
				'sizes'    => array(),
			)
		);
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );

		return array(
			'id'          => $attachment_id,
			'title'       => $title,
			'caption'     => $caption,
			'alt'         => $alt,
			'description' => $description,
			'url'         => wp_get_attachment_url( $attachment_id ),
		);
	}

	/**
	 * Invoke the real Gallery handler and capture its HTML or empty response.
	 *
	 * @param int $attachment_id Requested attachment ID.
	 * @return string
	 * @throws RuntimeException When the handler throws unexpectedly.
	 */
	private function invoke_ajax_get_attachment( $attachment_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Real handler test request with a valid typed nonce.
		$_POST = array(
			'id'        => $attachment_id,
			'field_key' => self::FIELD_KEY,
			'nonce'     => wp_create_nonce( 'acf_field_gallery_' . self::FIELD_KEY ),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Mirrors the request source consumed by acf_request_args().
		$_REQUEST = $_POST;

		$halt_marker  = 'acf_gallery_ajax_halt';
		$force_ajax   = static function () {
			return true;
		};
		$halt_handler = static function () use ( $halt_marker ) {
			return static function () use ( $halt_marker ) {
				throw new RuntimeException( esc_html( $halt_marker ) );
			};
		};

		add_filter( 'wp_doing_ajax', $force_ajax );
		add_filter( 'wp_die_ajax_handler', $halt_handler, 100 );

		$output              = '';
		$halted              = false;
		$unrelated_exception = null;

		ob_start();
		try {
			acf_get_field_type( 'gallery' )->ajax_get_attachment();
		} catch ( RuntimeException $exception ) {
			if ( $halt_marker === $exception->getMessage() ) {
				$halted = true;
			} else {
				$unrelated_exception = $exception;
			}
		} finally {
			$output = ob_get_clean();

			remove_filter( 'wp_die_ajax_handler', $halt_handler, 100 );
			remove_filter( 'wp_doing_ajax', $force_ajax );
		}

		if ( $unrelated_exception ) {
			throw $unrelated_exception;
		}

		$this->assertTrue( $halted, 'The AJAX handler should terminate through wp_die().' );

		return $output;
	}

	/**
	 * Assert that the real Gallery markup contains all seeded attachment metadata.
	 *
	 * @param array  $attachment Attachment fixture data.
	 * @param string $output     Rendered handler response.
	 * @return void
	 */
	private function assert_attachment_metadata_rendered( $attachment, $output ) {
		$this->assertStringContainsString( 'acf-gallery-side-info', $output );

		foreach ( array( 'title', 'caption', 'alt', 'description', 'url' ) as $key ) {
			$this->assertStringContainsString( (string) $attachment[ $key ], $output, "Expected attachment {$key} in Gallery output." );
		}
	}

	/**
	 * Assert that no seeded attachment metadata escaped in a denied response.
	 *
	 * @param array  $attachment Attachment fixture data.
	 * @param string $output     Denied handler response.
	 * @return void
	 */
	private function assert_attachment_metadata_absent( $attachment, $output ) {
		foreach ( array( 'title', 'caption', 'alt', 'description', 'url' ) as $key ) {
			$this->assertStringNotContainsString( (string) $attachment[ $key ], $output, "Unexpected attachment {$key} in denied Gallery output." );
		}
	}
}
