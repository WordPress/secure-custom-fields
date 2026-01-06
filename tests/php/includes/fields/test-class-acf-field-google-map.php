<?php
/**
 * Tests for the Google Map field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_google_map.
 */
class Test_ACF_Field_Google_Map extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'google_map';
	}

	/**
	 * Google Map field instance.
	 *
	 * @var acf_field_google_map
	 */
	protected $field_instance;

	/**
	 * Get a base google map field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'        => 'field_google_map_test',
				'name'       => 'test_google_map',
				'type'       => 'google_map',
				'label'      => 'Test Google Map',
				'required'   => 0,
				'center_lat' => '',
				'center_lng' => '',
				'zoom'       => '',
				'height'     => '',
			),
			$overrides
		);
	}

	/**
	 * Get sample location data.
	 *
	 * @return array
	 */
	protected function get_sample_location() {
		return array(
			'address'       => '123 Main Street, New York, NY',
			'lat'           => 40.7128,
			'lng'           => -74.0060,
			'zoom'          => 14,
			'place_id'      => 'ChIJOwg_06VPwokRYv534QaPC8g',
			'street_name'   => 'Main Street',
			'city'          => 'New York',
			'state'         => 'New York',
			'country'       => 'United States',
			'country_short' => 'US',
		);
	}

	/**
	 * Test format_value_for_rest returns location data.
	 */
	public function test_format_value_for_rest() {
		$field    = $this->get_field();
		$location = $this->get_sample_location();

		$result = $this->field_instance->format_value_for_rest( $location, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'lat', $result );
		$this->assertArrayHasKey( 'lng', $result );
	}

	/**
	 * Test update_value stores location.
	 */
	public function test_update_value() {
		$field    = $this->get_field();
		$location = $this->get_sample_location();

		$result = $this->field_instance->update_value( $location, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertEquals( 40.7128, $result['lat'] );
	}

	/**
	 * Test update_value with empty returns empty.
	 */
	public function test_update_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( '', $this->post_id, $field );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 *
	 * Note: Google Map schema type may be an array containing multiple types
	 * (e.g., ['object', 'null']) to allow null values.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		// Schema type may be 'object' or an array containing 'object'.
		if ( is_array( $schema['type'] ) ) {
			$this->assertContains( 'object', $schema['type'] );
		} else {
			$this->assertEquals( 'object', $schema['type'] );
		}
	}

	/**
	 * Test load_value returns stored value.
	 */
	public function test_load_value() {
		$field    = $this->get_field();
		$location = $this->get_sample_location();

		$result = $this->field_instance->load_value( $location, $this->post_id, $field );

		$this->assertIsArray( $result );
	}

	/**
	 * Test center_lat and center_lng options.
	 */
	public function test_center_options() {
		$field = $this->get_field(
			array(
				'center_lat' => '51.5074',
				'center_lng' => '-0.1278',
			)
		);

		$this->assertEquals( '51.5074', $field['center_lat'] );
		$this->assertEquals( '-0.1278', $field['center_lng'] );
	}

	/**
	 * Test zoom option.
	 */
	public function test_zoom_option() {
		$field = $this->get_field( array( 'zoom' => 15 ) );

		$this->assertEquals( 15, $field['zoom'] );
	}

	/**
	 * Test height option.
	 */
	public function test_height_option() {
		$field = $this->get_field( array( 'height' => 400 ) );

		$this->assertEquals( 400, $field['height'] );
	}
}
