<?php
/**
 * Tests for ACF_Location_Block class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Block.
 */
class Test_ACF_Location_Block extends BaseTestCase {

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
			'matches block from screen'         => array(
				array( 'block' => 'acf/testimonial' ),
				'acf/testimonial',
				true,
			),
			'does not match different block'    => array(
				array( 'block' => 'acf/hero' ),
				'acf/testimonial',
				false,
			),
			'all value matches any block'       => array(
				array( 'block' => 'acf/custom' ),
				'all',
				true,
			),
			'returns false without screen args' => array(
				array(),
				'acf/testimonial',
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
		$location = acf_get_location_type( 'block' );

		$rule = array(
			'param'    => 'block',
			'operator' => '==',
			'value'    => $rule_value,
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}
}
