<?php
/**
 * Tests for SCF @since placeholder resolution.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;
use WordPress\SCF\Scripts\Since_Resolver;

require_once dirname( __DIR__, 3 ) . '/bin/class-scf-since-resolver.php';

/**
 * Tests for the SCF @since placeholder resolver.
 */
class Test_SCF_Since_Resolver extends BaseTestCase {
	/**
	 * Temporary source directory.
	 *
	 * @var string
	 */
	private $source_dir;

	/**
	 * Remove temporary files after each test.
	 */
	public function tear_down() {
		if ( $this->source_dir && is_dir( $this->source_dir ) ) {
			$this->remove_directory( $this->source_dir );
		}

		parent::tear_down();
	}

	/**
	 * Resolves SCF annotations and leaves legacy and resolved tags untouched.
	 */
	public function test_resolves_placeholders_without_changing_existing_tags() {
		$this->source_dir = sys_get_temp_dir() . '/scf-since-' . uniqid();
		mkdir( $this->source_dir . '/includes', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture setup.
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
			$this->source_dir . '/includes/example.php',
			"<?php\n/**\n * @since SCF {NEXT_MAJOR_VERSION}\n * @since ACF 6.1\n * @since SCF 6.9.5\n */\n"
		);

		$changed = ( new Since_Resolver() )->resolve( '7.0.0', $this->source_dir );
		$content = file_get_contents( $this->source_dir . '/includes/example.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture assertion.

		$this->assertCount( 1, $changed );
		$this->assertStringContainsString( '@since SCF 7.0.0', $content );
		$this->assertStringContainsString( '@since ACF 6.1', $content );
		$this->assertStringContainsString( '@since SCF 6.9.5', $content );
	}

	/**
	 * Handles the documented placeholder form without the SCF prefix.
	 */
	public function test_resolves_placeholder_without_scf_prefix() {
		$this->source_dir = sys_get_temp_dir() . '/scf-since-' . uniqid();
		mkdir( $this->source_dir . '/src', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture setup.
		file_put_contents( $this->source_dir . '/src/example.js', '/** @since {NEXT_MAJOR_VERSION} */' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.

		$changed = ( new Since_Resolver() )->resolve( '7.0.0', $this->source_dir );
		$content = file_get_contents( $this->source_dir . '/src/example.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture assertion.

		$this->assertCount( 1, $changed );
		$this->assertStringContainsString( '@since SCF 7.0.0', $content );
	}

	/**
	 * Leaves placeholder text that is not an annotation alone.
	 */
	public function test_ignores_placeholder_outside_annotations() {
		$this->source_dir = sys_get_temp_dir() . '/scf-since-' . uniqid();
		mkdir( $this->source_dir . '/includes', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture setup.
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
			$this->source_dir . '/includes/example.php',
			"<?php\n\$label = 'SCF {NEXT_MAJOR_VERSION}';\n"
		);

		$changed = ( new Since_Resolver() )->resolve( '7.0.0', $this->source_dir );
		$content = file_get_contents( $this->source_dir . '/includes/example.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture assertion.

		$this->assertSame( array(), $changed );
		$this->assertStringContainsString( "'SCF {NEXT_MAJOR_VERSION}'", $content );
	}

	/**
	 * Ignores unsupported file types.
	 */
	public function test_ignores_unsupported_files() {
		$this->source_dir = sys_get_temp_dir() . '/scf-since-' . uniqid();
		mkdir( $this->source_dir . '/includes', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture setup.
		file_put_contents( $this->source_dir . '/includes/example.txt', '@since SCF {NEXT_MAJOR_VERSION}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.

		$this->assertSame( array(), ( new Since_Resolver() )->resolve( '7.0.0', $this->source_dir ) );
	}

	/**
	 * Returns no changed files when nothing needs resolving.
	 */
	public function test_reports_no_changes_without_placeholders() {
		$this->source_dir = sys_get_temp_dir() . '/scf-since-' . uniqid();
		mkdir( $this->source_dir . '/src', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture setup.
		file_put_contents( $this->source_dir . '/src/example.js', '/** @since SCF 6.9.5 */' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.

		$this->assertSame( array(), ( new Since_Resolver() )->resolve( '7.0.0', $this->source_dir ) );
	}

	/**
	 * Recursively remove a temporary directory.
	 *
	 * @param string $dir Directory path.
	 */
	private function remove_directory( $dir ) {
		$files = array_diff( scandir( $dir ), array( '.', '..' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_scandir -- Test fixture cleanup.

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;

			if ( is_dir( $path ) ) {
				$this->remove_directory( $path );
			} else {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
			}
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture cleanup.
	}
}
