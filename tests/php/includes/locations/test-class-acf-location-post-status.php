<?php
/**
 * Tests for ACF_Location_Post_Status class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Post_Status.
 */
class Test_ACF_Location_Post_Status extends BaseTestCase {

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
			'matches draft status'              => array(
				array( 'post_status' => 'draft' ),
				'draft',
				true,
			),
			'matches publish status'            => array(
				array( 'post_status' => 'publish' ),
				'publish',
				true,
			),
			'auto-draft treated as draft'       => array(
				array( 'post_status' => 'auto-draft' ),
				'draft',
				true,
			),
			'does not match different status'   => array(
				array( 'post_status' => 'publish' ),
				'draft',
				false,
			),
			'returns false without screen args' => array(
				array(),
				'publish',
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
		$location = acf_get_location_type( 'post_status' );

		$rule = array(
			'param'    => 'post_status',
			'operator' => '==',
			'value'    => $rule_value,
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}
}
