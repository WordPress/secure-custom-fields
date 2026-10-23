<?php
/**
 * Tests for the SCF inline-token (Bit) Registry.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;
use SCF\Bits\Registry;

/**
 * Tests for the SCF inline-token (Bit) Registry.
 *
 * @covers \SCF\Bits\Registry
 */
class Test_Bits_Registry extends BaseTestCase {

	/**
	 * Reset the singleton between tests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$reflection = new ReflectionClass( Registry::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		$bits = $reflection->getProperty( 'bits' );
		$bits->setAccessible( true );
		$bits->setValue( Registry::instance(), array() );

		$booted = $reflection->getProperty( 'booted' );
		$booted->setAccessible( true );
		$booted->setValue( Registry::instance(), false );

		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
	}

	/**
	 * Verifies `boot()` registers the built-in `scf/field` bit.
	 *
	 * @return void
	 */
	public function test_boot_registers_default_bit() {
		$registry = Registry::instance();
		$registry->boot();

		$built_in = $registry->get( 'scf/field' );
		$this->assertNotNull( $built_in );
		$this->assertSame( 'Custom Field', $built_in['label'] );
		$this->assertSame( array(), $built_in['allowed_block_types'] );
	}

	/**
	 * Confirms repeated `boot()` calls stay idempotent.
	 *
	 * @return void
	 */
	public function test_boot_is_idempotent() {
		$registry = Registry::instance();
		$registry->boot();
		$registry->boot();
		$registry->boot();

		$this->assertNotNull( $registry->get( 'scf/field' ) );
	}

	/**
	 * Confirms `register()` rejects empty / whitespace-only names.
	 *
	 * @return void
	 */
	public function test_register_rejects_empty_name() {
		$registry = Registry::instance();

		$this->assertFalse( $registry->register( '' ) );
		$this->assertFalse( $registry->register( '   ' ) );
	}

	/**
	 * Confirms `register()` rejects names already present in the registry.
	 *
	 * @return void
	 */
	public function test_register_rejects_duplicate_name() {
		$registry = Registry::instance();
		$registry->boot();

		$this->assertFalse( $registry->register( 'scf/field' ) );
	}

	/**
	 * Confirms `register()` rejects non-callable `render_callback` args.
	 *
	 * @return void
	 */
	public function test_register_rejects_non_callable_render_callback() {
		$registry = Registry::instance();

		$this->assertFalse(
			$registry->register(
				'scf/test-bad-cb',
				array(
					'render_callback' => 'not_a_real_callable_xyz',
				)
			)
		);
	}

	/**
	 * Round-trips `register()` + `unregister()` for an arbitrary bit.
	 *
	 * @return void
	 */
	public function test_register_unregister_roundtrip() {
		$registry = Registry::instance();
		$registry->register(
			'scf/test',
			array(
				'label' => 'Test',
			)
		);

		$this->assertNotNull( $registry->get( 'scf/test' ) );
		$this->assertTrue( $registry->unregister( 'scf/test' ) );
		$this->assertNull( $registry->get( 'scf/test' ) );
		$this->assertFalse( $registry->unregister( 'scf/test' ) );
	}

	/**
	 * Confirms `get_for_block_type()` honours `allowed_block_types`.
	 *
	 * @return void
	 */
	public function test_get_for_block_type_filters_by_allowed() {
		$registry = Registry::instance();
		$registry->boot();

		// Default bit allows every block type.
		$this->assertContains( 'scf/field', $registry->get_for_block_type( 'core/paragraph' ) );

		$registry->register(
			'scf/restricted',
			array(
				'allowed_block_types' => array( 'core/heading' ),
			)
		);

		$this->assertContains( 'scf/restricted', $registry->get_for_block_type( 'core/heading' ) );
		$this->assertNotContains( 'scf/restricted', $registry->get_for_block_type( 'core/paragraph' ) );
	}
}
