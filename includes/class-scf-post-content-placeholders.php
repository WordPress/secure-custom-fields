<?php
/**
 * SCF post content placeholders.
 *
 * @package wordpress/secure-custom-fields
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles frontend post-content placeholder replacement for supported blocks.
 */
class SCF_Post_Content_Placeholders {
	/**
	 * Placeholder-matching regular expression.
	 *
	 * @var string
	 */
	const PLACEHOLDER_REGEX = '~\[\[([A-Za-z0-9_-]+)\]\]~';

	/**
	 * Request-local resolved placeholder values keyed by post ID and field name.
	 *
	 * @var array<int, array<string, string>>
	 */
	protected $value_cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'render_block', array( $this, 'filter_render_block' ), 10, 2 );
	}

	/**
	 * Replaces supported placeholders in supported block output.
	 *
	 * @param string $block_content The block content about to be rendered.
	 * @param array  $block         The parsed block data.
	 * @return string
	 */
	public function filter_render_block( $block_content, $block ) {
		$post_id = $this->get_supported_post_id( $block_content, $block );

		if ( ! $post_id ) {
			return $block_content;
		}

		$placeholder_names = $this->extract_placeholder_names( $block_content );

		if ( empty( $placeholder_names ) ) {
			return $block_content;
		}

		$replacements = array();

		foreach ( $placeholder_names as $placeholder_name ) {
			$replacements[ $placeholder_name ] = $this->get_placeholder_value( $placeholder_name, $post_id );
		}

		$rendered_content = preg_replace_callback(
			self::PLACEHOLDER_REGEX,
			function ( $matches ) use ( $replacements ) {
				$placeholder_name = $matches[1];

				if ( ! array_key_exists( $placeholder_name, $replacements ) ) {
					return $matches[0];
				}

				return $replacements[ $placeholder_name ];
			},
			$block_content
		);

		return is_string( $rendered_content ) ? $rendered_content : $block_content;
	}

	/**
	 * Returns the current post ID when the render context is supported.
	 *
	 * @param string $block_content The rendered block HTML.
	 * @param array  $block         The parsed block data.
	 * @return int
	 */
	protected function get_supported_post_id( $block_content, $block ) {
		if ( is_admin() ) {
			return 0;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return 0;
		}

		if ( is_feed() || ! is_singular() ) {
			return 0;
		}

		if ( ! doing_filter( 'the_content' ) || ! in_the_loop() || ! is_main_query() ) {
			return 0;
		}

		if ( ! is_array( $block ) || empty( $block['blockName'] ) || ! in_array( $block['blockName'], $this->get_supported_block_names(), true ) ) {
			return 0;
		}

		if ( false === strpos( $block_content, '[[' ) ) {
			return 0;
		}

		$post_id = get_the_ID();

		return $post_id ? (int) $post_id : 0;
	}

	/**
	 * Returns the supported block names.
	 *
	 * @return string[]
	 */
	protected function get_supported_block_names() {
		return array(
			'core/paragraph',
			'core/heading',
		);
	}

	/**
	 * Extracts unique placeholder names from a block-content string.
	 *
	 * @param string $block_content The rendered block HTML.
	 * @return string[]
	 */
	protected function extract_placeholder_names( $block_content ) {
		$matches = array();

		if ( ! preg_match_all( self::PLACEHOLDER_REGEX, $block_content, $matches ) ) {
			return array();
		}

		return array_values( array_unique( $matches[1] ) );
	}

	/**
	 * Returns the resolved placeholder value for a field on a post.
	 *
	 * @param string $placeholder_name The placeholder field name.
	 * @param int    $post_id          The current post ID.
	 * @return string
	 */
	protected function get_placeholder_value( $placeholder_name, $post_id ) {
		if ( isset( $this->value_cache[ $post_id ] ) && array_key_exists( $placeholder_name, $this->value_cache[ $post_id ] ) ) {
			return $this->value_cache[ $post_id ][ $placeholder_name ];
		}

		$value = '';

		$access_already_prevented = apply_filters( 'acf/prevent_access_to_unknown_fields', false );
		$filter_applied           = false;

		if ( ! $access_already_prevented ) {
			$filter_applied = true;
			add_filter( 'acf/prevent_access_to_unknown_fields', '__return_true' );
		}

		try {
			$field = get_field_object( $placeholder_name, $post_id, true, true, false );
		} finally {
			if ( $filter_applied ) {
				remove_filter( 'acf/prevent_access_to_unknown_fields', '__return_true' );
			}
		}

		if ( $this->is_supported_field( $field ) ) {
			$value = $this->prepare_field_value( $field );
		}

		if ( ! isset( $this->value_cache[ $post_id ] ) ) {
			$this->value_cache[ $post_id ] = array();
		}

		$this->value_cache[ $post_id ][ $placeholder_name ] = $value;

		return $value;
	}

	/**
	 * Returns true when the field can be rendered via placeholders.
	 *
	 * @param mixed $field The field object returned by SCF.
	 * @return bool
	 */
	protected function is_supported_field( $field ) {
		if ( ! is_array( $field ) || empty( $field['type'] ) ) {
			return false;
		}

		if ( ! in_array( $field['type'], $this->get_supported_field_types(), true ) ) {
			return false;
		}

		if ( ! array_key_exists( 'allow_in_bindings', $field ) || ! $field['allow_in_bindings'] ) {
			return false;
		}

		/**
		 * Filters whether a field is eligible for post-content placeholder output.
		 *
		 * @param bool  $is_allowed Whether the field is allowed.
		 * @param array $field      The field object.
		 */
		return (bool) apply_filters( 'scf/post_content_placeholders/is_field_allowed', true, $field );
	}

	/**
	 * Returns the supported field types.
	 *
	 * @return string[]
	 */
	protected function get_supported_field_types() {
		$supported_field_types = array(
			'text',
			'textarea',
			'number',
			'range',
			'email',
			'url',
			'select',
			'radio',
			'button_group',
			'date_picker',
			'date_time_picker',
			'time_picker',
			'wysiwyg',
		);

		/**
		 * Filters the placeholder-supported field types.
		 *
		 * @param string[] $supported_field_types The supported field types.
		 */
		return apply_filters( 'scf/post_content_placeholders/supported_field_types', $supported_field_types );
	}

	/**
	 * Prepares a field value for inline placeholder output.
	 *
	 * @param array $field The field object.
	 * @return string
	 */
	protected function prepare_field_value( $field ) {
		if ( ! array_key_exists( 'value', $field ) || ! is_scalar( $field['value'] ) ) {
			return '';
		}

		$prepared_value = (string) $field['value'];

		if ( '' === trim( $prepared_value ) ) {
			return '';
		}

		/**
		 * Filters the field value before placeholder sanitization.
		 *
		 * @param string $prepared_value The scalar field value cast to a string.
		 * @param array  $field          The field object.
		 */
		$prepared_value = apply_filters( 'scf/post_content_placeholders/prepared_value', $prepared_value, $field );

		if ( ! is_scalar( $prepared_value ) ) {
			return '';
		}

		$prepared_value = (string) $prepared_value;

		if ( '' === trim( $prepared_value ) ) {
			return '';
		}

		$sanitized_value = wp_kses( $prepared_value, $this->get_inline_allowed_html() );

		if ( '' === trim( $sanitized_value ) ) {
			return '';
		}

		return $sanitized_value;
	}

	/**
	 * Returns the inline-only HTML allowed for placeholders.
	 *
	 * @return array<string, array<string, bool>>
	 */
	protected function get_inline_allowed_html() {
		$allowed_html = array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
				'title'  => true,
			),
			'abbr'   => array(
				'title' => true,
			),
			'b'      => array(),
			'br'     => array(),
			'cite'   => array(),
			'code'   => array(),
			'del'    => array(),
			'em'     => array(),
			'i'      => array(),
			'mark'   => array(),
			'small'  => array(),
			'strong' => array(),
			'sub'    => array(),
			'sup'    => array(),
		);

		/**
		 * Filters the inline HTML allowed for placeholder output.
		 *
		 * @param array<string, array<string, bool>> $allowed_html The allowed HTML map.
		 */
		return apply_filters( 'scf/post_content_placeholders/inline_allowed_html', $allowed_html );
	}
}
