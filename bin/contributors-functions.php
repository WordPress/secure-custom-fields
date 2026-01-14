<?php
/**
 * Helper functions for contributor data operations
 *
 * Functions for reading, writing, and merging contributor data
 * from the contributors.json file.
 *
 * @package wordpress/secure-custom-fields
 */

// phpcs:disable WordPress.WP.AlternativeFunctions

namespace WordPress\SCF\Contributors;

/**
 * Valid contribution types.
 */
const CONTRIBUTION_TYPES = array( 'commit', 'review', 'comment', 'issue' );

/**
 * WordPress.org API configuration.
 */
const WPORG_API_ENDPOINT = 'https://profiles.wordpress.org/wp-json/wporg-github/v1/lookup/';
const WPORG_BATCH_SIZE   = 50;
const WPORG_MAX_RETRIES  = 5;
const WPORG_BASE_DELAY   = 1000; // 1 second in milliseconds.
const WPORG_MAX_DELAY    = 32000; // 32 seconds in milliseconds.

/**
 * Get the path to the contributors.json file.
 *
 * @return string The absolute path to contributors.json.
 */
function get_contributors_file_path() {
	return __DIR__ . '/contributors.json';
}

/**
 * Read contributors from the contributors.json file.
 *
 * Handles both old format (plain array) and new format (object with metadata).
 *
 * @param string|null $file_path Optional custom file path for testing.
 * @return array Array of contributor objects, or empty array if file doesn't exist or is invalid.
 */
function read_contributors( $file_path = null ) {
	$path = $file_path ?? get_contributors_file_path();

	if ( ! file_exists( $path ) ) {
		return array();
	}

	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		return array();
	}

	$data = json_decode( $contents, true );
	if ( ! is_array( $data ) ) {
		return array();
	}

	// Handle new format with metadata.
	if ( isset( $data['contributors'] ) && is_array( $data['contributors'] ) ) {
		return $data['contributors'];
	}

	// Old format: plain array of contributors.
	return $data;
}

/**
 * Read contributors metadata from the contributors.json file.
 *
 * Returns the metadata section of the contributors file, or an empty array
 * if the file uses the old format or doesn't exist.
 *
 * @param string|null $file_path Optional custom file path for testing.
 * @return array Metadata array with keys like 'last_processed_pr_cursor', 'last_processed_date', etc.
 */
function read_contributors_metadata( $file_path = null ) {
	$path = $file_path ?? get_contributors_file_path();

	if ( ! file_exists( $path ) ) {
		return array();
	}

	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		return array();
	}

	$data = json_decode( $contents, true );
	if ( ! is_array( $data ) ) {
		return array();
	}

	// Return metadata if present in new format.
	if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
		return $data['metadata'];
	}

	// Old format or no metadata.
	return array();
}

/**
 * Write contributors to the contributors.json file.
 *
 * Contributors are sorted alphabetically by GitHub username (case-insensitive).
 * This function preserves existing metadata if the file already uses the new format.
 *
 * @param array       $contributors Array of contributor data.
 * @param string|null $file_path    Optional custom file path for testing.
 * @return bool True on success, false on failure.
 */
function write_contributors( array $contributors, $file_path = null ) {
	$path = $file_path ?? get_contributors_file_path();

	// Sort contributors alphabetically by github_username (case-insensitive).
	usort(
		$contributors,
		function ( $a, $b ) {
			return strcasecmp( $a['github_username'] ?? '', $b['github_username'] ?? '' );
		}
	);

	// Check if existing file has metadata to preserve.
	$existing_metadata = read_contributors_metadata( $file_path );

	// Always use new format with metadata (use empty metadata if none exists).
	$data = array(
		'metadata'     => ! empty( $existing_metadata ) ? $existing_metadata : array(),
		'contributors' => $contributors,
	);

	$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( false === $json ) {
		return false;
	}

	// Ensure trailing newline.
	$json .= "\n";

	$result = file_put_contents( $path, $json );
	return false !== $result;
}

/**
 * Write contributors to the contributors.json file with metadata.
 *
 * This function always uses the new format with metadata.
 * Contributors are sorted alphabetically by GitHub username (case-insensitive).
 *
 * @param array       $contributors Array of contributor data.
 * @param array       $metadata     Metadata to include (cursor, dates, etc.).
 * @param string|null $file_path    Optional custom file path for testing.
 * @return bool True on success, false on failure.
 */
function write_contributors_with_metadata( array $contributors, array $metadata, $file_path = null ) {
	$path = $file_path ?? get_contributors_file_path();

	// Sort contributors alphabetically by github_username (case-insensitive).
	usort(
		$contributors,
		function ( $a, $b ) {
			return strcasecmp( $a['github_username'] ?? '', $b['github_username'] ?? '' );
		}
	);

	$data = array(
		'metadata'     => $metadata,
		'contributors' => $contributors,
	);

	$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( false === $json ) {
		return false;
	}

	// Ensure trailing newline.
	$json .= "\n";

	$result = file_put_contents( $path, $json );
	return false !== $result;
}

/**
 * Validate a contributor entry structure.
 *
 * Valid structure:
 * - github_username: string (required)
 * - wporg_username: string|null
 * - wporg_display_name: string|null
 * - contribution_types: array of valid contribution type strings
 * - first_contribution_date: string in ISO 8601 format (YYYY-MM-DD)
 *
 * @param array $contributor The contributor data to validate.
 * @return bool True if valid, false otherwise.
 */
function validate_contributor( array $contributor ) {
	// Check required field: github_username.
	if ( ! isset( $contributor['github_username'] ) || ! is_string( $contributor['github_username'] ) || '' === $contributor['github_username'] ) {
		return false;
	}

	// Check optional string|null fields.
	$nullable_strings = array( 'wporg_username', 'wporg_display_name' );
	foreach ( $nullable_strings as $field ) {
		if ( isset( $contributor[ $field ] ) && null !== $contributor[ $field ] && ! is_string( $contributor[ $field ] ) ) {
			return false;
		}
	}

	// Check contribution_types is an array with valid values.
	if ( ! isset( $contributor['contribution_types'] ) || ! is_array( $contributor['contribution_types'] ) ) {
		return false;
	}

	foreach ( $contributor['contribution_types'] as $type ) {
		if ( ! in_array( $type, CONTRIBUTION_TYPES, true ) ) {
			return false;
		}
	}

	// Check first_contribution_date is a valid date string.
	if ( ! isset( $contributor['first_contribution_date'] ) || ! is_string( $contributor['first_contribution_date'] ) ) {
		return false;
	}

	// Validate date format (YYYY-MM-DD).
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $contributor['first_contribution_date'] ) ) {
		return false;
	}

	return true;
}

/**
 * Merge new contributors with existing data.
 *
 * Deduplication is by github_username (case-insensitive).
 * When merging:
 * - Preserves the earliest first_contribution_date
 * - Merges contribution_types arrays (unique values)
 * - Updates wporg_username and wporg_display_name if new data has values
 *
 * @param array $existing_contributors Existing contributor data.
 * @param array $new_contributors      New contributor data to merge.
 * @return array Merged contributor data.
 */
function merge_contributors( array $existing_contributors, array $new_contributors ) {
	// Index existing contributors by lowercase github_username for quick lookup.
	$contributors_map = array();
	foreach ( $existing_contributors as $contributor ) {
		$key                      = strtolower( $contributor['github_username'] ?? '' );
		$contributors_map[ $key ] = $contributor;
	}

	// Merge new contributors.
	foreach ( $new_contributors as $new_contributor ) {
		$key = strtolower( $new_contributor['github_username'] ?? '' );

		if ( '' === $key ) {
			continue;
		}

		if ( isset( $contributors_map[ $key ] ) ) {
			// Merge with existing contributor.
			$existing = $contributors_map[ $key ];

			// Merge contribution types (unique values).
			$merged_types = array_unique(
				array_merge(
					$existing['contribution_types'] ?? array(),
					$new_contributor['contribution_types'] ?? array()
				)
			);
			sort( $merged_types );

			// Keep earliest first_contribution_date.
			$existing_date = $existing['first_contribution_date'] ?? '9999-99-99';
			$new_date      = $new_contributor['first_contribution_date'] ?? '9999-99-99';
			$merged_date   = $existing_date < $new_date ? $existing_date : $new_date;

			// Update wporg data if new data has values.
			$wporg_username     = $new_contributor['wporg_username'] ?? $existing['wporg_username'] ?? null;
			$wporg_display_name = $new_contributor['wporg_display_name'] ?? $existing['wporg_display_name'] ?? null;

			$contributors_map[ $key ] = array(
				'github_username'         => $existing['github_username'],
				'wporg_username'          => $wporg_username,
				'wporg_display_name'      => $wporg_display_name,
				'contribution_types'      => array_values( $merged_types ),
				'first_contribution_date' => $merged_date,
			);
		} else {
			// Add new contributor.
			$contributors_map[ $key ] = array(
				'github_username'         => $new_contributor['github_username'],
				'wporg_username'          => $new_contributor['wporg_username'] ?? null,
				'wporg_display_name'      => $new_contributor['wporg_display_name'] ?? null,
				'contribution_types'      => $new_contributor['contribution_types'] ?? array(),
				'first_contribution_date' => $new_contributor['first_contribution_date'] ?? '',
			);
		}
	}

	return array_values( $contributors_map );
}

/**
 * Lookup WordPress.org profiles for GitHub usernames.
 *
 * Uses the WordPress.org API to find linked accounts.
 * Implements batch processing (max 50 usernames per request) and
 * exponential backoff retry logic for resilience.
 *
 * @param array    $github_usernames Array of GitHub usernames to lookup.
 * @param callable $logger           Optional logging callback for failures.
 * @return array Map of github_username => wporg data (slug, display_name).
 */
function lookup_wporg_profiles( array $github_usernames, ?callable $logger = null ) {
	if ( empty( $github_usernames ) ) {
		return array();
	}

	$all_results = array();
	$batches     = array_chunk( $github_usernames, WPORG_BATCH_SIZE );

	foreach ( $batches as $batch_index => $batch ) {
		$result = wporg_api_request_with_retry( $batch, $batch_index + 1, count( $batches ), $logger );

		if ( is_array( $result ) ) {
			$all_results = array_merge( $all_results, $result );
		}
	}

	return $all_results;
}

/**
 * Make WordPress.org API request with retry logic.
 *
 * Implements smart backoff using rate limit headers when available,
 * falling back to exponential backoff for transient failures.
 *
 * @param array    $usernames    Array of GitHub usernames for this batch.
 * @param int      $batch_num    Current batch number (for logging).
 * @param int      $total_batches Total number of batches (for logging).
 * @param callable $logger       Optional logging callback.
 * @return array|null API response data or null on failure.
 */
function wporg_api_request_with_retry( array $usernames, int $batch_num, int $total_batches, ?callable $logger = null ) {
	$attempt = 0;

	while ( $attempt < WPORG_MAX_RETRIES ) {
		++$attempt;

		$response = make_wporg_api_request( $usernames );

		if ( null !== $response && isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return $response['data'];
		}

		$status_code     = $response['status'] ?? 0;
		$rate_limit_info = $response['rate_limit'] ?? array();

		if ( ! should_retry_wporg_request( $attempt, $status_code ) ) {
			if ( $logger ) {
				$logger(
					sprintf(
						'Batch %d/%d failed with status %d after %d attempt(s) - not retrying',
						$batch_num,
						$total_batches,
						$status_code,
						$attempt
					)
				);
			}
			break;
		}

		// Use smart backoff which considers rate limit headers.
		$delay_ms = calculate_smart_backoff( $rate_limit_info, $attempt );

		if ( $logger ) {
			$using_smart_backoff = isset( $rate_limit_info['retry_after'] ) ||
				( isset( $rate_limit_info['remaining'] ) && 0 === $rate_limit_info['remaining'] );
			$backoff_type        = $using_smart_backoff ? 'smart' : 'exponential';

			$logger(
				sprintf(
					'Batch %d/%d: Attempt %d failed (status %d), retrying in %dms (%s backoff)...',
					$batch_num,
					$total_batches,
					$attempt,
					$status_code,
					$delay_ms,
					$backoff_type
				)
			);
		}

		usleep( $delay_ms * 1000 );
	}

	if ( $logger ) {
		$logger(
			sprintf(
				'Batch %d/%d failed after %d attempts',
				$batch_num,
				$total_batches,
				$attempt
			)
		);
	}

	return null;
}

/**
 * Make a single WordPress.org API request.
 *
 * @param array $usernames Array of GitHub usernames.
 * @return array|null Response with 'status', 'data', and 'rate_limit' keys, or null on error.
 */
function make_wporg_api_request( array $usernames ) {
	$request_body = json_encode( array( 'github_user' => $usernames ) );

	if ( false === $request_body ) {
		return null;
	}

	$context = stream_context_create(
		array(
			'http' => array(
				'method'        => 'POST',
				'header'        => implode(
					"\r\n",
					array(
						'Content-Type: application/json',
						'Accept: application/json',
						'User-Agent: WordPress-SCF-Contributor-Lookup',
					)
				),
				'content'       => $request_body,
				'timeout'       => 30,
				'ignore_errors' => true,
			),
		)
	);

	$response = @file_get_contents( WPORG_API_ENDPOINT, false, $context );

	// Extract status code and rate limit info from response headers.
	$status_code     = 0;
	$rate_limit_info = array(
		'remaining'   => null,
		'reset'       => null,
		'retry_after' => null,
	);

	// @phpstan-ignore isset.variable (http_response_header is a magic PHP variable set by file_get_contents)
	if ( isset( $http_response_header ) && is_array( $http_response_header ) ) {
		foreach ( $http_response_header as $header ) {
			if ( preg_match( '/^HTTP\/\d+\.?\d*\s+(\d+)/', $header, $matches ) ) {
				$status_code = (int) $matches[1];
			}
		}
		// Parse rate limit headers.
		$rate_limit_info = parse_rate_limit_headers( $http_response_header );
	}

	if ( false === $response ) {
		return array(
			'status'     => 0,
			'data'       => null,
			'rate_limit' => $rate_limit_info,
		);
	}

	$data = json_decode( $response, true );

	return array(
		'status'     => $status_code,
		'data'       => ( 200 === $status_code && is_array( $data ) ) ? $data : null,
		'rate_limit' => $rate_limit_info,
	);
}

/**
 * Determine if a WordPress.org API request should be retried.
 *
 * @param int $attempt     Current attempt number (1-based).
 * @param int $status_code HTTP status code (0 for network failures).
 * @return bool Whether to retry.
 */
function should_retry_wporg_request( int $attempt, int $status_code ) {
	if ( $attempt >= WPORG_MAX_RETRIES ) {
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
 * Calculate exponential backoff delay.
 *
 * @param int $attempt Current attempt number (1-based).
 * @return int Delay in milliseconds.
 */
function calculate_backoff_delay( int $attempt ) {
	$delay = WPORG_BASE_DELAY * pow( 2, $attempt - 1 );
	return min( $delay, WPORG_MAX_DELAY );
}

/**
 * Parse rate limit headers from HTTP response headers.
 *
 * Extracts rate limit information from the $http_response_header array
 * that is automatically populated by file_get_contents().
 *
 * @param array $http_response_header The HTTP response headers array.
 * @return array Associative array with keys:
 *               - 'remaining': int|null Requests remaining in current window.
 *               - 'reset': int|null Unix timestamp when limit resets.
 *               - 'retry_after': int|null Seconds to wait (from 429 responses).
 */
function parse_rate_limit_headers( array $http_response_header ) {
	$rate_limit_info = array(
		'remaining'   => null,
		'reset'       => null,
		'retry_after' => null,
	);

	foreach ( $http_response_header as $header ) {
		// Parse X-RateLimit-Remaining header.
		if ( preg_match( '/^X-RateLimit-Remaining:\s*(\d+)/i', $header, $matches ) ) {
			$rate_limit_info['remaining'] = (int) $matches[1];
			continue;
		}

		// Parse X-RateLimit-Reset header (Unix timestamp).
		if ( preg_match( '/^X-RateLimit-Reset:\s*(\d+)/i', $header, $matches ) ) {
			$rate_limit_info['reset'] = (int) $matches[1];
			continue;
		}

		// Parse Retry-After header (seconds to wait).
		if ( preg_match( '/^Retry-After:\s*(\d+)/i', $header, $matches ) ) {
			$rate_limit_info['retry_after'] = (int) $matches[1];
			continue;
		}
	}

	return $rate_limit_info;
}

/**
 * Calculate smart backoff delay based on rate limit headers.
 *
 * Uses rate limit headers when available for intelligent backoff:
 * 1. If Retry-After header is present, use that value
 * 2. If rate limit is exhausted (remaining = 0), wait until reset time
 * 3. Otherwise, fall back to exponential backoff
 *
 * @param array $rate_limit_info Rate limit info from parse_rate_limit_headers().
 * @param int   $attempt         Current attempt number (1-based) for fallback.
 * @return int Delay in milliseconds.
 */
function calculate_smart_backoff( array $rate_limit_info, int $attempt ) {
	// Priority 1: Use Retry-After header if present (commonly sent with 429 responses).
	if ( isset( $rate_limit_info['retry_after'] ) && $rate_limit_info['retry_after'] > 0 ) {
		// Retry-After is in seconds, convert to milliseconds.
		// Add a small buffer (100ms) to account for timing variations.
		$delay_ms = ( $rate_limit_info['retry_after'] * 1000 ) + 100;
		// Allow up to 2x max delay for rate limits.
		return min( $delay_ms, WPORG_MAX_DELAY * 2 );
	}

	// Priority 2: If rate limit is exhausted, wait until reset time.
	if ( isset( $rate_limit_info['remaining'] ) && 0 === $rate_limit_info['remaining'] && isset( $rate_limit_info['reset'] ) ) {
		$current_time      = time();
		$reset_time        = $rate_limit_info['reset'];
		$wait_seconds      = max( 0, $reset_time - $current_time );
		$wait_milliseconds = ( $wait_seconds * 1000 ) + 100; // Add small buffer.

		// Cap at a reasonable maximum (5 minutes) to avoid extremely long waits.
		return min( $wait_milliseconds, 300000 );
	}

	// Priority 3: Fall back to exponential backoff.
	return calculate_backoff_delay( $attempt );
}

/**
 * Update contributors with WordPress.org profile data.
 *
 * Performs batch lookups for all contributors and updates their
 * wporg_username and wporg_display_name fields.
 *
 * @param array    $contributors Array of contributor data.
 * @param callable $logger       Optional logging callback.
 * @return array Updated contributor data.
 */
function update_contributors_with_wporg_data( array $contributors, ?callable $logger = null ) {
	if ( empty( $contributors ) ) {
		return $contributors;
	}

	// Extract GitHub usernames.
	$github_usernames = array_column( $contributors, 'github_username' );

	// Lookup WordPress.org profiles.
	$wporg_data = lookup_wporg_profiles( $github_usernames, $logger );

	// Apply WordPress.org data to contributors.
	return apply_wporg_data_to_contributors( $contributors, $wporg_data );
}

/**
 * Apply WordPress.org API response data to contributors.
 *
 * @param array $contributors Array of contributor data.
 * @param array $wporg_data   WordPress.org API response (github_username => profile data).
 * @return array Updated contributor data.
 */
function apply_wporg_data_to_contributors( array $contributors, array $wporg_data ) {
	// Create case-insensitive lookup map.
	$wporg_map = array();
	foreach ( $wporg_data as $github_username => $profile ) {
		$wporg_map[ strtolower( $github_username ) ] = $profile;
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
 * Get contributors with linked WordPress.org accounts.
 *
 * Filters the contributor list to only include those who have
 * a valid (non-null, non-empty) wporg_username.
 *
 * @param array $contributors Array of contributor data.
 * @return array Filtered array containing only linked contributors.
 */
function get_linked_contributors( array $contributors ) {
	return array_values(
		array_filter(
			$contributors,
			function ( $contributor ) {
				$wporg_username = $contributor['wporg_username'] ?? null;
				return ! empty( $wporg_username );
			}
		)
	);
}

/**
 * Generate the Contributors field value for readme.txt.
 *
 * Returns a comma-separated list of WordPress.org usernames.
 * Always includes 'wordpressdotorg' first, then linked contributors
 * sorted by first contribution date (earliest first).
 *
 * @param array $contributors Array of contributor data.
 * @return string Comma-separated WordPress.org usernames.
 */
function generate_readme_contributors_field( array $contributors ) {
	$linked = get_linked_contributors( $contributors );

	// Sort by first_contribution_date (earliest first).
	usort(
		$linked,
		function ( $a, $b ) {
			$date_a = $a['first_contribution_date'] ?? '9999-99-99';
			$date_b = $b['first_contribution_date'] ?? '9999-99-99';
			return strcmp( $date_a, $date_b );
		}
	);

	$wporg_usernames = array_column( $linked, 'wporg_username' );

	// Always include wordpressdotorg first.
	array_unshift( $wporg_usernames, 'wordpressdotorg' );

	// Remove any duplicates (in case wordpressdotorg is already in the list).
	$wporg_usernames = array_unique( $wporg_usernames );

	return implode( ', ', $wporg_usernames );
}

/**
 * Generate CONTRIBUTORS.md content.
 *
 * Creates a markdown file with introductory text and a table
 * of all contributors (linked and unlinked).
 *
 * Table columns: WordPress.org Username | GitHub Username | Display Name
 * Sorted by: wporg_username (blanks at end), then github_username
 *
 * @param array $contributors Array of contributor data.
 * @return string Markdown content for CONTRIBUTORS.md.
 */
function generate_contributors_md( array $contributors ) {
	$lines = array();

	// Header and introduction.
	$lines[] = '# Contributors';
	$lines[] = '';
	$lines[] = 'Thank you to all the contributors who have helped make Secure Custom Fields better.';
	$lines[] = '';
	$lines[] = 'This file is automatically generated from the contributor acknowledgement system.';
	$lines[] = 'Contributors are recognized for their commits, code reviews, issue reports, and comments.';
	$lines[] = '';

	// Table header.
	$lines[] = '| WordPress.org Username | GitHub Username | Display Name |';
	$lines[] = '| ---------------------- | --------------- | ------------ |';

	// Sort contributors: wporg_username first (blanks at end), then github_username.
	$sorted = $contributors;
	usort(
		$sorted,
		function ( $a, $b ) {
			$wporg_a  = $a['wporg_username'] ?? '';
			$wporg_b  = $b['wporg_username'] ?? '';
			$github_a = $a['github_username'] ?? '';
			$github_b = $b['github_username'] ?? '';

			// Blanks go to the end.
			$a_has_wporg = ! empty( $wporg_a );
			$b_has_wporg = ! empty( $wporg_b );

			if ( $a_has_wporg && ! $b_has_wporg ) {
				return -1;
			}
			if ( ! $a_has_wporg && $b_has_wporg ) {
				return 1;
			}

			// Both have wporg or both don't - sort by wporg first, then github.
			if ( $a_has_wporg && $b_has_wporg ) {
				$wporg_cmp = strcasecmp( $wporg_a, $wporg_b );
				if ( 0 !== $wporg_cmp ) {
					return $wporg_cmp;
				}
			}

			return strcasecmp( $github_a, $github_b );
		}
	);

	// Table rows.
	foreach ( $sorted as $contributor ) {
		$github_username = $contributor['github_username'] ?? '';
		$wporg_username  = $contributor['wporg_username'] ?? null;
		$display_name    = $contributor['wporg_display_name'] ?? null;

		// WordPress.org username with link (or empty).
		$wporg_cell = '';
		if ( ! empty( $wporg_username ) ) {
			$wporg_cell = sprintf( '[@%s](https://profiles.wordpress.org/%s)', $wporg_username, $wporg_username );
		}

		// GitHub username with link.
		$github_cell = sprintf( '[@%s](https://github.com/%s)', $github_username, $github_username );

		// Display name (or empty).
		$display_cell = $display_name ?? '';

		$lines[] = sprintf( '| %s | %s | %s |', $wporg_cell, $github_cell, $display_cell );
	}

	$lines[] = '';

	return implode( "\n", $lines );
}

/**
 * Generate docs/contributing/contributors.md content.
 *
 * Creates a markdown file formatted for the developer.wordpress.org
 * docs site style.
 *
 * Table columns: WordPress.org Username | GitHub Username | Display Name
 * Sorted by: wporg_username (blanks at end), then github_username
 *
 * @param array $contributors Array of contributor data.
 * @return string Markdown content for docs/contributing/contributors.md.
 */
function generate_docs_contributors_md( array $contributors ) {
	$lines = array();

	// Header for docs site.
	$lines[] = '# Contributors';
	$lines[] = '';
	$lines[] = 'This page acknowledges all contributors to Secure Custom Fields.';
	$lines[] = '';
	$lines[] = 'Contributors are recognized for their commits, code reviews, issue reports, and comments on the project.';
	$lines[] = '';
	$lines[] = '## Contributor List';
	$lines[] = '';

	// Table header.
	$lines[] = '| WordPress.org Username | GitHub Username | Display Name |';
	$lines[] = '| ---------------------- | --------------- | ------------ |';

	// Sort contributors: wporg_username first (blanks at end), then github_username.
	$sorted = $contributors;
	usort(
		$sorted,
		function ( $a, $b ) {
			$wporg_a  = $a['wporg_username'] ?? '';
			$wporg_b  = $b['wporg_username'] ?? '';
			$github_a = $a['github_username'] ?? '';
			$github_b = $b['github_username'] ?? '';

			// Blanks go to the end.
			$a_has_wporg = ! empty( $wporg_a );
			$b_has_wporg = ! empty( $wporg_b );

			if ( $a_has_wporg && ! $b_has_wporg ) {
				return -1;
			}
			if ( ! $a_has_wporg && $b_has_wporg ) {
				return 1;
			}

			// Both have wporg or both don't - sort by wporg first, then github.
			if ( $a_has_wporg && $b_has_wporg ) {
				$wporg_cmp = strcasecmp( $wporg_a, $wporg_b );
				if ( 0 !== $wporg_cmp ) {
					return $wporg_cmp;
				}
			}

			return strcasecmp( $github_a, $github_b );
		}
	);

	// Table rows.
	foreach ( $sorted as $contributor ) {
		$github_username = $contributor['github_username'] ?? '';
		$wporg_username  = $contributor['wporg_username'] ?? null;
		$display_name    = $contributor['wporg_display_name'] ?? null;

		// WordPress.org username with link (or empty).
		$wporg_cell = '';
		if ( ! empty( $wporg_username ) ) {
			$wporg_cell = sprintf( '[@%s](https://profiles.wordpress.org/%s)', $wporg_username, $wporg_username );
		}

		// GitHub username with link.
		$github_cell = sprintf( '[@%s](https://github.com/%s)', $github_username, $github_username );

		// Display name (or empty).
		$display_cell = $display_name ?? '';

		$lines[] = sprintf( '| %s | %s | %s |', $wporg_cell, $github_cell, $display_cell );
	}

	$lines[] = '';
	$lines[] = '## How to Get Listed';
	$lines[] = '';
	$lines[] = 'Contributors are automatically added when they:';
	$lines[] = '';
	$lines[] = '- Commit code to the repository';
	$lines[] = '- Review pull requests';
	$lines[] = '- Report issues that are resolved';
	$lines[] = '- Provide helpful comments on pull requests';
	$lines[] = '';
	$lines[] = 'To link your GitHub account to your WordPress.org profile, visit your [WordPress.org profile settings](https://profiles.wordpress.org/me/profile/edit/).';
	$lines[] = '';

	return implode( "\n", $lines );
}

/**
 * Update readme.txt with new contributors field.
 *
 * Reads the existing readme.txt, updates the Contributors field on line 2,
 * and writes the file back.
 *
 * @param array       $contributors Array of contributor data.
 * @param string|null $file_path    Optional custom file path for testing.
 * @return bool True on success, false on failure.
 */
function update_readme_contributors( array $contributors, $file_path = null ) {
	$path = $file_path ?? dirname( __DIR__ ) . '/readme.txt';

	if ( ! file_exists( $path ) ) {
		return false;
	}

	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		return false;
	}

	$contributors_field = generate_readme_contributors_field( $contributors );

	// If no linked contributors, keep the existing field or use placeholder.
	if ( empty( $contributors_field ) ) {
		$contributors_field = 'wordpressdotorg';
	}

	// Replace the Contributors line (line 2).
	$pattern      = '/^Contributors:.*$/m';
	$replacement  = 'Contributors: ' . $contributors_field;
	$new_contents = preg_replace( $pattern, $replacement, $contents, 1 );

	if ( null === $new_contents ) {
		return false;
	}

	$result = file_put_contents( $path, $new_contents );
	return false !== $result;
}

/**
 * Write CONTRIBUTORS.md file.
 *
 * @param array       $contributors Array of contributor data.
 * @param string|null $file_path    Optional custom file path for testing.
 * @return bool True on success, false on failure.
 */
function write_contributors_md( array $contributors, $file_path = null ) {
	$path = $file_path ?? dirname( __DIR__ ) . '/CONTRIBUTORS.md';

	$content = generate_contributors_md( $contributors );

	$result = file_put_contents( $path, $content );
	return false !== $result;
}

/**
 * Write docs/contributing/contributors.md file.
 *
 * @param array       $contributors Array of contributor data.
 * @param string|null $file_path    Optional custom file path for testing.
 * @return bool True on success, false on failure.
 */
function write_docs_contributors_md( array $contributors, $file_path = null ) {
	$path = $file_path ?? dirname( __DIR__ ) . '/docs/contributing/contributors.md';

	// Ensure directory exists.
	$dir = dirname( $path );
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0755, true );
	}

	$content = generate_docs_contributors_md( $contributors );

	$result = file_put_contents( $path, $content );
	return false !== $result;
}

/**
 * Default bot account exclusion list.
 *
 * These usernames are filtered out in addition to accounts ending in [bot].
 */
const BOT_EXCLUSION_LIST = array(
	'web-flow',
	'github-actions',
	'codecov',
	'copilot-pull-request-reviewer',
	'dependabot',
	'renovate',
);

/**
 * Filter bot accounts from contributor list.
 *
 * Removes accounts that:
 * - End with [bot] (case-insensitive)
 * - Are in the exclusion list (case-insensitive)
 *
 * @param array $contributors   List of contributors with 'github_username' key.
 * @param array $exclusion_list Additional usernames to exclude (optional, uses BOT_EXCLUSION_LIST if empty).
 * @return array Filtered list without bot accounts.
 */
function filter_bot_accounts( array $contributors, array $exclusion_list = array() ) {
	// Use default exclusion list if none provided.
	if ( empty( $exclusion_list ) ) {
		$exclusion_list = BOT_EXCLUSION_LIST;
	}

	$exclusion_list_lower = array_map( 'strtolower', $exclusion_list );

	return array_values(
		array_filter(
			$contributors,
			function ( $contributor ) use ( $exclusion_list_lower ) {
				$username = $contributor['github_username'] ?? '';

				// Filter accounts ending in [bot].
				if ( preg_match( '/\[bot\]$/i', $username ) ) {
					return false;
				}

				// Filter accounts in exclusion list.
				if ( in_array( strtolower( $username ), $exclusion_list_lower, true ) ) {
					return false;
				}

				return true;
			}
		)
	);
}

/**
 * Parse REST API contributors response.
 *
 * Transforms the GitHub REST API /repos/{owner}/{repo}/contributors response
 * into the internal contributor format.
 *
 * @param array  $api_response The API response array from GitHub.
 * @param string $default_date Default contribution date (YYYY-MM-DD format).
 * @return array Parsed contributors in internal format.
 */
function parse_rest_api_contributors( array $api_response, string $default_date = '' ) {
	if ( empty( $default_date ) ) {
		$default_date = gmdate( 'Y-m-d' );
	}

	$contributors = array();

	foreach ( $api_response as $contributor ) {
		if ( ! isset( $contributor['login'] ) ) {
			continue;
		}

		$contributors[] = array(
			'github_username'         => $contributor['login'],
			'wporg_username'          => null,
			'wporg_display_name'      => null,
			'contribution_types'      => array( 'commit' ),
			'first_contribution_date' => $default_date,
		);
	}

	return $contributors;
}

/**
 * Add contributor to map with contribution type.
 *
 * Helper function for building contributor maps from various sources.
 * Creates new entries or updates existing ones, merging contribution types
 * and tracking the earliest contribution date.
 *
 * @param array  $map   Reference to contributors map (keyed by lowercase username).
 * @param string $login GitHub username.
 * @param string $type  Contribution type (commit, review, comment, issue).
 * @param string $date  Contribution date (YYYY-MM-DD format).
 */
function add_to_contributors_map( array &$map, string $login, string $type, string $date ) {
	$key = strtolower( $login );

	if ( ! isset( $map[ $key ] ) ) {
		$map[ $key ] = array(
			'github_username'         => $login,
			'wporg_username'          => null,
			'wporg_display_name'      => null,
			'contribution_types'      => array(),
			'first_contribution_date' => $date,
		);
	}

	if ( ! in_array( $type, $map[ $key ]['contribution_types'], true ) ) {
		$map[ $key ]['contribution_types'][] = $type;
	}

	if ( $date < $map[ $key ]['first_contribution_date'] ) {
		$map[ $key ]['first_contribution_date'] = $date;
	}
}

/**
 * Parse GraphQL PR data response.
 *
 * Transforms the GitHub GraphQL API response for merged PRs into
 * the internal contributor format. Extracts reviewers, commenters,
 * and linked issue reporters.
 *
 * @param array $response The GraphQL response with repository.pullRequests structure.
 * @return array Parsed contributors in internal format.
 */
function parse_graphql_pr_data( array $response ) {
	$contributors_map = array();

	$prs = $response['data']['repository']['pullRequests']['nodes'] ?? array();

	foreach ( $prs as $pr ) {
		$merged_at = $pr['mergedAt'] ?? null;
		$date      = $merged_at ? substr( $merged_at, 0, 10 ) : gmdate( 'Y-m-d' );

		// Process reviews.
		$reviews = $pr['reviews']['nodes'] ?? array();
		foreach ( $reviews as $review ) {
			$login = $review['author']['login'] ?? null;
			if ( $login ) {
				add_to_contributors_map( $contributors_map, $login, 'review', $date );
			}
		}

		// Process comments.
		$comments = $pr['comments']['nodes'] ?? array();
		foreach ( $comments as $comment ) {
			$login = $comment['author']['login'] ?? null;
			if ( $login ) {
				add_to_contributors_map( $contributors_map, $login, 'comment', $date );
			}
		}

		// Process linked issues.
		$issues = $pr['closingIssuesReferences']['nodes'] ?? array();
		foreach ( $issues as $issue ) {
			$login = $issue['author']['login'] ?? null;
			if ( $login ) {
				add_to_contributors_map( $contributors_map, $login, 'issue', $date );
			}
		}
	}

	return array_values( $contributors_map );
}

/**
 * Merge contributors from multiple source types.
 *
 * Combines contributors from commits, reviews, comments, and issues
 * into a single contributor list with merged contribution types.
 *
 * @param array  $commit_contributors  Contributors from commits (with 'login' key).
 * @param array  $review_contributors  Contributors from reviews (with 'login' key).
 * @param array  $comment_contributors Contributors from comments (with 'login' key).
 * @param array  $issue_contributors   Contributors from issues (with 'login' key).
 * @param string $default_date         Default contribution date (YYYY-MM-DD format).
 * @return array Merged contributors in internal format.
 */
function merge_contribution_sources(
	array $commit_contributors,
	array $review_contributors,
	array $comment_contributors,
	array $issue_contributors,
	string $default_date
) {
	$contributors_map = array();

	// Process commits.
	foreach ( $commit_contributors as $contributor ) {
		$login = $contributor['login'] ?? null;
		if ( $login ) {
			add_to_contributors_map( $contributors_map, $login, 'commit', $default_date );
		}
	}

	// Process reviews.
	foreach ( $review_contributors as $contributor ) {
		$login = $contributor['login'] ?? null;
		if ( $login ) {
			add_to_contributors_map( $contributors_map, $login, 'review', $default_date );
		}
	}

	// Process comments.
	foreach ( $comment_contributors as $contributor ) {
		$login = $contributor['login'] ?? null;
		if ( $login ) {
			add_to_contributors_map( $contributors_map, $login, 'comment', $default_date );
		}
	}

	// Process issues.
	foreach ( $issue_contributors as $contributor ) {
		$login = $contributor['login'] ?? null;
		if ( $login ) {
			add_to_contributors_map( $contributors_map, $login, 'issue', $default_date );
		}
	}

	return array_values( $contributors_map );
}

/**
 * Find contributor by username in array.
 *
 * Searches a contributor array for a specific GitHub username (case-insensitive).
 *
 * @param array  $contributors List of contributors.
 * @param string $username     GitHub username to find.
 * @return array|null Contributor data or null if not found.
 */
function find_contributor_by_username( array $contributors, string $username ) {
	foreach ( $contributors as $contributor ) {
		if ( strtolower( $contributor['github_username'] ?? '' ) === strtolower( $username ) ) {
			return $contributor;
		}
	}
	return null;
}

/**
 * Generate all output files from contributor data.
 *
 * Updates readme.txt, creates CONTRIBUTORS.md, and creates
 * docs/contributing/contributors.md.
 *
 * @param array    $contributors Array of contributor data.
 * @param callable $logger       Optional logging callback.
 * @return array Results with 'readme', 'contributors_md', 'docs_md' keys.
 */
function generate_all_output_files( array $contributors, ?callable $logger = null ) {
	$results = array(
		'readme'          => false,
		'contributors_md' => false,
		'docs_md'         => false,
	);

	// Update readme.txt.
	if ( $logger ) {
		$logger( 'Updating readme.txt...' );
	}
	$results['readme'] = update_readme_contributors( $contributors );
	if ( $logger ) {
		$logger( $results['readme'] ? 'readme.txt updated successfully.' : 'Failed to update readme.txt.' );
	}

	// Create CONTRIBUTORS.md.
	if ( $logger ) {
		$logger( 'Creating CONTRIBUTORS.md...' );
	}
	$results['contributors_md'] = write_contributors_md( $contributors );
	if ( $logger ) {
		$logger( $results['contributors_md'] ? 'CONTRIBUTORS.md created successfully.' : 'Failed to create CONTRIBUTORS.md.' );
	}

	// Create docs/contributing/contributors.md.
	if ( $logger ) {
		$logger( 'Creating docs/contributing/contributors.md...' );
	}
	$results['docs_md'] = write_docs_contributors_md( $contributors );
	if ( $logger ) {
		$logger( $results['docs_md'] ? 'docs/contributing/contributors.md created successfully.' : 'Failed to create docs/contributing/contributors.md.' );
	}

	return $results;
}
