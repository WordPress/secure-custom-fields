<?php
/**
 * Tests for helper functions in acf-helper-functions.php.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests for helper functions.
 *
 * @covers ::acf_is_empty
 * @covers ::acf_not_empty
 * @covers ::acf_uniqid
 * @covers ::acf_merge_attributes
 * @covers ::acf_cache_key
 * @covers ::acf_request_args
 * @covers ::acf_request_arg
 * @covers ::acf_enable_filter
 * @covers ::acf_disable_filter
 * @covers ::acf_is_filter_enabled
 * @covers ::acf_get_filters
 * @covers ::acf_set_filters
 * @covers ::acf_disable_filters
 * @covers ::acf_enable_filters
 * @covers ::acf_idval
 * @covers ::acf_maybe_idval
 * @covers ::acf_format_numerics
 * @covers ::acf_numval
 * @covers ::acf_idify
 * @covers ::acf_slugify
 * @covers ::acf_punctify
 * @covers ::acf_strlen
 * @covers ::acf_did
 * @covers ::acf_with_default
 * @covers ::acf_sanitize_request_args
 * @covers ::acf_sanitize_files_array
 * @covers ::acf_sanitize_files_value_array
 * @covers ::acf_maybe_unserialize
 */
class Test_ACF_Helper_Functions extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Reset global state.
		global $acf_uniqid;
		$acf_uniqid = 1;
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down(): void {
		// Reset filters store.
		$store = acf_get_store( 'filters' );
		if ( $store ) {
			$store->set( array() );
		}

		// Reset data store.
		$data_store = acf_get_store( 'data' );
		if ( $data_store ) {
			$data_store->set( array() );
		}

		// Clear superglobals.
		$_REQUEST = array();
		$_POST    = array();

		parent::tear_down();
	}

	/**
	 * Data provider for acf_is_empty tests.
	 *
	 * @return array[]
	 */
	public function data_provider_is_empty(): array {
		return array(
			'null'          => array( null, true ),
			'false'         => array( false, true ),
			'empty_string'  => array( '', true ),
			'empty_array'   => array( array(), true ),
			'zero_int'      => array( 0, false ),
			'zero_float'    => array( 0.0, false ),
			'zero_string'   => array( '0', false ),
			'positive_int'  => array( 1, false ),
			'string'        => array( 'hello', false ),
			'array'         => array( array( 1, 2, 3 ), false ),
			'true'          => array( true, false ),
			'negative_int'  => array( -1, false ),
			'negative_zero' => array( -0, false ),
		);
	}

	/**
	 * Test acf_is_empty with various values.
	 *
	 * @dataProvider data_provider_is_empty
	 *
	 * @param mixed $value    The value to test.
	 * @param bool  $expected Expected result.
	 */
	public function test_acf_is_empty( $value, bool $expected ): void {
		$this->assertSame( $expected, acf_is_empty( $value ) );
	}

	/**
	 * Data provider for acf_not_empty tests.
	 *
	 * @return array[]
	 */
	public function data_provider_not_empty(): array {
		return array(
			'null'         => array( null, false ),
			'false'        => array( false, false ),
			'empty_string' => array( '', false ),
			'empty_array'  => array( array(), false ),
			'zero_int'     => array( 0, true ),
			'zero_float'   => array( 0.0, true ),
			'zero_string'  => array( '0', true ),
			'positive_int' => array( 1, true ),
			'string'       => array( 'hello', true ),
			'array'        => array( array( 1, 2, 3 ), true ),
			'true'         => array( true, true ),
			'negative_int' => array( -1, true ),
		);
	}

	/**
	 * Test acf_not_empty with various values.
	 *
	 * @dataProvider data_provider_not_empty
	 *
	 * @param mixed $value    The value to test.
	 * @param bool  $expected Expected result.
	 */
	public function test_acf_not_empty( $value, bool $expected ): void {
		$this->assertSame( $expected, acf_not_empty( $value ) );
	}

	/**
	 * Test acf_uniqid generates unique IDs.
	 */
	public function test_acf_uniqid_generates_unique_ids(): void {
		$id1 = acf_uniqid();
		$id2 = acf_uniqid();
		$id3 = acf_uniqid();

		$this->assertNotEquals( $id1, $id2 );
		$this->assertNotEquals( $id2, $id3 );
	}

	/**
	 * Test acf_uniqid uses prefix.
	 */
	public function test_acf_uniqid_uses_prefix(): void {
		$default_id = acf_uniqid();
		$this->assertStringStartsWith( 'acf-', $default_id );

		// Reset counter.
		global $acf_uniqid;
		$acf_uniqid = 1;

		$custom_id = acf_uniqid( 'custom' );
		$this->assertStringStartsWith( 'custom-', $custom_id );
	}

	/**
	 * Test acf_uniqid increments counter.
	 */
	public function test_acf_uniqid_increments_counter(): void {
		global $acf_uniqid;
		$acf_uniqid = 1;

		$this->assertEquals( 'acf-1', acf_uniqid() );
		$this->assertEquals( 'acf-2', acf_uniqid() );
		$this->assertEquals( 'acf-3', acf_uniqid() );
	}

	/**
	 * Test acf_merge_attributes merges arrays.
	 */
	public function test_acf_merge_attributes_merges_arrays(): void {
		$array1 = array(
			'id'   => 'test-id',
			'name' => 'test-name',
		);
		$array2 = array(
			'value' => 'test-value',
			'type'  => 'text',
		);

		$result = acf_merge_attributes( $array1, $array2 );

		$this->assertEquals( 'test-id', $result['id'] );
		$this->assertEquals( 'test-name', $result['name'] );
		$this->assertEquals( 'test-value', $result['value'] );
		$this->assertEquals( 'text', $result['type'] );
	}

	/**
	 * Test acf_merge_attributes concatenates class attribute.
	 */
	public function test_acf_merge_attributes_concatenates_class(): void {
		$array1 = array( 'class' => 'class-a' );
		$array2 = array( 'class' => 'class-b' );

		$result = acf_merge_attributes( $array1, $array2 );

		$this->assertEquals( 'class-a class-b', $result['class'] );
	}

	/**
	 * Test acf_merge_attributes concatenates style attribute.
	 */
	public function test_acf_merge_attributes_concatenates_style(): void {
		$array1 = array( 'style' => 'color: red;' );
		$array2 = array( 'style' => 'font-size: 12px;' );

		$result = acf_merge_attributes( $array1, $array2 );

		$this->assertEquals( 'color: red; font-size: 12px;', $result['style'] );
	}

	/**
	 * Test acf_merge_attributes trims class and style values.
	 */
	public function test_acf_merge_attributes_trims_values(): void {
		$array1 = array( 'class' => '  class-a  ' );
		$array2 = array( 'class' => '  class-b  ' );

		$result = acf_merge_attributes( $array1, $array2 );

		$this->assertEquals( 'class-a class-b', $result['class'] );
	}

	/**
	 * Test acf_merge_attributes with second array overriding first.
	 */
	public function test_acf_merge_attributes_override(): void {
		$array1 = array(
			'id'    => 'original',
			'class' => 'class-a',
		);
		$array2 = array(
			'id'    => 'new',
			'class' => 'class-b',
		);

		$result = acf_merge_attributes( $array1, $array2 );

		// id should be overridden.
		$this->assertEquals( 'new', $result['id'] );
		// class should be concatenated.
		$this->assertEquals( 'class-a class-b', $result['class'] );
	}

	/**
	 * Test acf_cache_key returns key.
	 */
	public function test_acf_cache_key_returns_key(): void {
		$key    = 'test_cache_key';
		$result = acf_cache_key( $key );

		$this->assertEquals( $key, $result );
	}

	/**
	 * Test acf_cache_key applies filter.
	 */
	public function test_acf_cache_key_applies_filter(): void {
		add_filter(
			'acf/get_cache_key',
			function ( $key ) {
				return 'filtered_' . $key;
			}
		);

		$result = acf_cache_key( 'test' );

		$this->assertEquals( 'filtered_test', $result );

		remove_all_filters( 'acf/get_cache_key' );
	}

	/**
	 * Test acf_request_args returns defaults when no request data.
	 */
	public function test_acf_request_args_returns_defaults(): void {
		$_REQUEST = array();
		$defaults = array(
			'page'   => 1,
			'search' => '',
		);

		$result = acf_request_args( $defaults );

		$this->assertEquals( 1, $result['page'] );
		$this->assertEquals( '', $result['search'] );
	}

	/**
	 * Test acf_request_args gets values from request.
	 */
	public function test_acf_request_args_gets_request_values(): void {
		$_REQUEST = array(
			'page'   => '5',
			'search' => 'test',
		);

		$defaults = array(
			'page'   => 1,
			'search' => '',
		);

		$result = acf_request_args( $defaults );

		$this->assertEquals( '5', $result['page'] );
		$this->assertEquals( 'test', $result['search'] );
	}

	/**
	 * Test acf_request_arg returns default when not set.
	 */
	public function test_acf_request_arg_returns_default(): void {
		$_REQUEST = array();

		$result = acf_request_arg( 'missing', 'default_value' );

		$this->assertEquals( 'default_value', $result );
	}

	/**
	 * Test acf_request_arg returns request value.
	 */
	public function test_acf_request_arg_returns_request_value(): void {
		$_REQUEST = array( 'test_key' => 'test_value' );

		$result = acf_request_arg( 'test_key', 'default' );

		$this->assertEquals( 'test_value', $result );
	}

	/**
	 * Test acf_request_arg returns null as default.
	 */
	public function test_acf_request_arg_null_default(): void {
		$_REQUEST = array();

		$result = acf_request_arg( 'missing' );

		$this->assertNull( $result );
	}

	/**
	 * Test acf_enable_filter enables a filter.
	 */
	public function test_acf_enable_filter(): void {
		acf_enable_filter( 'test_filter' );

		$this->assertTrue( acf_is_filter_enabled( 'test_filter' ) );
	}

	/**
	 * Test acf_disable_filter disables a filter.
	 */
	public function test_acf_disable_filter(): void {
		acf_enable_filter( 'test_filter' );
		acf_disable_filter( 'test_filter' );

		$this->assertFalse( acf_is_filter_enabled( 'test_filter' ) );
	}

	/**
	 * Test acf_is_filter_enabled returns null for non-existent filter.
	 */
	public function test_acf_is_filter_enabled_returns_null_for_nonexistent(): void {
		$result = acf_is_filter_enabled( 'nonexistent_filter' );

		$this->assertNull( $result );
	}

	/**
	 * Test acf_get_filters returns all filters.
	 */
	public function test_acf_get_filters(): void {
		acf_enable_filter( 'filter_a' );
		acf_disable_filter( 'filter_b' );

		$filters = acf_get_filters();

		$this->assertIsArray( $filters );
		$this->assertTrue( $filters['filter_a'] );
		$this->assertFalse( $filters['filter_b'] );
	}

	/**
	 * Test acf_set_filters sets multiple filters.
	 */
	public function test_acf_set_filters(): void {
		$filters = array(
			'filter_a' => true,
			'filter_b' => false,
			'filter_c' => true,
		);

		acf_set_filters( $filters );
		$result = acf_get_filters();

		// Check that our specific filters are set correctly.
		$this->assertTrue( $result['filter_a'] );
		$this->assertFalse( $result['filter_b'] );
		$this->assertTrue( $result['filter_c'] );
	}

	/**
	 * Test acf_disable_filters disables all and returns previous state.
	 */
	public function test_acf_disable_filters(): void {
		acf_enable_filter( 'filter_a' );
		acf_enable_filter( 'filter_b' );

		$prev_state = acf_disable_filters();

		// Check previous state was returned.
		$this->assertTrue( $prev_state['filter_a'] );
		$this->assertTrue( $prev_state['filter_b'] );

		// Check filters are now disabled.
		$this->assertFalse( acf_is_filter_enabled( 'filter_a' ) );
		$this->assertFalse( acf_is_filter_enabled( 'filter_b' ) );
	}

	/**
	 * Test acf_enable_filters enables all and returns previous state.
	 */
	public function test_acf_enable_filters_enables_all(): void {
		acf_disable_filter( 'filter_a' );
		acf_disable_filter( 'filter_b' );

		$prev_state = acf_enable_filters();

		// Check previous state was returned.
		$this->assertFalse( $prev_state['filter_a'] );
		$this->assertFalse( $prev_state['filter_b'] );

		// Check filters are now enabled.
		$this->assertTrue( acf_is_filter_enabled( 'filter_a' ) );
		$this->assertTrue( acf_is_filter_enabled( 'filter_b' ) );
	}

	/**
	 * Test acf_enable_filters restores specific state.
	 */
	public function test_acf_enable_filters_restores_state(): void {
		// Set our test filters.
		acf_enable_filter( 'filter_a' );
		acf_disable_filter( 'filter_b' );

		// Get current state before disabling.
		$prev_state = acf_disable_filters();

		// Verify filters were disabled.
		$this->assertFalse( acf_is_filter_enabled( 'filter_a' ) );
		$this->assertFalse( acf_is_filter_enabled( 'filter_b' ) );

		// Restore original state.
		acf_enable_filters( $prev_state );

		// Verify our specific filters are restored to their original values.
		$this->assertTrue( acf_is_filter_enabled( 'filter_a' ) );
		$this->assertFalse( acf_is_filter_enabled( 'filter_b' ) );
	}

	/**
	 * Data provider for acf_idval tests.
	 *
	 * @return array[]
	 */
	public function data_provider_idval(): array {
		$obj_with_id     = new stdClass();
		$obj_with_id->ID = 42;

		$obj_without_id = new stdClass();

		return array(
			'integer'            => array( 123, 123 ),
			'string_numeric'     => array( '456', 456 ),
			'float'              => array( 78.9, 78 ),
			'array_with_id'      => array( array( 'ID' => 100 ), 100 ),
			'array_without_id'   => array( array( 'other' => 'value' ), 0 ),
			'object_with_id'     => array( $obj_with_id, 42 ),
			'object_without_id'  => array( $obj_without_id, 0 ),
			'string_non_numeric' => array( 'hello', 0 ),
			'null'               => array( null, 0 ),
			'empty_array'        => array( array(), 0 ),
			'zero'               => array( 0, 0 ),
			'negative'           => array( -5, -5 ),
			'array_with_id_zero' => array( array( 'ID' => 0 ), 0 ),
		);
	}

	/**
	 * Test acf_idval with various values.
	 *
	 * @dataProvider data_provider_idval
	 *
	 * @param mixed $value    The value to test.
	 * @param int   $expected Expected result.
	 */
	public function test_acf_idval( $value, int $expected ): void {
		$this->assertSame( $expected, acf_idval( $value ) );
	}

	/**
	 * Test acf_maybe_idval returns ID when found.
	 */
	public function test_acf_maybe_idval_returns_id(): void {
		$value = array( 'ID' => 123 );

		$result = acf_maybe_idval( $value );

		$this->assertEquals( 123, $result );
	}

	/**
	 * Test acf_maybe_idval returns original when no ID.
	 */
	public function test_acf_maybe_idval_returns_original(): void {
		$value = 'no-id-here';

		$result = acf_maybe_idval( $value );

		$this->assertEquals( 'no-id-here', $result );
	}

	/**
	 * Test acf_maybe_idval with zero ID returns original value.
	 */
	public function test_acf_maybe_idval_with_zero_id(): void {
		$value = array( 'ID' => 0 );

		$result = acf_maybe_idval( $value );

		// Since acf_idval returns 0, and 0 is falsy, original value is returned.
		$this->assertEquals( $value, $result );
	}

	/**
	 * Data provider for acf_format_numerics tests.
	 *
	 * @return array[]
	 */
	public function data_provider_format_numerics(): array {
		return array(
			'integer_string'  => array( '42', 42 ),
			'float_string'    => array( '3.14', 3.14 ),
			'non_numeric'     => array( 'hello', 'hello' ),
			'integer'         => array( 42, 42 ),
			'float'           => array( 3.14, 3.14 ),
			'zero_string'     => array( '0', 0 ),
			'negative_string' => array( '-5', -5 ),
			'array_mixed'     => array(
				array( '1', '2.5', 'text', 3 ),
				array( 1, 2.5, 'text', 3 ),
			),
			'empty_string'    => array( '', '' ),
		);
	}

	/**
	 * Test acf_format_numerics with various values.
	 *
	 * @dataProvider data_provider_format_numerics
	 *
	 * @param mixed $value    The value to test.
	 * @param mixed $expected Expected result.
	 */
	public function test_acf_format_numerics( $value, $expected ): void {
		$this->assertEquals( $expected, acf_format_numerics( $value ) );
	}

	/**
	 * Data provider for acf_numval tests.
	 *
	 * @return array[]
	 */
	public function data_provider_numval(): array {
		return array(
			'integer'        => array( 42, 42 ),
			'float'          => array( 3.14, 3.14 ),
			'integer_string' => array( '42', 42 ),
			'float_string'   => array( '3.14', 3.14 ),
			'zero'           => array( 0, 0 ),
			'zero_float'     => array( 0.0, 0 ),
			'negative_int'   => array( -5, -5 ),
			'negative_float' => array( -3.14, -3.14 ),
			'whole_float'    => array( 5.0, 5 ),
		);
	}

	/**
	 * Test acf_numval with various values.
	 *
	 * @dataProvider data_provider_numval
	 *
	 * @param mixed     $value    The value to test.
	 * @param int|float $expected Expected result.
	 */
	public function test_acf_numval( $value, $expected ): void {
		$this->assertSame( $expected, acf_numval( $value ) );
	}

	/**
	 * Data provider for acf_idify tests.
	 *
	 * @return array[]
	 */
	public function data_provider_idify(): array {
		return array(
			'simple'          => array( 'field_name', 'field_name' ),
			'with_brackets'   => array( 'acf[field_name]', 'acf-field_name' ),
			'nested_brackets' => array( 'acf[group][field]', 'acf-group-field' ),
			'uppercase'       => array( 'FIELD_NAME', 'field_name' ),
			'mixed_case'      => array( 'Field_Name', 'field_name' ),
			'empty'           => array( '', '' ),
			'multiple_nested' => array( 'acf[a][b][c]', 'acf-a-b-c' ),
		);
	}

	/**
	 * Test acf_idify with various values.
	 *
	 * @dataProvider data_provider_idify
	 *
	 * @param string $value    The value to test.
	 * @param string $expected Expected result.
	 */
	public function test_acf_idify( string $value, string $expected ): void {
		$this->assertEquals( $expected, acf_idify( $value ) );
	}

	/**
	 * Data provider for acf_slugify tests.
	 *
	 * @return array[]
	 */
	public function data_provider_slugify(): array {
		return array(
			'simple'          => array( 'Hello World', '-', 'hello-world' ),
			'underscores'     => array( 'hello_world', '-', 'hello-world' ),
			'mixed'           => array( 'Hello World_Test', '-', 'hello-world-test' ),
			'special_chars'   => array( 'Hello! World?', '-', 'hello-world' ),
			'slashes'         => array( 'path/to/file', '-', 'path-to-file' ),
			'custom_glue'     => array( 'hello world', '_', 'hello_world' ),
			'numbers'         => array( 'Test123', '-', 'test123' ),
			'empty'           => array( '', '-', '' ),
			'uppercase'       => array( 'UPPERCASE', '-', 'uppercase' ),
			'multiple_spaces' => array( 'hello  world', '-', 'hello--world' ),
		);
	}

	/**
	 * Test acf_slugify with various values.
	 *
	 * @dataProvider data_provider_slugify
	 *
	 * @param string $value    The value to test.
	 * @param string $glue     The glue character.
	 * @param string $expected Expected result.
	 */
	public function test_acf_slugify( string $value, string $glue, string $expected ): void {
		$this->assertEquals( $expected, acf_slugify( $value, $glue ) );
	}

	/**
	 * Test acf_slugify applies filter.
	 */
	public function test_acf_slugify_applies_filter(): void {
		add_filter(
			'acf/slugify',
			function ( $slug ) {
				return 'custom_' . $slug;
			}
		);

		$result = acf_slugify( 'test' );

		$this->assertEquals( 'custom_test', $result );

		remove_all_filters( 'acf/slugify' );
	}

	/**
	 * Data provider for acf_punctify tests.
	 *
	 * @return array[]
	 */
	public function data_provider_punctify(): array {
		return array(
			'no_period'       => array( 'Hello world', 'Hello world.' ),
			'with_period'     => array( 'Hello world.', 'Hello world.' ),
			'with_whitespace' => array( 'Hello world  ', 'Hello world.' ),
			'with_html'       => array( 'Hello <strong>world</strong>', 'Hello <strong>world</strong>.' ),
			'period_in_html'  => array( 'Hello <strong>world.</strong>', 'Hello <strong>world.</strong>' ),
			'empty'           => array( '', '.' ),
			'just_period'     => array( '.', '.' ),
		);
	}

	/**
	 * Test acf_punctify with various values.
	 *
	 * @dataProvider data_provider_punctify
	 *
	 * @param string $value    The value to test.
	 * @param string $expected Expected result.
	 */
	public function test_acf_punctify( string $value, string $expected ): void {
		$this->assertEquals( $expected, acf_punctify( $value ) );
	}

	/**
	 * Test acf_did prevents duplicate events.
	 */
	public function test_acf_did_prevents_duplicates(): void {
		// First call should return false (allowing event).
		$first = acf_did( 'test_event' );
		$this->assertFalse( $first );

		// Second call should return true (preventing event).
		$second = acf_did( 'test_event' );
		$this->assertTrue( $second );
	}

	/**
	 * Test acf_did allows different events.
	 */
	public function test_acf_did_allows_different_events(): void {
		$first_a = acf_did( 'event_a' );
		$first_b = acf_did( 'event_b' );

		$this->assertFalse( $first_a );
		$this->assertFalse( $first_b );
	}

	/**
	 * Test acf_strlen counts correctly.
	 */
	public function test_acf_strlen(): void {
		$this->assertEquals( 5, acf_strlen( 'hello' ) );
	}

	/**
	 * Test acf_strlen handles line breaks.
	 */
	public function test_acf_strlen_handles_line_breaks(): void {
		// \r\n should be counted as one character.
		$string = "line1\r\nline2";
		$result = acf_strlen( $string );

		// Expected: 5 chars + 1 newline + 5 chars = 11 total characters.
		$this->assertEquals( 11, $result );
	}

	/**
	 * Test acf_strlen handles slashed input.
	 */
	public function test_acf_strlen_handles_slashed_input(): void {
		// Simulates $_POST slashing.
		$string = addslashes( "it's a test" );
		$result = acf_strlen( $string );

		// Should unslash and count: "it's a test" = 11.
		$this->assertEquals( 11, $result );
	}

	/**
	 * Test acf_strlen handles special characters.
	 */
	public function test_acf_strlen_handles_special_chars(): void {
		// Multibyte characters.
		$string = 'héllo';
		$result = acf_strlen( $string );

		$this->assertEquals( 5, $result );
	}

	/**
	 * Test acf_with_default returns value when truthy.
	 */
	public function test_acf_with_default_returns_value(): void {
		$this->assertEquals( 'value', acf_with_default( 'value', 'default' ) );
		$this->assertEquals( 123, acf_with_default( 123, 'default' ) );
		$this->assertEquals( array( 1 ), acf_with_default( array( 1 ), 'default' ) );
	}

	/**
	 * Test acf_with_default returns default when falsy.
	 *
	 * Note: This function uses PHP's loose falsy check ($value ? $value : $default).
	 * This differs from acf_is_empty() which treats 0 as NOT empty.
	 * The test documents actual behavior - 0 returns default because it's falsy.
	 */
	public function test_acf_with_default_returns_default(): void {
		$this->assertEquals( 'default', acf_with_default( '', 'default' ) );
		$this->assertEquals( 'default', acf_with_default( null, 'default' ) );
		$this->assertEquals( 'default', acf_with_default( false, 'default' ) );
		// Note: 0 is falsy in PHP, so it returns default (unlike acf_is_empty which treats 0 as NOT empty).
		$this->assertEquals( 'default', acf_with_default( 0, 'default' ) );
	}

	/**
	 * Data provider for acf_sanitize_request_args tests.
	 *
	 * @return array[]
	 */
	public function data_provider_sanitize_request_args(): array {
		return array(
			'boolean_true'  => array( true, true ),
			'boolean_false' => array( false, false ),
			'integer'       => array( 42, 42 ),
			'double'        => array( 3.14, 3.14 ),
			'string'        => array( 'hello', 'hello' ),
		);
	}

	/**
	 * Test acf_sanitize_request_args with various types.
	 *
	 * @dataProvider data_provider_sanitize_request_args
	 *
	 * @param mixed $value    The value to test.
	 * @param mixed $expected Expected result.
	 */
	public function test_acf_sanitize_request_args( $value, $expected ): void {
		$this->assertSame( $expected, acf_sanitize_request_args( $value ) );
	}

	/**
	 * Test acf_sanitize_request_args sanitizes array keys.
	 */
	public function test_acf_sanitize_request_args_sanitizes_array_keys(): void {
		$input = array(
			'<script>bad</script>' => 'value',
			'normal_key'           => 'normal_value',
		);

		$result = acf_sanitize_request_args( $input );

		// Keys with HTML should be sanitized (script tags stripped).
		$this->assertArrayNotHasKey( '<script>bad</script>', $result );
		// Normal keys should be preserved.
		$this->assertArrayHasKey( 'normal_key', $result );
	}

	/**
	 * Test acf_sanitize_request_args sanitizes string values through wp_kses.
	 */
	public function test_acf_sanitize_request_args_sanitizes_string_values(): void {
		// Script tags should be removed from string values.
		$input  = '<script>alert("xss")</script>Hello';
		$result = acf_sanitize_request_args( $input );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( '</script>', $result );
		$this->assertStringContainsString( 'Hello', $result );
	}

	/**
	 * Test acf_sanitize_request_args handles nested arrays recursively.
	 */
	public function test_acf_sanitize_request_args_recursive(): void {
		$input = array(
			'level1' => array(
				'level2' => array(
					'value' => 'safe_text',
				),
			),
		);

		$result = acf_sanitize_request_args( $input );

		$this->assertIsArray( $result );
		$this->assertIsArray( $result['level1'] );
		$this->assertIsArray( $result['level1']['level2'] );
		$this->assertEquals( 'safe_text', $result['level1']['level2']['value'] );
	}

	/**
	 * Test acf_sanitize_files_array with single file.
	 */
	public function test_acf_sanitize_files_array_single_file(): void {
		$file = array(
			'name'     => 'test.jpg',
			'tmp_name' => '/tmp/phpXXXXXX',
			'type'     => 'image/jpeg',
			'size'     => 1024,
			'error'    => 0,
		);

		$result = acf_sanitize_files_array( $file );

		$this->assertEquals( 'test.jpg', $result['name'] );
		$this->assertEquals( '/tmp/phpXXXXXX', $result['tmp_name'] );
		$this->assertEquals( 'image/jpeg', $result['type'] );
		$this->assertEquals( 1024, $result['size'] );
		$this->assertEquals( 0, $result['error'] );
	}

	/**
	 * Test acf_sanitize_files_array returns defaults for empty name.
	 */
	public function test_acf_sanitize_files_array_empty_name(): void {
		$file = array(
			'name'     => '',
			'tmp_name' => '/tmp/phpXXXXXX',
			'type'     => 'image/jpeg',
			'size'     => 1024,
			'error'    => 0,
		);

		$result = acf_sanitize_files_array( $file );

		$this->assertEquals( '', $result['name'] );
		$this->assertEquals( '', $result['tmp_name'] );
		$this->assertEquals( '', $result['type'] );
		$this->assertEquals( 0, $result['size'] );
		$this->assertEquals( '', $result['error'] );
	}

	/**
	 * Test acf_sanitize_files_array with multiple files.
	 */
	public function test_acf_sanitize_files_array_multiple_files(): void {
		$files = array(
			'name'     => array( 'file1.jpg', 'file2.png' ),
			'tmp_name' => array( '/tmp/php1', '/tmp/php2' ),
			'type'     => array( 'image/jpeg', 'image/png' ),
			'size'     => array( 1024, 2048 ),
			'error'    => array( 0, 0 ),
		);

		$result = acf_sanitize_files_array( $files );

		$this->assertIsArray( $result['name'] );
		$this->assertEquals( 'file1.jpg', $result['name'][0] );
		$this->assertEquals( 'file2.png', $result['name'][1] );
	}

	/**
	 * Test acf_sanitize_files_value_array with invalid function.
	 *
	 * When an invalid sanitize function is provided, the function should
	 * return the original array unmodified as a safety measure.
	 */
	public function test_acf_sanitize_files_value_array_invalid_function(): void {
		$array  = array( 'value1', 'value2' );
		$result = acf_sanitize_files_value_array( $array, 'nonexistent_function_' . uniqid() );

		// Should return original array unmodified if function doesn't exist.
		$this->assertSame( $array, $result );
	}

	/**
	 * Test acf_sanitize_files_value_array with valid function on array.
	 */
	public function test_acf_sanitize_files_value_array_valid_function(): void {
		$array  = array( 'hello', 'world' );
		$result = acf_sanitize_files_value_array( $array, 'strtoupper' );

		// Should apply strtoupper to each element.
		$this->assertEquals( array( 'HELLO', 'WORLD' ), $result );
	}

	/**
	 * Test acf_sanitize_files_value_array with scalar value.
	 */
	public function test_acf_sanitize_files_value_array_scalar(): void {
		$result = acf_sanitize_files_value_array( 'test', 'strtoupper' );

		$this->assertEquals( 'TEST', $result );
	}

	/**
	 * Test acf_sanitize_files_value_array recursively.
	 */
	public function test_acf_sanitize_files_value_array_recursive(): void {
		$array = array(
			'level1' => array(
				'level2' => 'value',
			),
		);

		$result = acf_sanitize_files_value_array( $array, 'strtoupper' );

		$this->assertEquals( 'VALUE', $result['level1']['level2'] );
	}

	/**
	 * Test acf_maybe_unserialize with serialized data.
	 */
	public function test_acf_maybe_unserialize_with_serialized(): void {
		$original = array( 'key' => 'value' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Testing unserialization.
		$serialized = serialize( $original );

		$result = acf_maybe_unserialize( $serialized );

		$this->assertEquals( $original, $result );
	}

	/**
	 * Test acf_maybe_unserialize with non-serialized data.
	 */
	public function test_acf_maybe_unserialize_with_non_serialized(): void {
		$data = 'not serialized';

		$result = acf_maybe_unserialize( $data );

		$this->assertEquals( $data, $result );
	}

	/**
	 * Test acf_maybe_unserialize blocks class instantiation.
	 */
	public function test_acf_maybe_unserialize_blocks_classes(): void {
		$object     = new stdClass();
		$object->id = 123;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Testing unserialization security.
		$serialized = serialize( $object );

		$result = acf_maybe_unserialize( $serialized );

		// Should return __PHP_Incomplete_Class instead of stdClass.
		$this->assertNotInstanceOf( stdClass::class, $result );
	}

	/**
	 * Test acf_is_beta returns correct value based on ACF_VERSION constant.
	 *
	 * This test verifies the function correctly checks for a dash in the version string.
	 */
	public function test_acf_is_beta_checks_version_for_dash(): void {
		// This function checks if ACF_VERSION contains a dash.
		// We verify the logic by checking the constant directly and comparing.
		$result = acf_is_beta();

		$this->assertIsBool( $result );

		// Verify the function's logic matches checking the constant directly.
		$expected = defined( 'ACF_VERSION' ) && strpos( ACF_VERSION, '-' ) !== false;
		$this->assertSame( $expected, $result, 'acf_is_beta() should return true if ACF_VERSION contains a dash' );
	}

	/**
	 * Test acf_get_current_url returns empty in CLI.
	 */
	public function test_acf_get_current_url_in_cli(): void {
		// In CLI context, SERVER vars are not set.
		unset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] );

		$result = acf_get_current_url();

		$this->assertEquals( '', $result );
	}

	/**
	 * Test acf_get_current_url with server vars.
	 */
	public function test_acf_get_current_url_with_server_vars(): void {
		$_SERVER['HTTP_HOST']   = 'example.com';
		$_SERVER['REQUEST_URI'] = '/page?query=value';

		$result = acf_get_current_url();

		$this->assertStringContainsString( 'example.com', $result );
		$this->assertStringContainsString( '/page', $result );

		unset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] );
	}
}
