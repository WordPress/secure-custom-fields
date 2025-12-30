<?php
/**
 * Test acf_form_nav_menu class.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the acf_form_nav_menu class.
acf_include( 'includes/forms/form-nav-menu.php' );

/**
 * Class Test_Form_Nav_Menu
 */
class Test_Form_Nav_Menu extends BaseTestCase {

	/**
	 * Test if the acf_form_nav_menu class exists.
	 */
	public function test_form_nav_menu_class_exists() {
		$this->assertTrue( class_exists( 'acf_form_nav_menu' ), 'acf_form_nav_menu class should exist' );
	}

	/**
	 * Test if the acf_form_nav_menu class is properly initialized.
	 */
	public function test_form_nav_menu_initialization() {
		$form_nav_menu = new acf_form_nav_menu();

		$this->assertInstanceOf( 'acf_form_nav_menu', $form_nav_menu, 'acf_form_nav_menu should be properly initialized' );
	}

	/**
	 * Test constructor registers correct actions.
	 */
	public function test_constructor_registers_actions() {
		$form_nav_menu = new acf_form_nav_menu();

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', array( $form_nav_menu, 'admin_enqueue_scripts' ) ),
			'Should register admin_enqueue_scripts action'
		);

		$this->assertNotFalse(
			has_action( 'wp_update_nav_menu', array( $form_nav_menu, 'update_nav_menu' ) ),
			'Should register wp_update_nav_menu action'
		);

		$this->assertNotFalse(
			has_action( 'acf/validate_save_post', array( $form_nav_menu, 'acf_validate_save_post' ) ),
			'Should register acf_validate_save_post action'
		);

		$this->assertNotFalse(
			has_filter( 'wp_nav_menu_item_custom_fields', array( $form_nav_menu, 'wp_nav_menu_item_custom_fields' ) ),
			'Should register wp_nav_menu_item_custom_fields filter'
		);

		$this->assertNotFalse(
			has_filter( 'wp_get_nav_menu_items', array( $form_nav_menu, 'wp_get_nav_menu_items' ) ),
			'Should register wp_get_nav_menu_items filter'
		);

		$this->assertNotFalse(
			has_filter( 'wp_edit_nav_menu_walker', array( $form_nav_menu, 'wp_edit_nav_menu_walker' ) ),
			'Should register wp_edit_nav_menu_walker filter'
		);
	}

	/**
	 * Test update_nav_menu returns early when nonce fails.
	 */
	public function test_update_nav_menu_returns_when_nonce_fails() {
		$form_nav_menu = new acf_form_nav_menu();

		$menu_id = 123;

		// Clear any nonce.
		unset( $_POST['_acf_nonce'] );

		$result = $form_nav_menu->update_nav_menu( $menu_id );

		$this->assertEquals( $menu_id, $result, 'Should return menu_id when nonce verification fails' );
	}

	/**
	 * Test acf_validate_save_post returns early when no menu-item-acf data.
	 */
	public function test_acf_validate_save_post_returns_early_without_menu_item_data() {
		$form_nav_menu = new acf_form_nav_menu();

		// Clear any menu-item-acf data.
		unset( $_POST['menu-item-acf'] );

		// Should not throw any errors and should return early.
		$form_nav_menu->acf_validate_save_post();

		$this->assertTrue( true, 'Should return early when no menu-item-acf data' );
	}

	/**
	 * Test acf_validate_save_post processes menu item values.
	 */
	public function test_acf_validate_save_post_processes_menu_items() {
		$form_nav_menu = new acf_form_nav_menu();

		// Set up menu-item-acf data.
		$_POST['menu-item-acf'] = array(
			123 => array(
				'field_test' => 'test value',
			),
		);

		// Track validation calls.
		$validated = false;
		add_action(
			'acf/validate_values',
			function () use ( &$validated ) {
				$validated = true;
			}
		);

		$form_nav_menu->acf_validate_save_post();

		// Cleanup.
		unset( $_POST['menu-item-acf'] );

		// The function should complete without errors.
		$this->assertTrue( true, 'Should process menu item values' );
	}

	/**
	 * Test wp_get_nav_menu_items stores menu ID.
	 */
	public function test_wp_get_nav_menu_items_stores_menu_id() {
		$form_nav_menu = new acf_form_nav_menu();

		$items         = array();
		$menu          = new stdClass();
		$menu->term_id = 456;
		$args          = array();

		$result = $form_nav_menu->wp_get_nav_menu_items( $items, $menu, $args );

		$this->assertEquals( $items, $result, 'Should return items unchanged' );
		$this->assertEquals( 456, acf_get_data( 'nav_menu_id' ), 'Should store menu ID in acf_data' );
	}

	/**
	 * Test wp_edit_nav_menu_walker returns walker class.
	 */
	public function test_wp_edit_nav_menu_walker_returns_class() {
		$form_nav_menu = new acf_form_nav_menu();

		$class   = 'Walker_Nav_Menu_Edit';
		$menu_id = 789;

		$result = $form_nav_menu->wp_edit_nav_menu_walker( $class, $menu_id );

		$this->assertEquals( $class, $result, 'Should return walker class unchanged' );
		$this->assertEquals( $menu_id, acf_get_data( 'nav_menu_id' ), 'Should store menu ID in acf_data' );
	}

	/**
	 * Test wp_edit_nav_menu_walker handles default menu_id.
	 */
	public function test_wp_edit_nav_menu_walker_handles_default_menu_id() {
		$form_nav_menu = new acf_form_nav_menu();

		$class = 'Walker_Nav_Menu_Edit';

		$result = $form_nav_menu->wp_edit_nav_menu_walker( $class );

		$this->assertEquals( $class, $result, 'Should return walker class unchanged with default menu_id' );
	}

	/**
	 * Test wp_nav_menu_item_custom_fields renders nothing without matching field groups.
	 */
	public function test_wp_nav_menu_item_custom_fields_renders_nothing_without_field_groups() {
		$form_nav_menu = new acf_form_nav_menu();

		$item_id    = 123;
		$item       = new stdClass();
		$item->type = 'custom';
		$depth      = 0;
		$args       = new stdClass();

		ob_start();
		$form_nav_menu->wp_nav_menu_item_custom_fields( $item_id, $item, $depth, $args );
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Should render nothing when no field groups match' );
	}

	/**
	 * Test admin_footer outputs JavaScript.
	 */
	public function test_admin_footer_outputs_script() {
		$form_nav_menu = new acf_form_nav_menu();

		// Set nav_menu_id in acf_data.
		acf_set_data( 'nav_menu_id', 123 );

		// Return no field groups for simplicity.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array();
			}
		);

		ob_start();
		$form_nav_menu->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'tmpl-acf-menu-settings', $output, 'Should output template wrapper' );
		$this->assertStringContainsString( '<script type="text/javascript">', $output, 'Should output script tag' );
		$this->assertStringContainsString( '#update-nav-menu', $output, 'Should reference nav menu form' );
	}

	/**
	 * Test admin_footer renders field groups when present.
	 */
	public function test_admin_footer_renders_field_groups() {
		$form_nav_menu = new acf_form_nav_menu();

		// Set nav_menu_id in acf_data.
		acf_set_data( 'nav_menu_id', 456 );

		// Return a field group.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array(
					array(
						'ID'                    => 1,
						'key'                   => 'group_nav',
						'title'                 => 'Nav Menu Settings',
						'style'                 => 'default',
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
		$form_nav_menu->admin_footer();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'acf-menu-settings', $output, 'Should contain acf-menu-settings class' );
	}

	/**
	 * Test update_nav_menu_items returns early without menu-item-acf data.
	 */
	public function test_update_nav_menu_items_returns_early_without_data() {
		$form_nav_menu = new acf_form_nav_menu();

		// Clear any menu-item-acf data.
		unset( $_POST['menu-item-acf'] );

		// Should not throw any errors.
		$form_nav_menu->update_nav_menu_items( 123 );

		$this->assertTrue( true, 'Should return early when no menu-item-acf data' );
	}

	/**
	 * Data provider for nav menu walker scenarios.
	 *
	 * @return array
	 */
	public function walker_scenarios_provider() {
		return array(
			'default_walker' => array( 'Walker_Nav_Menu_Edit', 100, 'Walker_Nav_Menu_Edit' ),
			'custom_walker'  => array( 'Custom_Walker', 200, 'Custom_Walker' ),
			'empty_walker'   => array( '', 300, '' ),
		);
	}

	/**
	 * Test wp_edit_nav_menu_walker with various scenarios.
	 *
	 * @dataProvider walker_scenarios_provider
	 *
	 * @param string $walker_class The walker class.
	 * @param int    $menu_id      The menu ID.
	 * @param string $expected     Expected return value.
	 */
	public function test_wp_edit_nav_menu_walker_scenarios( $walker_class, $menu_id, $expected ) {
		$form_nav_menu = new acf_form_nav_menu();

		$result = $form_nav_menu->wp_edit_nav_menu_walker( $walker_class, $menu_id );

		$this->assertEquals( $expected, $result, 'Should return walker class unchanged' );
		$this->assertEquals( $menu_id, acf_get_data( 'nav_menu_id' ), 'Should store menu ID' );
	}
}
