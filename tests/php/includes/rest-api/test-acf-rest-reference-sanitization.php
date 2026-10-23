<?php
/**
 * Tests that REST responses only expand references the current user can read.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests the REST reference sanitizer.
 *
 * @group rest-api
 * @group security
 */
class Test_ACF_REST_Reference_Sanitization extends BaseTestCase {

	/**
	 * A published post.
	 *
	 * @var int
	 */
	private $public_id;

	/**
	 * A draft post.
	 *
	 * @var int
	 */
	private $draft_id;

	/**
	 * A private post.
	 *
	 * @var int
	 */
	private $private_id;

	/**
	 * Test filters that must be removed during cleanup.
	 *
	 * @var array
	 */
	private $test_filters = array();

	/**
	 * Create posts with different statuses, as a logged-out visitor.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->public_id  = $this->create_post( 'publish' );
		$this->draft_id   = $this->create_post( 'draft' );
		$this->private_id = $this->create_post( 'private' );

		wp_set_current_user( 0 );
	}

	/**
	 * Remove filters and posts.
	 */
	public function tear_down(): void {
		acf_get_store( 'values' )->reset();

		foreach ( $this->test_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}
		$this->test_filters = array();

		foreach ( array( $this->public_id, $this->draft_id, $this->private_id ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		wp_set_current_user( 0 );

		parent::tear_down();
	}

	// =========================================================================
	// acf_rest_reference_is_exposable()
	// =========================================================================

	/**
	 * Logged-out visitors can only see published targets.
	 */
	public function test_is_exposable_follows_rest_read_permission() {
		$field = $this->field( 'post_object' );

		$this->assertTrue( acf_rest_reference_is_exposable( $this->public_id, $field ) );
		$this->assertFalse( acf_rest_reference_is_exposable( $this->draft_id, $field ) );
		$this->assertFalse( acf_rest_reference_is_exposable( $this->private_id, $field ) );
		$this->assertFalse( acf_rest_reference_is_exposable( 999999, $field ) );
	}

	/**
	 * Users who can edit the targets can see them.
	 */
	public function test_is_exposable_for_editor() {
		$this->set_role( 'editor' );
		$field = $this->field( 'post_object' );

		$this->assertTrue( acf_rest_reference_is_exposable( $this->draft_id, $field ) );
		$this->assertTrue( acf_rest_reference_is_exposable( $this->private_id, $field ) );
	}

	/**
	 * Password-protected posts are only exposable to users who can edit them.
	 */
	public function test_is_exposable_hides_password_protected_posts() {
		$field        = $this->field( 'post_object' );
		$protected_id = $this->create_protected_post();

		$this->assertFalse( acf_rest_reference_is_exposable( $protected_id, $field ), 'Anonymous readers' );

		$this->set_role( 'author' );
		$this->assertFalse( acf_rest_reference_is_exposable( $protected_id, $field ), 'Authors who cannot edit the post' );

		$this->set_role( 'editor' );
		$this->assertTrue( acf_rest_reference_is_exposable( $protected_id, $field ), 'Editors' );

		wp_set_current_user( 0 );
		$this->add_test_filter( 'post_password_required', '__return_false' );
		$this->assertTrue( acf_rest_reference_is_exposable( $protected_id, $field ), 'Visitors who entered the password' );

		wp_delete_post( $protected_id, true );
	}

	/**
	 * Password-protected references are reduced to IDs.
	 */
	public function test_password_protected_reference_is_reduced() {
		$protected_id = $this->create_protected_post();
		$protected    = get_post( $protected_id );

		$this->assertSame( $protected_id, acf_rest_sanitize_reference_data( $protected, $protected_id, $this->field( 'post_object' ) ) );

		$mixed = array( get_post( $this->public_id ), $protected );
		$this->assertSame(
			array( $this->public_id, $protected_id ),
			acf_rest_sanitize_reference_data( $mixed, array( $this->public_id, $protected_id ), $this->field( 'relationship' ) )
		);

		wp_delete_post( $protected_id, true );
	}

	/**
	 * The filter can expose a target the default check would reduce.
	 */
	public function test_is_exposable_filter() {
		$this->add_test_filter( 'acf/rest/expose_reference_target', '__return_true' );

		$this->assertTrue( acf_rest_reference_is_exposable( $this->draft_id, $this->field( 'post_object' ) ) );
	}

	// =========================================================================
	// Reduction
	// =========================================================================

	/**
	 * Readable single references stay expanded; unreadable ones become an ID.
	 */
	public function test_single_reference() {
		$field = $this->field( 'post_object' );

		$public = get_post( $this->public_id );
		$draft  = get_post( $this->draft_id );

		$this->assertEquals( $public, acf_rest_sanitize_reference_data( $public, $this->public_id, $field ) );
		$this->assertSame( $this->draft_id, acf_rest_sanitize_reference_data( $draft, $this->draft_id, $field ) );
	}

	/**
	 * One unreadable target reduces the whole multi-value field to IDs.
	 */
	public function test_multiple_references_are_all_or_nothing() {
		$field = $this->field( 'relationship' );

		$readable = array( get_post( $this->public_id ) );
		$this->assertEquals( $readable, acf_rest_sanitize_reference_data( $readable, array( $this->public_id ), $field ) );

		$mixed = array( get_post( $this->public_id ), get_post( $this->private_id ) );
		$this->assertSame(
			array( $this->public_id, $this->private_id ),
			acf_rest_sanitize_reference_data( $mixed, array( $this->public_id, $this->private_id ), $field )
		);
	}

	/**
	 * IDs are taken from the formatted value when the raw value is unusable.
	 */
	public function test_ids_fall_back_to_formatted_value() {
		$field = $this->field( 'image' );

		$this->assertSame(
			$this->draft_id,
			acf_rest_sanitize_reference_data( array( 'ID' => $this->draft_id ), null, $field )
		);
	}

	/**
	 * Media library icons are reduced; other icon types are left alone.
	 */
	public function test_icon_picker() {
		$field = $this->field( 'icon_picker' );

		$expanded = array(
			'type'  => 'media_library',
			'value' => array( 'ID' => $this->draft_id ),
		);
		$this->assertSame(
			array(
				'type'  => 'media_library',
				'value' => $this->draft_id,
			),
			acf_rest_sanitize_reference_data(
				$expanded,
				array(
					'type'  => 'media_library',
					'value' => $this->draft_id,
				),
				$field
			)
		);

		$dashicon = array(
			'type'  => 'dashicons',
			'value' => 'dashicons-admin-post',
		);
		$this->assertSame( $dashicon, acf_rest_sanitize_reference_data( $dashicon, $dashicon, $field ) );
	}

	/**
	 * References nested in Group, Repeater and Flexible Content fields are reduced.
	 */
	public function test_nested_references() {
		$post_object = $this->field( 'post_object' );
		$text        = $this->field( 'text' );

		$group = array(
			'key'        => 'field_group',
			'type'       => 'group',
			'sub_fields' => array( $post_object, $text ),
		);
		$this->assertSame(
			array(
				'test_post_object' => $this->draft_id,
				'test_text'        => 'kept',
			),
			acf_rest_sanitize_reference_data(
				array(
					'test_post_object' => get_post( $this->draft_id ),
					'test_text'        => 'kept',
				),
				array(
					'field_test_post_object' => $this->draft_id,
					'field_test_text'        => 'kept',
				),
				$group
			)
		);

		$repeater = array(
			'key'        => 'field_repeater',
			'type'       => 'repeater',
			'sub_fields' => array( $post_object ),
		);
		$this->assertEquals(
			array(
				array( 'test_post_object' => get_post( $this->public_id ) ),
				array( 'test_post_object' => $this->private_id ),
			),
			acf_rest_sanitize_reference_data(
				array(
					array( 'test_post_object' => get_post( $this->public_id ) ),
					array( 'test_post_object' => get_post( $this->private_id ) ),
				),
				array(
					array( 'field_test_post_object' => $this->public_id ),
					array( 'field_test_post_object' => $this->private_id ),
				),
				$repeater
			)
		);

		$flexible = array(
			'key'     => 'field_flexible',
			'type'    => 'flexible_content',
			'layouts' => array(
				array(
					'name'       => 'layout',
					'sub_fields' => array( $post_object ),
				),
			),
		);
		$this->assertSame(
			array(
				array(
					'acf_fc_layout'    => 'layout',
					'test_post_object' => $this->draft_id,
				),
			),
			acf_rest_sanitize_reference_data(
				array(
					array(
						'acf_fc_layout'    => 'layout',
						'test_post_object' => get_post( $this->draft_id ),
					),
				),
				array(
					array(
						'acf_fc_layout'          => 'layout',
						'field_test_post_object' => $this->draft_id,
					),
				),
				$flexible
			)
		);
	}

	// =========================================================================
	// Wiring
	// =========================================================================

	/**
	 * Standard REST formatting reduces unreadable references, and the sanitizer
	 * is hooked to the REST format filter.
	 */
	public function test_standard_rest_formatting_reduces_unreadable_references() {
		$field = $this->field( 'file' );

		// Stand in for the File field expanding its stored ID.
		$this->add_test_filter(
			'acf/format_value/type=file',
			function ( $value ) {
				return array(
					'ID'  => (int) $value,
					'url' => 'https://example.test/file-' . (int) $value,
				);
			}
		);

		$this->assertSame( 10, has_filter( 'acf/rest/format_value_for_rest', 'acf_rest_apply_reference_sanitizer' ) );
		$this->assertSame( $this->draft_id, acf_format_value_for_rest( $this->draft_id, $this->public_id, $field, 'standard' ) );
		acf_get_store( 'values' )->reset();
		$this->assertSame(
			array(
				'ID'  => $this->public_id,
				'url' => 'https://example.test/file-' . $this->public_id,
			),
			acf_format_value_for_rest( $this->public_id, $this->public_id, $field, 'standard' )
		);
	}

	/**
	 * The standard value used for the `_source` REST output reduces unreadable
	 * references too, without going through the REST format filter.
	 */
	public function test_source_value_reduces_unreadable_references() {
		$field = $this->field( 'file' );

		$this->add_test_filter(
			'acf/format_value/type=file',
			function ( $value ) {
				return array(
					'ID'  => (int) $value,
					'url' => 'https://example.test/file-' . (int) $value,
				);
			}
		);
		remove_filter( 'acf/rest/format_value_for_rest', 'acf_rest_apply_reference_sanitizer', 10 );

		$draft = scf_rest_format_standard_value( $this->draft_id, $this->public_id, $field );
		acf_get_store( 'values' )->reset();
		$public = scf_rest_format_standard_value( $this->public_id, $this->public_id, $field );

		add_filter( 'acf/rest/format_value_for_rest', 'acf_rest_apply_reference_sanitizer', 10, 5 );

		$this->assertSame( $this->draft_id, $draft );
		$this->assertSame(
			array(
				'ID'  => $this->public_id,
				'url' => 'https://example.test/file-' . $this->public_id,
			),
			$public
		);
	}

	/**
	 * Light formatting is left alone.
	 */
	public function test_light_format_is_not_changed() {
		$this->assertSame( 'light value', acf_rest_apply_reference_sanitizer( 'light value', $this->public_id, $this->field( 'file' ), $this->draft_id, 'light' ) );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Builds a field array of the given type.
	 *
	 * @param string $type The field type.
	 * @return array
	 */
	private function field( $type ) {
		return array(
			'key'   => 'field_test_' . $type,
			'name'  => 'test_' . $type,
			'_name' => 'test_' . $type,
			'type'  => $type,
		);
	}

	/**
	 * Creates a post with the given status.
	 *
	 * @param string $status The post status.
	 * @return int
	 */
	private function create_post( $status ) {
		return wp_insert_post(
			array(
				'post_title'  => 'Reference ' . $status,
				'post_status' => $status,
				'post_type'   => 'post',
			)
		);
	}

	/**
	 * Creates a published, password-protected post.
	 *
	 * @return int
	 */
	private function create_protected_post() {
		return wp_insert_post(
			array(
				'post_title'    => 'Reference protected',
				'post_status'   => 'publish',
				'post_type'     => 'post',
				'post_content'  => 'Protected body',
				'post_password' => 'secret',
			)
		);
	}

	/**
	 * Logs in a new user with the given role.
	 *
	 * @param string $role The role.
	 */
	private function set_role( $role ) {
		wp_set_current_user(
			wp_insert_user(
				array(
					'user_login' => $role . '_' . uniqid(),
					'user_pass'  => 'password',
					'role'       => $role,
				)
			)
		);
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
