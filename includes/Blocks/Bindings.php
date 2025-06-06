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
			register_block_bindings_source(
				'acf/field',
				array(
					'label'              => _x( 'SCF Fields', 'The core SCF block binding source name for fields on the current page', 'secure-custom-fields' ),
					'get_value_callback' => array( $this, 'get_value' ),
				)
			);
			register_block_bindings_source(
				'scf/experimental-field',
				array(
					'label'              => _x( 'SCF Fields', 'The core SCF block binding source name for fields on the current page', 'secure-custom-fields' ),
					'uses_context'       => array( 'postId', 'postType' ),
					'get_value_callback' => array( $this, 'scf_get_block_binding_value' ),
				)
			);
	}

	/**
	 * Handle returning the block binding value for an ACF meta value.
	 *
	 * @since SCF 6.5
	 *
	 * @param array     $source_attrs     An array of the source attributes requested.
	 * @param \WP_Block $block_instance  The block instance.
	 * @param string    $attribute_name The block's bound attribute name.
	 * @return string|null The block binding value or an empty string on failure.
	 */
	public function scf_get_block_binding_value( $source_attrs, $block_instance, $attribute_name ) {
		$post_id = $block_instance->context['postId'] ?? get_the_ID();

		// Ensure we're using the parent post ID if this is a revision
		if ( $post_id && wp_is_post_revision( $post_id ) ) {
			$post_id = wp_get_post_parent_id( $post_id );
		}

		$field_name = $source_attrs['field'] ?? '';

		if ( ! $post_id || ! $field_name ) {
			return '';
		}

		$value = get_field( $field_name, $post_id );
		// Handle different field types based on attribute
		switch ( $attribute_name ) {
			case 'content':
				return is_array( $value ) ? ( $value['alt'] ?? '' ) : (string) $value;
			case 'url':
				if ( is_array( $value ) && isset( $value['url'] ) ) {
					return $value['url'];
				}
				if ( is_numeric( $value ) ) {
					return wp_get_attachment_url( $value );
				}
				return (string) $value;
			case 'alt':
				if ( is_array( $value ) && isset( $value['alt'] ) ) {
					return $value['alt'];
				}
				if ( is_numeric( $value ) ) {
					return get_post_meta( $value, '_wp_attachment_image_alt', true );
				}
				return '';
			case 'id':
				if ( is_array( $value ) && isset( $value['id'] ) ) {
					return (string) $value['id'];
				}
				if ( is_numeric( $value ) ) {
					return (string) $value;
				}
				return '';
			default:
				return is_string( $value ) ? $value : '';
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

			$value = $field['value'];

			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			// If we're not a scalar we'd throw an error, so return early for safety.
			if ( ! is_scalar( $value ) ) {
				$value = null;
			}
		}

		return apply_filters( 'acf/blocks/binding_value', $value, $source_attrs, $block_instance, $attribute_name );
	}
}
