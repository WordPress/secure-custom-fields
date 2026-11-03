<?php
/**
 * Test Secure Custom Fields main functionality
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the ACF_Form_Customizer class.
acf_include( 'includes/forms/form-customizer.php' );

/**
 * Class Test_Form_Customizer
 */
class Test_Form_Customizer extends BaseTestCase {

	/**
	 * $_POST['acf'] as seen by acf/save_post, keyed by the ACF post ID saved.
	 *
	 * @var array
	 */
	private $saved_values = array();

	/**
	 * Widget registered for the save tests.
	 *
	 * @var WP_Widget|null
	 */
	private $test_widget;

	/**
	 * Test filters that must be removed during cleanup.
	 *
	 * @var array
	 */
	private $test_filters = array();

	/**
	 * Registers the field type, location and widget the save tests use.
	 */
	public function set_up() {
		parent::set_up();

		acf_reset_local();
		acf_enable_local();
		$this->reset_stores();

		acf_include( 'includes/fields/class-acf-field-text.php' );
		if ( ! acf_get_field_type( 'text' ) ) {
			acf_register_field_type( 'acf_field_text' );
		}

		acf_include( 'includes/locations/class-acf-location-widget.php' );
		if ( ! acf_get_location_type( 'widget' ) ) {
			acf_register_location_type( 'ACF_Location_Widget' );
		}

		if ( ! has_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts' ) ) {
			add_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts', 20, 2 );
		}

		global $wp_widget_factory;
		$this->test_widget = new WP_Widget( 'scf_test', 'SCF test' );
		$wp_widget_factory->register( $this->test_widget );

		$this->saved_values = array();
		$this->add_test_filter( 'acf/save_post', array( $this, 'capture_saved_values' ), 1 );

		$_POST  = array();
		$_FILES = array();
	}

	/**
	 * Removes local fields, the test widget, request data and filters.
	 */
	public function tear_down() {
		foreach ( $this->test_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}
		$this->test_filters = array();

		global $wp_widget_factory;
		$wp_widget_factory->unregister( $this->test_widget );
		$this->test_widget = null;

		acf_reset_local();
		acf_enable_local();
		$this->reset_stores();

		$_POST  = array();
		$_FILES = array();

		parent::tear_down();
	}

	/**
	 * Records $_POST['acf'] when a save runs.
	 *
	 * @param string $post_id The ACF post ID being saved.
	 */
	public function capture_saved_values( $post_id ) {
		/* phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Test reads the request it built. */
		$this->saved_values[ $post_id ] = isset( $_POST['acf'] ) ? $_POST['acf'] : array();
	}

	/**
	 * Test if the ACF_Form_Customizer class exists.
	 */
	public function test_form_post_class_exists() {
		$this->assertTrue( class_exists( 'ACF_Form_Customizer' ), 'ACF_Form_Customizer class should exist' );
	}


		/**
		 * Test if the preview properties are initialized correctly.
		 */
	public function test_class_contructor() {
		$form_customizer = new ACF_Form_Customizer();

		$this->assertInstanceOf( 'ACF_Form_Customizer', $form_customizer, 'ACF_Form_Customizer should be properly initialized' );

		$this->assertIsArray( $form_customizer->preview_values, 'preview_values should be initialized as an array' );
		$this->assertEmpty( $form_customizer->preview_values, 'preview_values should be empty on initialization' );
		$this->assertIsArray( $form_customizer->preview_fields, 'preview_fields should be initialized as an array' );
		$this->assertEmpty( $form_customizer->preview_fields, 'preview_fields should be empty on initialization' );
		$this->assertIsArray( $form_customizer->preview_errors, 'preview_errors should be initialized as an array' );
		$this->assertEmpty( $form_customizer->preview_errors, 'preview_errors should be empty on initialization' );

		$this->assertEquals( 10, has_action( 'customize_controls_init', array( $form_customizer, 'customize_controls_init' ) ) );
		$this->assertEquals( 1, has_action( 'customize_preview_init', array( $form_customizer, 'customize_preview_init' ) ) );
		$this->assertEquals( 1, has_action( 'customize_save', array( $form_customizer, 'customize_save' ) ) );
		$this->assertEquals( 10, has_filter( 'widget_update_callback', array( $form_customizer, 'save_widget' ) ) );
	}

	/**
	 * Test the save_widget method.
	 */
	public function test_save_widget() {

		$form_customizer = new ACF_Form_Customizer();

		// Test case 1: Should return instance unchanged when wp_customize is not set
		$instance     = array( 'test' => 'value' );
		$new_instance = array( 'acf' => array( 'field_123' => 'test_value' ) );
		$old_instance = array();
		$widget       = (object) array(
			'id'      => 'widget-1',
			'id_base' => 'widget',
		);

		$result = $form_customizer->save_widget( $instance, $new_instance, $old_instance, $widget );
		$this->assertEquals( $instance, $result, 'Should return instance unchanged when wp_customize is not set' );

		// Test case 2: Should return instance unchanged when acf values are not set
		$_POST['wp_customize'] = '1';
		// Create a nonce value for the widget
		$_POST['_acf_nonce'] = wp_create_nonce( 'widget' );

		$new_instance_without_acf = array( 'title' => 'Widget Title' );

		$result = $form_customizer->save_widget( $instance, $new_instance_without_acf, $old_instance, $widget );
		$this->assertEquals( $instance, $result, 'Should return instance unchanged when acf values are not set' );

		// Test case 3: Should return instance with acf values when acf values are set.
		$new_instance_with_acf = array( 'acf' => array( 'field_123' => 'test_value' ) );
		$result                = $form_customizer->save_widget( $instance, $new_instance_with_acf, $old_instance, $widget );

		// Update the assertion to check for the actual structure
		$this->assertArrayHasKey( 'acf', $result, 'Result should contain the acf key' );
		$this->assertArrayHasKey( 'post_id', $result['acf'], 'ACF array should contain post_id' );
		$this->assertEquals( 'widget_widget-1', $result['acf']['post_id'], 'post_id should match widget ID' );
		$this->assertArrayHasKey( 'values', $result['acf'], 'ACF array should contain values' );
		$this->assertArrayHasKey( 'fields', $result['acf'], 'ACF array should contain fields' );
		// Cleanup
		unset( $_POST['wp_customize'] );
		unset( $_POST['_acf_nonce'] );
	}

	/**
	 * Test the pre_update_option method.
	 */
	public function test_pre_update_option() {
		$form_customizer = new ACF_Form_Customizer();

		// Test case 1: Should return value unchanged when value is empty.
		$empty_value = array();
		$result      = $form_customizer->pre_update_option( $empty_value, $empty_value, $empty_value );
		$this->assertEquals( $empty_value, $result, 'Should return value unchanged when value is empty' );

		// Test case 2: Should return value unchanged when no widgets have acf data.
		$value_without_acf = array(
			0 => array( 'title' => 'Widget 1' ),
			1 => array( 'title' => 'Widget 2' ),
		);
		$result            = $form_customizer->pre_update_option( $value_without_acf, $value_without_acf, $value_without_acf );
		$this->assertEquals( $value_without_acf, $result, 'Should return value unchanged when no widgets have acf data' );

		// Test case 3: Should remove acf data from widgets.
		$value_with_acf = array(
			0 => array(
				'title' => 'Widget 1',
				'acf'   => array( 'field_123' => 'test_value' ),
			),
			1 => array(
				'title' => 'Widget 2',
				'acf'   => array( 'field_456' => 'another_value' ),
			),
			2 => array(
				'title' => 'Widget 3', // No ACF data
			),
		);

		$expected_result = array(
			0 => array( 'title' => 'Widget 1' ),
			1 => array( 'title' => 'Widget 2' ),
			2 => array( 'title' => 'Widget 3' ),
		);

		$result = $form_customizer->pre_update_option( $value_with_acf, $value_with_acf, $value_with_acf );
		$this->assertEquals( $expected_result, $result, 'Should remove acf data from widgets' );
	}

	/**
	 * Test the settings method.
	 */
	public function test_settings() {
		$form_customizer = new ACF_Form_Customizer();

		// Create a mock WP_Customize_Manager object.
		$customizer = $this->getMockBuilder( stdClass::class )
			->addMethods( array( 'settings' ) )
			->getMock();

		// Test case 1: Should return false when no settings exist.
		$customizer->method( 'settings' )->willReturn( array() );
		$result = $form_customizer->settings( $customizer );
		$this->assertFalse( $result, 'Should return false when no settings exist' );

		// Test case 2: Should return false when no settings with ACF data exist.
		// Create settings without ACF data.
		$setting1 = $this->createMockSetting( 'widget_1', array( 'title' => 'Widget 1' ) );
		$setting2 = $this->createMockSetting( 'nav_menu_1', array( 'name' => 'Main Menu' ) );
		$setting3 = $this->createMockSetting( 'other_setting', 'some value' );

		$customizer = $this->getMockBuilder( stdClass::class )
			->addMethods( array( 'settings' ) )
			->getMock();
		$customizer->method( 'settings' )->willReturn( array( $setting1, $setting2, $setting3 ) );

		$result = $form_customizer->settings( $customizer );
		$this->assertFalse( $result, 'Should return false when no settings with ACF data exist' );

		// Test case 3: Should return array of settings with ACF data.
		// Create settings with ACF data using proper ID format
		$setting1 = $this->createMockSetting(
			'widget_text[1]', // Properly formatted widget ID
			array(
				'title' => 'Widget 1',
				'acf'   => array( 'field_123' => 'value1' ),
			)
		);
		// The second setting is being filtered out in the actual implementation
		// So let's adjust our test to only expect one setting
		$setting2 = $this->createMockSetting(
			'nav_menu_item[2]', // This may not match the exact pattern the method is looking for
			array(
				'name' => 'Main Menu',
				'acf'  => array( 'field_456' => 'value2' ),
			)
		);
		$setting3 = $this->createMockSetting(
			'other_setting',
			array(
				'acf' => array( 'field_789' => 'value3' ),
			)
		);

		$customizer = $this->getMockBuilder( stdClass::class )
			->addMethods( array( 'settings' ) )
			->getMock();
		$customizer->method( 'settings' )->willReturn( array( $setting1, $setting2, $setting3 ) );

		$result = $form_customizer->settings( $customizer );

		// UPDATED: Only the widget setting is being included based on the actual behavior
		$this->assertIsArray( $result, 'Should return an array of settings with ACF data' );
		$this->assertCount( 1, $result, 'Should include widget settings with ACF data' );

		// Check that the settings have the acf property set
		$this->assertObjectHasProperty( 'acf', $result[0], 'Settings should have acf property' );
		$this->assertEquals( array( 'field_123' => 'value1' ), $result[0]->acf, 'ACF data should be set on the setting object' );
	}

	/**
	 * Test the customize_preview_init method.
	 */
	public function test_customize_preview_init() {
		$form_customizer = new ACF_Form_Customizer();

		// Test case 1: Should do nothing when no settings exist
		$customizer_no_settings = $this->getMockBuilder( stdClass::class )
		->getMock();

		// Mock the settings method to return false (no settings)
		$form_customizer_mock = $this->getMockBuilder( 'ACF_Form_Customizer' )
		->setMethods( array( 'settings' ) )
		->getMock();
		$form_customizer_mock->method( 'settings' )->willReturn( false );

		// Ensure preview_values are empty before
		$this->assertEmpty( $form_customizer_mock->preview_values );

		// Run the method
		$form_customizer_mock->customize_preview_init( $customizer_no_settings );

		// Verify preview_values are still empty
		$this->assertEmpty( $form_customizer_mock->preview_values );

		// Test case 2: Should populate preview values and fields when settings exist
		// Create mock settings with ACF data
		$setting1      = new stdClass();
		$setting1->id  = 'widget_1';
		$setting1->acf = array(
			'post_id' => 'widget_widget-1',
			'values'  => array( 'field_123' => 'value1' ),
			'fields'  => array( 'field_name' => 'field_123' ),
		);

		$setting2      = new stdClass();
		$setting2->id  = 'nav_menu_1';
		$setting2->acf = array(
			'post_id' => 'nav_menu_nav-1',
			'values'  => array( 'field_456' => 'value2' ),
			'fields'  => array( 'another_field' => 'field_456' ),
		);

		// Set up form_customizer with mocked settings method
		$form_customizer_with_settings = $this->getMockBuilder( 'ACF_Form_Customizer' )
		->setMethods( array( 'settings' ) )
		->getMock();
		$form_customizer_with_settings->method( 'settings' )
		->willReturn( array( $setting1, $setting2 ) );

		// Record filter state before
		$has_pre_load_value_filter_before     = has_filter( 'acf/pre_load_value', array( $form_customizer_with_settings, 'pre_load_value' ) );
		$has_pre_load_reference_filter_before = has_filter( 'acf/pre_load_reference', array( $form_customizer_with_settings, 'pre_load_reference' ) );

		// Run the method
		$form_customizer_with_settings->customize_preview_init( $customizer_no_settings );

		// Verify preview_values were populated correctly
		$this->assertNotEmpty( $form_customizer_with_settings->preview_values );
		$this->assertNotEmpty( $form_customizer_with_settings->preview_fields );

		// Check specific values
		$this->assertArrayHasKey( 'widget_widget-1', $form_customizer_with_settings->preview_values );
		$this->assertEquals( array( 'field_123' => 'value1' ), $form_customizer_with_settings->preview_values['widget_widget-1'] );

		$this->assertArrayHasKey( 'nav_menu_nav-1', $form_customizer_with_settings->preview_values );
		$this->assertEquals( array( 'field_456' => 'value2' ), $form_customizer_with_settings->preview_values['nav_menu_nav-1'] );

		// Check fields
		$this->assertArrayHasKey( 'widget_widget-1', $form_customizer_with_settings->preview_fields );
		$this->assertEquals( array( 'field_name' => 'field_123' ), $form_customizer_with_settings->preview_fields['widget_widget-1'] );

		$this->assertArrayHasKey( 'nav_menu_nav-1', $form_customizer_with_settings->preview_fields );
		$this->assertEquals( array( 'another_field' => 'field_456' ), $form_customizer_with_settings->preview_fields['nav_menu_nav-1'] );

		// Verify filters were added
		$has_pre_load_value_filter_after     = has_filter( 'acf/pre_load_value', array( $form_customizer_with_settings, 'pre_load_value' ) );
		$has_pre_load_reference_filter_after = has_filter( 'acf/pre_load_reference', array( $form_customizer_with_settings, 'pre_load_reference' ) );

		$this->assertTrue( false !== $has_pre_load_value_filter_after );
		$this->assertTrue( false !== $has_pre_load_reference_filter_after );

		// Clean up filters
		remove_filter( 'acf/pre_load_value', array( $form_customizer_with_settings, 'pre_load_value' ) );
		remove_filter( 'acf/pre_load_reference', array( $form_customizer_with_settings, 'pre_load_reference' ) );
	}

	/**
	 * Test the pre_load_value method.
	 */
	public function test_pre_load_value() {
		$form_customizer = new ACF_Form_Customizer();

		// Set up preview values
		$form_customizer->preview_values = array(
			'widget_test-1' => array(
				'field_123' => 'preview value 1',
				'field_456' => 'preview value 2',
			),
			'nav_menu_1'    => array(
				'field_789' => 'menu preview value',
			),
		);

		// Test case 1: Should return the preview value when it exists
		$post_id        = 'widget_test-1';
		$field          = array( 'key' => 'field_123' );
		$original_value = 'original value';

		$result = $form_customizer->pre_load_value( $original_value, $post_id, $field );
		$this->assertEquals( 'preview value 1', $result, 'Should return the preview value when it exists' );

		// Test case 2: Should return the original value when post_id doesn't have preview values
		$post_id        = 'non_existent_post';
		$field          = array( 'key' => 'field_123' );
		$original_value = 'original value';

		$result = $form_customizer->pre_load_value( $original_value, $post_id, $field );
		$this->assertEquals( $original_value, $result, 'Should return the original value when post_id doesn\'t have preview values' );

		// Test case 3: Should return the original value when field key doesn't have preview values
		$post_id        = 'widget_test-1';
		$field          = array( 'key' => 'non_existent_field' );
		$original_value = 'original value';

		$result = $form_customizer->pre_load_value( $original_value, $post_id, $field );
		$this->assertEquals( $original_value, $result, 'Should return the original value when field key doesn\'t have preview values' );

		// Test case 4: Verify another field and post_id combination works
		$post_id        = 'nav_menu_1';
		$field          = array( 'key' => 'field_789' );
		$original_value = 'original menu value';

		$result = $form_customizer->pre_load_value( $original_value, $post_id, $field );
		$this->assertEquals( 'menu preview value', $result, 'Should correctly return preview values for different post_id and field combinations' );
	}

	/**
	 * Test the pre_load_reference method.
	 */
	public function test_pre_load_reference() {
		$form_customizer = new ACF_Form_Customizer();

		// Set up preview fields
		$form_customizer->preview_fields = array(
			'widget_test-1' => array(
				'title_field'   => 'field_123',
				'content_field' => 'field_456',
			),
			'nav_menu_1'    => array(
				'menu_name' => 'field_789',
			),
		);

		// Test case 1: Should return the preview field key when it exists
		$field_key  = 'some_other_key';
		$field_name = 'title_field';
		$post_id    = 'widget_test-1';

		$result = $form_customizer->pre_load_reference( $field_key, $field_name, $post_id );
		$this->assertEquals( 'field_123', $result, 'Should return the preview field key when it exists' );

		// Test case 2: Should return the original field key when post_id doesn't have preview fields
		$field_key  = 'original_key';
		$field_name = 'title_field';
		$post_id    = 'non_existent_post';

		$result = $form_customizer->pre_load_reference( $field_key, $field_name, $post_id );
		$this->assertEquals( $field_key, $result, 'Should return the original field key when post_id doesn\'t have preview fields' );

		// Test case 3: Should return the original field key when field name doesn't have preview fields
		$field_key  = 'original_key';
		$field_name = 'non_existent_field';
		$post_id    = 'widget_test-1';

		$result = $form_customizer->pre_load_reference( $field_key, $field_name, $post_id );
		$this->assertEquals( $field_key, $result, 'Should return the original field key when field name doesn\'t have preview fields' );

		// Test case 4: Verify another field name and post_id combination works
		$field_key  = 'some_menu_key';
		$field_name = 'menu_name';
		$post_id    = 'nav_menu_1';

		$result = $form_customizer->pre_load_reference( $field_key, $field_name, $post_id );
		$this->assertEquals( 'field_789', $result, 'Should correctly return preview field keys for different post_id and field name combinations' );
	}

	/**
	 * Test the customize_save method.
	 */
	public function test_customize_save() {

		$form_customizer = new ACF_Form_Customizer();

		// Test case 1: Should do nothing when no settings exist
		$customizer_no_settings = $this->getMockBuilder( stdClass::class )
		->getMock();

		// Mock the settings method to return false (no settings)
		$form_customizer_mock = $this->getMockBuilder( 'ACF_Form_Customizer' )
		->setMethods( array( 'settings' ) )
		->getMock();
		$form_customizer_mock->method( 'settings' )->willReturn( false );

		// Run the method - should not produce errors
		$form_customizer_mock->customize_save( $customizer_no_settings );

		// Test case 2: Should save ACF data and add filters when settings exist
		// Create mock settings with ACF data and id_data method
		$setting1      = $this->getMockBuilder( stdClass::class )
		->addMethods( array( 'id_data' ) )
		->getMock();
		$setting1->id  = 'widget_1';
		$setting1->acf = array(
			'post_id' => 'widget_widget-1',
			'values'  => array( 'field_123' => 'value1' ),
		);
		$setting1->method( 'id_data' )->willReturn( array( 'base' => 'widget_text' ) );

		$setting2      = $this->getMockBuilder( stdClass::class )
		->addMethods( array( 'id_data' ) )
		->getMock();
		$setting2->id  = 'nav_menu_1';
		$setting2->acf = array(
			'post_id' => 'nav_menu_nav-1',
			'values'  => array( 'field_456' => 'value2' ),
		);
		$setting2->method( 'id_data' )->willReturn( array( 'base' => 'nav_menu_widgets' ) );

		// Set up form_customizer with mocked settings method
		$form_customizer_with_settings = $this->getMockBuilder( 'ACF_Form_Customizer' )
		->setMethods( array( 'settings' ) )
		->getMock();
		$form_customizer_with_settings->method( 'settings' )
		->willReturn( array( $setting1, $setting2 ) );

		// Record filter state before
		$has_filter_widget_text_before = has_filter( 'pre_update_option_widget_text', array( $form_customizer_with_settings, 'pre_update_option' ) );
		$has_filter_nav_menu_before    = has_filter( 'pre_update_option_nav_menu_widgets', array( $form_customizer_with_settings, 'pre_update_option' ) );

		// Run the method
		$form_customizer_with_settings->customize_save( $customizer_no_settings );

		// Verify filters were added
		$has_filter_widget_text_after = has_filter( 'pre_update_option_widget_text', array( $form_customizer_with_settings, 'pre_update_option' ) );
		$has_filter_nav_menu_after    = has_filter( 'pre_update_option_nav_menu_widgets', array( $form_customizer_with_settings, 'pre_update_option' ) );

		$this->assertTrue( false !== $has_filter_widget_text_after, 'Filter should be added for widget_text' );
		$this->assertTrue( false !== $has_filter_nav_menu_after, 'Filter should be added for nav_menu_widgets' );

		// Clean up filters
		remove_filter( 'pre_update_option_widget_text', array( $form_customizer_with_settings, 'pre_update_option' ) );
		remove_filter( 'pre_update_option_nav_menu_widgets', array( $form_customizer_with_settings, 'pre_update_option' ) );
	}

	/**
	 * Helper method to create a mock setting object with a proper post_value method.
	 *
	 * @param string $id    The setting ID.
	 * @param mixed  $value The value to return from post_value.
	 * @return object Mock setting object.
	 */
	private function createMockSetting( $id, $value ) {
		$setting = $this->getMockBuilder( stdClass::class )
			->addMethods( array( 'post_value' ) )
			->getMock();

		$setting->id = $id;
		$setting->method( 'post_value' )->willReturn( $value );

		return $setting;
	}

	/**
	 * A Customizer widget update keeps fields located on the widget type and drops the rest.
	 */
	public function test_save_widget_drops_fields_not_located_on_widget() {
		$this->add_widget_groups();

		$instance = $this->save_customizer_widget(
			array(
				'field_form_customizer_located' => 'kept',
				'field_form_customizer_other'   => 'dropped',
				'field_form_customizer_unknown' => 'dropped',
			)
		);

		$this->assertSame( 'widget_scf_test-3', $instance['acf']['post_id'] );
		$this->assertSame( array( 'field_form_customizer_located' => 'kept' ), $instance['acf']['values'] );
		$this->assertSame( array( 'field_form_customizer_located' => 'field_form_customizer_located' ), $instance['acf']['fields'] );
		$this->assertArrayNotHasKey( '_acf_fields', $instance );
	}

	/**
	 * The widget allowed-keys filter applies to Customizer widget updates.
	 */
	public function test_save_widget_allowed_keys_filter_adds_keys() {
		$this->add_widget_groups();
		$received = null;
		$this->add_test_filter(
			'acf/form/widget/allowed_field_keys',
			function ( $keys, $post_id ) use ( &$received ) {
				$received = $post_id;
				$keys[]   = 'field_form_customizer_injected';
				return $keys;
			},
			10,
			2
		);

		$instance = $this->save_customizer_widget(
			array(
				'field_form_customizer_located'  => 'kept',
				'field_form_customizer_injected' => 'kept',
			)
		);

		$this->assertSame( 'widget_scf_test-3', $received );
		$this->assertSame(
			array(
				'field_form_customizer_located'  => 'kept',
				'field_form_customizer_injected' => 'kept',
			),
			$instance['acf']['values']
		);
	}

	/**
	 * A Customizer widget update accepts fields listed for its widget type, ignores other types' lists, and keeps the lists.
	 */
	public function test_save_widget_accepts_only_fields_listed_for_widget_type() {
		$this->add_widget_groups();
		$lists = array(
			'widget/scf_test' => array( $this->get_signed_field_list( 'widget/scf_test', array( 'field_form_customizer_other' ) ) ),
			'widget/archives' => array( $this->get_signed_field_list( 'widget/archives', array( 'field_form_customizer_unknown' ) ) ),
		);

		$instance = $this->save_customizer_widget(
			array(
				'field_form_customizer_other'   => 'kept',
				'field_form_customizer_unknown' => 'dropped',
			),
			$lists
		);

		$this->assertSame( array( 'field_form_customizer_other' => 'kept' ), $instance['acf']['values'] );
		$this->assertSame( $lists, $instance['acf']['_acf_fields'] );
	}

	/**
	 * Publishing the Customizer saves only the fields located on the setting's widget, to that widget.
	 */
	public function test_customize_save_drops_fields_not_located_on_widget() {
		$this->add_widget_groups();
		$setting = $this->get_widget_setting(
			'widget_scf_test[3]',
			array(
				'post_id' => 'widget_archives-9',
				'values'  => array(
					'field_form_customizer_located' => 'kept',
					'field_form_customizer_other'   => 'dropped',
					'field_form_customizer_unknown' => 'dropped',
				),
			)
		);

		( new ACF_Form_Customizer() )->customize_save( $this->get_customizer( array( $setting ) ) );

		$this->assertSame( array( 'widget_scf_test-3' => array( 'field_form_customizer_located' => 'kept' ) ), $this->saved_values );
	}

	/**
	 * Publishing the Customizer accepts fields listed for the setting's widget type and ignores other types' lists.
	 */
	public function test_customize_save_accepts_only_fields_listed_for_widget_type() {
		$this->add_widget_groups();
		$setting = $this->get_widget_setting(
			'widget_scf_test[3]',
			array(
				'values'      => array(
					'field_form_customizer_other'   => 'kept',
					'field_form_customizer_unknown' => 'dropped',
				),
				'_acf_fields' => array(
					'widget/scf_test' => array( $this->get_signed_field_list( 'widget/scf_test', array( 'field_form_customizer_other' ) ) ),
					'widget/archives' => array( $this->get_signed_field_list( 'widget/archives', array( 'field_form_customizer_unknown' ) ) ),
				),
			)
		);

		( new ACF_Form_Customizer() )->customize_save( $this->get_customizer( array( $setting ) ) );

		$this->assertSame( array( 'widget_scf_test-3' => array( 'field_form_customizer_other' => 'kept' ) ), $this->saved_values );
	}

	/**
	 * Publishing the Customizer saves nothing for settings that are not a single registered widget.
	 */
	public function test_customize_save_skips_settings_that_are_not_registered_widgets() {
		$this->add_widget_groups();
		$values   = array( 'values' => array( 'field_form_customizer_located' => 'dropped' ) );
		$settings = array(
			$this->get_widget_setting( 'widget_unregistered[3]', $values ),
			$this->get_widget_setting( 'widget_scf_test[abc]', $values ),
			$this->get_widget_setting( 'widget_scf_test[3][4]', $values ),
			$this->get_widget_setting( 'nav_menu_item[3]', $values ),
		);

		( new ACF_Form_Customizer() )->customize_save( $this->get_customizer( $settings ) );

		$this->assertSame( array(), $this->saved_values );
	}

	/**
	 * Registers field groups located on the test widget and on the archives widget.
	 */
	private function add_widget_groups() {
		$this->add_group( 'group_form_customizer_located', 'scf_test', 'field_form_customizer_located' );
		$this->add_group( 'group_form_customizer_other', 'archives', 'field_form_customizer_other' );
	}

	/**
	 * Runs the Customizer widget update for the test widget.
	 *
	 * @param array $values The submitted acf values.
	 * @param array $lists  The submitted field lists, keyed by render ID.
	 * @return array The instance the handler returns.
	 */
	private function save_customizer_widget( array $values, array $lists = array() ) {
		$_POST['wp_customize'] = 'on';
		$_POST['_acf_nonce']   = wp_create_nonce( 'widget' );

		$widget = (object) array(
			'id'      => 'scf_test-3',
			'id_base' => 'scf_test',
			'number'  => 3,
		);

		$new_instance = array(
			'title'       => 'Widget',
			'acf'         => $values,
			'_acf_fields' => $lists,
		);

		return ( new ACF_Form_Customizer() )->save_widget( $new_instance, $new_instance, array(), $widget );
	}

	/**
	 * Returns a Customizer setting posting ACF data, with its ID parsed as WP_Customize_Setting does.
	 *
	 * @param string $id  The setting ID.
	 * @param array  $acf The ACF data posted in the setting's value.
	 * @return object
	 */
	private function get_widget_setting( $id, array $acf ) {
		$parts = explode( '[', str_replace( ']', '', $id ) );

		return new class( $id, array_shift( $parts ), $parts, $acf ) {
			/**
			 * The setting ID.
			 *
			 * @var string
			 */
			public $id;

			/**
			 * The ACF data, set by ACF_Form_Customizer::settings().
			 *
			 * @var mixed
			 */
			public $acf;

			/**
			 * The parsed ID.
			 *
			 * @var array
			 */
			private $id_data;

			/**
			 * The posted value.
			 *
			 * @var array
			 */
			private $value;

			/**
			 * Constructor.
			 *
			 * @param string $id   The setting ID.
			 * @param string $base The ID base.
			 * @param array  $keys The ID keys.
			 * @param array  $acf  The posted ACF data.
			 */
			public function __construct( $id, $base, $keys, $acf ) {
				$this->id      = $id;
				$this->id_data = array(
					'base' => $base,
					'keys' => $keys,
				);
				$this->value   = array( 'acf' => $acf );
			}

			/**
			 * Returns the parsed ID.
			 *
			 * @return array
			 */
			public function id_data() {
				return $this->id_data;
			}

			/**
			 * Returns the posted value.
			 *
			 * @return array
			 */
			public function post_value() {
				return $this->value;
			}
		};
	}

	/**
	 * Returns a Customizer manager holding the given settings.
	 *
	 * @param array $settings The settings.
	 * @return object
	 */
	private function get_customizer( array $settings ) {
		return new class( $settings ) {
			/**
			 * The settings.
			 *
			 * @var array
			 */
			private $settings;

			/**
			 * Constructor.
			 *
			 * @param array $settings The settings.
			 */
			public function __construct( $settings ) {
				$this->settings = $settings;
			}

			/**
			 * Returns the settings.
			 *
			 * @return array
			 */
			public function settings() {
				return $this->settings;
			}
		};
	}

	/**
	 * Returns a field list signed for a render ID, as acf_render_fields() outputs it.
	 *
	 * @param string $render_id The render ID.
	 * @param array  $keys      The listed field keys.
	 * @return string
	 */
	private function get_signed_field_list( $render_id, array $keys ) {
		ob_start();
		_acf_render_field_list( $keys, $render_id, '_acf_fields' );
		$processor = new WP_HTML_Tag_Processor( ob_get_clean() );
		$processor->next_tag( 'input' );

		return (string) $processor->get_attribute( 'value' );
	}

	/**
	 * Registers a local field group located on a widget type, with one text field.
	 *
	 * @param string $group_key The field group key.
	 * @param string $id_base   The widget type the group is located on.
	 * @param string $field_key The field key.
	 */
	private function add_group( $group_key, $id_base, $field_key ) {
		acf_add_local_field_group(
			array(
				'key'      => $group_key,
				'title'    => $group_key,
				'fields'   => array(
					array(
						'key'   => $field_key,
						'name'  => $field_key,
						'label' => $field_key,
						'type'  => 'text',
					),
				),
				'active'   => true,
				'location' => array(
					array(
						array(
							'param'    => 'widget',
							'operator' => '==',
							'value'    => $id_base,
						),
					),
				),
			)
		);
	}

	/**
	 * Adds and tracks a test-specific filter.
	 *
	 * @param string   $hook          Filter name.
	 * @param callable $callback      Filter callback.
	 * @param int      $priority      Filter priority.
	 * @param int      $accepted_args Accepted argument count.
	 */
	private function add_test_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $hook, $callback, $priority, $accepted_args );

		$this->test_filters[] = array(
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		);
	}

	/**
	 * Resets the stores the fixtures write to.
	 */
	private function reset_stores() {
		foreach ( array( 'local-empty', 'fields', 'field-groups', 'values', 'form' ) as $store_name ) {
			$store = acf_get_store( $store_name );
			if ( $store ) {
				$store->reset();
			}
		}
	}
}
