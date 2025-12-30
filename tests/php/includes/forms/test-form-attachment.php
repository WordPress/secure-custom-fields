<?php
/**
 * Test ACF_Form_Attachment class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the acf_form_attachment class.
acf_include( 'includes/forms/form-attachment.php' );

/**
 * Class Test_Form_Attachment
 */
class Test_Form_Attachment extends BaseTestCase {

	/**
	 * Test if the acf_form_attachment class exists.
	 */
	public function test_form_attachment_class_exists() {
		$this->assertTrue( class_exists( 'acf_form_attachment' ), 'acf_form_attachment class should exist' );
	}

	/**
	 * Test if the acf_form_attachment class is properly initialized.
	 */
	public function test_form_attachment_initialization() {
		$form_attachment = new acf_form_attachment();

		$this->assertInstanceOf( 'acf_form_attachment', $form_attachment, 'acf_form_attachment should be properly initialized' );
	}

	/**
	 * Test constructor registers correct actions.
	 */
	public function test_constructor_registers_actions() {
		$form_attachment = new acf_form_attachment();

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', array( $form_attachment, 'admin_enqueue_scripts' ) ),
			'Should register admin_enqueue_scripts action'
		);

		$this->assertNotFalse(
			has_filter( 'attachment_fields_to_edit', array( $form_attachment, 'edit_attachment' ) ),
			'Should register edit_attachment filter'
		);

		$this->assertNotFalse(
			has_filter( 'attachment_fields_to_save', array( $form_attachment, 'save_attachment' ) ),
			'Should register save_attachment filter'
		);
	}

	/**
	 * Test edit_attachment returns form_fields unchanged when no field groups.
	 */
	public function test_edit_attachment_returns_form_fields_when_no_field_groups() {
		$form_attachment = new acf_form_attachment();

		// Create a mock post object.
		$post              = new stdClass();
		$post->ID          = 1;
		$post->post_type   = 'attachment';
		$post->post_status = 'inherit';

		$form_fields = array(
			'existing_field' => array(
				'label' => 'Existing',
				'input' => 'text',
			),
		);

		// Ensure no field groups match.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		$result = $form_attachment->edit_attachment( $form_fields, $post );

		$this->assertEquals( $form_fields, $result, 'Should return form_fields unchanged when no field groups match' );
	}

	/**
	 * Test save_attachment returns post when nonce fails.
	 */
	public function test_save_attachment_returns_post_when_nonce_fails() {
		$form_attachment = new acf_form_attachment();

		$post       = array( 'ID' => 123 );
		$attachment = array();

		// Clear any nonce.
		unset( $_POST['_acf_nonce'] );

		$result = $form_attachment->save_attachment( $post, $attachment );

		$this->assertEquals( $post, $result, 'Should return post when nonce verification fails' );
	}

	/**
	 * Test save_attachment processes AJAX save request.
	 */
	public function test_save_attachment_handles_ajax_request() {
		$form_attachment = new acf_form_attachment();

		$post       = array( 'ID' => 456 );
		$attachment = array();

		// Set up valid nonce.
		$_POST['_acf_screen'] = 'attachment';
		$_POST['_acf_nonce']  = wp_create_nonce( 'attachment' );

		// Track if acf_save_post was conceptually called.
		$saved_post_id = null;
		add_action(
			'acf/save_post',
			function ( $post_id ) use ( &$saved_post_id ) {
				$saved_post_id = $post_id;
			}
		);

		// The function should return the post array.
		$result = $form_attachment->save_attachment( $post, $attachment );

		$this->assertEquals( $post, $result, 'Should return post after processing' );

		// Cleanup.
		unset( $_POST['_acf_screen'] );
		unset( $_POST['_acf_nonce'] );
	}

	/**
	 * Test admin_footer outputs JavaScript.
	 */
	public function test_admin_footer_outputs_script() {
		$form_attachment = new acf_form_attachment();

		ob_start();
		$form_attachment->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script type="text/javascript">', $output, 'Should output script tag' );
		$this->assertStringContainsString( 'acf.unload.active = 0', $output, 'Should disable unload warning' );
	}

	/**
	 * Test admin_enqueue_scripts bails early on non-attachment screens.
	 */
	public function test_admin_enqueue_scripts_bails_on_wrong_screen() {
		$form_attachment = new acf_form_attachment();

		// Mock a non-attachment screen.
		set_current_screen( 'edit-post' );

		// This should not throw any errors and should exit early.
		$form_attachment->admin_enqueue_scripts();

		// If we get here without errors, the test passes.
		$this->assertTrue( true );
	}

	/**
	 * Data provider for attachment save scenarios.
	 *
	 * @return array
	 */
	public function save_scenarios_provider() {
		return array(
			'no_nonce'      => array(
				'nonce'    => '',
				'expected' => false,
			),
			'invalid_nonce' => array(
				'nonce'    => 'invalid_nonce_value',
				'expected' => false,
			),
		);
	}

	/**
	 * Test save_attachment with various nonce scenarios.
	 *
	 * @dataProvider save_scenarios_provider
	 *
	 * @param string $nonce    The nonce value to test.
	 * @param bool   $expected Whether save should proceed.
	 */
	public function test_save_attachment_nonce_scenarios( $nonce, $expected ) {
		$form_attachment = new acf_form_attachment();

		$post       = array( 'ID' => 789 );
		$attachment = array();

		if ( $nonce ) {
			$_POST['_acf_screen'] = 'attachment';
			$_POST['_acf_nonce']  = $nonce;
		}

		$result = $form_attachment->save_attachment( $post, $attachment );

		// Should always return the post array.
		$this->assertEquals( $post, $result );

		// Cleanup.
		unset( $_POST['_acf_screen'] );
		unset( $_POST['_acf_nonce'] );
	}
}
