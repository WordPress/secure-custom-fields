<?php
/**
 * Tests for ACF_Location_Current_User class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Current_User.
 */
class Test_ACF_Location_Current_User extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		acf_init();
	}

	/**
	 * Test get_values returns expected options.
	 */
	public function test_get_values() {
		$location = acf_get_location_type( 'current_user' );

		$values = $location->get_values( array() );

		$this->assertIsArray( $values, 'Should return an array' );
		$this->assertArrayHasKey( 'logged_in', $values, 'Should have "logged_in" option' );
		$this->assertArrayHasKey( 'viewing_front', $values, 'Should have "viewing_front" option' );
		$this->assertArrayHasKey( 'viewing_back', $values, 'Should have "viewing_back" option' );
	}
}
