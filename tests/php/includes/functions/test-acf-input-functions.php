<?php
/**
 * Tests for ACF Input Functions.
 *
 * Tests functions from includes/acf-input-functions.php including:
 * - Attribute filtering and escaping
 * - HTML input generation (hidden, text, file, textarea, checkbox, radio, select)
 * - KSES allowed HTML handling
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_ACF_Input_Functions
 *
 * Tests for ACF input generation and attribute handling functions.
 */
class Test_ACF_Input_Functions extends BaseTestCase {

	// =========================================================================
	// Helper Methods
	// =========================================================================

	/**
	 * Assert that HTML output contains expected input attributes.
	 *
	 * @param string $html     The HTML output to check.
	 * @param string $type     Expected input type.
	 * @param array  $contains Additional strings that should be present.
	 */
	protected function assertInputContains( $html, $type, $contains = array() ) {
		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( "type=\"{$type}\"", $html );
		foreach ( $contains as $string ) {
			$this->assertStringContainsString( $string, $html );
		}
	}

	/**
	 * Capture output from an echo function.
	 *
	 * @param callable $func Function to call.
	 * @param array    $args Arguments to pass.
	 * @return string Captured output.
	 */
	protected function captureOutput( $func, $args = array() ) {
		ob_start();
		call_user_func_array( $func, $args );
		return ob_get_clean();
	}

	// =========================================================================
	// acf_filter_attrs() Tests
	// =========================================================================

	/**
	 * Test that acf_filter_attrs filters out empty values but keeps zero values.
	 */
	public function test_acf_filter_attrs_filtering() {
		$attrs = array(
			'name'     => 'test_field',
			'value'    => '',
			'class'    => 'my-class',
			'id'       => '',
			'zero_str' => '0',
			'zero_int' => 0,
		);

		$result = acf_filter_attrs( $attrs );

		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'class', $result );
		$this->assertArrayHasKey( 'zero_str', $result );
		$this->assertArrayHasKey( 'zero_int', $result );
		$this->assertArrayNotHasKey( 'value', $result );
		$this->assertArrayNotHasKey( 'id', $result );
	}

	/**
	 * Test boolean attribute handling (required, readonly, disabled, multiple).
	 *
	 * @dataProvider booleanAttributesProvider
	 *
	 * @param string $attr  Attribute name.
	 * @param mixed  $value Input value.
	 * @param mixed  $expected Expected result (attribute name if true, absent if false).
	 */
	public function test_acf_filter_attrs_boolean_attributes( $attr, $value, $expected ) {
		$result = acf_filter_attrs( array( $attr => $value ) );

		if ( null === $expected ) {
			$this->assertArrayNotHasKey( $attr, $result );
		} else {
			$this->assertEquals( $expected, $result[ $attr ] );
		}
	}

	/**
	 * Data provider for boolean attributes.
	 */
	public function booleanAttributesProvider() {
		return array(
			'required true'  => array( 'required', true, 'required' ),
			'required false' => array( 'required', false, null ),
			'readonly true'  => array( 'readonly', true, 'readonly' ),
			'readonly false' => array( 'readonly', false, null ),
			'disabled true'  => array( 'disabled', true, 'disabled' ),
			'multiple true'  => array( 'multiple', true, 'multiple' ),
		);
	}

	// =========================================================================
	// acf_esc_attrs() Tests
	// =========================================================================

	/**
	 * Test that acf_esc_attrs generates valid HTML attributes.
	 */
	public function test_acf_esc_attrs_generates_html_attributes() {
		$result = acf_esc_attrs(
			array(
				'name'  => 'field_name',
				'id'    => 'field_id',
				'class' => 'my-class',
			)
		);

		$this->assertStringContainsString( 'name="field_name"', $result );
		$this->assertStringContainsString( 'id="field_id"', $result );
		$this->assertStringContainsString( 'class="my-class"', $result );
	}

	/**
	 * Test value trimming behavior (trims all except 'value' attribute).
	 */
	public function test_acf_esc_attrs_trims_strings_except_value() {
		$result = acf_esc_attrs(
			array(
				'name'  => '  padded  ',
				'value' => '  has spaces  ',
			)
		);

		$this->assertStringContainsString( 'name="padded"', $result );
		$this->assertStringContainsString( 'value="  has spaces  "', $result );
	}

	/**
	 * Test type conversion (booleans to int, arrays/objects to JSON).
	 */
	public function test_acf_esc_attrs_type_conversion() {
		$result = acf_esc_attrs(
			array(
				'data-active'   => true,
				'data-disabled' => false,
				'data-settings' => array( 'key' => 'value' ),
			)
		);

		$this->assertStringContainsString( 'data-active="1"', $result );
		$this->assertStringContainsString( 'data-disabled="0"', $result );
		$this->assertStringContainsString( 'data-settings=', $result );
	}

	/**
	 * Test XSS prevention through HTML entity escaping.
	 */
	public function test_acf_esc_attrs_escapes_html_entities() {
		$result = acf_esc_attrs( array( 'value' => '<script>alert("xss")</script>' ) );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	// =========================================================================
	// acf_esc_html() Tests
	// =========================================================================

	/**
	 * Test acf_esc_html behavior with various inputs.
	 *
	 * @dataProvider escHtmlProvider
	 *
	 * @param mixed  $input    Input value.
	 * @param string $contains String that should be in result (or false for non-scalar).
	 * @param string $excludes String that should NOT be in result.
	 */
	public function test_acf_esc_html( $input, $contains, $excludes = null ) {
		$result = acf_esc_html( $input );

		if ( false === $contains ) {
			$this->assertFalse( $result );
		} else {
			$this->assertStringContainsString( $contains, $result );
			if ( $excludes ) {
				$this->assertStringNotContainsString( $excludes, $result );
			}
		}
	}

	/**
	 * Data provider for acf_esc_html tests.
	 */
	public function escHtmlProvider() {
		return array(
			'plain text'      => array( 'Hello World', 'Hello World' ),
			'allowed tags'    => array( '<strong>Bold</strong>', '<strong>' ),
			'disallowed tags' => array( '<script>xss</script>Safe', 'Safe', '<script>' ),
			'array input'     => array( array( 'test' ), false ),
			'object input'    => array( new stdClass(), false ),
		);
	}

	// =========================================================================
	// _acf_kses_allowed_html() Tests
	// =========================================================================

	/**
	 * Test KSES allowed HTML filter behavior.
	 */
	public function test_acf_kses_allowed_html() {
		global $allowedposttags;

		// ACF context returns allowed post tags.
		$this->assertEquals( $allowedposttags, _acf_kses_allowed_html( array(), 'acf' ) );

		// Other contexts pass through.
		$tags = array( 'div' => array() );
		$this->assertEquals( $tags, _acf_kses_allowed_html( $tags, 'post' ) );
	}

	// =========================================================================
	// Input Generation Tests (Hidden, Text, File, Textarea)
	// =========================================================================

	/**
	 * Test hidden input generation.
	 */
	public function test_acf_get_hidden_input() {
		$result = acf_get_hidden_input(
			array(
				'name'  => 'my_field',
				'value' => 'my_value',
			)
		);

		$this->assertInputContains( $result, 'hidden', array( 'name="my_field"', 'value="my_value"' ) );

		// Empty attrs still produces valid hidden input.
		$this->assertInputContains( acf_get_hidden_input( array() ), 'hidden' );
	}

	/**
	 * Test text input generation.
	 */
	public function test_acf_get_text_input() {
		// Basic text input.
		$result = acf_get_text_input( array( 'name' => 'text_field' ) );
		$this->assertInputContains( $result, 'text', array( 'name="text_field"' ) );

		// Type override.
		$result = acf_get_text_input(
			array(
				'type' => 'email',
				'name' => 'email_field',
			)
		);
		$this->assertInputContains( $result, 'email' );

		// XSS prevention.
		$result = acf_get_text_input( array( 'value' => '<script>alert("xss")</script>' ) );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * Test file input generation with nonce handling.
	 */
	public function test_acf_get_file_input() {
		// Basic file input.
		$result = acf_get_file_input( array( 'name' => 'file_field' ) );
		$this->assertInputContains( $result, 'file', array( 'name="file_field"' ) );

		// With explicit key - includes nonce.
		$result = acf_get_file_input(
			array(
				'name' => 'acf[field_abc123]',
				'key'  => 'field_abc123',
			)
		);
		$this->assertStringContainsString( 'acf[field_abc123_file_nonce]', $result );
		$this->assertEquals( 2, substr_count( $result, '<input' ) );

		// Key extracted from name.
		$result = acf_get_file_input( array( 'name' => 'acf[field_xyz789]' ) );
		$this->assertStringContainsString( 'acf[field_xyz789_file_nonce]', $result );

		// Simple name - no nonce.
		$result = acf_get_file_input( array( 'name' => 'simple_file' ) );
		$this->assertEquals( 1, substr_count( $result, '<input' ) );
	}

	/**
	 * Test textarea input generation.
	 */
	public function test_acf_get_textarea_input() {
		$result = acf_get_textarea_input(
			array(
				'name'  => 'textarea_field',
				'value' => 'Hello World',
			)
		);

		$this->assertStringContainsString( '<textarea', $result );
		$this->assertStringContainsString( '</textarea>', $result );
		$this->assertStringContainsString( 'name="textarea_field"', $result );
		$this->assertStringContainsString( 'Hello World', $result );

		// XSS prevention.
		$result = acf_get_textarea_input( array( 'value' => '<script>alert("xss")</script>' ) );
		$this->assertStringNotContainsString( '<script>alert', $result );

		// Empty value.
		$result = acf_get_textarea_input( array( 'name' => 'test' ) );
		$this->assertStringContainsString( '><', $result );
	}

	// =========================================================================
	// Checkbox/Radio Input Tests
	// =========================================================================

	/**
	 * Test checkbox input generation.
	 */
	public function test_acf_get_checkbox_input() {
		// Basic checkbox.
		$result = acf_get_checkbox_input(
			array(
				'name'  => 'checkbox_field',
				'value' => '1',
				'label' => 'Accept Terms',
			)
		);
		$this->assertStringContainsString( '<label', $result );
		$this->assertInputContains( $result, 'checkbox', array( 'Accept Terms' ) );

		// Checked state adds selected class.
		$result = acf_get_checkbox_input(
			array(
				'name'    => 'checkbox_field',
				'value'   => '1',
				'checked' => true,
				'label'   => 'Test',
			)
		);
		$this->assertStringContainsString( 'class="selected"', $result );

		// XSS prevention in label.
		$result = acf_get_checkbox_input(
			array(
				'name'  => 'test',
				'value' => '1',
				'label' => '<script>alert("xss")</script>',
			)
		);
		$this->assertStringNotContainsString( '<script>alert', $result );
	}

	/**
	 * Test checkbox button group mode with ARIA attributes.
	 *
	 * @dataProvider buttonGroupProvider
	 *
	 * @param bool   $checked         Whether the option is checked.
	 * @param string $expected_aria   Expected aria-checked value.
	 * @param string $expected_tabindex Expected tabindex value.
	 */
	public function test_acf_get_checkbox_input_button_group( $checked, $expected_aria, $expected_tabindex ) {
		$attrs = array(
			'name'         => 'btn_group',
			'value'        => '1',
			'label'        => 'Option',
			'button_group' => true,
		);

		if ( $checked ) {
			$attrs['checked'] = true;
		}

		$result = acf_get_checkbox_input( $attrs );

		$this->assertStringContainsString( 'role="radio"', $result );
		$this->assertStringContainsString( "aria-checked=\"{$expected_aria}\"", $result );
		$this->assertStringContainsString( "tabindex=\"{$expected_tabindex}\"", $result );
	}

	/**
	 * Data provider for button group tests.
	 */
	public function buttonGroupProvider() {
		return array(
			'checked'   => array( true, 'true', '0' ),
			'unchecked' => array( false, 'false', '-1' ),
		);
	}

	/**
	 * Test radio input generation (wrapper for checkbox with radio type).
	 */
	public function test_acf_get_radio_input() {
		$result = acf_get_radio_input(
			array(
				'name'    => 'radio_field',
				'value'   => 'option1',
				'label'   => 'Option 1',
				'checked' => true,
			)
		);

		$this->assertInputContains( $result, 'radio', array( 'Option 1', 'class="selected"' ) );
	}

	// =========================================================================
	// Select Input Tests
	// =========================================================================

	/**
	 * Test select input generation.
	 */
	public function test_acf_get_select_input() {
		$result = acf_get_select_input(
			array(
				'name'    => 'select_field',
				'choices' => array(
					'opt1' => 'Option 1',
					'opt2' => 'Option 2',
				),
				'value'   => 'opt2',
			)
		);

		$this->assertStringContainsString( '<select', $result );
		$this->assertStringContainsString( '</select>', $result );
		$this->assertStringContainsString( 'name="select_field"', $result );
		$this->assertStringContainsString( 'Option 1', $result );
		$this->assertStringContainsString( 'Option 2', $result );
		$this->assertStringContainsString( 'selected="selected"', $result );

		// Empty choices.
		$result = acf_get_select_input(
			array(
				'name'    => 'empty',
				'choices' => array(),
			)
		);
		$this->assertStringNotContainsString( '<option', $result );
	}

	/**
	 * Test select option walking with optgroups.
	 */
	public function test_acf_walk_select_input() {
		// Simple options.
		$result = acf_walk_select_input(
			array(
				'val1' => 'Label 1',
				'val2' => 'Label 2',
			),
			array( 'val2' )
		);

		$this->assertStringContainsString( 'value="val1"', $result );
		$this->assertStringContainsString( 'Label 1', $result );
		$this->assertStringContainsString( 'selected="selected"', $result );
		$this->assertStringContainsString( 'data-i="0"', $result );

		// Optgroups.
		$result = acf_walk_select_input(
			array(
				'Group 1' => array(
					'val1' => 'Option 1',
					'val2' => 'Option 2',
				),
				'Group 2' => array(
					'val3' => 'Option 3',
				),
			),
			array( 'val1' )
		);

		$this->assertStringContainsString( '<optgroup label="Group 1">', $result );
		$this->assertStringContainsString( '<optgroup label="Group 2">', $result );
		$this->assertStringContainsString( '</optgroup>', $result );
		$this->assertStringContainsString( 'selected="selected"', $result );

		// Empty choices.
		$this->assertEquals( '', acf_walk_select_input( array(), array() ) );
	}

	// =========================================================================
	// Echo Function Tests (consolidated)
	// =========================================================================

	/**
	 * Test that echo functions output correctly.
	 *
	 * @dataProvider echoFunctionsProvider
	 *
	 * @param string $func     Function name.
	 * @param array  $attrs    Attributes to pass.
	 * @param string $contains String that should be in output.
	 */
	public function test_echo_functions( $func, $attrs, $contains ) {
		$output = $this->captureOutput( $func, array( $attrs ) );
		$this->assertStringContainsString( $contains, $output );
	}

	/**
	 * Data provider for echo function tests.
	 */
	public function echoFunctionsProvider() {
		return array(
			'acf_hidden_input'   => array( 'acf_hidden_input', array( 'name' => 'test' ), 'type="hidden"' ),
			'acf_text_input'     => array( 'acf_text_input', array( 'name' => 'test' ), 'type="text"' ),
			'acf_file_input'     => array( 'acf_file_input', array( 'name' => 'test' ), 'type="file"' ),
			'acf_textarea_input' => array( 'acf_textarea_input', array( 'name' => 'test' ), '<textarea' ),
			'acf_checkbox_input' => array(
				'acf_checkbox_input',
				array(
					'name'  => 'test',
					'value' => '1',
					'label' => 'L',
				),
				'type="checkbox"',
			),
			'acf_radio_input'    => array(
				'acf_radio_input',
				array(
					'name'  => 'test',
					'value' => '1',
					'label' => 'L',
				),
				'type="radio"',
			),
			'acf_select_input'   => array(
				'acf_select_input',
				array(
					'name'    => 'test',
					'choices' => array( 'a' => 'A' ),
				),
				'<select',
			),
		);
	}

	// =========================================================================
	// Alias Function Tests (consolidated)
	// =========================================================================

	/**
	 * Test that alias functions work correctly.
	 *
	 * @dataProvider aliasFunctionsProvider
	 *
	 * @param string $alias    Alias function name.
	 * @param string $original Original function name.
	 * @param bool   $is_echo  Whether the alias echoes output.
	 */
	public function test_alias_functions( $alias, $original, $is_echo = false ) {
		$attrs = array(
			'name'     => 'test',
			'value'    => 'hello',
			'required' => true,
		);

		if ( $is_echo ) {
			$output = $this->captureOutput( $alias, array( $attrs ) );
			$this->assertEquals( call_user_func( $original, $attrs ), $output );
		} else {
			$this->assertEquals(
				call_user_func( $original, $attrs ),
				call_user_func( $alias, $attrs )
			);
		}
	}

	/**
	 * Data provider for alias function tests.
	 */
	public function aliasFunctionsProvider() {
		return array(
			'acf_clean_atts' => array( 'acf_clean_atts', 'acf_filter_attrs', false ),
			'acf_esc_atts'   => array( 'acf_esc_atts', 'acf_esc_attrs', false ),
			'acf_esc_attr'   => array( 'acf_esc_attr', 'acf_esc_attrs', false ),
			'acf_esc_attr_e' => array( 'acf_esc_attr_e', 'acf_esc_attrs', true ),
			'acf_esc_atts_e' => array( 'acf_esc_atts_e', 'acf_esc_attrs', true ),
		);
	}
}
