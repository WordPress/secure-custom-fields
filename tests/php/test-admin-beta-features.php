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
		delete_option( 'scf_beta_feature_editor_sidebar_enabled' );
		parent::tear_down();
	}

	/**
	 * Test beta feature registration.
	 */
	public function test_register_beta_feature() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );
		$beta_features = $this->beta_features->get_beta_features();
		$this->assertArrayHasKey( 'editor_sidebar', $beta_features );
		$this->assertInstanceOf( 'SCF_Admin_Beta_Feature_Editor_Sidebar', $beta_features['editor_sidebar'] );
	}

	/**
	 * Test beta feature initialization.
	 */
	public function test_beta_feature_initialization() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );

		$beta_feature = $this->beta_features->get_beta_feature( 'editor_sidebar' );

		$this->assertEquals( 'editor_sidebar', $beta_feature->name );
		$this->assertEquals( 'Move Elements to Editor Sidebar', $beta_feature->title );
		$this->assertNotEmpty( $beta_feature->description );
	}

	/**
	 * Test beta feature enabling and disabling.
	 */
	public function test_beta_feature_enable_disable() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );

		$beta_feature = $this->beta_features->get_beta_feature( 'editor_sidebar' );

		$this->assertFalse( $beta_feature->is_enabled() );

		$beta_feature->set_enabled( true );
		$this->assertTrue( $beta_feature->is_enabled() );
		$this->assertTrue( get_option( 'scf_beta_feature_editor_sidebar_enabled' ) );

		$beta_feature->set_enabled( false );
		$this->assertFalse( $beta_feature->is_enabled() );
		$this->assertFalse( get_option( 'scf_beta_feature_editor_sidebar_enabled' ) );
	}

	/**
	 * The Beta Features admin page is gone, but beta features still reach the block editor.
	 */
	public function test_beta_features_page_removed_and_script_hooked() {
		$this->assertFalse( has_action( 'admin_menu', array( $this->beta_features, 'admin_menu' ) ) );
		$this->assertFalse( method_exists( $this->beta_features, 'check_submit' ) );
		$this->assertSame( 20, has_action( 'admin_enqueue_scripts', array( $this->beta_features, 'add_beta_features_script' ) ) );
	}

	/**
	 * The beta features URL now points to the Settings page.
	 */
	public function test_beta_features_url_points_to_settings_page() {
		$this->assertStringContainsString( 'page=acf-settings-tab-general', scf_get_admin_beta_features_url() );
	}

	/**
	 * Test beta feature cleanup.
	 */
	public function test_beta_feature_cleanup() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );

		$beta_feature = $this->beta_features->get_beta_feature( 'editor_sidebar' );

		$beta_feature->set_enabled( true );
		$this->assertTrue( get_option( 'scf_beta_feature_editor_sidebar_enabled' ) );

		$beta_feature->cleanup();

		$this->assertFalse( get_option( 'scf_beta_feature_editor_sidebar_enabled' ) );
	}
}
