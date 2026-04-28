<?php // phpcs:ignore Squiz.Commenting.FileComment.Missing

namespace SCF\Pro\AI\GEO\Outputs;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\SCF\AI\GEO\Outputs\Blocks' ) ) {
	if ( function_exists( 'acf_include' ) ) {
		acf_include( 'src/AI/GEO/Outputs/Blocks.php' );
	} else {
		require_once dirname( __DIR__, 4 ) . '/AI/GEO/Outputs/Blocks.php';
	}
}

if ( class_exists( '\SCF\AI\GEO\Outputs\Blocks' ) && ! class_exists( __NAMESPACE__ . '\Blocks', false ) ) {
	/**
	 * Backwards-compatible wrapper for the non-PRO SCF GEO block output.
	 */
	class Blocks extends \SCF\AI\GEO\Outputs\Blocks {}
}
