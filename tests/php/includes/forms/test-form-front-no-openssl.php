<?php
/**
 * Tests front-end form authorization when OpenSSL is unavailable.
 *
 * @package wordpress/secure-custom-fields
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_Form_Front_No_Openssl
 *
 * These tests run the form code in a child PHP process because disabled
 * functions can only be configured before PHP starts.
 *
 * @group forms
 * @group security
 */
class Test_Form_Front_No_Openssl extends TestCase {

	/**
	 * Functions disabled in the child process.
	 *
	 * Keep this list in sync with every OpenSSL primitive used by the legacy
	 * acf_encrypt()/acf_decrypt() implementation.
	 *
	 * @var string
	 */
	private const DISABLED_FUNCTIONS = 'openssl_encrypt,openssl_decrypt,openssl_random_pseudo_bytes,openssl_cipher_iv_length';

	/**
	 * An unchanged form still works when OpenSSL is disabled.
	 *
	 * @return void
	 */
	public function test_valid_rendered_form_still_submits_without_openssl(): void {
		$result = $this->run_fixture( 'valid' );

		$this->assert_disabled_functions( $result );
		$this->assertTrue( $result['handler_returned'] );
		$this->assertSame( 1, $result['validation_action_calls'] );
		$this->assertSame( 1, $result['field_validator_calls'] );
		$this->assertSame(
			array(
				'pre_submit_form' => 1,
				'pre_save_post'   => 1,
				'save_post'       => 1,
				'submit_form'     => 1,
			),
			$result['form_hook_calls']
		);
		$this->assertSame( 'valid submitted value', $result['primary_value'] );
		$this->assertSame( 'original victim value', $result['victim_value'] );
		$this->assertSame( array(), $result['redirects'] );
	}

	/**
	 * The handler rejects changes to rendered security data before running
	 * hooks, saving values, or redirecting.
	 *
	 * @dataProvider tampering_scenario_provider
	 *
	 * @param string $scenario Fixture scenario.
	 * @return void
	 */
	public function test_tampering_is_rejected_before_processing_without_openssl( $scenario ): void {
		$result = $this->run_fixture( $scenario );

		$this->assert_disabled_functions( $result );
		$this->assertTrue( $result['handler_returned'], 'The rejected request should return normally.' );
		$this->assertFalse( $result['handler_result'], 'The rejected request should return false.' );
		$this->assertSame( 0, $result['validation_action_calls'], 'Form validation must not run.' );
		$this->assertSame( 0, $result['field_validator_calls'], 'Field validators must not run.' );
		$this->assertSame(
			array(
				'pre_submit_form' => 0,
				'pre_save_post'   => 0,
				'save_post'       => 0,
				'submit_form'     => 0,
			),
			$result['form_hook_calls'],
			'Submission hooks must not run.'
		);
		$this->assertSame(
			$result['submitted_raw_value'],
			$result['raw_value_after'],
			'KSES must not touch field data from a rejected request.'
		);
		$this->assertSame( 'original primary value', $result['primary_value'] );
		$this->assertSame( 'original victim value', $result['victim_value'] );
		$this->assertSame( 'Original primary title', $result['primary_title'] );
		$this->assertSame( 'Original victim title', $result['victim_title'] );
		$this->assertSame( 0, $result['new_post_insertions'] );
		$this->assertSame( array(), $result['redirects'] );
		$this->assertFalse( $result['global_form_set'] );
	}

	/**
	 * Cases that change the readable fallback payload.
	 *
	 * @return array<string,array{string}>
	 */
	public function tampering_scenario_provider(): array {
		return array(
			'_acf_form payload' => array( 'form' ),
			'return URL'        => array( 'return' ),
			'target post'       => array( 'target' ),
			'new_post settings' => array( 'new_post' ),
			'form flags'        => array( 'flags' ),
			'form metadata'     => array( 'metadata' ),
		);
	}

	/**
	 * Runs one fixture scenario in a separate PHP process.
	 *
	 * @param string $scenario Fixture scenario.
	 * @return array
	 */
	private function run_fixture( $scenario ): array {
		if ( ! function_exists( 'proc_open' ) ) {
			$this->markTestSkipped( 'proc_open() is required for the no-OpenSSL child-process tests.' );
		}

		$fixture = dirname( __DIR__, 2 ) . '/fixtures/form-front-no-openssl.php';
		$command = array(
			PHP_BINARY,
			'-d',
			'disable_functions=' . self::DISABLED_FUNCTIONS,
			$fixture,
			$scenario,
		);
		$spec    = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( $command, $spec, $pipes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- A separate PHP process is required to disable functions.
		$this->assertIsResource( $process, 'Unable to start the no-OpenSSL fixture process.' );

		fclose( $pipes[0] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing test process pipe.
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing test process pipe.
		fclose( $pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing test process pipe.

		$exit_code = proc_close( $process );
		$marker    = 'SCF_NO_OPENSSL_RESULT=';
		$position  = strrpos( $stdout, $marker );

		$this->assertSame(
			0,
			$exit_code,
			"Fixture failed for scenario {$scenario}.\nSTDOUT:\n{$stdout}\nSTDERR:\n{$stderr}"
		);
		$this->assertNotFalse(
			$position,
			"Fixture emitted no result for scenario {$scenario}.\nSTDOUT:\n{$stdout}\nSTDERR:\n{$stderr}"
		);

		$json   = trim( substr( $stdout, $position + strlen( $marker ) ) );
		$result = json_decode( $json, true );

		$this->assertIsArray(
			$result,
			"Fixture emitted invalid JSON for scenario {$scenario}.\nJSON:\n{$json}\nSTDERR:\n{$stderr}"
		);
		$this->assertArrayNotHasKey(
			'fixture_error',
			$result,
			isset( $result['fixture_error'] ) ? $result['fixture_error'] : 'Fixture setup failed.'
		);

		return $result;
	}

	/**
	 * Checks that the child process ran without OpenSSL.
	 *
	 * @param array $result Fixture result.
	 * @return void
	 */
	private function assert_disabled_functions( array $result ): void {
		$this->assertSame(
			array(
				'openssl_encrypt'             => false,
				'openssl_decrypt'             => false,
				'openssl_random_pseudo_bytes' => false,
				'openssl_cipher_iv_length'    => false,
			),
			$result['openssl_functions_exist']
		);
	}
}
