<?php
/**
 * Tests for the settings page.
 *
 * @package wordpress/secure-custom-fields
 */

use SCF\Settings_Page\Settings_Page;
use WorDBless\BaseTestCase;

/**
 * Covers the settings page's AJAX handlers, tabs and sections.
 *
 * @group settings
 * @group ajax
 * @group security
 */
class Test_Settings_Page extends BaseTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private $editor_id;

	/**
	 * The settings page.
	 *
	 * @var Settings_Page
	 */
	private $page;

	/**
	 * _doing_it_wrong() messages recorded during a test.
	 *
	 * @var string[]
	 */
	private $doing_it_wrong = array();

	/**
	 * Settings saved by the tests.
	 *
	 * @var string[]
	 */
	private $settings = array( 'enable_shortcode', 'enable_schema', 'rest_api_format', 'google_api_key', 'google_api_client', 'remove_wp_meta_box' );

	/**
	 * Original in-memory setting values.
	 *
	 * @var array
	 */
	private $original = array();

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->admin_id  = wp_insert_user(
			array(
				'user_login' => 'settings_admin',
				'user_pass'  => 'password',
				'user_email' => 'settings_admin@example.com',
				'role'       => 'administrator',
			)
		);
		$this->editor_id = wp_insert_user(
			array(
				'user_login' => 'settings_editor',
				'user_pass'  => 'password',
				'user_email' => 'settings_editor@example.com',
				'role'       => 'editor',
			)
		);

		foreach ( $this->settings as $name ) {
			$this->original[ $name ] = acf_raw_setting( $name );
		}

		acf_set_data( 'settings_set_in_code', array() );

		// Exposes the settings page's protected methods for testing.
		$this->page = new class() extends Settings_Page {

			/**
			 * Returns the tabs, built fresh.
			 *
			 * @return array[]
			 */
			public function tabs() {
				$this->tabs = null;
				return $this->get_tabs();
			}

			/**
			 * Returns a tab's data for JS.
			 *
			 * @param string $key The tab key.
			 * @return mixed
			 */
			public function tab_data( $key ) {
				return $this->get_tab_data( $this->get_tab_config( $key ) );
			}

			/**
			 * Returns a field from a tab's data.
			 *
			 * @param string $tab The tab key.
			 * @param string $key The field key.
			 * @return array|null
			 */
			public function field( $tab, $key ) {
				$data = $this->tab_data( $tab );
				foreach ( $data['sections'] as $section ) {
					foreach ( $section['fields'] as $field ) {
						if ( $field['key'] === $key ) {
							return $field;
						}
					}
				}
				return null;
			}
		};

		$this->doing_it_wrong = array();
		add_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ), 10, 2 );
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

		$_POST    = array();
		$_REQUEST = array();
		$_GET     = array();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		wp_set_current_user( 0 );

		remove_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ), 10 );
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		remove_all_filters( 'acf/settings_page/tabs' );
		remove_all_filters( 'acf/settings_page/general/sections' );
		remove_all_filters( 'acf/settings_page/general/interface/fields' );
		remove_all_filters( 'acf/settings_page/general/api_scripts/fields' );
		remove_all_filters( 'acf/settings/show_settings' );
		remove_all_filters( 'acf/settings/enable_schema' );
		remove_all_filters( 'acf/fields/google_map/api' );

		foreach ( $this->original as $name => $value ) {
			acf()->settings[ $name ] = $value;
			delete_option( 'acf_' . $name );
		}

		acf_set_data( 'settings_set_in_code', array() );

		$_POST    = array();
		$_REQUEST = array();
		$_GET     = array();

		parent::tear_down();
	}

	/**
	 * Records a _doing_it_wrong() message.
	 *
	 * @param string $function_name The function name.
	 * @param string $message       The message.
	 */
	public function record_doing_it_wrong( $function_name, $message ) {
		$this->doing_it_wrong[] = html_entity_decode( $message );
	}

	/**
	 * Runs an AJAX handler and returns its decoded JSON response.
	 *
	 * The status code isn't checked: wp_send_json() can't send headers once PHPUnit has printed output.
	 *
	 * @param string $method The handler method.
	 * @param array  $post   The request parameters.
	 * @return array{body: mixed}
	 *
	 * @throws \RuntimeException Re-thrown when an unrelated runtime exception bubbles out of the handler.
	 */
	private function call_ajax( $method, array $post ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test populates request superglobals to drive the handler under test.
		$_POST    = $post;
		$_REQUEST = $post;

		$marker  = 'scf_settings_ajax_halt';
		$force   = static function () {
			return true;
		};
		$handler = static function () use ( $marker ) {
			return static function () use ( $marker ) {
				throw new \RuntimeException( esc_html( $marker ) );
			};
		};

		add_filter( 'wp_doing_ajax', $force );
		add_filter( 'wp_die_ajax_handler', $handler, 100 );
		add_filter( 'wp_die_json_handler', $handler, 100 );

		$unrelated = null;
		ob_start();
		try {
			$this->page->$method();
		} catch ( \RuntimeException $halt ) {
			if ( $halt->getMessage() !== $marker ) {
				$unrelated = $halt;
			}
		} finally {
			$output = ob_get_clean();
			remove_filter( 'wp_doing_ajax', $force );
			remove_filter( 'wp_die_ajax_handler', $handler, 100 );
			remove_filter( 'wp_die_json_handler', $handler, 100 );
		}

		if ( null !== $unrelated ) {
			throw $unrelated;
		}

		return array( 'body' => json_decode( $output, true ) );
	}

	/**
	 * Sends a save request.
	 *
	 * @param array  $settings The settings to save.
	 * @param string $nonce    The nonce, or null for a valid one.
	 * @param string $tab      The tab.
	 * @return array{body: mixed}
	 */
	private function save( array $settings, $nonce = null, $tab = 'general' ) {
		return $this->call_ajax(
			'ajax_request',
			array(
				'action'      => 'acf-settings',
				'nonce'       => null === $nonce ? wp_create_nonce( 'acf_nonce' ) : $nonce,
				'tab'         => $tab,
				'acfSettings' => wp_slash( wp_json_encode( $settings ) ),
			)
		);
	}

	/**
	 * The AJAX actions match ACF's.
	 */
	public function test_ajax_actions_are_registered() {
		$this->assertSame( 10, has_action( 'wp_ajax_acf-settings', array( $this->page, 'ajax_request' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_acf_settings_tab_data', array( $this->page, 'ajax_get_tab_data' ) ) );
		$this->assertFalse( has_action( 'wp_ajax_nopriv_acf-settings' ) );
		$this->assertFalse( has_action( 'wp_ajax_nopriv_acf_settings_tab_data' ) );
	}

	/**
	 * An administrator saves a setting to the database and gets the saved value back.
	 */
	public function test_save_updates_setting() {
		wp_set_current_user( $this->admin_id );

		$response = $this->save(
			array(
				'enable_shortcode' => true,
				'rest_api_format'  => 'standard',
			)
		);

		$this->assertTrue( $response['body']['success'] );
		$this->assertSame(
			array(
				'enable_shortcode' => true,
				'rest_api_format'  => 'standard',
			),
			$response['body']['data']['settings']
		);
		$this->assertSame( 1, (int) get_option( 'acf_enable_shortcode' ) );
		$this->assertTrue( acf_get_setting( 'enable_shortcode' ) );
		$this->assertSame( 'standard', acf_get_setting( 'rest_api_format' ) );
	}

	/**
	 * Saved values come back sanitized.
	 */
	public function test_save_returns_sanitized_values() {
		wp_set_current_user( $this->admin_id );

		$response = $this->save(
			array(
				'rest_api_format' => 'bogus',
				'google_api_key'  => ' <b>key</b> ',
			)
		);

		$this->assertSame( 'light', $response['body']['data']['settings']['rest_api_format'] );
		$this->assertSame( 'key', $response['body']['data']['settings']['google_api_key'] );
	}

	/**
	 * A bad nonce is rejected.
	 */
	public function test_save_rejects_bad_nonce() {
		wp_set_current_user( $this->admin_id );

		$response = $this->save( array( 'enable_shortcode' => true ), 'invalid' );

		$this->assertFalse( $response['body']['success'] );
		$this->assertSame( 'Invalid nonce.', $response['body']['data'] );
		$this->assertFalse( get_option( 'acf_enable_shortcode' ) );
	}

	/**
	 * Users without SCF's capability can't save, even with a valid nonce.
	 */
	public function test_save_requires_capability() {
		wp_set_current_user( $this->editor_id );

		$response = $this->save( array( 'enable_shortcode' => true ) );

		$this->assertFalse( $response['body']['success'] );
		$this->assertSame( 'Sorry, you do not have permission to do that.', $response['body']['data'] );
		$this->assertFalse( get_option( 'acf_enable_shortcode' ) );
	}

	/**
	 * Logged-out users can't save.
	 */
	public function test_save_rejects_logged_out_user() {
		wp_set_current_user( 0 );

		$response = $this->save( array( 'enable_shortcode' => true ) );

		$this->assertFalse( $response['body']['success'] );
		$this->assertFalse( get_option( 'acf_enable_shortcode' ) );
	}

	/**
	 * Settings that aren't allowed in the database are skipped, and nothing else is stored.
	 */
	public function test_save_skips_disallowed_settings() {
		wp_set_current_user( $this->admin_id );

		$response = $this->save(
			array(
				'show_admin'       => false,
				'capability'       => 'read',
				'enable_shortcode' => true,
			)
		);

		$this->assertTrue( $response['body']['success'] );
		$this->assertSame( array( 'show_admin', 'capability' ), $response['body']['data']['skipped'] );
		$this->assertArrayNotHasKey( 'show_admin', $response['body']['data']['settings'] );
		$this->assertFalse( get_option( 'acf_show_admin' ) );
		$this->assertFalse( get_option( 'acf_capability' ) );
		$this->assertTrue( acf_get_setting( 'show_admin' ) );
		$this->assertSame( 'manage_options', acf_get_setting( 'capability' ) );
	}

	/**
	 * A request that only has disallowed settings fails.
	 */
	public function test_save_with_only_disallowed_settings_fails() {
		wp_set_current_user( $this->admin_id );

		$response = $this->save( array( 'capability' => 'read' ) );

		$this->assertFalse( $response['body']['success'] );
		$this->assertStringContainsString( 'capability', $response['body']['data'] );
	}

	/**
	 * Settings managed via a filter or acf_update_setting() are skipped.
	 */
	public function test_save_skips_managed_settings() {
		wp_set_current_user( $this->admin_id );

		add_filter( 'acf/settings/enable_schema', '__return_false' );
		acf_update_setting( 'remove_wp_meta_box', true );

		$response = $this->save(
			array(
				'enable_schema'      => true,
				'remove_wp_meta_box' => false,
				'enable_shortcode'   => true,
			)
		);

		$this->assertTrue( $response['body']['success'] );
		$this->assertSame( array( 'enable_schema', 'remove_wp_meta_box' ), $response['body']['data']['skipped'] );
		$this->assertFalse( get_option( 'acf_enable_schema' ) );
		$this->assertFalse( get_option( 'acf_remove_wp_meta_box' ) );
		$this->assertTrue( acf_get_setting( 'remove_wp_meta_box' ) );
	}

	/**
	 * Missing or malformed settings are rejected.
	 */
	public function test_save_rejects_invalid_payload() {
		wp_set_current_user( $this->admin_id );

		$missing = $this->call_ajax(
			'ajax_request',
			array(
				'nonce' => wp_create_nonce( 'acf_nonce' ),
				'tab'   => 'general',
			)
		);
		$this->assertSame( 'No settings provided.', $missing['body']['data'] );

		$invalid = $this->call_ajax(
			'ajax_request',
			array(
				'nonce'       => wp_create_nonce( 'acf_nonce' ),
				'tab'         => 'general',
				'acfSettings' => 'not json',
			)
		);
		$this->assertFalse( $invalid['body']['success'] );
		$this->assertSame( 'Invalid settings format.', $invalid['body']['data'] );
	}

	/**
	 * With show_settings off, the Features tab is gone and its settings can't be saved.
	 */
	public function test_save_blocked_when_show_settings_is_false() {
		wp_set_current_user( $this->admin_id );
		add_filter( 'acf/settings/show_settings', '__return_false' );

		$this->assertSame( array(), $this->page->tabs() );

		$response = $this->save( array( 'enable_shortcode' => true ) );

		$this->assertFalse( $response['body']['success'] );
		$this->assertSame( 'The settings page has been disabled.', $response['body']['data'] );
		$this->assertFalse( get_option( 'acf_enable_shortcode' ) );
	}

	/**
	 * With show_settings off, a plugin's tab can still save the settings it shows, but no others.
	 */
	public function test_show_settings_false_allows_own_fields_only() {
		wp_set_current_user( $this->admin_id );
		add_filter( 'acf/settings/show_settings', '__return_false' );
		add_filter(
			'acf/settings_page/tabs',
			static function ( $tabs ) {
				$tabs[] = array(
					'key'      => 'plugin',
					'label'    => 'Plugin',
					'tab_data' => array(
						'sections' => array(
							array(
								'title'  => 'Plugin',
								'fields' => array(
									array(
										'key'   => 'enable_shortcode',
										'type'  => 'toggle',
										'label' => 'Shortcode',
										'value' => false,
									),
								),
							),
						),
					),
				);
				return $tabs;
			}
		);
		$this->page->tabs();

		$own = $this->save( array( 'enable_shortcode' => true ), null, 'plugin' );
		$this->assertTrue( $own['body']['success'] );
		$this->assertSame( 1, (int) get_option( 'acf_enable_shortcode' ) );

		$other = $this->save( array( 'enable_schema' => true ), null, 'plugin' );
		$this->assertSame( 'The settings page has been disabled.', $other['body']['data'] );
		$this->assertFalse( get_option( 'acf_enable_schema' ) );
	}

	/**
	 * A tab's own AJAX callback handles its saves.
	 */
	public function test_tab_ajax_callback_is_used() {
		wp_set_current_user( $this->admin_id );
		add_filter(
			'acf/settings_page/tabs',
			static function ( $tabs ) {
				$tabs[] = array(
					'key'           => 'custom',
					'label'         => 'Custom',
					'ajax_callback' => static function () {
						return array( 'handled' => true );
					},
				);
				return $tabs;
			}
		);
		$this->page->tabs();

		$response = $this->save( array( 'enable_shortcode' => true ), null, 'custom' );

		$this->assertSame( array( 'handled' => true ), $response['body']['data'] );
		$this->assertFalse( get_option( 'acf_enable_shortcode' ) );
	}

	/**
	 * The tab data endpoint checks the nonce and capability, and returns the tab's data with a fresh nonce.
	 */
	public function test_tab_data_endpoint() {
		$params = array(
			'action' => 'acf_settings_tab_data',
			'nonce'  => '',
			'tab'    => 'general',
		);

		wp_set_current_user( $this->admin_id );
		$params['nonce'] = 'invalid';
		$this->assertSame( 'Invalid nonce.', $this->call_ajax( 'ajax_get_tab_data', $params )['body']['data'] );

		wp_set_current_user( $this->editor_id );
		$params['nonce'] = wp_create_nonce( 'acf_nonce' );
		$this->assertSame( 'Sorry, you do not have permission to do that.', $this->call_ajax( 'ajax_get_tab_data', $params )['body']['data'] );

		wp_set_current_user( $this->admin_id );
		$params['nonce'] = wp_create_nonce( 'acf_nonce' );
		$response        = $this->call_ajax( 'ajax_get_tab_data', $params );
		$this->assertTrue( $response['body']['success'] );
		$this->assertSame( 'general', $response['body']['data']['activeTab'] );
		$this->assertNotEmpty( $response['body']['data']['tabData']['sections'] );
		$this->assertNotEmpty( $response['body']['data']['nonce'] );
		$this->assertSame( 'Managed via code', $response['body']['data']['strings']['managedViaCode'] );

		$params['tab'] = 'missing';
		$this->assertSame( 'Invalid tab.', $this->call_ajax( 'ajax_get_tab_data', $params )['body']['data'] );
	}

	/**
	 * The Features tab has SCF's sections and settings, including the ones that are PRO in ACF.
	 */
	public function test_features_tab_sections() {
		$tabs = $this->page->tabs();

		$this->assertSame( 'general', $tabs[0]['key'] );
		$this->assertSame( 'Features', $tabs[0]['label'] );

		$data = $this->page->tab_data( 'general' );
		$this->assertSame(
			array( 'Interface', 'API & Scripts', 'AI & GEO', 'Field Specific Settings' ),
			array_column( $data['sections'], 'title' )
		);

		$keys = array();
		foreach ( $data['sections'] as $section ) {
			$keys = array_merge( $keys, array_column( $section['fields'], 'key' ) );
		}
		$this->assertSame(
			array( 'enable_post_types', 'remove_wp_meta_box', 'enable_options_pages_ui', 'enable_shortcode', 'rest_api_enabled', 'rest_api_format', 'preload_blocks', 'enable_datastore', 'enable_abilities', 'enable_schema', 'google_api_key', 'google_api_client' ),
			$keys
		);

		// Every field can be saved.
		$this->assertEmpty( array_diff( $keys, array_keys( acf_get_allowed_db_settings() ) ) );

		$datastore = $this->page->field( 'general', 'enable_datastore' );
		$this->assertTrue( $datastore['experimental'] );
		$this->assertSame( ! version_compare( get_bloginfo( 'version' ), '6.7', '>=' ), $datastore['disabled'] );

		$shortcode = $this->page->field( 'general', 'enable_shortcode' );
		$this->assertSame( 'SCF Shortcode', $shortcode['label'] );
		$this->assertStringStartsWith( 'https://developer.wordpress.org/secure-custom-fields/', $shortcode['learnMoreUrl'] );
		$this->assertIsBool( $shortcode['value'] );
	}

	/**
	 * Settings managed via code are locked.
	 */
	public function test_managed_fields_are_locked() {
		$this->assertFalse( $this->page->field( 'general', 'enable_schema' )['managed'] );

		add_filter( 'acf/settings/enable_schema', '__return_true' );
		$field = $this->page->field( 'general', 'enable_schema' );
		$this->assertTrue( $field['managed'] );
		$this->assertTrue( $field['value'] );

		acf_update_setting( 'remove_wp_meta_box', false );
		$this->assertTrue( $this->page->field( 'general', 'remove_wp_meta_box' )['managed'] );
	}

	/**
	 * A Google Maps key set with the "acf/fields/google_map/api" filter is shown and locked.
	 */
	public function test_google_maps_filter_locks_field() {
		acf_update_setting( 'google_api_key', 'stored', true );

		$field = $this->page->field( 'general', 'google_api_key' );
		$this->assertSame( 'stored', $field['value'] );
		$this->assertFalse( $field['managed'] );

		add_filter(
			'acf/fields/google_map/api',
			static function ( $api ) {
				$api['key'] = 'from-filter';
				return $api;
			}
		);

		$field = $this->page->field( 'general', 'google_api_key' );
		$this->assertSame( 'from-filter', $field['value'] );
		$this->assertTrue( $field['managed'] );
		$this->assertFalse( $this->page->field( 'general', 'google_api_client' )['managed'] );
	}

	/**
	 * Fields can be added to a section, and sections added to the tab. Empty sections aren't rendered.
	 */
	public function test_section_and_field_filters() {
		add_filter(
			'acf/settings_page/general/interface/fields',
			static function ( $fields ) {
				$fields[] = array(
					'key'          => 'my_setting',
					'type'         => 'toggle',
					'label'        => 'My <b>setting</b>',
					'description'  => '<script>alert(1)</script>Description',
					'value'        => '1',
					'experimental' => true,
				);
				return $fields;
			}
		);
		add_filter( 'acf/settings_page/general/ai/fields', '__return_empty_array' );
		add_filter(
			'acf/settings_page/general/sections',
			static function ( $sections ) {
				$sections[] = array(
					'title'  => 'Mine',
					'fields' => array(
						array(
							'key'     => 'my_select',
							'type'    => 'select',
							'label'   => 'Select',
							'value'   => null,
							'options' => array(
								array(
									'label' => '<em>One</em>',
									'value' => 'one',
								),
								array( 'label' => 'No value' ),
								array( 'value' => 'two' ),
							),
						),
						array(
							'key'   => 'Bad Key',
							'type'  => 'text',
							'label' => 'Bad',
							'value' => '',
						),
						array(
							'key'  => 'no_value',
							'type' => 'text',
						),
					),
				);
				$sections[] = 'not a section';
				$sections[] = array(
					'title'  => 'Empty',
					'fields' => array(),
				);
				return $sections;
			}
		);

		$data   = $this->page->tab_data( 'general' );
		$titles = array_column( $data['sections'], 'title' );

		$this->assertSame( array( 'Interface', 'API & Scripts', 'Field Specific Settings', 'Mine' ), $titles );

		$mine = $this->page->field( 'general', 'my_setting' );
		$this->assertSame( 'My setting', $mine['label'] );
		$this->assertSame( 'Description', $mine['description'] );
		$this->assertTrue( $mine['value'] );
		$this->assertTrue( $mine['experimental'] );

		$select = $this->page->field( 'general', 'my_select' );
		$this->assertSame( '', $select['value'] );
		$this->assertSame(
			array(
				array(
					'label'    => 'One',
					'value'    => 'one',
					'disabled' => false,
				),
				array(
					'label'    => 'two',
					'value'    => 'two',
					'disabled' => false,
				),
			),
			$select['options']
		);

		$this->assertCount( 1, $data['sections'][3]['fields'] );
		$this->assertStringContainsString( 'Bad Key', implode( "\n", $this->doing_it_wrong ) );
	}

	/**
	 * Tabs are validated: invalid keys are dropped, reserved keys are kept for SCF, and keys are normalized.
	 */
	public function test_tabs_filter_validation() {
		add_filter(
			'acf/settings_page/tabs',
			static function ( $tabs ) {
				$tabs[] = array(
					'key'   => 'general',
					'label' => 'Impostor',
				);
				$tabs[] = array( 'label' => 'No key' );
				$tabs[] = array(
					'key'   => '!!!',
					'label' => 'Bad key',
				);
				$tabs[] = array(
					'key'   => 'My-Tab',
					'label' => 'Mine',
					'order' => 5,
				);
				$tabs[] = array( 'key' => 'nolabel' );
				return $tabs;
			}
		);

		$tabs = $this->page->tabs();

		$this->assertSame( array( 'my-tab', 'general', 'nolabel' ), array_column( $tabs, 'key' ) );
		$this->assertSame( 'Features', $tabs[1]['label'] );
		$this->assertSame( 'nolabel', $tabs[2]['label'] );
		$this->assertSame(
			array( 'acf-settings-tab-my-tab', 'acf-settings-tab-general', 'acf-settings-tab-nolabel' ),
			$this->page->get_page_slugs()
		);

		$messages = implode( "\n", $this->doing_it_wrong );
		$this->assertStringContainsString( 'reserved for SCF', $messages );
		$this->assertStringContainsString( 'need a "key"', $messages );
		$this->assertStringContainsString( '"My-Tab" has been changed to "my-tab"', $messages );
	}

	/**
	 * A tabs filter that doesn't return an array keeps SCF's tabs.
	 */
	public function test_tabs_filter_must_return_array() {
		add_filter( 'acf/settings_page/tabs', '__return_null' );

		$tabs = $this->page->tabs();

		$this->assertSame( array( 'general' ), array_column( $tabs, 'key' ) );
		$this->assertStringContainsString( 'must return an array', implode( "\n", $this->doing_it_wrong ) );
	}

	/**
	 * The old Beta Features page redirects administrators to the settings page.
	 */
	public function test_beta_features_page_redirects() {
		$redirects = array();
		// Stop before redirect_legacy_pages() exits.
		$capture = static function ( $location ) use ( &$redirects ): string {
			$redirects[] = $location;
			throw new \RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $capture ); // @phpstan-ignore return.missing (the callback throws to stop before exit.)

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test sets the requested page.
		$_GET['page'] = 'scf-beta-features';

		wp_set_current_user( $this->editor_id );
		$this->page->redirect_legacy_pages();
		$this->assertSame( array(), $redirects );

		wp_set_current_user( $this->admin_id );
		try {
			$this->page->redirect_legacy_pages();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		}

		remove_filter( 'wp_redirect', $capture );

		$this->assertCount( 1, $redirects );
		$this->assertStringContainsString( 'edit.php?post_type=acf-field-group&page=acf-settings-tab-general', $redirects[0] );

		// Other pages are left alone.
		$_GET['page'] = 'something-else';
		$this->page->redirect_legacy_pages();
		$this->assertCount( 1, $redirects );
	}

	/**
	 * The header bar uses SCF's classes, with the Save button the app controls.
	 */
	public function test_headerbar_markup() {
		ob_start();
		$this->page->render_headerbar();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'class="acf-headerbar"', $html );
		$this->assertStringContainsString( 'id="acf-settings-save" class="acf-btn acf-btn-sm" disabled', $html );

		ob_start();
		$this->page->admin_html();
		$html = ob_get_clean();

		$this->assertStringContainsString( '<hr class="wp-header-end">', $html );
		$this->assertStringContainsString( 'id="acf-settings-app"', $html );
	}
}
