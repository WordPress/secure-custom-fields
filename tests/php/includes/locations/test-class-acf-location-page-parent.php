<?php
/**
 * Tests for ACF_Location_Page_Parent class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Page_Parent.
 */
class Test_ACF_Location_Page_Parent extends BaseTestCase {

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
			'matches page_parent from screen'   => array(
				array( 'page_parent' => 10 ),
				10,
				true,
			),
			'returns false without screen args' => array(
				array(),
				10,
				false,
			),
		);
	}

	/**
	 * Test match method.
	 *
	 * @dataProvider data_provider_match
	 * @param array $screen     Screen args.
	 * @param int   $rule_value Rule value.
	 * @param bool  $expected   Expected result.
	 */
	public function test_match( $screen, $rule_value, $expected ) {
		$location = acf_get_location_type( 'page_parent' );

		$rule = array(
			'param'    => 'page_parent',
			'operator' => '==',
			'value'    => $rule_value,
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}
}
