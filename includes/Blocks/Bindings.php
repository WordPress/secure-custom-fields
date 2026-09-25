<?php
/**
 * SCF Block Bindings
 *
 * @since ACF 6.2.8
 * @package wordpress/secure-custom-fields
 */

namespace SCF\Blocks;

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
					'label'              => _x( 'Custom Fields', 'The core SCF block binding source name for fields on the current page', 'secure-custom-fields' ),
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
					/**
					 * Filters the message shown in the editor preview when a field type cannot be used in bindings.
					 *
					 * @since ACF 6.2.8
					 *
					 * @param string $message The message to display.
					 */
					return apply_filters( 'acf/bindings/field_not_supported_message', '[' . esc_html__( 'The requested SCF field type does not support output in Block Bindings or the SCF shortcode.', 'secure-custom-fields' ) . ']' );
				} else {
					return '';
				}
			}

			if ( isset( $field['allow_in_bindings'] ) && ! $field['allow_in_bindings'] ) {
				if ( is_preview() ) {
					/**
					 * Filters the message shown in the editor preview when a field is not allowed in bindings.
					 *
					 * @since ACF 6.2.8
					 *
					 * @param string $message The message to display.
					 */
					return apply_filters( 'acf/bindings/field_not_allowed_message', '[' . esc_html__( 'The requested SCF field is not allowed to be output in bindings or the SCF Shortcode.', 'secure-custom-fields' ) . ']' );
				} else {
					return '';
				}
			}

			$field_value = $field['value'];

			/**
			 * Filters the field value before it is mapped to a block attribute.
			 *
			 * Use this filter to replace a stored value with data from another
			 * source, for example an external API. The value arriving here is
			 * already formatted and HTML escaped, because the field is loaded
			 * with formatting on. The filtered value is then passed through the
			 * standard attribute mapping, so arrays returned here still resolve
			 * for image and link attributes.
			 *
			 * The filter does not run for field types that opt out of bindings,
			 * for fields with allow_in_bindings turned off, or for sub fields of
			 * repeater, group and clone fields.
			 *
			 * @since SCF 6.9.6
			 *
			 * @param mixed     $field_value    The loaded, formatted field value.
			 * @param array     $field          The field array.
			 * @param array     $source_attrs   The source attributes requested by the binding.
			 * @param \WP_Block $block_instance The block instance.
			 * @param string    $attribute_name The block's bound attribute name.
			 */
			$field_value = apply_filters( 'scf/blocks/binding_field_value', $field_value, $field, $source_attrs, $block_instance, $attribute_name );

			switch ( $attribute_name ) {
				case 'id':
				case 'alt':
				case 'title':
					// The value is in the field of the same name.
					$value = is_array( $field_value ) ? $field_value[ $attribute_name ] ?? '' : '';
					break;
				case 'url':
					if ( is_array( $field_value ) ) {
						// The URL is in the array returned by media-like fields.
						$value = $field_value['url'] ?? '';
					} elseif ( is_scalar( $field_value ) || null === $field_value ) {
						// Scalar URL-like fields use the field value directly.
						$value = $field_value ?? '';
					} else {
						$value = '';
					}
					break;
				case 'rel':
					// Handle checkbox field for rel attribute by joining array values.
					if ( is_array( $field_value ) ) {
						$value = implode( ' ', $field_value );
					} elseif ( is_scalar( $field_value ) || null === $field_value ) {
						$value = $field_value ?? '';
					} else {
						$value = '';
					}
					break;
				default:
					$value = $field_value;

					if ( is_array( $value ) ) {
						$value = wp_json_encode( $value );
					} elseif ( ! is_scalar( $value ) && null !== $value ) {
						$value = '';
					}
			}
		}

		/**
		 * Filters the final value returned by the binding source.
		 *
		 * Runs after the value has been mapped to the bound attribute, so it can
		 * be used to adjust the output of any field type or attribute.
		 *
		 * @since ACF 6.2.8
		 *
		 * @param mixed     $value          The value to return to the block binding.
		 * @param array     $source_attrs   The source attributes requested by the binding.
		 * @param \WP_Block $block_instance The block instance.
		 * @param string    $attribute_name The block's bound attribute name.
		 */
		return apply_filters( 'acf/blocks/binding_value', $value, $source_attrs, $block_instance, $attribute_name );
	}
}
