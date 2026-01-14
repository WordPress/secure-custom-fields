<?php
/**
 * Tests for GitHub API integration
 *
 * Tests the parsing functions in contributors-functions.php that are used
 * by the backfill-contributors.php script to process GitHub API responses.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the contributors functions.
require_once dirname( dirname( __DIR__ ) ) . '/bin/contributors-functions.php';

use function WordPress\SCF\Contributors\parse_rest_api_contributors;
use function WordPress\SCF\Contributors\parse_graphql_pr_data;
use function WordPress\SCF\Contributors\filter_bot_accounts;
use function WordPress\SCF\Contributors\merge_contribution_sources;
use function WordPress\SCF\Contributors\find_contributor_by_username;

/**
 * Test GitHub API integration for contributor backfill
 */
class Test_GitHub_API_Integration extends BaseTestCase {

	/**
	 * Test GitHub REST API contributor parsing
	 *
	 * Validates that the REST API response for contributors is parsed correctly,
	 * extracting github_username and assigning commit contribution type.
	 */
	public function test_github_rest_api_contributor_parsing() {
		// Sample GitHub REST API response for /repos/{owner}/{repo}/contributors
		$api_response = array(
			array(
				'login'         => 'developer1',
				'id'            => 12345,
				'type'          => 'User',
				'contributions' => 50,
			),
			array(
				'login'         => 'developer2',
				'id'            => 67890,
				'type'          => 'User',
				'contributions' => 25,
			),
		);

		$contributors = parse_rest_api_contributors( $api_response );

		$this->assertCount( 2, $contributors, 'Should parse 2 contributors' );
		$this->assertEquals( 'developer1', $contributors[0]['github_username'], 'First contributor username should match' );
		$this->assertEquals( 'developer2', $contributors[1]['github_username'], 'Second contributor username should match' );
		$this->assertContains( 'commit', $contributors[0]['contribution_types'], 'Should have commit contribution type' );
		$this->assertContains( 'commit', $contributors[1]['contribution_types'], 'Should have commit contribution type' );

		// Verify contribution counts are included.
		$this->assertEquals( 50, $contributors[0]['contribution_counts']['commit'], 'First contributor should have 50 commits' );
		$this->assertEquals( 25, $contributors[1]['contribution_counts']['commit'], 'Second contributor should have 25 commits' );
	}

	/**
	 * Test GitHub GraphQL query for PR data
	 *
	 * Validates that GraphQL response for merged PRs is parsed correctly,
	 * extracting reviewers, commenters, and issue reporters.
	 */
	public function test_github_graphql_query_for_pr_data() {
		// Sample GraphQL response structure for merged PRs.
		$graphql_response = array(
			'data' => array(
				'repository' => array(
					'pullRequests' => array(
						'pageInfo' => array(
							'hasNextPage' => false,
							'endCursor'   => null,
						),
						'nodes'    => array(
							array(
								'number'                  => 123,
								'mergedAt'                => '2024-06-15T10:30:00Z',
								'author'                  => array( 'login' => 'pr_author' ),
								'reviews'                 => array(
									'nodes' => array(
										array( 'author' => array( 'login' => 'reviewer1' ) ),
										array( 'author' => array( 'login' => 'reviewer2' ) ),
									),
								),
								'comments'                => array(
									'nodes' => array(
										array( 'author' => array( 'login' => 'commenter1' ) ),
										array( 'author' => array( 'login' => 'pr_author' ) ),
									),
								),
								'closingIssuesReferences' => array(
									'nodes' => array(
										array( 'author' => array( 'login' => 'issue_reporter' ) ),
									),
								),
							),
						),
					),
				),
			),
		);

		$contributors = parse_graphql_pr_data( $graphql_response );

		// Should find unique contributors with correct contribution types.
		$usernames = array_column( $contributors, 'github_username' );

		$this->assertContains( 'reviewer1', $usernames, 'Should include reviewer1' );
		$this->assertContains( 'reviewer2', $usernames, 'Should include reviewer2' );
		$this->assertContains( 'commenter1', $usernames, 'Should include commenter1' );
		$this->assertContains( 'issue_reporter', $usernames, 'Should include issue_reporter' );

		// Verify contribution types.
		$reviewer1 = find_contributor_by_username( $contributors, 'reviewer1' );
		$this->assertContains( 'review', $reviewer1['contribution_types'], 'Reviewer should have review type' );

		$commenter1 = find_contributor_by_username( $contributors, 'commenter1' );
		$this->assertContains( 'comment', $commenter1['contribution_types'], 'Commenter should have comment type' );

		$issue_reporter = find_contributor_by_username( $contributors, 'issue_reporter' );
		$this->assertContains( 'issue', $issue_reporter['contribution_types'], 'Issue reporter should have issue type' );
	}

	/**
	 * Test bot account filtering
	 *
	 * Validates that bot accounts (ending in [bot]) and those in the exclusion list
	 * are filtered out from contributor lists.
	 */
	public function test_bot_account_filtering() {
		$contributors = array(
			array(
				'github_username'         => 'developer1',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-01',
			),
			array(
				'github_username'         => 'dependabot[bot]',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-02',
			),
			array(
				'github_username'         => 'github-actions[bot]',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-03',
			),
			array(
				'github_username'         => 'renovate[bot]',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-04',
			),
			array(
				'github_username'         => 'developer2',
				'contribution_types'      => array( 'review' ),
				'first_contribution_date' => '2024-01-05',
			),
		);

		$exclusion_list = array( 'some-service-account' );
		$filtered       = filter_bot_accounts( $contributors, $exclusion_list );

		$this->assertCount( 2, $filtered, 'Should only have 2 human contributors after filtering' );
		$usernames = array_column( $filtered, 'github_username' );
		$this->assertContains( 'developer1', $usernames, 'developer1 should remain' );
		$this->assertContains( 'developer2', $usernames, 'developer2 should remain' );
		$this->assertNotContains( 'dependabot[bot]', $usernames, 'dependabot[bot] should be filtered' );
		$this->assertNotContains( 'github-actions[bot]', $usernames, 'github-actions[bot] should be filtered' );
		$this->assertNotContains( 'renovate[bot]', $usernames, 'renovate[bot] should be filtered' );

		// Test with exclusion list.
		$contributors_with_service = array(
			array(
				'github_username'         => 'some-service-account',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-01',
			),
			array(
				'github_username'         => 'real-developer',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-02',
			),
		);

		$filtered_with_exclusion = filter_bot_accounts( $contributors_with_service, $exclusion_list );
		$this->assertCount( 1, $filtered_with_exclusion, 'Exclusion list account should be filtered' );
		$this->assertEquals( 'real-developer', $filtered_with_exclusion[0]['github_username'], 'Only real developer should remain' );
	}

	/**
	 * Test contribution type assignment from API response
	 *
	 * Validates that contribution types are correctly assigned based on
	 * where the contributor was found (commits, reviews, comments, issues).
	 */
	public function test_contribution_type_assignment_from_api_response() {
		// Simulate a contributor found in multiple contexts.
		$commit_contributors = array(
			array(
				'login'         => 'multi-contributor',
				'contributions' => 10,
			),
		);

		$review_contributors = array(
			array( 'login' => 'multi-contributor' ),
			array( 'login' => 'review-only' ),
		);

		$comment_contributors = array(
			array( 'login' => 'multi-contributor' ),
			array( 'login' => 'comment-only' ),
		);

		$issue_contributors = array(
			array( 'login' => 'issue-only' ),
		);

		$contributors = merge_contribution_sources(
			$commit_contributors,
			$review_contributors,
			$comment_contributors,
			$issue_contributors,
			'2024-01-01'
		);

		// Find multi-contributor - should have all applicable types.
		$multi = find_contributor_by_username( $contributors, 'multi-contributor' );
		$this->assertNotNull( $multi, 'multi-contributor should exist' );
		$this->assertContains( 'commit', $multi['contribution_types'], 'Should have commit type' );
		$this->assertContains( 'review', $multi['contribution_types'], 'Should have review type' );
		$this->assertContains( 'comment', $multi['contribution_types'], 'Should have comment type' );

		// Find single-type contributors.
		$review_only = find_contributor_by_username( $contributors, 'review-only' );
		$this->assertNotNull( $review_only, 'review-only should exist' );
		$this->assertContains( 'review', $review_only['contribution_types'], 'Should have review type' );
		$this->assertNotContains( 'commit', $review_only['contribution_types'], 'Should not have commit type' );

		$comment_only = find_contributor_by_username( $contributors, 'comment-only' );
		$this->assertNotNull( $comment_only, 'comment-only should exist' );
		$this->assertContains( 'comment', $comment_only['contribution_types'], 'Should have comment type' );

		$issue_only = find_contributor_by_username( $contributors, 'issue-only' );
		$this->assertNotNull( $issue_only, 'issue-only should exist' );
		$this->assertContains( 'issue', $issue_only['contribution_types'], 'Should have issue type' );
	}

	/**
	 * Test default bot exclusion list filtering
	 *
	 * Validates that the default BOT_EXCLUSION_LIST constant is used
	 * when no exclusion list is provided.
	 */
	public function test_default_bot_exclusion_list() {
		$contributors = array(
			array(
				'github_username'         => 'web-flow',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-01',
			),
			array(
				'github_username'         => 'github-actions',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-02',
			),
			array(
				'github_username'         => 'real-developer',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-03',
			),
		);

		// Use default exclusion list (empty array triggers default).
		$filtered = filter_bot_accounts( $contributors );

		$usernames = array_column( $filtered, 'github_username' );
		$this->assertNotContains( 'web-flow', $usernames, 'web-flow should be filtered by default' );
		$this->assertNotContains( 'github-actions', $usernames, 'github-actions should be filtered by default' );
		$this->assertContains( 'real-developer', $usernames, 'real-developer should remain' );
	}

	/**
	 * Test find_contributor_by_username function
	 *
	 * Validates that the helper function correctly finds contributors
	 * by username (case-insensitive).
	 */
	public function test_find_contributor_by_username() {
		$contributors = array(
			array(
				'github_username'         => 'UserOne',
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-01',
			),
			array(
				'github_username'         => 'USERTWO',
				'contribution_types'      => array( 'review' ),
				'first_contribution_date' => '2024-01-02',
			),
		);

		// Test exact match.
		$result = find_contributor_by_username( $contributors, 'UserOne' );
		$this->assertNotNull( $result, 'Should find UserOne' );
		$this->assertEquals( 'UserOne', $result['github_username'] );

		// Test case-insensitive match.
		$result = find_contributor_by_username( $contributors, 'userone' );
		$this->assertNotNull( $result, 'Should find userone (case-insensitive)' );
		$this->assertEquals( 'UserOne', $result['github_username'] );

		$result = find_contributor_by_username( $contributors, 'usertwo' );
		$this->assertNotNull( $result, 'Should find usertwo (case-insensitive)' );
		$this->assertEquals( 'USERTWO', $result['github_username'] );

		// Test non-existent user.
		$result = find_contributor_by_username( $contributors, 'nonexistent' );
		$this->assertNull( $result, 'Should return null for nonexistent user' );
	}

	/**
	 * Test GraphQL parsing with contribution counts
	 *
	 * Validates that contribution counts are tracked when parsing GraphQL data.
	 */
	public function test_graphql_parsing_tracks_contribution_counts() {
		// Create a response with the same reviewer reviewing multiple PRs.
		$graphql_response = array(
			'data' => array(
				'repository' => array(
					'pullRequests' => array(
						'pageInfo' => array(
							'hasNextPage' => false,
							'endCursor'   => null,
						),
						'nodes'    => array(
							array(
								'number'                  => 1,
								'mergedAt'                => '2024-06-15T10:30:00Z',
								'author'                  => array( 'login' => 'author' ),
								'reviews'                 => array(
									'nodes' => array(
										array( 'author' => array( 'login' => 'frequent_reviewer' ) ),
									),
								),
								'comments'                => array( 'nodes' => array() ),
								'closingIssuesReferences' => array( 'nodes' => array() ),
							),
							array(
								'number'                  => 2,
								'mergedAt'                => '2024-06-16T10:30:00Z',
								'author'                  => array( 'login' => 'author' ),
								'reviews'                 => array(
									'nodes' => array(
										array( 'author' => array( 'login' => 'frequent_reviewer' ) ),
									),
								),
								'comments'                => array( 'nodes' => array() ),
								'closingIssuesReferences' => array( 'nodes' => array() ),
							),
							array(
								'number'                  => 3,
								'mergedAt'                => '2024-06-17T10:30:00Z',
								'author'                  => array( 'login' => 'author' ),
								'reviews'                 => array(
									'nodes' => array(
										array( 'author' => array( 'login' => 'frequent_reviewer' ) ),
									),
								),
								'comments'                => array( 'nodes' => array() ),
								'closingIssuesReferences' => array( 'nodes' => array() ),
							),
						),
					),
				),
			),
		);

		$contributors = parse_graphql_pr_data( $graphql_response );
		$reviewer     = find_contributor_by_username( $contributors, 'frequent_reviewer' );

		$this->assertNotNull( $reviewer, 'frequent_reviewer should exist' );
		$this->assertArrayHasKey( 'contribution_counts', $reviewer, 'Should have contribution_counts' );
		$this->assertEquals( 3, $reviewer['contribution_counts']['review'], 'Should have 3 reviews counted' );
	}
}
