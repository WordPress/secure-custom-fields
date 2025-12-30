<?php
/**
 * Adds SCF functionality to WooCommerce HPOS order pages.
 *
 * @package    SCF
 * @subpackage Forms
 */

namespace SCF\Forms;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Adds ACF metaboxes to the new WooCommerce order screen.
 */
class WC_Order {

	/**
	 * Constructs the ACF_Form_WC_Order class.
	 *
	 * @since 6.5
	 */
	public function __construct() {
		add_action( 'load-woocommerce_page_wc-orders', array( $this, 'initialize' ) );
		add_action( 'woocommerce_update_order', array( $this, 'save_order' ), 10, 1 );
		// Defer registering other order type hooks to after all order types are registered
		add_action( 'wp_loaded', array( $this, 'register_order_type_hooks' ) );
	}

	/**
	 * Enqueues ACF scripts on the WooCommerce order page and
	 * registers actions specific to that page.
	 *
	 * @since 6.5
	 *
	 * @return void
	 */
	public function initialize() {
		acf_enqueue_scripts( array( 'uploader' => true ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
	}

	/**
	 * Registers initialization hooks for all WooCommerce order types.
	 *
	 * @since 6.8.0
	 * @return void
	 */
	public function register_order_type_hooks() {

		$order_types = wc_get_order_types( 'view-orders' );

		foreach ( $order_types as $order_type ) {
			// shop_order uses the base hook without suffix
			if ( 'shop_order' === $order_type ) {
				continue;
			}
			add_action( 'load-woocommerce_page_wc-orders--' . $order_type, array( $this, 'initialize' ) );
		}
	}

	/**
	 * Adds ACF metaboxes to the WooCommerce Order pages.
	 *
	 * @since 6.5
	 *
	 * @param string   $post_type The current post type.
	 * @param \WP_Post $post      The WP_Post object or the WC_Order object.
	 * @return void
	 */
	public function add_meta_boxes( $post_type, $post ) {
		// Storage for localized postboxes.
		$postboxes = array();

		$order = ( $post instanceof \WP_Post ) ? wc_get_order( $post->ID ) : $post;
		if ( ! $order ) {
			return;
		}

		// Dynamically get order type from the order object
		$location = $order->get_type();

		// Determine screen ID based on HPOS status and order type
		if ( $this->is_hpos_enabled() ) {
			$screen = $this->get_hpos_screen_id( $location );
		} else {
			$screen = $location;
		}

		// Get field groups for this screen.
		$field_groups = acf_get_field_groups(
			array(
				'post_id'   => $order->get_id(),
				'post_type' => $location,
			)
		);

		// Loop over field groups.
		if ( $field_groups ) {
			foreach ( $field_groups as $field_group ) {
				$id       = "acf-{$field_group['key']}"; // acf-group_123
				$context  = $field_group['position'];    // normal, side, acf_after_title
				$priority = 'core';                      // high, core, default, low

				// Allow field groups assigned to after title to still be rendered.
				if ( 'acf_after_title' === $context ) {
					$context = 'normal';
				}

				/**
				 * Filters the metabox priority.
				 *
				 * @since 6.5
				 *
				 * @param string $priority    The metabox priority (high, core, default, low).
				 * @param array  $field_group The field group array.
				 */
				$priority = apply_filters( 'acf/input/meta_box_priority', $priority, $field_group );

				// Localize data
				$postboxes[] = array(
					'id'    => $id,
					'key'   => $field_group['key'],
					'style' => $field_group['style'],
					'label' => $field_group['label_placement'],
					'edit'  => acf_get_field_group_edit_link( $field_group['ID'] ),
				);

				// Add the meta box.
				add_meta_box(
					$id,
					acf_esc_html( acf_get_field_group_title( $field_group ) ),
					array( $this, 'render_meta_box' ),
					$screen,
					$context,
					$priority,
					array( 'field_group' => $field_group )
				);
			}

			// Localize postboxes.
			acf_localize_data(
				array(
					'postboxes' => $postboxes,
				)
			);
		}

		// Removes the WordPress core "Custom Fields" meta box.
		if ( acf_get_setting( 'remove_wp_meta_box' ) ) {
			remove_meta_box( 'order_custom', $screen, 'normal' );
		}

		// Add hidden input fields.
		add_action( 'order_edit_form_top', array( $this, 'order_edit_form_top' ) );

		/**
		 * Fires after metaboxes have been added.
		 *
		 * @date    13/12/18
		 * @since   ACF 5.8.0
		 *
		 * @param string   $post_type    The post type.
		 * @param \WP_Post $post         The post being edited.
		 * @param array    $field_groups The field groups added.
		 */
		do_action( 'acf/add_meta_boxes', $post_type, $post, $field_groups );
	}

	/**
	 * Gets the HPOS screen ID for an order type.
	 *
	 * @since 6.8.0
	 *
	 * @param string $order_type The order type (e.g., 'shop_order', 'shop_subscription', 'shop_order_charge').
	 * @return string The screen ID.
	 */
	protected function get_hpos_screen_id( string $order_type ): string {
		// Check for WooCommerce Subscriptions helper function
		if ( 'shop_subscription' === $order_type && function_exists( 'wcs_get_page_screen_id' ) ) {
			return wcs_get_page_screen_id( 'shop_subscription' );
		}

		// For shop_order, use the standard WC function
		if ( 'shop_order' === $order_type ) {
			return wc_get_page_screen_id( 'shop-order' );
		}

		// For custom order types, construct the screen ID
		// Pattern: woocommerce_page_wc-orders--{order_type}
		return 'woocommerce_page_wc-orders--' . $order_type;
	}

	/**
	 * Renders hidden fields.
	 *
	 * @since 6.5
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 * @return void
	 */
	public function order_edit_form_top( $order ) {
		// Render post data.
		acf_form_data(
			array(
				'screen'  => 'post',
				'post_id' => 'woo_order_' . $order->get_id(),
			)
		);
	}

	/**
	 * Renders the ACF metabox HTML.
	 *
	 * @since 6.5
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Can be a standard \WP_Post object or the \WC_Order object.
	 * @param array              $metabox       The add_meta_box() args.
	 * @return  void
	 */
	public function render_meta_box( $post_or_order, $metabox ) {
		$order       = ( $post_or_order instanceof \WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		$field_group = $metabox['args']['field_group'];

		// Render fields.
		$fields = acf_get_fields( $field_group );
		acf_render_fields( $fields, 'woo_order_' . $order->get_id(), 'div', $field_group['instruction_placement'] );
	}

	/**
	 * Checks if WooCommerce HPOS is enabled.
	 *
	 * @since ACF 6.4.2
	 *
	 * @return boolean
	 */
	public function is_hpos_enabled(): bool {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return true;
		}

		return false;
	}

	/**
	 * Saves ACF fields to the current order.
	 *
	 * @since 6.5
	 *
	 * @param integer $order_id The order ID.
	 * @return void
	 */
	public function save_order( int $order_id ) {
		// Bail if not using HPOS to prevent a double-save.
		if ( ! $this->is_hpos_enabled() ) {
			return;
		}
		// Remove the action to prevent an infinite loop via $order->save().
		remove_action( 'woocommerce_update_order', array( $this, 'save_order' ), 10 );
		acf_save_post( 'woo_order_' . $order_id );
	}
}
