<?php
/**
 * Tests for the oEmbed field type AJAX authorization surface.
 *
 * Covers the registration of the oEmbed search AJAX hooks and (in later
 * additions) the capability-gated rejection behaviour of the handler.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the oEmbed field type so its initialize() runs and the AJAX
// action registrations are present in the global hook table before
// WorDBless snapshots hook state on the first test.
acf_include( 'includes/fields/class-acf-field-oembed.php' );

/**
 * Class Test_ACF_Field_Oembed_Ajax_Security
 *
 * Verifies that the oEmbed search AJAX action is registered only for
 * authenticated requests, and that the handler enforces a capability
 * check for any anonymous or low-privilege caller.
 *
 * @group fields
 * @group ajax
 * @group security
 */
class Test_ACF_Field_Oembed_Ajax_Security extends BaseTestCase {

	/**
	 * Administrator user ID created in set_up().
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Subscriber user ID created in set_up().
	 *
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * Set up test fixtures.
	 *
	 * Creates one administrator and one subscriber user, registers a
	 * minimal local oEmbed field used by the handler-level tests, and
	 * resets the request superglobals so each test starts from a known
	 * empty request state.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// Create an admin user.
		$this->admin_user_id = wp_insert_user(
			array(
				'user_login' => 'admin_user',
				'user_pass'  => 'password',
				'user_email' => 'admin@example.com',
				'role'       => 'administrator',
			)
		);

		// Create a subscriber user.
		$this->subscriber_user_id = wp_insert_user(
			array(
				'user_login' => 'subscriber_user',
				'user_pass'  => 'password',
				'user_email' => 'subscriber@example.com',
				'role'       => 'subscriber',
			)
		);

		// Register the shared test oEmbed field so handler tests have a
		// stable field key to reference.
		acf_add_local_field(
			array(
				'key'  => 'field_test_oembed',
				'name' => 'test_oembed',
				'type' => 'oembed',
			)
		);

		// Clear any existing request data.
		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
	}

	/**
	 * Tear down test fixtures.
	 *
	 * Resets the current user, clears request superglobals, removes the
	 * locally registered test field, and removes any one-shot
	 * `pre_http_request` filter a handler test may have installed so
	 * subsequent tests start from a clean global state.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// Clear user.
		wp_set_current_user( 0 );

		// Clear request globals.
		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();

		// De-register the shared test field so the local-field registry
		// is shaped the same after each test as before it.
		acf_remove_local_field( 'field_test_oembed' );

		// Remove any one-shot pre_http_request filter a handler test may
		// have installed (handler tests are added in a later task; this
		// keeps tear_down defensive against cross-test contamination).
		remove_all_filters( 'pre_http_request' );

		parent::tear_down();
	}

	/**
	 * The unauthenticated AJAX action for the oEmbed search must not be
	 * registered. Visitors without a session must have no entry point
	 * into the handler at the WordPress action layer.
	 *
	 * @return void
	 */
	public function test_nopriv_hook_is_not_registered() {
		$this->assertFalse(
			has_action( 'wp_ajax_nopriv_acf/fields/oembed/search' ),
			'The wp_ajax_nopriv_acf/fields/oembed/search action must not be registered.'
		);
	}

	/**
	 * The authenticated AJAX action for the oEmbed search must remain
	 * registered so legitimate logged-in editors continue to receive
	 * embed previews.
	 *
	 * @return void
	 */
	public function test_authenticated_hook_is_registered() {
		$this->assertNotFalse(
			has_action( 'wp_ajax_acf/fields/oembed/search' ),
			'The wp_ajax_acf/fields/oembed/search action must remain registered.'
		);
	}
}
