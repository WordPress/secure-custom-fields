<?php
/**
 * Tests for ACF_Location_Post_Taxonomy class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Post_Taxonomy.
 */
class Test_ACF_Location_Post_Taxonomy extends BaseTestCase {

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
		$location = acf_get_location_type( 'post_taxonomy' );

		$rule = array(
			'param'    => 'post_taxonomy',
			'operator' => '==',
			'value'    => 'category:1',
		);

		$this->assertFalse( $location->match( $rule, array(), array() ), 'Should return false without screen args' );
	}
}
