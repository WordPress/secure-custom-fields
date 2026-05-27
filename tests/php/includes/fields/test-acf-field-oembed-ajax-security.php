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

	/**
	 * Invokes the oEmbed field-type ajax_query() handler under output
	 * buffering and returns the JSON-decoded response payload.
	 *
	 * The handler is reached via the singleton already registered by the
	 * plugin (`acf_get_field_type( 'oembed' )`); instantiating the class
	 * directly would re-run `initialize()` and pollute the global hook
	 * table that the registration tests in this class assert against.
	 *
	 * Two filters are installed for the duration of the call:
	 *
	 * - `wp_doing_ajax` is forced to true so `wp_send_json()` and
	 *   `wp_send_json_error()` route their terminal exit through
	 *   `wp_die()` (writing JSON) instead of bare `die` (which would
	 *   terminate PHPUnit itself).
	 * - `wp_die_ajax_handler` and `wp_die_json_handler` are replaced
	 *   with a handler that throws a sentinel-tagged \RuntimeException.
	 *   WorDBless installs its own handler that merely sets
	 *   `exit => false` and returns, which leaves the calling handler
	 *   free to keep running past its intended termination point and
	 *   emit a second JSON payload (corrupting the captured buffer).
	 *   Throwing models the real "exits here" semantics of production
	 *   WP without terminating the test process; the caught exception
	 *   is identified by message so unrelated runtime exceptions still
	 *   surface as failures.
	 *
	 * @return mixed The JSON-decoded response payload (associative array
	 *               on success/rejection) or null if the captured buffer
	 *               did not contain valid JSON.
	 *
	 * @throws \RuntimeException Re-thrown when an unrelated runtime
	 *                           exception bubbles out of the handler;
	 *                           the sentinel-tagged halt exception is
	 *                           consumed internally.
	 */
	private function invoke_ajax_query() {
		$halt_marker  = 'acf_oembed_ajax_halt';
		$force_ajax   = static function () {
			return true;
		};
		$halt_handler = static function () use ( $halt_marker ) {
			return static function () use ( $halt_marker ) {
				throw new \RuntimeException( esc_html( $halt_marker ) );
			};
		};

		add_filter( 'wp_doing_ajax', $force_ajax );
		add_filter( 'wp_die_ajax_handler', $halt_handler, 100 );
		add_filter( 'wp_die_json_handler', $halt_handler, 100 );

		$output    = '';
		$unrelated = null;

		ob_start();
		try {
			acf_get_field_type( 'oembed' )->ajax_query();
		} catch ( \RuntimeException $halt ) {
			if ( $halt->getMessage() !== $halt_marker ) {
				$unrelated = $halt;
			}
		} finally {
			$output = ob_get_clean();
			remove_filter( 'wp_die_json_handler', $halt_handler, 100 );
			remove_filter( 'wp_die_ajax_handler', $halt_handler, 100 );
			remove_filter( 'wp_doing_ajax', $force_ajax );
		}

		if ( null !== $unrelated ) {
			throw $unrelated;
		}

		return json_decode( $output, true );
	}

	/**
	 * An anonymous request to the oEmbed search handler must be rejected
	 * with the wp_send_json_error() JSON envelope. The capability clause
	 * of the combined guard short-circuits before any oEmbed work runs,
	 * so no outbound HTTP path is reachable and no stub is required.
	 *
	 * @return void
	 */
	public function test_ajax_query_rejects_unauthenticated_user() {
		wp_set_current_user( 0 );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test populates request superglobals to drive acf_request_args(); the handler under test is the one that performs nonce verification.
		$_POST = array(
			's'         => 'https://example.invalid/test',
			'field_key' => 'field_test_oembed',
			'nonce'     => 'irrelevant',
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Mirror of $_POST for handlers that read from $_REQUEST; the handler under test is the one that performs nonce verification.
		$_REQUEST = $_POST;

		$payload = $this->invoke_ajax_query();

		$this->assertSame(
			array( 'success' => false ),
			$payload,
			'Anonymous callers must receive the wp_send_json_error() rejection envelope.'
		);
		$this->assertArrayNotHasKey( 'url', (array) $payload, 'Rejection payload must not leak a url key.' );
		$this->assertArrayNotHasKey( 'html', (array) $payload, 'Rejection payload must not leak an html key.' );
	}

	/**
	 * An authenticated subscriber (who lacks `edit_posts`) submitting a
	 * genuinely valid nonce must still be rejected. The valid nonce is
	 * load-bearing: it forces the `acf_verify_ajax()` clause of the
	 * combined guard to evaluate true so the rejection must originate
	 * from the capability clause, proving the capability check is the
	 * gate and not the nonce check.
	 *
	 * @return void
	 */
	public function test_ajax_query_rejects_subscriber() {
		wp_set_current_user( $this->subscriber_user_id );

		$nonce = wp_create_nonce( 'acf_field_oembed_field_test_oembed' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test populates request superglobals to drive acf_request_args(); the handler under test is the one that performs nonce verification.
		$_POST = array(
			's'         => 'https://example.invalid/test',
			'field_key' => 'field_test_oembed',
			'nonce'     => $nonce,
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Mirror of $_POST for handlers that read from $_REQUEST; the handler under test is the one that performs nonce verification.
		$_REQUEST = $_POST;

		$payload = $this->invoke_ajax_query();

		$this->assertSame(
			array( 'success' => false ),
			$payload,
			'Subscribers lacking edit_posts must receive the wp_send_json_error() rejection envelope even with a valid nonce.'
		);
	}

	/**
	 * An administrator submitting a valid nonce reaches the oEmbed
	 * lookup and receives the success contract (`url` and `html` keys).
	 * A one-shot `pre_http_request` filter stubs the underlying HTTP
	 * call so the test is deterministic and performs no real outbound
	 * traffic; the filter is removed before assertions so it cannot
	 * leak to other tests, and `tear_down()` defensively clears all
	 * `pre_http_request` filters as a safety net.
	 *
	 * @return void
	 */
	public function test_ajax_query_succeeds_for_admin() {
		wp_set_current_user( $this->admin_user_id );

		$nonce = wp_create_nonce( 'acf_field_oembed_field_test_oembed' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test populates request superglobals to drive acf_request_args(); the handler under test is the one that performs nonce verification.
		$_POST = array(
			's'         => 'https://example.invalid/test',
			'field_key' => 'field_test_oembed',
			'nonce'     => $nonce,
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Mirror of $_POST for handlers that read from $_REQUEST; the handler under test is the one that performs nonce verification.
		$_REQUEST = $_POST;

		// One-shot stub for the only HTTP path the handler can reach.
		$stub = static function () {
			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => '',
				'headers'  => array(),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $stub );

		try {
			$payload = $this->invoke_ajax_query();
		} finally {
			remove_filter( 'pre_http_request', $stub );
		}

		$this->assertIsArray( $payload, 'Success response must decode to an associative array.' );
		$this->assertArrayHasKey( 'url', $payload, 'Success response must include a url key.' );
		$this->assertArrayHasKey( 'html', $payload, 'Success response must include an html key.' );

		// After removal, no pre_http_request filter callback should
		// remain so subsequent tests in this class and other test
		// classes cannot inherit the stub.
		$this->assertFalse(
			has_filter( 'pre_http_request', $stub ),
			'The pre_http_request stub must be removed before the test exits.'
		);
	}
}
