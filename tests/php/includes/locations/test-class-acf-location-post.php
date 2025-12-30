<?php
/**
 * Tests for ACF_Location_Post class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Post.
 */
class Test_ACF_Location_Post extends BaseTestCase {

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
			'matches post_id from screen'       => array(
				array( 'post_id' => 123 ),
				array(
					'operator' => '==',
					'value'    => 123,
				),
				true,
			),
			'does not match different post_id'  => array(
				array( 'post_id' => 456 ),
				array(
					'operator' => '==',
					'value'    => 123,
				),
				false,
			),
			'not equals matches different id'   => array(
				array( 'post_id' => 456 ),
				array(
					'operator' => '!=',
					'value'    => 123,
				),
				true,
			),
			'returns false without screen args' => array(
				array(),
				array(
					'operator' => '==',
					'value'    => 123,
				),
				false,
			),
		);
	}

	/**
	 * Test match method.
	 *
	 * @dataProvider data_provider_match
	 * @param array $screen        Screen args.
	 * @param array $rule_override Rule overrides.
	 * @param bool  $expected      Expected result.
	 */
	public function test_match( $screen, $rule_override, $expected ) {
		$location = acf_get_location_type( 'post' );

		$rule = array_merge(
			array(
				'param'    => 'post',
				'operator' => '==',
				'value'    => 123,
			),
			$rule_override
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}
}
