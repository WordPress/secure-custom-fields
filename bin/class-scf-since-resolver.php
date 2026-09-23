<?php
/**
 * Resolve SCF @since placeholders.
 *
 * @package wordpress/secure-custom-fields
 */

namespace WordPress\SCF\Scripts;

/**
 * Resolves release-time @since placeholders in source files.
 *
 * Contributors write `@since SCF {NEXT_MAJOR_VERSION}` for new APIs so the
 * version does not have to be guessed before the release.
 */
class Since_Resolver {
	/**
	 * Placeholder used for new SCF APIs.
	 *
	 * @var string
	 */
	const PLACEHOLDER = '{NEXT_MAJOR_VERSION}';

	/**
	 * Source directories to scan.
	 *
	 * @var string[]
	 */
	const SOURCE_DIRECTORIES = array( 'includes', 'src', 'assets/src' );

	/**
	 * Resolve placeholders in source files.
	 *
	 * @param string $version Release version.
	 * @param string $root    Repository root.
	 * @return string[] Paths of files that were changed.
	 * @throws \RuntimeException When a source file cannot be written.
	 */
	public function resolve( $version, $root ) {
		$changed = array();

		foreach ( self::SOURCE_DIRECTORIES as $directory ) {
			$path = $root . '/' . $directory;
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path ) );
			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || ! $this->is_supported_file( $file->getFilename() ) ) {
					continue;
				}

				if ( $this->resolve_file( $file->getPathname(), $version ) ) {
					$changed[] = $file->getPathname();
				}
			}
		}

		return $changed;
	}

	/**
	 * Resolve placeholders in one file.
	 *
	 * Only the placeholder sitting in an `@since` annotation is replaced, so
	 * unrelated text is never touched.
	 *
	 * @param string $path    File path.
	 * @param string $version Release version.
	 * @return int Number of replacements.
	 * @throws \RuntimeException When the file cannot be written.
	 */
	private function resolve_file( $path, $version ) {
		$content = file_get_contents( $path );
		if ( false === $content || false === strpos( $content, self::PLACEHOLDER ) ) {
			return 0;
		}

		$updated = preg_replace_callback(
			'/@since\s+(?:SCF\s+)?' . preg_quote( self::PLACEHOLDER, '/' ) . '/',
			function () use ( $version ) {
				return '@since SCF ' . $version;
			},
			$content,
			-1,
			$count
		);

		if ( null === $updated || 0 === $count ) {
			return 0;
		}

		if ( false === file_put_contents( $path, $updated ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Release tooling runs outside WordPress.
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI error output, not rendered HTML.
			throw new \RuntimeException( 'Could not write resolved @since annotations to ' . $path );
		}

		return $count;
	}

	/**
	 * Check whether a file can contain a source docblock.
	 *
	 * @param string $filename File name.
	 * @return bool Whether the file is supported.
	 */
	private function is_supported_file( $filename ) {
		return (bool) preg_match( '/\.(php|js|jsx|ts|tsx)$/', $filename );
	}
}
