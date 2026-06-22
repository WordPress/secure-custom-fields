<?php
/**
 * Tests for the oEmbed field type AJAX preview surface.
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
 * Verifies that the oEmbed search AJAX action remains available for valid
 * field-nonce callers while restricting URL discovery for users who cannot
 * author content.
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
	 * The last URL observed by the oEmbed stub.
	 *
	 * @var string
	 */
	private $last_oembed_url = '';

	/**
	 * The last argument array observed by the oEmbed stub.
	 *
	 * @var array
	 */
	private $last_oembed_args = array();

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_user_id = wp_insert_user(
			array(
				'user_login' => 'admin_user',
				'user_pass'  => 'password',
				'user_email' => 'admin@example.com',
				'role'       => 'administrator',
			)
		);

		$this->subscriber_user_id = wp_insert_user(
			array(
				'user_login' => 'subscriber_user',
				'user_pass'  => 'password',
				'user_email' => 'subscriber@example.com',
				'role'       => 'subscriber',
			)
		);

		acf_add_local_field(
			array(
				'key'  => 'field_test_oembed',
				'name' => 'test_oembed',
				'type' => 'oembed',
			)
		);

		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();

		$this->last_oembed_url  = '';
		$this->last_oembed_args = array();
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		wp_set_current_user( 0 );

		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();

		acf_remove_local_field( 'field_test_oembed' );
		remove_filter( 'acf/fields/oembed/allow_discovery', '__return_true' );

		$this->last_oembed_url  = '';
		$this->last_oembed_args = array();

		parent::tear_down();
	}

	/**
	 * The unauthenticated AJAX action for the oEmbed search remains registered
	 * so front-end forms can continue to request previews with a valid field
	 * nonce.
	 *
	 * @return void
	 */
	public function test_nopriv_hook_remains_registered() {
		$this->assertNotFalse(
			has_action( 'wp_ajax_nopriv_acf/fields/oembed/search' ),
			'The wp_ajax_nopriv_acf/fields/oembed/search action must remain registered.'
		);
	}

	/**
	 * The authenticated AJAX action for the oEmbed search must remain registered
	 * so legitimate logged-in editors continue to receive embed previews.
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
	 * Installs a deterministic oEmbed stub and records the URL/args passed to
	 * WordPress's oEmbed layer.
	 *
	 * @param string $html The HTML to return from the oEmbed request.
	 * @return callable
	 */
	private function install_oembed_stub( $html = '<div class="oembed-stub"></div>' ) {
		$stub = function ( $result, $url, $args ) use ( $html ) {
			$this->last_oembed_url  = $url;
			$this->last_oembed_args = $args;

			return $html;
		};

		add_filter( 'pre_oembed_result', $stub, 10, 3 );

		return $stub;
	}

	/**
	 * Invokes the oEmbed field-type ajax_query() handler under output buffering
	 * and returns the JSON-decoded response payload.
	 *
	 * @return mixed The JSON-decoded response payload.
	 *
	 * @throws \RuntimeException Re-thrown when an unrelated runtime exception
	 *                           bubbles out of the handler.
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
	 * Sets the request payload expected by ajax_query().
	 *
	 * @param string $nonce The field nonce.
	 * @return void
	 */
	private function set_ajax_request( $nonce ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test populates request superglobals to drive the handler under test.
		$_POST = array(
			's'         => 'https://example.invalid/test',
			'field_key' => 'field_test_oembed',
			'nonce'     => $nonce,
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Mirror of $_POST for handlers that read from $_REQUEST.
		$_REQUEST = $_POST;
	}

	/**
	 * Anonymous callers with a valid field nonce can request a preview, but URL
	 * discovery is disabled.
	 *
	 * @return void
	 */
	public function test_ajax_query_allows_anonymous_valid_nonce_with_discovery_disabled() {
		wp_set_current_user( 0 );

		$nonce = wp_create_nonce( 'acf_field_oembed_field_test_oembed' );
		$this->set_ajax_request( $nonce );

		$stub = $this->install_oembed_stub();

		try {
			$payload = $this->invoke_ajax_query();
		} finally {
			remove_filter( 'pre_oembed_result', $stub, 10 );
		}

		$this->assertSame( 'https://example.invalid/test', $payload['url'] );
		$this->assertSame( '<div class="oembed-stub"></div>', $payload['html'] );
		$this->assertSame( 'https://example.invalid/test', $this->last_oembed_url );
		$this->assertArrayHasKey( 'discover', $this->last_oembed_args );
		$this->assertFalse( $this->last_oembed_args['discover'], 'Anonymous preview requests must not enable oEmbed discovery.' );
	}

	/**
	 * Authenticated subscribers with a valid field nonce can request a preview,
	 * but URL discovery is disabled because they cannot author content.
	 *
	 * @return void
	 */
	public function test_ajax_query_allows_subscriber_valid_nonce_with_discovery_disabled() {
		wp_set_current_user( $this->subscriber_user_id );

		$nonce = wp_create_nonce( 'acf_field_oembed_field_test_oembed' );
		$this->set_ajax_request( $nonce );

		$stub = $this->install_oembed_stub();

		try {
			$payload = $this->invoke_ajax_query();
		} finally {
			remove_filter( 'pre_oembed_result', $stub, 10 );
		}

		$this->assertSame( 'https://example.invalid/test', $payload['url'] );
		$this->assertSame( '<div class="oembed-stub"></div>', $payload['html'] );
		$this->assertArrayHasKey( 'discover', $this->last_oembed_args );
		$this->assertFalse( $this->last_oembed_args['discover'], 'Subscriber preview requests must not enable oEmbed discovery.' );
	}

	/**
	 * Administrators with a valid field nonce can request a preview with URL
	 * discovery enabled.
	 *
	 * @return void
	 */
	public function test_ajax_query_allows_admin_valid_nonce_with_discovery_enabled() {
		wp_set_current_user( $this->admin_user_id );

		$nonce = wp_create_nonce( 'acf_field_oembed_field_test_oembed' );
		$this->set_ajax_request( $nonce );

		$stub = $this->install_oembed_stub();

		try {
			$payload = $this->invoke_ajax_query();
		} finally {
			remove_filter( 'pre_oembed_result', $stub, 10 );
		}

		$this->assertSame( 'https://example.invalid/test', $payload['url'] );
		$this->assertSame( '<div class="oembed-stub"></div>', $payload['html'] );
		$this->assertArrayHasKey( 'discover', $this->last_oembed_args );
		$this->assertTrue( $this->last_oembed_args['discover'], 'Administrator preview requests should enable oEmbed discovery.' );
	}

	/**
	 * The allow_discovery filter can opt a site into discovery for a lower
	 * privilege caller when that site accepts the tradeoff.
	 *
	 * @return void
	 */
	public function test_allow_discovery_filter_can_enable_discovery_for_subscriber() {
		wp_set_current_user( $this->subscriber_user_id );

		add_filter( 'acf/fields/oembed/allow_discovery', '__return_true' );

		$nonce = wp_create_nonce( 'acf_field_oembed_field_test_oembed' );
		$this->set_ajax_request( $nonce );

		$stub = $this->install_oembed_stub();

		try {
			$payload = $this->invoke_ajax_query();
		} finally {
			remove_filter( 'pre_oembed_result', $stub, 10 );
			remove_filter( 'acf/fields/oembed/allow_discovery', '__return_true' );
		}

		$this->assertSame( 'https://example.invalid/test', $payload['url'] );
		$this->assertArrayHasKey( 'discover', $this->last_oembed_args );
		$this->assertTrue( $this->last_oembed_args['discover'], 'The allow_discovery filter should be able to enable discovery.' );
	}
}
