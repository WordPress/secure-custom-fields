<?php
/**
 * Tests for contributor rate limit handling functions
 *
 * Tests the parse_rate_limit_headers() and calculate_smart_backoff()
 * functions used for intelligent API rate limit handling.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the contributors functions.
require_once dirname( dirname( __DIR__ ) ) . '/bin/contributors-functions.php';

use function WordPress\SCF\Contributors\parse_rate_limit_headers;
use function WordPress\SCF\Contributors\calculate_smart_backoff;
use function WordPress\SCF\Contributors\calculate_backoff_delay;

use const WordPress\SCF\Contributors\WPORG_MAX_DELAY;
use const WordPress\SCF\Contributors\WPORG_BASE_DELAY;

/**
 * Test rate limit header parsing and smart backoff calculations
 */
class Test_Contributors_Rate_Limit extends BaseTestCase {

	/**
	 * Test parsing rate limit headers from HTTP response headers
	 *
	 * Verifies that parse_rate_limit_headers() correctly extracts
	 * X-RateLimit-Remaining, X-RateLimit-Reset, and Retry-After headers.
	 */
	public function test_parse_rate_limit_headers_extracts_all_headers() {
		$http_response_header = array(
			'HTTP/1.1 200 OK',
			'Content-Type: application/json',
			'X-RateLimit-Remaining: 42',
			'X-RateLimit-Reset: 1704067200',
			'Retry-After: 60',
		);

		$result = parse_rate_limit_headers( $http_response_header );

		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertEquals( 42, $result['remaining'], 'Should extract remaining count' );
		$this->assertEquals( 1704067200, $result['reset'], 'Should extract reset timestamp' );
		$this->assertEquals( 60, $result['retry_after'], 'Should extract retry-after seconds' );
	}

	/**
	 * Test parsing headers when only some rate limit headers are present
	 *
	 * Verifies that missing headers result in null values.
	 */
	public function test_parse_rate_limit_headers_handles_partial_headers() {
		$http_response_header = array(
			'HTTP/1.1 429 Too Many Requests',
			'Content-Type: application/json',
			'Retry-After: 30',
		);

		$result = parse_rate_limit_headers( $http_response_header );

		$this->assertNull( $result['remaining'], 'Missing remaining should be null' );
		$this->assertNull( $result['reset'], 'Missing reset should be null' );
		$this->assertEquals( 30, $result['retry_after'], 'Should extract retry-after' );
	}

	/**
	 * Test parsing empty header array
	 *
	 * Verifies that empty headers return all null values.
	 */
	public function test_parse_rate_limit_headers_handles_empty_array() {
		$result = parse_rate_limit_headers( array() );

		$this->assertNull( $result['remaining'], 'remaining should be null' );
		$this->assertNull( $result['reset'], 'reset should be null' );
		$this->assertNull( $result['retry_after'], 'retry_after should be null' );
	}

	/**
	 * Test case-insensitive header parsing
	 *
	 * HTTP headers are case-insensitive, verify our parsing handles this.
	 */
	public function test_parse_rate_limit_headers_is_case_insensitive() {
		$http_response_header = array(
			'HTTP/1.1 200 OK',
			'x-ratelimit-remaining: 100',
			'X-RATELIMIT-RESET: 1704067200',
			'retry-after: 45',
		);

		$result = parse_rate_limit_headers( $http_response_header );

		$this->assertEquals( 100, $result['remaining'], 'Should extract lowercase header' );
		$this->assertEquals( 1704067200, $result['reset'], 'Should extract uppercase header' );
		$this->assertEquals( 45, $result['retry_after'], 'Should extract lowercase retry-after' );
	}

	/**
	 * Test that smart backoff uses Retry-After header when present
	 *
	 * The Retry-After header should take priority over other methods.
	 */
	public function test_calculate_smart_backoff_uses_retry_after_first() {
		$rate_limit_info = array(
			'remaining'   => 0,
			'reset'       => time() + 3600, // 1 hour from now.
			'retry_after' => 10, // 10 seconds.
		);

		$result = calculate_smart_backoff( $rate_limit_info, 1 );

		// Should use retry-after (10 seconds = 10000ms + 100ms buffer).
		$this->assertEquals( 10100, $result, 'Should use Retry-After header value with buffer' );
	}

	/**
	 * Test that smart backoff waits until reset when rate limit exhausted
	 *
	 * When remaining is 0 and no Retry-After, should wait until reset time.
	 */
	public function test_calculate_smart_backoff_waits_for_reset_when_exhausted() {
		$current_time = time();
		$reset_time   = $current_time + 30; // 30 seconds from now.

		$rate_limit_info = array(
			'remaining'   => 0,
			'reset'       => $reset_time,
			'retry_after' => null,
		);

		$result = calculate_smart_backoff( $rate_limit_info, 1 );

		// Should wait until reset time (30 seconds = ~30000ms + buffer).
		$expected_min = 29900; // Allow for timing variations.
		$expected_max = 31000;

		$this->assertGreaterThanOrEqual( $expected_min, $result, 'Should wait at least 30 seconds' );
		$this->assertLessThanOrEqual( $expected_max, $result, 'Should not wait much more than 30 seconds' );
	}

	/**
	 * Test that smart backoff falls back to exponential backoff
	 *
	 * When no rate limit headers are available, should use exponential backoff.
	 */
	public function test_calculate_smart_backoff_falls_back_to_exponential() {
		$rate_limit_info = array(
			'remaining'   => null,
			'reset'       => null,
			'retry_after' => null,
		);

		$attempt_1 = calculate_smart_backoff( $rate_limit_info, 1 );
		$attempt_2 = calculate_smart_backoff( $rate_limit_info, 2 );
		$attempt_3 = calculate_smart_backoff( $rate_limit_info, 3 );

		$this->assertEquals( WPORG_BASE_DELAY, $attempt_1, 'First attempt should use base delay' );
		$this->assertEquals( WPORG_BASE_DELAY * 2, $attempt_2, 'Second attempt should double' );
		$this->assertEquals( WPORG_BASE_DELAY * 4, $attempt_3, 'Third attempt should quadruple' );
	}

	/**
	 * Test that smart backoff caps Retry-After at reasonable maximum
	 *
	 * Very large Retry-After values should be capped.
	 */
	public function test_calculate_smart_backoff_caps_retry_after() {
		$rate_limit_info = array(
			'remaining'   => null,
			'reset'       => null,
			'retry_after' => 3600, // 1 hour in seconds.
		);

		$result = calculate_smart_backoff( $rate_limit_info, 1 );

		// Should be capped at 2x WPORG_MAX_DELAY.
		$max_allowed = WPORG_MAX_DELAY * 2;
		$this->assertLessThanOrEqual( $max_allowed, $result, 'Retry-After should be capped' );
	}

	/**
	 * Test that smart backoff caps reset wait time
	 *
	 * Very long reset wait times should be capped at 5 minutes.
	 */
	public function test_calculate_smart_backoff_caps_reset_wait_time() {
		$rate_limit_info = array(
			'remaining'   => 0,
			'reset'       => time() + 7200, // 2 hours from now.
			'retry_after' => null,
		);

		$result = calculate_smart_backoff( $rate_limit_info, 1 );

		// Should be capped at 5 minutes (300000ms).
		$this->assertEquals( 300000, $result, 'Reset wait time should be capped at 5 minutes' );
	}

	/**
	 * Test that smart backoff handles already-passed reset time
	 *
	 * If reset time is in the past, should use exponential backoff.
	 */
	public function test_calculate_smart_backoff_handles_past_reset_time() {
		$rate_limit_info = array(
			'remaining'   => 0,
			'reset'       => time() - 10, // 10 seconds in the past.
			'retry_after' => null,
		);

		$result = calculate_smart_backoff( $rate_limit_info, 1 );

		// Should return minimal wait (just the 100ms buffer since wait is 0).
		$this->assertLessThanOrEqual( 200, $result, 'Past reset should result in minimal wait' );
	}

	/**
	 * Test that remaining > 0 doesn't trigger reset wait
	 *
	 * If we still have remaining requests, should fall back to exponential.
	 */
	public function test_calculate_smart_backoff_ignores_reset_when_remaining_positive() {
		$rate_limit_info = array(
			'remaining'   => 50, // Still have requests.
			'reset'       => time() + 3600, // Reset in 1 hour.
			'retry_after' => null,
		);

		$result = calculate_smart_backoff( $rate_limit_info, 1 );

		// Should use exponential backoff, not wait for reset.
		$this->assertEquals( WPORG_BASE_DELAY, $result, 'Should use exponential backoff when remaining > 0' );
	}

	/**
	 * Test exponential backoff delay calculation
	 *
	 * Verifies the base calculate_backoff_delay function works correctly.
	 */
	public function test_calculate_backoff_delay_exponential_growth() {
		$delay_1 = calculate_backoff_delay( 1 );
		$delay_2 = calculate_backoff_delay( 2 );
		$delay_3 = calculate_backoff_delay( 3 );
		$delay_4 = calculate_backoff_delay( 4 );
		$delay_5 = calculate_backoff_delay( 5 );

		$this->assertEquals( WPORG_BASE_DELAY, $delay_1, 'Attempt 1: base delay' );
		$this->assertEquals( WPORG_BASE_DELAY * 2, $delay_2, 'Attempt 2: 2x base' );
		$this->assertEquals( WPORG_BASE_DELAY * 4, $delay_3, 'Attempt 3: 4x base' );
		$this->assertEquals( WPORG_BASE_DELAY * 8, $delay_4, 'Attempt 4: 8x base' );
		$this->assertEquals( WPORG_BASE_DELAY * 16, $delay_5, 'Attempt 5: 16x base' );
	}

	/**
	 * Test that exponential backoff is capped at maximum
	 *
	 * Verifies that delay doesn't exceed WPORG_MAX_DELAY.
	 */
	public function test_calculate_backoff_delay_capped_at_max() {
		// High attempt number that would exceed max.
		$delay = calculate_backoff_delay( 10 );

		$this->assertLessThanOrEqual(
			WPORG_MAX_DELAY,
			$delay,
			'Delay should be capped at WPORG_MAX_DELAY'
		);
		$this->assertEquals( WPORG_MAX_DELAY, $delay, 'High attempts should return max delay' );
	}

	/**
	 * Test parsing headers with extra whitespace
	 *
	 * HTTP headers may have varying whitespace after colons.
	 */
	public function test_parse_rate_limit_headers_handles_whitespace() {
		$http_response_header = array(
			'HTTP/1.1 200 OK',
			'X-RateLimit-Remaining:   50',  // Extra spaces.
			'X-RateLimit-Reset:1704067200', // No space.
			'Retry-After: 25',              // Normal space.
		);

		$result = parse_rate_limit_headers( $http_response_header );

		$this->assertEquals( 50, $result['remaining'], 'Should handle extra whitespace' );
		$this->assertEquals( 1704067200, $result['reset'], 'Should handle no whitespace' );
		$this->assertEquals( 25, $result['retry_after'], 'Should handle normal whitespace' );
	}

	/**
	 * Test that zero Retry-After falls back to other methods
	 *
	 * A Retry-After of 0 should not be used.
	 */
	public function test_calculate_smart_backoff_ignores_zero_retry_after() {
		$rate_limit_info = array(
			'remaining'   => 100,
			'reset'       => null,
			'retry_after' => 0,
		);

		$result = calculate_smart_backoff( $rate_limit_info, 2 );

		// Should fall back to exponential backoff.
		$this->assertEquals( WPORG_BASE_DELAY * 2, $result, 'Zero retry-after should use exponential' );
	}
}
