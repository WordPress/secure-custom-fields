<?php
/**
 * Tests for acf_decode_block_query_arg().
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_Blocks_Query_Decoding
 *
 * Verifies acf_decode_block_query_arg() — the helper used by
 * acf_ajax_fetch_block() to normalize the `query` ajax arg, which
 * may arrive as either an array or a JSON-encoded string. Without
 * this normalization PHP 8.4 raises a fatal TypeError when offsets
 * are accessed on a string.
 *
 * @group blocks
 */
class Test_Blocks_Query_Decoding extends BaseTestCase {

	/**
	 * A JSON object string is decoded to an array.
	 */
	public function test_json_object_string_is_decoded_to_array() {
		$result = acf_decode_block_query_arg( '{"post_type":"post","posts_per_page":3}' );

		$this->assertIsArray( $result );
		$this->assertSame( 'post', $result['post_type'] );
		$this->assertSame( 3, $result['posts_per_page'] );
	}

	/**
	 * A slashed JSON string (as it would arrive in $_REQUEST) is decoded.
	 */
	public function test_slashed_json_string_is_decoded() {
		$result = acf_decode_block_query_arg( wp_slash( '{"preview":true,"form":false}' ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['preview'] );
		$this->assertFalse( $result['form'] );
	}

	/**
	 * A nested JSON object matching real query keys decodes correctly.
	 */
	public function test_nested_json_object_decodes() {
		$json   = '{"preview":true,"form":true,"validate":false,"meta":{"foo":"bar"}}';
		$result = acf_decode_block_query_arg( $json );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['preview'] );
		$this->assertSame( 'bar', $result['meta']['foo'] );
	}

	/**
	 * An array is returned unchanged.
	 */
	public function test_array_is_returned_unchanged() {
		$query = array(
			'post_type'      => 'page',
			'posts_per_page' => 5,
		);

		$result = acf_decode_block_query_arg( $query );

		$this->assertSame( $query, $result );
	}

	/**
	 * Invalid JSON falls back to an empty array.
	 */
	public function test_invalid_json_falls_back_to_empty_array() {
		$this->assertSame( array(), acf_decode_block_query_arg( 'not valid json {{{' ) );
	}

	/**
	 * An empty string falls back to an empty array.
	 */
	public function test_empty_string_falls_back_to_empty_array() {
		$this->assertSame( array(), acf_decode_block_query_arg( '' ) );
	}

	/**
	 * A JSON string encoding a scalar (not an array/object) falls back to an empty array.
	 *
	 * @dataProvider provide_json_scalars
	 *
	 * @param string $json A JSON-encoded scalar.
	 */
	public function test_json_scalar_falls_back_to_empty_array( $json ) {
		$this->assertSame( array(), acf_decode_block_query_arg( $json ) );
	}

	/**
	 * Data provider for JSON scalars.
	 *
	 * @return array
	 */
	public function provide_json_scalars() {
		return array(
			'string' => array( '"just a string"' ),
			'true'   => array( 'true' ),
			'false'  => array( 'false' ),
			'null'   => array( 'null' ),
			'number' => array( '42' ),
		);
	}

	/**
	 * Non-string, non-array values fall back to an empty array.
	 *
	 * @dataProvider provide_non_string_non_array
	 *
	 * @param mixed $value Input value.
	 */
	public function test_non_string_non_array_falls_back_to_empty_array( $value ) {
		$this->assertSame( array(), acf_decode_block_query_arg( $value ) );
	}

	/**
	 * Data provider for non-string, non-array inputs.
	 *
	 * @return array
	 */
	public function provide_non_string_non_array() {
		return array(
			'null'    => array( null ),
			'integer' => array( 42 ),
			'bool'    => array( true ),
			'object'  => array( new stdClass() ),
		);
	}
}
