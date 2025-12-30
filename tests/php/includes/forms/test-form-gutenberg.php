<?php
/**
 * Test ACF_Form_Gutenberg class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the ACF_Form_Gutenberg class.
acf_include( 'includes/forms/form-gutenberg.php' );

/**
 * Class Test_Form_Gutenberg
 */
class Test_Form_Gutenberg extends BaseTestCase {

	/**
	 * Clean up after each test to prevent global state pollution.
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Reset global $current_screen to prevent polluting other tests.
		global $current_screen;
		$current_screen = null;
	}

	/**
	 * Test if the ACF_Form_Gutenberg class exists.
	 */
	public function test_form_gutenberg_class_exists() {
		$this->assertTrue( class_exists( 'ACF_Form_Gutenberg' ), 'ACF_Form_Gutenberg class should exist' );
	}

	/**
	 * Test if the ACF_Form_Gutenberg class is properly initialized.
	 */
	public function test_form_gutenberg_initialization() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		$this->assertInstanceOf( 'ACF_Form_Gutenberg', $form_gutenberg, 'ACF_Form_Gutenberg should be properly initialized' );
	}

	/**
	 * Test constructor registers correct actions.
	 */
	public function test_constructor_registers_actions() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		$this->assertNotFalse(
			has_action( 'enqueue_block_editor_assets', array( $form_gutenberg, 'enqueue_block_editor_assets' ) ),
			'Should register enqueue_block_editor_assets action'
		);

		$this->assertNotFalse(
			has_action( 'acf/validate_save_post', array( $form_gutenberg, 'acf_validate_save_post' ) ),
			'Should register acf_validate_save_post action'
		);
	}

	/**
	 * Test acf_validate_save_post resets errors during meta-box-loader.
	 */
	public function test_acf_validate_save_post_resets_errors_for_meta_box_loader() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		// Add a validation error.
		acf_add_validation_error( 'test_field', 'Test error' );

		// Simulate meta-box-loader request.
		$_GET['meta-box-loader'] = '1';

		$form_gutenberg->acf_validate_save_post();

		// Errors should be reset.
		$errors = acf_get_validation_errors();
		$this->assertEmpty( $errors, 'Validation errors should be reset during meta-box-loader request' );

		// Cleanup.
		unset( $_GET['meta-box-loader'] );
	}

	/**
	 * Test acf_validate_save_post does not reset errors for normal requests.
	 */
	public function test_acf_validate_save_post_keeps_errors_for_normal_requests() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		// Clear previous errors.
		acf_reset_validation_errors();

		// Add a validation error.
		acf_add_validation_error( 'test_field', 'Test error' );

		// Make sure meta-box-loader is not set.
		unset( $_GET['meta-box-loader'] );

		$form_gutenberg->acf_validate_save_post();

		// Errors should remain.
		$errors = acf_get_validation_errors();
		$this->assertNotEmpty( $errors, 'Validation errors should remain for normal requests' );

		// Cleanup.
		acf_reset_validation_errors();
	}

	/**
	 * Test enqueue_block_editor_assets adds meta_boxes action.
	 */
	public function test_enqueue_block_editor_assets_adds_meta_boxes_action() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		// Call the method.
		$form_gutenberg->enqueue_block_editor_assets();

		$this->assertNotFalse(
			has_action( 'add_meta_boxes', array( $form_gutenberg, 'add_meta_boxes' ) ),
			'Should add add_meta_boxes action'
		);
	}

	/**
	 * Test enqueue_block_editor_assets adds hidden_fields action.
	 */
	public function test_enqueue_block_editor_assets_adds_hidden_fields_action() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		// Call the method.
		$form_gutenberg->enqueue_block_editor_assets();

		$this->assertNotFalse(
			has_action( 'block_editor_meta_box_hidden_fields', array( $form_gutenberg, 'block_editor_meta_box_hidden_fields' ) ),
			'Should add block_editor_meta_box_hidden_fields action'
		);
	}

	/**
	 * Test enqueue_block_editor_assets adds meta_boxes filter.
	 */
	public function test_enqueue_block_editor_assets_adds_meta_boxes_filter() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		// Call the method.
		$form_gutenberg->enqueue_block_editor_assets();

		$this->assertNotFalse(
			has_filter( 'filter_block_editor_meta_boxes', array( $form_gutenberg, 'filter_block_editor_meta_boxes' ) ),
			'Should add filter_block_editor_meta_boxes filter'
		);
	}

	/**
	 * Test filter_block_editor_meta_boxes moves acf_after_title to normal.
	 */
	public function test_filter_block_editor_meta_boxes_moves_after_title() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		// Set up current_screen global.
		global $current_screen;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.
		$current_screen     = new stdClass();
		$current_screen->id = 'post';

		$wp_meta_boxes = array(
			'post' => array(
				'acf_after_title' => array(
					'high' => array(
						'acf-test-box' => array(
							'id'    => 'acf-test-box',
							'title' => 'Test Box',
						),
					),
				),
				'normal'          => array(
					'high' => array(),
				),
			),
		);

		$result = $form_gutenberg->filter_block_editor_meta_boxes( $wp_meta_boxes );

		// acf_after_title should be removed.
		$this->assertArrayNotHasKey( 'acf_after_title', $result['post'], 'acf_after_title should be removed' );

		// Meta box should be in normal high.
		$this->assertArrayHasKey( 'acf-test-box', $result['post']['normal']['high'], 'Meta box should be moved to normal high' );
	}

	/**
	 * Test filter_block_editor_meta_boxes returns unchanged when no acf_after_title.
	 */
	public function test_filter_block_editor_meta_boxes_unchanged_without_acf_after_title() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		// Set up current_screen global.
		global $current_screen;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.
		$current_screen     = new stdClass();
		$current_screen->id = 'post';

		$wp_meta_boxes = array(
			'post' => array(
				'normal' => array(
					'high' => array(
						'existing-box' => array(
							'id'    => 'existing-box',
							'title' => 'Existing',
						),
					),
				),
			),
		);

		$result = $form_gutenberg->filter_block_editor_meta_boxes( $wp_meta_boxes );

		$this->assertEquals( $wp_meta_boxes, $result, 'Should return unchanged when no acf_after_title' );
	}

	/**
	 * Test modify_user_option_meta_box_order moves acf_after_title.
	 */
	public function test_modify_user_option_meta_box_order_moves_after_title() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		$locations = array(
			'acf_after_title' => 'acf-box-1,acf-box-2',
			'normal'          => 'existing-box',
		);

		$result = $form_gutenberg->modify_user_option_meta_box_order( $locations );

		$this->assertArrayNotHasKey( 'acf_after_title', $result, 'acf_after_title should be removed' );
		$this->assertStringContainsString( 'acf-box-1,acf-box-2', $result['normal'], 'ACF boxes should be prepended to normal' );
	}

	/**
	 * Test modify_user_option_meta_box_order handles empty normal.
	 */
	public function test_modify_user_option_meta_box_order_handles_empty_normal() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		$locations = array(
			'acf_after_title' => 'acf-box-1',
		);

		$result = $form_gutenberg->modify_user_option_meta_box_order( $locations );

		$this->assertEquals( 'acf-box-1', $result['normal'], 'Should set normal to acf_after_title content' );
		$this->assertArrayNotHasKey( 'acf_after_title', $result, 'acf_after_title should be removed' );
	}

	/**
	 * Test modify_user_option_meta_box_order unchanged without acf_after_title.
	 */
	public function test_modify_user_option_meta_box_order_unchanged_without_acf() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		$locations = array(
			'normal' => 'existing-box',
			'side'   => 'side-box',
		);

		$result = $form_gutenberg->modify_user_option_meta_box_order( $locations );

		$this->assertEquals( $locations, $result, 'Should return unchanged without acf_after_title' );
	}

	/**
	 * Test filter_block_editor_meta_boxes creates normal location if not exists.
	 */
	public function test_filter_block_editor_meta_boxes_creates_normal_location() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		// Set up current_screen global.
		global $current_screen;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.
		$current_screen     = new stdClass();
		$current_screen->id = 'page';

		$wp_meta_boxes = array(
			'page' => array(
				'acf_after_title' => array(
					'high' => array(
						'acf-test' => array( 'id' => 'acf-test' ),
					),
				),
				// No 'normal' key exists.
			),
		);

		$result = $form_gutenberg->filter_block_editor_meta_boxes( $wp_meta_boxes );

		$this->assertArrayHasKey( 'normal', $result['page'], 'normal location should be created' );
		$this->assertArrayHasKey( 'high', $result['page']['normal'], 'normal[high] should be created' );
	}

	/**
	 * Test filter_block_editor_meta_boxes adds user option filter.
	 */
	public function test_filter_block_editor_meta_boxes_adds_user_option_filter() {
		$form_gutenberg = new ACF_Form_Gutenberg();

		// Set up current_screen global.
		global $current_screen;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test requires setting global.
		$current_screen     = new stdClass();
		$current_screen->id = 'custom_post_type';

		$wp_meta_boxes = array(
			'custom_post_type' => array(
				'acf_after_title' => array(
					'high' => array(
						'acf-test' => array( 'id' => 'acf-test' ),
					),
				),
			),
		);

		$form_gutenberg->filter_block_editor_meta_boxes( $wp_meta_boxes );

		$this->assertNotFalse(
			has_filter( 'get_user_option_meta-box-order_custom_post_type', array( $form_gutenberg, 'modify_user_option_meta_box_order' ) ),
			'Should add user option filter for the post type'
		);
	}

	/**
	 * Data provider for meta-box-loader scenarios.
	 *
	 * @return array
	 */
	public function meta_box_loader_scenarios_provider() {
		return array(
			'with_meta_box_loader'    => array( '1', true ),
			'with_true'               => array( 'true', true ),
			'without_meta_box_loader' => array( null, false ),
		);
	}

	/**
	 * Test acf_validate_save_post with various meta-box-loader values.
	 *
	 * @dataProvider meta_box_loader_scenarios_provider
	 *
	 * @param string|null $value            The meta-box-loader value.
	 * @param bool        $should_reset     Whether errors should be reset.
	 */
	public function test_acf_validate_save_post_scenarios( $value, $should_reset ) {
		$form_gutenberg = new ACF_Form_Gutenberg();

		// Clear and add a validation error.
		acf_reset_validation_errors();
		acf_add_validation_error( 'test_field', 'Test error' );

		if ( null === $value ) {
			unset( $_GET['meta-box-loader'] );
		} else {
			$_GET['meta-box-loader'] = $value;
		}

		$form_gutenberg->acf_validate_save_post();

		$errors = acf_get_validation_errors();

		if ( $should_reset ) {
			$this->assertEmpty( $errors, 'Errors should be reset' );
		} else {
			$this->assertNotEmpty( $errors, 'Errors should remain' );
		}

		// Cleanup.
		unset( $_GET['meta-box-loader'] );
		acf_reset_validation_errors();
	}
}
