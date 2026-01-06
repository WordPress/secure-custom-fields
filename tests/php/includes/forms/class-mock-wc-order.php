<?php
/**
 * Mock WC_Order class for testing.
 *
 * @package wordpress/secure-custom-fields
 */

/**
 * Mock WC_Order class for testing SCF WC_Order integration.
 */
class Mock_WC_Order {

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Order type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Constructor.
	 *
	 * @param int    $id   Order ID.
	 * @param string $type Order type.
	 */
	public function __construct( $id = 1, $type = 'shop_order' ) {
		$this->id   = $id;
		$this->type = $type;
	}

	/**
	 * Get order ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get order type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}
}
