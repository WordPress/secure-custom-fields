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
	 * Menu the save tests submit values for.
	 *
	 * @var integer
	 */
	private $menu_id = 7;

	/**
	 * $_POST['acf'] as seen by acf/save_post, keyed by the ACF post ID saved.
	 *
	 * @var array
	 */
	private $saved_values = array();

	/**
	 * Test filters that must be removed during cleanup.
	 *
	 * @var array
	 */
	private $test_filters = array();

	/**
	 * Registers the field type and locations the save tests use.
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

		acf_include( 'includes/locations/class-acf-location-nav-menu.php' );
		acf_include( 'includes/locations/class-acf-location-nav-menu-item.php' );
		if ( ! acf_get_location_type( 'nav_menu' ) ) {
			acf_register_location_type( 'ACF_Location_Nav_Menu' );
		}
		if ( ! acf_get_location_type( 'nav_menu_item' ) ) {
			acf_register_location_type( 'ACF_Location_Nav_Menu_Item' );
		}

		if ( ! has_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts' ) ) {
			add_filter( 'acf/load_field_groups', '_acf_apply_get_local_internal_posts', 20, 2 );
		}

		$this->saved_values = array();
		$this->add_test_filter( 'acf/save_post', array( $this, 'capture_saved_values' ), 1 );

		$_POST  = array();
		$_FILES = array();
	}

	/**
	 * Removes local fields, request data, menu data and filters.
	 */
	public function tear_down() {
		foreach ( $this->test_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}
		$this->test_filters = array();

		acf_reset_local();
		acf_enable_local();
		$this->reset_stores();
		acf_set_data( 'nav_menu_id', null );

		$_POST  = array();
		$_FILES = array();

		parent::tear_down();
	}

	/**
	 * Records $_POST['acf'] when a save runs.
	 *
	 * @param integer|string $post_id The ACF post ID being saved.
	 */
	public function capture_saved_values( $post_id ) {
		/* phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Test reads the request it built. */
		$this->saved_values[ $post_id ] = isset( $_POST['acf'] ) ? $_POST['acf'] : array();
	}

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
	 *
	 * This test verifies the early-exit code path executes without error.
	 */
	public function test_acf_validate_save_post_returns_early_without_menu_item_data() {
		$form_nav_menu = new acf_form_nav_menu();

		// Clear any menu-item-acf data.
		unset( $_POST['menu-item-acf'] );

		// Verify no validation errors are generated when data is empty.
		acf_reset_validation_errors();
		$form_nav_menu->acf_validate_save_post();
		$errors = acf_get_validation_errors();

		$this->assertEmpty( $errors, 'No validation errors should be generated when no menu-item-acf data' );
	}

	/**
	 * Test acf_validate_save_post processes menu item values.
	 *
	 * This test verifies the method executes the validation code path.
	 * Since test field keys don't exist, no actual validation errors occur.
	 */
	public function test_acf_validate_save_post_processes_menu_items() {
		$form_nav_menu = new acf_form_nav_menu();

		// Set up menu-item-acf data with fake field key.
		$_POST['menu-item-acf'] = array(
			123 => array(
				'field_test' => 'test value',
			),
		);

		// Verify method executes without error.
		acf_reset_validation_errors();
		$form_nav_menu->acf_validate_save_post();
		$errors = acf_get_validation_errors();

		// Cleanup.
		unset( $_POST['menu-item-acf'] );

		// No errors expected since field_test doesn't exist as a registered field.
		$this->assertEmpty( $errors, 'No validation errors should occur for non-existent fields' );
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

		// Track if save was called.
		$saved = false;
		add_action(
			'acf/save_post',
			function () use ( &$saved ) {
				$saved = true;
			}
		);

		$form_nav_menu->update_nav_menu_items( 123 );

		$this->assertFalse( $saved, 'acf_save_post should not be called when no menu-item-acf data' );
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

	/**
	 * Test admin_enqueue_scripts returns early when not on nav-menus screen.
	 *
	 * In test environment, get_current_screen() returns null, so acf_is_screen()
	 * returns false, triggering the early return path.
	 */
	public function test_admin_enqueue_scripts_bails_when_not_on_nav_menus_screen() {
		$form_nav_menu = new acf_form_nav_menu();

		// Call admin_enqueue_scripts - should return early since no screen is set.
		$form_nav_menu->admin_enqueue_scripts();

		// admin_footer action should NOT be added when not on nav-menus screen.
		$this->assertFalse(
			has_action( 'admin_footer', array( $form_nav_menu, 'admin_footer' ) ),
			'admin_footer action should not be added when not on nav-menus screen'
		);
	}

	/**
	 * A menu save keeps fields from groups located on the menu and drops the rest.
	 */
	public function test_update_nav_menu_drops_fields_not_located_on_menu() {
		$this->add_menu_groups();
		$this->submit(
			array(
				'field_form_nav_menu_menu'    => 'kept',
				'field_form_nav_menu_other'   => 'dropped',
				'field_form_nav_menu_item'    => 'dropped',
				'field_form_nav_menu_unknown' => 'dropped',
			)
		);

		( new acf_form_nav_menu() )->update_nav_menu( $this->menu_id );

		$this->assertSame( array( 'term_7' => array( 'field_form_nav_menu_menu' => 'kept' ) ), $this->saved_values );
	}

	/**
	 * The menu allowed-keys filter can allow extra keys, and receives the ACF menu ID.
	 */
	public function test_update_nav_menu_allowed_keys_filter_adds_keys() {
		$this->add_menu_groups();
		$received = null;
		$this->add_test_filter(
			'acf/form/nav_menu/allowed_field_keys',
			function ( $keys, $post_id ) use ( &$received ) {
				$received = $post_id;
				$keys[]   = 'field_form_nav_menu_injected';
				return $keys;
			},
			10,
			2
		);
		$this->submit(
			array(
				'field_form_nav_menu_menu'     => 'kept',
				'field_form_nav_menu_injected' => 'kept',
			)
		);

		( new acf_form_nav_menu() )->update_nav_menu( $this->menu_id );

		$this->assertSame( 'term_7', $received );
		$this->assertSame(
			array(
				'field_form_nav_menu_menu'     => 'kept',
				'field_form_nav_menu_injected' => 'kept',
			),
			$this->saved_values['term_7']
		);
	}

	/**
	 * A menu save accepts fields listed for the menu and ignores lists signed for another menu.
	 */
	public function test_update_nav_menu_accepts_only_fields_listed_for_menu() {
		$this->add_menu_groups();
		$this->submit(
			array(
				'field_form_nav_menu_other'   => 'kept',
				'field_form_nav_menu_unknown' => 'dropped',
			)
		);
		$_POST['_acf_fields'] = array(
			'term_7' => array( $this->get_signed_field_list( 'term_7', array( 'field_form_nav_menu_other' ) ) ),
			'term_8' => array( $this->get_signed_field_list( 'term_8', array( 'field_form_nav_menu_unknown' ) ) ),
		);

		( new acf_form_nav_menu() )->update_nav_menu( $this->menu_id );

		$this->assertSame( array( 'field_form_nav_menu_other' => 'kept' ), $this->saved_values['term_7'] );
	}

	/**
	 * Menu item saves keep fields located on menu items, and skip IDs that are not menu items.
	 */
	public function test_update_nav_menu_items_drop_fields_not_located_on_items() {
		$this->add_menu_groups();
		$item_id = $this->insert_menu_item();
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Not a menu item',
				'post_status' => 'publish',
			)
		);
		$this->submit( array() );
		$_POST['menu-item-acf'] = array(
			$item_id => array(
				'field_form_nav_menu_item' => 'kept',
				'field_form_nav_menu_menu' => 'dropped',
			),
			$post_id => array( 'field_form_nav_menu_item' => 'dropped' ),
		);

		( new acf_form_nav_menu() )->update_nav_menu( $this->menu_id );

		$this->assertSame( array( $item_id => array( 'field_form_nav_menu_item' => 'kept' ) ), $this->saved_values );
	}

	/**
	 * The menu item allowed-keys filter can allow extra keys, and receives the item ID.
	 */
	public function test_update_nav_menu_items_allowed_keys_filter_adds_keys() {
		$this->add_menu_groups();
		$item_id  = $this->insert_menu_item();
		$received = null;
		$this->add_test_filter(
			'acf/form/nav_menu_item/allowed_field_keys',
			function ( $keys, $post_id ) use ( &$received ) {
				$received = $post_id;
				$keys[]   = 'field_form_nav_menu_injected';
				return $keys;
			},
			10,
			2
		);
		$this->submit( array() );
		$_POST['menu-item-acf'] = array(
			$item_id => array(
				'field_form_nav_menu_item'     => 'kept',
				'field_form_nav_menu_injected' => 'kept',
			),
		);

		( new acf_form_nav_menu() )->update_nav_menu( $this->menu_id );

		$this->assertSame( $item_id, $received );
		$this->assertSame(
			array(
				'field_form_nav_menu_item'     => 'kept',
				'field_form_nav_menu_injected' => 'kept',
			),
			$this->saved_values[ $item_id ]
		);
	}

	/**
	 * Each menu item accepts only the fields listed for itself.
	 */
	public function test_update_nav_menu_items_accept_only_fields_listed_for_item() {
		$this->add_menu_groups();
		$item_id       = $this->insert_menu_item();
		$other_item_id = $this->insert_menu_item();
		$this->submit( array() );
		$_POST['menu-item-acf'] = array(
			$item_id       => array(
				'field_form_nav_menu_other'   => 'kept',
				'field_form_nav_menu_unknown' => 'dropped',
			),
			$other_item_id => array( 'field_form_nav_menu_other' => 'dropped' ),
		);
		$_POST['_acf_fields']   = array(
			$item_id       => array( $this->get_signed_field_list( (string) $item_id, array( 'field_form_nav_menu_other' ) ) ),
			$other_item_id => array( $this->get_signed_field_list( (string) $other_item_id, array( 'field_form_nav_menu_unknown' ) ) ),
		);

		( new acf_form_nav_menu() )->update_nav_menu( $this->menu_id );

		$this->assertSame( array( 'field_form_nav_menu_other' => 'kept' ), $this->saved_values[ $item_id ] );
		$this->assertArrayNotHasKey( $other_item_id, $this->saved_values );
	}

	/**
	 * Registers field groups located on the menu, on another menu, and on the menu's items.
	 */
	private function add_menu_groups() {
		$this->add_group( 'group_form_nav_menu_menu', 'nav_menu', '7', 'field_form_nav_menu_menu' );
		$this->add_group( 'group_form_nav_menu_other', 'nav_menu', '8', 'field_form_nav_menu_other' );
		$this->add_group( 'group_form_nav_menu_item', 'nav_menu_item', '7', 'field_form_nav_menu_item' );
	}

	/**
	 * Inserts a custom link menu item.
	 *
	 * @return integer The menu item ID.
	 */
	private function insert_menu_item() {
		$item_id = wp_insert_post(
			array(
				'post_type'   => 'nav_menu_item',
				'post_title'  => 'Menu item',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $item_id, '_menu_item_type', 'custom' );

		return $item_id;
	}

	/**
	 * Builds a nonce-verified menu form submission.
	 *
	 * @param array $values The submitted acf values for the menu.
	 */
	private function submit( array $values ) {
		$_POST['_acf_screen'] = 'nav_menu';
		$_POST['_acf_nonce']  = wp_create_nonce( 'nav_menu' );
		$_POST['acf']         = $values;
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
	 * Registers a local field group with one text field.
	 *
	 * @param string $group_key The field group key.
	 * @param string $param     The location rule param.
	 * @param string $value     The location rule value.
	 * @param string $field_key The field key.
	 */
	private function add_group( $group_key, $param, $value, $field_key ) {
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
							'param'    => $param,
							'operator' => '==',
							'value'    => $value,
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
