<?php
/**
 * Tests for the oEmbed field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_oembed.
 */
class Test_ACF_Field_Oembed extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'oembed';
	}

	/**
	 * The oEmbed field instance.
	 *
	 * @var acf_field_oembed
	 */
	protected $field_instance;

	/**
	 * Get a base oEmbed field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'      => 'field_oembed_test',
				'name'     => 'test_oembed',
				'type'     => 'oembed',
				'label'    => 'Test oEmbed',
				'required' => 0,
				'width'    => '',
				'height'   => '',
			),
			$overrides
		);
	}

	/**
	 * Data provider for valid oEmbed URLs.
	 *
	 * @return array
	 */
	public function valid_url_provider() {
		return array(
			'youtube url'    => array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ),
			'vimeo url'      => array( 'https://vimeo.com/123456789' ),
			'twitter url'    => array( 'https://twitter.com/user/status/123456789' ),
			'spotify track'  => array( 'https://open.spotify.com/track/abc123' ),
			'soundcloud url' => array( 'https://soundcloud.com/artist/track' ),
		);
	}

	/**
	 * Data provider for invalid URLs.
	 *
	 * @return array
	 */
	public function invalid_url_provider() {
		return array(
			'empty string'    => array( '' ),
			'plain text'      => array( 'not a url' ),
			'invalid scheme'  => array( 'ftp://example.com/video' ),
			'local file path' => array( '/path/to/file.mp4' ),
		);
	}

	/**
	 * Test format_value returns URL for REST context.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();
		$url   = 'https://www.youtube.com/watch?v=test123';

		$result = $this->field_instance->format_value_for_rest( $url, $this->post_id, $field );

		// REST format returns the URL.
		$this->assertEquals( $url, $result );
	}

	/**
	 * Test format_value returns empty for empty URL.
	 */
	public function test_format_value_empty() {
		$field  = $this->get_field();
		$result = $this->field_instance->format_value( '', $this->post_id, $field );

		$this->assertEmpty( $result );
	}

	/**
	 * Test get_rest_schema returns valid schema.
	 */
	public function test_get_rest_schema() {
		$field  = $this->get_field();
		$schema = $this->field_instance->get_rest_schema( $field );

		$this->assertIsArray( $schema );
		$this->assertContains( 'string', $schema['type'] );
	}

	/**
	 * Test field handles width/height settings.
	 */
	public function test_field_with_dimensions() {
		$field = $this->get_field(
			array(
				'width'  => '640',
				'height' => '480',
			)
		);

		$this->assertEquals( '640', $field['width'] );
		$this->assertEquals( '480', $field['height'] );
	}

	/**
	 * Test validate_rest_value with valid URL.
	 */
	public function test_validate_rest_value_valid() {
		$field = $this->get_field();
		$url   = 'https://www.youtube.com/watch?v=test';

		$valid = $this->field_instance->validate_rest_value( true, $url, $field, 'test_oembed', array(), '' );

		$this->assertTrue( $valid );
	}
}
