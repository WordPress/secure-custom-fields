<?php
/**
 * Tests for ACF_Location_Comment class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Comment.
 */
class Test_ACF_Location_Comment extends BaseTestCase {

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
			'matches comment from screen'       => array(
				array( 'comment' => 'post' ),
				'post',
				true,
			),
			'all value matches any comment'     => array(
				array( 'comment' => 'page' ),
				'all',
				true,
			),
			'returns false without screen args' => array(
				array(),
				'post',
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
		$location = acf_get_location_type( 'comment' );

		$rule = array(
			'param'    => 'comment',
			'operator' => '==',
			'value'    => $rule_value,
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}
}
