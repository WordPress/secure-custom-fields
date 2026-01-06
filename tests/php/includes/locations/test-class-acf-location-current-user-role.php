<?php
/**
 * Tests for ACF_Location_Current_User_Role class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Current_User_Role.
 */
class Test_ACF_Location_Current_User_Role extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		acf_init();
	}

	/**
	 * Test location is registered.
	 */
	public function test_location_is_registered() {
		$location = acf_get_location_type( 'current_user_role' );

		$this->assertInstanceOf( 'ACF_Location_Current_User_Role', $location );
		$this->assertSame( 'current_user_role', $location->name );
	}

	/**
	 * Test get_values returns roles.
	 */
	public function test_get_values_returns_array() {
		$location = acf_get_location_type( 'current_user_role' );

		$values = $location->get_values( array() );

		$this->assertIsArray( $values, 'Should return an array' );
	}
}
