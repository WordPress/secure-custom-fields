<?php
/**
 * Property-based fuzz tests for post-ID decoding functions.
 *
 * Functions under test ingest arbitrary, untrusted $post_id values:
 * - acf_decode_post_id()
 * - acf_get_post_id_info()
 * - acf_get_valid_post_id()
 *
 * Properties asserted are invariants, not exact outputs: the functions must
 * never throw (or raise warnings/notices, which PHPUnit converts to
 * exceptions), must always return arrays with the documented keys, and must
 * be stable/idempotent where the contract implies it.
 *
 * Determinism: every test uses SCF_Fuzz_Generator with a fixed seed noted in
 * its docblock. Failures embed the seed, iteration and var_export() of the
 * failing input. Crank iterations locally with SCF_FUZZ_ITERATIONS.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

require_once __DIR__ . '/class-scf-fuzz-generator.php';

/**
 * Fuzz tests for post-ID decoding.
 */
class Test_Fuzz_Post_ID_Decoding extends BaseTestCase {

	/**
	 * Base seed for this test class. Per-test seeds are derived from it.
	 *
	 * @var integer
	 */
	const BASE_SEED = 612001;

	/**
	 * Property: acf_decode_post_id() must always return an array with "type" (string)
	 * and "id" (int|string) keys and never throw, whatever it is fed.
	 *
	 * Seed: 612001. Default iterations: 300.
	 */
	public function test_decode_post_id_always_returns_type_and_id() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED );
		$iterations = SCF_Fuzz_Generator::iterations( 300 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$input = $generator->post_id_input();

			try {
				$result = acf_decode_post_id( $input );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $input, 'acf_decode_post_id threw: ' . $e->getMessage() ) );
			}

			$this->assertIsArray( $result, $generator->failure_message( $i, $input ) );
			$this->assertArrayHasKey( 'type', $result, $generator->failure_message( $i, $input ) );
			$this->assertArrayHasKey( 'id', $result, $generator->failure_message( $i, $input ) );
			$this->assertIsString( $result['type'], $generator->failure_message( $i, $input ) );
			$this->assertTrue(
				is_int( $result['id'] ) || is_string( $result['id'] ) || is_float( $result['id'] ),
				$generator->failure_message( $i, $input, 'id is ' . gettype( $result['id'] ) )
			);
		}
	}

	/**
	 * Property: acf_decode_post_id() must be a pure function of its input: calling it
	 * twice with the same value yields an identical result.
	 *
	 * Seed: 612002. Default iterations: 150.
	 */
	public function test_decode_post_id_is_stable() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 1 );
		$iterations = SCF_Fuzz_Generator::iterations( 150 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$input = $generator->post_id_input();

			$first  = acf_decode_post_id( $input );
			$second = acf_decode_post_id( $input );

			$this->assertSame( $first, $second, $generator->failure_message( $i, $input ) );
		}
	}

	/**
	 * Property: acf_get_post_id_info() must always return an array with "type" and
	 * "id" keys, with a non-empty string type, and never throw.
	 *
	 * Seed: 612003. Default iterations: 300.
	 */
	public function test_get_post_id_info_always_returns_type_and_id() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 2 );
		$iterations = SCF_Fuzz_Generator::iterations( 300 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$input = $generator->post_id_input();

			try {
				$info = acf_get_post_id_info( $input );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $input, 'acf_get_post_id_info threw: ' . $e->getMessage() ) );
			}

			$this->assertIsArray( $info, $generator->failure_message( $i, $input ) );
			$this->assertArrayHasKey( 'type', $info, $generator->failure_message( $i, $input ) );
			$this->assertArrayHasKey( 'id', $info, $generator->failure_message( $i, $input ) );
			$this->assertIsString( $info['type'], $generator->failure_message( $i, $input ) );
			$this->assertNotSame( '', $info['type'], $generator->failure_message( $i, $input ) );
		}
	}

	/**
	 * Decoding functions must agree on stability across repeated calls with
	 * inputs already shaped like decoded output ("type_id" strings).
	 *
	 * Seed: 612004. Default iterations: 150.
	 */
	public function test_get_post_id_info_is_stable() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 3 );
		$iterations = SCF_Fuzz_Generator::iterations( 150 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$input = $generator->post_id_input();

			$first  = acf_get_post_id_info( $input );
			$second = acf_get_post_id_info( $input );

			$this->assertSame( $first, $second, $generator->failure_message( $i, $input ) );
		}
	}

	/**
	 * Property: acf_get_valid_post_id() must never throw, and must be idempotent:
	 * feeding its own output back in returns the same value.
	 *
	 * Falsy inputs (0, '', null, false, empty arrays) are exercised for the
	 * "never throws" half only, because they trigger the queried-object
	 * lookup whose result depends on global state, not on the input.
	 *
	 * Seed: 612005. Default iterations: 300.
	 */
	public function test_get_valid_post_id_never_throws_and_is_idempotent() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 4 );
		$iterations = SCF_Fuzz_Generator::iterations( 300 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$input = $generator->post_id_input();

			try {
				$first = acf_get_valid_post_id( $input );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $input, 'acf_get_valid_post_id threw: ' . $e->getMessage() ) );
			}

			if ( ! $input || is_object( $input ) ) {
				// Falsy inputs resolve from global state; objects are
				// translated to scalars, so idempotence is checked on the
				// output below only when that output is itself truthy.
				if ( ! $first ) {
					continue;
				}
			}

			try {
				$second = acf_get_valid_post_id( $first );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $input, 'second acf_get_valid_post_id call threw on ' . var_export( $first, true ) . ': ' . $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- reproducible fuzz failure report.
			}

			$this->assertSame(
				$first,
				$second,
				$generator->failure_message( $i, $input, 'acf_get_valid_post_id is not idempotent: first=' . var_export( $first, true ) . ' second=' . var_export( $second, true ) ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- reproducible fuzz failure report.
			);
		}
	}
}
