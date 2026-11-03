<?php
/**
 * Test acf_form_widget class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the acf_form_widget class.
acf_include( 'includes/forms/form-widget.php' );

/**
 * Class Test_Form_Widget
 */
class Test_Form_Widget extends BaseTestCase {

	/**
	 * $_POST['acf'] as seen by acf/save_post, or null when no save ran.
	 *
	 * @var array|null
	 */
	private $saved_values;

	/**
	 * ACF post ID the last save ran for.
	 *
	 * @var string|null
	 */
	private $saved_post_id;

	/**
	 * Test filters that must be removed during cleanup.
	 *
	 * @var array
	 */
	private $test_filters = array();

	/**
	 * Registers the field type and location the save tests use.
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

		$this->saved_values  = null;
		$this->saved_post_id = null;
		$this->add_test_filter( 'acf/save_post', array( $this, 'capture_saved_values' ), 1 );

		$_POST  = array();
		$_FILES = array();
	}

	/**
	 * Removes local fields, request data and filters.
	 */
	public function tear_down() {
		foreach ( $this->test_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}
		$this->test_filters = array();

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
		$this->saved_post_id = $post_id;
		/* phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Test reads the request it built. */
		$this->saved_values = isset( $_POST['acf'] ) ? $_POST['acf'] : array();
	}

	/**
	 * Test if the acf_form_widget class exists.
	 */
	public function test_form_widget_class_exists() {
		$this->assertTrue( class_exists( 'acf_form_widget' ), 'acf_form_widget class should exist' );
	}

	/**
	 * Test if the acf_form_widget class is properly initialized.
	 */
	public function test_form_widget_initialization() {
		$form_widget = new acf_form_widget();

		$this->assertInstanceOf( 'acf_form_widget', $form_widget, 'acf_form_widget should be properly initialized' );
	}

	/**
	 * Test constructor registers correct actions.
	 */
	public function test_constructor_registers_actions() {
		$form_widget = new acf_form_widget();

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', array( $form_widget, 'admin_enqueue_scripts' ) ),
			'Should register admin_enqueue_scripts action'
		);

		$this->assertNotFalse(
			has_action( 'in_widget_form', array( $form_widget, 'edit_widget' ) ),
			'Should register in_widget_form action'
		);

		$this->assertNotFalse(
			has_action( 'acf/validate_save_post', array( $form_widget, 'acf_validate_save_post' ) ),
			'Should register acf_validate_save_post action'
		);

		$this->assertNotFalse(
			has_filter( 'widget_update_callback', array( $form_widget, 'save_widget' ) ),
			'Should register widget_update_callback filter'
		);
	}

	/**
	 * Test acf_validate_save_post returns early without widget_id.
	 *
	 * This test verifies the early-exit code path executes without error.
	 */
	public function test_acf_validate_save_post_returns_early_without_widget_id() {
		$form_widget = new acf_form_widget();

		// Clear any widget ID.
		unset( $_POST['_acf_widget_id'] );

		// Verify no validation errors are generated when widget ID is empty.
		acf_reset_validation_errors();
		$form_widget->acf_validate_save_post();
		$errors = acf_get_validation_errors();

		$this->assertEmpty( $errors, 'No validation errors should be generated when no widget ID' );
	}

	/**
	 * Test acf_validate_save_post processes widget values.
	 *
	 * This test verifies the method executes the validation code path.
	 * Since test field keys don't exist, no actual validation errors occur.
	 */
	public function test_acf_validate_save_post_processes_widget_values() {
		$form_widget = new acf_form_widget();

		$_POST['_acf_widget_id']     = 'widget-test_widget';
		$_POST['_acf_widget_number'] = '2';
		$_POST['_acf_widget_prefix'] = 'widget-test_widget[2][acf]';
		$_POST['widget-test_widget'] = array(
			'2' => array(
				'acf' => array(
					'field_test' => 'test value',
				),
			),
		);

		// Verify method executes without error and processes the values.
		acf_reset_validation_errors();
		$form_widget->acf_validate_save_post();
		$errors = acf_get_validation_errors();

		// Cleanup.
		unset( $_POST['_acf_widget_id'] );
		unset( $_POST['_acf_widget_number'] );
		unset( $_POST['_acf_widget_prefix'] );
		unset( $_POST['widget-test_widget'] );

		// No errors expected since field_test doesn't exist as a registered field.
		$this->assertEmpty( $errors, 'No validation errors should occur for non-existent fields' );
	}

	/**
	 * Test save_widget returns instance when nonce fails.
	 */
	public function test_save_widget_returns_instance_when_nonce_fails() {
		$form_widget = new acf_form_widget();

		$instance     = array( 'title' => 'Test Widget' );
		$new_instance = array( 'title' => 'New Title' );
		$old_instance = array( 'title' => 'Old Title' );
		$widget       = new stdClass();
		$widget->id   = 'test_widget-1';

		// Clear any nonce.
		unset( $_POST['_acf_nonce'] );

		$result = $form_widget->save_widget( $instance, $new_instance, $old_instance, $widget );

		$this->assertEquals( $instance, $result, 'Should return instance when nonce verification fails' );
	}

	/**
	 * Test save_widget returns instance for customizer.
	 */
	public function test_save_widget_returns_instance_for_customizer() {
		$form_widget = new acf_form_widget();

		$instance     = array( 'title' => 'Test Widget' );
		$new_instance = array( 'title' => 'New Title' );
		$old_instance = array( 'title' => 'Old Title' );
		$widget       = new stdClass();
		$widget->id   = 'test_widget-1';

		// Set up nonce but simulate customizer.
		$_POST['_acf_screen']  = 'widget';
		$_POST['_acf_nonce']   = wp_create_nonce( 'widget' );
		$_POST['wp_customize'] = 'on';

		$result = $form_widget->save_widget( $instance, $new_instance, $old_instance, $widget );

		$this->assertEquals( $instance, $result, 'Should return instance for customizer requests' );

		// Cleanup.
		unset( $_POST['_acf_screen'] );
		unset( $_POST['_acf_nonce'] );
		unset( $_POST['wp_customize'] );
	}

	/**
	 * Test save_widget returns instance when no acf data.
	 */
	public function test_save_widget_returns_instance_without_acf_data() {
		$form_widget = new acf_form_widget();

		$instance     = array( 'title' => 'Test Widget' );
		$new_instance = array( 'title' => 'New Title' );
		$old_instance = array( 'title' => 'Old Title' );
		$widget       = new stdClass();
		$widget->id   = 'test_widget-1';

		// Set up valid nonce but no ACF data.
		$_POST['_acf_screen'] = 'widget';
		$_POST['_acf_nonce']  = wp_create_nonce( 'widget' );

		$result = $form_widget->save_widget( $instance, $new_instance, $old_instance, $widget );

		$this->assertEquals( $instance, $result, 'Should return instance when no ACF data' );

		// Cleanup.
		unset( $_POST['_acf_screen'] );
		unset( $_POST['_acf_nonce'] );
	}

	/**
	 * Test edit_widget renders nothing without field groups.
	 */
	public function test_edit_widget_renders_nothing_without_field_groups() {
		$form_widget = new acf_form_widget();

		$widget          = new stdClass();
		$widget->id_base = 'test_widget';
		$widget->id      = 'test_widget-1';
		$widget->number  = '1';
		$widget->updated = false;
		$return          = null;
		$instance        = array();

		// Return no field groups.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		ob_start();
		$form_widget->edit_widget( $widget, $return, $instance );
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Should render nothing when no field groups match' );
	}

	/**
	 * Test admin_footer outputs JavaScript.
	 */
	public function test_admin_footer_outputs_script() {
		$form_widget = new acf_form_widget();

		ob_start();
		$form_widget->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script type="text/javascript">', $output, 'Should output script tag' );
		$this->assertStringContainsString( "acf.set('post_id', 'widgets')", $output, 'Should set post_id to widgets' );
		$this->assertStringContainsString( '#widgets-right', $output, 'Should reference widgets-right container' );
	}

	/**
	 * Test admin_footer includes validation handler.
	 */
	public function test_admin_footer_includes_validation() {
		$form_widget = new acf_form_widget();

		ob_start();
		$form_widget->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'acf.validateForm', $output, 'Should include form validation' );
		$this->assertStringContainsString( '.widget-control-save', $output, 'Should handle save button click' );
	}

	/**
	 * Test admin_footer includes field filtering.
	 */
	public function test_admin_footer_includes_field_filtering() {
		$form_widget = new acf_form_widget();

		ob_start();
		$form_widget->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( "acf.addFilter('find_fields'", $output, 'Should add find_fields filter' );
		$this->assertStringContainsString( '#available-widgets', $output, 'Should filter out available widgets' );
	}

	/**
	 * Test class has preview properties.
	 */
	public function test_class_has_preview_properties() {
		$form_widget = new acf_form_widget();

		$this->assertIsArray( $form_widget->preview_values, 'Should have preview_values array' );
		$this->assertIsArray( $form_widget->preview_reference, 'Should have preview_reference array' );
		$this->assertIsArray( $form_widget->preview_errors, 'Should have preview_errors array' );
	}

	/**
	 * Data provider for widget save scenarios.
	 *
	 * @return array
	 */
	public function save_scenarios_provider() {
		return array(
			'no_nonce'        => array(
				'nonce'       => '',
				'has_acf'     => true,
				'customizer'  => false,
				'should_save' => false,
			),
			'with_customizer' => array(
				'nonce'       => 'valid',
				'has_acf'     => true,
				'customizer'  => true,
				'should_save' => false,
			),
			'no_acf_data'     => array(
				'nonce'       => 'valid',
				'has_acf'     => false,
				'customizer'  => false,
				'should_save' => false,
			),
		);
	}

	/**
	 * Test save_widget with various scenarios.
	 *
	 * @dataProvider save_scenarios_provider
	 *
	 * @param string $nonce       The nonce value.
	 * @param bool   $has_acf     Whether ACF data is present.
	 * @param bool   $customizer  Whether customizer is active.
	 * @param bool   $should_save Whether save should proceed.
	 */
	public function test_save_widget_scenarios( $nonce, $has_acf, $customizer, $should_save ) {
		$form_widget = new acf_form_widget();

		$instance     = array( 'title' => 'Original' );
		$new_instance = $has_acf ? array(
			'title' => 'New',
			'acf'   => array(),
		) : array( 'title' => 'New' );
		$old_instance = array( 'title' => 'Old' );
		$widget       = new stdClass();
		$widget->id   = 'test_widget-1';

		if ( 'valid' === $nonce ) {
			$_POST['_acf_screen'] = 'widget';
			$_POST['_acf_nonce']  = wp_create_nonce( 'widget' );
		}

		if ( $customizer ) {
			$_POST['wp_customize'] = 'on';
		}

		$result = $form_widget->save_widget( $instance, $new_instance, $old_instance, $widget );

		// In all test scenarios, the result should be the original instance.
		$this->assertEquals( $instance, $result );

		// Cleanup.
		unset( $_POST['_acf_screen'] );
		unset( $_POST['_acf_nonce'] );
		unset( $_POST['wp_customize'] );
	}

	/**
	 * Test admin_enqueue_scripts returns early when not on widgets screen.
	 *
	 * In test environment, get_current_screen() returns null, so acf_is_screen()
	 * returns false, triggering the early return path.
	 */
	public function test_admin_enqueue_scripts_bails_when_not_on_widgets_screen() {
		$form_widget = new acf_form_widget();

		// Call admin_enqueue_scripts - should return early since no screen is set.
		$form_widget->admin_enqueue_scripts();

		// acf/input/admin_footer action should NOT be added when not on widgets screen.
		$this->assertFalse(
			has_action( 'acf/input/admin_footer', array( $form_widget, 'admin_footer' ) ),
			'admin_footer action should not be added when not on widgets screen'
		);
	}

	/**
	 * A widget save keeps fields from groups located on the widget type and drops the rest.
	 */
	public function test_save_widget_drops_fields_not_located_on_widget() {
		$this->add_widget_groups();

		$instance = $this->save_widget(
			array(
				'field_form_widget_text'    => 'kept',
				'field_form_widget_archive' => 'dropped',
				'field_form_widget_unknown' => 'dropped',
			)
		);

		$this->assertSame( 'widget_text-2', $this->saved_post_id );
		$this->assertSame( array( 'field_form_widget_text' => 'kept' ), $this->saved_values );
		$this->assertArrayNotHasKey( '_acf_fields', $instance );
	}

	/**
	 * The widget allowed-keys filter can allow extra keys, and receives the widget's ACF post ID.
	 */
	public function test_save_widget_allowed_keys_filter_adds_keys() {
		$this->add_widget_groups();
		$received = null;
		$this->add_test_filter(
			'acf/form/widget/allowed_field_keys',
			function ( $keys, $post_id ) use ( &$received ) {
				$received = $post_id;
				$keys[]   = 'field_form_widget_injected';
				return $keys;
			},
			10,
			2
		);

		$this->save_widget(
			array(
				'field_form_widget_text'     => 'kept',
				'field_form_widget_injected' => 'kept',
			)
		);

		$this->assertSame( 'widget_text-2', $received );
		$this->assertSame(
			array(
				'field_form_widget_text'     => 'kept',
				'field_form_widget_injected' => 'kept',
			),
			$this->saved_values
		);
	}

	/**
	 * A widget save accepts fields listed for its widget type and ignores lists signed for another type.
	 */
	public function test_save_widget_accepts_only_fields_listed_for_widget_type() {
		$this->add_widget_groups();

		$this->save_widget(
			array(
				'field_form_widget_archive' => 'kept',
				'field_form_widget_unknown' => 'dropped',
			),
			array(
				'widget/text'     => array( $this->get_signed_field_list( 'widget/text', array( 'field_form_widget_archive' ) ) ),
				'widget/archives' => array( $this->get_signed_field_list( 'widget/archives', array( 'field_form_widget_unknown' ) ) ),
			)
		);

		$this->assertSame( array( 'field_form_widget_archive' => 'kept' ), $this->saved_values );
	}

	/**
	 * Filtering values for one widget type ignores a list signed for another type.
	 */
	public function test_filter_values_ignores_list_signed_for_another_widget_type() {
		$this->add_widget_groups();
		$lists = array(
			'widget/text' => array( $this->get_signed_field_list( 'widget/text', array( 'field_form_widget_unknown' ) ) ),
		);

		$values = ( new acf_form_widget() )->filter_values(
			array(
				'field_form_widget_archive' => 'kept',
				'field_form_widget_unknown' => 'dropped',
			),
			$lists,
			'archives',
			'widget_archives-3'
		);

		$this->assertSame( array( 'field_form_widget_archive' => 'kept' ), $values );
	}

	/**
	 * Registers field groups located on the text and archives widgets.
	 */
	private function add_widget_groups() {
		$this->add_group( 'group_form_widget_text', 'text', 'field_form_widget_text' );
		$this->add_group( 'group_form_widget_archive', 'archives', 'field_form_widget_archive' );
	}

	/**
	 * Saves a text widget's instance through the widgets screen handler.
	 *
	 * @param array $values The submitted acf values.
	 * @param array $lists  The submitted field lists, keyed by render ID.
	 * @return array The instance the handler returns.
	 */
	private function save_widget( array $values, array $lists = array() ) {
		$_POST['_acf_screen'] = 'widget';
		$_POST['_acf_nonce']  = wp_create_nonce( 'widget' );

		$widget          = new stdClass();
		$widget->id      = 'text-2';
		$widget->id_base = 'text';
		$widget->number  = 2;

		$new_instance = array(
			'title'       => 'Widget',
			'acf'         => $values,
			'_acf_fields' => $lists,
		);

		return ( new acf_form_widget() )->save_widget( $new_instance, $new_instance, array(), $widget );
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
