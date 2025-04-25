<?php
/**
 * Test Secure Custom Fields beta features functionality.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;


/**
 * Tests for the SCF_Admin_Beta_Features class.
 */
class SCF_Admin_Beta_Features_Test extends BaseTestCase {

	/**
	 * The beta features instance.
	 *
	 * @var SCF_Admin_Beta_Features
	 */
	protected $beta_features;

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		acf_include( 'includes/admin/beta-features.php' );
		acf_include( 'includes/admin/beta-features/class-scf-beta-feature.php' );
		acf_include( 'includes/admin/beta-features/class-scf-beta-feature-editor-sidebar.php' );

		$this->beta_features = new SCF_Admin_Beta_Features();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		delete_option( 'scf_beta_feature_editor-sidebar_enabled' );
		parent::tear_down();
	}

	/**
	 * Test beta feature registration.
	 */
	public function test_register_beta_feature() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );
		$beta_features = $this->beta_features->get_beta_features();
		$this->assertArrayHasKey( 'editor-sidebar', $beta_features );
		$this->assertInstanceOf( 'SCF_Admin_Beta_Feature_Editor_Sidebar', $beta_features['editor-sidebar'] );
	}

	/**
	 * Test beta feature initialization.
	 */
	public function test_beta_feature_initialization() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );

		$beta_feature = $this->beta_features->get_beta_feature( 'editor-sidebar' );

		$this->assertEquals( 'editor-sidebar', $beta_feature->name );
		$this->assertEquals( 'Move Elements to Editor Sidebar', $beta_feature->title );
		$this->assertNotEmpty( $beta_feature->description );
	}

	/**
	 * Test beta feature enabling and disabling.
	 */
	public function test_beta_feature_enable_disable() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );

		$beta_feature = $this->beta_features->get_beta_feature( 'editor-sidebar' );

		$this->assertFalse( $beta_feature->is_enabled() );

		$beta_feature->set_enabled( true );
		$this->assertTrue( $beta_feature->is_enabled() );
		$this->assertTrue( get_option( 'scf_beta_feature_editor-sidebar_enabled' ) );

		$beta_feature->set_enabled( false );
		$this->assertFalse( $beta_feature->is_enabled() );
		$this->assertFalse( get_option( 'scf_beta_feature_editor-sidebar_enabled' ) );
	}

	/**
	 * Test beta feature admin menu integration.
	 */
	public function test_beta_feature_admin_menu() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );

		// Use a reflection to check if the admin_menu method exists.
		$reflection = new ReflectionClass( $this->beta_features );
		$this->assertTrue( $reflection->hasMethod( 'admin_menu' ), 'Admin menu method should exist' );

		// In a real WordPress environment, the admin_menu method would be hooked to the admin_menu action
		// For testing purposes, we'll just verify the method exists and is callable
		$this->assertTrue( is_callable( array( $this->beta_features, 'admin_menu' ) ) );
	}

	/**
	 * Test beta feature form submission.
	 */
	public function test_beta_feature_form_submission() {

		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );

		$beta_feature = $this->beta_features->get_beta_feature( 'editor-sidebar' );

		$this->assertFalse( $beta_feature->is_enabled() );

		$_POST['scf_beta_features_nonce'] = wp_create_nonce( 'scf_beta_features_update' );
		$_POST['scf_beta_features']       = array( 'editor-sidebar' => '1' );

		$this->beta_features->check_submit();

		$this->assertTrue( $beta_feature->is_enabled() );
		$this->assertTrue( get_option( 'scf_beta_feature_editor-sidebar_enabled' ) );
	}

	/**
	 * Test beta feature cleanup.
	 */
	public function test_beta_feature_cleanup() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );

		$beta_feature = $this->beta_features->get_beta_feature( 'editor-sidebar' );

		$beta_feature->set_enabled( true );
		$this->assertTrue( get_option( 'scf_beta_feature_editor-sidebar_enabled' ) );

		$beta_feature->cleanup();

		$this->assertFalse( get_option( 'scf_beta_feature_editor-sidebar_enabled' ) );
	}

	/**
	 * Test beta feature nonce verification.
	 */
	public function test_beta_feature_nonce_verification() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );

		$beta_feature = $this->beta_features->get_beta_feature( 'editor-sidebar' );

		$this->assertFalse( $beta_feature->is_enabled() );

		$_POST['scf_beta_features_nonce'] = 'invalid_nonce';
		$_POST['scf_beta_features']       = array( 'editor-sidebar' => '1' );

		$this->beta_features->check_submit();

		$this->assertFalse( $beta_feature->is_enabled() );
		$this->assertFalse( get_option( 'scf_beta_feature_editor-sidebar_enabled' ) );
	}
}
