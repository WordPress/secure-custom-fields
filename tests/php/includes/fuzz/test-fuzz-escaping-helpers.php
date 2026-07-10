<?php
/**
 * Property-based fuzz tests for SCF escaping / sanitization helpers.
 *
 * Functions under test (all defined by SCF):
 *   - acf_esc_html()            includes/acf-input-functions.php
 *   - acf_esc_attrs()           includes/acf-input-functions.php
 *   - acf_maybe_unserialize()   includes/acf-helper-functions.php  (SECURITY)
 *   - acf_sanitize_request_args() includes/acf-helper-functions.php
 *   - acf_parse_args()          includes/api/api-helpers.php
 *   - acf_slugify() / acf_idify() / acf_punctify() / acf_strlen()
 *
 * Invariants asserted (per documented contract):
 *   - Escapers never throw and never emit a raw, executable <script>/<iframe>
 *     vector for their documented context.
 *   - acf_esc_attrs() output contains no raw '<' or '>' (esc_attr encodes
 *     them), so attribute values cannot break out of the tag.
 *   - acf_maybe_unserialize() NEVER yields a real object instance: with
 *     allowed_classes => false, any object in the result is a
 *     __PHP_Incomplete_Class placeholder, never the named application class.
 *     THIS IS A SECURITY PROPERTY — a failure here is reported prominently.
 *   - acf_parse_args() always returns an array; acf_sanitize_request_args()
 *     returns a sanitized value of an expected type.
 *   - String helpers return their documented type and never throw.
 *
 * Determinism: fixed seeds per test (see docblocks). Failures embed seed,
 * iteration and the failing input. Crank with SCF_FUZZ_ITERATIONS.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

require_once __DIR__ . '/class-scf-fuzz-generator.php';

/**
 * Fuzz tests for escaping / sanitization helpers.
 */
class Test_Fuzz_Escaping_Helpers extends BaseTestCase {

	/**
	 * Base seed for this test class.
	 *
	 * @var integer
	 */
	const BASE_SEED = 612601;

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();
		acf_init();
	}

	/**
	 * Recursively asserts that no value in $data is a real object instance.
	 * Only __PHP_Incomplete_Class placeholders are tolerated (the safe result
	 * of unserialize with allowed_classes => false).
	 *
	 * @param mixed              $data      The value to inspect.
	 * @param SCF_Fuzz_Generator $generator Generator (for failure reports).
	 * @param integer            $iteration Iteration index.
	 * @param mixed              $input     The original payload.
	 */
	private function assert_no_real_objects( $data, $generator, $iteration, $input ) {
		if ( is_object( $data ) ) {
			$this->assertInstanceOf(
				'__PHP_Incomplete_Class',
				$data,
				$generator->failure_message( $iteration, $input, 'SECURITY: acf_maybe_unserialize instantiated a real ' . get_class( $data ) )
			);
			return;
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$this->assert_no_real_objects( $value, $generator, $iteration, $input );
			}
		}
	}

	/**
	 * SECURITY PROPERTY: acf_maybe_unserialize() must never return a real
	 * object instance, even when fed object-injection payloads. It must also
	 * never throw, and must round-trip non-serialized strings unchanged.
	 *
	 * Seed: 612601. Default iterations: 300.
	 */
	public function test_maybe_unserialize_never_yields_real_objects() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED );
		$iterations = SCF_Fuzz_Generator::iterations( 300 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$payload = $generator->serialized_payload();

			try {
				$result = acf_maybe_unserialize( $payload );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $payload, 'acf_maybe_unserialize threw: ' . $e->getMessage() ) );
			}

			$this->assert_no_real_objects( $result, $generator, $i, $payload );
		}
	}

	/**
	 * Property: acf_maybe_unserialize() must return non-serialized input unchanged
	 * (it only unserializes strings that pass is_serialized()).
	 *
	 * Seed: 612602. Default iterations: 200.
	 */
	public function test_maybe_unserialize_passes_through_plain_strings() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 1 );
		$iterations = SCF_Fuzz_Generator::iterations( 200 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$value = $generator->unicode_string( 4 );

			// Skip strings that happen to look serialized.
			if ( is_serialized( $value ) ) {
				continue;
			}

			try {
				$result = acf_maybe_unserialize( $value );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $value, 'threw: ' . $e->getMessage() ) );
			}

			$this->assertSame( $value, $result, $generator->failure_message( $i, $value, 'plain string was altered' ) );
		}
	}

	/**
	 * Property: acf_esc_html() must return false for non-scalars and, for scalar input,
	 * a string with no raw <script>/<iframe>/<object> executable vector.
	 *
	 * Seed: 612603. Default iterations: 300.
	 */
	public function test_esc_html_strips_executable_vectors() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 2 );
		$iterations = SCF_Fuzz_Generator::iterations( 300 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			// Mix scalars and non-scalars.
			$input = $generator->chance( 0.75 ) ? $generator->unicode_string( 4 ) : $generator->anything( 2 );

			try {
				$result = acf_esc_html( $input );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $input, 'acf_esc_html threw: ' . $e->getMessage() ) );
			}

			if ( ! is_scalar( $input ) ) {
				$this->assertFalse( $result, $generator->failure_message( $i, $input, 'non-scalar should yield false' ) );
				continue;
			}

			$this->assertIsString( $result, $generator->failure_message( $i, $input ) );

			$lower = strtolower( $result );
			foreach ( array( '<script', '<iframe', '<object', '<embed', '<form' ) as $vector ) {
				$this->assertStringNotContainsString(
					$vector,
					$lower,
					$generator->failure_message( $i, $input, "output retained an executable vector '{$vector}': " . $result )
				);
			}
		}
	}

	/**
	 * Property: acf_esc_attrs() must never throw, must return a string, and that string
	 * must contain no raw '<' or '>' (esc_attr encodes them), so an attribute
	 * value cannot break out of its tag.
	 *
	 * Seed: 612604. Default iterations: 250.
	 */
	public function test_esc_attrs_cannot_break_out_of_tag() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 3 );
		$iterations = SCF_Fuzz_Generator::iterations( 250 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			// Build an attribute map with random (string) keys and scalar
			// values, plus the occasional array/object value which the
			// function json_encodes.
			$attrs = array();
			$count = $generator->int_between( 0, 5 );
			for ( $k = 0; $k < $count; $k++ ) {
				$key   = 'attr' . $k . $generator->ascii_string( 3 );
				$value = $generator->chance( 0.75 ) ? $generator->unicode_string( 2 ) : $generator->nested_array( 2, 2 );

				$attrs[ $key ] = $value;
			}

			try {
				$result = acf_esc_attrs( $attrs );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $attrs, 'acf_esc_attrs threw: ' . $e->getMessage() ) );
			}

			$this->assertIsString( $result, $generator->failure_message( $i, $attrs ) );
			$this->assertStringNotContainsString( '<', $result, $generator->failure_message( $i, $attrs, 'output contains raw <: ' . $result ) );
			$this->assertStringNotContainsString( '>', $result, $generator->failure_message( $i, $attrs, 'output contains raw >: ' . $result ) );
		}
	}

	/**
	 * Property: acf_parse_args() must always return an array and never throw, whatever
	 * the args / defaults shapes.
	 *
	 * Seed: 612605. Default iterations: 200.
	 */
	public function test_parse_args_always_returns_array() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 4 );
		$iterations = SCF_Fuzz_Generator::iterations( 200 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$args     = $generator->chance( 0.7 ) ? $generator->nested_array( 3, 4 ) : $generator->scalar();
			$defaults = $generator->nested_array( 2, 4 );

			try {
				$result = acf_parse_args( $args, $defaults );
			} catch ( Throwable $e ) {
				$this->fail(
					$generator->failure_message(
						$i,
						array(
							'args'     => $args,
							'defaults' => $defaults,
						),
						'acf_parse_args threw: ' . $e->getMessage()
					)
				);
			}

			$this->assertIsArray(
				$result,
				$generator->failure_message(
					$i,
					array(
						'args'     => $args,
						'defaults' => $defaults,
					)
				)
			);
		}
	}

	/**
	 * Property: acf_sanitize_request_args() must never throw and must return a value of
	 * the type implied by its input (bool/int/float/array/string), never an
	 * object for scalar/array inputs.
	 *
	 * Seed: 612606. Default iterations: 200.
	 */
	public function test_sanitize_request_args_returns_expected_type() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 5 );
		$iterations = SCF_Fuzz_Generator::iterations( 200 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$args = $generator->chance( 0.6 ) ? $generator->nested_array( 3, 4 ) : $generator->scalar();

			try {
				$result = acf_sanitize_request_args( $args );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $args, 'acf_sanitize_request_args threw: ' . $e->getMessage() ) );
			}

			if ( is_array( $args ) ) {
				$this->assertIsArray( $result, $generator->failure_message( $i, $args ) );
			} else {
				$this->assertFalse( is_object( $result ), $generator->failure_message( $i, $args, 'scalar input produced an object' ) );
			}
		}
	}

	/**
	 * The string helpers acf_slugify(), acf_idify() and acf_punctify() must
	 * never throw on hostile strings and must always return a string;
	 * acf_strlen() must always return a non-negative integer.
	 *
	 * Seed: 612607. Default iterations: 250.
	 */
	public function test_string_helpers_return_documented_types() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 6 );
		$iterations = SCF_Fuzz_Generator::iterations( 250 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$str = $generator->unicode_string( 4 );

			try {
				$slug  = acf_slugify( $str );
				$id    = acf_idify( $str );
				$punct = acf_punctify( $str );
				$len   = acf_strlen( $str );
			} catch ( Throwable $e ) {
				$this->fail( $generator->failure_message( $i, $str, 'a string helper threw: ' . $e->getMessage() ) );
			}

			$this->assertIsString( $slug, $generator->failure_message( $i, $str, 'acf_slugify did not return a string' ) );
			$this->assertIsString( $id, $generator->failure_message( $i, $str, 'acf_idify did not return a string' ) );
			$this->assertIsString( $punct, $generator->failure_message( $i, $str, 'acf_punctify did not return a string' ) );
			$this->assertIsInt( $len, $generator->failure_message( $i, $str, 'acf_strlen did not return an int' ) );
			$this->assertGreaterThanOrEqual( 0, $len, $generator->failure_message( $i, $str, 'acf_strlen returned a negative length' ) );

			// acf_slugify never produces uppercase or whitespace.
			$this->assertSame( $slug, strtolower( $slug ), $generator->failure_message( $i, $str, 'slug contains uppercase: ' . $slug ) );
			$this->assertDoesNotMatchRegularExpression( '/\s/', $slug, $generator->failure_message( $i, $str, 'slug contains whitespace: ' . $slug ) );
		}
	}
}
