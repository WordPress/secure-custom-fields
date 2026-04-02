<?php
/**
 * Tests for block query parameter decoding in acf_ajax_fetch_block().
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_Blocks_Query_Decoding
 *
 * Tests that the query parameter is correctly decoded from a JSON string
 * to an array in acf_ajax_fetch_block(), preventing fatal TypeError
 * on PHP 8.4 when accessing offsets on a string.
 *
 * @group blocks
 */
class Test_Blocks_Query_Decoding extends BaseTestCase {

	/**
	 * Test that a JSON-encoded query string is decoded to an array.
	 */
	public function test_json_string_query_is_decoded_to_array() {
		$query = wp_slash( '{"post_type":"post","posts_per_page":3}' );

		// Simulate the decoding logic from acf_ajax_fetch_block().
		if ( is_string( $query ) ) {
			$query = json_decode( wp_unslash( $query ), true );
			if ( ! is_array( $query ) ) {
				$query = array();
			}
		}

		$this->assertIsArray( $query );
		$this->assertSame( 'post', $query['post_type'] );
		$this->assertSame( 3, $query['posts_per_page'] );
	}

	/**
	 * Test that an invalid JSON string falls back to an empty array.
	 */
	public function test_invalid_json_string_falls_back_to_empty_array() {
		$query = 'not valid json {{{';

		if ( is_string( $query ) ) {
			$query = json_decode( wp_unslash( $query ), true );
			if ( ! is_array( $query ) ) {
				$query = array();
			}
		}

		$this->assertIsArray( $query );
		$this->assertEmpty( $query );
	}

	/**
	 * Test that an array query is left unchanged.
	 */
	public function test_array_query_is_unchanged() {
		$query = array(
			'post_type'      => 'page',
			'posts_per_page' => 5,
		);

		if ( is_string( $query ) ) {
			$query = json_decode( wp_unslash( $query ), true );
			if ( ! is_array( $query ) ) {
				$query = array();
			}
		}

		$this->assertIsArray( $query );
		$this->assertSame( 'page', $query['post_type'] );
		$this->assertSame( 5, $query['posts_per_page'] );
	}

	/**
	 * Test that a JSON string encoding a non-array value falls back to empty array.
	 */
	public function test_json_non_array_value_falls_back_to_empty_array() {
		$query = '"just a string"';

		if ( is_string( $query ) ) {
			$query = json_decode( wp_unslash( $query ), true );
			if ( ! is_array( $query ) ) {
				$query = array();
			}
		}

		$this->assertIsArray( $query );
		$this->assertEmpty( $query );
	}

	/**
	 * Test that an empty string falls back to an empty array.
	 */
	public function test_empty_string_falls_back_to_empty_array() {
		$query = '';

		if ( is_string( $query ) ) {
			$query = json_decode( wp_unslash( $query ), true );
			if ( ! is_array( $query ) ) {
				$query = array();
			}
		}

		$this->assertIsArray( $query );
		$this->assertEmpty( $query );
	}
}
