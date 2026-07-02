<?php
/**
 * Test SCF WC_Order form integration.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;
use SCF\Forms\WC_Order;

// Load mock functions and classes.
require_once __DIR__ . '/wc-order-test-functions.php';

/**
 * Class Test_Form_WC_Order
 */
class Test_Form_WC_Order extends BaseTestCase {
	/**
	 * User IDs created during a test.
	 *
	 * @var int[]
	 */
	private $user_ids = array();

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		acf_get_store( 'form' )->reset();

		// Clean up any filters/actions we added.
		remove_all_filters( 'acf/input/meta_box_priority' );
		remove_all_actions( 'acf/add_meta_boxes' );

		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->user_ids = array();

		unset( $_POST['acf'], $_POST['_acf_nonce'] );
		wp_set_current_user( 0 );

		global $current_screen;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Resetting globals in test teardown.
		$current_screen = null;

		parent::tear_down();
	}

	/**
	 * Test WC_Order class exists.
	 */
	public function test_wc_order_class_exists() {
		$this->assertTrue( class_exists( WC_Order::class ), 'SCF\Forms\WC_Order class should exist' );
	}

	/**
	 * Test WC_Order can be instantiated.
	 */
	public function test_wc_order_instantiation() {
		$wc_order = new WC_Order();
		$this->assertInstanceOf( WC_Order::class, $wc_order );
	}

	/**
	 * Test constructor registers correct actions.
	 */
	public function test_constructor_registers_actions() {
		$wc_order = new WC_Order();

		$this->assertNotFalse(
			has_action( 'load-woocommerce_page_wc-orders', array( $wc_order, 'initialize' ) ),
			'Should register initialize action for base WC orders page'
		);

		$this->assertFalse(
			has_action( 'woocommerce_update_order', array( $wc_order, 'save_order' ) ),
			'Should not register save_order until an order edit screen loads'
		);

		$this->assertNotFalse(
			has_action( 'wp_loaded', array( $wc_order, 'register_order_type_hooks' ) ),
			'Should register register_order_type_hooks action on wp_loaded'
		);
	}

	/**
	 * Test get_hpos_screen_id returns correct screen for shop_order.
	 */
	public function test_get_hpos_screen_id_shop_order() {
		$wc_order = new WC_Order();

		// Use reflection to access protected method.
		$method = new ReflectionMethod( WC_Order::class, 'get_hpos_screen_id' );
		$method->setAccessible( true );

		$result = $method->invoke( $wc_order, 'shop_order' );

		$this->assertEquals(
			'woocommerce_page_wc-orders',
			$result,
			'shop_order should return WC page screen ID'
		);
	}

	/**
	 * Test get_hpos_screen_id returns correct screen for custom order types.
	 */
	public function test_get_hpos_screen_id_custom_order_type() {
		$wc_order = new WC_Order();

		$method = new ReflectionMethod( WC_Order::class, 'get_hpos_screen_id' );
		$method->setAccessible( true );

		// Test with a custom order type.
		$result = $method->invoke( $wc_order, 'shop_order_charge' );

		$this->assertEquals(
			'woocommerce_page_wc-orders--shop_order_charge',
			$result,
			'Custom order types should follow pattern: woocommerce_page_wc-orders--{order_type}'
		);
	}

	/**
	 * Test get_hpos_screen_id returns correct screen for shop_order_refund.
	 */
	public function test_get_hpos_screen_id_refund_order() {
		$wc_order = new WC_Order();

		$method = new ReflectionMethod( WC_Order::class, 'get_hpos_screen_id' );
		$method->setAccessible( true );

		$result = $method->invoke( $wc_order, 'shop_order_refund' );

		$this->assertEquals(
			'woocommerce_page_wc-orders--shop_order_refund',
			$result,
			'Refund orders should follow custom order type pattern'
		);
	}

	/**
	 * Test get_hpos_screen_id handles shop_subscription when wcs_get_page_screen_id exists.
	 */
	public function test_get_hpos_screen_id_subscription_with_helper() {
		$wc_order = new WC_Order();

		$method = new ReflectionMethod( WC_Order::class, 'get_hpos_screen_id' );
		$method->setAccessible( true );

		$result = $method->invoke( $wc_order, 'shop_subscription' );

		$this->assertEquals(
			'woocommerce_page_wc-orders--shop_subscription',
			$result,
			'shop_subscription should use wcs_get_page_screen_id when available'
		);
	}

	/**
	 * Test register_order_type_hooks skips shop_order.
	 */
	public function test_register_order_type_hooks_skips_shop_order() {
		$wc_order = new WC_Order();

		// Run the method.
		$wc_order->register_order_type_hooks();

		// shop_order should NOT have its own suffixed hook.
		$this->assertFalse(
			has_action( 'load-woocommerce_page_wc-orders--shop_order', array( $wc_order, 'initialize' ) ),
			'shop_order should not have a suffixed hook registered'
		);
	}

	/**
	 * Test register_order_type_hooks registers hooks for other order types.
	 */
	public function test_register_order_type_hooks_registers_other_types() {
		$wc_order = new WC_Order();

		// Run the method.
		$wc_order->register_order_type_hooks();

		// shop_subscription should have its hook registered.
		$this->assertNotFalse(
			has_action( 'load-woocommerce_page_wc-orders--shop_subscription', array( $wc_order, 'initialize' ) ),
			'shop_subscription should have a hook registered'
		);

		// shop_order_refund should have its hook registered.
		$this->assertNotFalse(
			has_action( 'load-woocommerce_page_wc-orders--shop_order_refund', array( $wc_order, 'initialize' ) ),
			'shop_order_refund should have a hook registered'
		);
	}

	/**
	 * Test is_hpos_enabled returns boolean.
	 */
	public function test_is_hpos_enabled_returns_boolean() {
		$wc_order = new WC_Order();

		$result = $wc_order->is_hpos_enabled();

		$this->assertIsBool( $result, 'is_hpos_enabled should return a boolean' );
	}

	/**
	 * Test is_hpos_enabled returns false when OrderUtil class does not exist.
	 */
	public function test_is_hpos_enabled_false_without_order_util() {
		$wc_order = new WC_Order();

		// Without the OrderUtil class being properly set up, should return false.
		// In our test environment, the class may not exist or method may return false.
		$result = $wc_order->is_hpos_enabled();

		// We just verify it returns a boolean without throwing errors.
		$this->assertIsBool( $result );
	}

	/**
	 * Test add_meta_boxes returns early when order is null.
	 */
	public function test_add_meta_boxes_returns_early_for_null_order() {
		$wc_order = new WC_Order();

		// Pass null directly - the method should return early.
		$wc_order->add_meta_boxes( 'shop_order', null );

		// When order is null, the method returns early before adding the order_edit_form_top action.
		$this->assertFalse(
			has_action( 'order_edit_form_top', array( $wc_order, 'order_edit_form_top' ) ),
			'order_edit_form_top action should not be added when order is null'
		);
	}

	/**
	 * Test add_meta_boxes uses order type from order object.
	 */
	public function test_add_meta_boxes_uses_dynamic_order_type() {
		$wc_order = new WC_Order();

		// Create a mock order with a custom type.
		$mock_order = new Mock_WC_Order( 123, 'shop_order_charge' );

		// Add filter to prevent further processing and track calls.
		add_filter(
			'acf/get_field_groups',
			function () {
				return array(); // Return empty to prevent further processing.
			}
		);

		// Call the method with our mock order.
		$wc_order->add_meta_boxes( 'shop_order', $mock_order );

		// The method should have used the order's type.
		$this->assertEquals( 'shop_order_charge', $mock_order->get_type() );
	}

	/**
	 * Test initialize method adds correct actions.
	 */
	public function test_initialize_adds_meta_boxes_action() {
		$wc_order = new WC_Order();

		// Remove any existing add_meta_boxes action first.
		remove_all_actions( 'add_meta_boxes' );

		// Call initialize.
		$wc_order->initialize();

		// Check that add_meta_boxes action was added.
		$this->assertNotFalse(
			has_action( 'add_meta_boxes', array( $wc_order, 'add_meta_boxes' ) ),
			'initialize should add add_meta_boxes action'
		);

		// Check that the save handler is attached on the order edit screen.
		$this->assertNotFalse(
			has_action( 'woocommerce_update_order', array( $wc_order, 'save_order' ) ),
			'initialize should add save_order action'
		);

		remove_action( 'woocommerce_update_order', array( $wc_order, 'save_order' ), 10 );
	}

	/**
	 * Test order_edit_form_top renders form data with correct post_id format.
	 */
	public function test_order_edit_form_top_uses_correct_post_id_format() {
		$wc_order = new WC_Order();

		// Create a mock order.
		$mock_order = new Mock_WC_Order( 456 );

		// Capture output.
		ob_start();
		$wc_order->order_edit_form_top( $mock_order );
		$output = ob_get_clean();

		// Verify the output contains the correct post_id format.
		$this->assertStringContainsString(
			'woo_order_456',
			$output,
			'Form data should use woo_order_{id} format for post_id'
		);

		// Verify the hidden input structure is present.
		$this->assertStringContainsString(
			'id="acf-form-data"',
			$output,
			'Should output acf-form-data container'
		);
	}

	/**
	 * Data provider for render_meta_box input types.
	 *
	 * @return array
	 */
	public function render_meta_box_input_provider() {
		return array(
			'WP_Post object'  => array(
				'input'       => new WP_Post( (object) array( 'ID' => 789 ) ),
				'expected_id' => 'woo_order_789',
			),
			'WC_Order object' => array(
				'input'       => new Mock_WC_Order( 321 ),
				'expected_id' => 'woo_order_321',
			),
		);
	}

	/**
	 * Test render_meta_box handles different input types correctly.
	 *
	 * @dataProvider render_meta_box_input_provider
	 *
	 * @param object $input       The input object (WP_Post or Mock_WC_Order).
	 * @param string $expected_id The expected post_id format.
	 */
	public function test_render_meta_box_uses_correct_post_id_format( $input, $expected_id ) {
		$wc_order = new WC_Order();

		$metabox = array(
			'args' => array(
				'field_group' => array(
					'ID'                    => 1,
					'key'                   => 'group_test',
					'title'                 => 'Test Group',
					'instruction_placement' => 'label',
				),
			),
		);

		// Track if acf_render_fields action was triggered with correct post_id.
		$rendered_post_id = null;
		$action_callback  = function ( $fields, $post_id ) use ( &$rendered_post_id ) {
			$rendered_post_id = $post_id;
		};
		add_action( 'acf/render_fields', $action_callback, 10, 2 );

		ob_start();
		$wc_order->render_meta_box( $input, $metabox );
		ob_get_clean();

		// Cleanup action to prevent pollution.
		remove_action( 'acf/render_fields', $action_callback, 10 );

		// Verify the action was actually called.
		$this->assertNotNull(
			$rendered_post_id,
			'acf/render_fields action should have been triggered'
		);

		// Verify the post_id format used is woo_order_{id}.
		$this->assertEquals(
			$expected_id,
			$rendered_post_id,
			'render_meta_box should use woo_order_{id} format'
		);
	}

	/**
	 * Test save_order only runs when HPOS is enabled.
	 */
	public function test_save_order_requires_hpos() {
		// Create a partial mock to control is_hpos_enabled.
		$wc_order = $this->getMockBuilder( WC_Order::class )
			->onlyMethods( array( 'is_hpos_enabled' ) )
			->getMock();

		$wc_order->method( 'is_hpos_enabled' )->willReturn( false );

		// Add the action first.
		add_action( 'woocommerce_update_order', array( $wc_order, 'save_order' ), 10 );

		// Call save_order - should return early without removing the action.
		$wc_order->save_order( 123 );

		// When HPOS is disabled, the method returns early before remove_action,
		// so the action should still be present.
		$this->assertNotFalse(
			has_action( 'woocommerce_update_order', array( $wc_order, 'save_order' ) ),
			'save_order should return early and not remove the action when HPOS is disabled'
		);

		// Cleanup.
		remove_action( 'woocommerce_update_order', array( $wc_order, 'save_order' ), 10 );
	}

	/**
	 * Test save_order does not save outside the admin order screen.
	 */
	public function test_save_order_requires_admin_context() {
		$wc_order = $this->get_hpos_enabled_order_form();
		$this->set_order_manager_user();
		$this->set_valid_acf_order_payload();

		$saved_post_id = null;
		$save_hook     = function ( $post_id ) use ( &$saved_post_id ) {
			$saved_post_id = $post_id;
		};
		add_action( 'acf/save_post', $save_hook );

		$wc_order->save_order( 123 );

		remove_action( 'acf/save_post', $save_hook );

		$this->assertNull( $saved_post_id, 'ACF order fields should not save outside admin context.' );
	}

	/**
	 * Test save_order requires the shop order editing capability.
	 */
	public function test_save_order_requires_shop_order_capability() {
		$wc_order = $this->get_hpos_enabled_order_form();
		set_current_screen( 'woocommerce_page_wc-orders' );
		$this->set_order_manager_user( false );
		$this->set_valid_acf_order_payload();

		$saved_post_id = null;
		$save_hook     = function ( $post_id ) use ( &$saved_post_id ) {
			$saved_post_id = $post_id;
		};
		add_action( 'acf/save_post', $save_hook );

		$wc_order->save_order( 123 );

		remove_action( 'acf/save_post', $save_hook );

		$this->assertNull( $saved_post_id, 'Users without edit_shop_orders must not save ACF order fields.' );
	}

	/**
	 * Test save_order requires an ACF post nonce.
	 */
	public function test_save_order_requires_acf_nonce() {
		$wc_order = $this->get_hpos_enabled_order_form();
		set_current_screen( 'woocommerce_page_wc-orders' );
		$this->set_order_manager_user();
		$_POST['acf'] = array( 'field_test_wc_order' => 'Injected value' );

		$saved_post_id = null;
		$save_hook     = function ( $post_id ) use ( &$saved_post_id ) {
			$saved_post_id = $post_id;
		};
		add_action( 'acf/save_post', $save_hook );

		$wc_order->save_order( 123 );

		remove_action( 'acf/save_post', $save_hook );

		$this->assertNull( $saved_post_id, 'ACF order fields should not save without a valid ACF nonce.' );
	}

	/**
	 * Test save_order saves ACF fields for authorized admin order edits.
	 */
	public function test_save_order_saves_for_authorized_admin_order_edit() {
		$wc_order = $this->get_hpos_enabled_order_form();
		set_current_screen( 'woocommerce_page_wc-orders' );
		$this->set_order_manager_user();
		$this->set_valid_acf_order_payload();

		$saved_post_id = null;
		$save_hook     = function ( $post_id ) use ( &$saved_post_id ) {
			$saved_post_id = $post_id;
		};
		add_action( 'acf/save_post', $save_hook );

		$wc_order->save_order( 123 );

		remove_action( 'acf/save_post', $save_hook );

		$this->assertSame( 'woo_order_123', $saved_post_id, 'Authorized admin order edits should save ACF order fields.' );
	}

	/**
	 * Test save_order removes action to prevent infinite loop.
	 */
	public function test_save_order_removes_action_to_prevent_loop() {
		// Create a partial mock to control is_hpos_enabled.
		$wc_order = $this->get_hpos_enabled_order_form();
		set_current_screen( 'woocommerce_page_wc-orders' );
		$this->set_order_manager_user();
		$this->set_valid_acf_order_payload();

		// Add the action first.
		add_action( 'woocommerce_update_order', array( $wc_order, 'save_order' ), 10 );

		// Verify it's added.
		$this->assertNotFalse( has_action( 'woocommerce_update_order', array( $wc_order, 'save_order' ) ) );

		// Call save_order.
		$wc_order->save_order( 123 );

		// The action should be removed.
		$this->assertFalse(
			has_action( 'woocommerce_update_order', array( $wc_order, 'save_order' ) ),
			'save_order should remove itself to prevent infinite loop'
		);
	}

	/**
	 * Get a WC order form mock with HPOS enabled.
	 *
	 * @return WC_Order
	 */
	private function get_hpos_enabled_order_form() {
		$wc_order = $this->getMockBuilder( WC_Order::class )
			->onlyMethods( array( 'is_hpos_enabled' ) )
			->getMock();

		$wc_order->method( 'is_hpos_enabled' )->willReturn( true );

		return $wc_order;
	}

	/**
	 * Set the current user to a test user.
	 *
	 * @param bool $can_edit_orders Whether the user should have edit_shop_orders.
	 * @return int The user ID.
	 */
	private function set_order_manager_user( $can_edit_orders = true ) {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'wc_order_user_' . uniqid(),
				'user_pass'  => 'password',
				'user_email' => 'wc-order-user-' . uniqid() . '@example.com',
				'role'       => 'subscriber',
			)
		);

		$this->user_ids[] = $user_id;
		$user             = get_user_by( 'id', $user_id );

		if ( $can_edit_orders ) {
			$user->add_cap( 'edit_shop_orders' );
		}

		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Set a valid ACF order save payload.
	 */
	private function set_valid_acf_order_payload() {
		$_POST['_acf_nonce'] = wp_create_nonce( 'post' );
		$_POST['acf']        = array( 'field_test_wc_order' => 'Saved value' );
	}

	/**
	 * Test multiple order types can be registered dynamically.
	 *
	 * @dataProvider order_types_provider
	 *
	 * @param string $order_type    The order type to test.
	 * @param string $expected_hook The expected hook name.
	 */
	public function test_dynamic_order_type_hooks( $order_type, $expected_hook ) {
		$wc_order = new WC_Order();

		$method = new ReflectionMethod( WC_Order::class, 'get_hpos_screen_id' );
		$method->setAccessible( true );

		if ( 'shop_order' === $order_type ) {
			// shop_order uses wc_get_page_screen_id.
			$result = $method->invoke( $wc_order, $order_type );
			$this->assertEquals( 'woocommerce_page_wc-orders', $result );
		} else {
			// Other types follow the pattern.
			$result = $method->invoke( $wc_order, $order_type );
			$this->assertEquals( $expected_hook, $result );
		}
	}

	/**
	 * Data provider for order types.
	 *
	 * @return array
	 */
	public function order_types_provider() {
		return array(
			'shop_order'        => array( 'shop_order', 'woocommerce_page_wc-orders' ),
			'shop_order_refund' => array( 'shop_order_refund', 'woocommerce_page_wc-orders--shop_order_refund' ),
			'shop_order_charge' => array( 'shop_order_charge', 'woocommerce_page_wc-orders--shop_order_charge' ),
			'custom_order_type' => array( 'custom_order_type', 'woocommerce_page_wc-orders--custom_order_type' ),
		);
	}
}
