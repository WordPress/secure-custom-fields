<?php
/**
 * Tests for ACF_Location_User_Form class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location_User_Form.
 */
class Test_ACF_Location_User_Form extends BaseTestCase {

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
			'matches user_form from screen'     => array(
				array( 'user_form' => 'edit' ),
				'edit',
				true,
			),
			'all value matches any user_form'   => array(
				array( 'user_form' => 'add' ),
				'all',
				true,
			),
			'edit value matches add form'       => array(
				array( 'user_form' => 'add' ),
				'edit',
				true,
			),
			'REST API always matches'           => array(
				array( 'rest' => true ),
				'edit',
				true,
			),
			'returns false without screen args' => array(
				array(),
				'edit',
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
		$location = acf_get_location_type( 'user_form' );

		$rule = array(
			'param'    => 'user_form',
			'operator' => '==',
			'value'    => $rule_value,
		);

		$result = $location->match( $rule, $screen, array() );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test get_values returns expected forms.
	 */
	public function test_get_values() {
		$location = acf_get_location_type( 'user_form' );

		$values = $location->get_values( array() );

		$this->assertIsArray( $values, 'Should return an array' );
		$this->assertArrayHasKey( 'all', $values, 'Should have "all" option' );
		$this->assertArrayHasKey( 'add', $values, 'Should have "add" option' );
		$this->assertArrayHasKey( 'edit', $values, 'Should have "edit" option' );
		$this->assertArrayHasKey( 'register', $values, 'Should have "register" option' );
	}
}
