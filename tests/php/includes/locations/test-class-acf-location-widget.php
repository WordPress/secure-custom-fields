<?php
/**
 * Tests for ACF_Location_Widget class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Widget.
 */
class Test_ACF_Location_Widget extends BaseTestCase {

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
			'matches widget from screen'        => array(
				array( 'widget' => 'text' ),
				'text',
				true,
			),
			'all value matches any widget'      => array(
				array( 'widget' => 'custom_widget' ),
				'all',
				true,
			),
			'returns false without screen args' => array(
				array(),
				'text',
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
		$location = acf_get_location_type( 'widget' );

		$rule = array(
			'param'    => 'widget',
			'operator' => '==',
			'value'    => $rule_value,
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}
}
