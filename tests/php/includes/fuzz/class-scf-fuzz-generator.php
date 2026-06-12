<?php
/**
 * Deterministic random input generator for SCF property/fuzz tests.
 *
 * This is intentionally NOT coverage-guided fuzzing. It is a small,
 * dependency-free generator of "hostile" but deterministic inputs used by
 * the property-based tests in tests/php/includes/fuzz/.
 *
 * Determinism: the generator uses its own internal LCG (not mt_rand), so
 * its sequence cannot be perturbed by WordPress core or plugin code calling
 * PHP's global RNG between generations. Every test constructs the generator
 * with a fixed, documented seed, so any failure is reproducible by simply
 * re-running the test. Failure messages embed the seed, the iteration number
 * and a var_export() of the failing input.
 *
 * Iterations: each test uses a modest default iteration count to keep CI
 * fast. Set the SCF_FUZZ_ITERATIONS environment variable to crank the
 * number of iterations locally, e.g.:
 *
 *     SCF_FUZZ_ITERATIONS=5000 composer test:php -- --filter Fuzz
 *
 * @package wordpress/secure-custom-fields
 */

/**
 * Deterministic pseudo-random input generator for fuzz tests.
 */
class SCF_Fuzz_Generator {

	/**
	 * The seed this generator was constructed with.
	 *
	 * @var integer
	 */
	private $seed;

	/**
	 * Current internal LCG state.
	 *
	 * @var integer
	 */
	private $state;

	/**
	 * A pool of "interesting" string atoms: unicode, RTL, emoji, control
	 * characters, null bytes, quotes/backslashes and XSS/SQLi payloads.
	 *
	 * @var array
	 */
	const STRING_ATOMS = array(
		'',
		' ',
		'simple',
		'UPPER lower 12345',
		'0',
		'-1',
		'1e10',
		'0x1Az',
		'007',
		'3.14159',
		"\ttab\tseparated\t",
		"line\nbreaks\r\nhere",
		"null\0byte",
		"\0",
		"\x01\x02\x03\x1B",
		'😀🔥🚀💉',
		'👨‍👩‍👧‍👦',
		'مرحبا بالعالم',
		'שלום עולם',
		"\xE2\x80\xAEevil\xE2\x80\xAC",
		'Ωmega ångström ümlaut',
		'こんにちは世界',
		'中文字符串',
		'a̐éö̲',
		"quote'single",
		'quote"double',
		'back\\slash',
		'double\\\\backslash',
		'%00%0a%0d',
		'<script>alert(1)</script>',
		'<SCRIPT SRC=//evil.example/x.js></SCRIPT>',
		'"><img src=x onerror=alert(1)>',
		"'><svg/onload=alert(1)>",
		'javascript:alert(1)',
		'<a href="javascript:alert(1)">x</a>',
		'<iframe src="https://evil.example"></iframe>',
		"' OR '1'='1",
		"'; DROP TABLE wp_posts;--",
		'1; SELECT * FROM wp_users',
		'../../../../etc/passwd',
		'a:1:{i:0;s:3:"abc";}',
		'O:8:"stdClass":0:{}',
		'b:0;',
		'{"json":"value"}',
		'[1,2,3]',
		'&amp;&lt;&gt;&quot;&#39;',
		'%3Cscript%3Ealert(1)%3C/script%3E',
		"\xC3\x28",
		"\xF0\x9F\x92",
	);

	/**
	 * A pool of "interesting" integers.
	 *
	 * @var array
	 */
	const INT_ATOMS = array(
		0,
		1,
		-1,
		2,
		7,
		10,
		42,
		255,
		256,
		1024,
		65535,
		65536,
		-12345,
		2147483647,
		-2147483648,
		4294967296,
		PHP_INT_MAX,
		PHP_INT_MIN,
	);

	/**
	 * A pool of "interesting" floats.
	 *
	 * @var array
	 */
	const FLOAT_ATOMS = array(
		0.0,
		-0.0,
		1.5,
		-1.5,
		3.14159265358979,
		1.0e-10,
		1.0e10,
		1.0e308,
		-1.0e308,
		0.1,
		PHP_FLOAT_EPSILON,
		PHP_FLOAT_MAX,
		-PHP_FLOAT_MAX,
	);

	/**
	 * Constructor.
	 *
	 * @param integer $seed The deterministic seed for this generator.
	 */
	public function __construct( $seed ) {
		$this->seed  = (int) $seed;
		$this->state = ( abs( (int) $seed ) % 0x7FFFFFFE ) + 1;

		// Also seed PHP's global RNG so any code under test using mt_rand()
		// behaves deterministically too. mt_srand (not wp_rand) is required
		// precisely because determinism is the goal of these fuzz tests.
		mt_srand( $this->seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- deterministic seeding is the point of fuzz tests.
	}

	/**
	 * Returns the seed this generator was constructed with.
	 *
	 * @return integer
	 */
	public function get_seed() {
		return $this->seed;
	}

	/**
	 * Returns the iteration count for a property, honoring the
	 * SCF_FUZZ_ITERATIONS environment variable.
	 *
	 * @param integer $default_iterations Default iteration count for CI runs.
	 * @return integer
	 */
	public static function iterations( $default_iterations ) {
		$env = getenv( 'SCF_FUZZ_ITERATIONS' );

		if ( false !== $env && (int) $env > 0 ) {
			return (int) $env;
		}

		return $default_iterations;
	}

	/**
	 * Builds a reproducible failure message embedding seed, iteration and input.
	 *
	 * @param integer $iteration Zero-based iteration index that failed.
	 * @param mixed   $input     The failing input.
	 * @param string  $extra     Optional extra context.
	 * @return string
	 */
	public function failure_message( $iteration, $input, $extra = '' ) {
		$message = sprintf(
			"Fuzz property failed (seed=%d, iteration=%d).\nInput: %s",
			$this->seed,
			$iteration,
			var_export( $input, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- intentional: reproducible fuzz failure reports.
		);

		if ( '' !== $extra ) {
			$message .= "\nContext: " . $extra;
		}

		return $message;
	}

	/**
	 * Advances the internal LCG and returns a 31-bit non-negative integer.
	 *
	 * Uses the classic glibc constants; statistical quality is more than
	 * enough for input fuzzing, and the math stays inside 64-bit ints.
	 *
	 * @return integer
	 */
	private function next() {
		$this->state = ( 1103515245 * $this->state + 12345 ) & 0x7FFFFFFF;
		return $this->state;
	}

	/**
	 * Returns a random integer between $min and $max inclusive.
	 *
	 * @param integer $min Lower bound.
	 * @param integer $max Upper bound.
	 * @return integer
	 */
	public function int_between( $min, $max ) {
		if ( $max <= $min ) {
			return $min;
		}

		return $min + ( $this->next() % ( $max - $min + 1 ) );
	}

	/**
	 * Returns a random boolean.
	 *
	 * @return boolean
	 */
	public function random_bool() {
		return ( $this->next() & 1 ) === 1;
	}

	/**
	 * Returns true with probability $chance (0..1).
	 *
	 * @param float $chance Probability of returning true.
	 * @return boolean
	 */
	public function chance( $chance ) {
		return $this->next() < (int) ( $chance * 0x7FFFFFFF );
	}

	/**
	 * Picks a random element from an array.
	 *
	 * @param array $items Non-empty list of candidates.
	 * @return mixed
	 */
	public function pick( array $items ) {
		$values = array_values( $items );
		return $values[ $this->int_between( 0, count( $values ) - 1 ) ];
	}

	/**
	 * Returns a random "interesting" integer.
	 *
	 * @return integer
	 */
	public function int_value() {
		if ( $this->chance( 0.5 ) ) {
			return $this->pick( self::INT_ATOMS );
		}

		return $this->int_between( -100000, 100000 );
	}

	/**
	 * Returns a random "interesting" float.
	 *
	 * @param boolean $include_special Whether to include INF/NAN.
	 * @return float
	 */
	public function float_value( $include_special = false ) {
		if ( $include_special && $this->chance( 0.15 ) ) {
			return $this->pick( array( INF, -INF, NAN ) );
		}

		return $this->pick( self::FLOAT_ATOMS );
	}

	/**
	 * Returns a random string of printable ASCII characters.
	 *
	 * @param integer $max_len Maximum length.
	 * @return string
	 */
	public function ascii_string( $max_len = 16 ) {
		$len = $this->int_between( 0, $max_len );
		$out = '';

		for ( $i = 0; $i < $len; $i++ ) {
			$out .= chr( $this->int_between( 32, 126 ) );
		}

		return $out;
	}

	/**
	 * Returns a random string of completely arbitrary bytes.
	 *
	 * @param integer $max_len Maximum length.
	 * @return string
	 */
	public function byte_string( $max_len = 32 ) {
		$len = $this->int_between( 0, $max_len );
		$out = '';

		for ( $i = 0; $i < $len; $i++ ) {
			$out .= chr( $this->int_between( 0, 255 ) );
		}

		return $out;
	}

	/**
	 * Returns a random hostile unicode string assembled from the atom pool,
	 * random ASCII and random raw bytes.
	 *
	 * @param integer $max_atoms          Maximum number of atoms to concatenate.
	 * @param boolean $include_backslashes Whether backslashes may appear. WP's
	 *                                     meta API wp_unslash()es written values,
	 *                                     so byte-identity round-trip properties
	 *                                     exclude backslashes (see the round-trip
	 *                                     test for the documented behavior).
	 * @return string
	 */
	public function unicode_string( $max_atoms = 4, $include_backslashes = true ) {
		$count = $this->int_between( 1, max( 1, $max_atoms ) );
		$out   = '';

		for ( $i = 0; $i < $count; $i++ ) {
			$roll = $this->int_between( 0, 9 );

			if ( $roll < 7 ) {
				$out .= $this->pick( self::STRING_ATOMS );
			} elseif ( $roll < 9 ) {
				$out .= $this->ascii_string( 8 );
			} else {
				$out .= $this->byte_string( 8 );
			}
		}

		if ( ! $include_backslashes ) {
			$out = str_replace( '\\', '/', $out );
		}

		return $out;
	}

	/**
	 * Returns a random scalar (or null).
	 *
	 * @param boolean $include_special_floats Whether INF/NAN may be produced.
	 * @return mixed
	 */
	public function scalar( $include_special_floats = false ) {
		switch ( $this->int_between( 0, 5 ) ) {
			case 0:
				return null;
			case 1:
				return $this->random_bool();
			case 2:
				return $this->int_value();
			case 3:
				return $this->float_value( $include_special_floats );
			default:
				return $this->unicode_string();
		}
	}

	/**
	 * Returns a random nested array with capped depth and breadth.
	 *
	 * Leaves are scalars (no INF/NAN so the structures stay comparable).
	 *
	 * @param integer $max_depth   Maximum nesting depth.
	 * @param integer $max_breadth Maximum elements per level.
	 * @return array
	 */
	public function nested_array( $max_depth = 3, $max_breadth = 4 ) {
		$size = $this->int_between( 0, $max_breadth );
		$out  = array();

		for ( $i = 0; $i < $size; $i++ ) {
			// Mix integer and string keys.
			if ( $this->chance( 0.5 ) ) {
				$key = $this->int_between( 0, 20 );
			} else {
				$key = $this->unicode_string( 1 );
			}

			if ( $max_depth > 1 && $this->chance( 0.4 ) ) {
				$out[ $key ] = $this->nested_array( $max_depth - 1, $max_breadth );
			} else {
				$out[ $key ] = $this->scalar();
			}
		}

		return $out;
	}

	/**
	 * Returns completely arbitrary data: scalars, arrays or plain objects.
	 *
	 * @param integer $max_depth Maximum nesting depth for containers.
	 * @return mixed
	 */
	public function anything( $max_depth = 3 ) {
		switch ( $this->int_between( 0, 7 ) ) {
			case 0:
				return $this->nested_array( $max_depth );
			case 1:
				return (object) $this->nested_array( min( 2, $max_depth ) );
			default:
				return $this->scalar();
		}
	}

	/**
	 * Returns a random field type name from the simple core types.
	 *
	 * @return string
	 */
	public function field_type() {
		return $this->pick(
			array( 'text', 'textarea', 'number', 'email', 'url', 'select', 'checkbox', 'radio', 'true_false' )
		);
	}

	/**
	 * Returns a random (possibly nonsensical) field configuration array.
	 *
	 * Always contains the structural keys ACF expects from a registered
	 * field (key/name/label/type) so the result is a *plausible* field,
	 * while every setting value is fuzzed.
	 *
	 * @param string $type Optional fixed field type; random when empty.
	 * @return array
	 */
	public function field_config( $type = '' ) {
		if ( '' === $type ) {
			$type = $this->field_type();
		}

		$field = array(
			'key'   => 'field_fuzz_' . $this->int_between( 0, PHP_INT_MAX >> 8 ),
			'name'  => 'fuzz_' . $this->int_between( 0, 999999 ),
			'label' => $this->unicode_string( 2 ),
			'type'  => $type,
		);

		if ( $this->chance( 0.5 ) ) {
			$field['required'] = $this->random_bool();
		}

		if ( $this->chance( 0.4 ) ) {
			$field['default_value'] = $this->scalar();
		}

		if ( $this->chance( 0.4 ) ) {
			$field['min'] = $this->pick( array( 0, 1, 5, -3, '10', '2.5', '' ) );
		}

		if ( $this->chance( 0.4 ) ) {
			$field['max'] = $this->pick( array( 0, 1, 5, 100, '10', '0.5', '' ) );
		}

		if ( $this->chance( 0.4 ) ) {
			$field['maxlength'] = $this->pick( array( 0, 1, 5, 64, '12', '' ) );
		}

		if ( in_array( $type, array( 'select', 'checkbox', 'radio' ), true ) ) {
			$field['choices'] = array(
				'a'                        => 'Choice A',
				'b'                        => 'Choice B',
				$this->unicode_string( 1 ) => $this->unicode_string( 1 ),
			);

			if ( 'select' === $type && $this->chance( 0.5 ) ) {
				$field['multiple'] = $this->random_bool();
			}
		}

		return $field;
	}

	/**
	 * Returns random garbage suitable for feeding into post-ID decoding
	 * functions: ints, floats, bools, null, arrays, objects, and strings
	 * with both valid and invalid type prefixes.
	 *
	 * @return mixed
	 */
	public function post_id_input() {
		switch ( $this->int_between( 0, 11 ) ) {
			case 0:
				return $this->int_value();
			case 1:
				return $this->float_value();
			case 2:
				return $this->random_bool();
			case 3:
				return null;
			case 4:
				return $this->nested_array( 2, 3 );
			case 5:
				return (object) array(
					$this->unicode_string( 1 ) => $this->scalar(),
				);
			case 6:
				// Well-formed prefix with a random id part.
				$prefix = $this->pick( array( 'post', 'user', 'term', 'comment', 'attachment', 'widget', 'menu', 'menu_item', 'block', 'option', 'blog', 'site', 'woo_order', 'taxonomy' ) );
				$id     = $this->pick( array( '1', '0', '-5', '999999999999999999999', 'abc', '1.5', '😀', '' ) );
				return $prefix . '_' . $id;
			case 7:
				// Strings with multiple/odd underscores.
				return $this->pick(
					array(
						'user_abc_9',
						'_',
						'__',
						'_1',
						'user_',
						'a_b_c_d_e',
						'term__7',
						'option',
						'options',
						'comment_007',
						'block_abc123',
						'user_-12',
					)
				);
			case 8:
				return $this->unicode_string( 2 );
			case 9:
				return (string) $this->int_value();
			case 10:
				return '';
			default:
				return $this->byte_string( 12 );
		}
	}

	/**
	 * Returns a random serialized-PHP payload, biased toward object
	 * injection attempts. Used to verify acf_maybe_unserialize() never
	 * instantiates real classes.
	 *
	 * @return string
	 */
	public function serialized_payload() {
		$payloads = array(
			'O:8:"stdClass":0:{}',
			'O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}',
			'O:20:"SCF_Definitely_Not_A":0:{}',
			'a:1:{i:0;O:8:"stdClass":0:{}}',
			'a:2:{i:0;s:1:"a";i:1;a:1:{s:3:"obj";O:8:"stdClass":0:{}}}',
			'C:11:"ArrayObject":37:{x:i:0;a:1:{i:0;s:4:"evil";};m:a:0:{}}',
			'O:9:"Exception":7:{s:10:"*message";s:4:"pwnd";s:17:"Exceptionstring";s:0:"";s:7:"*code";i:0;s:7:"*file";s:1:"x";s:7:"*line";i:1;s:16:"Exceptiontrace";a:0:{}s:19:"Exceptionprevious";N;}',
			'a:2:{s:4:"safe";b:1;s:1:"n";i:1;}', // A safe, class-free array payload (no object tokens).
			's:13:"just a string";', // A safe scalar string payload.
			'b:1;',
			'i:42;',
			'd:1.5;',
			'N;',
			's:4:"abc";', // Wrong length: corrupt.
			'a:2:{i:0;s:1:"a";}', // Truncated count: corrupt.
		);

		if ( $this->chance( 0.7 ) ) {
			return $this->pick( $payloads );
		}

		// Random garbage that may or may not look serialized.
		return $this->pick( array( 'O:', 'a:', 's:', '' ) ) . $this->byte_string( 16 );
	}
}
