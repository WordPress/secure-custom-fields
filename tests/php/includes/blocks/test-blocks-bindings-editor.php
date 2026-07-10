<?php
/**
 * Tests for includes/Blocks/Bindings_Editor.php.
 *
 * Covers the hook registration performed by the constructor and the
 * gating of the editor bindings script enqueue on the
 * enable_block_bindings setting and the datastore.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;
use SCF\Blocks\Bindings_Editor;

/**
 * Test the block bindings editor integration.
 */
class Test_Blocks_Bindings_Editor extends BaseTestCase {

	/**
	 * Original enable_block_bindings setting.
	 *
	 * @var bool
	 */
	private $original_setting;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		acf_init();

		$this->original_setting = acf_get_setting( 'enable_block_bindings' );

		// In production the handle is registered by the assets layer on
		// admin screens. WP_Dependencies::enqueue() silently ignores
		// unregistered handles, so register a stub for the tests.
		if ( ! wp_script_is( 'acf-field-bindings', 'registered' ) ) {
			wp_register_script( 'acf-field-bindings', 'https://example.com/acf-field-bindings.js', array(), '1.0', true );
		}
	}

	/**
	 * Clean up test state.
	 */
	public function tear_down() {
		acf_update_setting( 'enable_block_bindings', $this->original_setting );

		// The script registry and queue are global state shared between tests.
		wp_dequeue_script( 'acf-field-bindings' );
		wp_deregister_script( 'acf-field-bindings' );

		parent::tear_down();
	}

	/**
	 * Test that the constructor hooks the editor asset enqueue on WP 6.7+.
	 */
	public function test_constructor_registers_enqueue_hook() {
		$editor = new Bindings_Editor();

		$this->assertNotFalse(
			has_action( 'enqueue_block_editor_assets', array( $editor, 'enqueue_block_editor_assets' ) )
		);
	}

	/**
	 * Test that the script is not enqueued when the datastore is disabled.
	 */
	public function test_no_enqueue_without_datastore() {
		// The datastore defaults to disabled.
		$this->assertFalse( acf_is_using_datastore() );

		$editor = new Bindings_Editor();
		$editor->enqueue_block_editor_assets();

		$this->assertFalse( wp_script_is( 'acf-field-bindings', 'enqueued' ) );
	}

	/**
	 * Test that the script is not enqueued when block bindings are disabled.
	 */
	public function test_no_enqueue_when_bindings_disabled() {
		add_filter( 'acf/settings/enable_datastore', '__return_true' );
		acf_update_setting( 'enable_block_bindings', false );

		$editor = new Bindings_Editor();
		$editor->enqueue_block_editor_assets();

		$this->assertFalse( wp_script_is( 'acf-field-bindings', 'enqueued' ) );
	}

	/**
	 * Test that the script is enqueued when bindings and datastore are enabled.
	 */
	public function test_enqueues_script_with_bindings_and_datastore() {
		add_filter( 'acf/settings/enable_datastore', '__return_true' );
		acf_update_setting( 'enable_block_bindings', true );

		$this->assertTrue( acf_is_using_datastore() );

		$editor = new Bindings_Editor();
		$editor->enqueue_block_editor_assets();

		$this->assertTrue( wp_script_is( 'acf-field-bindings', 'enqueued' ) );
	}
}
