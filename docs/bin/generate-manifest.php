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
$slug_map = array(); // Track slugs and their sources
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

// First pass: collect all slugs
foreach ( $paths as $path_pattern ) {
	foreach ( glob( $path_pattern ) as $file ) {
		// Skip specified README.md files and all META.md files.
		if ( in_array( $file, $excludes, true ) || basename( $file ) === 'META.md' ) {
			continue;
		}

		$slug = basename( $file, '.md' );
		$key  = str_replace( array( $root . '/', '.md' ), '', $file );

		// Special handling for tutorial files
		if ( 'tutorial' === $slug ) {
			$bits = explode( '/', $key );
			array_pop( $bits ); // Remove 'tutorial'
			$slug = end( $bits ) . '-tutorial'; // Use parent directory name plus -tutorial
		} elseif ( 'index' === $slug ) { // Handle index.md files specially.
			$bits = explode( '/', $key );
			array_pop( $bits ); // Remove 'index'
			$slug = end( $bits ); // Use parent directory name as slug
			$key  = implode( '/', $bits ); // Remove /index from key
		}

		// Check for slug conflicts
		if ( isset( $slug_map[ $slug ] ) ) {
			printf( "\nError: Slug conflict detected!\n\n" );
			printf( "Original entry:\n%s\n\n", json_encode( array( 'key' => $slug_map[ $slug ] ), JSON_PRETTY_PRINT ) );
			printf( "Conflicting entry:\n%s\n\n", json_encode( array( 'key' => $key ), JSON_PRETTY_PRINT ) );
			exit( 1 );
		}
		$slug_map[ $slug ] = $key;
	}
}

// Second pass: build manifest with validated parents
foreach ( $paths as $path_pattern ) {
	foreach ( glob( $path_pattern ) as $file ) {
		if ( in_array( $file, $excludes, true ) || basename( $file ) === 'META.md' ) {
			continue;
		}

		$slug = basename( $file, '.md' );
		$key  = str_replace( array( $root . '/', '.md' ), '', $file );

		// Special handling for tutorial files
		if ( 'tutorial' === $slug ) {
			$bits = explode( '/', $key );
			array_pop( $bits ); // Remove 'tutorial'
			$slug = end( $bits ) . '-tutorial';
		} elseif ( 'index' === $slug ) {
			$bits = explode( '/', $key );
			array_pop( $bits );
			$slug = end( $bits );
			$key  = implode( '/', $bits );
		}

		// Get parent slug (not path)
		$parent = null;
		if ( stripos( $key, '/' ) ) {
			$bits = explode( '/', $key );
			array_pop( $bits ); // Remove current item
			$parent_key = implode( '/', $bits );

			// Find the parent's slug
			foreach ( $slug_map as $potential_parent_slug => $mapped_key ) {
				if ( $mapped_key === $parent_key ) {
					$parent = $potential_parent_slug;
					break;
				}
			}

			// Validate parent exists
			if ( ! isset( $slug_map[ $parent ] ) ) {
                // phpcs:ignore
				printf( "\nError: Parent slug '%s' not found for '%s'\n", $parent, $key );
				exit( 1 );
			}
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

/**
 * Sort entries hierarchically - null parents first, then by depth.
 *
 * @param array $manifest The manifest to sort.
 * @return array Sorted manifest.
 */
function sort_manifest_hierarchically( $manifest ) {
	// First, group by depth
	$grouped = array();
	foreach ( $manifest as $key => $entry ) {
		$depth = substr_count( $key, '/' );
		if ( ! isset( $grouped[ $depth ] ) ) {
			$grouped[ $depth ] = array();
		}
		$grouped[ $depth ][ $key ] = $entry;
	}

	// Sort depths
	ksort( $grouped );

	// Merge back maintaining order
	$sorted = array();
	foreach ( $grouped as $depth => $entries ) {
		// Sort entries within each depth
		ksort( $entries );
		foreach ( $entries as $key => $entry ) {
			$sorted[ $key ] = $entry;
		}
	}

	return $sorted;
}

// Before writing the manifest, sort it hierarchically
$manifest = sort_manifest_hierarchically( $manifest );

file_put_contents( $root . '/bin/manifest.json', json_encode( (object) $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

$count = count( $manifest );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
printf( 'Generated manifest.json of %d pages%s', $count, PHP_EOL );
