<?php
/**
 * Tests for ACF_Location_Post_Category class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Post_Category.
 *
 * Note: ACF_Location_Post_Category extends ACF_Location_Post_Taxonomy
 * and filters to category terms only. Basic matching behavior is inherited.
 */
class Test_ACF_Location_Post_Category extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		acf_init();
	}

	/**
	 * Test location is registered.
	 */
	public function test_location_is_registered() {
		$location = acf_get_location_type( 'post_category' );

		$this->assertInstanceOf( 'ACF_Location_Post_Category', $location );
		$this->assertSame( 'post_category', $location->name );
	}

	/**
	 * Test get_values returns categories.
	 */
	public function test_get_values_returns_array() {
		$location = acf_get_location_type( 'post_category' );

		$values = $location->get_values( array() );

		$this->assertIsArray( $values, 'Should return an array' );
	}
}
