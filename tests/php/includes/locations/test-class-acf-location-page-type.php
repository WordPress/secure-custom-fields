<?php
/**
 * Tests for ACF_Location_Page_Type class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Page_Type.
 */
class Test_ACF_Location_Page_Type extends BaseTestCase {

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
		$location = acf_get_location_type( 'page_type' );

		$rule = array(
			'param'    => 'page_type',
			'operator' => '==',
			'value'    => 'front_page',
		);

		$this->assertFalse( $location->match( $rule, array(), array() ), 'Should return false without post_id in screen' );
	}

	/**
	 * Test get_values returns expected types.
	 */
	public function test_get_values() {
		$location = acf_get_location_type( 'page_type' );

		$values = $location->get_values( array() );

		$this->assertIsArray( $values, 'Should return an array' );
		$this->assertArrayHasKey( 'front_page', $values, 'Should have front_page option' );
		$this->assertArrayHasKey( 'posts_page', $values, 'Should have posts_page option' );
		$this->assertArrayHasKey( 'top_level', $values, 'Should have top_level option' );
		$this->assertArrayHasKey( 'parent', $values, 'Should have parent option' );
		$this->assertArrayHasKey( 'child', $values, 'Should have child option' );
	}
}
