<?php
/**
 * Test the tools redesign beta feature.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

require_once __DIR__ . '/../../includes/admin/admin-tools.php';
require_once __DIR__ . '/../../includes/admin/beta-features.php';
require_once __DIR__ . '/../../includes/admin/beta-features/class-scf-beta-feature.php';
require_once __DIR__ . '/../../includes/admin/beta-features/class-scf-beta-feature-tools-redesign.php';

/**
 * Tests for the tools redesign beta feature integration.
 */
class SCF_Tools_Redesign_Test extends BaseTestCase {

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

		$this->beta_features       = new SCF_Admin_Beta_Features();
		acf()->admin_beta_features = $this->beta_features;

		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Tools_Redesign' );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		delete_option( 'scf_beta_feature_tools_redesign_enabled' );
		unset( $GLOBALS['current_screen'] );
		parent::tear_down();
	}

	/**
	 * Test that the beta feature registers with the expected properties.
	 */
	public function test_beta_feature_registered() {
		$feature = $this->beta_features->get_beta_feature( 'tools_redesign' );

		$this->assertNotNull( $feature );
		$this->assertEquals( 'tools_redesign', $feature->name );
		$this->assertNotEmpty( $feature->title );
		$this->assertNotEmpty( $feature->description );
	}

	/**
	 * Test that the tools screen gates on the beta feature option.
	 */
	public function test_is_tools_redesign_enabled() {
		$tools = acf()->admin_tools;

		$this->assertFalse( $tools->is_tools_redesign_enabled() );

		$feature = $this->beta_features->get_beta_feature( 'tools_redesign' );
		$feature->set_enabled( true );

		$this->assertTrue( $tools->is_tools_redesign_enabled() );
	}

	/**
	 * Test that the export tool exposes generated PHP as a string.
	 */
	public function test_get_php_export_code_returns_string() {
		$tools = acf()->admin_tools;
		$tools->include_tools();

		$tool = $tools->get_tool( 'export' );
		$this->assertInstanceOf( 'ACF_Admin_Tool_Export', $tool );

		// No selection produces an empty string, not output.
		$this->assertSame( '', $tool->get_php_export_code() );
	}
}
