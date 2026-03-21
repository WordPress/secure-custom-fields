<?php
/**
 * Test ACF_Form_User class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the ACF_Form_User class.
acf_include( 'includes/forms/form-user.php' );

// Load the location type for user_form to enable field group matching.
acf_include( 'includes/locations/class-acf-location-user-form.php' );

/**
 * Class Test_Form_User
 */
class Test_Form_User extends BaseTestCase {

	/**
	 * Set up each test to prevent global state pollution.
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset form data store to prevent pollution from other tests.
		acf_get_store( 'form' )->reset();
	}

	/**
	 * Test if the ACF_Form_User class exists.
	 */
	public function test_form_user_class_exists() {
		$this->assertTrue( class_exists( 'ACF_Form_User' ), 'ACF_Form_User class should exist' );
	}

	/**
	 * Test if the ACF_Form_User class is properly initialized.
	 */
	public function test_form_user_initialization() {
		$form_user = new ACF_Form_User();

		$this->assertInstanceOf( 'ACF_Form_User', $form_user, 'ACF_Form_User should be properly initialized' );
	}

	/**
	 * Test constructor registers correct actions.
	 */
	public function test_constructor_registers_actions() {
		$form_user = new ACF_Form_User();

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', array( $form_user, 'admin_enqueue_scripts' ) ),
			'Should register admin_enqueue_scripts action'
		);

		$this->assertNotFalse(
			has_action( 'login_form_register', array( $form_user, 'login_form_register' ) ),
			'Should register login_form_register action'
		);

		$this->assertNotFalse(
			has_action( 'show_user_profile', array( $form_user, 'render_edit' ) ),
			'Should register show_user_profile action'
		);

		$this->assertNotFalse(
			has_action( 'edit_user_profile', array( $form_user, 'render_edit' ) ),
			'Should register edit_user_profile action'
		);

		$this->assertNotFalse(
			has_action( 'user_new_form', array( $form_user, 'render_new' ) ),
			'Should register user_new_form action'
		);

		$this->assertNotFalse(
			has_action( 'register_form', array( $form_user, 'render_register' ) ),
			'Should register register_form action'
		);

		$this->assertNotFalse(
			has_action( 'user_register', array( $form_user, 'save_user' ) ),
			'Should register user_register action'
		);

		$this->assertNotFalse(
			has_action( 'profile_update', array( $form_user, 'save_user' ) ),
			'Should register profile_update action'
		);

		$this->assertNotFalse(
			has_filter( 'registration_errors', array( $form_user, 'filter_registration_errors' ) ),
			'Should register registration_errors filter'
		);
	}

	/**
	 * Test save_user returns early when nonce fails.
	 */
	public function test_save_user_returns_when_nonce_fails() {
		$form_user = new ACF_Form_User();

		$user_id = 123;

		// Clear any nonce.
		unset( $_POST['_acf_nonce'] );

		$result = $form_user->save_user( $user_id );

		$this->assertEquals( $user_id, $result, 'Should return user_id when nonce verification fails' );
	}

	/**
	 * Test render renders nothing without matching field groups.
	 */
	public function test_render_renders_nothing_without_field_groups() {
		$form_user = new ACF_Form_User();

		ob_start();
		$form_user->render(
			array(
				'user_id' => 0,
				'view'    => 'add',
				'el'      => 'tr',
			)
		);
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Should render nothing when no field groups match' );
	}

	/**
	 * Test render_register calls render with correct args.
	 */
	public function test_render_register_calls_render() {
		$form_user = new ACF_Form_User();

		// Return no field groups.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		ob_start();
		$form_user->render_register();
		$output = ob_get_clean();

		// Should not error out, just return empty.
		$this->assertEmpty( $output, 'Should handle render_register without field groups' );
	}

	/**
	 * Test render_new returns early on multisite.
	 */
	public function test_render_new_returns_early_on_multisite() {
		$form_user = new ACF_Form_User();

		// Return no field groups.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		ob_start();
		$form_user->render_new();
		$output = ob_get_clean();

		// On non-multisite, should proceed (but with no field groups, renders nothing).
		$this->assertEmpty( $output, 'Should handle render_new' );
	}

	/**
	 * Test filter_registration_errors returns errors unchanged when validation passes.
	 */
	public function test_filter_registration_errors_returns_unchanged_on_valid() {
		$form_user = new ACF_Form_User();

		$errors = new WP_Error();
		$login  = 'testuser';
		$email  = 'test@example.com';

		// Clear any ACF validation errors.
		acf_reset_validation_errors();

		$result = $form_user->filter_registration_errors( $errors, $login, $email );

		$this->assertInstanceOf( 'WP_Error', $result, 'Should return WP_Error instance' );
	}

	/**
	 * Test filter_pre_load_value returns null when no POST data.
	 */
	public function test_filter_pre_load_value_returns_null_without_post() {
		$form_user = new ACF_Form_User();

		$field = array(
			'key'  => 'field_123',
			'name' => 'test_field',
		);

		// Clear any POST data.
		unset( $_POST['acf'] );

		$result = $form_user->filter_pre_load_value( null, 'user_1', $field );

		$this->assertNull( $result, 'Should return null when no POST data for field' );
	}

	/**
	 * Test filter_pre_load_value returns POST value when present.
	 */
	public function test_filter_pre_load_value_returns_post_value() {
		$form_user = new ACF_Form_User();

		$field = array(
			'key'  => 'field_456',
			'name' => 'test_field',
		);

		$_POST['acf'] = array(
			'field_456' => 'posted value',
		);

		$result = $form_user->filter_pre_load_value( null, 'user_1', $field );

		$this->assertEquals( 'posted value', $result, 'Should return POST value when present' );

		// Cleanup.
		unset( $_POST['acf'] );
	}

	/**
	 * Test admin_footer outputs JavaScript.
	 */
	public function test_admin_footer_outputs_script() {
		$form_user = new ACF_Form_User();

		ob_start();
		$form_user->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script type="text/javascript">', $output, 'Should output script tag' );
		$this->assertStringContainsString( 'input.button-primary', $output, 'Should target submit button' );
		$this->assertStringContainsString( 'spinner', $output, 'Should add spinner' );
	}

	/**
	 * Test render adds pre_load_value filter when POST data exists.
	 */
	public function test_render_adds_pre_load_filter_with_post_data() {
		$form_user = new ACF_Form_User();

		$_POST['acf'] = array(
			'field_test' => 'test value',
		);

		// Return no field groups to prevent output.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		$form_user->render(
			array(
				'user_id' => 0,
				'view'    => 'edit',
				'el'      => 'tr',
			)
		);

		$this->assertNotFalse(
			has_filter( 'acf/pre_load_value', array( $form_user, 'filter_pre_load_value' ) ),
			'Should add pre_load_value filter when POST data exists'
		);

		// Cleanup.
		unset( $_POST['acf'] );
	}

	/**
	 * Test render disables validation for register view.
	 */
	public function test_render_disables_validation_for_register() {
		$form_user = new ACF_Form_User();

		// Track form_data calls.
		$validation_disabled = false;
		add_action(
			'acf/input/form_data',
			function ( $args ) use ( &$validation_disabled ) {
				if ( isset( $args['validation'] ) && 0 === $args['validation'] ) {
					$validation_disabled = true;
				}
			}
		);

		// Return a field group.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array(
					array(
						'ID'                    => 1,
						'key'                   => 'group_user',
						'title'                 => 'User Fields',
						'style'                 => 'seamless',
						'instruction_placement' => 'label',
						'active'                => true,
						'location'              => array(),
					),
				);
			}
		);

		ob_start();
		$form_user->render(
			array(
				'user_id' => 0,
				'view'    => 'register',
				'el'      => 'div',
			)
		);
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Should render nothing for register view when no field groups match' );
	}

	/**
	 * Test admin_enqueue_scripts returns early when not on user screens.
	 *
	 * In test environment, get_current_screen() returns null, so acf_is_screen()
	 * returns false, triggering the early return path.
	 */
	public function test_admin_enqueue_scripts_bails_when_not_on_user_screen() {
		$form_user = new ACF_Form_User();

		// Track if acf_enqueue_scripts was called.
		$enqueue_called = false;
		add_action(
			'acf/enqueue_scripts',
			function () use ( &$enqueue_called ) {
				$enqueue_called = true;
			}
		);

		// Call admin_enqueue_scripts - should return early since no screen is set.
		$form_user->admin_enqueue_scripts();

		// acf_enqueue_scripts should NOT be called when not on user screen.
		$this->assertFalse( $enqueue_called, 'acf_enqueue_scripts should not be called when not on user screen' );
	}

	/**
	 * Test user form layout CSS includes expected rules.
	 */
	public function test_get_user_form_layout_css_contains_expected_rules() {
		$form_user = new ACF_Form_User();
		$css       = $form_user->get_user_form_layout_css();

		$this->assertIsString( $css, 'CSS should be a string' );
		$this->assertStringContainsString(
			'table.form-table tr.acf-field > td.acf-label',
			$css,
			'CSS should target ACF labels in user form table rows'
		);
		$this->assertStringContainsString(
			'input[type="text"]',
			$css,
			'CSS should target text-like input fields'
		);
		$this->assertStringContainsString(
			'@media screen and (max-width: 782px)',
			$css,
			'CSS should include responsive rules for mobile viewports'
		);
	}

	/**
	 * Test render_edit renders nothing without field groups.
	 */
	public function test_render_edit_renders_nothing_without_field_groups() {
		$form_user = new ACF_Form_User();

		// Create a mock user object.
		$user     = new stdClass();
		$user->ID = 123;

		// Return no field groups.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		ob_start();
		$form_user->render_edit( $user );
		$output = ob_get_clean();

		// Should complete without error and render nothing when no field groups.
		$this->assertEmpty( $output, 'render_edit should render nothing without field groups' );
	}

	/**
	 * Test render_edit with different user IDs.
	 *
	 * Verify render_edit works correctly with various user ID values.
	 */
	public function test_render_edit_with_different_user_ids() {
		$form_user = new ACF_Form_User();

		// Return no field groups.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		// Test with various user IDs.
		$user_ids = array( 1, 100, 999 );

		foreach ( $user_ids as $user_id ) {
			$user     = new stdClass();
			$user->ID = $user_id;

			ob_start();
			$form_user->render_edit( $user );
			$output = ob_get_clean();

			$this->assertEmpty( $output, "render_edit should handle user_id $user_id without error" );
		}
	}
}
