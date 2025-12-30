<?php
/**
 * Tests for ACF_Location_Page class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Page.
 */
class Test_ACF_Location_Page extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		acf_init();
	}

	/**
	 * Test page location delegates to post location.
	 */
	public function test_delegates_to_post() {
		$location = acf_get_location_type( 'page' );

		$rule = array(
			'param'    => 'page',
			'operator' => '==',
			'value'    => 42,
		);

		$screen = array( 'post_id' => 42 );

		$this->assertTrue( $location->match( $rule, $screen, array() ), 'Page location should delegate to post and match' );
	}

	/**
	 * Test page location returns false without screen args.
	 */
	public function test_returns_false_without_screen_args() {
		$location = acf_get_location_type( 'page' );

		$rule = array(
			'param'    => 'page',
			'operator' => '==',
			'value'    => 42,
		);

		$this->assertFalse( $location->match( $rule, array(), array() ), 'Should return false without screen args' );
	}
}
