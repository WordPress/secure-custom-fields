<?php
/**
 * SCF block bindings editor integration.
 *
 * @package wordpress/secure-custom-fields
 */

namespace SCF\Blocks;

/**
 * Enqueues the JS layer that powers SCF block bindings in the block editor.
 *
 * The JS bindings layer registers a block binding source via the stable
 * registerBlockBindingsSource API (WP 6.7+), enabling live preview and
 * editing of SCF field values bound to block attributes. It runs alongside
 * the shared server-side SCF\Blocks\Bindings class.
 */
class Bindings_Editor {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// The JS bindings layer relies on the stable registerBlockBindingsSource
		// API, which is only available on WP 6.7+.
		global $wp_version;
		if ( version_compare( $wp_version, '6.7', '<' ) ) {
			return;
		}

		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Enqueues the JS block bindings source script on block editor screens.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		if ( ! acf_get_setting( 'enable_block_bindings' ) ) {
			return;
		}

		// JS bindings layer requires the datastore for live editor preview/editing.
		if ( ! acf_is_using_datastore() ) {
			return;
		}

		wp_enqueue_script( 'acf-field-bindings' );
	}
}
