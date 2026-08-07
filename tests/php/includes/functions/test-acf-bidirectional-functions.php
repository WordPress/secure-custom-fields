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
	 * Test trusted programmatic updates retain bidirectional synchronization.
	 */
	public function test_programmatic_update_field_retains_bidirectional_synchronization() {
		acf_update_setting( 'enable_bidirection', true );
		if ( ! class_exists( 'acf_field_relationship' ) ) {
			acf_include( 'includes/fields/class-acf-field-relationship.php' );
		} elseif ( ! has_filter( 'acf/update_value/type=relationship' ) ) {
			acf_register_field_type( 'acf_field_relationship' );
		}
		$source_id = wp_insert_post( array( 'post_title' => 'Bidirectional source' ) );
		$target_id = wp_insert_post( array( 'post_title' => 'Bidirectional target' ) );
		acf_add_local_field(
			array(
				'key'                  => 'field_bidirectional_source',
				'name'                 => 'bidirectional_source',
				'label'                => 'Bidirectional source',
				'type'                 => 'relationship',
				'post_type'            => array(),
				'taxonomy'             => array(),
				'bidirectional'        => true,
				'bidirectional_target' => array( 'field_bidirectional_target' ),
			)
		);
		acf_add_local_field(
			array(
				'key'                  => 'field_bidirectional_target',
				'name'                 => 'bidirectional_target',
				'label'                => 'Bidirectional target',
				'type'                 => 'relationship',
				'post_type'            => array(),
				'taxonomy'             => array(),
				'bidirectional'        => false,
				'bidirectional_target' => array(),
			)
		);

		$field = acf_get_field( 'field_bidirectional_source' );
		$plan  = _scf_prepare_bidirectional_update( array( $target_id ), $source_id, $field, false, array() );
		$this->assertSame( array( $target_id ), $plan['destinations'] );
		$this->assertNull( get_field( 'field_bidirectional_target', $target_id, false ) );

		wp_set_current_user( 0 );
		$this->assertFalse( acf_current_user_can_edit_in_context( acf_decode_post_id( $target_id ) ) );
		update_field( 'field_bidirectional_source', array( $target_id ), $source_id );

		$this->assertSame( array( (string) $target_id ), get_field( 'field_bidirectional_source', $source_id, false ) );
		$this->assertSame( array( (string) $source_id ), get_field( 'field_bidirectional_target', $target_id, false ) );
		$field['type'] = 'user';
		$this->assertSame( array( "user_{$target_id}" ), _scf_prepare_bidirectional_update( array( $target_id ), $source_id, $field, null, array() )['destinations'] );
		$field['type'] = 'taxonomy';
		$this->assertSame( array( "term_{$target_id}" ), _scf_prepare_bidirectional_update( array( $target_id ), $source_id, $field, null, array() )['destinations'] );

		$field['type'] = 'relationship';
		wp_delete_post( $target_id, true );
		$removal_plan = _scf_prepare_bidirectional_update( array(), $source_id, $field, false, array( $target_id ) );
		$this->assertSame( array( $target_id ), $removal_plan['subtractions'] );
		$this->assertSame( array(), $removal_plan['destinations'] );
		$addition_plan = _scf_prepare_bidirectional_update( array( $target_id ), $source_id, $field, false, array() );
		$this->assertSame( array( $target_id ), $addition_plan['destinations'] );
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

	/**
	 * Registers the container field types used by the preflight regression tests.
	 */
	private function register_container_field_types() {
		foreach ( array( 'relationship', 'repeater', 'group' ) as $type ) {
			$class = 'acf_field_' . ( 'group' === $type ? '_group' : $type );
			if ( ! class_exists( $class ) ) {
				acf_include( "includes/fields/class-acf-field-{$type}.php" );
				acf_register_field_type( $class );
			}
		}
		if ( ! class_exists( 'acf_repeater_table' ) ) {
			acf_include( 'includes/fields/class-acf-repeater-table.php' );
		}
	}

	/**
	 * Client-controlled paginated row keys cannot hide a bidirectional destination from the preflight.
	 */
	public function test_paginated_client_row_keys_are_still_checked() {
		acf_update_setting( 'enable_bidirection', true );
		$this->register_container_field_types();
		$source = wp_insert_post( array( 'post_title' => 'Alias source' ) );
		$target = wp_insert_post( array( 'post_title' => 'Alias target' ) );
		acf_add_local_field_group(
			array(
				'key'      => 'group_alias',
				'title'    => 'Alias',
				'fields'   => array(
					array(
						'key'        => 'field_alias_repeater',
						'name'       => 'alias_repeater',
						'type'       => 'repeater',
						'pagination' => 1,
						'sub_fields' => array(
							array(
								'key'                  => 'field_alias_rel',
								'name'                 => 'alias_rel',
								'type'                 => 'relationship',
								'post_type'            => array(),
								'taxonomy'             => array(),
								'bidirectional'        => true,
								'bidirectional_target' => array( 'field_alias_target' ),
							),
						),
					),
					array(
						'key'       => 'field_alias_target',
						'name'      => 'alias_target',
						'type'      => 'relationship',
						'post_type' => array(),
						'taxonomy'  => array(),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);
		acf_update_setting( 'enable_bidirection', false );
		update_field( 'field_alias_repeater', array( array( 'field_alias_rel' => array() ) ), $source );
		acf_update_setting( 'enable_bidirection', true );

		// `row-0` deletes index 0; the aliased `evilrow-0` edits the same index with the target,
		// which the saver resurrects. The preflight must still see the target.
		$payload      = array(
			'row-0'     => array(
				'acf_deleted'     => 1,
				'field_alias_rel' => array(),
			),
			'evilrow-0' => array( 'field_alias_rel' => array( (string) $target ) ),
		);
		$destinations = _scf_collect_bidirectional_destinations( acf_get_field( 'field_alias_repeater' ), $payload, $source, null, true );

		$this->assertContains( (string) $target, $destinations, 'Aliased paginated edit must be checked' );

		// The saver treats keys without `row` as new rows before inspecting `acf_deleted`,
		// so the preflight must collect their destinations too.
		$payload      = array(
			'new-abc' => array(
				'acf_deleted'     => 1,
				'field_alias_rel' => array( (string) $target ),
			),
		);
		$destinations = _scf_collect_bidirectional_destinations( acf_get_field( 'field_alias_repeater' ), $payload, $source, null, true );

		$this->assertContains( (string) $target, $destinations, 'New paginated row with deletion metadata must be checked' );

		// An aliased reorder can resurrect the canonical deletion payload, including its
		// relationship value, so that value must remain in the permission plan.
		$payload      = array(
			'row-0'     => array(
				'acf_deleted'     => 1,
				'field_alias_rel' => array( (string) $target ),
			),
			'evilrow-0' => array(
				'acf_reordered'   => 1,
				'field_alias_rel' => array(),
			),
		);
		$destinations = _scf_collect_bidirectional_destinations( acf_get_field( 'field_alias_repeater' ), $payload, $source, null, true );

		$this->assertContains( (string) $target, $destinations, 'Reordered deletion payload must be checked' );
	}

	/**
	 * A paginated repeater nested in a Group loads its existing rows from the prefixed key.
	 */
	public function test_paginated_repeater_in_group_uses_prefixed_name() {
		acf_update_setting( 'enable_bidirection', true );
		$this->register_container_field_types();
		$source = wp_insert_post( array( 'post_title' => 'Group source' ) );
		acf_add_local_field_group(
			array(
				'key'      => 'group_nested_pag',
				'title'    => 'Nested pag',
				'fields'   => array(
					array(
						'key'        => 'field_np_group',
						'name'       => 'np_group',
						'type'       => 'group',
						'sub_fields' => array(
							array(
								'key'        => 'field_np_repeater',
								'name'       => 'np_repeater',
								'type'       => 'repeater',
								'pagination' => 1,
								'sub_fields' => array(
									array(
										'key'           => 'field_np_rel',
										'name'          => 'np_rel',
										'type'          => 'relationship',
										'post_type'     => array(),
										'taxonomy'      => array(),
										'bidirectional' => true,
										'bidirectional_target' => array( 'field_np_target' ),
									),
								),
							),
						),
					),
					array(
						'key'       => 'field_np_target',
						'name'      => 'np_target',
						'type'      => 'relationship',
						'post_type' => array(),
						'taxonomy'  => array(),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);

		// The Group stores its child repeater under `np_group_np_repeater`; the preflight must
		// load existing rows from that key, not the child's own name.
		$loaded_names = array();
		$spy          = static function ( $check, $post_id, $field ) use ( &$loaded_names ) {
			if ( 'repeater' === $field['type'] ) {
				$loaded_names[] = $field['name'];
			}
			return $check;
		};
		add_filter( 'acf/pre_load_value', $spy, 10, 3 );
		_scf_collect_bidirectional_destinations(
			acf_get_field( 'field_np_group' ),
			array( 'field_np_repeater' => array( array( 'field_np_rel' => array() ) ) ),
			$source,
			null,
			true
		);
		remove_filter( 'acf/pre_load_value', $spy, 10 );

		$this->assertContains( 'np_group_np_repeater', $loaded_names, 'Nested repeater must load from the prefixed key' );
		$this->assertNotContains( 'np_repeater', $loaded_names, 'Nested repeater must not load from the unprefixed key' );
	}

	/**
	 * A paginated repeater nested in a grouped Clone uses the Clone's database prefix.
	 */
	public function test_paginated_repeater_in_nested_clone_uses_prefixed_name() {
		$source       = wp_insert_post( array( 'post_title' => 'Nested clone source' ) );
		$loaded_names = array();
		$spy          = static function ( $check, $post_id, $field ) use ( &$loaded_names ) {
			if ( 'repeater' === $field['type'] ) {
				$loaded_names[] = $field['name'];
			}
			return $check;
		};
		add_filter( 'acf/pre_load_value', $spy, 10, 3 );

		foreach (
			array(
				'without_name_prefix' => array( 'rows', 'outer_rows' ),
				'with_name_prefix'    => array( 'clonebox_rows', 'outer_clonebox_rows' ),
			) as list( $resolved_name, $expected_name )
		) {
			$field = array(
				'key'        => 'field_clone_outer',
				'name'       => 'outer',
				'_name'      => 'outer',
				'type'       => 'group',
				'sub_fields' => array(
					array(
						'key'        => 'field_clonebox',
						'name'       => 'clonebox',
						'_name'      => 'clonebox',
						'type'       => 'clone',
						'sub_fields' => array(
							array(
								'key'        => 'field_clone_rows',
								'name'       => $resolved_name,
								'_name'      => 'rows',
								'type'       => 'repeater',
								'pagination' => 1,
								'sub_fields' => array(),
							),
						),
					),
				),
			);
			$value = array(
				'field_clonebox' => array(
					'field_clone_rows' => array(),
				),
			);

			_scf_collect_bidirectional_destinations( $field, $value, $source, null, true );

			$this->assertContains( $expected_name, $loaded_names );
			$this->assertNotContains( $resolved_name, $loaded_names );
		}

		remove_filter( 'acf/pre_load_value', $spy, 10 );
	}

	/**
	 * On create, a default-value target is checked because the saver applies the default.
	 */
	public function test_create_checks_default_value_targets() {
		acf_update_setting( 'enable_bidirection', true );
		$this->register_container_field_types();
		$target = wp_insert_post( array( 'post_title' => 'Default target' ) );
		acf_add_local_field_group(
			array(
				'key'      => 'group_default',
				'title'    => 'Default',
				'fields'   => array(
					array(
						'key'                  => 'field_default_rel',
						'name'                 => 'default_rel',
						'type'                 => 'relationship',
						'post_type'            => array(),
						'taxonomy'             => array(),
						'default_value'        => array( (string) $target ),
						'bidirectional'        => true,
						'bidirectional_target' => array( 'field_default_target' ),
					),
					array(
						'key'       => 'field_default_target',
						'name'      => 'default_target',
						'type'      => 'relationship',
						'post_type' => array(),
						'taxonomy'  => array(),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);

		// Create (origin id 0), field submitted empty: the saver subtracts the default target.
		$destinations = _scf_collect_bidirectional_destinations( acf_get_field( 'field_default_rel' ), array(), 0, array(), false );

		$this->assertContains( (string) $target, $destinations, 'Create must check the default-derived subtraction target' );
	}

	/**
	 * The preflight does not infer a destination context for third-party field types.
	 */
	public function test_non_core_field_type_without_explicit_prefix_has_no_permission_destinations() {
		acf_update_setting( 'enable_bidirection', true );
		$this->register_container_field_types();
		$source = wp_insert_post( array( 'post_title' => 'Non-core source' ) );
		$target = wp_insert_post( array( 'post_title' => 'Non-core target' ) );
		acf_add_local_field(
			array(
				'key'       => 'field_noncore_target',
				'name'      => 'noncore_target',
				'type'      => 'relationship',
				'post_type' => array(),
				'taxonomy'  => array(),
			)
		);
		$field = array(
			'key'                  => 'field_noncore_source',
			'name'                 => 'noncore_source',
			'type'                 => 'relationship',
			'bidirectional'        => true,
			'bidirectional_target' => array( 'field_noncore_target' ),
		);

		// Core type maps precisely: a single post-context destination, no over-check.
		$core = _scf_prepare_bidirectional_update( array( $target ), $source, $field, null, array() )['destinations'];
		$this->assertSame( array( $target ), $core );

		// A third-party field's target context cannot be inferred from its type.
		$field['type'] = 'my_custom_selector';
		$destinations  = _scf_prepare_bidirectional_update( array( $target ), $source, $field, null, array() )['destinations'];
		$this->assertSame( array(), $destinations );
	}

	/**
	 * Non-core field types retain explicit prefixes for trusted programmatic updates.
	 */
	public function test_non_core_field_type_uses_explicit_target_prefix() {
		acf_update_setting( 'enable_bidirection', true );
		$this->register_container_field_types();
		$source  = wp_insert_post( array( 'post_title' => 'Non-core source' ) );
		$user_id = wp_insert_user(
			array(
				'user_login' => 'noncore-target',
				'user_pass'  => 'password',
			)
		);
		$this->assertNull( get_post( $user_id ), 'Fixture requires no post with the user ID' );
		acf_add_local_field(
			array(
				'key'       => 'field_noncore_subtraction_target',
				'name'      => 'noncore_subtraction_target',
				'type'      => 'relationship',
				'post_type' => array(),
				'taxonomy'  => array(),
			)
		);
		$field = array(
			'key'                  => 'field_noncore_subtraction_source',
			'name'                 => 'noncore_subtraction_source',
			'type'                 => 'my_custom_selector',
			'bidirectional'        => true,
			'bidirectional_target' => array( 'field_noncore_subtraction_target' ),
		);

		$additions    = _scf_prepare_bidirectional_update( array( $user_id ), $source, $field, 'user', array() )['destinations'];
		$subtractions = _scf_prepare_bidirectional_update( array(), $source, $field, 'user', array( $user_id ) )['destinations'];

		$this->assertSame( array( "user_{$user_id}" ), $additions );
		$this->assertSame( array( "user_{$user_id}" ), $subtractions );

		$unprefixed = _scf_prepare_bidirectional_update( array( $user_id ), $source, $field, false, array() );
		$this->assertSame( array( $user_id ), $unprefixed['additions'] );
		$this->assertSame( array( $user_id ), $unprefixed['destinations'] );

		acf_update_bidirectional_values( array( $user_id ), $source, $field, 'user' );
		$this->assertSame( array( (string) $source ), get_field( 'field_noncore_subtraction_target', "user_{$user_id}", false ) );
	}
}
