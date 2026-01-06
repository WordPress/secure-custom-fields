<?php
/**
 * Tests for the Link field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_link.
 */
class Test_ACF_Field_Link extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'link';
	}

	/**
	 * Get a base link field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'           => 'field_link_test',
				'name'          => 'test_link',
				'type'          => 'link',
				'label'         => 'Test Link',
				'required'      => 0,
				'return_format' => 'array',
			),
			$overrides
		);
	}

	/**
	 * Get sample link data.
	 *
	 * @return array
	 */
	protected function get_sample_link() {
		return array(
			'title'  => 'Example Website',
			'url'    => 'https://example.com',
			'target' => '_blank',
		);
	}

	/**
	 * Test format_value with array return format.
	 */
	public function test_format_value_array_format() {
		$field = $this->get_field( array( 'return_format' => 'array' ) );
		$link  = $this->get_sample_link();

		$result = $this->field_instance->format_value( $link, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'target', $result );
	}

	/**
	 * Test format_value with URL return format.
	 */
	public function test_format_value_url_format() {
		$field = $this->get_field( array( 'return_format' => 'url' ) );
		$link  = $this->get_sample_link();

		$result = $this->field_instance->format_value( $link, $this->post_id, $field );

		$this->assertEquals( 'https://example.com', $result );
	}

	/**
	 * Test format_value returns empty for empty input.
	 */
	public function test_format_value_empty() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test format_value returns empty for empty array.
	 */
	public function test_format_value_empty_array() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( array(), $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test format_value_for_rest returns link data.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();
		$link  = $this->get_sample_link();

		$result = $this->field_instance->format_value_for_rest( $link, $this->post_id, $field );

		$this->assertNotEmpty( $result );
	}

	/**
	 * Test update_value stores link.
	 */
	public function test_update_value() {
		$field = $this->get_field();
		$link  = $this->get_sample_link();

		$result = $this->field_instance->update_value( $link, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertEquals( 'https://example.com', $result['url'] );
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
	 * Test validate_value with valid link.
	 */
	public function test_validate_value_valid() {
		$field = $this->get_field( array( 'required' => 1 ) );
		$link  = $this->get_sample_link();

		$valid = $this->field_instance->validate_value( true, $link, $field, 'acf[field_link_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with empty when required.
	 */
	public function test_validate_value_empty_required() {
		$field = $this->get_field( array( 'required' => 1 ) );

		$valid = $this->field_instance->validate_value( true, '', $field, 'acf[field_link_test]' );

		$this->assertFalse( $valid );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 */
	public function test_get_rest_schema() {
		$field = $this->get_field();

		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
	}

	/**
	 * Test link with no target.
	 */
	public function test_link_no_target() {
		$field = $this->get_field();
		$link  = array(
			'title'  => 'Example',
			'url'    => 'https://example.com',
			'target' => '',
		);

		$result = $this->field_instance->format_value( $link, $this->post_id, $field );

		$this->assertEquals( '', $result['target'] );
	}

	/**
	 * Test link with _self target.
	 */
	public function test_link_self_target() {
		$field = $this->get_field();
		$link  = array(
			'title'  => 'Example',
			'url'    => 'https://example.com',
			'target' => '_self',
		);

		$result = $this->field_instance->format_value( $link, $this->post_id, $field );

		$this->assertEquals( '_self', $result['target'] );
	}

	/**
	 * Test link with internal URL.
	 */
	public function test_internal_url() {
		$field = $this->get_field();
		$link  = array(
			'title'  => 'About Us',
			'url'    => '/about/',
			'target' => '',
		);

		$result = $this->field_instance->update_value( $link, $this->post_id, $field );

		$this->assertEquals( '/about/', $result['url'] );
	}

	/**
	 * Test get_link with array value (ACF 5.6.0+).
	 *
	 * Tests the array merge branch:
	 * `if ( is_array( $value ) ) { $link = array_merge( $link, $value ); }`
	 */
	public function test_get_link_array_format() {
		$input = array(
			'title'  => 'Custom Title',
			'url'    => 'https://custom.com',
			'target' => '_blank',
		);

		$result = $this->field_instance->get_link( $input );

		$this->assertEquals( 'Custom Title', $result['title'] );
		$this->assertEquals( 'https://custom.com', $result['url'] );
		$this->assertEquals( '_blank', $result['target'] );
	}

	/**
	 * Test get_link with string value (legacy ACF < 5.6.0).
	 *
	 * Tests the string branch:
	 * `elseif ( is_string( $value ) ) { $link['url'] = $value; }`
	 */
	public function test_get_link_string_format() {
		$result = $this->field_instance->get_link( 'https://example.com/page' );

		$this->assertEquals( '', $result['title'] );
		$this->assertEquals( 'https://example.com/page', $result['url'] );
		$this->assertEquals( '', $result['target'] );
	}

	/**
	 * Test get_link with empty string returns defaults.
	 */
	public function test_get_link_empty_string() {
		$result = $this->field_instance->get_link( '' );

		$this->assertEquals( '', $result['title'] );
		$this->assertEquals( '', $result['url'] );
		$this->assertEquals( '', $result['target'] );
	}

	/**
	 * Test get_link with partial array fills defaults.
	 */
	public function test_get_link_partial_array() {
		$result = $this->field_instance->get_link( array( 'url' => 'https://partial.com' ) );

		$this->assertEquals( '', $result['title'] );
		$this->assertEquals( 'https://partial.com', $result['url'] );
		$this->assertEquals( '', $result['target'] );
	}

	/**
	 * Test validate_value returns early when not required.
	 *
	 * Tests the early return:
	 * `if ( ! $field['required'] ) { return $valid; }`
	 */
	public function test_validate_value_not_required_returns_valid() {
		$field = $this->get_field( array( 'required' => 0 ) );

		// Even with empty value, should return passed $valid when not required.
		$valid = $this->field_instance->validate_value( true, '', $field, 'acf[field_link_test]' );

		$this->assertTrue( $valid );
	}

	/**
	 * Test validate_value with empty URL when required fails.
	 *
	 * Tests the URL check:
	 * `if ( empty( $value ) || empty( $value['url'] ) ) { return false; }`
	 */
	public function test_validate_value_empty_url_required_fails() {
		$field = $this->get_field( array( 'required' => 1 ) );
		$link  = array(
			'title'  => 'Has title',
			'url'    => '',  // Empty URL.
			'target' => '',
		);

		$valid = $this->field_instance->validate_value( true, $link, $field, 'acf[field_link_test]' );

		$this->assertFalse( $valid );
	}

	/**
	 * Test update_value converts empty URL array to empty string.
	 *
	 * Tests the empty check:
	 * `if ( empty( $value ) || empty( $value['url'] ) ) { $value = ''; }`
	 */
	public function test_update_value_empty_url_converts_to_empty_string() {
		$field = $this->get_field();
		$link  = array(
			'title'  => 'Has title',
			'url'    => '',
			'target' => '_blank',
		);

		$result = $this->field_instance->update_value( $link, $this->post_id, $field );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test update_value with null converts to empty string.
	 */
	public function test_update_value_null_converts_to_empty_string() {
		$field = $this->get_field();

		$result = $this->field_instance->update_value( null, $this->post_id, $field );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test update_value preserves valid link data.
	 */
	public function test_update_value_preserves_valid_link() {
		$field = $this->get_field();
		$link  = array(
			'title'  => 'Title',
			'url'    => 'https://valid.com',
			'target' => '_blank',
		);

		$result = $this->field_instance->update_value( $link, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertEquals( 'https://valid.com', $result['url'] );
	}

	/**
	 * Test format_value with URL return_format.
	 *
	 * Tests the return_format branch:
	 * `if ( $field['return_format'] == 'url' ) { return $link['url']; }`
	 */
	public function test_format_value_url_return_format() {
		$field = $this->get_field( array( 'return_format' => 'url' ) );
		$link  = array(
			'title'  => 'Title',
			'url'    => 'https://url-only.com',
			'target' => '',
		);

		$result = $this->field_instance->format_value( $link, $this->post_id, $field );

		$this->assertEquals( 'https://url-only.com', $result );
	}

	/**
	 * Test format_value with array return_format returns full link.
	 */
	public function test_format_value_array_return_format() {
		$field = $this->get_field( array( 'return_format' => 'array' ) );
		$link  = array(
			'title'  => 'Full Title',
			'url'    => 'https://array.com',
			'target' => '_self',
		);

		$result = $this->field_instance->format_value( $link, $this->post_id, $field );

		$this->assertIsArray( $result );
		$this->assertEquals( 'Full Title', $result['title'] );
		$this->assertEquals( 'https://array.com', $result['url'] );
		$this->assertEquals( '_self', $result['target'] );
	}
}
