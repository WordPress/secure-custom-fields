<?php
/**
 * Tests for the URL field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_url.
 */
class Test_ACF_Field_Url extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'url';
	}

	/**
	 * Get a base URL field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'         => 'field_url_test',
				'name'        => 'test_url',
				'type'        => 'url',
				'label'       => 'Test URL',
				'required'    => 0,
				'placeholder' => '',
			),
			$overrides
		);
	}

	/**
	 * Data provider for valid URLs.
	 *
	 * @return array
	 */
	public function valid_url_provider() {
		return array(
			'https url'      => array( 'https://example.com' ),
			'http url'       => array( 'http://example.com' ),
			'url with path'  => array( 'https://example.com/page' ),
			'url with query' => array( 'https://example.com?foo=bar' ),
			'url with port'  => array( 'https://example.com:8080' ),
			'subdomain url'  => array( 'https://sub.example.com' ),
		);
	}

	/**
	 * Data provider for invalid URLs.
	 *
	 * @return array
	 */
	public function invalid_url_provider() {
		return array(
			'no scheme'      => array( 'example.com' ),
			'invalid scheme' => array( 'ftp://example.com' ),
			'spaces'         => array( 'https://example .com' ),
			'javascript'     => array( 'javascript:alert(1)' ),
		);
	}

	/**
	 * Test format_value returns URL.
	 *
	 * @dataProvider valid_url_provider
	 *
	 * @param string $url The URL to test.
	 */
	public function test_format_value( $url ) {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( $url, $this->post_id, $field, false );

		$this->assertEquals( $url, $result );
	}

	/**
	 * Test format_value returns empty for empty input.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( '', $this->post_id, $field, false );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test format_value_for_rest returns URL.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( 'https://example.com', $this->post_id, $field );

		$this->assertEquals( 'https://example.com', $result );
	}

	/**
	 * Test validate_value with valid URL.
	 *
	 * @dataProvider valid_url_provider
	 *
	 * @param string $url The URL to test.
	 */
	public function test_validate_value_valid( $url ) {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, $url, $field, 'acf[field_url_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with empty when required.
	 *
	 * Note: The base validate_value method returns the $valid parameter passed to it.
	 * Empty value checking for required fields is handled separately by ACF.
	 */
	public function test_validate_value_empty_required() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, '', $field, 'acf[field_url_test]' );

		// The base validate_value returns the $valid parameter (true).
		// Required field empty checking is handled by ACF core, not the field type.
		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value does not crash on a non-scalar value.
	 *
	 * A crafted form submission can deliver an array (e.g. acf[field_key][])
	 * as the value. The field should treat it as invalid rather than raising
	 * a TypeError from strpos().
	 */
	public function test_validate_value_non_scalar() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, array( 'a' => 'b' ), $field, 'acf[field_url_test]' );

		$this->assertEquals( __( 'Value must be a valid URL', 'secure-custom-fields' ), $valid );
	}

	/**
	 * Test format_value does not crash on a non-scalar value when escaping.
	 *
	 * Calling esc_url() on an array would raise a TypeError. The field should
	 * return an empty string, matching how it treats an empty value.
	 */
	public function test_format_value_non_scalar_escaped() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( array( 'a' => 'b' ), $this->post_id, $field, true );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'string', $schema['type'] );
	}
}
