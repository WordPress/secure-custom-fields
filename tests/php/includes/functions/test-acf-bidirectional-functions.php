<?php
/**
 * Tests for bidirectional field functions in includes/acf-bidirectional-functions.php
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for acf-bidirectional-functions.php
 *
 * Tests cover:
 * - acf_get_valid_bidirectional_target_types() - Valid target types per object type
 * - acf_build_bidirectional_target_current_choices() - Choice building for select2
 * - acf_get_bidirectional_field_settings_instruction_text() - Instruction text generation
 * - acf_update_bidirectional_values() - Bidirectional value updates (when testable)
 */
class Test_ACF_Bidirectional_Functions extends BaseTestCase {

	/**
	 * Original bidirection setting.
	 *
	 * @var bool
	 */
	private $original_bidirection_setting;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_bidirection_setting = acf_get_setting( 'enable_bidirection' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tearDown(): void {
		parent::tearDown();
		acf_update_setting( 'enable_bidirection', $this->original_bidirection_setting );
		acf_set_data( 'acf_doing_bidirectional_update', false );
		remove_all_filters( 'acf/bidirectional/supported_field_types_for_post' );
		remove_all_filters( 'acf/bidirectional/supported_target_field_types' );
	}

	/**
	 * Test acf_get_valid_bidirectional_target_types for term object type.
	 */
	public function test_get_valid_bidirectional_target_types_term() {
		$result = acf_get_valid_bidirectional_target_types( 'term' );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertContains( 'taxonomy', $result, 'Term should support taxonomy field type' );
	}

	/**
	 * Test acf_get_valid_bidirectional_target_types for user object type.
	 */
	public function test_get_valid_bidirectional_target_types_user() {
		$result = acf_get_valid_bidirectional_target_types( 'user' );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertContains( 'user', $result, 'User should support user field type' );
	}

	/**
	 * Test acf_get_valid_bidirectional_target_types for post object type.
	 */
	public function test_get_valid_bidirectional_target_types_post() {
		$result = acf_get_valid_bidirectional_target_types( 'post' );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertContains( 'relationship', $result, 'Post should support relationship field type' );
		$this->assertContains( 'post_object', $result, 'Post should support post_object field type' );
	}

	/**
	 * Test acf_get_valid_bidirectional_target_types for unknown object type.
	 */
	public function test_get_valid_bidirectional_target_types_unknown() {
		$result = acf_get_valid_bidirectional_target_types( 'unknown' );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertEmpty( $result, 'Unknown type should return empty array' );
	}

	/**
	 * Test acf_get_valid_bidirectional_target_types applies filter.
	 */
	public function test_get_valid_bidirectional_target_types_applies_filter() {
		add_filter(
			'acf/bidirectional/supported_field_types_for_post',
			function ( $types, $object_type ) {
				if ( 'post' === $object_type ) {
					$types[] = 'custom_field';
				}
				return $types;
			},
			10,
			2
		);

		$result = acf_get_valid_bidirectional_target_types( 'post' );

		$this->assertContains( 'custom_field', $result, 'Filter should add custom field type' );
	}

	/**
	 * Data provider for valid bidirectional target types.
	 *
	 * @return array
	 */
	public function data_provider_bidirectional_target_types() {
		return array(
			'term returns taxonomy'                     => array( 'term', array( 'taxonomy' ) ),
			'user returns user'                         => array( 'user', array( 'user' ) ),
			'post returns relationship and post_object' => array( 'post', array( 'relationship', 'post_object' ) ),
			'comment returns empty'                     => array( 'comment', array() ),
			'option returns empty'                      => array( 'option', array() ),
		);
	}

	/**
	 * Test acf_get_valid_bidirectional_target_types with data provider.
	 *
	 * @dataProvider data_provider_bidirectional_target_types
	 * @param string $object_type    The object type.
	 * @param array  $expected_types The expected field types.
	 */
	public function test_get_valid_bidirectional_target_types_data_provider( $object_type, $expected_types ) {
		$result = acf_get_valid_bidirectional_target_types( $object_type );

		$this->assertSame( $expected_types, $result, "Object type '$object_type' should return expected types" );
	}

	/**
	 * Test acf_build_bidirectional_target_current_choices with empty array.
	 */
	public function test_build_bidirectional_target_current_choices_empty() {
		$result = acf_build_bidirectional_target_current_choices( array() );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertEmpty( $result, 'Empty input should return empty array' );
	}

	/**
	 * Test acf_build_bidirectional_target_current_choices with null.
	 */
	public function test_build_bidirectional_target_current_choices_null() {
		$result = acf_build_bidirectional_target_current_choices( null );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertEmpty( $result, 'Null input should return empty array' );
	}

	/**
	 * Test acf_build_bidirectional_target_current_choices skips empty choices.
	 */
	public function test_build_bidirectional_target_current_choices_skips_empty() {
		$result = acf_build_bidirectional_target_current_choices( array( '', null, false ) );

		$this->assertEmpty( $result, 'Empty/null/false choices should be skipped' );
	}

	/**
	 * Test acf_build_bidirectional_target_current_choices skips non-string choices.
	 */
	public function test_build_bidirectional_target_current_choices_skips_non_string() {
		$result = acf_build_bidirectional_target_current_choices( array( 123, array( 'test' ), new stdClass() ) );

		$this->assertEmpty( $result, 'Non-string choices should be skipped' );
	}

	/**
	 * Test acf_build_bidirectional_target_current_choices uses key as fallback label.
	 */
	public function test_build_bidirectional_target_current_choices_key_fallback() {
		// When the field object is not found, the key itself is used as the label.
		$choices = array( 'field_nonexistent_123' );
		$result  = acf_build_bidirectional_target_current_choices( $choices );

		$this->assertArrayHasKey( 'field_nonexistent_123', $result, 'Key should be present' );
		$this->assertSame( 'field_nonexistent_123', $result['field_nonexistent_123'], 'Key should be used as label when field not found' );
	}

	/**
	 * Test acf_get_bidirectional_field_settings_instruction_text returns string.
	 */
	public function test_get_bidirectional_field_settings_instruction_text_returns_string() {
		$result = acf_get_bidirectional_field_settings_instruction_text();

		$this->assertIsString( $result, 'Result should be a string' );
		$this->assertNotEmpty( $result, 'Result should not be empty' );
	}

	/**
	 * Test acf_get_bidirectional_field_settings_instruction_text contains expected content.
	 */
	public function test_get_bidirectional_field_settings_instruction_text_content() {
		$result = acf_get_bidirectional_field_settings_instruction_text();

		$this->assertStringContainsString( 'bidirectional', $result, 'Should mention bidirectional' );
		$this->assertStringContainsString( '<p', $result, 'Should contain HTML paragraph tag' );
		$this->assertStringContainsString( 'acf-feature-notice', $result, 'Should have feature notice class' );
	}

	/**
	 * Test acf_update_bidirectional_values returns early when already updating (recursion guard).
	 *
	 * When the recursion flag is already set, the function should return immediately
	 * without modifying the flag or processing any updates.
	 */
	public function test_update_bidirectional_values_prevents_recursion() {
		// Pre-set the recursion guard flag.
		acf_set_data( 'acf_doing_bidirectional_update', true );

		$field = array(
			'type'                 => 'relationship',
			'name'                 => 'test_field',
			'bidirectional'        => true,
			'bidirectional_target' => array( 'field_abc123' ),
		);

		// Call the function - it should return early without changing the flag.
		acf_update_bidirectional_values( array( 1, 2, 3 ), 'post_123', $field );

		// The flag should still be true (not reset to false), proving early return.
		$this->assertTrue(
			acf_get_data( 'acf_doing_bidirectional_update' ),
			'Recursion guard flag should remain true when function returns early'
		);
	}

	/**
	 * Test acf_update_bidirectional_values returns early when bidirection disabled.
	 *
	 * When the enable_bidirection setting is false, the function should return
	 * immediately without setting the recursion flag.
	 */
	public function test_update_bidirectional_values_disabled() {
		acf_update_setting( 'enable_bidirection', false );

		$field = array(
			'type'                 => 'relationship',
			'name'                 => 'test_field',
			'bidirectional'        => true,
			'bidirectional_target' => array( 'field_abc123' ),
		);

		// Call the function.
		acf_update_bidirectional_values( array( 1, 2, 3 ), 'post_123', $field );

		// The recursion flag should never have been set (early return before line 70).
		$this->assertFalse(
			acf_get_data( 'acf_doing_bidirectional_update' ),
			'Recursion flag should not be set when bidirection is disabled'
		);
	}

	/**
	 * Test acf_update_bidirectional_values returns early without bidirectional config.
	 *
	 * When the field lacks bidirectional settings, the function should return
	 * immediately without setting the recursion flag.
	 */
	public function test_update_bidirectional_values_no_config() {
		acf_update_setting( 'enable_bidirection', true );

		$field = array(
			'type' => 'relationship',
			'name' => 'test_field',
			// Missing 'bidirectional' and 'bidirectional_target'.
		);

		acf_update_bidirectional_values( array( 1, 2, 3 ), 'post_123', $field );

		// The recursion flag should never have been set (early return at line 33-34).
		$this->assertFalse(
			acf_get_data( 'acf_doing_bidirectional_update' ),
			'Recursion flag should not be set when field lacks bidirectional config'
		);
	}

	/**
	 * Test acf_update_bidirectional_values returns early with empty bidirectional_target.
	 *
	 * When bidirectional_target is empty, the function should return immediately
	 * without setting the recursion flag.
	 */
	public function test_update_bidirectional_values_empty_target() {
		acf_update_setting( 'enable_bidirection', true );

		$field = array(
			'type'                 => 'relationship',
			'name'                 => 'test_field',
			'bidirectional'        => true,
			'bidirectional_target' => array(),
		);

		acf_update_bidirectional_values( array( 1, 2, 3 ), 'post_123', $field );

		// The recursion flag should never have been set (early return at line 33-34).
		$this->assertFalse(
			acf_get_data( 'acf_doing_bidirectional_update' ),
			'Recursion flag should not be set when bidirectional_target is empty'
		);
	}

	/**
	 * Test acf_update_bidirectional_values sets flag during update.
	 */
	public function test_update_bidirectional_values_sets_flag() {
		acf_update_setting( 'enable_bidirection', true );

		// Verify flag is not set before.
		$this->assertFalse(
			acf_get_data( 'acf_doing_bidirectional_update' ),
			'Flag should not be set initially'
		);

		// The flag is set internally during the update process.
		// We can only verify the initial state and that no errors occur.
		$field = array(
			'type'                 => 'relationship',
			'name'                 => 'test_field',
			'bidirectional'        => true,
			'bidirectional_target' => array( 'field_abc123' ),
		);

		// This will attempt to update but fail silently due to missing field objects.
		acf_update_bidirectional_values( array(), 'post_123', $field );

		// After the function completes, flag should be reset.
		$this->assertFalse(
			acf_get_data( 'acf_doing_bidirectional_update' ),
			'Flag should be reset after update'
		);
	}

	/**
	 * Test acf_render_bidirectional_field_settings returns early when disabled.
	 */
	public function test_render_bidirectional_field_settings_disabled() {
		acf_update_setting( 'enable_bidirection', false );

		ob_start();
		acf_render_bidirectional_field_settings( array( 'type' => 'relationship' ) );
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'No output when bidirection is disabled' );
	}

	/**
	 * Test acf_render_bidirectional_field_settings generates output when enabled.
	 */
	public function test_render_bidirectional_field_settings_enabled() {
		acf_update_setting( 'enable_bidirection', true );

		// Create a properly structured field with required properties.
		$field = array(
			'key'                  => 'field_test_123',
			'type'                 => 'relationship',
			'name'                 => 'test_field',
			'label'                => 'Test Field',
			'bidirectional'        => false,
			'bidirectional_target' => array(),
			'prefix'               => 'acf_field_group',
		);

		ob_start();
		acf_render_bidirectional_field_settings( $field );
		$output = ob_get_clean();

		// The function calls acf_render_field_setting which produces HTML.
		$this->assertStringContainsString( 'Bidirectional', $output, 'Should contain Bidirectional label' );
	}

	/**
	 * Test acf_build_bidirectional_relationship_field_target_args returns correct structure.
	 */
	public function test_build_bidirectional_relationship_field_target_args_structure() {
		$results = array( 'results' => array() );
		$options = array();

		$result = acf_build_bidirectional_relationship_field_target_args( $results, $options );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertArrayHasKey( 'results', $result, 'Result should have results key' );
	}

	/**
	 * Test acf_build_bidirectional_relationship_field_target_args applies filter.
	 */
	public function test_build_bidirectional_relationship_field_target_args_filter() {
		add_filter(
			'acf/bidirectional/supported_target_field_types',
			function ( $types ) {
				$types[] = 'custom_type';
				return $types;
			}
		);

		$results = array( 'results' => array() );
		$options = array();

		$result = acf_build_bidirectional_relationship_field_target_args( $results, $options );

		// The filter is applied, function should work.
		$this->assertIsArray( $result, 'Result should be an array' );
	}

	/**
	 * Test acf_build_bidirectional_relationship_field_target_args with parent_key option.
	 */
	public function test_build_bidirectional_relationship_field_target_args_with_parent_key() {
		$results = array( 'results' => array() );
		$options = array( 'parent_key' => 'field_123' );

		$result = acf_build_bidirectional_relationship_field_target_args( $results, $options );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertArrayHasKey( 'results', $result, 'Result should have results key' );
	}

	/**
	 * Test default field types for bidirectional relationship targets.
	 */
	public function test_default_bidirectional_target_field_types() {
		// Test that the filter receives the expected default types.
		$captured_types = null;
		add_filter(
			'acf/bidirectional/supported_target_field_types',
			function ( $types ) use ( &$captured_types ) {
				$captured_types = $types;
				return $types;
			}
		);

		$results = array( 'results' => array() );
		acf_build_bidirectional_relationship_field_target_args( $results, array() );

		$this->assertContains( 'relationship', $captured_types, 'Should include relationship type' );
		$this->assertContains( 'post_object', $captured_types, 'Should include post_object type' );
		$this->assertContains( 'user', $captured_types, 'Should include user type' );
		$this->assertContains( 'taxonomy', $captured_types, 'Should include taxonomy type' );
	}
}
