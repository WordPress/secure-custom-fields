<?php
/**
 * Tests for ACF_Location base class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for ACF_Location base class methods.
 */
class Test_ACF_Location extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();
		acf_init();
	}

	/**
	 * Test compare_to_rule with equals operator - matching.
	 */
	public function test_compare_to_rule_equals_matching() {
		$location = acf_get_location_type( 'post_type' );

		$rule = array(
			'param'    => 'post_type',
			'operator' => '==',
			'value'    => 'post',
		);

		$this->assertTrue( $location->compare_to_rule( 'post', $rule ), 'Should match equal values' );
	}

	/**
	 * Test compare_to_rule with equals operator - not matching.
	 */
	public function test_compare_to_rule_equals_not_matching() {
		$location = acf_get_location_type( 'post_type' );

		$rule = array(
			'param'    => 'post_type',
			'operator' => '==',
			'value'    => 'post',
		);

		$this->assertFalse( $location->compare_to_rule( 'page', $rule ), 'Should not match different values' );
	}

	/**
	 * Test compare_to_rule with not-equals operator - matching.
	 */
	public function test_compare_to_rule_not_equals_matching() {
		$location = acf_get_location_type( 'post_type' );

		$rule = array(
			'param'    => 'post_type',
			'operator' => '!=',
			'value'    => 'post',
		);

		$this->assertTrue( $location->compare_to_rule( 'page', $rule ), 'Should match when values are different with != operator' );
	}

	/**
	 * Test compare_to_rule with not-equals operator - not matching.
	 */
	public function test_compare_to_rule_not_equals_not_matching() {
		$location = acf_get_location_type( 'post_type' );

		$rule = array(
			'param'    => 'post_type',
			'operator' => '!=',
			'value'    => 'post',
		);

		$this->assertFalse( $location->compare_to_rule( 'post', $rule ), 'Should not match same value with != operator' );
	}

	/**
	 * Test compare_to_rule with "all" value matches anything.
	 */
	public function test_compare_to_rule_all_value_matches_anything() {
		$location = acf_get_location_type( 'post_type' );

		$rule = array(
			'param'    => 'post_type',
			'operator' => '==',
			'value'    => 'all',
		);

		$this->assertTrue( $location->compare_to_rule( 'post', $rule ), '"all" should match post' );
		$this->assertTrue( $location->compare_to_rule( 'page', $rule ), '"all" should match page' );
		$this->assertTrue( $location->compare_to_rule( 'custom_type', $rule ), '"all" should match custom types' );
	}

	/**
	 * Test compare_to_rule with "all" value and != operator.
	 */
	public function test_compare_to_rule_all_value_with_not_equals() {
		$location = acf_get_location_type( 'post_type' );

		$rule = array(
			'param'    => 'post_type',
			'operator' => '!=',
			'value'    => 'all',
		);

		$this->assertFalse( $location->compare_to_rule( 'post', $rule ), '"all" with != should not match anything' );
	}

	/**
	 * Test get_operators returns default operators.
	 */
	public function test_get_operators_returns_default_operators() {
		$operators = ACF_Location::get_operators( array() );

		$this->assertIsArray( $operators, 'Should return an array' );
		$this->assertArrayHasKey( '==', $operators, 'Should have equals operator' );
		$this->assertArrayHasKey( '!=', $operators, 'Should have not-equals operator' );
		$this->assertCount( 2, $operators, 'Should have exactly 2 operators' );
	}

	/**
	 * Data provider for location type names.
	 *
	 * @return array
	 */
	public function data_provider_location_types() {
		return array(
			'post_type'         => array( 'post_type', 'ACF_Location_Post_Type' ),
			'post'              => array( 'post', 'ACF_Location_Post' ),
			'post_status'       => array( 'post_status', 'ACF_Location_Post_Status' ),
			'post_format'       => array( 'post_format', 'ACF_Location_Post_Format' ),
			'post_template'     => array( 'post_template', 'ACF_Location_Post_Template' ),
			'post_taxonomy'     => array( 'post_taxonomy', 'ACF_Location_Post_Taxonomy' ),
			'post_category'     => array( 'post_category', 'ACF_Location_Post_Category' ),
			'page'              => array( 'page', 'ACF_Location_Page' ),
			'page_parent'       => array( 'page_parent', 'ACF_Location_Page_Parent' ),
			'page_type'         => array( 'page_type', 'ACF_Location_Page_Type' ),
			'page_template'     => array( 'page_template', 'ACF_Location_Page_Template' ),
			'taxonomy'          => array( 'taxonomy', 'ACF_Location_Taxonomy' ),
			'attachment'        => array( 'attachment', 'ACF_Location_Attachment' ),
			'comment'           => array( 'comment', 'ACF_Location_Comment' ),
			'nav_menu'          => array( 'nav_menu', 'ACF_Location_Nav_Menu' ),
			'nav_menu_item'     => array( 'nav_menu_item', 'ACF_Location_Nav_Menu_Item' ),
			'widget'            => array( 'widget', 'ACF_Location_Widget' ),
			'user_role'         => array( 'user_role', 'ACF_Location_User_Role' ),
			'user_form'         => array( 'user_form', 'ACF_Location_User_Form' ),
			'current_user'      => array( 'current_user', 'ACF_Location_Current_User' ),
			'current_user_role' => array( 'current_user_role', 'ACF_Location_Current_User_Role' ),
			'options_page'      => array( 'options_page', 'ACF_Location_Options_Page' ),
			'block'             => array( 'block', 'ACF_Location_Block' ),
		);
	}

	/**
	 * Test all location types are registered and return correct class.
	 *
	 * @dataProvider data_provider_location_types
	 * @param string $type           The location type name.
	 * @param string $expected_class The expected class name.
	 */
	public function test_location_type_registered( $type, $expected_class ) {
		$location = acf_get_location_type( $type );

		$this->assertInstanceOf( $expected_class, $location, "Location type '$type' should be instance of $expected_class" );
	}

	/**
	 * Test acf_get_location_type returns null for unknown type.
	 */
	public function test_get_location_type_returns_null_for_unknown() {
		$location = acf_get_location_type( 'nonexistent_location_type' );

		$this->assertNull( $location, 'Should return null for unknown location type' );
	}

	/**
	 * Test all location types extend ACF_Location.
	 *
	 * @dataProvider data_provider_location_types
	 * @param string $type           The location type name.
	 * @param string $expected_class The expected class name.
	 */
	public function test_location_extends_base_class( $type, $expected_class ) {
		$location = acf_get_location_type( $type );

		$this->assertInstanceOf( 'ACF_Location', $location, "Location type '$type' should extend ACF_Location" );
	}

	/**
	 * Test all location types have required properties set.
	 *
	 * @dataProvider data_provider_location_types
	 * @param string $type           The location type name.
	 * @param string $expected_class The expected class name.
	 */
	public function test_location_has_required_properties( $type, $expected_class ) {
		$location = acf_get_location_type( $type );

		$this->assertNotEmpty( $location->name, "Location '$type' should have a name" );
		$this->assertSame( $type, $location->name, "Location name should match type '$type'" );
		$this->assertNotEmpty( $location->label, "Location '$type' should have a label" );
		$this->assertNotEmpty( $location->category, "Location '$type' should have a category" );
	}
}
