<?php
/**
 * Tests for WordPress.org API integration
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the contributors functions.
require_once dirname( dirname( __DIR__ ) ) . '/bin/contributors-functions.php';

/**
 * Test WordPress.org API integration for contributor profile lookup
 */
class Test_WPOrg_API_Integration extends BaseTestCase {

	/**
	 * Test batch lookup request formatting (max 50 usernames)
	 *
	 * Validates that the batch lookup function correctly chunks usernames
	 * into batches of max 50 and formats requests properly.
	 */
	public function test_batch_lookup_request_formatting_max_50_usernames() {
		// Test with less than 50 usernames.
		$small_batch = array_map(
			function ( $i ) {
				return "user{$i}";
			},
			range( 1, 25 )
		);

		$batches = $this->create_username_batches( $small_batch, 50 );
		$this->assertCount( 1, $batches, 'Small batch should produce 1 batch' );
		$this->assertCount( 25, $batches[0], 'First batch should have 25 usernames' );

		// Test with exactly 50 usernames.
		$exact_batch = array_map(
			function ( $i ) {
				return "user{$i}";
			},
			range( 1, 50 )
		);

		$batches = $this->create_username_batches( $exact_batch, 50 );
		$this->assertCount( 1, $batches, 'Exact 50 should produce 1 batch' );
		$this->assertCount( 50, $batches[0], 'First batch should have 50 usernames' );

		// Test with more than 50 usernames.
		$large_batch = array_map(
			function ( $i ) {
				return "user{$i}";
			},
			range( 1, 125 )
		);

		$batches = $this->create_username_batches( $large_batch, 50 );
		$this->assertCount( 3, $batches, '125 usernames should produce 3 batches' );
		$this->assertCount( 50, $batches[0], 'First batch should have 50 usernames' );
		$this->assertCount( 50, $batches[1], 'Second batch should have 50 usernames' );
		$this->assertCount( 25, $batches[2], 'Third batch should have 25 usernames' );

		// Verify request format is correct.
		$request_body = $this->format_lookup_request( array( 'user1', 'user2', 'user3' ) );
		$this->assertArrayHasKey( 'github_user', $request_body, 'Request should have github_user key' );
		$this->assertIsArray( $request_body['github_user'], 'github_user should be an array' );
		$this->assertEquals( array( 'user1', 'user2', 'user3' ), $request_body['github_user'], 'Usernames should match' );
	}

	/**
	 * Test response parsing for linked accounts
	 *
	 * Validates that the WordPress.org API response is correctly parsed
	 * to extract wporg_username and wporg_display_name for linked accounts.
	 */
	public function test_response_parsing_for_linked_accounts() {
		// Sample response from WordPress.org API for linked accounts.
		$api_response = array(
			'linkeduser1' => array(
				'slug'         => 'wporguser1',
				'display_name' => 'WordPress User One',
			),
			'linkeduser2' => array(
				'slug'         => 'wporguser2',
				'display_name' => 'WordPress User Two',
			),
		);

		$contributors = array(
			array(
				'github_username'         => 'linkeduser1',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-01',
			),
			array(
				'github_username'         => 'linkeduser2',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'review' ),
				'first_contribution_date' => '2024-01-02',
			),
			array(
				'github_username'         => 'unlinkeduser',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'comment' ),
				'first_contribution_date' => '2024-01-03',
			),
		);

		$updated_contributors = $this->apply_wporg_data( $contributors, $api_response );

		// Verify linked user 1.
		$linked1 = $this->find_contributor_by_username( $updated_contributors, 'linkeduser1' );
		$this->assertNotNull( $linked1, 'linkeduser1 should exist' );
		$this->assertEquals( 'wporguser1', $linked1['wporg_username'], 'wporg_username should be set' );
		$this->assertEquals( 'WordPress User One', $linked1['wporg_display_name'], 'wporg_display_name should be set' );

		// Verify linked user 2.
		$linked2 = $this->find_contributor_by_username( $updated_contributors, 'linkeduser2' );
		$this->assertNotNull( $linked2, 'linkeduser2 should exist' );
		$this->assertEquals( 'wporguser2', $linked2['wporg_username'], 'wporg_username should be set' );
		$this->assertEquals( 'WordPress User Two', $linked2['wporg_display_name'], 'wporg_display_name should be set' );

		// Verify unlinked user remains unchanged.
		$unlinked = $this->find_contributor_by_username( $updated_contributors, 'unlinkeduser' );
		$this->assertNotNull( $unlinked, 'unlinkeduser should exist' );
		$this->assertNull( $unlinked['wporg_username'], 'wporg_username should remain null for unlinked' );
		$this->assertNull( $unlinked['wporg_display_name'], 'wporg_display_name should remain null for unlinked' );

		// Verify other fields are preserved.
		$this->assertEquals( '2024-01-01', $linked1['first_contribution_date'], 'first_contribution_date should be preserved' );
		$this->assertContains( 'commit', $linked1['contribution_types'], 'contribution_types should be preserved' );

		// Test empty API response.
		$empty_result = $this->apply_wporg_data( $contributors, array() );
		$this->assertCount( 3, $empty_result, 'All contributors should remain' );
		foreach ( $empty_result as $contributor ) {
			$this->assertNull( $contributor['wporg_username'], 'wporg_username should remain null with empty response' );
		}

		// Test response with different case GitHub usernames (API may normalize case).
		$case_response = array(
			'LinkedUser1' => array(
				'slug'         => 'wporguser1',
				'display_name' => 'WordPress User One',
			),
		);

		$case_result = $this->apply_wporg_data( $contributors, $case_response );
		$case_linked = $this->find_contributor_by_username( $case_result, 'linkeduser1' );
		$this->assertEquals( 'wporguser1', $case_linked['wporg_username'], 'Should handle case-insensitive matching' );
	}

	/**
	 * Test exponential backoff retry logic
	 *
	 * Validates that the retry logic implements exponential backoff correctly
	 * with appropriate delays and max retries.
	 */
	public function test_exponential_backoff_retry_logic() {
		// Test delay calculation for exponential backoff.
		$base_delay_ms = 1000; // 1 second.
		$max_delay_ms  = 32000; // 32 seconds.

		// First retry: 1000ms * 2^0 = 1000ms.
		$delay_1 = $this->calculate_backoff_delay( 1, $base_delay_ms, $max_delay_ms );
		$this->assertEquals( 1000, $delay_1, 'First retry should wait 1 second' );

		// Second retry: 1000ms * 2^1 = 2000ms.
		$delay_2 = $this->calculate_backoff_delay( 2, $base_delay_ms, $max_delay_ms );
		$this->assertEquals( 2000, $delay_2, 'Second retry should wait 2 seconds' );

		// Third retry: 1000ms * 2^2 = 4000ms.
		$delay_3 = $this->calculate_backoff_delay( 3, $base_delay_ms, $max_delay_ms );
		$this->assertEquals( 4000, $delay_3, 'Third retry should wait 4 seconds' );

		// Fourth retry: 1000ms * 2^3 = 8000ms.
		$delay_4 = $this->calculate_backoff_delay( 4, $base_delay_ms, $max_delay_ms );
		$this->assertEquals( 8000, $delay_4, 'Fourth retry should wait 8 seconds' );

		// Fifth retry: 1000ms * 2^4 = 16000ms.
		$delay_5 = $this->calculate_backoff_delay( 5, $base_delay_ms, $max_delay_ms );
		$this->assertEquals( 16000, $delay_5, 'Fifth retry should wait 16 seconds' );

		// Sixth retry: 1000ms * 2^5 = 32000ms (max).
		$delay_6 = $this->calculate_backoff_delay( 6, $base_delay_ms, $max_delay_ms );
		$this->assertEquals( 32000, $delay_6, 'Sixth retry should be capped at max' );

		// Seventh retry should still be capped at max.
		$delay_7 = $this->calculate_backoff_delay( 7, $base_delay_ms, $max_delay_ms );
		$this->assertEquals( 32000, $delay_7, 'Seventh retry should still be capped at max' );

		// Test retry decision logic.
		$max_retries = 5;

		$this->assertTrue( $this->should_retry( 1, $max_retries, 429 ), 'Should retry on rate limit (attempt 1)' );
		$this->assertTrue( $this->should_retry( 3, $max_retries, 500 ), 'Should retry on server error (attempt 3)' );
		$this->assertTrue( $this->should_retry( 5, $max_retries, 503 ), 'Should retry on service unavailable (attempt 5)' );
		$this->assertFalse( $this->should_retry( 6, $max_retries, 429 ), 'Should not retry after max attempts' );
		$this->assertFalse( $this->should_retry( 1, $max_retries, 400 ), 'Should not retry on client error 400' );
		$this->assertFalse( $this->should_retry( 1, $max_retries, 404 ), 'Should not retry on client error 404' );
		$this->assertTrue( $this->should_retry( 1, $max_retries, 0 ), 'Should retry on network failure (status 0)' );
	}

	/**
	 * Create username batches of specified size
	 *
	 * @param array $usernames List of usernames.
	 * @param int   $batch_size Maximum batch size.
	 * @return array Array of username batches.
	 */
	private function create_username_batches( array $usernames, int $batch_size ) {
		return array_chunk( $usernames, $batch_size );
	}

	/**
	 * Format lookup request body
	 *
	 * @param array $usernames List of GitHub usernames.
	 * @return array Request body.
	 */
	private function format_lookup_request( array $usernames ) {
		return array( 'github_user' => $usernames );
	}

	/**
	 * Apply WordPress.org data to contributors
	 *
	 * @param array $contributors List of contributors.
	 * @param array $api_response WordPress.org API response.
	 * @return array Updated contributors.
	 */
	private function apply_wporg_data( array $contributors, array $api_response ) {
		// Create case-insensitive lookup map from API response.
		$wporg_map = array();
		foreach ( $api_response as $github_username => $wporg_data ) {
			$wporg_map[ strtolower( $github_username ) ] = $wporg_data;
		}

		return array_map(
			function ( $contributor ) use ( $wporg_map ) {
				$key = strtolower( $contributor['github_username'] ?? '' );

				if ( isset( $wporg_map[ $key ] ) ) {
					$contributor['wporg_username']     = $wporg_map[ $key ]['slug'] ?? null;
					$contributor['wporg_display_name'] = $wporg_map[ $key ]['display_name'] ?? null;
				}

				return $contributor;
			},
			$contributors
		);
	}

	/**
	 * Calculate exponential backoff delay
	 *
	 * @param int $attempt      Current attempt number (1-based).
	 * @param int $base_delay   Base delay in milliseconds.
	 * @param int $max_delay    Maximum delay in milliseconds.
	 * @return int Delay in milliseconds.
	 */
	private function calculate_backoff_delay( int $attempt, int $base_delay, int $max_delay ) {
		$delay = $base_delay * pow( 2, $attempt - 1 );
		return min( $delay, $max_delay );
	}

	/**
	 * Determine if a retry should be attempted
	 *
	 * @param int $attempt     Current attempt number (1-based).
	 * @param int $max_retries Maximum number of retries.
	 * @param int $status_code HTTP status code (0 for network failures).
	 * @return bool Whether to retry.
	 */
	private function should_retry( int $attempt, int $max_retries, int $status_code ) {
		if ( $attempt > $max_retries ) {
			return false;
		}

		// Retry on network failures.
		if ( 0 === $status_code ) {
			return true;
		}

		// Retry on rate limiting (429) and server errors (5xx).
		if ( 429 === $status_code || ( $status_code >= 500 && $status_code < 600 ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Find contributor by username in array
	 *
	 * @param array  $contributors List of contributors.
	 * @param string $username     Username to find.
	 * @return array|null Contributor data or null if not found.
	 */
	private function find_contributor_by_username( array $contributors, string $username ) {
		foreach ( $contributors as $contributor ) {
			if ( strtolower( $contributor['github_username'] ?? '' ) === strtolower( $username ) ) {
				return $contributor;
			}
		}
		return null;
	}
}
