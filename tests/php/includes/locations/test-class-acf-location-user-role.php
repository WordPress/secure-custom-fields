<?php
/**
 * Tests for ACF_Location_User_Role class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_User_Role.
 */
class Test_ACF_Location_User_Role extends BaseTestCase {

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
			'matches user_role from screen'     => array(
				array( 'user_role' => 'administrator' ),
				'administrator',
				true,
			),
			'all value matches any user_role'   => array(
				array( 'user_role' => 'subscriber' ),
				'all',
				true,
			),
			'returns false without screen args' => array(
				array(),
				'administrator',
				false,
			),
		);
	}

	/**
	 * Test match method.
	 *
	 * @dataProvider data_provider_match
	 * @param array  $screen     Screen args.
	 * @param string $rule_value Rule value.
	 * @param bool   $expected   Expected result.
	 */
	public function test_match( $screen, $rule_value, $expected ) {
		$location = acf_get_location_type( 'user_role' );

		$rule = array(
			'param'    => 'user_role',
			'operator' => '==',
			'value'    => $rule_value,
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}
}
