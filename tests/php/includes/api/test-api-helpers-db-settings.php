<?php
/**
 * Tests for database-backed settings in api-helpers.php.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Covers acf_update_setting( ..., true ), acf_get_setting() reading the database, settings set in code,
 * managed settings, the allowed settings filter and acf_is_using_datastore().
 *
 * @group settings
 */
class Test_API_Helpers_DB_Settings extends BaseTestCase {

	/**
	 * Settings changed by a test, with their original in-memory values.
	 *
	 * @var array
	 */
	private $original_settings = array();

	/**
	 * _doing_it_wrong() calls made during a test.
	 *
	 * @var array
	 */
	private $doing_it_wrong = array();

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( array( 'enable_shortcode', 'rest_api_format', 'google_api_key', 'enable_datastore', 'remove_wp_meta_box', 'scf_test_setting' ) as $name ) {
			$this->original_settings[ $name ] = acf_has_setting( $name ) ? acf_raw_setting( $name ) : null;
		}

		acf_set_data( 'settings_set_in_code', array() );

		$this->doing_it_wrong = array();
		add_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ), 10, 2 );
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		remove_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ) );
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		foreach ( $this->original_settings as $name => $value ) {
			acf()->settings[ $name ] = $value;
			if ( null === $value ) {
				unset( acf()->settings[ $name ] );
			}
			delete_option( 'acf_' . $name );
		}

		acf_set_data( 'settings_set_in_code', array() );

		parent::tear_down();
	}

	/**
	 * Records a _doing_it_wrong() call.
	 *
	 * @param string $function_name The function name.
	 * @param string $message       The message.
	 */
	public function record_doing_it_wrong( $function_name, $message = '' ) {
		$this->doing_it_wrong[] = array( $function_name, $message );
	}

	/**
	 * Allowed settings include SCF's own and the ones ACF PRO adds, which are free in SCF.
	 */
	public function test_allowed_db_settings_include_scf_settings() {
		$allowed = acf_get_allowed_db_settings();

		foreach ( array( 'enable_post_types', 'remove_wp_meta_box', 'enable_options_pages_ui', 'enable_shortcode', 'rest_api_enabled', 'rest_api_format', 'preload_blocks', 'enable_datastore', 'enable_abilities', 'enable_schema', 'google_api_key', 'google_api_client' ) as $name ) {
			$this->assertArrayHasKey( $name, $allowed, $name );
			$this->assertTrue( $allowed[ $name ]['autoload'] );
		}

		$this->assertSame( 'acf_sanitize_rest_api_format', $allowed['rest_api_format']['sanitize_callback'] );
		$this->assertSame( 'sanitize_text_field', $allowed['google_api_key']['sanitize_callback'] );
		$this->assertSame( 'wp_validate_boolean', $allowed['enable_datastore']['sanitize_callback'] );
		$this->assertArrayNotHasKey( 'show_admin', $allowed );
		$this->assertArrayNotHasKey( 'capability', $allowed );
	}

	/**
	 * Saving to the database stores booleans as integers in an "acf_" option and reads them back as booleans.
	 */
	public function test_boolean_setting_saved_to_db_and_restored() {
		$this->assertTrue( acf_update_setting( 'enable_shortcode', true, true ) );
		$this->assertSame( 1, (int) get_option( 'acf_enable_shortcode' ) );
		$this->assertTrue( acf_get_setting( 'enable_shortcode' ) );

		$this->assertTrue( acf_update_setting( 'enable_shortcode', false, true ) );
		$this->assertSame( 0, (int) get_option( 'acf_enable_shortcode' ) );
		$this->assertFalse( acf_get_setting( 'enable_shortcode' ) );

		// The database wins over the in-memory default, and strings from the database become booleans.
		acf()->settings['enable_shortcode'] = true;
		update_option( 'acf_enable_shortcode', '0' );
		$this->assertFalse( acf_get_setting( 'enable_shortcode' ) );
		update_option( 'acf_enable_shortcode', '1' );
		$this->assertTrue( acf_get_setting( 'enable_shortcode' ) );
	}

	/**
	 * Saving the same value twice is not reported as a failure.
	 */
	public function test_saving_unchanged_value_succeeds() {
		acf_update_setting( 'enable_shortcode', true, true );
		$this->assertNotInstanceOf( WP_Error::class, acf_update_setting( 'enable_shortcode', true, true ) );
		acf_update_setting( 'enable_shortcode', false, true );
		$this->assertNotInstanceOf( WP_Error::class, acf_update_setting( 'enable_shortcode', false, true ) );
	}

	/**
	 * Values are sanitized before they're saved, and kept in memory as saved.
	 */
	public function test_values_are_sanitized_on_save() {
		acf_update_setting( 'rest_api_format', 'evil', true );
		$this->assertSame( 'light', get_option( 'acf_rest_api_format' ) );
		$this->assertSame( 'light', acf_get_setting( 'rest_api_format' ) );

		acf_update_setting( 'rest_api_format', 'standard', true );
		$this->assertSame( 'standard', acf_get_setting( 'rest_api_format' ) );

		acf_update_setting( 'google_api_key', '<b>key</b>', true );
		$this->assertSame( 'key', get_option( 'acf_google_api_key' ) );
		$this->assertSame( 'key', acf_raw_setting( 'google_api_key' ) );
	}

	/**
	 * Saving a setting that isn't allowed in the database only updates it in memory, and doesn't mark it as set in code.
	 */
	public function test_disallowed_setting_is_not_saved_to_db() {
		acf_update_setting( 'scf_test_setting', 'value', true );

		$this->assertFalse( get_option( 'acf_scf_test_setting' ) );
		$this->assertSame( 'value', acf_get_setting( 'scf_test_setting' ) );
		$this->assertFalse( acf_is_setting_set_in_code( 'scf_test_setting' ) );
	}

	/**
	 * A database save that fails returns a WP_Error and leaves the in-memory value alone.
	 */
	public function test_failed_db_save_returns_error() {
		acf()->settings['google_api_key'] = 'original';

		$block = static function ( $value, $old_value ) {
			return $old_value;
		};
		// Keep the old value, so update_option() saves nothing and the stored value differs from what was asked for.
		add_filter( 'pre_update_option_acf_google_api_key', $block, 10, 2 );

		$result = acf_update_setting( 'google_api_key', 'new', true );

		remove_filter( 'pre_update_option_acf_google_api_key', $block, 10 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acf_update_setting_failed', $result->get_error_code() );
		$this->assertSame( 'original', acf_raw_setting( 'google_api_key' ) );
	}

	/**
	 * A setting updated in code wins over the database and is reported as set in code and managed.
	 */
	public function test_setting_set_in_code_wins_over_db() {
		update_option( 'acf_enable_shortcode', 1 );
		$this->assertTrue( acf_get_setting( 'enable_shortcode' ) );
		$this->assertFalse( acf_is_setting_set_in_code( 'enable_shortcode' ) );
		$this->assertFalse( acf_is_setting_managed( 'enable_shortcode' ) );

		acf_update_setting( 'enable_shortcode', false );

		$this->assertTrue( acf_is_setting_set_in_code( 'enable_shortcode' ) );
		$this->assertTrue( acf_is_setting_managed( 'enable_shortcode' ) );
		$this->assertFalse( acf_get_setting( 'enable_shortcode' ) );
	}

	/**
	 * Saving to the database doesn't mark a setting as set in code.
	 */
	public function test_db_save_is_not_set_in_code() {
		acf_update_setting( 'enable_shortcode', true, true );

		$this->assertFalse( acf_is_setting_set_in_code( 'enable_shortcode' ) );
		$this->assertFalse( acf_is_setting_managed( 'enable_shortcode' ) );
	}

	/**
	 * Settings that aren't allowed in the database are never reported as set in code.
	 */
	public function test_set_in_code_only_reports_allowed_settings() {
		acf_update_setting( 'scf_test_setting', 'value' );

		$this->assertFalse( acf_is_setting_set_in_code( 'scf_test_setting' ) );
	}

	/**
	 * A setting set in code before it's registered with the allowed settings filter is reported once registered.
	 */
	public function test_set_in_code_before_registration() {
		acf_update_setting( 'scf_test_setting', 'from-code' );
		update_option( 'acf_scf_test_setting', 'from-db' );

		$register = static function ( $settings ) {
			$settings['scf_test_setting'] = array( 'sanitize_callback' => 'sanitize_text_field' );
			return $settings;
		};
		add_filter( 'acf/settings/allowed_db_settings', $register );

		$set_in_code = acf_is_setting_set_in_code( 'scf_test_setting' );
		$value       = acf_get_setting( 'scf_test_setting' );

		remove_filter( 'acf/settings/allowed_db_settings', $register );

		$this->assertTrue( $set_in_code );
		$this->assertSame( 'from-code', $value );
	}

	/**
	 * A setting changed with an "acf/settings/{$name}" filter is managed and the filter wins over the database.
	 */
	public function test_filtered_setting_is_managed() {
		acf_update_setting( 'enable_shortcode', false, true );

		add_filter( 'acf/settings/enable_shortcode', '__return_true' );
		$managed = acf_is_setting_managed( 'enable_shortcode' );
		$value   = acf_get_setting( 'enable_shortcode' );
		remove_filter( 'acf/settings/enable_shortcode', '__return_true' );

		$this->assertTrue( $managed );
		$this->assertTrue( $value );
		$this->assertFalse( acf_is_setting_managed( 'enable_shortcode' ) );
		$this->assertFalse( acf_get_setting( 'enable_shortcode' ) );
	}

	/**
	 * Plugins can register their own settings, which then read from and save to the database.
	 */
	public function test_allowed_settings_filter_adds_setting() {
		$register = static function ( $settings ) {
			$settings['scf_test_setting'] = array(
				'sanitize_callback' => 'absint',
				'autoload'          => false,
			);
			return $settings;
		};
		add_filter( 'acf/settings/allowed_db_settings', $register );

		$allowed = acf_get_allowed_db_settings();
		$saved   = acf_update_setting( 'scf_test_setting', '42abc', true );
		$stored  = get_option( 'acf_scf_test_setting' );
		$value   = acf_get_setting( 'scf_test_setting' );

		remove_filter( 'acf/settings/allowed_db_settings', $register );

		$this->assertFalse( $allowed['scf_test_setting']['autoload'] );
		$this->assertTrue( $saved );
		$this->assertSame( 42, (int) $stored );
		$this->assertSame( 42, (int) $value );

		// The cache follows the filter: once it's removed, the setting is no longer allowed.
		$this->assertArrayNotHasKey( 'scf_test_setting', acf_get_allowed_db_settings() );
	}

	/**
	 * A filter callback that calls acf_get_setting() doesn't recurse forever.
	 */
	public function test_allowed_settings_filter_can_read_settings() {
		$register = static function ( $settings ) {
			acf_get_setting( 'enable_shortcode' );
			$settings['scf_test_setting'] = array( 'sanitize_callback' => 'sanitize_text_field' );
			return $settings;
		};
		add_filter( 'acf/settings/allowed_db_settings', $register );

		$allowed = acf_get_allowed_db_settings();

		remove_filter( 'acf/settings/allowed_db_settings', $register );

		$this->assertArrayHasKey( 'scf_test_setting', $allowed );
	}

	/**
	 * Entries without a callable sanitize callback, or without a string name, are dropped and reported.
	 */
	public function test_normalize_drops_invalid_entries() {
		$defaults   = array( 'enable_shortcode' => array( 'sanitize_callback' => 'wp_validate_boolean' ) );
		$normalized = acf_normalize_allowed_db_settings(
			array(
				'enable_shortcode'      => array( 'sanitize_callback' => 'wp_validate_boolean' ),
				'scf_no_sanitizer'      => array( 'autoload' => true ),
				'scf_bad_sanitizer'     => array( 'sanitize_callback' => 'scf_function_that_does_not_exist' ),
				'scf_not_array'         => 'nope',
				0                       => array( 'sanitize_callback' => 'sanitize_text_field' ),
				'scf_autoload_defaults' => array(
					'sanitize_callback' => 'sanitize_text_field',
					'extra'             => 'kept',
				),
			),
			$defaults
		);

		$this->assertSame( array( 'enable_shortcode', 'scf_autoload_defaults' ), array_keys( $normalized ) );
		$this->assertTrue( $normalized['enable_shortcode']['autoload'] );
		$this->assertTrue( $normalized['scf_autoload_defaults']['autoload'] );
		$this->assertSame( 'kept', $normalized['scf_autoload_defaults']['extra'] );

		$messages = implode( "\n", array_column( $this->doing_it_wrong, 1 ) );
		$this->assertStringContainsString( 'scf_no_sanitizer', $messages );
		$this->assertStringContainsString( 'scf_bad_sanitizer', $messages );
		$this->assertStringContainsString( 'scf_not_array', $messages );
		$this->assertStringContainsString( 'keyed by their name', $messages );
		$this->assertSame( array( 'acf_get_allowed_db_settings' ), array_unique( array_column( $this->doing_it_wrong, 0 ) ) );
	}

	/**
	 * A filter that doesn't return an array leaves SCF's own settings in place.
	 */
	public function test_filter_returning_non_array_keeps_defaults() {
		add_filter( 'acf/settings/allowed_db_settings', '__return_null' );
		$allowed = acf_get_allowed_db_settings();
		remove_filter( 'acf/settings/allowed_db_settings', '__return_null' );

		$this->assertArrayHasKey( 'enable_shortcode', $allowed );
		$this->assertArrayHasKey( 'enable_datastore', $allowed );
	}

	/**
	 * Each problem is only reported once.
	 */
	public function test_invalid_settings_reported_once() {
		$problems = array( array( 'no_sanitizer', 'scf_reported_once' ) );

		acf_report_invalid_allowed_db_settings( $problems );
		acf_report_invalid_allowed_db_settings( $problems );

		$this->assertCount( 1, $this->doing_it_wrong );
	}

	/**
	 * Only "light" and "standard" are valid REST API formats.
	 */
	public function test_sanitize_rest_api_format() {
		$this->assertSame( 'light', acf_sanitize_rest_api_format( 'light' ) );
		$this->assertSame( 'standard', acf_sanitize_rest_api_format( 'standard' ) );
		$this->assertSame( 'light', acf_sanitize_rest_api_format( 'Standard' ) );
		$this->assertSame( 'light', acf_sanitize_rest_api_format( '' ) );
		$this->assertSame( 'light', acf_sanitize_rest_api_format( null ) );
		$this->assertSame( 'light', acf_sanitize_rest_api_format( array( 'standard' ) ) );
	}

	/**
	 * The datastore follows the "enable_datastore" setting, from the database or a filter.
	 */
	public function test_is_using_datastore_reads_setting() {
		if ( ! version_compare( get_bloginfo( 'version' ), '6.7', '>=' ) ) {
			$this->markTestSkipped( 'The datastore requires WordPress 6.7.' );
		}

		$this->assertFalse( acf_is_using_datastore() );

		acf_update_setting( 'enable_datastore', true, true );
		$this->assertTrue( acf_is_using_datastore() );

		acf_update_setting( 'enable_datastore', false, true );
		$this->assertFalse( acf_is_using_datastore() );

		add_filter( 'acf/settings/enable_datastore', '__return_true' );
		$filtered = acf_is_using_datastore();
		remove_filter( 'acf/settings/enable_datastore', '__return_true' );
		$this->assertTrue( $filtered );
	}
}
