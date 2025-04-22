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

		// Ensure admin files are loaded
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
		// Register our test experiment through the experiments instance
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		// Get all registered experiments
		$experiments = $this->experiments->get_experiments();

		// Check if our experiment is registered
		$this->assertArrayHasKey( 'editor-sidebar', $experiments );
		$this->assertInstanceOf( 'SCF_Admin_Experiment_Editor_Sidebar', $experiments['editor-sidebar'] );
	}

	/**
	 * Test experiment initialization.
	 */
	public function test_experiment_initialization() {
		// Register our test experiment
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		// Get the experiment instance
		$experiment = $this->experiments->get_experiment( 'editor-sidebar' );

		// Check that the experiment was properly initialized
		$this->assertEquals( 'editor-sidebar', $experiment->name );
		$this->assertEquals( 'Move Elements to Editor Sidebar', $experiment->title );
		$this->assertNotEmpty( $experiment->description );
	}

	/**
	 * Test experiment enabling and disabling.
	 */
	public function test_experiment_enable_disable() {
		// Register our test experiment
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		// Get the experiment instance
		$experiment = $this->experiments->get_experiment( 'editor-sidebar' );

		// Initially disabled
		$this->assertFalse( $experiment->is_enabled() );

		// Enable the experiment
		$experiment->set_enabled( true );
		$this->assertTrue( $experiment->is_enabled() );
		$this->assertTrue( get_option( 'scf_experiment_editor-sidebar_enabled' ) );

		// Disable the experiment
		$experiment->set_enabled( false );
		$this->assertFalse( $experiment->is_enabled() );
		$this->assertFalse( get_option( 'scf_experiment_editor-sidebar_enabled' ) );
	}

	/**
	 * Test experiment admin menu integration.
	 */
	public function test_experiment_admin_menu() {
		// Register our test experiment
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		// Use a reflection to check if the admin_menu method exists
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
		// Register our test experiment
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		// Get the experiment instance
		$experiment = $this->experiments->get_experiment( 'editor-sidebar' );

		// Initially disabled
		$this->assertFalse( $experiment->is_enabled() );

		// Simulate form submission with valid nonce
		$_POST['scf_experiments_nonce'] = wp_create_nonce( 'scf_experiments_update' );
		$_POST['scf_experiments']       = array( 'editor-sidebar' => '1' );

		// Process the form submission
		$this->experiments->check_submit();

		// Verify experiment is enabled
		$this->assertTrue( $experiment->is_enabled() );
		$this->assertTrue( get_option( 'scf_experiment_editor-sidebar_enabled' ) );
	}

	/**
	 * Test experiment cleanup.
	 */
	public function test_experiment_cleanup() {
		// Register our test experiment
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		// Get the experiment instance
		$experiment = $this->experiments->get_experiment( 'editor-sidebar' );

		// Enable the experiment
		$experiment->set_enabled( true );
		$this->assertTrue( get_option( 'scf_experiment_editor-sidebar_enabled' ) );

		// Clean up the experiment
		$experiment->cleanup();

		// Verify option is removed
		$this->assertFalse( get_option( 'scf_experiment_editor-sidebar_enabled' ) );
	}

	/**
	 * Test experiment nonce verification.
	 */
	public function test_experiment_nonce_verification() {
		// Register our test experiment
		$this->experiments->register_experiment( 'SCF_Admin_Experiment_Editor_Sidebar' );

		// Get the experiment instance
		$experiment = $this->experiments->get_experiment( 'editor-sidebar' );

		// Initially disabled
		$this->assertFalse( $experiment->is_enabled() );

		// Simulate form submission with invalid nonce
		$_POST['scf_experiments_nonce'] = 'invalid_nonce';
		$_POST['scf_experiments']       = array( 'editor-sidebar' => '1' );

		// Process the form submission
		$this->experiments->check_submit();

		// Verify experiment remains disabled
		$this->assertFalse( $experiment->is_enabled() );
		$this->assertFalse( get_option( 'scf_experiment_editor-sidebar_enabled' ) );
	}
}
