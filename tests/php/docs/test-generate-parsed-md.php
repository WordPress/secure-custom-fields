<?php
/**
 * Tests for docs parsed markdown generation.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for the docs parsed markdown generator.
 */
class Test_Generate_Parsed_MD extends BaseTestCase {
	/**
	 * Temporary output directory created during a test.
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * Clean up temporary fixtures.
	 */
	public function tear_down() {
		if ( $this->temp_dir && is_dir( $this->temp_dir ) ) {
			$this->remove_directory( $this->temp_dir );
		}

		parent::tear_down();
	}

	/**
	 * Remote stream-wrapper output paths must be rejected before generation.
	 */
	public function test_rejects_remote_output_url() {
		$result = $this->run_generator( 'https://example.com/scf-docs-test' );

		$this->assertSame( 1, $result['status'] );
		$this->assertStringContainsString( 'Output directory must be a local filesystem path.', $result['output'] );
	}

	/**
	 * Local filesystem output directories remain supported.
	 */
	public function test_accepts_local_output_directory() {
		$this->temp_dir = sys_get_temp_dir() . '/scf-docs-output-' . uniqid();

		$result = $this->run_generator( $this->temp_dir );

		$this->assertSame( 0, $result['status'], $result['output'] );
		$this->assertDirectoryExists( $this->temp_dir );
		$this->assertFileExists( $this->temp_dir . '/index.md' );
	}

	/**
	 * Run the docs generator as a CLI script.
	 *
	 * @param string $output_dir Output directory argument.
	 * @return array{status:int, output:string} Process result.
	 */
	private function run_generator( $output_dir ) {
		$script      = dirname( __DIR__, 3 ) . '/docs/bin/generate-parsed-md.php';
		$descriptors = array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Test intentionally verifies CLI script behavior.
			array( PHP_BINARY, $script, '--output=' . $output_dir ),
			$descriptors,
			$pipes,
			dirname( __DIR__, 3 )
		);

		$this->assertIsResource( $process );

		$output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );

		fclose( $pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing test process pipe.
		fclose( $pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing test process pipe.

		return array(
			'status' => proc_close( $process ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_close -- Test intentionally verifies CLI script behavior.
			'output' => $output,
		);
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
