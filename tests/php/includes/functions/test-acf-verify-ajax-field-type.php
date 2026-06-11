<?php
/**
 * Tests for the acf_verify_ajax() field type validation and acf_decrypt() hardening.
 *
 * Covers the upstream 6.8.4 security backport: AJAX field handlers validate
 * that the request nonce was created for the expected field type, and
 * acf_decrypt() treats malformed payloads as a decrypt failure.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

/**
 * Class Test_ACF_Verify_Ajax_Field_Type
 *
 * @group ajax
 * @group security
 */
class Test_ACF_Verify_Ajax_Field_Type extends BaseTestCase {

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		acf_add_local_field(
			array(
				'key'  => 'field_test_select_type',
				'name' => 'test_select_type',
				'type' => 'select',
			)
		);

		acf_add_local_field(
			array(
				'key'  => 'field_test_oembed_type',
				'name' => 'test_oembed_type',
				'type' => 'oembed',
			)
		);

		$_REQUEST = array();
		$_POST    = array();
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		acf_remove_local_field( 'field_test_select_type' );
		acf_remove_local_field( 'field_test_oembed_type' );

		$_REQUEST = array();
		$_POST    = array();

		parent::tear_down();
	}

	/**
	 * A typed field nonce passes when the resolved field matches the expected type.
	 *
	 * @return void
	 */
	public function test_verify_ajax_accepts_matching_field_type() {
		$nonce = wp_create_nonce( 'acf_field_select_field_test_select_type' );

		$this->assertTrue(
			acf_verify_ajax( $nonce, 'field_test_select_type', true, 'select' )
		);
	}

	/**
	 * A nonce minted for one field type is rejected by a handler expecting another.
	 *
	 * @return void
	 */
	public function test_verify_ajax_rejects_mismatched_field_type() {
		// Valid typed nonce for the select field, replayed against a handler
		// that expects an oembed field.
		$nonce = wp_create_nonce( 'acf_field_select_field_test_select_type' );

		$this->assertFalse(
			acf_verify_ajax( $nonce, 'field_test_select_type', true, 'oembed' )
		);
	}

	/**
	 * The expected type is compared against the resolved field, not the action string.
	 *
	 * @return void
	 */
	public function test_verify_ajax_rejects_field_of_other_type_with_own_valid_nonce() {
		// Even a valid oembed-typed nonce for an oembed field must be rejected
		// when the handler expects a select field.
		$nonce = wp_create_nonce( 'acf_field_oembed_field_test_oembed_type' );

		$this->assertFalse(
			acf_verify_ajax( $nonce, 'field_test_oembed_type', true, 'select' )
		);
	}

	/**
	 * Omitting the expected type keeps the previous behavior.
	 *
	 * @return void
	 */
	public function test_verify_ajax_without_expected_type_is_backward_compatible() {
		$nonce = wp_create_nonce( 'acf_field_select_field_test_select_type' );

		$this->assertTrue(
			acf_verify_ajax( $nonce, 'field_test_select_type', true )
		);
	}

	/**
	 * An invalid nonce still fails regardless of the expected type matching.
	 *
	 * @return void
	 */
	public function test_verify_ajax_rejects_invalid_nonce_with_matching_type() {
		$this->assertFalse(
			acf_verify_ajax( 'not-a-valid-nonce', 'field_test_select_type', true, 'select' )
		);
	}

	/**
	 * The acf_decrypt() function round-trips acf_encrypt() output.
	 *
	 * @return void
	 */
	public function test_decrypt_round_trips_encrypted_data() {
		$data = '{"post_id":123,"fields":["field_abc"]}';

		$this->assertSame( $data, acf_decrypt( acf_encrypt( $data ) ) );
	}

	/**
	 * The acf_decrypt() function returns false for input that is not valid base64.
	 *
	 * @return void
	 */
	public function test_decrypt_returns_false_for_non_base64_input() {
		$this->assertFalse( acf_decrypt( '!!! not base64 !!!' ) );
	}

	/**
	 * The acf_decrypt() function returns false when the payload lacks the data::iv separator.
	 *
	 * @return void
	 */
	public function test_decrypt_returns_false_for_payload_without_separator() {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Building a malformed test fixture.
		$payload = base64_encode( 'payload-without-separator' );

		$this->assertFalse( acf_decrypt( $payload ) );
	}

	/**
	 * The acf_decrypt() function returns false for an empty payload.
	 *
	 * @return void
	 */
	public function test_decrypt_returns_false_for_empty_payload() {
		$this->assertFalse( acf_decrypt( '' ) );
	}
}
