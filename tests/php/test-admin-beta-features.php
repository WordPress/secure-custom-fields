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

		$this->beta_features = new SCF_Admin_Beta_Features();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		foreach ( array( 'editor_sidebar', 'enable_datastore', 'enable_acf_ai', 'enable_schema' ) as $name ) {
			delete_option( 'scf_beta_feature_' . $name . '_enabled' );
		}

		unset( $_POST['scf_beta_features_nonce'], $_POST['scf_beta_features'] );
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
	 * Test default beta feature registration.
	 */
	public function test_register_default_beta_features() {
		$beta_features = $this->beta_features->get_beta_features();

		$this->assertCount( 4, $beta_features );
		$this->assertArrayHasKey( 'editor_sidebar', $beta_features );
		$this->assertArrayHasKey( 'enable_datastore', $beta_features );
		$this->assertArrayHasKey( 'enable_acf_ai', $beta_features );
		$this->assertArrayHasKey( 'enable_schema', $beta_features );

		$expected_classes = array(
			'editor_sidebar'   => 'SCF_Admin_Beta_Feature_Editor_Sidebar',
			'enable_datastore' => 'SCF_Admin_Beta_Feature_Datastore',
			'enable_acf_ai'    => 'SCF_Admin_Beta_Feature_ACF_AI',
			'enable_schema'    => 'SCF_Admin_Beta_Feature_Schema',
		);

		foreach ( $expected_classes as $name => $class_name ) {
			$this->assertInstanceOf( $class_name, $beta_features[ $name ] );
			$this->assertSame( $name, $beta_features[ $name ]->name );
		}

		$this->assertNotEmpty( $beta_features['enable_datastore']->title );
		$this->assertNotEmpty( $beta_features['enable_acf_ai']->description );
		$this->assertNotEmpty( $beta_features['enable_schema']->description );
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

		$beta_feature = $this->beta_features->get_beta_feature( 'editor_sidebar' );

		$this->assertFalse( $beta_feature->is_enabled() );

		$_POST['scf_beta_features_nonce'] = wp_create_nonce( 'scf_beta_features_update' );
		$_POST['scf_beta_features']       = array( 'editor_sidebar' => '1' );

		$this->beta_features->check_submit();

		$this->assertTrue( $beta_feature->is_enabled() );
		$this->assertTrue( get_option( 'scf_beta_feature_editor_sidebar_enabled' ) );
	}

	/**
	 * Test beta feature settings enable runtime flags.
	 */
	public function test_beta_feature_settings_enable_runtime_flags() {
		foreach ( array( 'enable_datastore', 'enable_acf_ai', 'enable_schema' ) as $name ) {
			$this->assertFalse( apply_filters( 'acf/settings/' . $name, false ) );
			update_option( 'scf_beta_feature_' . $name . '_enabled', true );
			$this->assertTrue( apply_filters( 'acf/settings/' . $name, false ) );
		}
	}

	/**
	 * Test submitting beta feature settings updates every registered feature.
	 */
	public function test_beta_feature_form_submission_updates_all_features() {
		$beta_features = $this->beta_features->get_beta_features();
		$all_names     = array_keys( $beta_features );

		$_POST['scf_beta_features_nonce'] = wp_create_nonce( 'scf_beta_features_update' );
		$_POST['scf_beta_features']       = array_fill_keys( $all_names, '1' );

		$this->beta_features->check_submit();

		foreach ( $all_names as $name ) {
			$this->assertTrue( $beta_features[ $name ]->is_enabled() );
		}

		$_POST['scf_beta_features_nonce'] = wp_create_nonce( 'scf_beta_features_update' );
		$_POST['scf_beta_features']       = array( 'editor_sidebar' => '1' );

		$this->beta_features->check_submit();

		foreach ( $all_names as $name ) {
			$expected = 'editor_sidebar' === $name;
			$this->assertSame( $expected, $beta_features[ $name ]->is_enabled() );
		}
	}

	/**
	 * Test existing filters can enable runtime flags.
	 */
	public function test_existing_filters_can_enable_runtime_flags() {
		foreach ( array( 'enable_datastore', 'enable_acf_ai', 'enable_schema' ) as $name ) {
			add_filter( 'acf/settings/' . $name, '__return_true' );

			try {
				$this->assertTrue( apply_filters( 'acf/settings/' . $name, false ) );
			} finally {
				remove_filter( 'acf/settings/' . $name, '__return_true' );
			}
		}
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

	/**
	 * Test beta feature nonce verification.
	 */
	public function test_beta_feature_nonce_verification() {
		$this->beta_features->register_beta_feature( 'SCF_Admin_Beta_Feature_Editor_Sidebar' );

		$beta_feature = $this->beta_features->get_beta_feature( 'editor_sidebar' );

		$this->assertFalse( $beta_feature->is_enabled() );

		$_POST['scf_beta_features_nonce'] = 'invalid_nonce';
		$_POST['scf_beta_features']       = array( 'editor_sidebar' => '1' );

		$this->beta_features->check_submit();

		$this->assertFalse( $beta_feature->is_enabled() );
		$this->assertFalse( get_option( 'scf_beta_feature_editor_sidebar_enabled' ) );
	}
}
