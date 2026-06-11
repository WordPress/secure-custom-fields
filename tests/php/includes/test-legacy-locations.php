<?php
/**
 * Tests for the ACF_Legacy_Locations backwards compatibility class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- the fixture location type class must live alongside the test.

/**
 * Class SCF_Test_Legacy_Location_Type
 *
 * Minimal custom location type used to test legacy registration.
 */
class SCF_Test_Legacy_Location_Type extends ACF_Location {

	/**
	 * Initializes the location type props.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->name  = 'scf_test_legacy_location';
		$this->label = 'SCF Test Legacy Location';
	}
}

/**
 * Class Test_ACF_Legacy_Locations
 *
 * Tests that the legacy locations facade maps magic property and method
 * access onto the modern location type API.
 *
 * @group legacy
 */
class Test_ACF_Legacy_Locations extends BaseTestCase {

	/**
	 * The legacy locations instance under test.
	 *
	 * @var ACF_Legacy_Locations
	 */
	private $legacy;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Location types are registered during ACF init.
		acf_init();

		$this->legacy = new ACF_Legacy_Locations();
	}

	/**
	 * Test that only the 'locations' property is reported as set.
	 */
	public function test_isset_only_supports_locations_property() {
		$this->assertTrue( isset( $this->legacy->locations ) );
		$this->assertFalse( isset( $this->legacy->some_other_property ) );
	}

	/**
	 * Test that the locations property maps to acf_get_location_types().
	 */
	public function test_locations_property_returns_location_types() {
		$locations = $this->legacy->locations;

		$this->assertIsArray( $locations );
		$this->assertNotEmpty( $locations );
		$this->assertSame( acf_get_location_types(), $locations );
	}

	/**
	 * Test that the locations property contains the core location types.
	 */
	public function test_locations_property_contains_core_types() {
		$locations = $this->legacy->locations;

		foreach ( array( 'post_type', 'post_status', 'page_template', 'user_role', 'taxonomy' ) as $name ) {
			$this->assertArrayHasKey( $name, $locations, "Core location type '{$name}' should be registered" );
		}

		$this->assertInstanceOf( 'ACF_Location_Post_Type', $locations['post_type'] );
	}

	/**
	 * Test that unknown properties return null.
	 */
	public function test_unknown_property_returns_null() {
		$this->assertNull( $this->legacy->unknown_property );
	}

	/**
	 * Test that get_location() maps to acf_get_location_type().
	 */
	public function test_get_location_returns_location_type() {
		$location = $this->legacy->get_location( 'post_type' );

		$this->assertInstanceOf( 'ACF_Location_Post_Type', $location );
		$this->assertSame( acf_get_location_type( 'post_type' ), $location );
	}

	/**
	 * Test that get_location() returns null for unknown location types.
	 */
	public function test_get_location_returns_null_for_unknown_type() {
		$this->assertNull( $this->legacy->get_location( 'nonexistent_location_type' ) );
	}

	/**
	 * Test that get_locations() returns grouped location rule types.
	 */
	public function test_get_locations_returns_grouped_rule_types() {
		$rule_types = $this->legacy->get_locations();

		$this->assertIsArray( $rule_types );
		$this->assertNotEmpty( $rule_types );

		// Rule types are grouped by category label, e.g. "Post" => [ "post_type" => "Post Type" ].
		$this->assertArrayHasKey( 'Post', $rule_types );
		$this->assertArrayHasKey( 'post_type', $rule_types['Post'] );
	}

	/**
	 * Test that register_location() maps to acf_register_location_type().
	 */
	public function test_register_location_registers_location_type() {
		// The location types store persists for the whole PHPUnit process,
		// so registration must be guarded against duplicates.
		if ( ! acf_get_location_type( 'scf_test_legacy_location' ) ) {
			$result = $this->legacy->register_location( 'SCF_Test_Legacy_Location_Type' );

			$this->assertInstanceOf( 'SCF_Test_Legacy_Location_Type', $result );
		}

		$location = acf_get_location_type( 'scf_test_legacy_location' );

		$this->assertInstanceOf( 'SCF_Test_Legacy_Location_Type', $location );
		$this->assertSame( 'SCF Test Legacy Location', $location->label );
	}

	/**
	 * Test that unsupported magic methods return null without error.
	 */
	public function test_unknown_method_returns_null() {
		$this->assertNull( $this->legacy->some_unknown_method( 'arg' ) );
	}
}
