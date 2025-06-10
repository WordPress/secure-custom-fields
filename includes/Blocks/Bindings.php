<?php
/**
 * SCF Block Bindings
 *
 * @since ACF 6.2.8
 * @package wordpress/secure-custom-fields
 */

namespace ACF\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The core SCF Blocks binding class.
 */
class Bindings {
	/**
	 * Block Bindings constructor.
	 */
	public function __construct() {
		// Final check we're on WP 6.5 or newer.
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		add_action( 'acf/init', array( $this, 'register_binding_sources' ) );
	}

	/**
	 * Hooked to acf/init, register our binding sources.
	 */
	public function register_binding_sources() {
		if ( acf_get_setting( 'enable_block_bindings' ) ) {
			register_block_bindings_source(
				'acf/field',
				array(
					'label'              => _x( 'SCF Fields', 'The core SCF block binding source name for fields on the current page', 'secure-custom-fields' ),
					'get_value_callback' => array( $this, 'get_value' ),
					'uses_context'       => array( 'postId', 'postType' ),
				)
			);
		}
	}

	/**
	 * Handle returning the block binding value for an ACF meta value.
	 *
	 * @since ACF 6.2.8
	 *
	 * @param array     $source_attrs   An array of the source attributes requested.
	 * @param \WP_Block $block_instance The block instance.
	 * @param string    $attribute_name The block's bound attribute name.
	 * @return string|null The block binding value or an empty string on failure.
	 */
	public function get_value( array $source_attrs, \WP_Block $block_instance, string $attribute_name ) {
		if ( ! isset( $source_attrs['key'] ) || ! is_string( $source_attrs['key'] ) ) {
			$value = '';
		} else {
			$field = get_field_object( $source_attrs['key'], false, true, true, true );

			if ( ! $field ) {
				return '';
			}

			if ( ! acf_field_type_supports( $field['type'], 'bindings', true ) ) {
				if ( is_preview() ) {
					return apply_filters( 'acf/bindings/field_not_supported_message', '[' . esc_html__( 'The requested SCF field type does not support output in Block Bindings or the SCF shortcode.', 'secure-custom-fields' ) . ']' );
				} else {
					return '';
				}
			}

			if ( isset( $field['allow_in_bindings'] ) && ! $field['allow_in_bindings'] ) {
				if ( is_preview() ) {
					return apply_filters( 'acf/bindings/field_not_allowed_message', '[' . esc_html__( 'The requested SCF field is not allowed to be output in bindings or the SCF Shortcode.', 'secure-custom-fields' ) . ']' );
				} else {
					return '';
				}
			}

			switch ( $attribute_name ) {
				case 'id':
				case 'alt':
				case 'title':
					// The value is in the field of the same name.
					$value = $field['value'][ $attribute_name ] ?? '';
					break;
				case 'url':
					// The URL is the field value.
					$value = $field['value']['url'] ?? $field['value'] ?? '';
					break;
				case 'rel':
					// Handle checkbox field for rel attribute by joining array values.
					if ( is_array( $field['value'] ) ) {
						$value = implode( ' ', $field['value'] );
					} else {
						$value = $field['value'] ?? '';
					}
					break;
				default:
					$value = $field['value'];

					if ( is_array( $value ) ) {
						$value = wp_json_encode( $value );
					} elseif ( ! is_scalar( $value ) && null !== $value ) {
						$value = '';
					}
			}
		}

		return apply_filters( 'acf/blocks/binding_value', $value, $source_attrs, $block_instance, $attribute_name );
	}
}
