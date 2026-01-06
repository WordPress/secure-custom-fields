<?php
/**
 * Mock WooCommerce functions for WC_Order tests.
 *
 * @package wordpress/secure-custom-fields
 */

// Load mock class.
require_once __DIR__ . '/class-mock-wc-order.php';

/**
 * Mock function for wc_get_order_types.
 *
 * Returns a list of WooCommerce order types for testing.
 *
 * @param string $context The context for order types (unused in mock).
 * @return array Array of order types.
 */
function wc_get_order_types_mock( $context = '' ) {
	unset( $context ); // Silence unused parameter warning.
	return array( 'shop_order', 'shop_subscription', 'shop_order_refund' );
}

/**
 * Mock function for wc_get_order.
 *
 * @param int $order_id The order ID.
 * @return Mock_WC_Order Mock order object.
 */
function wc_get_order_mock( $order_id ) {
	return new Mock_WC_Order( $order_id );
}

/**
 * Mock function for wc_get_page_screen_id.
 *
 * @param string $page The page identifier (unused in mock).
 * @return string The screen ID.
 */
function wc_get_page_screen_id_mock( $page ) {
	unset( $page ); // Silence unused parameter warning.
	return 'woocommerce_page_wc-orders';
}

/**
 * Mock function for wcs_get_page_screen_id.
 *
 * @param string $page The page identifier (unused in mock).
 * @return string The screen ID.
 */
function wcs_get_page_screen_id_mock( $page ) {
	unset( $page ); // Silence unused parameter warning.
	return 'woocommerce_page_wc-orders--shop_subscription';
}

// Define WooCommerce mock functions if they don't exist.
if ( ! function_exists( 'wc_get_order_types' ) ) {
	/**
	 * Mock function for wc_get_order_types.
	 *
	 * @param string $context The context for order types.
	 * @return array Array of order types.
	 */
	function wc_get_order_types( $context = '' ) {
		return wc_get_order_types_mock( $context );
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	/**
	 * Mock function for wc_get_order.
	 *
	 * @param int $order_id The order ID.
	 * @return Mock_WC_Order Mock order object.
	 */
	function wc_get_order( $order_id ) {
		return wc_get_order_mock( $order_id );
	}
}

if ( ! function_exists( 'wc_get_page_screen_id' ) ) {
	/**
	 * Mock function for wc_get_page_screen_id.
	 *
	 * @param string $page The page identifier.
	 * @return string The screen ID.
	 */
	function wc_get_page_screen_id( $page ) {
		return wc_get_page_screen_id_mock( $page );
	}
}

if ( ! function_exists( 'wcs_get_page_screen_id' ) ) {
	/**
	 * Mock function for wcs_get_page_screen_id.
	 *
	 * @param string $page The page identifier.
	 * @return string The screen ID.
	 */
	function wcs_get_page_screen_id( $page ) {
		return wcs_get_page_screen_id_mock( $page );
	}
}
