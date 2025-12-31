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
}
