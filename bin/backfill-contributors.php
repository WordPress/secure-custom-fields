#!/usr/bin/env php
<?php
/**
 * Backfill historical contributors from GitHub API
 *
 * This script collects all historical contributors from the GitHub repository
 * using both REST and GraphQL APIs, then stores them in contributors.json.
 * It also looks up WordPress.org profile data for linked accounts and
 * generates output files (readme.txt, CONTRIBUTORS.md, docs page).
 *
 * By default, the script runs in incremental mode, only fetching PRs merged
 * after the last processed cursor. Use --full to fetch all historical data.
 *
 * Usage: php bin/backfill-contributors.php [--full] [--dry-run] [--skip-wporg] [--skip-output]
 *
 * Environment variables:
 *   GITHUB_TOKEN - GitHub API token for authentication (required)
 *
 * @package wordpress/secure-custom-fields
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
// phpcs:disable WordPress.WP.AlternativeFunctions

namespace WordPress\SCF\Scripts;

// Ensure we're in the right directory.
chdir( dirname( __DIR__ ) );

// Load the contributor helper functions.
require_once __DIR__ . '/contributors-functions.php';

use function WordPress\SCF\Contributors\read_contributors;
use function WordPress\SCF\Contributors\read_contributors_metadata;
use function WordPress\SCF\Contributors\write_contributors_with_metadata;
use function WordPress\SCF\Contributors\merge_contributors;
use function WordPress\SCF\Contributors\update_contributors_with_wporg_data;
use function WordPress\SCF\Contributors\generate_all_output_files;
use function WordPress\SCF\Contributors\parse_rate_limit_headers;
use function WordPress\SCF\Contributors\calculate_smart_backoff;
use function WordPress\SCF\Contributors\filter_bot_accounts;
use function WordPress\SCF\Contributors\add_to_contributors_map;

use const WordPress\SCF\Contributors\WPORG_MAX_RETRIES;
use const WordPress\SCF\Contributors\BOT_EXCLUSION_LIST;

/**
 * GitHub repository configuration
 */
const GITHUB_OWNER = 'WordPress';
const GITHUB_REPO  = 'secure-custom-fields';

/**
 * Handles backfilling contributors from GitHub APIs
 */
class Contributor_Backfill {

	/**
	 * GitHub API token
	 *
	 * @var string
	 */
	private $github_token;

	/**
	 * Full backfill mode flag (ignore cursor, fetch all)
	 *
	 * @var bool
	 */
	private $full_backfill = false;

	/**
	 * Dry run mode flag
	 *
	 * @var bool
	 */
	private $dry_run = false;

	/**
	 * Skip WordPress.org lookup flag
	 *
	 * @var bool
	 */
	private $skip_wporg = false;

	/**
	 * Skip output file generation flag
	 *
	 * @var bool
	 */
	private $skip_output = false;

	/**
	 * Validate against props-bot comments flag
	 *
	 * @var bool
	 */
	private $validate = false;

	/**
	 * Last processed PR cursor from metadata
	 *
	 * @var string|null
	 */
	private $last_cursor = null;

	/**
	 * New cursor after processing
	 *
	 * @var string|null
	 */
	private $new_cursor = null;

	/**
	 * Last validated merge date for props-bot incremental processing
	 *
	 * @var string|null
	 */
	private $last_validated_merge_date = null;

	/**
	 * Newest merge date seen during props-bot validation
	 *
	 * @var string|null
	 */
	private $new_validated_merge_date = null;

	/**
	 * Constructor
	 *
	 * @param string $github_token   GitHub API token.
	 * @param bool   $full_backfill  Whether to do a full backfill (ignore cursor).
	 * @param bool   $dry_run        Whether to run in dry-run mode.
	 * @param bool   $skip_wporg     Whether to skip WordPress.org lookup.
	 * @param bool   $skip_output    Whether to skip output file generation.
	 * @param bool   $validate       Whether to validate against props-bot comments.
	 */
	public function __construct( string $github_token, bool $full_backfill = false, bool $dry_run = false, bool $skip_wporg = false, bool $skip_output = false, bool $validate = false ) {
		$this->github_token  = $github_token;
		$this->full_backfill = $full_backfill;
		$this->dry_run       = $dry_run;
		$this->skip_wporg    = $skip_wporg;
		$this->skip_output   = $skip_output;
		$this->validate      = $validate;
	}

	/**
	 * Run the backfill process
	 */
	public function run() {
		echo "Starting contributor backfill...\n";

		if ( $this->dry_run ) {
			echo "[DRY RUN] No changes will be saved.\n";
		}

		// Determine if we need a full backfill.
		$is_incremental = $this->determine_backfill_mode();

		if ( $is_incremental ) {
			echo "[INCREMENTAL] Using cursor from last run.\n";
		} else {
			echo "[FULL] Fetching all historical data.\n";
		}

		// Collect contributors from REST API (commit authors) - always full for commits.
		echo "\nFetching commit authors from REST API...\n";
		$commit_contributors = $this->fetch_rest_api_contributors();
		printf( "Found %d commit authors.\n", count( $commit_contributors ) );

		// Collect contributors from GraphQL API (reviewers, commenters, issue reporters).
		echo "\nFetching PR contributors from GraphQL API...\n";
		$pr_contributors = $this->fetch_graphql_contributors( $is_incremental );
		printf( "Found %d PR contributors (reviewers, commenters, issue reporters).\n", count( $pr_contributors ) );

		// Merge all contributors.
		echo "\nMerging contributors...\n";
		$all_contributors = $this->merge_all_contributors( $commit_contributors, $pr_contributors );

		// Filter bot accounts.
		echo "Filtering bot accounts...\n";
		$filtered_contributors = filter_bot_accounts( $all_contributors );
		printf( "Filtered out %d bot accounts.\n", count( $all_contributors ) - count( $filtered_contributors ) );

		// Load existing contributors and merge.
		$existing_contributors = read_contributors();
		printf( "Found %d existing contributors in contributors.json.\n", count( $existing_contributors ) );

		$final_contributors = merge_contributors( $existing_contributors, $filtered_contributors );
		printf( "Total unique contributors after merge: %d\n", count( $final_contributors ) );

		// Perform WordPress.org profile lookup.
		if ( ! $this->skip_wporg ) {
			echo "\nLooking up WordPress.org profiles...\n";
			$final_contributors = update_contributors_with_wporg_data(
				$final_contributors,
				function ( $message ) {
					echo "  $message\n";
				}
			);

			$linked_count = count(
				array_filter(
					$final_contributors,
					function ( $c ) {
						return ! empty( $c['wporg_username'] );
					}
				)
			);
			printf( "Found %d contributors with linked WordPress.org accounts.\n", $linked_count );
		} else {
			echo "\nSkipping WordPress.org profile lookup.\n";
		}

		// Save or display results.
		if ( $this->dry_run ) {
			echo "\n[DRY RUN] Would save the following contributors:\n";
			foreach ( $final_contributors as $contributor ) {
				$wporg = $contributor['wporg_username'] ? " (wporg: {$contributor['wporg_username']})" : '';
				printf(
					"  - %s%s: %s\n",
					$contributor['github_username'],
					$wporg,
					implode( ', ', $contributor['contribution_types'] )
				);
			}
			if ( $this->new_cursor ) {
				echo "\n[DRY RUN] Would update cursor to: {$this->new_cursor}\n";
			}
		} else {
			// Build metadata for the new format.
			$metadata = $this->build_metadata( $is_incremental );

			$result = write_contributors_with_metadata( $final_contributors, $metadata );
			if ( $result ) {
				echo "\nSuccessfully saved contributors to contributors.json\n";
				if ( $this->new_cursor ) {
					echo "Updated cursor for incremental processing.\n";
				}
			} else {
				echo "\nError: Failed to save contributors.json\n";
				exit( 1 );
			}

			// Generate output files.
			if ( ! $this->skip_output ) {
				echo "\nGenerating output files...\n";
				$output_results = generate_all_output_files(
					$final_contributors,
					function ( $message ) {
						echo "  $message\n";
					}
				);

				$success_count = count( array_filter( $output_results ) );
				$total_count   = count( $output_results );
				printf( "Generated %d/%d output files successfully.\n", $success_count, $total_count );
			} else {
				echo "\nSkipping output file generation.\n";
			}

			// Validate against props-bot comments if requested.
			if ( $this->validate ) {
				echo "\nValidating against props-bot comments...\n";
				$validation_result = $this->validate_against_props_bot( $final_contributors );

				if ( ! empty( $validation_result['added'] ) ) {
					// Re-save with newly added contributors.
					$final_contributors = $validation_result['contributors'];
					$result             = write_contributors_with_metadata( $final_contributors, $metadata );
					if ( $result ) {
						echo "Updated contributors.json with validated contributors.\n";
					}

					// Regenerate output files if we added contributors.
					if ( ! $this->skip_output ) {
						echo "Regenerating output files with validated contributors...\n";
						generate_all_output_files(
							$final_contributors,
							function ( $message ) {
								echo "  $message\n";
							}
						);
					}
				}
			}
		}

		echo "\nBackfill complete!\n";
	}

	/**
	 * Determine if we should run in incremental mode
	 *
	 * Incremental mode is used when:
	 * - --full flag is NOT set
	 * - A valid cursor exists in the metadata
	 *
	 * @return bool True for incremental mode, false for full backfill.
	 */
	private function determine_backfill_mode() {
		// --full flag forces full backfill.
		if ( $this->full_backfill ) {
			return false;
		}

		// Check for existing cursor.
		$metadata = read_contributors_metadata();
		if ( ! empty( $metadata['last_processed_pr_cursor'] ) ) {
			$this->last_cursor = $metadata['last_processed_pr_cursor'];
			return true;
		}

		// No cursor means we need a full backfill.
		return false;
	}

	/**
	 * Build metadata for the contributors file
	 *
	 * @param bool $is_incremental Whether this was an incremental run.
	 * @return array Metadata array.
	 */
	private function build_metadata( bool $is_incremental ) {
		$now = gmdate( 'c' ); // ISO 8601 format.

		// Start with existing metadata or create new.
		$existing_metadata = read_contributors_metadata();

		$metadata = array(
			'last_processed_pr_cursor'  => $this->new_cursor ?? $existing_metadata['last_processed_pr_cursor'] ?? null,
			'last_processed_date'       => $now,
			'last_full_backfill'        => $is_incremental
				? ( $existing_metadata['last_full_backfill'] ?? $now )
				: $now,
			'last_validated_merge_date' => $this->new_validated_merge_date ?? $existing_metadata['last_validated_merge_date'] ?? null,
		);

		return $metadata;
	}

	/**
	 * Fetch contributors from GitHub REST API
	 *
	 * Uses the contributors endpoint to get commit authors, then fetches
	 * the first commit date for each contributor.
	 *
	 * @return array List of contributors from commits.
	 */
	private function fetch_rest_api_contributors() {
		$contributors = array();
		$page         = 1;
		$per_page     = 100;

		// First, collect all contributor data (username and commit count).
		$contributor_data = array();
		do {
			$url      = sprintf(
				'https://api.github.com/repos/%s/%s/contributors?per_page=%d&page=%d',
				GITHUB_OWNER,
				GITHUB_REPO,
				$per_page,
				$page
			);
			$response = $this->make_rest_request( $url );

			if ( empty( $response ) || ! is_array( $response ) ) {
				break;
			}

			foreach ( $response as $contributor ) {
				if ( isset( $contributor['login'] ) ) {
					$contributor_data[] = array(
						'login'        => $contributor['login'],
						'commit_count' => $contributor['contributions'] ?? 1,
					);
				}
			}

			$response_count = count( $response );
			++$page;
		} while ( $response_count === $per_page );

		// Fetch first commit date for each contributor.
		$total = count( $contributor_data );
		foreach ( $contributor_data as $index => $data ) {
			$first_commit_date = $this->fetch_first_commit_date( $data['login'] );

			$contributors[] = array(
				'github_username'         => $data['login'],
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => $first_commit_date,
			);

			// Progress indicator every 10 contributors.
			if ( 0 === ( $index + 1 ) % 10 || ( $index + 1 ) === $total ) {
				printf( "  Fetched commit dates for %d/%d contributors...\n", $index + 1, $total );
			}
		}

		return $contributors;
	}

	/**
	 * Fetch the first commit date for a contributor
	 *
	 * Queries the commits endpoint sorted by author-date ascending
	 * to find the earliest commit by this author.
	 *
	 * @param string $username GitHub username.
	 * @return string Date in YYYY-MM-DD format.
	 */
	private function fetch_first_commit_date( string $username ) {
		$url = sprintf(
			'https://api.github.com/repos/%s/%s/commits?author=%s&per_page=1&order=asc',
			GITHUB_OWNER,
			GITHUB_REPO,
			rawurlencode( $username )
		);

		$response = $this->make_rest_request( $url );

		if ( ! empty( $response ) && is_array( $response ) && isset( $response[0]['commit']['author']['date'] ) ) {
			$date = $response[0]['commit']['author']['date'];
			// Extract YYYY-MM-DD from ISO 8601 format.
			return substr( $date, 0, 10 );
		}

		// Fallback to today if we can't determine the date.
		return gmdate( 'Y-m-d' );
	}

	/**
	 * Fetch contributors from GitHub GraphQL API
	 *
	 * Queries merged PRs to find reviewers, commenters, and linked issue reporters.
	 * In incremental mode, starts from the last processed cursor to only fetch new PRs.
	 *
	 * @param bool $is_incremental Whether to use incremental mode (start from last cursor).
	 * @return array List of contributors from PRs.
	 */
	private function fetch_graphql_contributors( bool $is_incremental = false ) {
		$contributors_map = array();
		$page_count       = 0;
		$max_pages        = 100; // Safety limit.
		$first_cursor     = null; // Track the first cursor for saving as the new cursor.

		// In incremental mode, start from the last processed cursor.
		$cursor = $is_incremental ? $this->last_cursor : null;

		if ( $is_incremental && $cursor ) {
			printf( "  Starting from cursor: %s\n", substr( $cursor, 0, 30 ) . '...' );
		}

		do {
			$query    = $this->build_graphql_query( $cursor );
			$response = $this->make_graphql_request( $query );

			if ( ! $response || ! isset( $response['data']['repository']['pullRequests'] ) ) {
				echo "Warning: GraphQL request failed or returned unexpected format.\n";
				break;
			}

			$prs      = $response['data']['repository']['pullRequests'];
			$pr_nodes = $prs['nodes'] ?? array();

			foreach ( $pr_nodes as $pr ) {
				$this->process_pr_contributors( $pr, $contributors_map );
			}

			$has_next_page = $prs['pageInfo']['hasNextPage'] ?? false;
			$end_cursor    = $prs['pageInfo']['endCursor'] ?? null;

			// Save the first end cursor we see as the new cursor for next incremental run.
			if ( null === $first_cursor && $end_cursor ) {
				$first_cursor = $end_cursor;
			}

			$cursor = $end_cursor;
			++$page_count;

			if ( 0 === $page_count % 10 ) {
				printf( "  Processed %d pages of PRs...\n", $page_count );
			}
		} while ( $has_next_page && $cursor && $page_count < $max_pages );

		// Store the new cursor for saving in metadata.
		// For full backfill, use the last cursor (end of the list).
		if ( ! $is_incremental && $cursor ) {
			$this->new_cursor = $cursor;
		} elseif ( ! $is_incremental && $first_cursor ) {
			$this->new_cursor = $first_cursor;
		}

		return array_values( $contributors_map );
	}

	/**
	 * Build GraphQL query for merged PRs
	 *
	 * @param string|null $cursor Pagination cursor.
	 * @return string GraphQL query.
	 */
	private function build_graphql_query( $cursor = null ) {
		$after = $cursor ? sprintf( ', after: "%s"', $cursor ) : '';

		return <<<GRAPHQL
{
  repository(owner: "WordPress", name: "secure-custom-fields") {
    pullRequests(states: MERGED, first: 100{$after}) {
      pageInfo {
        hasNextPage
        endCursor
      }
      nodes {
        number
        mergedAt
        author {
          login
        }
        reviews(first: 100) {
          nodes {
            author {
              login
            }
          }
        }
        comments(first: 100) {
          nodes {
            author {
              login
            }
          }
        }
        closingIssuesReferences(first: 10) {
          nodes {
            author {
              login
            }
          }
        }
      }
    }
  }
}
GRAPHQL;
	}

	/**
	 * Process PR contributors and add to map
	 *
	 * @param array $pr              PR data from GraphQL.
	 * @param array $contributors_map Reference to contributors map.
	 */
	private function process_pr_contributors( array $pr, array &$contributors_map ) {
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

	/**
	 * Merge commit and PR contributors
	 *
	 * @param array $commit_contributors Contributors from REST API.
	 * @param array $pr_contributors     Contributors from GraphQL API.
	 * @return array Merged contributors.
	 */
	private function merge_all_contributors( array $commit_contributors, array $pr_contributors ) {
		$contributors_map = array();

		// Add commit contributors.
		foreach ( $commit_contributors as $contributor ) {
			$key                      = strtolower( $contributor['github_username'] );
			$contributors_map[ $key ] = $contributor;
		}

		// Merge PR contributors.
		foreach ( $pr_contributors as $contributor ) {
			$key = strtolower( $contributor['github_username'] );

			if ( isset( $contributors_map[ $key ] ) ) {
				// Merge contribution types.
				$existing_types = $contributors_map[ $key ]['contribution_types'];
				$new_types      = $contributor['contribution_types'];
				$merged_types   = array_unique( array_merge( $existing_types, $new_types ) );
				sort( $merged_types );
				$contributors_map[ $key ]['contribution_types'] = $merged_types;

				// Keep earliest date.
				if ( $contributor['first_contribution_date'] < $contributors_map[ $key ]['first_contribution_date'] ) {
					$contributors_map[ $key ]['first_contribution_date'] = $contributor['first_contribution_date'];
				}
			} else {
				$contributors_map[ $key ] = $contributor;
			}
		}

		return array_values( $contributors_map );
	}

	/**
	 * Make REST API request to GitHub with retry logic and rate limit handling.
	 *
	 * @param string $url API URL.
	 * @return array|null Response data or null on failure.
	 */
	private function make_rest_request( string $url ) {
		$attempt = 0;

		while ( $attempt < WPORG_MAX_RETRIES ) {
			++$attempt;

			$context = stream_context_create(
				array(
					'http' => array(
						'method'        => 'GET',
						'header'        => implode(
							"\r\n",
							array(
								'Accept: application/vnd.github+json',
								'Authorization: Bearer ' . $this->github_token,
								'User-Agent: WordPress-SCF-Contributor-Backfill',
								'X-GitHub-Api-Version: 2022-11-28',
							)
						),
						'timeout'       => 30,
						'ignore_errors' => true,
					),
				)
			);

			$response = @file_get_contents( $url, false, $context );

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
				$rate_limit_info = parse_rate_limit_headers( $http_response_header );
			}

			// Success case.
			if ( false !== $response && $status_code >= 200 && $status_code < 300 ) {
				return json_decode( $response, true );
			}

			// Check if we should retry.
			$should_retry = ( 0 === $status_code ) ||
				( 429 === $status_code ) ||
				( $status_code >= 500 && $status_code < 600 );

			if ( ! $should_retry || $attempt >= WPORG_MAX_RETRIES ) {
				break;
			}

			// Calculate delay using smart backoff.
			$delay_ms = calculate_smart_backoff( $rate_limit_info, $attempt );
			usleep( $delay_ms * 1000 );
		}

		return null;
	}

	/**
	 * Make GraphQL request to GitHub with retry logic and rate limit handling.
	 *
	 * @param string $query GraphQL query.
	 * @return array|null Response data or null on failure.
	 */
	private function make_graphql_request( string $query ) {
		$url     = 'https://api.github.com/graphql';
		$data    = json_encode( array( 'query' => $query ) );
		$attempt = 0;

		while ( $attempt < WPORG_MAX_RETRIES ) {
			++$attempt;

			$context = stream_context_create(
				array(
					'http' => array(
						'method'        => 'POST',
						'header'        => implode(
							"\r\n",
							array(
								'Content-Type: application/json',
								'Authorization: Bearer ' . $this->github_token,
								'User-Agent: WordPress-SCF-Contributor-Backfill',
							)
						),
						'content'       => $data,
						'timeout'       => 30,
						'ignore_errors' => true,
					),
				)
			);

			$response = @file_get_contents( $url, false, $context );

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
				$rate_limit_info = parse_rate_limit_headers( $http_response_header );
			}

			// Success case.
			if ( false !== $response && $status_code >= 200 && $status_code < 300 ) {
				return json_decode( $response, true );
			}

			// Check if we should retry.
			$should_retry = ( 0 === $status_code ) ||
				( 429 === $status_code ) ||
				( $status_code >= 500 && $status_code < 600 );

			if ( ! $should_retry || $attempt >= WPORG_MAX_RETRIES ) {
				break;
			}

			// Calculate delay using smart backoff.
			$delay_ms = calculate_smart_backoff( $rate_limit_info, $attempt );
			usleep( $delay_ms * 1000 );
		}

		return null;
	}

	/**
	 * Validate contributors against props-bot comments on merged PRs
	 *
	 * Fetches props-bot comments from merged PRs and ensures all mentioned
	 * WordPress.org usernames are in our contributor data.
	 *
	 * @param array $contributors Current contributor data.
	 * @return array Array with 'contributors' (updated list) and 'added' (newly added wporg usernames).
	 */
	private function validate_against_props_bot( array $contributors ) {
		// Load last validated merge date for incremental processing.
		$existing_metadata               = read_contributors_metadata();
		$this->last_validated_merge_date = $existing_metadata['last_validated_merge_date'] ?? null;

		if ( $this->full_backfill ) {
			// Full backfill ignores the last validated date.
			$this->last_validated_merge_date = null;
			echo "Validating all merged PRs (full mode)...\n";
		} elseif ( $this->last_validated_merge_date ) {
			printf( "Validating PRs merged after %s (incremental)...\n", $this->last_validated_merge_date );
		}

		$props_usernames = $this->fetch_props_bot_usernames();
		printf( "Found %d unique usernames in props-bot comments.\n", count( $props_usernames ) );

		// Build a set of existing wporg usernames (case-insensitive).
		$existing_wporg = array();
		foreach ( $contributors as $contributor ) {
			if ( ! empty( $contributor['wporg_username'] ) ) {
				$existing_wporg[ strtolower( $contributor['wporg_username'] ) ] = true;
			}
		}

		// Find missing usernames.
		$missing = array();
		$pr_map  = array(); // Track which PRs each username came from.
		foreach ( $props_usernames as $username => $prs ) {
			if ( ! isset( $existing_wporg[ strtolower( $username ) ] ) ) {
				$missing[]           = $username;
				$pr_map[ $username ] = $prs;
			}
		}

		if ( empty( $missing ) ) {
			echo "✓ All props-bot contributors are in our system.\n";
			return array(
				'contributors' => $contributors,
				'added'        => array(),
			);
		}

		printf( "Adding %d missing contributors from props-bot:\n", count( $missing ) );
		$added = array();
		foreach ( $missing as $wporg_username ) {
			$prs = $pr_map[ $wporg_username ];
			printf( "  + @%s (from PR %s)\n", $wporg_username, implode( ', ', array_slice( $prs, 0, 3 ) ) );

			// Add as a new contributor with wporg_username.
			// We use wporg_username as github_username placeholder since we don't have the mapping.
			$contributors[] = array(
				'github_username'         => $wporg_username,
				'wporg_username'          => $wporg_username,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'review' ), // Assume review since props-bot tracks PR activity.
				'first_contribution_date' => gmdate( 'Y-m-d' ),
			);
			$added[]        = $wporg_username;
		}

		return array(
			'contributors' => $contributors,
			'added'        => $added,
		);
	}

	/**
	 * Fetch WordPress.org usernames from props-bot comments on merged PRs
	 *
	 * Uses incremental processing based on last_validated_merge_date.
	 * PRs are fetched sorted by update time (most recent first), so we can
	 * stop early when we reach PRs we've already processed.
	 *
	 * @return array Map of wporg_username => array of PR numbers where they were mentioned.
	 */
	private function fetch_props_bot_usernames() {
		$usernames     = array();
		$page          = 1;
		$per_page      = 100;
		$prs_processed = 0;
		$reached_old   = false;

		echo "Fetching merged PRs with props-bot comments...\n";

		do {
			// Fetch merged PRs sorted by updated (most recent first).
			$url      = sprintf(
				'https://api.github.com/repos/%s/%s/pulls?state=closed&sort=updated&direction=desc&per_page=%d&page=%d',
				GITHUB_OWNER,
				GITHUB_REPO,
				$per_page,
				$page
			);
			$response = $this->make_rest_request( $url );

			if ( empty( $response ) || ! is_array( $response ) ) {
				break;
			}

			foreach ( $response as $pr ) {
				// Only process merged PRs.
				if ( empty( $pr['merged_at'] ) ) {
					continue;
				}

				$merged_at = $pr['merged_at'];
				$pr_number = $pr['number'];

				// Track the newest merge date we see (for saving to metadata).
				if ( null === $this->new_validated_merge_date || $merged_at > $this->new_validated_merge_date ) {
					$this->new_validated_merge_date = $merged_at;
				}

				// In incremental mode, skip PRs merged before our last validated date.
				if ( $this->last_validated_merge_date && $merged_at <= $this->last_validated_merge_date ) {
					$reached_old = true;
					continue;
				}

				++$prs_processed;

				// Fetch comments for this PR.
				$comments_url = sprintf(
					'https://api.github.com/repos/%s/%s/issues/%d/comments',
					GITHUB_OWNER,
					GITHUB_REPO,
					$pr_number
				);
				$comments     = $this->make_rest_request( $comments_url );

				if ( empty( $comments ) || ! is_array( $comments ) ) {
					continue;
				}

				// Look for props-bot comments.
				foreach ( $comments as $comment ) {
					if ( 'github-actions[bot]' !== ( $comment['user']['login'] ?? '' ) ) {
						continue;
					}

					// Parse props line from comment body.
					$parsed = $this->parse_props_from_comment( $comment['body'] ?? '' );
					foreach ( $parsed as $username ) {
						if ( ! isset( $usernames[ $username ] ) ) {
							$usernames[ $username ] = array();
						}
						if ( ! in_array( $pr_number, $usernames[ $username ], true ) ) {
							$usernames[ $username ][] = $pr_number;
						}
					}
				}
			}

			$response_count = count( $response );
			++$page;

			// In incremental mode, stop if we've reached old PRs.
			// In full mode, limit to 10 pages (1000 PRs) for safety.
			if ( $reached_old || ( ! $this->last_validated_merge_date && $page > 10 ) ) {
				break;
			}
		} while ( $response_count === $per_page );

		if ( $prs_processed > 0 ) {
			printf( "Processed %d merged PRs.\n", $prs_processed );
		}

		return $usernames;
	}

	/**
	 * Parse WordPress.org usernames from a props-bot comment
	 *
	 * Props-bot comments contain a line like:
	 * Props username1, username2, username3.
	 *
	 * @param string $body Comment body.
	 * @return array List of usernames found.
	 */
	private function parse_props_from_comment( string $body ) {
		$usernames = array();

		// Look for "Props username1, username2." pattern.
		if ( preg_match( '/^Props\s+([^.]+)\./m', $body, $matches ) ) {
			$props_line = $matches[1];
			// Split by comma and clean up.
			$parts = explode( ',', $props_line );
			foreach ( $parts as $part ) {
				$username = trim( $part );
				// Remove any @ prefix if present.
				$username = ltrim( $username, '@' );
				if ( ! empty( $username ) ) {
					$usernames[] = $username;
				}
			}
		}

		return $usernames;
	}

	/**
	 * Parse command-line arguments
	 *
	 * @param array $args Command-line arguments.
	 * @return array Parsed options.
	 */
	public static function parse_arguments( array $args ) {
		$options = array(
			'full'        => false,
			'dry_run'     => false,
			'skip_wporg'  => false,
			'skip_output' => false,
			'validate'    => false,
			'help'        => false,
		);

		foreach ( $args as $arg ) {
			if ( '--full' === $arg ) {
				$options['full'] = true;
			} elseif ( '--dry-run' === $arg ) {
				$options['dry_run'] = true;
			} elseif ( '--skip-wporg' === $arg ) {
				$options['skip_wporg'] = true;
			} elseif ( '--skip-output' === $arg ) {
				$options['skip_output'] = true;
			} elseif ( '--validate' === $arg ) {
				$options['validate'] = true;
			} elseif ( '--help' === $arg || '-h' === $arg ) {
				$options['help'] = true;
			}
		}

		return $options;
	}

	/**
	 * Display help message
	 */
	public static function display_help() {
		echo <<<'HELP'
Backfill historical contributors from GitHub API

Usage: php bin/backfill-contributors.php [options]

Options:
  --full         Force full backfill (ignore cursor, fetch all historical data)
  --validate     Validate against props-bot comments and add missing contributors
  --dry-run      Preview changes without saving to contributors.json
  --skip-wporg   Skip WordPress.org profile lookup
  --skip-output  Skip output file generation (readme.txt, CONTRIBUTORS.md, docs page)
  --help, -h     Display this help message

Environment variables:
  GITHUB_TOKEN  GitHub API token for authentication (required)

Incremental Processing:
  By default, the script runs in incremental mode. After the first run, it saves
  a cursor that tracks the last processed PR. Subsequent runs only fetch PRs
  merged after that point, significantly reducing API calls and processing time.

  Use --full to force a complete backfill of all historical data.

Props-bot Validation:
  Use --validate to cross-check against props-bot comments on merged PRs.
  This ensures any WordPress.org usernames mentioned in props-bot comments
  are included in the contributor list. Missing contributors are automatically added.

This script:
  1. Fetches commit authors from GitHub REST API
  2. Fetches reviewers, commenters, and issue reporters from GitHub GraphQL API
  3. Filters out bot accounts
  4. Merges with existing contributors.json data
  5. Looks up WordPress.org profiles for linked accounts
  6. Saves the updated contributor list with metadata for incremental processing
  7. Generates output files (readme.txt, CONTRIBUTORS.md, docs/contributing/contributors.md)
  8. (Optional) Validates against props-bot comments when --validate is used

HELP;
	}
}

// Main execution.
$options = Contributor_Backfill::parse_arguments( array_slice( $argv, 1 ) );

if ( $options['help'] ) {
	Contributor_Backfill::display_help();
	exit( 0 );
}

// Check for GITHUB_TOKEN.
$github_token = getenv( 'GITHUB_TOKEN' );
if ( ! $github_token ) {
	echo "Error: GITHUB_TOKEN environment variable is required.\n";
	echo "Set it with: export GITHUB_TOKEN=your_token_here\n";
	exit( 1 );
}

// Run the backfill.
$backfill = new Contributor_Backfill(
	$github_token,
	$options['full'],
	$options['dry_run'],
	$options['skip_wporg'],
	$options['skip_output'],
	$options['validate']
);
$backfill->run();
