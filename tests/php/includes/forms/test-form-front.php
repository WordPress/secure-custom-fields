<?php
/**
 * Test acf_form_front class and related functions.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the acf_form_front class.
acf_include( 'includes/forms/form-front.php' );

/**
 * Class Test_Form_Front
 */
class Test_Form_Front extends BaseTestCase {

	/**
	 * Test if the acf_form_front class exists.
	 */
	public function test_form_front_class_exists() {
		$this->assertTrue( class_exists( 'acf_form_front' ), 'acf_form_front class should exist' );
	}

	/**
	 * Test if the acf_form_front class is properly initialized.
	 */
	public function test_form_front_initialization() {
		$form_front = new acf_form_front();

		$this->assertInstanceOf( 'acf_form_front', $form_front, 'acf_form_front should be properly initialized' );
	}

	/**
	 * Test constructor registers correct actions.
	 */
	public function test_constructor_registers_actions() {
		$form_front = new acf_form_front();

		$this->assertNotFalse(
			has_action( 'acf/validate_save_post', array( $form_front, 'validate_save_post' ) ),
			'Should register validate_save_post action'
		);

		$this->assertNotFalse(
			has_filter( 'acf/pre_save_post', array( $form_front, 'pre_save_post' ) ),
			'Should register pre_save_post filter'
		);
	}

	/**
	 * Test get_default_fields returns expected fields.
	 */
	public function test_get_default_fields_returns_expected_fields() {
		$form_front = new acf_form_front();

		$fields = $form_front->get_default_fields();

		$this->assertIsArray( $fields, 'Should return an array' );
		$this->assertArrayHasKey( '_post_title', $fields, 'Should contain _post_title field' );
		$this->assertArrayHasKey( '_post_content', $fields, 'Should contain _post_content field' );
		$this->assertArrayHasKey( '_validate_email', $fields, 'Should contain _validate_email honeypot field' );
	}

	/**
	 * Test _post_title field structure.
	 */
	public function test_post_title_field_structure() {
		$form_front = new acf_form_front();

		$fields = $form_front->get_default_fields();
		$field  = $fields['_post_title'];

		$this->assertEquals( 'acf', $field['prefix'], 'prefix should be acf' );
		$this->assertEquals( '_post_title', $field['name'], 'name should be _post_title' );
		$this->assertEquals( '_post_title', $field['key'], 'key should be _post_title' );
		$this->assertEquals( 'text', $field['type'], 'type should be text' );
		$this->assertTrue( $field['required'], 'post_title should be required' );
	}

	/**
	 * Test _post_content field structure.
	 */
	public function test_post_content_field_structure() {
		$form_front = new acf_form_front();

		$fields = $form_front->get_default_fields();
		$field  = $fields['_post_content'];

		$this->assertEquals( 'acf', $field['prefix'], 'prefix should be acf' );
		$this->assertEquals( '_post_content', $field['name'], 'name should be _post_content' );
		$this->assertEquals( '_post_content', $field['key'], 'key should be _post_content' );
		$this->assertEquals( 'wysiwyg', $field['type'], 'type should be wysiwyg' );
	}

	/**
	 * Test _validate_email honeypot field structure.
	 */
	public function test_validate_email_honeypot_structure() {
		$form_front = new acf_form_front();

		$fields = $form_front->get_default_fields();
		$field  = $fields['_validate_email'];

		$this->assertEquals( 'text', $field['type'], 'type should be text' );
		$this->assertEquals( '', $field['value'], 'value should be empty' );
		$this->assertStringContainsString( 'display:none', $field['wrapper']['style'], 'Should be hidden' );
	}

	/**
	 * Test validate_form applies defaults.
	 */
	public function test_validate_form_applies_defaults() {
		$form_front = new acf_form_front();

		$args = $form_front->validate_form( array() );

		$this->assertArrayHasKey( 'id', $args, 'Should have id' );
		$this->assertArrayHasKey( 'post_id', $args, 'Should have post_id' );
		$this->assertArrayHasKey( 'field_groups', $args, 'Should have field_groups' );
		$this->assertArrayHasKey( 'submit_value', $args, 'Should have submit_value' );
		$this->assertArrayHasKey( 'honeypot', $args, 'Should have honeypot' );
		$this->assertTrue( $args['honeypot'], 'honeypot should be true by default' );
		$this->assertTrue( $args['kses'], 'kses should be true by default' );
	}

	/**
	 * Test validate_form handles new_post setting.
	 */
	public function test_validate_form_handles_new_post() {
		$form_front = new acf_form_front();

		$args = $form_front->validate_form(
			array(
				'post_id' => 'new_post',
			)
		);

		$this->assertIsArray( $args['new_post'], 'new_post should be an array' );
		$this->assertEquals( 'post', $args['new_post']['post_type'], 'Default post_type should be post' );
		$this->assertEquals( 'draft', $args['new_post']['post_status'], 'Default post_status should be draft' );
	}

	/**
	 * Test validate_form preserves custom new_post settings.
	 */
	public function test_validate_form_preserves_custom_new_post() {
		$form_front = new acf_form_front();

		$args = $form_front->validate_form(
			array(
				'post_id'  => 'new_post',
				'new_post' => array(
					'post_type'   => 'page',
					'post_status' => 'publish',
				),
			)
		);

		$this->assertEquals( 'page', $args['new_post']['post_type'], 'Should preserve custom post_type' );
		$this->assertEquals( 'publish', $args['new_post']['post_status'], 'Should preserve custom post_status' );
	}

	/**
	 * Test add_form stores form settings.
	 */
	public function test_add_form_stores_settings() {
		$form_front = new acf_form_front();

		$form_front->add_form(
			array(
				'id'     => 'test-form',
				'fields' => array( 'field_123' ),
			)
		);

		$form = $form_front->get_form( 'test-form' );

		$this->assertIsArray( $form, 'Should return form array' );
		$this->assertEquals( 'test-form', $form['id'], 'Should store form with correct id' );
	}

	/**
	 * Test get_form returns false for non-existent form.
	 */
	public function test_get_form_returns_false_for_nonexistent() {
		$form_front = new acf_form_front();

		$result = $form_front->get_form( 'nonexistent-form' );

		$this->assertFalse( $result, 'Should return false for non-existent form' );
	}

	/**
	 * Test get_forms returns all registered forms.
	 */
	public function test_get_forms_returns_all_forms() {
		$form_front = new acf_form_front();

		$form_front->add_form( array( 'id' => 'form-1' ) );
		$form_front->add_form( array( 'id' => 'form-2' ) );

		$forms = $form_front->get_forms();

		$this->assertIsArray( $forms, 'Should return array' );
		$this->assertArrayHasKey( 'form-1', $forms, 'Should contain form-1' );
		$this->assertArrayHasKey( 'form-2', $forms, 'Should contain form-2' );
	}

	/**
	 * Test validate_save_post detects honeypot spam.
	 */
	public function test_validate_save_post_detects_honeypot_spam() {
		$form_front = new acf_form_front();

		// Set honeypot field with value (indicating spam).
		$_POST['acf']['_validate_email'] = 'spam@bot.com';

		// Track if validation error was added.
		$error_added = false;
		add_action(
			'acf/validate_value',
			function () use ( &$error_added ) {
				$error_added = true;
			}
		);

		$form_front->validate_save_post();

		// Check for validation errors.
		$errors = acf_get_validation_errors();

		// Should have added a spam detection error.
		$this->assertNotEmpty( $errors, 'Should have validation errors for spam detection' );

		// Cleanup.
		unset( $_POST['acf']['_validate_email'] );
		acf_reset_validation_errors();
	}

	/**
	 * Test pre_save_post returns post_id for non-numeric, non-new_post.
	 */
	public function test_pre_save_post_returns_post_id_for_other_types() {
		$form_front = new acf_form_front();

		$post_id = 'user_123';
		$form    = array();

		$result = $form_front->pre_save_post( $post_id, $form );

		$this->assertEquals( $post_id, $result, 'Should return original post_id for non-post types' );
	}

	/**
	 * Test pre_save_post returns false when honeypot is filled.
	 */
	public function test_pre_save_post_returns_false_for_spam() {
		$form_front = new acf_form_front();

		$_POST['acf']['_validate_email'] = 'spam@bot.com';

		$post_id = 'new_post';
		$form    = array(
			'new_post' => array(
				'post_type'   => 'post',
				'post_status' => 'draft',
			),
		);

		$result = $form_front->pre_save_post( $post_id, $form );

		$this->assertFalse( $result, 'Should return false when honeypot is filled' );

		// Cleanup.
		unset( $_POST['acf']['_validate_email'] );
	}

	/**
	 * Test check_submit_form returns false without nonce.
	 */
	public function test_check_submit_form_returns_false_without_nonce() {
		$form_front = new acf_form_front();

		// Clear any nonce.
		unset( $_POST['_acf_nonce'] );

		$result = $form_front->check_submit_form();

		$this->assertFalse( $result, 'Should return false without valid nonce' );
	}

	/**
	 * Test check_submit_form returns false without _acf_form.
	 */
	public function test_check_submit_form_returns_false_without_acf_form() {
		$form_front = new acf_form_front();

		// Set up nonce but no _acf_form.
		$_POST['_acf_screen'] = 'acf_form';
		$_POST['_acf_nonce']  = wp_create_nonce( 'acf_form' );

		unset( $_POST['_acf_form'] );

		$result = $form_front->check_submit_form();

		$this->assertFalse( $result, 'Should return false without _acf_form' );

		// Cleanup.
		unset( $_POST['_acf_screen'] );
		unset( $_POST['_acf_nonce'] );
	}

	/**
	 * Test acf_form_head function exists.
	 */
	public function test_acf_form_head_function_exists() {
		$this->assertTrue( function_exists( 'acf_form_head' ), 'acf_form_head function should exist' );
	}

	/**
	 * Test acf_form function exists.
	 */
	public function test_acf_form_function_exists() {
		$this->assertTrue( function_exists( 'acf_form' ), 'acf_form function should exist' );
	}

	/**
	 * Test acf_get_form function exists.
	 */
	public function test_acf_get_form_function_exists() {
		$this->assertTrue( function_exists( 'acf_get_form' ), 'acf_get_form function should exist' );
	}

	/**
	 * Test acf_get_forms function exists.
	 */
	public function test_acf_get_forms_function_exists() {
		$this->assertTrue( function_exists( 'acf_get_forms' ), 'acf_get_forms function should exist' );
	}

	/**
	 * Test acf_register_form function exists.
	 */
	public function test_acf_register_form_function_exists() {
		$this->assertTrue( function_exists( 'acf_register_form' ), 'acf_register_form function should exist' );
	}

	/**
	 * Test render_form outputs form structure.
	 */
	public function test_render_form_outputs_form_structure() {
		$form_front = new acf_form_front();

		// Return a field group.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array(
					array(
						'ID'                    => 1,
						'key'                   => 'group_test',
						'title'                 => 'Test Group',
						'instruction_placement' => 'label',
						'active'                => true,
						'location'              => array(),
					),
				);
			}
		);

		// Return empty fields.
		add_filter(
			'acf/get_fields',
			function () {
				return array();
			}
		);

		ob_start();
		$form_front->render_form(
			array(
				'id'      => 'test-render-form',
				'post_id' => 123,
			)
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output, 'Should output form element' );
		$this->assertStringContainsString( 'acf-form', $output, 'Should have acf-form class' );
		$this->assertStringContainsString( 'acf-form-submit', $output, 'Should have submit button area' );
	}

	/**
	 * Test render_form with form=false skips form wrapper.
	 */
	public function test_render_form_skips_form_wrapper_when_disabled() {
		$form_front = new acf_form_front();

		// Return empty field groups.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		ob_start();
		$form_front->render_form(
			array(
				'id'      => 'test-no-form',
				'post_id' => 123,
				'form'    => false,
			)
		);
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '<form', $output, 'Should not output form element when form=false' );
	}

	/**
	 * Data provider for validate_form defaults.
	 *
	 * @return array
	 */
	public function validate_form_defaults_provider() {
		return array(
			'id'                    => array( 'id', 'acf-form' ),
			'form'                  => array( 'form', true ),
			'honeypot'              => array( 'honeypot', true ),
			'kses'                  => array( 'kses', true ),
			'label_placement'       => array( 'label_placement', 'top' ),
			'instruction_placement' => array( 'instruction_placement', 'label' ),
			'field_el'              => array( 'field_el', 'div' ),
			'uploader'              => array( 'uploader', 'wp' ),
		);
	}

	/**
	 * Test validate_form default values.
	 *
	 * @dataProvider validate_form_defaults_provider
	 *
	 * @param string $key      The key to check.
	 * @param mixed  $expected The expected default value.
	 */
	public function test_validate_form_defaults( $key, $expected ) {
		$form_front = new acf_form_front();

		$args = $form_front->validate_form( array() );

		$this->assertEquals( $expected, $args[ $key ], "$key should have correct default" );
	}

	/**
	 * Test enqueue_form triggers check_submit_form.
	 *
	 * When no form submission is present, enqueue_form should still complete
	 * without errors and call check_submit_form internally.
	 */
	public function test_enqueue_form_calls_check_submit_form() {
		$form_front = new acf_form_front();

		// Clear any POST data that might trigger form submission.
		unset( $_POST['_acf_nonce'] );
		unset( $_POST['_acf_form'] );

		// Track if check_submit_form returns false (no form submitted).
		$check_result = $form_front->check_submit_form();

		// Should return false when no form data is present.
		$this->assertFalse( $check_result, 'check_submit_form should return false without nonce' );
	}

	/**
	 * Test submit_form applies pre_submit_form filter.
	 */
	public function test_submit_form_applies_pre_submit_filter() {
		$form_front = new acf_form_front();

		$filter_called = false;
		add_filter(
			'acf/pre_submit_form',
			function ( $form ) use ( &$filter_called ) {
				$filter_called = true;
				// Remove return to prevent redirect.
				$form['return'] = '';
				return $form;
			}
		);

		// Use a real post to avoid issues.
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Test Submit Form',
				'post_status' => 'draft',
			)
		);

		$form = array(
			'post_id' => $post_id,
			'return'  => '',
		);

		$form_front->submit_form( $form );

		$this->assertTrue( $filter_called, 'acf/pre_submit_form filter should be applied' );

		// Cleanup.
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test submit_form fires submit_form action.
	 */
	public function test_submit_form_fires_action() {
		$form_front = new acf_form_front();

		$action_fired   = false;
		$action_post_id = null;
		add_action(
			'acf/submit_form',
			function ( $form, $post_id ) use ( &$action_fired, &$action_post_id ) {
				$action_fired   = true;
				$action_post_id = $post_id;
			},
			10,
			2
		);

		// Use a real post.
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Test Submit Form Action',
				'post_status' => 'draft',
			)
		);

		$form = array(
			'post_id' => $post_id,
			'return'  => '', // Empty to avoid redirect.
		);

		$form_front->submit_form( $form );

		$this->assertTrue( $action_fired, 'acf/submit_form action should fire' );
		$this->assertEquals( $post_id, $action_post_id, 'Action should receive correct post_id' );

		// Cleanup.
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test submit_form sets global acf_form variable.
	 */
	public function test_submit_form_sets_global() {
		$form_front = new acf_form_front();

		// Clear any existing global.
		unset( $GLOBALS['acf_form'] );

		// Use a real post.
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Test Global Form',
				'post_status' => 'draft',
			)
		);

		$form = array(
			'post_id'     => $post_id,
			'return'      => '',
			'custom_data' => 'test_value',
		);

		$form_front->submit_form( $form );

		$this->assertArrayHasKey( 'acf_form', $GLOBALS, 'Global acf_form should be set' );
		$this->assertEquals( 'test_value', $GLOBALS['acf_form']['custom_data'], 'Global should contain form data' );

		// Cleanup.
		wp_delete_post( $post_id, true );
		unset( $GLOBALS['acf_form'] ); // @phpstan-ignore-line -- Cleanup global in test.
	}
}
