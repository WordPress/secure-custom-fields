<?php
/**
 * Inline token (Bit) registry for SCF fields.
 *
 * @package wordpress/secure-custom-fields
 */

namespace SCF\Bits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Stores inline-token ("Bit") registrations.
 *
 * A Bit is a small dynamic-content placeholder that lives inside a RichText
 * string (paragraph, heading, list-item). Persisted as
 * `<span class="scf-field-bit" data-bit="<name>" ...>fallback</span>` and
 * resolved at render time by the walker.
 *
 * Unlike block bindings (whole-block attribute replacement), Bits are inline.
 */
class Registry {

	/**
	 * Singleton instance.
	 *
	 * @var Registry|null
	 */
	private static $instance;

	/**
	 * Registered bits keyed by name.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $bits = array();

	/**
	 * Whether the built-in `scf/field` bit has been auto-registered.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Singleton accessor.
	 *
	 * @return Registry
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers the built-in bits and wires any third-party hooks.
	 *
	 * Idempotent. Safe to call multiple times.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->register_default_bits();

		/**
		 * Fires after the SCF Bits registry has booted.
		 *
		 * Use this hook to register third-party inline-token ("Bit") sources.
		 *
		 * @since [version]
		 *
		 * @param Registry $registry The registry instance.
		 */
		do_action( 'scf/bits/register', $this );
	}

	/**
	 * Registers the built-in `scf/field` bit.
	 *
	 * @return void
	 */
	private function register_default_bits() {
		$this->register(
			'scf/field',
			array(
				'label'               => _x( 'Custom Field', 'Default inline-token source name for SCF fields', 'secure-custom-fields' ),
				'category'            => 'SCF',
				'allowed_block_types' => array(),
				'attributes'          => array(
					'key'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'target'   => array(
						'type'    => 'string',
						'default' => 'current',
					),
					'fallback' => array(
						'type'    => 'string',
						'default' => '',
					),
					'format'   => array(
						'type'    => 'enum',
						'values'  => array( 'text', 'html' ),
						'default' => 'text',
					),
				),
				'render_callback'     => array( __NAMESPACE__ . '\\Walker', 'render_bit' ),
			)
		);
	}

	/**
	 * Registers an inline token source.
	 *
	 * @param string $name Source name (e.g. `scf/field`).
	 * @param array  $args {
	 *     Optional. Bit configuration.
	 *
	 *     @type string        $label               Human-readable label. Default empty.
	 *     @type string        $category            Picker category. Default empty.
	 *     @type array<string> $allowed_block_types Block types the Bit may appear in.
	 *                                             Empty array means "any RichText block".
	 *                                             Default empty array.
	 *     @type array         $attributes          Attribute schema. Default empty array.
	 *     @type callable      $render_callback     Receives ( attrs, parsed_block, block_instance ).
	 *                                             Returns a string of replacement HTML.
	 *                                             Default null (uses default walker renderer).
	 * }
	 * @return bool True on success, false on validation failure.
	 */
	public function register( $name, $args = array() ) {
		$name = is_string( $name ) ? trim( $name ) : '';

		if ( '' === $name ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'A bit name is required.', 'secure-custom-fields' ), '[version]' );
			return false;
		}

		if ( isset( $this->bits[ $name ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf( /* translators: %s bit name */ esc_html__( 'The bit "%s" is already registered.', 'secure-custom-fields' ), esc_html( $name ) ),
				'[version]'
			);
			return false;
		}

		$args = is_array( $args ) ? $args : array();

		if ( isset( $args['render_callback'] ) ) {
			$cb = $args['render_callback'];
			if ( ! is_callable( $cb ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf( /* translators: %s bit name */ esc_html__( 'render_callback for bit "%s" is not callable.', 'secure-custom-fields' ), esc_html( $name ) ),
					'[version]'
				);
				return false;
			}
		}

		$defaults = array(
			'label'               => '',
			'category'            => '',
			'allowed_block_types' => array(),
			'attributes'          => array(),
			'render_callback'     => null,
		);

		$this->bits[ $name ] = array_merge( $defaults, $args );

		return true;
	}

	/**
	 * Removes a registered bit.
	 *
	 * @param string $name Bit name.
	 * @return bool True if removed, false if not found.
	 */
	public function unregister( $name ) {
		if ( ! isset( $this->bits[ $name ] ) ) {
			return false;
		}
		unset( $this->bits[ $name ] );
		return true;
	}

	/**
	 * Returns one registered bit by name.
	 *
	 * @param string $name Bit name.
	 * @return array<string, mixed>|null
	 */
	public function get( $name ) {
		return $this->bits[ $name ] ?? null;
	}

	/**
	 * Returns all registered bits.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all() {
		return $this->bits;
	}

	/**
	 * Returns the names of bits allowed in the given block type.
	 *
	 * @param string $block_type Block name (e.g. `core/paragraph`).
	 * @return array<string> Bit names.
	 */
	public function get_for_block_type( $block_type ) {
		$out = array();
		foreach ( $this->bits as $name => $args ) {
			$allowed = $args['allowed_block_types'];
			if ( empty( $allowed ) ) {
				$out[] = $name;
				continue;
			}
			if ( in_array( $block_type, $allowed, true ) ) {
				$out[] = $name;
			}
		}
		return $out;
	}
}
