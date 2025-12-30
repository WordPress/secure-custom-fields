<?php
/**
 * Tests for ACF_Location_Nav_Menu_Item class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Nav_Menu_Item.
 */
class Test_ACF_Location_Nav_Menu_Item extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		acf_init();
	}

	/**
	 * Test match returns false without screen args.
	 */
	public function test_match_returns_false_without_screen_args() {
		$location = acf_get_location_type( 'nav_menu_item' );

		$rule = array(
			'param'    => 'nav_menu_item',
			'operator' => '==',
			'value'    => 'all',
		);

		$this->assertFalse( $location->match( $rule, array(), array() ), 'Should return false without nav_menu_item in screen' );
	}
}
