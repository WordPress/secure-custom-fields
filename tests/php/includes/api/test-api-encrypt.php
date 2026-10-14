<?php
/**
 * Tests for acf_encrypt() and acf_decrypt().
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Tests authenticated encryption and context separation.
 *
 * @group api
 * @group security
 *
 * @covers ::acf_encrypt
 * @covers ::acf_decrypt
 */
class Test_API_Encrypt extends BaseTestCase {

	/**
	 * Values encrypted without a context round-trip, as before.
	 */
	public function test_round_trip_without_context() {
		$token = acf_encrypt( 'secret value' );

		$this->assertIsString( $token );
		$this->assertSame( 'secret value', acf_decrypt( $token ) );
	}

	/**
	 * Values encrypted with a context only decrypt with the same context.
	 */
	public function test_context_must_match() {
		$token = acf_encrypt( 'secret value', 'form' );

		$this->assertSame( 'secret value', acf_decrypt( $token, 'form' ) );
		$this->assertFalse( acf_decrypt( $token, 'other' ) );
		$this->assertFalse( acf_decrypt( $token ) );
	}

	/**
	 * Values encrypted without a context cannot be decrypted with one.
	 */
	public function test_legacy_token_rejected_with_context() {
		$this->assertFalse( acf_decrypt( acf_encrypt( 'secret value' ), 'form' ) );
	}

	/**
	 * Tampered values are rejected, with and without a context.
	 */
	public function test_tampered_tokens_are_rejected() {
		foreach ( array( '', 'form' ) as $context ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Inspecting our own token.
			$raw    = base64_decode( acf_encrypt( 'secret value', $context ) );
			$raw[0] = 'A' === $raw[0] ? 'B' : 'A';

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Rebuilding a tampered token.
			$this->assertFalse( acf_decrypt( base64_encode( $raw ), $context ) );
		}
	}
}
