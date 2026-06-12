<?php
/**
 * Tests for ACF_Ajax_User_Setting persistence.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_Ajax_User_Setting_Persistence
 *
 * Tests that the user_setting AJAX handler persists, returns and deletes
 * per-user settings stored in user meta.
 *
 * @group ajax
 */
class Test_Ajax_User_Setting_Persistence extends BaseTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Second admin user ID.
	 *
	 * @var int
	 */
	private $second_admin_user_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_user_id = wp_insert_user(
			array(
				'user_login' => 'us_admin_user',
				'user_pass'  => 'password',
				'user_email' => 'us_admin@example.com',
				'role'       => 'administrator',
			)
		);

		$this->second_admin_user_id = wp_insert_user(
			array(
				'user_login' => 'us_admin_user_two',
				'user_pass'  => 'password',
				'user_email' => 'us_admin_two@example.com',
				'role'       => 'administrator',
			)
		);

		$_REQUEST = array();
		$_POST    = array();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		wp_set_current_user( 0 );

		$_REQUEST = array();
		$_POST    = array();

		parent::tear_down();
	}

	/**
	 * Runs a user_setting request and returns the response.
	 *
	 * @param array $request The request args.
	 * @return mixed
	 */
	private function run_user_setting( array $request ) {
		$ajax          = new ACF_Ajax_User_Setting();
		$ajax->request = $request;

		return $ajax->get_response( $ajax->request );
	}

	/**
	 * Test that an updated setting can be read back through the handler.
	 */
	public function test_update_then_get_round_trip() {
		wp_set_current_user( $this->admin_user_id );

		$update_response = $this->run_user_setting(
			array(
				'name'  => 'test_round_trip',
				'value' => 'stored_value',
			)
		);

		$this->assertNotInstanceOf( 'WP_Error', $update_response );
		$this->assertNotFalse( $update_response, 'Updating a setting should succeed' );

		$get_response = $this->run_user_setting( array( 'name' => 'test_round_trip' ) );

		$this->assertSame( 'stored_value', $get_response );
	}

	/**
	 * Test that settings are stored in the acf_user_settings user meta.
	 */
	public function test_setting_persisted_in_user_meta() {
		wp_set_current_user( $this->admin_user_id );

		$this->run_user_setting(
			array(
				'name'  => 'test_meta_storage',
				'value' => 'meta_value',
			)
		);

		$settings = get_user_meta( $this->admin_user_id, 'acf_user_settings', true );

		$this->assertIsArray( $settings );
		$this->assertSame( 'meta_value', $settings['test_meta_storage'] );
	}

	/**
	 * Test that an empty value deletes the setting.
	 */
	public function test_empty_value_deletes_setting() {
		wp_set_current_user( $this->admin_user_id );

		$this->run_user_setting(
			array(
				'name'  => 'test_deletable',
				'value' => 'initial',
			)
		);

		// Send an empty value to delete the setting.
		$this->run_user_setting(
			array(
				'name'  => 'test_deletable',
				'value' => '',
			)
		);

		$get_response = $this->run_user_setting( array( 'name' => 'test_deletable' ) );

		$this->assertFalse( $get_response, 'A deleted setting should fall back to the default false' );
	}

	/**
	 * Test that numeric zero values are stored rather than deleted.
	 */
	public function test_zero_value_is_stored() {
		wp_set_current_user( $this->admin_user_id );

		$this->run_user_setting(
			array(
				'name'  => 'test_zero',
				'value' => '0',
			)
		);

		$get_response = $this->run_user_setting( array( 'name' => 'test_zero' ) );

		$this->assertSame( '0', $get_response, 'Zero values should be saved, not treated as empty' );
	}

	/**
	 * Test that settings are stored per-user.
	 */
	public function test_settings_are_per_user() {
		wp_set_current_user( $this->admin_user_id );

		$this->run_user_setting(
			array(
				'name'  => 'test_per_user',
				'value' => 'first_admin_value',
			)
		);

		// Switch user and read the same setting name.
		wp_set_current_user( $this->second_admin_user_id );

		$get_response = $this->run_user_setting( array( 'name' => 'test_per_user' ) );

		$this->assertFalse( $get_response, 'Another user should not see the first user setting' );
	}

	/**
	 * Test that a missing setting returns false.
	 */
	public function test_missing_setting_returns_false() {
		wp_set_current_user( $this->admin_user_id );

		$get_response = $this->run_user_setting( array( 'name' => 'test_never_set' ) );

		$this->assertFalse( $get_response );
	}
}
