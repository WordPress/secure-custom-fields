<?php
/**
 * Tests that comment and user form saves only accept fields from the field
 * groups that apply to the object being saved.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

acf_include( 'includes/forms/form-comment.php' );
acf_include( 'includes/forms/form-user.php' );

/**
 * Class Test_Form_Allowed_Field_Keys
 */
class Test_Form_Allowed_Field_Keys extends BaseTestCase {

	/**
	 * Post the test comment belongs to.
	 *
	 * @var integer
	 */
	private $post_id;

	/**
	 * Test comment.
	 *
	 * @var integer
	 */
	private $comment_id;

	/**
	 * Test user.
	 *
	 * @var integer
	 */
	private $user_id;

	/**
	 * $_POST['acf'] as seen by acf/save_post, or null when no save ran.
	 *
	 * @var array|null
	 */
	private $saved_values;

	/**
	 * Test filters that must be removed during cleanup.
	 *
	 * @var array
	 */
	private $test_filters = array();

	/**
	 * Sets up local field groups for comments, the register form and the edit-user form.
	 */
	public function set_up() {
		parent::set_up();

		acf_reset_local();
		acf_enable_local();
		$this->reset_stores();

		acf_include( 'includes/fields/class-acf-field-text.php' );
		if ( ! acf_get_field_type( 'text' ) ) {
			acf_register_field_type( 'acf_field_text' );
		}

		acf_include( 'includes/locations/class-acf-location-comment.php' );
		acf_include( 'includes/locations/class-acf-location-user-form.php' );
		if ( ! acf_get_location_type( 'comment' ) ) {
			acf_register_location_type( 'ACF_Location_Comment' );
		}
		if ( ! acf_get_location_type( 'user_form' ) ) {
			acf_register_location_type( 'ACF_Location_User_Form' );
		}

		if ( ! has_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts' ) ) {
			add_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts', 20, 2 );
		}

		$this->add_group( 'group_post_comments', 'comment', 'post', array( 'field_post_comment' ) );
		$this->add_group( 'group_page_comments', 'comment', 'page', array( 'field_page_comment' ) );
		$this->add_group( 'group_register', 'user_form', 'register', array( 'field_register' ) );
		$this->add_group( 'group_add', 'user_form', 'add', array( 'field_add' ) );
		$this->add_group( 'group_edit', 'user_form', 'edit', array( 'field_edit' ) );

		$this->post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Allowed keys post',
				'post_status' => 'publish',
			)
		);
		// WorDBless does not store comments, so seed the comment cache that
		// get_comment() reads before querying the database.
		$this->comment_id = 424242;
		wp_cache_set(
			$this->comment_id,
			(object) array(
				'comment_ID'      => (string) $this->comment_id,
				'comment_post_ID' => (string) $this->post_id,
				'comment_content' => 'Allowed keys comment',
			),
			'comment'
		);
		$this->user_id = wp_insert_user(
			array(
				'user_login' => 'allowed_keys_' . uniqid(),
				'user_pass'  => 'password',
			)
		);

		$this->saved_values = null;
		$this->add_test_filter( 'acf/save_post', array( $this, 'capture_saved_values' ), 1 );

		$_POST    = array();
		$_FILES   = array();
		$_REQUEST = array();
	}

	/**
	 * Removes local fields, request data and filters.
	 */
	public function tear_down() {
		foreach ( $this->test_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}
		$this->test_filters = array();

		wp_cache_delete( $this->comment_id, 'comment' );

		acf_reset_local();
		acf_enable_local();
		$this->reset_stores();

		$_POST    = array();
		$_FILES   = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Records $_POST['acf'] when a save runs.
	 */
	public function capture_saved_values() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Test reads the request it built.
		$this->saved_values = isset( $_POST['acf'] ) ? $_POST['acf'] : array();
	}

	// =========================================================================
	// Comments
	// =========================================================================

	/**
	 * Fields from groups for another post type are dropped from a comment save.
	 */
	public function test_save_comment_drops_fields_not_assigned_to_comment() {
		$this->submit(
			'comment',
			array(
				'field_post_comment' => 'kept',
				'field_page_comment' => 'dropped',
				'field_unknown'      => 'dropped',
			)
		);

		( new acf_form_comment() )->save_comment( $this->comment_id );

		$this->assertSame( array( 'field_post_comment' => 'kept' ), $this->saved_values );
	}

	/**
	 * A comment save with no allowed fields does not save anything.
	 */
	public function test_save_comment_skips_save_without_allowed_fields() {
		$this->submit( 'comment', array( 'field_page_comment' => 'dropped' ) );

		$result = ( new acf_form_comment() )->save_comment( $this->comment_id );

		$this->assertSame( $this->comment_id, $result );
		$this->assertNull( $this->saved_values );
	}

	/**
	 * A non-array acf submission does not save anything.
	 */
	public function test_save_comment_skips_save_for_non_array_values() {
		$this->submit( 'comment', 'not-an-array' );

		( new acf_form_comment() )->save_comment( $this->comment_id );

		$this->assertNull( $this->saved_values );
	}

	/**
	 * A comment that does not exist accepts no fields.
	 */
	public function test_save_comment_skips_save_for_missing_comment() {
		$this->submit( 'comment', array( 'field_post_comment' => 'dropped' ) );

		( new acf_form_comment() )->save_comment( 999999 );

		$this->assertNull( $this->saved_values );
	}

	/**
	 * The comment allowed-keys filter can allow extra keys, and receives the comment ID.
	 */
	public function test_save_comment_allowed_keys_filter_adds_keys() {
		$received = null;
		$this->add_test_filter(
			'acf/form/comment/allowed_field_keys',
			function ( $keys, $comment_id ) use ( &$received ) {
				$received = $comment_id;
				$keys[]   = 'field_injected';
				return $keys;
			},
			10,
			2
		);
		$this->submit(
			'comment',
			array(
				'field_post_comment' => 'kept',
				'field_injected'     => 'kept',
			)
		);

		( new acf_form_comment() )->save_comment( $this->comment_id );

		$this->assertSame( $this->comment_id, $received );
		$this->assertSame(
			array(
				'field_post_comment' => 'kept',
				'field_injected'     => 'kept',
			),
			$this->saved_values
		);
	}

	/**
	 * Allowed values are still sanitized with kses.
	 */
	public function test_save_comment_still_sanitizes_allowed_values() {
		$this->submit( 'comment', array( 'field_post_comment' => '<script>alert(1)</script><p>Safe</p>' ) );

		( new acf_form_comment() )->save_comment( $this->comment_id );

		$this->assertStringNotContainsString( '<script>', $this->saved_values['field_post_comment'] );
		$this->assertStringContainsString( '<p>Safe</p>', $this->saved_values['field_post_comment'] );
	}

	// =========================================================================
	// Users
	// =========================================================================

	/**
	 * Saving an existing user only accepts fields from edit-form groups.
	 */
	public function test_save_user_profile_update_only_accepts_edit_form_fields() {
		$this->submit(
			'user',
			array(
				'field_edit'     => 'kept',
				'field_register' => 'dropped',
				'field_add'      => 'dropped',
			)
		);

		$this->run_in_action( 'profile_update', $this->user_id );

		$this->assertSame( array( 'field_edit' => 'kept' ), $this->saved_values );
	}

	/**
	 * Registering a user accepts fields from register and add-user form groups.
	 * "Add / Edit" groups (stored as "edit") render on the add-user form, so
	 * they are accepted too.
	 */
	public function test_save_user_register_accepts_register_and_add_form_fields() {
		$this->submit(
			'user',
			array(
				'field_register'     => 'kept',
				'field_add'          => 'kept',
				'field_edit'         => 'kept',
				'field_post_comment' => 'dropped',
			)
		);

		$this->run_in_action( 'user_register', $this->user_id );

		$this->assertSame(
			array(
				'field_register' => 'kept',
				'field_add'      => 'kept',
				'field_edit'     => 'kept',
			),
			$this->saved_values
		);
	}

	/**
	 * A user save with no allowed fields does not save anything.
	 */
	public function test_save_user_skips_save_without_allowed_fields() {
		$this->submit( 'user', array( 'field_register' => 'dropped' ) );

		$this->run_in_action( 'profile_update', $this->user_id );

		$this->assertNull( $this->saved_values );
	}

	/**
	 * The user allowed-keys filter can allow extra keys, and a misbehaving
	 * callback cannot break the save.
	 */
	public function test_save_user_allowed_keys_filter_is_normalized() {
		$this->add_test_filter(
			'acf/form/user/allowed_field_keys',
			function ( $keys ) {
				return array_merge( $keys, array( 'field_injected', array( 'nested' ), null, '' ) );
			}
		);
		$this->submit(
			'user',
			array(
				'field_edit'     => 'kept',
				'field_injected' => 'kept',
			)
		);

		$this->run_in_action( 'profile_update', $this->user_id );

		$this->assertSame(
			array(
				'field_edit'     => 'kept',
				'field_injected' => 'kept',
			),
			$this->saved_values
		);
	}

	// =========================================================================
	// Uploads
	// =========================================================================

	/**
	 * Uploaded files for fields that are not allowed are dropped from comment saves.
	 */
	public function test_save_comment_drops_uploads_for_fields_not_allowed() {
		$this->submit( 'comment', array( 'field_page_comment' => 'dropped' ) );
		$this->add_upload_fixture( array( 'field_post_comment', 'field_page_comment' ) );

		( new acf_form_comment() )->save_comment( $this->comment_id );

		$this->assertUploadKeys( array( 'field_post_comment' ) );
	}

	/**
	 * Uploaded files for fields that are not allowed are dropped from user saves.
	 */
	public function test_save_user_drops_uploads_for_fields_not_allowed() {
		$this->submit( 'user', array( 'field_register' => 'dropped' ) );
		$this->add_upload_fixture( array( 'field_edit', 'field_register' ) );

		$this->run_in_action( 'profile_update', $this->user_id );

		$this->assertUploadKeys( array( 'field_edit' ) );
	}

	/**
	 * Adds an upload request with no actual files for the given field keys.
	 *
	 * @param string[] $keys The field keys.
	 */
	private function add_upload_fixture( array $keys ) {
		$branch = array_fill_keys( $keys, '' );

		$_FILES['acf'] = array(
			'name'     => $branch,
			'type'     => $branch,
			'tmp_name' => $branch,
			'error'    => array_fill_keys( $keys, UPLOAD_ERR_NO_FILE ),
			'size'     => array_fill_keys( $keys, 0 ),
		);
	}

	/**
	 * Asserts the field keys left in every uploaded file attribute.
	 *
	 * @param string[] $expected The expected field keys.
	 */
	private function assertUploadKeys( array $expected ) {
		// phpcs:disable WordPress.Security -- Test reads the request it built.
		foreach ( $_FILES['acf'] as $attribute => $branch ) {
			$this->assertSame( $expected, array_keys( $branch ), "Unexpected keys in {$attribute}." );
		}
		// phpcs:enable WordPress.Security
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Builds a nonce-verified submission for the given screen.
	 *
	 * @param string       $screen The ACF form screen.
	 * @param array|string $values The submitted acf values.
	 */
	private function submit( $screen, $values ) {
		$_POST['_acf_screen'] = $screen;
		$_POST['_acf_nonce']  = wp_create_nonce( $screen );
		$_POST['acf']         = $values;
	}

	/**
	 * Calls save_user() as if it ran inside the given action.
	 *
	 * @param string  $action  The action name.
	 * @param integer $user_id The user ID.
	 */
	private function run_in_action( $action, $user_id ) {
		global $wp_current_filter;

		$wp_current_filter[] = $action; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates the current action for save_user().
		try {
			( new ACF_Form_User() )->save_user( $user_id );
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * Registers a local field group with one text field per key.
	 *
	 * @param string $group_key The field group key.
	 * @param string $param     The location rule param.
	 * @param string $value     The location rule value.
	 * @param array  $keys      The field keys.
	 */
	private function add_group( $group_key, $param, $value, array $keys ) {
		$fields = array();
		foreach ( $keys as $key ) {
			$fields[] = array(
				'key'   => $key,
				'name'  => preg_replace( '/^field_/', '', $key ),
				'label' => $key,
				'type'  => 'text',
			);
		}

		acf_add_local_field_group(
			array(
				'key'      => $group_key,
				'title'    => $group_key,
				'fields'   => $fields,
				'active'   => true,
				'location' => array(
					array(
						array(
							'param'    => $param,
							'operator' => '==',
							'value'    => $value,
						),
					),
				),
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

	/**
	 * Resets the stores the fixtures write to.
	 */
	private function reset_stores() {
		foreach ( array( 'local-empty', 'fields', 'field-groups', 'values', 'form' ) as $store_name ) {
			$store = acf_get_store( $store_name );
			if ( $store ) {
				$store->reset();
			}
		}
	}
}
