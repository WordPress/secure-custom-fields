<?php
/**
 * Test Secure Custom Fields experiments functionality.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;


/**
 * Tests for the SCF_Admin_Experiments class.
 */
class SCF_Admin_Experiments_Test extends BaseTestCase {

	/**
	 * The experiments instance.
	 *
	 * @var SCF_Admin_Experiments
	 */
	protected $experiments;

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		acf_include( 'includes/admin/admin-experiments.php' );
		acf_include( 'includes/admin/experiments/class-scf-admin-experiment.php' );
		acf_include( 'includes/admin/experiments/class-scf-admin-experiment-editor-sidebar.php' );

		$this->experiments = new SCF_Admin_Experiments();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		delete_option( 'scf_experiment_editor-sidebar_enabled' );
		parent::tear_down();
	}

	/**
	 * Test experiment registration.
	 */
	public function test_register_experiment() {
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );
		$experiments = $this->experiments->get_experiments();
		$this->assertArrayHasKey( 'editor-sidebar', $experiments );
		$this->assertInstanceOf( 'SCF_Admin_Experiment_Editor_Sidebar', $experiments['editor-sidebar'] );
	}

	/**
	 * Test experiment initialization.
	 */
	public function test_experiment_initialization() {
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		$experiment = $this->experiments->get_experiment( 'editor-sidebar' );

		$this->assertEquals( 'editor-sidebar', $experiment->name );
		$this->assertEquals( 'Move Elements to Editor Sidebar', $experiment->title );
		$this->assertNotEmpty( $experiment->description );
	}

	/**
	 * Test experiment enabling and disabling.
	 */
	public function test_experiment_enable_disable() {
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		$experiment = $this->experiments->get_experiment( 'editor-sidebar' );

		$this->assertFalse( $experiment->is_enabled() );

		$experiment->set_enabled( true );
		$this->assertTrue( $experiment->is_enabled() );
		$this->assertTrue( get_option( 'scf_experiment_editor-sidebar_enabled' ) );

		$experiment->set_enabled( false );
		$this->assertFalse( $experiment->is_enabled() );
		$this->assertFalse( get_option( 'scf_experiment_editor-sidebar_enabled' ) );
	}

	/**
	 * Test experiment admin menu integration.
	 */
	public function test_experiment_admin_menu() {
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		// Use a reflection to check if the admin_menu method exists.
		$reflection = new ReflectionClass( $this->experiments );
		$this->assertTrue( $reflection->hasMethod( 'admin_menu' ), 'Admin menu method should exist' );

		// In a real WordPress environment, the admin_menu method would be hooked to the admin_menu action
		// For testing purposes, we'll just verify the method exists and is callable
		$this->assertTrue( is_callable( array( $this->experiments, 'admin_menu' ) ) );
	}

	/**
	 * Test experiment form submission.
	 */
	public function test_experiment_form_submission() {

		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		$experiment = $this->experiments->get_experiment( 'editor-sidebar' );

		$this->assertFalse( $experiment->is_enabled() );

		$_POST['scf_experiments_nonce'] = wp_create_nonce( 'scf_experiments_update' );
		$_POST['scf_experiments']       = array( 'editor-sidebar' => '1' );

		$this->experiments->check_submit();

		$this->assertTrue( $experiment->is_enabled() );
		$this->assertTrue( get_option( 'scf_experiment_editor-sidebar_enabled' ) );
	}

	/**
	 * Test experiment cleanup.
	 */
	public function test_experiment_cleanup() {
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		$experiment = $this->experiments->get_experiment( 'editor-sidebar' );

		$experiment->set_enabled( true );
		$this->assertTrue( get_option( 'scf_experiment_editor-sidebar_enabled' ) );

		$experiment->cleanup();

		$this->assertFalse( get_option( 'scf_experiment_editor-sidebar_enabled' ) );
	}

	/**
	 * Test experiment nonce verification.
	 */
	public function test_experiment_nonce_verification() {
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		$experiment = $this->experiments->get_experiment( 'editor-sidebar' );

		$this->assertFalse( $experiment->is_enabled() );

		$_POST['scf_experiments_nonce'] = 'invalid_nonce';
		$_POST['scf_experiments']       = array( 'editor-sidebar' => '1' );

		$this->experiments->check_submit();

		$this->assertFalse( $experiment->is_enabled() );
		$this->assertFalse( get_option( 'scf_experiment_editor-sidebar_enabled' ) );
	}
}
