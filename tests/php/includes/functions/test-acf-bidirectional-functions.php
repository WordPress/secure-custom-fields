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

		wp_set_current_user( 0 );
		$this->assertFalse( acf_current_user_can_edit_in_context( acf_decode_post_id( $target_id ) ) );
		update_field( 'field_bidirectional_source', array( $target_id ), $source_id );

		$this->assertSame( array( (string) $target_id ), get_field( 'field_bidirectional_source', $source_id, false ) );
		$this->assertSame( array( (string) $source_id ), get_field( 'field_bidirectional_target', $target_id, false ) );
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
	 * Register a core relational field type for submitted-value tests.
	 *
	 * @param string $type Field type name.
	 */
	private function register_relational_field_type( $type ) {
		$class = "acf_field_{$type}";
		if ( ! class_exists( $class ) ) {
			acf_include( "includes/fields/class-acf-field-{$type}.php" );
		} elseif ( ! has_filter( "acf/update_value/type={$type}" ) ) {
			acf_register_field_type( $class );
		}
	}

	/**
	 * Save values the way a submitted form does.
	 *
	 * @param array          $values  Submitted values keyed by field key.
	 * @param integer|string $post_id Origin object ID.
	 */
	private function save_submitted_values( $values, $post_id ) {
		$original_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test fixture.
		$_POST['acf']  = $values;
		acf_save_post( $post_id );
		$_POST = $original_post;
	}

	/**
	 * Registers a page-limited Relationship field with a bidirectional target.
	 */
	private function add_page_relationship_fields() {
		$this->register_relational_field_type( 'relationship' );
		acf_add_local_field(
			array(
				'key'                  => 'field_bidi_post_source',
				'name'                 => 'bidi_post_source',
				'type'                 => 'relationship',
				'post_type'            => array( 'page' ),
				'taxonomy'             => array(),
				'bidirectional'        => 1,
				'bidirectional_target' => array( 'field_bidi_post_target' ),
			)
		);
		acf_add_local_field(
			array(
				'key'       => 'field_bidi_post_target',
				'name'      => 'bidi_post_target',
				'type'      => 'relationship',
				'post_type' => array(),
				'taxonomy'  => array(),
			)
		);
	}

	/**
	 * Values submitted without a logged-in user don't add back-references.
	 */
	public function test_submitted_save_without_user_does_not_link_back() {
		acf_update_setting( 'enable_bidirection', true );
		$this->add_page_relationship_fields();
		wp_set_current_user( 0 );

		$source_id = wp_insert_post(
			array(
				'post_title'  => 'Source',
				'post_status' => 'publish',
			)
		);
		$target_id = wp_insert_post(
			array(
				'post_title'  => 'Public target',
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$original_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test fixture.
		acf_save_post( $source_id, array( 'field_bidi_post_source' => array( (string) $target_id ) ) );
		$_POST = $original_post;

		$this->assertSame( array( (string) $target_id ), get_field( 'field_bidi_post_source', $source_id, false ) );
		$this->assertEmpty( get_post_meta( $target_id, 'bidi_post_target', true ) );
		$this->assertEmpty( acf_get_data( 'acf_saving_submitted_values' ) );
	}

	/**
	 * Submitted values only add back-references to posts the current user can edit.
	 */
	public function test_submitted_save_only_links_posts_the_user_can_edit() {
		acf_update_setting( 'enable_bidirection', true );
		$this->add_page_relationship_fields();
		wp_set_current_user( 0 );

		$author_id = wp_insert_user(
			array(
				'user_login' => 'bidi-save-author',
				'user_pass'  => 'password',
				'role'       => 'author',
			)
		);
		$source_id = wp_insert_post(
			array(
				'post_title'  => 'Source',
				'post_status' => 'publish',
				'post_author' => $author_id,
			)
		);
		$own_id    = wp_insert_post(
			array(
				'post_title'  => 'Own target',
				'post_status' => 'publish',
				'post_author' => $author_id,
			)
		);
		$other_id  = wp_insert_post(
			array(
				'post_title'  => 'Other target',
				'post_status' => 'publish',
				'post_author' => 1,
			)
		);
		wp_set_current_user( $author_id );

		$this->save_submitted_values( array( 'field_bidi_post_source' => array( (string) $own_id, (string) $other_id ) ), $source_id );
		wp_set_current_user( 0 );

		$this->assertSame( array( (string) $source_id ), get_field( 'field_bidi_post_target', $own_id, false ) );
		$this->assertEmpty( get_post_meta( $other_id, 'bidi_post_target', true ), 'Posts the user cannot edit are not linked' );
	}

	/**
	 * Submitted User values need permission to edit the user, while update_field() is not checked.
	 */
	public function test_submitted_user_target_needs_permission() {
		acf_update_setting( 'enable_bidirection', true );
		$this->add_role_limited_user_fields();
		wp_set_current_user( 0 );

		$subscriber_id = wp_insert_user(
			array(
				'user_login' => 'bidi-subscriber',
				'user_pass'  => 'password',
				'role'       => 'subscriber',
			)
		);
		$source_id     = wp_insert_post(
			array(
				'post_title'  => 'Source',
				'post_status' => 'publish',
			)
		);

		$this->save_submitted_values( array( 'field_bidi_user_source' => (string) $subscriber_id ), $source_id );
		$this->assertEmpty( get_user_meta( $subscriber_id, 'bidi_user_target', true ), 'Users the submitter cannot edit are not linked' );

		update_field( 'field_bidi_user_source', $subscriber_id, $source_id );
		$this->assertSame( array( (string) $source_id ), get_field( 'field_bidi_user_target', "user_{$subscriber_id}", false ) );
	}

	/**
	 * The deprecated SCF filter can still prevent a back-reference from being added.
	 */
	public function test_deprecated_allow_target_filter_still_applies() {
		acf_update_setting( 'enable_bidirection', true );
		$this->add_page_relationship_fields();

		$admin_id  = wp_insert_user(
			array(
				'user_login' => 'bidi-filter-admin',
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		$source_id = wp_insert_post(
			array(
				'post_title'  => 'Source',
				'post_status' => 'publish',
			)
		);
		$target_id = wp_insert_post(
			array(
				'post_title'  => 'Target',
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$deprecated_hooks = array();
		$record_hook      = function ( $hook ) use ( &$deprecated_hooks ) {
			$deprecated_hooks[] = $hook;
		};
		add_action( 'deprecated_hook_run', $record_hook );
		add_filter( 'deprecated_hook_trigger_error', '__return_false' );
		add_filter( 'scf/bidirectional/allow_target', '__return_false' );
		wp_set_current_user( $admin_id );

		$this->save_submitted_values( array( 'field_bidi_post_source' => array( (string) $target_id ) ), $source_id );

		wp_set_current_user( 0 );
		remove_filter( 'scf/bidirectional/allow_target', '__return_false' );
		remove_filter( 'deprecated_hook_trigger_error', '__return_false' );
		remove_action( 'deprecated_hook_run', $record_hook );

		$this->assertEmpty( get_post_meta( $target_id, 'bidi_post_target', true ) );
		$this->assertContains( 'scf/bidirectional/allow_target', $deprecated_hooks );
	}

	/**
	 * Registers a role-limited User field with a bidirectional Relationship target on users.
	 */
	private function add_role_limited_user_fields() {
		$this->register_relational_field_type( 'user' );
		$this->register_relational_field_type( 'relationship' );
		acf_add_local_field(
			array(
				'key'                  => 'field_bidi_user_source',
				'name'                 => 'bidi_user_source',
				'type'                 => 'user',
				'role'                 => array( 'subscriber' ),
				'multiple'             => 0,
				'bidirectional'        => 1,
				'bidirectional_target' => array( 'field_bidi_user_target' ),
			)
		);
		acf_add_local_field(
			array(
				'key'       => 'field_bidi_user_target',
				'name'      => 'bidi_user_target',
				'type'      => 'relationship',
				'post_type' => array(),
				'taxonomy'  => array(),
			)
		);
	}

	/**
	 * A submitter who can edit the target may link to it even when it is not a listed choice.
	 */
	public function test_submitted_target_the_user_can_edit_is_linked() {
		acf_update_setting( 'enable_bidirection', true );
		$this->add_role_limited_user_fields();

		$admin_id  = wp_insert_user(
			array(
				'user_login' => 'bidi-editor-admin',
				'user_pass'  => 'password',
				'role'       => 'administrator',
			)
		);
		$editor_id = wp_insert_user(
			array(
				'user_login' => 'bidi-editor-target',
				'user_pass'  => 'password',
				'role'       => 'editor',
			)
		);
		$source_id = wp_insert_post(
			array(
				'post_title'  => 'Admin submission',
				'post_status' => 'publish',
			)
		);
		wp_set_current_user( $admin_id );

		$this->save_submitted_values( array( 'field_bidi_user_source' => (string) $editor_id ), $source_id );
		$this->assertSame( array( (string) $source_id ), get_field( 'field_bidi_user_target', "user_{$editor_id}", false ) );

		wp_set_current_user( 0 );
	}

	/**
	 * Saving values to a revision never writes back-references.
	 */
	public function test_revision_origin_never_links_back() {
		acf_update_setting( 'enable_bidirection', true );
		$this->add_role_limited_user_fields();
		wp_set_current_user( 0 );

		$subscriber_id = wp_insert_user(
			array(
				'user_login' => 'bidi-revision-subscriber',
				'user_pass'  => 'password',
				'role'       => 'subscriber',
			)
		);
		$parent_id     = wp_insert_post(
			array(
				'post_title'  => 'Parent',
				'post_status' => 'publish',
			)
		);
		$revision_id   = wp_insert_post(
			array(
				'post_title'  => 'Parent',
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $parent_id,
			)
		);
		$this->assertSame( $parent_id, wp_is_post_revision( $revision_id ) );

		update_field( 'field_bidi_user_source', $subscriber_id, $revision_id );
		$this->assertEmpty( get_user_meta( $subscriber_id, 'bidi_user_target', true ) );
	}

	/**
	 * The submitted-values flag is restored even when saving throws.
	 */
	public function test_submitted_flag_is_restored_after_an_exception() {
		acf_update_setting( 'enable_bidirection', true );
		$this->add_role_limited_user_fields();
		$source_id = wp_insert_post( array( 'post_title' => 'Source' ) );
		$thrower   = static function ( $value ) {
			if ( null !== $value ) {
				throw new RuntimeException( 'save failed' );
			}
			return $value;
		};
		add_filter( 'acf/update_value', $thrower );

		try {
			$this->save_submitted_values( array( 'field_bidi_user_source' => '1' ), $source_id );
			$this->fail( 'The save should have thrown.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'save failed', $e->getMessage() );
		} finally {
			remove_filter( 'acf/update_value', $thrower );
		}

		$this->assertEmpty( acf_get_data( 'acf_saving_submitted_values' ) );
	}
}
