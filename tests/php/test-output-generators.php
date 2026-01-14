<?php
/**
 * Tests for output file generators
 *
 * Tests for generating readme.txt Contributors field,
 * CONTRIBUTORS.md, and docs/contributing/contributors.md.
 *
 * @package wordpress/secure-custom-fields
 */

use WorDBless\BaseTestCase;

// Load the contributors functions.
require_once dirname( dirname( __DIR__ ) ) . '/bin/contributors-functions.php';

use function WordPress\SCF\Contributors\generate_readme_contributors_field;
use function WordPress\SCF\Contributors\generate_contributors_md;
use function WordPress\SCF\Contributors\generate_docs_contributors_md;
use function WordPress\SCF\Contributors\get_linked_contributors;

/**
 * Test output file generators for contributor acknowledgement system
 */
class Test_Output_Generators extends BaseTestCase {

	/**
	 * Sample contributors for testing
	 *
	 * @var array
	 */
	private $sample_contributors;

	/**
	 * Setup test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		$this->sample_contributors = array(
			array(
				'github_username'         => 'alice',
				'wporg_username'          => 'alicewp',
				'wporg_display_name'      => 'Alice Developer',
				'contribution_types'      => array( 'commit', 'review' ),
				'first_contribution_date' => '2024-01-01',
			),
			array(
				'github_username'         => 'bob',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'issue' ),
				'first_contribution_date' => '2024-02-15',
			),
			array(
				'github_username'         => 'charlie',
				'wporg_username'          => 'charliewp',
				'wporg_display_name'      => 'Charlie Smith',
				'contribution_types'      => array( 'comment', 'review' ),
				'first_contribution_date' => '2024-03-20',
			),
			array(
				'github_username'         => 'diana',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-04-10',
			),
		);
	}

	/**
	 * Test readme.txt Contributors field formatting
	 *
	 * Validates that the Contributors field is generated as a
	 * comma-separated list of WordPress.org usernames.
	 */
	public function test_readme_contributors_field_formatting() {
		$contributors_field = generate_readme_contributors_field( $this->sample_contributors );

		// Should be comma-separated WordPress.org usernames.
		$this->assertIsString( $contributors_field, 'Contributors field should be a string' );

		// Should only contain linked accounts (alicewp, charliewp).
		$this->assertStringContainsString( 'alicewp', $contributors_field, 'Should contain alicewp' );
		$this->assertStringContainsString( 'charliewp', $contributors_field, 'Should contain charliewp' );

		// Should NOT contain unlinked GitHub usernames.
		$this->assertStringNotContainsString( 'bob', $contributors_field, 'Should not contain unlinked bob' );
		$this->assertStringNotContainsString( 'diana', $contributors_field, 'Should not contain unlinked diana' );

		// Should be comma-separated with space.
		$this->assertMatchesRegularExpression( '/^[\w]+(?:, [\w]+)*$/', $contributors_field, 'Should be comma-separated' );

		// Test with empty contributor list - always includes wordpressdotorg first.
		$empty_result = generate_readme_contributors_field( array() );
		$this->assertEquals( 'wordpressdotorg', $empty_result, 'Empty contributors should produce wordpressdotorg' );

		// Test with all unlinked contributors - still includes wordpressdotorg.
		$unlinked_only   = array(
			array(
				'github_username'         => 'unlinked1',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-01',
			),
		);
		$unlinked_result = generate_readme_contributors_field( $unlinked_only );
		$this->assertEquals( 'wordpressdotorg', $unlinked_result, 'All unlinked should produce wordpressdotorg' );
	}

	/**
	 * Test CONTRIBUTORS.md markdown table generation
	 *
	 * Validates that CONTRIBUTORS.md is generated with proper markdown
	 * table format including all contributors (linked and unlinked).
	 */
	public function test_contributors_md_markdown_table_generation() {
		$contributors_md = generate_contributors_md( $this->sample_contributors );

		// Should contain header/introductory text.
		$this->assertStringContainsString( '# Contributors', $contributors_md, 'Should have main heading' );
		$this->assertStringContainsString( 'acknowledgement', strtolower( $contributors_md ), 'Should mention acknowledgement system' );

		// Should contain markdown table with proper headers.
		$this->assertStringContainsString( '| GitHub Username', $contributors_md, 'Should have GitHub Username column' );
		$this->assertStringContainsString( 'WordPress.org Username', $contributors_md, 'Should have WordPress.org Username column' );
		$this->assertStringContainsString( 'Display Name', $contributors_md, 'Should have Display Name column' );

		// Should contain table separator row.
		$this->assertMatchesRegularExpression( '/\|[\s-]+\|/', $contributors_md, 'Should have table separator row' );

		// Should include ALL contributors (linked and unlinked).
		$this->assertStringContainsString( 'alice', $contributors_md, 'Should contain alice' );
		$this->assertStringContainsString( 'bob', $contributors_md, 'Should contain bob (unlinked)' );
		$this->assertStringContainsString( 'charlie', $contributors_md, 'Should contain charlie' );
		$this->assertStringContainsString( 'diana', $contributors_md, 'Should contain diana (unlinked)' );

		// GitHub usernames should be linked.
		$this->assertStringContainsString( '[@alice](https://github.com/alice)', $contributors_md, 'GitHub username should be linked' );

		// WordPress.org usernames should be linked when present.
		$this->assertStringContainsString( '[@alicewp](https://profiles.wordpress.org/alicewp)', $contributors_md, 'WPorg username should be linked' );

		// Display name should be shown when available.
		$this->assertStringContainsString( 'Alice Developer', $contributors_md, 'Display name should be shown' );

		// Should be sorted by wporg username first (blanks at end), then by github username.
		// Linked users (alicewp, charliewp) come first, then unlinked (bob, diana).
		$alice_pos   = strpos( $contributors_md, '[@alice]' );
		$bob_pos     = strpos( $contributors_md, '[@bob]' );
		$charlie_pos = strpos( $contributors_md, '[@charlie]' );
		$diana_pos   = strpos( $contributors_md, '[@diana]' );

		// Linked users come before unlinked users.
		$this->assertLessThan( $bob_pos, $alice_pos, 'alice (linked) should come before bob (unlinked)' );
		$this->assertLessThan( $bob_pos, $charlie_pos, 'charlie (linked) should come before bob (unlinked)' );
		$this->assertLessThan( $diana_pos, $alice_pos, 'alice (linked) should come before diana (unlinked)' );
		$this->assertLessThan( $diana_pos, $charlie_pos, 'charlie (linked) should come before diana (unlinked)' );
	}

	/**
	 * Test docs/contributing/contributors.md generation
	 *
	 * Validates that the docs page is generated with proper format
	 * for developer.wordpress.org docs site style.
	 */
	public function test_docs_contributors_md_generation() {
		$docs_md = generate_docs_contributors_md( $this->sample_contributors );

		// Should have main heading for docs site.
		$this->assertStringContainsString( '# Contributors', $docs_md, 'Should have main heading' );

		// Should have introductory text.
		$this->assertStringContainsString( 'Secure Custom Fields', $docs_md, 'Should mention Secure Custom Fields' );

		// Should contain contributor table.
		$this->assertStringContainsString( '| GitHub Username', $docs_md, 'Should have GitHub Username column' );

		// Should include all contributors.
		$this->assertStringContainsString( 'alice', $docs_md, 'Should contain alice' );
		$this->assertStringContainsString( 'bob', $docs_md, 'Should contain bob' );

		// Should be formatted as proper docs page.
		$lines      = explode( "\n", $docs_md );
		$first_line = trim( $lines[0] );
		$this->assertEquals( '# Contributors', $first_line, 'First line should be the title' );

		// Should not have duplicate headers or malformed structure.
		$header_count = substr_count( $docs_md, '# Contributors' );
		$this->assertEquals( 1, $header_count, 'Should have exactly one main heading' );
	}

	/**
	 * Test filtering logic - readme.txt only includes linked accounts
	 *
	 * Validates that the filtering function correctly identifies
	 * contributors with linked WordPress.org accounts.
	 */
	public function test_filtering_logic_readme_only_linked_accounts() {
		$linked = get_linked_contributors( $this->sample_contributors );

		// Should return only contributors with wporg_username set.
		$this->assertCount( 2, $linked, 'Should have 2 linked contributors' );

		// Get GitHub usernames of linked contributors.
		$linked_usernames = array_column( $linked, 'github_username' );

		$this->assertContains( 'alice', $linked_usernames, 'alice should be in linked list' );
		$this->assertContains( 'charlie', $linked_usernames, 'charlie should be in linked list' );
		$this->assertNotContains( 'bob', $linked_usernames, 'bob should not be in linked list' );
		$this->assertNotContains( 'diana', $linked_usernames, 'diana should not be in linked list' );

		// All returned contributors should have wporg_username.
		foreach ( $linked as $contributor ) {
			$this->assertNotNull( $contributor['wporg_username'], 'Linked contributor should have wporg_username' );
			$this->assertNotEmpty( $contributor['wporg_username'], 'Linked contributor wporg_username should not be empty' );
		}

		// Test with empty input.
		$empty_result = get_linked_contributors( array() );
		$this->assertIsArray( $empty_result, 'Should return array for empty input' );
		$this->assertEmpty( $empty_result, 'Should return empty array for empty input' );

		// Test with all unlinked.
		$all_unlinked = array(
			array(
				'github_username'         => 'unlinked1',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-01',
			),
			array(
				'github_username'         => 'unlinked2',
				'wporg_username'          => null,
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'review' ),
				'first_contribution_date' => '2024-02-01',
			),
		);
		$no_linked    = get_linked_contributors( $all_unlinked );
		$this->assertEmpty( $no_linked, 'Should return empty array when no contributors are linked' );

		// Test with empty string wporg_username (should be treated as unlinked).
		$empty_string_wporg  = array(
			array(
				'github_username'         => 'emptystring',
				'wporg_username'          => '',
				'wporg_display_name'      => null,
				'contribution_types'      => array( 'commit' ),
				'first_contribution_date' => '2024-01-01',
			),
		);
		$empty_string_result = get_linked_contributors( $empty_string_wporg );
		$this->assertEmpty( $empty_string_result, 'Empty string wporg_username should be treated as unlinked' );
	}
}
