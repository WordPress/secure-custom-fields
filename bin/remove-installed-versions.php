<?php
/**
 * Remove Composer\InstalledVersions from autoload_static.php
 *
 * This script removes any references to Composer\InstalledVersions
 * from the autoload_static.php file to prevent conflicts.
 *
 * @package secure-custom-fields
 */

$autoload_file = __DIR__ . '/../vendor/composer/autoload_static.php';

if ( ! file_exists( $autoload_file ) ) {
	echo 'Autoload file not found: ' . esc_html( $autoload_file ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 0 );
}

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$content          = file_get_contents( $autoload_file );
$original_content = $content;

// Remove any lines containing InstalledVersions
$patterns = array(
	// Remove full class mapping lines with InstalledVersions.
	'/^\s*\'Composer\\\\InstalledVersions\'\s*=>\s*__DIR__\s*\.\s*[^,\n]*,?\s*\n/m',
	// Remove any other references to InstalledVersions.php.
	'/.*InstalledVersions\.php.*\n/',
);

foreach ( $patterns as $pattern ) {
	$content = preg_replace( $pattern, '', $content );
}

// Clean up any trailing commas that might be left.
$content = preg_replace( '/,(\s*\)\s*;)/m', '$1', $content );

// Only write if content changed.
if ( $content !== $original_content ) {
	// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( $autoload_file, $content );
	echo "Removed InstalledVersions references from autoload_static.php\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
	echo "No InstalledVersions references found in autoload_static.php\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
