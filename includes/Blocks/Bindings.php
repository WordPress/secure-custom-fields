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
			$post_id = acf_get_valid_post_id( false );
			if ( scf_post_password_required( $post_id ) ) {
				return '';
			}

			$field = get_field_object( $source_attrs['key'], $post_id, true, true, true );

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

			$raw_value   = acf_get_value( $post_id, $field );
			$field_value = scf_rest_sanitize_user_data( $field['value'], $raw_value, $field );

			add_filter( 'acf/rest/expose_reference_target', array( $this, 'expose_reference_target' ), PHP_INT_MAX, 2 );
			$field_value = acf_rest_sanitize_reference_data( $field_value, $raw_value, $field );
			remove_filter( 'acf/rest/expose_reference_target', array( $this, 'expose_reference_target' ), PHP_INT_MAX );
			$field_value = $this->strip_post_passwords( $field_value );

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

		return apply_filters( 'acf/blocks/binding_value', $value, $source_attrs, $block_instance, $attribute_name );
	}

	/**
	 * Removes the password from expanded post objects before they are output.
	 *
	 * @since SCF 6.9.7
	 *
	 * @param mixed $value The field value, possibly containing WP_Post objects.
	 * @return mixed
	 */
	private function strip_post_passwords( $value ) {
		if ( $value instanceof \WP_Post ) {
			$value                = clone $value;
			$value->post_password = '';
			return $value;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->strip_post_passwords( $item );
			}
		}

		return $value;
	}

	/**
	 * Decides whether a referenced post is expanded in a block binding value.
	 *
	 * Bindings render on the front end, so a referenced post is exposed when the
	 * viewer can read it there rather than when the REST API would return it.
	 * Attachments are always expanded because their files are publicly served.
	 * This runs last so that callbacks written for REST output do not decide
	 * what a rendered block shows.
	 *
	 * @since SCF 6.9.7
	 *
	 * @param boolean $exposable Whether the REST rules would expose the target.
	 * @param integer $target_id The referenced post or attachment ID.
	 * @return boolean
	 */
	public function expose_reference_target( $exposable, $target_id ) {
		$post = get_post( $target_id );

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( 'attachment' === $post->post_type ) {
			return true;
		}

		if ( post_password_required( $post ) && ! current_user_can( 'edit_post', $post->ID ) ) {
			return false;
		}

		return is_post_publicly_viewable( $post ) || current_user_can( 'read_post', $post->ID );
	}
}
