<?php
/**
 * Server-side walker that replaces SCF Bit spans in rendered block output.
 *
 * @package wordpress/secure-custom-fields
 */

namespace SCF\Bits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Hooks into the `render_block` filter, finds persisted Bit spans, and
 * replaces their inner HTML with values resolved through the registry.
 *
 * Replacement uses `WP_HTML_Tag_Processor` when `set_inner_html` is
 * available (preferred); falls back to a regex on legacy stubs.
 */
class Walker {

	/**
	 * Class name used to mark persisted bit spans.
	 *
	 * @var string
	 */
	const SPAN_CLASS = 'scf-field-bit';

	/**
	 * Hooks the walker filter on construct.
	 */
	public function __construct() {
		add_filter( 'render_block', array( __NAMESPACE__ . '\\Walker', 'maybe_render_block' ), 100, 2 );
	}

	/**
	 * Replaces `<span class="scf-field-bit">` occurrences inside a block with
	 * values resolved from the registry.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block array.
	 * @return string Modified block HTML.
	 */
	public static function maybe_render_block( $block_content, $block ) {
		if ( ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}

		if ( false === strpos( $block_content, self::SPAN_CLASS ) ) {
			return $block_content;
		}

		if ( false !== strpos( $block_content, '<script' ) || false !== strpos( $block_content, '<style' ) ) {
			return $block_content;
		}

		if ( class_exists( 'WP_HTML_Tag_Processor' ) && method_exists( 'WP_HTML_Tag_Processor', 'set_inner_html' ) && method_exists( 'WP_HTML_Tag_Processor', 'next_tag' ) ) {
			return self::render_with_tag_processor( $block_content, $block );
		}

		return self::render_with_regex( $block_content, $block );
	}

	/**
	 * Tag Processor path.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block array.
	 * @return string
	 */
	private static function render_with_tag_processor( $block_content, $block ) {
		$tags = new \WP_HTML_Tag_Processor( $block_content );

		while ( $tags->next_tag( 'span' ) ) {
			$class_attr = (string) $tags->get_attribute( 'class' );
			if ( false === strpos( $class_attr, self::SPAN_CLASS ) ) {
				continue;
			}

			$bit_name = (string) $tags->get_attribute( 'data-bit' );
			if ( '' === $bit_name ) {
				continue;
			}

			$attrs = array(
				'key'      => (string) $tags->get_attribute( 'data-key' ),
				'target'   => (string) $tags->get_attribute( 'data-target' ),
				'fallback' => (string) $tags->get_attribute( 'data-fallback' ),
				'format'   => (string) $tags->get_attribute( 'data-format' ),
			);

			$replacement = self::render_bit( $bit_name, $attrs, $block );
			$tags->set_inner_html( $replacement );
		}

		return (string) $tags->get_updated_html();
	}

	/**
	 * Regex fallback path. Used under limited test stubs that ship an older
	 * Tag Processor lacking `set_inner_html`. Behaves identically on real WP.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block array.
	 * @return string
	 */
	private static function render_with_regex( $block_content, $block ) {
		$pattern = '#<span\b([^>]*?)>(.*?)</span>#is';

		return preg_replace_callback(
			$pattern,
			static function ( $matches ) use ( $block ) {
				$attrs_str = $matches[1];
				if ( false === strpos( $attrs_str, self::SPAN_CLASS ) ) {
					return $matches[0];
				}

				$attrs = array(
					'key'      => self::extract_attr( $attrs_str, 'data-key' ),
					'target'   => self::extract_attr( $attrs_str, 'data-target' ),
					'fallback' => self::extract_attr( $attrs_str, 'data-fallback' ),
					'format'   => self::extract_attr( $attrs_str, 'data-format' ),
					'bit'      => self::extract_attr( $attrs_str, 'data-bit' ),
				);

				$bit = $attrs['bit'];
				if ( '' === $bit ) {
					return $matches[0];
				}

				$replacement = self::render_bit( $bit, $attrs, $block );

				return '<span ' . trim( $attrs_str ) . '>' . $replacement . '</span>';
			},
			$block_content
		);
	}

	/**
	 * Reads a single HTML attribute from a span attribute string.
	 *
	 * @param string $haystack Attribute substring of the opening tag.
	 * @param string $name      Attribute name.
	 * @return string Empty string when not present.
	 */
	private static function extract_attr( $haystack, $name ) {
		if ( preg_match( '#' . preg_quote( $name, '#' ) . '\s*=\s*"([^"]*)"#i', $haystack, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '#' . preg_quote( $name, '#' ) . "\\s*=\\s*'([^']*)'#i", $haystack, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Default render callback used by the built-in `scf/field` bit.
	 *
	 * @param string $bit_name Registered bit name.
	 * @param array  $attrs    Resolved data-* attributes.
	 * @param array  $block    Parsed block array.
	 * @return string Replacement HTML.
	 */
	public static function render_bit( $bit_name, $attrs, $block ) {
		if ( 'scf/field' !== $bit_name ) {
			return isset( $attrs['fallback'] ) ? self::escape_for_inner_html( $attrs['fallback'] ) : '';
		}

		$key      = isset( $attrs['key'] ) ? (string) $attrs['key'] : '';
		$target   = isset( $attrs['target'] ) ? (string) $attrs['target'] : 'current';
		$fallback = isset( $attrs['fallback'] ) ? (string) $attrs['fallback'] : '';
		$format   = isset( $attrs['format'] ) ? (string) $attrs['format'] : 'text';

		if ( '' === $key ) {
			return self::escape_for_inner_html( $fallback );
		}

		$resolved_id = self::resolve_target_id( $target, $block );
		if ( null === $resolved_id ) {
			return self::escape_for_inner_html( $fallback );
		}

		if ( ! function_exists( 'get_field' ) ) {
			return self::escape_for_inner_html( $fallback );
		}

		$value = get_field( $key, $resolved_id );
		if ( '' === $value || null === $value || ( is_array( $value ) && empty( $value ) ) ) {
			return self::escape_for_inner_html( $fallback );
		}

		if ( 'raw' === $format ) {
			if ( ! current_user_can( 'unfiltered_html' ) ) {
				return self::escape_for_inner_html( $fallback );
			}
			return (string) $value;
		}

		if ( is_array( $value ) ) {
			$value  = '[' . esc_html__( 'SCF field output', 'secure-custom-fields' ) . ']';
			$format = 'text';
		}

		if ( ! is_scalar( $value ) ) {
			return self::escape_for_inner_html( $fallback );
		}

		if ( 'html' === $format ) {
			return wp_kses_post( (string) $value );
		}

		return esc_html( (string) $value );
	}

	/**
	 * Resolves a target ID from the `target` attribute.
	 *
	 * @param string $target Raw target string.
	 * @param array  $block  Parsed block.
	 * @return int|null Post ID, or null when not resolvable.
	 */
	private static function resolve_target_id( $target, $block ) {
		$target = trim( (string) $target );

		if ( '' === $target || 'current' === $target ) {
			if ( is_singular() ) {
				return (int) get_the_ID();
			}
			if ( isset( $block['attrs']['postId'] ) ) {
				return (int) $block['attrs']['postId'];
			}
			return null;
		}

		if ( 0 === strpos( $target, 'post:' ) ) {
			$id = (int) substr( $target, 5 );
			return $id > 0 ? $id : null;
		}

		return null;
	}

	/**
	 * Escapes a fallback string for safe placement inside a span.
	 *
	 * @param string $text Raw fallback text.
	 * @return string
	 */
	private static function escape_for_inner_html( $text ) {
		if ( ! is_string( $text ) ) {
			return '';
		}
		return esc_html( $text );
	}
}
