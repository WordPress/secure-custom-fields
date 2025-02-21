<?php
/**
 * Test Legacy_Hooks functionality
 *
 * @package wordpress/secure-custom-fields
 */

namespace WordPress\SCF\Tests;

use WordPress\SCF\Legacy\Hooks;
use WorDBless\BaseTestCase;

/**
 * Test Legacy_Hooks class
 */
class Test_Legacy_Hooks extends BaseTestCase {

	/**
	 * Instance of Legacy_Hooks
	 *
	 * @var Hooks
	 */
	private $legacy_hooks;

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->legacy_hooks = new Hooks();
	}

	/**
	 * Test that legacy filters are called when new filters are applied
	 */
	public function test_legacy_filter_called() {
		$test_value = 'test';
		$modified   = 'modified';

		add_filter(
			'acf/blocks/binding_value',
			function ( $value ) use ( $modified ) {
				return $value . '_' . $modified;
			}
		);

		$result = apply_filters( 'scf_blocks_binding_value', $test_value );
		$this->assertEquals( $test_value . '_' . $modified, $result );
	}

	/**
	 * Test that legacy actions are called when new actions are triggered
	 */
	public function test_legacy_action_called() {
		$action_called = false;

		add_action(
			'acf/init',
			function () use ( &$action_called ) {
				$action_called = true;
			}
		);

		do_action( 'scf_init' );
		$this->assertTrue( $action_called );
	}

	/**
	 * Test that filter arguments are passed correctly
	 */
	public function test_filter_arguments_passed() {
		$test_value     = 'test';
		$source_attrs   = array( 'foo' => 'bar' );
		$block_instance = array( 'name' => 'test-block' );
		$attribute_name = 'test-attr';

		add_filter(
			'acf/blocks/binding_value',
			function ( $value, $attrs, $block, $attr ) use ( $source_attrs, $block_instance, $attribute_name ) {
				$this->assertEquals( $source_attrs, $attrs );
				$this->assertEquals( $block_instance, $block );
				$this->assertEquals( $attribute_name, $attr );
				return $value;
			},
			10,
			4
		);

		apply_filters( 'scf_blocks_binding_value', $test_value, $source_attrs, $block_instance, $attribute_name );
	}

	/**
	 * Test that action arguments are passed correctly
	 */
	public function test_action_arguments_passed() {
		$version = \ACF_MAJOR_VERSION;

		add_action(
			'acf/init',
			function ( $major_version ) use ( $version ) {
				$this->assertEquals( $version, $major_version );
			},
			10,
			1
		);

		do_action( 'scf_init', $version );
	}

	/**
	 * Test that legacy hooks can be disabled
	 */
	public function test_legacy_hooks_can_be_disabled() {
		add_filter( 'scf_enable_legacy_hooks', '__return_false' );

		$this->legacy_hooks = new Hooks(); // Reinitialize with filter active

		$action_called = false;
		add_action(
			'acf/bindings/test_action',
			function () use ( &$action_called ) {
				$action_called = true;
			}
		);

		do_action( 'scf_bindings_test_action' );
		$this->assertFalse( $action_called );
	}
}
