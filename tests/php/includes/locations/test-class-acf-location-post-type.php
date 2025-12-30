<?php
/**
 * Tests for ACF_Location_Post_Type class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_Post_Type.
 */
class Test_ACF_Location_Post_Type extends BaseTestCase {

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
			'matches post type from screen'      => array(
				array( 'post_type' => 'post' ),
				array(
					'operator' => '==',
					'value'    => 'post',
				),
				true,
			),
			'does not match different post type' => array(
				array( 'post_type' => 'page' ),
				array(
					'operator' => '==',
					'value'    => 'post',
				),
				false,
			),
			'not equals matches different type'  => array(
				array( 'post_type' => 'page' ),
				array(
					'operator' => '!=',
					'value'    => 'post',
				),
				true,
			),
			'all value matches any type'         => array(
				array( 'post_type' => 'custom_post_type' ),
				array(
					'operator' => '==',
					'value'    => 'all',
				),
				true,
			),
			'returns false without screen args'  => array(
				array(),
				array(
					'operator' => '==',
					'value'    => 'post',
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
		$location = acf_get_location_type( 'post_type' );

		$rule = array_merge(
			array(
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => 'post',
			),
			$rule_override
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test get_values returns post types.
	 */
	public function test_get_values() {
		$location = acf_get_location_type( 'post_type' );

		$values = $location->get_values( array() );

		$this->assertIsArray( $values, 'Should return an array' );
		$this->assertNotEmpty( $values, 'Should return available post types' );
	}

	/**
	 * Test get_object_subtype with == operator.
	 */
	public function test_get_object_subtype_equals() {
		$location = acf_get_location_type( 'post_type' );

		$rule = array(
			'operator' => '==',
			'value'    => 'post',
		);

		$result = $location->get_object_subtype( $rule );

		$this->assertSame( 'post', $result, 'Should return the rule value for == operator' );
	}

	/**
	 * Test get_object_subtype with != operator returns empty.
	 */
	public function test_get_object_subtype_not_equals() {
		$location = acf_get_location_type( 'post_type' );

		$rule = array(
			'operator' => '!=',
			'value'    => 'post',
		);

		$result = $location->get_object_subtype( $rule );

		$this->assertSame( '', $result, 'Should return empty string for != operator' );
	}
}
