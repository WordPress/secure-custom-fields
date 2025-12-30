<?php
/**
 * Tests for ACF_Location_Post_Template class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Post_Template.
 */
class Test_ACF_Location_Post_Template extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		acf_init();
	}

	/**
	 * Test match returns false without screen args.
	 */
	public function test_match_returns_false_without_screen_args() {
		$location = acf_get_location_type( 'post_template' );

		$rule = array(
			'param'    => 'post_template',
			'operator' => '==',
			'value'    => 'default',
		);

		$this->assertFalse( $location->match( $rule, array(), array() ), 'Should return false without screen args' );
	}
}
