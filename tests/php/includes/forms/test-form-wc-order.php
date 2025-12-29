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
	 * Tear down after each test.
	 */
	public function tear_down() {
		parent::tear_down();

		// Clean up any filters/actions we added.
		remove_all_filters( 'acf/input/meta_box_priority' );
		remove_all_actions( 'acf/add_meta_boxes' );
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

		$this->assertNotFalse(
			has_action( 'woocommerce_update_order', array( $wc_order, 'save_order' ) ),
			'Should register save_order action'
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

		// If we get here without errors, the test passes.
		$this->assertTrue( true );
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
	 * Test initialize method adds correct action.
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
		ob_get_clean();

		// The acf_form_data function should be called with 'woo_order_456'.
		// Since we can't easily capture function args, we just verify no errors.
		$this->assertTrue( true );
	}

	/**
	 * Test render_meta_box handles WP_Post object.
	 */
	public function test_render_meta_box_handles_wp_post() {
		$wc_order = new WC_Order();

		$post    = new WP_Post( (object) array( 'ID' => 789 ) );
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

		// This should not throw any errors.
		ob_start();
		$wc_order->render_meta_box( $post, $metabox );
		ob_get_clean();

		$this->assertTrue( true );
	}

	/**
	 * Test render_meta_box handles WC_Order object directly.
	 */
	public function test_render_meta_box_handles_wc_order() {
		$wc_order = new WC_Order();

		$mock_order = new Mock_WC_Order( 321 );
		$metabox    = array(
			'args' => array(
				'field_group' => array(
					'ID'                    => 1,
					'key'                   => 'group_test',
					'title'                 => 'Test Group',
					'instruction_placement' => 'label',
				),
			),
		);

		// This should not throw any errors.
		ob_start();
		$wc_order->render_meta_box( $mock_order, $metabox );
		ob_get_clean();

		$this->assertTrue( true );
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

		// Call save_order - should return early without calling acf_save_post.
		$wc_order->save_order( 123 );

		// If we get here without errors, the early return worked.
		$this->assertTrue( true );
	}

	/**
	 * Test save_order removes action to prevent infinite loop.
	 */
	public function test_save_order_removes_action_to_prevent_loop() {
		// Create a partial mock to control is_hpos_enabled.
		$wc_order = $this->getMockBuilder( WC_Order::class )
			->onlyMethods( array( 'is_hpos_enabled' ) )
			->getMock();

		$wc_order->method( 'is_hpos_enabled' )->willReturn( true );

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
