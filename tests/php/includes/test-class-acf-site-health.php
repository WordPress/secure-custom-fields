<?php
/**
 * Tests for the ACF_Site_Health class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the legacy Site Health class (not loaded by the plugin bootstrap).
acf_include( 'includes/class-acf-site-health.php' );

/**
 * Class Test_ACF_Site_Health
 *
 * Tests the Site Health debug information provider, including option
 * storage, event tracking and the structure of the debug info section.
 *
 * @group site-health
 */
class Test_ACF_Site_Health extends BaseTestCase {

	/**
	 * The ACF_Site_Health instance under test.
	 *
	 * @var ACF_Site_Health
	 */
	private $site_health;

	/**
	 * Field group key used by these tests.
	 *
	 * @var string
	 */
	private $group_key = 'group_test_site_health';

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		acf_init();

		$this->site_health = acf_get_instance( 'ACF_Site_Health' );

		delete_option( $this->site_health->option_name );

		acf_add_local_field_group(
			array(
				'key'      => $this->group_key,
				'title'    => 'Site Health Group',
				'fields'   => array(
					array(
						'key'   => 'field_test_site_health_text',
						'name'  => 'site_health_text',
						'label' => 'Site Health Text',
						'type'  => 'text',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		acf_remove_local_field_group( $this->group_key );
		delete_option( $this->site_health->option_name );

		parent::tear_down();
	}

	/**
	 * Test that get_site_health returns an empty array when no option exists.
	 */
	public function test_get_site_health_defaults_to_empty_array() {
		$this->assertSame( array(), $this->site_health->get_site_health() );
	}

	/**
	 * Test that data written via update_site_health can be read back.
	 */
	public function test_update_and_get_site_health_round_trip() {
		$data = array(
			'version'      => '1.2.3',
			'last_updated' => 1700000000,
		);

		$this->assertTrue( $this->site_health->update_site_health( $data ) );
		$this->assertSame( $data, $this->site_health->get_site_health() );
	}

	/**
	 * Test that the data is stored as a JSON encoded option.
	 */
	public function test_site_health_stored_as_json_option() {
		$this->site_health->update_site_health( array( 'foo' => 'bar' ) );

		$raw = get_option( $this->site_health->option_name );

		$this->assertIsString( $raw );
		$this->assertSame( array( 'foo' => 'bar' ), json_decode( $raw, true ) );
	}

	/**
	 * Test that a corrupted option value is handled gracefully.
	 */
	public function test_get_site_health_handles_corrupted_option() {
		update_option( $this->site_health->option_name, 'this-is-not-json' );

		$this->assertSame( array(), $this->site_health->get_site_health() );
	}

	/**
	 * Test that update_site_health_data flattens values and prefers debug values.
	 */
	public function test_update_site_health_data_flattens_values() {
		$this->site_health->update_site_health_data(
			array(
				'with_debug'    => array(
					'label' => 'With Debug',
					'value' => 'Yes',
					'debug' => true,
				),
				'without_debug' => array(
					'label' => 'Without Debug',
					'value' => '42',
				),
			)
		);

		$stored = $this->site_health->get_site_health();

		$this->assertTrue( $stored['with_debug'], 'The debug value should win over the display value' );
		$this->assertSame( '42', $stored['without_debug'] );
		$this->assertArrayHasKey( 'last_updated', $stored );
	}

	/**
	 * Test that update_site_health_data preserves previously stored events.
	 */
	public function test_update_site_health_data_preserves_events() {
		$this->site_health->add_site_health_event( 'some_event' );

		$this->site_health->update_site_health_data(
			array(
				'plain' => array(
					'label' => 'Plain',
					'value' => 'value',
				),
			)
		);

		$stored = $this->site_health->get_site_health();

		$this->assertArrayHasKey( 'event_some_event', $stored, 'event_* keys should survive data updates' );
		$this->assertSame( 'value', $stored['plain'] );
	}

	/**
	 * Test that add_site_health_event stores a timestamped event.
	 */
	public function test_add_site_health_event_stores_event() {
		$before = time();

		$this->assertTrue( $this->site_health->add_site_health_event( 'my_event' ) );

		$stored = $this->site_health->get_site_health();

		$this->assertArrayHasKey( 'event_my_event', $stored );
		$this->assertGreaterThanOrEqual( $before, $stored['event_my_event'] );
		$this->assertArrayHasKey( 'last_updated', $stored );
	}

	/**
	 * Test that events are only stored once.
	 */
	public function test_add_site_health_event_is_idempotent() {
		$this->assertTrue( $this->site_health->add_site_health_event( 'duplicate_event' ) );
		$this->assertFalse( $this->site_health->add_site_health_event( 'duplicate_event' ), 'A second identical event should be ignored' );
	}

	/**
	 * Test that an empty event name outside an acf/ hook is rejected.
	 */
	public function test_add_site_health_event_requires_name_outside_acf_hooks() {
		$this->assertFalse( $this->site_health->add_site_health_event() );
	}

	/**
	 * Test that the event name is derived from the current acf/ filter.
	 */
	public function test_add_site_health_event_derives_name_from_acf_hook() {
		add_action(
			'acf/test_sh_hook_event',
			function () {
				$this->site_health->add_site_health_event();
			}
		);
		do_action( 'acf/test_sh_hook_event' );

		$stored = $this->site_health->get_site_health();

		$this->assertArrayHasKey( 'event_test_sh_hook_event', $stored );
	}

	/**
	 * Test that add_activation_event records the first_activated event.
	 */
	public function test_add_activation_event_records_first_activated() {
		$this->assertTrue( $this->site_health->add_activation_event() );

		$stored = $this->site_health->get_site_health();

		$this->assertArrayHasKey( 'event_first_activated', $stored );

		// A repeat activation should not be recorded again.
		$this->assertFalse( $this->site_health->add_activation_event() );
	}

	/**
	 * Test that pre_update_acf_internal_cpt returns its input unchanged.
	 */
	public function test_pre_update_acf_internal_cpt_returns_input() {
		$post = array(
			'key'   => 'group_test_site_health_cpt',
			'title' => 'Some Group',
		);

		$this->assertSame( $post, $this->site_health->pre_update_acf_internal_cpt( $post ) );
		$this->assertSame( array(), $this->site_health->pre_update_acf_internal_cpt( array() ) );
	}

	/**
	 * Test that the first created field group is recorded as an event.
	 */
	public function test_pre_update_acf_internal_cpt_records_first_created_event() {
		// Force "no existing field groups" so the first-created event fires.
		add_filter( 'acf/load_field_groups', '__return_empty_array', 99 );

		$this->site_health->pre_update_acf_internal_cpt(
			array(
				'key'   => 'group_test_site_health_cpt',
				'title' => 'Some Group',
			)
		);

		$stored = $this->site_health->get_site_health();

		$this->assertArrayHasKey( 'event_first_created_field_group', $stored );
	}

	/**
	 * Test that no event is recorded when field groups already exist.
	 */
	public function test_pre_update_acf_internal_cpt_skips_event_when_posts_exist() {
		// The local field group registered in set_up() counts as an existing post.
		$this->site_health->pre_update_acf_internal_cpt(
			array(
				'key'   => 'group_test_site_health_cpt',
				'title' => 'Some Group',
			)
		);

		$stored = $this->site_health->get_site_health();

		$this->assertArrayNotHasKey( 'event_first_created_field_group', $stored );
	}

	/**
	 * Test the overall structure of get_site_health_values.
	 */
	public function test_get_site_health_values_structure() {
		$values = $this->site_health->get_site_health_values();

		$expected_keys = array(
			'version',
			'wp_version',
			'mysql_version',
			'is_multisite',
			'active_theme',
			'active_plugins',
			'ui_field_groups',
			'php_field_groups',
			'json_field_groups',
			'rest_field_groups',
			'number_of_fields_by_type',
			'number_of_third_party_fields_by_type',
			'post_types_enabled',
			'ui_post_types',
			'json_post_types',
			'ui_taxonomies',
			'json_taxonomies',
			'rest_api_format',
			'admin_ui_enabled',
			'shortcode_enabled',
			'registered_acf_forms',
			'json_save_paths',
			'json_load_paths',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $values, "Site health values should include '{$key}'" );
		}

		// Every entry must provide a label and a value.
		foreach ( $values as $key => $value ) {
			$this->assertArrayHasKey( 'label', $value, "Entry '{$key}' should have a label" );
			$this->assertArrayHasKey( 'value', $value, "Entry '{$key}' should have a value" );
		}
	}

	/**
	 * Test that the plugin version is reported.
	 */
	public function test_get_site_health_values_reports_plugin_version() {
		$values = $this->site_health->get_site_health_values();

		$this->assertSame( ACF_VERSION, $values['version']['value'] );
	}

	/**
	 * Test that registered fields are counted by type.
	 */
	public function test_get_site_health_values_counts_fields_by_type() {
		$values = $this->site_health->get_site_health_values();

		$by_type = $values['number_of_fields_by_type']['value'];

		$this->assertIsArray( $by_type );
		$this->assertArrayHasKey( 'text', $by_type, 'The registered text field should be counted' );
		$this->assertGreaterThanOrEqual( 1, $by_type['text'] );
	}

	/**
	 * Test that PHP field groups are not counted due to a case-sensitive comparison.
	 */
	public function test_php_field_group_count_does_not_match_lowercase_local() {
		$values = $this->site_health->get_site_health_values();

		// NOTE: documents current behavior — possible bug: acf_add_local_field_group()
		// registers groups with 'local' => 'php' (lowercase), but the count filter in
		// includes/class-acf-site-health.php compares against 'PHP' (uppercase), so
		// PHP-registered field groups are never counted. Tracked in #456.
		$this->assertSame( '0', $values['php_field_groups']['value'] );
	}

	/**
	 * Test that render_tab_content appends the SCF debug info section.
	 */
	public function test_render_tab_content_appends_scf_section() {
		$debug_info = $this->site_health->render_tab_content( array( 'existing' => array( 'label' => 'Existing' ) ) );

		$this->assertArrayHasKey( 'existing', $debug_info, 'Existing sections should be preserved' );
		$this->assertArrayHasKey( 'secure-custom-fields', $debug_info );

		$section = $debug_info['secure-custom-fields'];

		$this->assertSame( 'SCF', $section['label'] );
		$this->assertNotEmpty( $section['description'] );
		$this->assertIsArray( $section['fields'] );
		$this->assertArrayHasKey( 'version', $section['fields'] );
	}

	/**
	 * Test that render_tab_content hides internal-only values.
	 */
	public function test_render_tab_content_hides_internal_values() {
		$debug_info = $this->site_health->render_tab_content( array() );
		$fields     = $debug_info['secure-custom-fields']['fields'];

		$hidden_keys = array(
			'wp_version',
			'mysql_version',
			'is_multisite',
			'active_theme',
			'parent_theme',
			'active_plugins',
			'number_of_fields_by_type',
			'number_of_third_party_fields_by_type',
		);

		foreach ( $hidden_keys as $key ) {
			$this->assertArrayNotHasKey( $key, $fields, "Internal value '{$key}' should not be displayed" );
		}
	}

	/**
	 * Test that render_tab_content does not display event keys.
	 */
	public function test_render_tab_content_hides_events() {
		$this->site_health->add_site_health_event( 'hidden_event' );

		$debug_info = $this->site_health->render_tab_content( array() );

		foreach ( array_keys( $debug_info['secure-custom-fields']['fields'] ) as $key ) {
			$this->assertStringStartsNotWith( 'event_', $key, 'Event keys should not be displayed' );
		}
	}

	/**
	 * Test that render_tab_content persists the collected data to the option.
	 */
	public function test_render_tab_content_persists_data() {
		$this->site_health->render_tab_content( array() );

		$stored = $this->site_health->get_site_health();

		$this->assertNotEmpty( $stored );
		$this->assertSame( ACF_VERSION, $stored['version'] );
		$this->assertArrayHasKey( 'last_updated', $stored );
	}
}
