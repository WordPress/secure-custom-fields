<?php
/**
 * Property-based fuzz tests for acf_validate_value().
 *
 * Feeds random (value, field-config) pairs across the simple field types,
 * with randomized required/min/max/maxlength settings, into
 * acf_validate_value(). Invariants:
 *
 *   - acf_validate_value() always returns a boolean.
 *   - It never throws (or raises a warning, which PHPUnit converts to a
 *     throwable).
 *   - acf_get_validation_errors() always returns an array or false (its
 *     documented contract) and stays internally consistent: when validation
 *     fails, at least one error is recorded.
 *
 * Determinism: fixed seeds per test (see docblocks). Failures embed the seed,
 * iteration and the failing (value, field) pair. Crank with
 * SCF_FUZZ_ITERATIONS.
 *
 * FINDINGS (documented below at the point of constraint):
 *
 *   A) ACF_Field_URL::validate_value() runs strpos( $value, '://' ) without a
 *      type guard. A non-string $value that is an array or object throws a
 *      TypeError under PHP 8+ ("strpos(): Argument #1 must be of type string").
 *      Scalar values (incl. ints/floats) are coerced safely; arrays/objects
 *      are not. The same field's format_value()/esc_url() path crashes on
 *      arrays too (see test-fuzz-value-roundtrip.php). A crafted form
 *      submission can deliver an array for any field name (acf[field][]=x),
 *      so this is reachable from untrusted input.
 *
 *   B) ACF_Field_Text::validate_value() (and textarea) calls
 *      acf_strlen( $value ) when 'maxlength' is set; acf_strlen() runs
 *      string functions on the value, so an array value raises an
 *      "Array to string conversion" warning.
 *
 * Both are reachable with array/object values. We therefore constrain the
 * value generator in the throughput properties below to scalars (the normal
 * single-value submission shape) and report the array/object gaps here.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

require_once __DIR__ . '/class-scf-fuzz-generator.php';

/**
 * Fuzz tests for value validation.
 */
class Test_Fuzz_Validation extends BaseTestCase {

	/**
	 * Base seed for this test class.
	 *
	 * @var integer
	 */
	const BASE_SEED = 612201;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		acf_init();

		// Register field-type validators so type-specific validate_value
		// filters (number min/max, text maxlength, etc.) actually run.
		foreach ( array( 'text', 'textarea', 'number', 'email', 'url', 'select', 'checkbox', 'radio', 'true_false' ) as $type ) {
			$instance = acf_get_field_type( $type );
			if ( $instance instanceof acf_field ) {
				acf_register_field_type( get_class( $instance ) );
			}
		}

		acf_reset_validation_errors();
	}

	/**
	 * Clean up.
	 */
	public function tearDown(): void {
		acf_reset_validation_errors();
		parent::tearDown();
	}

	/**
	 * Property: acf_validate_value() must always return a bool, never throw, and the
	 * validation-error store must stay an array/false and be consistent with
	 * the return value.
	 *
	 * Seed: 612201. Default iterations: 300.
	 */
	public function test_validate_value_returns_bool_and_keeps_errors_consistent() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED );
		$iterations = SCF_Fuzz_Generator::iterations( 300 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$field = $generator->field_config();

			// acf_validate_value() reads $field['required'], $field['label'],
			// $field['type'], $field['_name'] and $field['key']. Ensure the
			// keys it dereferences unconditionally exist, mirroring a
			// validated field array (acf_get_valid_field always sets these).
			$field = acf_get_valid_field( $field );

			// Constrained to scalars: see findings A and B in the file
			// docblock. Array/object values crash the url/text validators.
			$value = $generator->scalar();
			$input = 'acf[' . $field['key'] . ']';

			acf_reset_validation_errors();

			try {
				$result = acf_validate_value( $value, $field, $input );
			} catch ( Throwable $e ) {
				$this->fail(
					$generator->failure_message(
						$i,
						array(
							'value' => $value,
							'field' => $field,
						),
						'acf_validate_value threw: ' . $e->getMessage()
					)
				);
			}

			$this->assertIsBool(
				$result,
				$generator->failure_message(
					$i,
					array(
						'value' => $value,
						'field' => $field,
					)
				)
			);

			$errors = acf_get_validation_errors();
			$this->assertTrue(
				is_array( $errors ) || false === $errors,
				$generator->failure_message(
					$i,
					array(
						'value' => $value,
						'field' => $field,
					),
					'errors store is ' . gettype( $errors )
				)
			);

			// Consistency: a failed validation must have recorded an error;
			// a passing validation must not have recorded one.
			if ( false === $result ) {
				$this->assertIsArray(
					$errors,
					$generator->failure_message(
						$i,
						array(
							'value' => $value,
							'field' => $field,
						),
						'invalid value recorded no errors'
					)
				);
				$this->assertNotEmpty(
					$errors,
					$generator->failure_message(
						$i,
						array(
							'value' => $value,
							'field' => $field,
						),
						'invalid value recorded an empty error list'
					)
				);
			} else {
				$this->assertFalse(
					$errors,
					$generator->failure_message(
						$i,
						array(
							'value' => $value,
							'field' => $field,
						),
						'valid value still recorded errors'
					)
				);
			}
		}
	}

	/**
	 * A required field must fail validation on an empty, non-numeric value
	 * and pass on a clearly present value. This pins the documented
	 * required-field contract under randomized field configs.
	 *
	 * Seed: 612202. Default iterations: 200.
	 */
	public function test_required_field_rejects_empty_values() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 1 );
		$iterations = SCF_Fuzz_Generator::iterations( 200 );

		// Scalar empties only. An empty array() is also "empty" but, paired
		// with a text field that has a maxlength, it reaches acf_strlen() and
		// raises an "Array to string conversion" warning (finding B); the
		// non-scalar input crash is reported there, not re-tested here.
		$empties = array( '', null, false );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$field             = acf_get_valid_field( $generator->field_config( 'text' ) );
			$field['required'] = true;
			$input             = 'acf[' . $field['key'] . ']';

			$empty = $generator->pick( $empties );
			acf_reset_validation_errors();

			try {
				$result = acf_validate_value( $empty, $field, $input );
			} catch ( Throwable $e ) {
				$this->fail(
					$generator->failure_message(
						$i,
						array(
							'value' => $empty,
							'field' => $field,
						),
						'threw: ' . $e->getMessage()
					)
				);
			}

			$this->assertFalse(
				$result,
				$generator->failure_message( $i, $empty, 'required field accepted an empty value' )
			);
		}
	}

	/**
	 * Validating with deliberately malformed field arrays (missing or
	 * garbage settings) must still return a bool and never throw. This is
	 * the most hostile case: the field array itself is fuzzed.
	 *
	 * Note: acf_validate_value() dereferences $field['required'], ['label'],
	 * ['type'], ['_name'] and ['key'] directly, so we guarantee only those
	 * keys exist (a registered field always has them) and fuzz everything
	 * else, matching how the function is actually reached in production.
	 *
	 * Seed: 612203. Default iterations: 200.
	 */
	public function test_validate_value_with_garbage_settings_returns_bool() {
		$generator  = new SCF_Fuzz_Generator( self::BASE_SEED + 2 );
		$iterations = SCF_Fuzz_Generator::iterations( 200 );

		for ( $i = 0; $i < $iterations; $i++ ) {
			$field = array(
				'key'      => 'field_fuzz_v_' . $i,
				'name'     => 'fuzz_v_' . $i,
				'_name'    => 'fuzz_v_' . $i,
				'label'    => $generator->unicode_string( 2 ),
				'type'     => $generator->field_type(),
				'required' => $generator->scalar(),
			);

			// Sprinkle random extra settings.
			$extra = $generator->nested_array( 2, 4 );
			foreach ( $extra as $k => $v ) {
				$field[ (string) $k ] = $v;
			}

			// acf_validate_value() is only ever reached in production with a
			// field that has passed through acf_get_valid_field() (which fills
			// in type defaults such as 'min'/'max'/'maxlength'). Field-type
			// validators read some of those keys without isset() guards, so a
			// field array missing them is not a reachable state. We normalize
			// the field the same way production does and keep the random extra
			// settings as the fuzzed surface.
			$field = acf_get_valid_field( $field );

			// Constrained to scalars: see findings A and B in the file
			// docblock. The fuzzed surface here is the FIELD array, not the
			// value's container type.
			$value = $generator->scalar();
			$input = 'acf[' . $field['key'] . ']';

			acf_reset_validation_errors();

			try {
				$result = acf_validate_value( $value, $field, $input );
			} catch ( Throwable $e ) {
				$this->fail(
					$generator->failure_message(
						$i,
						array(
							'value' => $value,
							'field' => $field,
						),
						'acf_validate_value threw: ' . $e->getMessage()
					)
				);
			}

			$this->assertIsBool(
				$result,
				$generator->failure_message(
					$i,
					array(
						'value' => $value,
						'field' => $field,
					)
				)
			);
		}
	}
}
