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
		$widget       = (object) array( 'id' => 'widget-1' );

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

		// Test case 1: Should return value unchanged when value is empty
		$empty_value = array();
		$result      = $form_customizer->pre_update_option( $empty_value );
		$this->assertEquals( $empty_value, $result, 'Should return value unchanged when value is empty' );

		// Test case 2: Should return value unchanged when no widgets have acf data
		$value_without_acf = array(
			0 => array( 'title' => 'Widget 1' ),
			1 => array( 'title' => 'Widget 2' ),
		);
		$result            = $form_customizer->pre_update_option( $value_without_acf );
		$this->assertEquals( $value_without_acf, $result, 'Should return value unchanged when no widgets have acf data' );

		// Test case 3: Should remove acf data from widgets
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

		$result = $form_customizer->pre_update_option( $value_with_acf );
		$this->assertEquals( $expected_result, $result, 'Should remove acf data from widgets' );
	}
}
