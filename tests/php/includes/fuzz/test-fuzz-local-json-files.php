<?php
/**
 * Property-based fuzz tests for ACF_Local_JSON file loading.
 *
 * Seeds a real temporary directory (registered via acf/settings/load_json,
 * mirroring tests/php/includes/test-local-json.php) with hostile files:
 * random bytes, truncated JSON, valid-JSON-of-the-wrong-shape, and
 * occasionally well-formed field groups. Then drives the scanner and
 * include_fields().
 *
 * Invariants:
 *   - scan_files() / include_fields() never throw (or raise a warning, which
 *     PHPUnit converts to a throwable).
 *   - Only well-formed groups (a JSON object with a 'key') are ever
 *     registered as local field groups; garbage files register nothing.
 *
 * Determinism: a fixed seed (see docblock); failures embed seed/iteration and
 * the offending corpus. Crank with SCF_FUZZ_ITERATIONS (here it controls the
 * number of randomized directory corpora generated).
 *
 * Teardown hygiene mirrors test-local-json.php: filters removed, temp dir
 * recursively deleted, local stores reset so other tests are not poisoned.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

require_once __DIR__ . '/class-scf-fuzz-generator.php';

/**
 * Fuzz tests for local JSON file loading.
 */
class Test_Fuzz_Local_JSON_Files extends BaseTestCase {

	/**
	 * Base seed for this test class.
	 *
	 * @var integer
	 */
	const BASE_SEED = 612401;

	/**
	 * The temporary JSON directory for the current corpus.
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * The ACF_Local_JSON instance.
	 *
	 * @var ACF_Local_JSON
	 */
	private $json;

	/**
	 * Save-path filter callback.
	 *
	 * @var callable
	 */
	private $save_filter;

	/**
	 * Load-path filter callback.
	 *
	 * @var callable
	 */
	private $load_filter;

	/**
	 * Set up a clean temp dir and local stores.
	 */
	public function setUp(): void {
		parent::setUp();

		acf_init();

		acf_reset_local();
		acf_get_store( 'local-empty' )->reset();
		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'values' )->reset();

		$this->temp_dir = sys_get_temp_dir() . '/scf-fuzz-local-json-' . uniqid();
		mkdir( $this->temp_dir, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture outside of WP_Filesystem context.

		$dir               = $this->temp_dir;
		$this->save_filter = function () use ( $dir ) {
			return $dir;
		};
		$this->load_filter = function () use ( $dir ) {
			return array( $dir );
		};

		add_filter( 'acf/settings/save_json', $this->save_filter, 100 );
		add_filter( 'acf/settings/load_json', $this->load_filter, 100 );

		$this->json = acf_get_instance( 'ACF_Local_JSON' );
		$this->json->scan_files();
	}

	/**
	 * Clean up: remove filters, delete temp dir, reset stores.
	 */
	public function tearDown(): void {
		remove_filter( 'acf/settings/save_json', $this->save_filter, 100 );
		remove_filter( 'acf/settings/load_json', $this->load_filter, 100 );

		$this->delete_dir( $this->temp_dir );

		$this->json->scan_files();

		acf_reset_local();
		acf_get_store( 'local-empty' )->reset();
		acf_get_store( 'fields' )->reset();
		acf_get_store( 'field-groups' )->reset();
		acf_get_store( 'values' )->reset();

		parent::tearDown();
	}

	/**
	 * Recursively deletes a directory (test fixture cleanup).
	 *
	 * @param string $dir Directory path.
	 */
	private function delete_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_dir( $path ) ) {
				$this->delete_dir( $path );
			} else {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture cleanup.
			}
		}

		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture cleanup.
	}

	/**
	 * Empties the temp dir between corpora.
	 */
	private function clear_temp_dir() {
		foreach ( scandir( $this->temp_dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $this->temp_dir . '/' . $entry;
			if ( is_file( $path ) ) {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture cleanup.
			}
		}
	}

	/**
	 * Writes a file into the temp dir.
	 *
	 * @param string $filename File name.
	 * @param string $contents Raw bytes.
	 */
	private function write_file( $filename, $contents ) {
		file_put_contents( $this->temp_dir . '/' . $filename, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
	}

	/**
	 * Builds a well-formed field group array with a unique key.
	 *
	 * @param SCF_Fuzz_Generator $generator Generator.
	 * @param integer            $n         Uniquifier.
	 * @return array
	 */
	private function valid_group( $generator, $n ) {
		// Titles/labels use printable ASCII only: a "valid" group must JSON
		// encode cleanly. acf_json_encode() returns false on invalid UTF-8
		// (wp_json_encode bails), which would silently write an empty file and
		// make the group un-loadable, so unicode is exercised in the
		// wrong-shape/byte-string corpora instead.
		return array(
			'key'    => 'group_fuzz_valid_' . $n,
			'title'  => 'Fuzz ' . $generator->ascii_string( 8 ),
			'fields' => array(
				array(
					'key'   => 'field_fuzz_valid_' . $n,
					'name'  => 'fuzz_valid_' . $n,
					'label' => 'Label ' . $generator->ascii_string( 8 ),
					'type'  => 'text',
				),
			),
			'local'  => 'json',
		);
	}

	/**
	 * Generates one randomized file's raw contents and a flag indicating
	 * whether it is a well-formed group that the loader should register.
	 *
	 * @param SCF_Fuzz_Generator $generator Generator.
	 * @param integer            $n         Uniquifier.
	 * @return array{0:string,1:bool,2:string} [contents, is_valid_group, key]
	 */
	private function random_file( $generator, $n ) {
		$roll = $generator->int_between( 0, 6 );

		switch ( $roll ) {
			case 0:
				// Well-formed field group.
				$group = $this->valid_group( $generator, $n );
				return array( acf_json_encode( $group ), true, $group['key'] );
			case 1:
				// Random raw bytes.
				return array( $generator->byte_string( 64 ), false, '' );
			case 2:
				// Truncated valid JSON.
				$full = (string) acf_json_encode( $this->valid_group( $generator, $n ) );
				$cut  = substr( $full, 0, max( 0, $generator->int_between( 0, strlen( $full ) - 1 ) ) );
				return array( $cut, false, '' );
			case 3:
				// Valid JSON, wrong shape: a JSON array (list), not an object.
				return array( (string) wp_json_encode( array( 1, 2, 3, $generator->unicode_string( 1 ) ) ), false, '' );
			case 4:
				// Valid JSON object but no 'key' (scanner requires 'key').
				return array(
					(string) wp_json_encode(
						array(
							'title'  => $generator->unicode_string( 1 ),
							'fields' => array(),
						)
					),
					false,
					'',
				);
			case 5:
				// Valid JSON scalar.
				return array( (string) wp_json_encode( $generator->scalar() ), false, '' );
			default:
				// Empty file.
				return array( '', false, '' );
		}
	}

	/**
	 * Property: scan_files() and include_fields() must never throw on a randomized
	 * corpus, and only well-formed groups (object with a 'key') may end up
	 * registered as local field groups.
	 *
	 * Seed: 612401. Default iterations: 60 corpora, each 1-8 files.
	 */
	public function test_loading_random_corpus_never_throws_and_registers_only_valid_groups() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED );
		$iterations = SCF_Fuzz_Generator::iterations( 60 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$this->clear_temp_dir();
			acf_reset_local();
			acf_get_store( 'local-empty' )->reset();
			acf_get_store( 'fields' )->reset();
			acf_get_store( 'field-groups' )->reset();

			$expected_keys = array();
			$file_count    = $generator->int_between( 1, 8 );

			for ( $f = 0; $f < $file_count; $f++ ) {
				list( $contents, $is_valid, $key ) = $this->random_file( $generator, ( $i * 100 ) + $f );

				// Mostly .json names; occasionally a non-json extension that
				// the scanner must ignore.
				$ext      = $generator->chance( 0.85 ) ? 'json' : $generator->pick( array( 'txt', 'php', 'json.bak' ) );
				$filename = sprintf( 'fuzz_%d_%d.%s', $i, $f, $ext );

				$this->write_file( $filename, $contents );

				if ( $is_valid && 'json' === $ext ) {
					$expected_keys[] = $key;
				}
			}

			try {
				$this->json->scan_files();
				$this->json->include_fields();
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $this->describe_corpus(), 'loading threw: ' . $e->getMessage() ) );
			}

			// The local stores are global and shared across the test suite, so
			// scope the assertions to keys produced by THIS test
			// ('group_fuzz_valid_'). Among those, the registered set must
			// exactly equal the set of valid groups we wrote: garbage files
			// must register nothing, and every valid file must register.
			$registered      = acf_get_local_field_groups();
			$registered_mine = array();
			foreach ( $registered as $group ) {
				if ( isset( $group['key'] ) && is_string( $group['key'] ) && 0 === strpos( $group['key'], 'group_fuzz_valid_' ) ) {
					$registered_mine[] = $group['key'];
				}
			}

			sort( $registered_mine );
			$expected_sorted = $expected_keys;
			sort( $expected_sorted );

			$this->assertSame(
				$expected_sorted,
				$registered_mine,
				$generator->failure_message( $i, $this->describe_corpus(), 'registered groups did not match the valid files written' )
			);
		}
	}

	/**
	 * Returns a description of the current temp-dir corpus for failure
	 * reports (filenames + byte lengths; raw bytes are not dumped wholesale).
	 *
	 * @return array
	 */
	private function describe_corpus() {
		$out = array();
		foreach ( scandir( $this->temp_dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path          = $this->temp_dir . '/' . $entry;
			$out[ $entry ] = is_file( $path ) ? filesize( $path ) . ' bytes' : 'dir';
		}
		return $out;
	}
}
