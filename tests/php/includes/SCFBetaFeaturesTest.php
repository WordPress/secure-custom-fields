<?php
/**
 * Tests for the beta feature runtime bridge.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for includes/beta-features.php.
 */
class SCFBetaFeaturesTest extends BaseTestCase {

	/**
	 * Beta feature settings to exercise.
	 *
	 * @var array
	 */
	private $settings = array(
		'enable_datastore',
		'enable_acf_ai',
		'enable_schema',
	);

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		acf_init();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		foreach ( $this->settings as $name ) {
			delete_option( 'scf_beta_feature_' . $name . '_enabled' );
		}

		parent::tear_down();
	}

	/**
	 * Test the runtime flags default to disabled.
	 */
	public function test_runtime_filters_registered_by_plugin_bootstrap() {
		$this->assertNotFalse( has_filter( 'acf/settings/enable_datastore' ) );
		$this->assertNotFalse( has_filter( 'acf/settings/enable_acf_ai' ) );
		$this->assertNotFalse( has_filter( 'acf/settings/enable_schema' ) );
	}

	/**
	 * Test the runtime flags default to disabled.
	 */
	public function test_runtime_flags_default_disabled() {
		$this->assertFalse( acf_get_setting( 'enable_acf_ai' ) );
		$this->assertFalse( acf_get_setting( 'enable_schema' ) );
		$this->assertFalse( acf_is_using_datastore() );
	}

	/**
	 * Test an enabled beta feature enables its runtime setting.
	 */
	public function test_enabled_beta_feature_enables_runtime_setting() {
		update_option( 'scf_beta_feature_enable_acf_ai_enabled', true );
		update_option( 'scf_beta_feature_enable_schema_enabled', true );
		update_option( 'scf_beta_feature_enable_datastore_enabled', true );

		$this->assertTrue( acf_get_setting( 'enable_acf_ai' ) );
		$this->assertTrue( acf_get_setting( 'enable_schema' ) );
		$this->assertTrue( acf_is_using_datastore() );
	}

	/**
	 * Test an existing filter can enable a setting regardless of the beta option.
	 */
	public function test_existing_filter_can_enable_setting() {
		add_filter( 'acf/settings/enable_schema', '__return_true' );

		try {
			$this->assertTrue( acf_get_setting( 'enable_schema' ) );
		} finally {
			remove_filter( 'acf/settings/enable_schema', '__return_true' );
		}
	}

	/**
	 * Test a later filter can override an enabled beta feature.
	 */
	public function test_beta_feature_setting_coerces_string_values() {
		foreach ( $this->settings as $name ) {
			update_option( 'scf_beta_feature_' . $name . '_enabled', '1' );
			$this->assertTrue( apply_filters( 'acf/settings/' . $name, false ) );

			update_option( 'scf_beta_feature_' . $name . '_enabled', '0' );
			$this->assertFalse( apply_filters( 'acf/settings/' . $name, false ) );
		}
	}

	/**
	 * Test a later filter can override an enabled beta feature.
	 */
	public function test_later_filter_can_override_beta_feature() {
		update_option( 'scf_beta_feature_enable_acf_ai_enabled', true );

		add_filter(
			'acf/settings/enable_acf_ai',
			static function () {
				return false;
			},
			20
		);

		$this->assertFalse( acf_get_setting( 'enable_acf_ai' ) );
	}

	/**
	 * Test uninstall cleanup removes all beta feature options.
	 */
	public function test_uninstall_cleanup_removes_beta_feature_options() {
		foreach ( array_merge( $this->settings, array( 'editor_sidebar' ) ) as $name ) {
			update_option( 'scf_beta_feature_' . $name . '_enabled', true );
		}

		scf_plugin_uninstall();

		foreach ( array_merge( $this->settings, array( 'editor_sidebar' ) ) as $name ) {
			$this->assertFalse( get_option( 'scf_beta_feature_' . $name . '_enabled' ) );
		}
	}
}
