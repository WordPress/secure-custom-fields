<?php
/**
 * PHP Code Coverage Collector for E2E Tests
 *
 * This mu-plugin collects PHP code coverage during e2e test execution.
 * It only activates when the X-PHP-Coverage header is present (sent by
 * Playwright when PHP_COVERAGE_ENABLED env var is set) or when the
 * PHP_COVERAGE_ENABLED constant is defined in wp-config.php.
 *
 * Coverage data is written to .php-coverage/ directory as JSON files
 * which can be merged using the merge-php-coverage.js script.
 *
 * @package SecureCustomFields\Tests
 *
 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
 * phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
 */

// Only run if coverage header is present or constant is defined.
// The header is the primary trigger - it's sent by Playwright tests.
if ( ! isset( $_SERVER['HTTP_X_PHP_COVERAGE'] ) && ! isset( $_COOKIE['PHP_COVERAGE'] ) ) {
	// Also check for the constant as a fallback.
	if ( ! defined( 'PHP_COVERAGE_ENABLED' ) || ! PHP_COVERAGE_ENABLED ) {
		return;
	}
}

// Check if Xdebug coverage mode is available.
if ( ! extension_loaded( 'xdebug' ) || ! function_exists( 'xdebug_start_code_coverage' ) ) {
	error_log( 'PHP Coverage: Xdebug not available or coverage mode not enabled' );
	return;
}

// Get coverage ID from header or cookie.
$coverage_id = isset( $_SERVER['HTTP_X_PHP_COVERAGE'] )
	? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_PHP_COVERAGE'] ) )
	: ( isset( $_COOKIE['PHP_COVERAGE'] )
		? sanitize_text_field( wp_unslash( $_COOKIE['PHP_COVERAGE'] ) )
		: 'unknown' );

// Sanitize coverage ID for filename.
$coverage_id = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $coverage_id );

// Define coverage output directory - write to the plugin directory which is mapped to host.
$coverage_dir = WP_PLUGIN_DIR . '/secure-custom-fields/.php-coverage';

if ( ! is_dir( $coverage_dir ) ) {
	mkdir( $coverage_dir, 0755, true );
}

// Start code coverage collection.
xdebug_start_code_coverage( XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE );

/**
 * Stop coverage and write data on shutdown.
 */
register_shutdown_function(
	function () use ( $coverage_id, $coverage_dir ) {
		$coverage_data = xdebug_get_code_coverage();
		xdebug_stop_code_coverage();

		if ( empty( $coverage_data ) ) {
			return;
		}

		// Filter to only include files from secure-custom-fields plugin.
		$plugin_path   = WP_PLUGIN_DIR . '/secure-custom-fields';
		$filtered_data = array();

		foreach ( $coverage_data as $file => $lines ) {
			// Only include files from the plugin directory.
			if ( strpos( $file, $plugin_path ) === 0 ) {
				// Exclude test files and vendor.
				if (
					strpos( $file, '/tests/' ) === false &&
					strpos( $file, '/vendor/' ) === false &&
					strpos( $file, '/node_modules/' ) === false
				) {
					$filtered_data[ $file ] = $lines;
				}
			}
		}

		if ( empty( $filtered_data ) ) {
			return;
		}

		// Generate unique filename.
		$filename = sprintf(
			'%s/coverage-%s-%s-%s.json',
			$coverage_dir,
			$coverage_id,
			gmdate( 'Ymd-His' ),
			substr( md5( uniqid( '', true ) ), 0, 8 )
		);

		// Get request URI safely.
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: 'unknown';

		// Write coverage data as JSON (easier to process).
		$json_data = json_encode(
			array(
				'coverage_id' => $coverage_id,
				'timestamp'   => time(),
				'request_uri' => $request_uri,
				'data'        => $filtered_data,
			),
			JSON_PRETTY_PRINT
		);

		file_put_contents( $filename, $json_data );
	}
);
