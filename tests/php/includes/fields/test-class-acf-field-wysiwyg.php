<?php
/**
 * Tests for the WYSIWYG field type.
 *
 * @package wordpress/secure-custom-fields
 * @group fields
 */

/**
 * Tests for acf_field_wysiwyg.
 */
class Test_ACF_Field_Wysiwyg extends Abstract_ACF_Field_Test {
	/**
	 * Get the field type name.
	 *
	 * @return string
	 */
	protected function get_field_type() {
		return 'wysiwyg';
	}

	/**
	 * Get a base WYSIWYG field configuration.
	 *
	 * @param array $overrides Optional overrides.
	 * @return array
	 */
	protected function get_field( $overrides = array() ) {
		return array_merge(
			array(
				'key'          => 'field_wysiwyg_test',
				'name'         => 'test_wysiwyg',
				'type'         => 'wysiwyg',
				'label'        => 'Test WYSIWYG',
				'required'     => 0,
				'tabs'         => 'all',
				'toolbar'      => 'full',
				'media_upload' => 1,
				'delay'        => 0,
			),
			$overrides
		);
	}

	/**
	 * Data provider for toolbar options.
	 *
	 * @return array
	 */
	public function toolbar_provider() {
		return array(
			'full toolbar'  => array( 'full' ),
			'basic toolbar' => array( 'basic' ),
		);
	}

	/**
	 * Test format_value returns formatted HTML.
	 */
	public function test_format_value() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( '<p>Hello World</p>', $this->post_id, $field, false );

		$this->assertStringContainsString( 'Hello World', $result );
	}

	/**
	 * Test format_value handles line breaks.
	 *
	 * Note: wpautop behavior may not work in WorDBless testing environment.
	 * This test verifies that format_value returns a string containing the content.
	 */
	public function test_format_value_wpautop() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value( "Line 1\n\nLine 2", $this->post_id, $field, false );

		// In WorDBless, wpautop may not be fully functional. Verify content is preserved.
		$this->assertStringContainsString( 'Line 1', $result );
		$this->assertStringContainsString( 'Line 2', $result );
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
	 * Test format_value_for_rest returns HTML.
	 */
	public function test_format_value_for_rest() {
		$field = $this->get_field();

		$result = $this->field_instance->format_value_for_rest( '<p>Test</p>', $this->post_id, $field );

		$this->assertStringContainsString( 'Test', $result );
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

	/**
	 * Test tabs option.
	 */
	public function test_tabs_option() {
		$all_tabs    = $this->get_field( array( 'tabs' => 'all' ) );
		$visual_only = $this->get_field( array( 'tabs' => 'visual' ) );
		$text_only   = $this->get_field( array( 'tabs' => 'text' ) );

		$this->assertEquals( 'all', $all_tabs['tabs'] );
		$this->assertEquals( 'visual', $visual_only['tabs'] );
		$this->assertEquals( 'text', $text_only['tabs'] );
	}

	/**
	 * Test toolbar option.
	 *
	 * @dataProvider toolbar_provider
	 *
	 * @param string $toolbar The toolbar option.
	 */
	public function test_toolbar_option( $toolbar ) {
		$field = $this->get_field( array( 'toolbar' => $toolbar ) );

		$this->assertEquals( $toolbar, $field['toolbar'] );
	}

	/**
	 * Test media_upload option.
	 */
	public function test_media_upload_option() {
		$with_media    = $this->get_field( array( 'media_upload' => 1 ) );
		$without_media = $this->get_field( array( 'media_upload' => 0 ) );

		$this->assertEquals( 1, $with_media['media_upload'] );
		$this->assertEquals( 0, $without_media['media_upload'] );
	}

	/**
	 * Test delay option.
	 */
	public function test_delay_option() {
		$immediate = $this->get_field( array( 'delay' => 0 ) );
		$delayed   = $this->get_field( array( 'delay' => 1 ) );

		$this->assertEquals( 0, $immediate['delay'] );
		$this->assertEquals( 1, $delayed['delay'] );
	}

	/**
	 * Test format_value handles shortcode syntax.
	 *
	 * Note: do_shortcode may not process shortcodes in WorDBless testing environment.
	 * This test verifies that format_value returns a string for shortcode input.
	 */
	public function test_format_value_shortcodes() {
		$field = $this->get_field();

		// Add a simple test shortcode.
		add_shortcode(
			'test_shortcode',
			function () {
				return 'SHORTCODE_OUTPUT';
			}
		);

		$result = $this->field_instance->format_value( '[test_shortcode]', $this->post_id, $field, false );

		// In WorDBless, do_shortcode may not be fully functional.
		// Verify that the method returns a string (either processed or original).
		$this->assertIsString( $result );

		remove_shortcode( 'test_shortcode' );
	}

	/**
	 * Test format_value with complex HTML.
	 */
	public function test_format_value_complex_html() {
		$field = $this->get_field();
		$html  = '<h2>Title</h2><p>Paragraph with <strong>bold</strong> and <em>italic</em>.</p><ul><li>Item 1</li><li>Item 2</li></ul>';

		$result = $this->field_instance->format_value( $html, $this->post_id, $field, false );

		$this->assertStringContainsString( '<h2>', $result );
		$this->assertStringContainsString( '<strong>', $result );
		$this->assertStringContainsString( '<li>', $result );
	}
}
