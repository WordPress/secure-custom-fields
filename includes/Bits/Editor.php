<?php
/**
 * Editor-side support for SCF Bits.
 *
 * Enqueues the JS picker, localizes the SCF field list, and wires the
 * RichText toolbar button via the JS `bits/sources.js` handler.
 *
 * @package wordpress/secure-custom-fields
 */

namespace SCF\Bits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Bridges PHP-side bit registration to the block editor.
 */
class Editor {

	/**
	 * Hook name for the inline script that supplies the picker state.
	 *
	 * @var string
	 */
	const HANDLE = 'scf-field-bits';

	/**
	 * Hooks the block-editor asset enqueue on construct.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Enqueues the editor-side picker script on block editor screens.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		// PHP 7.4 + WP 6.2 ships WP_HTML_Tag_Processor; UI requires WP 6.5+.
		global $wp_version;
		if ( version_compare( $wp_version, '6.5', '<' ) ) {
			return;
		}

		wp_enqueue_script( self::HANDLE );

		$registry = Registry::instance();
		$payload  = array(
			'bits'   => array(),
			'fields' => self::collect_field_labels(),
		);

		foreach ( $registry->get_all() as $name => $args ) {
			$payload['bits'][] = array(
				'name'              => $name,
				'label'             => isset( $args['label'] ) ? (string) $args['label'] : $name,
				'category'          => isset( $args['category'] ) ? (string) $args['category'] : '',
				'allowedBlockTypes' => isset( $args['allowed_block_types'] ) && is_array( $args['allowed_block_types'] )
					? array_values( $args['allowed_block_types'] )
					: array(),
				'attributes'        => isset( $args['attributes'] ) && is_array( $args['attributes'] )
					? array_keys( $args['attributes'] )
					: array(),
			);
		}

		wp_add_inline_script(
			self::HANDLE,
			'window.scfFieldBits = ' . wp_json_encode( $payload ) . ';',
			'before'
		);
	}

	/**
	 * Builds a small label map of registered SCF field keys for the picker.
	 *
	 * @return array<string, string>
	 */
	private static function collect_field_labels() {
		$out = array();
		if ( ! function_exists( 'acf_get_field' ) ) {
			return $out;
		}

		$groups = array();
		if ( function_exists( 'acf_get_field_groups' ) ) {
			$groups = (array) acf_get_field_groups();
		}

		foreach ( $groups as $group ) {
			if ( ! isset( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}
			foreach ( $group['fields'] as $field ) {
				if ( ! is_array( $field ) || empty( $field['name'] ) ) {
					continue;
				}
				$out[ (string) $field['name'] ] = isset( $field['label'] )
					? (string) $field['label']
					: (string) $field['name'];
			}
		}

		return $out;
	}
}
