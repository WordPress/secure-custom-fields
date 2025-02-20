#!/usr/bin/env php
<?php
/**
 * Generate a manifest of all documentation files.
 *
 * @package wordpress/secure-custom-fields
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- Using native PHP functions as this is a CLI script.

$root = dirname( __DIR__ ); // docs directory
$repo = 'wordpress/secure-custom-fields';

$manifest = array();
$paths    = array(
	$root . '/*.md',
	$root . '/*/*.md',
	$root . '/*/*/*.md',
	$root . '/*/*/*/*.md', // For deeper nesting like features/fields/accordion/
);

// Files to exclude from manifest
$excludes = array(
	$root . '/README.md',
	$root . '/bin/README.md',
);

foreach ( $paths as $path_pattern ) {
	foreach ( glob( $path_pattern ) as $file ) {
		// Skip specified README.md files and all META.md files.
		if ( in_array( $file, $excludes, true ) || basename( $file ) === 'META.md' ) {
			continue;
		}

		$slug = basename( $file, '.md' );
		// Get relative path from docs directory
		$key = str_replace( array( $root . '/', '.md' ), '', $file );

		// Handle index.md files specially
		if ( 'index' === $slug ) {
			$bits = explode( '/', $key );
			array_pop( $bits ); // Remove 'index'
			$slug = end( $bits ); // Use parent directory name as slug
			$key  = implode( '/', $bits ); // Remove /index from key
		}

		$parent = null;
		if ( stripos( $key, '/' ) ) {
			$bits = explode( '/', $key );
			array_pop( $bits );
			$parent = implode( '/', $bits );
		}

		$manifest[ $key ] = array(
			'slug'            => $slug,
			'parent'          => $parent,
			'markdown_source' => sprintf(
				'https://github.com/%s/blob/trunk/docs/%s%s',
				$repo,
				$key,
				basename( $file ) === 'index.md' ? '/index.md' : '.md'
			),
		);
	}
}

file_put_contents( $root . '/bin/manifest.json', json_encode( (object) $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

$count = count( $manifest );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
printf( 'Generated manifest.json of %d pages%s', $count, PHP_EOL );
