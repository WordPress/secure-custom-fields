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
		$_POST['wp_customize']    = '1';
		$new_instance_without_acf = array( 'title' => 'Widget Title' );

		$result = $form_customizer->save_widget( $instance, $new_instance_without_acf, $old_instance, $widget );
		$this->assertEquals( $instance, $result, 'Should return instance unchanged when acf values are not set' );

		// Cleanup
		unset( $_POST['wp_customize'] );
	}
}
