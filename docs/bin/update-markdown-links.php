#!/usr/bin/env php
<?php
/**
 * Update markdown links to remove .md extensions and /index paths.
 *
 * @package wordpress/secure-custom-fields
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- Using native PHP functions as this is a CLI script.

$root  = dirname( __DIR__ );
$paths = array(
	$root . '/*.md',
	$root . '/*/*.md',
	$root . '/*/*/*.md',
	$root . '/*/*/*/*.md',
);

$updated_files = 0;
$updated_links = 0;

foreach ( $paths as $path_pattern ) {
	foreach ( glob( $path_pattern ) as $file ) {
		if ( basename( $file ) === 'README.md' || basename( $file ) === 'META.md' ) {
			continue;
		}

		$content  = file_get_contents( $file );
		$original = $content;

		// Replace links ending in .md
		$content = preg_replace( '/\]\(([^)]+)\.md\)/', ']($1)', $content );

		// Replace links ending in /index
		$content = preg_replace( '/\]\(([^)]+)\/index\)/', ']($1)', $content );

		if ( $content !== $original ) {
			file_put_contents( $file, $content );
			++$updated_files;
			$updated_links += substr_count( $original, '.md)' ) + substr_count( $original, '/index)' );
		}
	}
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
printf( 'Updated %d links in %d files%s', $updated_links, $updated_files, PHP_EOL );
