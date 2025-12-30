<?php
/**
 * Tests for ACF_Location_Nav_Menu class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Nav_Menu.
 */
class Test_ACF_Location_Nav_Menu extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		acf_init();
	}

	/**
	 * Data provider for match tests.
	 *
	 * @return array
	 */
	public function data_provider_match() {
		return array(
			'matches nav_menu ID from screen'   => array(
				array( 'nav_menu' => 5 ),
				5,
				true,
			),
			'all value matches any nav_menu'    => array(
				array( 'nav_menu' => 99 ),
				'all',
				true,
			),
			'returns false without screen args' => array(
				array(),
				5,
				false,
			),
		);
	}

	/**
	 * Test match method.
	 *
	 * @dataProvider data_provider_match
	 * @param array      $screen     Screen args.
	 * @param string|int $rule_value Rule value.
	 * @param bool       $expected   Expected result.
	 */
	public function test_match( $screen, $rule_value, $expected ) {
		$location = acf_get_location_type( 'nav_menu' );

		$rule = array(
			'param'    => 'nav_menu',
			'operator' => '==',
			'value'    => $rule_value,
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}
}
