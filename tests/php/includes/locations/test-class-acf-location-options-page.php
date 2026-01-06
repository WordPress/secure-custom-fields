<?php
/**
 * Tests for ACF_Location_Options_Page class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Options_Page.
 */
class Test_ACF_Location_Options_Page extends BaseTestCase {

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
			'matches options_page from screen'  => array(
				array( 'options_page' => 'site-settings' ),
				'site-settings',
				true,
			),
			'does not match different page'     => array(
				array( 'options_page' => 'theme-options' ),
				'site-settings',
				false,
			),
			'returns false without screen args' => array(
				array(),
				'site-settings',
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
		$location = acf_get_location_type( 'options_page' );

		$rule = array(
			'param'    => 'options_page',
			'operator' => '==',
			'value'    => $rule_value,
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}
}
