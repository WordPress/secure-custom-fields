<?php
/**
 * Tests for hook functions in includes/acf-hook-functions.php
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for acf-hook-functions.php
 *
 * Tests cover:
 * - Filter variations registration and application
 * - Action variations registration and application
 * - Deprecated hook registration and backward compatibility
 */
class Test_ACF_Hook_Functions extends BaseTestCase {

	/**
	 * Original deprecated-hooks store data.
	 *
	 * @var array
	 */
	private $original_deprecated_hooks;

	/**
	 * Set up before each test.
	 *
	 * Saves the initial state of the deprecated-hooks store so it can
	 * be restored after each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Save the initial state of the deprecated-hooks store.
		$deprecated_store = acf_get_store( 'deprecated-hooks' );
		if ( $deprecated_store ) {
			$this->original_deprecated_hooks = $deprecated_store->get_data();
		}
	}

	/**
	 * Clean up test hooks after each test.
	 *
	 * Note: We only remove test-specific hooks from variations store.
	 * For deprecated-hooks, we restore the original state since it uses
	 * append() without keys.
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Remove test-specific entries from variations store (not reset).
		$variations_store = acf_get_store( 'hook-variations' );
		if ( $variations_store ) {
			$variations_store->remove( 'test_filter' );
			$variations_store->remove( 'test_action' );
		}

		// Restore the original deprecated-hooks store state.
		// Note: ACF_Data has public $data property, not set_data() method.
		$deprecated_store = acf_get_store( 'deprecated-hooks' );
		if ( $deprecated_store && isset( $this->original_deprecated_hooks ) ) {
			$deprecated_store->data = $this->original_deprecated_hooks;
		}

		// Remove all test filters and actions.
		remove_all_filters( 'test_filter' );
		remove_all_filters( 'test_filter/type=text' );
		remove_all_filters( 'test_filter/name=my_field' );
		remove_all_filters( 'test_filter/name=backup_field' );
		remove_all_filters( 'test_filter/key=field_123' );
		remove_all_filters( 'test_filter/type=' );
		remove_all_filters( 'test_filter/type=123' );
		remove_all_filters( 'test_action' );
		remove_all_filters( 'test_action/type=text' );
		remove_all_filters( 'new_replacement_hook' );
		remove_all_filters( 'old_deprecated_hook' );
		remove_all_filters( 'new_action' );
		remove_all_filters( 'old_action' );
		remove_all_filters( 'old_hook_1' );
		remove_all_filters( 'old_hook_2' );
	}

	/**
	 * Data provider for hook variation registration tests.
	 *
	 * @return array
	 */
	public function data_provider_hook_variation_registration() {
		return array(
			'filter with index 0'        => array( 'filter', array( 'type', 'name' ), 0 ),
			'filter with custom index 2' => array( 'filter', array( 'type' ), 2 ),
			'action with index 0'        => array( 'action', array( 'type', 'name' ), 0 ),
			'action with custom index 1' => array( 'action', array( 'key' ), 1 ),
		);
	}

	/**
	 * Test that hook variation functions store correct data structure.
	 *
	 * @dataProvider data_provider_hook_variation_registration
	 * @param string $hook_type   Either 'filter' or 'action'.
	 * @param array  $variations  The variation keys.
	 * @param int    $index       The argument index.
	 */
	public function test_add_hook_variations_stores_data( $hook_type, $variations, $index ) {
		$hook_name = 'filter' === $hook_type ? 'test_filter' : 'test_action';

		if ( 'filter' === $hook_type ) {
			acf_add_filter_variations( $hook_name, $variations, $index );
		} else {
			acf_add_action_variations( $hook_name, $variations, $index );
		}

		$store = acf_get_store( 'hook-variations' );
		$data  = $store->get( $hook_name );

		$this->assertIsArray( $data, 'Stored data should be an array' );
		$this->assertSame( $hook_type, $data['type'], "Type should be $hook_type" );
		$this->assertSame( $variations, $data['variations'], 'Variations should match' );
		$this->assertSame( $index, $data['index'], "Index should be $index" );
	}

	/**
	 * Test that hook variation functions register handlers.
	 *
	 * @dataProvider data_provider_hook_variation_registration
	 * @param string $hook_type   Either 'filter' or 'action'.
	 * @param array  $variations  The variation keys.
	 * @param int    $index       The argument index.
	 */
	public function test_add_hook_variations_registers_handler( $hook_type, $variations, $index ) {
		$hook_name = 'filter' === $hook_type ? 'test_filter' : 'test_action';

		if ( 'filter' === $hook_type ) {
			acf_add_filter_variations( $hook_name, $variations, $index );
			$has_handler = has_filter( $hook_name ) !== false;
		} else {
			acf_add_action_variations( $hook_name, $variations, $index );
			$has_handler = has_action( $hook_name ) !== false;
		}

		$this->assertTrue( $has_handler, "Handler should be registered for $hook_type" );
	}

	/**
	 * Test _acf_apply_hook_variations applies filter variations correctly.
	 *
	 * Uses index 1 because the filter signature is: apply_filters($hook, $value, $field)
	 * where $field is at index 1 (second argument after the value).
	 */
	public function test_apply_hook_variations_applies_filter() {
		// Index 1 means the field array is at position 1.
		acf_add_filter_variations( 'test_filter', array( 'type' ), 1 );

		// Add a specific variation handler.
		add_filter(
			'test_filter/type=text',
			function ( $value ) {
				return $value . '_modified';
			}
		);

		$field  = array( 'type' => 'text' );
		$result = apply_filters( 'test_filter', 'original', $field );

		$this->assertSame( 'original_modified', $result, 'Filter variation should modify the value' );
	}

	/**
	 * Test _acf_apply_hook_variations applies filter when field IS the value (index 0).
	 *
	 * This matches how acf/load_field works where the field itself is filtered.
	 */
	public function test_apply_hook_variations_with_field_as_value() {
		// Index 0 means the filtered value itself is the field array.
		acf_add_filter_variations( 'test_filter', array( 'type' ), 0 );

		add_filter(
			'test_filter/type=text',
			function ( $field ) {
				$field['modified'] = true;
				return $field;
			}
		);

		$field  = array( 'type' => 'text' );
		$result = apply_filters( 'test_filter', $field );

		$this->assertArrayHasKey( 'modified', $result, 'Field should be modified' );
		$this->assertTrue( $result['modified'], 'Modified flag should be true' );
	}

	/**
	 * Test _acf_apply_hook_variations applies multiple filter variations.
	 */
	public function test_apply_hook_variations_applies_multiple_filters() {
		acf_add_filter_variations( 'test_filter', array( 'type', 'name' ), 1 );

		// Add handlers for both variations.
		add_filter(
			'test_filter/type=text',
			function ( $value ) {
				return $value . '_type';
			}
		);

		add_filter(
			'test_filter/name=my_field',
			function ( $value ) {
				return $value . '_name';
			}
		);

		$field  = array(
			'type' => 'text',
			'name' => 'my_field',
		);
		$result = apply_filters( 'test_filter', 'original', $field );

		$this->assertSame( 'original_type_name', $result, 'Both filter variations should be applied in order' );
	}

	/**
	 * Test _acf_apply_hook_variations uses backup value with underscore prefix.
	 */
	public function test_apply_hook_variations_uses_backup_value() {
		acf_add_filter_variations( 'test_filter', array( 'name' ), 1 );

		add_filter(
			'test_filter/name=backup_field',
			function ( $value ) {
				return $value . '_backup';
			}
		);

		// Field with both regular and backup value - backup should take precedence.
		$field  = array(
			'name'  => 'regular_field',
			'_name' => 'backup_field',
		);
		$result = apply_filters( 'test_filter', 'original', $field );

		$this->assertSame( 'original_backup', $result, 'Backup value with underscore prefix should be used' );
	}

	/**
	 * Test _acf_apply_hook_variations skips missing variation values.
	 */
	public function test_apply_hook_variations_skips_missing_values() {
		acf_add_filter_variations( 'test_filter', array( 'type', 'missing_key' ), 1 );

		add_filter(
			'test_filter/type=text',
			function ( $value ) {
				return $value . '_type';
			}
		);

		// Field without 'missing_key'.
		$field  = array( 'type' => 'text' );
		$result = apply_filters( 'test_filter', 'original', $field );

		$this->assertSame( 'original_type', $result, 'Only existing variation keys should be applied' );
	}

	/**
	 * Test _acf_apply_hook_variations with field at index 2 (like acf/load_value).
	 */
	public function test_apply_hook_variations_with_index_2() {
		// Index 2 means the field array is the 3rd argument (after hook, value, post_id).
		acf_add_filter_variations( 'test_filter', array( 'type' ), 2 );

		add_filter(
			'test_filter/type=text',
			function ( $value ) {
				return $value . '_modified';
			}
		);

		$field  = array( 'type' => 'text' );
		$result = apply_filters( 'test_filter', 'original', 123, $field );

		$this->assertSame( 'original_modified', $result, 'Filter should work with field at index 2' );
	}

	/**
	 * Test _acf_apply_hook_variations triggers actions instead of filters.
	 */
	public function test_apply_hook_variations_triggers_actions() {
		acf_add_action_variations( 'test_action', array( 'type' ), 0 );

		$action_called = false;
		add_action(
			'test_action/type=text',
			function () use ( &$action_called ) {
				$action_called = true;
			}
		);

		$field = array( 'type' => 'text' );
		do_action( 'test_action', $field );

		$this->assertTrue( $action_called, 'Action variation should be triggered' );
	}

	/**
	 * Test acf_add_deprecated_filter stores deprecated hook data.
	 */
	public function test_add_deprecated_filter_stores_data() {
		acf_add_deprecated_filter( 'old_deprecated_hook', '5.0.0', 'new_replacement_hook' );

		$store = acf_get_store( 'deprecated-hooks' );
		$data  = $store->get_data();

		$this->assertNotEmpty( $data, 'Deprecated hooks store should have data' );

		$found = false;
		foreach ( $data as $hook ) {
			if ( 'old_deprecated_hook' === $hook['deprecated'] ) {
				$found = true;
				$this->assertSame( 'filter', $hook['type'], 'Type should be filter' );
				$this->assertSame( '5.0.0', $hook['version'], 'Version should match' );
				$this->assertSame( 'new_replacement_hook', $hook['replacement'], 'Replacement should match' );
				break;
			}
		}

		$this->assertTrue( $found, 'Deprecated hook should be stored' );
	}

	/**
	 * Test acf_add_deprecated_filter registers handler on replacement hook.
	 */
	public function test_add_deprecated_filter_registers_handler() {
		acf_add_deprecated_filter( 'old_deprecated_hook', '5.0.0', 'new_replacement_hook' );

		$this->assertTrue(
			has_filter( 'new_replacement_hook' ) !== false,
			'Handler should be registered on replacement hook'
		);
	}

	/**
	 * Test acf_add_deprecated_action stores deprecated action data.
	 */
	public function test_add_deprecated_action_stores_data() {
		acf_add_deprecated_action( 'old_action', '5.0.0', 'new_action' );

		$store = acf_get_store( 'deprecated-hooks' );
		$data  = $store->get_data();

		$found = false;
		foreach ( $data as $hook ) {
			if ( 'old_action' === $hook['deprecated'] ) {
				$found = true;
				$this->assertSame( 'action', $hook['type'], 'Type should be action' );
				break;
			}
		}

		$this->assertTrue( $found, 'Deprecated action should be stored' );
	}

	/**
	 * Test _acf_apply_deprecated_hook applies deprecated filter.
	 */
	public function test_apply_deprecated_hook_applies_filter() {
		acf_add_deprecated_filter( 'old_deprecated_hook', '5.0.0', 'new_replacement_hook' );

		// Someone is still using the old hook.
		add_filter(
			'old_deprecated_hook',
			function ( $value ) {
				return $value . '_old_modified';
			}
		);

		// Trigger the new hook.
		$result = apply_filters( 'new_replacement_hook', 'original' );

		$this->assertSame( 'original_old_modified', $result, 'Deprecated filter should be applied' );
	}

	/**
	 * Test _acf_apply_deprecated_hook applies deprecated action.
	 */
	public function test_apply_deprecated_hook_applies_action() {
		acf_add_deprecated_action( 'old_action', '5.0.0', 'new_action' );

		$action_called = false;
		add_action(
			'old_action',
			function () use ( &$action_called ) {
				$action_called = true;
			}
		);

		// Trigger the new action.
		do_action( 'new_action' );

		$this->assertTrue( $action_called, 'Deprecated action should be called' );
	}

	/**
	 * Test _acf_apply_deprecated_hook does nothing when no one uses deprecated hook.
	 */
	public function test_apply_deprecated_hook_skips_unused() {
		acf_add_deprecated_filter( 'old_deprecated_hook', '5.0.0', 'new_replacement_hook' );

		// No one hooks into the old hook.
		$result = apply_filters( 'new_replacement_hook', 'original' );

		$this->assertSame( 'original', $result, 'Value should pass through unchanged when deprecated hook is unused' );
	}

	/**
	 * Test multiple deprecated hooks can be registered for the same replacement.
	 */
	public function test_multiple_deprecated_hooks_for_same_replacement() {
		acf_add_deprecated_filter( 'old_hook_1', '4.0.0', 'new_replacement_hook' );
		acf_add_deprecated_filter( 'old_hook_2', '5.0.0', 'new_replacement_hook' );

		add_filter(
			'old_hook_1',
			function ( $value ) {
				return $value . '_v1';
			}
		);

		add_filter(
			'old_hook_2',
			function ( $value ) {
				return $value . '_v2';
			}
		);

		$result = apply_filters( 'new_replacement_hook', 'original' );

		$this->assertSame( 'original_v1_v2', $result, 'Both deprecated hooks should be applied' );
	}

	/**
	 * Test deprecated hooks pass arguments correctly.
	 */
	public function test_deprecated_hook_passes_arguments() {
		acf_add_deprecated_filter( 'old_deprecated_hook', '5.0.0', 'new_replacement_hook' );

		add_filter(
			'old_deprecated_hook',
			function ( $value, $arg1, $arg2 ) {
				return $value . '_' . $arg1 . '_' . $arg2;
			},
			10,
			3
		);

		$result = apply_filters( 'new_replacement_hook', 'original', 'first', 'second' );

		$this->assertSame( 'original_first_second', $result, 'Arguments should be passed to deprecated hook' );
	}

	/**
	 * Data provider for filter variation keys.
	 *
	 * @return array
	 */
	public function data_provider_variation_keys() {
		return array(
			'type key'     => array( 'type', 'text', 'test_filter/type=text' ),
			'name key'     => array( 'name', 'my_field', 'test_filter/name=my_field' ),
			'key key'      => array( 'key', 'field_123', 'test_filter/key=field_123' ),
			'empty string' => array( 'type', '', 'test_filter/type=' ),
			'numeric'      => array( 'type', '123', 'test_filter/type=123' ),
		);
	}

	/**
	 * Test variation hook names are constructed correctly.
	 *
	 * @dataProvider data_provider_variation_keys
	 * @param string $variation_key The variation key.
	 * @param string $value         The value for the key.
	 * @param string $expected_hook The expected hook name.
	 */
	public function test_variation_hook_name_construction( $variation_key, $value, $expected_hook ) {
		// Index 1 because we pass: apply_filters($hook, $passthrough_value, $field).
		acf_add_filter_variations( 'test_filter', array( $variation_key ), 1 );

		$hook_called = false;
		add_filter(
			$expected_hook,
			function ( $val ) use ( &$hook_called ) {
				$hook_called = true;
				return $val;
			}
		);

		$field = array( $variation_key => $value );
		apply_filters( 'test_filter', 'original', $field );

		$this->assertTrue( $hook_called, "Hook '$expected_hook' should be called" );
	}
}
