<?php
/**
 * Tests for ACF_Location_Taxonomy class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Taxonomy.
 */
class Test_ACF_Location_Taxonomy extends BaseTestCase {

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
			'matches taxonomy from screen'      => array(
				array( 'taxonomy' => 'category' ),
				array(
					'operator' => '==',
					'value'    => 'category',
				),
				true,
			),
			'does not match different taxonomy' => array(
				array( 'taxonomy' => 'post_tag' ),
				array(
					'operator' => '==',
					'value'    => 'category',
				),
				false,
			),
			'all value matches any taxonomy'    => array(
				array( 'taxonomy' => 'custom_taxonomy' ),
				array(
					'operator' => '==',
					'value'    => 'all',
				),
				true,
			),
			'returns false without screen args' => array(
				array(),
				array(
					'operator' => '==',
					'value'    => 'category',
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
		$location = acf_get_location_type( 'taxonomy' );

		$rule = array_merge(
			array( 'param' => 'taxonomy' ),
			$rule_override
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test get_object_subtype with == operator.
	 */
	public function test_get_object_subtype_equals() {
		$location = acf_get_location_type( 'taxonomy' );

		$rule = array(
			'operator' => '==',
			'value'    => 'category',
		);

		$result = $location->get_object_subtype( $rule );

		$this->assertSame( 'category', $result, 'Should return the taxonomy value' );
	}

	/**
	 * Test get_object_subtype with != operator.
	 */
	public function test_get_object_subtype_not_equals() {
		$location = acf_get_location_type( 'taxonomy' );

		$rule = array(
			'operator' => '!=',
			'value'    => 'category',
		);

		$result = $location->get_object_subtype( $rule );

		$this->assertSame( '', $result, 'Should return empty string for != operator' );
	}
}
