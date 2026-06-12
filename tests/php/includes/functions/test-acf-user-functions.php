<?php
/**
 * Tests for functions in acf-user-functions.php.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_ACF_User_Functions
 *
 * Tests user helper functions. WP_User_Query does not run real SQL under
 * WorDBless, so get_users() results are shimmed via 'users_pre_query'.
 *
 * @covers ::acf_get_users
 * @covers ::acf_get_user_result
 * @covers ::acf_get_user_role_labels
 * @covers ::acf_allow_unfiltered_html
 */
class Test_ACF_User_Functions extends BaseTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private $editor_user_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_user_id = wp_insert_user(
			array(
				'user_login' => 'uf_admin_user',
				'user_pass'  => 'password',
				'user_email' => 'uf_admin@example.com',
				'role'       => 'administrator',
			)
		);

		$this->editor_user_id = wp_insert_user(
			array(
				'user_login' => 'uf_editor_user',
				'user_pass'  => 'password',
				'user_email' => 'uf_editor@example.com',
				'role'       => 'editor',
				'first_name' => 'Edna',
				'last_name'  => 'Editor',
			)
		);

		$this->subscriber_user_id = wp_insert_user(
			array(
				'user_login' => 'uf_subscriber_user',
				'user_pass'  => 'password',
				'user_email' => 'uf_subscriber@example.com',
				'role'       => 'subscriber',
				'first_name' => 'Solo',
			)
		);
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Shims WP_User_Query results via the users_pre_query filter.
	 *
	 * @param array $user_ids User IDs to return.
	 */
	private function shim_user_query( array $user_ids ) {
		add_filter(
			'users_pre_query',
			function ( $results, $query ) use ( $user_ids ) {
				$query->total_users = count( $user_ids );
				return $user_ids;
			},
			10,
			2
		);
	}

	// =========================================================================
	// acf_get_users()
	// =========================================================================

	/**
	 * Test that acf_get_users returns user objects.
	 */
	public function test_acf_get_users_returns_users() {
		$this->shim_user_query( array( $this->admin_user_id, $this->editor_user_id ) );

		$users = acf_get_users( array( 'include' => array( $this->admin_user_id, $this->editor_user_id ) ) );

		$this->assertCount( 2, $users );
		$this->assertInstanceOf( 'WP_User', $users[0] );
	}

	/**
	 * Test that acf_get_users reorders results to match the include order.
	 */
	public function test_acf_get_users_maintains_include_order() {
		// The shim returns users in admin-first order.
		$this->shim_user_query( array( $this->admin_user_id, $this->editor_user_id, $this->subscriber_user_id ) );

		// Request a different order: editor, subscriber, admin.
		$include = array( $this->editor_user_id, $this->subscriber_user_id, $this->admin_user_id );
		$users   = acf_get_users( array( 'include' => $include ) );

		$this->assertSame( $include, wp_list_pluck( $users, 'ID' ), 'Results should follow the include order' );
	}

	/**
	 * Test that acf_get_users emits a PHP warning when no include arg is passed.
	 */
	public function test_acf_get_users_warns_without_include_arg() {
		$this->shim_user_query( array( $this->admin_user_id ) );

		// NOTE: documents current behavior — possible bug: acf_get_users() reads
		// $args['include'] without isset() (includes/acf-user-functions.php:20),
		// so any call without an 'include' arg that finds users raises an
		// "Undefined array key" warning on PHP 8+ (notice on PHP 7.4).
		// Tracked in #457.
		$message = null;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- capturing the documented diagnostic in a test.
		set_error_handler(
			function ( $errno, $errstr ) use ( &$message ) {
				$message = $errstr;
				return true;
			},
			E_WARNING | E_NOTICE
		);
		try {
			acf_get_users( array() );
		} finally {
			restore_error_handler();
		}

		$this->assertNotNull( $message, 'Calling acf_get_users() without include should raise a warning or notice' );
		$this->assertStringContainsString( 'include', $message );
	}

	/**
	 * Test that acf_get_users returns an empty array when no users are found.
	 */
	public function test_acf_get_users_returns_empty_array_when_no_users_found() {
		// No shim: WP_User_Query returns no results under WorDBless.
		$users = acf_get_users( array() );

		$this->assertSame( array(), $users );
	}

	// =========================================================================
	// acf_get_user_result()
	// =========================================================================

	/**
	 * Test result format for a user with first and last name.
	 */
	public function test_acf_get_user_result_with_full_name() {
		$result = acf_get_user_result( get_user_by( 'id', $this->editor_user_id ) );

		$this->assertSame(
			array(
				'id'   => $this->editor_user_id,
				'text' => 'uf_editor_user (Edna Editor)',
			),
			$result
		);
	}

	/**
	 * Test result format for a user with a first name only.
	 */
	public function test_acf_get_user_result_with_first_name_only() {
		$result = acf_get_user_result( get_user_by( 'id', $this->subscriber_user_id ) );

		$this->assertSame( 'uf_subscriber_user (Solo)', $result['text'] );
	}

	/**
	 * Test result format for a user without names.
	 */
	public function test_acf_get_user_result_without_names() {
		$result = acf_get_user_result( get_user_by( 'id', $this->admin_user_id ) );

		$this->assertSame(
			array(
				'id'   => $this->admin_user_id,
				'text' => 'uf_admin_user',
			),
			$result
		);
	}

	// =========================================================================
	// acf_get_user_role_labels()
	// =========================================================================

	/**
	 * Test that all role labels are returned by default.
	 */
	public function test_acf_get_user_role_labels_returns_all_roles() {
		$labels = acf_get_user_role_labels();

		$this->assertSame( 'Administrator', $labels['administrator'] );
		$this->assertSame( 'Editor', $labels['editor'] );
		$this->assertSame( 'Author', $labels['author'] );
		$this->assertSame( 'Contributor', $labels['contributor'] );
		$this->assertSame( 'Subscriber', $labels['subscriber'] );
	}

	/**
	 * Test that a subset of roles can be requested.
	 */
	public function test_acf_get_user_role_labels_with_subset() {
		$labels = acf_get_user_role_labels( array( 'editor', 'subscriber' ) );

		$this->assertSame(
			array(
				'editor'     => 'Editor',
				'subscriber' => 'Subscriber',
			),
			$labels
		);
	}

	/**
	 * Test that unknown roles are ignored.
	 */
	public function test_acf_get_user_role_labels_ignores_unknown_roles() {
		$labels = acf_get_user_role_labels( array( 'editor', 'nonexistent_role' ) );

		$this->assertSame( array( 'editor' => 'Editor' ), $labels );
		$this->assertArrayNotHasKey( 'nonexistent_role', $labels );
	}

	// =========================================================================
	// acf_allow_unfiltered_html()
	// =========================================================================

	/**
	 * Test that administrators may save unfiltered HTML.
	 */
	public function test_acf_allow_unfiltered_html_true_for_admin() {
		wp_set_current_user( $this->admin_user_id );

		$this->assertTrue( acf_allow_unfiltered_html() );
	}

	/**
	 * Test that subscribers may not save unfiltered HTML.
	 */
	public function test_acf_allow_unfiltered_html_false_for_subscriber() {
		wp_set_current_user( $this->subscriber_user_id );

		$this->assertFalse( acf_allow_unfiltered_html() );
	}

	/**
	 * Test that the acf/allow_unfiltered_html filter can override the capability.
	 */
	public function test_acf_allow_unfiltered_html_filter_override() {
		wp_set_current_user( $this->subscriber_user_id );

		add_filter( 'acf/allow_unfiltered_html', '__return_true' );

		$this->assertTrue( acf_allow_unfiltered_html() );
	}
}
