<?php
/**
 * Tests for ACF_Location_Post_Format class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Post_Format.
 */
class Test_ACF_Location_Post_Format extends BaseTestCase {

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
			'matches post_format from screen'   => array(
				array( 'post_format' => 'aside' ),
				'aside',
				true,
			),
			'does not match different format'   => array(
				array( 'post_format' => 'gallery' ),
				'aside',
				false,
			),
			'returns false without screen args' => array(
				array(),
				'standard',
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
		$location = acf_get_location_type( 'post_format' );

		$rule = array(
			'param'    => 'post_format',
			'operator' => '==',
			'value'    => $rule_value,
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}
}
