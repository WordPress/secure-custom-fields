<?php
/**
 * Tests for AJAX handlers security.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the AJAX classes.
acf_include( 'includes/ajax/class-acf-ajax.php' );
acf_include( 'includes/ajax/class-acf-ajax-query.php' );
acf_include( 'includes/ajax/class-acf-ajax-query-users.php' );
acf_include( 'includes/ajax/class-acf-ajax-check-screen.php' );
acf_include( 'includes/ajax/class-acf-ajax-upgrade.php' );
acf_include( 'includes/ajax/class-acf-ajax-user-setting.php' );
acf_include( 'includes/ajax/class-acf-ajax-local-json-diff.php' );

/**
 * Class Test_Ajax_Handlers
 *
 * Tests security aspects of AJAX handlers including nonce verification,
 * capability checks, and input sanitization.
 *
 * @group ajax
 * @group security
 * @group p0-critical
 */
class Test_Ajax_Handlers extends BaseTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_user_id;

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

		// Clear any existing request data.
		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		parent::tear_down();

		// Clear user.
		wp_set_current_user( 0 );

		// Clear request globals.
		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
	}

	// =========================================================================
	// ACF_Ajax Base Class Tests
	// =========================================================================

	/**
	 * Test that ACF_Ajax class exists and has expected properties.
	 */
	public function test_acf_ajax_class_exists() {
		$this->assertTrue( class_exists( 'ACF_Ajax' ), 'ACF_Ajax class should exist' );
	}

	/**
	 * Test that ACF_Ajax defaults to non-public (logged-in users only).
	 */
	public function test_acf_ajax_defaults_to_non_public() {
		$ajax = new ACF_Ajax();

		$this->assertFalse( $ajax->public, 'ACF_Ajax should default to non-public' );
	}

	/**
	 * Test that verify_request returns WP_Error for invalid nonce.
	 */
	public function test_verify_request_rejects_invalid_nonce() {
		wp_set_current_user( $this->admin_user_id );

		$ajax = new ACF_Ajax();

		// Set invalid nonce in request.
		$request = array( 'nonce' => 'invalid_nonce_value' );

		$result = $ajax->verify_request( $request );

		$this->assertInstanceOf( 'WP_Error', $result, 'Invalid nonce should return WP_Error' );
		$this->assertSame( 'acf_invalid_nonce', $result->get_error_code(), 'Error code should be acf_invalid_nonce' );
	}

	/**
	 * Test that verify_request returns WP_Error when nonce is missing.
	 */
	public function test_verify_request_rejects_missing_nonce() {
		wp_set_current_user( $this->admin_user_id );

		$ajax = new ACF_Ajax();

		$result = $ajax->verify_request( array() );

		$this->assertInstanceOf( 'WP_Error', $result, 'Missing nonce should return WP_Error' );
	}

	/**
	 * Test that verify_request accepts valid nonce.
	 */
	public function test_verify_request_accepts_valid_nonce() {
		wp_set_current_user( $this->admin_user_id );

		$ajax = new ACF_Ajax();

		// Create a valid nonce and set in $_REQUEST as acf_verify_ajax reads from there.
		$nonce             = wp_create_nonce( 'acf_nonce' );
		$_REQUEST['nonce'] = $nonce;
		$request           = array( 'nonce' => $nonce );

		$result = $ajax->verify_request( $request );

		$this->assertTrue( $result, 'Valid nonce should return true' );

		unset( $_REQUEST['nonce'] );
	}

	/**
	 * Test that has() method correctly identifies request keys.
	 */
	public function test_has_method_identifies_request_keys() {
		$ajax          = new ACF_Ajax();
		$ajax->request = array(
			'existing_key' => 'some_value',
			'empty_string' => '',
			'zero_value'   => 0,
		);

		$this->assertTrue( $ajax->has( 'existing_key' ), 'Should return true for existing key' );
		$this->assertTrue( $ajax->has( 'empty_string' ), 'Should return true for empty string' );
		$this->assertTrue( $ajax->has( 'zero_value' ), 'Should return true for zero value' );
		$this->assertFalse( $ajax->has( 'missing_key' ), 'Should return false for missing key' );
	}

	/**
	 * Test that get() method returns correct values.
	 */
	public function test_get_method_returns_correct_values() {
		$ajax          = new ACF_Ajax();
		$ajax->request = array(
			'string_value' => 'test_string',
			'int_value'    => 42,
			'array_value'  => array( 'a', 'b' ),
		);

		$this->assertSame( 'test_string', $ajax->get( 'string_value' ) );
		$this->assertSame( 42, $ajax->get( 'int_value' ) );
		$this->assertSame( array( 'a', 'b' ), $ajax->get( 'array_value' ) );
		$this->assertNull( $ajax->get( 'missing_key' ), 'Should return null for missing key' );
	}

	/**
	 * Test that set() method correctly sets values and returns instance.
	 */
	public function test_set_method_updates_request() {
		$ajax = new ACF_Ajax();

		$result = $ajax->set( 'test_key', 'test_value' );

		$this->assertSame( $ajax, $result, 'set() should return the instance for chaining' );
		$this->assertSame( 'test_value', $ajax->get( 'test_key' ) );
	}

	// =========================================================================
	// ACF_Ajax_Query Tests (PUBLIC Endpoint - Security Critical)
	// =========================================================================

	/**
	 * Test that ACF_Ajax_Query is marked as public.
	 */
	public function test_ajax_query_is_public() {
		$ajax = new ACF_Ajax_Query();

		$this->assertTrue( $ajax->public, 'ACF_Ajax_Query should be public (accessible to unauthenticated users)' );
	}

	/**
	 * Test that ACF_Ajax_Query still requires nonce verification.
	 */
	public function test_ajax_query_requires_nonce_despite_being_public() {
		$ajax = new ACF_Ajax_Query();

		// Attempt without nonce should fail.
		$result = $ajax->verify_request( array() );

		$this->assertInstanceOf( 'WP_Error', $result, 'ACF_Ajax_Query should still verify nonce' );
	}

	/**
	 * Test that init_request sanitizes page parameter.
	 */
	public function test_ajax_query_sanitizes_page_parameter() {
		$ajax = new ACF_Ajax_Query();

		// Test with various inputs.
		$test_cases = array(
			array(
				'input'    => '5',
				'expected' => 5,
			),
			array(
				'input'    => '10.5',
				'expected' => 10,
			),
			array(
				'input'    => '-3',
				'expected' => -3,
			),
			array(
				'input'    => 'abc',
				'expected' => 0,
			),
			array(
				'input'    => '1; DROP TABLE users;',
				'expected' => 1,
			),
		);

		foreach ( $test_cases as $case ) {
			$ajax->page = 1; // Reset.
			$ajax->init_request( array( 'page' => $case['input'] ) );

			$this->assertSame(
				$case['expected'],
				$ajax->page,
				sprintf( 'Page "%s" should be sanitized to %d', $case['input'], $case['expected'] )
			);
		}
	}

	/**
	 * Test that init_request sanitizes per_page parameter.
	 */
	public function test_ajax_query_sanitizes_per_page_parameter() {
		$ajax = new ACF_Ajax_Query();

		$test_cases = array(
			array(
				'input'    => '25',
				'expected' => 25,
			),
			array(
				'input'    => '100.9',
				'expected' => 100,
			),
			array(
				'input'    => 'malicious',
				'expected' => 0,
			),
		);

		foreach ( $test_cases as $case ) {
			$ajax->per_page = 20; // Reset to default.
			$ajax->init_request( array( 'per_page' => $case['input'] ) );

			$this->assertSame(
				$case['expected'],
				$ajax->per_page,
				sprintf( 'per_page "%s" should be sanitized to %d', $case['input'], $case['expected'] )
			);
		}
	}

	/**
	 * Test that init_request also accepts 's' parameter as search (alternative).
	 */
	public function test_ajax_query_accepts_s_as_search_parameter() {
		$ajax = new ACF_Ajax_Query();

		$ajax->init_request( array( 's' => 'query term' ) );

		$this->assertSame( 'query term', $ajax->search );
		$this->assertTrue( $ajax->is_search );
	}

	/**
	 * Test that empty search values don't trigger is_search flag.
	 */
	public function test_ajax_query_empty_search_not_flagged() {
		$ajax = new ACF_Ajax_Query();

		$ajax->init_request( array( 'search' => '' ) );

		$this->assertSame( '', $ajax->search );
		$this->assertFalse( $ajax->is_search, 'Empty search should not set is_search flag' );
	}

	/**
	 * Test that get_args returns empty array when query parameter is provided.
	 */
	public function test_ajax_query_get_args_returns_empty_array() {
		$ajax = new ACF_Ajax_Query();

		$custom_query = array(
			'post_type'   => 'page',
			'post_status' => 'publish',
		);

		$result = $ajax->get_args( array( 'query' => $custom_query ) );

		$this->assertSame( array(), $result, 'get_args should return an empty array' );
	}

	/**
	 * Test that default pagination values are reasonable.
	 */
	public function test_ajax_query_default_pagination_values() {
		$ajax = new ACF_Ajax_Query();

		$this->assertSame( 1, $ajax->page, 'Default page should be 1' );
		$this->assertSame( 20, $ajax->per_page, 'Default per_page should be 20' );
		$this->assertFalse( $ajax->more, 'Default more should be false' );
	}

	// =========================================================================
	// ACF_Ajax_Query_Users Tests (Requires Authentication)
	// =========================================================================

	/**
	 * Test that ACF_Ajax_Query_Users has correct action name.
	 */
	public function test_ajax_query_users_action_name() {
		$ajax = new ACF_Ajax_Query_Users();

		$this->assertSame( 'acf/ajax/query_users', $ajax->action );
	}

	/**
	 * Test that ACF_Ajax_Query_Users inherits from ACF_Ajax_Query.
	 */
	public function test_ajax_query_users_inherits_from_query() {
		$ajax = new ACF_Ajax_Query_Users();

		$this->assertInstanceOf( 'ACF_Ajax_Query', $ajax );
	}

	/**
	 * Test verify_request rejects when nonce is empty.
	 */
	public function test_ajax_query_users_rejects_empty_nonce() {
		$ajax = new ACF_Ajax_Query_Users();

		$result = $ajax->verify_request(
			array(
				'nonce'     => '',
				'field_key' => 'field_123',
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_args', $result->get_error_code() );
	}

	/**
	 * Test verify_request rejects when field_key is empty.
	 */
	public function test_ajax_query_users_rejects_empty_field_key() {
		$ajax = new ACF_Ajax_Query_Users();

		$result = $ajax->verify_request(
			array(
				'nonce'     => 'some_nonce',
				'field_key' => '',
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_args', $result->get_error_code() );
	}

	/**
	 * Test verify_request rejects invalid nonce.
	 */
	public function test_ajax_query_users_rejects_invalid_nonce() {
		wp_set_current_user( $this->admin_user_id );

		$ajax = new ACF_Ajax_Query_Users();

		$result = $ajax->verify_request(
			array(
				'nonce'     => 'invalid_nonce',
				'field_key' => 'field_123',
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_nonce', $result->get_error_code() );
	}

	/**
	 * Test that conditional_logic requires admin capability.
	 */
	public function test_ajax_query_users_conditional_logic_requires_admin() {
		// Set non-admin user.
		wp_set_current_user( $this->subscriber_user_id );

		$ajax = new ACF_Ajax_Query_Users();

		$result = $ajax->verify_request(
			array(
				'nonce'             => 'any_nonce',
				'field_key'         => 'field_123',
				'conditional_logic' => true,
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that conditional_logic passes for admin users.
	 */
	public function test_ajax_query_users_conditional_logic_passes_for_admin() {
		wp_set_current_user( $this->admin_user_id );

		// When conditional_logic is true, it uses standard ACF admin nonce via $_REQUEST.
		$nonce             = wp_create_nonce( 'acf_nonce' );
		$_REQUEST['nonce'] = $nonce;

		$ajax = new ACF_Ajax_Query_Users();

		$result = $ajax->verify_request(
			array(
				'nonce'             => $nonce,
				'field_key'         => 'field_123',
				'conditional_logic' => true,
			)
		);

		$this->assertTrue( $result, 'Admin with valid nonce should pass conditional_logic verification' );

		unset( $_REQUEST['nonce'] );
	}

	/**
	 * Test that get_args correctly returns number and paged.
	 */
	public function test_ajax_query_users_get_args_returns_number_and_paged() {
		$ajax           = new ACF_Ajax_Query_Users();
		$ajax->page     = 3;
		$ajax->per_page = 10;

		$result = $ajax->get_args( array() );

		$this->assertSame(
			array(
				'number' => 10,
				'paged'  => 3,
			),
			$result
		);
	}

	/**
	 * Test that prepare_args correctly calculates offset.
	 */
	public function test_ajax_query_users_prepare_args_calculates_offset() {
		$ajax           = new ACF_Ajax_Query_Users();
		$ajax->page     = 3;
		$ajax->per_page = 10;

		$args   = array( 'number' => 10 );
		$result = $ajax->prepare_args( $args );

		$this->assertSame( 20, $result['offset'], 'Offset should be (per_page * (page - 1))' );
		$this->assertSame( 10, $result['number'] );
		$this->assertTrue( $result['count_total'] );
	}

	/**
	 * Test that prepare_args handles users_per_page alternative.
	 */
	public function test_ajax_query_users_prepare_args_users_per_page() {
		$ajax = new ACF_Ajax_Query_Users();

		$args   = array( 'users_per_page' => 15 );
		$result = $ajax->prepare_args( $args );

		$this->assertSame( 15, $ajax->per_page );
		$this->assertArrayNotHasKey( 'users_per_page', $result, 'users_per_page should be removed from args' );
	}

	// =========================================================================
	// ACF_Ajax_Check_Screen Tests (Capability Check)
	// =========================================================================

	/**
	 * Test that ACF_Ajax_Check_Screen is non-public.
	 */
	public function test_ajax_check_screen_is_non_public() {
		$ajax = new ACF_Ajax_Check_Screen();

		$this->assertFalse( $ajax->public, 'ACF_Ajax_Check_Screen should not be public' );
	}

	/**
	 * Test that ACF_Ajax_Check_Screen has correct action name.
	 */
	public function test_ajax_check_screen_action_name() {
		$ajax = new ACF_Ajax_Check_Screen();

		$this->assertSame( 'acf/ajax/check_screen', $ajax->action );
	}

	/**
	 * Test that get_response rejects users without edit permission.
	 */
	public function test_ajax_check_screen_rejects_users_without_permission() {
		wp_set_current_user( $this->subscriber_user_id );

		$ajax          = new ACF_Ajax_Check_Screen();
		$ajax->request = array(
			'screen'  => 'post',
			'post_id' => 1,
		);

		$result = $ajax->get_response( $ajax->request );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that get_response validates post_id parameter.
	 */
	public function test_ajax_check_screen_validates_post_id() {
		wp_set_current_user( $this->subscriber_user_id );

		$ajax          = new ACF_Ajax_Check_Screen();
		$ajax->request = array(
			'screen'  => 'post',
			'post_id' => 0, // Zero post ID.
		);

		$result = $ajax->get_response( $ajax->request );

		// Zero post_id with subscriber should fail permission check.
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	// =========================================================================
	// ACF_Ajax_Upgrade Tests (Admin Capability Required)
	// =========================================================================

	/**
	 * Test that ACF_Ajax_Upgrade is non-public.
	 */
	public function test_ajax_upgrade_is_non_public() {
		$ajax = new ACF_Ajax_Upgrade();

		$this->assertFalse( $ajax->public, 'ACF_Ajax_Upgrade should not be public' );
	}

	/**
	 * Test that ACF_Ajax_Upgrade has correct action name.
	 */
	public function test_ajax_upgrade_action_name() {
		$ajax = new ACF_Ajax_Upgrade();

		$this->assertSame( 'acf/ajax/upgrade', $ajax->action );
	}

	/**
	 * Test that get_response rejects users without capability.
	 */
	public function test_ajax_upgrade_rejects_users_without_capability() {
		wp_set_current_user( $this->subscriber_user_id );

		$ajax = new ACF_Ajax_Upgrade();

		$result = $ajax->get_response( array() );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'upgrade_error', $result->get_error_code() );
		$this->assertStringContainsString( 'permission', $result->get_error_message() );
	}

	/**
	 * Test that get_response accepts users with correct capability.
	 */
	public function test_ajax_upgrade_accepts_admin_users() {
		wp_set_current_user( $this->admin_user_id );

		$ajax = new ACF_Ajax_Upgrade();

		// Since there's no actual upgrade available, we expect 'No updates available' error.
		$result = $ajax->get_response( array() );

		// This will return a WP_Error because no upgrades are available, but NOT a permission error.
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'upgrade_error', $result->get_error_code() );
		$this->assertStringContainsString( 'No updates available', $result->get_error_message() );
	}

	// =========================================================================
	// ACF_Ajax_User_Setting Tests (Admin Capability Required)
	// =========================================================================

	/**
	 * Test that ACF_Ajax_User_Setting is non-public.
	 */
	public function test_ajax_user_setting_is_non_public() {
		$ajax = new ACF_Ajax_User_Setting();

		$this->assertFalse( $ajax->public, 'ACF_Ajax_User_Setting should not be public' );
	}

	/**
	 * Test that ACF_Ajax_User_Setting has correct action name.
	 */
	public function test_ajax_user_setting_action_name() {
		$ajax = new ACF_Ajax_User_Setting();

		$this->assertSame( 'acf/ajax/user_setting', $ajax->action );
	}

	/**
	 * Test that get_response rejects non-admin users.
	 */
	public function test_ajax_user_setting_rejects_non_admin() {
		wp_set_current_user( $this->subscriber_user_id );

		$ajax          = new ACF_Ajax_User_Setting();
		$ajax->request = array( 'name' => 'test_setting' );

		$result = $ajax->get_response( $ajax->request );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Test that get_response can get settings for admin users.
	 */
	public function test_ajax_user_setting_get_for_admin() {
		wp_set_current_user( $this->admin_user_id );

		$ajax          = new ACF_Ajax_User_Setting();
		$ajax->request = array( 'name' => 'test_setting' );

		// Getting a non-existent setting should return a value (possibly empty).
		$result = $ajax->get_response( $ajax->request );

		// Should not be a WP_Error.
		$this->assertNotInstanceOf( 'WP_Error', $result, 'Admin should be able to get settings' );
	}

	/**
	 * Test that get_response can update settings for admin users.
	 */
	public function test_ajax_user_setting_update_for_admin() {
		wp_set_current_user( $this->admin_user_id );

		$ajax          = new ACF_Ajax_User_Setting();
		$ajax->request = array(
			'name'  => 'test_setting',
			'value' => 'test_value',
		);

		$result = $ajax->get_response( $ajax->request );

		// Should not be a WP_Error.
		$this->assertNotInstanceOf( 'WP_Error', $result, 'Admin should be able to update settings' );
	}

	// =========================================================================
	// ACF_Ajax_Local_JSON_Diff Tests (Admin + Validation)
	// =========================================================================

	/**
	 * Test that ACF_Ajax_Local_JSON_Diff is non-public.
	 */
	public function test_ajax_local_json_diff_is_non_public() {
		$ajax = new ACF_Ajax_Local_JSON_Diff();

		$this->assertFalse( $ajax->public, 'ACF_Ajax_Local_JSON_Diff should not be public' );
	}

	/**
	 * Test that ACF_Ajax_Local_JSON_Diff has correct action name.
	 */
	public function test_ajax_local_json_diff_action_name() {
		$ajax = new ACF_Ajax_Local_JSON_Diff();

		$this->assertSame( 'acf/ajax/local_json_diff', $ajax->action );
	}

	/**
	 * Test that get_response rejects non-admin users.
	 */
	public function test_ajax_local_json_diff_rejects_non_admin() {
		wp_set_current_user( $this->subscriber_user_id );

		$ajax = new ACF_Ajax_Local_JSON_Diff();

		$result = $ajax->get_response( array( 'id' => 1 ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_not_allowed', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Test that get_response rejects missing id parameter.
	 */
	public function test_ajax_local_json_diff_rejects_missing_id() {
		wp_set_current_user( $this->admin_user_id );

		$ajax = new ACF_Ajax_Local_JSON_Diff();

		$result = $ajax->get_response( array() );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_param', $result->get_error_code() );
	}

	/**
	 * Test that get_response rejects zero id parameter.
	 */
	public function test_ajax_local_json_diff_rejects_zero_id() {
		wp_set_current_user( $this->admin_user_id );

		$ajax = new ACF_Ajax_Local_JSON_Diff();

		$result = $ajax->get_response( array( 'id' => 0 ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_param', $result->get_error_code() );
	}

	/**
	 * Test that get_response sanitizes id parameter to integer.
	 */
	public function test_ajax_local_json_diff_sanitizes_id() {
		wp_set_current_user( $this->admin_user_id );

		$ajax = new ACF_Ajax_Local_JSON_Diff();

		// String id gets cast to int.
		$result = $ajax->get_response( array( 'id' => '123abc' ) );

		// Should fail with invalid_post_type or invalid_id (not invalid_param).
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertNotSame( 'acf_invalid_param', $result->get_error_code(), 'ID should be parsed as int 123' );
	}

	/**
	 * Test that get_response rejects invalid post types.
	 */
	public function test_ajax_local_json_diff_rejects_invalid_post_type() {
		wp_set_current_user( $this->admin_user_id );

		// Create a regular post.
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Test Post',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$ajax = new ACF_Ajax_Local_JSON_Diff();

		$result = $ajax->get_response( array( 'id' => $post_id ) );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'acf_invalid_post_type', $result->get_error_code() );

		wp_delete_post( $post_id, true );
	}

	// =========================================================================
	// acf_verify_ajax() Function Tests
	// =========================================================================

	/**
	 * Test that acf_verify_ajax returns false for empty nonce.
	 */
	public function test_acf_verify_ajax_empty_nonce() {
		$result = acf_verify_ajax( '', '' );

		$this->assertFalse( $result, 'Empty nonce should return false' );
	}

	/**
	 * Test that acf_verify_ajax returns false for invalid nonce.
	 */
	public function test_acf_verify_ajax_invalid_nonce() {
		$result = acf_verify_ajax( 'invalid_nonce', 'acf_nonce' );

		$this->assertFalse( $result, 'Invalid nonce should return false' );
	}

	/**
	 * Test that acf_verify_ajax returns true for valid nonce.
	 */
	public function test_acf_verify_ajax_valid_nonce() {
		$nonce = wp_create_nonce( 'acf_nonce' );

		$result = acf_verify_ajax( $nonce, 'acf_nonce' );

		$this->assertTrue( $result, 'Valid nonce should return true' );
	}

	/**
	 * Test that acf_verify_ajax uses custom action.
	 */
	public function test_acf_verify_ajax_custom_action() {
		$custom_action = 'custom_action_name';
		$nonce         = wp_create_nonce( $custom_action );

		$result = acf_verify_ajax( $nonce, $custom_action );

		$this->assertTrue( $result, 'Valid nonce with custom action should return true' );
	}

	/**
	 * Test that acf_verify_ajax reads from $_REQUEST when nonce param is empty.
	 */
	public function test_acf_verify_ajax_reads_from_request() {
		$nonce             = wp_create_nonce( 'acf_nonce' );
		$_REQUEST['nonce'] = $nonce;

		$result = acf_verify_ajax();

		$this->assertTrue( $result, 'Should read nonce from $_REQUEST' );

		unset( $_REQUEST['nonce'] );
	}

	/**
	 * Test that acf_verify_ajax_with_field_action validates field key format.
	 *
	 * @dataProvider data_provider_invalid_field_keys
	 *
	 * @param string $field_key Invalid field key to test.
	 */
	public function test_acf_verify_ajax_rejects_invalid_field_keys( $field_key ) {
		$result = acf_verify_ajax( 'some_nonce', $field_key, true );

		$this->assertFalse( $result, sprintf( 'Field key "%s" should be rejected', $field_key ) );
	}

	/**
	 * Data provider for invalid field keys.
	 *
	 * @return array Test cases.
	 */
	public function data_provider_invalid_field_keys() {
		return array(
			'empty string'       => array( '' ),
			'not field format'   => array( 'invalid_key' ),
			'sql injection'      => array( "field_'; DROP TABLE wp_posts; --" ),
			'group key'          => array( 'group_123' ),
			'numeric only'       => array( '12345' ),
			'special characters' => array( 'field_<script>' ),
		);
	}

	// =========================================================================
	// acf_current_user_can_admin() Function Tests
	// =========================================================================

	/**
	 * Test that acf_current_user_can_admin returns false for non-admin.
	 */
	public function test_current_user_can_admin_false_for_subscriber() {
		wp_set_current_user( $this->subscriber_user_id );

		$result = acf_current_user_can_admin();

		$this->assertFalse( $result, 'Subscriber should not have admin access' );
	}

	/**
	 * Test that acf_current_user_can_admin returns true for admin.
	 */
	public function test_current_user_can_admin_true_for_admin() {
		wp_set_current_user( $this->admin_user_id );

		$result = acf_current_user_can_admin();

		$this->assertTrue( $result, 'Administrator should have admin access' );
	}

	/**
	 * Test that acf_current_user_can_admin respects show_admin setting.
	 */
	public function test_current_user_can_admin_respects_show_admin_setting() {
		wp_set_current_user( $this->admin_user_id );

		// Disable show_admin setting.
		acf_update_setting( 'show_admin', false );

		$result = acf_current_user_can_admin();

		$this->assertFalse( $result, 'Should return false when show_admin is disabled' );

		// Re-enable for other tests.
		acf_update_setting( 'show_admin', true );
	}

	// =========================================================================
	// Input Sanitization Edge Cases
	// =========================================================================

	/**
	 * Test that search input is sanitized via sanitize_text_field.
	 *
	 * Note: sanitize_text_field strips HTML tags but doesn't strip SQL keywords.
	 * SQL injection protection is handled at the query level via $wpdb->prepare().
	 */
	public function test_search_input_sanitization() {
		$ajax = new ACF_Ajax_Query();

		// Test that HTML tags are stripped.
		$ajax->init_request( array( 'search' => '<script>alert("XSS")</script>' ) );
		$this->assertStringNotContainsString( '<script>', $ajax->search, 'Script tags should be stripped' );
		$this->assertStringNotContainsString( '</script>', $ajax->search, 'Script tags should be stripped' );

		// Test that the text content is preserved after stripping tags.
		$ajax->search = ''; // Reset.
		$ajax->init_request( array( 'search' => '<b>bold</b> text' ) );
		$this->assertSame( 'bold text', $ajax->search, 'Tags stripped but content preserved' );

		// Test that newlines are converted to spaces.
		$ajax->search = ''; // Reset.
		$ajax->init_request( array( 'search' => "line1\nline2" ) );
		$this->assertSame( 'line1 line2', $ajax->search, 'Newlines converted to spaces' );

		// Test that extra whitespace is trimmed.
		$ajax->search = ''; // Reset.
		$ajax->init_request( array( 'search' => '   trimmed   ' ) );
		$this->assertSame( 'trimmed', $ajax->search, 'Whitespace should be trimmed' );
	}

	/**
	 * Test that search sanitization handles various HTML injection attempts.
	 *
	 * @dataProvider data_provider_html_injection_attempts
	 *
	 * @param string $malicious_input HTML injection attempt.
	 * @param string $expected        Expected sanitized output.
	 */
	public function test_html_injection_in_search_sanitized( $malicious_input, $expected ) {
		$ajax = new ACF_Ajax_Query();
		$ajax->init_request( array( 'search' => $malicious_input ) );

		$this->assertSame( $expected, $ajax->search );
	}

	/**
	 * Data provider for HTML injection attempts.
	 *
	 * @return array Test cases with input and expected sanitized output.
	 */
	public function data_provider_html_injection_attempts() {
		return array(
			'script tag'  => array( '<script>alert("XSS")</script>', '' ),
			'img onerror' => array( '<img src=x onerror=alert("XSS")>', '' ),
			'svg onload'  => array( '<svg onload=alert("XSS")>', '' ),
			'iframe'      => array( '<iframe src="evil.com"></iframe>', '' ),
			'nested tags' => array( '<div><p>nested</p></div>', 'nested' ),
			'plain text'  => array( 'normal search query', 'normal search query' ),
		);
	}

	// =========================================================================
	// AJAX Action Registration Tests
	// =========================================================================

	/**
	 * Test that non-public handlers don't register nopriv action.
	 */
	public function test_non_public_handler_no_nopriv_action() {
		$action_name = 'acf/ajax/test_private';

		// Create a custom private handler for testing.
		$ajax         = new ACF_Ajax();
		$ajax->action = $action_name;
		$ajax->public = false;
		$ajax->add_actions();

		$this->assertTrue(
			has_action( "wp_ajax_{$action_name}" ) !== false,
			'Private handler should have logged-in action'
		);
		$this->assertFalse(
			has_action( "wp_ajax_nopriv_{$action_name}" ),
			'Private handler should NOT have nopriv action'
		);
	}

	/**
	 * Test that public handlers register both actions.
	 */
	public function test_public_handler_registers_both_actions() {
		$action_name = 'acf/ajax/test_public';

		$ajax         = new ACF_Ajax();
		$ajax->action = $action_name;
		$ajax->public = true;
		$ajax->add_actions();

		$this->assertTrue(
			has_action( "wp_ajax_{$action_name}" ) !== false,
			'Public handler should have logged-in action'
		);
		$this->assertTrue(
			has_action( "wp_ajax_nopriv_{$action_name}" ) !== false,
			'Public handler should have nopriv action'
		);
	}
}
