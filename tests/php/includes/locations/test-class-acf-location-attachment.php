<?php
/**
 * Tests for ACF_Location_Attachment class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Attachment.
 */
class Test_ACF_Location_Attachment extends BaseTestCase {

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
		$location = acf_get_location_type( 'attachment' );

		$rule = array(
			'param'    => 'attachment',
			'operator' => '==',
			'value'    => 'image',
		);

		$this->assertFalse( $location->match( $rule, array(), array() ), 'Should return false without attachment in screen' );
	}
}
